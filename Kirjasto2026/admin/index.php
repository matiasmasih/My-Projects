<?php
// admin/index.php — Kirjasto 2026 Admin Dashboard

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// ---- Fetch Global Stats ----
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_members = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
$total_staff = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
$total_managers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
$total_books = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$total_copies = $pdo->query("SELECT COUNT(*) FROM book_copies")->fetchColumn();
$total_devices = $pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
$total_loans = $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn();
$overdue_loans = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'active' AND due_at < NOW()")->fetchColumn();
$total_fines = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fines")->fetchColumn();
$unpaid_fines = $pdo->query("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM fines WHERE status != 'paid'")->fetchColumn();

// ---- Recent Audit Logs ----
$recent_logs = $pdo->query("
    SELECT a.*, u.email 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 5
")->fetchAll();

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Welcome Header -->
<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800; letter-spacing: -0.02em;">System Overview</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Welcome back, System Administrator. Here is the global status.</p>
</div>

<!-- Primary Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-value"><?= $total_users ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-book"></i></div>
        <div>
            <div class="stat-value"><?= $total_books ?></div>
            <div class="stat-label">Total Books</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-laptop"></i></div>
        <div>
            <div class="stat-value"><?= $total_devices ?></div>
            <div class="stat-label">Total Devices</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <div class="stat-value"><?= $overdue_loans ?></div>
            <div class="stat-label">Overdue Loans</div>
        </div>
    </div>
</div>

<!-- Secondary Stats -->
<div class="grid-3" style="margin-bottom: 32px;">
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Members</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $total_members ?></div>
    </div>
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Staff</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $total_staff ?></div>
    </div>
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Managers</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $total_managers ?></div>
    </div>
</div>

<!-- Fines & Activity -->
<div class="grid-2" style="margin-bottom: 32px;">
    <div class="card">
        <h3><i class="fas fa-euro-sign"></i> Financial Overview</h3>
        <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border);">
            <span style="color: var(--text-muted);">Total Fines Issued</span>
            <span style="color: var(--text); font-weight: 700;">€<?= number_format($total_fines, 2) ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; padding: 12px 0;">
            <span style="color: var(--text-muted);">Outstanding Fines</span>
            <span style="color: var(--danger); font-weight: 700;">€<?= number_format($unpaid_fines, 2) ?></span>
        </div>
    </div>

    <div class="card">
        <h3><i class="fas fa-shield-alt"></i> Recent Activity</h3>
        <?php if (empty($recent_logs)): ?>
            <p style="color: var(--text-muted); padding: 20px 0;">No activity yet.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($recent_logs as $log): ?>
                    <li style="padding: 10px 0; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; font-size: 0.85rem;">
                        <div>
                            <strong style="color: var(--text);"><?= e($log['email'] ?? 'Guest') ?></strong>
                            <span style="color: var(--text-muted);"><?= e(str_replace('_', ' ', $log['action'])) ?></span>
                        </div>
                        <span style="color: var(--text-muted); font-size: 0.8rem;"><?= date('H:i', strtotime($log['created_at'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div class="grid-2" style="margin-top: 20px;">
        <a href="<?= e(SITE_URL) ?>admin/users.php" class="btn-outline" style="padding: 20px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
            <i class="fas fa-users" style="font-size: 1.8rem; color: #3b82f6;"></i>
            <span>Manage Users</span>
        </a>
        <a href="<?= e(SITE_URL) ?>admin/settings.php" class="btn-outline" style="padding: 20px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
            <i class="fas fa-cog" style="font-size: 1.8rem; color: #3b82f6;"></i>
            <span>System Settings</span>
        </a>
        <a href="<?= e(SITE_URL) ?>admin/reports.php" class="btn-outline" style="padding: 20px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
            <i class="fas fa-chart-line" style="font-size: 1.8rem; color: #3b82f6;"></i>
            <span>Global Reports</span>
        </a>
        <a href="<?= e(SITE_URL) ?>admin/backup.php" class="btn-outline" style="padding: 20px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
            <i class="fas fa-database" style="font-size: 1.8rem; color: #3b82f6;"></i>
            <span>Database Backup</span>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
