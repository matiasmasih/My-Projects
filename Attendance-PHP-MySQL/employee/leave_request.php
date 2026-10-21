<?php
// employee/leave_request.php — Submit and view leave requests

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch employee details
$stmtUser = $pdo->prepare("SELECT e.* FROM employees e JOIN users u ON u.employee_id = e.id WHERE u.id = ? LIMIT 1");
$stmtUser->execute([$user_id]);
$employee = $stmtUser->fetch();
$employee_id = $employee['id'] ?? 0;

if (!$employee) {
    die("Employee record not found. Please contact admin.");
}

$flash = '';
$error = '';

// Handle new leave request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_leave') {
    $type = $_POST['type'] ?? 'vacation';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (empty($start_date) || empty($end_date) || empty($reason)) {
        $error = 'Please fill in all fields.';
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $error = 'End date cannot be before start date.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO leaves (employee_id, type, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$employee_id, $type, $start_date, $end_date, $reason]);
            $flash = 'Leave request submitted successfully!';
        } catch (PDOException $e) {
            $error = 'Error submitting request: ' . $e->getMessage();
        }
    }
}

// Fetch past leave requests
$stmt = $pdo->prepare("SELECT * FROM leaves WHERE employee_id = ? ORDER BY created_at DESC");
$stmt->execute([$employee_id]);
$leaves = $stmt->fetchAll();

$page_title = 'Leave Requests';
require_once __DIR__ . '/../includes/employee_header.php';
?>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- Flatpickr Dark Theme -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">

<div class="dashboard-container">

    <!-- HERO BANNER -->
    <div class="hero-banner">
        <div class="hero-content">
            <h1>Leave Requests</h1>
            <p><i class="fas fa-calendar-alt"></i> Manage your time off and view past requests.</p>
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

    <div class="leave-layout">

        <!-- Left Column: Form -->
        <div class="leave-form-card">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Request Time Off</h3>

            <form method="post">
                <input type="hidden" name="action" value="submit_leave">

                <div class="form-group">
                    <label>Leave Type</label>
                    <select name="type" required>
                        <option value="vacation">Vacation</option>
                        <option value="sick">Sick Leave</option>
                        <option value="personal">Personal</option>
                        <option value="unpaid">Unpaid Leave</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Start Date</label>
                    <!-- Added class="datepicker" and removed type="date" -->
                    <input type="text" name="start_date" id="start_date" class="datepicker" required placeholder="Select date...">
                </div>

                <div class="form-group">
                    <label>End Date</label>
                    <!-- Added class="datepicker" and removed type="date" -->
                    <input type="text" name="end_date" id="end_date" class="datepicker" required placeholder="Select date...">
                </div>

                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" required placeholder="Briefly explain the reason for your leave..."></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    Submit Request
                </button>
            </form>
        </div>

        <!-- Right Column: Table -->
        <div class="table-container" style="margin-top:0;">
            <?php if (empty($leaves)): ?>
                <div style="padding: 60px 20px; text-align: center; color: var(--text-muted);">
                    <i class="fas fa-plane" style="font-size: 3rem; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                    <h3 style="margin-bottom: 8px;">No leave requests yet</h3>
                    <p>Submit your first request using the form.</p>
                </div>
            <?php else: ?>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Dates</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaves as $leave): ?>
                            <tr>
                                <td style="font-weight: 600; text-transform: capitalize;">
                                    <?= e($leave['type']) ?>
                                </td>
                                <td>
                                    <?= date('M j', strtotime($leave['start_date'])) ?>
                                    -
                                    <?= date('M j, Y', strtotime($leave['end_date'])) ?>
                                </td>
                                <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($leave['reason']) ?>">
                                    <?= e($leave['reason']) ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= e($leave['status']) ?>">
                                        <?= ucfirst($leave['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Determine current theme to apply to Flatpickr
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

        // Initialize Flatpickr
        flatpickr(".datepicker", {
            dateFormat: "Y-m-d", // MySQL format
            altInput: true,
            altFormat: "F j, Y", // Human readable format
            minDate: "today",
            disableMobile: "true", // Force the beautiful custom calendar on mobile too
            theme: isDark ? "dark" : "light"
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/employee_footer.php'; ?>
