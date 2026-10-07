<?php
// register.php — New member registration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('register');

// Already logged in? Go to dashboard.
if (current_role() !== 'guest') {
    switch (current_role()) {
        case 'admin':     header('Location: admin_dashboard.php');     exit;
        case 'librarian': header('Location: librarian_dashboard.php'); exit;
        case 'member':    header('Location: member_dashboard.php');    exit;
    }
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    if ($username === '' || $email === '' || $password === '' || $firstName === '' || $lastName === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $error = 'Username must be 3–50 characters (letters, numbers, dot, dash, underscore).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($phone !== '' && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
        $error = 'Invalid phone number.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $dup = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $dup->execute([$username, $email]);
        if ($dup->fetchColumn()) {
            $error = 'Username or email is already registered.';
        }
    }

    if (!$error) {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (username, email, password, first_name, last_name, phone, role, status, language)
                 VALUES (?, ?, ?, ?, ?, ?, 'member', 'active', ?)"
            );
            $stmt->execute([
                $username, $email, $hashed, $firstName, $lastName,
                $phone ?: null, current_language()
            ]);
            $newId = (int)$pdo->lastInsertId();

            // Audit log
            $log = $pdo->prepare(
                "INSERT INTO activity_log (user_id, action, description) VALUES (?, ?, ?)"
            );
            $log->execute([$newId, 'register', "New member registered: {$username}"]);

            // Auto-login
            session_regenerate_id(true);
            $_SESSION['user_id']    = $newId;
            $_SESSION['username']   = $username;
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name']  = $lastName;
            $_SESSION['role']       = 'member';

            header('Location: member_dashboard.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$page_title = t('register');
$hide_welcome_banner = true;
include 'header.php';
?>

<div class="table-card" style="max-width:640px;margin:0 auto;">
    <h5 style="text-align:center;font-size:1.4rem;">
        <i class="bi bi-person-plus-fill"></i> <?= htmlspecialchars(t('register')) ?>
    </h5>

    <?php if ($error): ?>
        <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:16px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">

            <div>
                <label style="display:block;font-weight:600;margin-bottom:6px;">First Name *</label>
                <input type="text" name="first_name" required maxlength="100"
                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                       style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
            </div>

            <div>
                <label style="display:block;font-weight:600;margin-bottom:6px;">Last Name *</label>
                <input type="text" name="last_name" required maxlength="100"
                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                       style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
            </div>

            <div style="grid-column:span 2;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Username *</label>
                <input type="text" name="username" required maxlength="50"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
            </div>

            <div style="grid-column:span 2;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Email *</label>
                <input type="email" name="email" required maxlength="150"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
            </div>

            <div style="grid-column:span 2;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Phone</label>
                <input type="tel" name="phone" maxlength="50"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
            </div>

            <div>
                <label style="display:block;font-weight:600;margin-bottom:6px;">Password *</label>
                <input type="password" name="password" required minlength="6"
                       style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
            </div>

            <div>
                <label style="display:block;font-weight:600;margin-bottom:6px;">Confirm Password *</label>
                <input type="password" name="confirm_password" required minlength="6"
                       style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:22px;">
            <a href="login.php" style="color:var(--accent-dark);text-decoration:none;font-weight:600;">
                ← <?= htmlspecialchars(t('login')) ?>
            </a>
            <button type="submit" class="btn-add">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars(t('register')) ?>
            </button>
        </div>
    </form>
</div>

<?php include 'footer.php'; ?>
