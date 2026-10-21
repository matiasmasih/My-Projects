<?php
// admin_logout.php — End admin session

require_once __DIR__ . '/includes/config.php';

// Clear admin session keys only (keep customer session if any)
unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_username'],
    $_SESSION['admin_name'],
    $_SESSION['admin_role'],
    $_SESSION['admin_branch_id']
);

header('Location: ' . SITE_URL . 'admin_login.php');
exit;
