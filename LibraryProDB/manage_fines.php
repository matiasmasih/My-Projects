<?php
// manage_fines.php — Librarian: view / collect / waive member fines
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('manage_fines');
if (my_access('manage_fines') !== 'full') {
    header('Location: index.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Manage Fines';
$action     = $_GET['action'] ?? 'list';
$error      = '';
$success    = '';

// ============================================================
// MARK PAID
// ============================================================
if ($action === 'pay' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $fine_id = (int)($_POST['fine_id'] ?? 0);
    $method  = $_POST['method'] ?? 'cash';
    $notes   = trim($_POST['notes'] ?? '');

    if (!in_array($method, ['cash', 'card', 'bank', 'online', 'other'], true)) {
        $error = 'Invalid payment method.';
    } else {
        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare("SELECT id, user_id, amount, paid, waived FROM fines WHERE id = ? FOR UPDATE");
            $st->execute([$fine_id]);
            $fine = $st->fetch();

            if (!$fine) {
                throw new Exception('Fine not found.');
            }
            if ((int)$fine['paid'] === 1) {
                throw new Exception('This fine is already marked as paid.');
            }
            if ((int)$fine['waived'] === 1) {
                throw new Exception('This fine has been waived — cannot also collect payment.');
            }

            // Record payment
            $pdo->prepare(
                "INSERT INTO payments (user_id, fine_id, amount, method, received_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([
                $fine['user_id'],
                $fine_id,
                $fine['amount'],
                $method,
                $_SESSION['user_id'] ?? null,
                $notes !== '' ? $notes : null
            ]);

            // Mark fine paid
            $pdo->prepare("UPDATE fines SET paid = 1, paid_date = CURDATE() WHERE id = ?")
                ->execute([$fine_id]);

            // Audit
            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'fine_paid', ?, 'fine', ?)")
                ->execute([
                    $_SESSION['user_id'] ?? null,
                    "Collected €" . number_format((float)$fine['amount'], 2) . " for fine #{$fine_id} via {$method}",
                    $fine_id
                ]);

            $pdo->commit();
            header('Location: manage_fines.php?saved=paid');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// ============================================================
// WAIVE
// ============================================================
if ($action === 'waive' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $fine_id = (int)($_POST['fine_id'] ?? 0);
    $reason  = trim($_POST['reason'] ?? '');

    if ($reason === '') {
        $error = 'A reason is required to waive a fine.';
    } else {
        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare("SELECT id, user_id, amount, paid, waived FROM fines WHERE id = ? FOR UPDATE");
            $st->execute([$fine_id]);
            $fine = $st->fetch();

            if (!$fine) {
                throw new Exception('Fine not found.');
            }
            if ((int)$fine['paid'] === 1) {
                throw new Exception('Fine is already paid — cannot waive.');
            }
            if ((int)$fine['waived'] === 1) {
                throw new Exception('Fine is already waived.');
            }

            $pdo->prepare(
                "UPDATE fines SET waived = 1, waived_by = ?, description = CONCAT(COALESCE(description,''), ?)
                 WHERE id = ?"
            )->execute([
                $_SESSION['user_id'] ?? null,
                " [WAIVED: {$reason}]",
                $fine_id
            ]);

            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'fine_waived', ?, 'fine', ?)")
                ->execute([
                    $_SESSION['user_id'] ?? null,
                    "Waived €" . number_format((float)$fine['amount'], 2) . " on fine #{$fine_id} — {$reason}",
                    $fine_id
                ]);

            $pdo->commit();
            header('Location: manage_fines.php?saved=waived');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

// ============================================================
// LOAD FORM DATA (for pay/waive modal forms)
// ============================================================
$active_fine = null;
if (in_array($action, ['pay', 'waive'], true)) {
    $fid = (int)($_GET['id'] ?? $_POST['fine_id'] ?? 0);
    $st  = $pdo->prepare(
        "SELECT f.*, u.username, u.first_name, u.last_name, u.card_number,
                b.title
         FROM fines f
         JOIN users u ON u.id = f.user_id
         LEFT JOIN borrowings br ON br.id = f.borrowing_id
         LEFT JOIN copies c ON c.id = br.copy_id
         LEFT JOIN books b ON b.id = c.book_id
         WHERE f.id = ?"
    );
    $st->execute([$fid]);
    $active_fine = $st->fetch();

    if (!$active_fine && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: manage_fines.php');
        exit;
    }
}

// ============================================================
// LIST DATA
// ============================================================
$tab    = $_GET['tab']    ?? 'unpaid';
$q      = trim($_GET['q'] ?? '');
$branch = (int)($_GET['branch'] ?? 0);

if (!in_array($tab, ['unpaid', 'paid', 'waived', 'all'], true)) {
    $tab = 'unpaid';
}

$where = [];
$args  = [];

if ($tab === 'unpaid') {
    $where[] = "f.paid = 0 AND f.waived = 0";
} elseif ($tab === 'paid') {
    $where[] = "f.paid = 1";
} elseif ($tab === 'waived') {
    $where[] = "f.waived = 1";
}

if ($q !== '') {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?
                 OR u.card_number LIKE ? OR f.description LIKE ?)";
    $like    = "%{$q}%";
    array_push($args, $like, $like, $like, $like, $like);
}

if ($branch > 0) {
    $where[] = "u.branch_id = ?";
    $args[]  = $branch;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$st = $pdo->prepare(
    "SELECT f.id, f.amount, f.reason, f.description, f.issued_date,
            f.paid, f.paid_date, f.waived,
            u.id AS user_id, u.username, u.first_name, u.last_name, u.card_number,
            b.name AS branch_name,
            br.id AS borrowing_id,
            bk.title AS book_title, c.barcode,
            w.first_name AS waived_first, w.last_name AS waived_last
     FROM fines f
     JOIN users u ON u.id = f.user_id
     LEFT JOIN branches b ON b.id = u.branch_id
     LEFT JOIN borrowings br ON br.id = f.borrowing_id
     LEFT JOIN copies c ON c.id = br.copy_id
     LEFT JOIN books bk ON bk.id = c.book_id
     LEFT JOIN users w ON w.id = f.waived_by
     $where_sql
     ORDER BY f.issued_date DESC, f.id DESC
     LIMIT 500"
);
$st->execute($args);
$fines = $st->fetchAll();

// ============================================================
// STATS (global)
// ============================================================
$stats = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN paid=0 AND waived=0 THEN amount ELSE 0 END),0) AS unpaid_total,
        COUNT(CASE WHEN paid=0 AND waived=0 THEN 1 END) AS unpaid_count,
        COALESCE(SUM(CASE WHEN paid=1 THEN amount ELSE 0 END),0) AS paid_total,
        COUNT(CASE WHEN paid=1 THEN 1 END) AS paid_count,
        COALESCE(SUM(CASE WHEN waived=1 THEN amount ELSE 0 END),0) AS waived_total,
        COUNT(CASE WHEN waived=1 THEN 1 END) AS waived_count
     FROM fines"
)->fetch();

$all_branches = $pdo->query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();

include 'header.php';
?>

<style>
    /* ===== 1. SIDEBAR FULL HEIGHT & SCROLL FIX ===== */
    .sidebar {
        height: 100vh;
        max-height: 100vh;
        overflow-y: auto !important;
        position: sticky;
        top: 0;
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }
    .sidebar-menu a { 
        padding: 8px 14px; 
        font-size: .85rem; 
        white-space: nowrap; 
    }
    .sidebar-section { 
        padding: 10px 25px 6px; 
        font-size: .72rem; 
        white-space: nowrap; 
    }

    /* Layout wrapper to ensure sidebar sits next to content properly */
    .app-container {
        display: flex;
        min-height: 100vh;
        width: 100%;
        overflow-x: hidden;
    }
    .main-content {
        flex: 1;
        min-width: 0;
        padding: 24px;
    }

    /* ===== 2. ONE LINE PER ITEM ===== */
    .nowrap-item, 
    .toolbar input, 
    .toolbar select,
    .tabs a,
    .badge,
    table.fines th, 
    table.fines td {
        white-space: nowrap !important;
    }

    .single-line-text {
        white-space: nowrap !important;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 250px;
        display: inline-block;
        vertical-align: middle;
    }

    /* ===== Toolbar ===== */
    .toolbar {
        display: flex; 
        gap: 10px; 
        margin-bottom: 18px; 
        align-items: center;
        background: var(--card-bg); 
        padding: 14px; 
        border-radius: 12px;
        box-shadow: var(--shadow); 
        border: 1px solid rgba(13,148,136,0.08);
        overflow-x: auto;
    }
    .toolbar input, .toolbar select {
        padding: 9px 12px; 
        border: 1px solid var(--border); 
        border-radius: 8px;
        background: var(--card-bg); 
        color: var(--text); 
        font-size: .9rem;
    }
    .toolbar input[type=text] { flex: 1; min-width: 220px; }

    /* ===== Stats ===== */
    .fine-stats {
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px; 
        margin-bottom: 22px;
    }
    .fstat {
        background: var(--card-bg); 
        border-radius: 14px; 
        padding: 18px;
        box-shadow: var(--shadow); 
        border: 1px solid rgba(13,148,136,0.08);
        text-align: center;
    }
    .fstat .v { font-size: 1.6rem; font-weight: 700; line-height: 1; }
    .fstat .l { font-size: .82rem; color: var(--text-muted); margin-top: 6px; }
    .fstat.danger .v { color: #dc2626; }
    .fstat.ok .v     { color: #059669; }
    .fstat.warn .v   { color: #d97706; }

    /* ===== Tabs ===== */
    .tabs { display: flex; gap: 6px; margin-bottom: 18px; border-bottom: 2px solid var(--border); overflow-x: auto; }
    .tabs a {
        padding: 10px 16px; 
        text-decoration: none; 
        color: var(--text-muted);
        font-weight: 600; 
        font-size: .88rem; 
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }
    .tabs a.active { color: var(--primary-dark); border-bottom-color: var(--primary); }
    .tabs a:hover { color: var(--primary); }

    /* ===== Badges ===== */
    .badge { display: inline-block; padding: 3px 9px; border-radius: 8px; font-size: .72rem; font-weight: 600; }
    .b-unpaid { background: #fee2e2; color: #991b1b; }
    .b-paid   { background: #d1fae5; color: #065f46; }
    .b-waived { background: #e0e7ff; color: #3730a3; }

    /* ===== 3. TABLE HORIZONTAL SCROLLBAR ===== */
    .table-card {
        background: var(--card-bg); 
        border-radius: 12px;
        box-shadow: var(--shadow); 
        border: 1px solid rgba(13,148,136,0.08);
        width: 100%;
        overflow: hidden;
    }
    .table-scroll {
        width: 100%;
        overflow-x: auto !important;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        display: block;
    }
    table.fines { 
        width: 100%; 
        border-collapse: collapse; 
        min-width: 1000px; /* Forces horizontal scrollbar when screen is narrower */
    }
    table.fines th, table.fines td {
        padding: 12px 14px; 
        vertical-align: middle;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        text-align: left;
    }
    table.fines thead th {
        background: var(--card-bg); 
        font-weight: 600; 
        font-size: .82rem;
        color: var(--text-muted); 
        text-transform: uppercase; 
        letter-spacing: .03em;
        border-bottom: 2px solid var(--border);
    }

    .row-actions { display: flex; gap: 4px; white-space: nowrap; }
    .row-actions a, .row-actions button {
        padding: 6px 10px; 
        border-radius: 6px; 
        font-size: .75rem; 
        font-weight: 600;
        text-decoration: none; 
        display: inline-flex; 
        align-items: center; 
        gap: 4px;
        border: none; 
        cursor: pointer;
    }
    .btn-pay    { background: #10b981; color: #fff; }
    .btn-pay:hover { background: #059669; }
    .btn-waive  { background: #d97706; color: #fff; }
    .btn-waive:hover { background: #b45309; }

    /* ===== Form Panel ===== */
    .form-panel {
        background: var(--card-bg); 
        border-radius: var(--radius); 
        padding: 22px;
        box-shadow: var(--shadow); 
        border: 1px solid rgba(13,148,136,0.08);
        max-width: 640px; 
        margin: 0 auto;
    }
    .form-panel h3 { margin: 0 0 18px; display: flex; align-items: center; gap: 8px; }
    .form-panel h3 i { color: var(--primary); }
    .form-panel label { display: block; font-weight: 600; font-size: .85rem; margin-bottom: 6px; margin-top: 12px; }
    .form-panel input[type=text], .form-panel select, .form-panel textarea {
        width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px;
        font-size: .92rem; background: var(--card-bg); color: var(--text); font-family: inherit;
    }
    .fine-summary {
        background: #f0fdfa; border-radius: 12px; padding: 16px;
        margin-bottom: 18px; border: 1px solid rgba(13,148,136,0.15);
    }
    .fine-summary .amt { font-size: 1.8rem; font-weight: 800; color: #0d9488; line-height: 1; }
</style>

<div class="main-content">

<?php if (isset($_GET['saved'])): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i>
        <?= $_GET['saved'] === 'paid' ? 'Payment recorded.' : 'Fine waived.' ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($action === 'pay' && $active_fine): ?>

    <!-- ============ PAY FORM ============ -->
    <div class="form-panel">
        <h3><i class="bi bi-cash-coin"></i> Collect Payment</h3>

        <div class="fine-summary">
            <div class="amt">€<?= number_format((float)$active_fine['amount'], 2) ?></div>
            <div style="margin-top:8px;color:var(--text-muted);font-size:.88rem;">
                <strong><?= htmlspecialchars($active_fine['first_name'] . ' ' . $active_fine['last_name']) ?></strong>
                (@<?= htmlspecialchars($active_fine['username']) ?>
                · <?= htmlspecialchars($active_fine['card_number'] ?? '—') ?>)<br>
                Reason: <?= htmlspecialchars($active_fine['reason']) ?>
                <?= $active_fine['title'] ? ' — ' . htmlspecialchars($active_fine['title']) : '' ?><br>
                Issued: <?= htmlspecialchars($active_fine['issued_date']) ?>
            </div>
        </div>

        <form method="POST" action="manage_fines.php?action=pay">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="fine_id" value="<?= (int)$active_fine['id'] ?>">

            <label>Payment Method *</label>
            <select name="method" required>
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="bank">Bank transfer</option>
                <option value="online">Online</option>
                <option value="other">Other</option>
            </select>

            <label>Notes (optional)</label>
            <textarea name="notes" rows="2" placeholder="e.g. receipt #1234"></textarea>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:22px;">
                <a href="manage_fines.php" class="btn-secondary-custom">← Cancel</a>
                <button type="submit" class="btn-add">
                    <i class="bi bi-check-circle"></i> Confirm Payment
                </button>
            </div>
        </form>
    </div>

<?php elseif ($action === 'waive' && $active_fine): ?>

    <!-- ============ WAIVE FORM ============ -->
    <div class="form-panel">
        <h3><i class="bi bi-x-octagon"></i> Waive Fine</h3>

        <div class="fine-summary" style="background:#fef2f2;border-color:#fecaca;">
            <div class="amt" style="color:#dc2626;">€<?= number_format((float)$active_fine['amount'], 2) ?></div>
            <div style="margin-top:8px;color:var(--text-muted);font-size:.88rem;">
                <strong><?= htmlspecialchars($active_fine['first_name'] . ' ' . $active_fine['last_name']) ?></strong><br>
                Reason: <?= htmlspecialchars($active_fine['reason']) ?>
            </div>
        </div>

        <form method="POST" action="manage_fines.php?action=waive">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="fine_id" value="<?= (int)$active_fine['id'] ?>">

            <label>Reason for waiving *</label>
            <textarea name="reason" rows="3" required placeholder="e.g. system error, goodwill, member dispute resolved"></textarea>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:22px;">
                <a href="manage_fines.php" class="btn-secondary-custom">← Cancel</a>
                <button type="submit" class="btn-add" style="background:#dc2626;">
                    <i class="bi bi-x-octagon"></i> Waive Fine
                </button>
            </div>
        </form>
    </div>

<?php else: ?>

    <!-- ============ LIST ============ -->
    <div style="margin-bottom:16px;">
        <h2 style="margin:0; white-space:nowrap;"><i class="bi bi-cash-coin" style="color:var(--primary);"></i> Manage Fines</h2>
    </div>

    <!-- Stats -->
    <div class="fine-stats">
        <div class="fstat danger">
            <div class="v">€<?= number_format((float)$stats['unpaid_total'],2) ?></div>
            <div class="l">Unpaid (<?= (int)$stats['unpaid_count'] ?>)</div>
        </div>
        <div class="fstat ok">
            <div class="v">€<?= number_format((float)$stats['paid_total'],2) ?></div>
            <div class="l">Collected (<?= (int)$stats['paid_count'] ?>)</div>
        </div>
        <div class="fstat warn">
            <div class="v">€<?= number_format((float)$stats['waived_total'],2) ?></div>
            <div class="l">Waived (<?= (int)$stats['waived_count'] ?>)</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <a href="manage_fines.php?tab=unpaid" class="<?= $tab==='unpaid'?'active':'' ?>">
            <i class="bi bi-exclamation-circle"></i> Unpaid (<?= (int)$stats['unpaid_count'] ?>)
        </a>
        <a href="manage_fines.php?tab=paid" class="<?= $tab==='paid'?'active':'' ?>">
            <i class="bi bi-check-circle"></i> Collected (<?= (int)$stats['paid_count'] ?>)
        </a>
        <a href="manage_fines.php?tab=waived" class="<?= $tab==='waived'?'active':'' ?>">
            <i class="bi bi-x-octagon"></i> Waived (<?= (int)$stats['waived_count'] ?>)
        </a>
        <a href="manage_fines.php?tab=all" class="<?= $tab==='all'?'active':'' ?>">
            <i class="bi bi-list"></i> All
        </a>
    </div>

    <!-- Toolbar -->
    <form class="toolbar" method="get" action="manage_fines.php">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <input type="text" name="q" placeholder="Search member, card, description…" value="<?= htmlspecialchars($q) ?>">
        <select name="branch">
            <option value="0">All branches</option>
            <?php foreach ($all_branches as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= $branch===(int)$b['id']?'selected':'' ?>>
                    <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-add" style="white-space:nowrap;"><i class="bi bi-search"></i> Filter</button>
        <a href="manage_fines.php?tab=<?= htmlspecialchars($tab) ?>" class="btn-secondary-custom" style="white-space:nowrap;">Reset</a>
    </form>

    <!-- Table -->
    <?php if (!$fines): ?>
        <div class="table-card" style="text-align:center;padding:50px;">
            <i class="bi bi-cash-coin" style="font-size:3rem;color:var(--text-muted);"></i>
            <p style="color:var(--text-muted);margin-top:12px;">
                <?= $tab === 'unpaid' ? 'No unpaid fines. 🎉' : 'No fines in this view.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="table-card">
            <div class="table-scroll">
                <table class="fines">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Card #</th>
                            <th>Reason</th>
                            <th>Description</th>
                            <th>Book</th>
                            <th>Issued</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($fines as $f):
                        $reason_cls = 'b-' . htmlspecialchars($f['reason']);
                    ?>
                        <tr>
                            <td>
                                <a href="manage_members.php?action=edit&id=<?= (int)$f['user_id'] ?>"
                                   style="color:var(--primary-dark);text-decoration:none;font-weight:600;" class="nowrap-item">
                                    <?= htmlspecialchars($f['first_name'] . ' ' . $f['last_name']) ?>
                                </a>
                                <br><small style="color:var(--text-muted);" class="nowrap-item">@<?= htmlspecialchars($f['username']) ?></small>
                            </td>
                            <td><code style="font-size:.78rem;" class="nowrap-item"><?= htmlspecialchars($f['card_number'] ?? '—') ?></code></td>
                            <td><span class="badge <?= $reason_cls ?>"><?= htmlspecialchars($f['reason']) ?></span></td>
                            <td>
                                <span class="single-line-text" title="<?= htmlspecialchars($f['description'] ?? '—') ?>">
                                    <?= htmlspecialchars($f['description'] ?? '—') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($f['book_title']): ?>
                                    <span class="single-line-text" title="<?= htmlspecialchars($f['book_title']) ?>">
                                        <?= htmlspecialchars($f['book_title']) ?>
                                    </span>
                                    <br><small style="color:var(--text-muted);"><code class="nowrap-item"><?= htmlspecialchars($f['barcode'] ?? '') ?></code></small>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="nowrap-item"><?= htmlspecialchars($f['issued_date']) ?></td>
                            <td style="font-weight:700;" class="nowrap-item">€<?= number_format((float)$f['amount'],2) ?></td>
                            <td>
                                <?php if ((int)$f['waived'] === 1): ?>
                                    <span class="badge b-waived">waived</span>
                                <?php elseif ((int)$f['paid'] === 1): ?>
                                    <span class="badge b-paid">paid <?= htmlspecialchars($f['paid_date'] ?? '') ?></span>
                                <?php else: ?>
                                    <span class="badge b-unpaid">unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <?php if ((int)$f['paid'] === 0 && (int)$f['waived'] === 0): ?>
                                        <a href="manage_fines.php?action=pay&id=<?= (int)$f['id'] ?>" class="btn-pay">
                                            <i class="bi bi-cash"></i> Collect
                                        </a>
                                        <a href="manage_fines.php?action=waive&id=<?= (int)$f['id'] ?>" class="btn-waive">
                                            <i class="bi bi-x-octagon"></i> Waive
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:.78rem;">
                                            <?= (int)$f['waived'] === 1 ? 'waived' : 'closed' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

</div>

<?php include 'footer.php'; ?>
