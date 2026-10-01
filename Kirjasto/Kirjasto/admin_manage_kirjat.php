<?php
// ============================================
// admin_manage_kirjat.php - Kirjojen hallinta
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'connection.php';

date_default_timezone_set('Europe/Helsinki');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ============================================
// Helper Functions
// ============================================

function getProfileImageUrl($profile_image, $user_name) {
    if (empty($profile_image)) {
        return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
    }
    if (filter_var($profile_image, FILTER_VALIDATE_URL)) {
        return $profile_image;
    }
    if (file_exists($profile_image)) {
        return $profile_image;
    }
    if (file_exists('uploads/profiles/' . $profile_image)) {
        return 'uploads/profiles/' . $profile_image;
    }
    $filename = basename($profile_image);
    if (file_exists('uploads/profiles/' . $filename)) {
        return 'uploads/profiles/' . $filename;
    }
    if (file_exists($filename)) {
        return $filename;
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
}

function getInitials($name) {
    if (empty($name)) return 'AD';
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2);
}

// ============================================
// Get User Data
// ============================================

$user_id = $_SESSION['user_id'];
$user_query = "SELECT id, rooli, profile_image, etunimi, sukunimi, email FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

if (isset($_SESSION['profile_image'])) {
    $current_user['profile_image'] = $_SESSION['profile_image'];
}

$admin_user = [
    'id' => $current_user['id'] ?? $user_id,
    'rooli' => $current_user['rooli'] ?? 'admin',
    'profile_image' => $current_user['profile_image'] ?? null,
    'etunimi' => $current_user['etunimi'] ?? 'Admin',
    'sukunimi' => $current_user['sukunimi'] ?? 'User',
    'email' => $current_user['email'] ?? 'admin@example.com'
];

// Check access - only admin and manager
if (!$current_user || ($current_user['rooli'] != 'admin' && $current_user['rooli'] != 'manager')) {
    $rooli = htmlspecialchars($current_user['rooli'] ?? 'Ei määritelty');
    die("<div style='text-align: center; padding: 50px;'><h1>⛔ Käyttö estetty</h1><p>Vain ylläpitäjät ja managerit voivat käyttää tätä sivua.</p><a href='admin_dashboard.php'>Takaisin</a></div>");
}

// Display variables
$admin_name = $admin_user['etunimi'] . ' ' . $admin_user['sukunimi'];
$admin_role = $current_user['rooli'] === 'admin' ? 'Ylläpitäjä' : 'Manager';
$admin_permissions = $current_user['rooli'] === 'admin' ? 'Täydet järjestelmäoikeudet' : 'Laite- ja kirjahallinta';
$kayttajan_nimi = $admin_name;
$profile_image_url = getProfileImageUrl($current_user['profile_image'] ?? '', $kayttajan_nimi);

$errors = [];
$success = [];

// ============================================
// Search and Pagination
// ============================================

$search_query = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];
$types = '';

if (!empty($search_query)) {
    $where_conditions[] = "(nimi LIKE ? OR tekija LIKE ? OR isbn LIKE ? OR kategoria LIKE ? OR kustantaja LIKE ?)";
    $search_term = "%{$search_query}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    $types .= 'sssss';
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Count total books
$count_sql = "SELECT COUNT(*) as total FROM kirjat $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt) {
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_kirjat = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_kirjat / $limit);
} else {
    $total_kirjat = 0;
    $total_pages = 1;
}

// Fetch books
$kirjat_sql = "SELECT id, nimi, tekija, isbn, kategoria, julkaisuvuosi, kustantaja, luotu
               FROM kirjat $where_sql
               ORDER BY nimi
               LIMIT ? OFFSET ?";

$kirjat_stmt = $conn->prepare($kirjat_sql);
if ($kirjat_stmt) {
    $kirjat_params = $params;
    $kirjat_types = $types;
    $kirjat_params[] = $limit;
    $kirjat_params[] = $offset;
    $kirjat_types .= 'ii';

    if (!empty($kirjat_params)) {
        $kirjat_stmt->bind_param($kirjat_types, ...$kirjat_params);
    }
    $kirjat_stmt->execute();
    $kirjat_result = $kirjat_stmt->get_result();
    $kirjat = $kirjat_result->fetch_all(MYSQLI_ASSOC);
} else {
    $kirjat = [];
}

// ============================================
// Book Statistics
// ============================================

$book_stats = [];
try {
    $stats_result = $conn->query("SELECT COUNT(*) as total_books FROM kirjat");
    $book_stats['total_books'] = $stats_result ? $stats_result->fetch_assoc()['total_books'] : 0;

    $category_result = $conn->query("SELECT COUNT(DISTINCT kategoria) as total_categories FROM kirjat WHERE kategoria IS NOT NULL AND kategoria != ''");
    $book_stats['total_categories'] = $category_result ? $category_result->fetch_assoc()['total_categories'] : 0;

    $recent_result = $conn->query("SELECT COUNT(*) as recent_books FROM kirjat WHERE luotu >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $book_stats['recent_books'] = $recent_result ? $recent_result->fetch_assoc()['recent_books'] : 0;

    $recent_year_result = $conn->query("SELECT julkaisuvuosi FROM kirjat WHERE julkaisuvuosi IS NOT NULL AND julkaisuvuosi >= 1900 ORDER BY julkaisuvuosi DESC LIMIT 1");
    $book_stats['latest_year'] = ($recent_year_result && $recent_year_result->num_rows > 0) ? $recent_year_result->fetch_assoc()['julkaisuvuosi'] : date('Y');
} catch (Exception $e) {
    $book_stats = ['total_books' => 0, 'total_categories' => 0, 'recent_books' => 0, 'latest_year' => date('Y')];
}

// ============================================
// Handle POST Requests (Add, Update, Delete)
// ============================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new book
    if (isset($_POST['add_book'])) {
        $nimi = trim($_POST['nimi'] ?? '');
        $tekija = trim($_POST['tekija'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $julkaisuvuosi = (int)($_POST['julkaisuvuosi'] ?? 0);
        $kategoria = trim($_POST['kategoria'] ?? '');
        $kustantaja = trim($_POST['kustantaja'] ?? '');

        if (empty($nimi)) $errors[] = "Kirjan nimi on pakollinen";
        if (empty($tekija)) $errors[] = "Tekijän nimi on pakollinen";

        if (empty($errors)) {
            $add_sql = "INSERT INTO kirjat (nimi, tekija, isbn, julkaisuvuosi, kategoria, kustantaja) VALUES (?, ?, ?, ?, ?, ?)";
            $add_stmt = $conn->prepare($add_sql);
            if ($add_stmt) {
                $add_stmt->bind_param("sssiss", $nimi, $tekija, $isbn, $julkaisuvuosi, $kategoria, $kustantaja);
                if ($add_stmt->execute()) {
                    $success[] = "Kirja lisätty onnistuneesti!";
                    header("Location: admin_manage_kirjat.php");
                    exit();
                } else {
                    $errors[] = "Kirjan lisääminen epäonnistui";
                }
            }
        }
    }

    // Update book
    if (isset($_POST['update_book'])) {
        $book_id = (int)($_POST['book_id'] ?? 0);
        $nimi = trim($_POST['nimi'] ?? '');
        $tekija = trim($_POST['tekija'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $julkaisuvuosi = (int)($_POST['julkaisuvuosi'] ?? 0);
        $kategoria = trim($_POST['kategoria'] ?? '');
        $kustantaja = trim($_POST['kustantaja'] ?? '');

        if ($book_id <= 0) $errors[] = "Kirja ID on pakollinen";
        if (empty($nimi)) $errors[] = "Kirjan nimi on pakollinen";

        if (empty($errors)) {
            $update_sql = "UPDATE kirjat SET nimi = ?, tekija = ?, isbn = ?, julkaisuvuosi = ?, kategoria = ?, kustantaja = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param("sssissi", $nimi, $tekija, $isbn, $julkaisuvuosi, $kategoria, $kustantaja, $book_id);
                if ($update_stmt->execute()) {
                    $success[] = "Kirja päivitetty onnistuneesti!";
                    header("Location: admin_manage_kirjat.php");
                    exit();
                } else {
                    $errors[] = "Kirjan päivitys epäonnistui";
                }
            }
        }
    }

    // Delete book
    if (isset($_POST['delete_book'])) {
        $book_id = (int)($_POST['book_id'] ?? 0);
        if ($book_id > 0) {
            $delete_sql = "DELETE FROM kirjat WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            if ($delete_stmt) {
                $delete_stmt->bind_param("i", $book_id);
                if ($delete_stmt->execute()) {
                    $success[] = "Kirja poistettu onnistuneesti!";
                    header("Location: admin_manage_kirjat.php");
                    exit();
                } else {
                    $errors[] = "Kirjan poisto epäonnistui";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjojen Hallinta | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    /* ============================================
       MODERN ADMIN DASHBOARD CSS - FIXED SIDEBAR
       ============================================ */

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html, body {
        height: 100%;
        overflow: hidden; /* Prevent body scroll */
    }

    body {
        font-family: 'Inter', sans-serif;
        display: flex;
        position: relative;
    }

    /* Background */
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
        filter: brightness(0.3) blur(2px);
        z-index: -2;
    }

    body::after {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        z-index: -1;
    }

    /* ============================================
       SIDEBAR - FIXED (DOES NOT SCROLL WITH MAIN)
       ============================================ */
    .sidebar {
        width: 280px;
        background: rgba(15, 25, 35, 0.95);
        backdrop-filter: blur(10px);
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        overflow-y: auto; /* Sidebar has its OWN scroll if content overflows */
        z-index: 1000;
        border-right: 1px solid rgba(255,255,255,0.08);
        flex-shrink: 0;
    }

    /* Sidebar scrollbar styling */
    .sidebar::-webkit-scrollbar {
        width: 4px;
    }
    .sidebar::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
    }
    .sidebar::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 4px;
    }

    .sidebar-header {
        padding: 30px 25px;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.08);
    }

    .logo {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }

    .logo-icon {
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    .logo-text h2 {
        font-size: 1.3rem;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .logo-text p {
        font-size: 0.7rem;
        color: #94a3b8;
    }

    .user-profile-mini {
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        text-decoration: none;
        transition: all 0.3s;
    }

    .user-profile-mini:hover {
        background: rgba(102, 126, 234, 0.1);
    }

    .avatar-mini {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .avatar-mini img {
        width: 100%;
        height: 100%;
        border-radius: 12px;
        object-fit: cover;
    }

    .user-info-mini h4 {
        font-size: 0.9rem;
        font-weight: 600;
        color: white;
        margin-bottom: 3px;
    }

    .user-info-mini p {
        font-size: 0.7rem;
        color: #94a3b8;
    }

    .sidebar-menu {
        padding: 20px 16px;
    }

    .menu-section {
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #667eea;
        padding: 15px 16px 8px;
        letter-spacing: 1px;
    }

    .menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        color: #b0b8c5;
        text-decoration: none;
        border-radius: 12px;
        transition: all 0.3s;
        margin: 4px 0;
        font-weight: 500;
        font-size: 0.85rem;
    }

    .menu-item i {
        width: 22px;
        font-size: 1rem;
    }

    .menu-item:hover {
        background: rgba(102, 126, 234, 0.15);
        color: white;
        transform: translateX(5px);
    }

    .menu-item.active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }

    .logout-item {
        margin-top: 30px;
        border-top: 1px solid rgba(255,255,255,0.08);
        padding-top: 20px;
    }

    .logout-item:hover {
        background: rgba(239, 68, 68, 0.15);
    }

    /* ============================================
       MAIN CONTENT - SCROLLS INDEPENDENTLY
       ============================================ */
    .main-content {
        flex: 1;
        margin-left: 280px; /* Same as sidebar width */
        padding: 30px 40px;
        height: 100vh;
        overflow-y: auto; /* Main content has its OWN scroll */
        position: relative;
    }

    /* Main content scrollbar styling */
    .main-content::-webkit-scrollbar {
        width: 6px;
    }
    .main-content::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
        border-radius: 3px;
    }
    .main-content::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 3px;
    }

    /* HEADER */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 35px;
        flex-wrap: wrap;
        gap: 20px;
    }

    .page-title h1 {
        font-size: 2rem;
        font-weight: 800;
        background: linear-gradient(135deg, #fff, #a78bfa);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .page-title p {
        color: #94a3b8;
        font-size: 0.9rem;
        margin-top: 5px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 20px;
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        padding: 12px 25px;
        border-radius: 20px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .user-avatar {
        width: 55px;
        height: 55px;
        border-radius: 15px;
        overflow: hidden;
        border: 2px solid #667eea;
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .user-details h3 {
        font-size: 1rem;
        font-weight: 600;
        color: white;
        margin-bottom: 5px;
    }

    .user-details p {
        font-size: 0.75rem;
        color: #94a3b8;
    }

    /* STATS GRID */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 20px;
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        background: rgba(255,255,255,0.12);
        border-color: #667eea;
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .stat-info h3 {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #94a3b8;
        letter-spacing: 1px;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        color: white;
        margin-top: 5px;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 15px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
    }

    /* FILTER SECTION */
    .filter-section {
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 25px;
        margin-bottom: 30px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .section-title {
        color: white;
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .search-filter {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        align-items: end;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-label {
        display: block;
        color: #94a3b8;
        font-size: 0.75rem;
        margin-bottom: 6px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-control {
        width: 100%;
        padding: 12px 16px;
        background: rgba(0,0,0,0.3);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        color: white;
        font-size: 0.9rem;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
    }

    .form-control::placeholder {
        color: #64748b;
    }

    select.form-control option {
        background: #1a1a2e;
    }

    /* BUTTONS */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        font-size: 0.85rem;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .btn-success:hover {
        transform: translateY(-2px);
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .btn-danger:hover {
        transform: translateY(-2px);
    }

    .btn-warning {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .btn-warning:hover {
        transform: translateY(-2px);
    }

    .btn-light {
        background: rgba(255,255,255,0.1);
        color: #94a3b8;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .btn-light:hover {
        background: rgba(255,255,255,0.2);
        color: white;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.75rem;
    }

    /* BOOKS SECTION */
    .books-section {
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 25px;
        margin-bottom: 35px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .bulk-actions {
        margin-bottom: 20px;
    }

    /* TABLE WRAPPER - ONLY HORIZONTAL SCROLL WHEN NEEDED */
    .table-wrapper {
        overflow-x: auto;
        margin-top: 15px;
    }

    .table-wrapper::-webkit-scrollbar {
        height: 6px;
    }
    .table-wrapper::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
        border-radius: 3px;
    }
    .table-wrapper::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 3px;
    }

    .books-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }

    .books-table th {
        text-align: left;
        padding: 15px;
        color: #94a3b8;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .books-table td {
        padding: 15px;
        color: #cbd5e1;
        font-size: 0.85rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        vertical-align: middle;
    }

    .books-table tr:hover td {
        background: rgba(255,255,255,0.05);
    }

    .book-title {
        color: white;
        font-weight: 600;
    }

    .category-badge {
        display: inline-block;
        padding: 4px 10px;
        background: rgba(102, 126, 234, 0.15);
        color: #a78bfa;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
    }

    .table-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    /* PAGINATION */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin-top: 25px;
        padding-top: 15px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .page-info {
        color: #94a3b8;
        font-size: 0.85rem;
    }

    /* NOTIFICATIONS */
    .notification {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.4s ease;
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        border-left: 4px solid;
    }

    .notification-success {
        border-left-color: #10b981;
        color: #10b981;
    }

    .notification-error {
        border-left-color: #ef4444;
        color: #ef4444;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* EMPTY STATE */
    .empty-state {
        text-align: center;
        padding: 60px;
        color: #64748b;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        color: #667eea;
    }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .sidebar {
            transform: translateX(-100%);
            position: fixed;
            z-index: 2000;
        }
        .main-content {
            margin-left: 0;
            padding: 20px;
        }
    }

    @media (max-width: 768px) {
        .stats-grid,
        .search-filter {
            grid-template-columns: 1fr;
        }
        .header {
            flex-direction: column;
            text-align: center;
        }
        .table-actions {
            flex-direction: column;
        }
        .table-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon"><i class="fas fa-crown"></i></div>
            <div class="logo-text">
                <h2>Admin Panel</h2>
                <p>Kirjasto Hallinta</p>
            </div>
        </div>
    </div>

    <a href="admin_dashboard.php" class="user-profile-mini">
        <div class="avatar-mini">
            <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
        </div>
        <div class="user-info-mini">
            <h4><?php echo htmlspecialchars($admin_name); ?></h4>
            <p><?php echo htmlspecialchars($admin_role); ?></p>
        </div>
    </a>

    <div class="sidebar-menu">
        <div class="menu-section">⚙️ Päävalikko</div>
        <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i><span>Kojelauta</span></a>

        <div class="menu-section">📚 Kirjaston Hallinta</div>
        <a href="admin_manage_kirjat.php" class="menu-item active"><i class="fas fa-book"></i><span>Hallinnoi Kirjoja</span></a>
        <a href="admin_lisaa_kirja.php" class="menu-item"><i class="fas fa-plus"></i><span>Lisää Kirja</span></a>
        <a href="admin_muokkaa_kirjaa.php" class="menu-item"><i class="fas fa-edit"></i><span>Muokkaa Kirjoja</span></a>

        <div class="menu-section">👥 Jäsenten Hallinta</div>
        <a href="admin_kayttajien_hallinta.php" class="menu-item"><i class="fas fa-users"></i><span>Hallinnoi Jäseniä</span></a>
        <a href="register.php" class="menu-item"><i class="fas fa-user-plus"></i><span>Rekisteröi Jäsen</span></a>

        <div class="menu-section">🔄 Lainaushallinta</div>
        <a href="admin_lainat.php" class="menu-item"><i class="fas fa-list"></i><span>Hallinnoi Lainoja</span></a>
        <a href="admin_varaukset.php" class="menu-item"><i class="fas fa-check-circle"></i><span>Käsittele Lainoja</span></a>
        <a href="admin_palautukset.php" class="menu-item"><i class="fas fa-undo-alt"></i><span>Hallinnoi Palautuksia</span></a>
        <a href="admin_myohassa_kirjat.php" class="menu-item"><i class="fas fa-clock"></i><span>Myöhässä Olevat</span></a>

        <div class="menu-section">🖥️ Laitehallinta</div>
        <a href="admin_laitetyypit.php" class="menu-item"><i class="fas fa-laptop"></i><span>Laitetyypit</span></a>
        <a href="admin_laitteet.php" class="menu-item"><i class="fas fa-microchip"></i><span>Laitteet</span></a>
        <a href="admin_laitevaraukset.php" class="menu-item"><i class="fas fa-calendar-alt"></i><span>Laitevaraukset</span></a>

        <div class="menu-section">📊 Raportit & Sakot</div>
        <a href="admin_raportit.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Kirjasto Raportit</span></a>
        <a href="admin_sakot.php" class="menu-item"><i class="fas fa-euro-sign"></i><span>Hallinnoi Sakkoja</span></a>

        <div class="menu-section">📨 Viestit</div>
        <a href="admin_viestit.php" class="menu-item"><i class="fas fa-comments"></i><span>Hallinnoi Viestit</span></a>

        <div class="menu-section">🔧 Järjestelmä</div>
        <a href="admin_varmuuskopiointi.php" class="menu-item"><i class="fas fa-database"></i><span>Varmuuskopiot</span></a>
        <a href="admin_kayttooikeudet.php" class="menu-item"><i class="fas fa-cogs"></i><span>Järjestelmäasetukset</span></a>
        <a href="admin_palvelin_lokit.php" class="menu-item"><i class="fas fa-history"></i><span>Palvelinlokit</span></a>

        <a href="logout.php" class="menu-item logout-item"><i class="fas fa-sign-out-alt"></i><span>Kirjaudu Ulos</span></a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">

    <!-- Header -->
    <div class="header">
        <div class="page-title">
            <h1><i class="fas fa-book"></i> Kirjojen Hallinta</h1>
            <p><i class="fas fa-search"></i> Hallinnoi kirjaston kokoelmaa</p>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($admin_name); ?></h3>
                <p style="color: #94a3b8;"><i class="fas fa-envelope" style="color: #667eea;"></i> <?php echo htmlspecialchars($admin_user['email']); ?></p>
                <p style="color: #10b981;"><i class="fas fa-shield-alt" style="color: #10b981;"></i> <?php echo htmlspecialchars($admin_role); ?></p>
                <p style="color: #f59e0b;"><i class="fas fa-key" style="color: #f59e0b;"></i> <?php echo htmlspecialchars($admin_permissions); ?></p>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <?php if (!empty($errors)): ?>
    <div class="notification notification-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php foreach ($errors as $error): ?>
            <span><?php echo htmlspecialchars($error); ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
    <div class="notification notification-success">
        <i class="fas fa-check-circle"></i>
        <?php foreach ($success as $msg): ?>
            <span><?php echo htmlspecialchars($msg); ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Kirjoja Yhteensä</h3>
                    <div class="stat-number"><?php echo number_format($book_stats['total_books'], 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-book"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Kategorioita</h3>
                    <div class="stat-number"><?php echo number_format($book_stats['total_categories'], 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-tags"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Viimeisimmät (30pv)</h3>
                    <div class="stat-number"><?php echo number_format($book_stats['recent_books'], 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Viimeisin Vuosi</h3>
                    <div class="stat-number"><?php echo number_format($book_stats['latest_year'], 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            </div>
        </div>
    </div>

    <!-- Search Filter -->
    <div class="filter-section">
        <div class="section-title">
            <i class="fas fa-search"></i> Hae ja suodata kirjoja
        </div>
        <form method="GET" action="">
            <div class="search-filter">
                <div class="form-group">
                    <label class="form-label">Hae kirjoja</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Hae nimellä, tekijällä, ISBN:llä..."
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Hae
                        </button>
                        <a href="admin_manage_kirjat.php" class="btn btn-light">
                            <i class="fas fa-times"></i> Tyhjennä
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Books List -->
    <div class="books-section">
        <div class="section-title">
            <i class="fas fa-list"></i> Kirjalista (<?php echo $total_kirjat; ?> kirjaa)
        </div>

        <div class="bulk-actions">
            <a href="#add-book" class="btn btn-primary">
                <i class="fas fa-plus"></i> Lisää uusi kirja
            </a>
        </div>

        <?php if (empty($kirjat)): ?>
            <div class="empty-state">
                <i class="fas fa-book"></i>
                <p>Ei kirjoja löytynyt</p>
            </div>
        <?php else: ?>
            <!-- Table wrapper - ONLY horizontal scroll here if needed -->
            <div class="table-wrapper">
                <table class="books-table">
                    <thead>
                        <tr>
                            <th>Nimi</th>
                            <th>Tekijä</th>
                            <th>ISBN</th>
                            <th>Kategoria</th>
                            <th>Vuosi</th>
                            <th>Kustantaja</th>
                            <th>Toiminnot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kirjat as $book): ?>
                        <tr>
                            <td class="book-title"><?php echo htmlspecialchars($book['nimi']); ?></td>
                            <td><?php echo htmlspecialchars($book['tekija']); ?></td>
                            <td><?php echo htmlspecialchars($book['isbn'] ?? '-'); ?></td>
                            <td>
                                <?php if (!empty($book['kategoria'])): ?>
                                    <span class="category-badge"><?php echo htmlspecialchars($book['kategoria']); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($book['julkaisuvuosi'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($book['kustantaja'] ?? '-'); ?></td>
                            <td class="table-actions">
                                <button class="btn btn-primary btn-sm" onclick="editBook(<?php echo $book['id']; ?>)">
                                    <i class="fas fa-edit"></i> Muokkaa
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteBook(<?php echo $book['id']; ?>, '<?php echo addslashes($book['nimi']); ?>')">
                                    <i class="fas fa-trash"></i> Poista
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>" class="btn btn-light btn-sm">
                    <i class="fas fa-chevron-left"></i> Edellinen
                </a>
                <?php endif; ?>
                <span class="page-info">Sivu <?php echo $page; ?> / <?php echo $total_pages; ?></span>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>" class="btn btn-light btn-sm">
                    Seuraava <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Add Book Form -->
    <div id="add-book" class="books-section">
        <div class="section-title">
            <i class="fas fa-plus"></i> Lisää uusi kirja
        </div>
        <form method="POST" action="">
            <div class="search-filter">
                <div class="form-group">
                    <label class="form-label">Kirjan nimi *</label>
                    <input type="text" name="nimi" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tekijä *</label>
                    <input type="text" name="tekija" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ISBN</label>
                    <input type="text" name="isbn" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Julkaisuvuosi</label>
                    <input type="number" name="julkaisuvuosi" class="form-control" min="1000" max="2100">
                </div>
                <div class="form-group">
                    <label class="form-label">Kategoria</label>
                    <input type="text" name="kategoria" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Kustantaja</label>
                    <input type="text" name="kustantaja" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" name="add_book" class="btn btn-primary">
                        <i class="fas fa-save"></i> Tallenna kirja
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function editBook(bookId) {
        window.location.href = 'admin_muokkaa_kirjaa.php?id=' + bookId;
    }

    function deleteBook(bookId, bookName) {
        if (confirm('Haluatko varmasti poistaa kirjan "' + bookName + '"? Tätä toimintoa ei voi peruuttaa.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'book_id';
            input.value = bookId;
            form.appendChild(input);
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'delete_book';
            actionInput.value = '1';
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Auto-hide notifications after 5 seconds
    setTimeout(function() {
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(function(notification) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-15px)';
            notification.style.transition = 'all 0.3s ease';
            setTimeout(function() {
                notification.style.display = 'none';
            }, 300);
        });
    }, 5000);

    // Intersection Observer for scroll animations
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.stat-card, .filter-section, .books-section').forEach(function(el) {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'all 0.5s ease';
        observer.observe(el);
    });
</script>

</body>
</html>
