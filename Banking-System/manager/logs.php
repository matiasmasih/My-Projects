<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Verify Manager Role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$manager_check = $stmt->fetch();

if ($manager_check['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Fetch Logs
$logs = $pdo->query("
    SELECT a.*, u.email 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 100
")->fetchAll();

$page_title = 'Activity Logs';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">System Activity Logs</h1>
    <p style="color: var(--text-muted);">Monitor logins, registrations, and staff actions.</p>
</div>

<div class="card">
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>IP Address</th>
                    <th>Status</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--text);">
                            <?= e($log['email'] ?? 'Guest / Unknown') ?>
                        </td>
                        <td style="text-transform: capitalize; color: var(--text);">
                            <?= str_replace('_', ' ', $log['action']) ?>
                        </td>
                        <td style="color: var(--text-muted);">
                            <?= e($log['ip_address']) ?>
                        </td>
                        <td>
                            <span style="padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; 
                                background: <?= $log['status'] === 'success' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)' ?>; 
                                color: <?= $log['status'] === 'success' ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= e($log['status']) ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted);">
                            <?= date('M j, Y - H:i:s', strtotime($log['created_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">No activity logs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
