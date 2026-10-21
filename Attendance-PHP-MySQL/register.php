<?php
// register.php — Create new user account (Modern)

require_once __DIR__ . '/includes/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($username === '')  $errors[] = 'Username is required.';
    if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) $errors[] = 'Username: only letters, numbers, dots, dashes, underscores.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($full_name === '') $errors[] = 'Full name is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) $errors[] = 'Username or email already exists.';
        } catch (PDOException $e) {
            $errors[] = 'Database error. Please try again.';
        }
    }

    if (empty($errors)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // Role is strictly 'employee'. Admin assigns department/branch later.
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, 'employee')");
            $stmt->execute([$username, $email, $hash, $full_name]);
            $success = true;
        } catch (PDOException $e) {
            error_log('register: ' . $e->getMessage());
            $errors[] = 'Could not create account. Try again.';
        }
    }
}

$page_title = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hide navbar on register page -->
<style>
    .top-nav, .site-footer { display: none !important; }
    body { padding: 0 !important; background: linear-gradient(135deg, var(--bg) 0%, var(--bg-alt) 100%) !important; }
</style>

<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    
    <div class="admin-panel" style="width: 100%; max-width: 600px; padding: 40px; border-radius: var(--radius-xl); box-shadow: var(--shadow-lg);">
        
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); color: #fff; display: grid; place-items: center; font-size: 1.6rem; box-shadow: 0 8px 24px rgba(37,99,235,0.3);">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1 style="font-size: 1.6rem; font-weight: 800; margin-bottom: 6px; color: var(--text);">Create Account</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Join the attendance system</p>
        </div>

        <?php if ($success): ?>
            <div class="flash flash-success" style="margin-bottom: 20px; border-radius: var(--radius-sm);">
                <i class="fas fa-check-circle"></i> Account created! <a href="login.php" style="color:inherit;text-decoration:underline;margin-left:6px;">Login now →</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="flash flash-error" style="margin-bottom: 20px; display:block; border-radius: var(--radius-sm);">
                <?php foreach ($errors as $err): ?>
                    <div style="margin:4px 0;"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
            <form method="post">
                <!-- 2-Column Grid for Top Fields -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div>
                        <label style="font-size: 0.85rem; font-weight: 600; color: var(--text); margin-bottom: 6px; display: block;">Full Name</label>
                        <input type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>" placeholder="John Doe" style="width: 100%; padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-alt); color: var(--text);">
                    </div>
                    <div>
                        <label style="font-size: 0.85rem; font-weight: 600; color: var(--text); margin-bottom: 6px; display: block;">Username</label>
                        <input type="text" name="username" required value="<?= e($_POST['username'] ?? '') ?>" placeholder="johndoe" style="width: 100%; padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-alt); color: var(--text);">
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--text); margin-bottom: 6px; display: block;">Email Address</label>
                    <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="john@example.com" style="width: 100%; padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-alt); color: var(--text);">
                </div>

                <!-- 2-Column Grid for Passwords -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <label style="font-size: 0.85rem; font-weight: 600; color: var(--text); margin-bottom: 6px; display: block;">Password</label>
                        <input type="password" name="password" required minlength="6" placeholder="••••••••" style="width: 100%; padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-alt); color: var(--text);">
                    </div>
                    <div>
                        <label style="font-size: 0.85rem; font-weight: 600; color: var(--text); margin-bottom: 6px; display: block;">Confirm Password</label>
                        <input type="password" name="password2" required minlength="6" placeholder="••••••••" style="width: 100%; padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-alt); color: var(--text);">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 1rem; border-radius: var(--radius-sm);">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border); font-size: 0.88rem; color: var(--text-muted);">
            Already have an account? <a href="login.php" style="color: var(--primary); font-weight: 600;">Login here</a>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
