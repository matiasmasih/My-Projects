<?php
// admin_messages.php — Inbox for contact messages
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

require_login('admin_login.php');

$error   = '';
$success = '';

// -------- ACTIONS --------
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && $id > 0) {
    $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: admin_messages.php?msg=deleted');
    exit;
}

if ($action === 'mark_read' && $id > 0) {
    $stmt = $conn->prepare("UPDATE contact_messages SET status='read' WHERE id = ? AND status='unread'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: admin_messages.php?msg=read');
    exit;
}

if ($action === 'mark_unread' && $id > 0) {
    $stmt = $conn->prepare("UPDATE contact_messages SET status='unread' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: admin_messages.php?msg=unread');
    exit;
}

// -------- REPLY --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_submit'])) {
    $msg_id  = (int)($_POST['msg_id'] ?? 0);
    $reply   = trim($_POST['reply_message'] ?? '');

    if ($msg_id <= 0 || $reply === '') {
        $error = t('msg_err_reply');
    } else {
        $stmt = $conn->prepare(
            "UPDATE contact_messages
             SET reply = ?, reply_date = NOW(), status = 'replied'
             WHERE id = ?"
        );
        $stmt->bind_param("si", $reply, $msg_id);
        if ($stmt->execute()) {
            $success = t('msg_replied');
        } else {
            $error = t('msg_err_db') . ' ' . $conn->error;
        }
    }
}

// -------- LOAD CURRENT MESSAGE (for reply panel) --------
$view = null;
if (isset($_GET['view'])) {
    $view_id = (int)$_GET['view'];
    $stmt = $conn->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->bind_param("i", $view_id);
    $stmt->execute();
    $view = $stmt->get_result()->fetch_assoc();

    // Auto-mark read on open
    if ($view && $view['status'] === 'unread') {
        $conn->query("UPDATE contact_messages SET status='read' WHERE id = " . (int)$view_id);
        $view['status'] = 'read';
    }
}

// -------- LIST --------
$filter = $_GET['filter'] ?? 'all';
$where = '';
if ($filter === 'unread')  $where = "WHERE status = 'unread'";
if ($filter === 'read')    $where = "WHERE status = 'read'";
if ($filter === 'replied') $where = "WHERE status = 'replied'";

$messages = $conn->query(
    "SELECT * FROM contact_messages $where ORDER BY created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

// Counts
$counts = [
    'all'     => (int)$conn->query("SELECT COUNT(*) AS c FROM contact_messages")->fetch_assoc()['c'],
    'unread'  => (int)$conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE status='unread'")->fetch_assoc()['c'],
    'read'    => (int)$conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE status='read'")->fetch_assoc()['c'],
    'replied' => (int)$conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE status='replied'")->fetch_assoc()['c'],
];

$page_title = t('msg_manage_title');
require_once __DIR__ . '/includes/header.php';
?>

<section class="dash-page">
    <div class="container">

        <div class="editor-top">
            <a href="admin_dashboard.php" class="post-back">
                <i class="fas fa-arrow-left"></i> <?= htmlspecialchars(t('post_back_dash')) ?>
            </a>
            <h1><?= htmlspecialchars(t('msg_manage_title')) ?></h1>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div class="dash-flash dash-flash-success">
                <i class="fas fa-check-circle"></i>
                <?php
                    $msg_map = [
                        'deleted' => t('msg_deleted'),
                        'read'    => t('msg_marked_read'),
                        'unread'  => t('msg_marked_unread'),
                    ];
                    echo htmlspecialchars($msg_map[$_GET['msg']] ?? t('msg_updated'));
                ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="dash-flash dash-flash-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="dash-flash dash-flash-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Filter tabs -->
        <div class="msg-filters">
            <a href="admin_messages.php?filter=all"     class="<?= $filter === 'all' ? 'active' : '' ?>"><?= htmlspecialchars(t('msg_all')) ?> (<?= $counts['all'] ?>)</a>
            <a href="admin_messages.php?filter=unread"  class="<?= $filter === 'unread' ? 'active' : '' ?>"><?= htmlspecialchars(t('msg_unread')) ?> (<?= $counts['unread'] ?>)</a>
            <a href="admin_messages.php?filter=read"    class="<?= $filter === 'read' ? 'active' : '' ?>"><?= htmlspecialchars(t('msg_read')) ?> (<?= $counts['read'] ?>)</a>
            <a href="admin_messages.php?filter=replied" class="<?= $filter === 'replied' ? 'active' : '' ?>"><?= htmlspecialchars(t('msg_replied')) ?> (<?= $counts['replied'] ?>)</a>
        </div>

        <div class="editor-grid">

            <!-- Message list (left) -->
            <div class="editor-main">
                <div class="editor-panel">
                    <?php if (empty($messages)): ?>
                        <p class="dash-empty"><?= htmlspecialchars(t('msg_empty')) ?></p>
                    <?php else: ?>
                        <div class="msg-full-list">
                            <?php foreach ($messages as $m): ?>
                                <?php $is_open = $view && (int)$view['id'] === (int)$m['id']; ?>
                                <div class="msg-full-item <?= $m['status'] === 'unread' ? 'unread' : '' ?> <?= $is_open ? 'open' : '' ?>">
                                    <a href="admin_messages.php?filter=<?= htmlspecialchars($filter) ?>&view=<?= (int)$m['id'] ?>" class="msg-full-link">
                                        <div class="msg-full-icon">
                                            <i class="fas fa-envelope<?= $m['status'] === 'unread' ? '' : '-open' ?>"></i>
                                        </div>
                                        <div class="msg-full-body">
                                            <div class="msg-full-from">
                                                <strong><?= htmlspecialchars($m['name']) ?></strong>
                                                <span class="msg-mini-email">&lt;<?= htmlspecialchars($m['email']) ?>&gt;</span>
                                            </div>
                                            <div class="msg-full-subject"><?= htmlspecialchars($m['subject'] ?? '—') ?></div>
                                            <div class="msg-full-date">
                                                <?= htmlspecialchars(date('d.m.Y H:i', strtotime($m['created_at']))) ?>
                                                <?php if ($m['status'] === 'replied'): ?>
                                                    · <span class="dash-badge dash-badge-ok"><?= htmlspecialchars(t('msg_replied')) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detail panel (right) -->
            <div class="editor-side">
                <?php if ($view): ?>
                    <div class="editor-panel">
                        <h3 style="margin-bottom:14px;"><i class="fas fa-envelope-open-text" style="color:var(--accent);"></i>
                            <?= htmlspecialchars(t('msg_detail')) ?>
                        </h3>

                        <div class="msg-detail-meta">
                            <div><strong><?= htmlspecialchars($view['name']) ?></strong></div>
                            <div><a href="mailto:<?= htmlspecialchars($view['email']) ?>"><?= htmlspecialchars($view['email']) ?></a></div>
                            <div class="msg-full-date"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($view['created_at']))) ?></div>
                        </div>

                        <?php if (!empty($view['subject'])): ?>
                            <h4 class="msg-detail-subject"><?= htmlspecialchars($view['subject']) ?></h4>
                        <?php endif; ?>

                        <div class="msg-detail-body">
                            <?= nl2br(htmlspecialchars($view['message'])) ?>
                        </div>

                        <?php if (!empty($view['reply'])): ?>
                            <div class="msg-detail-reply">
                                <div class="msg-detail-reply-title"><?= htmlspecialchars(t('msg_your_reply')) ?></div>
                                <div><?= nl2br(htmlspecialchars($view['reply'])) ?></div>
                                <div class="msg-full-date"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($view['reply_date']))) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Reply form -->
                        <?php if (empty($view['reply'])): ?>
                            <form method="POST" action="admin_messages.php" class="msg-reply-form">
                                <input type="hidden" name="msg_id" value="<?= (int)$view['id'] ?>">
                                <label><?= htmlspecialchars(t('msg_reply')) ?></label>
                                <textarea name="reply_message" rows="4" required></textarea>
                                <button type="submit" name="reply_submit" value="1" class="btn btn-accent" style="width:100%;margin-top:10px;">
                                    <i class="fas fa-paper-plane"></i> <?= htmlspecialchars(t('msg_send_reply')) ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Quick actions -->
                        <div class="msg-detail-actions">
                            <a href="mailto:<?= htmlspecialchars($view['email']) ?>" class="btn btn-outline" style="flex:1;">
                                <i class="fas fa-reply"></i> <?= htmlspecialchars(t('msg_email_reply')) ?>
                            </a>
                            <?php if ($view['status'] === 'unread'): ?>
                                <a href="admin_messages.php?action=mark_read&id=<?= (int)$view['id'] ?>" class="btn btn-outline">
                                    <i class="fas fa-check"></i>
                                </a>
                            <?php else: ?>
                                <a href="admin_messages.php?action=mark_unread&id=<?= (int)$view['id'] ?>" class="btn btn-outline">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            <?php endif; ?>
                            <a href="admin_messages.php?action=delete&id=<?= (int)$view['id'] ?>"
                               class="btn" style="background:#dc2626;color:#fff;"
                               onclick="return confirm('<?= htmlspecialchars(t('msg_confirm_delete')) ?>');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="editor-panel" style="text-align:center;padding:40px 20px;">
                        <i class="fas fa-envelope-open-text" style="font-size:2.5rem;color:var(--accent);opacity:.4;"></i>
                        <p class="dash-empty" style="margin-top:14px;"><?= htmlspecialchars(t('msg_select_hint')) ?></p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
