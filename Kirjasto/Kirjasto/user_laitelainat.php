<?php
// ============================================
// FILE: user_laitelainat.php
// PURPOSE: User's device loans management
// ============================================

session_start();
require_once 'connection.php';
require_once 'receipt_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info
$user_query = "SELECT id, etunimi, sukunimi, email, jasennumero, rooli, profile_image FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

// Set current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get user initials for avatar
$initials = strtoupper(substr($current_user['etunimi'] ?? '', 0, 1) . substr($current_user['sukunimi'] ?? '', 0, 1));
$membership_number = $current_user['jasennumero'] ?? 'JASEN-' . str_pad($user_id, 6, '0', STR_PAD_LEFT);

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

$success_message = '';
$error_message = '';

// Process device return
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_device'])) {
    $laina_id = (int)$_POST['laina_id'];

    $check_sql = "SELECT l.*, d.merkki, d.malli, d.id as laite_id
                  FROM Laitelainat l
                  JOIN Laitteet d ON l.laite_id = d.id
                  WHERE l.id = ? AND l.jasen_id = ? AND l.palautus_pvm IS NULL";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $laina_id, $user_id);
    $check_stmt->execute();
    $loan_data = $check_stmt->get_result()->fetch_assoc();

    if ($loan_data) {
        $return_date = date('Y-m-d H:i:s');
        $due_date = $loan_data['erapaiva'];
        $fine = 0;

        if ($return_date > $due_date) {
            $days_overdue = (strtotime($return_date) - strtotime($due_date)) / (60 * 60 * 24);
            $fine = $days_overdue * 1.00;
        }

        $update_sql = "UPDATE Laitelainat SET palautus_pvm = ?, myohastyymismaksu = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sdi", $return_date, $fine, $laina_id);

        if ($update_stmt->execute()) {
            $update_device = "UPDATE Laitteet SET tila = 'saatavilla' WHERE id = ?";
            $device_stmt = $conn->prepare($update_device);
            $device_stmt->bind_param("i", $loan_data['laite_id']);
            $device_stmt->execute();

            $device_name = $loan_data['merkki'] . ' ' . $loan_data['malli'];
            createReturnReceipt($user_id, $laina_id, 'device', $device_name, date('Y-m-d H:i:s'));

            $success_message = "Laite '" . htmlspecialchars($device_name) . "' palautettu onnistuneesti!";
            if ($fine > 0) {
                $success_message .= " Sakko: " . number_format($fine, 2, ',', ' ') . " €";
            }
        } else {
            $error_message = "Palautus epäonnistui.";
        }
    } else {
        $error_message = "Lainaa ei löytynyt.";
    }
}

// Get active device loans
$active_sql = "SELECT
    l.id as laina_id,
    l.lainaus_pvm,
    l.erapaiva,
    d.merkki,
    d.malli,
    d.id as laite_id,
    DATEDIFF(NOW(), l.erapaiva) as myohassa_paivia
FROM Laitelainat l
JOIN Laitteet d ON l.laite_id = d.id
WHERE l.jasen_id = ? AND l.palautus_pvm IS NULL
ORDER BY l.erapaiva ASC";

$active_stmt = $conn->prepare($active_sql);
$active_stmt->bind_param("i", $user_id);
$active_stmt->execute();
$active_loans = $active_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get returned device loans
$history_sql = "SELECT
    l.lainaus_pvm,
    l.erapaiva,
    l.palautus_pvm,
    l.myohastyymismaksu as sakko,
    d.merkki,
    d.malli
FROM Laitelainat l
JOIN Laitteet d ON l.laite_id = d.id
WHERE l.jasen_id = ? AND l.palautus_pvm IS NOT NULL
ORDER BY l.palautus_pvm DESC
LIMIT 20";

$history_stmt = $conn->prepare($history_sql);
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$returned_loans = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Omat laitelainat | <?php echo htmlspecialchars($current_user['etunimi']); ?> <?php echo htmlspecialchars($current_user['sukunimi']); ?></title>
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

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            margin-bottom: 20px;
            padding: 8px 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 30px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }

        .back-link:hover {
            background: var(--gradient-1);
            color: white;
            transform: translateX(-5px);
        }

        /* ============================================
           NOTIFICATION STYLES
           ============================================ */
        .notification {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
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

        .notification-error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ============================================
           SECTION STYLES
           ============================================ */
        .section-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .section-header {
            padding: 18px 25px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0, 0, 0, 0.2);
        }

        .section-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 i {
            color: #667eea;
        }

        .count-badge {
            background: rgba(102, 126, 234, 0.2);
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            color: #a78bfa;
            border: 1px solid rgba(102, 126, 234, 0.3);
        }

        .section-content {
            padding: 25px;
        }

        /* ============================================
           TABLE STYLES
           ============================================ */
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
            background: rgba(255, 255, 255, 0.02);
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        /* ============================================
           STATUS BADGES
           ============================================ */
        .status-active {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-overdue {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .fine-amount {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 4px;
        }

        .btn-return {
            background: linear-gradient(135deg, #27AE60, #219653);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }

        .btn-return:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #667eea;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
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
            .section-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead {
                display: none;
            }
            tr {
                margin-bottom: 15px;
                border: 1px solid var(--border-color);
                border-radius: 12px;
                padding: 10px;
            }
            td {
                display: flex;
                justify-content: space-between;
                padding: 10px;
            }
            td::before {
                content: attr(data-label);
                font-weight: bold;
                width: 40%;
                color: var(--text-primary);
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
                <?php if (!empty($current_user['profile_image']) && file_exists("uploads/profiles/" . $current_user['profile_image'])): ?>
                    <img src="uploads/profiles/<?php echo htmlspecialchars($current_user['profile_image']); ?>" alt="Profiilikuva">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="user-info-mini">
                <h4><?php echo htmlspecialchars($current_user['etunimi'] . ' ' . $current_user['sukunimi']); ?></h4>
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
                <h1>Omat laitelainat</h1>
                <p><i class="fas fa-circle"></i> Hallinnoi laitelainojasi</p>
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

        <a href="user_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Takaisin
        </a>

        <?php if ($success_message): ?>
        <div class="notification notification-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="notification notification-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Active Device Loans -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-sync-alt"></i> Aktiiviset laitelainat</h2>
                <span class="count-badge"><?php echo count($active_loans); ?> lainaa</span>
            </div>
            <div class="section-content">
                <?php if (empty($active_loans)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>Sinulla ei ole aktiivisia laitelainoja.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Laite</th>
                                <th>Lainattu</th>
                                <th>Eräpäivä</th>
                                <th>Tila</th>
                                <th>Toiminto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_loans as $loan): ?>
                            <tr>
                                <td data-label="Laite"><strong><?php echo htmlspecialchars($loan['merkki'] . ' ' . $loan['malli']); ?></strong></td>
                                <td data-label="Lainattu"><?php echo date('d.m.Y H:i', strtotime($loan['lainaus_pvm'])); ?></td>
                                <td data-label="Eräpäivä">
                                    <?php echo date('d.m.Y', strtotime($loan['erapaiva'])); ?>
                                    <?php if ($loan['myohassa_paivia'] > 0): ?>
                                        <div class="fine-amount">(<?php echo $loan['myohassa_paivia']; ?> pv myöhässä)</div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Tila">
                                    <?php if ($loan['myohassa_paivia'] > 0): ?>
                                        <span class="status-overdue">Myöhässä</span>
                                    <?php else: ?>
                                        <span class="status-active">Aktiivinen</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Toiminto">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Haluatko varmasti palauttaa tämän laitteen?');">
                                        <input type="hidden" name="laina_id" value="<?php echo $loan['laina_id']; ?>">
                                        <button type="submit" name="return_device" class="btn-return">
                                            <i class="fas fa-undo-alt"></i> Palauta
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Returned Device Loans -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Palautetut laitelainat</h2>
                <span class="count-badge"><?php echo count($returned_loans); ?> lainaa</span>
            </div>
            <div class="section-content">
                <?php if (empty($returned_loans)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Ei palautettuja laitelainoja.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Laite</th>
                                <th>Lainattu</th>
                                <th>Palautettu</th>
                                <th>Sakko</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returned_loans as $loan): ?>
                            <tr>
                                <td data-label="Laite"><?php echo htmlspecialchars($loan['merkki'] . ' ' . $loan['malli']); ?></td>
                                <td data-label="Lainattu"><?php echo date('d.m.Y', strtotime($loan['lainaus_pvm'])); ?></td>
                                <td data-label="Palautettu"><?php echo date('d.m.Y', strtotime($loan['palautus_pvm'])); ?></td>
                                <td data-label="Sakko">
                                    <?php echo $loan['sakko'] > 0 ? number_format($loan['sakko'], 2, ',', ' ') . ' €' : '-'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- ========== MAIN CONTENT END ========== -->

    <script>
        setTimeout(function() {
            document.querySelectorAll('.notification').forEach(function(n) {
                n.style.opacity = '0';
                n.style.transition = 'all 0.3s ease';
                setTimeout(function() { n.style.display = 'none'; }, 300);
            });
        }, 5000);
    </script>
</body>
</html>
