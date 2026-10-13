<?php
// admin_testimonials.php — Moderate client testimonials
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

require_login('admin_login.php');

$success = '';

// -------- ACTIONS --------
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

if ($id > 0 && in_array($action, ['approve','reject','delete','reset'], true)) {
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE testimonials SET status='approved', is_visible=1 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $success = t('tst_approved');
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE testimonials SET status='rejected', is_visible=0 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $success = t('tst_rejected');
    } elseif ($action === 'reset') {
        $stmt = $conn->prepare("UPDATE testimonials SET status='pending', is_visible=0 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $success = t('tst_reset');
    } else {
        $stmt = $conn->prepare("DELETE FROM testimonials WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $success = t('tst_deleted');
    }
}

// -------- FILTER --------
$filter = $_GET['filter'] ?? 'pending';
if (!in_array($filter, ['pending','approved','rejected','all'], true)) $filter = 'pending';

$where = $filter === 'all' ? '' : "WHERE status = '" . $conn->real_escape_string($filter) . "'";

$list = $conn->query(
    "SELECT * FROM testimonials $where ORDER BY id DESC"
)->fetch_all(MYSQLI_ASSOC);

$counts = [
    'all'      => (int)$conn->query("SELECT COUNT(*) AS c FROM testimonials")->fetch_assoc()['c'],
    'pending'  => (int)$conn->query("SELECT COUNT(*) AS c FROM testimonials WHERE status='pending'")->fetch_assoc()['c'],
    'approved' => (int)$conn->query("SELECT COUNT(*) AS c FROM testimonials WHERE status='approved'")->fetch_assoc()['c'],
    'rejected' => (int)$conn->query("SELECT COUNT(*) AS c FROM testimonials WHERE status='rejected'")->fetch_assoc()['c'],
];

$page_title = t('tst_manage_title');
require_once __DIR__ . '/includes/header.php';
?>

<section class="dash-page">
    <div class="container">

        <div class="editor-top">
            <a href="admin_dashboard.php" class="post-back">
                <i class="fas fa-arrow-left"></i> <?= htmlspecialchars(t('post_back_dash')) ?>
            </a>
            <h1><?= htmlspecialchars(t('tst_manage_title')) ?></h1>
        </div>

        <?php if ($success): ?>
            <div class="dash-flash dash-flash-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="msg-filters">
            <a href="?filter=pending"  class="<?= $filter === 'pending'  ? 'active' : '' ?>"><?= htmlspecialchars(t('tst_pending')) ?> (<?= $counts['pending'] ?>)</a>
            <a href="?filter=approved" class="<?= $filter === 'approved' ? 'active' : '' ?>"><?= htmlspecialchars(t('tst_approved_tab')) ?> (<?= $counts['approved'] ?>)</a>
            <a href="?filter=rejected" class="<?= $filter === 'rejected' ? 'active' : '' ?>"><?= htmlspecialchars(t('tst_rejected_tab')) ?> (<?= $counts['rejected'] ?>)</a>
            <a href="?filter=all"      class="<?= $filter === 'all'      ? 'active' : '' ?>"><?= htmlspecialchars(t('tst_all')) ?> (<?= $counts['all'] ?>)</a>
        </div>

        <?php if (empty($list)): ?>
            <div class="editor-panel" style="text-align:center;padding:60px 20px;">
                <i class="fas fa-star" style="font-size:2.5rem;color:var(--accent);opacity:.35;"></i>
                <p class="dash-empty" style="margin-top:14px;"><?= htmlspecialchars(t('tst_empty_filter')) ?></p>
            </div>
        <?php else: ?>
            <div class="tst-manage-grid">
                <?php foreach ($list as $x):
                    $initials = strtoupper(mb_substr($x['client_name'], 0, 1));
                    if (strpos($x['client_name'], ' ') !== false) {
                        $parts = explode(' ', $x['client_name']);
                        $initials .= strtoupper(mb_substr($parts[1], 0, 1));
                    }
                ?>
                    <div class="tst-manage-card <?= $x['status'] === 'rejected' ? 'hidden-tst' : '' ?>">
                        <i class="fas fa-quote-left tst-manage-quote"></i>

                        <div class="tst-manage-stars">
                            <?php
                                $r = (int)$x['rating'];
                                for ($i = 1; $i <= 5; $i++) echo $i <= $r ? '★' : '☆';
                            ?>
                            <span class="tst-manage-status-badge tst-<?= htmlspecialchars($x['status']) ?>">
                                <?= htmlspecialchars(t('tst_status_' . $x['status'])) ?>
                            </span>
                        </div>

                        <p class="tst-manage-text">
                            <?= nl2br(htmlspecialchars($x['testimonial'])) ?>
                        </p>

                        <div class="tst-manage-footer">
                            <div class="tst-manage-avatar">
                                <?php if (!empty($x['client_image']) && file_exists(__DIR__ . '/' . $x['client_image'])): ?>
                                    <img src="<?= htmlspecialchars($x['client_image']) ?>" alt="">
                                <?php else: ?>
                                    <?= htmlspecialchars($initials) ?>
                                <?php endif; ?>
                            </div>
                            <div class="tst-manage-info">
                                <div class="tst-manage-name"><?= htmlspecialchars($x['client_name']) ?></div>
                                <?php if (!empty($x['client_position'])): ?>
                                    <div class="tst-manage-role"><?= htmlspecialchars($x['client_position']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tst-manage-actions-row">
                            <?php if ($x['status'] !== 'approved'): ?>
                                <a href="?filter=<?= $filter ?>&action=approve&id=<?= (int)$x['id'] ?>" class="tst-act tst-act-approve" title="Approve">
                                    <i class="fas fa-check"></i> <?= htmlspecialchars(t('tst_approve')) ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($x['status'] !== 'rejected'): ?>
                                <a href="?filter=<?= $filter ?>&action=reject&id=<?= (int)$x['id'] ?>" class="tst-act tst-act-reject" title="Reject">
                                    <i class="fas fa-times"></i> <?= htmlspecialchars(t('tst_reject')) ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($x['status'] !== 'pending'): ?>
                                <a href="?filter=<?= $filter ?>&action=reset&id=<?= (int)$x['id'] ?>" class="tst-act tst-act-reset" title="Reset">
                                    <i class="fas fa-undo"></i>
                                </a>
                            <?php endif; ?>
                            <a href="?filter=<?= $filter ?>&action=delete&id=<?= (int)$x['id'] ?>" class="tst-act tst-act-delete" title="Delete"
                               onclick="return confirm('<?= htmlspecialchars(t('tst_confirm_delete')) ?>');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
