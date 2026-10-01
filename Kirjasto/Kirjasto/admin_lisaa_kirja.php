<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Database connection
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Hae käyttäjän tiedot
$user_id = $_SESSION['user_id'];
$user_query = "SELECT rooli, profile_image, etunimi, sukunimi FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

if (!$current_user) {
    die("Käyttäjää ei löytynyt!");
}

// Update session with latest profile image
if (isset($current_user['profile_image'])) {
    $_SESSION['profile_image'] = $current_user['profile_image'];
}

// Get user's full name for display with fallback
$etunimi = $current_user['etunimi'] ?? '';
$sukunimi = $current_user['sukunimi'] ?? '';
$kayttajan_nimi = trim($etunimi . ' ' . $sukunimi);

if (empty($kayttajan_nimi) || $kayttajan_nimi == ' ') {
    $kayttajan_nimi = "User";
}

// ================ PROFILE INFORMATION FROM DATABASE ================
$custom_name = $kayttajan_nimi;  // Uses actual name from database
$custom_email = $current_user['email'] ?? 'admin@example.com';  // Get email from database
$custom_role_display = ($current_user['rooli'] ?? 'admin') === 'admin' ? 'Ylläpitäjä' : 'Manager';
$custom_permissions = ($current_user['rooli'] ?? 'admin') === 'admin' ? 'Täydet järjestelmäoikeudet' : 'Rajoitetut oikeudet';
// ================ END PROFILE INFORMATION ================

// Profile image helper function
function getProfileImageUrl($profile_image, $user_name) {
    if (empty($user_name)) {
        $user_name = "User";
    }

    if (empty($profile_image)) {
        return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
    }

    if (filter_var($profile_image, FILTER_VALIDATE_URL)) {
        return $profile_image;
    }

    if (file_exists($profile_image)) {
        return $profile_image;
    }

    $profile_path = 'uploads/profiles/' . $profile_image;
    if (file_exists($profile_path)) {
        return $profile_path;
    }

    $filename = basename($profile_image);
    $profile_path = 'uploads/profiles/' . $filename;
    if (file_exists($profile_path)) {
        return $profile_path;
    }

    if (file_exists($filename)) {
        return $filename;
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
}

$profile_image_url = getProfileImageUrl($current_user['profile_image'] ?? '', $kayttajan_nimi);

// Initialize variables
$nimi = $tekija = $isbn = $kategoria = $julkaisuvuosi = $kustantaja = "";
$kokonaismaara = 1;
$success_message = "";
$error_message = "";
$errors = [];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_book'])) {
    // Sanitize inputs
    $nimi = trim($_POST['nimi'] ?? '');
    $tekija = trim($_POST['tekija'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $kategoria = trim($_POST['kategoria'] ?? '');
    $julkaisuvuosi = trim($_POST['julkaisuvuosi'] ?? '');
    $kustantaja = trim($_POST['kustantaja'] ?? '');
    $kokonaismaara = trim($_POST['kokonaismaara'] ?? '1');

    // Validation
    if (empty($nimi)) $errors[] = "Kirjan nimi on pakollinen";
    if (empty($tekija)) $errors[] = "Kirjoittajan nimi on pakollinen";

    if (!empty($kokonaismaara)) {
        if (!is_numeric($kokonaismaara) || $kokonaismaara < 1) {
            $errors[] = "Kokonaismäärän tulee olla positiivinen numero";
        } else {
            $kokonaismaara = (int)$kokonaismaara;
        }
    }

    if (!empty($julkaisuvuosi)) {
        $julkaisuvuosi = (int)$julkaisuvuosi;
        if ($julkaisuvuosi < 1000 || $julkaisuvuosi > date('Y')) {
            $errors[] = "Julkaisuvuoden tulee olla välillä 1000-" . date('Y');
        }
    }

    // If no errors, insert into database
    if (empty($errors)) {
        $sql = "INSERT INTO kirjat (nimi, tekija, isbn, kategoria, julkaisuvuosi, kustantaja, kokonaismaara)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $julkaisuvuosi_param = !empty($julkaisuvuosi) ? $julkaisuvuosi : NULL;

            $stmt->bind_param(
                "ssssisi",
                $nimi,
                $tekija,
                $isbn,
                $kategoria,
                $julkaisuvuosi_param,
                $kustantaja,
                $kokonaismaara
            );

            if ($stmt->execute()) {
                $success_message = "Kirja lisätty onnistuneesti!";
                // Clear form fields
                $nimi = $tekija = $isbn = $kategoria = $julkaisuvuosi = $kustantaja = "";
                $kokonaismaara = 1;
            } else {
                $error_message = "Virhe: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Valmistelu virhe: " . $conn->error;
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Get existing categories
$kategoriat = [];
$kategoria_query = "SELECT DISTINCT kategoria FROM kirjat WHERE kategoria IS NOT NULL AND kategoria != '' ORDER BY kategoria";
$kategoria_result = $conn->query($kategoria_query);
if ($kategoria_result) {
    while ($row = $kategoria_result->fetch_assoc()) {
        $kategoriat[] = $row['kategoria'];
    }
}

function getInitials($name) {
    if (empty($name)) return 'A';
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2);
}

$user_initials = getInitials($kayttajan_nimi);

$user_role = 'Järjestelmänkäyttäjä';
if (isset($current_user['rooli']) && strtolower($current_user['rooli']) === 'admin') {
    $user_role = 'Täydet Järjestelmäoikeudet';
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lisää uusi kirja - Admin | Kirjasto</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ============================================
           MODERN ADMIN DASHBOARD CSS
           ============================================ */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            position: relative;
        }

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
            filter: brightness(0.3) blur(2px);
            z-index: -2;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: -1;
        }

        /* SIDEBAR - FIXED */
        .sidebar {
            width: 280px;
            background: rgba(15, 25, 35, 0.95);
            backdrop-filter: blur(10px);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
            border-right: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }

        .sidebar::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 4px;
        }

        .sidebar-header {
            padding: 30px 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .logo-text h2 {
            font-size: 1.3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-text p {
            font-size: 0.7rem;
            color: #94a3b8;
        }

        .user-profile-mini {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-decoration: none;
            transition: all 0.3s;
        }

        .user-profile-mini:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .avatar-mini {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .avatar-mini img {
            width: 100%;
            height: 100%;
            border-radius: 12px;
            object-fit: cover;
        }

        .user-info-mini h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
            margin-bottom: 3px;
        }

        .user-info-mini p {
            font-size: 0.7rem;
            color: #94a3b8;
        }

        .sidebar-menu {
            padding: 20px 16px;
        }

        .menu-section {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #667eea;
            padding: 15px 16px 8px;
            letter-spacing: 1px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #b0b8c5;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            margin: 4px 0;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .menu-item i {
            width: 22px;
            font-size: 1rem;
        }

        .menu-item:hover {
            background: rgba(102, 126, 234, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .menu-item.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .logout-item {
            margin-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 20px;
        }

        .logout-item:hover {
            background: rgba(239, 68, 68, 0.15);
        }

        /* MAIN CONTENT - SCROLLS INDEPENDENTLY */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
            height: 100vh;
            overflow-y: auto;
            position: relative;
        }

        .main-content::-webkit-scrollbar {
            width: 6px;
        }
        .main-content::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
            border-radius: 3px;
        }
        .main-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 3px;
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-title p {
            color: #94a3b8;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            padding: 12px 25px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .user-avatar {
            width: 55px;
            height: 55px;
            border-radius: 15px;
            overflow: hidden;
            border: 2px solid #667eea;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-details h3 {
            font-size: 1rem;
            font-weight: 600;
            color: white;
            margin-bottom: 5px;
        }

        .user-details p {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        /* NOTIFICATIONS */
        .notification {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border-left: 4px solid;
        }

        .notification-success {
            border-left-color: #10b981;
            color: #10b981;
        }

        .notification-error {
            border-left-color: #ef4444;
            color: #ef4444;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* CONTENT CARD */
        .content-card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 35px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* SECTION HEADER */
        .section-header {
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            margin: 0 0 20px 0;
            padding-left: 12px;
            border-left: 3px solid #667eea;
        }

        /* FORM GRID */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            color: #94a3b8;
            font-size: 0.75rem;
            margin-bottom: 8px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .required::after {
            content: " *";
            color: #ef4444;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: white;
            font-size: 0.9rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-control::placeholder {
            color: #64748b;
        }

        select.form-control option {
            background: #1a1a2e;
        }

        .form-hint {
            display: block;
            margin-top: 6px;
            font-size: 0.7rem;
            color: #64748b;
        }

        /* DIVIDER */
        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.1), transparent);
            margin: 20px 0;
        }

        /* INFO BOX */
        .info-box {
            background: rgba(0,0,0,0.2);
            padding: 15px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .info-box p {
            color: #94a3b8;
            font-size: 0.8rem;
            margin-bottom: 8px;
        }

        .info-box p:last-child {
            margin-bottom: 0;
        }

        /* BUTTONS */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 0.85rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #94a3b8;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 2000;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .header {
                flex-direction: column;
                text-align: center;
            }
            .button-group {
                flex-direction: column;
            }
            .button-group .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon"><i class="fas fa-crown"></i></div>
            <div class="logo-text">
                <h2>Admin Panel</h2>
                <p>Kirjasto Hallinta</p>
            </div>
        </div>
    </div>

    <a href="admin_dashboard.php" class="user-profile-mini">
        <div class="avatar-mini">
            <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
        </div>
        <div class="user-info-mini">
            <h4><?php echo htmlspecialchars($custom_name); ?></h4>
            <p><?php echo htmlspecialchars($custom_role_display); ?></p>
        </div>
    </a>

    <div class="sidebar-menu">
        <div class="menu-section">⚙️ Päävalikko</div>
        <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i><span>Kojelauta</span></a>

        <div class="menu-section">📚 Kirjaston Hallinta</div>
        <a href="admin_manage_kirjat.php" class="menu-item"><i class="fas fa-book"></i><span>Hallinnoi Kirjoja</span></a>
        <a href="admin_lisaa_kirja.php" class="menu-item active"><i class="fas fa-plus"></i><span>Lisää Kirja</span></a>
        <a href="admin_muokkaa_kirjaa.php" class="menu-item"><i class="fas fa-edit"></i><span>Muokkaa Kirjoja</span></a>

        <div class="menu-section">👥 Jäsenten Hallinta</div>
        <a href="admin_kayttajien_hallinta.php" class="menu-item"><i class="fas fa-users"></i><span>Hallinnoi Jäseniä</span></a>
        <a href="register.php" class="menu-item"><i class="fas fa-user-plus"></i><span>Rekisteröi Jäsen</span></a>

        <div class="menu-section">🔄 Lainaushallinta</div>
        <a href="admin_lainat.php" class="menu-item"><i class="fas fa-list"></i><span>Hallinnoi Lainoja</span></a>
        <a href="admin_varaukset.php" class="menu-item"><i class="fas fa-check-circle"></i><span>Käsittele Lainoja</span></a>
        <a href="admin_palautukset.php" class="menu-item"><i class="fas fa-undo-alt"></i><span>Hallinnoi Palautuksia</span></a>
        <a href="admin_myohassa_kirjat.php" class="menu-item"><i class="fas fa-clock"></i><span>Myöhässä Olevat</span></a>

        <div class="menu-section">🖥️ Laitehallinta</div>
        <a href="admin_laitetyypit.php" class="menu-item"><i class="fas fa-laptop"></i><span>Laitetyypit</span></a>
        <a href="admin_laitteet.php" class="menu-item"><i class="fas fa-microchip"></i><span>Laitteet</span></a>
        <a href="admin_laitevaraukset.php" class="menu-item"><i class="fas fa-calendar-alt"></i><span>Laitevaraukset</span></a>

        <div class="menu-section">📊 Raportit & Sakot</div>
        <a href="admin_raportit.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Kirjasto Raportit</span></a>
        <a href="admin_sakot.php" class="menu-item"><i class="fas fa-euro-sign"></i><span>Hallinnoi Sakkoja</span></a>

        <div class="menu-section">📨 Viestit</div>
        <a href="admin_viestit.php" class="menu-item"><i class="fas fa-comments"></i><span>Hallinnoi Viestit</span></a>

        <div class="menu-section">🔧 Järjestelmä</div>
        <a href="admin_varmuuskopiointi.php" class="menu-item"><i class="fas fa-database"></i><span>Varmuuskopiot</span></a>
        <a href="admin_kayttooikeudet.php" class="menu-item"><i class="fas fa-cogs"></i><span>Järjestelmäasetukset</span></a>
        <a href="admin_palvelin_lokit.php" class="menu-item"><i class="fas fa-history"></i><span>Palvelinlokit</span></a>

        <a href="logout.php" class="menu-item logout-item"><i class="fas fa-sign-out-alt"></i><span>Kirjaudu Ulos</span></a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- HEADER -->
    <div class="header">
        <div class="page-title">
            <h1><i class="fas fa-plus-circle"></i> Lisää uusi kirja</h1>
            <p><i class="fas fa-book"></i> Lisää uusi kirja kirjaston kokoelmaan</p>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($custom_name); ?></h3>
                <p style="color: #94a3b8;"><i class="fas fa-envelope" style="color: #667eea;"></i> <?php echo htmlspecialchars($custom_email); ?></p>
                <p style="color: #10b981;"><i class="fas fa-shield-alt" style="color: #10b981;"></i> <?php echo htmlspecialchars($custom_role_display); ?></p>
                <p style="color: #f59e0b;"><i class="fas fa-key" style="color: #f59e0b;"></i> <?php echo htmlspecialchars($custom_permissions); ?></p>
            </div>
        </div>
    </div>

    <!-- NOTIFICATIONS -->
    <?php if (!empty($errors)): ?>
        <div class="notification notification-error">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <strong>Virheitä lomakkeessa:</strong>
                <?php foreach ($errors as $err): ?>
                    <div style="margin-top: 5px;"><?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="notification notification-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_message && empty($errors)): ?>
        <div class="notification notification-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <!-- ADD BOOK FORM -->
    <div class="content-card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-plus"></i> Kirjan perustiedot</div>
        </div>

        <form method="POST" action="" id="addBookForm">
            <div class="section-header"><i class="fas fa-info-circle"></i> Kirjan tiedot</div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required"><i class="fas fa-book"></i> Kirjan nimi</label>
                    <input type="text" id="nimi" name="nimi" class="form-control"
                           value="<?php echo htmlspecialchars($nimi); ?>"
                           required placeholder="Kirjan nimi">
                    <div class="form-hint">Kirjan koko nimi, kuten se esiintyy kannessa</div>
                </div>

                <div class="form-group">
                    <label class="form-label required"><i class="fas fa-user-pen"></i> Kirjailija</label>
                    <input type="text" id="tekija" name="tekija" class="form-control"
                           value="<?php echo htmlspecialchars($tekija); ?>"
                           required placeholder="Kirjailijan nimi">
                    <div class="form-hint">Kirjailijan tai kirjailijoiden nimet</div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-barcode"></i> ISBN-numero</label>
                    <input type="text" id="isbn" name="isbn" class="form-control"
                           value="<?php echo htmlspecialchars($isbn); ?>"
                           placeholder="978-951-12345-6-7">
                    <div class="form-hint">ISBN-10 tai ISBN-13 muodossa. Voit jättää tyhjäksi.</div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-tags"></i> Kategoria</label>
                    <input type="text" id="kategoria" name="kategoria" class="form-control"
                           value="<?php echo htmlspecialchars($kategoria); ?>"
                           list="kategoriat-list" placeholder="Esimerkiksi: Romaani, Tietokirja, Fantasia">
                    <datalist id="kategoriat-list">
                        <?php foreach ($kategoriat as $kat): ?>
                            <option value="<?php echo htmlspecialchars($kat); ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-hint">Kirjoita uusi kategoria tai valitse listasta</div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-calendar"></i> Julkaisuvuosi</label>
                    <input type="number" id="julkaisuvuosi" name="julkaisuvuosi" class="form-control"
                           value="<?php echo htmlspecialchars($julkaisuvuosi); ?>"
                           min="1000" max="<?php echo date('Y'); ?>"
                           placeholder="<?php echo date('Y'); ?>">
                    <div class="form-hint">Vuosiluku, esim. 2023</div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-building"></i> Kustantaja</label>
                    <input type="text" id="kustantaja" name="kustantaja" class="form-control"
                           value="<?php echo htmlspecialchars($kustantaja); ?>"
                           placeholder="Kustantajan nimi">
                    <div class="form-hint">Kirjan julkaisija</div>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label required"><i class="fas fa-copy"></i> Kappalemäärä</label>
                    <input type="number" id="kokonaismaara" name="kokonaismaara" class="form-control"
                           value="<?php echo htmlspecialchars($kokonaismaara); ?>"
                           min="1" max="1000" required placeholder="1">
                    <div class="form-hint">Varastoon lisättävien kirjojen määrä</div>
                </div>

                <div class="form-group">
                    <div class="info-box">
                        <p><i class="fas fa-info-circle"></i> Automaattinen tila</p>
                        <p style="margin-top: 8px;">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i>
                            Kaikki kopiot merkitään saatavilla. Saatavilla-kenttä päivittyy automaattisesti lainausten mukaan.
                        </p>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" name="add_book" class="btn btn-primary">
                    <i class="fas fa-save"></i> Tallenna kirja
                </button>
                <a href="admin_manage_kirjat.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Takaisin listaukseen
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Focus on book name field
        document.getElementById('nimi').focus();

        // Set current year as placeholder for publication year
        const yearInput = document.getElementById('julkaisuvuosi');
        if (yearInput) {
            yearInput.placeholder = new Date().getFullYear();
        }

        // Auto-hide notifications after 5 seconds
        setTimeout(function() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(function(notification) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-15px)';
                notification.style.transition = 'all 0.3s ease';
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 300);
            });
        }, 5000);

        // Intersection Observer for scroll animations
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.content-card').forEach(function(el) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.5s ease';
            observer.observe(el);
        });
    });
</script>

</body>
</html>
