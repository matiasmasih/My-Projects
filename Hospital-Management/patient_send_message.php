<?php
session_start();
include 'config.php';

// Allow ONLY Patient (6)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 6) {
    header("Location: login.php");
    exit;
}

// Get patient info by email
$patient = null;
$patient_id = 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE email = ?");
    $stmt->execute([$_SESSION['email'] ?? '']);
    $patient = $stmt->fetch();
    $patient_id = $patient ? $patient['id'] : 0;
} catch (Exception $e) {}

// ============================================
// SUCCESS/ERROR MESSAGES
// ============================================
$success_message = '';
$error_message = '';

// ============================================
// HANDLE FORM SUBMIT
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_id = intval($_POST['recipient_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $priority = $_POST['priority'] ?? 'normal';

    if (empty($recipient_id) || empty($subject) || empty($body)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, body, priority, status, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 'unread', 0, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $recipient_id, $subject, $body, $priority]);

            $success_message = '✅ Message sent successfully!';
        } catch (Exception $e) {
            $error_message = 'Failed to send message: ' . $e->getMessage();
        }
    }
}

// ============================================
// GET RECIPIENTS (Doctors + Admin + Manager)
// ============================================
$recipients = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, r.name AS role
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.role_id IN (1, 2, 3)
        ORDER BY u.first_name ASC
    ");
    $recipients = $stmt->fetchAll();
} catch (Exception $e) {}

$page_title = 'Send Message';
include 'patient_header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h2>Send Message 💬</h2>
    <p>Send a message to a doctor, manager, or admin</p>
    <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">Loading...</div>
    </div>
</div>

<?php if (!empty($success_message)): ?>
<div class="alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="alert-error"><?php echo $error_message; ?></div>
<?php endif; ?>

<!-- Send Message Form -->
<div class="table-card">
    <h5><i class="bi bi-chat-dots"></i> New Message</h5>

    <?php if (count($recipients) > 0): ?>
    <form method="POST" action="" class="booking-form">

        <!-- Recipient -->
        <div class="bf-group">
            <label class="bf-label"><i class="bi bi-person-badge"></i> Recipient <span class="req">*</span></label>
            <div class="bf-select-wrap">
                <select name="recipient_id" required class="bf-select">
                    <option value="">-- Choose a recipient --</option>
                    <?php foreach ($recipients as $rec): ?>
                        <option value="<?php echo $rec['id']; ?>">
                            <?php echo htmlspecialchars($rec['first_name'] . ' ' . $rec['last_name']); ?>
                            — <?php echo htmlspecialchars($rec['role']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="bi bi-chevron-down bf-icon"></i>
            </div>
        </div>

        <!-- Subject -->
        <div class="bf-group">
            <label class="bf-label"><i class="bi bi-tag"></i> Subject <span class="req">*</span></label>
            <div class="bf-input-wrap">
                <input type="text" name="subject" required placeholder="Enter the message subject"
                       class="bf-input">
            </div>
        </div>

        <!-- Priority -->
        <div class="bf-group">
            <label class="bf-label"><i class="bi bi-flag"></i> Priority</label>
            <div class="bf-select-wrap">
                <select name="priority" class="bf-select">
                    <option value="low">🟢 Low</option>
                    <option value="normal" selected>🔵 Normal</option>
                    <option value="high">🟠 High</option>
                    <option value="urgent">🔴 Urgent</option>
                </select>
                <i class="bi bi-chevron-down bf-icon"></i>
            </div>
        </div>

        <!-- Message -->
        <div class="bf-group">
            <label class="bf-label"><i class="bi bi-chat-left-text"></i> Message <span class="req">*</span></label>
            <textarea name="body" rows="6" required placeholder="Type your message here..."
                      class="bf-textarea"></textarea>
        </div>

        <!-- Submit -->
        <div class="bf-group">
            <button type="submit" class="bf-submit">
                <i class="bi bi-send"></i> Send Message
            </button>
        </div>

    </form>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-exclamation-circle" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No recipients available
        </p>
    <?php endif; ?>
</div>

<style>
/* ============================================
   ALERTS
============================================ */
.alert-success {
    background: rgba(34, 197, 94, 0.15);
    color: #16a34a;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 600;
}

.alert-error {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 600;
}

/* ============================================
   FORM
============================================ */
.booking-form {
    display: grid;
    gap: 22px;
}

.bf-group {
    display: flex;
    flex-direction: column;
}

.bf-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 10px;
    font-size: 0.95rem;
}

.bf-label i {
    color: var(--primary);
    font-size: 1.05rem;
}

.req {
    color: #dc2626;
}

.bf-input-wrap,
.bf-select-wrap {
    position: relative;
    width: 100%;
}

.bf-input,
.bf-select {
    width: 100%;
    padding: 14px 45px 14px 18px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 1rem;
    background: var(--card-bg);
    color: var(--text);
    transition: all 0.3s;
    cursor: pointer;
}

.bf-input {
    padding-right: 18px;
}

.bf-input::placeholder {
    color: var(--text-muted);
}

.bf-input:hover,
.bf-select:hover {
    border-color: var(--primary-light);
}

.bf-input:focus,
.bf-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
}

.bf-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: none;
}

.bf-select option {
    background: var(--card-bg);
    color: var(--text);
    padding: 10px;
}

.bf-icon {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary);
    font-size: 1.1rem;
    pointer-events: none;
}

.bf-textarea {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 1rem;
    background: var(--card-bg);
    color: var(--text);
    transition: all 0.3s;
    resize: vertical;
    min-height: 140px;
}

.bf-textarea::placeholder {
    color: var(--text-muted);
}

.bf-textarea:hover {
    border-color: var(--primary-light);
}

.bf-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
}

.bf-submit {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
    padding: 16px 32px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 6px 20px rgba(14, 165, 233, 0.3);
    font-family: 'Inter', sans-serif;
}

.bf-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(14, 165, 233, 0.4);
}
</style>

<?php include 'patient_footer.php'; ?>
