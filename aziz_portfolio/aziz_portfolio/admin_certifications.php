<?php
// admin_certifications.php — Manage certifications
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

require_login('admin_login.php');

$error   = '';
$success = '';

// -------- DELETE --------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM certifications WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header('Location: admin_certifications.php?msg=deleted');
    exit;
}

// -------- ADD / EDIT --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int)($_POST['id'] ?? 0);
    $title          = trim($_POST['title'] ?? '');
    $issuer         = trim($_POST['issuer'] ?? '');
    $issue_date     = $_POST['issue_date'] ?? '';
    $expiry_date    = $_POST['expiry_date'] ?? '';
    $credential_url = trim($_POST['credential_url'] ?? '');
    $image_url      = trim($_POST['image_url'] ?? '');
    $display_order  = (int)($_POST['display_order'] ?? 0);

    if ($title === '') {
        $error = t('cert_err_title');
    } else {
        $issue_date  = $issue_date  !== '' ? $issue_date  : null;
        $expiry_date = $expiry_date !== '' ? $expiry_date : null;

        if ($id > 0) {
            $stmt = $conn->prepare(
                "UPDATE certifications
                 SET title=?, issuer=?, issue_date=?, expiry_date=?, credential_url=?, image_url=?, display_order=?
                 WHERE id=?"
            );
            $stmt->bind_param(
                "ssssssii",
                $title, $issuer, $issue_date, $expiry_date, $credential_url, $image_url, $display_order, $id
            );
            $stmt->execute();
            $success = t('cert_updated');
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO certifications (title, issuer, issue_date, expiry_date, credential_url, image_url, display_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssssssi",
                $title, $issuer, $issue_date, $expiry_date, $credential_url, $image_url, $display_order
            );
            $stmt->execute();
            $success = t('cert_added');
        }
    }
}

// -------- LOAD EDIT TARGET --------
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM certifications WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
}

// -------- LIST --------
$list = $conn->query("SELECT * FROM certifications ORDER BY display_order, issue_date DESC, id DESC")->fetch_all(MYSQLI_ASSOC);

$page_title = t('cert_manage_title');
require_once __DIR__ . '/includes/header.php';
?>

<section class="dash-page">
    <div class="container">

        <div class="editor-top">
            <a href="admin_dashboard.php" class="post-back">
                <i class="fas fa-arrow-left"></i> <?= htmlspecialchars(t('post_back_dash')) ?>
            </a>
            <h1><?= htmlspecialchars(t('cert_manage_title')) ?></h1>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="dash-flash dash-flash-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars(t('cert_deleted')) ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="dash-flash dash-flash-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="dash-flash dash-flash-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="editor-grid">

            <!-- LEFT: list -->
            <div class="editor-main">
                <div class="editor-panel">
                    <h3 style="margin-bottom:18px;">
                        <i class="fas fa-award" style="color:var(--accent);"></i>
                        <?= htmlspecialchars(t('cert_all')) ?> (<?= count($list) ?>)
                    </h3>

                    <?php if (empty($list)): ?>
                        <p class="dash-empty"><?= htmlspecialchars(t('cert_empty')) ?></p>
                    <?php else: ?>
                        <div class="cert-manage-list">
                            <?php foreach ($list as $c): ?>
                                <div class="cert-manage-item">
                                    <div class="cert-manage-icon"><i class="fas fa-award"></i></div>
                                    <div class="cert-manage-body">
                                        <div class="cert-manage-title"><?= htmlspecialchars($c['title']) ?></div>
                                        <?php if (!empty($c['issuer'])): ?>
                                            <div class="cert-manage-issuer"><?= htmlspecialchars($c['issuer']) ?></div>
                                        <?php endif; ?>
                                        <div class="cert-manage-date">
                                            <?= $c['issue_date'] ? htmlspecialchars(date('d.m.Y', strtotime($c['issue_date']))) : '—' ?>
                                            · <?= htmlspecialchars(t('cert_order')) ?>: <?= (int)$c['display_order'] ?>
                                        </div>
                                    </div>
                                    <div class="cert-manage-actions">
                                        <a href="admin_certifications.php?edit=<?= (int)$c['id'] ?>" class="dash-btn-edit" title="Edit">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>
                                        <a href="admin_certifications.php?delete=<?= (int)$c['id'] ?>"
                                           class="dash-btn-del" title="Delete"
                                           onclick="return confirm('<?= htmlspecialchars(t('cert_confirm_delete')) ?>');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: form -->
            <div class="editor-side">
                <form method="POST" action="admin_certifications.php" class="editor-panel">
                    <h3 style="margin-bottom:16px;">
                        <i class="fas fa-<?= $edit ? 'pencil-alt' : 'plus-circle' ?>" style="color:var(--accent);"></i>
                        <?= $edit ? htmlspecialchars(t('cert_edit')) : htmlspecialchars(t('cert_add')) ?>
                    </h3>

                    <input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">

                    <label><?= htmlspecialchars(t('cert_form_title')) ?> *</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($edit['title'] ?? '') ?>">

                    <label><?= htmlspecialchars(t('cert_form_issuer')) ?></label>
                    <input type="text" name="issuer" value="<?= htmlspecialchars($edit['issuer'] ?? '') ?>">

                    <label><?= htmlspecialchars(t('cert_form_issue_date')) ?></label>
                    <input type="date" name="issue_date" value="<?= htmlspecialchars($edit['issue_date'] ?? '') ?>">

                    <label><?= htmlspecialchars(t('cert_form_expiry_date')) ?></label>
                    <input type="date" name="expiry_date" value="<?= htmlspecialchars($edit['expiry_date'] ?? '') ?>">

                    <label><?= htmlspecialchars(t('cert_form_credential')) ?></label>
                    <input type="text" name="credential_url" value="<?= htmlspecialchars($edit['credential_url'] ?? '') ?>" placeholder="https://...">

                    <label><?= htmlspecialchars(t('cert_form_image')) ?></label>
                    <input type="text" name="image_url" value="<?= htmlspecialchars($edit['image_url'] ?? '') ?>" placeholder="https://...">

                    <label><?= htmlspecialchars(t('cert_form_order')) ?></label>
                    <input type="number" name="display_order" value="<?= (int)($edit['display_order'] ?? 0) ?>" min="0">

                    <div style="display:flex;gap:8px;margin-top:20px;">
                        <button type="submit" class="btn btn-accent" style="flex:1;">
                            <i class="fas fa-check"></i> <?= htmlspecialchars(t('cert_save')) ?>
                        </button>
                        <?php if ($edit): ?>
                            <a href="admin_certifications.php" class="btn btn-outline"><?= htmlspecialchars(t('post_cancel')) ?></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
