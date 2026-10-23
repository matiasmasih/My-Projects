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

    if ($action === 'approve_loan' || $action === 'reject_loan') {
        $status = $action === 'approve_loan' ? 'approved' : 'rejected';
        $pdo->prepare("UPDATE loans SET status = ? WHERE id = ?")->execute([$status, $target_id]);
        $flash = "Loan application $status successfully.";
    }
}

// Fetch Pending Loans with User Photo
$loans = $pdo->query("
    SELECT l.*, u.email, u.first_name, u.last_name, u.photo 
    FROM loans l 
    JOIN users u ON l.user_id = u.id 
    WHERE l.status = 'pending' 
    ORDER BY l.id DESC
")->fetchAll();

$page_title = 'Loan Approvals';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Loan Approvals</h1>
    <p style="color: var(--text-muted);">Review and process customer loan applications.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>

<div class="card">
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Loan Type</th>
                    <th>Amount</th>
                    <th>Term</th>
                    <th>Interest</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loans as $l): ?>
                    <tr>
                        <td style="display: flex; align-items: center; gap: 16px;">
                            <?php if (!empty($l['photo']) && file_exists(__DIR__ . '/../uploads/' . $l['photo'])): ?>
                                <img src="<?= UPLOADS_URL . e($l['photo']) ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                            <?php else: ?>
                                <div style="width: 44px; height: 44px; border-radius: 50%; background: #f59e0b; color: white; display: grid; place-items: center; font-weight: bold;">
                                    <?= strtoupper(substr($l['email'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong style="display: block; color: var(--text);"><?= e($l['first_name'] . ' ' . $l['last_name']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($l['email']) ?></small>
                            </div>
                        </td>
                        <td style="text-transform: capitalize; color: var(--text); font-weight: 600;"><?= e($l['loan_type']) ?></td>
                        <td style="color: var(--primary); font-weight: 700;">€<?= number_format($l['principal_amount'], 2) ?></td>
                        <td style="color: var(--text-muted);"><?= $l['term_months'] ?> Months</td>
                        <td style="color: var(--text-muted);"><?= $l['interest_rate'] ?>%</td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="approve_loan">
                                    <input type="hidden" name="target_id" value="<?= $l['id'] ?>">
                                    <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.8rem; background: var(--success);">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="reject_loan">
                                    <input type="hidden" name="target_id" value="<?= $l['id'] ?>">
                                    <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.8rem; background: var(--danger);">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($loans)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No pending loan applications.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
