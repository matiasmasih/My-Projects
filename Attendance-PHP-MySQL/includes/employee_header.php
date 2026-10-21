<?php
// includes/employee_header.php — Employee restricted layout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// Fetch employee details directly from DB for the sidebar
$stmt_header = $pdo->prepare("SELECT first_name, last_name, photo FROM employees WHERE id = (SELECT employee_id FROM users WHERE id = ?) LIMIT 1");
$stmt_header->execute([$_SESSION['user_id']]);
$emp_data = $stmt_header->fetch();

$full_name = $emp_data ? $emp_data['first_name'] . ' ' . $emp_data['last_name'] : 'Employee';
$initials = $emp_data ? strtoupper(substr($emp_data['first_name'], 0, 1) . substr($emp_data['last_name'], 0, 1)) : 'EM';
$header_photo = $emp_data['photo'] ?? null;

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark"> <!-- Default to Dark Mode -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' · Attendance' : 'Attendance' ?></title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Base CSS -->
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/style.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/admin.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/employee.css">

    <!-- Theme Toggle Script (Runs immediately to prevent flash) -->
    <script>
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    </script>
</head>
<body class="admin-body">

    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-logo">
            <i class="fas fa-clock"></i> Attendance
        </div>

        <nav class="sidebar-nav">
            <a href="<?= SITE_URL ?>employee/dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-clock"></i> My Dashboard
            </a>
            <a href="<?= SITE_URL ?>employee/history.php" class="nav-item <?= $current_page === 'history.php' ? 'active' : '' ?>">
                <i class="fas fa-history"></i> My History
            </a>
            <a href="<?= SITE_URL ?>employee/leave_request.php" class="nav-item <?= $current_page === 'leave_request.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> Leave Requests
            </a>
            <a href="<?= SITE_URL ?>employee/profile.php" class="nav-item <?= $current_page === 'profile.php' ? 'active' : '' ?>">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-mini-profile">
                <?php if (!empty($header_photo) && file_exists(__DIR__ . '/../uploads/' . $header_photo)): ?>
                    <img src="<?= SITE_URL ?>uploads/<?= e($header_photo) ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <div class="avatar"><?= e($initials) ?></div>
                <?php endif; ?>
                <div class="user-info">
                    <span class="name"><?= e($full_name) ?></span>
                    <span class="role">Employee</span>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="admin-main">

        <!-- Topbar -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <h2><?= isset($page_title) ? e($page_title) : 'Dashboard' ?></h2>
            </div>
            <div class="topbar-right" style="display:flex; gap:12px; align-items:center;">

                <!-- Theme Toggle Button -->
                <button id="themeToggle" class="btn btn-outline btn-sm" style="border-radius: 50%; width: 40px; height: 40px; padding: 0; display: grid; place-items: center;" title="Toggle Theme">
                    <i class="fas fa-sun" id="themeIcon"></i>
                </button>

                <a href="<?= SITE_URL ?>logout.php" class="btn btn-outline btn-sm" title="Logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <!-- Page Content Container -->
        <div class="admin-content">

<script>
    // Global banner clock
    function updateBannerClock() {
        const timeEl = document.getElementById('bannerTime');
        const dateEl = document.getElementById('bannerDate');
        if (!timeEl) return;

        const now = new Date();
        // Time
        timeEl.textContent = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute:'2-digit', second:'2-digit' });
        // Date
        dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' });
    }
    setInterval(updateBannerClock, 1000);
    updateBannerClock();
</script>
