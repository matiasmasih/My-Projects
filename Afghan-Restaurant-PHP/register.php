<?php
// register.php — Create customer account

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (!empty($_SESSION['customer_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$errors = [];
$old = [
    'full_name' => '',
    'email'     => '',
    'phone'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email']     = trim($_POST['email'] ?? '');
    $old['phone']     = trim($_POST['phone'] ?? '');
    $password         = $_POST['password'] ?? '';
    $password2        = $_POST['password2'] ?? '';

    if ($old['full_name'] === '')       $errors[] = 'Full name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 6)          $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password2)       $errors[] = 'Passwords do not match.';

    // Email uniqueness
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
            $stmt->execute([$old['email']]);
            if ($stmt->fetch()) $errors[] = 'That email is already registered.';
        } catch (PDOException $e) {
            $errors[] = 'Could not check email. Try again.';
        }
    }

    if (empty($errors)) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO customers (email, password_hash, full_name, phone, preferred_lang, preferred_currency) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $old['email'],
                $hash,
                $old['full_name'],
                $old['phone'],
                current_language(),
                $_SESSION['currency'] ?? 'EUR',
            ]);

            $_SESSION['customer_id']    = (int)$pdo->lastInsertId();
            $_SESSION['customer_name']  = $old['full_name'];
            $_SESSION['customer_email'] = $old['email'];

            header('Location: ' . SITE_URL . 'index.php');
            exit;
        } catch (PDOException $e) {
            error_log('register.php error: ' . $e->getMessage());
            $errors[] = 'Could not create account. Try again.';
        }
    }
}

$page_title = t('nav_register');
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-page">
    <div class="auth-card" style="max-width:480px;">
        <div class="auth-icon"><i class="fas fa-user-plus"></i></div>
        <h1 class="auth-title"><?= e(t('nav_register')) ?></h1>
        <p class="auth-subtitle"><?= e(t('footer_tagline', 'Join us')) ?></p>

        <?php if (!empty($errors)): ?>
            <div class="auth-error" style="display:block;text-align:left;">
                <?php foreach ($errors as $err): ?>
                    <div><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="register.php">
            <div class="auth-field">
                <label for="full_name"><?= e(t('checkout_name')) ?></label>
                <input type="text" id="full_name" name="full_name" required
                       value="<?= e($old['full_name']) ?>">
            </div>

            <div class="auth-field">
                <label for="email"><?= e(t('checkout_email')) ?></label>
                <input type="email" id="email" name="email" required
                       value="<?= e($old['email']) ?>">
            </div>

            <div class="auth-field">
                <label for="phone"><?= e(t('checkout_phone')) ?></label>
                <input type="tel" id="phone" name="phone"
                       value="<?= e($old['phone']) ?>">
            </div>

            <div class="auth-field">
                <label for="password"><?= e(t('login_password')) ?></label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>

            <div class="auth-field">
                <label for="password2"><?= e(t('login_confirm_password')) ?></label>
                <input type="password" id="password2" name="password2" required minlength="6">
            </div>

            <button type="submit" class="btn btn-accent btn-block auth-submit">
                <i class="fas fa-user-plus"></i> <?= e(t('nav_register')) ?>
            </button>
        </form>

        <div class="auth-footer">
            <?= e(t('nav_login', 'Already have an account?')) ?>
            <a href="login.php"><?= e(t('nav_login')) ?></a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
