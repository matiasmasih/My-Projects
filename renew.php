<?php
// renew.php — Librarian: extend an active loan by LOAN_DAYS
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('renew');
if (my_access('renew') !== 'full') {
    header('Location: index.php'); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Renew Loan';
$error = '';
$success = '';

// ============================================================
// RENEW (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'renew') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $borrowing_id = (int)($_POST['borrowing_id'] ?? 0);

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare(
            "SELECT br.id, br.user_id, br.copy_id, br.due_date, br.renewed_count, br.status,
                    c.barcode, c.book_id, b.title,
                    u.username, u.first_name, u.last_name, u.status AS user_status
             FROM borrowings br
             JOIN copies c ON c.id = br.copy_id
             JOIN books  b ON b.id = c.book_id
             JOIN users  u ON u.id = br.user_id
             WHERE br.id = ? FOR UPDATE"
        );
        $st->execute([$borrowing_id]);
        $loan = $st->fetch();

        if (!$loan) {
            throw new Exception('Loan not found.');
        }
        if ($loan['status'] !== 'borrowed') {
            throw new Exception('Only active loans can be renewed.');
        }
        if ($loan['user_status'] !== 'active') {
            throw new Exception('Member account is not active — cannot renew.');
        }
        if ((int)$loan['renewed_count'] >= MAX_RENEWALS) {
            throw new Exception('Renewal limit reached (' . MAX_RENEWALS . ').');
        }
        if (strtotime($loan['due_date']) < strtotime('-1 day')) {
            throw new Exception('Loan is overdue — cannot renew. Please return it first.');
        }

        // No pending holds on this book?
        $holds = $pdo->prepare(
            "SELECT COUNT(*) FROM holds
             WHERE book_id = ? AND status IN ('pending','ready')"
        );
        $holds->execute([$loan['book_id']]);
        if ((int)$holds->fetchColumn() > 0) {
            throw new Exception('Another member is waiting on hold for this book — cannot renew.');
        }

        // Unpaid fines block?
        $f = $pdo->prepare(
            "SELECT COALESCE(SUM(amount),0) FROM fines
             WHERE user_id = ? AND paid = 0 AND waived = 0"
        );
        $f->execute([$loan['user_id']]);
        $owed = (float)$f->fetchColumn();
        if ($owed > FINE_BLOCK_THRESHOLD) {
            throw new Exception('Member owes €' . number_format($owed,2) . ' — above the renewal threshold.');
        }

        $old_due   = $loan['due_date'];
        $new_due   = date('Y-m-d', strtotime($old_due . ' +' . LOAN_DAYS . ' days'));
        $new_count = (int)$loan['renewed_count'] + 1;

        $pdo->prepare("UPDATE borrowings SET due_date = ?, renewed_count = ? WHERE id = ?")
            ->execute([$new_due, $new_count, $borrowing_id]);

        $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                       VALUES (?, 'loan_renewed', ?, 'borrowing', ?)")
            ->execute([
                $_SESSION['user_id'] ?? null,
                "Renewed loan #{$borrowing_id} ({$loan['title']} · {$loan['barcode']}) for {$loan['username']} — new due date {$new_due} (renewal {$new_count}/" . MAX_RENEWALS . ")",
                $borrowing_id
            ]);

        $pdo->commit();

        $success = "Renewed. \"{$loan['title']}\" for {$loan['username']} — new due date {$new_due} (renewal {$new_count}/" . MAX_RENEWALS . ").";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// ============================================================
// LOAD ACTIVE LOANS
// ============================================================
$q = trim($_GET['q'] ?? '');
$where = ["br.status = 'borrowed'"];
$args  = [];
if ($q !== '') {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?
                 OR c.barcode LIKE ? OR b.title LIKE ?)";
    $like = "%$q%";
    array_push($args, $like, $like, $like, $like, $like);
}
$where_sql = 'WHERE ' . implode(' AND ', $where);

$st = $pdo->prepare(
    "SELECT br.id, br.borrow_date, br.due_date, br.renewed_count,
            u.id AS user_id, u.username, u.first_name, u.last_name, u.card_number,
            c.barcode, c.id AS copy_id,
            b.id AS book_id, b.title,
            (SELECT COUNT(*) FROM holds h WHERE h.book_id = b.id AND h.status IN ('pending','ready')) AS holds_waiting
     FROM borrowings br
     JOIN users  u ON u.id = br.user_id
     JOIN copies c ON c.id = br.copy_id
     JOIN books  b ON b.id = c.book_id
     $where_sql
     ORDER BY br.due_date ASC
     LIMIT 500"
);
$st->execute($args);
$loans = $st->fetchAll();

include 'header.php';
?>

<div class="main-content">

<style>
    .renew-toolbar {
        display:flex; gap:10px; flex-wrap:nowrap; align-items:center;
        background:var(--card-bg); padding:14px; border-radius:12px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        margin-bottom:18px; overflow-x:auto;
    }
    .renew-toolbar input, .renew-toolbar select {
        padding:9px 12px; border:1px solid var(--border); border-radius:8px;
        background:var(--card-bg); color:var(--text); font-size:.9rem;
        white-space:nowrap;
    }
    .renew-toolbar input[type=text] { flex:1; min-width:220px; }

    .renew-card {
        background:var(--card-bg); border-radius:12px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        overflow:hidden;
    }
    .renew-scroll { width:100%; overflow-x:auto; overflow-y:visible; -webkit-overflow-scrolling:touch; }
    table.renew-table { width:100%; border-collapse:collapse; min-width:1100px; }
    table.renew-table th, table.renew-table td {
        padding:12px 14px; vertical-align:middle;
        white-space:nowrap; border-bottom:1px solid rgba(0,0,0,0.05); text-align:left;
    }
    table.renew-table thead th {
        background:var(--card-bg); font-weight:600; font-size:.82rem;
        color:var(--text-muted); text-transform:uppercase; letter-spacing:.03em;
        border-bottom:2px solid var(--border);
    }

    .renew-badge { display:inline-block; padding:3px 9px; border-radius:8px; font-size:.72rem; font-weight:600; white-space:nowrap; }
    .rb-ok   { background:#d1fae5; color:#065f46; }
    .rb-warn { background:#fef3c7; color:#92400e; }
    .rb-stop { background:#fee2e2; color:#991b1b; }

    .renew-btn {
        padding:6px 12px; border-radius:6px; border:none; cursor:pointer;
        font-size:.78rem; font-weight:600; background:#0d9488; color:#fff;
        display:inline-flex; align-items:center; gap:4px;
    }
    .renew-btn:hover { background:#0f766e; }
    .renew-btn:disabled { background:#cbd5e1; color:#64748b; cursor:not-allowed; }
</style>

<?php if ($success): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div style="margin-bottom:16px;">
    <h2 style="margin:0;"><i class="bi bi-arrow-clockwise" style="color:var(--primary);"></i> Renew Active Loans</h2>
    <p style="color:var(--text-muted);font-size:.88rem;margin-top:6px;">
        Renewal extends the due date by <strong><?= LOAN_DAYS ?> days</strong>.
        Max <strong><?= MAX_RENEWALS ?></strong> renewals per loan.
        Not allowed if overdue, on hold, or fines over €<?= number_format(FINE_BLOCK_THRESHOLD, 2) ?>.
    </p>
</div>

<form class="renew-toolbar" method="get" action="renew.php">
    <input type="text" name="q" placeholder="Search member, barcode, title…" value="<?= htmlspecialchars($q) ?>">
    <button type="submit" class="btn-add" style="white-space:nowrap;"><i class="bi bi-search"></i> Search</button>
    <a href="renew.php" class="btn-secondary-custom" style="white-space:nowrap;">Reset</a>
</form>

<?php if (!$loans): ?>
    <div class="renew-card" style="text-align:center;padding:50px;">
        <i class="bi bi-arrow-clockwise" style="font-size:3rem;color:var(--text-muted);"></i>
        <p style="color:var(--text-muted);margin-top:12px;">No active loans to renew.</p>
    </div>
<?php else: ?>
    <div class="renew-card">
        <div class="renew-scroll">
            <table class="renew-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Card #</th>
                        <th>Book</th>
                        <th>Barcode</th>
                        <th>Borrowed</th>
                        <th>Due</th>
                        <th>Renewed</th>
                        <th>Holds</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($loans as $l):
                    $is_overdue = strtotime($l['due_date']) < strtotime('today');
                    $maxed_out  = (int)$l['renewed_count'] >= MAX_RENEWALS;
                    $has_hold   = (int)$l['holds_waiting'] > 0;
                    $blocked    = $is_overdue || $maxed_out || $has_hold;
                    $reason     = $is_overdue  ? 'Overdue'
                                : ($maxed_out ? 'Max renewals'
                                : ($has_hold  ? 'On hold' : ''));
                ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($l['first_name'] . ' ' . $l['last_name']) ?>
                            <br><small style="color:var(--text-muted);">@<?= htmlspecialchars($l['username']) ?></small>
                        </td>
                        <td><code style="font-size:.78rem;"><?= htmlspecialchars($l['card_number'] ?? '—') ?></code></td>
                        <td>
                            <a href="book.php?id=<?= (int)$l['book_id'] ?>" style="color:var(--primary-dark);text-decoration:none;font-weight:600;">
                                <?= htmlspecialchars($l['title']) ?>
                            </a>
                        </td>
                        <td><code style="font-size:.78rem;"><?= htmlspecialchars($l['barcode']) ?></code></td>
                        <td><?= htmlspecialchars(substr($l['borrow_date'],0,10)) ?></td>
                        <td>
                            <?= htmlspecialchars($l['due_date']) ?>
                            <?php if ($is_overdue): ?>
                                <br><span class="renew-badge rb-stop">overdue</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$l['renewed_count'] ?> / <?= MAX_RENEWALS ?></td>
                        <td>
                            <?php if ($has_hold): ?>
                                <span class="renew-badge rb-warn"><?= (int)$l['holds_waiting'] ?> waiting</span>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($blocked): ?>
                                <button class="renew-btn" disabled title="<?= htmlspecialchars($reason) ?>">
                                    <i class="bi bi-slash-circle"></i> <?= htmlspecialchars($reason) ?>
                                </button>
                            <?php else: ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Renew this loan by <?= LOAN_DAYS ?> days?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="action" value="renew">
                                    <input type="hidden" name="borrowing_id" value="<?= (int)$l['id'] ?>">
                                    <button type="submit" class="renew-btn">
                                        <i class="bi bi-arrow-clockwise"></i> Renew +<?= LOAN_DAYS ?>d
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p style="color:var(--text-muted);font-size:.82rem;margin-top:10px;">
        Showing <?= count($loans) ?> active loan(s).
    </p>
<?php endif; ?>

</div>

<?php include 'footer.php'; ?>
