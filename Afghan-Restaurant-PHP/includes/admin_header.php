<?php
// includes/admin_header.php — Admin panel layout (sidebar + topbar)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/i18n.php';

// Redirect if not logged in
if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$lang = current_language();
$dir  = text_direction();
$langs = supported_languages();

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
try {
    $stmt = $pdo->prepare("SELECT full_name, full_name_fi, full_name_fa FROM admin_users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['admin_id']]);
    $row = $stmt->fetch();
    if ($row) {
        if ($lang === 'fi' && !empty($row['full_name_fi'])) $admin_name = $row['full_name_fi'];
        elseif ($lang === 'fa' && !empty($row['full_name_fa'])) $admin_name = $row['full_name_fa'];
        else $admin_name = $row['full_name'];
    }
} catch (PDOException $e) {}

$admin_role   = $_SESSION['admin_role']   ?? 'admin';
$admin_branch = $_SESSION['admin_branch_id'] ?? null;

function pick_branch_name(array $branch, string $lang): string {
    if ($lang === 'fi' && !empty($branch['name_fi'])) return $branch['name_fi'];
    if ($lang === 'fa' && !empty($branch['name_fa'])) return $branch['name_fa'];
    return $branch['name'] ?? '';
}


// Current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

$page_title = $page_title ?? 'Admin';

// Currency (based on language)
$currencies = [];
try {
    $currencies = $pdo->query("SELECT code, symbol FROM currencies WHERE is_active = 1")->fetchAll();
} catch (PDOException $e) {}

$current_currency = $_SESSION['currency'] ?? 'EUR';
$currency_symbol  = '€';
foreach ($currencies as $c) {
    if ($c['code'] === $current_currency) { $currency_symbol = $c['symbol']; break; }
}


// Current branch name
$branch_name = '';
if ($admin_branch) {
    try {
        $stmt = $pdo->prepare("SELECT name, name_fi, name_fa FROM branches WHERE id = ? LIMIT 1");
        $stmt->execute([$admin_branch]);
        $b = $stmt->fetch();
        if ($b) {
            if ($lang === 'fi' && !empty($b['name_fi'])) $branch_name = $b['name_fi'];
            elseif ($lang === 'fa' && !empty($b['name_fa'])) $branch_name = $b['name_fa'];
            else $branch_name = $b['name'];
        }
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <script>
        (function() {
            var t = localStorage.getItem('afghan-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — Admin</title>
    <link rel="stylesheet" href="<?= e(ASSETS_URL) ?>style.css">
    <link rel="stylesheet" href="<?= e(ASSETS_URL) ?>admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="<?= e(ASSETS_URL) ?>images/favicon.ico">
    <link rel="manifest" href="<?= e(SITE_URL) ?>manifest.json">
    <link rel="apple-touch-icon" href="<?= e(ASSETS_URL) ?>icons/apple-touch-icon.png">
    <meta name="theme-color" content="#ca8a04">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Afghan Restaurant">
</head>
<body>

<div class="admin-layout">

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">

        <a href="admin_dashboard.php" class="admin-sidebar-logo">
            <i class="fas fa-utensils"></i>
            <span>Admin Panel</span>
        </a>

        <nav class="admin-sidebar-nav">
            <a href="admin_dashboard.php"     class="admin-sidebar-link <?= $current_page === 'admin_dashboard.php'     ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i> <?= e(t('admin_dashboard')) ?>
            </a>
            <a href="admin_orders.php"        class="admin-sidebar-link <?= $current_page === 'admin_orders.php'        ? 'active' : '' ?>">
                <i class="fas fa-receipt"></i> <?= e(t('admin_orders')) ?>
            </a>
            <a href="admin_menu_items.php"    class="admin-sidebar-link <?= $current_page === 'admin_menu_items.php'    ? 'active' : '' ?>">
                <i class="fas fa-utensils"></i> <?= e(t('admin_menu_items')) ?>
            </a>
            <a href="admin_categories.php"    class="admin-sidebar-link <?= $current_page === 'admin_categories.php'    ? 'active' : '' ?>">
                <i class="fas fa-tags"></i> <?= e(t('admin_categories')) ?>
            </a>
            <a href="admin_reservations.php"  class="admin-sidebar-link <?= $current_page === 'admin_reservations.php'  ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i> <?= e(t('admin_reservations')) ?>
            </a>
            <a href="admin_messages.php"      class="admin-sidebar-link <?= $current_page === 'admin_messages.php'      ? 'active' : '' ?>">
                <i class="fas fa-envelope"></i> <?= e(t('admin_messages')) ?>
            </a>
            <a href="admin_reports.php" class="admin-sidebar-link <?= $current_page === 'admin_reports.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-pie"></i> Reports
            </a>

            <?php if ($admin_role === 'admin'): ?>
                <div class="admin-sidebar-divider"></div>

                <a href="admin_branches.php" class="admin-sidebar-link <?= $current_page === 'admin_branches.php' ? 'active' : '' ?>">
                    <i class="fas fa-store"></i> <?= e(t('admin_branches')) ?>
                </a>
                <a href="admin_users.php" class="admin-sidebar-link <?= $current_page === 'admin_users.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> <?= e(t('admin_users')) ?>
                </a>
                <a href="admin_settings.php" class="admin-sidebar-link <?= $current_page === 'admin_settings.php' ? 'active' : '' ?>">
                    <i class="fas fa-gear"></i> <?= e(t('admin_settings') ?? 'Settings') ?>
                </a>
            <?php endif; ?>
        </nav>

        <div class="admin-sidebar-footer">
            <div><?= e($admin_role === 'admin' ? 'Admin' : 'Manager') ?></div>
            <div style="opacity:0.7;margin-top:4px;"><?= e($branch_name ?: 'All branches') ?></div>
        </div>

    </aside>

    <!-- Main -->
    <div class="admin-main">

        <!-- Top Bar -->
        <header class="admin-topbar">

            <div style="display:flex;align-items:center;gap:12px;">
                <button type="button" class="admin-sidebar-toggle" onclick="document.getElementById('adminSidebar').classList.toggle('open')">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="admin-topbar-title">
                    <i class="fas fa-utensils" style="color:var(--accent);"></i>
                    <?= e($page_title) ?>
                </div>
            </div>

            <div class="admin-topbar-actions">

                <!-- Language -->
                <div class="dropdown" data-dropdown>
                    <button class="dropdown-toggle" type="button">
                        <?= e($langs[$lang]['flag']) ?> <?= strtoupper($lang) ?>
                        <i class="fas fa-chevron-down chev"></i>
                    </button>
                    <div class="dropdown-menu">
                        <?php foreach ($langs as $code => $info): ?>
                            <a href="?lang=<?= e($code) ?>" class="<?= $code === $lang ? 'active' : '' ?>">
                                <?= e($info['flag']) ?> <?= e($info['native']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Theme toggle -->
                <button type="button" class="theme-btn" id="themeToggleBtn" title="Theme">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>

                <!-- User -->
                <div class="admin-user-badge">
                    <i class="fas fa-user-circle"></i>
                    <span><?= e($admin_name) ?></span>
                </div>

                <!-- Logout -->
                <a href="admin_logout.php" class="btn btn-outline btn-sm" title="<?= e(t('admin_logout')) ?>">
                    <i class="fas fa-right-from-bracket"></i>
                </a>

            </div>

<script>
(function() {
    var btn = document.getElementById('themeToggleBtn');
    var icon = document.getElementById('themeIcon');
    var currentTheme = localStorage.getItem('afghan-theme') || 'light';

    // Set initial icon
    if (icon) {
        icon.className = currentTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }

    // Handle click
    if (btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var h = document.documentElement;
            var t = h.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';

            h.setAttribute('data-theme', t);
            localStorage.setItem('afghan-theme', t);

            if (icon) {
                icon.className = t === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        });
    }
})();
</script>
</header>

        <!-- Content -->
        <div class="admin-content">
