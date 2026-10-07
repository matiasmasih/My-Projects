<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
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

// If user is admin or manager, redirect
if ($user['rooli'] == 'admin') {
    header("Location: admin_dashboard.php");
    exit();
} elseif ($user['rooli'] == 'manager') {
    header("Location: manager_dashboard.php");
    exit();
}

// Handle add to favorites
if (isset($_GET['add']) && isset($_GET['type']) && isset($_GET['id'])) {
    $type = $_GET['type'];
    $id = (int)$_GET['id'];
    
    // Check if already in favorites
    $check = "SELECT id FROM suosikit WHERE jasen_id = ? AND kohde_tyyppi = ? AND kohde_id = ?";
    $stmt = $conn->prepare($check);
    $stmt->bind_param("isi", $user_id, $type, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        $insert = "INSERT INTO suosikit (jasen_id, kohde_tyyppi, kohde_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert);
        $stmt->bind_param("isi", $user_id, $type, $id);
        $stmt->execute();
    }
    header("Location: user_suosikit.php?success=added");
    exit();
}

// Handle remove from favorites
if (isset($_GET['remove']) && isset($_GET['type']) && isset($_GET['id'])) {
    $type = $_GET['type'];
    $id = (int)$_GET['id'];
    
    $delete = "DELETE FROM suosikit WHERE jasen_id = ? AND kohde_tyyppi = ? AND kohde_id = ?";
    $stmt = $conn->prepare($delete);
    $stmt->bind_param("isi", $user_id, $type, $id);
    $stmt->execute();
    
    header("Location: user_suosikit.php?success=removed");
    exit();
}

// Get user's favorite books
$favorite_books = [];
$book_query = "SELECT s.*, k.nimi, k.tekija, k.julkaisuvuosi, k.isbn,
                      (SELECT COUNT(*) FROM Kirjakopiot kp WHERE kp.kirja_id = k.id AND kp.tila = 'saatavilla') as saatavilla
               FROM suosikit s
               JOIN kirjat k ON s.kohde_id = k.id
               WHERE s.jasen_id = ? AND s.kohde_tyyppi = 'kirja'
               ORDER BY s.luotu DESC";
$stmt = $conn->prepare($book_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$favorite_books = $stmt->get_result();

// Get user's favorite devices
$favorite_devices = [];
$device_query = "SELECT s.*, l.merkki, l.malli, lt.nimi as tyyppi_nimi, l.kunto, l.tila
                FROM suosikit s
                JOIN Laitteet l ON s.kohde_id = l.id
                LEFT JOIN Laitetyypit lt ON l.laite_tyyppi_id = lt.id
                WHERE s.jasen_id = ? AND s.kohde_tyyppi = 'laite'
                ORDER BY s.luotu DESC";
$stmt = $conn->prepare($device_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$favorite_devices = $stmt->get_result();

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

// Get current page name for active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Omat suosikit | <?php echo htmlspecialchars($user['etunimi']); ?> <?php echo htmlspecialchars($user['sukunimi']); ?></title>
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

        .section {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .section-header h2 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 i {
            color: #667eea;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #667eea;
        }

        .books-grid, .devices-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .book-card, .device-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s;
            position: relative;
        }

        .book-card:hover, .device-card:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            box-shadow: var(--shadow-hover);
        }

        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #ef4444;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .favorite-btn:hover {
            transform: scale(1.1);
        }

        .book-title, .device-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
            padding-right: 25px;
        }

        .book-author, .device-model {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .book-meta, .device-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        .available-badge {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.75rem;
        }

        .btn-small {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--gradient-1);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        @media (max-width: 1200px) {
            .books-grid, .devices-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .books-grid, .devices-grid {
                grid-template-columns: 1fr;
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

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Omat suosikit</h1>
                <p><i class="fas fa-circle"></i> Tallentamasi kirjat ja laitteet</p>
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

        <?php if (isset($_GET['success'])): ?>
            <div style="background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid rgba(16,185,129,0.3); padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle"></i>
                <?php if ($_GET['success'] == 'added'): ?>
                    Suosikki lisätty onnistuneesti!
                <?php elseif ($_GET['success'] == 'removed'): ?>
                    Suosikki poistettu onnistuneesti!
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Favorite Books -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-book"></i> Suosikkikirjat</h2>
                <a href="user_selaa_kirjoja.php" class="btn-small btn-primary">
                    <i class="fas fa-plus"></i> Selaa kirjoja
                </a>
            </div>

            <?php if ($favorite_books && $favorite_books->num_rows > 0): ?>
                <div class="books-grid">
                    <?php while ($book = $favorite_books->fetch_assoc()): ?>
                        <div class="book-card">
                            <a href="user_suosikit.php?remove=1&type=kirja&id=<?php echo $book['kohde_id']; ?>" class="favorite-btn" onclick="return confirm('Poistetaanko kirja suosikeista?')">
                                <i class="fas fa-heart"></i>
                            </a>
                            <div class="book-title"><?php echo htmlspecialchars($book['nimi']); ?></div>
                            <div class="book-author"><?php echo htmlspecialchars($book['tekija']); ?></div>
                            <?php if (!empty($book['julkaisuvuosi'])): ?>
                                <div style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 10px;">
                                    <?php echo $book['julkaisuvuosi']; ?>
                                </div>
                            <?php endif; ?>
                            <div class="book-meta">
                                <span class="available-badge">
                                    <i class="fas fa-copy"></i> <?php echo $book['saatavilla']; ?> saatavilla
                                </span>
                                <?php if ($book['saatavilla'] > 0): ?>
                                    <a href="user_varaa_kirja.php?id=<?php echo $book['kohde_id']; ?>" class="btn-small btn-primary">
                                        <i class="fas fa-bookmark"></i> Varaa
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-heart"></i>
                    <h3 style="margin-bottom: 10px;">Ei suosikkikirjoja</h3>
                    <p>Lisää kirjoja suosikkeihin selatessasi</p>
                    <a href="user_selaa_kirjoja.php" class="btn-small btn-primary" style="margin-top: 15px; display: inline-block;">
                        <i class="fas fa-book"></i> Selaa kirjoja
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Favorite Devices -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-laptop"></i> Suosikkilaitteet</h2>
                <a href="user_selaa_laitteita.php" class="btn-small btn-primary">
                    <i class="fas fa-plus"></i> Selaa laitteita
                </a>
            </div>

            <?php if ($favorite_devices && $favorite_devices->num_rows > 0): ?>
                <div class="devices-grid">
                    <?php while ($device = $favorite_devices->fetch_assoc()): ?>
                        <div class="device-card">
                            <a href="user_suosikit.php?remove=1&type=laite&id=<?php echo $device['kohde_id']; ?>" class="favorite-btn" onclick="return confirm('Poistetaanko laite suosikeista?')">
                                <i class="fas fa-heart"></i>
                            </a>
                            <div class="device-title"><?php echo htmlspecialchars($device['merkki']); ?></div>
                            <div class="device-model"><?php echo htmlspecialchars($device['malli']); ?></div>
                            <div style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 10px;">
                                <?php echo htmlspecialchars($device['tyyppi_nimi'] ?? 'Laite'); ?>
                            </div>
                            <div class="device-meta">
                                <span class="available-badge" style="<?php echo $device['tila'] == 'saatavilla' ? 'background: rgba(16,185,129,0.15); color: #10b981;' : 'background: rgba(239,68,68,0.15); color: #ef4444;'; ?>">
                                    <i class="fas fa-<?php echo $device['tila'] == 'saatavilla' ? 'check-circle' : 'times-circle'; ?>"></i>
                                    <?php echo $device['tila'] == 'saatavilla' ? 'Saatavilla' : 'Ei saatavilla'; ?>
                                </span>
                                <?php if ($device['tila'] == 'saatavilla'): ?>
                                    <a href="user_varaa_laite.php?id=<?php echo $device['kohde_id']; ?>" class="btn-small btn-primary">
                                        <i class="fas fa-calendar-plus"></i> Varaa
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-laptop"></i>
                    <h3 style="margin-bottom: 10px;">Ei suosikkilaitteita</h3>
                    <p>Lisää laitteita suosikkeihin selatessasi</p>
                    <a href="user_selaa_laitteita.php" class="btn-small btn-primary" style="margin-top: 15px; display: inline-block;">
                        <i class="fas fa-laptop"></i> Selaa laitteita
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
