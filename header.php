<?php
// header.php — Shared header for LibraryPro (multi-language)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

$viewer_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($viewer_name === '') {
    $viewer_name = $_SESSION['username'] ?? t('role_guest');
}

$role_names = [
    'admin'     => t('role_admin'),
    'librarian' => t('role_librarian'),
    'member'    => t('role_member'),
    'guest'     => t('role_guest'),
];
$viewer_role = $role_names[current_role()] ?? t('role_guest');
$current_page = basename($_SERVER['PHP_SELF']);
$dir = text_direction();

// Logo target
$logo_href = 'index.php';
switch (current_role()) {
    case 'admin':     $logo_href = 'admin_dashboard.php';     break;
    case 'librarian': $logo_href = 'librarian_dashboard.php'; break;
    case 'member':    $logo_href = 'member_dashboard.php';    break;
}

// Current URL for language switcher links
$qs = $_GET;
unset($qs['lang']);
$base_url = $current_page . ($qs ? '?' . http_build_query($qs) : '');
?>
<!DOCTYPE html>
<html lang="<?= current_language() ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? t('site_name')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0d9488;
            --primary-dark: #0f766e;
            --primary-light: #5eead4;
            --accent: #657aa8;
            --accent-dark: #4f6088;
            --bg: #f0fdfa;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #cbd5e1;
            --sidebar-bg: #134e4a;
            --sidebar-hover: #1a6360;
            --shadow: 0 4px 20px rgba(13, 148, 136, 0.08);
            --radius: 16px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: <?= current_language() === 'fa' ? "'Vazirmatn', sans-serif" : "'Inter', sans-serif" ?>;
            background: var(--bg);
            color: var(--text);
        }
        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(13, 148, 136, 0.3);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar .nav-link {
            color: rgba(255,255,255,0.95) !important;
            font-weight: 500;
            padding: 8px 16px !important;
            border-radius: 10px;
        }
        .navbar .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: white !important;
        }
        .lang-dropdown {
            background: rgba(255,255,255,0.15);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .lang-dropdown option { color: #1e293b; }
        .main-container { display: flex; min-height: calc(100vh - 68px); }
        .sidebar {
            background: var(--sidebar-bg);
            color: white;
            width: 250px;
            padding: 25px 0;
            position: sticky;
            top: 68px;
            height: calc(100vh - 68px);
            overflow-y: auto;
        }
        .sidebar-section {
            padding: 15px 25px 8px;
            color: var(--primary-light);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .sidebar-menu { list-style: none; padding: 0 10px; }
        .sidebar-menu li { margin-bottom: 4px; }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: #b8d4d1;
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .sidebar-menu a:hover {
            background: var(--sidebar-hover);
            color: white;
        }
        .sidebar-menu a.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        .sidebar-menu i {
            margin-right: 12px;
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }
        .main-content {
            flex: 1;
            padding: 30px;
            background: var(--bg);
        }
        .table-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 25px;
        }
        .table-card h5 {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 18px;
            color: var(--accent-dark);
        }
        .table-card h5 i { color: var(--primary); margin-right: 6px; }
        .table-card table { width: 100%; border-collapse: collapse; }
        .table-card th {
            text-align: left;
            padding: 10px 12px;
            background: var(--accent);
            color: white;
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--accent-dark);
        }
        .table-card th:first-child { border-top-left-radius: 8px; }
        .table-card th:last-child  { border-top-right-radius: 8px; }
        .table-card td {
            padding: 12px;
            color: var(--text);
            font-size: 0.9rem;
            border-bottom: 1px solid var(--border);
        }
        .table-card tr:hover td { background: rgba(101, 122, 168, 0.05); }
        .btn-add {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        .btn-add:hover { background: var(--primary-dark); color: white; }
        .btn-secondary-custom {
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-secondary-custom:hover { background: var(--accent-dark); color: white; }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-active    { background: rgba(13, 148, 136, 0.15); color: #0f766e; }
        .status-pending   { background: rgba(101, 122, 168, 0.18); color: #4f6088; }
        .status-cancelled { background: rgba(239, 68, 68, 0.15);  color: #dc2626; }
        .status-danger    { background: rgba(239, 68, 68, 0.15);  color: #dc2626; }
        .status-warning   { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .status-info      { background: rgba(101, 122, 168, 0.18); color: #4f6088; }
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: var(--radius);
            padding: 30px;
            margin-bottom: 25px;
            color: white;
            box-shadow: 0 10px 30px rgba(13, 148, 136, 0.25);
        }
        .welcome-banner h2 { font-weight: 800; font-size: 1.7rem; margin-bottom: 6px; }
        .welcome-banner p  { opacity: 0.95; margin: 0; }
        .welcome-banner .badge-role {
            display: inline-block;
            background: rgba(255,255,255,0.22);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 8px;
            vertical-align: middle;
        }
        @media (max-width: 768px) {
            .main-container { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; top: 0; }
            .sidebar-menu { display: flex; overflow-x: auto; padding: 0 15px; gap: 5px; }
            .sidebar-menu a { padding: 10px 14px; white-space: nowrap; font-size: 0.82rem; }
            .main-content { padding: 20px 15px; }
            .sidebar-section { display: none; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="<?= htmlspecialchars($logo_href) ?>">
            <i class="bi bi-book"></i>
            <span><?= htmlspecialchars(t('site_name')) ?></span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <select class="lang-dropdown" onchange="window.location.href='<?= htmlspecialchars($base_url) ?><?= strpos($base_url, '?') !== false ? '&' : '?' ?>lang='+this.value">
                <option value="en" <?= current_language()==='en'?'selected':'' ?>>English</option>
                <option value="fi" <?= current_language()==='fi'?'selected':'' ?>>Suomi</option>
                <option value="fa" <?= current_language()==='fa'?'selected':'' ?>>فارسی</option>
            </select>
            <?php if (current_role() === 'guest'): ?>
                <a class="nav-link" href="login.php"><i class="bi bi-box-arrow-in-right"></i> <?= htmlspecialchars(t('login')) ?></a>
                <a class="nav-link" href="register.php"><i class="bi bi-person-plus"></i> <?= htmlspecialchars(t('register')) ?></a>
            <?php else: ?>
                <span class="nav-link"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($viewer_name) ?></span>
                <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> <?= htmlspecialchars(t('logout')) ?></a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="main-container">
    <div class="sidebar">
        <div class="sidebar-section"><?= htmlspecialchars(t('sec_catalog')) ?></div>
        <ul class="sidebar-menu">
            <li><a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>"><i class="bi bi-house"></i> <?= htmlspecialchars(t('nav_home')) ?></a></li>
            <li><a href="catalog.php" class="<?= $current_page == 'catalog.php' ? 'active' : '' ?>"><i class="bi bi-book"></i> <?= htmlspecialchars(t('nav_browse')) ?></a></li>
            <li><a href="search.php" class="<?= $current_page == 'search.php' ? 'active' : '' ?>"><i class="bi bi-search"></i> <?= htmlspecialchars(t('nav_search')) ?></a></li>
            <li><a href="rules.php" class="<?= $current_page == 'rules.php' ? 'active' : '' ?>"><i class="bi bi-info-circle"></i> <?= htmlspecialchars(t('nav_rules')) ?></a></li>
        </ul>

        <?php $__role = current_role(); ?>

        <?php if ($__role === 'admin'): ?>
        <div class="sidebar-section"><?= htmlspecialchars(t('sec_dashboard')) ?></div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="<?= $current_page == 'admin_dashboard.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> <?= htmlspecialchars(t('dashboard_admin')) ?></a></li>
        </ul>
        <?php elseif ($__role === 'librarian'): ?>
        <div class="sidebar-section"><?= htmlspecialchars(t('sec_dashboard')) ?></div>
        <ul class="sidebar-menu">
            <li><a href="librarian_dashboard.php" class="<?= $current_page == 'librarian_dashboard.php' ? 'active' : '' ?>"><i class="bi bi-clipboard-data"></i> <?= htmlspecialchars(t('dashboard_librarian')) ?></a></li>
        </ul>
        <?php elseif ($__role === 'member'): ?>
        <div class="sidebar-section"><?= htmlspecialchars(t('sec_dashboard')) ?></div>
        <ul class="sidebar-menu">
            <li><a href="member_dashboard.php" class="<?= $current_page == 'member_dashboard.php' ? 'active' : '' ?>"><i class="bi bi-person-badge"></i> <?= htmlspecialchars(t('dashboard_member')) ?></a></li>
        </ul>
        <?php endif; ?>

        <?php if (in_array($__role, ['member','librarian','admin'], true)): ?>
        <div class="sidebar-section"><?= htmlspecialchars(t('sec_my_account')) ?></div>
        <ul class="sidebar-menu">
            <li><a href="my_account.php" class="<?= $current_page == 'my_account.php' ? 'active' : '' ?>"><i class="bi bi-person"></i> <?= htmlspecialchars(t('nav_profile')) ?></a></li>
            <li><a href="my_loans.php"   class="<?= $current_page == 'my_loans.php'   ? 'active' : '' ?>"><i class="bi bi-bookmark"></i> <?= htmlspecialchars(t('nav_my_loans')) ?></a></li>
            <li><a href="my_history.php" class="<?= $current_page == 'my_history.php' ? 'active' : '' ?>"><i class="bi bi-clock-history"></i> <?= htmlspecialchars(t('nav_history')) ?></a></li>
            <li><a href="my_holds.php"   class="<?= $current_page == 'my_holds.php'   ? 'active' : '' ?>"><i class="bi bi-bookmark-star"></i> <?= htmlspecialchars(t('nav_holds')) ?></a></li>
            <li><a href="my_fines.php"   class="<?= $current_page == 'my_fines.php'   ? 'active' : '' ?>"><i class="bi bi-cash-coin"></i> <?= htmlspecialchars(t('nav_fines')) ?></a></li>
            <li><a href="my_reviews.php" class="<?= $current_page == 'my_reviews.php' ? 'active' : '' ?>"><i class="bi bi-star"></i> <?= htmlspecialchars(t('nav_reviews')) ?></a></li>
            <li><a href="my_messages.php" class="<?= $current_page == 'my_messages.php' ? 'active' : '' ?>"><i class="bi bi-envelope"></i> <?= htmlspecialchars(t('nav_messages')) ?></a></li>
            <li><a href="my_preferences.php" class="<?= $current_page == 'my_preferences.php' ? 'active' : '' ?>"><i class="bi bi-sliders"></i> <?= htmlspecialchars(t('nav_preferences')) ?></a></li>
        </ul>
        <?php endif; ?>

        <?php if (in_array($__role, ['librarian','admin'], true)): ?>
        <div class="sidebar-section"><?= htmlspecialchars(t('sec_librarian')) ?></div>
        <ul class="sidebar-menu">
            <li><a href="manage_books.php"   class="<?= $current_page == 'manage_books.php'   ? 'active' : '' ?>"><i class="bi bi-journal-text"></i> <?= htmlspecialchars(t('nav_books')) ?></a></li>
            <li><a href="manage_copies.php"  class="<?= $current_page == 'manage_copies.php'  ? 'active' : '' ?>"><i class="bi bi-collection"></i> <?= htmlspecialchars(t('nav_copies')) ?></a></li>
            <li><a href="manage_members.php" class="<?= $current_page == 'manage_members.php' ? 'active' : '' ?>"><i class="bi bi-people"></i> <?= htmlspecialchars(t('nav_members')) ?></a></li>
            <li><a href="checkout.php"       class="<?= $current_page == 'checkout.php'       ? 'active' : '' ?>"><i class="bi bi-box-arrow-right"></i> <?= htmlspecialchars(t('nav_checkout')) ?></a></li>
            <li><a href="checkin.php"        class="<?= $current_page == 'checkin.php'        ? 'active' : '' ?>"><i class="bi bi-box-arrow-in-left"></i> <?= htmlspecialchars(t('nav_checkin')) ?></a></li>
            <li><a href="renew.php"     class="<?= $current_page == 'renew.php'     ? 'active' : '' ?>"><i class="bi bi-arrow-clockwise"></i> <?= htmlspecialchars(t('nav_renew')) ?></a></li>
            <li><a href="manage_holds.php"   class="<?= $current_page == 'manage_holds.php'   ? 'active' : '' ?>"><i class="bi bi-bookmark-star"></i> <?= htmlspecialchars(t('nav_holds')) ?></a></li>
            <li><a href="manage_fines.php"   class="<?= $current_page == 'manage_fines.php'   ? 'active' : '' ?>"><i class="bi bi-cash-coin"></i> <?= htmlspecialchars(t('nav_fines')) ?></a></li>
            <li><a href="manage_devices.php" class="<?= $current_page == 'manage_devices.php' ? 'active' : '' ?>"><i class="bi bi-laptop"></i> <?= htmlspecialchars(t('nav_devices')) ?></a></li>
        </ul>
        <?php endif; ?>

        <?php if ($__role === 'admin'): ?>
        <div class="sidebar-section"><?= htmlspecialchars(t('sec_admin')) ?></div>
        <ul class="sidebar-menu">
            <li><a href="manage_users.php"    class="<?= $current_page == 'manage_users.php'    ? 'active' : '' ?>"><i class="bi bi-people-fill"></i> <?= htmlspecialchars(t('nav_users')) ?></a></li>
            <li><a href="manage_branches.php" class="<?= $current_page == 'manage_branches.php' ? 'active' : '' ?>"><i class="bi bi-building"></i> <?= htmlspecialchars(t('nav_branches')) ?></a></li>
            <li><a href="reports.php"         class="<?= $current_page == 'reports.php'         ? 'active' : '' ?>"><i class="bi bi-bar-chart"></i> <?= htmlspecialchars(t('nav_reports')) ?></a></li>
            <li><a href="activity_log.php"    class="<?= $current_page == 'activity_log.php'    ? 'active' : '' ?>"><i class="bi bi-clock-history"></i> <?= htmlspecialchars(t('nav_activity_log')) ?></a></li>
            <li><a href="manage_settings.php" class="<?= $current_page == 'manage_settings.php' ? 'active' : '' ?>"><i class="bi bi-gear"></i> <?= htmlspecialchars(t('nav_settings')) ?></a></li>
        </ul>
        <?php endif; ?>
    </div>

    <div class="main-content">
        <?php if (empty($hide_welcome_banner)): ?>
        <div class="welcome-banner">
            <h2>
                <?= htmlspecialchars(t('welcome')) ?>, <?= htmlspecialchars($viewer_name) ?>
                <span class="badge-role"><?= htmlspecialchars($viewer_role) ?></span>
            </h2>
            <p><?= htmlspecialchars($page_title ?? t('site_name')) ?></p>
        </div>
        <?php endif; ?>
