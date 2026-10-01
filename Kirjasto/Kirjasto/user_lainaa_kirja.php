<?php
// ============================================
// FILE: user_lainaa_kirja.php
// PURPOSE: User book borrowing page
// ============================================

session_start();
require_once 'connection.php';
require_once 'receipt_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

// Check if user is admin/manager - redirect
if ($current_user['rooli'] == 'admin' || $current_user['rooli'] == 'manager') {
    header("Location: admin_lainat.php");
    exit();
}

// Get book ID from URL
$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success_message = '';
$error_message = '';

// Get book details
$book_sql = "SELECT k.*,
             (SELECT COUNT(*) FROM lainat WHERE kirja_id = k.id AND tila = 'aktiivinen') as active_loans
             FROM kirjat k WHERE k.id = ?";
$book_stmt = $conn->prepare($book_sql);
$book_stmt->bind_param("i", $book_id);
$book_stmt->execute();
$book = $book_stmt->get_result()->fetch_assoc();

// Check if book is available
$is_available = ($book && $book['active_loans'] == 0);

// Process borrowing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['borrow_book'])) {
    $book_id = (int)$_POST['book_id'];

    // Check if user already has this book borrowed
    $check_sql = "SELECT id FROM lainat WHERE jasen_id = ? AND kirja_id = ? AND tila = 'aktiivinen'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $book_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();

    if ($existing) {
        $error_message = "Olet jo lainannut tämän kirjan!";
    } else {
        // Check max loans (5 books at a time)
        $loan_count_sql = "SELECT COUNT(*) as count FROM lainat WHERE jasen_id = ? AND tila = 'aktiivinen'";
        $count_stmt = $conn->prepare($loan_count_sql);
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $loan_count = $count_stmt->get_result()->fetch_assoc()['count'];

        if ($loan_count >= 5) {
            $error_message = "Sinulla on jo 5 lainaa. Palauta ensin jokin kirja.";
        } else {
            // Calculate due date (14 days from today)
            $borrow_date = date('Y-m-d');
            $due_date = date('Y-m-d', strtotime('+30 days'));

            // Insert loan
            $insert_sql = "INSERT INTO lainat (jasen_id, kirja_id, lainauspaiva, erapaiva, tila, sakot)
                           VALUES (?, ?, ?, ?, 'aktiivinen', 0)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iiss", $user_id, $book_id, $borrow_date, $due_date);

            if ($insert_stmt->execute()) {
                $loan_id = $insert_stmt->insert_id;

                // Generate receipt
                createLoanReceipt($user_id, $loan_id, 'book', $book['nimi'], date('Y-m-d H:i:s'));

                $success_message = "Kirja '" . htmlspecialchars($book['nimi']) . "' lainattu onnistuneesti!<br>
                                    Lainauspäivä: " . date('d.m.Y', strtotime($borrow_date)) . "<br>
                                    Palautuspäivä: " . date('d.m.Y', strtotime($due_date));
            } else {
                $error_message = "Lainaus epäonnistui. Yritä uudelleen.";
            }
        }
    }
}

// Get user's active loans
$active_sql = "SELECT l.*, k.nimi as kirja_nimi, k.tekija
               FROM lainat l
               JOIN kirjat k ON l.kirja_id = k.id
               WHERE l.jasen_id = ? AND l.tila = 'aktiivinen'
               ORDER BY l.erapaiva ASC";
$active_stmt = $conn->prepare($active_sql);
$active_stmt->bind_param("i", $user_id);
$active_stmt->execute();
$active_loans = $active_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <title>Lainaa kirja | <?php echo htmlspecialchars($current_user['etunimi']); ?> <?php echo htmlspecialchars($current_user['sukunimi']); ?></title>
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
           CSS VARIABLES (Dark Theme)
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
           BACK BUTTON
           ============================================ */
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
           BOOK DETAILS SECTION
           ============================================ */
        .book-section {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }

        .book-details {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .book-cover {
            flex: 0 0 200px;
            background: var(--gradient-1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 250px;
            color: white;
            font-size: 4rem;
        }

        .book-info {
            flex: 1;
        }

        .book-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .book-author {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }

        .book-isbn {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 15px;
        }

        .book-description {
            font-size: 0.95rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .book-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .meta-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 10px 15px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .meta-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .meta-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .status-available {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            display: inline-block;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-unavailable {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            display: inline-block;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* ============================================
           BUTTON STYLES
           ============================================ */
        .btn-borrow {
            background: linear-gradient(135deg, #27AE60, #219653);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            margin-top: 10px;
            margin-left: 15px;
        }

        .btn-borrow:hover:not(:disabled) {
            transform: scale(1.02);
            box-shadow: 0 5px 20px rgba(39, 174, 96, 0.4);
        }

        .btn-borrow:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        /* ============================================
           RULES SECTION
           ============================================ */
        .rules-section {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rules-list {
            list-style: none;
            padding: 0;
        }

        .rules-list li {
            padding: 12px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .rules-list li i {
            width: 25px;
            color: #667eea;
        }

        /* ============================================
           ACTIVE LOANS SECTION
           ============================================ */
        .loans-section {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }

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

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .status-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
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

        /* ============================================
           RESPONSIVE DESIGN
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
            .book-details {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .book-title {
                font-size: 1.5rem;
            }
            .book-meta {
                justify-content: center;
            }
            .top-bar {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .section-title {
                font-size: 1.1rem;
                flex-wrap: wrap;
            }
            .rules-list li {
                font-size: 0.9rem;
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
                <h1>Lainaa kirja</h1>
                <p><i class="fas fa-circle"></i> Lainaaminen on helppoa</p>
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

        <a href="user_selaa_kirjoja.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Takaisin kirjoihin
        </a>

        <?php if ($success_message): ?>
        <div class="notification notification-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="notification notification-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <?php if ($book): ?>
        <!-- Book Details Section -->
        <div class="book-section">
            <div class="book-details">
                <div class="book-cover">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="book-info">
                    <h1 class="book-title"><?php echo htmlspecialchars($book['nimi']); ?></h1>
                    <p class="book-author"><i class="fas fa-user-pen"></i> <?php echo htmlspecialchars($book['tekija']); ?></p>
                    <p class="book-isbn"><i class="fas fa-barcode"></i> ISBN: <?php echo htmlspecialchars($book['isbn'] ?? 'Ei tiedossa'); ?></p>
                    <div class="book-description">
                        <?php echo htmlspecialchars($book['kuvaus'] ?? 'Ei kuvausta saatavilla.'); ?>
                    </div>
                    <div class="book-meta">
                        <div class="meta-item">
                            <div class="meta-label">Julkaisuvuosi</div>
                            <div class="meta-value"><?php echo htmlspecialchars($book['julkaisuvuosi'] ?? '-'); ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Kustantaja</div>
                            <div class="meta-value"><?php echo htmlspecialchars($book['kustantaja'] ?? '-'); ?></div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Kategoria</div>
                            <div class="meta-value"><?php echo htmlspecialchars($book['kategoria'] ?? '-'); ?></div>
                        </div>
                    </div>

                    <?php if ($is_available): ?>
                        <span class="status-available">
                            <i class="fas fa-check-circle"></i> Saatavilla
                        </span>
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                            <button type="submit" name="borrow_book" class="btn-borrow" onclick="return confirm('Haluatko varmasti lainata tämän kirjan? Laina-aika on 14 päivää.');">
                                <i class="fas fa-hand-holding-heart"></i> Lainaa kirja
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="status-unavailable">
                            <i class="fas fa-times-circle"></i> Lainassa
                        </span>
                        <button class="btn-borrow" disabled>
                            <i class="fas fa-ban"></i> Ei saatavilla
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Borrowing Rules Section -->
        <div class="rules-section">
            <div class="section-title">
                <i class="fas fa-gavel"></i> Lainaamisen säännöt
            </div>
            <ul class="rules-list">
                <li><i class="fas fa-calendar-alt"></i> Laina-aika on 30 päivää</li>
                <li><i class="fas fa-book"></i> Voit lainata enintään 5 kirjaa kerrallaan</li>
                <li><i class="fas fa-euro-sign"></i> Myöhästymismaksu on 1€/päivä/kirja</li>
                <li><i class="fas fa-undo-alt"></i> Lainaa voi jatkaa kerran, jos kirjaa ei ole varattu</li>
                <li><i class="fas fa-clock"></i> Myöhästyneistä lainoista tulee muistutus sähköpostiin</li>
            </ul>
        </div>
        <?php else: ?>
        <div class="book-section">
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <p>Kirjaa ei löytynyt.</p>
                <a href="user_selaa_kirjoja.php" class="btn-borrow" style="display: inline-block; margin-top: 15px; text-decoration: none;">
                    Takaisin kirjoihin
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Loans Section -->
        <div class="loans-section">
            <div class="section-title">
                <i class="fas fa-list"></i> Aktiiviset lainasi
                <span style="font-size: 0.8rem; background: rgba(255,255,255,0.1); padding: 2px 10px; border-radius: 20px;">
                    <?php echo count($active_loans); ?>/5
                </span>
            </div>

            <?php if (empty($active_loans)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Sinulla ei ole aktiivisia lainoja.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Kirja</th>
                            <th>Tekijä</th>
                            <th>Lainattu</th>
                            <th>Palautettava</th>
                            <th>Tila</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_loans as $loan):
                            $today = date('Y-m-d');
                            $due_date = $loan['erapaiva'];
                            $is_overdue = $due_date < $today;
                        ?>
                        <tr>
                            <td data-label="Kirja"><strong><?php echo htmlspecialchars($loan['kirja_nimi']); ?></strong></td>
                            <td data-label="Tekijä"><?php echo htmlspecialchars($loan['tekija']); ?></td>
                            <td data-label="Lainattu"><?php echo date('d.m.Y', strtotime($loan['lainauspaiva'])); ?></td>
                            <td data-label="Palautettava"><?php echo date('d.m.Y', strtotime($loan['erapaiva'])); ?></td>
                            <td data-label="Tila">
                                <?php if ($is_overdue): ?>
                                    <span class="status-badge status-warning">
                                        <i class="fas fa-exclamation-triangle"></i> Myöhässä
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-active">
                                        <i class="fas fa-clock"></i> Aktiivinen
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- ========== MAIN CONTENT END ========== -->

<script>
    // ============================================
    // USER_LAINAA_KIRJA.PHP - JAVASCRIPT
    // ============================================

    // Wait for DOM to fully load
    document.addEventListener('DOMContentLoaded', function() {

        // ============================================
        // 1. AUTO-HIDE NOTIFICATIONS AFTER 5 SECONDS
        // ============================================
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

        // ============================================
        // 2. BORROW BUTTON CONFIRMATION (Already in HTML)
        // Additional safety check
        // ============================================
        const borrowButtons = document.querySelectorAll('.btn-borrow');
        borrowButtons.forEach(function(button) {
            if (button.type === 'submit') {
                button.addEventListener('click', function(e) {
                    if (!confirm('Haluatko varmasti lainata tämän kirjan?\n\nLaina-aika: 14 päivää\nMyöhästymismaksu: 1€/päivä')) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });

        // ============================================
        // 3. ACTIVE LOANS TABLE - ADD DATA-LABELS FOR MOBILE
        // ============================================
        const tables = document.querySelectorAll('table');
        tables.forEach(function(table) {
            const headers = table.querySelectorAll('thead th');
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(function(row) {
                const cells = row.querySelectorAll('td');
                cells.forEach(function(cell, index) {
                    if (headers[index]) {
                        const headerText = headers[index].innerText;
                        cell.setAttribute('data-label', headerText);
                    }
                });
            });
        });

        // ============================================
        // 4. SIDEBAR TOGGLE FOR MOBILE (if needed)
        // ============================================
        // Create mobile toggle button if screen width < 1024px
        function createMobileToggle() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');

            // Check if toggle button already exists
            if (document.querySelector('.mobile-toggle')) {
                return;
            }

            // Only add toggle button on mobile screens
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
                `;

                toggleBtn.onclick = function() {
                    if (sidebar.style.transform === 'translateX(0px)') {
                        sidebar.style.transform = 'translateX(-100%)';
                        mainContent.style.marginLeft = '0';
                    } else {
                        sidebar.style.transform = 'translateX(0)';
                        mainContent.style.marginLeft = '0';
                    }
                };

                document.body.appendChild(toggleBtn);

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(e) {
                    if (window.innerWidth <= 1024) {
                        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                            sidebar.style.transform = 'translateX(-100%)';
                        }
                    }
                });
            }
        }

        // Call on load and on resize
        createMobileToggle();
        window.addEventListener('resize', function() {
            const existingToggle = document.querySelector('.mobile-toggle');
            if (existingToggle) {
                existingToggle.remove();
            }
            createMobileToggle();
        });

        // ============================================
        // 5. ADD HOVER EFFECT ON BOOK COVER
        // ============================================
        const bookCover = document.querySelector('.book-cover');
        if (bookCover) {
            bookCover.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.02)';
                this.style.transition = 'transform 0.3s ease';
            });
            bookCover.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        }

        // ============================================
        // 6. RULE ITEMS ANIMATION ON HOVER
        // ============================================
        const ruleItems = document.querySelectorAll('.rules-list li');
        ruleItems.forEach(function(item) {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(5px)';
                this.style.transition = 'transform 0.2s ease';
                const icon = this.querySelector('i');
                if (icon) {
                    icon.style.color = '#10b981';
                }
            });
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
                const icon = this.querySelector('i');
                if (icon) {
                    icon.style.color = '#667eea';
                }
            });
        });

        // ============================================
        // 7. COPY ISBN TO CLIPBOARD (if needed)
        // ============================================
        const isbnElement = document.querySelector('.book-isbn');
        if (isbnElement) {
            const isbnText = isbnElement.innerText.replace('ISBN: ', '');
            if (isbnText && isbnText !== 'Ei tiedossa') {
                // Create copy button
                const copyBtn = document.createElement('button');
                copyBtn.innerHTML = '<i class="fas fa-copy"></i>';
                copyBtn.title = 'Kopioi ISBN';
                copyBtn.style.cssText = `
                    background: rgba(255,255,255,0.1);
                    border: 1px solid var(--border-color);
                    color: var(--text-secondary);
                    padding: 4px 8px;
                    border-radius: 6px;
                    cursor: pointer;
                    margin-left: 10px;
                    font-size: 0.7rem;
                    transition: all 0.3s;
                `;
                copyBtn.onclick = function() {
                    navigator.clipboard.writeText(isbnText).then(function() {
                        copyBtn.innerHTML = '<i class="fas fa-check"></i>';
                        copyBtn.style.background = '#10b981';
                        setTimeout(function() {
                            copyBtn.innerHTML = '<i class="fas fa-copy"></i>';
                            copyBtn.style.background = 'rgba(255,255,255,0.1)';
                        }, 2000);
                    });
                };
                copyBtn.onmouseenter = function() {
                    this.style.background = 'var(--gradient-1)';
                    this.style.color = 'white';
                };
                copyBtn.onmouseleave = function() {
                    this.style.background = 'rgba(255,255,255,0.1)';
                    this.style.color = 'var(--text-secondary)';
                };
                isbnElement.appendChild(copyBtn);
            }
        }

        // ============================================
        // 8. SHOW TOAST MESSAGE FUNCTION
        // ============================================
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `notification notification-${type}`;
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 2000;
                max-width: 350px;
                animation: slideIn 0.3s ease;
            `;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
            document.body.appendChild(toast);

            setTimeout(function() {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s ease';
                setTimeout(function() {
                    if (toast && toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 4000);
        }

        // ============================================
        // 9. ADD KEYBOARD SHORTCUTS
        // ============================================
        document.addEventListener('keydown', function(e) {
            // Press 'B' to focus borrow button
            if (e.key === 'b' || e.key === 'B') {
                const borrowBtn = document.querySelector('.btn-borrow');
                if (borrowBtn && !borrowBtn.disabled) {
                    borrowBtn.focus();
                    borrowBtn.style.transform = 'scale(1.02)';
                    setTimeout(function() {
                        borrowBtn.style.transform = 'scale(1)';
                    }, 200);
                }
            }

            // Press 'Esc' to go back
            if (e.key === 'Escape') {
                const backLink = document.querySelector('.back-link');
                if (backLink) {
                    window.location.href = backLink.href;
                }
            }
        });

        // ============================================
        // 10. CONFIRM BEFORE LEAVING PAGE WITH UNSAVED CHANGES
        // ============================================
        let formModified = false;
        const forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            form.addEventListener('change', function() {
                formModified = true;
            });
        });

        window.addEventListener('beforeunload', function(e) {
            if (formModified) {
                e.preventDefault();
                e.returnValue = 'Sinulla on keskeneräinen lainaus. Haluatko varmasti poistua?';
                return e.returnValue;
            }
        });

        // ============================================
        // 11. ADD SCROLL TO TOP BUTTON
        // ============================================
        const scrollBtn = document.createElement('button');
        scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        scrollBtn.title = 'Takaisin ylös';
        scrollBtn.style.cssText = `
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--gradient-1);
            border: none;
            color: white;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            z-index: 1000;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            font-size: 1.2rem;
        `;
        scrollBtn.onclick = function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };
        document.body.appendChild(scrollBtn);

        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                scrollBtn.style.display = 'flex';
                scrollBtn.style.alignItems = 'center';
                scrollBtn.style.justifyContent = 'center';
            } else {
                scrollBtn.style.display = 'none';
            }
        });

        scrollBtn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
        });
        scrollBtn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });

        // ============================================
        // 12. ADD ANIMATION TO STAT CARDS (if any)
        // ============================================
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(function(card, index) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(function() {
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // ============================================
        // 13. SIDEBAR ACTIVE LINK HIGHLIGHTING (REINFORCE)
        // ============================================
        const currentUrl = window.location.pathname.split('/').pop();
        const menuLinks = document.querySelectorAll('.menu-item');
        menuLinks.forEach(function(link) {
            const href = link.getAttribute('href');
            if (href === currentUrl) {
                link.classList.add('active');
            } else {
                // Remove active from others if needed
                if (link.classList.contains('active') && href !== currentUrl) {
                    link.classList.remove('active');
                }
            }
        });
    });
</script>
</body>
</html>
