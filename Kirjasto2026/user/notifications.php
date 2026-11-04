<?php
// user/notifications.php — Member Notifications (Modern & Branch-Aware)

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fixed role check
if (!$user || ($user['role'] !== 'member' && $user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$flash = '';
$error = '';

// ---- MARK AS READ ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    $notif_id = (int)($_POST['notification_id'] ?? 0);
    if ($notif_id > 0) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$notif_id, $user_id]);
        $flash = 'Notification marked as read.';
    }
}

// ---- MARK ALL AS READ ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$user_id]);
    $flash = 'All notifications marked as read.';
}

// ---- FETCH NOTIFICATIONS (with Filter) ----
$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$user_id];

if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $sql .= " AND is_read = 1";
}

$sql .= " ORDER BY created_at DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Count unread
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$unread_count = (int)$stmt->fetchColumn();

// Count total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_count = (int)$stmt->fetchColumn();

$page_title = 'Notifications';
require_once __DIR__ . '/../includes/user_header.php';
?>

<!-- Hero Banner -->
<div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); padding: 40px; margin-bottom: 32px; position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
    <div style="position: relative; z-index: 2; max-width: 60%;">
        <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text); margin-bottom: 8px;">Notifications</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; margin: 0;">
            <?= $branch_name ? 'Updates for <strong>' . e($branch_name) . '</strong>' : 'Updates for your account' ?>
            <?php if ($unread_count > 0): ?>
                · You have <strong style="color: #10b981;"><?= $unread_count ?> unread</strong>.
            <?php else: ?>
                · You're all caught up!
            <?php endif; ?>
        </p>
    </div>

    <?php if ($unread_count > 0): ?>
    <div style="position: relative; z-index: 2;">
        <form method="POST" style="margin: 0;">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-primary" style="padding: 14px 28px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; font-weight: 700; cursor: pointer; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-check-double"></i> Mark All as Read
            </button>
        </form>
    </div>
    <?php endif; ?>

    <i class="fas fa-bell" style="position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: #10b981; opacity: 0.05; z-index: 1; transform: rotate(-15deg);"></i>
</div>

<?php if ($flash): ?>
    <div class="flash flash-success" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; color: #10b981; font-weight: 600;">
        <i class="fas fa-check-circle"></i> <?= e($flash) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="flash flash-error" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; color: #ef4444; font-weight: 600;">
        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Filter Tabs -->
<div style="display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap;">
    <a href="?filter=all" class="btn-outline" style="padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; <?= $filter === 'all' ? 'background: #10b981; color: white; border-color: transparent;' : 'background: var(--bg-alt); color: var(--text); border: 1px solid var(--border);' ?>">
        <i class="fas fa-inbox"></i> All (<?= $total_count ?>)
    </a>
    <a href="?filter=unread" class="btn-outline" style="padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; <?= $filter === 'unread' ? 'background: #10b981; color: white; border-color: transparent;' : 'background: var(--bg-alt); color: var(--text); border: 1px solid var(--border);' ?>">
        <i class="fas fa-circle" style="font-size: 0.6rem;"></i> Unread (<?= $unread_count ?>)
    </a>
    <a href="?filter=read" class="btn-outline" style="padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; <?= $filter === 'read' ? 'background: #10b981; color: white; border-color: transparent;' : 'background: var(--bg-alt); color: var(--text); border: 1px solid var(--border);' ?>">
        <i class="fas fa-check-circle"></i> Read
    </a>
</div>

<?php if (empty($notifications)): ?>
    <div class="card" style="text-align: center; padding: 80px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
        <i class="fas fa-bell-slash" style="font-size: 4rem; color: var(--border); margin-bottom: 16px;"></i>
        <h3 style="color: var(--text); margin-bottom: 8px;">No notifications found</h3>
        <p style="color: var(--text-muted); margin: 0;">
            <?= $filter === 'unread' ? 'You have no unread notifications.' : 'When a book is due soon, a reservation is ready, or you have a new message, you\'ll see it here.' ?>
        </p>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach ($notifications as $notif):
            $is_unread = ($notif['is_read'] == 0);
            
            // Determine icon and color based on content/title keywords
            $icon = 'fa-bell';
            $icon_bg = 'rgba(16, 185, 129, 0.1)';
            $icon_color = '#10b981';
            
            $title_lower = strtolower($notif['title']);
            $content_lower = strtolower($notif['content']);
            
            if (strpos($title_lower, 'due') !== false || strpos($content_lower, 'overdue') !== false) {
                $icon = 'fa-clock';
                $icon_bg = 'rgba(245, 158, 11, 0.1)';
                $icon_color = '#f59e0b';
            } elseif (strpos($title_lower, 'fine') !== false || strpos($content_lower, 'fine') !== false) {
                $icon = 'fa-euro-sign';
                $icon_bg = 'rgba(239, 68, 68, 0.1)';
                $icon_color = '#ef4444';
            } elseif (strpos($title_lower, 'reservation') !== false || strpos($title_lower, 'ready') !== false) {
                $icon = 'fa-bookmark';
                $icon_bg = 'rgba(14, 165, 233, 0.1)';
                $icon_color = '#0ea5e9';
            } elseif (strpos($title_lower, 'message') !== false || strpos($title_lower, 'reply') !== false) {
                $icon = 'fa-comment-dots';
                $icon_bg = 'rgba(139, 92, 246, 0.1)';
                $icon_color = '#8b5cf6';
            } elseif (strpos($title_lower, 'transfer') !== false) {
                $icon = 'fa-truck';
                $icon_bg = 'rgba(236, 72, 153, 0.1)';
                $icon_color = '#ec4899';
            }
        ?>
            <div class="card" style="padding: 20px; display: flex; gap: 16px; align-items: flex-start; transition: all 0.2s;
                <?= $is_unread ? 'border-left: 4px solid #10b981; background: rgba(16, 185, 129, 0.03);' : 'border-left: 4px solid transparent; opacity: 0.85;' ?>"
                onmouseover="this.style.transform='translateX(4px)';" onmouseout="this.style.transform='translateX(0)';">
                
                <!-- Icon -->
                <div style="width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: <?= $icon_bg ?>; color: <?= $icon_color ?>;">
                    <i class="fas <?= $icon ?>" style="font-size: 1.2rem;"></i>
                </div>

                <!-- Content -->
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 6px;">
                        <h4 style="margin: 0; font-size: 1rem; color: var(--text); font-weight: <?= $is_unread ? '800' : '600' ?>;">
                            <?= e($notif['title']) ?>
                            <?php if ($is_unread): ?>
                                <span style="display: inline-block; width: 8px; height: 8px; background: #10b981; border-radius: 50%; margin-left: 8px; vertical-align: middle; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);"></span>
                            <?php endif; ?>
                        </h4>
                        <span style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; background: var(--bg-alt); padding: 2px 8px; border-radius: 6px;">
                            <i class="fas fa-clock" style="font-size: 0.65rem;"></i> <?= date('d M, H:i', strtotime($notif['created_at'])) ?>
                        </span>
                    </div>
                    <p style="margin: 0 0 12px 0; color: var(--text-muted); font-size: 0.9rem; line-height: 1.6;">
                        <?= nl2br(e($notif['content'])) ?>
                    </p>
                    <?php if ($is_unread): ?>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="notification_id" value="<?= $notif['id'] ?>">
                            <button type="submit" class="btn-outline" style="padding: 6px 14px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fas fa-check"></i> Mark as read
                            </button>
                        </form>
                    <?php else: ?>
                        <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">
                            <i class="fas fa-check-double"></i> Read
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
