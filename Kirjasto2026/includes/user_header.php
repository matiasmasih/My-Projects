<?php
// includes/user_header.php — Member Layout (Green theme)

require_once __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, email, first_name, last_name, profile_image, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$member_user = $stmt->fetch();

if (!$member_user || ($member_user['role'] !== 'member' && $member_user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branch_id = $member_user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$current_user_name = !empty($member_user['first_name'])
    ? $member_user['first_name'] . ' ' . $member_user['last_name']
    : $member_user['email'];

$initials = !empty($member_user['first_name'])
    ? strtoupper(substr($member_user['first_name'], 0, 1) . substr($member_user['last_name'], 0, 1))
    : strtoupper(substr($member_user['email'], 0, 2));

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Member Portal';
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
        /* Member Green Theme Override */
        .admin-sidebar-link.active {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            box-shadow: 0 4px 15px -5px rgba(16, 185, 129, 0.6) !important;
        }
        .admin-sidebar-logo i {
            color: #10b981 !important;
            filter: drop-shadow(0 0 8px rgba(16, 185, 129, 0.5)) !important;
        }
        .admin-topbar-title i {
            color: #10b981 !important;
        }
    </style>
</head>
<body>

<div class="admin-layout">
    <aside class="admin-sidebar" id="adminSidebar">
        <a href="<?= e(SITE_URL) ?>user/dashboard.php" class="admin-sidebar-logo">
            <i class="fas fa-book-reader"></i>
            <span>Member Portal</span>
        </a>

        <!-- Branch Badge -->
        <?php if ($branch_name): ?>
        <div style="padding: 0 24px; margin-bottom: 16px; margin-top: -10px;">
            <div style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; border: 1px solid rgba(16, 185, 129, 0.2);">
                <i class="fas fa-map-marker-alt"></i> <?= e($branch_name) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SCROLLABLE NAVIGATION AREA -->
        <div style="flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px 12px 20px 12px;">
            <nav class="admin-sidebar-nav" style="padding: 0; gap: 4px; display: flex; flex-direction: column;">
                <a href="<?= e(SITE_URL) ?>user/dashboard.php" class="admin-sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="<?= e(SITE_URL) ?>user/books.php" class="admin-sidebar-link <?= $current_page === 'books.php' ? 'active' : '' ?>">
                    <i class="fas fa-book"></i> Browse Books
                </a>
                <a href="<?= e(SITE_URL) ?>user/loans.php" class="admin-sidebar-link <?= $current_page === 'loans.php' ? 'active' : '' ?>">
                    <i class="fas fa-hand-holding-heart"></i> My Loans
                </a>
                <a href="<?= e(SITE_URL) ?>user/reservations.php" class="admin-sidebar-link <?= $current_page === 'reservations.php' ? 'active' : '' ?>">
                    <i class="fas fa-bookmark"></i> My Reservations
                </a>
                <a href="<?= e(SITE_URL) ?>user/favorites.php" class="admin-sidebar-link <?= $current_page === 'favorites.php' ? 'active' : '' ?>">
                    <i class="fas fa-heart"></i> Favorites
                </a>

                <div class="admin-sidebar-divider"></div>

                <a href="<?= e(SITE_URL) ?>user/devices.php" class="admin-sidebar-link <?= $current_page === 'devices.php' ? 'active' : '' ?>">
                    <i class="fas fa-laptop"></i> Browse Devices
                </a>
                <a href="<?= e(SITE_URL) ?>user/device_loans.php" class="admin-sidebar-link <?= $current_page === 'device_loans.php' ? 'active' : '' ?>">
                    <i class="fas fa-mobile-alt"></i> My Device Loans
                </a>

                <div class="admin-sidebar-divider"></div>

                <a href="<?= e(SITE_URL) ?>user/fines.php" class="admin-sidebar-link <?= $current_page === 'fines.php' ? 'active' : '' ?>">
                    <i class="fas fa-euro-sign"></i> My Fines
                </a>
                <a href="<?= e(SITE_URL) ?>user/messages.php" class="admin-sidebar-link <?= $current_page === 'messages.php' ? 'active' : '' ?>">
                    <i class="fas fa-comments"></i> Messages
                </a>
                <a href="<?= e(SITE_URL) ?>user/notifications.php" class="admin-sidebar-link <?= $current_page === 'notifications.php' ? 'active' : '' ?>">
                    <i class="fas fa-bell"></i> Notifications
                </a>

                <div class="admin-sidebar-divider"></div>

                <a href="<?= e(SITE_URL) ?>user/profile.php" class="admin-sidebar-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
                <a href="<?= e(SITE_URL) ?>user/settings.php" class="admin-sidebar-link <?= $current_page === 'settings.php' ? 'active' : '' ?>">
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
                <?php if (!empty($member_user['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $member_user['profile_image'])): ?>
                    <img src="<?= UPLOADS_URL . e($member_user['profile_image']) ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.9rem;">
                        <?= e($initials) ?>
                    </div>
                <?php endif; ?>
                <div style="display:flex; flex-direction:column; overflow:hidden;">
                    <span style="font-weight:600; font-size:0.9rem; color:var(--text); white-space:nowrap; text-overflow: ellipsis;"><?= e($current_user_name) ?></span>
                    <span style="font-size:0.75rem; color:var(--text-muted); text-transform: uppercase;">Member</span>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <main class="admin-main">
        <!-- TOP BAR -->
        <header class="admin-topbar">
            <div class="admin-topbar-title">
                <i class="fas fa-book-reader"></i> <?= e($page_title) ?>
            </div>
            <div style="display: flex; gap: 16px; align-items: center;">
                <button type="button" id="themeBtn" class="btn btn-secondary" style="padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--text); cursor: pointer; font-size: 1rem;">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>

                 <div style="display: flex; align-items: center; gap: 12px; padding-right: 16px;">
                    <?php if (!empty($member_user['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $member_user['profile_image'])): ?>
                        <img src="<?= UPLOADS_URL . e($member_user['profile_image']) ?>" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                    <?php else: ?>
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.85rem;">
                            <?= e($initials) ?>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; flex-direction: column;">
                        <span style="font-size: 0.85rem; font-weight: 700; color: var(--text);"><?= e($current_user_name) ?></span>
                        <span style="font-size: 0.75rem; color: var(--text-muted);"><?= e($member_user['email']) ?></span>
                </div>
             </div>

                <a href="<?= e(SITE_URL) ?>logout.php" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--danger); cursor: pointer; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <!-- OPEN CONTENT WRAPPER -->
        <div class="admin-content">
