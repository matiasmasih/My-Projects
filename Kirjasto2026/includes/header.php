<?php
// includes/header.php — Public header (Login / Register)

require_once __DIR__ . '/config.php';

$current_user = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id, email, role, profile_image, first_name FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
}

$page_title = $page_title ?? 'Kirjasto 2026';
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
</head>
<body>

<nav style="display:flex; justify-content:space-between; align-items:center; padding:16px 40px; background:var(--card-solid); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:100;">
    <a href="<?= e(SITE_URL) ?>index.php" style="display:flex; align-items:center; gap:10px; font-size:1.3rem; font-weight:800; color:var(--text); text-decoration:none;">
        <i class="fas fa-book-open" style="color:var(--primary); filter: drop-shadow(0 0 8px var(--primary-glow));"></i>
        <span>Kirjasto 2026</span>
    </a>
    <div style="display:flex; align-items:center; gap:24px;">
        <button type="button" id="themeBtn" style="background:none; border:none; color:var(--text-muted); font-size:1.2rem; cursor:pointer; transition:var(--transition);">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        <?php if ($current_user): ?>
            <a href="<?= e(SITE_URL) ?>logout.php" style="padding:8px 16px; background:rgba(239,68,68,0.1); color:var(--danger); border-radius:10px; text-decoration:none; font-weight:600; font-size:0.85rem; border:1px solid rgba(239,68,68,0.2);">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        <?php else: ?>
            <a href="<?= e(SITE_URL) ?>index.php" style="padding:10px 24px; background:var(--primary); border-radius:10px; color:#fff; text-decoration:none; font-weight:600; box-shadow: 0 4px 15px -5px var(--primary-glow);">
                Login
            </a>
        <?php endif; ?>
    </div>
</nav>

<main style="min-height: calc(100vh - 150px);">
