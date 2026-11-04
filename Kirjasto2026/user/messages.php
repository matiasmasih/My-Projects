<?php
// user/messages.php — Member Messaging (Role-Aware Banner & Send To)

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || ($user['role'] !== 'member' && $user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$flash = '';
$error = '';

// ---- HANDLE NEW CONVERSATION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_conversation') {
    $subject = trim($_POST['subject'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = $_POST['category'] ?? 'general';
    $priority = $_POST['priority'] ?? 'normal';
    $send_to = $_POST['send_to'] ?? 'staff';

    if ($subject === '' || $content === '') {
        $error = 'Subject and message are required.';
    } else {
        try {
            $pdo->beginTransaction();
            $full_subject = "[To " . ucfirst($send_to) . "] " . $subject;

            $stmt = $pdo->prepare("INSERT INTO conversations (user_id, subject, last_message, last_at, status, category, priority) VALUES (?, ?, ?, NOW(), 'open', ?, ?)");
            $stmt->execute([$user_id, $full_subject, $content, $category, $priority]);
            $conversation_id = $pdo->lastInsertId();

            $pdo->prepare("INSERT INTO conversation_messages (conversation_id, sender_id, content) VALUES (?, ?, ?)")
                ->execute([$conversation_id, $user_id, $content]);

            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)")
                ->execute([$user_id, 'message.new_conversation', "Started conversation ID: $conversation_id to $send_to", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            $pdo->commit();
            $_SESSION['flash_success'] = 'Message sent to ' . ucfirst($send_to) . '.';
            header('Location: ' . SITE_URL . 'user/messages.php?conversation=' . $conversation_id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to send message: ' . $e->getMessage();
        }
    }
}

// ---- HANDLE REPLY ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reply') {
    $conversation_id = (int)($_POST['conversation_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    $stmt = $pdo->prepare("SELECT id, status FROM conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);
    $conv = $stmt->fetch();

    if (!$conv) {
        $error = 'Invalid conversation.';
    } elseif ($conv['status'] === 'closed') {
        $error = 'This conversation has been closed by staff.';
    } elseif ($content === '') {
        $error = 'Message cannot be empty.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO conversation_messages (conversation_id, sender_id, content) VALUES (?, ?, ?)")
                ->execute([$conversation_id, $user_id, $content]);
            $pdo->prepare("UPDATE conversations SET last_message = ?, last_at = NOW(), status = 'open' WHERE id = ?")
                ->execute([$content, $conversation_id]);
            $pdo->commit();
            $_SESSION['flash_success'] = 'Reply sent.';
            header('Location: ' . SITE_URL . 'user/messages.php?conversation=' . $conversation_id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to send reply: ' . $e->getMessage();
        }
    }
}

// ---- PICK VIEW ----
$conversation_id = isset($_GET['conversation']) ? (int)$_GET['conversation'] : 0;
$current_conv = null;
$messages = [];
$last_staff_reply = null;

if ($conversation_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);
    $current_conv = $stmt->fetch();

    if ($current_conv) {
        $stmt = $pdo->prepare("
            SELECT cm.*, u.role, u.first_name, u.last_name, u.branch_id
            FROM conversation_messages cm
            JOIN users u ON u.id = cm.sender_id
            WHERE cm.conversation_id = ?
            ORDER BY cm.created_at ASC
        ");
        $stmt->execute([$conversation_id]);
        $messages = $stmt->fetchAll();

        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($messages[$i]['sender_id'] != $user_id) {
                $last_staff_reply = $messages[$i];
                break;
            }
        }
    }
}

// ---- FETCH INBOX ----
$search = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, (SELECT COUNT(*) FROM conversation_messages cm WHERE cm.conversation_id = c.id) as msg_count FROM conversations c WHERE c.user_id = ?";
$params = [$user_id];
if ($search) {
    $sql .= " AND (c.subject LIKE ? OR c.last_message LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY c.last_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inbox = $stmt->fetchAll();

$page_title = 'Messages';
require_once __DIR__ . '/../includes/user_header.php';
?>

<!-- Hero Banner with Role Badges -->
<div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); padding: 40px; margin-bottom: 32px; position: relative; overflow: hidden;">
    <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text); margin-bottom: 8px;">Messages</h1>
            <p style="color: var(--text-muted); font-size: 1.1rem; margin: 0 0 16px 0;">Communicate directly with library staff.</p>
            
            <!-- Role Legend Badges -->
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 6px; background: rgba(14, 165, 233, 0.1); border: 1px solid rgba(14, 165, 233, 0.3); color: #0ea5e9; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                    <i class="fas fa-user-tie"></i> Staff
                </div>
                <div style="display: flex; align-items: center; gap: 6px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: #f59e0b; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                    <i class="fas fa-user-cog"></i> Manager
                </div>
                <div style="display: flex; align-items: center; gap: 6px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #ef4444; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                    <i class="fas fa-shield-alt"></i> Admin
                </div>
            </div>
        </div>

        <button onclick="document.getElementById('newMessageForm').style.display='block'; document.getElementById('newMessageForm').scrollIntoView({behavior:'smooth'});" class="btn btn-primary" style="padding: 14px 28px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; font-weight: 700; cursor: pointer; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> New Message
        </button>
    </div>
    <i class="fas fa-comments" style="position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: #10b981; opacity: 0.05; z-index: 1; transform: rotate(-15deg);"></i>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="flash flash-success" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; color: #10b981; font-weight: 600;">
        <i class="fas fa-check-circle"></i> <?= e($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if ($error): ?>
    <div class="flash flash-error" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; color: #ef4444; font-weight: 600;">
        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- New Message Form -->
<div id="newMessageForm" class="card" style="display: none; margin-bottom: 32px; border: 1px solid rgba(16, 185, 129, 0.3);">
    <h3><i class="fas fa-pen" style="color: #10b981;"></i> New Message</h3>
    <form method="POST" style="margin-top: 20px;">
        <input type="hidden" name="action" value="new_conversation">
        
        <div class="grid-2">
            <div class="form-group">
                <label>Send To</label>
                <select name="send_to" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-weight: 600;">
                    <option value="staff">👤 <?= e($branch_name) ?> Staff</option>
                    <option value="manager">💼 <?= e($branch_name) ?> Manager</option>
                    <option value="admin">🛡️ Central Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                    <option value="general">📚 General Question</option>
                    <option value="book_issue">📖 Book Issue</option>
                    <option value="device_issue">💻 Device Issue</option>
                    <option value="fine_dispute">💰 Fine Dispute</option>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>Priority</label>
                <select name="priority" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                    <option value="normal">🟢 Normal</option>
                    <option value="urgent">🔴 Urgent</option>
                </select>
            </div>
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" required placeholder="e.g. Question about my loan" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
            </div>
        </div>

        <div class="form-group">
            <label>Message</label>
            <textarea name="content" required rows="4" placeholder="Type your message here..." style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); resize: vertical;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" onclick="document.getElementById('newMessageForm').style.display='none';" class="btn btn-secondary" style="padding: 10px 20px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--text); cursor: pointer; font-weight: 600;">Cancel</button>
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px; border-radius: 8px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; cursor: pointer; font-weight: 600;"><i class="fas fa-paper-plane"></i> Send Message</button>
        </div>
    </form>
</div>

<!-- Two-Column Layout -->
<div style="display: grid; grid-template-columns: 340px 1fr; gap: 24px; align-items: start;">
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 20px; border-bottom: 1px solid var(--border);">
            <h3 style="margin: 0 0 12px 0;"><i class="fas fa-inbox"></i> Inbox</h3>
            <form method="GET" style="display: flex; gap: 6px;">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search..." style="flex: 1; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 0.85rem;">
                <button type="submit" style="padding: 8px 12px; border-radius: 6px; background: var(--bg-alt); border: 1px solid var(--border); color: var(--text); cursor: pointer;"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <?php if (empty($inbox)): ?>
            <div style="padding: 40px 20px; text-align: center;">
                <i class="fas fa-envelope-open" style="font-size: 3rem; color: var(--border); margin-bottom: 16px;"></i>
                <p style="color: var(--text-muted); margin: 0; font-size: 0.9rem;">No messages found.</p>
            </div>
        <?php else: ?>
            <div style="max-height: 600px; overflow-y: auto;">
                <?php foreach ($inbox as $conv):
                    $is_active = ($conv['id'] == $conversation_id);
                    $is_urgent = ($conv['priority'] === 'urgent');
                ?>
                    <a href="?conversation=<?= $conv['id'] ?>" style="display: block; padding: 16px 20px; text-decoration: none; border-bottom: 1px solid var(--border); background: <?= $is_active ? 'rgba(16, 185, 129, 0.1)' : 'transparent' ?>; border-left: 3px solid <?= $is_active ? '#10b981' : 'transparent' ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <strong style="color: var(--text); font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?php if ($is_urgent): ?><i class="fas fa-exclamation-circle" style="color: #ef4444; margin-right: 4px;"></i><?php endif; ?>
                                <?= e($conv['subject'] ?: 'Untitled') ?>
                            </strong>
                        </div>
                        <div style="color: var(--text-muted); font-size: 0.8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 6px;"><?= e($conv['last_message'] ?: '') ?></div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: var(--text-muted);">
                            <span><i class="fas fa-comment"></i> <?= $conv['msg_count'] ?></span>
                            <span><?= $conv['last_at'] ? date('d M, H:i', strtotime($conv['last_at'])) : '' ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card" style="padding: 0; overflow: hidden; min-height: 600px; display: flex; flex-direction: column;">
        <?php if ($current_conv): ?>
            <div style="padding: 20px; border-bottom: 1px solid var(--border); background: var(--bg-alt);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0 0 4px 0; font-size: 1.1rem;">
                            <?php if ($current_conv['priority'] === 'urgent'): ?><span style="color: #ef4444; font-size: 0.8rem; background: rgba(239,68,68,0.1); padding: 2px 8px; border-radius: 4px; margin-right: 8px;">URGENT</span><?php endif; ?>
                            <?= e($current_conv['subject'] ?: 'Untitled') ?>
                        </h3>
                        <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                            <span>Conversation with:</span>
                            <?php if ($last_staff_reply): ?>
                                <?php 
                                    $reply_branch = $last_staff_reply['branch_id'] ? get_branch_name($last_staff_reply['branch_id']) : 'Central';
                                    $role_icon = $last_staff_reply['role'] === 'admin' ? '🛡️' : ($last_staff_reply['role'] === 'manager' ? '💼' : '👤');
                                    $role_color = $last_staff_reply['role'] === 'admin' ? '#ef4444' : ($last_staff_reply['role'] === 'manager' ? '#f59e0b' : '#0ea5e9');
                                ?>
                                <strong style="color: var(--text);"><?= $role_icon ?> <?= e($last_staff_reply['first_name'] . ' ' . $last_staff_reply['last_name']) ?></strong>
                                <span style="background: <?= $role_color ?>15; color: <?= $role_color ?>; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; font-size: 0.65rem; font-weight: 800;"><?= e($last_staff_reply['role']) ?></span>
                                <span>· <?= e($reply_branch) ?></span>
                            <?php else: ?>
                                <strong style="color: var(--text);">🏢 <?= e($branch_name) ?> Staff</strong>
                                <span style="font-size: 0.75rem; font-style: italic;">(Awaiting first reply)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="?" style="font-size: 0.85rem; color: var(--text-muted); text-decoration: none;"><i class="fas fa-times"></i></a>
                </div>
            </div>

            <div style="flex: 1; padding: 20px; overflow-y: auto; max-height: 500px; display: flex; flex-direction: column; gap: 16px;">
                <?php if (empty($messages)): ?>
                    <p style="color: var(--text-muted); text-align: center;">No messages yet.</p>
                <?php else: ?>
                    <?php foreach ($messages as $msg):
                        $is_member = ($msg['sender_id'] == $user_id);
                        $sender_name = $is_member ? 'You' : trim($msg['first_name'] . ' ' . $msg['last_name']);
                        $sender_role = ucfirst($msg['role']);
                        
                        $role_color = 'var(--text-muted)';
                        $role_bg = 'var(--bg-alt)';
                        if (!$is_member) {
                            if ($msg['role'] === 'admin') { $role_color = '#ef4444'; $role_bg = 'rgba(239,68,68,0.1)'; }
                            elseif ($msg['role'] === 'manager') { $role_color = '#f59e0b'; $role_bg = 'rgba(245,158,11,0.1)'; }
                            elseif ($msg['role'] === 'staff') { $role_color = '#0ea5e9'; $role_bg = 'rgba(14,165,233,0.1)'; }
                        }
                    ?>
                        <div style="display: flex; flex-direction: column; align-items: <?= $is_member ? 'flex-end' : 'flex-start' ?>;">
                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                                <?php if (!$is_member): ?>
                                    <span style="background: <?= $role_bg ?>; color: <?= $role_color ?>; padding: 2px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase; font-size: 0.6rem;"><?= e($sender_role) ?></span>
                                <?php endif; ?>
                                <strong><?= e($sender_name) ?></strong> · <?= date('d M, H:i', strtotime($msg['created_at'])) ?>
                            </div>
                            <div style="max-width: 70%; padding: 12px 16px; border-radius: 14px; font-size: 0.9rem; line-height: 1.5; word-wrap: break-word;
                                <?= $is_member
                                    ? 'background: linear-gradient(135deg, #10b981, #059669); color: white; border-bottom-right-radius: 4px;'
                                    : 'background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); border-bottom-left-radius: 4px;' ?>">
                                <?= nl2br(e($msg['content'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($current_conv['status'] === 'open'): ?>
                <div style="padding: 16px; border-top: 1px solid var(--border); background: var(--bg-alt);">
                    <form method="POST" style="display: flex; gap: 8px; align-items: center;">
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="conversation_id" value="<?= $current_conv['id'] ?>">
                        <textarea name="content" required rows="1" placeholder="Type your reply..." style="flex: 1; padding: 10px 14px; border-radius: 20px; border: 1px solid var(--border); background: var(--bg); color: var(--text); resize: none; font-family: inherit; font-size: 0.9rem;"></textarea>
                        <button type="submit" class="btn-primary" style="padding: 10px 20px; border-radius: 20px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; cursor: pointer; font-weight: 600;"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            <?php else: ?>
                <div style="padding: 16px; border-top: 1px solid var(--border); background: var(--bg-alt); text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                    <i class="fas fa-lock"></i> This conversation has been closed by library staff.
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; text-align: center;">
                <i class="fas fa-comments" style="font-size: 4rem; color: var(--border); margin-bottom: 16px;"></i>
                <h3 style="color: var(--text); margin-bottom: 8px;">Select a conversation</h3>
                <p style="color: var(--text-muted); margin: 0;">Choose a conversation from the inbox, or start a new one.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
