<?php
// admin_login.php — Staff/Admin login

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

// If already logged in, redirect to dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("
              SELECT id, username, password_hash, full_name, full_name_fi, full_name_fa, role, branch_id, is_active
                FROM admin_users
                WHERE username = ? OR email = ?
               LIMIT 1
            ");
            $stmt->execute([$username, $username]);
            $admin = $stmt->fetch();

            if (!$admin || !$admin['is_active']) {
                $error = 'Wrong username or password.';
            } elseif (!password_verify($password, $admin['password_hash'])) {
                $error = 'Wrong username or password.';
            } else {
                // Success
                $_SESSION['admin_id']        = (int)$admin['id'];
                $_SESSION['admin_username']  = $admin['username'];

                // Pick name based on current language
                $lang_now = current_language();
                $display_name = $admin['full_name'];
                if ($lang_now === 'fi' && !empty($admin['full_name_fi'])) $display_name = $admin['full_name_fi'];
                if ($lang_now === 'fa' && !empty($admin['full_name_fa'])) $display_name = $admin['full_name_fa'];
                $_SESSION['admin_name'] = $display_name;

                $_SESSION['admin_role']      = $admin['role'];
                $_SESSION['admin_branch_id'] = $admin['branch_id'];

                try {
                    $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")
                        ->execute([$admin['id']]);
                } catch (PDOException $e) {}

                header('Location: ' . SITE_URL . 'admin_dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('admin_login error: ' . $e->getMessage());
            $error = 'Login failed. Please try again.';
        }
    }
}

$page_title = 'Admin Login';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-page">
    <div class="auth-card">
        <div class="auth-icon"><i class="fas fa-user-shield"></i></div>
        <h1 class="auth-title"><?= e(t('admin_login_title')) ?></h1>
        <p class="auth-subtitle"><?= e(t('admin_login_subtitle')) ?></p>

        <?php if ($error): ?>
            <div class="auth-error">
                <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="admin_login.php">
            <div class="auth-field">
                <label for="username"><?= e(t('admin_username')) ?></label>
                <input type="text" id="username" name="username" required autofocus
                       value="<?= e($_POST['username'] ?? '') ?>">
            </div>

            <div class="auth-field">
                <label for="password"><?= e(t('login_password')) ?></label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-accent btn-block auth-submit">
                <i class="fas fa-right-to-bracket"></i> Login
            </button>
        </form>

        <div class="auth-footer">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i> <?= e(t('admin_back_to_site')) ?>
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
