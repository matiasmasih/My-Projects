<?php
// admin/attendance.php — Modern attendance records

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$flash = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $pdo->prepare("DELETE FROM attendance WHERE id = ?")->execute([$id]);
            $flash = 'Record deleted.';
        } catch (PDOException $e) {
            $flash = 'Could not delete record.';
        }
    }
}

// Filters
$filter_date     = $_GET['date'] ?? '';
$filter_employee = (int)($_GET['employee'] ?? 0);
$filter_status   = $_GET['status'] ?? '';
$filter_branch   = (int)($_GET['branch'] ?? 0);
$preset          = $_GET['preset'] ?? '';

// Preset overrides
if ($preset === 'today') $filter_date = date('Y-m-d');
elseif ($preset === 'yesterday') $filter_date = date('Y-m-d', strtotime('-1 day'));

// Build WHERE
$where = "WHERE 1=1";
$params = [];

if ($filter_date !== '') {
    $where .= " AND a.date = ?";
    $params[] = $filter_date;
}
if ($filter_employee > 0) {
    $where .= " AND a.employee_id = ?";
    $params[] = $filter_employee;
}
if ($filter_status !== '') {
    $where .= " AND a.status = ?";
    $params[] = $filter_status;
}
if ($filter_branch > 0) {
    $where .= " AND e.branch_id = ?";
    $params[] = $filter_branch;
}

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $stmt = $pdo->prepare("
            SELECT a.date, e.employee_code, e.first_name, e.last_name,
                   b.name AS branch_name, a.clock_in, a.clock_out, 
                   a.total_minutes, a.status, a.notes
            FROM attendance a
            JOIN employees e ON e.id = a.employee_id
            LEFT JOIN branches b ON b.id = e.branch_id
            $where
            ORDER BY a.date DESC, a.clock_in DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date','Code','First Name','Last Name','Branch','Clock In','Clock Out','Total Minutes','Status','Notes']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['date'],$r['employee_code'],$r['first_name'],$r['last_name'],
                          $r['branch_name'],$r['clock_in'],$r['clock_out'],
                          $r['total_minutes'],$r['status'],$r['notes']]);
        }
        fclose($out);
        exit;
    } catch (PDOException $e) { die('Export failed.'); }
}

// Summary stats for the current filter
$summary = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent
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
} catch (PDOException $e) {}

// Load records
$records = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, e.first_name, e.last_name, e.employee_code, b.name AS branch_name
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        LEFT JOIN branches b ON b.id = e.branch_id
        $where
        ORDER BY a.date DESC, a.clock_in DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll();
} catch (PDOException $e) {}

// Filter data
$employees = [];
$branches  = [];
try {
    $employees = $pdo->query("SELECT id, first_name, last_name, employee_code FROM employees WHERE is_active = 1 ORDER BY first_name")->fetchAll();
    $branches  = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();
} catch (PDOException $e) {}

$statuses = ['present', 'absent', 'late', 'early-leave', 'half-day', 'holiday', 'sick', 'vacation'];

$page_title = 'Attendance';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Header banner -->
<div class="admin-panel" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border:none;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <h2 style="color:#fff;font-size:1.6rem;margin-bottom:4px;">
                <i class="fas fa-calendar-check"></i> Attendance
            </h2>
            <p style="color:rgba(255,255,255,0.85);font-size:0.92rem;">
                <?= $summary['total'] ?> total records
                <?php if ($filter_date): ?>
                    · <?= e(date('M j, Y', strtotime($filter_date))) ?>
                <?php endif; ?>
            </p>
        </div>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" 
           class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
            <i class="fas fa-download"></i> Export CSV
        </a>
    </div>
</div>

<!-- Preset buttons -->
<div style="display:flex;gap:8px;margin-top:20px;flex-wrap:wrap;">
    <a href="?preset=today&employee=<?= $filter_employee ?>&branch=<?= $filter_branch ?>&status=<?= $filter_status ?>" 
       class="btn btn-sm <?= $preset === 'today' ? 'btn-primary' : 'btn-outline' ?>">Today</a>
    <a href="?preset=yesterday&employee=<?= $filter_employee ?>&branch=<?= $filter_branch ?>&status=<?= $filter_status ?>" 
       class="btn btn-sm <?= $preset === 'yesterday' ? 'btn-primary' : 'btn-outline' ?>">Yesterday</a>
    <a href="?employee=<?= $filter_employee ?>&branch=<?= $filter_branch ?>&status=<?= $filter_status ?>" 
       class="btn btn-sm <?= ($filter_date === '' && $preset === '') ? 'btn-primary' : 'btn-outline' ?>">All</a>
</div>

<!-- Filters -->
<div class="admin-panel" style="margin-top:20px;">
    <form method="get" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr auto;gap:12px;align-items:flex-end;">
        <div>
            <label style="font-size:0.8rem;">Date</label>
            <input type="date" name="date" value="<?= e($filter_date) ?>">
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
            <label style="font-size:0.8rem;">Status</label>
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?= e($st) ?>" <?= $filter_status === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div></div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
    </form>
</div>

<!-- Summary stats -->
<div class="stat-grid" style="margin-top:20px;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-list"></i></div>
        <div>
            <div class="stat-value"><?= $summary['total'] ?></div>
            <div class="stat-label">Total Records</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-value"><?= $summary['present'] ?></div>
            <div class="stat-label">Present</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value"><?= $summary['late'] ?></div>
            <div class="stat-label">Late</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-user-xmark"></i></div>
        <div>
            <div class="stat-value"><?= $summary['absent'] ?></div>
            <div class="stat-label">Absent</div>
        </div>
    </div>
</div>

<?php if ($flash): ?>
    <div class="flash flash-info" style="margin-top:16px;">
        <i class="fas fa-info-circle"></i> <?= e($flash) ?>
    </div>
<?php endif; ?>

<!-- Records table -->
<div class="admin-panel" style="margin-top:20px;">
    <h3><i class="fas fa-list"></i> Records (<?= count($records) ?>)</h3>

    <?php if (empty($records)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
            <i class="fas fa-inbox" style="font-size:3rem;opacity:0.3;display:block;margin-bottom:14px;"></i>
            <p>No attendance records match the filters.</p>
        </div>
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
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <?php
                            $badge = 'primary';
                            if ($r['status'] === 'present') $badge = 'success';
                            elseif ($r['status'] === 'late') $badge = 'warning';
                            elseif ($r['status'] === 'absent') $badge = 'danger';
                        ?>
                        <tr>
                            <td><strong><?= e(date('M j, Y', strtotime($r['date']))) ?></strong></td>
                            <td>
                                <strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                                <div style="font-size:0.75rem;color:var(--text-muted);"><?= e($r['employee_code']) ?></div>
                            </td>
                            <td><?= e($r['branch_name'] ?? '—') ?></td>
                            <td><?= $r['clock_in'] ? e(date('H:i', strtotime($r['clock_in']))) : '—' ?></td>
                            <td><?= $r['clock_out'] ? e(date('H:i', strtotime($r['clock_out']))) : '—' ?></td>
                            <td><?= $r['total_minutes'] ? floor($r['total_minutes'] / 60) . 'h ' . ($r['total_minutes'] % 60) . 'm' : '—' ?></td>
                            <td><span class="badge badge-<?= $badge ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px;text-align:right;color:var(--text-muted);font-size:0.85rem;">
            Showing <?= count($records) ?> record<?= count($records) !== 1 ? 's' : '' ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
