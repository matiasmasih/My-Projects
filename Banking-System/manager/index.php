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

    if ($action === 'approve_loan' || $action === 'reject_loan') {
        $status = $action === 'approve_loan' ? 'approved' : 'rejected';
        $pdo->prepare("UPDATE loans SET status = ? WHERE id = ?")->execute([$status, $target_id]);
        $flash = "Loan application $status successfully.";
    }
}

// Fetch Pending Items
$kycs = $pdo->query("SELECT k.*, u.email FROM kyc_documents k JOIN users u ON k.user_id = u.id WHERE k.status = 'pending' ORDER BY k.id DESC")->fetchAll();
$loans = $pdo->query("SELECT l.*, u.email FROM loans l JOIN users u ON l.user_id = u.id WHERE l.status = 'pending' ORDER BY l.id DESC")->fetchAll();

// Fetch All Users
$users_list = $pdo->query("SELECT id, email, phone, role, photo, created_at, first_name, last_name FROM users ORDER BY id DESC")->fetchAll();

$page_title = 'Manager Dashboard';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Branch Overview</h1>
    <p style="color: var(--text-muted);">Approve requests and manage customers.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>

<!-- Approval Center -->
<div class="grid-2" style="margin-bottom: 32px;">
    <!-- Pending KYC -->
    <div class="card">
        <h3><i class="fas fa-id-card" style="color: #f59e0b;"></i> Pending KYC Documents</h3>
        <?php if(empty($kycs)): ?>
            <p style="color: var(--text-muted); padding: 20px 0;">No pending KYC documents.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach($kycs as $k): ?>
                    <li style="padding: 16px 0; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                        <div style="min-width: 0;">
                            <strong style="display: block; color: var(--text); text-overflow: ellipsis; overflow: hidden;"><?= e($k['email']) ?></strong>
                            <small style="color: var(--text-muted);">Doc: <?= strtoupper($k['document_type']) ?> - <?= e($k['document_number']) ?></small>
                        </div>
                        <div style="display: flex; gap: 8px; flex-shrink: 0;">
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="action" value="approve_kyc">
                                <input type="hidden" name="target_id" value="<?= $k['id'] ?>">
                                <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background: var(--success);">Approve</button>
                            </form>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="action" value="reject_kyc">
                                <input type="hidden" name="target_id" value="<?= $k['id'] ?>">
                                <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background: var(--danger);">Reject</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Pending Loans -->
    <div class="card">
        <h3><i class="fas fa-home" style="color: #f59e0b;"></i> Pending Loan Applications</h3>
        <?php if(empty($loans)): ?>
            <p style="color: var(--text-muted); padding: 20px 0;">No pending loan applications.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach($loans as $l): ?>
                    <li style="padding: 16px 0; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                        <div style="min-width: 0;">
                            <strong style="display: block; color: var(--text); text-overflow: ellipsis; overflow: hidden;"><?= e($l['email']) ?></strong>
                            <small style="color: var(--text-muted);"><?= strtoupper($l['loan_type']) ?> - €<?= number_format($l['principal_amount'], 2) ?></small>
                        </div>
                        <div style="display: flex; gap: 8px; flex-shrink: 0;">
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="action" value="approve_loan">
                                <input type="hidden" name="target_id" value="<?= $l['id'] ?>">
                                <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background: var(--success);">Approve</button>
                            </form>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="action" value="reject_loan">
                                <input type="hidden" name="target_id" value="<?= $l['id'] ?>">
                                <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; background: var(--danger);">Reject</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- User Accounts Table -->
<div class="card">
    <h3><i class="fas fa-users" style="color: #f59e0b;"></i> User Accounts</h3>
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Phone</th>
                    <th>Joined Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users_list as $u): ?>
                    <tr>
                        <td style="display: flex; align-items: center; gap: 16px;">
                            <?php if (!empty($u['photo']) && file_exists(__DIR__ . '/../uploads/' . $u['photo'])): ?>
                                <img src="<?= UPLOADS_URL . e($u['photo']) ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                            <?php else: ?>
                                <div style="width: 44px; height: 44px; border-radius: 50%; background: #f59e0b; color: white; display: grid; place-items: center; font-weight: bold;">
                                    <?= strtoupper(substr($u['email'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong style="display: block; color: var(--text);"><?= !empty($u['first_name']) ? e($u['first_name'] . ' ' . $u['last_name']) : e($u['email']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($u['email']) ?></small>
                            </div>
                        </td>
                        <td>
                            <span style="background: var(--bg); padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: <?= $u['role'] === 'admin' ? 'var(--danger)' : ($u['role'] === 'manager' ? '#d97706' : ($u['role'] === 'staff' ? 'var(--primary)' : 'var(--success)')) ?>;">
                                <?= e($u['role']) ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted);"><?= e($u['phone']) ?></td>
                        <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
