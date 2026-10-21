<?php
// admin_settings.php — Edit site-wide settings

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$lang = current_language();

// Flash
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Load all settings
$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value, setting_type FROM settings")->fetchAll();
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}

// Helper
function s($key, $default = '') {
    global $settings;
    return $settings[$key] ?? $default;
}

$page_title = 'Settings';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-panel">
    <h3><i class="fas fa-gear"></i> Site Settings</h3>

    <?php if ($flash_success): ?>
        <div class="flash flash-success">
            <i class="fas fa-check-circle"></i> <?= e($flash_success) ?>
        </div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="flash flash-error">
            <i class="fas fa-exclamation-circle"></i> <?= e($flash_error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="admin_settings_process.php">

        <!-- ============ GENERAL ============ -->
        <h4 style="margin:20px 0 16px;padding-bottom:10px;border-bottom:1px solid var(--border);color:var(--accent);">
            <i class="fas fa-info-circle"></i> General
        </h4>

        <div class="form-row">
            <div class="form-field">
                <label for="site_name">Site Name *</label>
                <input type="text" id="site_name" name="site_name" required value="<?= e(s('site_name')) ?>">
            </div>
            <div class="form-field">
                <label for="site_tagline">Site Tagline</label>
                <input type="text" id="site_tagline" name="site_tagline" value="<?= e(s('site_tagline')) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="site_email">Site Email</label>
                <input type="email" id="site_email" name="site_email" value="<?= e(s('site_email')) ?>">
            </div>
            <div class="form-field">
                <label for="site_phone">Site Phone</label>
                <input type="tel" id="site_phone" name="site_phone" value="<?= e(s('site_phone')) ?>">
            </div>
        </div>

        <!-- ============ LANGUAGES & CURRENCY ============ -->
        <h4 style="margin:28px 0 16px;padding-bottom:10px;border-bottom:1px solid var(--border);color:var(--accent);">
            <i class="fas fa-globe"></i> Languages &amp; Currency
        </h4>

        <div class="form-row">
            <div class="form-field">
                <label for="default_lang">Default Language</label>
                <select id="default_lang" name="default_lang">
                    <option value="en" <?= s('default_lang') === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="fi" <?= s('default_lang') === 'fi' ? 'selected' : '' ?>>Suomi</option>
                    <option value="fa" <?= s('default_lang') === 'fa' ? 'selected' : '' ?>>فارسی</option>
                </select>
            </div>
            <div class="form-field">
                <label for="default_currency">Default Currency</label>
                <select id="default_currency" name="default_currency">
                    <option value="EUR" <?= s('default_currency') === 'EUR' ? 'selected' : '' ?>>€ EUR</option>
                    <option value="USD" <?= s('default_currency') === 'USD' ? 'selected' : '' ?>>$ USD</option>
                    <option value="AFN" <?= s('default_currency') === 'AFN' ? 'selected' : '' ?>>؋ AFN</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="exchange_rate_api">Exchange Rate API URL</label>
                <input type="url" id="exchange_rate_api" name="exchange_rate_api" value="<?= e(s('exchange_rate_api')) ?>">
            </div>
            <div class="form-field">
                <label for="exchange_rate_fetch_hour">Fetch Hour (0–23)</label>
                <input type="number" id="exchange_rate_fetch_hour" name="exchange_rate_fetch_hour" min="0" max="23" value="<?= e(s('exchange_rate_fetch_hour', '3')) ?>">
            </div>
        </div>

        <!-- ============ ORDERING ============ -->
        <h4 style="margin:28px 0 16px;padding-bottom:10px;border-bottom:1px solid var(--border);color:var(--accent);">
            <i class="fas fa-shopping-bag"></i> Ordering
        </h4>

        <div class="form-row">
            <div class="form-field">
                <label>Ordering Enabled</label>
                <label class="toggle"><input type="checkbox" name="orders_enabled" value="1" <?= s('orders_enabled') === '1' ? 'checked' : '' ?>> Allow customers to place orders</label>
            </div>
            <div class="form-field">
                <label>Guest Checkout</label>
                <label class="toggle"><input type="checkbox" name="guest_checkout" value="1" <?= s('guest_checkout') === '1' ? 'checked' : '' ?>> Allow orders without account</label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label>Delivery</label>
                <label class="toggle"><input type="checkbox" name="delivery_enabled" value="1" <?= s('delivery_enabled') === '1' ? 'checked' : '' ?>> Enable delivery orders</label>
            </div>
            <div class="form-field">
                <label>Pickup</label>
                <label class="toggle"><input type="checkbox" name="pickup_enabled" value="1" <?= s('pickup_enabled') === '1' ? 'checked' : '' ?>> Enable pickup orders</label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label>Dine-in</label>
                <label class="toggle"><input type="checkbox" name="dinein_enabled" value="1" <?= s('dinein_enabled') === '1' ? 'checked' : '' ?>> Enable dine-in orders</label>
            </div>
            <div class="form-field">
                <label>Reservations</label>
                <label class="toggle"><input type="checkbox" name="reservations_enabled" value="1" <?= s('reservations_enabled') === '1' ? 'checked' : '' ?>> Enable table reservations</label>
            </div>
        </div>

        <!-- ============ FEATURES ============ -->
        <h4 style="margin:28px 0 16px;padding-bottom:10px;border-bottom:1px solid var(--border);color:var(--accent);">
            <i class="fas fa-star"></i> Features
        </h4>

        <div class="form-row">
            <div class="form-field">
                <label>Loyalty Points</label>
                <label class="toggle"><input type="checkbox" name="loyalty_enabled" value="1" <?= s('loyalty_enabled') === '1' ? 'checked' : '' ?>> Enable loyalty program</label>
            </div>
            <div class="form-field">
                <label>Coupons</label>
                <label class="toggle"><input type="checkbox" name="coupons_enabled" value="1" <?= s('coupons_enabled') === '1' ? 'checked' : '' ?>> Enable coupon codes</label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label>Reviews</label>
                <label class="toggle"><input type="checkbox" name="reviews_enabled" value="1" <?= s('reviews_enabled') === '1' ? 'checked' : '' ?>> Enable customer reviews</label>
            </div>
            <div class="form-field">
                <label>Review Moderation</label>
                <label class="toggle"><input type="checkbox" name="review_moderation" value="1" <?= s('review_moderation') === '1' ? 'checked' : '' ?>> Require admin approval for reviews</label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-field">
                <label for="loyalty_points_per_eur">Loyalty: points per €1</label>
                <input type="number" id="loyalty_points_per_eur" name="loyalty_points_per_eur" min="0" value="<?= e(s('loyalty_points_per_eur', '10')) ?>">
            </div>
            <div class="form-field">
                <label for="loyalty_redeem_rate">Loyalty: points for €1 discount</label>
                <input type="number" id="loyalty_redeem_rate" name="loyalty_redeem_rate" min="1" value="<?= e(s('loyalty_redeem_rate', '100')) ?>">
            </div>
        </div>

        <!-- ============ MAINTENANCE ============ -->
        <h4 style="margin:28px 0 16px;padding-bottom:10px;border-bottom:1px solid var(--border);color:var(--accent);">
            <i class="fas fa-tools"></i> Maintenance
        </h4>

        <div class="form-field">
            <label>Maintenance Mode</label>
            <label class="toggle"><input type="checkbox" name="maintenance_mode" value="1" <?= s('maintenance_mode') === '1' ? 'checked' : '' ?>> Take site offline (visitors see maintenance page)</label>
        </div>

        <!-- ============ SAVE ============ -->
        <div style="display:flex;gap:10px;margin-top:32px;padding-top:20px;border-top:1px solid var(--border);">
            <button type="submit" class="btn btn-accent btn-lg">
                <i class="fas fa-save"></i> Save settings
            </button>
            <a href="admin_dashboard.php" class="btn btn-outline btn-lg">Cancel</a>
        </div>

    </form>
</div>

<style>
.toggle {
    display: flex;
    gap: 10px;
    align-items: center;
    cursor: pointer;
    font-weight: 500;
    font-size: 0.9rem;
    padding: 10px 14px;
    background: var(--bg-alt);
    border-radius: 8px;
}
.toggle input[type="checkbox"] {
    width: auto;
    accent-color: var(--accent);
    transform: scale(1.2);
}
</style>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
