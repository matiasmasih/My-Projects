<?php
// account_update.php — Save profile changes

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['customer_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . 'account.php');
    exit;
}

$customer_id = (int)$_SESSION['customer_id'];

$full_name  = trim($_POST['full_name'] ?? '');
$phone      = trim($_POST['phone'] ?? '');
$email      = trim($_POST['email'] ?? '');
$pref_lang  = $_POST['preferred_lang'] ?? 'en';
$pref_curr  = $_POST['preferred_currency'] ?? 'EUR';
$password   = $_POST['password'] ?? '';
$password2  = $_POST['password2'] ?? '';

$errors = [];

if ($full_name === '')                              $errors[] = 'Full name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = 'Valid email is required.';
if (!in_array($pref_lang, ['en','fi','fa'], true))  $pref_lang = 'en';
if (!in_array($pref_curr, ['EUR','USD','AFN'], true)) $pref_curr = 'EUR';

// Email uniqueness (excluding self)
if (empty($errors)) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? AND id != ? LIMIT 1");
        $stmt->execute([$email, $customer_id]);
        if ($stmt->fetch()) $errors[] = 'That email is already used by another account.';
    } catch (PDOException $e) {}
}

// Password change (optional)
$change_password = false;
if ($password !== '' || $password2 !== '') {
    if (strlen($password) < 6)      $errors[] = 'Password must be at least 6 characters.';
    elseif ($password !== $password2) $errors[] = 'Passwords do not match.';
    else $change_password = true;
}

if (!empty($errors)) {
    $_SESSION['flash_error'] = implode(' ', $errors);
    header('Location: ' . SITE_URL . 'account.php?tab=profile');
    exit;
}

try {
    if ($change_password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE customers
            SET full_name = ?, phone = ?, email = ?, preferred_lang = ?, preferred_currency = ?, password_hash = ?
            WHERE id = ?
        ");
        $stmt->execute([$full_name, $phone, $email, $pref_lang, $pref_curr, $hash, $customer_id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE customers
            SET full_name = ?, phone = ?, email = ?, preferred_lang = ?, preferred_currency = ?
            WHERE id = ?
        ");
        $stmt->execute([$full_name, $phone, $email, $pref_lang, $pref_curr, $customer_id]);
    }

    // Update session
    $_SESSION['customer_name']  = $full_name;
    $_SESSION['customer_email'] = $email;

    $_SESSION['flash_success'] = 'Profile updated.';
    header('Location: ' . SITE_URL . 'account.php?tab=profile');
    exit;

} catch (PDOException $e) {
    error_log('account_update: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not save changes.';
    header('Location: ' . SITE_URL . 'account.php?tab=profile');
    exit;
}
