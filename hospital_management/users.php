<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (current_role() !== 'admin') {
    $_SESSION['flash_error'] = "Access denied. Only admins can manage users.";
    header("Location: login.php");
    exit;
}

$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$my_id = (int)$_SESSION['user_id'];

// ============================================
// HANDLE DELETE USER (POST)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $del_id = (int)($_POST['user_id'] ?? 0);

    if ($del_id === $my_id) {
        $_SESSION['flash_error'] = "You cannot delete your own account.";
    } elseif ($del_id <= 0) {
        $_SESSION['flash_error'] = "Invalid user ID.";
    } else {
        try {
            // Check the user has no dependencies (optional safety)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$del_id]);
            $_SESSION['flash_success'] = "User deleted successfully.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    }
    header("Location: users.php");
    exit;
}

// ============================================
// FETCH USERS
// ============================================
$users = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
               u.role_id, u.is_active, u.status, u.last_login, u.created_at,
               r.name AS role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY u.id DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'Users';
include 'users_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-3 gap-2" style="flex-wrap: nowrap;">
    <div class="d-flex align-items-center gap-2" style="flex-wrap: nowrap;">
        <input type="text" class="form-control" id="searchInput" placeholder="Search users..." style="width: 280px; flex-shrink: 0;">
        <button class="btn btn-add" id="searchButton" style="white-space: nowrap; flex-shrink: 0;">
            <i class="bi bi-search"></i> Search
        </button>
    </div>
    <div class="d-flex align-items-center gap-2" style="flex-wrap: nowrap;">
        <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px; white-space: nowrap; flex-shrink: 0;">
            Total: <?= count($users); ?> users
        </span>
        <a href="add_user.php" class="btn btn-add" style="white-space: nowrap; flex-shrink: 0;">
            <i class="bi bi-person-plus"></i> Add User
        </a>
    </div>
</div>

<!-- Users Table -->
<div class="table-card">
    <h5><i class="bi bi-people-fill"></i> All Users (<?= count($users); ?>)</h5>

    <?php if (count($users) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="userTable">
                <?php foreach ($users as $index => $u): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><strong><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></strong></td>
                    <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                    <td>
                        <span class="role-badge role-<?= (int)($u['role_id'] ?? 0) ?>">
                            <?= htmlspecialchars($u['role_name'] ?? '-') ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($u['is_active'])): ?>
                            <span class="status-active">Active</span>
                        <?php else: ?>
                            <span class="status-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($u['last_login']) ? date('M d, Y H:i', strtotime($u['last_login'])) : '<span style="color:var(--text-muted);">Never</span>' ?></td>
                    <td>
                        <a href="edit_user.php?id=<?= (int)$u['id'] ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>

                        <?php if ((int)$u['id'] === $my_id): ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">You</span>
                        <?php else: ?>
                            <form method="POST" action="users.php" style="display:inline;" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="delete_user" value="1">
                                <button type="submit" class="btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 40px;">
            <i class="bi bi-people-fill" style="font-size: 3rem; display: block; margin-bottom: 12px;"></i>
            No users found.
        </p>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const userTable = document.getElementById('userTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = userTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<?php include 'users_footer.php'; ?>
