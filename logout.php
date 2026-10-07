<?php
// logout.php — End user session and redirect to login

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';

// Clear all session variables
$_SESSION = [];

// Delete the session cookie
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
header('Location: login.php');
exit;

