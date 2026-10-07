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
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    
    // Validate
    if (empty($username)) {
        $error = 'Käyttäjätunnus on pakollinen';
    } elseif (strlen($username) < 3) {
        $error = 'Käyttäjätunnuksen on oltava vähintään 3 merkkiä';
    } elseif (empty($password)) {
        $error = 'Salasana on pakollinen';
    } elseif (strlen($password) < 4) {
        $error = 'Salasanan on oltava vähintään 4 merkkiä';
    } elseif ($password !== $confirm_password) {
        $error = 'Salasanat eivät täsmää';
    } elseif (empty($email)) {
        $error = 'Sähköposti on pakollinen';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Syötä kelvollinen sähköpostiosoite';
    } elseif (empty($full_name)) {
        $error = 'Koko nimi on pakollinen';
    } else {
        // Check if username already exists
        $check_sql = "SELECT id FROM admin_users WHERE username = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'Käyttäjätunnus on jo käytössä!';
        } else {
            // Check if email already exists
            $check_email_sql = "SELECT id FROM admin_users WHERE email = ?";
            $check_email_stmt = $conn->prepare($check_email_sql);
            $check_email_stmt->bind_param("s", $email);
            $check_email_stmt->execute();
            $check_email_result = $check_email_stmt->get_result();
            
            if ($check_email_result->num_rows > 0) {
                $error = 'Sähköposti on jo käytössä!';
            } else {
                // Create new admin (store password as plain text for now)
                $insert_sql = "INSERT INTO admin_users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, 'admin')";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ssss", $username, $password, $email, $full_name);
                
                if ($insert_stmt->execute()) {
                    $success = 'Tili luotu onnistuneesti! Voit nyt kirjautua sisään.';
                    // Clear form
                    $_POST = array();
                } else {
                    $error = 'Virhe: ' . $conn->error;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luo Admin Tili | <?php echo htmlspecialchars($info['full_name']); ?></title>
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

        .register-container {
            width: 100%;
            max-width: 500px;
            padding: 20px;
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .register-card {
            background: rgba(3, 11, 39, 0.6);
            backdrop-filter: blur(20px);
            border-radius: 40px;
            padding: 48px 40px;
            border: 1px solid rgba(0, 229, 255, 0.25);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
        }

        .register-card:hover {
            border-color: rgba(0, 229, 255, 0.5);
            box-shadow: 0 30px 55px rgba(0, 229, 255, 0.1);
        }

        /* Logo Section */
        .logo-section {
            text-align: center;
            margin-bottom: 35px;
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
            margin-bottom: 20px;
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
            font-size: 16px;
            transition: all 0.3s;
        }

        .input-group input {
            width: 100%;
            padding: 14px 20px 14px 48px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(0, 229, 255, 0.25);
            border-radius: 30px;
            color: #fff;
            font-size: 14px;
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
        .btn-register {
            width: 100%;
            padding: 14px;
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
            margin-top: 15px;
        }

        .btn-register:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 229, 255, 0.4);
            gap: 15px;
        }

        /* Links */
        .login-link {
            text-align: center;
            margin-top: 25px;
        }

        .login-link a {
            color: #00e5ff;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .login-link a:hover {
            color: #8a2be2;
            gap: 10px;
        }

        .back-link {
            text-align: center;
            margin-top: 15px;
        }

        .back-link a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s;
        }

        .back-link a:hover {
            color: #00e5ff;
        }

        /* Messages */
        .error, .success {
            padding: 12px 18px;
            border-radius: 30px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .error {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid #e74c3c;
            color: #e74c3c;
            animation: shake 0.5s ease;
        }

        .success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid #10b981;
            color: #10b981;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @media (max-width: 480px) {
            .register-card {
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
            .input-group input {
                padding: 12px 16px 12px 45px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1>Luo Admin Tili</h1>
                <p>Rekisteröidy hallintapaneeliin</p>
            </div>

            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="full_name" placeholder="Koko nimi" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="Sähköposti" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-group">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="username" placeholder="Käyttäjätunnus (min. 3 merkkiä)" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Salasana (min. 4 merkkiä)" required>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-group">
                        <i class="fas fa-check-circle"></i>
                        <input type="password" name="confirm_password" placeholder="Vahvista salasana" required>
                    </div>
                </div>
                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus"></i> Luo tili
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="login-link">
                <a href="admin_login.php">
                    <i class="fas fa-sign-in-alt"></i> Onko sinulla jo tili? Kirjaudu sisään
                </a>
            </div>
            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-home"></i> Takaisin etusivulle
                </a>
            </div>
        </div>
    </div>
</body>
</html>
