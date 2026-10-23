<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Verify Admin Role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Fetch Stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pending_kyc = $pdo->query("SELECT COUNT(*) FROM kyc_documents WHERE status = 'pending'")->fetchColumn();
$pending_loans = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'")->fetchColumn();

// Fetch all users for the table
$users_list = $pdo->query("SELECT id, email, phone, role, photo, created_at, first_name, last_name FROM users ORDER BY id DESC")->fetchAll();

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 1.8rem; color: var(--text);">Bank Overview</h1>
    <p style="color: var(--text-muted);">Welcome back, System Administrator.</p>
</div>

<!-- Stats Grid -->
<div class="grid-2" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 32px; display: grid; gap: 24px;">
    <div class="card" style="text-align: center; border-top: 4px solid var(--primary); padding: 24px;">
        <h3 style="font-size: 2.5rem; margin: 0; color: var(--primary); font-weight: 800;"><?= $total_users ?></h3>
        <p style="color: var(--text-muted); font-weight: 600; margin-top: 8px; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;">Total Users</p>
    </div>
    <div class="card" style="text-align: center; border-top: 4px solid var(--warning); padding: 24px;">
        <h3 style="font-size: 2.5rem; margin: 0; color: var(--warning); font-weight: 800;"><?= $pending_kyc ?></h3>
        <p style="color: var(--text-muted); font-weight: 600; margin-top: 8px; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;">Pending KYC</p>
    </div>
    <div class="card" style="text-align: center; border-top: 4px solid var(--danger); padding: 24px;">
        <h3 style="font-size: 2.5rem; margin: 0; color: var(--danger); font-weight: 800;"><?= $pending_loans ?></h3>
        <p style="color: var(--text-muted); font-weight: 600; margin-top: 8px; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px;">Pending Loans</p>
    </div>
</div>

<!-- User Management Table -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h3 style="margin: 0;"><i class="fas fa-users"></i> User Management</h3>
        <a href="<?= e(SITE_URL) ?>admin/add_user.php" class="btn-primary" style="width: auto; padding: 10px 20px; font-size: 0.9rem;">
            <i class="fas fa-plus"></i> Add User
        </a>
    </div>
    
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
                                <div style="width: 44px; height: 44px; border-radius: 50%; background: var(--primary); color: white; display: grid; place-items: center; font-weight: bold;">
                                    <?= strtoupper(substr($u['email'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong style="display: block; color: var(--text);"><?= !empty($u['first_name']) ? e($u['first_name'] . ' ' . $u['last_name']) : e($u['email']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($u['email']) ?></small>
                            </div>
                        </td>
                        <td>
                            <span style="background: var(--bg); padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: <?= $u['role'] === 'admin' ? 'var(--danger)' : ($u['role'] === 'manager' ? 'var(--warning)' : ($u['role'] === 'staff' ? 'var(--primary)' : 'var(--success)')) ?>;">
                                <?= e($u['role']) ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted);"><?= e($u['phone']) ?></td>
                        <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($users_list)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
