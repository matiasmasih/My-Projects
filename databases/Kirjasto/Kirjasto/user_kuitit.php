<?php
// ============================================
// FILE: user_kuitit.php
// PURPOSE: User receipts page
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
$stmt = $conn->prepare("SELECT id, etunimi, sukunimi, jasennumero, email, puhelin, rooli, profile_image FROM jasenet WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Set current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get user initials for avatar
$initials = strtoupper(substr($user['etunimi'] ?? '', 0, 1) . substr($user['sukunimi'] ?? '', 0, 1));

// Create membership number
$membership_number = $user['jasennumero'] ?? 'JASEN-' . str_pad($user_id, 6, '0', STR_PAD_LEFT);

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

// If user is admin or manager, they can still view but with different perspective
$is_admin = ($user['rooli'] == 'admin' || $user['rooli'] == 'manager');

// Get all user's book loans
$book_loans_query = "SELECT
                        l.id,
                        l.lainauspaiva,
                        l.erapaiva,
                        l.palautuspaiva,
                        l.tila,
                        k.nimi as kirja_nimi,
                        k.tekija as kirja_tekija,
                        DATEDIFF(CURDATE(), l.erapaiva) as myohassa_paivia
                    FROM lainat l
                    JOIN kirjat k ON l.kirja_id = k.id
                    WHERE l.jasen_id = ?
                    ORDER BY l.lainauspaiva DESC";

$stmt = $conn->prepare($book_loans_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$book_loans = $stmt->get_result();
$stmt->close();

// Get all user's device loans
$device_loans_query = "SELECT
                        l.id,
                        l.lainaus_pvm,
                        l.erapaiva,
                        l.palautus_pvm,
                        d.merkki,
                        d.malli,
                        DATEDIFF(CURDATE(), l.erapaiva) as myohassa_paivia
                    FROM Laitelainat l
                    JOIN Laitteet d ON l.laite_id = d.id
                    WHERE l.jasen_id = ?
                    ORDER BY l.lainaus_pvm DESC";

$stmt = $conn->prepare($device_loans_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$device_loans = $stmt->get_result();
$stmt->close();

// Get all user's fines
$fines_query = "SELECT
                    id,
                    sakko_maara,
                    maksettu_maara,
                    sakko_paiva,
                    tila,
                    syy
                FROM sakot
                WHERE jasen_id = ?
                ORDER BY sakko_paiva DESC";

$stmt = $conn->prepare($fines_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$fines = $stmt->get_result();
$stmt->close();

// Get receipts (kuitit)
$receipts_query = "SELECT * FROM kuitit WHERE jasen_id = ? ORDER BY maksupaiva DESC";
$stmt = $conn->prepare($receipts_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$receipts = $stmt->get_result();
$stmt->close();

// Calculate statistics
$total_loans = 0;
$active_loans = 0;
$overdue_loans = 0;
$total_fines = 0;
$unpaid_fines = 0;
$total_receipts = 0;
$total_paid = 0;

// Count book loans
$book_loans_count = 0;
while($loan = $book_loans->fetch_assoc()) {
    $total_loans++;
    $book_loans_count++;
    if($loan['palautuspaiva'] == null) {
        $active_loans++;
        if($loan['myohassa_paivia'] > 0) {
            $overdue_loans++;
        }
    }
}
$book_loans->data_seek(0);

// Count device loans
$device_loans_count = 0;
while($loan = $device_loans->fetch_assoc()) {
    $total_loans++;
    $device_loans_count++;
    if($loan['palautus_pvm'] == null) {
        $active_loans++;
        if($loan['myohassa_paivia'] > 0) {
            $overdue_loans++;
        }
    }
}
$device_loans->data_seek(0);

// Calculate fines
while($fine = $fines->fetch_assoc()) {
    $total_fines += $fine['sakko_maara'];
    if($fine['tila'] != 'maksettu') {
        $unpaid_fines += ($fine['sakko_maara'] - $fine['maksettu_maara']);
    }
}
$fines->data_seek(0);

// Calculate receipts
while($receipt = $receipts->fetch_assoc()) {
    $total_receipts++;
    $total_paid += $receipt['summa'];
}
$receipts->data_seek(0);
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Omat kuitit | <?php echo htmlspecialchars($user['etunimi']); ?> <?php echo htmlspecialchars($user['sukunimi']); ?></title>
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
            --gradient-2: linear-gradient(135deg, #f093fb, #f5576c);
            --gradient-3: linear-gradient(135deg, #4facfe, #00f2fe);
            --gradient-4: linear-gradient(135deg, #43e97b, #38f9d7);
            --gradient-5: linear-gradient(135deg, #fa709a, #fee140);
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
           STATS GRID
           ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(102, 126, 234, 0.3);
            box-shadow: var(--shadow-hover);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: var(--gradient-1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }

        .stat-icon i {
            font-size: 1.3rem;
            color: white;
        }

        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .stat-card p {
            font-size: 0.8rem;
            color: var(--text-muted);
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
           RECEIPT ITEMS
           ============================================ */
        .receipt-item {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            border-left: 4px solid #10b981;
        }

        .receipt-item.overdue {
            border-left-color: #ef4444;
        }

        .receipt-item.returned {
            border-left-color: #6b7280;
            opacity: 0.8;
        }

        .receipt-item:hover {
            transform: translateX(5px);
            background: rgba(255, 255, 255, 0.06);
        }

        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .receipt-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .receipt-title i {
            color: #667eea;
        }

        .receipt-date {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .receipt-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-item i {
            width: 25px;
            color: #667eea;
            font-size: 0.85rem;
        }

        .detail-item span {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .detail-item strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* ============================================
           BADGES
           ============================================ */
        .warning-badge {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 0.75rem;
            color: #fca5a5;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .success-badge {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 0.75rem;
            color: #86efac;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .info-badge {
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 0.75rem;
            color: #93c5fd;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print {
            background: linear-gradient(135deg, #3498DB, #2980B9);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-print:hover {
            background: linear-gradient(135deg, #2980B9, #1a6d8a);
            transform: translateY(-2px);
        }

        .btn-print:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .receipt-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
            color: #667eea;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
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
                grid-template-columns: repeat(2, 1fr);
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
            .receipt-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .receipt-details {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        .btn-pdf {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            margin-left: 10px;
        }
        .btn-pdf {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn-pdf:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: translateY(-2px);
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
                <h1>Omat kuitit</h1>
                <p><i class="fas fa-circle"></i> Tarkastele maksukuittiasi ja lainahistoriaasi</p>
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

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <h3><?php echo $total_loans; ?></h3>
                <p>Lainoja yhteensä</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <h3><?php echo $active_loans; ?></h3>
                <p>Aktiivisia lainoja</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h3><?php echo $overdue_loans; ?></h3>
                <p>Myöhässä olevia</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-euro-sign"></i></div>
                <h3><?php echo number_format($unpaid_fines, 2); ?> €</h3>
                <p>Maksamattomia sakkoja</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                <h3><?php echo $total_receipts; ?></h3>
                <p>Maksettuja kuitteja</p>
            </div>
        </div>

        <!-- Receipts Section (Kuitit) -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-receipt"></i> Maksukuitit</h2>
                <span class="count-badge"><?php echo $total_receipts; ?> kpl</span>
            </div>
            <div class="section-content">
                <?php if ($receipts && $receipts->num_rows > 0): ?>
                    <?php while($receipt = $receipts->fetch_assoc()): ?>
                    <div class="receipt-item">
                        <div class="receipt-header">
                            <div class="receipt-title">
                                <i class="fas fa-receipt"></i> Kuitti #<?php echo $receipt['id']; ?>
                            </div>
                            <div class="receipt-date">
                                <i class="far fa-calendar-alt"></i> <?php echo date('d.m.Y H:i', strtotime($receipt['maksupaiva'])); ?>
                            </div>
                        </div>
                        <div class="receipt-details">
                            <div class="detail-item">
                                <i class="fas fa-info-circle"></i>
                                <span>Kuvaus: <strong><?php echo htmlspecialchars($receipt['kuvaus']); ?></strong></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-euro-sign"></i>
                                <span>Summa: <strong><?php echo number_format($receipt['summa'], 2); ?> €</strong></span>
                            </div>
                        </div>
                        <div class="receipt-actions">
                            <a href="receipt_pdf.php?id=<?php echo $receipt['id']; ?>" class="btn-pdf" target="_blank">
                                <i class="fas fa-file-pdf"></i> Lataa PDF
                            </a>
                            <button class="btn-print" onclick="printReceipt(<?php echo $receipt['id']; ?>, 'receipt')">
                                <i class="fas fa-print"></i> Tulosta kuitti
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <p>Ei maksettuja kuitteja</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Book Loans Section -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-book"></i> Kirjalainat</h2>
                <span class="count-badge"><?php echo $book_loans_count; ?> kpl</span>
            </div>
            <div class="section-content">
                <?php if ($book_loans && $book_loans->num_rows > 0): ?>
                    <?php while($loan = $book_loans->fetch_assoc()):
                        $is_overdue = ($loan['myohassa_paivia'] > 0 && $loan['palautuspaiva'] == null);
                        $is_returned = ($loan['palautuspaiva'] != null);
                    ?>
                    <div class="receipt-item <?php echo $is_overdue ? 'overdue' : ($is_returned ? 'returned' : ''); ?>">
                        <div class="receipt-header">
                            <div class="receipt-title">
                                <i class="fas fa-book-open"></i> <?php echo htmlspecialchars($loan['kirja_nimi']); ?>
                            </div>
                            <div class="receipt-date">
                                <i class="far fa-calendar-alt"></i> Lainattu: <?php echo date('d.m.Y', strtotime($loan['lainauspaiva'])); ?>
                            </div>
                        </div>
                        <div class="receipt-details">
                            <div class="detail-item">
                                <i class="fas fa-user"></i>
                                <span>Tekijä: <strong><?php echo htmlspecialchars($loan['kirja_tekija']); ?></strong></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-calendar-check"></i>
                                <span>Palautettava: <strong><?php echo date('d.m.Y', strtotime($loan['erapaiva'])); ?></strong></span>
                            </div>
                            <?php if($loan['palautuspaiva']): ?>
                            <div class="detail-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Palautettu: <strong><?php echo date('d.m.Y', strtotime($loan['palautuspaiva'])); ?></strong></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if($is_overdue): ?>
                        <div class="warning-badge">
                            <i class="fas fa-exclamation-triangle"></i>
                            Myöhässä <?php echo $loan['myohassa_paivia']; ?> päivää! Sakkoja kertyy.
                        </div>
                        <?php elseif($is_returned): ?>
                        <div class="success-badge">
                            <i class="fas fa-check-circle"></i>
                            Palautettu ajallaan
                        </div>
                        <?php else: ?>
                        <div class="info-badge">
                            <i class="fas fa-clock"></i>
                            Laina aktiivinen - palauta eräpäivään mennessä
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <p>Ei kirjalainoja</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Device Loans Section -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-laptop"></i> Laitelainat</h2>
                <span class="count-badge"><?php echo $device_loans_count; ?> kpl</span>
            </div>
            <div class="section-content">
                <?php if ($device_loans && $device_loans->num_rows > 0): ?>
                    <?php while($loan = $device_loans->fetch_assoc()):
                        $is_overdue = ($loan['myohassa_paivia'] > 0 && $loan['palautus_pvm'] == null);
                        $is_returned = ($loan['palautus_pvm'] != null);
                    ?>
                    <div class="receipt-item <?php echo $is_overdue ? 'overdue' : ($is_returned ? 'returned' : ''); ?>">
                        <div class="receipt-header">
                            <div class="receipt-title">
                                <i class="fas fa-mobile-alt"></i> <?php echo htmlspecialchars($loan['merkki'] . ' ' . $loan['malli']); ?>
                            </div>
                            <div class="receipt-date">
                                <i class="far fa-calendar-alt"></i> Lainattu: <?php echo date('d.m.Y', strtotime($loan['lainaus_pvm'])); ?>
                            </div>
                        </div>
                        <div class="receipt-details">
                            <div class="detail-item">
                                <i class="fas fa-calendar-check"></i>
                                <span>Palautettava: <strong><?php echo date('d.m.Y', strtotime($loan['erapaiva'])); ?></strong></span>
                            </div>
                            <?php if($loan['palautus_pvm']): ?>
                            <div class="detail-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Palautettu: <strong><?php echo date('d.m.Y', strtotime($loan['palautus_pvm'])); ?></strong></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if($is_overdue): ?>
                        <div class="warning-badge">
                            <i class="fas fa-exclamation-triangle"></i>
                            Myöhässä <?php echo $loan['myohassa_paivia']; ?> päivää! Sakkoja kertyy.
                        </div>
                        <?php elseif($is_returned): ?>
                        <div class="success-badge">
                            <i class="fas fa-check-circle"></i>
                            Palautettu ajallaan
                        </div>
                        <?php else: ?>
                        <div class="info-badge">
                            <i class="fas fa-clock"></i>
                            Laina aktiivinen - palauta eräpäivään mennessä
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-laptop"></i>
                        <p>Ei laitelainoja</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Fines Section -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fas fa-euro-sign"></i> Sakot</h2>
                <span class="count-badge"><?php echo $fines->num_rows; ?> kpl</span>
            </div>
            <div class="section-content">
                <?php if ($fines && $fines->num_rows > 0): ?>
                    <?php while($fine = $fines->fetch_assoc()):
                        $unpaid = $fine['sakko_maara'] - $fine['maksettu_maara'];
                    ?>
                    <div class="receipt-item">
                        <div class="receipt-header">
                            <div class="receipt-title">
                                <i class="fas fa-receipt"></i> Sakko #<?php echo $fine['id']; ?>
                            </div>
                            <div class="receipt-date">
                                <i class="far fa-calendar-alt"></i> <?php echo date('d.m.Y', strtotime($fine['sakko_paiva'])); ?>
                            </div>
                        </div>
                        <div class="receipt-details">
                            <div class="detail-item">
                                <i class="fas fa-info-circle"></i>
                                <span>Syy: <strong><?php echo htmlspecialchars($fine['syy']); ?></strong></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-euro-sign"></i>
                                <span>Summa: <strong><?php echo number_format($fine['sakko_maara'], 2); ?> €</strong></span>
                            </div>
                            <?php if($unpaid > 0): ?>
                            <div class="detail-item">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Maksettavaa: <strong style="color:#ef4444;"><?php echo number_format($unpaid, 2); ?> €</strong></span>
                            </div>
                            <?php else: ?>
                            <div class="detail-item">
                                <i class="fas fa-check-circle"></i>
                                <span>Tila: <strong style="color:#10b981;">Maksettu</strong></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if($unpaid > 0): ?>
                        <div class="warning-badge">
                            <i class="fas fa-exclamation-triangle"></i>
                            Sakko maksamatta! Ole hyvä ja maksa viipymättä.
                        </div>
                        <?php else: ?>
                        <div class="success-badge">
                            <i class="fas fa-check-circle"></i>
                            Sakko maksettu
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-euro-sign"></i>
                        <p>Ei sakkoja</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- ========== MAIN CONTENT END ========== -->

    <script>
        function printReceipt(id, type) {
            window.open('user_print_loan_receipt.php?id=' + id + '&type=' + type, '_blank', 'width=500,height=600');
        }

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
