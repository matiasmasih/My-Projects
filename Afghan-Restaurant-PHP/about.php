<?php
// about.php — About page (fully translated)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
$page_title = t('about_title');
require_once __DIR__ . '/includes/header.php';

$lang = current_language();
?>

<section class="menu-page">
    <div class="container">

        <div class="section-header">
            <h2><?= e(t('about_title')) ?></h2>
            <p class="section-subtitle"><?= e(t('about_subtitle')) ?></p>
        </div>

        <!-- Hero text -->
        <div style="max-width:720px;margin:0 auto 60px;text-align:center;">
            <p style="font-size:1.15rem;line-height:1.9;color:var(--text);">
                <?= e(t('about_text')) ?>
            </p>
        </div>

        <!-- Two column: story + values -->
        <div class="grid-2" style="max-width:1000px;margin:0 auto 60px;align-items:center;gap:60px;">

            <div>
                <span class="badge badge-accent" style="margin-bottom:12px;"><?= e(t('about_badge_story')) ?></span>
                <h3 style="margin-bottom:16px;"><?= e(t('about_story_head')) ?></h3>
                <p style="font-size:0.98rem;line-height:1.8;color:var(--text-muted);margin-bottom:14px;">
                    <?= e(t('about_story_p1')) ?>
                </p>
                <p style="font-size:0.98rem;line-height:1.8;color:var(--text-muted);">
                    <?= e(t('about_story_p2')) ?>
                </p>
            </div>

            <div class="card" style="text-align:center;">
                <div style="font-size:3.5rem;color:var(--accent);margin-bottom:16px;">
                    <i class="fas fa-utensils"></i>
                </div>
                <h3 style="margin-bottom:18px;"><?= e(setting('site_name', SITE_NAME)) ?></h3>
                <ul style="list-style:none;text-align:left;padding:0;margin:0;font-size:0.95rem;line-height:2.2;">
                    <li>✅ <?= e(t('footer_tagline')) ?></li>
                    <li>✅ <?= e(t('menu_subtitle')) ?></li>
                    <li>✅ <?= e(t('about_fact_branches')) ?></li>
                    <li>✅ <?= e(t('about_fact_langs')) ?></li>
                </ul>
            </div>

        </div>

        <!-- Three feature cards -->
        <div class="grid-3" style="max-width:1000px;margin:0 auto;">

            <div class="card" style="text-align:center;">
                <div style="font-size:2.5rem;color:var(--accent);margin-bottom:14px;">
                    <i class="fas fa-leaf"></i>
                </div>
                <h4 style="margin-bottom:10px;"><?= e(t('about_fresh_title')) ?></h4>
                <p style="font-size:0.9rem;color:var(--text-muted);line-height:1.7;">
                    <?= e(t('about_fresh_text')) ?>
                </p>
            </div>

            <div class="card" style="text-align:center;">
                <div style="font-size:2.5rem;color:var(--accent);margin-bottom:14px;">
                    <i class="fas fa-fire"></i>
                </div>
                <h4 style="margin-bottom:10px;"><?= e(t('about_trad_title')) ?></h4>
                <p style="font-size:0.9rem;color:var(--text-muted);line-height:1.7;">
                    <?= e(t('about_trad_text')) ?>
                </p>
            </div>

            <div class="card" style="text-align:center;">
                <div style="font-size:2.5rem;color:var(--accent);margin-bottom:14px;">
                    <i class="fas fa-heart"></i>
                </div>
                <h4 style="margin-bottom:10px;"><?= e(t('about_love_title')) ?></h4>
                <p style="font-size:0.9rem;color:var(--text-muted);line-height:1.7;">
                    <?= e(t('about_love_text')) ?>
                </p>
            </div>

        </div>

        <!-- CTA -->
        <div class="text-center" style="margin-top:60px;">
            <a href="menu.php" class="btn btn-accent btn-lg">
                <i class="fas fa-book-open"></i> <?= e(t('hero_view_menu')) ?>
            </a>
            <a href="reserve.php" class="btn btn-outline btn-lg" style="margin-left:12px;">
                <i class="fas fa-calendar-check"></i> <?= e(t('hero_reserve')) ?>
            </a>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
