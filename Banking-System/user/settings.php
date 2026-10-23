<?php
require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

if ($current_user['role'] !== 'user') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Fetch current data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();

$flash = '';
$error = '';

// Handle Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $user_data['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user_data['id']]);
        $flash = 'Password updated successfully!';
    }
}

$page_title = 'Settings';
require_once __DIR__ . '/../includes/user_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Settings</h1>
    <p style="color: var(--text-muted);">Manage your security and preferences.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2" style="align-items: start; gap: 32px;">
    
    <!-- Left Column: Security (Change Password) -->
    <div class="card">
        <h3><i class="fas fa-lock" style="color: #10b981;"></i> Security & Password</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px; font-size: 0.9rem;">Keep your account secure by updating your password regularly.</p>
        
        <form method="post">
            <input type="hidden" name="action" value="update_password">
            
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
            
            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 12px; background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-key"></i> Update Password
            </button>
        </form>
    </div>

    <!-- Right Column: Preferences, Notifications & Legal -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        
        <!-- Appearance -->
        <div class="card">
            <h3><i class="fas fa-palette" style="color: #10b981;"></i> Appearance</h3>
            <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">Customize how My Bank looks.</p>
            
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

        <!-- Notifications -->
        <div class="card">
            <h3><i class="fas fa-bell" style="color: #10b981;"></i> Notifications</h3>
            <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">Choose what alerts you want to receive.</p>
            
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <label style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border); cursor: pointer;">
                    <input type="checkbox" checked style="width: 18px; height: 18px; accent-color: #10b981;">
                    <span style="font-size: 0.9rem;">Email me when I receive a transfer</span>
                </label>
                <label style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border); cursor: pointer;">
                    <input type="checkbox" checked style="width: 18px; height: 18px; accent-color: #10b981;">
                    <span style="font-size: 0.9rem;">Alert me when a loan is approved</span>
                </label>
                <label style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border); cursor: pointer;">
                    <input type="checkbox" style="width: 18px; height: 18px; accent-color: #10b981;">
                    <span style="font-size: 0.9rem;">Send me a monthly account summary</span>
                </label>
            </div>
        </div>

        <!-- Legal & Privacy -->
        <div class="card">
            <h3><i class="fas fa-balance-scale" style="color: #10b981;"></i> Legal & Privacy</h3>
            <p style="color: var(--text-muted); margin-bottom: 20px; font-size: 0.9rem;">Review our terms, policies, and bank regulations.</p>
            
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <a href="<?= e(SITE_URL) ?>user/legal.php?page=privacy" style="display: flex; align-items: center; justify-content: space-between; padding: 16px; background: var(--bg-alt); border-radius: 12px; border: 1px solid var(--border); text-decoration: none; color: var(--text); transition: var(--transition);">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-shield-alt" style="color: #10b981;"></i>
                        <span style="font-weight: 600; font-size: 0.9rem;">Privacy Policy</span>
                    </div>
                    <i class="fas fa-chevron-right" style="color: var(--text-muted); font-size: 0.8rem;"></i>
                </a>

                <a href="<?= e(SITE_URL) ?>user/legal.php?page=terms" style="display: flex; align-items: center; justify-content: space-between; padding: 16px; background: var(--bg-alt); border-radius: 12px; border: 1px solid var(--border); text-decoration: none; color: var(--text); transition: var(--transition);">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-file-contract" style="color: #10b981;"></i>
                        <span style="font-weight: 600; font-size: 0.9rem;">Terms of Service</span>
                    </div>
                    <i class="fas fa-chevron-right" style="color: var(--text-muted); font-size: 0.8rem;"></i>
                </a>

                <a href="<?= e(SITE_URL) ?>user/legal.php?page=cookies" style="display: flex; align-items: center; justify-content: space-between; padding: 16px; background: var(--bg-alt); border-radius: 12px; border: 1px solid var(--border); text-decoration: none; color: var(--text); transition: var(--transition);">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-cookie-bite" style="color: #10b981;"></i>
                        <span style="font-weight: 600; font-size: 0.9rem;">Cookie Policy</span>
                    </div>
                    <i class="fas fa-chevron-right" style="color: var(--text-muted); font-size: 0.8rem;"></i>
                </a>
            </div>
        </div>
        
    </div>
</div>

<script>
    // Reuse the theme toggle logic from the header
    function toggleTheme() {
        var h = document.documentElement;
        var t = h.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        h.setAttribute('data-theme', t);
        localStorage.setItem('bank-theme', t);
        var icon = document.getElementById('themeIcon');
        if (icon) icon.className = t === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
</script>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
