<?php
// includes/manager_header.php — Manager Layout (Gold theme)

require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT u.role, u.email, u.first_name, u.last_name, u.profile_image FROM users u WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$manager_user = $stmt->fetch();

if (!$manager_user || $manager_user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$current_user_name = !empty($manager_user['first_name'])
    ? $manager_user['first_name'] . ' ' . $manager_user['last_name']
    : $manager_user['email'];

$initials = !empty($manager_user['first_name'])
    ? strtoupper(substr($manager_user['first_name'], 0, 1) . substr($manager_user['last_name'], 0, 1))
    : strtoupper(substr($manager_user['email'], 0, 2));

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Manager Dashboard';
$selected_branch_id = isset($_GET['branch']) ? (int)$_GET['branch'] : ($_SESSION['branch_id'] ?? 1);
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
        /* Manager Gold Theme Override */
        .admin-sidebar-link.active {
            background: linear-gradient(135deg, #f59e0b, #ea580c) !important;
            box-shadow: 0 4px 15px -5px rgba(245, 158, 11, 0.6) !important;
        }
        .admin-sidebar-logo i {
            color: #f59e0b !important;
            filter: drop-shadow(0 0 8px rgba(245, 158, 11, 0.5)) !important;
        }
        .admin-topbar-title i {
            color: #f59e0b !important;
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <aside class="admin-sidebar" id="adminSidebar">
        <a href="<?= e(SITE_URL) ?>manager/index.php" class="admin-sidebar-logo">
            <i class="fas fa-user-tie"></i>
            <span>Manager Portal</span>
        </a>

        <div style="flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px 12px 20px 12px;">
            <nav class="admin-sidebar-nav" style="padding: 0; gap: 4px; display: flex; flex-direction: column;">
                <a href="<?= e(SITE_URL) ?>manager/index.php?branch=<?= $selected_branch_id ?>" class="admin-sidebar-link <?= $current_page === 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>

                <div class="admin-sidebar-divider"></div>

                <!-- 1. Manage Branch (Top item, distinct color, no active background) -->
                <a href="<?= e(SITE_URL) ?>manager/branches.php" class="admin-sidebar-link <?= $current_page === 'branches.php' ? 'active' : '' ?>">
                    <i class="fas fa-building"></i> Manage Branch
                </a>
                <a href="<?= e(SITE_URL) ?>manager/staff.php" class="admin-sidebar-link <?= $current_page === 'staff.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-tie"></i> Manage Staff
                </a>

                <div class="admin-sidebar-divider"></div>

                <!-- 2. View Switcher Links -->
                <a href="<?= e(SITE_URL) ?>admin/index.php" class="admin-sidebar-link" style="color: #ef4444;">
                    <i class="fas fa-shield-alt"></i> Admin View
                </a>
                <a href="<?= e(SITE_URL) ?>staff/index.php" class="admin-sidebar-link" style="color: #0ea5e9;">
                    <i class="fas fa-user-tie"></i> Staff View
                </a>
                <a href="<?= e(SITE_URL) ?>user/dashboard.php" class="admin-sidebar-link" style="color: #10b981;">
                    <i class="fas fa-user"></i> Member View
                </a>

                <div class="admin-sidebar-divider"></div>

                <!-- 3. Manager Tools -->
                <a href="<?= e(SITE_URL) ?>manager/books.php?branch=<?= $selected_branch_id ?>" class="admin-sidebar-link <?= in_array($current_page, ['books.php', 'book_edit.php']) ? 'active' : '' ?>">
                    <i class="fas fa-book"></i> Manage Books
                </a>
                <a href="<?= e(SITE_URL) ?>manager/loans.php?branch=<?= $selected_branch_id ?>" class="admin-sidebar-link <?= $current_page === 'loans.php' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> Book Loans
                </a>
                <a href="<?= e(SITE_URL) ?>manager/devices.php?branch=<?= $selected_branch_id ?>" class="admin-sidebar-link <?= $current_page === 'devices.php' ? 'active' : '' ?>">
                    <i class="fas fa-laptop"></i> Devices
                </a>
                <a href="<?= e(SITE_URL) ?>manager/device_loans.php?branch=<?= $selected_branch_id ?>" class="admin-sidebar-link <?= $current_page === 'device_loans.php' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> Device Loans
                </a>
                <a href="<?= e(SITE_URL) ?>manager/reports.php?branch=<?= $selected_branch_id ?>" class="admin-sidebar-link <?= $current_page === 'reports.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i> Reports
                </a>
                <a href="<?= e(SITE_URL) ?>manager/messages.php?branch=<?= $selected_branch_id ?>" class="admin-sidebar-link <?= $current_page === 'messages.php' ? 'active' : '' ?>">
                    <i class="fas fa-comments"></i> Messages
                </a>

                <div class="admin-sidebar-divider"></div>

                <!-- 4. Profile & Settings -->
                <a href="<?= e(SITE_URL) ?>manager/profile.php" class="admin-sidebar-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
                <a href="<?= e(SITE_URL) ?>manager/settings.php" class="admin-sidebar-link <?= $current_page === 'settings.php' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </nav>
        </div>

        <div style="flex-shrink: 0; padding: 0 24px; margin-top: 10px;">
            <div style="height: 1px; background: var(--border); width: 100%;"></div>
        </div>

        <div class="admin-sidebar-footer" style="border-top: none; padding: 16px 24px 20px 24px; margin-top: 10px; flex-shrink: 0;">
            <div style="display:flex; align-items:center; gap:12px;">
                <?php if (!empty($manager_user['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $manager_user['profile_image'])): ?>
                    <img src="<?= UPLOADS_URL . e($manager_user['profile_image']) ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #f59e0b, #ea580c); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.9rem;">
                        <?= e($initials) ?>
                    </div>
                <?php endif; ?>
                <div style="display:flex; flex-direction:column; overflow:hidden;">
                    <span style="font-weight:600; font-size:0.9rem; color:var(--text); white-space:nowrap; text-overflow: ellipsis;"><?= e($current_user_name) ?></span>
                    <span style="font-size:0.75rem; color:var(--text-muted); text-transform: uppercase;">MANAGER</span>
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
                    <i class="fas fa-user-tie" style="color: #f59e0b;"></i>
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
