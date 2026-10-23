<?php
require_once __DIR__ . '/includes/config.php';

// Log the logout action if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, status) VALUES (?, 'logout', ?, 'success')")
            ->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Silently fail logging — still destroy session
    }
}

// Destroy session and redirect to login
session_unset();
session_destroy();
header('Location: ' . SITE_URL . 'index.php');
exit;
?>
