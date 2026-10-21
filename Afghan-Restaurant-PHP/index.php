<?php
// index.php — Homepage

$page_title = 'Home';
require_once __DIR__ . '/includes/header.php';

$lang = current_language();

// DEBUG — remove after fixing
echo "<!-- DEBUG: current_currency=" . ($current_currency ?? 'NULL') . " symbol=" . ($currency_symbol ?? 'NULL') . " -->\n";

// Helper: pick translated field with English fallback
function pick_lang(array $row, string $field, string $lang): string {
    $key = $field . '_' . $lang;
    if (!empty($row[$key])) return $row[$key];
    return $row[$field . '_en'] ?? '';
}

// ---------- Featured items from DB ----------
try {
    $featured = $pdo->query("
        SELECT m.id, m.slug, m.name_en, m.name_fi, m.name_fa,
               m.description_en, m.description_fi, m.description_fa,
               m.base_price_cents, m.image, m.is_featured,
               c.name_en AS cat_name_en, c.name_fi AS cat_name_fi, c.name_fa AS cat_name_fa
        FROM menu_items m
        JOIN categories c ON c.id = m.category_id
        WHERE m.is_available = 1 AND m.is_featured = 1
        ORDER BY m.sort_order
        LIMIT 6
    ")->fetchAll();
} catch (PDOException $e) {
    $featured = [];
}

// Fallback: if no featured items, show any 6
if (empty($featured)) {
    try {
        $featured = $pdo->query("
            SELECT m.id, m.slug, m.name_en, m.name_fi, m.name_fa,
                   m.description_en, m.description_fi, m.description_fa,
                   m.base_price_cents, m.image, m.is_featured,
                   c.name_en AS cat_name_en, c.name_fi AS cat_name_fi, c.name_fa AS cat_name_fa
            FROM menu_items m
            JOIN categories c ON c.id = m.category_id
            WHERE m.is_available = 1
            ORDER BY m.sort_order
            LIMIT 6
        ")->fetchAll();
    } catch (PDOException $e) {
        $featured = [];
    }
}
?>

<!-- HERO -->
<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="hero-greeting"><?= e(t('hero_greeting')) ?></span>
            <h1 class="hero-title">
                <?= e(t('hero_title_1')) ?><br>
                <span><?= e(t('hero_title_2')) ?></span>
            </h1>
            <p class="hero-text"><?= e(t('hero_text')) ?></p>
            <div class="hero-actions">
                <a href="menu.php" class="btn btn-accent btn-lg">
                    <i class="fas fa-book-open"></i> <?= e(t('hero_view_menu')) ?>
                </a>
                <a href="reserve.php" class="btn btn-outline btn-lg">
                    <i class="fas fa-calendar-check"></i> <?= e(t('hero_reserve')) ?>
                </a>
            </div>
        </div>
        <div class="hero-visual">
            <div class="hero-card">
                <?php
                    $hero_img = __DIR__ . '/uploads/menu/kabuli-pulao.jpg';
                    if (!file_exists($hero_img)) $hero_img = __DIR__ . '/uploads/menu/pizza.jpg';
                ?>
                <?php if (file_exists($hero_img)): ?>
                    <img src="uploads/menu/<?= e(basename($hero_img)) ?>" alt="<?= e(setting('site_name')) ?>">
                <?php else: ?>
                    <div class="hero-card-placeholder"><i class="fas fa-utensils"></i></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- FEATURED MENU -->
<section class="menu-page">
    <div class="container">
        <div class="section-header">
            <h2><?= e(t('menu_title')) ?></h2>
            <p class="section-subtitle"><?= e(t('menu_subtitle')) ?></p>
        </div>

        <?php if (empty($featured)): ?>

            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <p><?= e(t('menu_empty')) ?></p>
            </div>

        <?php else: ?>

            <div class="menu-grid">
                <?php foreach ($featured as $item): ?>
                    <?php
                        $name  = pick_lang($item, 'name', $lang);
                        $desc  = pick_lang($item, 'description', $lang);
                        $cat   = pick_lang($item, 'cat_name', $lang);
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
                            <span class="menu-card-category"><?= e($cat) ?></span>
                            <h3 class="menu-card-title"><?= e($name) ?></h3>
                            <p class="menu-card-desc"><?= e($desc) ?></p>
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

            <div class="text-center mt-4">
                <a href="menu.php" class="btn btn-primary btn-lg">
                    <?= e(t('hero_view_menu')) ?>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>

        <?php endif; ?>
    </div>
</section>

<!-- ABOUT -->
<section style="padding:80px 0; background:var(--bg-alt);">
    <div class="container">
        <div class="section-header">
            <h2><?= e(t('about_title')) ?></h2>
            <p class="section-subtitle"><?= e(t('about_subtitle')) ?></p>
        </div>

        <div class="grid-2" style="max-width:900px; margin:0 auto; align-items:center;">
            <div>
                <p style="font-size:1.05rem; line-height:1.75; margin-bottom:16px;">
                    <?= e(t('about_text')) ?>
                </p>
                <p style="font-size:0.98rem; line-height:1.7; color:var(--text-muted);">
                    <?= e(setting('site_tagline', '')) ?>
                </p>
            </div>
            <div class="card" style="text-align:center;">
                <div style="font-size:3rem; color:var(--accent); margin-bottom:12px;">
                    <i class="fas fa-utensils"></i>
                </div>
                <h3 style="margin-bottom:14px;"><?= e(setting('site_name')) ?></h3>
                <ul style="list-style:none; text-align:left; padding:0; margin:0; font-size:0.92rem; line-height:2;">
                    <li>✅ <?= e(t('menu_subtitle')) ?></li>
                    <li>✅ <?= e(t('footer_tagline')) ?></li>
                    <li>✅ <?= e(t('hero_greeting')) ?></li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
