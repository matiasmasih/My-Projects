<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (current_role() !== 'admin') {
    header("Location: manager_dashboard.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: users.php");
    exit;
}

// Fetch user
try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.name AS role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        header("Location: users.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$error = '';
$my_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $role_id    = (int)($_POST['role_id'] ?? 0);
    $is_active  = isset($_POST['is_active']) ? 1 : 0;
    $new_pass   = $_POST['new_password'] ?? '';

    if ($first_name === '' || $last_name === '' || $email === '' || $role_id <= 0) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif ($new_pass !== '' && strlen($new_pass) < 6) {
        $error = "New password must be at least 6 characters.";
    } elseif ($id === $my_id && !$is_active) {
        $error = "You cannot deactivate your own account.";
    } else {
        try {
            // Check email uniqueness
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $id]);
            if ($check->fetch()) {
                $error = "Email is already used by another user.";
            } else {
                // Build update
                if ($new_pass !== '') {
                    $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users SET
                            first_name = ?, last_name = ?, email = ?, phone = ?,
                            role_id = ?, is_active = ?, status = ?, password_hash = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $first_name, $last_name, $email, $phone, $role_id,
                        $is_active, $is_active ? 'active' : 'inactive',
                        $hash, $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users SET
                            first_name = ?, last_name = ?, email = ?, phone = ?,
                            role_id = ?, is_active = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $first_name, $last_name, $email, $phone, $role_id,
                        $is_active, $is_active ? 'active' : 'inactive',
                        $id
                    ]);
                }

                $_SESSION['flash_success'] = "User updated successfully.";
                header("Location: users.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch roles
$roles = [];
try {
    $roles = $pdo->query("SELECT id, name FROM roles ORDER BY id")->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Edit User';
include 'users_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-pencil-square"></i> Edit User — <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></h5>

    <?php if ($id === $my_id): ?>
    <div style="background:#fef3c7; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:0.9rem;">
        <i class="bi bi-info-circle"></i> You are editing <strong>your own account</strong>.
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Email <span style="color:#ef4444;">*</span></label>
                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Role <span style="color:#ef4444;">*</span></label>
                <select name="role_id" class="form-select" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === (int)$user['role_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password" minlength="6">
                <small style="color: var(--text-muted); font-size:0.8rem;">Only fill this if you want to reset the password.</small>
            </div>

            <div class="col-md-12">
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" <?= !empty($user['is_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Account is active</label>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Changes</button>
            <a href="users.php" class="btn btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back to Users</a>
        </div>
    </form>
</div>

<?php include 'users_footer.php'; ?>
