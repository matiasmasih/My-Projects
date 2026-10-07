<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$device_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($device_id === 0) {
    header("Location: user_selaa_laitteita.php");
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
    header("Location: user_selaa_laitteita.php");
    exit();
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

// Decode features
$features = !empty($device['ominaisuudet']) ? json_decode($device['ominaisuudet'], true) : [];
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($device['merkki'] . ' ' . $device['malli']); ?> | Kirjasto</title>
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

        .back-btn {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: var(--gradient-1);
            color: white;
            transform: translateX(-5px);
        }

        .device-detail-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .device-header {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
        }

        .device-icon {
            width: 120px;
            height: 120px;
            background: var(--gradient-1);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3.5rem;
            flex-shrink: 0;
        }

        .device-title h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .device-title p {
            color: var(--text-muted);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .device-title p i {
            color: #667eea;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-available {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-loaned {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-maintenance {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
        }

        .info-box .label {
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .info-box .label i {
            color: #667eea;
        }

        .info-box .value {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 600;
        }

        .features-section {
            margin-bottom: 30px;
        }

        .features-section h3 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .features-section h3 i {
            color: #667eea;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .feature-item {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
        }

        .feature-item .feature-label {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .feature-item .feature-value {
            color: var(--text-primary);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            padding: 14px 30px;
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
            text-decoration: none;
        }

        .btn-primary {
            background: var(--gradient-1);
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(102, 126, 234, 0.5);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            flex: 1;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        @media (max-width: 1200px) {
            .features-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .device-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .features-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .top-bar {
                flex-direction: column;
                gap: 15px;
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
            <a href="admin_sakot.php" class="menu-item">
                <i class="fas fa-euro-sign"></i>
                <span>Hallinnoi Sakkoja</span>
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
                <h1>Laitteen tiedot</h1>
                <p><i class="fas fa-circle"></i> <?php echo htmlspecialchars($device['merkki'] . ' ' . $device['malli']); ?></p>
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
                <a href="user_selaa_laitteita.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Takaisin
                </a>
            </div>
        </div>

        <!-- Device Details -->
        <div class="device-detail-card">
            <div class="device-header">
                <div class="device-icon">
                    <i class="fas fa-<?php 
                        $type = strtolower($device['tyyppi_nimi'] ?? '');
                        if (strpos($type, 'tabletti') !== false) echo 'tablet-alt';
                        elseif (strpos($type, 'puhelin') !== false) echo 'mobile-alt';
                        elseif (strpos($type, 'kamera') !== false) echo 'camera';
                        elseif (strpos($type, 'projektori') !== false) echo 'video';
                        else echo 'laptop';
                    ?>"></i>
                </div>
                <div class="device-title">
                    <h2><?php echo htmlspecialchars($device['merkki'] . ' ' . $device['malli']); ?></h2>
                    <p>
                        <i class="fas fa-tag"></i> 
                        <?php echo htmlspecialchars($device['tyyppi_nimi'] ?? 'Laite'); ?>
                        <span class="status-badge status-<?php 
                            echo $device['tila'] == 'saatavilla' ? 'available' : 
                                ($device['tila'] == 'lainassa' ? 'loaned' : 'maintenance'); 
                        ?>">
                            <i class="fas fa-<?php 
                                echo $device['tila'] == 'saatavilla' ? 'check-circle' : 
                                    ($device['tila'] == 'lainassa' ? 'clock' : 'tools'); 
                            ?>"></i>
                            <?php 
                                echo $device['tila'] == 'saatavilla' ? 'Saatavilla' : 
                                    ($device['tila'] == 'lainassa' ? 'Lainassa' : 'Huollossa'); 
                            ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Basic Info Grid -->
            <div class="info-grid">
                <div class="info-box">
                    <div class="label"><i class="fas fa-barcode"></i> Sarjanumero</div>
                    <div class="value"><?php echo htmlspecialchars($device['sarjanumero']); ?></div>
                </div>
                <div class="info-box">
                    <div class="label"><i class="fas fa-star"></i> Kunto</div>
                    <div class="value"><?php echo ucfirst($device['kunto']); ?></div>
                </div>
                <div class="info-box">
                    <div class="label"><i class="fas fa-map-marker-alt"></i> Sijainti</div>
                    <div class="value"><?php echo htmlspecialchars($device['sijainti'] ?? 'Ei määritelty'); ?></div>
                </div>
                <div class="info-box">
                    <div class="label"><i class="fas fa-calendar-plus"></i> Hankintapäivä</div>
                    <div class="value"><?php echo date('d.m.Y', strtotime($device['hankintapaiva'])); ?></div>
                </div>
                <div class="info-box">
                    <div class="label"><i class="fas fa-calendar-check"></i> Viimeisin huolto</div>
                    <div class="value"><?php echo $device['viime_huolto'] ? date('d.m.Y', strtotime($device['viime_huolto'])) : 'Ei tehty'; ?></div>
                </div>
                <div class="info-box">
                    <div class="label"><i class="fas fa-clock"></i> Lisätty</div>
                    <div class="value"><?php echo date('d.m.Y', strtotime($device['luotu'])); ?></div>
                </div>
            </div>

            <!-- Features Section -->
            <?php if (!empty($features)): ?>
            <div class="features-section">
                <h3><i class="fas fa-microchip"></i> Ominaisuudet</h3>
                <div class="features-grid">
                    <?php foreach ($features as $key => $value): 
                        if (empty($value)) continue;
                        $icon = 'fa-circle-info';
                        if (strpos($key, 'prosessori') !== false) $icon = 'fa-microchip';
                        elseif (strpos($key, 'ram') !== false) $icon = 'fa-memory';
                        elseif (strpos($key, 'storage') !== false) $icon = 'fa-hard-drive';
                        elseif (strpos($key, 'naytto') !== false) $icon = 'fa-tv';
                        elseif (strpos($key, 'sensor') !== false) $icon = 'fa-camera';
                        elseif (strpos($key, 'brightness') !== false) $icon = 'fa-sun';
                        elseif (strpos($key, 'resolution') !== false) $icon = 'fa-expand';
                        elseif (strpos($key, 'color') !== false) $icon = 'fa-palette';
                    ?>
                        <div class="feature-item">
                            <div class="feature-label"><i class="fas <?php echo $icon; ?>"></i> <?php echo ucfirst($key); ?></div>
                            <div class="feature-value"><?php echo htmlspecialchars($value); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Additional Info -->
            <?php if (!empty($device['huomiot'])): ?>
            <div style="margin-top: 20px; padding: 15px; background: rgba(255,255,255,0.02); border-radius: 12px;">
                <div style="color: var(--text-muted); margin-bottom: 5px;"><i class="fas fa-sticky-note"></i> Huomiot:</div>
                <div style="color: var(--text-primary);"><?php echo nl2br(htmlspecialchars($device['huomiot'])); ?></div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($device['tila'] == 'saatavilla'): ?>
                    <a href="user_varaa_laite.php?id=<?php echo $device['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Varaa laite
                    </a>
                <?php endif; ?>
                <a href="user_selaa_laitteita.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Takaisin hakuun
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.getElementsByClassName('alert');
            for (var i = 0; i < alerts.length; i++) {
                if (alerts[i]) {
                    alerts[i].style.transition = 'opacity 0.5s';
                    alerts[i].style.opacity = '0';
                }
            }
        }, 5000);
    </script>
</body>
</html>
