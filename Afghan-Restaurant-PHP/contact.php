<?php
// contact.php — Contact form

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
$page_title = t('contact_title');
require_once __DIR__ . '/includes/header.php';

$flash_success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
?>

<section class="form-page">
    <div class="container">

        <div class="section-header">
            <h2><?= e(t('contact_title')) ?></h2>
            <p class="section-subtitle"><?= e(t('contact_subtitle')) ?></p>
        </div>

        <?php if ($flash_success): ?>
            <div class="flash flash-success" style="max-width:640px;margin:0 auto 24px;">
                <i class="fas fa-check-circle"></i> <?= e($flash_success) ?>
            </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="flash flash-error" style="max-width:640px;margin:0 auto 24px;">
                <i class="fas fa-exclamation-circle"></i> <?= e($flash_error) ?>
            </div>
        <?php endif; ?>

        <div class="form-layout" style="max-width:960px;margin:0 auto;">

            <div class="form-card">
                <form method="post" action="contact_process.php">

                    <div class="form-row">
                        <div class="form-field">
                            <label for="name"><?= e(t('contact_name')) ?> *</label>
                            <input type="text" id="name" name="name" required
                                   value="<?= e($_SESSION['customer_name'] ?? '') ?>">
                        </div>
                        <div class="form-field">
                            <label for="phone"><?= e(t('contact_phone')) ?></label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="email"><?= e(t('contact_email')) ?> *</label>
                        <input type="email" id="email" name="email" required
                               value="<?= e($_SESSION['customer_email'] ?? '') ?>">
                    </div>

                    <div class="form-field">
                        <label for="subject"><?= e(t('contact_subject')) ?></label>
                        <input type="text" id="subject" name="subject">
                    </div>

                    <div class="form-field">
                        <label for="message"><?= e(t('contact_message')) ?> *</label>
                        <textarea id="message" name="message" rows="6" required></textarea>
                    </div>

                    <!-- Honeypot (hidden from humans) -->
                    <input type="text" name="website" style="display:none !important;" tabindex="-1" autocomplete="off">

                    <button type="submit" class="btn btn-accent btn-lg" style="width:100%;">
                        <i class="fas fa-paper-plane"></i> <?= e(t('contact_send')) ?>
                    </button>

                </form>
            </div>

            <div class="cart-summary">
                <h3><?= e(t('footer_contact')) ?></h3>

                <div style="display:flex;flex-direction:column;gap:16px;font-size:0.9rem;">
                    <div>
                        <div style="color:var(--text-muted);font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">
                          <i class="fas fa-phone"></i> <?= e(t('footer_contact')) ?>
                        </div>
                        <a href="tel:<?= e(setting('site_phone')) ?>"><?= e(setting('site_phone')) ?></a>
                    </div>

                    <div>
                        <div style="color:var(--text-muted);font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">
                          <i class="fas fa-envelope"></i> <?= e(t('contact_email_label')) ?>
                        </div>
                        <a href="mailto:<?= e(setting('site_email')) ?>"><?= e(setting('site_email')) ?></a>
                    </div>

                    <?php if (!empty($current_branch)): ?>
                    <div>
                        <div style="color:var(--text-muted);font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">
                           <i class="fas fa-location-dot"></i> <?= e(t('contact_branch_label')) ?>
                        </div>
                        <div>
                            <?= e($current_branch['name']) ?><br>
                            <span class="text-muted" style="font-size:0.85rem;">
                                <?= e($current_branch['address'] ?? '') ?>
                                <?php if (!empty($current_branch['city'])): ?>
                                    <br><?= e($current_branch['city']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

                <h4 style="font-size:0.85rem;margin-bottom:10px;">
                    <?= e(t('footer_follow')) ?>
                </h4>
                <div class="footer-social-icons">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                </div>
            </div>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
