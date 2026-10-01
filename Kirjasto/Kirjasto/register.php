<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'connection.php';

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

// Total active loans (to show library activity)
$loans_query = "SELECT COUNT(*) as total_loans FROM lainat WHERE tila = 'aktiivinen'";
$loans_result = $conn->query($loans_query);
$stats['total_loans'] = $loans_result ? $loans_result->fetch_assoc()['total_loans'] : 0;

// Calculate satisfaction rate (based on returned books vs total loans)
$returned_query = "SELECT COUNT(*) as returned FROM lainat WHERE tila = 'palautettu'";
$returned_result = $conn->query($returned_query);
$returned = $returned_result ? $returned_result->fetch_assoc()['returned'] : 0;
$total_loans_completed = $stats['total_loans'] + $returned;
$stats['satisfaction'] = $total_loans_completed > 0 ? round(($returned / $total_loans_completed) * 100) : 98;

// Format numbers (K for thousands)
function formatNumber($num) {
    if ($num >= 1000) {
        return round($num / 1000, 1) . 'k+';
    }
    return $num;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $etunimi = trim($_POST['first_name']);
    $sukunimi = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $puhelin = trim($_POST['phone']);
    $rooli = $_POST['role'];

    // Validation
    if (empty($etunimi)) $errors[] = "Etunimi on pakollinen";
    if (empty($sukunimi)) $errors[] = "Sukunimi on pakollinen";
    if (empty($email)) {
        $errors[] = "Sähköposti on pakollinen";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Syötä kelvollinen sähköpostiosoite";
    }
    if (empty($password)) {
        $errors[] = "Salasana on pakollinen";
    } elseif (strlen($password) < 6) {
        $errors[] = "Salasanan tulee olla vähintään 6 merkkiä pitkä";
    }
    if ($password !== $confirm_password) $errors[] = "Salasanat eivät täsmää";

    $valid_roles = ['user', 'manager', 'admin'];
    if (!in_array($rooli, $valid_roles)) {
        $errors[] = "Virheellinen rooli valittu";
    }

    if (empty($errors)) {
        try {
            $check_sql = "SELECT id FROM jasenet WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $errors[] = "Sähköposti on jo käytössä";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $jasennumero = 'KIR' . date('Ymd') . rand(1000, 9999);
                $liittymispaiva = date('Y-m-d');

                $insert_sql = "INSERT INTO jasenet (etunimi, sukunimi, email, password, puhelin, jasennumero, liittymispaiva, rooli, jasentyyppi, tila)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'perus', 'aktiivinen')";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("ssssssss", $etunimi, $sukunimi, $email, $hashed_password, $puhelin, $jasennumero, $liittymispaiva, $rooli);

                if ($insert_stmt->execute()) {
                    $_SESSION['success'] = "Rekisteröinti onnistui! Voit nyt kirjautua sisään.";
                    header("Location: login.php");
                    exit();
                } else {
                    $errors[] = "Rekisteröinti epäonnistui: " . $conn->error;
                }
            }
        } catch (Exception $e) {
            $errors[] = "Tietokantavirhe: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekisteröidy | Kirjasto</title>
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
        .register-container {
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

        /* Left Side - Premium Hero with Background Image */
        .premium-hero {
            flex: 1;
            background: linear-gradient(145deg, rgba(26, 26, 46, 0.92), rgba(22, 33, 62, 0.92));
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .premium-hero::before {
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

        /* Premium Features */
        .premium-features {
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

        /* Hero Stats - DYNAMIC */
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

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            transition: all 0.2s;
            pointer-events: none;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #f8fafc;
        }

        .input-group select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
        }

        .input-group input:focus,
        .input-group select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
        }

        .input-group input:focus + i,
        .input-group select:focus + i {
            color: #667eea;
        }

        /* Password Strength */
        .password-strength {
            margin-top: 10px;
        }

        .strength-bar {
            height: 4px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 6px;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .strength-fill.weak { width: 33%; background: #ef4444; }
        .strength-fill.medium { width: 66%; background: #f59e0b; }
        .strength-fill.strong { width: 100%; background: #10b981; }

        .strength-text {
            font-size: 0.7rem;
            color: #64748b;
        }

        .password-match {
            font-size: 0.75rem;
            margin-top: 6px;
        }

        .match-success { color: #10b981; }
        .match-error { color: #ef4444; }

        /* Terms */
        .terms-checkbox {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 25px 0;
        }

        .terms-checkbox input {
            width: 20px;
            height: 20px;
            accent-color: #667eea;
            cursor: pointer;
        }

        .terms-checkbox label {
            color: #64748b;
            font-size: 0.85rem;
        }

        .terms-checkbox a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .terms-checkbox a:hover {
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
            margin-top: 10px;
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

        /* Login Link */
        .login-section {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
        }

        .login-section p {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 12px;
        }

        .login-link-btn {
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

        .login-link-btn:hover {
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
            .register-container {
                flex-direction: column;
                max-width: 600px;
            }
            .premium-hero {
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
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
            .premium-features {
                gap: 15px;
            }
            body {
                padding: 10px;
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

<div class="register-container">
    <!-- Left Side - Hero Section -->
    <div class="premium-hero">
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
                <i class="fas fa-star"></i> Tervetuloa kirjastoon
            </div>
            <h1>Liity <span>jäseneksi</span></h1>
            <p>Avaa ovet rajattomaan lukemisen iloon ja digitaalisiin palveluihin</p>
        </div>

        <div class="premium-features">
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

        <!-- DYNAMIC STATS FROM DATABASE -->
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
                <div class="stat-number"><?php echo $stats['satisfaction']; ?>%</div>
                <div class="stat-label">Asiakastyytyväisyys</div>
            </div>
        </div>
    </div>

    <!-- Right Side - Form Container -->
    <div class="form-container">
        <div class="form-header">
            <h2>Luo tunnukset</h2>
            <p>Täytä tiedot ja aloita lukuseikkailusi</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <?php foreach ($errors as $error): ?>
                        <p style="margin: 2px 0;"><?php echo $error; ?></p>
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

        <form method="POST" action="" id="registerForm">
            <div class="form-grid">
                <div class="form-group">
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="first_name" placeholder="Etunimi"
                               value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="last_name" placeholder="Sukunimi"
                               value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-group full-width">
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Sähköposti"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
            </div>

            <div class="form-group full-width">
                <div class="input-group">
                    <i class="fas fa-phone"></i>
                    <input type="tel" name="phone" placeholder="Puhelin (vapaaehtoinen)"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>
            </div>

            <div class="form-group full-width">
                <div class="input-group">
                    <i class="fas fa-user-tag"></i>
                    <select name="role" required>
                        <option value="user" <?php echo (isset($_POST['role']) && $_POST['role'] == 'user') ? 'selected' : ''; ?>>Peruskäyttäjä</option>
                        <option value="manager" <?php echo (isset($_POST['role']) && $_POST['role'] == 'manager') ? 'selected' : ''; ?>>Manager (hallinnoija)</option>
                        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Admin (ylläpitäjä)</option>
                    </select>
                </div>
            </div>

            <div class="form-group full-width">
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" placeholder="Salasana (vähintään 6 merkkiä)" required>
                </div>
                <div class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Salasanan tulee olla vähintään 6 merkkiä</div>
                </div>
            </div>

            <div class="form-group full-width">
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Vahvista salasana" required>
                </div>
                <div class="password-match" id="passwordMatch"></div>
            </div>

            <div class="terms-checkbox">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">
                    Hyväksyn <a href="#" onclick="return false;">käyttöehdot</a> ja <a href="#" onclick="return false;">tietosuojakäytännön</a>
                </label>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">
                <i class="fas fa-user-plus"></i>
                <span>Luo tunnukset</span>
            </button>
        </form>

        <div class="login-section">
            <p>Onko sinulla jo tunnukset?</p>
            <a href="login.php" class="login-link-btn">
                <i class="fas fa-sign-in-alt"></i>
                <span>Kirjaudu sisään</span>
            </a>
        </div>
    </div>
</div>

<script>
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    const passwordMatch = document.getElementById('passwordMatch');
    const submitBtn = document.getElementById('submitBtn');
    const registerForm = document.getElementById('registerForm');

    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let message = '';

        if (password.length >= 6) strength++;
        if (password.length >= 10) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        strengthFill.className = 'strength-fill';
        if (strength <= 2) {
            strengthFill.classList.add('weak');
            message = 'Heikko salasana';
        } else if (strength <= 4) {
            strengthFill.classList.add('medium');
            message = 'Keskiverto salasana';
        } else {
            strengthFill.classList.add('strong');
            message = 'Vahva salasana!';
        }

        strengthText.textContent = message;
        checkPasswordMatch();
    });

    function checkPasswordMatch() {
        if (confirmInput.value === '') {
            passwordMatch.textContent = '';
            passwordMatch.className = 'password-match';
        } else if (passwordInput.value === confirmInput.value) {
            passwordMatch.textContent = '✓ Salasanat täsmäävät';
            passwordMatch.className = 'password-match match-success';
        } else {
            passwordMatch.textContent = '✗ Salasanat eivät täsmää';
            passwordMatch.className = 'password-match match-error';
        }
    }

    passwordInput.addEventListener('input', checkPasswordMatch);
    confirmInput.addEventListener('input', checkPasswordMatch);

    registerForm.addEventListener('submit', function(e) {
        const terms = document.getElementById('terms').checked;
        if (!terms) {
            e.preventDefault();
            alert('Sinun tulee hyväksyä käyttöehdot.');
            return;
        }
        if (passwordInput.value !== confirmInput.value) {
            e.preventDefault();
            alert('Salasanat eivät täsmää!');
            return;
        }
        if (passwordInput.value.length < 6) {
            e.preventDefault();
            alert('Salasanan tulee olla vähintään 6 merkkiä!');
            return;
        }

        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fas fa-spinner"></i><span>Luodaan tunnuksia...</span>';
        submitBtn.disabled = true;
    });

    document.querySelector('input[name="first_name"]').focus();
</script>

</body>
</html>
