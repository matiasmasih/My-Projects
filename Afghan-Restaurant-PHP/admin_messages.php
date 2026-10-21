<?php
// admin_messages.php — Contact form inbox with reply

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$flash = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            if ($action === 'delete') {
                $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
                $flash = 'Message deleted.';
            } elseif ($action === 'toggle_read') {
                $pdo->prepare("UPDATE contact_messages SET is_read = NOT is_read WHERE id = ?")->execute([$id]);
                $flash = 'Read status updated.';
            } elseif ($action === 'save_reply') {
                $reply = trim($_POST['reply'] ?? '');
                if ($reply !== '') {
                    $pdo->prepare("
                        UPDATE contact_messages
                        SET admin_reply = ?, replied_at = NOW(), replied_by = ?, is_replied = 1, is_read = 1
                        WHERE id = ?
                    ")->execute([$reply, (int)$_SESSION['admin_id'], $id]);
                    $flash = 'Reply saved — visible to customer.';
                } else {
                    $flash = 'Reply cannot be empty.';
                }
            }
        } catch (PDOException $e) {
            error_log('admin_messages action: ' . $e->getMessage());
            $flash = 'Action failed.';
        }
    }
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$where = '';
if ($filter === 'unread')      $where = "WHERE m.is_read = 0";
elseif ($filter === 'unreplied') $where = "WHERE m.is_replied = 0";

// Load messages
$messages = [];
try {
    $messages = $pdo->query("
        SELECT m.*, b.name AS branch_name
        FROM contact_messages m
        LEFT JOIN branches b ON b.id = m.branch_id
        $where
        ORDER BY m.created_at DESC
        LIMIT 200
    ")->fetchAll();
} catch (PDOException $e) {
    $messages = [];
}

// Counts
$unread_count = 0;
$unreplied_count = 0;
try {
    $unread_count    = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    $unreplied_count = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_replied = 0")->fetchColumn();
} catch (PDOException $e) {}

$page_title = 'Messages';
require_once __DIR__ . '/includes/admin_header.php';
?>



        <div class="admin-hero">
            <div>
                <h1><?= e(t('admin_messages')) ?></h1>
                <p><?= e(t('admin_messages_sub')) ?></p>
            </div>
            <a href="admin_dashboard.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-arrow-left"></i> <?= e(t('admin_dashboard')) ?>
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= e($flash) ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="admin-panel">
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="admin_messages.php" class="category-tab <?= $filter === 'all' ? 'active' : '' ?>">
                    All
                </a>
                <a href="admin_messages.php?filter=unread" class="category-tab <?= $filter === 'unread' ? 'active' : '' ?>">
                    Unread (<?= $unread_count ?>)
                </a>
                <a href="admin_messages.php?filter=unreplied" class="category-tab <?= $filter === 'unreplied' ? 'active' : '' ?>">
                    Unreplied (<?= $unreplied_count ?>)
                </a>
            </div>
        </div>

        <?php if (empty($messages)): ?>
            <div class="admin-panel">
                <div class="admin-empty">
                    <i class="fas fa-inbox"></i>
                    <?= e(t('admin_no_messages')) ?>
                </div>
            </div>
        <?php else: ?>

            <?php foreach ($messages as $m): ?>
                <div class="admin-panel" style="<?= !$m['is_read'] ? 'border-left:4px solid var(--accent);' : '' ?>">

                    <!-- Header row -->
                    <div class="admin-panel-header">
                        <div>
                            <h3 style="margin-bottom:4px;">
                                <?php if (!$m['is_read']): ?>
                                    <span style="display:inline-block;width:8px;height:8px;background:var(--accent);border-radius:50%;margin-right:8px;vertical-align:middle;"></span>
                                <?php endif; ?>
                                <i class="fas fa-envelope"></i> <?= e($m['subject']) ?>
                            </h3>
                            <div style="font-size:0.85rem;color:var(--text-muted);">
                                From <strong><?= e($m['name']) ?></strong> &lt;<?= e($m['email']) ?>&gt;
                                <?php if ($m['phone']): ?> · <?= e($m['phone']) ?><?php endif; ?>
                                <?php if ($m['branch_name']): ?> · <?= e($m['branch_name']) ?><?php endif; ?>
                                · <?= e(date('Y-m-d H:i', strtotime($m['created_at']))) ?>
                            </div>
                        </div>

                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_read">
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <button type="submit" class="btn btn-outline btn-sm">
                                    <i class="fas <?= $m['is_read'] ? 'fa-envelope' : 'fa-envelope-open' ?>"></i>
                                    <?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?>
                                </button>
                            </form>

                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this message?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <button type="submit" class="admin-btn-del" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- <?= e(t('admin_related_to')) ?> -->
                    <?php if ($m['related_type'] !== 'general'): ?>
                        <div style="margin-bottom:12px;">
                            <span class="badge badge-accent">
                                <i class="fas <?= $m['related_type'] === 'order' ? 'fa-receipt' : 'fa-calendar-check' ?>"></i>
                                <?= e(t('admin_related_to')) ?> <?= e($m['related_type']) ?> #<?= (int)$m['related_id'] ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Message body -->
                    <div style="background:var(--bg-alt);padding:16px;border-radius:8px;font-size:0.92rem;line-height:1.7;white-space:pre-wrap;margin-bottom:14px;">
                        <?= e($m['message']) ?>
                    </div>

                    <!-- Existing reply -->
                    <?php if (!empty($m['admin_reply'])): ?>
                        <div style="border-left:3px solid var(--accent);padding:12px 16px;background:var(--bg-alt);border-radius:6px;margin-bottom:14px;">
                            <div style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">
                                <i class="fas fa-reply"></i> Reply
                                <?php if ($m['replied_at']): ?>
                                    · <?= e(date('Y-m-d H:i', strtotime($m['replied_at']))) ?>
                                <?php endif; ?>
                                <?php if ($m['is_replied']): ?>
                                    · <span class="status-badge status-ready" style="font-size:0.7rem;">Visible to customer</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:0.92rem;line-height:1.7;white-space:pre-wrap;">
                                <?= e($m['admin_reply']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Reply form -->
                    <form method="post">
                        <input type="hidden" name="action" value="save_reply">
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">

                        <div class="form-field" style="margin-bottom:10px;">
                            <label for="reply-<?= (int)$m['id'] ?>" style="font-size:0.85rem;color:var(--text-muted);">
                                <?= empty($m['admin_reply']) ? 'Reply to customer' : 'Edit reply' ?>
                            </label>
                            <textarea id="reply-<?= (int)$m['id'] ?>" name="reply" rows="3"
                                      placeholder="Type your reply — this will be visible to the customer in their account."><?= e($m['admin_reply']) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-accent btn-sm">
                            <i class="fas fa-paper-plane"></i> <?= empty($m['admin_reply']) ? 'Send reply' : 'Update reply' ?>
                        </button>
                    </form>

                    <?php if ($m['ip_address']): ?>
                        <div style="margin-top:12px;font-size:0.75rem;color:var(--text-muted);">
                            IP: <?= e($m['ip_address']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
