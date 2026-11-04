<?php
// user/settings.php — Member Preferences & Library Calendar

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fixed role check
if (!$user || ($user['role'] !== 'member' && $user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : 'Main Branch';
$all_branches = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();

$flash = '';
$error = '';

// ---- UNIFIED UPDATE: ALL PREFERENCES IN ONE POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_preferences') {
    $email_overdue    = isset($_POST['email_overdue']) ? '1' : '0';
    $email_reports    = isset($_POST['email_reports']) ? '1' : '0';
    $sms_alerts       = isset($_POST['sms_alerts']) ? '1' : '0';
    $reading_goal     = max(1, (int)($_POST['reading_goal'] ?? 12));
    $pickup_location  = $_POST['pickup_location'] ?? $branch_name;

    try {
        $pdo->beginTransaction();
        $values = [
            'email_overdue'   => $email_overdue,
            'email_reports'   => $email_reports,
            'sms_alerts'      => $sms_alerts,
            'reading_goal'    => (string)$reading_goal,
            'pickup_location' => $pickup_location,
        ];
        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ");
        foreach ($values as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        $pdo->commit();

        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $log_stmt->execute([
            $user['id'],
            'settings.update',
            'Member updated preferences',
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $flash = 'Preferences updated successfully.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to save preferences: ' . $e->getMessage();
    }
}

$current_email_overdue = get_setting('email_overdue', '1');
$current_email_reports = get_setting('email_reports', '1');
$current_sms           = get_setting('sms_alerts', '0');
$current_reading_goal  = (int)get_setting('reading_goal', '12');
$current_pickup        = get_setting('pickup_location', $branch_name);

// ---- CALENDAR LOGIC ----
$current_month = (int)date('n');
$current_year  = (int)date('Y');
$month_name    = date('F Y');
$days_in_month = (int)date('t', strtotime("$current_year-$current_month-01"));
$today         = (int)date('j');
$first_day_of_week = (int)date('N', strtotime("$current_year-$current_month-01")); // 1 (Mon) to 7 (Sun)

$page_title = 'Settings';
require_once __DIR__ . '/../includes/user_header.php';
?>

<!-- Hero Banner -->
<div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); padding: 40px; margin-bottom: 32px; position: relative; overflow: hidden;">
    <div style="position: relative; z-index: 2;">
        <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text); margin-bottom: 8px;">Settings</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; margin: 0;">
            <?= $branch_name ? 'Preferences for <strong>' . e($branch_name) . '</strong>.' : 'Customize your experience and manage your account.' ?>
        </p>
    </div>
    <i class="fas fa-cog" style="position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: #10b981; opacity: 0.05; z-index: 1; transform: rotate(-15deg);"></i>
</div>

<?php if ($flash): ?>
    <div class="flash flash-success" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; color: #10b981; font-weight: 600;">
        <i class="fas fa-check-circle"></i> <?= e($flash) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="flash flash-error" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; color: #ef4444; font-weight: 600;">
        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="action" value="update_preferences">

    <div class="grid-2" style="align-items: start; gap: 32px;">

        <!-- LEFT COLUMN: Preferences -->
        <div style="display: flex; flex-direction: column; gap: 32px;">

            <!-- Reading Goal -->
            <div class="card">
                <h3><i class="fas fa-bullseye" style="color: #10b981;"></i> Reading Goal</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px;">Set how many books you want to read this year.</p>
                <div class="form-group">
                    <label>Books to read in <?= $current_year ?></label>
                    <input type="number" name="reading_goal" value="<?= $current_reading_goal ?>" min="1" max="365" style="width: 100%; padding: 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 1.2rem; font-weight: 700;">
                </div>
            </div>

            <!-- Notifications -->
            <div class="card">
                <h3><i class="fas fa-bell" style="color: #f59e0b;"></i> Notifications</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px;">Choose which alerts you want to receive.</p>

                <div style="background: var(--bg-alt); border-radius: 12px; padding: 16px; margin-bottom: 12px; border: 1px solid var(--border);">
                    <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                        <input type="checkbox" name="email_overdue" value="1" <?= $current_email_overdue === '1' ? 'checked' : '' ?> style="margin-top: 4px; width: 18px; height: 18px; accent-color: #10b981; cursor: pointer;">
                        <div>
                            <div style="font-weight: 700; color: var(--text); font-size: 0.95rem;">Overdue Book Alerts</div>
                            <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">Receive an email when a book is due soon.</div>
                        </div>
                    </label>
                </div>

                <div style="background: var(--bg-alt); border-radius: 12px; padding: 16px; margin-bottom: 12px; border: 1px solid var(--border);">
                    <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                        <input type="checkbox" name="email_reports" value="1" <?= $current_email_reports === '1' ? 'checked' : '' ?> style="margin-top: 4px; width: 18px; height: 18px; accent-color: #10b981; cursor: pointer;">
                        <div>
                            <div style="font-weight: 700; color: var(--text); font-size: 0.95rem;">Monthly Reading Reports</div>
                            <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">Get a monthly summary of your reading activity.</div>
                        </div>
                    </label>
                </div>

                <div style="background: var(--bg-alt); border-radius: 12px; padding: 16px; border: 1px solid var(--border);">
                    <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
                        <input type="checkbox" name="sms_alerts" value="1" <?= $current_sms === '1' ? 'checked' : '' ?> style="margin-top: 4px; width: 18px; height: 18px; accent-color: #10b981; cursor: pointer;">
                        <div>
                            <div style="font-weight: 700; color: var(--text); font-size: 0.95rem;">SMS Alerts</div>
                            <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">Receive a text message for overdue reminders.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Pickup Location -->
            <div class="card">
                <h3><i class="fas fa-map-marker-alt" style="color: #ef4444;"></i> Preferred Pickup Location</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px;">Where do you usually pick up your reservations?</p>
                <select name="pickup_location" style="width: 100%; padding: 14px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 1rem; font-weight: 600; cursor: pointer;">
                    <?php foreach ($all_branches as $b): ?>
                        <option value="<?= e($b['name']) ?>" <?= $current_pickup === $b['name'] ? 'selected' : '' ?>>🏛️ <?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-primary" style="padding: 16px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4);">
                <i class="fas fa-save" style="margin-right: 8px;"></i> Save All Preferences
            </button>

        </div>

        <!-- RIGHT COLUMN: Calendar & Info -->
        <div style="display: flex; flex-direction: column; gap: 32px;">

            <!-- Modern Calendar -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;"><i class="fas fa-calendar-alt" style="color: #0ea5e9;"></i> Library Calendar</h3>
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;"><?= e($month_name) ?></span>
                </div>

                <div style="background: var(--bg-alt); border-radius: 16px; padding: 16px; border: 1px solid var(--border);">
                    <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; font-size: 0.8rem;">
                        <div style="font-weight: 700; color: var(--text-muted); padding-bottom: 8px;">Mo</div>
                        <div style="font-weight: 700; color: var(--text-muted); padding-bottom: 8px;">Tu</div>
                        <div style="font-weight: 700; color: var(--text-muted); padding-bottom: 8px;">We</div>
                        <div style="font-weight: 700; color: var(--text-muted); padding-bottom: 8px;">Th</div>
                        <div style="font-weight: 700; color: var(--text-muted); padding-bottom: 8px;">Fr</div>
                        <div style="font-weight: 700; color: var(--text-muted); padding-bottom: 8px;">Sa</div>
                        <div style="font-weight: 700; color: var(--text-muted); padding-bottom: 8px;">Su</div>

                        <?php
                        // Empty slots before the 1st
                        for ($i = 1; $i < $first_day_of_week; $i++) {
                            echo '<div></div>';
                        }
                        // Days of the month
                        for ($d = 1; $d <= $days_in_month; $d++) {
                            $day_of_week = ((($first_day_of_week + $d - 2) % 7) + 1); // 1=Mon, 7=Sun
                            $is_weekend = ($day_of_week >= 6);
                            $is_today   = ($d == $today);

                            $style = 'padding: 8px 0; border-radius: 8px; font-weight: 600;';
                            if ($is_today) {
                                $style .= ' background: #10b981; color: white; box-shadow: 0 4px 10px rgba(16,185,129,0.4);';
                            } elseif ($is_weekend) {
                                $style .= ' color: var(--danger); opacity: 0.6;';
                            } else {
                                $style .= ' color: var(--text);';
                            }
                            echo "<div style=\"$style\">$d</div>";
                        }
                        ?>
                    </div>
                </div>

                <div style="margin-top: 16px; display: flex; gap: 16px; font-size: 0.8rem; justify-content: center;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 10px; height: 10px; border-radius: 50%; background: #10b981;"></div>
                        <span style="color: var(--text-muted);">Today</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 10px; height: 10px; border-radius: 50%; background: var(--danger); opacity: 0.6;"></div>
                        <span style="color: var(--text-muted);">Closed (Weekends)</span>
                    </div>
                </div>
            </div>

            <!-- Upcoming Closures -->
            <div class="card">
                <h3><i class="fas fa-door-closed" style="color: #f59e0b;"></i> Upcoming Closures</h3>
                <div style="margin-top: 16px; display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; justify-content: space-between; padding: 12px; background: var(--bg-alt); border-radius: 8px; border: 1px solid var(--border);">
                        <span style="font-weight: 600; color: var(--text);">All Saints' Day</span>
                        <span style="color: var(--text-muted);">Nov 1</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 12px; background: var(--bg-alt); border-radius: 8px; border: 1px solid var(--border);">
                        <span style="font-weight: 600; color: var(--text);">Independence Day</span>
                        <span style="color: var(--text-muted);">Dec 6</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 12px; background: var(--bg-alt); border-radius: 8px; border: 1px solid var(--border);">
                        <span style="font-weight: 600; color: var(--text);">Christmas Eve</span>
                        <span style="color: var(--text-muted);">Dec 24</span>
                    </div>
                </div>
            </div>

            <!-- My Reports -->
            <div class="card">
                <h3><i class="fas fa-chart-bar" style="color: #0ea5e9;"></i> My Reports</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px;">View a summary of your reading activity.</p>
                <a href="<?= e(SITE_URL) ?>user/reports.php" class="btn-primary" style="display: block; text-align: center; padding: 14px; border-radius: 8px; background: linear-gradient(135deg, #10b981, #059669); color: white; text-decoration: none; font-weight: 700;">
                    <i class="fas fa-file-alt"></i> Open My Reports
                </a>
            </div>

        </div>
    </div>
</form>

<!-- Terms & Conditions (Modern Document Style) -->
<div class="card" style="margin-top: 32px; padding: 0; overflow: hidden; border: 1px solid var(--border);">
    <div style="padding: 24px; background: var(--bg-alt); border-bottom: 1px solid var(--border);">
        <h3 style="margin: 0; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-file-contract" style="color: #10b981;"></i> Terms & Conditions
        </h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 4px 0 0 0;">Please read these terms carefully before using the library.</p>
    </div>

    <div style="max-height: 400px; overflow-y: auto; padding: 32px; line-height: 1.8; color: var(--text); font-size: 0.9rem;">
        <div style="background: rgba(16, 185, 129, 0.05); border-left: 4px solid #10b981; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
            <strong style="display: block; margin-bottom: 4px;">Last Updated: <?= date('F j, Y') ?></strong>
            <span style="color: var(--text-muted);">By using the Kirjasto 2026 library system, you agree to the following terms.</span>
        </div>

        <h4 style="color: var(--text); font-size: 1rem; font-weight: 800; margin: 24px 0 8px 0; padding-bottom: 8px; border-bottom: 1px solid var(--border);">1. Membership & Borrowing</h4>
        <p style="margin-bottom: 16px; color: var(--text-muted);">Members may borrow up to 10 items at a time. The standard loan period is 14 days, renewable once if no other member has reserved the item. You are responsible for all items borrowed on your card.</p>

        <h4 style="color: var(--text); font-size: 1rem; font-weight: 800; margin: 24px 0 8px 0; padding-bottom: 8px; border-bottom: 1px solid var(--border);">2. Returns & Overdue Items</h4>
        <p style="margin-bottom: 16px; color: var(--text-muted);">Items must be returned by the due date. Overdue items will incur a fine of €0.50 per day per item. If an item is lost or damaged, you will be charged the replacement cost.</p>

        <h4 style="color: var(--text); font-size: 1rem; font-weight: 800; margin: 24px 0 8px 0; padding-bottom: 8px; border-bottom: 1px solid var(--border);">3. Fines & Payments</h4>
        <p style="margin-bottom: 16px; color: var(--text-muted);">Outstanding fines must be paid before new items can be borrowed. Payments can be made online, at the front desk, or via mobile payment. A receipt will be issued for every payment.</p>

        <h4 style="color: var(--text); font-size: 1rem; font-weight: 800; margin: 24px 0 8px 0; padding-bottom: 8px; border-bottom: 1px solid var(--border);">4. Personal Data (GDPR)</h4>
        <p style="margin-bottom: 16px; color: var(--text-muted);">Your personal data is stored securely and never shared with third parties without your consent. You have the right to access, correct, or delete your data at any time by contacting the library.</p>

        <h4 style="color: var(--text); font-size: 1rem; font-weight: 800; margin: 24px 0 8px 0; padding-bottom: 8px; border-bottom: 1px solid var(--border);">5. Contact & Support</h4>
        <p style="margin-bottom: 16px; color: var(--text-muted);">If you have any questions about these terms, please contact us at <strong>info@kirjasto.local</strong> or visit the front desk at your branch.</p>
    </div>
</div>

<!-- Danger Zone (Original) -->
<div class="card" style="margin-top: 32px; border-color: rgba(239, 68, 68, 0.3); background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), transparent);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h3 style="color: #ef4444; margin-bottom: 4px;"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">Once you delete your account, there is no going back.</p>
        </div>
        <button type="button" onclick="alert('Please contact library staff to delete your account.')" style="padding: 12px 24px; background: transparent; color: #ef4444; border: 1px solid #ef4444; border-radius: 8px; font-weight: 700; cursor: pointer;">
            Delete Account
        </button>
    </div>
</div>


<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
