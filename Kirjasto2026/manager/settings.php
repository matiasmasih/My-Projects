<?php
// manager/settings.php — Manager Operational Settings with Calendar & System Status

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$manager = $stmt->fetch();

if ($manager['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// ---- Resolve manager's branch ----
$branch_id = $manager['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : 'Unknown Branch';

$flash = '';
$error = '';

// ---- CHANGE PASSWORD ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $manager['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $manager['id']]);
        $flash = 'Password updated successfully.';
    }
}

// ---- FETCH LIBRARY SETTINGS ----
$settings_rows = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll();
$settings_data = [];
foreach ($settings_rows as $row) {
    $settings_data[$row['key']] = $row['value'];
}

// ---- FETCH SYSTEM STATUS & OVERDUE ALERTS ----
$overdue_books = 0;
$overdue_devices = 0;

if ($branch_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE status = 'active' AND due_at < NOW() AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $overdue_books = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM device_loans WHERE status = 'active' AND due_at < NOW() AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $overdue_devices = $stmt->fetchColumn();
}

$page_title = 'Settings';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Manager Settings</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Manage your security and branch operations.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2" style="align-items: start; gap: 32px;">

    <!-- LEFT COLUMN: Security & Appearance -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- Change Password -->
        <div class="card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: grid; place-items: center; font-size: 1.2rem;">
                    <i class="fas fa-lock"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem;">Security & Password</h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Keep your account secure.</p>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 12px; background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-key"></i> Update Password
                </button>
            </form>
        </div>

        <!-- Appearance -->
        <div class="card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: grid; place-items: center; font-size: 1.2rem;">
                    <i class="fas fa-palette"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem;">Appearance</h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Customize the portal view.</p>
                </div>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px; background: var(--bg-alt); border-radius: 12px; border: 1px solid var(--border);">
                <div>
                    <strong style="display: block; color: var(--text);">Dark Mode</strong>
                    <span style="font-size: 0.85rem; color: var(--text-muted);">Toggle between light and dark theme.</span>
                </div>
                <button onclick="toggleTheme()" class="btn-outline" style="padding: 8px 16px; font-size: 0.85rem;">
                    <i class="fas fa-moon"></i> Toggle
                </button>
            </div>
        </div>

        <!-- NEW: System Status & Overdue Alerts -->
        <div class="card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: grid; place-items: center; font-size: 1.2rem;">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem;">System Status</h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Live branch health & overdue alerts.</p>
                </div>
            </div>

            <!-- System Status Rows -->
            <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border);">
                    <span style="color: var(--text-muted); font-size: 0.9rem;"><i class="fas fa-server"></i> Database</span>
                    <span style="color: var(--success); font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
                        <span style="width: 8px; height: 8px; background: var(--success); border-radius: 50%; display: inline-block;"></span> Online
                    </span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border);">
                    <span style="color: var(--text-muted); font-size: 0.9rem;"><i class="fas fa-building"></i> Current Branch</span>
                    <span style="color: var(--text); font-weight: 700; font-size: 0.85rem;"><?= e($branch_name) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border);">
                    <span style="color: var(--text-muted); font-size: 0.9rem;"><i class="fas fa-clock"></i> Server Time</span>
                    <span style="color: var(--text); font-weight: 700; font-size: 0.85rem;"><?= date('H:i A') ?></span>
                </div>
            </div>

            <!-- Overdue Alerts Preview -->
            <div style="padding: 14px; background: rgba(239, 68, 68, 0.05); border-radius: 10px; border: 1px solid rgba(239, 68, 68, 0.2);">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                    <strong style="color: var(--danger); font-size: 0.9rem;">Overdue Alerts</strong>
                </div>
                <p style="margin: 0; color: var(--text-muted); font-size: 0.85rem;">
                    You currently have <strong style="color: var(--danger);"><?= $overdue_books ?> overdue book(s)</strong> and <strong style="color: var(--danger);"><?= $overdue_devices ?> overdue device(s)</strong> in your branch.
                </p>
            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN: Calendar, Notifications, Config -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- MODERN BEAUTIFUL CALENDAR -->
        <div class="card" style="padding: 24px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: grid; place-items: center; font-size: 1.2rem;">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 1.1rem;">Branch Calendar</h3>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Schedule and events</p>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button onclick="changeMonth(-1)" style="width: 32px; height: 32px; border-radius: 8px; background: var(--bg-alt); border: 1px solid var(--border); color: var(--text); cursor: pointer; display: grid; place-items: center; transition: all 0.2s;" onmouseover="this.style.borderColor='#f59e0b'" onmouseout="this.style.borderColor='var(--border)'">
                        <i class="fas fa-chevron-left" style="font-size: 0.8rem;"></i>
                    </button>
                    <button onclick="changeMonth(1)" style="width: 32px; height: 32px; border-radius: 8px; background: var(--bg-alt); border: 1px solid var(--border); color: var(--text); cursor: pointer; display: grid; place-items: center; transition: all 0.2s;" onmouseover="this.style.borderColor='#f59e0b'" onmouseout="this.style.borderColor='var(--border)'">
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

        <!-- Notifications (Operational) -->
        <div class="card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: grid; place-items: center; font-size: 1.2rem;">
                    <i class="fas fa-bell"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem;">Operational Notifications</h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Alerts specific to your daily tasks.</p>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <label style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border); cursor: pointer;">
                    <input type="checkbox" checked style="width: 18px; height: 18px; accent-color: #f59e0b;">
                    <span style="font-size: 0.9rem;">Email me when a book is returned late</span>
                </label>
                <label style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border); cursor: pointer;">
                    <input type="checkbox" checked style="width: 18px; height: 18px; accent-color: #f59e0b;">
                    <span style="font-size: 0.9rem;">Alert me when a reservation expires</span>
                </label>
                <label style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border); cursor: pointer;">
                    <input type="checkbox" style="width: 18px; height: 18px; accent-color: #f59e0b;">
                    <span style="font-size: 0.9rem;">Send me a weekly summary of branch fines</span>
                </label>
            </div>
        </div>

        <!-- Library Configuration (Read-Only) -->
        <div class="card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: grid; place-items: center; font-size: 1.2rem;">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem;">Library Configuration</h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Global rules set by the Admin.</p>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px;">
                <div style="display: flex; justify-content: space-between; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border);">
                    <span style="color: var(--text-muted); font-size: 0.9rem;">Library Name</span>
                    <span style="color: var(--text); font-weight: 600;"><?= e($settings_data['library_name'] ?? 'Kirjasto 2026') ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border);">
                    <span style="color: var(--text-muted); font-size: 0.9rem;">Standard Loan Period</span>
                    <span style="color: var(--text); font-weight: 600;"><?= e($settings_data['loan_period_days'] ?? '14') ?> days</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border);">
                    <span style="color: var(--text-muted); font-size: 0.9rem;">Daily Fine Rate</span>
                    <span style="color: var(--text); font-weight: 600;">€<?= e($settings_data['fine_per_day'] ?? '0.50') ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border);">
                    <span style="color: var(--text-muted); font-size: 0.9rem;">Max Books Limit</span>
                    <span style="color: var(--text); font-weight: 600;"><?= e($settings_data['max_books_per_user'] ?? '5') ?> per user</span>
                </div>
            </div>

            <div style="margin-top: 20px; padding: 12px; background: rgba(59, 130, 246, 0.05); border-radius: 10px; border: 1px solid rgba(59, 130, 246, 0.2);">
                <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0;">
                    <i class="fas fa-info-circle" style="color: #3b82f6;"></i> To change these rules, please contact the System Administrator.
                </p>
            </div>
        </div>

    </div>
</div>

<script>
    function toggleTheme() {
        const h = document.documentElement;
        const next = h.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        h.setAttribute('data-theme', next);
        localStorage.setItem('kirjasto-theme', next);
        const icon = document.getElementById('themeIcon');
        if (icon) icon.className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }

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
                html += `<div style="padding: 10px 0; border-radius: 10px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; font-weight: 800; box-shadow: 0 4px 12px rgba(245,158,11,0.4); transform: scale(1.05); transition: transform 0.2s;">${d}</div>`;
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

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
