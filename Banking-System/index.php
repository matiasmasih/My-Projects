<?php
require_once __DIR__ . '/includes/config.php';

// If already logged in, redirect to their specific dashboard
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
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
}

$error = '';
$success = '';

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        
        // Log successful login
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, status) VALUES (?, 'login', ?, 'success')")
            ->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);
            
        // Redirect based on role
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
        // Log failed attempt
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, status) VALUES (NULL, 'login_attempt', ?, 'failed')")
            ->execute([$_SERVER['REMOTE_ADDR']]);
    }
}

// Handle Registration (Creates Customers only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    if ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // Role is strictly 'user' for public registrations
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?, 'user')");
            $stmt->execute([$first_name, $last_name, $email, $phone, $hash]);
            $success = 'Registration successful! You can now login.';
            
            $new_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, status) VALUES (?, 'register', ?, 'success')")
                ->execute([$new_id, $_SERVER['REMOTE_ADDR']]);
        }
    }
}

$page_title = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page-wrapper">
    <div class="auth-container">
        
        <?php if ($error): ?>
            <div class="flash flash-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="flash flash-success"><?= e($success) ?></div>
        <?php endif; ?>

        <!-- Login Form -->
        <div id="login-form">
            <h2 style="text-align: center; margin-bottom: 8px;">Welcome Back</h2>
            <p style="text-align: center; color: var(--text-muted); margin-bottom: 32px;">Sign in to your banking account</p>
            
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="name@example.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
            <div class="auth-footer">
                Don't have an account? <a href="#" onclick="toggleForms(event)">Register here</a>
            </div>
        </div>

        <!-- Register Form -->
        <div id="register-form" style="display: none;">
            <h2 style="text-align: center; margin-bottom: 8px;">Create Account</h2>
            <p style="text-align: center; color: var(--text-muted); margin-bottom: 32px;">Join Modern Bank today</p>
            
            <form method="post">
                <input type="hidden" name="action" value="register">
                <div class="grid-2" style="gap: 16px;">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" required placeholder="John">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required placeholder="Doe">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="name@example.com">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" required placeholder="+358 40 123 4567">
                </div>
                <div class="grid-2" style="gap: 16px;">
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required minlength="6" placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="password_confirm" required minlength="6" placeholder="••••••••">
                    </div>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            <div class="auth-footer">
                Already have an account? <a href="#" onclick="toggleForms(event)">Login here</a>
            </div>
        </div>

    </div>
</div>

<script>
    function toggleForms(e) {
        e.preventDefault();
        const login = document.getElementById('login-form');
        const register = document.getElementById('register-form');
        if (login.style.display === 'none') {
            login.style.display = 'block';
            register.style.display = 'none';
        } else {
            login.style.display = 'none';
            register.style.display = 'block';
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
