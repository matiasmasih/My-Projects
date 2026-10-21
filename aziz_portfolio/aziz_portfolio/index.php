<?php
// index.php — Portfolio homepage (rebuilding section by section)

require_once __DIR__ . '/includes/header.php';

// Load personal info from database
$info_sql    = "SELECT * FROM personal_info LIMIT 1";
$info_result = $conn->query($info_sql);
$info        = $info_result->fetch_assoc();

// Load CV path (for the CV download button)
$cv_sql    = "SELECT file_path FROM resume_files WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
$cv_result = $conn->query($cv_sql);
$cv        = $cv_result->fetch_assoc();
$cv_path   = $cv ? $cv['file_path'] : '#';
?>

<!-- ============================================================
     HERO SECTION
     ============================================================ -->
<section class="hero">
    <div class="container">
        <div class="hero-grid">

            <div class="hero-text">
                <span class="hero-greeting"><?= htmlspecialchars(t('hero_greeting')) ?></span>
                <h1 class="hero-name"><?= htmlspecialchars($info['full_name'] ?? 'Aziz Rahman Noyan') ?></h1>
                <p class="hero-role">
                     <?= htmlspecialchars(t('hero_role')) ?>
                </p>
                <p class="hero-intro">
                   <?= htmlspecialchars(mb_substr(t('about_bio'), 0, 200)) ?>
                </p>

                <div class="hero-actions">
                    <a href="#about" class="btn btn-accent">
                        <?= htmlspecialchars(t('hero_view_more')) ?>
                    </a>
                    <a href="<?= htmlspecialchars($cv_path) ?>" class="btn btn-outline" download>
                        <?= htmlspecialchars(t('about_download_cv')) ?>
                    </a>
                </div>

                <div class="hero-stats">
                    <div class="hero-stat">
                        <div class="hero-stat-value">3+</div>
                        <div class="hero-stat-label"><?= htmlspecialchars(t('hero_years')) ?></div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-value">5+</div>
                        <div class="hero-stat-label"><?= htmlspecialchars(t('hero_projects')) ?></div>
                    </div>
                </div>
            </div>

            <div class="hero-visual">
                <div class="hero-card" onclick="this.classList.toggle('flipped')">
                    <div class="hero-card-inner">

                        <!-- FRONT: Photo + name -->
                        <div class="hero-card-face hero-card-front">
                            <?php if (!empty($info['profile_image']) && file_exists(__DIR__ . '/' . $info['profile_image'])): ?>
                                <img src="<?= htmlspecialchars($info['profile_image']) ?>" alt="<?= htmlspecialchars($info['full_name']) ?>" class="hero-photo">
                            <?php else: ?>
                                <div class="hero-avatar"><?= strtoupper(mb_substr($info['full_name'] ?? 'AN', 0, 1)) ?></div>
                            <?php endif; ?>
                            <div class="hero-card-name"><?= htmlspecialchars($info['full_name'] ?? 'Aziz Rahman Noyan') ?></div>
                            <div class="hero-card-role"><?= htmlspecialchars(t('hero_role')) ?></div>
                            <div class="hero-card-hint"><?= htmlspecialchars(t('hero_flip_hint')) ?></div>
                        </div>

                        <!-- BACK: QR code -->
                        <div class="hero-card-face hero-card-back">
                            <div class="hero-qr-title"><?= htmlspecialchars(t('hero_qr_title')) ?></div>
                            <div class="hero-qr-box" id="heroQrBox"></div>
                            <div class="hero-qr-hint"><?= htmlspecialchars(t('hero_qr_hint')) ?></div>
                        </div>

                    </div>
                </div>
        </div>
    </div>
</section>

<!-- ============================================================
     ABOUT SECTION
     ============================================================ -->
<section class="about" id="about">
    <div class="container">
        <div class="section-header">
            <h2><?= htmlspecialchars(t('about_title')) ?></h2>
        </div>

        <div class="about-grid">
            <div class="about-content">
                <p class="about-lead">
                     <?= htmlspecialchars(t('about_bio')) ?>
                </p>

                <div class="about-meta">
                    <?php if (!empty($info['phone'])): ?>
                        <div class="about-meta-item">
                            <i class="fas fa-phone"></i>
                            <span><?= htmlspecialchars($info['phone']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($info['email'])): ?>
                        <div class="about-meta-item">
                            <i class="fas fa-envelope"></i>
                            <span><?= htmlspecialchars($info['email']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($info['city'])): ?>
                        <div class="about-meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= htmlspecialchars($info['city']) ?><?= !empty($info['country']) ? ', ' . htmlspecialchars($info['country']) : '' ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($info['birthday'])): ?>
                        <div class="about-meta-item">
                            <i class="fas fa-birthday-cake"></i>
                            <span><?= htmlspecialchars($info['birthday']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <a href="<?= htmlspecialchars($cv_path) ?>" class="btn btn-accent" download>
                    <i class="fas fa-download"></i>
                    <?= htmlspecialchars(t('about_download_cv')) ?>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     EDUCATION SECTION
     ============================================================ -->
<section class="education" id="education">
    <div class="container">
        <div class="section-header">
            <h2><?= htmlspecialchars(t('education_title')) ?></h2>
        </div>

        <?php
            $edu_sql    = "SELECT * FROM education ORDER BY display_order, id";
            $edu_result = $conn->query($edu_sql);
        ?>

        <?php if ($edu_result && $edu_result->num_rows > 0): ?>
            <div class="timeline">
                <?php while ($edu = $edu_result->fetch_assoc()): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon">
                            <i class="fas <?= htmlspecialchars($edu['icon'] ?: 'fa-graduation-cap') ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-period"><?= htmlspecialchars($edu['period'] ?? '') ?></div>
                            <?php
                                $edu_title_key = 'edu_' . (int)$edu['id'] . '_title';
                                $edu_inst_key  = 'edu_' . (int)$edu['id'] . '_institution';
                                $edu_title     = t($edu_title_key);
                                $edu_inst      = t($edu_inst_key);
                                if ($edu_title === $edu_title_key) $edu_title = $edu['title'];
                                if ($edu_inst  === $edu_inst_key)  $edu_inst  = $edu['institution'] ?? '';
                            ?>
                            <h3 class="timeline-title"><?= htmlspecialchars($edu_title) ?></h3>
                            <?php if (!empty($edu_inst)): ?>
                                <p class="timeline-subtitle"><?= htmlspecialchars($edu_inst) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p style="text-align:center;color:var(--text-muted);">No education entries yet.</p>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================================
     EXPERIENCE SECTION
     ============================================================ -->
<section class="experience" id="experience">
    <div class="container">
        <div class="section-header">
            <h2><?= htmlspecialchars(t('experience_title')) ?></h2>
        </div>

        <?php
            $exp_sql    = "SELECT * FROM experience ORDER BY display_order, id DESC";
            $exp_result = $conn->query($exp_sql);
        ?>

        <?php if ($exp_result && $exp_result->num_rows > 0): ?>
            <div class="timeline">
                <?php while ($exp = $exp_result->fetch_assoc()): ?>
                    <?php
                        $exp_title_key = 'exp_' . (int)$exp['id'] . '_title';
                        $exp_comp_key  = 'exp_' . (int)$exp['id'] . '_company';
                        $exp_desc_key  = 'exp_' . (int)$exp['id'] . '_description';
                        $exp_title = t($exp_title_key);
                        $exp_comp  = t($exp_comp_key);
                        $exp_desc  = t($exp_desc_key);
                        if ($exp_title === $exp_title_key) $exp_title = $exp['title'];
                        if ($exp_comp  === $exp_comp_key)  $exp_comp  = $exp['company'] ?? '';
                        if ($exp_desc  === $exp_desc_key)  $exp_desc  = $exp['description'] ?? '';
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-icon">
                            <i class="fas <?= htmlspecialchars($exp['icon'] ?: 'fa-laptop-code') ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-period"><?= htmlspecialchars($exp['period'] ?? '') ?></div>
                            <h3 class="timeline-title"><?= htmlspecialchars($exp_title) ?></h3>
                            <?php if (!empty($exp_comp)): ?>
                                <p class="timeline-subtitle"><?= htmlspecialchars($exp_comp) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($exp_desc)): ?>
                                <p class="timeline-description"><?= nl2br(htmlspecialchars($exp_desc)) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p style="text-align:center;color:var(--text-muted);">No experience entries yet.</p>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================================
     SKILLS SECTION
     ============================================================ -->
<section class="skills" id="skills">
    <div class="container">
        <div class="section-header">
            <h2><?= htmlspecialchars(t('skills_title')) ?></h2>
        </div>

        <?php
            $skill_sql    = "SELECT * FROM skills ORDER BY category, display_order, id";
            $skill_result = $conn->query($skill_sql);

            // Group skills by category
            $skills_by_cat = [];
            if ($skill_result) {
                while ($row = $skill_result->fetch_assoc()) {
                    $skills_by_cat[$row['category'] ?: 'other'][] = $row;
                }
            }
        ?>

        <?php if (!empty($skills_by_cat)): ?>
            <div class="skills-grid">
                <?php foreach ($skills_by_cat as $cat => $items): ?>
                    <div class="skill-group">
                        <h3 class="skill-group-title">
                            <?php
                                $cat_key = 'skill_cat_' . $cat;
                                $cat_label = t($cat_key);
                                if ($cat_label === $cat_key) {
                                    $cat_label = ucfirst($cat);
                                }
                                echo htmlspecialchars($cat_label);
                            ?>
                        </h3>

                        <div class="skill-list">
                            <?php foreach ($items as $sk): ?>
                                <div class="skill-item">
                                    <div class="skill-header">
                                        <?php
                                            $sk_key  = 'skill_' . (int)$sk['id'] . '_name';
                                            $sk_name = t($sk_key);
                                            if ($sk_name === $sk_key) $sk_name = $sk['skill_name'];
                                        ?>
                                        <span class="skill-name"><?= htmlspecialchars($sk_name) ?></span>
                                        <span class="skill-percent"><?= (int)$sk['percentage'] ?>%</span>
                                    </div>
                                    <div class="skill-bar">
                                        <div class="skill-bar-fill" style="width: <?= (int)$sk['percentage'] ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="text-align:center;color:var(--text-muted);">No skills yet.</p>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================================
     CERTIFICATIONS SECTION
     ============================================================ -->
<section class="certifications" id="certifications">
    <div class="container">
        <div class="section-header">
            <h2><?= htmlspecialchars(t('certifications_title')) ?></h2>
            <p class="section-subtitle"><?= htmlspecialchars(t('certifications_subtitle')) ?></p>
        </div>

        <?php
            $cert_sql    = "SELECT * FROM certifications ORDER BY display_order, issue_date DESC, id DESC";
            $cert_result = $conn->query($cert_sql);
        ?>

        <?php if ($cert_result && $cert_result->num_rows > 0): ?>
            <div class="cert-grid">
                <?php while ($cert = $cert_result->fetch_assoc()): ?>
                    <?php
                        $cert_title_key  = 'cert_' . (int)$cert['id'] . '_title';
                        $cert_issuer_key = 'cert_' . (int)$cert['id'] . '_issuer';
                        $cert_title  = t($cert_title_key);
                        $cert_issuer = t($cert_issuer_key);
                        if ($cert_title  === $cert_title_key)  $cert_title  = $cert['title'];
                        if ($cert_issuer === $cert_issuer_key) $cert_issuer = $cert['issuer'] ?? '';

                        $year = $cert['issue_date'] ? date('Y', strtotime($cert['issue_date'])) : '';
                        $full_date = $cert['issue_date'] ? date('d.m.Y', strtotime($cert['issue_date'])) : '';
                    ?>
                    <div class="cert-card">
                        <div class="cert-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="cert-body">
                            <h3 class="cert-title"><?= htmlspecialchars($cert_title) ?></h3>
                            <?php if (!empty($cert_issuer)): ?>
                                <p class="cert-issuer"><?= htmlspecialchars($cert_issuer) ?></p>
                            <?php endif; ?>
                            <?php if ($full_date): ?>
                                <p class="cert-date"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($full_date) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($cert['credential_url'])): ?>
                                <a href="<?= htmlspecialchars($cert['credential_url']) ?>" target="_blank" rel="noopener" class="cert-link">
                                    <?= htmlspecialchars(t('certifications_view')) ?> <i class="fas fa-external-link-alt"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p style="text-align:center;color:var(--text-muted);">No certifications yet.</p>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================================
     PROJECTS SECTION
     ============================================================ -->
<section class="projects" id="projects">
    <div class="container">
        <div class="section-header">
            <h2><?= htmlspecialchars(t('projects_title')) ?></h2>
            <p class="section-subtitle"><?= htmlspecialchars(t('projects_subtitle')) ?></p>
        </div>

        <?php
            // Collect distinct categories for filter buttons
            $cat_sql     = "SELECT DISTINCT category FROM projects WHERE category IS NOT NULL AND category <> '' ORDER BY category";
            $cat_result  = $conn->query($cat_sql);
            $categories  = [];
            if ($cat_result) {
                while ($c = $cat_result->fetch_assoc()) $categories[] = $c['category'];
            }

            // Load all projects
            $proj_sql    = "SELECT * FROM projects ORDER BY display_order, id DESC";
            $proj_result = $conn->query($proj_sql);
            $projects    = [];
            if ($proj_result) {
                while ($p = $proj_result->fetch_assoc()) $projects[] = $p;
            }
        ?>

        <?php if (!empty($projects)): ?>

            <!-- Filter buttons -->
            <div class="project-filters">
                <button class="filter-btn active" data-filter="all"><?= htmlspecialchars(t('projects_filter_all')) ?></button>
                <?php foreach ($categories as $cat): ?>
                    <button class="filter-btn" data-filter="<?= htmlspecialchars($cat) ?>">
                        <?= htmlspecialchars(t('project_cat_' . $cat) === 'project_cat_' . $cat ? ucfirst($cat) : t('project_cat_' . $cat)) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Project cards -->
            <div class="project-grid">
                <?php foreach ($projects as $proj): ?>
                    <?php
                        $proj_title_key = 'proj_' . (int)$proj['id'] . '_title';
                        $proj_desc_key  = 'proj_' . (int)$proj['id'] . '_description';
                        $proj_title = t($proj_title_key);
                        $proj_desc  = t($proj_desc_key);
                        if ($proj_title === $proj_title_key) $proj_title = $proj['title'];
                        if ($proj_desc  === $proj_desc_key)  $proj_desc  = $proj['description'] ?? '';
                    ?>
                    <div class="project-card" data-category="<?= htmlspecialchars($proj['category']) ?>">
                        <div class="project-image">
                            <?php if (!empty($proj['image_url'])): ?>
                                <img src="<?= htmlspecialchars($proj['image_url']) ?>" alt="<?= htmlspecialchars($proj_title) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="project-image-placeholder">
                                    <i class="fas fa-code"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="project-body">
                            <span class="project-category">
                                <?= htmlspecialchars(t('project_cat_' . $proj['category']) === 'project_cat_' . $proj['category'] ? ucfirst($proj['category']) : t('project_cat_' . $proj['category'])) ?>
                            </span>
                            <h3 class="project-title"><?= htmlspecialchars($proj_title) ?></h3>
                            <?php if (!empty($proj_desc)): ?>
                                <p class="project-desc"><?= htmlspecialchars($proj_desc) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($proj['project_url'])): ?>
                                <a href="<?= htmlspecialchars($proj['project_url']) ?>" target="_blank" rel="noopener" class="project-link">
                                    <?= htmlspecialchars(t('projects_view')) ?> <i class="fas fa-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <p style="text-align:center;color:var(--text-muted);">No projects yet.</p>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================================
     TESTIMONIALS SECTION
     ============================================================ -->
<section class="testimonials" id="testimonials">
    <div class="container">
        <div class="section-header">
            <h2><?= htmlspecialchars(t('testimonials_title')) ?></h2>
            <p class="section-subtitle"><?= htmlspecialchars(t('testimonials_subtitle')) ?></p>
        </div>

        <?php
            $tst_sql    = "SELECT * FROM testimonials WHERE is_visible = 1 ORDER BY display_order, id DESC";
            $tst_result = $conn->query($tst_sql);
        ?>

        <?php if ($tst_result && $tst_result->num_rows > 0): ?>
            <div class="testimonial-grid">
                <?php while ($tst = $tst_result->fetch_assoc()): ?>
                    <?php
                        $tst_text_key  = 'tst_' . (int)$tst['id'] . '_text';
                        $tst_pos_key   = 'tst_' . (int)$tst['id'] . '_position';
                        $tst_text      = t($tst_text_key);
                        $tst_pos       = t($tst_pos_key);
                        if ($tst_text === $tst_text_key) $tst_text = $tst['testimonial'];
                        if ($tst_pos  === $tst_pos_key)  $tst_pos  = $tst['client_position'] ?? '';

                        $rating = (int)($tst['rating'] ?? 5);
                        $initials = strtoupper(
                            mb_substr($tst['client_name'], 0, 1) .
                            (strpos($tst['client_name'], ' ') !== false
                                ? mb_substr(explode(' ', $tst['client_name'])[1], 0, 1)
                                : '')
                        );
                    ?>
                    <div class="testimonial-card">
                        <div class="testimonial-quote">
                            <i class="fas fa-quote-left"></i>
                        </div>

                        <div class="testimonial-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i > $rating ? '-o' : '' ?>" style="<?= $i > $rating ? 'opacity:0.3;' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>

                        <p class="testimonial-text"><?= nl2br(htmlspecialchars($tst_text)) ?></p>

                        <div class="testimonial-author">
                            <?php if (!empty($tst['client_image']) && file_exists(__DIR__ . '/' . $tst['client_image'])): ?>
                                <img src="<?= htmlspecialchars($tst['client_image']) ?>" alt="<?= htmlspecialchars($tst['client_name']) ?>" class="testimonial-avatar">
                            <?php else: ?>
                                <div class="testimonial-avatar testimonial-avatar-initials"><?= htmlspecialchars($initials) ?></div>
                            <?php endif; ?>
                            <div class="testimonial-author-info">
                                <div class="testimonial-author-name"><?= htmlspecialchars($tst['client_name']) ?></div>
                                <?php if (!empty($tst_pos)): ?>
                                    <div class="testimonial-author-role"><?= htmlspecialchars($tst_pos) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p style="text-align:center;color:var(--text-muted);">No testimonials yet.</p>
        <?php endif; ?>
    </div>
</section>

<!-- ============================================================
     LEAVE A REVIEW FORM
     ============================================================ -->
<section class="leave-review" id="leave-review">
    <div class="container">
        <div class="section-header">
            <h2><?= htmlspecialchars(t('review_title')) ?></h2>
            <p class="section-subtitle"><?= htmlspecialchars(t('review_subtitle')) ?></p>
        </div>

        <div class="review-form-wrap">
            <div id="reviewAlert" class="contact-feedback" style="display:none;margin-bottom:16px;"></div>

            <form id="reviewForm" method="POST" action="leave_review.php" class="review-form">
                <div class="review-row">
                    <div class="review-field">
                        <label for="rv_name"><?= htmlspecialchars(t('review_name')) ?> *</label>
                        <input type="text" id="rv_name" name="client_name" required maxlength="100">
                    </div>
                    <div class="review-field">
                        <label for="rv_position"><?= htmlspecialchars(t('review_position')) ?></label>
                        <input type="text" id="rv_position" name="client_position" maxlength="100"
                               placeholder="<?= htmlspecialchars(t('review_position_ph')) ?>">
                    </div>
                </div>

                <div class="review-field">
                    <label><?= htmlspecialchars(t('review_rating')) ?> *</label>
                    <div class="review-stars" id="reviewStars">
                        <span data-val="1">★</span>
                        <span data-val="2">★</span>
                        <span data-val="3">★</span>
                        <span data-val="4">★</span>
                        <span data-val="5">★</span>
                    </div>
                    <input type="hidden" name="rating" id="reviewRatingInput" value="5">
                </div>

                <div class="review-field">
                    <label for="rv_text"><?= htmlspecialchars(t('review_text')) ?> *</label>
                    <textarea id="rv_text" name="testimonial" rows="5" required minlength="20" maxlength="1000"
                              placeholder="<?= htmlspecialchars(t('review_text_ph')) ?>"></textarea>
                </div>

                <div class="review-submit-wrap">
                    <button type="submit" class="btn btn-accent">
                        <i class="fas fa-paper-plane"></i> <?= htmlspecialchars(t('review_send')) ?>
                    </button>
                    <p class="review-note">
                        <i class="fas fa-info-circle"></i>
                        <?= htmlspecialchars(t('review_note')) ?>
                    </p>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- ============================================================
     CONTACT SECTION
     ============================================================ -->
<section class="contact" id="contact">
    <div class="container">
        <div class="section-header">
            <h2><?= htmlspecialchars(t('contact_title')) ?></h2>
            <p class="section-subtitle"><?= htmlspecialchars(t('contact_subtitle')) ?></p>
        </div>

        <div class="contact-grid">

            <!-- LEFT: contact info -->
            <div class="contact-info">
                <?php if (!empty($info['address'])): ?>
                    <div class="contact-info-item">
                        <div class="contact-info-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div>
                            <div class="contact-info-label"><?= htmlspecialchars(t('contact_address')) ?></div>
                            <div class="contact-info-value"><?= htmlspecialchars($info['address']) ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($info['email'])): ?>
                    <div class="contact-info-item">
                        <div class="contact-info-icon"><i class="fas fa-envelope"></i></div>
                        <div>
                            <div class="contact-info-label"><?= htmlspecialchars(t('contact_email_label')) ?></div>
                            <div class="contact-info-value">
                                <a href="mailto:<?= htmlspecialchars($info['email']) ?>"><?= htmlspecialchars($info['email']) ?></a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($info['phone'])): ?>
                    <div class="contact-info-item">
                        <div class="contact-info-icon"><i class="fas fa-phone"></i></div>
                        <div>
                            <div class="contact-info-label"><?= htmlspecialchars(t('contact_phone')) ?></div>
                            <div class="contact-info-value">
                                <a href="tel:<?= htmlspecialchars($info['phone']) ?>"><?= htmlspecialchars($info['phone']) ?></a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: form -->
            <form id="contactForm" class="contact-form" method="POST" action="contact_process.php">
                <div class="contact-field">
                    <label for="c_name"><?= htmlspecialchars(t('contact_name')) ?> *</label>
                    <input type="text" id="c_name" name="name" required>
                </div>

                <div class="contact-field">
                    <label for="c_email"><?= htmlspecialchars(t('contact_email')) ?> *</label>
                    <input type="email" id="c_email" name="email" required>
                </div>

                <div class="contact-field">
                    <label for="c_subject"><?= htmlspecialchars(t('contact_subject')) ?> *</label>
                    <input type="text" id="c_subject" name="subject" required>
                </div>

                <div class="contact-field">
                    <label for="c_message"><?= htmlspecialchars(t('contact_message')) ?> *</label>
                    <textarea id="c_message" name="message" rows="5" required minlength="10"></textarea>
                </div>

                <button type="submit" class="btn btn-accent contact-submit">
                    <i class="fas fa-paper-plane"></i>
                    <?= htmlspecialchars(t('contact_send')) ?>
                </button>

                <div id="contactFeedback" class="contact-feedback" style="display:none;"></div>
            </form>

        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ---------- QR CODE ---------- */
    var box = document.getElementById('heroQrBox');
    if (box && typeof QRCode !== 'undefined') {
        new QRCode(box, {
            text: '<?= htmlspecialchars(SITE_URL) ?>',
            width: 160,
            height: 160,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }

    /* ---------- PROJECT FILTERS ---------- */
    var filterBtns = document.querySelectorAll('.filter-btn');
    var cards = document.querySelectorAll('.project-card');

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var filter = btn.dataset.filter;

            filterBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');

            cards.forEach(function (card) {
                if (filter === 'all' || card.dataset.category === filter) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        });
    });

    /* ---------- CONTACT FORM (AJAX) ---------- */
    var form = document.getElementById('contactForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fb = document.getElementById('contactFeedback');
            var fd = new FormData(form);

            fetch('contact_process.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                fb.style.display = 'block';
                fb.className = 'contact-feedback ' + (data.success ? 'success' : 'error');
                fb.textContent = data.message;
                if (data.success) form.reset();
            })
            .catch(function () {
                fb.style.display = 'block';
                fb.className = 'contact-feedback error';
                fb.textContent = 'Network error. Please try again.';
            });
        });
    }

    /* ---------- REVIEW STAR RATING ---------- */
    var starWrap = document.getElementById('reviewStars');
    var ratingInput = document.getElementById('reviewRatingInput');
    var paintStars = function () {};

    if (starWrap && ratingInput) {
        var stars = starWrap.querySelectorAll('span');

        paintStars = function (val) {
            stars.forEach(function (s) {
                s.classList.toggle('on', parseInt(s.dataset.val, 10) <= val);
            });
        };

        paintStars(5);

        stars.forEach(function (s) {
            s.addEventListener('click', function () {
                var v = parseInt(s.dataset.val, 10);
                ratingInput.value = v;
                paintStars(v);
            });
            s.addEventListener('mouseenter', function () {
                paintStars(parseInt(s.dataset.val, 10));
            });
        });

        starWrap.addEventListener('mouseleave', function () {
            paintStars(parseInt(ratingInput.value, 10));
        });
    }

    /* ---------- REVIEW FORM (AJAX) ---------- */
    var reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var alertBox = document.getElementById('reviewAlert');
            var fd = new FormData(reviewForm);

            fetch('leave_review.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                alertBox.style.display = 'block';
                alertBox.className = 'contact-feedback ' + (data.success ? 'success' : 'error');
                alertBox.textContent = data.message;
                if (data.success) {
                    reviewForm.reset();
                    if (ratingInput) {
                        ratingInput.value = 5;
                        paintStars(5);
                    }
                }
            })
            .catch(function () {
                alertBox.style.display = 'block';
                alertBox.className = 'contact-feedback error';
                alertBox.textContent = 'Network error. Please try again.';
            });
        });
    }

});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
