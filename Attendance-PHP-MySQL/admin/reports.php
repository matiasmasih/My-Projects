<?php
// admin/reports.php — Modern reports with charts and filters

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// ---------- Date range ----------
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Quick presets
$preset = $_GET['preset'] ?? '';
if ($preset === 'today') {
    $from = date('Y-m-d'); $to = date('Y-m-d');
} elseif ($preset === 'week') {
    $from = date('Y-m-d', strtotime('monday this week'));
    $to   = date('Y-m-d');
} elseif ($preset === 'month') {
    $from = date('Y-m-01');
    $to   = date('Y-m-d');
} elseif ($preset === 'year') {
    $from = date('Y-01-01');
    $to   = date('Y-m-d');
}

// Filters
$filter_branch = (int)($_GET['branch'] ?? 0);
$filter_employee = (int)($_GET['employee'] ?? 0);

// ---------- CSV Export ----------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $where = "WHERE a.date BETWEEN ? AND ?";
    $params = [$from, $to];
    if ($filter_branch > 0)  { $where .= " AND e.branch_id = ?"; $params[] = $filter_branch; }
    if ($filter_employee > 0){ $where .= " AND e.id = ?"; $params[] = $filter_employee; }

    try {
        $stmt = $pdo->prepare("
            SELECT a.date, e.employee_code, e.first_name, e.last_name,
                   a.clock_in, a.clock_out, a.total_minutes, a.status, a.notes
            FROM attendance a
            JOIN employees e ON e.id = a.employee_id
            $where
            ORDER BY a.date DESC, a.clock_in DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance-' . $from . '-to-' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date','Employee Code','First Name','Last Name','Clock In','Clock Out','Total Minutes','Status','Notes']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['date'],$r['employee_code'],$r['first_name'],$r['last_name'],
                          $r['clock_in'],$r['clock_out'],$r['total_minutes'],$r['status'],$r['notes']]);
        }
        fclose($out);
        exit;
    } catch (PDOException $e) { die('Export failed.'); }
}

// ---------- Build WHERE ----------
$where = "WHERE a.date BETWEEN ? AND ?";
$params = [$from, $to];
if ($filter_branch > 0)  { $where .= " AND e.branch_id = ?"; $params[] = $filter_branch; }
if ($filter_employee > 0){ $where .= " AND e.id = ?"; $params[] = $filter_employee; }

// ---------- Summary KPIs ----------
$summary = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'hours' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent,
            SUM(a.total_minutes) AS minutes
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        $where
    ");
    $stmt->execute($params);
    $r = $stmt->fetch();
    $summary['total']   = (int)($r['total'] ?? 0);
    $summary['present'] = (int)($r['present'] ?? 0);
    $summary['late']    = (int)($r['late'] ?? 0);
    $summary['absent']  = (int)($r['absent'] ?? 0);
    $summary['hours']   = round(((int)($r['minutes'] ?? 0)) / 60, 1);
} catch (PDOException $e) {}

// ---------- Daily breakdown (for chart) ----------
$daily = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.date,
               SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present,
               SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late,
               SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        $where
        GROUP BY a.date
        ORDER BY a.date ASC
    ");
    $stmt->execute($params);
    $daily = $stmt->fetchAll();
} catch (PDOException $e) {}

// ---------- Per-employee breakdown ----------
$per_emp = [];
try {
    $stmt = $pdo->prepare("
        SELECT e.id, e.employee_code, e.first_name, e.last_name, b.name AS branch_name,
               COUNT(a.id) AS days,
               SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_days,
               SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_days,
               SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
               SUM(a.total_minutes) AS minutes
        FROM employees e
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.date BETWEEN ? AND ?
        LEFT JOIN branches b ON b.id = e.branch_id
        WHERE e.is_active = 1
        " . ($filter_branch > 0 ? " AND e.branch_id = ?" : "") . "
        " . ($filter_employee > 0 ? " AND e.id = ?" : "") . "
        GROUP BY e.id
        ORDER BY absent_days DESC, present_days ASC
    ");
    $emp_params = [$from, $to];
    if ($filter_branch > 0)   $emp_params[] = $filter_branch;
    if ($filter_employee > 0) $emp_params[] = $filter_employee;
    $stmt->execute($emp_params);
    $per_emp = $stmt->fetchAll();
} catch (PDOException $e) {}

// ---------- Detailed records ----------
$records = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, e.first_name, e.last_name, e.employee_code, b.name AS branch_name
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        LEFT JOIN branches b ON b.id = e.branch_id
        $where
        ORDER BY a.date DESC, a.clock_in DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll();
} catch (PDOException $e) {}

// ---------- Branch/Employee lists for filters ----------
$branches = [];
try { $branches = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll(); } catch (PDOException $e) {}

$employees = [];
try { $employees = $pdo->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE is_active = 1 ORDER BY first_name")->fetchAll(); } catch (PDOException $e) {}

$page_title = 'Reports';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Filter bar -->
<div class="admin-panel">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <h3 style="margin:0;"><i class="fas fa-chart-pie"></i> Reports</h3>
        <a href="?from=<?= e($from) ?>&to=<?= e($to) ?>&branch=<?= $filter_branch ?>&employee=<?= $filter_employee ?>&export=csv" 
           class="btn btn-success">
            <i class="fas fa-download"></i> Export CSV
        </a>
    </div>

    <!-- Presets -->
    <div style="display:flex;gap:8px;margin:20px 0 16px;flex-wrap:wrap;">
        <a href="?preset=today&branch=<?= $filter_branch ?>&employee=<?= $filter_employee ?>" 
           class="btn btn-sm <?= $preset === 'today' ? 'btn-primary' : 'btn-outline' ?>">Today</a>
        <a href="?preset=week&branch=<?= $filter_branch ?>&employee=<?= $filter_employee ?>" 
           class="btn btn-sm <?= $preset === 'week' ? 'btn-primary' : 'btn-outline' ?>">This Week</a>
        <a href="?preset=month&branch=<?= $filter_branch ?>&employee=<?= $filter_employee ?>" 
           class="btn btn-sm <?= $preset === 'month' ? 'btn-primary' : 'btn-outline' ?>">This Month</a>
        <a href="?preset=year&branch=<?= $filter_branch ?>&employee=<?= $filter_employee ?>" 
           class="btn btn-sm <?= $preset === 'year' ? 'btn-primary' : 'btn-outline' ?>">This Year</a>
    </div>

    <!-- Filters -->
    <form method="get" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:12px;align-items:flex-end;padding:16px;background:var(--bg-alt);border-radius:12px;">
        <div>
            <label style="font-size:0.8rem;">From</label>
            <input type="date" name="from" value="<?= e($from) ?>">
        </div>
        <div>
            <label style="font-size:0.8rem;">To</label>
            <input type="date" name="to" value="<?= e($to) ?>">
        </div>
        <div>
            <label style="font-size:0.8rem;">Branch</label>
            <select name="branch">
                <option value="0">All branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= $filter_branch == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.8rem;">Employee</label>
            <select name="employee">
                <option value="0">All employees</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= (int)$emp['id'] ?>" <?= $filter_employee == $emp['id'] ? 'selected' : '' ?>>
                        <?= e($emp['first_name'] . ' ' . $emp['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
    </form>
</div>

<!-- KPI cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-list"></i></div>
        <div>
            <div class="stat-value"><?= number_format($summary['total']) ?></div>
            <div class="stat-label">Total Records</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-value"><?= number_format($summary['present']) ?></div>
            <div class="stat-label">Present</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value"><?= number_format($summary['late']) ?></div>
            <div class="stat-label">Late</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-user-xmark"></i></div>
        <div>
            <div class="stat-value"><?= number_format($summary['absent']) ?></div>
            <div class="stat-label">Absent</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon sky"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <div class="stat-value"><?= $summary['hours'] ?>h</div>
            <div class="stat-label">Hours Worked</div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="grid-2" style="margin-top:24px;">
    <div class="admin-panel">
        <h3><i class="fas fa-chart-bar"></i> Daily Breakdown</h3>
        <div style="height:280px;position:relative;">
            <canvas id="dailyChart"></canvas>
        </div>
        <?php if (empty($daily)): ?>
            <p style="text-align:center;color:var(--text-muted);padding:20px;">No data for this date range.</p>
        <?php endif; ?>
    </div>

    <div class="admin-panel">
        <h3><i class="fas fa-chart-pie"></i> Status Distribution</h3>
        <div style="height:280px;position:relative;">
            <canvas id="statusChart"></canvas>
        </div>
    </div>
</div>

<!-- Per-employee -->
<div class="admin-panel">
    <h3><i class="fas fa-users"></i> Per-Employee Breakdown</h3>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Employee</th>
                    <th>Branch</th>
                    <th>Present</th>
                    <th>Late</th>
                    <th>Absent</th>
                    <th>Hours</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($per_emp as $e): ?>
                    <tr>
                        <td><strong><?= e($e['employee_code']) ?></strong></td>
                        <td><?= e($e['first_name'] . ' ' . $e['last_name']) ?></td>
                        <td><?= e($e['branch_name'] ?? '—') ?></td>
                        <td><span class="badge badge-success"><?= (int)$e['present_days'] ?></span></td>
                        <td><span class="badge badge-warning"><?= (int)$e['late_days'] ?></span></td>
                        <td><span class="badge badge-danger"><?= (int)$e['absent_days'] ?></span></td>
                        <td><?= $e['minutes'] ? round($e['minutes'] / 60, 1) . 'h' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detailed records -->
<div class="admin-panel">
    <h3><i class="fas fa-list"></i> All Records (last 100)</h3>
    <?php if (empty($records)): ?>
        <p style="text-align:center;color:var(--text-muted);padding:40px;">No records for this date range.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Branch</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><?= e(date('M j, Y', strtotime($r['date']))) ?></td>
                            <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                            <td><?= e($r['branch_name'] ?? '—') ?></td>
                            <td><?= $r['clock_in'] ? e(date('H:i', strtotime($r['clock_in']))) : '—' ?></td>
                            <td><?= $r['clock_out'] ? e(date('H:i', strtotime($r['clock_out']))) : '—' ?></td>
                            <td><?= $r['total_minutes'] ? round($r['total_minutes'] / 60, 1) . 'h' : '—' ?></td>
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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    var dailyData = <?= json_encode($daily) ?>;
    var summary = <?= json_encode($summary) ?>;
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var textColor = isDark ? '#f1f5f9' : '#0f172a';
    var gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.06)';

    // Daily chart
    if (dailyData.length > 0) {
        var labels = dailyData.map(d => d.date.substring(5));
        var present = dailyData.map(d => parseInt(d.present));
        var late = dailyData.map(d => parseInt(d.late));
        var absent = dailyData.map(d => parseInt(d.absent));

        new Chart(document.getElementById('dailyChart'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Present', data: present, backgroundColor: '#10b981' },
                    { label: 'Late',    data: late,    backgroundColor: '#f59e0b' },
                    { label: 'Absent',  data: absent,  backgroundColor: '#ef4444' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: textColor } }
                },
                scales: {
                    x: { ticks: { color: textColor }, grid: { color: gridColor }, stacked: true },
                    y: { ticks: { color: textColor }, grid: { color: gridColor }, stacked: true, beginAtZero: true }
                }
            }
        });
    }

    // Status pie chart
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Late', 'Absent'],
            datasets: [{
                data: [summary.present, summary.late, summary.absent],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: textColor, padding: 16 } }
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
