<?php
// employee/dashboard.php — Employee clock in/out + daily stats

require_once __DIR__ . '/../includes/config.php';

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch employee details securely
$stmtUser = $pdo->prepare("SELECT e.*, b.name AS branch_name, d.name AS dept_name
                           FROM employees e
                           LEFT JOIN branches b ON b.id = e.branch_id
                           LEFT JOIN departments d ON d.id = e.department_id
                           WHERE e.id = (SELECT employee_id FROM users WHERE id = ?) LIMIT 1");
$stmtUser->execute([$user_id]);
$employee = $stmtUser->fetch();
$employee_id = $employee['id'] ?? 0;

if (!$employee) {
    die("Employee record not found. Please contact admin.");
}

$today = date('Y-m-d');
$flash = '';
$error = '';

// Handle Clock In / Clock Out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $now = date('Y-m-d H:i:s');

    if ($action === 'clock_in') {
        $check = $pdo->prepare("SELECT id, clock_in FROM attendance WHERE employee_id = ? AND date = ?");
        $check->execute([$employee_id, $today]);
        $existing = $check->fetch();

        if ($existing && $existing['clock_in']) {
            $error = 'You have already clocked in today.';
        } else {
            if ($existing) {
                $pdo->prepare("UPDATE attendance SET clock_in = ?, status = 'present' WHERE id = ?")
                    ->execute([$now, $existing['id']]);
            } else {
                $pdo->prepare("INSERT INTO attendance (employee_id, date, clock_in, status) VALUES (?, ?, ?, 'present')")
                    ->execute([$employee_id, $today, $now]);
            }
            $flash = 'Clocked in successfully at ' . date('h:i A');
        }
    }
    elseif ($action === 'clock_out') {
        $check = $pdo->prepare("SELECT id, clock_in, clock_out FROM attendance WHERE employee_id = ? AND date = ?");
        $check->execute([$employee_id, $today]);
        $existing = $check->fetch();

        if (!$existing || !$existing['clock_in']) {
            $error = 'You need to clock in first.';
        } elseif ($existing['clock_out']) {
            $error = 'You have already clocked out today.';
        } else {
            $clock_in = new DateTime($existing['clock_in']);
            $clock_out = new DateTime($now);
            $diff = $clock_in->diff($clock_out);
            $total_minutes = ($diff->h * 60) + $diff->i;

            $pdo->prepare("UPDATE attendance SET clock_out = ?, total_minutes = ? WHERE id = ?")
                ->execute([$now, $total_minutes, $existing['id']]);

            $flash = 'Clocked out successfully at ' . date('h:i A');
        }
    }
}

// Fetch today's record
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ? LIMIT 1");
$stmt->execute([$employee_id, $today]);
$today_record = $stmt->fetch();

$is_clocked_in = ($today_record && $today_record['clock_in'] && !$today_record['clock_out']);
$is_done = ($today_record && $today_record['clock_out']);

$page_title = 'My Dashboard';
require_once __DIR__ . '/../includes/employee_header.php';
?>

<div class="dashboard-container">

    <!-- HERO BANNER -->
    <div class="hero-banner">
        <div class="hero-content">
            <h1>Welcome back, <?= e($employee['first_name'] ?? 'Employee') ?>!</h1>
            <p><i class="fas fa-clock"></i> Ready to start your shift?</p>
        </div>
        <div class="hero-datetime">
            <div class="time" id="bannerTime"><?= date('h:i:s A') ?></div>
            <div class="date" id="bannerDate"><?= date('l, M j, Y') ?></div>
        </div>
    </div>

    <!-- FLASH MESSAGES -->
    <?php if ($flash): ?>
        <div class="flash flash-success" style="margin-bottom:20px; border-radius: 12px;">
            <i class="fas fa-check-circle"></i> <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="flash flash-error" style="margin-bottom:20px; display:block; border-radius: 12px;">
            <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- MAIN DASHBOARD GRID -->
    <div class="dashboard-grid">

        <!-- Left Column: Profile -->
        <div class="modern-card profile-modern">
            <div class="profile-avatar-container">
                <?php if (!empty($employee['photo']) && file_exists(__DIR__ . '/../uploads/' . $employee['photo'])): ?>
                    <img src="<?= SITE_URL ?>uploads/<?= e($employee['photo']) ?>?v=<?= time() ?>" alt="Profile" class="profile-avatar-img">
                <?php else: ?>
                    <div class="profile-avatar-placeholder">
                        <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <h2><?= e($employee['first_name'] . ' ' . $employee['last_name']) ?></h2>
            <div class="role-text"><?= e($employee['position'] ?: 'Employee') ?></div>

            <div class="profile-details">
                <div class="detail-row">
                    <span>Employee ID</span>
                    <span><?= e($employee['employee_code']) ?></span>
                </div>
                <div class="detail-row">
                    <span>Branch</span>
                    <span><?= e($employee['branch_name'] ?: 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span>Department</span>
                    <span><?= e($employee['dept_name'] ?: 'N/A') ?></span>
                </div>
            </div>
        </div>

        <!-- Right Column: Clock & Action -->
        <div class="modern-card clock-modern">

            <div id="liveClock" class="clock-display">
                <?= date('h:i:s') ?>
            </div>
            <div class="clock-date">
                <i class="far fa-calendar-alt"></i> <?= date('l, F j, Y') ?>
            </div>

            <?php if ($is_clocked_in): ?>
                <div class="status-badge-modern" style="border-color: rgba(16, 185, 129, 0.3); color: #10b981;">
                    <div class="pulse-dot"></div> Clocked in at <?= date('h:i A', strtotime($today_record['clock_in'])) ?>
                </div>
                <form method="post" class="action-form">
                    <button type="submit" name="action" value="clock_out" class="btn-glow clock-out">
                        <i class="fas fa-sign-out-alt"></i> Clock Out
                    </button>
                </form>

            <?php elseif ($is_done): ?>
                <div class="status-badge-modern" style="background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); color: #f59e0b;">
                    <i class="fas fa-check-circle"></i> Shift Complete (<?= round($today_record['total_minutes'] / 60, 2) ?> Hours)
                </div>
                <button type="button" class="btn-glow" disabled>
                    Done for today
                </button>

            <?php else: ?>
                <div class="status-badge-modern">
                    <i class="fas fa-info-circle" style="color: var(--primary);"></i> Ready to start
                </div>
                <form method="post" class="action-form">
                    <button type="submit" name="action" value="clock_in" class="btn-glow clock-in">
                        <i class="fas fa-sign-in-alt"></i> Clock In
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>


<?php require_once __DIR__ . '/../includes/employee_footer.php'; ?>
