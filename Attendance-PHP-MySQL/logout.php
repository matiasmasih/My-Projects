<?php
// logout.php — End user session

require_once __DIR__ . '/includes/config.php';

// Clear all user session keys
unset(
    $_SESSION['user_id'],
    $_SESSION['user_username'],
    $_SESSION['user_name'],
    $_SESSION['user_role']
);

header('Location: ' . SITE_URL . 'login.php');
exit;
