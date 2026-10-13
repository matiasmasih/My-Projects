<?php
// admin_dashboard.php — Admin control panel
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

// Require login
require_login('admin_login.php');

$admin_name  = current_admin_name();
$admin       = current_admin();

// -------- STATS --------
$total_posts       = (int)$conn->query("SELECT COUNT(*) AS c FROM blog_posts")->fetch_assoc()['c'];
$published_posts   = (int)$conn->query("SELECT COUNT(*) AS c FROM blog_posts WHERE status='published'")->fetch_assoc()['c'];
$draft_posts       = $total_posts - $published_posts;
$total_views       = (int)($conn->query("SELECT COALESCE(SUM(views),0) AS s FROM blog_posts")->fetch_assoc()['s'] ?? 0);

$total_messages    = (int)$conn->query("SELECT COUNT(*) AS c FROM contact_messages")->fetch_assoc()['c'];
$unread_messages   = (int)$conn->query("SELECT COUNT(*) AS c FROM contact_messages WHERE status='unread'")->fetch_assoc()['c'];

$total_certs       = (int)$conn->query("SELECT COUNT(*) AS c FROM certifications")->fetch_assoc()['c'];
$total_testi       = (int)$conn->query("SELECT COUNT(*) AS c FROM testimonials WHERE is_visible=1")->fetch_assoc()['c'];

// -------- DELETE POST --------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM blog_posts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    header('Location: admin_dashboard.php?msg=deleted');
    exit;
}

// -------- RECENT POSTS --------
$posts = [];
$res = $conn->query("SELECT id, title, category, status, published_at, views FROM blog_posts ORDER BY COALESCE(published_at, created_at) DESC LIMIT 10");
if ($res) while ($r = $res->fetch_assoc()) $posts[] = $r;

// -------- RECENT MESSAGES --------
$messages = [];
$res = $conn->query("SELECT id, name, email, subject, status, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 5");
if ($res) while ($r = $res->fetch_assoc()) $messages[] = $r;

$page_title = t('dash_title');
require_once __DIR__ . '/includes/header.php';
?>

<section class="dash-page">
    <div class="container">

        <!-- Welcome banner -->
        <div class="dash-hero">
            <div>
                <h1 class="dash-hello"><?= htmlspecialchars(t('dash_welcome')) ?>, <?= htmlspecialchars($admin_name) ?>! 👋</h1>
                <p class="dash-sub"><?= htmlspecialchars(t('dash_subtitle')) ?></p>
            </div>
            <div class="dash-hero-actions">
                <a href="admin_add_post.php" class="btn btn-accent">
                    <i class="fas fa-plus"></i> <?= htmlspecialchars(t('dash_new_post')) ?>
                </a>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="dash-flash dash-flash-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars(t('dash_deleted')) ?>
            </div>
        <?php endif; ?>

        <?php if ($unread_messages > 0): ?>
            <div class="dash-flash dash-flash-info">
                <i class="fas fa-envelope"></i>
                <?= (int)$unread_messages ?> <?= htmlspecialchars(t('dash_unread_msg')) ?>
                <a href="admin_messages.php" class="dash-flash-link"><?= htmlspecialchars(t('dash_view')) ?> →</a>
            </div>
        <?php endif; ?>

        <!-- Stat cards -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-newspaper"></i></div>
                <div>
                    <div class="stat-value"><?= $total_posts ?></div>
                    <div class="stat-label"><?= htmlspecialchars(t('dash_total_posts')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-value"><?= $published_posts ?></div>
                    <div class="stat-label"><?= htmlspecialchars(t('dash_published')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon amber"><i class="fas fa-pencil-alt"></i></div>
                <div>
                    <div class="stat-value"><?= $draft_posts ?></div>
                    <div class="stat-label"><?= htmlspecialchars(t('dash_drafts')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-eye"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($total_views) ?></div>
                    <div class="stat-label"><?= htmlspecialchars(t('dash_views')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-envelope"></i></div>
                <div>
                    <div class="stat-value"><?= $total_messages ?><?= $unread_messages > 0 ? ' <small style="color:#dc2626;font-size:0.85rem;">(' . $unread_messages . ' ' . htmlspecialchars(t('dash_unread')) . ')</small>' : '' ?></div>
                    <div class="stat-label"><?= htmlspecialchars(t('dash_messages')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-award"></i></div>
                <div>
                    <div class="stat-value"><?= $total_certs ?></div>
                    <div class="stat-label"><?= htmlspecialchars(t('dash_certs')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon amber"><i class="fas fa-star"></i></div>
                <div>
                    <div class="stat-value"><?= $total_testi ?></div>
                    <div class="stat-label"><?= htmlspecialchars(t('dash_testimonials')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user"></i></div>
                <div>
                    <div class="stat-value"><?= htmlspecialchars($admin['role']) ?></div>
                    <div class="stat-label"><?= htmlspecialchars(t('dash_role')) ?></div>
                </div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="dash-panel">
            <h3><i class="fas fa-bolt"></i> <?= htmlspecialchars(t('dash_quick_actions')) ?></h3>
            <div class="dash-actions">
                <a href="admin_add_post.php" class="action-tile">
                    <i class="fas fa-plus-circle"></i>
                    <span><?= htmlspecialchars(t('dash_new_post')) ?></span>
                </a>
                <a href="admin_certifications.php" class="action-tile">
                    <i class="fas fa-award"></i>
                    <span><?= htmlspecialchars(t('dash_certs')) ?></span>
                </a>
                <a href="admin_messages.php" class="action-tile">
                    <i class="fas fa-envelope"></i>
                    <span><?= htmlspecialchars(t('dash_messages')) ?></span>
                </a>
                <a href="admin_testimonials.php" class="action-tile">
                    <i class="fas fa-star"></i>
                    <span><?= htmlspecialchars(t('dash_testimonials')) ?></span>
                </a>
                <a href="admin_settings.php" class="action-tile">
                    <i class="fas fa-cog"></i>
                    <span><?= htmlspecialchars(t('nav_settings')) ?></span>
                </a>
                <a href="index.php" class="action-tile">
                    <i class="fas fa-globe"></i>
                    <span><?= htmlspecialchars(t('dash_view_site')) ?></span>
                </a>
            </div>
        </div>

        <!-- Recent posts -->
        <div class="dash-panel">
            <div class="dash-panel-header">
                <h3><i class="fas fa-newspaper"></i> <?= htmlspecialchars(t('dash_recent_posts')) ?></h3>
                <a href="admin_add_post.php" class="btn btn-outline" style="padding:6px 14px;font-size:.82rem;">
                    <i class="fas fa-plus"></i> <?= htmlspecialchars(t('dash_new_post')) ?>
                </a>
            </div>

            <?php if (empty($posts)): ?>
                <p class="dash-empty"><?= htmlspecialchars(t('dash_no_posts')) ?></p>
            <?php else: ?>
                <div class="dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th><?= htmlspecialchars(t('dash_col_title')) ?></th>
                                <th><?= htmlspecialchars(t('dash_col_category')) ?></th>
                                <th><?= htmlspecialchars(t('dash_col_status')) ?></th>
                                <th><?= htmlspecialchars(t('dash_col_date')) ?></th>
                                <th><?= htmlspecialchars(t('dash_col_views')) ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($posts as $p): ?>
                            <tr>
                                <td class="dash-td-title"><?= htmlspecialchars($p['title']) ?></td>
                                <td><?= htmlspecialchars($p['category'] ?? '—') ?></td>
                                <td>
                                    <span class="dash-badge dash-badge-<?= $p['status'] === 'published' ? 'ok' : 'warn' ?>">
                                        <?= htmlspecialchars($p['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($p['published_at'] ? date('d.m.Y', strtotime($p['published_at'])) : '—') ?></td>
                                <td><?= (int)$p['views'] ?></td>
                                <td class="dash-td-actions">
                                    <a href="admin_edit_post.php?id=<?= (int)$p['id'] ?>" class="dash-btn-edit" title="Edit">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <a href="admin_dashboard.php?delete=<?= (int)$p['id'] ?>"
                                       class="dash-btn-del" title="Delete"
                                       onclick="return confirm('<?= htmlspecialchars(t('dash_confirm_delete')) ?>');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent messages -->
        <div class="dash-panel">
            <div class="dash-panel-header">
                <h3><i class="fas fa-envelope"></i> <?= htmlspecialchars(t('dash_recent_messages')) ?></h3>
                <a href="admin_messages.php" class="btn btn-outline" style="padding:6px 14px;font-size:.82rem;">
                    <?= htmlspecialchars(t('dash_view_all')) ?>
                </a>
            </div>

            <?php if (empty($messages)): ?>
                <p class="dash-empty"><?= htmlspecialchars(t('dash_no_messages')) ?></p>
            <?php else: ?>
                <div class="msg-mini-list">
                    <?php foreach ($messages as $m): ?>
                        <a href="admin_messages.php" class="msg-mini <?= $m['status'] === 'unread' ? 'unread' : '' ?>">
                            <div class="msg-mini-icon"><i class="fas fa-envelope<?= $m['status'] === 'unread' ? '' : '-open' ?>"></i></div>
                            <div class="msg-mini-body">
                                <div class="msg-mini-from"><?= htmlspecialchars($m['name']) ?> <span class="msg-mini-email">&lt;<?= htmlspecialchars($m['email']) ?>&gt;</span></div>
                                <div class="msg-mini-subject"><?= htmlspecialchars($m['subject']) ?></div>
                                <div class="msg-mini-date"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($m['created_at']))) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
