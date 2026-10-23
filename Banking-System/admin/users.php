<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Verify Admin Role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin_check = $stmt->fetch();

if ($admin_check['role'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';
$error = '';

// Handle Role Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_role') {
    $target_user = (int)$_POST['user_id'];
    $new_role = $_POST['role'];
    
    if ($target_user === $_SESSION['user_id']) {
        $error = 'You cannot change your own role.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $target_user]);
        $flash = 'User role updated successfully.';
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $target_user = (int)$_POST['user_id'];
    
    if ($target_user === $_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$target_user]);
        $flash = 'User deleted successfully.';
    }
}

// Fetch All Users
$users = $pdo->query("SELECT id, first_name, last_name, email, phone, role, photo, created_at FROM users ORDER BY id DESC")->fetchAll();

$page_title = 'Manage Users';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="font-size: 1.8rem; color: var(--text);">Manage Users</h1>
        <p style="color: var(--text-muted);">View, edit, or remove any account in the bank.</p>
    </div>
    <a href="<?= e(SITE_URL) ?>admin/add_user.php" class="btn-primary" style="width: auto; padding: 10px 20px; font-size: 0.9rem;">
        <i class="fas fa-plus"></i> Add User
    </a>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
    <div class="table-responsive" style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Phone</th>
                    <th>Joined</th>
                    <th>Role (Change)</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
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
                        <td style="color: var(--text-muted);"><?= e($u['phone']) ?></td>
                        <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['id'] === $_SESSION['user_id']): ?>
                                <span style="background: var(--danger); color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; text-transform: uppercase;"><?= e($u['role']) ?> (You)</span>
                            <?php else: ?>
                                <form method="post" style="display: inline-block;">
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="role" onchange="this.form.submit()" style="padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-weight: 600; cursor: pointer;">
                                        <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>Customer</option>
                                        <option value="staff" <?= $u['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                        <option value="manager" <?= $u['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-outline" style="padding: 6px 12px; font-size: 0.8rem; color: var(--danger); border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05);">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.8rem;">Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
