<?php
// includes/header.php — Public top nav + footer opener

require_once __DIR__ . '/config.php';

// Detect current user
$current_user = null;
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, username, full_name, role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch();
    } catch (PDOException $e) {}
}

// Active page
$current_page = basename($_SERVER['PHP_SELF']);

// Page title
$page_title = $page_title ?? 'Attendance System';

// Initials for avatar
$initials = '';
if ($current_user) {
    $parts = explode(' ', $current_user['full_name']);
    $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
    if ($initials === '') $initials = strtoupper(substr($current_user['username'], 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <script>
        (function() {
            var t = localStorage.getItem('attendance-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — Attendance System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(ASSETS_URL) ?>style.css">
</head>
<body>

<nav class="top-nav">
    <div class="container nav-inner">

        <a href="<?= e(SITE_URL) ?>index.php" class="nav-logo">
            <i class="fas fa-clock"></i>
            <span>Attendance</span>
        </a>

        <div class="nav-menu" id="navMenu">
            <a href="<?= e(SITE_URL) ?>index.php" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a>
            <?php if ($current_user): ?>
                <?php if ($current_user['role'] === 'admin' || $current_user['role'] === 'manager'): ?>
                    <a href="<?= e(SITE_URL) ?>admin/dashboard.php">Admin</a>
                <?php endif; ?>
                <?php if ($current_user['role'] === 'employee'): ?>
                    <a href="<?= e(SITE_URL) ?>employee/dashboard.php">My Dashboard</a>
                    <a href="<?= e(SITE_URL) ?>employee/history.php">My History</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="nav-right">

            <button type="button" class="dropdown-toggle" id="themeBtn" title="Toggle theme" style="width:38px;height:38px;padding:0;justify-content:center;">
                <i class="fas fa-moon" id="themeIcon"></i>
            </button>

            <?php if ($current_user): ?>
                <div class="dropdown" data-dropdown>
                    <button class="dropdown-toggle user-avatar" type="button">
                        <span class="user-avatar-initials"><?= e($initials) ?></span>
                        <span><?= e($current_user['full_name']) ?></span>
                        <i class="fas fa-chevron-down" style="font-size:0.7rem;"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="<?= e(SITE_URL) ?>logout.php">
                            <i class="fas fa-right-from-bracket"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= e(SITE_URL) ?>login.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-right-to-bracket"></i> Login
                </a>
                <a href="<?= e(SITE_URL) ?>register.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-user-plus"></i> Register
                </a>
            <?php endif; ?>

        </div>

    </div>
</nav>

<main>
