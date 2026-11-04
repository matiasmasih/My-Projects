<?php
// admin/reports.php — Global System Reports

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

// ---- Global User Stats ----
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_members = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
$total_staff = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn();
$total_managers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn();
$total_admins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

// ---- Library Stats ----
$total_books = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$total_copies = $pdo->query("SELECT COUNT(*) FROM book_copies")->fetchColumn();
$total_devices = $pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
$total_loans = $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn();
$active_loans = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'active'")->fetchColumn();
$overdue_loans = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'active' AND due_at < NOW()")->fetchColumn();

// ---- Financial Stats ----
$total_fines = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fines")->fetchColumn();
$paid_fines = $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM fines")->fetchColumn();
$unpaid_fines = $total_fines - $paid_fines;
$total_receipts = $pdo->query("SELECT COUNT(*) FROM receipts")->fetchColumn();

// ---- Recent Signups (Last 10) ----
$recent_users = $pdo->query("
    SELECT id, first_name, last_name, email, role, profile_image, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// ---- Recent Activity (Last 10 Audit Logs) ----
$recent_logs = $pdo->query("
    SELECT a.*, u.email 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 10
")->fetchAll();

// ---- Monthly Growth (Last 6 Months) ----
$monthly_growth = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
           COUNT(*) AS count
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
")->fetchAll();

$page_title = 'Global Reports';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Global Reports</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">System-wide analytics across all users, books, and devices.</p>
</div>

<!-- User Breakdown -->
<div class="grid-3" style="margin-bottom: 32px;">
    <div class="card" style="border-left: 4px solid var(--danger);">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Admins</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $total_admins ?></div>
    </div>
    <div class="card" style="border-left: 4px solid var(--warning);">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Managers</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $total_managers ?></div>
    </div>
    <div class="card" style="border-left: 4px solid var(--primary);">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Staff</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $total_staff ?></div>
    </div>
</div>

<!-- Main Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-value"><?= $total_members ?></div>
            <div class="stat-label">Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-book"></i></div>
        <div>
            <div class="stat-value"><?= $total_books ?></div>
            <div class="stat-label">Books (Titles)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-copy"></i></div>
        <div>
            <div class="stat-value"><?= $total_copies ?></div>
            <div class="stat-label">Physical Copies</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-laptop"></i></div>
        <div>
            <div class="stat-value"><?= $total_devices ?></div>
            <div class="stat-label">Devices</div>
        </div>
    </div>
</div>

<!-- Financial & Loan Stats -->
<div class="grid-3" style="margin-bottom: 32px;">
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Loans</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $total_loans ?></div>
    </div>
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Active Loans</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--warning); margin-top: 4px;"><?= $active_loans ?></div>
    </div>
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Overdue Loans</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--danger); margin-top: 4px;"><?= $overdue_loans ?></div>
    </div>
</div>

<div class="grid-3" style="margin-bottom: 32px;">
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Fines Issued</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;">€<?= number_format($total_fines, 2) ?></div>
    </div>
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Fines Paid</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--success); margin-top: 4px;">€<?= number_format($paid_fines, 2) ?></div>
    </div>
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Outstanding Fines</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--danger); margin-top: 4px;">€<?= number_format($unpaid_fines, 2) ?></div>
    </div>
</div>

<div class="grid-2" style="margin-bottom: 32px;">
    <!-- Recent Signups -->
    <div class="card">
        <h3><i class="fas fa-user-plus"></i> Recent Signups</h3>
        <?php if (empty($recent_users)): ?>
            <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No users yet.</p>
        <?php else: ?>
            <?php foreach ($recent_users as $ru): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border);">
                    <?php if (!empty($ru['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $ru['profile_image'])): ?>
                        <img src="<?= UPLOADS_URL . e($ru['profile_image']) ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.8rem;">
                            <?= strtoupper(substr($ru['email'], 0, 2)) ?>
                        </div>
                    <?php endif; ?>
                    <div style="flex: 1;">
                        <strong style="display: block; color: var(--text); font-size: 0.9rem;"><?= e($ru['first_name'] . ' ' . $ru['last_name']) ?></strong>
                        <small style="color: var(--text-muted); font-size: 0.8rem;"><?= e($ru['email']) ?></small>
                    </div>
                    <span style="padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; background: var(--bg-alt); color: var(--text-muted);">
                        <?= e($ru['role']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Recent Activity Logs -->
    <div class="card">
        <h3><i class="fas fa-shield-alt"></i> Recent Activity</h3>
        <?php if (empty($recent_logs)): ?>
            <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No activity yet.</p>
        <?php else: ?>
            <?php foreach ($recent_logs as $log): ?>
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 0.85rem;">
                    <div>
                        <strong style="color: var(--text);"><?= e($log['email'] ?? 'Guest') ?></strong>
                        <span style="color: var(--text-muted);"><?= e(str_replace('_', ' ', $log['action'])) ?></span>
                    </div>
                    <span style="color: var(--text-muted); font-size: 0.8rem;"><?= date('H:i', strtotime($log['created_at'])) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Monthly Growth -->
<div class="card">
    <h3><i class="fas fa-chart-line"></i> User Growth (Last 6 Months)</h3>
    <?php if (empty($monthly_growth)): ?>
        <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No user growth data yet.</p>
    <?php else: ?>
        <?php $max_growth = max(array_column($monthly_growth, 'count')); ?>
        <?php foreach ($monthly_growth as $mg): ?>
            <div style="padding: 10px 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="color: var(--text); font-weight: 600; font-size: 0.9rem;"><?= date('F Y', strtotime($mg['month'] . '-01')) ?></span>
                    <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;"><?= $mg['count'] ?> new users</span>
                </div>
                <div style="height: 8px; background: var(--bg-alt); border-radius: 4px; overflow: hidden;">
                    <div style="height: 100%; width: <?= ($mg['count'] / $max_growth) * 100 ?>%; background: linear-gradient(90deg, #3b82f6, #2563eb); border-radius: 4px;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
