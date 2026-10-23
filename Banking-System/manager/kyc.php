<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Verify Manager Role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';

// Handle Approvals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $target_id = (int)$_POST['target_id'];

    if ($action === 'approve_kyc' || $action === 'reject_kyc') {
        $status = $action === 'approve_kyc' ? 'approved' : 'rejected';
        $pdo->prepare("UPDATE kyc_documents SET status = ? WHERE id = ?")->execute([$status, $target_id]);

        $stmt = $pdo->prepare("SELECT user_id FROM kyc_documents WHERE id = ?");
        $stmt->execute([$target_id]);
        $kyc = $stmt->fetch();
        $pdo->prepare("UPDATE users SET kyc_status = ? WHERE id = ?")->execute([$status, $kyc['user_id']]);

        $flash = "KYC document $status successfully.";
    }
}

// Fetch Pending KYC with User Photo
$kycs = $pdo->query("
    SELECT k.*, u.email, u.first_name, u.last_name, u.photo 
    FROM kyc_documents k 
    JOIN users u ON k.user_id = u.id 
    WHERE k.status = 'pending' 
    ORDER BY k.id DESC
")->fetchAll();

$page_title = 'KYC Approvals';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">KYC Approvals</h1>
    <p style="color: var(--text-muted);">Review identity documents submitted by customers.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>

<div class="card">
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Document</th>
                    <th>Doc Number</th>
                    <th>Expiry</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kycs as $k): ?>
                    <tr>
                        <td style="display: flex; align-items: center; gap: 16px;">
                            <?php if (!empty($k['photo']) && file_exists(__DIR__ . '/../uploads/' . $k['photo'])): ?>
                                <img src="<?= UPLOADS_URL . e($k['photo']) ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                            <?php else: ?>
                                <div style="width: 44px; height: 44px; border-radius: 50%; background: #f59e0b; color: white; display: grid; place-items: center; font-weight: bold;">
                                    <?= strtoupper(substr($k['email'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong style="display: block; color: var(--text);"><?= e($k['first_name'] . ' ' . $k['last_name']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($k['email']) ?></small>
                            </div>
                        </td>
                        <td style="text-transform: capitalize; color: var(--text);"><?= e($k['document_type']) ?></td>
                        <td style="color: var(--text-muted);"><?= e($k['document_number']) ?></td>
                        <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($k['expiry_date'])) ?></td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="approve_kyc">
                                    <input type="hidden" name="target_id" value="<?= $k['id'] ?>">
                                    <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.8rem; background: var(--success);">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="reject_kyc">
                                    <input type="hidden" name="target_id" value="<?= $k['id'] ?>">
                                    <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.8rem; background: var(--danger);">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($kycs)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">No pending KYC documents.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
