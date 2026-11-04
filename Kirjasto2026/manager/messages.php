<?php
// manager/messages.php — Internal Messaging System

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$manager_id = $_SESSION['user_id'];
$flash = '';
$error = '';

// ---- SEND MESSAGE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    $receiver_id = (int)$_POST['receiver_id'];
    $content = trim($_POST['content']);

    if (empty($content)) {
        $error = 'Message cannot be empty.';
    } elseif ($receiver_id <= 0) {
        $error = 'Please select a recipient.';
    } else {
        try {
            $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)")
                ->execute([$manager_id, $receiver_id, $content]);
            $flash = 'Message sent.';
        } catch (PDOException $e) {
            $error = 'Failed to send message.';
        }
    }
}

// ---- MARK AS READ ----
if (isset($_GET['mark_read'])) {
    $sender_id = (int)$_GET['mark_read'];
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")
        ->execute([$sender_id, $manager_id]);
    header('Location: ' . SITE_URL . 'manager/messages.php');
    exit;
}

// ---- FETCH CONVERSATIONS ----
// Each "conversation" = latest message with each distinct user
$conversations = $pdo->prepare("
    SELECT 
        m.*,
        u.first_name, u.last_name, u.email, u.profile_image, u.role,
        (SELECT COUNT(*) FROM messages 
         WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) AS unread_count
    FROM messages m
    JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
    WHERE m.id IN (
        SELECT MAX(id) FROM messages 
        WHERE sender_id = ? OR receiver_id = ? 
        GROUP BY CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
    )
    ORDER BY m.created_at DESC
");
$conversations->execute([$manager_id, $manager_id, $manager_id, $manager_id, $manager_id]);
$conversations = $conversations->fetchAll();

// ---- ACTIVE CHAT ----
$active_user_id = (int)($_GET['user_id'] ?? 0);
$active_user = null;
$chat_messages = [];

if ($active_user_id > 0) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, profile_image, role FROM users WHERE id = ?");
    $stmt->execute([$active_user_id]);
    $active_user = $stmt->fetch();

    if ($active_user) {
        // Mark all as read
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")
            ->execute([$active_user_id, $manager_id]);

        // Fetch messages between manager and this user
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC
        ");
        $stmt->execute([$manager_id, $active_user_id, $active_user_id, $manager_id]);
        $chat_messages = $stmt->fetchAll();
    }
}

// ---- ALL USERS (for new message) ----
$all_users = $pdo->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE id != ? ORDER BY first_name ASC");
$all_users->execute([$manager_id]);
$all_users = $all_users->fetchAll();

$page_title = 'Messages';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Messages</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Communicate with members, staff, and admins.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px; min-height: 600px;">
    
    <!-- Left: Conversations List -->
    <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
        <div style="padding: 20px; border-bottom: 1px solid var(--border);">
            <h3 style="margin: 0; font-size: 1rem;"><i class="fas fa-comments"></i> Conversations (<?= count($conversations) ?>)</h3>
        </div>
        
        <div style="flex: 1; overflow-y: auto;">
            <?php if (empty($conversations)): ?>
                <div style="padding: 40px 20px; text-align: center; color: var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 12px;"></i>
                    <p style="font-size: 0.85rem;">No conversations yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <a href="?user_id=<?= $conv['sender_id'] == $manager_id ? $conv['receiver_id'] : $conv['sender_id'] ?>" 
                       style="display: block; padding: 16px 20px; border-bottom: 1px solid var(--border); text-decoration: none; transition: background 0.15s; <?= ($conv['sender_id'] == $manager_id ? $conv['receiver_id'] : $conv['sender_id']) == $active_user_id ? 'background: var(--bg-alt);' : '' ?>"
                       onmouseover="this.style.background='var(--bg-alt)'" onmouseout="this.style.background='<?= ($conv['sender_id'] == $manager_id ? $conv['receiver_id'] : $conv['sender_id']) == $active_user_id ? 'var(--bg-alt)' : 'transparent' ?>'">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <?php if (!empty($conv['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $conv['profile_image'])): ?>
                                <img src="<?= UPLOADS_URL . e($conv['profile_image']) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0;">
                            <?php else: ?>
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">
                                    <?= strtoupper(substr($conv['email'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="color: var(--text); font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($conv['first_name'] . ' ' . $conv['last_name']) ?></strong>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span style="background: var(--danger); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 700;"><?= $conv['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2px;">
                                    <small style="color: var(--text-muted); font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px;"><?= e($conv['content']) ?></small>
                                    <small style="color: var(--text-muted); font-size: 0.7rem; flex-shrink: 0;"><?= date('H:i', strtotime($conv['created_at'])) ?></small>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Chat Area or New Message -->
    <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
        <?php if ($active_user): ?>
            <!-- Active Chat Header -->
            <div style="padding: 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px;">
                <?php if (!empty($active_user['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $active_user['profile_image'])): ?>
                    <img src="<?= UPLOADS_URL . e($active_user['profile_image']) ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.9rem;">
                        <?= strtoupper(substr($active_user['email'], 0, 2)) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <strong style="display: block; color: var(--text);"><?= e($active_user['first_name'] . ' ' . $active_user['last_name']) ?></strong>
                    <small style="color: var(--text-muted); font-size: 0.8rem; text-transform: capitalize;"><?= e($active_user['role']) ?> · <?= e($active_user['email']) ?></small>
                </div>
            </div>

            <!-- Chat Messages -->
            <div style="flex: 1; overflow-y: auto; padding: 24px; background: var(--bg); min-height: 400px; max-height: 500px;">
                <?php if (empty($chat_messages)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                        <i class="fas fa-comment-dots" style="font-size: 2.5rem; opacity: 0.3; display: block; margin-bottom: 12px;"></i>
                        <p>No messages yet. Start the conversation.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($chat_messages as $msg): ?>
                        <?php $is_me = ($msg['sender_id'] == $manager_id); ?>
                        <div style="display: flex; justify-content: <?= $is_me ? 'flex-end' : 'flex-start' ?>; margin-bottom: 12px;">
                            <div style="max-width: 70%; padding: 12px 16px; border-radius: 16px; <?= $is_me ? 'background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-bottom-right-radius: 4px;' : 'background: var(--card-solid); color: var(--text); border: 1px solid var(--border); border-bottom-left-radius: 4px;' ?>">
                                <p style="margin: 0; font-size: 0.9rem; line-height: 1.5;"><?= nl2br(e($msg['content'])) ?></p>
                                <div style="font-size: 0.7rem; margin-top: 4px; opacity: 0.8; text-align: <?= $is_me ? 'right' : 'left' ?>;">
                                    <?= date('M j, H:i', strtotime($msg['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Message Input -->
            <div style="padding: 16px; border-top: 1px solid var(--border); background: var(--card-solid);">
                <form method="post" style="display: flex; gap: 12px;">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="receiver_id" value="<?= $active_user['id'] ?>">
                    <input type="text" name="content" required placeholder="Type a message..." style="flex: 1; padding: 12px 16px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 24px; color: var(--text); font-family: var(--font); font-size: 0.95rem; outline: none;">
                    <button type="submit" class="btn-primary" style="width: auto; padding: 12px 24px; border-radius: 24px; background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- New Message Compose -->
            <div style="padding: 24px; border-bottom: 1px solid var(--border);">
                <h3 style="margin: 0;"><i class="fas fa-pen"></i> New Message</h3>
            </div>
            <div style="padding: 24px; flex: 1;">
                <form method="post">
                    <input type="hidden" name="action" value="send_message">
                    <div class="form-group">
                        <label>Recipient</label>
                        <select name="receiver_id" required>
                            <option value="">— Select user —</option>
                            <?php foreach ($all_users as $u): ?>
                                <option value="<?= $u['id'] ?>">
                                    <?= e($u['first_name'] . ' ' . $u['last_name']) ?> (<?= e($u['role']) ?>) — <?= e($u['email']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="content" rows="6" required placeholder="Type your message..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
