<?php
session_start();
require_once 'connection.php';
require_once 'receipt_helper.php'; // Added receipt helper

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$device_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($device_id === 0) {
    header("Location: user_selaa_laitteita.php?error=invalid_device");
    exit();
}

// Get user info
$user_query = "SELECT etunimi, sukunimi, profile_image, jasennumero, rooli FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// If user is admin or manager, redirect to their dashboard
if ($user['rooli'] == 'admin') {
    header("Location: admin_dashboard.php");
    exit();
} elseif ($user['rooli'] == 'manager') {
    header("Location: manager_dashboard.php");
    exit();
}

// Get device details
$device_query = "SELECT l.*, lt.nimi as tyyppi_nimi
                 FROM Laitteet l
                 LEFT JOIN Laitetyypit lt ON l.laite_tyyppi_id = lt.id
                 WHERE l.id = ?";
$stmt = $conn->prepare($device_query);
$stmt->bind_param("i", $device_id);
$stmt->execute();
$device = $stmt->get_result()->fetch_assoc();

if (!$device) {
    header("Location: user_selaa_laitteita.php?error=device_not_found");
    exit();
}

// Check if device is available
if ($device['tila'] != 'saatavilla') {
    header("Location: user_selaa_laitteita.php?error=device_not_available");
    exit();
}

// Check if user already has this device on loan
$check_loan = "SELECT id FROM Laitelainat WHERE laite_id = ? AND jasen_id = ? AND palautus_pvm IS NULL";
$stmt = $conn->prepare($check_loan);
$stmt->bind_param("ii", $device_id, $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    header("Location: user_selaa_laitteita.php?error=already_loaned");
    exit();
}

// Handle loan confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_loan'])) {
    $lainaus_kunto = $_POST['lainaus_kunto'] ?? 'hyvä';
    $huomiot = $_POST['huomiot'] ?? '';

    // Set loan period (default 14 days)
    $lainaus_pvm = date('Y-m-d H:i:s');
    $erapaiva = date('Y-m-d H:i:s', strtotime('+14 days'));

    // Insert loan
    $insert_query = "INSERT INTO Laitelainat (laite_id, jasen_id, lainaus_pvm, erapaiva, lainaus_kunto, huomiot)
                     VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iissss", $device_id, $user_id, $lainaus_pvm, $erapaiva, $lainaus_kunto, $huomiot);

    if ($stmt->execute()) {
        $laitelaina_id = $conn->insert_id;
        
        // Update device status
        $update_device = "UPDATE Laitteet SET tila = 'lainassa' WHERE id = ?";
        $stmt2 = $conn->prepare($update_device);
        $stmt2->bind_param("i", $device_id);
        $stmt2->execute();
        $stmt2->close();

        // ============================================
        // GENERATE RECEIPT FOR DEVICE LOAN
        // ============================================
        $laite_nimi = $device['merkki'] . ' ' . $device['malli'];
        createLoanReceipt($user_id, $laitelaina_id, 'device', $laite_nimi, $lainaus_pvm);

        header("Location: admin_laitelainat.php?success=loaned");
        exit();
    } else {
        $error_message = "Lainaus epäonnistui. Yritä uudelleen.";
    }
}

// Get user initials for avatar
$initials = strtoupper(substr($user['etunimi'] ?? '', 0, 1) . substr($user['sukunimi'] ?? '', 0, 1));

// Create membership number
$membership_number = isset($user['jasennumero']) && !empty($user['jasennumero'])
    ? $user['jasennumero']
    : 'JASEN-' . str_pad($user_id, 6, '0', STR_PAD_LEFT);

// Get unread messages count
$unread_messages_count = 0;
try {
    $unread_query = "SELECT COUNT(*) as count FROM viestit WHERE vastaanottaja_id = ? AND luettu = 0";
    $stmt = $conn->prepare($unread_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unread_result = $stmt->get_result();
    $unread_data = $unread_result->fetch_assoc();
    $unread_messages_count = $unread_data['count'] ?? 0;
} catch (Exception $e) {
    error_log("Unread messages error: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lainaa Laite | <?php echo htmlspecialchars($device['merkki'] . ' ' . $device['malli']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            position: relative;
            background-color: #0a0c10;
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
            filter: brightness(0.25) blur(4px);
            z-index: -1;
        }

        :root {
            --bg-primary: rgba(18, 22, 28, 0.85);
            --bg-secondary: rgba(26, 32, 39, 0.9);
            --bg-card: rgba(30, 36, 45, 0.85);
            --bg-sidebar: rgba(13, 17, 23, 0.95);
            --text-primary: #ffffff;
            --text-secondary: #b0b8c5;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
            --gradient-1: linear-gradient(135deg, #667eea, #764ba2);
            --gradient-2: linear-gradient(135deg, #f093fb, #f5576c);
            --gradient-3: linear-gradient(135deg, #4facfe, #00f2fe);
            --gradient-4: linear-gradient(135deg, #43e97b, #38f9d7);
            --gradient-5: linear-gradient(135deg, #fa709a, #fee140);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-hover: 0 15px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 20px rgba(102, 126, 234, 0.3);
        }

        .sidebar {
            width: 280px;
            background: var(--bg-sidebar);
            backdrop-filter: blur(10px);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: var(--shadow);
            overflow-y: auto;
            z-index: 1000;
            border-right: 1px solid var(--border-color);
        }

        .sidebar-header {
            padding: 30px 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: var(--gradient-1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            box-shadow: var(--glow);
        }

        .logo-text h2 {
            font-size: 1.4rem;
            font-weight: 700;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2px;
        }

        .logo-text p {
            font-size: 0.7rem;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .user-profile-mini {
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .user-profile-mini:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(5px);
        }

        .avatar-mini {
            width: 55px;
            height: 55px;
            border-radius: 14px;
            overflow: hidden;
            background: var(--gradient-1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.3rem;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .avatar-mini img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info-mini h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .user-info-mini p {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .user-info-mini p i {
            font-size: 0.7rem;
            color: #10b981;
        }

        .sidebar-menu {
            padding: 20px 16px;
        }

        .menu-section {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 20px 16px 8px;
            letter-spacing: 0.5px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            margin: 4px 0;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .menu-item i {
            width: 22px;
            font-size: 1.1rem;
            color: var(--text-muted);
            transition: all 0.3s;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            transform: translateX(5px);
        }

        .menu-item:hover i {
            color: #667eea;
        }

        .menu-item.active {
            background: var(--gradient-1);
            color: white;
            box-shadow: 0 10px 20px -5px rgba(102, 126, 234, 0.3);
        }

        .menu-item.active i {
            color: white;
        }

        .logout-item {
            margin-top: 30px;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .logout-item i {
            color: #ef4444;
        }

        .logout-item:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .logout-item:hover i {
            color: #ef4444;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
            height: 100vh;
            overflow-y: auto;
        }

        .top-bar {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-color);
        }

        .page-title h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }

        .page-title p {
            color: var(--text-muted);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .page-title p i {
            font-size: 0.5rem;
            color: #10b981;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .date-badge {
            background: rgba(255, 255, 255, 0.05);
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-color);
        }

        .date-badge i {
            color: #667eea;
        }

        .notification-icon {
            position: relative;
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }

        .notification-icon:hover {
            background: var(--gradient-1);
            color: white;
            transform: rotate(5deg);
        }

        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            border: 2px solid var(--bg-card);
        }

        .loan-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .device-header {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .device-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            flex-shrink: 0;
        }

        .device-info h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .device-info p {
            color: var(--text-muted);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .info-value {
            color: var(--text-primary);
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }

        .btn-primary {
            background: var(--gradient-1);
            color: white;
            margin-bottom: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(102, 126, 234, 0.5);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .terms {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .terms i {
            color: #667eea;
            margin-right: 5px;
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .device-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="logo-text">
                    <h2>Kirjasto</h2>
                    <p>Lukemisen iloa</p>
                </div>
            </div>
        </div>

        <!-- Profile Mini -->
        <a href="user_profile.php" class="user-profile-mini">
            <div class="avatar-mini">
                <?php if (!empty($user['profile_image']) && file_exists("uploads/profiles/" . $user['profile_image'])): ?>
                    <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profiilikuva">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="user-info-mini">
                <h4><?php echo htmlspecialchars($user['etunimi'] . ' ' . $user['sukunimi']); ?></h4>
                <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($membership_number); ?></p>
            </div>
        </a>

        <!-- Navigation Menu -->
        <div class="sidebar-menu">
            <div class="menu-section">Päävalikko</div>
            <a href="user_dashboard.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>

            <div class="menu-section">Kirjasto</div>
            <a href="user_selaa_kirjoja.php" class="menu-item">
                <i class="fas fa-book"></i>
                <span>Selaa Kirjoja</span>
            </a>
            <a href="user_lainahistoria.php" class="menu-item">
                <i class="fas fa-history"></i>
                <span>Lainahistoria</span>
            </a>
            <a href="user_oma_varaukset.php" class="menu-item">
                <i class="fas fa-bookmark"></i>
                <span>Omat Varaukset</span>
            </a>

            <div class="menu-section">Laitteet</div>
            <a href="user_selaa_laitteita.php" class="menu-item">
                <i class="fas fa-laptop"></i>
                <span>Selaa Laitteita</span>
            </a>
            <a href="admin_laitelainat.php" class="menu-item">
                <i class="fas fa-mobile-alt"></i>
                <span>Laitelainat</span>
            </a>

            <div class="menu-section">Sakot</div>
            <a href="user_sakot.php" class="menu-item">
                <i class="fas fa-euro-sign"></i>
                <span>Hallinnoi Sakkoja</span>
            </a>
            <a href="user_kuitit.php" class="menu-item">
                <i class="fas fa-receipt"></i>
                <span>Omat kuitit</span>
            </a>

            <div class="menu-section">Asetukset</div>
            <a href="user_profile.php" class="menu-item">
                <i class="fas fa-user"></i>
                <span>Oma Profiili</span>
            </a>
            <a href="salasana.php" class="menu-item">
                <i class="fas fa-key"></i>
                <span>Vaihda Salasana</span>
            </a>

            <a href="logout.php" class="menu-item logout-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Kirjaudu Ulos</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Lainaa Laite</h1>
                <p><i class="fas fa-circle"></i> Vahvista lainaus</p>
            </div>
            <div class="top-actions">
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo date('j. F Y'); ?>
                </div>
                <div class="notification-icon">
                    <i class="far fa-bell"></i>
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="badge"><?php echo $unread_messages_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Loan Card -->
        <div class="loan-card">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <div class="device-header">
                <div class="device-icon">
                    <i class="fas fa-laptop"></i>
                </div>
                <div class="device-info">
                    <h2><?php echo htmlspecialchars($device['merkki'] . ' ' . $device['malli']); ?></h2>
                    <p>
                        <i class="fas fa-tag"></i> 
                        <?php echo htmlspecialchars($device['tyyppi_nimi'] ?? 'Laite'); ?>
                        <span class="status-badge" style="background: rgba(16,185,129,0.15); color: #10b981; padding: 4px 12px; border-radius: 30px; font-size: 0.8rem; margin-left: 10px;">
                            <i class="fas fa-check-circle"></i> Saatavilla
                        </span>
                    </p>
                </div>
            </div>

            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Sarjanumero:</span>
                    <span class="info-value"><?php echo htmlspecialchars($device['sarjanumero']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Kunto:</span>
                    <span class="info-value"><?php echo ucfirst($device['kunto']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Sijainti:</span>
                    <span class="info-value"><?php echo htmlspecialchars($device['sijainti'] ?? 'Ei määritelty'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Lainausaika:</span>
                    <span class="info-value">14 päivää</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Eräpäivä:</span>
                    <span class="info-value"><?php echo date('d.m.Y', strtotime('+14 days')); ?></span>
                </div>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="lainaus_kunto">Lainattavan laitteen kunto</label>
                    <select name="lainaus_kunto" id="lainaus_kunto" class="form-control" required>
                        <option value="erinomainen">Erinomainen</option>
                        <option value="hyvä" selected>Hyvä</option>
                        <option value="tyydyttävä">Tyydyttävä</option>
                        <option value="huono">Huono</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="huomiot">Huomiot (valinnainen)</label>
                    <textarea name="huomiot" id="huomiot" class="form-control" placeholder="Kirjoita mahdolliset huomiot laitteesta..."></textarea>
                </div>

                <div class="terms">
                    <i class="fas fa-info-circle"></i>
                    Vahvistamalla lainauksen sitoudut palauttamaan laitteen viimeistään eräpäivänä ja vastaamaan laitteen asianmukaisesta käytöstä. Myöhästyneistä palautuksista peritään sakkomaksu.
                </div>

                <button type="submit" name="confirm_loan" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Vahvista lainaus
                </button>

                <a href="user_selaa_laitteita.php" class="btn btn-secondary">
                    <i class="fas fa-times-circle"></i> Peruuta
                </a>
            </form>
        </div>
    </div>
</body>
</html>
