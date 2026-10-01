<?php
// ============================================
// FILE: user_kayttoehdot.php
// PURPOSE: User terms and conditions page
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
    <title>Käyttöehdot | <?php echo htmlspecialchars($user['etunimi']); ?> <?php echo htmlspecialchars($user['sukunimi']); ?></title>
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
            padding: 40px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            max-width: 1000px;
            margin: 0 auto;
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

        .section-title i {
            color: #667eea;
        }

        .terms-section {
            margin-bottom: 35px;
            animation: fadeInUp 0.5s ease;
        }

        .terms-section h3 {
            color: #667eea;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .terms-section h3 i {
            font-size: 1rem;
        }

        .terms-section p {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .terms-section ul {
            color: var(--text-secondary);
            padding-left: 30px;
            line-height: 1.8;
            list-style-type: disc;
        }

        .terms-section li {
            margin-bottom: 8px;
        }

        .highlight-box {
            color: #f59e0b !important;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(245, 158, 11, 0.1));
            border-left: 4px solid #ef4444;
            padding: 20px 25px;
            border-radius: 12px;
            margin: 20px 0;
            animation: pulseBox 2s infinite;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.2);
        }

        .highlight-box i {
            color: #ef4444;
            margin-right: 8px;
        }

        .highlight-box strong {
            color: #f59e0b;
            font-size: 1.05rem;
        }

        @keyframes pulseBox {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2);
                border-left-color: #ef4444;
            }
            50% {
                box-shadow: 0 0 0 8px rgba(239, 68, 68, 0.05);
                border-left-color: #f59e0b;
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2);
                border-left-color: #ef4444;
            }
        }

        .highlight-box strong {
            color: #667eea;
        }

        .last-updated {
            text-align: right;
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
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
            .content-card {
                padding: 25px;
            }
            .terms-section h3 {
                font-size: 1.1rem;
            }
        .highlight-box {
            color: #f59e0b !important;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(245, 158, 11, 0.1));
            border-left: 4px solid #ef4444;
            padding: 20px 25px;
            border-radius: 12px;
            margin: 20px 0;
            animation: pulseBox 2s infinite;
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.2);
        }

        .highlight-box i {
            color: #ef4444;
            margin-right: 8px;
        }

        .highlight-box strong {
            color: #f59e0b;
            font-size: 1.05rem;
        }

        @keyframes pulseBox {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2);
                border-left-color: #ef4444;
            }
            50% {
                box-shadow: 0 0 0 8px rgba(239, 68, 68, 0.05);
                border-left-color: #f59e0b;
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2);
                border-left-color: #ef4444;
            }
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
                <h1>Käyttöehdot</h1>
                <p><i class="fas fa-circle"></i> Lue kirjaston käyttöehdot</p>
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
                <i class="fas fa-file-contract"></i> Kirjaston käyttöehdot
            </div>

            <div class="terms-section">
                <h3><i class="fas fa-book"></i> 1. Kirjaston käyttö</h3>
                <p>Kirjaston palvelut ovat tarkoitettu kaikille asiakkaille. Kirjastokortti on henkilökohtainen ja sitä tulee käyttää vastuullisesti. Kirjastokortista vastaa sen haltija, eikä sitä saa luovuttaa toisen henkilön käyttöön.</p>
            </div>

            <div class="terms-section">
                <h3><i class="fas fa-calendar-alt"></i> 2. Lainaus ja palautus</h3>
                <p>Laina-aika on pääsääntöisesti 14 päivää kirjoille ja 7 päivää lehdille. Lainoja voi uusia, ellei niitä ole varattu. Lainojen uusiminen on mahdollista verkkopalvelussa, puhelimitse tai kirjastossa asioimalla.</p>

                <div class="highlight-box">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Myöhästyneet palautukset:</strong> Myöhästyneistä palautuksista peritään sakkomaksu. Sakkomaksun suuruus on 1€ / päivä / kirja.
                </div>
            </div>

            <div class="terms-section">
                <h3><i class="fas fa-bookmark"></i> 3. Varaukset</h3>
                <p>Varaukset ovat maksuttomia. Varattu aineisto säilyy noudettavana 7 päivää. Mikäli varausta ei noudeta määräaikaan mennessä, se vapautuu muiden asiakkaiden käyttöön.</p>
            </div>

            <div class="terms-section">
                <h3><i class="fas fa-euro-sign"></i> 4. Maksut</h3>
                <ul>
                    <li><strong>Myöhästymismaksu:</strong> 1€ / päivä / kirja (maksimissaan 10€ / kirja)</li>
                    <li><strong>Kadonnut aineisto:</strong> Hankintahinta + 10€ käsittelymaksu</li>
                    <li><strong>Kirjastokortin uusiminen:</strong> 5€</li>
                </ul>
            </div>

            <div class="terms-section">
                <h3><i class="fas fa-lock"></i> 5. Yksityisyys ja tietosuoja</h3>
                <p>Noudatamme tietosuojalainsäädäntöä ja hyvää tietojenkäsittelytapaa. Asiakastietoja käytetään vain kirjaston palveluiden toteuttamiseen ja niitä ei luovuteta ulkopuolisille ilman asiakkaan suostumusta.</p>
                <p>Asiakkaalla on oikeus tarkistaa itseään koskevat tiedot ja vaatia virheellisen tiedon korjaamista.</p>
            </div>

            <div class="terms-section">
                <h3><i class="fas fa-exclamation-triangle"></i> 6. Vastuut ja vahingot</h3>
                <p>Asiakas on vastuussa lainaamansa aineiston asianmukaisesta käsittelystä. Jos lainattu aineisto katoaa tai vahingoittuu, asiakas on velvollinen korvaamaan sen. Mikäli laite vahingoittuu laina-aikana, siitä tulee ilmoittaa välittömästi kirjaston henkilökunnalle.</p>
            </div>

            <div class="last-updated">
                <i class="far fa-calendar-check"></i> Viimeksi päivitetty: 10.04.2026
            </div>
        </div>
    </div>
    <!-- ========== MAIN CONTENT END ========== -->
<script>
    // ============================================
    // USER_KAYTTOEHDOT.PHP - JAVASCRIPT WITH ANIMATIONS
    // ============================================

    // Wait for DOM to fully load
    document.addEventListener('DOMContentLoaded', function() {
        
        // ============================================
        // 1. FADE IN ANIMATION FOR TERMS SECTIONS
        // ============================================
        const termsSections = document.querySelectorAll('.terms-section');
        termsSections.forEach((section, index) => {
            section.style.opacity = '0';
            section.style.transform = 'translateY(30px)';
            setTimeout(() => {
                section.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                section.style.opacity = '1';
                section.style.transform = 'translateY(0)';
            }, index * 150);
        });

        // ============================================
        // 2. CONTENT CARD FADE IN
        // ============================================
        const contentCard = document.querySelector('.content-card');
        if (contentCard) {
            contentCard.style.opacity = '0';
            contentCard.style.transform = 'scale(0.98)';
            setTimeout(() => {
                contentCard.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                contentCard.style.opacity = '1';
                contentCard.style.transform = 'scale(1)';
            }, 100);
        }

        // ============================================
        // 3. HIGHLIGHT BOX PULSE ANIMATION
        // ============================================
        const highlightBox = document.querySelector('.highlight-box');
        if (highlightBox) {
            setInterval(() => {
                highlightBox.style.transform = 'scale(1.01)';
                highlightBox.style.transition = 'transform 0.3s ease';
                setTimeout(() => {
                    highlightBox.style.transform = 'scale(1)';
                }, 300);
            }, 5000);
        }

        // ============================================
        // 4. SECTION ICON HOVER EFFECTS
        // ============================================
        const sectionIcons = document.querySelectorAll('.terms-section h3 i');
        sectionIcons.forEach(icon => {
            icon.addEventListener('mouseenter', () => {
                icon.style.transform = 'scale(1.2) rotate(5deg)';
                icon.style.transition = 'transform 0.2s ease';
            });
            icon.addEventListener('mouseleave', () => {
                icon.style.transform = 'scale(1) rotate(0deg)';
            });
        });

        // ============================================
        // 5. LIST ITEMS STAGGERED ANIMATION
        // ============================================
        const listItems = document.querySelectorAll('.terms-section ul li');
        listItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                item.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }, 500 + (index * 100));
        });

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
        // 7. SIDEBAR MOBILE TOGGLE
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
        // 8. ADD CSS ANIMATIONS TO HEAD
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
            @keyframes pulse {
                0% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.7; transform: scale(1.05); }
                100% { opacity: 1; transform: scale(1); }
            }
            .terms-section {
                animation: fadeInUp 0.5s ease;
            }
            .last-updated {
                animation: slideInRight 0.5s ease;
            }
        `;
        document.head.appendChild(animationStyles);

        // ============================================
        // 9. SMOOTH SCROLL FOR ANCHOR LINKS
        // ============================================
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // ============================================
        // 10. COPY LAST UPDATED DATE TO CLIPBOARD
        // ============================================
        const lastUpdated = document.querySelector('.last-updated');
        if (lastUpdated) {
            lastUpdated.style.cursor = 'pointer';
            lastUpdated.addEventListener('click', () => {
                const dateText = lastUpdated.innerText.replace('Viimeksi päivitetty:', '').trim();
                navigator.clipboard.writeText(dateText);
                
                // Show toast notification
                const toast = document.createElement('div');
                toast.innerHTML = '<i class="fas fa-check-circle"></i> Päivämäärä kopioitu!';
                toast.style.cssText = `
                    position: fixed;
                    bottom: 100px;
                    right: 30px;
                    background: var(--bg-card);
                    backdrop-filter: blur(10px);
                    color: #10b981;
                    padding: 12px 20px;
                    border-radius: 12px;
                    border-left: 4px solid #10b981;
                    z-index: 1000;
                    animation: slideInRight 0.3s ease;
                    box-shadow: var(--shadow);
                `;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }, 2000);
            });
        }

        // ============================================
        // 11. SCROLL REVEAL ANIMATION
        // ============================================
        const revealElements = document.querySelectorAll('.terms-section, .highlight-box');
        
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.2 });
        
        revealElements.forEach(el => {
            el.classList.add('reveal-hidden');
            revealObserver.observe(el);
        });
        
        // Add CSS for reveal animation
        const revealStyles = document.createElement('style');
        revealStyles.textContent = `
            .reveal-hidden {
                opacity: 0;
                transform: translateY(30px);
                transition: opacity 0.6s ease, transform 0.6s ease;
            }
            .reveal-hidden.revealed {
                opacity: 1;
                transform: translateY(0);
            }
        `;
        document.head.appendChild(revealStyles);

        // ============================================
        // 12. PRINT STYLE FUNCTIONALITY
        // ============================================
        const printBtn = document.createElement('button');
        printBtn.innerHTML = '<i class="fas fa-print"></i> Tulosta';
        printBtn.className = 'print-btn';
        printBtn.style.cssText = `
            position: fixed;
            bottom: 30px;
            right: 100px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 10px 18px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
        `;
        
        printBtn.onclick = () => {
            window.print();
        };
        
        printBtn.addEventListener('mouseenter', () => {
            printBtn.style.background = 'var(--gradient-1)';
            printBtn.style.color = 'white';
        });
        
        printBtn.addEventListener('mouseleave', () => {
            printBtn.style.background = 'rgba(255, 255, 255, 0.1)';
            printBtn.style.color = 'var(--text-secondary)';
        });
        
        document.body.appendChild(printBtn);
        
        // Hide print button when scrolling to bottom
        let lastScrollY = window.scrollY;
        window.addEventListener('scroll', () => {
            if (window.scrollY > lastScrollY && window.scrollY > 200) {
                printBtn.style.opacity = '0';
                printBtn.style.transform = 'translateY(20px)';
            } else {
                printBtn.style.opacity = '1';
                printBtn.style.transform = 'translateY(0)';
            }
            lastScrollY = window.scrollY;
        });
    });
</script>
</body>
</html>
