<?php
// login.php — Login with username, email, OR card number
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('login');

// Already logged in? Go to dashboard.
if (current_role() !== 'guest') {
    switch (current_role()) {
        case 'admin':     header('Location: admin_dashboard.php');     exit;
        case 'librarian': header('Location: librarian_dashboard.php'); exit;
        case 'member':    header('Location: member_dashboard.php');    exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your credentials.';
    } else {
        // Look up by username, email, OR card number
        $stmt = $pdo->prepare(
            "SELECT * FROM users
             WHERE username = ? OR email = ? OR card_number = ?
             LIMIT 1"
        );
        $stmt->execute([$identifier, $identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && !empty($user['password']) && password_verify($password, $user['password'])) {

            // Status check
            if ($user['status'] !== 'active') {
                $error = 'Your account is ' . htmlspecialchars($user['status']) . '. Contact the library.';
            } else {
                // Update last_login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                    ->execute([(int)$user['id']]);

                // Set session
                session_regenerate_id(true);
                $_SESSION['user_id']    = (int)$user['id'];
                $_SESSION['username']   = $user['username'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name']  = $user['last_name'];
                $_SESSION['role']       = strtolower(trim($user['role']));
                $_SESSION['language']   = $user['language'] ?? 'en';

                // Audit log
                $log = $pdo->prepare(
                    "INSERT INTO activity_log (user_id, action, description, ip_address)
                     VALUES (?, 'login', ?, ?)"
                );
                $log->execute([
                    (int)$user['id'],
                    "User {$user['username']} logged in",
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);

                // Redirect by role
                switch ($_SESSION['role']) {
                    case 'admin':     header('Location: admin_dashboard.php');     exit;
                    case 'librarian': header('Location: librarian_dashboard.php'); exit;
                    case 'member':    header('Location: member_dashboard.php');    exit;
                    default:
                        session_destroy();
                        $error = 'Unknown role.';
                }
            }
        } else {
            $error = 'Invalid credentials.';
        }
    }
}

$page_title = t('login');
$hide_welcome_banner = true;
include 'header.php';
?>

<div class="table-card" style="max-width:480px;margin:0 auto;">
    <h5 style="text-align:center;font-size:1.4rem;">
        <i class="bi bi-box-arrow-in-right"></i> <?= htmlspecialchars(t('login')) ?>
    </h5>

    <?php if ($error): ?>
        <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:16px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div style="margin-bottom:14px;">
            <label style="display:block;font-weight:600;margin-bottom:6px;">
                <?= htmlspecialchars(t('login_with')) ?>
            </label>
            <input type="text" name="identifier" required autofocus
                   value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                   placeholder="username / email / LIB-2026-00001"
                   style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
        </div>

        <div style="margin-bottom:20px;">
            <label style="display:block;font-weight:600;margin-bottom:6px;"><?= htmlspecialchars(t('login')) ?></label>
            <input type="password" name="password" required
                   style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;">
            <a href="register.php" style="color:var(--accent-dark);text-decoration:none;font-weight:600;">
                <?= htmlspecialchars(t('register')) ?> →
            </a>
            <button type="submit" class="btn-add">
                <i class="bi bi-box-arrow-in-right"></i> <?= htmlspecialchars(t('login')) ?>
            </button>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
