<?php
// logout.php — Audit-logged logout

require_once __DIR__ . '/includes/config.php';

// Log the logout action before destroying the session
if (isset($_SESSION['user_id'])) {
    try {
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'logout', ?, ?)")
            ->execute([
                $_SESSION['user_id'],
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
    } catch (PDOException $e) {
        // Silently fail logging — still destroy session
    }
}

// Clear all session data
$_SESSION = [];

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login
header('Location: ' . SITE_URL . 'index.php');
exit;
