<?php
session_start();
include 'config.php';
include_once 'access_control.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Enforce access on Admin Dashboard
$access = require_access('admin_dashboard', 'login.php');
$is_view_only = ($access === 'view');

// ============================================
// FETCH STATS
// ============================================
$stats = [];
$tables = [
    'users' => 'Users',
    'roles' => 'Roles',
    'patients' => 'Patients',
    'doctors' => 'Doctors',
    'appointments' => 'Appointments',
    'invoices' => 'Invoices',
    'payments' => 'Payments',
    'pharmacy_stock' => 'Pharmacy',
    'medicines' => 'Medicines',
    'wards' => 'Wards',
    'rooms' => 'Rooms',
    'messages' => 'Messages',
    'admissions' => 'Admissions'
];

foreach ($tables as $table => $label) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
        $stats[$label] = $stmt->fetch()['total'];
    } catch (Exception $e) {
        $stats[$label] = 0;
    }
}

// ============================================
// USERS BY ROLE
// ============================================
$users_by_role = [];
try {
    $stmt = $pdo->query("SELECT r.name, COUNT(u.id) as total FROM roles r LEFT JOIN users u ON u.role_id = r.id GROUP BY r.id");
    while ($row = $stmt->fetch()) {
        $users_by_role[$row['name']] = $row['total'];
    }
} catch (Exception $e) {}

// ============================================
// REVENUE (LAST 7 DAYS)
// ============================================
$revenue_data = [];
$revenue_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $revenue_labels[] = date('D', strtotime($date));
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM invoices WHERE DATE(issued_at) = ?");
        $stmt->execute([$date]);
        $revenue_data[] = floatval($stmt->fetch()['total']);
    } catch (Exception $e) {
        $revenue_data[] = 0;
    }
}

// ============================================
// RECENT USERS
// ============================================
$recent_users = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.created_at, r.name as role
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY u.created_at DESC
        LIMIT 5
    ");
    $recent_users = $stmt->fetchAll();
} catch (Exception $e) {}

// Page title + header
$page_title = 'Admin Dashboard';
include 'admin_header.php';
?>

<!-- WELCOME BANNER -->
<div class="welcome-banner">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Admin'); ?> 👋</h2>
    <p>Full system control & overview — you can access all dashboards</p>

    <?php if ($is_view_only): ?>
    <div style="background: rgba(255,255,255,0.2); padding: 10px 15px; border-radius: 8px; margin-top: 10px; display: inline-block; font-size: 0.9rem;">
        👁️ <strong>View Only Mode</strong> — You cannot make changes
    </div>
    <?php endif; ?>

    <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">Loading...</div>
    </div>
</div>

<!-- STATS GRID -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-users"><i class="bi bi-people-fill"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Users']; ?>">0</h3>
            <p>Users</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-roles"><i class="bi bi-shield-lock"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Roles']; ?>">0</h3>
            <p>Roles</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-patients"><i class="bi bi-person-fill"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Patients']; ?>">0</h3>
            <p>Patients</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-doctors"><i class="bi bi-person-badge"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Doctors']; ?>">0</h3>
            <p>Doctors</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-appointments"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Appointments']; ?>">0</h3>
            <p>Appointments</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-invoices"><i class="bi bi-receipt"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Invoices']; ?>">0</h3>
            <p>Invoices</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-pharmacy"><i class="bi bi-capsule"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Pharmacy']; ?>">0</h3>
            <p>Pharmacy</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-medicines"><i class="bi bi-heart-pulse"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Medicines']; ?>">0</h3>
            <p>Medicines</p>
        </div>
    </div>
</div>

<!-- CHARTS -->
<div class="charts-grid">
    <div class="chart-card">
        <h5><i class="bi bi-graph-up-arrow"></i> Revenue (Last 7 Days)</h5>
        <div class="chart-wrapper">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <h5><i class="bi bi-pie-chart-fill"></i> Users by Role</h5>
        <div class="chart-wrapper">
            <canvas id="roleChart"></canvas>
        </div>
    </div>
</div>

<!-- QUICK ACTIONS -->
<h5 style="font-weight: 700; margin-bottom: 15px; color: var(--text);">
    <i class="bi bi-lightning-charge-fill" style="color: var(--primary);"></i> Quick Actions
</h5>
<div class="quick-actions">
    <?php if (!$is_view_only): ?>
    <a href="users.php" class="action-btn"><i class="bi bi-person-plus"></i> Manage Users</a>
    <a href="roles.php" class="action-btn"><i class="bi bi-shield-lock"></i> Manage Roles</a>
    <?php endif; ?>
    <a href="manager_dashboard.php" class="action-btn"><i class="bi bi-clipboard2-pulse"></i> Manager View</a>
    <a href="doctor_dashboard.php" class="action-btn"><i class="bi bi-heart-pulse"></i> Doctor View</a>
    <a href="staff_dashboard.php" class="action-btn"><i class="bi bi-person-badge"></i> Staff View</a>
    <a href="pharmacy_stock.php" class="action-btn"><i class="bi bi-capsule"></i> Pharmacy</a>
</div>

<!-- RECENT USERS -->
<div class="table-card">
    <h5><i class="bi bi-clock-history"></i> Recent Users</h5>
    <?php if (count($recent_users) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_users as $u): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')); ?></strong></td>
                    <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
                    <td>
                        <span class="role-badge role-<?php echo strtolower($u['role'] ?? 'patient'); ?>">
                            <?php echo htmlspecialchars($u['role'] ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td><?php echo $u['created_at'] ? date('M d, Y', strtotime($u['created_at'])) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">No users yet.</p>
    <?php endif; ?>
</div>

<!-- PAGE CHARTS -->
<script>
// REVENUE CHART
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const gradient = revenueCtx.createLinearGradient(0, 0, 0, 300);
gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
gradient.addColorStop(1, 'rgba(99, 102, 241, 0.01)');

new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($revenue_labels); ?>,
        datasets: [{
            label: 'Revenue (€)',
            data: <?php echo json_encode($revenue_data); ?>,
            borderColor: '#6366f1',
            backgroundColor: gradient,
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#6366f1',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { color: '#64748b', font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 11 } } }
        }
    }
});

// ROLE CHART
const roleCtx = document.getElementById('roleChart').getContext('2d');
const roleData = <?php echo json_encode($users_by_role); ?>;
const roleLabels = Object.keys(roleData);
const roleValues = Object.values(roleData);

new Chart(roleCtx, {
    type: 'doughnut',
    data: {
        labels: roleLabels.length ? roleLabels : ['No Data'],
        datasets: [{
            data: roleValues.length ? roleValues : [1],
            backgroundColor: ['#6366f1', '#10b981', '#06b6d4', '#f59e0b', '#ec4899', '#8b5cf6'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: '#64748b', font: { size: 11 }, padding: 10, usePointStyle: true, pointStyle: 'circle' }
            }
        },
        cutout: '65%'
    }
});
</script>

<?php include 'admin_footer.php'; ?>
