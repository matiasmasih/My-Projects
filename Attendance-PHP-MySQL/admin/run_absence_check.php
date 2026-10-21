<?php
// admin/run_absence_check.php — Modern manual absence check

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$result = null;
$target_date = $_POST['target_date'] ?? $_GET['target_date'] ?? date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)) {
    $target_date = date('Y-m-d');
}

// Run the cron inline
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ob_start();
        $argv = [null, $target_date];
        include __DIR__ . '/../cron/mark_absences.php';
        $result = ob_get_clean();
    } catch (Exception $e) {
        $result = "ERROR: " . $e->getMessage();
    }
}

// Preview data
$preview = [];
$day_of_week = (int)date('w', strtotime($target_date));

// Check holiday
$is_holiday = false;
try {
    $stmt = $pdo->prepare("SELECT name FROM holidays WHERE date = ? LIMIT 1");
    $stmt->execute([$target_date]);
    $holiday = $stmt->fetch();
    if ($holiday) $is_holiday = $holiday['name'];
} catch (PDOException $e) {}

// Get employees scheduled today
try {
    $stmt = $pdo->prepare("
        SELECT e.id, e.first_name, e.last_name, e.employee_code,
               s.name AS shift_name, s.days AS shift_days
        FROM employees e
        JOIN shifts s ON s.id = e.shift_id
        WHERE e.is_active = 1 AND s.is_active = 1
    ");
    $stmt->execute();
    foreach ($stmt->fetchAll() as $emp) {
        $shift_days = explode(',', $emp['shift_days']);
        if (!in_array((string)$day_of_week, $shift_days)) continue;

        $stmt2 = $pdo->prepare("SELECT status, clock_in FROM attendance WHERE employee_id = ? AND date = ? LIMIT 1");
        $stmt2->execute([$emp['id'], $target_date]);
        $att = $stmt2->fetch();

        $emp['attendance_status'] = $att['status'] ?? null;
        $emp['clock_in'] = $att['clock_in'] ?? null;
        $preview[] = $emp;
    }
} catch (PDOException $e) {}

// Count
$will_mark = 0;
$already_recorded = 0;
foreach ($preview as $p) {
    if ($p['attendance_status']) $already_recorded++;
    else $will_mark++;
}

// Recent absence history
$recent_absences = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.date, e.first_name, e.last_name, e.employee_code, a.notes
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        WHERE a.status = 'absent' AND a.notes LIKE '%Auto-marked%'
        ORDER BY a.date DESC, a.id DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_absences = $stmt->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Mark Absences';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Header banner -->
<div class="admin-panel" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border:none;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <h2 style="color:#fff;font-size:1.6rem;margin-bottom:4px;">
                <i class="fas fa-user-clock"></i> Mark Absences
            </h2>
            <p style="color:rgba(255,255,255,0.85);font-size:0.92rem;">
                Auto-mark employees absent if they have a scheduled shift but no clock-in record
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Holiday warning -->
<?php if ($is_holiday): ?>
    <div class="flash flash-warning" style="margin-top:20px;">
        <i class="fas fa-calendar-day"></i>
        <strong><?= e($target_date) ?> is a holiday: <?= e($is_holiday) ?></strong>
        — Running absence check will skip this date.
    </div>
<?php endif; ?>

<!-- Action form -->
<div class="admin-panel" style="margin-top:20px;">
    <h3><i class="fas fa-play"></i> Run Absence Check</h3>

    <form method="post" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;padding:20px;background:var(--bg-alt);border-radius:12px;margin-top:16px;">
        <div style="flex:1;min-width:200px;">
            <label>Target Date</label>
            <input type="date" name="target_date" value="<?= e($target_date) ?>">
        </div>
        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-warning btn-lg"
                    onclick="return confirm('Mark <?= $will_mark ?> employee(s) as absent for <?= e($target_date) ?>?\n\nAlready recorded: <?= $already_recorded ?>\n\nContinue?');">
                <i class="fas fa-play"></i> Run Check
            </button>
            <a href="run_absence_check.php" class="btn btn-outline btn-lg">Reset</a>
        </div>
    </form>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:20px;">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div>
                <div class="stat-value"><?= count($preview) ?></div>
                <div class="stat-label">Scheduled Today</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="stat-value"><?= $already_recorded ?></div>
                <div class="stat-label">Already Recorded</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-user-xmark"></i></div>
            <div>
                <div class="stat-value"><?= $will_mark ?></div>
                <div class="stat-label">Will Be Marked Absent</div>
            </div>
        </div>
    </div>
</div>

<!-- Terminal output (after running) -->
<?php if ($result !== null): ?>
    <div class="admin-panel">
        <h3><i class="fas fa-terminal"></i> Output</h3>
        <div style="background:#0f172a;color:#10b981;padding:20px;border-radius:12px;font-family:'Courier New',monospace;font-size:0.85rem;line-height:1.6;white-space:pre-wrap;overflow-x:auto;max-height:400px;overflow-y:auto;">
<?= e($result) ?>
        </div>
    </div>
<?php endif; ?>

<!-- Preview table -->
<div class="admin-panel" style="margin-top:20px;">
    <h3><i class="fas fa-list"></i> Preview — <?= e(date('l, F j, Y', strtotime($target_date))) ?></h3>

    <?php if (empty($preview)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
            <i class="fas fa-calendar-xmark" style="font-size:3rem;opacity:0.3;display:block;margin-bottom:14px;"></i>
            <p>No employees are scheduled for this date.</p>
            <p style="font-size:0.85rem;">(Day <?= $day_of_week ?> = <?= ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][$day_of_week] ?>)</p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Employee</th>
                        <th>Shift</th>
                        <th>Current Status</th>
                        <th>Action if Run</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview as $p): ?>
                        <tr>
                            <td><strong><?= e($p['employee_code']) ?></strong></td>
                            <td><?= e($p['first_name'] . ' ' . $p['last_name']) ?></td>
                            <td><?= e($p['shift_name']) ?></td>
                            <td>
                                <?php if ($p['attendance_status']): ?>
                                    <?php
                                        $badge = 'success';
                                        if ($p['attendance_status'] === 'late') $badge = 'warning';
                                        elseif ($p['attendance_status'] === 'absent') $badge = 'danger';
                                    ?>
                                    <span class="badge badge-<?= $badge ?>"><?= e(ucfirst($p['attendance_status'])) ?></span>
                                    <?php if ($p['clock_in']): ?>
                                        <span style="font-size:0.75rem;color:var(--text-muted);margin-left:6px;">
                                            <?= e(date('H:i', strtotime($p['clock_in']))) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge" style="background:var(--bg-alt);color:var(--text-muted);">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['attendance_status']): ?>
                                    <span style="color:var(--text-muted);font-size:0.85rem;">
                                        <i class="fas fa-check"></i> Skip (already recorded)
                                    </span>
                                <?php else: ?>
                                    <span style="color:#ef4444;font-weight:700;font-size:0.85rem;">
                                        <i class="fas fa-xmark"></i> Mark absent
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Recent auto-marked history -->
<?php if (!empty($recent_absences)): ?>
    <div class="admin-panel" style="margin-top:20px;">
        <h3><i class="fas fa-history"></i> Recent Auto-Marked Absences</h3>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Code</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_absences as $a): ?>
                        <tr>
                            <td><strong><?= e(date('M j, Y', strtotime($a['date']))) ?></strong></td>
                            <td><?= e($a['first_name'] . ' ' . $a['last_name']) ?></td>
                            <td><?= e($a['employee_code']) ?></td>
                            <td style="font-size:0.85rem;color:var(--text-muted);"><?= e($a['notes']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
