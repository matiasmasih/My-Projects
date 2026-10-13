<?php
// admin_edit_post.php — Edit existing blog post
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

require_login('admin_login.php');

$post_id = (int)($_GET['id'] ?? 0);
if ($post_id <= 0) {
    header('Location: admin_dashboard.php');
    exit;
}

// Load post
$stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    header('Location: admin_dashboard.php');
    exit;
}

$error   = '';
$success = '';

// -------- DELETE --------
if (isset($_POST['delete'])) {
    $del = $conn->prepare("DELETE FROM blog_posts WHERE id = ?");
    $del->bind_param("i", $post_id);
    $del->execute();
    header('Location: admin_dashboard.php?msg=deleted');
    exit;
}

// -------- UPDATE --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete'])) {
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
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $title));
        $slug = trim($slug, '-');
        if ($slug === '') $slug = 'post-' . $post_id;

        // Unique slug (exclude self)
        $chk = $conn->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id <> ?");
        $chk->bind_param("si", $slug, $post_id);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $slug .= '-' . time();
        }

        $excerpt = trim(strip_tags($content));
        if (mb_strlen($excerpt) > 200) $excerpt = mb_substr($excerpt, 0, 200) . '…';

        // If first time published, set published_at
        $published_at = $post['published_at'];
        if ($status === 'published' && empty($published_at)) {
            $published_at = date('Y-m-d H:i:s');
        }

        $upd = $conn->prepare(
            "UPDATE blog_posts
             SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?,
                 category = ?, tags = ?, status = ?, published_at = ?
             WHERE id = ?"
        );
        $upd->bind_param(
            "sssssssssi",
            $title, $slug, $content, $excerpt, $image, $category, $tags, $status, $published_at, $post_id
        );

        if ($upd->execute()) {
            $success = t('post_updated');
            // Reload post
            $r = $conn->prepare("SELECT * FROM blog_posts WHERE id = ?");
            $r->bind_param("i", $post_id);
            $r->execute();
            $post = $r->get_result()->fetch_assoc();
        } else {
            $error = t('post_err_db') . ' ' . $conn->error;
        }
    }
}

$page_title = t('post_edit_title');
require_once __DIR__ . '/includes/header.php';
?>

<section class="post-editor-page">
    <div class="container">

        <div class="editor-top">
            <a href="admin_dashboard.php" class="post-back">
                <i class="fas fa-arrow-left"></i> <?= htmlspecialchars(t('post_back_dash')) ?>
            </a>
            <h1><?= htmlspecialchars(t('post_edit_title')) ?></h1>
        </div>

        <?php if ($success): ?>
            <div class="dash-flash dash-flash-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="dash-flash dash-flash-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_edit_post.php?id=<?= (int)$post_id ?>" class="editor-form">
            <div class="editor-grid">

                <!-- Main -->
                <div class="editor-main">
                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_title')) ?> *</label>
                        <input type="text" name="title" required value="<?= htmlspecialchars($post['title']) ?>">
                    </div>

                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_content')) ?> *</label>
                        <p class="editor-hint"><?= htmlspecialchars(t('post_html_hint')) ?></p>
                        <textarea name="content" rows="20" required><?= htmlspecialchars($post['content']) ?></textarea>
                    </div>
                </div>

                <!-- Side -->
                <div class="editor-side">
                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_status')) ?></label>
                        <select name="status">
                            <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>><?= htmlspecialchars(t('post_status_published')) ?></option>
                            <option value="draft"     <?= $post['status'] === 'draft'     ? 'selected' : '' ?>><?= htmlspecialchars(t('post_status_draft')) ?></option>
                        </select>
                    </div>

                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_category')) ?></label>
                        <input type="text" name="category" value="<?= htmlspecialchars($post['category'] ?? '') ?>">
                    </div>

                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_tags')) ?></label>
                        <input type="text" name="tags" value="<?= htmlspecialchars($post['tags'] ?? '') ?>">
                    </div>

                    <div class="editor-panel">
                        <label><?= htmlspecialchars(t('post_form_image')) ?></label>
                        <input type="text" name="featured_image" value="<?= htmlspecialchars($post['featured_image'] ?? '') ?>" placeholder="https://...">
                    </div>

                    <div class="editor-panel editor-actions">
                        <button type="submit" class="btn btn-accent" style="width:100%;">
                            <i class="fas fa-check"></i> <?= htmlspecialchars(t('post_save')) ?>
                        </button>
                        <a href="blog_post.php?id=<?= (int)$post_id ?>" target="_blank" class="btn btn-outline" style="width:100%;">
                            <i class="fas fa-eye"></i> <?= htmlspecialchars(t('post_preview')) ?>
                        </a>
                        <a href="admin_dashboard.php" class="btn btn-outline" style="width:100%;">
                            <?= htmlspecialchars(t('post_cancel')) ?>
                        </a>
                    </div>

                    <div class="editor-panel" style="border-color:#dc2626;">
                        <button type="submit" name="delete" value="1"
                                class="btn" style="width:100%;background:#dc2626;color:#fff;"
                                onclick="return confirm('<?= htmlspecialchars(t('post_confirm_delete')) ?>');">
                            <i class="fas fa-trash"></i> <?= htmlspecialchars(t('post_delete')) ?>
                        </button>
                    </div>
                </div>

            </div>
        </form>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
