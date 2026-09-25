<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (current_role() !== 'admin') {
    $_SESSION['flash_error'] = "Access denied. Only admins can manage roles.";
    header("Location: login.php");
    exit;
}


$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ============================================
// HANDLE CHANGE USER ROLE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $role_id = (int)($_POST['role_id'] ?? 0);

    if ($user_id <= 0 || $role_id <= 0) {
        $_SESSION['flash_error'] = "Invalid request.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
            $stmt->execute([$role_id, $user_id]);
            $_SESSION['flash_success'] = "User role updated.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Update failed: " . $e->getMessage();
        }
    }
    header("Location: roles.php");
    exit;
}

// ============================================
// HANDLE ADD ROLE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    $name = trim($_POST['role_name'] ?? '');
    if ($name === '') {
        $_SESSION['flash_error'] = "Role name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO roles (name) VALUES (?)");
            $stmt->execute([$name]);
            $_SESSION['flash_success'] = "Role added: " . $name;
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Add failed: " . $e->getMessage();
        }
    }
    header("Location: roles.php");
    exit;
}

// ============================================
// HANDLE DELETE ROLE (POST)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role'])) {
    $rid = (int)($_POST['role_id'] ?? 0);
    if ($rid > 0) {
        try {
            // Check if any users use this role
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
            $check->execute([$rid]);
            $in_use = (int)$check->fetchColumn();

            if ($in_use > 0) {
                $_SESSION['flash_error'] = "Cannot delete — {$in_use} user(s) still use this role.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
                $stmt->execute([$rid]);
                $_SESSION['flash_success'] = "Role deleted.";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    }
    header("Location: roles.php");
    exit;
}

// ============================================
// FETCH DATA
// ============================================
$roles = [];
try {
    $roles = $pdo->query("SELECT id, name FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$users = [];
try {
    $users = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.role_id, r.name AS role
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY u.id
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Count users per role
$role_usage = [];
try {
    $stmt = $pdo->query("SELECT role_id, COUNT(*) AS c FROM users GROUP BY role_id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $role_usage[(int)$row['role_id']] = (int)$row['c'];
    }
} catch (PDOException $e) {}

$page_title = 'Roles';
include 'users_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- ============================================ -->
<!-- SECTION 1: MANAGE USER ROLES                -->
<!-- ============================================ -->
<div class="table-card">
    <h5><i class="bi bi-people"></i> Manage User Roles (<?= count($users) ?>)</h5>

    <div class="mb-3">
        <input type="text" class="form-control" id="searchUsers" placeholder="Search users..." style="max-width: 320px;">
    </div>

    <?php if (count($users) > 0): ?>
    <div style="overflow-x: auto;">
        <table id="usersTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Current Role</th>
                    <th>Change Role</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></strong></td>
                    <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                    <td>
                        <span class="role-badge role-<?= (int)($u['role_id'] ?? 0) ?>">
                            <?= htmlspecialchars($u['role'] ?? '-') ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="roles.php" class="d-flex" style="gap:6px;">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <select name="role_id" class="form-select" style="width: auto;">
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === (int)$u['role_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="change_role" class="btn btn-add">
                                <i class="bi bi-save"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">No users found.</p>
    <?php endif; ?>
</div>

<!-- ============================================ -->
<!-- SECTION 2: ROLES MANAGEMENT                 -->
<!-- ============================================ -->
<div class="table-card">
    <h5><i class="bi bi-shield-lock"></i> Roles (<?= count($roles) ?>)</h5>

    <!-- Add Role -->
    <form method="POST" action="roles.php" class="d-flex mb-3" style="gap:8px; max-width: 500px;">
        <input type="text" name="role_name" class="form-control" placeholder="New role name..." required>
        <button type="submit" name="add_role" class="btn btn-add" style="white-space: nowrap;">
            <i class="bi bi-plus-lg"></i> Add
        </button>
    </form>

    <?php if (count($roles) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Role ID</th>
                    <th>Role Name</th>
                    <th>Users</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $r): ?>
                <tr>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                    <td>
                        <span class="role-badge role-<?= (int)$r['id'] ?>">
                            <?= (int)($role_usage[(int)$r['id']] ?? 0) ?> users
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="roles.php" style="display:inline;"
                              onsubmit="return confirm('Delete this role?');">
                            <input type="hidden" name="role_id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="delete_role" value="1">
                            <button type="submit" class="btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">No roles found.</p>
    <?php endif; ?>
</div>

<script>
const searchUsers = document.getElementById('searchUsers');
const usersTable  = document.getElementById('usersTable');

if (searchUsers && usersTable) {
    searchUsers.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        Array.from(usersTable.getElementsByTagName('tr')).forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
        });
    });
}
</script>

<?php include 'users_footer.php'; ?>
