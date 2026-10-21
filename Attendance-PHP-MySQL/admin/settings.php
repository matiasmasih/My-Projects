<?php
// admin/settings.php — Full application settings (all fields save correctly)

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$flash = '';

// All allowed setting keys with types
$allowed = [
    // Company
    'company_name'              => 'text',
    'company_address'           => 'text',
    'company_phone'             => 'text',
    'company_email'             => 'text',
    'currency'                  => 'text',
    'timezone'                  => 'text',
    'logo_url'                  => 'text',

    // Attendance rules
    'work_day_hours'            => 'number',
    'late_threshold_minutes'    => 'number',
    'overtime_threshold_minutes'=> 'number',
    'auto_absence_enabled'      => 'bool',
    'attendance_mode'           => 'text',
    'week_start_day'            => 'text',

    // Work schedule (special: checkbox array)
    'work_days'                 => 'array',

    // Email / SMTP
    'smtp_host'                 => 'text',
    'smtp_port'                 => 'number',
    'smtp_username'             => 'text',
    'smtp_password'             => 'text',
    'smtp_from_name'            => 'text',
    'smtp_from_email'           => 'text',

    // Notifications
    'notify_late'               => 'bool',
    'notify_absent'             => 'bool',
    'notify_weekly'             => 'bool',

    // System
    'maintenance_mode'          => 'bool',
    'debug_mode'                => 'bool',
];

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($allowed as $key => $type) {
            // Determine value and DB type
            if ($type === 'bool') {
                $val = isset($_POST[$key]) ? '1' : '0';
                $db_type = 'bool';
            } elseif ($type === 'array') {
                $selected = isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : [];
                $val = implode(',', array_map('intval', $selected));
                $db_type = 'text';
            } else {
                if (!isset($_POST[$key])) continue;
                $val = trim((string)$_POST[$key]);
                $db_type = 'text';
            }

            $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_type)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    setting_type = VALUES(setting_type)
            ")->execute([$key, $val, $db_type]);
        }
        $flash = 'Settings saved successfully.';
    } catch (PDOException $e) {
        error_log('settings: ' . $e->getMessage());
        $flash = 'ERROR: ' . $e->getMessage();
    }
}

// Load current settings
$current = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
    foreach ($rows as $row) $current[$row['setting_key']] = $row['setting_value'];
} catch (PDOException $e) {}

// Helper
function is_on($key, $default = '0') {
    global $current;
    return ($current[$key] ?? $default) === '1';
}

$all_days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
$work_days_list = explode(',', $current['work_days'] ?? '1,2,3,4,5');

$page_title = 'Settings';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-panel">
    <h3><i class="fas fa-gear"></i> Application Settings</h3>

    <?php if ($flash): ?>
        <div class="flash flash-success" style="margin-bottom:16px;">
            <i class="fas fa-check-circle"></i> <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <form method="post">

        <!-- ============ COMPANY ============ -->
        <h4 style="font-size:0.9rem;color:var(--text-muted);margin-bottom:16px;text-transform:uppercase;letter-spacing:0.05em;">
            <i class="fas fa-building"></i> Company Information
        </h4>

        <div class="grid-2">
            <div>
                <label>Company Name</label>
                <input type="text" name="company_name" value="<?= e($current['company_name'] ?? 'My Company') ?>">
            </div>
            <div>
                <label>Company Email</label>
                <input type="email" name="company_email" value="<?= e($current['company_email'] ?? '') ?>" placeholder="info@company.com">
            </div>
        </div>

        <div class="grid-2" style="margin-top:16px;">
            <div>
                <label>Phone</label>
                <input type="tel" name="company_phone" value="<?= e($current['company_phone'] ?? '') ?>" placeholder="+358 40 123 4567">
            </div>
            <div>
                <label>Address</label>
                <input type="text" name="company_address" value="<?= e($current['company_address'] ?? '') ?>" placeholder="Street 1, City">
            </div>
        </div>

        <div class="grid-2" style="margin-top:16px;">
            <div>
                <label>Currency Symbol</label>
                <input type="text" name="currency" value="<?= e($current['currency'] ?? '€') ?>">
            </div>
            <div>
                <label>Timezone</label>
                <input type="text" name="timezone" value="<?= e($current['timezone'] ?? 'Europe/Helsinki') ?>">
            </div>
        </div>

        <div style="margin-top:16px;">
            <label>Logo URL</label>
            <input type="text" name="logo_url" value="<?= e($current['logo_url'] ?? '') ?>" placeholder="assets/images/logo.png">
        </div>

        <!-- ============ ATTENDANCE RULES ============ -->
        <h4 style="font-size:0.9rem;color:var(--text-muted);margin:32px 0 16px;text-transform:uppercase;letter-spacing:0.05em;">
            <i class="fas fa-clock"></i> Attendance Rules
        </h4>

        <div class="grid-3">
            <div>
                <label>Work Day Hours</label>
                <input type="number" name="work_day_hours" min="1" max="24" value="<?= e($current['work_day_hours'] ?? '8') ?>">
            </div>
            <div>
                <label>Late Threshold (min)</label>
                <input type="number" name="late_threshold_minutes" min="0" value="<?= e($current['late_threshold_minutes'] ?? '15') ?>">
            </div>
            <div>
                <label>Overtime Threshold (min)</label>
                <input type="number" name="overtime_threshold_minutes" min="0" value="<?= e($current['overtime_threshold_minutes'] ?? '30') ?>">
            </div>
        </div>

        <div class="grid-2" style="margin-top:16px;">
            <div>
                <label>Attendance Mode</label>
                <select name="attendance_mode">
                    <option value="clock_in_out" <?= ($current['attendance_mode'] ?? '') === 'clock_in_out' ? 'selected' : '' ?>>Clock In / Clock Out</option>
                    <option value="manual" <?= ($current['attendance_mode'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual Entry Only</option>
                </select>
            </div>
            <div>
                <label>Week Starts On</label>
                <select name="week_start_day">
                    <option value="monday" <?= ($current['week_start_day'] ?? 'monday') === 'monday' ? 'selected' : '' ?>>Monday</option>
                    <option value="sunday" <?= ($current['week_start_day'] ?? '') === 'sunday' ? 'selected' : '' ?>>Sunday</option>
                </select>
            </div>
        </div>

        <div style="margin-top:16px;padding:14px 18px;background:var(--bg-alt);border-radius:8px;">
            <label style="display:flex;gap:10px;align-items:center;cursor:pointer;margin:0;">
                <input type="checkbox" name="auto_absence_enabled" value="1" 
                       <?= is_on('auto_absence_enabled', '1') ? 'checked' : '' ?>
                       style="width:auto;accent-color:var(--primary);">
                <div>
                    <div style="font-weight:600;">Auto-Absence Tracking</div>
                    <div style="font-size:0.82rem;color:var(--text-muted);">Automatically mark absent when employee doesn't clock in on scheduled shift</div>
                </div>
            </label>
        </div>

        <!-- ============ WORK SCHEDULE ============ -->
        <h4 style="font-size:0.9rem;color:var(--text-muted);margin:32px 0 16px;text-transform:uppercase;letter-spacing:0.05em;">
            <i class="fas fa-calendar"></i> Default Work Days
        </h4>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($all_days as $num => $label): ?>
                <label style="display:flex;align-items:center;gap:6px;padding:10px 16px;background:var(--card-bg);border:1.5px solid var(--border);border-radius:8px;cursor:pointer;font-weight:600;font-size:0.85rem;">
                    <input type="checkbox" name="work_days[]" value="<?= $num ?>" 
                           <?= in_array((string)$num, $work_days_list) ? 'checked' : '' ?>
                           style="accent-color:var(--primary);">
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>

        <!-- ============ EMAIL / SMTP ============ -->
        <h4 style="font-size:0.9rem;color:var(--text-muted);margin:32px 0 16px;text-transform:uppercase;letter-spacing:0.05em;">
            <i class="fas fa-envelope"></i> Email / SMTP
        </h4>

        <div class="grid-2">
            <div>
                <label>SMTP Host</label>
                <input type="text" name="smtp_host" value="<?= e($current['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
            </div>
            <div>
                <label>SMTP Port</label>
                <input type="number" name="smtp_port" value="<?= e($current['smtp_port'] ?? '587') ?>" placeholder="587">
            </div>
        </div>

        <div class="grid-2" style="margin-top:16px;">
            <div>
                <label>SMTP Username</label>
                <input type="text" name="smtp_username" value="<?= e($current['smtp_username'] ?? '') ?>">
            </div>
            <div>
                <label>SMTP Password</label>
                <input type="password" name="smtp_password" value="<?= e($current['smtp_password'] ?? '') ?>">
            </div>
        </div>

        <div class="grid-2" style="margin-top:16px;">
            <div>
                <label>From Name</label>
                <input type="text" name="smtp_from_name" value="<?= e($current['smtp_from_name'] ?? '') ?>" placeholder="Attendance System">
            </div>
            <div>
                <label>From Email</label>
                <input type="email" name="smtp_from_email" value="<?= e($current['smtp_from_email'] ?? '') ?>" placeholder="noreply@company.com">
            </div>
        </div>

        <!-- ============ NOTIFICATIONS ============ -->
        <h4 style="font-size:0.9rem;color:var(--text-muted);margin:32px 0 16px;text-transform:uppercase;letter-spacing:0.05em;">
            <i class="fas fa-bell"></i> Notifications
        </h4>

        <div style="display:flex;flex-direction:column;gap:10px;">
            <label style="display:flex;gap:10px;align-items:center;padding:14px 18px;background:var(--bg-alt);border-radius:8px;cursor:pointer;">
                <input type="checkbox" name="notify_late" value="1" <?= is_on('notify_late') ? 'checked' : '' ?> style="width:auto;accent-color:var(--primary);">
                <div>
                    <div style="font-weight:600;">Notify on Late Arrival</div>
                    <div style="font-size:0.82rem;color:var(--text-muted);">Send email when someone clocks in late</div>
                </div>
            </label>

            <label style="display:flex;gap:10px;align-items:center;padding:14px 18px;background:var(--bg-alt);border-radius:8px;cursor:pointer;">
                <input type="checkbox" name="notify_absent" value="1" <?= is_on('notify_absent') ? 'checked' : '' ?> style="width:auto;accent-color:var(--primary);">
                <div>
                    <div style="font-weight:600;">Notify on Absence</div>
                    <div style="font-size:0.82rem;color:var(--text-muted);">Send email when someone is marked absent</div>
                </div>
            </label>

            <label style="display:flex;gap:10px;align-items:center;padding:14px 18px;background:var(--bg-alt);border-radius:8px;cursor:pointer;">
                <input type="checkbox" name="notify_weekly" value="1" <?= is_on('notify_weekly') ? 'checked' : '' ?> style="width:auto;accent-color:var(--primary);">
                <div>
                    <div style="font-weight:600;">Weekly Summary</div>
                    <div style="font-size:0.82rem;color:var(--text-muted);">Send weekly attendance summary every Monday</div>
                </div>
            </label>
        </div>

        <!-- ============ SYSTEM ============ -->
        <h4 style="font-size:0.9rem;color:var(--text-muted);margin:32px 0 16px;text-transform:uppercase;letter-spacing:0.05em;">
            <i class="fas fa-tools"></i> System
        </h4>

        <div style="display:flex;flex-direction:column;gap:10px;">
            <label style="display:flex;gap:10px;align-items:center;padding:14px 18px;background:var(--bg-alt);border-radius:8px;cursor:pointer;">
                <input type="checkbox" name="maintenance_mode" value="1" <?= is_on('maintenance_mode') ? 'checked' : '' ?> style="width:auto;accent-color:var(--primary);">
                <div>
                    <div style="font-weight:600;">Maintenance Mode</div>
                    <div style="font-size:0.82rem;color:var(--text-muted);">Only admins can access the site</div>
                </div>
            </label>

            <label style="display:flex;gap:10px;align-items:center;padding:14px 18px;background:var(--bg-alt);border-radius:8px;cursor:pointer;">
                <input type="checkbox" name="debug_mode" value="1" <?= is_on('debug_mode') ? 'checked' : '' ?> style="width:auto;accent-color:var(--primary);">
                <div>
                    <div style="font-weight:600;">Debug Mode</div>
                    <div style="font-size:0.82rem;color:var(--text-muted);">Show detailed error messages (disable in production)</div>
                </div>
            </label>
        </div>

        <!-- ============ SAVE ============ -->
        <div style="margin-top:32px;padding-top:20px;border-top:1px solid var(--border);">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Save All Settings
            </button>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
