<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info - MUISTA HAKIA 'rooli'!
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

// Get user's device loans
$loans_query = "SELECT ll.*, l.merkki, l.malli, lt.nimi as tyyppi_nimi,
                       DATEDIFF(CURDATE(), ll.erapaiva) as days_overdue
                FROM Laitelainat ll
                JOIN Laitteet l ON ll.laite_id = l.id
                LEFT JOIN Laitetyypit lt ON l.laite_tyyppi_id = lt.id
                WHERE ll.jasen_id = ?
                ORDER BY
                    CASE
                        WHEN ll.palautus_pvm IS NULL AND ll.erapaiva < CURDATE() THEN 0
                        WHEN ll.palautus_pvm IS NULL THEN 1
                        ELSE 2
                    END,
                    ll.erapaiva ASC";
$stmt = $conn->prepare($loans_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$loans_result = $stmt->get_result();

// Get active loans count
$active_query = "SELECT COUNT(*) as count FROM Laitelainat WHERE jasen_id = ? AND palautus_pvm IS NULL";
$stmt = $conn->prepare($active_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_result = $stmt->get_result();
$active_count = $active_result->fetch_assoc()['count'];

// Get overdue loans count
$overdue_query = "SELECT COUNT(*) as count FROM Laitelainat WHERE jasen_id = ? AND palautus_pvm IS NULL AND erapaiva < CURDATE()";
$stmt = $conn->prepare($overdue_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$overdue_result = $stmt->get_result();
$overdue_count = $overdue_result->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laitelainat | <?php echo htmlspecialchars($user['etunimi']); ?> <?php echo htmlspecialchars($user['sukunimi']); ?></title>
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
            overflow: hidden; /* Estää koko sivun scrollauksen */
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
            overflow-y: auto; /* Vain main-content scrollaa */
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: #667eea;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: var(--gradient-1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
        }

        .stat-icon.warning {
            background: var(--gradient-5);
        }

        .stat-icon.info {
            background: var(--gradient-3);
        }

        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .stat-info p {
            font-size: 0.8rem;
            color: var(--text-muted);
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

        .table-responsive {
            overflow-x: auto;
            border-radius: 16px;
            max-height: 400px; /* Taulukon maksimikorkeus */
            overflow-y: auto; /* Vain taulukko scrollaa pystysuunnassa */
        }

        /* Custom scrollbar */
        .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--gradient-1);
            border-radius: 10px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #764ba2, #667eea);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px; /* Pakotetaan vaakascrolli jos näyttö pieni */
        }

        th {
            text-align: left;
            padding: 16px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #667eea;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(102, 126, 234, 0.05);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-overdue {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-returned {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn-small {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
            color: #667eea;
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            background: #ef4444;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .no-results {
            text-align: center;
            padding: 60px;
            color: var(--text-muted);
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #667eea;
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
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .top-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .section-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
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
            <a href="user_suosikit.php" class="menu-item <?php echo $current_page == 'user_suosikit.php' ? 'active' : ''; ?>">
                <i class="fas fa-heart"></i>
                <span>Suosikit</span>
            </a>

            <div class="menu-section">Laitteet</div>
            <a href="user_selaa_laitteita.php" class="menu-item">
                <i class="fas fa-laptop"></i>
                <span>Selaa Laitteita</span>
            </a>
            <a href="admin_laitelainat.php" class="menu-item active">
                <i class="fas fa-mobile-alt"></i>
                <span>Laitelainat</span>
            </a>

            <div class="menu-section">Sakot</div>
            <a href="user_sakot.php" class="menu-item">
                 <i class="fas fa-euro-sign"></i>
                 <span>Hallinnoi Sakkoja</span>
             </a>

            <div class="menu-section">Tietoa</div>
            <a href="user_yhteystiedot.php" class="menu-item">
                 <i class="fas fa-map-marker-alt"></i>
                 <span>Yhteystiedot</span>
             </a>
             <a href="user_kayttoehdot.php" class="menu-item">
                  <i class="fas fa-file-alt"></i>
                  <span>Käyttöehdot</span>
             </a>
             <a href="user_ilmoitukset.php" class="menu-item">
                  <i class="fas fa-bell"></i>
                  <span>Ilmoitukset</span>
                <?php if (isset($unread_messages_count) && $unread_messages_count > 0): ?>
                  <span style="background: #ef4444; color: white; padding: 2px 6px; border-radius: 30px; font-size: 0.7rem; margin-left: 5px;">
                <?php echo $unread_messages_count; ?>
                  </span>
                <?php endif; ?>
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
                <h1>Laitelainat</h1>
                <p><i class="fas fa-circle"></i> Lainaamasi laitteet</p>
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

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $active_count; ?></h3>
                    <p>Aktiiviset lainat</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $overdue_count; ?></h3>
                    <p>Myöhässä</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $loans_result->num_rows; ?></h3>
                    <p>Lainoja yhteensä</p>
                </div>
            </div>
        </div>

        <!-- Loans Table -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-list"></i> Lainahistoria</h2>
            </div>

            <?php if ($loans_result && $loans_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Laite</th>
                                <th>Tyyppi</th>
                                <th>Lainauspäivä</th>
                                <th>Eräpäivä</th>
                                <th>Palautuspäivä</th>
                                <th>Tila</th>
                                <th>Toiminnot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($loan = $loans_result->fetch_assoc()):
                                $is_active = is_null($loan['palautus_pvm']);
                                $is_overdue = $is_active && strtotime($loan['erapaiva']) < time();
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($loan['merkki'] . ' ' . $loan['malli']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($loan['tyyppi_nimi'] ?? 'Laite'); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($loan['lainaus_pvm'])); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($loan['erapaiva'])); ?></td>
                                    <td>
                                        <?php if ($loan['palautus_pvm']): ?>
                                            <?php echo date('d.m.Y', strtotime($loan['palautus_pvm'])); ?>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">–</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($loan['palautus_pvm']): ?>
                                            <span class="status-badge status-returned">
                                                <i class="fas fa-check-circle"></i> Palautettu
                                            </span>
                                        <?php elseif ($is_overdue): ?>
                                            <span class="status-badge status-overdue">
                                                <i class="fas fa-exclamation-triangle"></i> Myöhässä <?php echo abs($loan['days_overdue']); ?> pv
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-active">
                                                <i class="fas fa-clock"></i> Lainassa
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$loan['palautus_pvm']): ?>
                                            <div class="action-buttons">
                                                <a href="jatka_lainaa.php?id=<?php echo $loan['id']; ?>" class="btn-small btn-outline">
                                                    <i class="fas fa-redo-alt"></i> Jatka
                                                </a>
                                                <a href="palauta_laite.php?id=<?php echo $loan['id']; ?>" class="btn-small btn-danger" onclick="return confirm('Haluatko varmasti palauttaa tämän laitteen?')">
                                                    <i class="fas fa-undo-alt"></i> Palauta
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-mobile-alt"></i>
                    <h3 style="margin-bottom: 10px;">Ei laitelainoja</h3>
                    <p>Sinulla ei ole vielä lainattuja laitteita</p>
                    <a href="user_selaa_laitteita.php" class="btn-small btn-outline" style="margin-top: 20px; display: inline-block; padding: 12px 30px;">
                        <i class="fas fa-laptop"></i> Selaa laitteita
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
