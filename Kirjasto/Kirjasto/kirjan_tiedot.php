<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get book ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: user_selaa_kirjoja.php");
    exit();
}

$book_id = $_GET['id'];

// Get user info
$user_query = "SELECT etunimi, sukunimi, profile_image, rooli, jasennumero FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Set current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);

// Get user initials for avatar
$initials = strtoupper(substr($user['etunimi'] ?? '', 0, 1) . substr($user['sukunimi'] ?? '', 0, 1));

// Create membership number
$membership_number = isset($user['jasennumero']) && !empty($user['jasennumero'])
    ? $user['jasennumero']
    : 'JASEN-' . str_pad($user_id, 6, '0', STR_PAD_LEFT);

// Get book details with available copies
$book_query = "SELECT k.*,
                      COALESCE((SELECT COUNT(*) FROM Kirjakopiot kp WHERE kp.kirja_id = k.id AND kp.tila = 'saatavilla'), 0) as saatavilla,
                      COALESCE((SELECT COUNT(*) FROM Kirjakopiot kp WHERE kp.kirja_id = k.id), 0) as total_kopiot
               FROM kirjat k
               WHERE k.id = ?";
$stmt = $conn->prepare($book_query);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book_result = $stmt->get_result();

if ($book_result->num_rows === 0) {
    header("Location: user_selaa_kirjoja.php");
    exit();
}

$book = $book_result->fetch_assoc();

// Get all copies of this book
$copies_query = "SELECT * FROM Kirjakopiot WHERE kirja_id = ? ORDER BY id";
$stmt = $conn->prepare($copies_query);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$copies_result = $stmt->get_result();

// Check if user has already reserved this book
$reserved_query = "SELECT id FROM varaukset WHERE kirja_id = ? AND jasen_id = ? AND tila = 'odottaa'";
$stmt = $conn->prepare($reserved_query);
$stmt->bind_param("ii", $book_id, $user_id);
$stmt->execute();
$already_reserved = $stmt->get_result()->num_rows > 0;

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
    <title><?php echo htmlspecialchars($book['nimi']); ?> | Kirjasto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    /* RESET STYLES */
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

    /* CSS VARIABLES */
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

    /* ========== BOOK DETAILS STYLES ========== */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--text-secondary);
        text-decoration: none;
        margin-bottom: 20px;
        padding: 8px 16px;
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

    .book-detail-card {
        background: var(--bg-card);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 30px;
        border: 1px solid var(--border-color);
    }

    .book-header {
        display: flex;
        gap: 30px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }

    .book-cover {
        width: 200px;
        height: 280px;
        background: var(--gradient-1);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 4rem;
        box-shadow: var(--shadow);
    }

    .book-info {
        flex: 1;
    }

    .book-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 10px;
    }

    .book-author {
        font-size: 1.3rem;
        color: var(--text-secondary);
        margin-bottom: 20px;
    }

    .book-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    .meta-item {
        background: rgba(255, 255, 255, 0.03);
        padding: 15px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }

    .meta-label {
        font-size: 0.8rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }

    .meta-value {
        font-size: 1.1rem;
        color: var(--text-primary);
        font-weight: 600;
    }

    /* ========== BUTTON STYLES ========== */
    .btn-borrow {
        padding: 15px 30px;
        background: linear-gradient(135deg, #27AE60, #219653);
        color: white;
        text-decoration: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        margin-right: 15px;
    }

    .btn-borrow:hover {
        opacity: 0.9;
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(39, 174, 96, 0.5);
    }

    .btn-reserve {
        padding: 15px 30px;
        background: var(--gradient-1);
        color: white;
        text-decoration: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }

    .btn-reserve:hover:not(.disabled) {
        opacity: 0.9;
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.5);
    }

    .btn-reserve.disabled,
    .btn-borrow.disabled {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-muted);
        cursor: not-allowed;
    }

    .button-group {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 20px;
    }

    /* ========== AVAILABILITY SECTION ========== */
    .availability-section {
        background: rgba(255, 255, 255, 0.02);
        border-radius: 16px;
        padding: 20px;
        margin-top: 30px;
    }

    .availability-title {
        font-size: 1.2rem;
        color: var(--text-primary);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .copies-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }

    .copy-item {
        padding: 15px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }

    .copy-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 30px;
        font-size: 0.8rem;
        margin-bottom: 8px;
    }

    .status-available {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
    }

    .status-loaned {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
    }

    .status-maintenance {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
    }

    /* ========== RESPONSIVE ========== */
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 20px;
        }
        .book-header {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .book-title {
            font-size: 2rem;
        }
        .button-group {
            justify-content: center;
        }
    }
</style>
</head>
<body>
 <!-- ========== SIDEBAR START ========== -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-book-open"></i></div>
                <div class="logo-text"><h2>Kirjasto</h2><p>Lukemisen iloa</p></div>
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
                <h1>Kirjan Tiedot</h1>
                <p><i class="fas fa-circle"></i> <?php echo htmlspecialchars($book['nimi']); ?></p>
            </div>
            <div class="top-actions">
                <div class="date-badge"><i class="far fa-calendar-alt"></i> <?php echo date('j. F Y'); ?></div>
                <div class="notification-icon">
                    <i class="far fa-bell"></i>
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="badge"><?php echo $unread_messages_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <a href="user_selaa_kirjoja.php" class="back-link"><i class="fas fa-arrow-left"></i> Takaisin hakuun</a>

        <div class="book-detail-card">
            <div class="book-header">
                <div class="book-cover"><i class="fas fa-book"></i></div>
                <div class="book-info">
                    <h1 class="book-title"><?php echo htmlspecialchars($book['nimi']); ?></h1>
                    <div class="book-author"><?php echo htmlspecialchars($book['tekija']); ?></div>

                    <div class="book-meta-grid">
                        <?php if (!empty($book['isbn'])): ?>
                        <div class="meta-item"><div class="meta-label">ISBN</div><div class="meta-value"><?php echo htmlspecialchars($book['isbn']); ?></div></div>
                        <?php endif; ?>
                        <?php if (!empty($book['julkaisuvuosi'])): ?>
                        <div class="meta-item"><div class="meta-label">Julkaisuvuosi</div><div class="meta-value"><?php echo htmlspecialchars($book['julkaisuvuosi']); ?></div></div>
                        <?php endif; ?>
                        <?php if (!empty($book['kustantaja'])): ?>
                        <div class="meta-item"><div class="meta-label">Kustantaja</div><div class="meta-value"><?php echo htmlspecialchars($book['kustantaja']); ?></div></div>
                        <?php endif; ?>
                        <?php if (!empty($book['kategoria'])): ?>
                        <div class="meta-item"><div class="meta-label">Kategoria</div><div class="meta-value"><?php echo htmlspecialchars($book['kategoria']); ?></div></div>
                        <?php endif; ?>
                    </div>

                    <!-- BUTTON GROUP: BORROW + RESERVE -->
                    <div class="button-group">
                        <?php if ($book['saatavilla'] > 0): ?>
                            <!-- BORROW BUTTON - Green -->
                            <a href="user_lainaa_kirja.php?id=<?php echo $book['id']; ?>" class="btn-borrow">
                                <i class="fas fa-hand-holding-heart"></i> Lainaa kirja
                            </a>
                        <?php else: ?>
                            <button class="btn-borrow disabled" disabled>
                                <i class="fas fa-ban"></i> Ei saatavilla
                            </button>
                        <?php endif; ?>

                        <!-- RESERVE BUTTON - Blue/Purple -->
                        <?php if ($book['saatavilla'] > 0): ?>
                            <?php if ($already_reserved): ?>
                                <button class="btn-reserve disabled" disabled>
                                    <i class="fas fa-check-circle"></i> Olet jo varannut tämän kirjan
                                </button>
                            <?php else: ?>
                                <a href="user_varaa_kirja.php?id=<?php echo $book['id']; ?>" class="btn-reserve">
                                    <i class="fas fa-bookmark"></i> Varaa kirja
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn-reserve disabled" disabled>
                                <i class="fas fa-ban"></i> Ei saatavilla tällä hetkellä
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="availability-section">
                <h3 class="availability-title"><i class="fas fa-copy"></i> Kirjan kappaleet (<?php echo $book['total_kopiot']; ?> kpl)</h3>
                <div class="copies-grid">
                    <?php if ($copies_result && $copies_result->num_rows > 0): ?>
                        <?php while ($copy = $copies_result->fetch_assoc()): ?>
                            <?php
                            $status_class = 'status-';
                            $status_text = '';
                            switch ($copy['tila']) {
                                case 'saatavilla': $status_class .= 'available'; $status_text = 'Saatavilla'; break;
                                case 'lainassa': $status_class .= 'loaned'; $status_text = 'Lainassa'; break;
                                case 'huolto': $status_class .= 'maintenance'; $status_text = 'Huollossa'; break;
                                default: $status_class .= 'loaned'; $status_text = ucfirst($copy['tila']);
                            }
                            ?>
                            <div class="copy-item">
                                <span class="copy-status <?php echo $status_class; ?>"><i class="fas fa-circle"></i> <?php echo $status_text; ?></span>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;">Kopio #<?php echo $copy['id']; ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 30px; color: var(--text-muted);">
                            <i class="fas fa-info-circle"></i> Ei kopioita tästä kirjasta
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- ========== MAIN CONTENT END ========== -->

</body>
</html>
