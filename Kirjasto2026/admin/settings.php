<?php
// admin/settings.php — Real Library System Settings

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';
$error = '';

// ---- UPDATE SETTINGS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_settings') {
    // Fetch existing settings to merge
    $existing = [];
    $rows = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll();
    foreach ($rows as $r) $existing[$r['key']] = $r['value'];

    // Map POST fields to setting keys
    $settings_to_update = [
        // General
        'library_name' => trim($_POST['library_name'] ?? 'Kirjasto 2026'),
        'library_email' => trim($_POST['library_email'] ?? ''),
        'library_phone' => trim($_POST['library_phone'] ?? ''),
        'currency' => $_POST['currency'] ?? 'EUR',
        // Circulation
        'loan_period_days' => (int)($_POST['loan_period_days'] ?? 14),
        'max_books_per_user' => (int)($_POST['max_books_per_user'] ?? 5),
        'max_extensions' => (int)($_POST['max_extensions'] ?? 2),
        'reservation_expiry_days' => (int)($_POST['reservation_expiry_days'] ?? 3),
        // Fines
        'fine_per_day' => (float)($_POST['fine_per_day'] ?? 0.50),
        'grace_period_days' => (int)($_POST['grace_period_days'] ?? 2),
        'max_fine_cap' => (float)($_POST['max_fine_cap'] ?? 10.00),
        'lost_book_fee' => (float)($_POST['lost_book_fee'] ?? 5.00),
        // Security
        'allow_self_registration' => isset($_POST['allow_self_registration']) ? '1' : '0',
        'session_timeout_minutes' => (int)($_POST['session_timeout_minutes'] ?? 30),
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
    ];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
        foreach ($settings_to_update as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }
        $pdo->commit();
        $flash = 'System settings updated successfully.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to update settings.';
    }
}

// ---- FETCH SETTINGS ----
$settings_rows = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll();
$s = [];
foreach ($settings_rows as $row) $s[$row['key']] = $row['value'];

$page_title = 'System Settings';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">System Settings</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Configure global library rules, fines, and security policies.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<form method="post">
    <input type="hidden" name="action" value="update_settings">

    <!-- Section 1: General Information -->
    <div class="card" style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px;">
            <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: grid; place-items: center; font-size: 1.1rem;">
                <i class="fas fa-building"></i>
            </div>
            <h3 style="margin: 0; font-size: 1.1rem;">General Information</h3>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label>Library Name</label>
                <input type="text" name="library_name" value="<?= e($s['library_name'] ?? 'Kirjasto 2026') ?>">
            </div>
            <div class="form-group">
                <label>Support Phone</label>
                <input type="text" name="library_phone" value="<?= e($s['library_phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Support Email</label>
                <input type="email" name="library_email" value="<?= e($s['library_email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Currency</label>
                <select name="currency">
                    <option value="EUR" <?= ($s['currency'] ?? 'EUR') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                    <option value="USD" <?= ($s['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                    <option value="GBP" <?= ($s['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Section 2: Circulation Rules -->
    <div class="card" style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px;">
            <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: grid; place-items: center; font-size: 1.1rem;">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <h3 style="margin: 0; font-size: 1.1rem;">Circulation & Loan Rules</h3>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label>Default Loan Period (Days)</label>
                <input type="number" name="loan_period_days" value="<?= e($s['loan_period_days'] ?? '14') ?>" min="1">
            </div>
            <div class="form-group">
                <label>Max Books Per User</label>
                <input type="number" name="max_books_per_user" value="<?= e($s['max_books_per_user'] ?? '5') ?>" min="1">
            </div>
            <div class="form-group">
                <label>Max Renewals (Extensions)</label>
                <input type="number" name="max_extensions" value="<?= e($s['max_extensions'] ?? '2') ?>" min="0">
            </div>
            <div class="form-group">
                <label>Reservation Hold Expiry (Days)</label>
                <input type="number" name="reservation_expiry_days" value="<?= e($s['reservation_expiry_days'] ?? '3') ?>" min="1">
            </div>
        </div>
    </div>

    <!-- Section 3: Fines & Fees -->
    <div class="card" style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px;">
            <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: grid; place-items: center; font-size: 1.1rem;">
                <i class="fas fa-euro-sign"></i>
            </div>
            <h3 style="margin: 0; font-size: 1.1rem;">Fines & Fees</h3>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label>Fine Per Day (€)</label>
                <input type="number" step="0.01" name="fine_per_day" value="<?= e($s['fine_per_day'] ?? '0.50') ?>" min="0">
            </div>
            <div class="form-group">
                <label>Grace Period (Days)</label>
                <input type="number" name="grace_period_days" value="<?= e($s['grace_period_days'] ?? '2') ?>" min="0">
            </div>
            <div class="form-group">
                <label>Maximum Fine Cap (€)</label>
                <input type="number" step="0.01" name="max_fine_cap" value="<?= e($s['max_fine_cap'] ?? '10.00') ?>" min="0">
            </div>
            <div class="form-group">
                <label>Lost Book Replacement Fee (€)</label>
                <input type="number" step="0.01" name="lost_book_fee" value="<?= e($s['lost_book_fee'] ?? '5.00') ?>" min="0">
            </div>
        </div>
    </div>

    <!-- Section 4: Security & Maintenance -->
    <div class="card" style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px;">
            <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: grid; place-items: center; font-size: 1.1rem;">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h3 style="margin: 0; font-size: 1.1rem;">Security & Maintenance</h3>
        </div>
        
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; margin-bottom: 16px;">
                <input type="checkbox" name="allow_self_registration" style="width: 20px; height: 20px; accent-color: var(--primary);" <?= ($s['allow_self_registration'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span style="font-weight: 600; color: var(--text);">Allow Self-Registration</span>
            </label>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-left: 32px; margin-top: -12px;">If disabled, only Staff and Admins can create new members.</p>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>Session Timeout (Minutes)</label>
                <input type="number" name="session_timeout_minutes" value="<?= e($s['session_timeout_minutes'] ?? '30') ?>" min="5">
            </div>
        </div>

        <div class="form-group" style="margin-top: 16px; padding: 16px; background: rgba(239, 68, 68, 0.05); border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.2);">
            <label style="display: flex; align-items: center; gap: 12px; cursor: pointer; margin: 0;">
                <input type="checkbox" name="maintenance_mode" style="width: 20px; height: 20px; accent-color: var(--danger);" <?= ($s['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                <div>
                    <strong style="display: block; color: var(--danger); font-size: 0.95rem;">Maintenance Mode</strong>
                    <span style="font-size: 0.85rem; color: var(--text-muted);">Locks out all Managers, Staff, and Members. Only Admins can log in.</span>
                </div>
            </label>
        </div>
    </div>

    <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
        <button type="submit" class="btn-primary" style="width: auto; padding: 14px 40px; background: linear-gradient(135deg, #3b82f6, #2563eb);">
            <i class="fas fa-save"></i> Save All Settings
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
