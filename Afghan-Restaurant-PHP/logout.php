<?php
// logout.php — End session

require_once __DIR__ . '/includes/config.php';

// Clear customer session data (keep language/currency/cart preferences)
unset(
    $_SESSION['customer_id'],
    $_SESSION['customer_name'],
    $_SESSION['customer_email']
);

header('Location: ' . SITE_URL . 'index.php');
exit;
