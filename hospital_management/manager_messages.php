<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('messages', 'login.php');
$role   = current_role();

if ($role !== 'manager') {
    header("Location: " . ($role === 'admin' ? 'admin_messages.php' : 'messages.php'));
    exit;
}

$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$my_id = (int)$_SESSION['user_id'];

// ============================================
// HANDLE MARK AS READ
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $mid = (int)($_POST['message_id'] ?? 0);
    if ($mid > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE messages SET status='read', is_read=1, read_at=NOW() WHERE id=? AND recipient_id=?");
            $stmt->execute([$mid, $my_id]);
            $_SESSION['flash_success'] = "Message marked as read.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Update failed: " . $e->getMessage();
        }
    }
    header("Location: manager_messages.php");
    exit;
}

// ============================================
// HANDLE DELETE (soft delete via is_deleted)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $mid = (int)($_POST['message_id'] ?? 0);
    if ($mid > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE messages SET is_deleted=1 WHERE id=? AND recipient_id=?");
            $stmt->execute([$mid, $my_id]);
            $_SESSION['flash_success'] = "Message deleted.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    }
    header("Location: manager_messages.php");
    exit;
}

// ============================================
// HANDLE REPLY
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $original_id   = (int)($_POST['original_message_id'] ?? 0);
    $recipient_id  = (int)($_POST['recipient_id'] ?? 0);
    $subject       = trim($_POST['reply_subject'] ?? '');
    $body          = trim($_POST['reply_body'] ?? '');

    if ($subject === '' || $body === '') {
        $_SESSION['flash_error'] = "Please fill in subject and message.";
    } elseif ($original_id <= 0 || $recipient_id <= 0) {
        $_SESSION['flash_error'] = "Invalid reply target.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (parent_id, sender_id, recipient_id, recipient_type, subject, body, priority, status)
                VALUES (?, ?, ?, 'user', ?, ?, 'normal', 'unread')
            ");
            $stmt->execute([$original_id, $my_id, $recipient_id, $subject, $body]);
            $_SESSION['flash_success'] = "Reply sent.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Reply failed: " . $e->getMessage();
        }
    }
    header("Location: manager_messages.php");
    exit;
}

// ============================================
// FETCH MESSAGES
// ============================================
$messages = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.*,
               u.first_name, u.last_name, u.email, u.role_id,
               (SELECT COUNT(*) FROM messages WHERE parent_id = m.id) AS reply_count
        FROM messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE m.recipient_id = ?
          AND (m.is_deleted = 0 OR m.is_deleted IS NULL)
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$my_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$total_messages = count($messages);
$unread_count = 0;
foreach ($messages as $m) {
    if (($m['status'] ?? '') === 'unread') $unread_count++;
}
$read_count = $total_messages - $unread_count;

// Fetch templates
$templates = [];
try {
    $templates = $pdo->query("SELECT id, title, category, body FROM message_templates ORDER BY category, title")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$role_names = [1=>'Admin', 2=>'Manager', 3=>'Doctor', 4=>'Staff', 5=>'Nurse', 6=>'Patient'];

$page_title = 'Manager Messages';
include 'messages_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- STATS -->
<div class="d-flex gap-3 mb-3 flex-wrap">
    <span class="badge" style="background: var(--primary); font-size: 0.9rem; padding: 10px 16px;">
        <i class="bi bi-inbox"></i> Total: <?= $total_messages ?>
    </span>
    <span class="badge" style="background: #f59e0b; font-size: 0.9rem; padding: 10px 16px;">
        <i class="bi bi-envelope"></i> Unread: <?= $unread_count ?>
    </span>
    <span class="badge" style="background: #22c55e; font-size: 0.9rem; padding: 10px 16px;">
        <i class="bi bi-envelope-open"></i> Read: <?= $read_count ?>
    </span>
</div>

<!-- TOOLBAR -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search messages...">
        <button class="btn btn-add" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>
    <a href="messages.php" class="btn btn-add">
        <i class="bi bi-send"></i> Send Message
    </a>
</div>

<!-- MESSAGES LIST -->
<div class="table-card">
    <h5><i class="bi bi-inbox"></i> Inbox (<?= $total_messages ?>)</h5>

    <?php if ($total_messages === 0): ?>
        <p style="color: var(--text-muted); text-align: center; padding: 40px;">
            <i class="bi bi-inbox" style="font-size: 3rem; display: block; margin-bottom: 12px;"></i>
            No messages yet.
        </p>
    <?php else: ?>
        <div id="messageList">
        <?php foreach ($messages as $m): ?>
            <?php
                $is_unread = (($m['status'] ?? '') === 'unread');
                $sender = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
                $initial = strtoupper(substr($m['first_name'] ?? '?', 0, 1));
                $role_lbl = $role_names[$m['role_id'] ?? 0] ?? 'User';

                $prio = $m['priority'] ?? 'normal';
                $prio_color = '#3b82f6';
                if ($prio === 'urgent') $prio_color = '#ef4444';
                elseif ($prio === 'high') $prio_color = '#f59e0b';
                elseif ($prio === 'low') $prio_color = '#10b981';
            ?>
            <div class="msg-item <?= $is_unread ? 'msg-unread' : '' ?>" data-msg-id="<?= (int)$m['id'] ?>">
                <div class="msg-left">
                    <div class="msg-avatar"><?= htmlspecialchars($initial) ?></div>
                </div>
                <div class="msg-main">
                    <div class="msg-header-line">
                        <strong><?= htmlspecialchars($m['subject'] ?? '(no subject)') ?></strong>
                        <span class="prio-badge" style="background:<?= $prio_color ?>;"><?= ucfirst(htmlspecialchars($prio)) ?></span>
                        <?php if (($m['reply_count'] ?? 0) > 0): ?>
                            <span class="reply-count"><?= (int)$m['reply_count'] ?> replies</span>
                        <?php endif; ?>
                    </div>

                    <div class="msg-from">
                        <strong><?= htmlspecialchars($sender) ?></strong>
                        <span class="role-chip"><?= htmlspecialchars($role_lbl) ?></span>
                        <span class="msg-dot">•</span>
                        <span class="msg-email"><?= htmlspecialchars($m['email'] ?? '') ?></span>
                    </div>

                    <div class="msg-body"><?= nl2br(htmlspecialchars($m['body'] ?? '')) ?></div>

                    <div class="msg-meta">
                        <i class="bi bi-clock"></i>
                        <?= !empty($m['created_at']) ? date('M d, Y \a\t g:i A', strtotime($m['created_at'])) : '-' ?>
                        <?php if ($is_unread): ?>
                            <span class="new-badge">New</span>
                        <?php endif; ?>
                        <span style="margin-left:10px; color:#94a3b8;">#<?= (int)$m['id'] ?></span>
                    </div>

                    <div class="msg-actions">
                        <button type="button" class="btn btn-add" onclick="toggleReply(<?= (int)$m['id'] ?>)">
                            <i class="bi bi-reply"></i> Reply
                        </button>
                        <?php if ($is_unread): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" name="mark_read" class="btn btn-edit"><i class="bi bi-check2"></i> Mark Read</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?');">
                            <input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
                            <button type="submit" name="delete_message" class="btn btn-delete"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </div>

                    <!-- REPLY FORM (hidden by default) -->
                    <div class="reply-box" id="reply-box-<?= (int)$m['id'] ?>" style="display:none;">
                        <form method="POST">
                            <input type="hidden" name="original_message_id" value="<?= (int)$m['id'] ?>">
                            <input type="hidden" name="recipient_id" value="<?= (int)($m['sender_id'] ?? 0) ?>">

                            <div class="mb-2">
                                <label class="form-label">Subject</label>
                                <input type="text" name="reply_subject" class="form-control" value="Re: <?= htmlspecialchars($m['subject'] ?? '') ?>" required>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Quick Reply Templates</label>
                                <div class="template-buttons">
                                    <?php foreach ($templates as $t): ?>
                                        <button type="button" class="template-btn"
                                                data-target="reply-body-<?= (int)$m['id'] ?>"
                                                data-body="<?= htmlspecialchars($t['body']) ?>">
                                            <?= htmlspecialchars($t['title']) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Your Reply</label>
                                <textarea id="reply-body-<?= (int)$m['id'] ?>" name="reply_body" class="form-control" rows="4" required placeholder="Type your reply or pick a template above..."></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="reply_message" class="btn btn-add"><i class="bi bi-send"></i> Send Reply</button>
                                <button type="button" class="btn btn-secondary-custom" onclick="toggleReply(<?= (int)$m['id'] ?>)">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .msg-item {
        display: flex;
        gap: 14px;
        padding: 18px 16px;
        border: 1px solid var(--border);
        border-radius: 12px;
        margin-bottom: 12px;
        background: var(--card-bg);
        transition: all 0.2s;
    }
    .msg-item:hover { border-color: var(--primary); }
    .msg-item.msg-unread {
        background: linear-gradient(135deg, var(--card-bg) 0%, #f0fdfa 100%);
        border-left: 4px solid var(--primary);
    }
    .msg-left { flex-shrink: 0; }
    .msg-avatar {
        width: 44px; height: 44px; border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white; font-weight: 700; font-size: 1.1rem;
        display: flex; align-items: center; justify-content: center;
    }
    .msg-main { flex: 1; min-width: 0; }
    .msg-header-line {
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 4px; flex-wrap: wrap;
    }
    .msg-header-line strong { font-size: 1rem; color: var(--text); }
    .prio-badge {
        color: white; padding: 2px 8px; border-radius: 4px;
        font-size: 0.68rem; font-weight: 600; text-transform: uppercase;
    }
    .reply-count {
        background: rgba(59,130,246,.15); color: #2563eb;
        padding: 2px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 600;
    }
    .msg-from {
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 8px; flex-wrap: wrap; font-size: 0.9rem;
    }
    .role-chip {
        background: rgba(20,184,166,.12); color: #0d9488;
        padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 600;
    }
    .msg-dot { color: #cbd5e1; }
    .msg-email { color: var(--text-muted); font-size: 0.85rem; }
    .msg-body {
        color: var(--text); line-height: 1.6;
        margin-bottom: 10px; word-break: break-word;
    }
    .msg-meta {
        color: var(--text-muted); font-size: 0.82rem; margin-bottom: 12px;
    }
    .new-badge {
        background: #f59e0b; color: white; padding: 2px 8px;
        border-radius: 20px; font-size: 0.7rem; font-weight: 600; margin-left: 8px;
    }
    .msg-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .reply-box {
        margin-top: 14px; padding: 16px; background: #f8fafc;
        border-left: 4px solid var(--primary); border-radius: 8px;
    }
    .template-buttons { display: flex; flex-wrap: wrap; gap: 6px; }
    .template-btn {
        background: #f0fdfa; color: #0d9488; border: 1px solid #14b8a6;
        padding: 5px 12px; border-radius: 20px; font-size: 0.78rem;
        cursor: pointer; transition: all 0.2s;
    }
    .template-btn:hover { background: #14b8a6; color: white; }
    [data-theme="dark"] .msg-item.msg-unread {
        background: linear-gradient(135deg, #1e293b 0%, #134e4a 100%);
    }
    [data-theme="dark"] .reply-box {
        background: #0f172a;
    }
</style>

<script>
function toggleReply(id) {
    const box = document.getElementById('reply-box-' + id);
    if (!box) return;
    box.style.display = (box.style.display === 'none' || !box.style.display) ? 'block' : 'none';
}

// Template button -> fill textarea
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('template-btn')) {
        const targetId = e.target.getAttribute('data-target');
        const body = e.target.getAttribute('data-body');
        const ta = document.getElementById(targetId);
        if (ta) {
            if (ta.value.trim() === '') ta.value = body;
            else ta.value += "\n\n" + body;
            ta.focus();
        }
    }
});

// Search filter
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const list = document.getElementById('messageList');

function filterList() {
    if (!list) return;
    const filter = (searchInput?.value || '').toLowerCase();
    const items = list.querySelectorAll('.msg-item');
    items.forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterList);
if (searchButton) searchButton.addEventListener('click', filterList);
</script>

<?php include 'messages_footer.php'; ?>
