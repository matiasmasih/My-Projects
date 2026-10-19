<?php
// login.php — Customer login

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

// If already logged in, redirect
if (!empty($_SESSION['customer_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = t('login_error_fields', 'Please fill in both fields.');
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, preferred_lang, preferred_currency, is_active FROM customers WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !$user['is_active']) {
                $error = t('login_error_wrong', 'Wrong email or password.');
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = t('login_error_wrong', 'Wrong email or password.');
            } else {
                // Success
                $_SESSION['customer_id']       = (int)$user['id'];
                $_SESSION['customer_name']     = $user['full_name'];
                $_SESSION['customer_email']    = $user['email'];
                $_SESSION['language']          = $user['preferred_lang'] ?: current_language();
                $_SESSION['currency']          = $user['preferred_currency'] ?: 'EUR';

                // Update last_login
                try {
                    $pdo->prepare("UPDATE customers SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                } catch (PDOException $e) {}

                header('Location: ' . SITE_URL . 'index.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('login.php error: ' . $e->getMessage());
            $error = t('login_error_generic', 'Something went wrong. Please try again.');
        }
    }
}

$page_title = t('nav_login');
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-page">
    <div class="auth-card">
        <div class="auth-icon"><i class="fas fa-user"></i></div>
        <h1 class="auth-title"><?= e(t('nav_login')) ?></h1>
        <p class="auth-subtitle"><?= e(t('footer_tagline', 'Welcome back')) ?></p>

        <?php if ($error): ?>
            <div class="auth-error">
                <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="login.php">
            <div class="auth-field">
                <label for="email"><?= e(t('checkout_email')) ?></label>
                <input type="email" id="email" name="email" required autofocus
                       value="<?= e($_POST['email'] ?? '') ?>">
            </div>

            <div class="auth-field">
                <label for="password"><?= e(t('login_password')) ?></label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-accent btn-block auth-submit">
                <i class="fas fa-right-to-bracket"></i> <?= e(t('nav_login')) ?>
            </button>
        </form>

        <div class="auth-footer">
            <?= e(t('checkout_register', 'No account yet?')) ?>
            <a href="register.php"><?= e(t('nav_register')) ?></a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
