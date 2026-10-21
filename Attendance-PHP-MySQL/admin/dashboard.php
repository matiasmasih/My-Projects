<?php
// admin/dashboard.php — Modern admin dashboard

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'employee/dashboard.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? 'Admin';

// ---------- KPI Stats ----------
$stats = [
    'employees' => 0,
    'branches'  => 0,
    'present'   => 0,
    'late'      => 0,
    'absent'    => 0,
    'total_today' => 0,
    'pending_leaves' => 0,
    'maintenance' => setting('maintenance_mode', '0'),
];

try {
    $stats['employees'] = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();
    $stats['branches']  = (int)$pdo->query("SELECT COUNT(*) FROM branches WHERE is_active = 1")->fetchColumn();
    $stats['present']   = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present'")->fetchColumn();
    $stats['late']      = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'late'")->fetchColumn();
    $stats['absent']    = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'absent'")->fetchColumn();
    $stats['total_today'] = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()")->fetchColumn();
    $stats['pending_leaves'] = (int)$pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")->fetchColumn();
} catch (PDOException $e) {}

// ---------- Today's attendance ----------
$today_records = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, e.first_name, e.last_name, e.employee_code, b.name AS branch_name
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        LEFT JOIN branches b ON b.id = e.branch_id
        WHERE a.date = CURDATE()
        ORDER BY a.clock_in DESC
        LIMIT 20
    ");
    $today_records = $stmt->fetchAll();
} catch (PDOException $e) {}

// ---------- Weekly chart (last 7 days) ----------
$weekly = [];
try {
    $stmt = $pdo->query("
        SELECT 
            date,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent
        FROM attendance
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY date
        ORDER BY date ASC
    ");
    $weekly = $stmt->fetchAll();
} catch (PDOException $e) {}

// ---------- Status distribution (this month) ----------
$status_dist = ['present' => 0, 'late' => 0, 'absent' => 0];
try {
    $rows = $pdo->query("
        SELECT status, COUNT(*) AS cnt
        FROM attendance
        WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())
        GROUP BY status
    ")->fetchAll();
    foreach ($rows as $row) {
        if (isset($status_dist[$row['status']])) {
            $status_dist[$row['status']] = (int)$row['cnt'];
        }
    }
} catch (PDOException $e) {}

// ---------- Top performers (best attendance this month) ----------
$top_performers = [];
try {
    $top_performers = $pdo->query("
        SELECT e.id, e.first_name, e.last_name, e.employee_code, e.position,
               COUNT(a.id) AS total_records,
               SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_days
        FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id 
                                AND MONTH(a.date) = MONTH(CURDATE()) 
                                AND YEAR(a.date) = YEAR(CURDATE())
        WHERE e.is_active = 1
        GROUP BY e.id
        HAVING present_days > 0
        ORDER BY present_days DESC
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Welcome banner -->
<div class="admin-panel" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border:none;margin-bottom:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <h2 style="color:#fff;font-size:1.5rem;margin-bottom:4px;">
                Welcome back, <?= e($user_name) ?>
            </h2>
            <p style="color:rgba(255,255,255,0.85);font-size:0.92rem;">
                <i class="fas fa-calendar"></i> <?= date('l, F j, Y') ?>
                &nbsp;·&nbsp;
                <i class="fas fa-clock"></i> <?= date('H:i') ?>
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="employees.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-user-plus"></i> Add Employee
            </a>
            <a href="reports.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-chart-pie"></i> Reports
            </a>
        </div>
    </div>
</div>

<!-- Alerts -->
<?php if ($stats['maintenance'] === '1'): ?>
    <div class="flash flash-warning" style="margin-bottom:20px;">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Maintenance Mode is ON</strong> — Only admins can access the site.
        <a href="settings.php" style="color:inherit;text-decoration:underline;margin-left:8px;">Turn off →</a>
    </div>
<?php endif; ?>

<?php if ($stats['pending_leaves'] > 0): ?>
    <div class="flash flash-info" style="margin-bottom:20px;">
        <i class="fas fa-calendar-check"></i>
        <strong><?= $stats['pending_leaves'] ?></strong> leave request<?= $stats['pending_leaves'] > 1 ? 's' : '' ?> pending review.
    </div>
<?php endif; ?>

<!-- KPI cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-value"><?= $stats['employees'] ?></div>
            <div class="stat-label">Active Employees</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon sky"><i class="fas fa-building"></i></div>
        <div>
            <div class="stat-value"><?= $stats['branches'] ?></div>
            <div class="stat-label">Branches</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-value"><?= $stats['present'] ?></div>
            <div class="stat-label">Present Today</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value"><?= $stats['late'] ?></div>
            <div class="stat-label">Late Today</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-user-xmark"></i></div>
        <div>
            <div class="stat-value"><?= $stats['absent'] ?></div>
            <div class="stat-label">Absent Today</div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="grid-2" style="margin-top:24px;">
    <div class="admin-panel">
        <h3><i class="fas fa-chart-bar"></i> Last 7 Days Attendance</h3>
        <div style="height:260px;position:relative;">
            <canvas id="weeklyChart"></canvas>
        </div>
        <?php if (empty($weekly)): ?>
            <p style="text-align:center;color:var(--text-muted);padding:20px;">No data for this week.</p>
        <?php endif; ?>
    </div>
    <div class="admin-panel">
        <h3><i class="fas fa-chart-pie"></i> Status This Month</h3>
        <div style="height:260px;position:relative;">
            <canvas id="statusChart"></canvas>
        </div>
    </div>
</div>

<!-- Today's attendance (full width) -->
<div class="admin-panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;"><i class="fas fa-calendar-day"></i> Today's Attendance</h3>
        <a href="attendance.php" class="btn btn-outline btn-sm">View all</a>
    </div>

    <?php if (empty($today_records)): ?>
        <div style="text-align:center;padding:40px;color:var(--text-muted);">
            <i class="fas fa-inbox" style="font-size:2rem;opacity:0.4;display:block;margin-bottom:10px;"></i>
            No records yet today.
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Branch</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($today_records as $r): ?>
                        <tr>
                            <td>
                                <strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                                <div style="font-size:0.75rem;color:var(--text-muted);"><?= e($r['employee_code']) ?></div>
                            </td>
                            <td><?= e($r['branch_name'] ?? '—') ?></td>
                            <td><?= $r['clock_in'] ? e(date('H:i', strtotime($r['clock_in']))) : '—' ?></td>
                            <td><?= $r['clock_out'] ? e(date('H:i', strtotime($r['clock_out']))) : '—' ?></td>
                            <td>
                                <?php
                                    $badge = 'primary';
                                    if ($r['status'] === 'present') $badge = 'success';
                                    elseif ($r['status'] === 'late') $badge = 'warning';
                                    elseif ($r['status'] === 'absent') $badge = 'danger';
                                ?>
                                <span class="badge badge-<?= $badge ?>"><?= e(ucfirst($r['status'])) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Top performers (full width, below) -->
<div class="admin-panel" style="margin-top:24px;">
    <h3><i class="fas fa-trophy"></i> Top Performers (This Month)</h3>

    <?php if (empty($top_performers)): ?>
        <div style="text-align:center;padding:40px;color:var(--text-muted);">
            <i class="fas fa-trophy" style="font-size:2rem;opacity:0.4;display:block;margin-bottom:10px;"></i>
            No attendance records yet.
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Employee</th>
                        <th>Position</th>
                        <th>Present Days</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_performers as $i => $emp): ?>
                        <tr>
                            <td>
                                <div style="width:30px;height:30px;border-radius:50%;background:<?= $i === 0 ? '#eab308' : ($i === 1 ? '#94a3b8' : ($i === 2 ? '#b45309' : 'var(--border)')) ?>;color:#fff;display:grid;place-items:center;font-weight:800;font-size:0.8rem;">
                                    <?= $i + 1 ?>
                                </div>
                            </td>
                            <td>
                                <strong><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></strong>
                                <div style="font-size:0.75rem;color:var(--text-muted);"><?= e($emp['employee_code']) ?></div>
                            </td>
                            <td><?= e($emp['position'] ?: '—') ?></td>
                            <td>
                                <span class="badge badge-success"><?= (int)$emp['present_days'] ?> days</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    var weeklyData = <?= json_encode($weekly) ?>;
    var statusData = <?= json_encode($status_dist) ?>;
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var textColor = isDark ? '#f1f5f9' : '#0f172a';
    var gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.06)';

    if (weeklyData.length > 0) {
        new Chart(document.getElementById('weeklyChart'), {
            type: 'bar',
            data: {
                labels: weeklyData.map(d => d.date.substring(5)),
                datasets: [
                    { label: 'Present', data: weeklyData.map(d => parseInt(d.present)), backgroundColor: '#10b981' },
                    { label: 'Late',    data: weeklyData.map(d => parseInt(d.late)),    backgroundColor: '#f59e0b' },
                    { label: 'Absent',  data: weeklyData.map(d => parseInt(d.absent)),  backgroundColor: '#ef4444' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: textColor } } },
                scales: {
                    x: { ticks: { color: textColor }, grid: { color: gridColor }, stacked: true },
                    y: { ticks: { color: textColor }, grid: { color: gridColor }, stacked: true, beginAtZero: true }
                }
            }
        });
    }

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [statusData.present, statusData.late, statusData.absent],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { color: textColor, padding: 14 } } }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
