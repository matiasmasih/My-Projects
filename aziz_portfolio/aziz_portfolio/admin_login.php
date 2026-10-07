<?php
session_start();
require_once 'config.php';

// Get site info for design
$info_sql = "SELECT * FROM personal_info LIMIT 1";
$info_result = $conn->query($info_sql);
$info = $info_result->fetch_assoc();

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $sql = "SELECT id, username, password, full_name, role FROM admin_users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $admin = $result->fetch_assoc();
        
        if ($password == $admin['password']) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            
            header('Location: admin_dashboard.php');
            exit();
        } else {
            $error = 'Väärä salasana!';
        }
    } else {
        $error = 'Käyttäjätunnusta ei löytynyt!';
    }
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | <?php echo htmlspecialchars($info['full_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #010714;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(0, 229, 255, 0.15), transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(138, 43, 226, 0.15), transparent 50%),
                repeating-linear-gradient(45deg, rgba(255,255,255,0.02) 0px, rgba(255,255,255,0.02) 2px, transparent 2px, transparent 8px);
            z-index: -2;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(0,229,255,0.03)" stroke-width="2"/></svg>') repeat;
            opacity: 0.5;
            pointer-events: none;
            z-index: -1;
            animation: rotateBg 60s linear infinite;
        }

        @keyframes rotateBg {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Floating Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: rgba(0, 229, 255, 0.2);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); opacity: 0.3; }
            50% { transform: translateY(-50px) translateX(30px); opacity: 0.6; }
        }

        .login-container {
            width: 100%;
            max-width: 460px;
            padding: 20px;
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: rgba(3, 11, 39, 0.6);
            backdrop-filter: blur(20px);
            border-radius: 40px;
            padding: 48px 40px;
            border: 1px solid rgba(0, 229, 255, 0.25);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
        }

        .login-card:hover {
            border-color: rgba(0, 229, 255, 0.5);
            box-shadow: 0 30px 55px rgba(0, 229, 255, 0.1);
        }

        /* Logo Section */
        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(0, 229, 255, 0.3);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 10px 30px rgba(0, 229, 255, 0.3); }
            50% { transform: scale(1.05); box-shadow: 0 15px 40px rgba(0, 229, 255, 0.5); }
        }

        .logo-icon i {
            font-size: 32px;
            color: white;
        }

        .logo-section h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, #00e5ff);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .logo-section p {
            color: rgba(255,255,255,0.5);
            font-size: 14px;
        }

        /* Form */
        .form-group {
            margin-bottom: 24px;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #00e5ff;
            font-size: 18px;
            transition: all 0.3s;
        }

        .input-group input {
            width: 100%;
            padding: 16px 20px 16px 50px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(0, 229, 255, 0.25);
            border-radius: 30px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #00e5ff;
            background: rgba(0, 0, 0, 0.6);
            box-shadow: 0 0 20px rgba(0, 229, 255, 0.2);
            transform: translateY(-2px);
        }

        .input-group input:focus + i {
            color: #8a2be2;
        }

        /* Button */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 229, 255, 0.4);
            gap: 15px;
        }

        /* Links */
        .links {
            text-align: center;
            margin-top: 30px;
        }

        .links a {
            color: #00e5ff;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .links a:hover {
            color: #8a2be2;
            gap: 10px;
        }

        .register-link {
            margin-top: 15px;
        }

        .register-link a {
            color: rgba(255,255,255,0.7);
        }

        .register-link a:hover {
            color: #00e5ff;
        }

        /* Error Message */
        .error {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid #e74c3c;
            color: #e74c3c;
            padding: 12px 20px;
            border-radius: 30px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 25px 0;
            color: rgba(255,255,255,0.3);
            font-size: 12px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(0, 229, 255, 0.2);
        }

        .divider span {
            padding: 0 15px;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 35px 25px;
            }
            .logo-icon {
                width: 55px;
                height: 55px;
            }
            .logo-icon i {
                font-size: 26px;
            }
            .logo-section h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="particles" id="particles"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>Admin Portal</h1>
                <p>Kirjaudu hallintapaneeliin</p>
            </div>

            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" placeholder="Käyttäjätunnus" required>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Salasana" required>
                    </div>
                </div>
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Kirjaudu
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="divider">
                <span></span>
            </div>

            <div class="links">
                <a href="index.php">
                    <i class="fas fa-home"></i> Takaisin etusivulle
                </a>
            </div>
            <div class="register-link">
                <a href="admin_register.php">
                    <i class="fas fa-user-plus"></i> Luo uusi admin-tili
                </a>
            </div>
        </div>
    </div>

    <script>
        // Create floating particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                const size = Math.random() * 4 + 2;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 5 + 's';
                particle.style.animationDuration = Math.random() * 6 + 4 + 's';
                particlesContainer.appendChild(particle);
            }
        }
        createParticles();
    </script>
</body>
</html>
