<?php
// staff/settings.php — Staff Settings, Preferences & Security (Modern)

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fixed role check
if (!$user || ($user['role'] !== 'staff' && $user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$flash = '';
$error = '';

// ---- HANDLE FORM SUBMISSIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all password fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } else {
            if (password_verify($current_password, $user['password_hash'])) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $user['id']]);

                $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$user['id'], 'settings.password_change', 'Staff changed their password', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

                $flash = 'Password updated successfully.';
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }

    if ($action === 'update_preferences') {
        $default_loan_days = (int)($_POST['default_loan_days'] ?? 14);
        $daily_fine = (float)($_POST['daily_fine'] ?? 0.50);
        $email_overdue = isset($_POST['email_overdue']) ? '1' : '0';
        $email_reports = isset($_POST['email_reports']) ? '1' : '0';

        try {
            $settings = [
                'default_loan_days' => $default_loan_days,
                'daily_fine' => $daily_fine,
                'email_overdue' => $email_overdue,
                'email_reports' => $email_reports
            ];

            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
                $stmt->execute([$key, $value, $value]);
            }

            $flash = 'System preferences saved successfully.';
        } catch (PDOException $e) {
            $error = 'Failed to save preferences: ' . $e->getMessage();
        }
    }
}

// Fetch current preferences
$current_loan_days = get_setting('default_loan_days', '14');
$current_daily_fine = get_setting('daily_fine', '0.50');
$current_email_overdue = get_setting('email_overdue', '1');
$current_email_reports = get_setting('email_reports', '1');

// ---- SYSTEM STATUS STATS ----
$overdue_books = 0;
if ($branch_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE status = 'active' AND due_at < NOW() AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $overdue_books = $stmt->fetchColumn();
}

$page_title = 'Settings';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Settings</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Manage your account security and library preferences.
    </p>
</div>

<?php if ($flash): ?><div class="flash flash-success" style="margin-bottom: 24px;"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error" style="margin-bottom: 24px;"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2" style="align-items: start; gap: 32px;">

    <!-- LEFT COLUMN: Security & Preferences -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- Change Password -->
        <div class="card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); color: #0ea5e9; display: grid; place-items: center; font-size: 1.2rem;">
                    <i class="fas fa-lock"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem;">Security & Password</h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Keep your account secure.</p>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_password">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="8">
                    <small style="color: var(--text-muted); display: block; margin-top: 6px; font-size: 0.8rem;">Minimum 8 characters.</small>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="8">
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 12px; background: linear-gradient(135deg, #0ea5e9, #14b8a6);">
                    <i class="fas fa-key"></i> Update Password
                </button>
            </form>
        </div>

        <!-- System Status -->
        <div class="card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: grid; place-items: center; font-size: 1.2rem;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem;">System Status</h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Live branch health & alerts.</p>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border);">
                    <span style="color: var(--text-muted); font-size: 0.9rem;"><i class="fas fa-server"></i> Database</span>
                    <span style="color: var(--success); font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
                        <span style="width: 8px; height: 8px; background: var(--success); border-radius: 50%; display: inline-block;"></span> Online
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border);">
                    <span style="color: var(--text-muted); font-size: 0.9rem;"><i class="fas fa-building"></i> Current Branch</span>
                    <span style="color: var(--text); font-weight: 700; font-size: 0.85rem;"><?= e($branch_name ?? 'Unassigned') ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border);">
                    <span style="color: var(--text-muted); font-size: 0.9rem;"><i class="fas fa-clock"></i> Server Time</span>
                    <span style="color: var(--text); font-weight: 700; font-size: 0.85rem;"><?= date('H:i A') ?></span>
                </div>
            </div>

            <div style="margin-top: 16px; padding: 14px; background: rgba(239, 68, 68, 0.05); border-radius: 10px; border: 1px solid rgba(239, 68, 68, 0.2);">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                    <strong style="color: var(--danger); font-size: 0.9rem;">Overdue Alerts</strong>
                </div>
                <p style="margin: 0; color: var(--text-muted); font-size: 0.85rem;">
                    You currently have <strong style="color: var(--danger);"><?= $overdue_books ?> overdue book(s)</strong> in your branch.
                </p>
            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN: Calendar & Preferences -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- Modern Calendar -->
        <div class="card" style="padding: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); color: #0ea5e9; display: grid; place-items: center; font-size: 1.2rem;">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.1rem;">Branch Calendar</h3>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Schedule and events</p>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button onclick="changeMonth(-1)" style="width: 32px; height: 32px; border-radius: 8px; background: var(--bg-alt); border: 1px solid var(--border); color: var(--text); cursor: pointer; display: grid; place-items: center; transition: all 0.2s;" onmouseover="this.style.borderColor='#0ea5e9'" onmouseout="this.style.borderColor='var(--border)'">
                        <i class="fas fa-chevron-left" style="font-size: 0.8rem;"></i>
                    </button>
                    <button onclick="changeMonth(1)" style="width: 32px; height: 32px; border-radius: 8px; background: var(--bg-alt); border: 1px solid var(--border); color: var(--text); cursor: pointer; display: grid; place-items: center; transition: all 0.2s;" onmouseover="this.style.borderColor='#0ea5e9'" onmouseout="this.style.borderColor='var(--border)'">
                        <i class="fas fa-chevron-right" style="font-size: 0.8rem;"></i>
                    </button>
                </div>
            </div>

            <div style="background: var(--bg-alt); border-radius: 16px; padding: 20px; border: 1px solid var(--border);">
                <div id="calendarMonthYear" style="text-align: center; font-weight: 800; color: var(--text); font-size: 1.2rem; margin-bottom: 20px; letter-spacing: 0.5px;"></div>
                
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">
                    <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
                </div>
                
                <div id="calendarDays" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; text-align: center; font-size: 0.9rem;"></div>
            </div>
        </div>

        <!-- Library Preferences -->
        <div class="card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); color: #0ea5e9; display: grid; place-items: center; font-size: 1.2rem;">
                    <i class="fas fa-sliders-h"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem;">Library Preferences</h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Default loan rules and alerts.</p>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_preferences">

                <div class="grid-2">
                    <div class="form-group">
                        <label>Default Loan (Days)</label>
                        <input type="number" name="default_loan_days" value="<?= e($current_loan_days) ?>" min="1" max="60" required>
                    </div>
                    <div class="form-group">
                        <label>Daily Fine (€)</label>
                        <input type="number" name="daily_fine" value="<?= e($current_daily_fine) ?>" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 16px;">
                    <label style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border); cursor: pointer;">
                        <input type="checkbox" name="email_overdue" value="1" <?= $current_email_overdue === '1' ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: #0ea5e9;">
                        <span style="font-size: 0.9rem;">Email alerts for overdue books</span>
                    </label>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border); cursor: pointer;">
                        <input type="checkbox" name="email_reports" value="1" <?= $current_email_reports === '1' ? 'checked' : '' ?> style="width: 18px; height: 18px; accent-color: #0ea5e9;">
                        <span style="font-size: 0.9rem;">Receive daily shift reports</span>
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                    <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #0ea5e9, #14b8a6); border: none; border-radius: 8px; font-weight: 600; color: white; cursor: pointer;">
                        <i class="fas fa-check"></i> Save Preferences
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
    // --- Modern Calendar Logic ---
    let currentDate = new Date();

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        document.getElementById('calendarMonthYear').innerText = monthNames[month] + ' ' + year;

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        
        const today = new Date();
        const isToday = (d) => d === today.getDate() && month === today.getMonth() && year === today.getFullYear();

        let html = '';
        for (let i = 0; i < firstDay; i++) {
            html += '<div></div>';
        }
        for (let d = 1; d <= daysInMonth; d++) {
            if (isToday(d)) {
                html += `<div style="padding: 10px 0; border-radius: 10px; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; font-weight: 800; box-shadow: 0 4px 12px rgba(14,165,233,0.4); transform: scale(1.05); transition: transform 0.2s;">${d}</div>`;
            } else {
                html += `<div style="padding: 10px 0; color: var(--text); font-weight: 600; border-radius: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='var(--border)'; this.style.transform='scale(1.05)';" onmouseout="this.style.background='transparent'; this.style.transform='scale(1)';" onclick="alert('You clicked on ${monthNames[month]} ${d}, ${year}')">${d}</div>`;
            }
        }
        document.getElementById('calendarDays').innerHTML = html;
    }

    function changeMonth(direction) {
        currentDate.setMonth(currentDate.getMonth() + direction);
        renderCalendar();
    }

    renderCalendar();
</script>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
