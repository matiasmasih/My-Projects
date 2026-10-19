<?php
// admin_settings_process.php — Save site settings

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . 'admin_settings.php');
    exit;
}

// Text fields
$text_fields = [
    'site_name', 'site_tagline', 'site_email', 'site_phone',
    'default_lang', 'default_currency',
    'exchange_rate_api',
];

// Number fields
$number_fields = [
    'exchange_rate_fetch_hour',
    'loyalty_points_per_eur',
    'loyalty_redeem_rate',
];

// Bool fields — checkboxes (1 if checked, 0 if not)
$bool_fields = [
    'orders_enabled', 'guest_checkout',
    'delivery_enabled', 'pickup_enabled', 'dinein_enabled',
    'reservations_enabled',
    'loyalty_enabled', 'coupons_enabled',
    'reviews_enabled', 'review_moderation',
    'maintenance_mode',
];

$updates = [];

// Text
foreach ($text_fields as $key) {
    if (isset($_POST[$key])) {
        $updates[$key] = trim($_POST[$key]);
    }
}

// Number
foreach ($number_fields as $key) {
    if (isset($_POST[$key])) {
        $updates[$key] = (string)(int)$_POST[$key];
    }
}

// Bool — if not present in POST, set to '0'
foreach ($bool_fields as $key) {
    $updates[$key] = isset($_POST[$key]) ? '1' : '0';
}

// Save
try {
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $count = 0;
    foreach ($updates as $key => $value) {
        $stmt->execute([$value, $key]);
        $count++;
    }

    $_SESSION['flash_success'] = "Saved $count settings successfully.";
    header('Location: ' . SITE_URL . 'admin_settings.php');
    exit;

} catch (PDOException $e) {
    error_log('admin_settings_process: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not save settings. Please try again.';
    header('Location: ' . SITE_URL . 'admin_settings.php');
    exit;
}
