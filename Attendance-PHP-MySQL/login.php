<?php
// login.php — User login (Modern)

require_once __DIR__ . '/includes/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Please enter both login and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, email, password_hash, full_name, role, is_active FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if (!$user || !$user['is_active']) {
                $error = 'Wrong credentials or account disabled.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = 'Wrong credentials or account disabled.';
            } else {
                $_SESSION['user_id']       = (int)$user['id'];
                $_SESSION['user_username'] = $user['username'];
                $_SESSION['user_name']     = $user['full_name'];
                $_SESSION['user_role']     = $user['role'];

                try { $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]); } catch (PDOException $e) {}

                if ($user['role'] === 'admin' || $user['role'] === 'manager') {
                    header('Location: ' . SITE_URL . 'admin/dashboard.php');
                } else {
                    header('Location: ' . SITE_URL . 'employee/dashboard.php');
                }
                exit;
            }
        } catch (PDOException $e) {
            error_log('login: ' . $e->getMessage());
            $error = 'Login failed. Try again.';
        }
    }
}

$page_title = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hide navbar on login page -->
<style>
    .top-nav, .site-footer { display: none !important; }
    body { padding: 0 !important; background: linear-gradient(135deg, var(--bg) 0%, var(--bg-alt) 100%) !important; }
</style>

<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    
    <div class="admin-panel" style="width: 100%; max-width: 420px; padding: 40px; border-radius: var(--radius-xl); box-shadow: var(--shadow-lg);">
        
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); color: #fff; display: grid; place-items: center; font-size: 1.6rem; box-shadow: 0 8px 24px rgba(37,99,235,0.3);">
                <i class="fas fa-right-to-bracket"></i>
            </div>
            <h1 style="font-size: 1.6rem; font-weight: 800; margin-bottom: 6px; color: var(--text);">Welcome Back</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Sign in to your attendance account</p>
        </div>

        <?php if ($error): ?>
            <div class="flash flash-error" style="margin-bottom: 20px; border-radius: var(--radius-sm);">
                <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div style="margin-bottom: 16px;">
                <label style="font-size: 0.85rem; font-weight: 600; color: var(--text); margin-bottom: 6px; display: block;">Username or Email</label>
                <input type="text" name="login" required autofocus value="<?= e($_POST['login'] ?? '') ?>" placeholder="Enter your username" style="width: 100%; padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-alt); color: var(--text);">
            </div>

            <div style="margin-bottom: 24px;">
                <label style="font-size: 0.85rem; font-weight: 600; color: var(--text); margin-bottom: 6px; display: block;">Password</label>
                <input type="password" name="password" required placeholder="Enter your password" style="width: 100%; padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-alt); color: var(--text);">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 1rem; border-radius: var(--radius-sm);">
                <i class="fas fa-right-to-bracket"></i> Sign In
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border); font-size: 0.88rem; color: var(--text-muted);">
            Don't have an account? <a href="register.php" style="color: var(--primary); font-weight: 600;">Register here</a>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
