<?php
// contact_process.php — Handle contact form submissions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

date_default_timezone_set('Europe/Helsinki');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '') {
        $response['message'] = t('contact_err_name');
    } elseif ($email === '') {
        $response['message'] = t('contact_err_email');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = t('contact_err_email_invalid');
    } elseif ($subject === '') {
        $response['message'] = t('contact_err_subject');
    } elseif ($message === '') {
        $response['message'] = t('contact_err_message');
    } elseif (mb_strlen($message) < 10) {
        $response['message'] = t('contact_err_message_short');
    } else {
        $sql  = "INSERT INTO contact_messages (name, email, subject, message, status, created_at)
                 VALUES (?, ?, ?, ?, 'unread', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $name, $email, $subject, $message);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = t('contact_success');
        } else {
            $response['message'] = t('contact_err_db') . ' ' . $conn->error;
        }
        $stmt->close();
    }
}

// AJAX response
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Fallback redirect
$_SESSION['contact_response'] = $response;
header('Location: index.php#contact');
exit;
