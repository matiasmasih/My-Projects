<?php
// contact_process.php — Save contact message to database

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . 'contact.php');
    exit;
}

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$phone   = trim($_POST['phone']   ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Honeypot — bots fill hidden field
if (!empty($_POST['website'])) {
    header('Location: ' . SITE_URL . 'contact.php');
    exit;
}

// Validate
$errors = [];
if ($name === '')                                 $errors[] = 'Name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))   $errors[] = 'Valid email is required.';
if ($message === '')                              $errors[] = 'Message is required.';
if (strlen($message) > 5000)                      $errors[] = 'Message is too long.';

if (!empty($errors)) {
    $_SESSION['flash_error'] = implode(' ', $errors);
    header('Location: ' . SITE_URL . 'contact.php');
    exit;
}

// Current branch
$branch_id = $_SESSION['branch_id'] ?? null;
if (!$branch_id) {
    try {
        $row = $pdo->query("SELECT id FROM branches WHERE is_active = 1 ORDER BY sort_order LIMIT 1")->fetch();
        $branch_id = $row['id'] ?? null;
    } catch (PDOException $e) {
        $branch_id = null;
    }
}

// Save
try {
    $stmt = $pdo->prepare("
        INSERT INTO contact_messages
            (branch_id, name, email, phone, subject, message, ip_address, user_agent)
        VALUES
            (:branch_id, :name, :email, :phone, :subject, :message, :ip, :ua)
    ");
    $stmt->execute([
        ':branch_id' => $branch_id,
        ':name'      => $name,
        ':email'     => $email,
        ':phone'     => $phone,
        ':subject'   => $subject !== '' ? $subject : 'Contact form message',
        ':message'   => $message,
        ':ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
        ':ua'        => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
    ]);

    $_SESSION['flash_success'] = 'Thank you! Your message has been sent. We will reply soon.';
    header('Location: ' . SITE_URL . 'contact.php');
    exit;

} catch (PDOException $e) {
    error_log('contact_process error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not send message. Please try again or call us.';
    header('Location: ' . SITE_URL . 'contact.php');
    exit;
}
