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
$my_id  = (int)$_SESSION['user_id'];

$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ============================================
// HANDLE SEND MESSAGE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipient_type = $_POST['recipient_type'] ?? '';
    $subject        = trim($_POST['subject'] ?? '');
    $body           = trim($_POST['message'] ?? '');
    $priority       = $_POST['priority'] ?? 'normal';

    $allowed_types = ['admin', 'manager', 'doctor'];
    if (!in_array($recipient_type, $allowed_types)) {
        $_SESSION['flash_error'] = "Invalid recipient.";
    } elseif ($subject === '' || $body === '') {
        $_SESSION['flash_error'] = "Please fill in subject and message.";
    } elseif (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
        $priority = 'normal';
    } else {
        try {
            // Determine recipient_id
            $recipient_id = 0;
            $rrole_id = 0;
            if ($recipient_type === 'admin')   $rrole_id = 1;
            if ($recipient_type === 'manager') $rrole_id = 2;
            if ($recipient_type === 'doctor')  $rrole_id = 3;

            // Find a user with that role (first match)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role_id = ? AND id != ? ORDER BY id LIMIT 1");
            $stmt->execute([$rrole_id, $my_id]);
            $recipient_id = (int)($stmt->fetchColumn() ?: 0);

            if ($recipient_id <= 0) {
                $_SESSION['flash_error'] = "No user found with role: " . $recipient_type;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO messages (sender_id, recipient_id, recipient_type, subject, body, priority, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'unread')
                ");
                $stmt->execute([$my_id, $recipient_id, $recipient_type, $subject, $body, $priority]);
                $_SESSION['flash_success'] = "Message sent to " . ucfirst($recipient_type) . ".";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
        }
    }
    header("Location: messages.php");
    exit;
}

// ============================================
// FETCH SENT MESSAGES
// ============================================
$sent = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.*, u.first_name, u.last_name
        FROM messages m
        LEFT JOIN users u ON m.recipient_id = u.id
        WHERE m.sender_id = ?
          AND (m.is_deleted = 0 OR m.is_deleted IS NULL)
        ORDER BY m.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$my_id]);
    $sent = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$page_title = 'Send Message';
include 'messages_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- PICK RECIPIENT -->
<h5 style="font-weight:700; margin-bottom:14px; color: var(--text);">
    <i class="bi bi-person-plus" style="color: var(--primary);"></i> Choose Recipient
</h5>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="recipient-card" onclick="pickRecipient('admin', this)">
            <div class="recipient-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="recipient-title">Message Admin</div>
            <div class="recipient-sub">System administrator</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="recipient-card" onclick="pickRecipient('manager', this)">
            <div class="recipient-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <i class="bi bi-person-gear"></i>
            </div>
            <div class="recipient-title">Message Manager</div>
            <div class="recipient-sub">Hospital manager</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="recipient-card" onclick="pickRecipient('doctor', this)">
            <div class="recipient-icon" style="background: linear-gradient(135deg, #14b8a6, #0d9488);">
                <i class="bi bi-heart-pulse"></i>
            </div>
            <div class="recipient-title">Message Doctor</div>
            <div class="recipient-sub">Medical doctors</div>
        </div>
    </div>
</div>

<!-- MESSAGE FORM -->
<div class="table-card" id="messageFormCard" style="display:none;">
    <h5><i class="bi bi-send"></i> Compose Message to <span id="recipientLabel"></span></h5>

    <form method="POST">
        <input type="hidden" name="recipient_type" id="recipientTypeField">

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Subject <span style="color:#ef4444;">*</span></label>
                <input type="text" name="subject" class="form-control" required placeholder="What is this about?">
            </div>
            <div class="col-md-4">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="low">Low</option>
                    <option value="normal" selected>Normal</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>

            <div class="col-md-12">
                <label class="form-label">Message <span style="color:#ef4444;">*</span></label>
                <textarea name="message" class="form-control" rows="6" required placeholder="Type your message..."></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" name="send_message" class="btn btn-add"><i class="bi bi-send"></i> Send Message</button>
            <button type="button" class="btn btn-secondary-custom" onclick="cancelForm()">Cancel</button>
        </div>
    </form>
</div>

<!-- SENT MESSAGES -->
<div class="table-card">
    <h5><i class="bi bi-send-check"></i> Recently Sent (<?= count($sent) ?>)</h5>

    <?php if (count($sent) > 0): ?>
    <div style="overflow-x: auto;">
        <table style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align:left; padding:10px 12px; color: var(--text-muted); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid var(--border);">To</th>
                    <th style="text-align:left; padding:10px 12px; color: var(--text-muted); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid var(--border);">Subject</th>
                    <th style="text-align:left; padding:10px 12px; color: var(--text-muted); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid var(--border);">Priority</th>
                    <th style="text-align:left; padding:10px 12px; color: var(--text-muted); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid var(--border);">Status</th>
                    <th style="text-align:left; padding:10px 12px; color: var(--text-muted); font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; border-bottom:2px solid var(--border);">Sent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sent as $s): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding:10px 12px;">
                            <strong><?= htmlspecialchars(trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')) ?: '-') ?></strong>
                            <span style="color: var(--text-muted); font-size:0.8rem;">(<?= htmlspecialchars(ucfirst($s['recipient_type'] ?? '')) ?>)</span>
                        </td>
                        <td style="padding:10px 12px;"><?= htmlspecialchars($s['subject'] ?? '-') ?></td>
                        <td style="padding:10px 12px;">
                            <span style="padding:2px 8px; border-radius:4px; font-size:0.72rem; font-weight:600; color:white; background:
                                <?php
                                    $p = $s['priority'] ?? 'normal';
                                    if ($p === 'urgent') echo '#ef4444';
                                    elseif ($p === 'high') echo '#f59e0b';
                                    elseif ($p === 'low') echo '#10b981';
                                    else echo '#3b82f6';
                                ?>;">
                                <?= ucfirst(htmlspecialchars($p)) ?>
                            </span>
                        </td>
                        <td style="padding:10px 12px;">
                            <span style="color: <?= ($s['status'] ?? '') === 'read' ? '#22c55e' : '#f59e0b' ?>; font-weight:600; font-size:0.85rem;">
                                <?= ucfirst(htmlspecialchars($s['status'] ?? 'unread')) ?>
                            </span>
                        </td>
                        <td style="padding:10px 12px; color: var(--text-muted); font-size:0.85rem;">
                            <?= !empty($s['created_at']) ? date('M d, Y H:i', strtotime($s['created_at'])) : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-send" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No messages sent yet.
        </p>
    <?php endif; ?>
</div>

<style>
    .recipient-card {
        background: var(--card-bg);
        border: 2px solid var(--border);
        border-radius: 14px;
        padding: 24px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .recipient-card:hover {
        border-color: var(--primary);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(20,184,166,.15);
    }
    .recipient-card.picked {
        border-color: var(--primary);
        background: #f0fdfa;
        box-shadow: 0 8px 20px rgba(20,184,166,.25);
    }
    .recipient-icon {
        width: 56px; height: 56px; border-radius: 14px;
        color: white; font-size: 1.5rem;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 12px;
    }
    .recipient-title { font-weight: 700; font-size: 1rem; color: var(--text); margin-bottom: 4px; }
    .recipient-sub { font-size: 0.85rem; color: var(--text-muted); }
    [data-theme="dark"] .recipient-card.picked { background: #134e4a; }
</style>

<script>
function pickRecipient(type, el) {
    document.querySelectorAll('.recipient-card').forEach(c => c.classList.remove('picked'));
    el.classList.add('picked');

    const label = type.charAt(0).toUpperCase() + type.slice(1);
    document.getElementById('recipientLabel').textContent = label;
    document.getElementById('recipientTypeField').value = type;

    const card = document.getElementById('messageFormCard');
    card.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cancelForm() {
    document.getElementById('messageFormCard').style.display = 'none';
    document.querySelectorAll('.recipient-card').forEach(c => c.classList.remove('picked'));
}
</script>

<?php include 'messages_footer.php'; ?>
