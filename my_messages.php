<?php
// my_messages.php — Member: view own notifications / messages
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('my_messages');
if (my_access('my_messages') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'My Messages';
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) { header('Location: login.php'); exit; }

$success = '';

// ---------- ACTIONS ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE id=? AND user_id=?")
            ->execute([$id, $user_id]);
        $success = 'Message marked as read.';
    }

    if ($action === 'mark_all_read') {
        $pdo->prepare("UPDATE notifications SET is_read=1, read_at=NOW() WHERE user_id=? AND is_read=0")
            ->execute([$user_id]);
        $success = 'All messages marked as read.';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")
            ->execute([$id, $user_id]);
        $success = 'Message deleted.';
    }

    if ($action === 'clear_read') {
        $pdo->prepare("DELETE FROM notifications WHERE user_id=? AND is_read=1")
            ->execute([$user_id]);
        $success = 'Read messages cleared.';
    }
}

// ---------- FILTER ----------
$tab = $_GET['tab'] ?? 'all';
if (!in_array($tab, ['all','unread','read'], true)) $tab = 'all';

$where = "WHERE user_id = ?";
$args  = [$user_id];
if ($tab === 'unread') { $where .= " AND is_read = 0"; }
if ($tab === 'read')   { $where .= " AND is_read = 1"; }

// ---------- LOAD ----------
$st = $pdo->prepare(
    "SELECT id, type, title, message, related_id, is_read, read_at, created_at
     FROM notifications
     $where
     ORDER BY is_read ASC, created_at DESC
     LIMIT 200"
);
$st->execute($args);
$messages = $st->fetchAll();

// ---------- COUNTS ----------
$c = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(is_read=0) AS unread,
        SUM(is_read=1) AS read_
     FROM notifications WHERE user_id = ?"
);
$c->execute([$user_id]);
$counts = $c->fetch();

// Type → icon + color
$types = [
    'due_soon'    => ['icon' => 'bi-clock-history',    'color' => '#f59e0b'],
    'overdue'     => ['icon' => 'bi-exclamation-triangle-fill', 'color' => '#dc2626'],
    'hold_ready'  => ['icon' => 'bi-bookmark-star-fill','color' => '#0d9488'],
    'fine_issued' => ['icon' => 'bi-cash-coin',        'color' => '#dc2626'],
    'welcome'     => ['icon' => 'bi-emoji-smile',      'color' => '#0d9488'],
    'general'     => ['icon' => 'bi-info-circle-fill', 'color' => '#657aa8'],
];

include 'header.php';
?>

<style>
    .msg-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin-bottom:22px; }
    .msg-stat {
        background:var(--card-bg); border-radius:12px; padding:16px; text-align:center;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
    }
    .msg-stat .v { font-size:1.6rem; font-weight:700; color:var(--primary-dark); }
    .msg-stat .l { font-size:.82rem; color:var(--text-muted); margin-top:6px; }

    .msg-tabs { display:flex; gap:8px; margin-bottom:20px; border-bottom:2px solid var(--border); flex-wrap:wrap; align-items:center; }
    .msg-tabs a {
        padding:10px 16px; text-decoration:none; color:var(--text-muted);
        font-weight:600; font-size:.9rem; border-bottom:3px solid transparent; margin-bottom:-2px;
    }
    .msg-tabs a.active { color:var(--primary-dark); border-bottom-color:var(--primary); }
    .msg-tabs a:hover { color:var(--primary); }
    .msg-tabs .right { margin-left:auto; margin-bottom:6px; display:flex; gap:8px; }

    .msg-item {
        background:var(--card-bg); border-radius:12px; padding:16px;
        display:flex; gap:14px; box-shadow:var(--shadow);
        border:1px solid rgba(13,148,136,0.08); margin-bottom:10px;
        transition:transform .12s ease;
    }
    .msg-item:hover { transform:translateX(2px); }
    .msg-item.unread { background:linear-gradient(to right,#f0fdfa, #ffffff 60%); border-left:4px solid var(--primary); }

    .msg-icon {
        width:44px; height:44px; border-radius:12px; display:grid; place-items:center;
        color:#fff; font-size:1.3rem; flex-shrink:0;
    }
    .msg-body { flex:1; min-width:0; }
    .msg-title { font-weight:700; margin:0 0 4px; font-size:.98rem; color:var(--text); }
    .msg-title .dot { display:inline-block; width:8px; height:8px; background:var(--primary); border-radius:50%; margin-left:6px; }
    .msg-text { color:var(--text); line-height:1.55; font-size:.9rem; margin:4px 0 8px; }
    .msg-meta { color:var(--text-muted); font-size:.78rem; }
    .msg-meta .type-tag {
        display:inline-block; padding:2px 8px; border-radius:6px;
        background:#f1f5f9; color:#475569; font-weight:600; font-size:.72rem;
        margin-right:8px;
    }

    .msg-actions { display:flex; gap:6px; margin-top:10px; flex-wrap:wrap; }
    .msg-actions form { display:inline; }
    .msg-actions button {
        padding:5px 12px; border-radius:8px; font-size:.78rem; font-weight:600;
        cursor:pointer; border:none; display:inline-flex; align-items:center; gap:5px;
    }
    .btn-read   { background:#e0f2f1; color:#0f766e; }
    .btn-read:hover { background:#0d9488; color:#fff; }
    .btn-del    { background:transparent; border:1px solid #ef4444; color:#ef4444; }
    .btn-del:hover { background:#ef4444; color:#fff; }

    .empty {
        text-align:center; padding:50px; background:var(--card-bg);
        border-radius:var(--radius); box-shadow:var(--shadow);
        border:1px solid rgba(13,148,136,0.08);
    }
    .empty i { font-size:3rem; color:var(--text-muted); }
    .empty p { color:var(--text-muted); margin-top:12px; }
</style>

<h2 style="margin-bottom:18px;"><i class="bi bi-envelope" style="color:var(--primary);"></i> My Messages</h2>

<?php if ($success): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<!-- STATS -->
<div class="msg-stats">
    <div class="msg-stat">
        <div class="v"><?= (int)$counts['total'] ?></div>
        <div class="l">Total Messages</div>
    </div>
    <div class="msg-stat">
        <div class="v" style="color:<?= (int)$counts['unread'] > 0 ? '#dc2626' : 'var(--primary-dark)' ?>;">
            <?= (int)$counts['unread'] ?>
        </div>
        <div class="l">Unread</div>
    </div>
    <div class="msg-stat">
        <div class="v"><?= (int)$counts['read_'] ?></div>
        <div class="l">Read</div>
    </div>
</div>

<!-- TABS -->
<div class="msg-tabs">
    <a href="my_messages.php?tab=all"    class="<?= $tab==='all'?'active':'' ?>"><i class="bi bi-inbox"></i> All (<?= (int)$counts['total'] ?>)</a>
    <a href="my_messages.php?tab=unread" class="<?= $tab==='unread'?'active':'' ?>"><i class="bi bi-envelope-fill"></i> Unread (<?= (int)$counts['unread'] ?>)</a>
    <a href="my_messages.php?tab=read"   class="<?= $tab==='read'?'active':'' ?>"><i class="bi bi-envelope-open"></i> Read (<?= (int)$counts['read_'] ?>)</a>

    <div class="right">
        <?php if ((int)$counts['unread'] > 0): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Mark all as read?');">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn-add" style="padding:6px 12px;font-size:.82rem;">
                    <i class="bi bi-check-all"></i> Mark All Read
                </button>
            </form>
        <?php endif; ?>
        <?php if ((int)$counts['read_'] > 0): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete all read messages?');">
                <input type="hidden" name="action" value="clear_read">
                <button type="submit" class="btn-secondary-custom" style="padding:6px 12px;font-size:.82rem;">
                    <i class="bi bi-trash"></i> Clear Read
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- MESSAGES -->
<?php if (!$messages): ?>
    <div class="empty">
        <i class="bi bi-inbox"></i>
        <p>
            <?php if ($tab === 'unread'): ?>
                No unread messages. You're all caught up! 🎉
            <?php elseif ($tab === 'read'): ?>
                No read messages.
            <?php else: ?>
                No messages yet.
            <?php endif; ?>
        </p>
        <a href="catalog.php" class="btn-add" style="text-decoration:none;display:inline-block;margin-top:10px;">
            <i class="bi bi-book"></i> Browse Catalog
        </a>
    </div>
<?php else: ?>
    <?php foreach ($messages as $m):
        $t = $types[$m['type']] ?? $types['general'];
    ?>
        <div class="msg-item <?= $m['is_read'] ? '' : 'unread' ?>">
            <div class="msg-icon" style="background:<?= $t['color'] ?>;">
                <i class="bi <?= $t['icon'] ?>"></i>
            </div>
            <div class="msg-body">
                <div class="msg-title">
                    <?= htmlspecialchars($m['title']) ?>
                    <?php if (!$m['is_read']): ?><span class="dot"></span><?php endif; ?>
                </div>
                <div class="msg-meta">
                    <span class="type-tag"><?= htmlspecialchars(str_replace('_',' ', $m['type'])) ?></span>
                    <?= htmlspecialchars($m['created_at']) ?>
                    <?php if ($m['is_read'] && $m['read_at']): ?>
                        · read <?= htmlspecialchars($m['read_at']) ?>
                    <?php endif; ?>
                </div>
                <p class="msg-text"><?= nl2br(htmlspecialchars($m['message'])) ?></p>

                <div class="msg-actions">
                    <?php if (!$m['is_read']): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" class="btn-read"><i class="bi bi-check2"></i> Mark Read</button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Delete this message?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <button type="submit" class="btn-del"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include 'footer.php'; ?>
