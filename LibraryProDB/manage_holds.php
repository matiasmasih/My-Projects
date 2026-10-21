<?php
// manage_holds.php — Librarian: view + manage all holds
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('manage_holds');
if (my_access('manage_holds') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'Manage Holds';
$error = '';
$success = '';

// ---------- ACTIONS ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $hold_id = (int)($_POST['hold_id'] ?? 0);

    if ($hold_id <= 0) {
        $error = 'Invalid hold.';
    } else {
        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare("SELECT h.*, b.title FROM holds h JOIN books b ON b.id=h.book_id WHERE h.id=? FOR UPDATE");
            $st->execute([$hold_id]);
            $hold = $st->fetch();
            if (!$hold) throw new Exception('Hold not found.');

            if ($action === 'ready') {
                $pdo->prepare("UPDATE holds SET status='ready', notified_date=NOW(), expires_date=DATE_ADD(CURDATE(), INTERVAL 3 DAY) WHERE id=?")
                    ->execute([$hold_id]);
                $log_desc = "Marked hold #{$hold_id} ready ({$hold['title']})";
                $success = 'Hold marked as ready.';
            } elseif ($action === 'fulfilled') {
                $pdo->prepare("UPDATE holds SET status='fulfilled' WHERE id=?")->execute([$hold_id]);
                $log_desc = "Fulfilled hold #{$hold_id} ({$hold['title']})";
                $success = 'Hold marked as fulfilled.';
            } elseif ($action === 'cancel') {
                $pdo->prepare("UPDATE holds SET status='cancelled' WHERE id=?")->execute([$hold_id]);
                $log_desc = "Cancelled hold #{$hold_id} ({$hold['title']})";
                $success = 'Hold cancelled.';
            } else {
                throw new Exception('Unknown action.');
            }

            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'hold_update', ?, 'hold', ?)")
                ->execute([$_SESSION['user_id'] ?? null, $log_desc, $hold_id]);

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// ---------- FILTER ----------
$status = $_GET['status'] ?? 'active';
if (!in_array($status, ['active','pending','ready','fulfilled','cancelled','expired','all'], true)) {
    $status = 'active';
}

$where = '';
$args = [];
if ($status === 'active') {
    $where = "WHERE h.status IN ('pending','ready')";
} elseif ($status !== 'all') {
    $where = "WHERE h.status = ?";
    $args[] = $status;
}

$sql = "SELECT h.id, h.book_id, h.user_id, h.branch_id, h.placed_date, h.notified_date,
               h.expires_date, h.status, h.queue_position,
               b.title, b.cover_image,
               u.username, u.first_name, u.last_name, u.card_number,
               br.name AS branch_name
        FROM holds h
        JOIN books b ON b.id = h.book_id
        JOIN users u ON u.id = h.user_id
        LEFT JOIN branches br ON br.id = h.branch_id
        $where
        ORDER BY FIELD(h.status,'ready','pending','fulfilled','cancelled','expired'),
                 h.placed_date ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$holds = $stmt->fetchAll();

// ---------- COUNTS ----------
$counts = $pdo->query(
    "SELECT
        SUM(status='pending') AS pending,
        SUM(status='ready') AS ready,
        SUM(status='fulfilled') AS fulfilled,
        SUM(status='cancelled') AS cancelled,
        SUM(status='expired') AS expired
     FROM holds"
)->fetch();

include 'header.php';
?>

<style>
    .tabs { display:flex; gap:6px; margin-bottom:20px; border-bottom:2px solid var(--border); flex-wrap:wrap; }
    .tabs a {
        padding:10px 14px; text-decoration:none; color:var(--text-muted);
        font-weight:600; font-size:.85rem; border-bottom:3px solid transparent; margin-bottom:-2px;
    }
    .tabs a.active { color:var(--primary-dark); border-bottom-color:var(--primary); }
    .tabs a:hover { color:var(--primary); }

    .badge { display:inline-block; padding:3px 9px; border-radius:8px; font-size:.75rem; font-weight:600; }
    .badge-pending   { background:#fef3c7; color:#92400e; }
    .badge-ready     { background:#d1fae5; color:#065f46; }
    .badge-fulfilled { background:#ccfbf1; color:#115e59; }
    .badge-cancelled { background:#e2e8f0; color:#475569; }
    .badge-expired   { background:#fee2e2; color:#991b1b; }

    .mini-cover { width:36px; height:52px; object-fit:cover; border-radius:4px; }
    .mini-ph {
        width:36px; height:52px; border-radius:4px;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:1rem;
    }
    .action-btn {
        padding:5px 10px; font-size:.78rem; border-radius:8px; border:none;
        cursor:pointer; font-weight:600; text-decoration:none; display:inline-block;
        margin:2px;
    }
    .btn-ready     { background:#10b981; color:#fff; }
    .btn-fulfill   { background:var(--primary); color:#fff; }
    .btn-cancel    { background:#ef4444; color:#fff; }
    .btn-ready:hover, .btn-fulfill:hover, .btn-cancel:hover { opacity:.9; }

    .empty-state { text-align:center; padding:50px; }
    .empty-state i { font-size:3rem; color:var(--text-muted); }
    .empty-state p { color:var(--text-muted); margin-top:10px; }
</style>

<h2 style="margin-bottom:18px;"><i class="bi bi-bookmark-star" style="color:var(--primary);"></i> Manage Holds</h2>

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

<div class="tabs">
    <a href="manage_holds.php?status=active"    class="<?= $status==='active'?'active':'' ?>">Active (<?= (int)$counts['pending'] + (int)$counts['ready'] ?>)</a>
    <a href="manage_holds.php?status=pending"   class="<?= $status==='pending'?'active':'' ?>">Pending (<?= (int)$counts['pending'] ?>)</a>
    <a href="manage_holds.php?status=ready"     class="<?= $status==='ready'?'active':'' ?>">Ready (<?= (int)$counts['ready'] ?>)</a>
    <a href="manage_holds.php?status=fulfilled" class="<?= $status==='fulfilled'?'active':'' ?>">Fulfilled (<?= (int)$counts['fulfilled'] ?>)</a>
    <a href="manage_holds.php?status=cancelled" class="<?= $status==='cancelled'?'active':'' ?>">Cancelled (<?= (int)$counts['cancelled'] ?>)</a>
    <a href="manage_holds.php?status=expired"   class="<?= $status==='expired'?'active':'' ?>">Expired (<?= (int)$counts['expired'] ?>)</a>
    <a href="manage_holds.php?status=all"       class="<?= $status==='all'?'active':'' ?>">All</a>
</div>

<?php if (!$holds): ?>
    <div class="table-card empty-state">
        <i class="bi bi-bookmark"></i>
        <p>No <?= $status === 'all' ? '' : htmlspecialchars($status) . ' ' ?>holds.</p>
    </div>
<?php else: ?>
    <div class="table-card">
        <div style="overflow:auto;max-height:75vh;">
            <table>
                <thead>
                    <tr style="position:sticky;top:0;background:var(--card-bg);z-index:2;">
                        <th style="width:60px;"></th>
                        <th>Book</th>
                        <th>Member</th>
                        <th>Branch</th>
                        <th>Placed</th>
                        <th>Queue</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($holds as $h): ?>
                    <tr>
                        <td>
                            <?php if (!empty($h['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $h['cover_image'])): ?>
                                <img src="uploads/covers/<?= htmlspecialchars($h['cover_image']) ?>" class="mini-cover" alt="">
                            <?php else: ?>
                                <div class="mini-ph"><i class="bi bi-book"></i></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="book.php?id=<?= (int)$h['book_id'] ?>" style="color:var(--primary-dark);text-decoration:none;font-weight:600;">
                                <?= htmlspecialchars($h['title']) ?>
                            </a>
                        </td>
                        <td>
                            <?= htmlspecialchars(trim($h['first_name'].' '.$h['last_name'])) ?>
                            <br><small style="color:var(--text-muted);">@<?= htmlspecialchars($h['username']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($h['branch_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars(substr($h['placed_date'],0,10)) ?></td>
                        <td>#<?= (int)$h['queue_position'] ?></td>
                        <td><span class="badge badge-<?= htmlspecialchars($h['status']) ?>"><?= htmlspecialchars($h['status']) ?></span></td>
                        <td><?= $h['expires_date'] ? htmlspecialchars($h['expires_date']) : '—' ?></td>
                        <td style="white-space:nowrap;">
                            <?php if ($h['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="ready">
                                    <input type="hidden" name="hold_id" value="<?= (int)$h['id'] ?>">
                                    <button type="submit" class="action-btn btn-ready" onclick="return confirm('Mark as ready for pickup?');">
                                        <i class="bi bi-bell"></i> Ready
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($h['status'], ['pending','ready'], true)): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="fulfilled">
                                    <input type="hidden" name="hold_id" value="<?= (int)$h['id'] ?>">
                                    <button type="submit" class="action-btn btn-fulfill" onclick="return confirm('Mark as fulfilled (picked up)?');">
                                        <i class="bi bi-check2"></i> Fulfill
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="hold_id" value="<?= (int)$h['id'] ?>">
                                    <button type="submit" class="action-btn btn-cancel" onclick="return confirm('Cancel this hold?');">
                                        <i class="bi bi-x-lg"></i> Cancel
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
        Showing <?= count($holds) ?> hold(s).
    </p>
<?php endif; ?>

<?php include 'footer.php'; ?>
