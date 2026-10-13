<?php
// admin_add_post.php — Create new blog post
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

require_login('admin_login.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $content  = $_POST['content'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $tags     = trim($_POST['tags'] ?? '');
    $status   = $_POST['status'] ?? 'published';
    $image    = trim($_POST['featured_image'] ?? '');

    if ($title === '') {
        $error = t('post_err_title');
    } elseif ($content === '') {
        $error = t('post_err_content');
    } elseif (!in_array($status, ['draft','published'], true)) {
        $error = t('post_err_status');
    } else {
        // Build slug
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $title));
        $slug = trim($slug, '-');
        if ($slug === '') $slug = 'post-' . time();

        // Unique slug
        $chk = $conn->prepare("SELECT id FROM blog_posts WHERE slug = ?");
        $chk->bind_param("s", $slug);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $slug .= '-' . time();
        }

        // Excerpt from content
        $excerpt = trim(strip_tags($content));
        if (mb_strlen($excerpt) > 200) $excerpt = mb_substr($excerpt, 0, 200) . '…';

        $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;

        $stmt = $conn->prepare(
            "INSERT INTO blog_posts (title, slug, content, excerpt, featured_image, category, tags, status, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            "sssssssss",
            $title, $slug, $content, $excerpt, $image, $category, $tags, $status, $published_at
        );

        if ($stmt->execute()) {
            header('Location: admin_dashboard.php?msg=added');
            exit;
        } else {
            $error = t('post_err_db') . ' ' . $conn->error;
        }
    }
}

$page_title = t('post_new_title');
require_once __DIR__ . '/includes/header.php';
?>

<section class="post-editor-page">
    <div class="container">

        <div class="editor-top">
            <a href="admin_dashboard.php" class="post-back">
                <i class="fas fa-arrow-left"></i> <?= htmlspecialchars(t('post_back_dash')) ?>
            </a>
            <h1><?= htmlspecialchars(t('post_new_title')) ?></h1>
        </div>

        <?php if ($error): ?>
            <div class="dash-flash dash-flash-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_add_post.php" class="editor-form">
            <div class="editor-grid">

                <!-- Main column -->
                <div class="editor-main">
                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_title')) ?> *</label>
                        <input type="text" name="title" required
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                               placeholder="<?= htmlspecialchars(t('post_ph_title')) ?>">
                    </div>

                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_content')) ?> *</label>
                        <p class="editor-hint"><?= htmlspecialchars(t('post_html_hint')) ?></p>
                        <textarea name="content" rows="20" required
                                  placeholder="<h2>...</h2>&#10;<p>...</p>"><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Side column -->
                <div class="editor-side">
                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_status')) ?></label>
                        <select name="status">
                            <option value="published" <?= ($_POST['status'] ?? 'published') === 'published' ? 'selected' : '' ?>><?= htmlspecialchars(t('post_status_published')) ?></option>
                            <option value="draft"     <?= ($_POST['status'] ?? '') === 'draft' ? 'selected' : '' ?>><?= htmlspecialchars(t('post_status_draft')) ?></option>
                        </select>
                    </div>

                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_category')) ?></label>
                        <input type="text" name="category"
                               value="<?= htmlspecialchars($_POST['category'] ?? '') ?>"
                               placeholder="<?= htmlspecialchars(t('post_ph_category')) ?>">
                    </div>

                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_tags')) ?></label>
                        <input type="text" name="tags"
                               value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>"
                               placeholder="<?= htmlspecialchars(t('post_ph_tags')) ?>">
                    </div>

                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_image')) ?></label>
                        <input type="text" name="featured_image"
                               value="<?= htmlspecialchars($_POST['featured_image'] ?? '') ?>"
                               placeholder="https://...">
                        <p class="editor-hint"><?= htmlspecialchars(t('post_image_hint')) ?></p>
                    </div>

                    <div class="editor-panel editor-actions">
                        <button type="submit" class="btn btn-accent" style="width:100%;">
                            <i class="fas fa-check"></i> <?= htmlspecialchars(t('post_save')) ?>
                        </button>
                        <a href="admin_dashboard.php" class="btn btn-outline" style="width:100%;">
                            <?= htmlspecialchars(t('post_cancel')) ?>
                        </a>
                    </div>
                </div>

            </div>
        </form>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
