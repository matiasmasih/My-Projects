<?php
// kirjat.php - Public book browsing with cards
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'connection.php';

// Set timezone to Finland
date_default_timezone_set('Europe/Helsinki');

// Hae käyttäjän tiedot jos kirjautunut
$current_user = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT rooli, etunimi FROM jasenet WHERE id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_user = $result->fetch_assoc();
} else {
    $current_user = null;
}

// GET-parametrit
$search_query = $_GET['search'] ?? '';
$filter_kategoria = $_GET['kategoria'] ?? '';
$filter_status = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Hae kategoriat
$kategoriat = [];
$kategoria_result = $conn->query("SELECT DISTINCT kategoria FROM kirjat WHERE kategoria IS NOT NULL AND kategoria != '' ORDER BY kategoria");
if ($kategoria_result) {
    while ($row = $kategoria_result->fetch_assoc()) {
        $kategoriat[] = $row['kategoria'];
    }
}

// Hae kirjat hakuehdoilla (show ALL books)
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search_query)) {
    $where_conditions[] = "(nimi LIKE ? OR tekija LIKE ? OR isbn LIKE ?)";
    $search_term = "%{$search_query}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

if (!empty($filter_kategoria)) {
    $where_conditions[] = "kategoria = ?";
    $params[] = $filter_kategoria;
    $types .= 's';
}

if (!empty($filter_status)) {
    if ($filter_status === 'available') {
        $where_conditions[] = "saatavilla > 0";
    } elseif ($filter_status === 'borrowed') {
        $where_conditions[] = "saatavilla = 0";
    }
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Laske yhteensä
$count_sql = "SELECT COUNT(*) as total FROM kirjat $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt) {
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_books = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_books / $limit);
} else {
    $total_books = 0;
    $total_pages = 1;
}

// Hae kirjat
$books_sql = "SELECT
    id,
    nimi,
    tekija,
    isbn,
    kategoria,
    julkaisuvuosi,
    kustantaja,
    saatavilla,
    kokonaismaara,
    luotu
    FROM kirjat $where_sql ORDER BY nimi LIMIT ? OFFSET ?";

$books_stmt = $conn->prepare($books_sql);
if ($books_stmt) {
    $books_params = $params;
    $books_types = $types;
    $books_params[] = $limit;
    $books_params[] = $offset;
    $books_types .= 'ii';
    
    if (!empty($books_params)) {
        $books_stmt->bind_param($books_types, ...$books_params);
    }
    $books_stmt->execute();
    $books_result = $books_stmt->get_result();
    $books = $books_result->fetch_all(MYSQLI_ASSOC);
} else {
    $books = [];
}

// Hae suosituimmat kirjat
$popular_books_sql = "SELECT nimi, tekija, saatavilla FROM kirjat ORDER BY saatavilla DESC, nimi ASC LIMIT 5";
$popular_books_result = $conn->query($popular_books_sql);
$popular_books = $popular_books_result ? $popular_books_result->fetch_all(MYSQLI_ASSOC) : [];

// Hae uusimmat kirjat
$newest_books_sql = "SELECT nimi, tekija, luotu FROM kirjat ORDER BY luotu DESC, nimi ASC LIMIT 5";
$newest_books_result = $conn->query($newest_books_sql);
$newest_books = $newest_books_result ? $newest_books_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjat - Kirjasto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)),
                        url('https://images.unsplash.com/photo-1521587760476-6c12a4b040da?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white;
            display: flex;
        }

        /* Sidebar Styles - SAME AS admin_manage_kirjat.php */
        .sidebar {
            width: 260px;
            background: rgba(0, 0, 0, 0.95);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
        }

        .sidebar-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 25px 20px;
            overflow-y: auto;
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logo-icon {
            width: 65px;
            height: 65px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.6rem;
            color: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .logo h2 {
            color: white;
            font-size: 1.3rem;
            font-weight: bold;
            margin: 0;
        }

        .logo p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8rem;
            margin-top: 4px;
        }

        .user-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .user-info h4 {
            color: white;
            margin: 0 0 4px 0;
            font-size: 1rem;
        }

        .user-info .role {
            color: #667eea;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .nav-menu {
            flex: 1;
            margin-bottom: 15px;
        }

        .nav-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-menu li {
            margin-bottom: 6px;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .nav-menu a:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(3px);
        }

        .nav-menu a.active {
            background: rgba(102, 126, 234, 0.3);
            color: white;
            border-left: 4px solid #667eea;
        }

        .nav-menu i {
            width: 22px;
            font-size: 1rem;
            text-align: center;
            margin-right: 10px;
        }

        .logout-btn {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid rgba(231, 76, 60, 0.3);
            padding: 10px 12px;
            border-radius: 6px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }

        .logout-btn:hover {
            background: rgba(231, 76, 60, 0.3);
            color: white;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 25px;
            min-height: 100vh;
        }

        .header {
            margin-bottom: 30px;
            padding: 25px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        .header h1 {
            color: white;
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
        }

        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(102, 126, 234, 0.2);
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #667eea;
            margin-left: 5px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        /* Search Filter */
        .search-filter {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            margin-bottom: 25px;
            align-items: end;
        }

        @media (max-width: 768px) {
            .search-filter {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: rgba(102, 126, 234, 0.8);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-light {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .btn-light:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(46, 204, 113, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(243, 156, 18, 0.4);
        }

        /* Books Grid - CARD VIEW */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }

        .book-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .book-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        .book-cover {
            height: 180px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3.5rem;
        }

        .book-content {
            padding: 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .book-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: white;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .book-author {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            margin-bottom: 12px;
            font-style: italic;
        }

        .book-meta {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .book-category {
            display: inline-block;
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .book-availability {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .availability-info {
            display: flex;
            flex-direction: column;
        }

        .available-count {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2ecc71;
        }

        .total-count {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-available {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .status-borrowed {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        .book-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .book-actions .btn {
            flex: 1;
            padding: 10px;
            font-size: 0.9rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }

        .page-info {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        /* Sidebar Info Boxes */
        .sidebar-info {
            margin-top: 30px;
        }

        .info-box {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-title {
            color: white;
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .book-list {
            list-style: none;
        }

        .book-list li {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .book-list li:last-child {
            border-bottom: none;
        }

        .book-list .name {
            color: white;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .book-list .author {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
        }

        .availability-badge {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .date-badge {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-container {
                padding: 15px 10px;
            }
            .sidebar .logo h2,
            .sidebar .logo p,
            .sidebar .user-info h4,
            .sidebar .user-info .role,
            .sidebar .nav-menu a span,
            .logout-btn span {
                display: none;
            }
            .logo-icon {
                width: 45px;
                height: 45px;
                font-size: 1.3rem;
                margin-bottom: 8px;
            }
            .main-content {
                margin-left: 70px;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .sidebar-container {
                flex-direction: row;
                align-items: center;
                padding: 12px 15px;
            }
            .logo {
                margin: 0;
                margin-right: 15px;
                border-bottom: none;
                padding-bottom: 0;
            }
            .logo-icon {
                width: 35px;
                height: 35px;
                font-size: 1rem;
                margin: 0;
            }
            .user-info {
                display: none;
            }
            .nav-menu ul {
                display: flex;
                gap: 8px;
                overflow-x: auto;
            }
            .nav-menu a {
                padding: 8px 10px;
                white-space: nowrap;
            }
            .logout-btn {
                margin-top: 0;
                margin-left: auto;
                padding: 8px 10px;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .books-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Results Count */
        .results-count {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            margin-bottom: 20px;
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <!-- Sidebar - SAME AS admin_manage_kirjat.php -->

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-container">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-book"></i>
                </div>
                <h2>Kirjasto</h2>
                <p>Hallintapaneeli</p>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($current_user['etunimi']); ?></h4>
                <div class="role"><?php echo ucfirst($current_user['rooli']); ?></div>
            </div>

            <nav class="nav-menu">
                <ul>
                    <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> <span>Kojelauta</span></a></li>
                    <li><a href="admin_kayttajien_hallinta.php"><i class="fas fa-users"></i> <span>Käyttäjät</span></a></li>
                    <li><a href="admin_kayttooikeudet.php"><i class="fas fa-user-shield"></i> <span>Käyttöoikeudet</span></a></li>
                    <li><a href="admin_manage_kirjat.php" class="active"><i class="fas fa-book"></i> <span>Kirjojen hallinta</span></a></li>
                    <li><a href="admin_lisaa_kirja.php"><i class="fas fa-plus-circle"></i> <span>Lisää kirja</span></a></li>
                    <li><a href="admin_lainat.php"><i class="fas fa-exchange-alt"></i> <span>Lainat</span></a></li>
                    <li><a href="admin_varaukset.php"><i class="fas fa-calendar-check"></i> <span>Varaukset</span></a></li>
                    <li><a href="admin_sakot.php"><i class="fas fa-exclamation-triangle"></i> <span>Sakot</span></a></li>
                    <li><a href="admin_varmuuskopiointi.php"><i class="fas fa-database"></i> <span>Varmuuskopiointi</span></a></li>
                    <li><a href="admin_palvelin_lokit.php"><i class="fas fa-file-alt"></i> <span>Palvelinlokit</span></a></li>
                    <li><a href="salasana.php"><i class="fas fa-key"></i> <span>Vaihda salasana</span></a></li>
                </ul>
            </nav>

            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> <span>Kirjaudu ulos</span>
            </a>
        </div>
    </div>








<!-- Sidebar for kirjat.php - Public book browsing -->
<div class="sidebar">
    <div class="sidebar-container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-book"></i>
            </div>
            <h2>Kirjasto</h2>
            <p>Kirjaston kirjat</p>
        </div>

        <?php if ($current_user): ?>
        <div class="user-info">
            <h4><?php echo htmlspecialchars($current_user['etunimi']); ?></h4>
            <div class="role"><?php echo ucfirst($current_user['rooli']); ?></div>
        </div>
        <?php endif; ?>

        <nav class="nav-menu">
            <ul>
                <!-- Kirjat link is ALWAYS visible and active -->
                <li><a href="kirjat.php" class="active"><i class="fas fa-book"></i> <span>Kirjat</span></a></li>
                
                <?php if ($current_user): ?>
                    <!-- LOGGED IN USERS - Show user-specific links -->
                    
                    <?php if ($current_user['rooli'] == 'admin'): ?>
                        <!-- ADMIN sees admin dashboard -->
                        <li><a href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Hallintapaneeli</span></a></li>
                    <?php elseif ($current_user['rooli'] == 'manager'): ?>
                        <!-- MANAGER sees manager dashboard -->
                        <li><a href="manager_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Hallintapaneeli</span></a></li>
                    <?php else: ?>
                        <!-- REGULAR USER sees their dashboard -->
                        <li><a href="admin_dashboard.php"><i class="fas fa-user"></i> <span>Oma sivu</span></a></li>
                    <?php endif; ?>
                    
                    <!-- COMMON links for ALL logged in users -->
                    <li><a href="admin_lainat.php"><i class="fas fa-exchange-alt"></i> <span>Lainani</span></a></li>
                    <li><a href="admin_varaukset.php"><i class="fas fa-bookmark"></i> <span>Varaukseni</span></a></li>
                    <li><a href="salasana.php"><i class="fas fa-key"></i> <span>Vaihda salasana</span></a></li>
                    
                <?php else: ?>
                    <!-- NOT LOGGED IN (public users) -->
                    <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> <span>Kirjaudu</span></a></li>
                    <li><a href="register.php"><i class="fas fa-user-plus"></i> <span>Rekisteröidy</span></a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <?php if ($current_user): ?>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> <span>Kirjaudu ulos</span>
        </a>
        <?php endif; ?>
    </div>
</div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-book"></i> Kirjaston kirjat</h1>
            <p>Selaa kirjaston kokoelmaa. <?php echo $total_books; ?> kirjaa saatavilla.</p>
        </div>

        <!-- HAKU JA SUODATUS -->
        <div class="glass-card">
            <h2 style="margin-bottom: 20px; color: white; font-size: 1.4rem;">
                <i class="fas fa-search"></i> Etsi kirjoja
            </h2>

            <form method="GET" action="">
                <div class="search-filter">
                    <div class="form-group">
                        <label for="search"><i class="fas fa-search"></i> Hae kirjoja</label>
                        <input type="text" id="search" name="search" class="form-control"
                               placeholder="Hae nimen, kirjailijan tai ISBN:n perusteella..."
                               value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>

                    <div class="form-group">
                        <label for="kategoria"><i class="fas fa-tag"></i> Kategoria</label>
                        <select id="kategoria" name="kategoria" class="form-control">
                            <option value="">Kaikki kategoriat</option>
                            <?php foreach ($kategoriat as $kategoria): ?>
                            <option value="<?php echo htmlspecialchars($kategoria); ?>"
                                <?php echo ($filter_kategoria == $kategoria) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kategoria); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status"><i class="fas fa-info-circle"></i> Lainatilanne</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">Kaikki</option>
                            <option value="available" <?php echo ($filter_status == 'available') ? 'selected' : ''; ?>>Vain saatavilla</option>
                            <option value="borrowed" <?php echo ($filter_status == 'borrowed') ? 'selected' : ''; ?>>Vain lainassa</option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary" style="height: 44px;">
                            <i class="fas fa-filter"></i> Suodata
                        </button>
                        <a href="kirjat.php" class="btn btn-light" style="height: 44px; margin-top: 5px;">
                            <i class="fas fa-times"></i> Tyhjennä
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Count -->
        <div class="results-count">
            <i class="fas fa-info-circle"></i>
            Löytyi <?php echo $total_books; ?> kirjaa 
            <?php if (!empty($search_query)): ?>
                hakusanalla "<?php echo htmlspecialchars($search_query); ?>"
            <?php endif; ?>
            <?php if (!empty($filter_kategoria)): ?>
                kategoriassa "<?php echo htmlspecialchars($filter_kategoria); ?>"
            <?php endif; ?>
        </div>

        <!-- KIRJALISTA - CARD VIEW -->
        <div class="glass-card">
            <?php if (empty($books)): ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-book" style="font-size: 4rem; color: rgba(255, 255, 255, 0.3); margin-bottom: 20px;"></i>
                    <h3 style="color: white; margin-bottom: 15px; font-size: 1.5rem;">Kirjoja ei löytynyt</h3>
                    <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.1rem; max-width: 500px; margin: 0 auto;">
                        Valituilla hakuehdoilla ei löytynyt kirjoja. Kokeile toista hakusanaa tai poista suodattimia.
                    </p>
                </div>
            <?php else: ?>
                <div class="books-grid">
                    <?php foreach ($books as $book):
                        // Määritä tilan mukainen status
                        if ($book['saatavilla'] > 0) {
                            $status_text = 'Vapaa';
                            $status_class = 'status-available';
                        } else {
                            $status_text = 'Lainassa';
                            $status_class = 'status-borrowed';
                        }
                    ?>
                    <div class="book-card">
                        <div class="book-cover">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="book-content">
                            <h3 class="book-title"><?php echo htmlspecialchars($book['nimi']); ?></h3>
                            <p class="book-author"><?php echo htmlspecialchars($book['tekija']); ?></p>
                            
                            <div class="book-meta">
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo htmlspecialchars($book['julkaisuvuosi']); ?></span>
                                </div>
                                
                                <?php if ($book['kustantaja']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-building"></i>
                                    <span><?php echo htmlspecialchars($book['kustantaja']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="meta-item">
                                    <i class="fas fa-barcode"></i>
                                    <span><?php echo htmlspecialchars($book['isbn']); ?></span>
                                </div>
                            </div>
                            
                            <?php if ($book['kategoria']): ?>
                            <div class="book-category"><?php echo htmlspecialchars($book['kategoria']); ?></div>
                            <?php endif; ?>
                            
                            <div class="book-availability">
                                <div class="availability-info">
                                    <span class="available-count"><?php echo $book['saatavilla']; ?> saatavilla</span>
                                    <span class="total-count">/ <?php echo $book['kokonaismaara']; ?> kopiota</span>
                                </div>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                            
                            <div class="book-actions">
                                <!-- NÄYTÄ button -->
                                <a href="nayta_kirja.php?id=<?php echo $book['id']; ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> Näytä
                                </a>
                                
                                <?php if ($current_user && $book['saatavilla'] > 0): ?>
                                <!-- VARAUS button (only for logged in users) -->
                                <form method="POST" action="admin_varaukset.php" style="display: inline; flex: 1;">
                                    <input type="hidden" name="action" value="reserve">
                                    <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                    <button type="submit" class="btn btn-success"
                                            onclick="return confirm('Haluatko varata kirjan \'<?php echo addslashes(htmlspecialchars($book['nimi'])); ?>\'?')">
                                        <i class="fas fa-bookmark"></i> Varaa
                                    </button>
                                </form>
                                <?php elseif (!$current_user && $book['saatavilla'] > 0): ?>
                                <!-- LOGIN to reserve -->
                                <a href="login.php?redirect=kirjat.php" class="btn btn-warning">
                                    <i class="fas fa-sign-in-alt"></i> Kirjaudu
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- SIVUTUS -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&kategoria=<?php echo urlencode($filter_kategoria); ?>&status=<?php echo urlencode($filter_status); ?>"
                       class="btn btn-light">
                        <i class="fas fa-chevron-left"></i> Edellinen
                    </a>
                    <?php endif; ?>

                    <span class="page-info">
                        Sivu <?php echo $page; ?> / <?php echo $total_pages; ?>
                    </span>

                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&kategoria=<?php echo urlencode($filter_kategoria); ?>&status=<?php echo urlencode($filter_status); ?>"
                       class="btn btn-light">
                        Seuraava <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-focus search input
        document.getElementById('search')?.focus();
    </script>
</body>
</html>
