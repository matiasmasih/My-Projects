<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'user') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Get the page slug from the URL
$slug = $_GET['page'] ?? 'privacy';

// Fetch the legal page from the database
$stmt = $pdo->prepare("SELECT * FROM legal_pages WHERE slug = ? LIMIT 1");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    header('Location: ' . SITE_URL . 'user/settings.php');
    exit;
}

$page_title = $page['title'];
require_once __DIR__ . '/../includes/user_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <a href="<?= e(SITE_URL) ?>user/settings.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-weight: 600; margin-bottom: 16px;">
        <i class="fas fa-arrow-left"></i> Back to Settings
    </a>
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;"><?= e($page['title']) ?></h1>
    <p style="color: var(--text-muted);">Last updated: <?= date('F j, Y', strtotime($page['updated_at'])) ?></p>
</div>

<div class="card" style="max-width: 900px; margin: 0 auto; padding: 40px;">
    <div style="color: var(--text); line-height: 1.8; font-size: 1rem;">
        <?= $page['content'] ?>
    </div>
</div>

<style>
    /* Styling for the legal content */
    .card h2 {
        font-size: 1.3rem;
        margin-top: 24px;
        margin-bottom: 12px;
        color: #10b981;
    }
    .card h2:first-child {
        margin-top: 0;
    }
    .card p {
        margin-bottom: 16px;
        color: var(--text-muted);
    }
</style>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
