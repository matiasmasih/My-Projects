<?php
// admin_login.php — Admin login
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

// Already logged in → dashboard
if (is_logged_in()) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = t('login_err_required');
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, email, role FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if (!$admin) {
            $error = t('login_err_invalid');
        } elseif ($password !== $admin['password']) {
            $error = t('login_err_invalid');
        } else {
            // Set session via auth helper
            login_admin($admin);

            // Legacy compatibility (old admin pages)
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_name']      = $admin['full_name'];

            header('Location: admin_dashboard.php');
            exit;
        }
    }
}

$page_title = t('login_title');
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-page">
    <div class="auth-card">
        <div class="auth-icon">
            <i class="fas fa-lock"></i>
        </div>

        <h1 class="auth-title"><?= htmlspecialchars(t('login_title')) ?></h1>
        <p class="auth-subtitle"><?= htmlspecialchars(t('login_subtitle')) ?></p>

        <?php if ($error): ?>
            <div class="auth-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php" class="auth-form">
            <div class="auth-field">
                <label for="username"><?= htmlspecialchars(t('login_username')) ?></label>
                <input type="text" id="username" name="username" required autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="auth-field">
                <label for="password"><?= htmlspecialchars(t('login_password')) ?></label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-accent auth-submit">
                <i class="fas fa-sign-in-alt"></i>
                <?= htmlspecialchars(t('login_button')) ?>
            </button>
        </form>

        <div class="auth-footer">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i>
                <?= htmlspecialchars(t('login_back')) ?>
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
