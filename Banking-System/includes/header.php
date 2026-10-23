<?php
// includes/header.php — Public top nav for Banking App
require_once __DIR__ . '/config.php';

// Detect current user
$current_user = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id, email, role, photo, first_name FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
}

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Modern Bank';

// Initials for avatar fallback
$initials = '';
if ($current_user) {
    if (!empty($current_user['first_name'])) {
        $initials = strtoupper(substr($current_user['first_name'], 0, 2));
    } else {
        $parts = explode('@', $current_user['email']);
        $initials = strtoupper(substr($parts[0], 0, 2));
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <script>
        (function() {
            var t = localStorage.getItem('bank-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — Modern Bank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= e(ASSETS_URL) ?>style.css">
</head>
<body>

<nav class="top-nav" style="display:flex; justify-content:space-between; align-items:center; padding: 16px 40px; background: var(--card-bg); border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100;">
    
    <!-- Logo -->
    <a href="<?= e(SITE_URL) ?>index.php" style="display:flex; align-items:center; gap:10px; font-size:1.3rem; font-weight:800; color:var(--text); text-decoration:none;">
        <i class="fas fa-university" style="color:var(--primary); filter: drop-shadow(0 0 8px var(--primary-glow));"></i>
        <span>Modern Bank</span>
    </a>

    <!-- Right Side -->
    <div style="display:flex; align-items:center; gap: 24px;">
        
        <!-- Theme Toggle -->
        <button type="button" id="themeBtn" style="background:none; border:none; color:var(--text-muted); font-size:1.2rem; cursor:pointer; transition: var(--transition);">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>

        <?php if ($current_user): ?>
            <!-- Logged In User -->
            <div style="display:flex; align-items:center; gap:12px; border-left: 1px solid var(--border); padding-left: 24px;">
                <?php if (!empty($current_user['photo']) && file_exists(__DIR__ . '/../uploads/' . $current_user['photo'])): ?>
                    <img src="<?= UPLOADS_URL . e($current_user['photo']) ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                <?php else: ?>
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: grid; place-items: center; font-weight: bold; font-size: 0.9rem;">
                        <?= e($initials) ?>
                    </div>
                <?php endif; ?>
                <div style="display:flex; flex-direction:column; line-height: 1.2;">
                    <span style="font-size:0.9rem; font-weight:600; color:var(--text);"><?= e($current_user['email']) ?></span>
                    <a href="<?= e(SITE_URL) ?>logout.php" style="font-size:0.8rem; color:var(--danger); text-decoration:none; font-weight: 600;">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Not Logged In -->
            <a href="<?= e(SITE_URL) ?>index.php" style="padding: 10px 24px; background: var(--primary); border-radius: 10px; color: white; text-decoration: none; font-weight: 600; box-shadow: 0 4px 15px -5px var(--primary-glow); transition: var(--transition);">Login</a>
        <?php endif; ?>
    </div>
</nav>

<main>
