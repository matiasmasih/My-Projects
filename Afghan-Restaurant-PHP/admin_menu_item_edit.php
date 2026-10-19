<?php
// admin_menu_item_edit.php — Add or edit a menu item

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

$lang = current_language();

function pick_lang(array $row, string $field, string $lang): string {
    $key = $field . '_' . $lang;
    if (!empty($row[$key])) return $row[$key];
    return $row[$field . '_en'] ?? '';
}

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$is_new = ($id === 0);

$flash = '';
$errors = [];

// Defaults
$item = [
    'category_id'     => 1,
    'slug'            => '',
    'name_en'         => '',
    'name_fi'         => '',
    'name_fa'         => '',
    'description_en'  => '',
    'description_fi'  => '',
    'description_fa'  => '',
    'base_price_cents'=> 0,
    'image'           => '',
    'allergens'       => '',
    'spice_level'     => 'none',
    'portion_size'    => 'regular',
    'is_available'    => 1,
    'is_featured'     => 0,
    'sort_order'      => 0,
];

// Load categories
$categories = [];
try {
    $categories = $pdo->query("SELECT id, name_en, name_fi, name_fa FROM categories ORDER BY sort_order")->fetchAll();
} catch (PDOException $e) {}

// Load existing item
if (!$is_new) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $item = $row;
        } else {
            header('Location: ' . SITE_URL . 'admin_menu_items.php');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: ' . SITE_URL . 'admin_menu_items.php');
        exit;
    }
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gather
    $item['category_id']      = (int)($_POST['category_id'] ?? 0);
    $item['slug']             = trim($_POST['slug'] ?? '');
    $item['name_en']          = trim($_POST['name_en'] ?? '');
    $item['name_fi']          = trim($_POST['name_fi'] ?? '');
    $item['name_fa']          = trim($_POST['name_fa'] ?? '');
    $item['description_en']   = trim($_POST['description_en'] ?? '');
    $item['description_fi']   = trim($_POST['description_fi'] ?? '');
    $item['description_fa']   = trim($_POST['description_fa'] ?? '');
    $item['base_price_cents'] = (int)round(((float)($_POST['base_price'] ?? 0)) * 100);
    $item['image']            = trim($_POST['image'] ?? '');
    $item['allergens']        = trim($_POST['allergens'] ?? '');
    $item['spice_level']      = $_POST['spice_level'] ?? 'none';
    $item['portion_size']     = $_POST['portion_size'] ?? 'regular';
    $item['is_available']     = isset($_POST['is_available']) ? 1 : 0;
    $item['is_featured']      = isset($_POST['is_featured']) ? 1 : 0;
    $item['sort_order']       = (int)($_POST['sort_order'] ?? 0);

    // Validate
    if ($item['slug'] === '')     $errors[] = 'Slug is required.';
    if ($item['name_en'] === '')  $errors[] = 'English name is required.';
    if ($item['name_fi'] === '')  $errors[] = 'Finnish name is required.';
    if ($item['name_fa'] === '')  $errors[] = 'Farsi name is required.';
    if ($item['base_price_cents'] <= 0) $errors[] = 'Price must be greater than 0.';
    if (!in_array($item['spice_level'], ['none','mild','medium','hot'])) $item['spice_level'] = 'none';
    if (!in_array($item['portion_size'], ['small','regular','large']))   $item['portion_size'] = 'regular';

    // Slug uniqueness
    if (empty($errors)) {
        try {
            $sql = "SELECT id FROM menu_items WHERE slug = ?";
            $params = [$item['slug']];
            if (!$is_new) {
                $sql .= " AND id != ?";
                $params[] = $id;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) $errors[] = 'Slug already exists.';
        } catch (PDOException $e) {}
    }

    // Save
    if (empty($errors)) {
        try {
            if ($is_new) {
                $stmt = $pdo->prepare("
                    INSERT INTO menu_items
                    (category_id, slug, name_en, name_fi, name_fa,
                     description_en, description_fi, description_fa,
                     base_price_cents, image, allergens, spice_level, portion_size,
                     is_available, is_featured, sort_order)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([
                    $item['category_id'], $item['slug'],
                    $item['name_en'], $item['name_fi'], $item['name_fa'],
                    $item['description_en'], $item['description_fi'], $item['description_fa'],
                    $item['base_price_cents'], $item['image'], $item['allergens'],
                    $item['spice_level'], $item['portion_size'],
                    $item['is_available'], $item['is_featured'], $item['sort_order'],
                ]);
                $new_id = (int)$pdo->lastInsertId();
                header('Location: ' . SITE_URL . 'admin_menu_items.php');
                exit;
            } else {
                $stmt = $pdo->prepare("
                    UPDATE menu_items SET
                        category_id = ?, slug = ?,
                        name_en = ?, name_fi = ?, name_fa = ?,
                        description_en = ?, description_fi = ?, description_fa = ?,
                        base_price_cents = ?, image = ?, allergens = ?,
                        spice_level = ?, portion_size = ?,
                        is_available = ?, is_featured = ?, sort_order = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $item['category_id'], $item['slug'],
                    $item['name_en'], $item['name_fi'], $item['name_fa'],
                    $item['description_en'], $item['description_fi'], $item['description_fa'],
                    $item['base_price_cents'], $item['image'], $item['allergens'],
                    $item['spice_level'], $item['portion_size'],
                    $item['is_available'], $item['is_featured'], $item['sort_order'],
                    $id
                ]);
                $flash = 'Item saved.';
            }
        } catch (PDOException $e) {
            error_log('admin_menu_item_edit save: ' . $e->getMessage());
            $errors[] = 'Could not save item: ' . $e->getMessage();
        }
    }
}

$page_title = $is_new ? 'New Menu Item' : 'Edit Menu Item';
require_once __DIR__ . '/includes/admin_header.php';
?>



        <div class="admin-hero">
            <div>
                <h1><?= $is_new ? 'New Menu Item' : 'Edit Menu Item' ?></h1>
                <p><?= $is_new ? 'Add a new dish to your menu' : e(pick_lang($item, 'name', $lang)) ?></p>
            </div>
            <a href="admin_menu_items.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-arrow-left"></i> <?= e(t('admin_back_to_list')) ?>
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="flash flash-success">
                <i class="fas fa-check-circle"></i> <?= e($flash) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="flash flash-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <?php foreach ($errors as $err): ?>
                        <div><?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" class="admin-panel">

            <h3><i class="fas fa-info-circle"></i> Basic info</h3>

            <div class="form-row">
                <div class="form-field">
                    <label for="category_id"><?= e(t('admin_category')) ?> *</label>
                    <select id="category_id" name="category_id" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $c['id'] == $item['category_id'] ? 'selected' : '' ?>>
                                <?= e(pick_lang($c, 'name', $lang)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="slug"><?= e(t('admin_slug')) ?> *</label>
                    <input type="text" id="slug" name="slug" required
                           value="<?= e($item['slug']) ?>">
                </div>
            </div>

            <div class="form-field">
                <label for="base_price"><?= e(t('admin_base_price')) ?> *</label>
                <input type="number" id="base_price" name="base_price" step="0.01" min="0.01" required
                       value="<?= e(number_format($item['base_price_cents'] / 100, 2, '.', '')) ?>">
            </div>

            <h3 style="margin:24px 0 20px;"><i class="fas fa-language"></i> Names (3 languages)</h3>

            <div class="form-row">
                <div class="form-field">
                    <label for="name_en">English *</label>
                    <input type="text" id="name_en" name="name_en" required value="<?= e($item['name_en']) ?>">
                </div>
                <div class="form-field">
                    <label for="name_fi">Finnish *</label>
                    <input type="text" id="name_fi" name="name_fi" required value="<?= e($item['name_fi']) ?>">
                </div>
            </div>

            <div class="form-field">
                <label for="name_fa">Farsi *</label>
                <input type="text" id="name_fa" name="name_fa" required value="<?= e($item['name_fa']) ?>" dir="rtl">
            </div>

            <h3 style="margin:24px 0 20px;"><i class="fas fa-align-left"></i> Descriptions (3 languages)</h3>

            <div class="form-row">
                <div class="form-field">
                    <label for="description_en">English</label>
                    <textarea id="description_en" name="description_en" rows="3"><?= e($item['description_en']) ?></textarea>
                </div>
                <div class="form-field">
                    <label for="description_fi">Finnish</label>
                    <textarea id="description_fi" name="description_fi" rows="3"><?= e($item['description_fi']) ?></textarea>
                </div>
            </div>

            <div class="form-field">
                <label for="description_fa">Farsi</label>
                <textarea id="description_fa" name="description_fa" rows="3" dir="rtl"><?= e($item['description_fa']) ?></textarea>
            </div>

            <h3 style="margin:24px 0 20px;"><i class="fas fa-image"></i> Media & tags</h3>

            <div class="form-row">
                <div class="form-field">
                    <label for="image"><?= e(t('admin_image_filename')) ?></label>
                    <input type="text" id="image" name="image" value="<?= e($item['image']) ?>">
                </div>
                <div class="form-field">
                    <label for="allergens"><?= e(t('admin_allergens')) ?></label>
                    <input type="text" id="allergens" name="allergens" value="<?= e($item['allergens']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="spice_level"><?= e(t('admin_spice_level')) ?></label>
                    <select id="spice_level" name="spice_level">
                        <?php foreach (['none','mild','medium','hot'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= $item['spice_level'] === $s ? 'selected' : '' ?>>
                                <?= e(ucfirst($s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="portion_size"><?= e(t('admin_portion_size')) ?></label>
                    <select id="portion_size" name="portion_size">
                        <?php foreach (['small','regular','large'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= $item['portion_size'] === $s ? 'selected' : '' ?>>
                                <?= e(ucfirst($s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="sort_order"><?= e(t('admin_sort_order')) ?></label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= (int)$item['sort_order'] ?>">
                </div>
                <div class="form-field" style="padding-top:32px;">
                    <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                        <input type="checkbox" name="is_available" value="1" <?= $item['is_available'] ? 'checked' : '' ?> style="width:auto;">
                        <?= e(t('admin_available_menu')) ?>
                    </label>
                    <label style="display:flex;gap:8px;align-items:center;cursor:pointer;margin-top:8px;">
                        <input type="checkbox" name="is_featured" value="1" <?= $item['is_featured'] ? 'checked' : '' ?> style="width:auto;">
                        <?= e(t('admin_featured_home')) ?>
                    </label>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:24px;">
                <button type="submit" class="btn btn-accent btn-lg">
                    <i class="fas fa-save"></i> <?= e(t('admin_save_item')) ?>
                </button>
                <a href="admin_menu_items.php" class="btn btn-outline btn-lg">
                    Cancel
                </a>
            </div>

        </form>

    

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
