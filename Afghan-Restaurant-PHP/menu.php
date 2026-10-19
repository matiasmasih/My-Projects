<?php
// menu.php — Full menu page with category filter

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
$page_title = t('nav_menu');
require_once __DIR__ . '/includes/header.php';

$lang = current_language();

// Helper: pick translated field with English fallback
function pick_lang(array $row, string $field, string $lang): string {
    $key = $field . '_' . $lang;
    if (!empty($row[$key])) return $row[$key];
    return $row[$field . '_en'] ?? '';
}

// ---------- Active category filter ----------
$active_slug   = $_GET['category'] ?? '';
$allowed_slugs = [];

// ---------- Load categories ----------
try {
    $categories = $pdo->query("
        SELECT id, slug, name_en, name_fi, name_fa, sort_order
        FROM categories
        WHERE is_active = 1
        ORDER BY sort_order
    ")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

foreach ($categories as $c) {
    $allowed_slugs[$c['slug']] = true;
}

if ($active_slug && !isset($allowed_slugs[$active_slug])) {
    $active_slug = '';
}

// ---------- Load menu items ----------
$where  = "WHERE m.is_available = 1";
$params = [];

if ($active_slug) {
    $where .= " AND c.slug = :cat";
    $params[':cat'] = $active_slug;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            m.id, m.slug,
            m.name_en, m.name_fi, m.name_fa,
            m.description_en, m.description_fi, m.description_fa,
            m.base_price_cents, m.image,
            m.allergens, m.spice_level, m.portion_size,
            c.slug AS cat_slug,
            c.name_en AS cat_name_en, c.name_fi AS cat_name_fi, c.name_fa AS cat_name_fa
        FROM menu_items m
        JOIN categories c ON c.id = m.category_id
        $where
        ORDER BY c.sort_order, m.sort_order, m.id
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    $items = [];
}

// ---------- Group items by category ----------
$items_by_cat = [];
foreach ($items as $row) {
    $items_by_cat[$row['cat_slug']][] = $row;
}
?>

<section class="menu-page">
    <div class="container">

        <div class="section-header">
            <h2><?= e(t('menu_title')) ?></h2>
            <p class="section-subtitle"><?= e(t('menu_subtitle')) ?></p>
        </div>

        <!-- Category tabs -->
        <?php if (!empty($categories)): ?>
            <div class="category-tabs">
                <a href="menu.php"
                   class="category-tab <?= $active_slug === '' ? 'active' : '' ?>">
                    <?= e(t('menu_all')) ?>
                </a>
                <?php foreach ($categories as $cat): ?>
                    <?php $cat_name = pick_lang($cat, 'name', $lang); ?>
                    <a href="menu.php?category=<?= e($cat['slug']) ?>"
                       class="category-tab <?= $active_slug === $cat['slug'] ? 'active' : '' ?>">
                        <?= e($cat_name) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Items -->
        <?php if (empty($items)): ?>

            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <p><?= e(t('menu_empty')) ?></p>
            </div>

        <?php else: ?>

            <?php foreach ($items_by_cat as $cat_slug => $cat_items): ?>
                <?php
                    $first = $cat_items[0];
                    $cat_name = pick_lang($first, 'cat_name', $lang);
                ?>

                <div class="mb-4">
                    <h3 style="margin-bottom:24px; padding-bottom:12px; border-bottom:2px solid var(--border);">
                        <?= e($cat_name) ?>
                    </h3>

                    <div class="menu-grid">
                        <?php foreach ($cat_items as $item): ?>
                            <?php
                                $name  = pick_lang($item, 'name', $lang);
                                $desc  = pick_lang($item, 'description', $lang);
                                $price = (int)$item['base_price_cents'];
                                $img   = $item['image'];
                                $img_abs = __DIR__ . '/uploads/menu/' . $img;
                            ?>
                            <div class="menu-card">
                                <div class="menu-card-image">
                                    <?php if ($img && file_exists($img_abs)): ?>
                                        <img src="uploads/menu/<?= e(rawurlencode($img)) ?>" alt="<?= e($name) ?>">
                                    <?php else: ?>
                                        <div class="menu-card-placeholder">
                                            <i class="fas fa-utensils"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="menu-card-body">
                                    <span class="menu-card-category"><?= e($cat_name) ?></span>
                                    <h3 class="menu-card-title"><?= e($name) ?></h3>
                                    <p class="menu-card-desc"><?= e($desc) ?></p>

                                    <!-- Item details -->
                                    <div class="menu-card-meta" style="font-size:0.78rem; color:var(--text-muted); margin-bottom:10px;">
                                        <?php if ($item['spice_level'] !== 'none'): ?>
                                            <span style="margin-right:10px;">
                                                🌶️ <?= e(t('item_spice_' . $item['spice_level'])) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($item['allergens']): ?>
                                         <span>
                                            ⚠️ <?= e(implode(', ', array_map(function($a) {
                                               return t('allergen_' . trim($a), trim($a));
                                            }, explode(',', $item['allergens'])))) ?>
                                        </span>
                                      <?php endif; ?>
                                    </div>

                                    <div class="menu-card-footer">
                                        <div class="menu-card-price">
                                            <?= e(format_price($price, $current_currency)) ?>
                                        </div>
                                        <button class="menu-card-add"
                                                type="button"
                                                data-add-to-cart
                                                data-id="<?= (int)$item['id'] ?>"
                                                data-name="<?= e($name) ?>"
                                                data-price="<?= (int)$price ?>"
                                                data-image="<?= e($img) ?>">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
