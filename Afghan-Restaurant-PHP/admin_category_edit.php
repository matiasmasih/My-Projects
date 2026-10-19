<?php
// admin_category_edit.php — Add or edit a category

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$is_new = ($id === 0);

$flash = '';
$errors = [];

$cat = [
    'slug'           => '',
    'name_en'        => '',
    'name_fi'        => '',
    'name_fa'        => '',
    'description_en' => '',
    'description_fi' => '',
    'description_fa' => '',
    'icon'           => '',
    'sort_order'     => 0,
    'is_active'      => 1,
];

if (!$is_new) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) $cat = $row;
        else { header('Location: ' . SITE_URL . 'admin_categories.php'); exit; }
    } catch (PDOException $e) {
        header('Location: ' . SITE_URL . 'admin_categories.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat['slug']           = trim($_POST['slug'] ?? '');
    $cat['name_en']        = trim($_POST['name_en'] ?? '');
    $cat['name_fi']        = trim($_POST['name_fi'] ?? '');
    $cat['name_fa']        = trim($_POST['name_fa'] ?? '');
    $cat['description_en'] = trim($_POST['description_en'] ?? '');
    $cat['description_fi'] = trim($_POST['description_fi'] ?? '');
    $cat['description_fa'] = trim($_POST['description_fa'] ?? '');
    $cat['icon']           = trim($_POST['icon'] ?? '');
    $cat['sort_order']     = (int)($_POST['sort_order'] ?? 0);
    $cat['is_active']      = isset($_POST['is_active']) ? 1 : 0;

    if ($cat['slug'] === '')     $errors[] = 'Slug is required.';
    if ($cat['name_en'] === '')  $errors[] = 'English name is required.';
    if ($cat['name_fi'] === '')  $errors[] = 'Finnish name is required.';
    if ($cat['name_fa'] === '')  $errors[] = 'Farsi name is required.';

    // Slug uniqueness
    if (empty($errors)) {
        try {
            $sql = "SELECT id FROM categories WHERE slug = ?";
            $params = [$cat['slug']];
            if (!$is_new) { $sql .= " AND id != ?"; $params[] = $id; }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) $errors[] = 'Slug already exists.';
        } catch (PDOException $e) {}
    }

    if (empty($errors)) {
        try {
            if ($is_new) {
                $stmt = $pdo->prepare("
                    INSERT INTO categories
                    (slug, name_en, name_fi, name_fa, description_en, description_fi, description_fa, icon, sort_order, is_active)
                    VALUES (?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([
                    $cat['slug'], $cat['name_en'], $cat['name_fi'], $cat['name_fa'],
                    $cat['description_en'], $cat['description_fi'], $cat['description_fa'],
                    $cat['icon'], $cat['sort_order'], $cat['is_active']
                ]);
                header('Location: ' . SITE_URL . 'admin_categories.php');
                exit;
            } else {
                $stmt = $pdo->prepare("
                    UPDATE categories SET
                        slug = ?, name_en = ?, name_fi = ?, name_fa = ?,
                        description_en = ?, description_fi = ?, description_fa = ?,
                        icon = ?, sort_order = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $cat['slug'], $cat['name_en'], $cat['name_fi'], $cat['name_fa'],
                    $cat['description_en'], $cat['description_fi'], $cat['description_fa'],
                    $cat['icon'], $cat['sort_order'], $cat['is_active'], $id
                ]);
                $flash = 'Category saved.';
            }
        } catch (PDOException $e) {
            error_log('admin_category_edit save: ' . $e->getMessage());
            $errors[] = 'Could not save category.';
        }
    }
}

$page_title = $is_new ? 'New Category' : 'Edit Category';
require_once __DIR__ . '/includes/admin_header.php';
?>



        <div class="admin-hero">
            <div>
                <h1><?= $is_new ? 'New Category' : 'Edit Category' ?></h1>
                <p><?= $is_new ? 'Add a new category' : e($cat['name_en']) ?></p>
            </div>
            <a href="admin_categories.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-arrow-left"></i> <?= e(t('admin_back_to_list')) ?>
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= e($flash) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="flash flash-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" class="admin-panel">

            <div class="form-row">
                <div class="form-field">
                    <label for="slug"><?= e(t('admin_slug')) ?> *</label>
                    <input type="text" id="slug" name="slug" required value="<?= e($cat['slug']) ?>">
                </div>
                <div class="form-field">
                    <label for="icon"><?= e(t('admin_icon_class')) ?></label>
                    <input type="text" id="icon" name="icon" value="<?= e($cat['icon']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="name_en"><?= e(t('admin_name')) ?> — English *</label>
                    <input type="text" id="name_en" name="name_en" required value="<?= e($cat['name_en']) ?>">
                </div>
                <div class="form-field">
                    <label for="name_fi"><?= e(t('admin_name')) ?> — Finnish *</label>
                    <input type="text" id="name_fi" name="name_fi" required value="<?= e($cat['name_fi']) ?>">
                </div>
            </div>

            <div class="form-field">
                <label for="name_fa"><?= e(t('admin_name')) ?> — Farsi *</label>
                <input type="text" id="name_fa" name="name_fa" required dir="rtl" value="<?= e($cat['name_fa']) ?>">
            </div>

            <div class="form-field">
                <label for="description_en">Description — English</label>
                <textarea id="description_en" name="description_en" rows="2"><?= e($cat['description_en']) ?></textarea>
            </div>
            <div class="form-field">
                <label for="description_fi">Description — Finnish</label>
                <textarea id="description_fi" name="description_fi" rows="2"><?= e($cat['description_fi']) ?></textarea>
            </div>
            <div class="form-field">
                <label for="description_fa">Description — Farsi</label>
                <textarea id="description_fa" name="description_fa" rows="2" dir="rtl"><?= e($cat['description_fa']) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="sort_order"><?= e(t('admin_sort_order')) ?></label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= (int)$cat['sort_order'] ?>">
                </div>
                <div class="form-field" style="padding-top:32px;">
                    <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" <?= $cat['is_active'] ? 'checked' : '' ?> style="width:auto;">
                        <?= e(t('admin_active')) ?>
                    </label>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:24px;">
                <button type="submit" class="btn btn-accent btn-lg"><i class="fas fa-save"></i> <?= e(t('admin_save_category')) ?></button>
                <a href="admin_categories.php" class="btn btn-outline btn-lg">Cancel</a>
            </div>

        </form>

    

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
