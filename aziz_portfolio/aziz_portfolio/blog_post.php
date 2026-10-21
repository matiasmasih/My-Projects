<?php
// blog_post.php — Single blog post view
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

$post_id = (int)($_GET['id'] ?? 0);
if ($post_id <= 0) {
    header('Location: blog.php');
    exit;
}

// Personal info for CV etc.
$info = $conn->query("SELECT * FROM personal_info LIMIT 1")->fetch_assoc();

// Load post
$stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ? AND status = 'published'");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    header('Location: blog.php');
    exit;
}

// Increment view count
$conn->query("UPDATE blog_posts SET views = views + 1 WHERE id = " . (int)$post_id);

// Related posts (same category, exclude current, limit 3)
$related = [];
if (!empty($post['category'])) {
    $rs = $conn->prepare("SELECT id, title, featured_image, published_at
                          FROM blog_posts
                          WHERE status='published' AND category = ? AND id <> ?
                          ORDER BY published_at DESC LIMIT 3");
    $rs->bind_param("si", $post['category'], $post_id);
    $rs->execute();
    $related = $rs->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Page title = post title
$page_title = $post['title'];

require_once __DIR__ . '/includes/header.php';
?>

<article class="post-page">
    <div class="container container-narrow">

        <!-- Back link -->
        <a href="blog.php" class="post-back">
            <i class="fas fa-arrow-left"></i> <?= htmlspecialchars(t('post_back')) ?>
        </a>

        <!-- Featured image -->
        <?php if (!empty($post['featured_image'])): ?>
            <div class="post-hero-image">
                <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>">
            </div>
        <?php endif; ?>

        <!-- Meta -->
        <div class="post-meta-top">
            <?php if (!empty($post['category'])): ?>
                <span class="post-category"><?= htmlspecialchars($post['category']) ?></span>
            <?php endif; ?>
            <span class="post-date">
                <i class="far fa-calendar"></i>
                <?= htmlspecialchars(date('d.m.Y', strtotime($post['published_at'] ?? $post['created_at'] ?? 'now'))) ?>
            </span>
            <span class="post-views">
                <i class="far fa-eye"></i>
                <?= (int)$post['views'] ?> <?= htmlspecialchars(t('post_views')) ?>
            </span>
        </div>

        <!-- Title -->
        <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>

        <!-- Tags -->
        <?php if (!empty($post['tags'])): ?>
            <div class="post-tags">
                <?php foreach (explode(',', $post['tags']) as $tag): ?>
                    <span class="post-tag"><?= htmlspecialchars(trim($tag)) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Content -->
        <div class="post-content">
            <?= $post['content'] ?>
        </div>

        <!-- Footer of post -->
        <div class="post-footer">
            <div class="post-share">
                <span><?= htmlspecialchars(t('post_share')) ?></span>
                <a href="https://twitter.com/intent/tweet?url=<?= urlencode(SITE_URL . 'blog_post.php?id=' . $post_id) ?>&text=<?= urlencode($post['title']) ?>" target="_blank" rel="noopener" title="Twitter">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(SITE_URL . 'blog_post.php?id=' . $post_id) ?>" target="_blank" rel="noopener" title="Facebook">
                    <i class="fab fa-facebook"></i>
                </a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode(SITE_URL . 'blog_post.php?id=' . $post_id) ?>" target="_blank" rel="noopener" title="LinkedIn">
                    <i class="fab fa-linkedin"></i>
                </a>
            </div>
        </div>

        <!-- Related posts -->
        <?php if ($related): ?>
            <div class="post-related">
                <h3><?= htmlspecialchars(t('post_related')) ?></h3>
                <div class="post-related-grid">
                    <?php foreach ($related as $r): ?>
                        <a href="blog_post.php?id=<?= (int)$r['id'] ?>" class="post-related-card">
                            <div class="post-related-image">
                                <?php if (!empty($r['featured_image'])): ?>
                                    <img src="<?= htmlspecialchars($r['featured_image']) ?>" alt="<?= htmlspecialchars($r['title']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="post-related-placeholder"><i class="fas fa-newspaper"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="post-related-body">
                                <div class="post-related-title"><?= htmlspecialchars($r['title']) ?></div>
                                <div class="post-related-date">
                                    <?= htmlspecialchars(date('d.m.Y', strtotime($r['published_at'] ?? 'now'))) ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
