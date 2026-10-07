<?php
session_start();
require_once 'connection.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    // Validation
    if (empty($email)) {
        $errors[] = "Sähköposti on pakollinen";
    }

    if (empty($password)) {
        $errors[] = "Salasana on pakollinen";
    }

    if (empty($errors)) {
        try {
            $sql = "SELECT * FROM jasenet WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['first_name'] = $user['etunimi'];
                $_SESSION['last_name'] = $user['sukunimi'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['membership_number'] = $user['jasennumero'];
                $_SESSION['rooli'] = $user['rooli'];

                if ($remember) {
                    setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), "/");
                }

                // Redirect based on role
                if ($user['rooli'] == 'admin' || $user['rooli'] == 'manager') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit();
            } else {
                $errors[] = "Virheellinen sähköposti tai salasana";
            }
        } catch (Exception $e) {
            $errors[] = "Tietokantavirhe: " . $e->getMessage();
        }
    }
}

$remembered_email = isset($_COOKIE['remember_email']) ? $_COOKIE['remember_email'] : '';

// Fetch dynamic statistics from database
$stats = [];

// Total books count
$books_query = "SELECT COUNT(*) as total_books FROM kirjat";
$books_result = $conn->query($books_query);
$stats['total_books'] = $books_result ? $books_result->fetch_assoc()['total_books'] : 0;

// Total active members
$members_query = "SELECT COUNT(*) as total_members FROM jasenet WHERE tila = 'aktiivinen'";
$members_result = $conn->query($members_query);
$stats['total_members'] = $members_result ? $members_result->fetch_assoc()['total_members'] : 0;

// Format numbers (K for thousands)
function formatNumber($num) {
    if ($num >= 1000) {
        return round($num / 1000, 1) . 'k+';
    }
    return $num;
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjaudu | Kirjasto</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        padding: 20px;
    }

    /* Library Background Image */
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        filter: brightness(0.35) blur(3px);
        z-index: -2;
    }

    body::after {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(0,0,0,0.6), rgba(0,0,0,0.4));
        z-index: -1;
    }

    /* Main Container */
    .login-container {
        max-width: 1300px;
        width: 100%;
        display: flex;
        background: rgba(255, 255, 255, 0.98);
        border-radius: 48px;
        overflow: hidden;
        box-shadow: 0 50px 80px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(2px);
        animation: fadeInScale 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes fadeInScale {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    /* Left Side - Hero Section */
    .hero-section {
        flex: 1;
        background: linear-gradient(145deg, rgba(26, 26, 46, 0.92), rgba(22, 33, 62, 0.92));
        padding: 50px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .hero-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('https://images.unsplash.com/photo-1521587760476-6c12a4b040da?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80');
        background-size: cover;
        background-position: center;
        opacity: 0.15;
        z-index: 0;
    }

    /* Library Logo */
    .library-logo {
        display: flex;
        align-items: center;
        gap: 15px;
        position: relative;
        z-index: 1;
    }

    .logo-circle {
        width: 55px;
        height: 55px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        color: white;
        box-shadow: 0 10px 25px rgba(102,126,234,0.3);
    }

    .logo-text h3 {
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .logo-text p {
        color: rgba(255,255,255,0.5);
        font-size: 0.75rem;
        letter-spacing: 1px;
    }

    /* Hero Main Content */
    .hero-main {
        position: relative;
        z-index: 1;
        margin: 60px 0;
    }

    .hero-badge {
        display: inline-block;
        background: rgba(102,126,234,0.2);
        backdrop-filter: blur(10px);
        padding: 6px 14px;
        border-radius: 40px;
        font-size: 0.75rem;
        color: #a78bfa;
        margin-bottom: 25px;
        border: 1px solid rgba(102,126,234,0.3);
    }

    .hero-main h1 {
        color: white;
        font-size: 3.5rem;
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: 20px;
        letter-spacing: -1.5px;
    }

    .hero-main h1 span {
        background: linear-gradient(135deg, #667eea, #f093fb);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .hero-main p {
        color: rgba(255,255,255,0.7);
        font-size: 1rem;
        line-height: 1.6;
        max-width: 80%;
    }

    /* Features */
    .features-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
        margin: 40px 0;
        position: relative;
        z-index: 1;
    }

    .feature-card {
        display: flex;
        align-items: center;
        gap: 18px;
        background: rgba(255,255,255,0.05);
        backdrop-filter: blur(10px);
        padding: 14px 20px;
        border-radius: 24px;
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.3s ease;
    }

    .feature-card:hover {
        background: rgba(255,255,255,0.1);
        transform: translateX(8px);
        border-color: rgba(102,126,234,0.5);
    }

    .feature-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
    }

    .feature-info h4 {
        color: white;
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .feature-info p {
        color: rgba(255,255,255,0.5);
        font-size: 0.7rem;
        margin: 0;
    }

    /* Hero Stats */
    .hero-stats {
        display: flex;
        gap: 40px;
        padding-top: 30px;
        border-top: 1px solid rgba(255,255,255,0.1);
        position: relative;
        z-index: 1;
    }

    .stat-block {
        text-align: left;
    }

    .stat-number {
        color: white;
        font-size: 1.8rem;
        font-weight: 800;
    }

    .stat-label {
        color: rgba(255,255,255,0.5);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Right Side - Form */
    .form-container {
        flex: 1.2;
        padding: 50px;
        background: white;
    }

    .form-header {
        text-align: left;
        margin-bottom: 35px;
    }

    .form-header h2 {
        font-size: 2rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 10px;
        letter-spacing: -0.5px;
    }

    .form-header p {
        color: #64748b;
        font-size: 0.9rem;
    }

    /* Form Groups - FIXED */
    .form-group {
        margin-bottom: 25px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        color: #334155;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .input-group {
        position: relative;
        width: 100%;
    }

    /* Left icon inside input */
    .input-group i:first-child {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1rem;
        transition: all 0.2s;
        pointer-events: none;
        z-index: 2;
    }

    /* Input field */
    .input-group input {
        width: 100%;
        padding: 14px 50px 14px 48px;
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        font-size: 0.95rem;
        transition: all 0.3s;
        background: #f8fafc;
    }

    .input-group input:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
    }

    .input-group input:focus + i:first-child {
        color: #667eea;
    }

    /* Password Toggle - FIXED POSITION */
    .password-toggle {
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #94a3b8;
        transition: color 0.2s;
        z-index: 10;
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
    }

    .password-toggle:hover {
        color: #667eea;
    }

    /* Form Options */
    .form-options {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .remember-checkbox {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        color: #64748b;
        font-size: 0.85rem;
    }

    .remember-checkbox input {
        width: 18px;
        height: 18px;
        accent-color: #667eea;
        cursor: pointer;
    }

    .forgot-link {
        color: #667eea;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: color 0.2s;
    }

    .forgot-link:hover {
        text-decoration: underline;
    }

    /* Submit Button */
    .submit-btn {
        width: 100%;
        padding: 16px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border: none;
        border-radius: 16px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(102,126,234,0.3);
    }

    .submit-btn:active {
        transform: translateY(0);
    }

    .submit-btn.loading {
        opacity: 0.7;
        pointer-events: none;
    }

    .submit-btn.loading i {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    /* Register Section */
    .register-section {
        text-align: center;
        margin-top: 30px;
        padding-top: 25px;
        border-top: 1px solid #e2e8f0;
    }

    .register-section p {
        color: #64748b;
        font-size: 0.85rem;
        margin-bottom: 12px;
    }

    .register-link-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 28px;
        background: #f1f5f9;
        border-radius: 40px;
        color: #334155;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.85rem;
        transition: all 0.3s;
    }

    .register-link-btn:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
        color: #667eea;
    }

    /* Alerts */
    .alert {
        padding: 14px 18px;
        border-radius: 16px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-error {
        background: #fef2f2;
        border-left: 4px solid #ef4444;
        color: #991b1b;
    }

    .alert-success {
        background: #f0fdf4;
        border-left: 4px solid #10b981;
        color: #065f46;
    }

    /* Responsive */
    @media (max-width: 1100px) {
        .login-container {
            flex-direction: column;
            max-width: 600px;
        }
        .hero-section {
            padding: 40px 35px;
        }
        .hero-main h1 {
            font-size: 2.5rem;
        }
        .hero-main p {
            max-width: 100%;
        }
        .form-container {
            padding: 40px 35px;
        }
        .hero-stats {
            gap: 25px;
            flex-wrap: wrap;
        }
    }

    @media (max-width: 600px) {
        body {
            padding: 10px;
        }
        .form-options {
            flex-direction: column;
            align-items: flex-start;
        }
        .hero-stats {
            gap: 20px;
        }
        .stat-number {
            font-size: 1.4rem;
        }
    }
</style>
</head>
<body>

<div class="login-container">
    <!-- Left Side - Hero Section -->
    <div class="hero-section">
        <div class="library-logo">
            <div class="logo-circle">
                <i class="fas fa-book-open"></i>
            </div>
            <div class="logo-text">
                <h3>Kirjasto</h3>
                <p>lukemisen iloa</p>
            </div>
        </div>

        <div class="hero-main">
            <div class="hero-badge">
                <i class="fas fa-star"></i> Tervetuloa takaisin
            </div>
            <h1>Kirjaudu <span>sisään</span></h1>
            <p>Pääse käsiksi kokoelman tuhansiin kirjoihin ja jatka lukuseikkailuasi</p>
        </div>

        <div class="features-list">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-infinity"></i></div>
                <div class="feature-info">
                    <h4>Rajaton lainaus</h4>
                    <p>Lainaa niin monta kirjaa kuin haluat</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-clock"></i></div>
                <div class="feature-info">
                    <h4>Helpot aukioloajat</h4>
                    <p>Palvelemme sinua joka päivä</p>
                </div>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                <div class="feature-info">
                    <h4>Moderni verkkokirjasto</h4>
                    <p>Toimii saumattomasti kaikilla laitteilla</p>
                </div>
            </div>
        </div>

        <!-- Dynamic Stats from Database -->
        <div class="hero-stats">
            <div class="stat-block">
                <div class="stat-number"><?php echo formatNumber($stats['total_books']); ?></div>
                <div class="stat-label">Kirjoja</div>
            </div>
            <div class="stat-block">
                <div class="stat-number"><?php echo formatNumber($stats['total_members']); ?></div>
                <div class="stat-label">Aktiivista jäsentä</div>
            </div>
            <div class="stat-block">
                <div class="stat-number">95%</div>
                <div class="stat-label">Asiakastyytyväisyys</div>
            </div>
        </div>
    </div>

    <!-- Right Side - Form Container -->
    <div class="form-container">
        <div class="form-header">
            <h2>Tervetuloa takaisin</h2>
            <p>Kirjaudu sisään jatkaaksesi lukemista</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <p><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label class="form-label">Sähköposti</label>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email"
                           name="email"
                           value="<?php echo !empty($remembered_email) ? htmlspecialchars($remembered_email) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>"
                           placeholder="nimi@esimerkki.fi"
                           required>
                </div>
            </div>

        <div class="form-group">
            <label class="form-label">Salasana</label>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password"
                       name="password"
                       id="password"
                       placeholder="********"
                       required>
                <span class="password-toggle" onclick="togglePassword()">
                    <i class="far fa-eye" id="toggleIcon"></i>
                </span>
            </div>
        </div>

        <div class="form-options">
            <label class="remember-checkbox">
                <input type="checkbox" name="remember" <?php echo !empty($remembered_email) ? 'checked' : ''; ?>>
                <span>Muista minut</span>
            </label>
            <a href="forgot_password.php" class="forgot-link">Unohditko salasanan?</a>
        </div>

            <button type="submit" class="submit-btn" id="submitBtn">
                <i class="fas fa-sign-in-alt"></i>
                <span>Kirjaudu sisään</span>
            </button>
        </form>

        <div class="register-section">
            <p>Eikö sinulla ole vielä tunnuksia?</p>
            <a href="register.php" class="register-link-btn">
                <i class="fas fa-user-plus"></i>
                <span>Luo uusi tili</span>
            </a>
        </div>
    </div>
</div>

<script>
    // Password visibility toggle - FIXED
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        if (passwordInput && toggleIcon) {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
                // Add animation effect
                toggleIcon.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    toggleIcon.style.transform = 'scale(1)';
                }, 200);
            } else {
                passwordInput.type = 'password';
                toggleIcon.className = 'fas fa-eye';
                // Add animation effect
                toggleIcon.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    toggleIcon.style.transform = 'scale(1)';
                }, 200);
            }
        }
    }

    // Form loading state
    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');

    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Kirjaudutaan...</span>';
            submitBtn.disabled = true;
        });
    }

    // Input focus effects with animation
    document.querySelectorAll('.input-group input').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.boxShadow = '0 0 0 3px rgba(102,126,234,0.2)';
            this.parentElement.style.transition = 'all 0.3s ease';
            // Add pulse animation to the icon
            const icon = this.parentElement.querySelector('i:first-child');
            if (icon) {
                icon.style.transform = 'scale(1.1)';
                icon.style.color = '#667eea';
                setTimeout(() => {
                    icon.style.transform = 'scale(1)';
                }, 200);
            }
        });

        input.addEventListener('blur', function() {
            this.parentElement.style.boxShadow = 'none';
            const icon = this.parentElement.querySelector('i:first-child');
            if (icon) {
                icon.style.color = '#94a3b8';
            }
        });
    });

    // Animated placeholder effect
    document.querySelectorAll('.input-group input').forEach(input => {
        const label = input.closest('.form-group')?.querySelector('.form-label');
        if (label) {
            input.addEventListener('focus', function() {
                label.style.transform = 'translateY(-2px)';
                label.style.color = '#667eea';
                label.style.transition = 'all 0.2s ease';
            });
            input.addEventListener('blur', function() {
                label.style.transform = 'translateY(0)';
                label.style.color = '#334155';
            });
        }
    });

    // Auto-focus email or password with smooth delay
    document.addEventListener('DOMContentLoaded', function() {
        // Add fade-in animation to form elements
        const formElements = document.querySelectorAll('.form-group, .form-options, .submit-btn, .register-section');
        formElements.forEach((el, index) => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = `opacity 0.4s ease ${index * 0.05}s, transform 0.4s ease ${index * 0.05}s`;
            setTimeout(() => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, 100);
        });

        // Focus on email or password
        const emailInput = document.querySelector('input[name="email"]');
        if (emailInput && !emailInput.value) {
            setTimeout(() => {
                emailInput.focus();
                // Add highlight effect on focus
                emailInput.parentElement.style.boxShadow = '0 0 0 3px rgba(102,126,234,0.2)';
                setTimeout(() => {
                    if (emailInput.parentElement) {
                        emailInput.parentElement.style.boxShadow = 'none';
                    }
                }, 1000);
            }, 300);
        } else {
            const passwordInput = document.querySelector('input[name="password"]');
            if (passwordInput) {
                setTimeout(() => {
                    passwordInput.focus();
                    passwordInput.parentElement.style.boxShadow = '0 0 0 3px rgba(102,126,234,0.2)';
                    setTimeout(() => {
                        if (passwordInput.parentElement) {
                            passwordInput.parentElement.style.boxShadow = 'none';
                        }
                    }, 1000);
                }, 300);
            }
        }
    });

    // Add typing animation effect on inputs
    document.querySelectorAll('.input-group input').forEach(input => {
        input.addEventListener('input', function() {
            const icon = this.parentElement.querySelector('i:first-child');
            if (icon && this.value.length > 0) {
                icon.style.transform = 'scale(1.05)';
                icon.style.color = '#10b981';
                setTimeout(() => {
                    if (icon) icon.style.transform = 'scale(1)';
                }, 200);
            } else if (icon) {
                icon.style.color = '#94a3b8';
            }
        });
    });

    // Shake animation for error messages
    const alertBox = document.querySelector('.alert-error');
    if (alertBox) {
        alertBox.style.animation = 'shake 0.5s ease';
        setTimeout(() => {
            alertBox.style.animation = '';
        }, 500);
    }

    // Add shake keyframe animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        .alert-error {
            animation: shake 0.5s ease;
        }
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
</script>

</body>
</html>
