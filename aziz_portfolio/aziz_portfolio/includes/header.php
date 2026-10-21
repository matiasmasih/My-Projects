<?php
// includes/header.php — Top navigation with dropdowns
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';

$page_title     = $page_title ?? 'Aziz Rahman Noyan';
$current_lang   = current_language();
$dir            = text_direction();
$langs          = supported_languages();
$logged_in      = is_logged_in();
$admin          = current_admin();
$admin_name     = current_admin_name();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($current_lang) ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | <?= htmlspecialchars(SITE_NAME) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">

    <!-- Favicon + PWA manifest -->
    <link rel="icon" type="image/png" sizes="192x192" href="assets/images/icon-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="assets/images/icon-512.png">
    <link rel="shortcut icon" href="favicon.ico">
    <link rel="apple-touch-icon" href="assets/images/icon-192.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#d4af37">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
</head>
<body>

<nav class="top-nav">
    <div class="container nav-inner">

        <!-- Logo -->
        <a href="index.php" class="nav-logo">
            <?= htmlspecialchars(SITE_NAME) ?>
        </a>

        <!-- Nav menu -->
        <nav class="nav-menu">
            <a href="index.php#about"><?= htmlspecialchars(t('nav_about')) ?></a>
            <a href="index.php#certifications"><?= htmlspecialchars(t('nav_certifications')) ?></a>
            <a href="index.php#projects"><?= htmlspecialchars(t('nav_projects')) ?></a>
            <a href="blog.php"><?= htmlspecialchars(t('nav_blog')) ?></a>
            <a href="index.php#contact"><?= htmlspecialchars(t('nav_contact')) ?></a>

            <!-- MOBILE-ONLY CONTROLS (hidden on desktop) -->
            <div class="nav-mobile-controls">

                <div class="dropdown" id="dropdown-lang-mobile">
                    <button type="button" class="dropdown-toggle">
                        <span>🌐 <?= strtoupper($current_lang) ?></span>
                        <i class="fas fa-chevron-down chev"></i>
                    </button>
                    <div class="dropdown-menu">
                        <?php foreach ($langs as $code => $meta): ?>
                            <a href="?lang=<?= $code ?>" class="<?= $code === $current_lang ? 'active' : '' ?>">
                                <span><?= $meta['flag'] ?></span>
                                <span><?= htmlspecialchars($meta['native']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="dropdown" id="dropdown-theme-mobile">
                    <button type="button" class="dropdown-toggle">
                        <span data-theme-label>☀️ <?= htmlspecialchars(t('theme_light')) ?></span>
                        <i class="fas fa-chevron-down chev"></i>
                    </button>
                    <div class="dropdown-menu">
                        <button type="button" data-theme-option="light" onclick="toggleTheme('light')">
                            <span>☀️</span>
                            <span><?= htmlspecialchars(t('theme_light')) ?></span>
                        </button>
                        <button type="button" data-theme-option="dark" onclick="toggleTheme('dark')">
                            <span>🌙</span>
                            <span><?= htmlspecialchars(t('theme_dark')) ?></span>
                        </button>
                    </div>
                </div>

                <button type="button" class="prefs-btn" data-prefs-open>
                    <span>🎨 <?= htmlspecialchars(t('prefs_label')) ?></span>
                </button>

                <?php if ($logged_in): ?>
                    <div class="dropdown" id="dropdown-account-mobile">
                        <button type="button" class="dropdown-toggle">
                            <span>👤 <?= htmlspecialchars($admin_name) ?></span>
                            <i class="fas fa-chevron-down chev"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="admin_dashboard.php">
                                <span>📊</span>
                                <span><?= htmlspecialchars(t('nav_dashboard')) ?></span>
                            </a>
                            <a href="admin_settings.php">
                                <span>⚙️</span>
                                <span><?= htmlspecialchars(t('nav_settings')) ?></span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="admin_logout.php">
                                <span>🚪</span>
                                <span><?= htmlspecialchars(t('nav_logout')) ?></span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="admin_login.php" class="btn btn-primary nav-mobile-login">
                        <i class="fas fa-sign-in-alt"></i>
                        <?= htmlspecialchars(t('nav_login')) ?>
                    </a>
                <?php endif; ?>

            </div>
        </nav>

        <!-- Mobile hamburger -->
        <button type="button" class="nav-hamburger" onclick="document.querySelector('.nav-menu').classList.toggle('open')">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Right-side controls -->
        <div class="nav-right">

            <!-- LANGUAGE DROPDOWN -->
            <div class="dropdown" id="dropdown-lang">
                <button type="button" class="dropdown-toggle">
                    <span>🌐 <?= strtoupper($current_lang) ?></span>
                    <i class="fas fa-chevron-down chev"></i>
                </button>
                <div class="dropdown-menu">
                    <?php foreach ($langs as $code => $meta): ?>
                        <a href="?lang=<?= $code ?>" class="<?= $code === $current_lang ? 'active' : '' ?>">
                            <span><?= $meta['flag'] ?></span>
                            <span><?= htmlspecialchars($meta['native']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- THEME DROPDOWN -->
            <div class="dropdown" id="dropdown-theme">
                <button type="button" class="dropdown-toggle">
                    <span data-theme-label>☀️ <?= htmlspecialchars(t('theme_light')) ?></span>
                    <i class="fas fa-chevron-down chev"></i>
                </button>
                <div class="dropdown-menu">
                    <button type="button" data-theme-option="light" onclick="toggleTheme('light')">
                        <span>☀️</span>
                        <span><?= htmlspecialchars(t('theme_light')) ?></span>
                    </button>
                    <button type="button" data-theme-option="dark" onclick="toggleTheme('dark')">
                        <span>🌙</span>
                        <span><?= htmlspecialchars(t('theme_dark')) ?></span>
                    </button>
                </div>
            </div>

            <!-- VISITOR PREFERENCES BUTTON -->
            <button type="button" class="prefs-btn" data-prefs-open>
                <span>🎨 <?= htmlspecialchars(t('prefs_label')) ?></span>
            </button>

            <!-- ACCOUNT DROPDOWN / LOGIN BUTTON -->
            <?php if ($logged_in): ?>
                <div class="dropdown" id="dropdown-account">
                    <button type="button" class="dropdown-toggle">
                        <span>👤 <?= htmlspecialchars($admin_name) ?></span>
                        <i class="fas fa-chevron-down chev"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="admin_dashboard.php">
                            <span>📊</span>
                            <span><?= htmlspecialchars(t('nav_dashboard')) ?></span>
                        </a>
                        <a href="admin_settings.php">
                            <span>⚙️</span>
                            <span><?= htmlspecialchars(t('nav_settings')) ?></span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="admin_logout.php">
                            <span>🚪</span>
                            <span><?= htmlspecialchars(t('nav_logout')) ?></span>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="admin_login.php" class="btn btn-primary" style="padding: 8px 18px;">
                    <i class="fas fa-sign-in-alt"></i>
                    <?= htmlspecialchars(t('nav_login')) ?>
                </a>
            <?php endif; ?>

        </div>
    </div>
</nav>

<!-- Theme bootstrap script — applies saved theme before paint -->
<script>
(function () {
    var saved = localStorage.getItem('aziz-theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
})();
</script>
<script src="assets/app.js" defer></script>
<script src="assets/preferences.js" defer></script>
