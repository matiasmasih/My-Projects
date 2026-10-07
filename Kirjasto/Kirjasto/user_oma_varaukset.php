<?php
// ============================================
// FILE: user_oma_varaukset.php
// PURPOSE: User's reservations page
// ============================================

session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info
$user_query = "SELECT etunimi, sukunimi, profile_image, rooli, jasennumero FROM jasenet WHERE id = ?";
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

// Set current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

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

// Handle cancel reservation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $cancel_id = $_GET['cancel'];

    // Verify this reservation belongs to the user
    $check_query = "SELECT id FROM varaukset WHERE id = ? AND jasen_id = ? AND tila = 'odottaa'";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $cancel_id, $user_id);
    $stmt->execute();
    $check_result = $stmt->get_result();

    if ($check_result->num_rows > 0) {
        $update_query = "UPDATE varaukset SET tila = 'peruutettu' WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $cancel_id);
        if ($stmt->execute()) {
            header("Location: user_oma_varaukset.php?success=1");
            exit();
        } else {
            $error_message = "Varauksen peruutus epäonnistui.";
        }
    }
}

// Check for success message
if (isset($_GET['success'])) {
    $success_message = "Varaus peruutettu onnistuneesti!";
}

// Get user's active reservations
$reservations_query = "SELECT v.*, k.nimi, k.tekija, k.isbn,
                              (SELECT COUNT(*) FROM Kirjakopiot kp WHERE kp.kirja_id = k.id AND kp.tila = 'saatavilla') as saatavilla
                       FROM varaukset v
                       JOIN kirjat k ON v.kirja_id = k.id
                       WHERE v.jasen_id = ? AND v.tila = 'odottaa'
                       ORDER BY v.varaus_paiva DESC";
$stmt = $conn->prepare($reservations_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reservations_result = $stmt->get_result();

// Get reservation history (past reservations)
$history_query = "SELECT v.*, k.nimi, k.tekija
                  FROM varaukset v
                  JOIN kirjat k ON v.kirja_id = k.id
                  WHERE v.jasen_id = ? AND v.tila != 'odottaa'
                  ORDER BY v.varaus_paiva DESC
                  LIMIT 10";
$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$history_result = $stmt->get_result();

// Calculate statistics
$active_reservations = $reservations_result->num_rows;
$total_reservations = $active_reservations + ($history_result ? $history_result->num_rows : 0);
$ready_count = 0;

// Count how many reservations have available copies
if ($reservations_result->num_rows > 0) {
    $reservations_result->data_seek(0);
    while ($reservation = $reservations_result->fetch_assoc()) {
        if ($reservation['saatavilla'] > 0) {
            $ready_count++;
        }
    }
    $reservations_result->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Omat Varaukset | <?php echo htmlspecialchars($user['etunimi']); ?> <?php echo htmlspecialchars($user['sukunimi']); ?></title>
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

        /* ========== SIDEBAR STYLES ========== */
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

        /* ========== MAIN CONTENT STYLES ========== */
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

        /* ========== STATS GRID ========== */
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

        .stat-icon.success {
            background: var(--gradient-4);
        }

        .stat-icon.warning {
            background: var(--gradient-5);
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

        /* ========== SECTION STYLES ========== */
        .section {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
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

        .badge-count {
            background: rgba(102, 126, 234, 0.15);
            color: #667eea;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid rgba(102, 126, 234, 0.3);
        }

        /* ========== TABLE STYLES ========== */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(255, 255, 255, 0.02);
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        /* ========== STATUS BADGES ========== */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-waiting {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-ready {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-completed {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        /* ========== BUTTON STYLES ========== */
        .btn-small {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
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

        .availability-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.75rem;
        }

        .availability-yes {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .availability-no {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
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

        .notification {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            animation: slideIn 0.4s ease-out;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border-left: 4px solid #10b981;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .stats-grid {
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
            table {
                font-size: 0.8rem;
            }
            th, td {
                padding: 10px;
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
                <h1>Omat varaukset</h1>
                <p><i class="fas fa-circle"></i> Hallinnoi kirjavarauksiasi</p>
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

        <?php if (isset($success_message)): ?>
        <div class="notification notification-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $active_reservations; ?></h3>
                    <p>Aktiiviset varaukset</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $ready_count; ?></h3>
                    <p>Noudettavissa</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning"><i class="fas fa-history"></i></div>
                <div class="stat-info">
                    <h3><?php echo $total_reservations; ?></h3>
                    <p>Varauksia yhteensä</p>
                </div>
            </div>
        </div>

        <!-- Active Reservations -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-clock"></i> Aktiiviset varaukset</h2>
                <span class="badge-count"><?php echo $active_reservations; ?> varausta</span>
            </div>

            <?php if ($active_reservations > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Kirja</th>
                            <th>Tekijä</th>
                            <th>Varauspäivä</th>
                            <th>Tila</th>
                            <th>Toiminnot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($reservation = $reservations_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($reservation['nimi']); ?></strong></td>
                            <td><?php echo htmlspecialchars($reservation['tekija']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($reservation['varaus_paiva'])); ?></td>
                            <td>
                                <?php if ($reservation['saatavilla'] > 0): ?>
                                    <span class="status-badge status-ready">
                                        <i class="fas fa-check-circle"></i> Noudettavissa
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-waiting">
                                        <i class="fas fa-hourglass-half"></i> Odottaa
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?cancel=<?php echo $reservation['id']; ?>" 
                                   class="btn-small btn-danger"
                                   onclick="return confirm('Haluatko varmasti peruuttaa tämän varauksen?');">
                                    <i class="fas fa-trash-alt"></i> Peruuta
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="no-results">
                <i class="fas fa-clock"></i>
                <h3>Ei aktiivisia varauksia</h3>
                <p>Sinulla ei ole yhtään aktiivista varausta tällä hetkellä.</p>
                <a href="user_selaa_kirjoja.php" class="btn-small" style="margin-top: 15px; display: inline-block; background: var(--gradient-1); color: white; padding: 10px 25px; text-decoration: none; border-radius: 30px;">
                    <i class="fas fa-book"></i> Selaa kirjoja
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Reservation History -->
        <?php if ($history_result && $history_result->num_rows > 0): ?>
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Varaushistoria</h2>
                <span class="badge-count"><?php echo $history_result->num_rows; ?> varausta</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Kirja</th>
                            <th>Tekijä</th>
                            <th>Varauspäivä</th>
                            <th>Tila</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($history = $history_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($history['nimi']); ?></strong></td>
                            <td><?php echo htmlspecialchars($history['tekija']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($history['varaus_paiva'])); ?></td>
                            <td>
                                <?php if ($history['tila'] == 'peruutettu'): ?>
                                    <span class="status-badge status-cancelled">
                                        <i class="fas fa-times-circle"></i> Peruutettu
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-completed">
                                        <i class="fas fa-check-circle"></i> Käsitelty
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- ========== MAIN CONTENT END ========== -->

    <script>
        // Auto-hide notifications after 5 seconds
        setTimeout(function() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(function(notification) {
                notification.style.transition = 'opacity 0.5s ease';
                notification.style.opacity = '0';
                setTimeout(function() {
                    if (notification && notification.parentNode) {
                        notification.style.display = 'none';
                    }
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
