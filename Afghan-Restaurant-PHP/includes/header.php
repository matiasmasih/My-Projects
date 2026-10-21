<?php
// includes/header.php — Top navigation (desktop + mobile)
// Requires config.php and i18n.php to be loaded first.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/i18n.php';

$lang       = current_language();
$dir        = text_direction();
$langs      = supported_languages();
$page_title = $page_title ?? SITE_NAME;

// ---------- Branch selection ----------
try {
    $branches = $pdo->query("SELECT id, slug, name, name_fi, name_fa, country_code, default_lang, currency_code FROM branches WHERE is_active = 1 ORDER BY sort_order")
                    ->fetchAll();
} catch (PDOException $e) {
    $branches = [];
}

// Current branch: URL → session → first branch
$current_branch_id = null;
if (isset($_GET['branch'])) {
    foreach ($branches as $b) {
        if ($b['slug'] === $_GET['branch']) {
            $_SESSION['branch_id'] = $b['id'];
            $_SESSION['branch_slug'] = $b['slug'];
            $current_branch_id = $b['id'];
            break;
        }
    }
}
if ($current_branch_id === null && isset($_SESSION['branch_id'])) {
    $current_branch_id = $_SESSION['branch_id'];
}
if ($current_branch_id === null && !empty($branches)) {
    $current_branch_id = $branches[0]['id'];
    $_SESSION['branch_id']   = $branches[0]['id'];
    $_SESSION['branch_slug'] = $branches[0]['slug'];
}

$current_branch = null;
foreach ($branches as $b) {
    if ($b['id'] == $current_branch_id) { $current_branch = $b; break; }
}

// Helper: pick branch name based on current lang
function pick_branch_name(array $branch, string $lang): string {
    if ($lang === 'fi' && !empty($branch['name_fi'])) return $branch['name_fi'];
    if ($lang === 'fa' && !empty($branch['name_fa'])) return $branch['name_fa'];
    return $branch['name'] ?? '';
}

// ---------- Currency selection ----------
try {
    $currencies = $pdo->query("SELECT code, name, symbol FROM currencies WHERE is_active = 1 ORDER BY sort_order")
                      ->fetchAll();
} catch (PDOException $e) {
    $currencies = [];
}

if (isset($_GET['currency'])) {
    foreach ($currencies as $c) {
        if ($c['code'] === strtoupper($_GET['currency'])) {
            $_SESSION['currency'] = $c['code'];
            break;
        }
    }
}
$current_currency = $_SESSION['currency'] ?? setting('default_currency', 'EUR');
$currency_symbol  = '€';
foreach ($currencies as $c) {
    if ($c['code'] === $current_currency) { $currency_symbol = $c['symbol']; break; }
}

// ---------- Cart count ----------
$cart_count = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += (int)($item['qty'] ?? 0);
    }
}

// ---------- Is customer logged in? ----------
$customer_logged_in = !empty($_SESSION['customer_id']);
$customer_name      = $_SESSION['customer_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — <?= e(setting('site_name', SITE_NAME)) ?></title>
    <meta name="description" content="<?= e(setting('site_tagline', '')) ?>">

    <link rel="stylesheet" href="<?= e(ASSETS_URL) ?>style.css">
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

<!-- =========================================================
     TOP NAVIGATION
     ========================================================= -->
<nav class="top-nav">
    <div class="container nav-inner">

        <!-- Logo -->
        <a href="<?= e(SITE_URL) ?>index.php" class="nav-logo">
            <i class="fas fa-utensils"></i>
            <span><?= e(setting('site_name', SITE_NAME)) ?></span>
        </a>

        <!-- Primary menu -->
        <div class="nav-menu" id="navMenu">
            <a href="<?= e(SITE_URL) ?>index.php"><?= e(t('nav_home')) ?></a>
            <a href="<?= e(SITE_URL) ?>menu.php"><?= e(t('nav_menu')) ?></a>
            <a href="<?= e(SITE_URL) ?>reserve.php"><?= e(t('nav_reservations')) ?></a>
            <a href="<?= e(SITE_URL) ?>contact.php"><?= e(t('nav_contact')) ?></a>
            <a href="<?= e(SITE_URL) ?>about.php"><?= e(t('nav_about')) ?></a>

            <!-- Mobile-only controls -->
            <div class="nav-mobile-controls">

                <!-- Branch -->
                <?php if (!empty($branches)): ?>
                <div class="dropdown" data-dropdown>
                    <button class="dropdown-toggle" type="button">
                        <i class="fas fa-location-dot"></i>
                        <?= e($current_branch ? pick_branch_name($current_branch, $lang) : t('nav_select_branch')) ?>
                        <i class="fas fa-chevron-down chev"></i>
                    </button>
                    <div class="dropdown-menu">
                        <?php foreach ($branches as $b): ?>
                            <a href="?branch=<?= e($b['slug']) ?>" class="<?= $b['id'] == $current_branch_id ? 'active' : '' ?>">
                                <i class="fas fa-store"></i> <?= e(pick_branch_name($b, $lang)) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Language -->
                <div class="dropdown" data-dropdown>
                    <button class="dropdown-toggle" type="button">
                        <i class="fas fa-globe"></i>
                        <?= e($langs[$lang]['flag']) ?> <?= e($langs[$lang]['native']) ?>
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

                <!-- Currency -->
                <?php if (!empty($currencies)): ?>
                <div class="dropdown" data-dropdown>
                    <button class="dropdown-toggle" type="button">
                        <i class="fas fa-coins"></i>
                        <?= e($currency_symbol) ?> <?= e($current_currency) ?>
                        <i class="fas fa-chevron-down chev"></i>
                    </button>
                    <div class="dropdown-menu">
                        <?php foreach ($currencies as $c): ?>
                            <a href="?currency=<?= e($c['code']) ?>" class="<?= $c['code'] === $current_currency ? 'active' : '' ?>">
                                <?= e($c['symbol']) ?> <?= e($c['code']) ?> — <?= e($c['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Theme -->
                <button type="button" class="nav-mobile-link" data-theme-toggle>
                    <i class="fas fa-moon"></i> <?= e(t('nav_theme_dark')) ?>
                </button>

                <!-- Account -->
                <?php if ($customer_logged_in): ?>
                    <a class="nav-mobile-link" href="<?= e(SITE_URL) ?>account.php">
                        <i class="fas fa-user"></i> <?= e($customer_name) ?>
                    </a>
                    <a class="nav-mobile-link" href="<?= e(SITE_URL) ?>logout.php">
                        <i class="fas fa-right-from-bracket"></i> <?= e(t('nav_logout')) ?>
                    </a>
                <?php else: ?>
                    <a class="nav-mobile-link" href="<?= e(SITE_URL) ?>login.php">
                        <i class="fas fa-right-to-bracket"></i> <?= e(t('nav_login')) ?>
                    </a>
                    <a class="nav-mobile-link" href="<?= e(SITE_URL) ?>register.php">
                        <i class="fas fa-user-plus"></i> <?= e(t('nav_register')) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right-side controls -->
        <div class="nav-right">

            <!-- Branch (desktop) -->
            <?php if (!empty($branches)): ?>
            <div class="dropdown" data-dropdown>
                <button class="dropdown-toggle" type="button">
                    <i class="fas fa-location-dot"></i>
                    <span><?= e($current_branch ? pick_branch_name($current_branch, $lang) : t('nav_select_branch')) ?></span>
                    <i class="fas fa-chevron-down chev"></i>
                </button>
                <div class="dropdown-menu">
                    <?php foreach ($branches as $b): ?>
                        <a href="?branch=<?= e($b['slug']) ?>" class="<?= $b['id'] == $current_branch_id ? 'active' : '' ?>">
                            <i class="fas fa-store"></i> <?= e(pick_branch_name($b, $lang)) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Language (desktop) -->
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

            <!-- Currency (desktop) -->
            <?php if (!empty($currencies)): ?>
            <div class="dropdown" data-dropdown>
                <button class="dropdown-toggle" type="button">
                    <?= e($currency_symbol) ?> <?= e($current_currency) ?>
                    <i class="fas fa-chevron-down chev"></i>
                </button>
                <div class="dropdown-menu">
                    <?php foreach ($currencies as $c): ?>
                        <a href="?currency=<?= e($c['code']) ?>" class="<?= $c['code'] === $current_currency ? 'active' : '' ?>">
                            <?= e($c['symbol']) ?> <?= e($c['code']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Theme toggle -->
            <button type="button" class="dropdown-toggle" data-theme-toggle title="<?= e(t('nav_theme_dark')) ?>">
                <i class="fas fa-moon"></i>
            </button>

            <!-- Cart -->
            <a href="<?= e(SITE_URL) ?>cart.php" class="nav-cart" title="<?= e(t('nav_cart')) ?>">
                <i class="fas fa-cart-plus"></i>
                <?php if ($cart_count > 0): ?>
                    <span class="nav-cart-count"><?= (int)$cart_count ?></span>
                <?php endif; ?>
            </a>

            <!-- Account -->
            <?php if ($customer_logged_in): ?>
                <div class="dropdown" data-dropdown>
                    <button class="dropdown-toggle" type="button">
                        <i class="fas fa-user"></i>
                        <i class="fas fa-chevron-down chev"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="<?= e(SITE_URL) ?>account.php?tab=profile">
                            <i class="fas fa-user"></i> <?= e(t('nav_account')) ?>
                        </a>
                        <a href="<?= e(SITE_URL) ?>account.php?tab=orders">
                            <i class="fas fa-receipt"></i> <?= e(t('nav_orders')) ?>
                        </a>
                        <a href="<?= e(SITE_URL) ?>account.php?tab=reservations">
                            <i class="fas fa-calendar-check"></i> <?= e(t('nav_reservations')) ?>
                        </a>
                        <a href="<?= e(SITE_URL) ?>account.php?tab=messages">
                            <i class="fas fa-envelope"></i> <?= e(t('nav_messages', 'Messages')) ?>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?= e(SITE_URL) ?>logout.php">
                            <i class="fas fa-right-from-bracket"></i> <?= e(t('nav_logout')) ?>
                        </a>
                    </div>

                </div>
            <?php else: ?>
                <a href="<?= e(SITE_URL) ?>login.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-right-to-bracket"></i> <?= e(t('nav_login')) ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Hamburger (mobile) -->
        <button class="nav-hamburger" type="button" data-nav-toggle aria-label="Menu">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</nav>

<main>
