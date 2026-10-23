<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin_check = $stmt->fetch();

if ($admin_check['role'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_to_update = [
        'bank_name' => trim($_POST['bank_name']),
        'bank_tagline' => trim($_POST['bank_tagline']),
        'support_email' => trim($_POST['support_email']),
        'support_phone' => trim($_POST['support_phone']),
        'support_address' => trim($_POST['support_address']),
        'loan_interest_rate' => trim($_POST['loan_interest_rate']),
        'max_loan_amount' => trim($_POST['max_loan_amount']),
        'daily_transfer_limit' => trim($_POST['daily_transfer_limit']),
        'intl_transfer_fee' => trim($_POST['intl_transfer_fee']),
        'min_balance' => trim($_POST['min_balance']),
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        'registration_open' => isset($_POST['registration_open']) ? '1' : '0',
        'kyc_required' => isset($_POST['kyc_required']) ? '1' : '0'
    ];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($settings_to_update as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }
        $pdo->commit();
        $flash = 'Bank settings updated successfully!';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to update settings: ' . $e->getMessage();
    }
}

$settings_data = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch()) {
    $settings_data[$row['setting_key']] = $row['setting_value'];
}

$page_title = 'System Settings';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 1.8rem; color: var(--text);">System Settings</h1>
    <p style="color: var(--text-muted);">Manage bank identity, financial limits, and security policies.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" style="max-width: 900px; margin: 0 auto;">

    <!-- 1. BANK IDENTITY -->
    <div class="card" style="margin-bottom: 24px;">
        <h3><i class="fas fa-building"></i> Bank Identity</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px; font-size: 0.9rem;">This information appears on the public login page and customer statements.</p>

        <div class="grid-2">
            <div class="form-group">
                <label>Bank Name</label>
                <input type="text" name="bank_name" value="<?= e($settings_data['bank_name'] ?? 'Modern Bank') ?>" required>
            </div>
            <div class="form-group">
                <label>Slogan / Tagline</label>
                <input type="text" name="bank_tagline" value="<?= e($settings_data['bank_tagline'] ?? 'Banking for the Future') ?>">
            </div>
            <div class="form-group">
                <label>Support Email</label>
                <input type="email" name="support_email" value="<?= e($settings_data['support_email'] ?? 'support@modernbank.com') ?>" required>
            </div>
            <div class="form-group">
                <label>Support Phone</label>
                <input type="text" name="support_phone" value="<?= e($settings_data['support_phone'] ?? '+358 40 123 4567') ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label>Headquarters Address</label>
            <input type="text" name="support_address" value="<?= e($settings_data['support_address'] ?? 'Mannerheimintie 1, Helsinki, Finland') ?>">
        </div>
    </div>

    <!-- 2. FINANCIAL PARAMETERS -->
    <div class="card" style="margin-bottom: 24px;">
        <h3><i class="fas fa-coins"></i> Financial Parameters</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px; font-size: 0.9rem;">Global limits that apply to every customer transaction.</p>
        
        <div class="grid-2">
            <div class="form-group">
                <label>Default Loan Interest Rate (%)</label>
                <input type="number" step="0.1" name="loan_interest_rate" value="<?= e($settings_data['loan_interest_rate'] ?? '5.5') ?>" required>
            </div>
            <div class="form-group">
                <label>Maximum Loan Amount (€)</label>
                <input type="number" name="max_loan_amount" value="<?= e($settings_data['max_loan_amount'] ?? '50000') ?>" required>
            </div>
            <div class="form-group">
                <label>Daily Transfer Limit (€)</label>
                <input type="number" name="daily_transfer_limit" value="<?= e($settings_data['daily_transfer_limit'] ?? '10000') ?>" required>
            </div>
            <div class="form-group">
                <label>International Transfer Fee (€)</label>
                <input type="number" step="0.01" name="intl_transfer_fee" value="<?= e($settings_data['intl_transfer_fee'] ?? '15.00') ?>" required>
            </div>
            <div class="form-group">
                <label>Minimum Balance Required (€)</label>
                <input type="number" step="0.01" name="min_balance" value="<?= e($settings_data['min_balance'] ?? '0.00') ?>">
            </div>
        </div>
    </div>

    <!-- 3. SECURITY & POLICIES -->
    <div class="card" style="margin-bottom: 24px;">
        <h3><i class="fas fa-shield-alt"></i> Security & Policies</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px; font-size: 0.9rem;">Toggle global access controls for the entire banking system.</p>
        
        <div class="grid-2">
            <label class="toggle-box" style="background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2);">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <input type="checkbox" name="maintenance_mode" style="width: 24px; height: 24px;" <?= ($settings_data['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <div>
                        <strong style="color: var(--danger); display: block; font-size: 1rem;">Maintenance Mode</strong>
                        <span style="font-size: 0.8rem; color: var(--text-muted);">Only Admins can log in.</span>
                    </div>
                </div>
            </label>

            <label class="toggle-box" style="background: rgba(16, 185, 129, 0.05); border-color: rgba(16, 185, 129, 0.2);">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <input type="checkbox" name="registration_open" style="width: 24px; height: 24px;" <?= ($settings_data['registration_open'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <div>
                        <strong style="color: var(--success); display: block; font-size: 1rem;">Public Registration</strong>
                        <span style="font-size: 0.8rem; color: var(--text-muted);">Allow new customers to sign up.</span>
                    </div>
                </div>
            </label>

            <label class="toggle-box" style="background: rgba(245, 158, 11, 0.05); border-color: rgba(245, 158, 11, 0.2);">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <input type="checkbox" name="kyc_required" style="width: 24px; height: 24px;" <?= ($settings_data['kyc_required'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <div>
                        <strong style="color: var(--warning); display: block; font-size: 1rem;">Mandatory KYC</strong>
                        <span style="font-size: 0.8rem; color: var(--text-muted);">Users must verify ID to transact.</span>
                    </div>
                </div>
            </label>
        </div>
    </div>

    <!-- SAVE BUTTON -->
    <div style="display: flex; justify-content: flex-end; position: sticky; bottom: 20px; z-index: 10;">
        <button type="submit" class="btn-primary" style="width: auto; padding: 16px 48px; font-size: 1.05rem; box-shadow: 0 10px 30px -5px var(--primary-glow);">
            <i class="fas fa-save"></i> Save All Settings
        </button>
    </div>

</form>

<style>
    .toggle-box {
        display: block;
        padding: 20px;
        border-radius: 12px;
        border: 1px solid var(--border);
        cursor: pointer;
        transition: var(--transition);
    }
    .toggle-box:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    .toggle-box input[type="checkbox"] {
        accent-color: var(--primary);
        cursor: pointer;
    }
</style>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
