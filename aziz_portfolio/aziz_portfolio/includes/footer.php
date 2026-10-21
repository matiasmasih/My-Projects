<?php
// includes/footer.php — Site footer
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/i18n.php';

$year = date('Y');
?>

<footer class="site-footer">
    <div class="container">
        <div class="footer-inner">

            <div class="footer-brand">
                <div class="footer-logo"><?= htmlspecialchars(SITE_NAME) ?></div>
                  <p class="footer-tagline"><?= htmlspecialchars(t('footer_tagline')) ?></p>
            </div>

            <div class="footer-links">
              <h4 class="footer-col-title"><?= htmlspecialchars(t('footer_nav')) ?></h4>
              <a href="index.php#about"><?= htmlspecialchars(t('nav_about')) ?></a>
              <a href="index.php#projects"><?= htmlspecialchars(t('nav_projects')) ?></a>
              <a href="blog.php"><?= htmlspecialchars(t('nav_blog')) ?></a>
              <a href="index.php#contact"><?= htmlspecialchars(t('nav_contact')) ?></a>
           </div>

           <div class="footer-socials">
              <h4 class="footer-col-title"><?= htmlspecialchars(t('footer_connect')) ?></h4>
              <div class="footer-social-icons">
                  <a href="mailto:matiasmasih@gmail.com" title="Email"><i class="fas fa-envelope"></i></a>
                  <a href="tel:+358413114312" title="Phone"><i class="fas fa-phone"></i></a>
                  <a href="https://github.com/matiasmasih" target="_blank" rel="noopener" title="GitHub"><i class="fab fa-github"></i></a>
                  <a href="https://www.linkedin.com/" target="_blank" rel="noopener" title="LinkedIn"><i class="fas fa-linkedin"></i></a>
              </div>
           </div>

        </div>
    </div>

    <div class="footer-bottom">
        <div class="container">
            <p>© <?= $year ?> <?= htmlspecialchars(SITE_NAME) ?>. <?= htmlspecialchars(t('footer_rights')) ?></p>
        </div>
    </div>
</footer>

<!-- Visitor Preferences Panel -->
<div class="prefs-overlay" id="prefsPanel" data-prefs-panel>
    <div class="prefs-card" role="dialog" aria-modal="true" aria-labelledby="prefsTitle">
        <div class="prefs-head">
            <h3 id="prefsTitle">🎨 <?= htmlspecialchars(t('prefs_title')) ?></h3>
            <button type="button" class="prefs-close" data-prefs-close aria-label="Close">✕</button>
        </div>

        <div class="prefs-row">
            <label class="prefs-label"><?= htmlspecialchars(t('prefs_font')) ?></label>
            <div class="prefs-opts">
                <button type="button" data-pref-font="small"  onclick="prefsSetFont('small')"><?= htmlspecialchars(t('prefs_font_small')) ?></button>
                <button type="button" data-pref-font="normal" onclick="prefsSetFont('normal')"><?= htmlspecialchars(t('prefs_font_normal')) ?></button>
                <button type="button" data-pref-font="large"  onclick="prefsSetFont('large')"><?= htmlspecialchars(t('prefs_font_large')) ?></button>
            </div>
        </div>

        <div class="prefs-row">
            <label class="prefs-label"><?= htmlspecialchars(t('prefs_motion')) ?></label>
            <div class="prefs-opts">
                <button type="button" data-pref-motion="on"  onclick="prefsSetMotion('on')"><?= htmlspecialchars(t('prefs_on')) ?></button>
                <button type="button" data-pref-motion="off" onclick="prefsSetMotion('off')"><?= htmlspecialchars(t('prefs_off')) ?></button>
            </div>
        </div>

        <p class="prefs-note"><?= htmlspecialchars(t('prefs_note')) ?></p>
    </div>
</div>

</body>
</html>
