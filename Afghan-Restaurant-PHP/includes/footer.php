<?php
// includes/footer.php — Site footer
// Requires config.php and i18n.php (already loaded via header.php)
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-inner">

            <!-- Column 1: Brand -->
            <div>
                <div class="footer-logo">
                    <i class="fas fa-utensils"></i>
                    <span><?= e(setting('site_name', SITE_NAME)) ?></span>
                </div>
                  <p class="footer-tagline">
                      <?= e(t('footer_tagline', setting('site_tagline', ''))) ?>
                 </p>
            </div>

            <!-- Column 2: Quick links -->
            <div>
                <h4 class="footer-col-title"><?= e(t('footer_links')) ?></h4>
                <div class="footer-links">
                    <a href="<?= e(SITE_URL) ?>index.php"><?= e(t('nav_home')) ?></a>
                    <a href="<?= e(SITE_URL) ?>menu.php"><?= e(t('nav_menu')) ?></a>
                    <a href="<?= e(SITE_URL) ?>reserve.php"><?= e(t('nav_reservations')) ?></a>
                    <a href="<?= e(SITE_URL) ?>about.php"><?= e(t('nav_about')) ?></a>
                    <a href="<?= e(SITE_URL) ?>contact.php"><?= e(t('nav_contact')) ?></a>
                </div>
            </div>

            <!-- Column 3: Contact -->
            <div>
                <h4 class="footer-col-title"><?= e(t('footer_contact')) ?></h4>
                <div class="footer-links">
                    <a href="tel:<?= e(setting('site_phone')) ?>">
                        <i class="fas fa-phone"></i> <?= e(setting('site_phone')) ?>
                    </a>
                    <a href="mailto:<?= e(setting('site_email')) ?>">
                        <i class="fas fa-envelope"></i> <?= e(setting('site_email')) ?>
                    </a>
                    <?php if ($current_branch ?? null): ?>
                        <span class="text-muted" style="font-size:0.88rem;">
                            <i class="fas fa-location-dot"></i>
                            <?= e($current_branch['city'] ?? '') ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h4 class="footer-col-title" style="margin-top:24px;"><?= e(t('footer_follow')) ?></h4>
                <div class="footer-social-icons">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>

        </div>
    </div>

    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= e(setting('site_name', SITE_NAME)) ?>. <?= e(t('footer_rights')) ?></p>
        </div>
    </div>
</footer>

<script src="<?= e(ASSETS_URL) ?>app.js"></script>
</body>
</html>
