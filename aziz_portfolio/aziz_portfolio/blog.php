<?php
// blog.php — Blog listing page
$page_title = 'Blog';
require_once __DIR__ . '/includes/header.php';

// Pagination
$per_page = 9;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// Category filter
$cat_filter = trim($_GET['category'] ?? '');

$where = "WHERE status = 'published'";
$args  = [];
if ($cat_filter !== '') {
    $where .= " AND category = ?";
    $args[] = $cat_filter;
}

// Total count for pagination
$count_sql    = "SELECT COUNT(*) AS c FROM blog_posts $where";
$count_stmt   = $conn->prepare($count_sql);
if ($args) $count_stmt->bind_param(str_repeat('s', count($args)), ...$args);
$count_stmt->execute();
$total        = (int)$count_stmt->get_result()->fetch_assoc()['c'];
$total_pages  = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// Posts
$sql = "SELECT id, title, slug, excerpt, featured_image, category, tags, views, published_at
        FROM blog_posts
        $where
        ORDER BY published_at DESC, id DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($args) $stmt->bind_param(str_repeat('s', count($args)), ...$args);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Categories list
$cats_result = $conn->query("SELECT DISTINCT category FROM blog_posts WHERE status='published' AND category IS NOT NULL ORDER BY category");
$categories  = $cats_result ? $cats_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<section class="blog-page">
    <div class="container">

        <!-- Header -->
        <div class="section-header" style="margin-bottom: 40px;">
            <h1><?= htmlspecialchars(t('blog_title')) ?></h1>
            <p class="section-subtitle"><?= htmlspecialchars(t('blog_subtitle')) ?></p>
        </div>

        <!-- Category filters -->
        <?php if ($categories): ?>
            <div class="blog-filters">
                <a href="blog.php" class="filter-btn <?= $cat_filter === '' ? 'active' : '' ?>">
                    <?= htmlspecialchars(t('blog_all')) ?>
                </a>
                <?php foreach ($categories as $c): ?>
                    <a href="blog.php?category=<?= urlencode($c['category']) ?>"
                       class="filter-btn <?= $cat_filter === $c['category'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($c['category']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Posts grid -->
        <?php if (empty($posts)): ?>
            <div class="blog-empty">
                <i class="fas fa-newspaper"></i>
                <p><?= htmlspecialchars(t('blog_empty')) ?></p>
            </div>
        <?php else: ?>
            <div class="blog-grid">
                <?php foreach ($posts as $p): ?>
                    <article class="blog-card">
                        <a href="blog_post.php?id=<?= (int)$p['id'] ?>" class="blog-card-image-link">
                            <?php if (!empty($p['featured_image'])): ?>
                                <img src="<?= htmlspecialchars($p['featured_image']) ?>"
                                     alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="blog-card-placeholder">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                            <?php endif; ?>
                        </a>

                        <div class="blog-card-body">
                            <?php if (!empty($p['category'])): ?>
                                <span class="blog-card-category"><?= htmlspecialchars($p['category']) ?></span>
                            <?php endif; ?>

                            <h2 class="blog-card-title">
                                <a href="blog_post.php?id=<?= (int)$p['id'] ?>">
                                    <?= htmlspecialchars($p['title']) ?>
                                </a>
                            </h2>

                            <?php if (!empty($p['excerpt'])): ?>
                                <p class="blog-card-excerpt"><?= htmlspecialchars($p['excerpt']) ?></p>
                            <?php endif; ?>

                            <div class="blog-card-meta">
                                <span><i class="far fa-calendar"></i> <?= htmlspecialchars(date('d.m.Y', strtotime($p['published_at'] ?? $p['created_at'] ?? 'now'))) ?></span>
                                <span><i class="far fa-eye"></i> <?= (int)$p['views'] ?></span>
                            </div>

                            <a href="blog_post.php?id=<?= (int)$p['id'] ?>" class="blog-card-link">
                                <?= htmlspecialchars(t('blog_read_more')) ?> <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $cat_filter ? '&category=' . urlencode($cat_filter) : '' ?>">
                            <i class="fas fa-chevron-left"></i> <?= htmlspecialchars(t('blog_prev')) ?>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?><?= $cat_filter ? '&category=' . urlencode($cat_filter) : '' ?>"
                           class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $cat_filter ? '&category=' . urlencode($cat_filter) : '' ?>">
                            <?= htmlspecialchars(t('blog_next')) ?> <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
