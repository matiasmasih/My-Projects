<?php
// employee/history.php — Personal attendance history

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 1. Get the employee_id from the users table first
$stmtUserId = $pdo->prepare("SELECT employee_id FROM users WHERE id = ? LIMIT 1");
$stmtUserId->execute([$user_id]);
$emp_id_row = $stmtUserId->fetch();
$employee_id = $emp_id_row['employee_id'] ?? 0;

// 2. Fetch the full employee details using that ID
$stmtUser = $pdo->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
$stmtUser->execute([$employee_id]);
$employee = $stmtUser->fetch();

// Default to current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Calculate start and end dates for the month
$start_date = sprintf('%04d-%02d-01', $year, $month);
$end_date = date('Y-m-t', strtotime($start_date));

// Fetch attendance records for this month
$stmt = $pdo->prepare("SELECT * FROM attendance 
                       WHERE employee_id = ? AND date BETWEEN ? AND ? 
                       ORDER BY date DESC");
$stmt->execute([$employee_id, $start_date, $end_date]);
$records = $stmt->fetchAll();

// Calculate totals
$total_worked_minutes = 0;
$days_present = 0;
$days_late = 0;

foreach ($records as $r) {
    $total_worked_minutes += $r['total_minutes'];
    if ($r['status'] === 'present' || $r['status'] === 'early-leave') $days_present++;
    if ($r['status'] === 'late') $days_late++;
}

$total_hours = round($total_worked_minutes / 60, 1);

$page_title = 'My History';
require_once __DIR__ . '/../includes/employee_header.php';
?>

<div class="dashboard-container">

    <!-- HERO BANNER -->
    <div class="hero-banner">
        <div class="hero-content">
            <h1>Welcome back, <?= e($employee['first_name'] ?? 'Employee') ?>!</h1>
            <p><i class="fas fa-history"></i> Here is your personal attendance history.</p>
        </div>
        <div class="hero-datetime">
            <div class="time" id="bannerTime"><?= date('H:i:s') ?></div>
            <div class="date" id="bannerDate"><?= date('l, M j, Y') ?></div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-icon blue"><i class="fas fa-clock"></i></div>
            <div class="summary-info">
                <h3><?= $total_hours ?>h</h3>
                <p>Total Hours</p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon green"><i class="fas fa-calendar-check"></i></div>
            <div class="summary-info">
                <h3><?= $days_present ?></h3>
                <p>Days Present</p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="summary-info">
                <h3><?= $days_late ?></h3>
                <p>Late Arrivals</p>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" style="display:flex; gap:12px; align-items:center; width:100%;">
            <label style="margin:0; font-size:0.9rem; color:var(--text-muted); font-weight:600;">Filter:</label>
            
            <select name="month">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                    </option>
                <?php endfor; ?>
            </select>
            
            <select name="year">
                <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            
            <button type="submit" class="btn btn-primary btn-sm" style="border-radius:10px;">Apply</button>
        </form>
    </div>

    <!-- Table -->
    <div class="table-container">
        <?php if (empty($records)): ?>
            <div style="padding: 60px 20px; text-align: center; color: var(--text-muted);">
                <i class="fas fa-calendar-times" style="font-size: 3rem; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                <h3 style="margin-bottom: 8px;">No records found</h3>
                <p>You have no attendance records for this month.</p>
            </div>
        <?php else: ?>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Total Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                        <?php 
                            // Format status class
                            $status_class = 'present';
                            if ($row['status'] === 'late') $status_class = 'late';
                            if ($row['status'] === 'absent') $status_class = 'absent';
                        ?>
                        <tr>
                            <td style="font-weight: 600;">
                                <?= date('D, M j, Y', strtotime($row['date'])) ?>
                            </td>
                            <td>
                                <?= $row['clock_in'] ? date('H:i', strtotime($row['clock_in'])) : '<span style="color:var(--text-muted);">—</span>' ?>
                            </td>
                            <td>
                                <?= $row['clock_out'] ? date('H:i', strtotime($row['clock_out'])) : '<span style="color:var(--text-muted);">—</span>' ?>
                            </td>
                            <td style="font-weight: 600;">
                                <?= $row['total_minutes'] > 0 ? round($row['total_minutes'] / 60, 1) . 'h' : '—' ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $status_class ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/employee_footer.php'; ?>
