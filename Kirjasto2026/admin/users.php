<?php
// admin/users.php — Global User Management
// Kirjasto 2026 — Phase 9 (multi-branch)

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';
$error = '';

// ---- CHANGE ROLE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_role') {
    $target_id = (int)$_POST['user_id'];
    $new_role = $_POST['role'];

    if ($target_id === $_SESSION['user_id']) {
        $error = 'You cannot change your own role.';
    } else {
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $target_id]);
        $flash = 'User role updated successfully.';
    }
}

// ---- CHANGE BRANCH ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_branch') {
    $target_id = (int)$_POST['user_id'];
    $new_branch = $_POST['branch_id'] === '' ? null : (int)$_POST['branch_id'];

    $pdo->prepare("UPDATE users SET branch_id = ? WHERE id = ?")->execute([$new_branch, $target_id]);

    $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $log_stmt->execute([$_SESSION['user_id'], 'user.branch_change', "Changed branch for user ID $target_id to " . ($new_branch ?? 'none'), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

    $flash = 'User branch updated successfully.';
}

// ---- DELETE USER ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $target_id = (int)$_POST['user_id'];

    if ($target_id === $_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        try {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_id]);
            $flash = 'User deleted successfully.';
        } catch (PDOException $e) {
            $error = 'Cannot delete user — they may have active loans.';
        }
    }
}

// ---- SEARCH & FILTER ----
$search = trim($_GET['search'] ?? '');
$filter_role = $_GET['role'] ?? 'all';
$filter_branch = $_GET['branch'] ?? 'all';

$query = "SELECT * FROM users";
$params = [];
$conditions = [];

if ($search !== '') {
    $conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR membership_number LIKE ?)";
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if ($filter_role !== 'all') {
    $conditions[] = "role = ?";
    $params[] = $filter_role;
}

if ($filter_branch !== 'all') {
    if ($filter_branch === 'none') {
        $conditions[] = "branch_id IS NULL";
    } else {
        $conditions[] = "branch_id = ?";
        $params[] = (int)$filter_branch;
    }
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}
$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$branches = get_branches();

$page_title = 'Manage Users';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Manage Users</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">View, edit, or remove any account in the system.</p>
    </div>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<!-- Search & Filter -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <form method="get" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by name, email, or membership #..." style="flex: 1; min-width: 200px; padding: 12px 16px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: var(--font); font-size: 0.95rem; outline: none;">
        <select name="role" style="padding: 12px 16px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: var(--font); font-size: 0.95rem; outline: none;">
            <option value="all" <?= $filter_role === 'all' ? 'selected' : '' ?>>All Roles</option>
            <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="manager" <?= $filter_role === 'manager' ? 'selected' : '' ?>>Manager</option>
            <option value="staff" <?= $filter_role === 'staff' ? 'selected' : '' ?>>Staff</option>
            <option value="member" <?= $filter_role === 'member' ? 'selected' : '' ?>>Member</option>
        </select>
        <select name="branch" style="padding: 12px 16px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: var(--font); font-size: 0.95rem; outline: none;">
            <option value="all" <?= $filter_branch === 'all' ? 'selected' : '' ?>>All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $filter_branch == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
            <option value="none" <?= $filter_branch === 'none' ? 'selected' : '' ?>>No Branch</option>
        </select>
        <button type="submit" class="btn-primary" style="width: auto; padding: 12px 24px; background: linear-gradient(135deg, #3b82f6, #2563eb);">
            <i class="fas fa-search"></i> Search
        </button>
    </form>
</div>

<!-- Users Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Membership #</th>
                    <th>Phone</th>
                    <th>Joined</th>
                    <th>Branch</th>
                    <th>Role</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td style="display: flex; align-items: center; gap: 16px;">
                            <?php if (!empty($u['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $u['profile_image'])): ?>
                                <img src="<?= UPLOADS_URL . e($u['profile_image']) ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                            <?php else: ?>
                                <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; display: grid; place-items: center; font-weight: bold;">
                                    <?= strtoupper(substr($u['email'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong style="display: block; color: var(--text);"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($u['email']) ?></small>
                            </div>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;"><?= e($u['membership_number'] ?: '—') ?></td>
                        <td style="color: var(--text-muted);"><?= e($u['phone']) ?></td>
                        <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <form method="post" style="margin: 0;">
                                <input type="hidden" name="action" value="change_branch">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <select name="branch_id" onchange="this.form.submit()" style="padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--text); font-weight: 600; cursor: pointer; font-size: 0.85rem;">
                                    <option value="">— No Branch —</option>
                                    <?php foreach ($branches as $b): ?>
                                        <option value="<?= $b['id'] ?>" <?= $u['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <?php if ($u['id'] === $_SESSION['user_id']): ?>
                                <span style="background: var(--danger); color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">You (Admin)</span>
                            <?php else: ?>
                                <form method="post" style="display: inline-block;">
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <select name="role" onchange="this.form.submit()" style="padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--text); font-weight: 600; cursor: pointer; font-size: 0.85rem;">
                                        <option value="member" <?= $u['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                        <option value="staff" <?= $u['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                        <option value="manager" <?= $u['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                <form method="post" onsubmit="return confirm('Delete <?= e($u['first_name']) ?>? This cannot be undone.');" style="margin: 0;">
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
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--text-muted);">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
