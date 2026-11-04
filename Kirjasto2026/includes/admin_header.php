<?php
// includes/admin_header.php — Admin Layout (Blue theme, Fixed Sidebar)

require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, email, first_name, last_name, profile_image FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin_user = $stmt->fetch();

if ($admin_user['role'] !== 'admin' && $admin_user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$current_user_name = !empty($admin_user['first_name'])
    ? $admin_user['first_name'] . ' ' . $admin_user['last_name']
    : $admin_user['email'];
$initials = !empty($admin_user['first_name'])
    ? strtoupper(substr($admin_user['first_name'], 0, 1) . substr($admin_user['last_name'], 0, 1))
    : strtoupper(substr($admin_user['email'], 0, 2));
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <script>
        (function() {
            var t = localStorage.getItem('kirjasto-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — Kirjasto 2026</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(ASSETS_URL) ?>style.css">
    <link rel="stylesheet" href="<?= e(ASSETS_URL) ?>admin.css">
    <style>
        /* Admin Blue Theme Override */
        .admin-sidebar-link.active {
            background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
            box-shadow: 0 4px 15px -5px rgba(59, 130, 246, 0.6) !important;
        }
        .admin-sidebar-logo i {
            color: #3b82f6 !important;
            filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.5)) !important;
        }
        .admin-topbar-title i {
            color: #3b82f6 !important;
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <aside class="admin-sidebar" id="adminSidebar">
        <a href="<?= e(SITE_URL) ?>admin/index.php" class="admin-sidebar-logo">
            <i class="fas fa-shield-alt"></i>
            <span>Admin Portal</span>
        </a>

        <!-- SCROLLABLE NAVIGATION AREA -->
        <div style="flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px 12px 20px 12px;">
            <nav class="admin-sidebar-nav" style="padding: 0; gap: 4px; display: flex; flex-direction: column;">
                <a href="<?= e(SITE_URL) ?>admin/index.php" class="admin-sidebar-link <?= $current_page === 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <a href="<?= e(SITE_URL) ?>admin/users.php" class="admin-sidebar-link <?= $current_page === 'users.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="<?= SITE_URL ?>admin/branches.php" class="admin-sidebar-link <?= $current_page === 'branches.php' ? 'active' : '' ?>">
                    <i class="fas fa-building"></i> Manage Branches
               </a>
               <a href="<?= SITE_URL ?>admin/transfers.php" class="admin-sidebar-link <?= $current_page === 'transfers.php' ? 'active' : '' ?>">
                     <i class="fas fa-truck"></i> Book Transfers
               </a>
               <a href="<?= SITE_URL ?>admin/device_transfers.php" class="admin-sidebar-link <?= $current_page === 'device_transfers.php' ? 'active' : '' ?>">
                   <i class="fas fa-truck-loading"></i> Device Transfers
               </a>

               <a href="<?= e(SITE_URL) ?>admin/books.php" class="admin-sidebar-link <?= in_array($current_page, ['books.php', 'book_edit.php']) ? 'active' : '' ?>">
                    <i class="fas fa-book"></i> Manage Books
                </a>
                <a href="<?= e(SITE_URL) ?>admin/devices.php" class="admin-sidebar-link <?= in_array($current_page, ['devices.php', 'device_edit.php']) ? 'active' : '' ?>">
                    <i class="fas fa-laptop"></i> Manage Devices
                </a>
                <a href="<?= e(SITE_URL) ?>admin/reports.php" class="admin-sidebar-link <?= $current_page === 'reports.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i> Global Reports
                </a>
                <a href="<?= e(SITE_URL) ?>admin/logs.php" class="admin-sidebar-link <?= $current_page === 'logs.php' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i> Audit Logs
                </a>

                <div class="admin-sidebar-divider"></div>

                <a href="<?= e(SITE_URL) ?>admin/settings.php" class="admin-sidebar-link <?= $current_page === 'settings.php' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> System Settings
                </a>
                <a href="<?= e(SITE_URL) ?>admin/backup.php" class="admin-sidebar-link <?= $current_page === 'backup.php' ? 'active' : '' ?>">
                    <i class="fas fa-database"></i> Backup
                </a>
                
                <div class="admin-sidebar-divider"></div>

                <a href="<?= e(SITE_URL) ?>admin/profile.php" class="admin-sidebar-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
            </nav>
        </div>

        <!-- GAP AND DIVIDER BEFORE FOOTER -->
        <div style="flex-shrink: 0; padding: 0 24px; margin-top: 10px;">
            <div style="height: 1px; background: var(--border); width: 100%;"></div>
        </div>

        <!-- FOOTER -->
        <div class="admin-sidebar-footer" style="border-top: none; padding: 16px 24px 20px 24px; margin-top: 10px; flex-shrink: 0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <?php if (!empty($admin_user['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $admin_user['profile_image'])): ?>
                    <img src="<?= UPLOADS_URL . e($admin_user['profile_image']) ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.9rem;">
                        <?= e($initials) ?>
                    </div>
                <?php endif; ?>
                <div style="display:flex; flex-direction:column; overflow:hidden;">
                    <span style="font-weight:600; font-size:0.9rem; color:var(--text); white-space:nowrap; text-overflow: ellipsis;"><?= e($current_user_name) ?></span>
                    <span style="font-size:0.75rem; color:var(--text-muted); text-transform: uppercase;">Admin</span>
                </div>
            </div>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div style="display:flex; align-items:center; gap:16px;">
                <button type="button" class="admin-sidebar-toggle" onclick="document.getElementById('adminSidebar').classList.toggle('open')">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="admin-topbar-title">
                    <i class="fas fa-shield-alt" style="color: #3b82f6;"></i>
                    <?= e($page_title) ?>
                </div>
            </div>

            <div class="admin-topbar-actions" style="display: flex; align-items: center; gap: 20px;">
                <button type="button" id="themeBtn" title="Toggle theme" style="background: transparent; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer;">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>

                <a href="<?= e(SITE_URL) ?>logout.php" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: rgba(239, 68, 68, 0.1); color: var(--danger); border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.85rem; border: 1px solid rgba(239, 68, 68, 0.2);">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <div class="admin-content">
