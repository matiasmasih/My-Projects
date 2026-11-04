<?php
// includes/staff_header.php — Staff Layout (Teal theme)

require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, email, first_name, last_name, profile_image FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff_user = $stmt->fetch();

if ($staff_user['role'] !== 'staff' && $staff_user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$current_user_name = !empty($staff_user['first_name'])
    ? $staff_user['first_name'] . ' ' . $staff_user['last_name']
    : $staff_user['email'];
$initials = !empty($staff_user['first_name'])
    ? strtoupper(substr($staff_user['first_name'], 0, 1) . substr($staff_user['last_name'], 0, 1))
    : strtoupper(substr($staff_user['email'], 0, 2));
$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Staff';
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
        /* Staff Teal Theme Override */
        .admin-sidebar-link.active {
            background: linear-gradient(135deg, #0ea5e9, #14b8a6) !important;
            box-shadow: 0 4px 15px -5px rgba(14, 165, 233, 0.6) !important;
        }
        .admin-sidebar-logo i {
            color: #0ea5e9 !important;
            filter: drop-shadow(0 0 8px rgba(14, 165, 233, 0.5)) !important;
        }
        .admin-topbar-title i {
            color: #0ea5e9 !important;
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <aside class="admin-sidebar" id="adminSidebar">
        <a href="<?= e(SITE_URL) ?>staff/index.php" class="admin-sidebar-logo">
            <i class="fas fa-user-tie"></i>
            <span>Staff Portal</span>
        </a>

        <!-- SCROLLABLE NAVIGATION AREA -->
        <div style="flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px 12px 20px 12px;">
            <nav class="admin-sidebar-nav" style="padding: 0; gap: 4px; display: flex; flex-direction: column;">
                <a href="<?= e(SITE_URL) ?>staff/index.php" class="admin-sidebar-link <?= $current_page === 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i> Dashboard
                </a>
                <div class="admin-sidebar-divider"></div>

                <a href="<?= e(SITE_URL) ?>staff/members.php" class="admin-sidebar-link <?= $current_page === 'members.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Members
                </a>
                <a href="<?= e(SITE_URL) ?>staff/add_member.php" class="admin-sidebar-link">
                    <i class="fas fa-user-plus"></i> Add Member
                </a>

                <div class="admin-sidebar-divider"></div>

                <a href="<?= e(SITE_URL) ?>staff/loans.php" class="admin-sidebar-link <?= $current_page === 'loans.php' ? 'active' : '' ?>">
                    <i class="fas fa-book-open"></i> Process Book Loans
                </a>
                <a href="<?= e(SITE_URL) ?>staff/device_loans.php" class="admin-sidebar-link <?= $current_page === 'device_loans.php' ? 'active' : '' ?>">
                    <i class="fas fa-laptop"></i> Process Device Loans
                </a>
                <a href="<?= e(SITE_URL) ?>staff/reservations.php" class="admin-sidebar-link <?= $current_page === 'reservations.php' ? 'active' : '' ?>">
                    <i class="fas fa-bookmark"></i> Reservations
                </a>

                <div class="admin-sidebar-divider"></div>

                <a href="<?= e(SITE_URL) ?>staff/transfers.php" class="admin-sidebar-link <?= $current_page === 'transfers.php' ? 'active' : '' ?>">
                    <i class="fas fa-exchange-alt"></i> Book Transfers
                </a>
                <a href="<?= e(SITE_URL) ?>staff/device_transfers.php" class="admin-sidebar-link <?= $current_page === 'device_transfers.php' ? 'active' : '' ?>">
                    <i class="fas fa-truck"></i> Device Transfers
                </a>

                <div class="admin-sidebar-divider"></div>

                <a href="<?= e(SITE_URL) ?>staff/fines.php" class="admin-sidebar-link <?= $current_page === 'fines.php' ? 'active' : '' ?>">
                    <i class="fas fa-euro-sign"></i> Manage Fines
                </a>

                <div class="admin-sidebar-divider"></div>

                <a href="<?= e(SITE_URL) ?>staff/profile.php" class="admin-sidebar-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
                <a href="<?= e(SITE_URL) ?>staff/settings.php" class="admin-sidebar-link <?= $current_page === 'settings.php' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> Settings
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
                <?php if (!empty($staff_user['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $staff_user['profile_image'])): ?>
                    <img src="<?= UPLOADS_URL . e($staff_user['profile_image']) ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.9rem;">
                        <?= e($initials) ?>
                    </div>
                <?php endif; ?>
                <div style="display:flex; flex-direction:column; overflow:hidden;">
                    <span style="font-weight:600; font-size:0.9rem; color:var(--text); white-space:nowrap; text-overflow: ellipsis;"><?= e($current_user_name) ?></span>
                    <span style="font-size:0.75rem; color:var(--text-muted); text-transform: uppercase;">Staff</span>
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
                    <i class="fas fa-user-tie" style="color: #0ea5e9;"></i>
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
