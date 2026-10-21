<?php
// index.php — Entry point redirect
session_start();

// Assuming SITE_URL is defined in config, but just in case, let's load it
require_once __DIR__ . '/includes/config.php';

if (!empty($_SESSION['user_id'])) {
    // User is logged in, send them to the right dashboard
    if ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'manager') {
        header('Location: ' . SITE_URL . 'admin/dashboard.php');
    } else {
        header('Location: ' . SITE_URL . 'employee/dashboard.php');
    }
} else {
    // User is not logged in, send them to login
    header('Location: ' . SITE_URL . 'login.php');
}
exit;
