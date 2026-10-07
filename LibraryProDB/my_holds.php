<?php
// my_holds.php — Member: view own holds, cancel pending
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('my_holds');
if (my_access('my_holds') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'My Holds';
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) { header('Location: login.php'); exit; }

$success = '';
$error = '';

// ---------- CANCEL HOLD ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $hold_id = (int)($_POST['hold_id'] ?? 0);
    if ($hold_id <= 0) {
        $error = 'Invalid hold.';
    } else {
        $st = $pdo->prepare("SELECT id, status FROM holds WHERE id=? AND user_id=?");
        $st->execute([$hold_id, $user_id]);
        $h = $st->fetch();

        if (!$h) {
            $error = 'Hold not found.';
        } elseif (!in_array($h['status'], ['pending','ready'], true)) {
            $error = 'This hold can no longer be cancelled.';
        } else {
            $pdo->prepare("UPDATE holds SET status='cancelled' WHERE id=?")->execute([$hold_id]);
            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'hold_cancelled', 'Cancelled own hold', 'hold', ?)")
                ->execute([$user_id, $hold_id]);
            $success = 'Hold cancelled.';
        }
    }
}

// ---------- DATA ----------
$st = $pdo->prepare(
    "SELECT h.id, h.book_id, h.placed_date, h.notified_date, h.expires_date,
            h.status, h.queue_position,
            b.title, b.cover_image,
            br.name AS branch_name,
            (SELECT COUNT(*) FROM copies c WHERE c.book_id=h.book_id AND c.status='available') AS avail
     FROM holds h
     JOIN books b ON b.id = h.book_id
     LEFT JOIN branches br ON br.id = h.branch_id
     WHERE h.user_id = ?
     ORDER BY FIELD(h.status,'ready','pending','fulfilled','cancelled','expired'),
              h.placed_date DESC
     LIMIT 100"
);
$st->execute([$user_id]);
$holds = $st->fetchAll();

// ---------- STATS ----------
$s = $pdo->prepare(
    "SELECT
        SUM(status='pending') AS pending,
        SUM(status='ready') AS ready,
        SUM(status IN ('fulfilled','cancelled','expired')) AS done
     FROM holds WHERE user_id = ?"
);
$s->execute([$user_id]);
$stats = $s->fetch();

include 'header.php';
?>

<style>
    .holds-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
    .hold-card {
        background:var(--card-bg); border-radius:var(--radius); padding:16px;
        display:flex; gap:14px; box-shadow:var(--shadow);
        border:1px solid rgba(13,148,136,0.08);
    }
    .hold-cover {
        width:60px; height:90px; object-fit:cover; border-radius:6px; flex-shrink:0;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:1.4rem;
    }
    .hold-body { flex:1; min-width:0; }
    .hold-title { font-weight:600; margin-bottom:4px; }
    .hold-meta { font-size:.82rem; color:var(--text-muted); margin:2px 0; }
    .hold-badge { display:inline-block; padding:3px 9px; border-radius:8px; font-size:.75rem; font-weight:600; margin-top:6px; }
    .badge-pending { background:#fef3c7; color:#92400e; }
    .badge-ready { background:#d1fae5; color:#065f46; }
    .badge-fulfilled { background:#ccfbf1; color:#115e59; }
    .badge-cancelled { background:#e2e8f0; color:#475569; }
    .badge-expired { background:#fee2e2; color:#991b1b; }
    .cancel-btn {
        margin-top:8px; background:transparent; border:1px solid #ef4444;
        color:#ef4444; padding:4px 10px; border-radius:8px;
        font-size:.75rem; font-weight:600; cursor:pointer;
    }
    .cancel-btn:hover { background:#ef4444; color:#fff; }

    .stats-strip { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:22px; }
    .stat-mini {
        background:var(--card-bg); border-radius:12px; padding:16px; text-align:center;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
    }
    .stat-mini .v { font-size:1.6rem; font-weight:700; color:var(--primary-dark); }
    .stat-mini .l { font-size:.82rem; color:var(--text-muted); margin-top:6px; }

    .notice-ready {
        background:#d1fae5; border-left:4px solid #10b981; padding:14px 18px;
        border-radius:10px; margin-bottom:18px; color:#065f46; font-size:.9rem;
    }
</style>

<h2 style="margin-bottom:18px;"><i class="bi bi-bookmark-star" style="color:var(--primary);"></i> My Holds</h2>

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

<?php if ((int)$stats['ready'] > 0): ?>
    <div class="notice-ready">
        <i class="bi bi-bell-fill"></i>
        <strong><?= (int)$stats['ready'] ?></strong> hold(s) ready for pickup!
        Please visit the library before the expiry date.
    </div>
<?php endif; ?>

<div class="stats-strip">
    <div class="stat-mini">
        <div class="v"><?= (int)$stats['pending'] ?></div>
        <div class="l">Pending</div>
    </div>
    <div class="stat-mini">
        <div class="v" style="color:<?= (int)$stats['ready'] > 0 ? '#059669' : 'var(--primary-dark)' ?>;">
            <?= (int)$stats['ready'] ?>
        </div>
        <div class="l">Ready for Pickup</div>
    </div>
    <div class="stat-mini">
        <div class="v"><?= (int)$stats['done'] ?></div>
        <div class="l">Completed / Cancelled</div>
    </div>
</div>

<?php if (!$holds): ?>
    <div class="table-card" style="text-align:center;padding:50px;">
        <i class="bi bi-bookmark" style="font-size:3rem;color:var(--text-muted);"></i>
        <p style="color:var(--text-muted);margin-top:12px;">You have no holds.</p>
        <a href="catalog.php" class="btn-add" style="text-decoration:none;display:inline-block;margin-top:8px;">
            <i class="bi bi-search"></i> Browse Catalog
        </a>
    </div>
<?php else: ?>
    <div class="holds-grid">
        <?php foreach ($holds as $h): ?>
            <div class="hold-card">
                <a href="book.php?id=<?= (int)$h['book_id'] ?>" style="text-decoration:none;">
                    <?php if (!empty($h['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $h['cover_image'])): ?>
                        <img src="uploads/covers/<?= htmlspecialchars($h['cover_image']) ?>" class="hold-cover" alt="">
                    <?php else: ?>
                        <div class="hold-cover"><i class="bi bi-book"></i></div>
                    <?php endif; ?>
                </a>
                <div class="hold-body">
                    <div class="hold-title">
                        <a href="book.php?id=<?= (int)$h['book_id'] ?>" style="color:inherit;text-decoration:none;">
                            <?= htmlspecialchars($h['title']) ?>
                        </a>
                    </div>
                    <div class="hold-meta"><i class="bi bi-calendar"></i> Placed <?= htmlspecialchars(substr($h['placed_date'],0,10)) ?></div>
                    <div class="hold-meta"><i class="bi bi-hash"></i> Queue position: #<?= (int)$h['queue_position'] ?></div>
                    <?php if ($h['branch_name']): ?>
                        <div class="hold-meta"><i class="bi bi-building"></i> <?= htmlspecialchars($h['branch_name']) ?></div>
                    <?php endif; ?>
                    <?php if ((int)$h['avail'] > 0 && $h['status'] === 'pending'): ?>
                        <div class="hold-meta" style="color:#059669;font-weight:600;">
                            <i class="bi bi-check-circle"></i> <?= (int)$h['avail'] ?> copy(ies) now available
                        </div>
                    <?php endif; ?>
                    <?php if ($h['expires_date']): ?>
                        <div class="hold-meta"><i class="bi bi-hourglass-split"></i> Expires <?= htmlspecialchars($h['expires_date']) ?></div>
                    <?php endif; ?>
                    <span class="hold-badge badge-<?= htmlspecialchars($h['status']) ?>"><?= htmlspecialchars($h['status']) ?></span>

                    <?php if (in_array($h['status'], ['pending','ready'], true)): ?>
                        <form method="POST" style="margin-top:4px;">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="hold_id" value="<?= (int)$h['id'] ?>">
                            <button type="submit" class="cancel-btn" onclick="return confirm('Cancel this hold?');">
                                <i class="bi bi-x-circle"></i> Cancel Hold
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>

