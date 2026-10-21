<?php
// admin_logout.php — Log the admin out and redirect home
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Clear our new session keys
logout_admin();

// Clear legacy session keys (old admin pages)
unset(
    $_SESSION['admin_logged_in'],
    $_SESSION['admin_name']
);

// Destroy session completely
$_SESSION = [];

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

session_destroy();

// Redirect home
header('Location: index.php');
exit;

