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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $role_id    = (int)($_POST['role_id'] ?? 0);
    $password   = $_POST['password'] ?? '';
    $is_active  = isset($_POST['is_active']) ? 1 : 0;

    if ($first_name === '' || $last_name === '' || $email === '' || $role_id <= 0 || $password === '') {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        try {
            // Check if email is already taken
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = "Email is already registered.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (role_id, first_name, last_name, email, phone, password_hash, is_active, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $role_id,
                    $first_name,
                    $last_name,
                    $email,
                    $phone,
                    $hash,
                    $is_active,
                    $is_active ? 'active' : 'inactive'
                ]);

                $_SESSION['flash_success'] = "User created: " . $first_name . ' ' . $last_name;
                header("Location: users.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch roles for the dropdown
$roles = [];
try {
    $roles = $pdo->query("SELECT id, name FROM roles ORDER BY id")->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Add User';
include 'users_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-person-plus"></i> Add New User</h5>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="first_name" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="last_name" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Email <span style="color:#ef4444;">*</span></label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control">
            </div>

            <div class="col-md-6">
                <label class="form-label">Role <span style="color:#ef4444;">*</span></label>
                <select name="role_id" class="form-select" required>
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Password <span style="color:#ef4444;">*</span></label>
                <input type="password" name="password" class="form-control" required minlength="6" placeholder="At least 6 characters">
            </div>

            <div class="col-md-12">
                <div class="form-check">
                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" checked>
                    <label class="form-check-label" for="is_active">
                        Account is active
                    </label>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Create User</button>
            <a href="users.php" class="btn btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back to Users</a>
        </div>
    </form>
</div>

<?php include 'users_footer.php'; ?>
