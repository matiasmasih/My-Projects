<?php
// index.php — Kirjasto 2026 Modern Login

require_once __DIR__ . '/includes/config.php';

// If already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $_SESSION['branch_id'] = $user['branch_id'] ?? null;

    if ($user && $user['role'] === 'admin') {
        header('Location: ' . SITE_URL . 'admin/index.php');
    } elseif ($user && $user['role'] === 'manager') {
        header('Location: ' . SITE_URL . 'manager/index.php');
    } elseif ($user && $user['role'] === 'staff') {
        header('Location: ' . SITE_URL . 'staff/index.php');
    } else {
        header('Location: ' . SITE_URL . 'user/dashboard.php');
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['branch_id'] = $user['branch_id'] ?? null;

                // Audit log
                $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'login', ?, ?)")
                    ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);

                if ($remember) {
                    setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/');
                }

                // Redirect
                if ($user['role'] === 'admin') {
                    header('Location: ' . SITE_URL . 'admin/index.php');
                } elseif ($user['role'] === 'manager') {
                    header('Location: ' . SITE_URL . 'manager/index.php');
                } elseif ($user['role'] === 'staff') {
                    header('Location: ' . SITE_URL . 'staff/index.php');
                } else {
                    header('Location: ' . SITE_URL . 'user/dashboard.php');
                }
                exit;
            } else {
                $error = 'Invalid email or password.';
                $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (NULL, 'login_failed', ?, ?)")
                    ->execute([$_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            }
        } catch (PDOException $e) {
            $error = 'Login error. Please try again.';
        }
    }
}

$page_title = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: calc(100vh - 80px); display: flex; align-items: center; justify-content: center; padding: 40px 20px; background: radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.08) 0%, transparent 40%), radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.06) 0%, transparent 40%);">
    <div style="width: 100%; max-width: 440px; background: var(--card-bg); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--border); border-radius: 24px; padding: 48px 40px; box-shadow: var(--shadow-lg);">
        
        <div style="text-align: center; margin-bottom: 32px;">
            <div style="width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 20px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); display: grid; place-items: center; color: #fff; font-size: 1.8rem; box-shadow: 0 10px 25px -5px var(--primary-glow);">
                <i class="fas fa-book-open"></i>
            </div>
            <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--text); margin-bottom: 8px; letter-spacing: -0.02em;">Welcome Back</h1>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Sign in to Kirjasto 2026</p>
        </div>

        <?php if ($error): ?>
            <div class="flash flash-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="flash flash-success"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required autofocus value="<?= e($_COOKIE['remember_email'] ?? '') ?>" placeholder="name@example.com">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                    <input type="checkbox" name="remember" style="width: 16px; height: 16px; accent-color: var(--primary);">
                    Remember me
                </label>
                <a href="#" style="font-size: 0.85rem; font-weight: 600; color: var(--primary);">Forgot password?</a>
            </div>
            <button type="submit" class="btn-primary" style="width: 100%;">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div style="text-align: center; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border); font-size: 0.9rem; color: var(--text-muted);">
            Don't have an account? <a href="<?= e(SITE_URL) ?>register.php" style="font-weight: 600; color: var(--primary);">Register here</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
