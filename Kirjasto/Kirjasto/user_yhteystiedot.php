<?php
// ============================================
// FILE: user_yhteystiedot.php
// PURPOSE: User contact information page
// ============================================

session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info
$user_query = "SELECT etunimi, sukunimi, profile_image, jasennumero, rooli FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user['rooli'] == 'admin') {
    header("Location: admin_dashboard.php");
    exit();
} elseif ($user['rooli'] == 'manager') {
    header("Location: manager_dashboard.php");
    exit();
}

// Set current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get user initials
$initials = strtoupper(substr($user['etunimi'] ?? '', 0, 1) . substr($user['sukunimi'] ?? '', 0, 1));

// Membership number
$membership_number = isset($user['jasennumero']) && !empty($user['jasennumero'])
    ? $user['jasennumero']
    : 'JASEN-' . str_pad($user_id, 6, '0', STR_PAD_LEFT);

// Unread messages count
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

$profile_image = $user['profile_image'] ?? null;
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yhteystiedot | <?php echo htmlspecialchars($user['etunimi']); ?> <?php echo htmlspecialchars($user['sukunimi']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           RESET STYLES
           ============================================ */
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

        /* ============================================
           CSS VARIABLES
           ============================================ */
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
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-hover: 0 15px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 20px rgba(102, 126, 234, 0.3);
        }

        /* ============================================
           SIDEBAR STYLES
           ============================================ */
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

        /* ============================================
           MAIN CONTENT STYLES
           ============================================ */
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

        /* ============================================
           CONTENT CARD STYLES
           ============================================ */
        .content-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 25px 20px;
            text-align: center;
            transition: all 0.3s;
        }

        .info-box:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .info-box i {
            font-size: 2.2rem;
            color: #667eea;
            margin-bottom: 12px;
        }

        .info-box h3 {
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .info-box p {
            color: var(--text-muted);
            line-height: 1.5;
            font-size: 0.9rem;
        }

        /* ============================================
           MODERN MAP CARD STYLES
           ============================================ */
        .modern-map-card {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            margin-top: 20px;
        }

        .modern-map-card:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            box-shadow: var(--shadow-hover);
        }

        .map-card-header {
            padding: 20px 25px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .map-header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .map-icon {
            width: 50px;
            height: 50px;
            background: var(--gradient-1);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .map-icon i {
            font-size: 1.5rem;
            color: white;
        }

        .map-title-info h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
        }

        .map-title-info p {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .open-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 30px;
            color: #10b981;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .open-status i {
            font-size: 0.6rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }

        .map-image-container {
            position: relative;
            overflow: hidden;
        }

        .map-image-container iframe {
            display: block;
            width: 100%;
            height: 350px;
            border: none;
        }

        .map-info-overlay {
            position: absolute;
            bottom: 15px;
            left: 15px;
            right: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .overlay-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 0.8rem;
        }

        .overlay-item i {
            color: #667eea;
        }

        .map-card-footer {
            padding: 15px 25px;
            border-top: 1px solid var(--border-color);
            text-align: center;
        }

        .map-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 25px;
            background: var(--gradient-1);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .map-link-btn:hover {
            transform: translateX(5px);
            gap: 15px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 1200px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
            .info-grid {
                grid-template-columns: 1fr;
            }
            .top-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .map-card-header {
                flex-direction: column;
                text-align: center;
            }
            .map-header-left {
                flex-direction: column;
            }
            .map-info-overlay {
                position: relative;
                margin-top: -5px;
                background: rgba(0, 0, 0, 0.8);
            }
            .map-image-container iframe {
                height: 280px;
            }
        }
    </style>
</head>
<body>

    <!-- ========== SIDEBAR START ========== -->
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

        <a href="user_profile.php" class="user-profile-mini">
            <div class="avatar-mini">
                <?php if (!empty($profile_image) && file_exists("uploads/profiles/" . $profile_image)): ?>
                    <img src="uploads/profiles/<?php echo htmlspecialchars($profile_image); ?>" alt="Profiilikuva">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="user-info-mini">
                <h4><?php echo htmlspecialchars($user['etunimi'] . ' ' . $user['sukunimi']); ?></h4>
                <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($membership_number); ?></p>
            </div>
        </a>

        <div class="sidebar-menu">
            <div class="menu-section">📊 Päävalikko</div>
            <a href="user_dashboard.php" class="menu-item <?php echo $current_page == 'user_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>

            <div class="menu-section">📚 Kirjat</div>
            <a href="user_selaa_kirjoja.php" class="menu-item <?php echo $current_page == 'user_selaa_kirjoja.php' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> <span>Selaa kirjoja</span>
            </a>
            <a href="user_lainaa_kirja.php" class="menu-item <?php echo $current_page == 'user_lainaa_kirja.php' ? 'active' : ''; ?>">
                <i class="fas fa-hand-holding-heart"></i> <span>Lainaa kirja</span>
            </a>
            <a href="user_lainahistoria.php" class="menu-item <?php echo $current_page == 'user_lainahistoria.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> <span>Lainahistoria</span>
            </a>
            <a href="user_oma_varaukset.php" class="menu-item <?php echo $current_page == 'user_oma_varaukset.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> <span>Omat varaukset</span>
            </a>
            <a href="user_suosikit.php" class="menu-item <?php echo $current_page == 'user_suosikit.php' ? 'active' : ''; ?>">
                <i class="fas fa-heart"></i> <span>Suosikit</span>
            </a>

            <div class="menu-section">💻 Laitteet</div>
            <a href="user_selaa_laitteita.php" class="menu-item <?php echo $current_page == 'user_selaa_laitteita.php' ? 'active' : ''; ?>">
                <i class="fas fa-laptop"></i> <span>Selaa laitteita</span>
            </a>
            <a href="user_laitelainat.php" class="menu-item <?php echo $current_page == 'user_laitelainat.php' ? 'active' : ''; ?>">
                <i class="fas fa-microchip"></i> <span>Laitelainat</span>
            </a>

            <div class="menu-section">💰 Talous</div>
            <a href="user_sakot.php" class="menu-item <?php echo $current_page == 'user_sakot.php' ? 'active' : ''; ?>">
                <i class="fas fa-euro-sign"></i> <span>Sakot</span>
            </a>
            <a href="user_kuitit.php" class="menu-item <?php echo $current_page == 'user_kuitit.php' ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> <span>Kuitit</span>
            </a>

            <div class="menu-section">👤 Oma tili</div>
            <a href="user_profile.php" class="menu-item <?php echo $current_page == 'user_profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i> <span>Profiili</span>
            </a>
            <a href="user_yhteystiedot.php" class="menu-item <?php echo $current_page == 'user_yhteystiedot.php' ? 'active' : ''; ?>">
                <i class="fas fa-address-card"></i> <span>Yhteystiedot</span>
            </a>
            <a href="salasana.php" class="menu-item <?php echo $current_page == 'salasana.php' ? 'active' : ''; ?>">
                <i class="fas fa-key"></i> <span>Vaihda salasana</span>
            </a>
            <a href="user_kayttoehdot.php" class="menu-item <?php echo $current_page == 'user_kayttoehdot.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-contract"></i> <span>Käyttöehdot</span>
            </a>
            <a href="user_ilmoitukset.php" class="menu-item <?php echo $current_page == 'user_ilmoitukset.php' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i> <span>Ilmoitukset</span>
                <?php if ($unread_messages_count > 0): ?>
                    <span class="badge"><?php echo $unread_messages_count; ?></span>
                <?php endif; ?>
            </a>

            <div class="menu-section"></div>
            <a href="logout.php" class="menu-item logout-item">
                <i class="fas fa-sign-out-alt"></i> <span>Kirjaudu ulos</span>
            </a>
        </div>
    </div>
    <!-- ========== SIDEBAR END ========== -->

    <!-- ========== MAIN CONTENT START ========== -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Yhteystiedot</h1>
                <p><i class="fas fa-circle"></i> Ota yhteyttä kirjastoon</p>
            </div>
            <div class="top-actions">
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i> <?php echo date('j. F Y'); ?>
                </div>
                <div class="notification-icon">
                    <i class="far fa-bell"></i>
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="badge"><?php echo $unread_messages_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-address-card"></i> Kirjaston yhteystiedot
            </div>

            <div class="info-grid">
                <div class="info-box">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Osoite</h3>
                    <p>Paalutori 3<br>01600 Vantaa</p>
                </div>
                <div class="info-box">
                    <i class="fas fa-phone"></i>
                    <h3>Puhelin</h3>
                    <p>0413114312</p>
                </div>
                <div class="info-box">
                    <i class="fas fa-envelope"></i>
                    <h3>Sähköposti</h3>
                    <p>matiasmasih@gmail.com</p>
                </div>
                <div class="info-box">
                    <i class="fas fa-clock"></i>
                    <h3>Aukioloajat</h3>
                    <p>Ma-Pe 07-20<br>La-Su 10-16</p>
                </div>
            </div>

            <!-- Modern Map Card -->
            <div class="modern-map-card">
                <div class="map-card-header">
                    <div class="map-header-left">
                        <div class="map-icon">
                            <i class="fas fa-map-marked-alt"></i>
                        </div>
                        <div class="map-title-info">
                            <h3>Myrbacka bibliotek</h3>
                            <p>Myyrmäen kirjasto</p>
                        </div>
                    </div>
                    <div class="open-status">
                        <i class="fas fa-circle"></i>
                        <span>Avoinna nyt</span>
                    </div>
                </div>

                <div class="map-image-container">
                    <iframe
                        width="100%"
                        height="350"
                        frameborder="0"
                        scrolling="no"
                        marginheight="0"
                        marginwidth="0"
                        src="https://www.openstreetmap.org/export/embed.html?bbox=24.8332681%2C60.2413665%2C24.8732681%2C60.2813665&amp;layer=mapnik&amp;marker=60.2613665%2C24.8532681">
                    </iframe>

                    <div class="map-info-overlay">
                        <div class="overlay-item">
                            <i class="fas fa-location-dot"></i>
                            <span>Paalutori 3, 01600 Vantaa</span>
                        </div>
                        <div class="overlay-item">
                            <i class="fas fa-clock"></i>
                            <span>Ma-Pe 07-20 | La-Su 10-16</span>
                        </div>
                    </div>
                </div>

                <div class="map-card-footer">
                    <a href="https://www.openstreetmap.org/?mlat=60.2613665&amp;mlon=24.8532681#map=17/60.2613665/24.8532681" target="_blank" class="map-link-btn">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Näytä isompana karttana</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- ========== MAIN CONTENT END ========== -->
<script>
    // ============================================
    // USER_YHTEYSTIEDOT.PHP - JAVASCRIPT WITH ANIMATIONS
    // ============================================

    // Wait for DOM to fully load
    document.addEventListener('DOMContentLoaded', function() {
        
        // ============================================
        // 1. FADE IN ANIMATION FOR CONTENT CARDS
        // ============================================
        const contentCards = document.querySelectorAll('.content-card, .info-box, .modern-map-card');
        contentCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            setTimeout(() => {
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 150);
        });

        // ============================================
        // 2. INFO BOXES HOVER EFFECT WITH RIPPLE
        // ============================================
        const infoBoxes = document.querySelectorAll('.info-box');
        infoBoxes.forEach(box => {
            box.addEventListener('mouseenter', function(e) {
                // Create ripple effect
                const ripple = document.createElement('span');
                ripple.classList.add('ripple');
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.backgroundColor = 'rgba(102, 126, 234, 0.3)';
                ripple.style.width = '100px';
                ripple.style.height = '100px';
                ripple.style.left = e.clientX - e.target.getBoundingClientRect().left - 50 + 'px';
                ripple.style.top = e.clientY - e.target.getBoundingClientRect().top - 50 + 'px';
                ripple.style.pointerEvents = 'none';
                ripple.style.zIndex = '0';
                
                const oldRipple = box.querySelector('.ripple');
                if (oldRipple) oldRipple.remove();
                
                box.style.position = 'relative';
                box.style.overflow = 'hidden';
                box.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });

        // Add ripple animation to styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                from { transform: scale(0); opacity: 0.6; }
                to { transform: scale(2); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // ============================================
        // 3. MAP CARD ANIMATION ON SCROLL
        // ============================================
        const mapCard = document.querySelector('.modern-map-card');
        if (mapCard) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('map-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.3 });
            observer.observe(mapCard);
            
            // Add CSS for map animation
            const mapStyle = document.createElement('style');
            mapStyle.textContent = `
                .modern-map-card {
                    transition: all 0.5s ease;
                }
                .modern-map-card:not(.map-visible) {
                    opacity: 0;
                    transform: translateY(50px);
                }
                .modern-map-card.map-visible {
                    opacity: 1;
                    transform: translateY(0);
                }
            `;
            document.head.appendChild(mapStyle);
        }

        // ============================================
        // 4. INTERACTIVE MAP HOVER EFFECTS
        // ============================================
        const mapContainer = document.querySelector('.map-image-container');
        if (mapContainer) {
            mapContainer.addEventListener('mouseenter', () => {
                const iframe = mapContainer.querySelector('iframe');
                if (iframe) {
                    iframe.style.transition = 'transform 0.3s ease';
                    iframe.style.transform = 'scale(1.02)';
                }
            });
            mapContainer.addEventListener('mouseleave', () => {
                const iframe = mapContainer.querySelector('iframe');
                if (iframe) {
                    iframe.style.transform = 'scale(1)';
                }
            });
        }

        // ============================================
        // 5. OPENING STATUS PULSE ANIMATION (ENHANCED)
        // ============================================
        const openStatus = document.querySelector('.open-status');
        if (openStatus) {
            setInterval(() => {
                const dot = openStatus.querySelector('i');
                if (dot) {
                    dot.style.animation = 'none';
                    setTimeout(() => {
                        dot.style.animation = 'pulse 2s infinite';
                    }, 10);
                }
            }, 4000);
        }

        // ============================================
        // 6. BACK TO TOP BUTTON
        // ============================================
        const backToTop = document.createElement('button');
        backToTop.innerHTML = '<i class="fas fa-arrow-up"></i>';
        backToTop.className = 'back-to-top';
        backToTop.style.cssText = `
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            z-index: 1000;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            font-size: 1.2rem;
        `;
        backToTop.onclick = () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };
        document.body.appendChild(backToTop);
        
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                backToTop.style.display = 'flex';
                backToTop.style.alignItems = 'center';
                backToTop.style.justifyContent = 'center';
                backToTop.style.animation = 'fadeInUp 0.3s ease';
            } else {
                backToTop.style.display = 'none';
            }
        });
        
        backToTop.addEventListener('mouseenter', () => {
            backToTop.style.transform = 'translateY(-5px)';
            backToTop.style.boxShadow = '0 8px 25px rgba(102, 126, 234, 0.6)';
        });
        backToTop.addEventListener('mouseleave', () => {
            backToTop.style.transform = 'translateY(0)';
            backToTop.style.boxShadow = '0 4px 15px rgba(102, 126, 234, 0.4)';
        });

        // ============================================
        // 7. CONTACT INFO COPY FUNCTIONALITY
        // ============================================
        const phoneBox = document.querySelector('.info-box:has(.fa-phone)');
        const emailBox = document.querySelector('.info-box:has(.fa-envelope)');
        const addressBox = document.querySelector('.info-box:has(.fa-map-marker-alt)');
        
        function createCopyToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = 'copy-toast';
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
            toast.style.cssText = `
                position: fixed;
                bottom: 100px;
                right: 30px;
                background: var(--bg-card);
                backdrop-filter: blur(10px);
                color: ${type === 'success' ? '#10b981' : '#ef4444'};
                padding: 12px 20px;
                border-radius: 12px;
                border-left: 4px solid ${type === 'success' ? '#10b981' : '#ef4444'};
                z-index: 1000;
                animation: slideInRight 0.3s ease;
                box-shadow: var(--shadow);
            `;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 2500);
        }
        
        if (phoneBox) {
            phoneBox.style.cursor = 'pointer';
            phoneBox.addEventListener('click', () => {
                const phone = phoneBox.querySelector('p')?.innerText.split('\n')[0];
                if (phone) {
                    navigator.clipboard.writeText(phone);
                    createCopyToast('Puhelinnumero kopioitu!');
                    phoneBox.style.transform = 'scale(1.02)';
                    setTimeout(() => phoneBox.style.transform = '', 200);
                }
            });
        }
        
        if (emailBox) {
            emailBox.style.cursor = 'pointer';
            emailBox.addEventListener('click', () => {
                const email = emailBox.querySelector('p')?.innerText;
                if (email) {
                    navigator.clipboard.writeText(email);
                    createCopyToast('Sähköposti kopioitu!');
                    emailBox.style.transform = 'scale(1.02)';
                    setTimeout(() => emailBox.style.transform = '', 200);
                }
            });
        }
        
        if (addressBox) {
            addressBox.style.cursor = 'pointer';
            addressBox.addEventListener('click', () => {
                const address = addressBox.querySelector('p')?.innerText.replace(/<br>/g, ', ');
                if (address) {
                    navigator.clipboard.writeText(address);
                    createCopyToast('Osoite kopioitu!');
                    addressBox.style.transform = 'scale(1.02)';
                    setTimeout(() => addressBox.style.transform = '', 200);
                }
            });
        }

        // ============================================
        // 8. ADD CSS ANIMATIONS
        // ============================================
        const animationStyles = document.createElement('style');
        animationStyles.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(50px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(50px);
                }
            }
            @keyframes bounce {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-10px); }
            }
            .info-box {
                transition: all 0.3s ease;
            }
            .info-box:active {
                transform: scale(0.98);
            }
            .map-link-btn {
                transition: all 0.3s ease;
            }
            .map-link-btn:active {
                transform: scale(0.95);
            }
        `;
        document.head.appendChild(animationStyles);

        // ============================================
        // 9. SIDEBAR MOBILE TOGGLE
        // ============================================
        function createMobileToggle() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (document.querySelector('.mobile-toggle')) return;
            
            if (window.innerWidth <= 1024) {
                const toggleBtn = document.createElement('button');
                toggleBtn.className = 'mobile-toggle';
                toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                toggleBtn.style.cssText = `
                    position: fixed;
                    top: 15px;
                    left: 15px;
                    z-index: 1001;
                    background: var(--gradient-1);
                    border: none;
                    color: white;
                    width: 45px;
                    height: 45px;
                    border-radius: 12px;
                    font-size: 1.2rem;
                    cursor: pointer;
                    box-shadow: var(--shadow);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.3s;
                `;
                
                toggleBtn.onclick = () => {
                    if (sidebar.style.transform === 'translateX(0px)') {
                        sidebar.style.transform = 'translateX(-100%)';
                        mainContent.style.marginLeft = '0';
                        toggleBtn.style.left = '15px';
                    } else {
                        sidebar.style.transform = 'translateX(0)';
                        mainContent.style.marginLeft = '0';
                        toggleBtn.style.left = '295px';
                    }
                };
                
                document.body.appendChild(toggleBtn);
                
                document.addEventListener('click', (e) => {
                    if (window.innerWidth <= 1024) {
                        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                            sidebar.style.transform = 'translateX(-100%)';
                            toggleBtn.style.left = '15px';
                        }
                    }
                });
            }
        }
        
        createMobileToggle();
        window.addEventListener('resize', () => {
            const existing = document.querySelector('.mobile-toggle');
            if (existing) existing.remove();
            createMobileToggle();
        });

        // ============================================
        // 10. AUTO-HIDE NOTIFICATIONS
        // ============================================
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(n => {
                n.style.transition = 'opacity 0.5s ease';
                n.style.opacity = '0';
                setTimeout(() => n.style.display = 'none', 500);
            });
        }, 5000);
    });
</script>
</body>
</html>
