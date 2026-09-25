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
// MY MESSAGES
// ============================================
$messages = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.*,
               CONCAT(u.first_name, ' ', u.last_name) AS sender_name
        FROM messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE m.patient_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    $messages = [];
}

// ============================================
// STATS
// ============================================
$total_messages = count($messages);
$unread_messages = 0;
$read_messages = 0;

foreach ($messages as $m) {
    if (($m['is_read'] ?? 0) == 0) {
        $unread_messages++;
    } else {
        $read_messages++;
    }
}

// Set page title
$page_title = 'Messages';

// Include shared header
include 'patient_header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h2>Messages 💬</h2>
    <p>View messages from your doctors and staff</p>
    <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">Loading...</div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-messages"><i class="bi bi-chat-dots"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_messages; ?>">0</h3>
            <p>Total Messages</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-invoices"><i class="bi bi-envelope-fill"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $unread_messages; ?>">0</h3>
            <p>Unread</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-prescriptions"><i class="bi bi-envelope-open-fill"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $read_messages; ?>">0</h3>
            <p>Read</p>
        </div>
    </div>
</div>

<!-- Messages Table -->
<div class="table-card">
    <h5><i class="bi bi-chat-dots"></i> All Messages</h5>
    <?php if (count($messages) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>From</th>
                    <th>Subject</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $m): ?>
                <tr>
                    <td><?php echo !empty($m['created_at']) ? date('M d, Y h:i A', strtotime($m['created_at'])) : '-'; ?></td>
                    <td><strong><?php echo htmlspecialchars($m['sender_name'] ?? 'System'); ?></strong></td>
                    <td><?php echo htmlspecialchars($m['subject'] ?? '(No subject)'); ?></td>
                    <td>
                        <?php if (($m['is_read'] ?? 0) == 0): ?>
                            <span class="status-badge status-issued">Unread</span>
                        <?php else: ?>
                            <span class="status-badge status-completed">Read</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-chat-dots" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No messages yet
        </p>
    <?php endif; ?>
</div>

<?php include 'patient_footer.php'; ?>
