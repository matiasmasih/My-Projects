<?php
// includes/admin_header.php — Admin sidebar + topbar

require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'employee/dashboard.php');
    exit;
}

$current_user_id   = (int)$_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'] ?? 'Admin';
$current_user_role = $_SESSION['user_role'] ?? 'admin';

$current_page = basename($_SERVER['PHP_SELF']);
$page_title   = $page_title ?? 'Admin';

// --- FETCH ADMIN PHOTO FROM DATABASE ---
$stmt_admin = $pdo->prepare("SELECT e.photo FROM employees e JOIN users u ON u.employee_id = e.id WHERE u.id = ? LIMIT 1");
$stmt_admin->execute([$current_user_id]);
$admin_data = $stmt_admin->fetch();
$admin_photo = $admin_data['photo'] ?? null;

// Generate initials for fallback
$initials = '';
$parts = explode(' ', $current_user_name);
$initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
if ($initials === '') $initials = strtoupper(substr($current_user_name, 0, 2));
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
    <title><?= e($page_title) ?> — Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <link rel="stylesheet" href="<?= e(ASSETS_URL) ?>style.css">
    <link rel="stylesheet" href="<?= e(ASSETS_URL) ?>admin.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>

<div class="admin-layout">

    <aside class="admin-sidebar" id="adminSidebar">
        <a href="<?= e(SITE_URL) ?>admin/dashboard.php" class="admin-sidebar-logo">
            <i class="fas fa-clock"></i>
            <span>Attendance</span>
        </a>

        <nav class="admin-sidebar-nav">
            <a href="<?= e(SITE_URL) ?>admin/dashboard.php" class="admin-sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a href="<?= e(SITE_URL) ?>admin/employees.php" class="admin-sidebar-link <?= $current_page === 'employees.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Employees
            </a>
            <a href="<?= e(SITE_URL) ?>admin/attendance.php" class="admin-sidebar-link <?= $current_page === 'attendance.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i> Attendance
            </a>
            <a href="<?= e(SITE_URL) ?>admin/reports.php" class="admin-sidebar-link <?= $current_page === 'reports.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie"></i> Reports
            </a>
            <a href="<?= e(SITE_URL) ?>admin/run_absence_check.php" class="admin-sidebar-link <?= $current_page === 'run_absence_check.php' ? 'active' : '' ?>">
                <i class="fas fa-user-clock"></i> Mark Absences
            </a>

            <div class="admin-sidebar-divider"></div>

            <a href="<?= e(SITE_URL) ?>admin/branches.php" class="admin-sidebar-link <?= $current_page === 'branches.php' ? 'active' : '' ?>">
                <i class="fas fa-building"></i> Branches
            </a>
            <a href="<?= e(SITE_URL) ?>admin/departments.php" class="admin-sidebar-link <?= $current_page === 'departments.php' ? 'active' : '' ?>">
                <i class="fas fa-sitemap"></i> Departments
            </a>
            <a href="<?= e(SITE_URL) ?>admin/shifts.php" class="admin-sidebar-link <?= $current_page === 'shifts.php' ? 'active' : '' ?>">
                <i class="fas fa-clock"></i> Shifts
            </a>
            <a href="<?= e(SITE_URL) ?>admin/settings.php" class="admin-sidebar-link <?= $current_page === 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-gear"></i> Settings
            </a>
        </nav>

        <div class="admin-sidebar-footer">
            <div style="display:flex; align-items:center; gap:10px;">
                <?php if (!empty($admin_photo) && file_exists(__DIR__ . '/../uploads/' . $admin_photo)): ?>
                    <img src="<?= SITE_URL ?>uploads/<?= e($admin_photo) ?>" alt="Admin" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border: 2px solid var(--border);">
                <?php else: ?>
                    <div style="width:36px; height:36px; border-radius:50%; background:var(--primary); color:#fff; display:grid; place-items:center; font-weight:700; font-size:0.8rem;">
                        <?= e($initials) ?>
                    </div>
                <?php endif; ?>
                <div style="display:flex; flex-direction:column; overflow:hidden;">
                    <span style="font-weight:600; font-size:0.85rem; color:var(--text); white-space:nowrap;"><?= e($current_user_name) ?></span>
                    <span style="font-size:0.75rem; color:var(--text-muted);"><?= e(ucfirst($current_user_role)) ?></span>
                </div>
            </div>
        </div>
    </aside>

    <div class="admin-main">

        <header class="admin-topbar">

            <div style="display:flex;align-items:center;gap:12px;">
                <button type="button" class="admin-sidebar-toggle" onclick="document.getElementById('adminSidebar').classList.toggle('open')">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="admin-topbar-title">
                    <i class="fas fa-clock" style="color:var(--primary);"></i>
                    <?= e($page_title) ?>
                </div>
            </div>

            <div class="admin-topbar-actions">

                <button type="button" class="dropdown-toggle" id="themeBtn" title="Toggle theme" style="width:38px;height:38px;padding:0;justify-content:center;">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>

                <div class="user-avatar" style="display:flex; align-items:center; gap:10px;">
                    <?php if (!empty($admin_photo) && file_exists(__DIR__ . '/../uploads/' . $admin_photo)): ?>
                        <img src="<?= SITE_URL ?>uploads/<?= e($admin_photo) ?>" alt="Admin" style="width:30px; height:30px; border-radius:50%; object-fit:cover;">
                    <?php else: ?>
                        <span class="user-avatar-initials"><?= e($initials) ?></span>
                    <?php endif; ?>
                    <span><?= e($current_user_name) ?></span>
                </div>

                <a href="<?= e(SITE_URL) ?>logout.php" class="btn btn-outline btn-sm" title="Logout">
                    <i class="fas fa-right-from-bracket"></i>
                </a>

            </div>
        </header>

        <div class="admin-content">
