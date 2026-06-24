<?php
// admin_palautukset.php - Palautusten hallinta (Admin)
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Include the database connection
require_once 'connection.php';
require_once 'receipt_helper.php';

// Set timezone to Finland
date_default_timezone_set('Europe/Helsinki');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Hae käyttäjän tiedot
$user_id = $_SESSION['user_id'];
$user_query = "SELECT rooli, profile_image, etunimi, sukunimi, email FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

// Make sure we use the latest profile image from session if it exists
if (isset($_SESSION['profile_image'])) {
    $current_user['profile_image'] = $_SESSION['profile_image'];
}

// Admin and Manager can access
if (!$current_user || ($current_user['rooli'] != 'admin' && $current_user['rooli'] != 'manager')) {
    $rooli = htmlspecialchars($current_user['rooli'] ?? 'Ei määritelty');
    die("<div style='text-align: center; padding: 50px;'><h1>⛔ Käyttö estetty</h1><p>Vain ylläpitäjät ja managerit voivat käyttää tätä sivua.</p><a href='admin_dashboard.php'>Takaisin</a></div>");
}

// Get user's full name for display
$kayttajan_nimi = $current_user['etunimi'] . ' ' . $current_user['sukunimi'];

$custom_name = $current_user['etunimi'] . ' ' . $current_user['sukunimi'];
$custom_email = isset($current_user['email']) ? $current_user['email'] : "admin@example.com";
$custom_role_display = $current_user['rooli'] === 'admin' ? "Ylläpitäjä" : "Manager";
$custom_permissions = $current_user['rooli'] === 'admin' ? "Täydet järjestelmäoikeudet" : "Rajoitetut oikeudet";

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

$profile_image_url = getProfileImageUrl($current_user['profile_image'] ?? '', $kayttajan_nimi);

$errors = [];
$success = [];

// GET-parametrit
$search_query = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE conditions
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search_query)) {
    $where_conditions[] = "(k.nimi LIKE ? OR k.tekija LIKE ? OR j.etunimi LIKE ? OR j.sukunimi LIKE ?)";
    $search_term = "%{$search_query}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

if (!empty($filter_status)) {
    $where_conditions[] = "l.tila = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if (!empty($filter_type)) {
    $today = date('Y-m-d');
    switch ($filter_type) {
        case 'overdue':
            $where_conditions[] = "l.erapaiva < ? AND l.tila = 'aktiivinen'";
            $params[] = $today;
            $types .= 's';
            break;
        case 'today':
            $where_conditions[] = "l.erapaiva = ? AND l.tila = 'aktiivinen'";
            $params[] = $today;
            $types .= 's';
            break;
        case 'upcoming':
            $where_conditions[] = "l.erapaiva > ? AND l.tila = 'aktiivinen'";
            $params[] = $today;
            $types .= 's';
            break;
        case 'active':
            $where_conditions[] = "l.tila = 'aktiivinen'";
            break;
    }
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Count total - with DISTINCT
$count_sql = "SELECT COUNT(DISTINCT l.id) as total
              FROM lainat l
              JOIN kirjat k ON l.kirja_id = k.id
              JOIN jasenet j ON l.jasen_id = j.id
              $where_sql";

$count_stmt = $conn->prepare($count_sql);
if ($count_stmt) {
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $row = $count_result->fetch_assoc();
    $total_lainat = $row ? $row['total'] : 0;
    $total_pages = ceil($total_lainat / $limit);
} else {
    $total_lainat = 0;
    $total_pages = 1;
}

// Get loans - with DISTINCT
$lainat_sql = "SELECT DISTINCT
    l.id as laina_id,
    l.lainauspaiva,
    l.erapaiva,
    l.palautuspaiva,
    l.tila,
    COALESCE(l.sakot, 0) as sakko,
    k.nimi as kirja_nimi,
    k.tekija as kirja_tekija,
    k.isbn,
    j.etunimi as jasen_etunimi,
    j.sukunimi as jasen_sukunimi,
    j.email as jasen_email,
    j.puhelin as jasen_puhelin,
    DATEDIFF(CURDATE(), l.erapaiva) as paivia_myohassa
    FROM lainat l
    JOIN kirjat k ON l.kirja_id = k.id
    JOIN jasenet j ON l.jasen_id = j.id
    $where_sql
    ORDER BY l.erapaiva ASC
    LIMIT ? OFFSET ?";

$lainat_stmt = $conn->prepare($lainat_sql);
if ($lainat_stmt) {
    $lainat_params = $params;
    $lainat_types = $types;
    $lainat_params[] = $limit;
    $lainat_params[] = $offset;
    $lainat_types .= 'ii';

    if (!empty($lainat_params)) {
        $lainat_stmt->bind_param($lainat_types, ...$lainat_params);
    }
    $lainat_stmt->execute();
    $lainat_result = $lainat_stmt->get_result();
    $lainat = $lainat_result ? $lainat_result->fetch_all(MYSQLI_ASSOC) : [];
} else {
    $lainat = [];
    $errors[] = "SQL-valmistelu epäonnistui: " . $conn->error;
}

// Get statistics
$stats_sql = "SELECT
    COUNT(*) as total_lainat,
    COALESCE(SUM(CASE WHEN tila = 'aktiivinen' THEN 1 ELSE 0 END), 0) as aktiiviset_lainat,
    COALESCE(SUM(CASE WHEN tila = 'palautettu' THEN 1 ELSE 0 END), 0) as palautetut_lainat,
    COALESCE(SUM(CASE WHEN tila = 'myohassa' THEN 1 ELSE 0 END), 0) as myohassa_lainat,
    COALESCE(SUM(CASE WHEN erapaiva < CURDATE() AND tila = 'aktiivinen' THEN 1 ELSE 0 END), 0) as overdue_count,
    COALESCE(SUM(sakot), 0) as total_sakot
    FROM lainat";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : [
    'total_lainat' => 0,
    'aktiiviset_lainat' => 0,
    'palautetut_lainat' => 0,
    'myohassa_lainat' => 0,
    'overdue_count' => 0,
    'total_sakot' => 0
];

// POST handlers
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['mark_returned'])) {
        $laina_id = (int)($_POST['laina_id'] ?? 0);
        $sakko = (float)($_POST['sakko'] ?? 0);

        if ($laina_id > 0) {
            $book_info_sql = "SELECT k.nimi, l.jasen_id FROM lainat l JOIN kirjat k ON l.kirja_id = k.id WHERE l.id = ?";
            $book_stmt = $conn->prepare($book_info_sql);
            $book_stmt->bind_param("i", $laina_id);
            $book_stmt->execute();
            $book_result = $book_stmt->get_result();
            $book_data = $book_result->fetch_assoc();
            $book_stmt->close();

            $update_sql = "UPDATE lainat SET tila = 'palautettu', palautuspaiva = CURDATE(), sakot = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param("di", $sakko, $laina_id);
                if ($update_stmt->execute()) {
                    if ($book_data) {
                        createReturnReceipt($book_data['jasen_id'], $laina_id, 'book', $book_data['nimi'], date('Y-m-d H:i:s'));
                    }
                    $success[] = "Laina merkitty palautetuksi!";
                    if ($sakko > 0) {
                        $success[] = "Sakko asetettu: " . number_format($sakko, 2, ',', ' ') . " €";
                    }
                    header("Location: admin_palautukset.php?success=1");
                    exit();
                } else {
                    $errors[] = "Lainan päivitys epäonnistui: " . $conn->error;
                }
            }
        }
    }

    if (isset($_POST['bulk_action'])) {
        $action = $_POST['bulk_action'] ?? '';
        $selected_lainat = $_POST['selected_lainat'] ?? [];

        if (empty($selected_lainat)) {
            $errors[] = "Valitse vähintään yksi laina käsiteltäväksi";
        } else {
            $updated_count = 0;
            foreach ($selected_lainat as $laina_id) {
                $laina_id = (int)$laina_id;
                if ($laina_id > 0 && $action == 'return') {
                    $book_info_sql = "SELECT k.nimi, l.jasen_id FROM lainat l JOIN kirjat k ON l.kirja_id = k.id WHERE l.id = ?";
                    $book_stmt = $conn->prepare($book_info_sql);
                    $book_stmt->bind_param("i", $laina_id);
                    $book_stmt->execute();
                    $book_result = $book_stmt->get_result();
                    $book_data = $book_result->fetch_assoc();
                    $book_stmt->close();

                    $update_sql = "UPDATE lainat SET tila = 'palautettu', palautuspaiva = CURDATE() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("i", $laina_id);
                    if ($update_stmt->execute() && $book_data) {
                        createReturnReceipt($book_data['jasen_id'], $laina_id, 'book', $book_data['nimi'], date('Y-m-d H:i:s'));
                        $updated_count++;
                    }
                }
            }
            if ($updated_count > 0) {
                $success[] = "$updated_count lainaa palautettu onnistuneesti!";
                header("Location: admin_palautukset.php?success=1");
                exit();
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
    <title>Palautusten Hallinta | Admin | Kirjasto</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    /* ============================================
       RESET & BASE STYLES
       ============================================ */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html, body {
        height: 100%;
        overflow: hidden;
    }

    body {
        font-family: 'Inter', sans-serif;
        display: flex;
        position: relative;
    }

    /* Library Background Image */
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
       SIDEBAR - FIXED
       ============================================ */
    .sidebar {
        width: 280px;
        background: rgba(15, 25, 35, 0.95);
        backdrop-filter: blur(10px);
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        overflow-y: auto;
        z-index: 1000;
        border-right: 1px solid rgba(255, 255, 255, 0.08);
        flex-shrink: 0;
    }

    .sidebar::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 4px;
    }

    .sidebar-header {
        padding: 30px 25px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
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

    /* Profile Mini in Sidebar */
    .user-profile-mini {
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
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

    /* Sidebar Menu */
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
        border-top: 1px solid rgba(255, 255, 255, 0.08);
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
        margin-left: 280px;
        padding: 30px 40px;
        height: 100vh;
        overflow-y: auto;
        position: relative;
    }

    .main-content::-webkit-scrollbar {
        width: 6px;
    }

    .main-content::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 3px;
    }

    .main-content::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 3px;
    }

    /* ============================================
       HEADER
       ============================================ */
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
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(10px);
        padding: 12px 25px;
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.1);
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

    /* ============================================
       STATS GRID
       ============================================ */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 20px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        background: rgba(255, 255, 255, 0.12);
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

    /* ============================================
       FILTER SECTION
       ============================================ */
    .filter-section {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 25px;
        margin-bottom: 30px;
        border: 1px solid rgba(255, 255, 255, 0.1);
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
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: white;
        font-size: 0.9rem;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
    }

    .form-control option {
        background: #1a1a2e;
    }

    /* ============================================
       BUTTONS
       ============================================ */
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

    .btn-light {
        background: rgba(255, 255, 255, 0.1);
        color: #94a3b8;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .btn-light:hover {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.75rem;
    }

    /* ============================================
       RETURNS SECTION
       ============================================ */
    .returns-section {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 25px;
        margin-bottom: 30px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .bulk-actions {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
        align-items: center;
    }

    /* ============================================
       TABLE
       ============================================ */
    .table-wrapper {
        overflow-x: auto;
    }

    .table-wrapper::-webkit-scrollbar {
        height: 6px;
    }

    .table-wrapper::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 3px;
    }

    .table-wrapper::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 3px;
    }

    .returns-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }

    .returns-table th {
        text-align: left;
        padding: 15px;
        color: #94a3b8;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(0, 0, 0, 0.2);
    }

    .returns-table td {
        padding: 15px;
        color: #cbd5e1;
        font-size: 0.85rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        vertical-align: middle;
    }

    .returns-table tr:hover td {
        background: rgba(255, 255, 255, 0.05);
    }

    .member-name {
        color: white;
        font-weight: 600;
    }

    .member-info {
        font-size: 0.7rem;
        color: #94a3b8;
    }

    .book-title {
        color: white;
        font-weight: 600;
    }

    .book-info {
        font-size: 0.7rem;
        color: #94a3b8;
    }

    /* ============================================
       STATUS BADGES
       ============================================ */
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .status-active {
        background: rgba(59, 130, 246, 0.2);
        color: #60a5fa;
    }

    .status-overdue {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
    }

    .status-returned {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }

    .date-overdue {
        color: #ef4444;
        font-weight: 600;
    }

    .date-today {
        color: #f59e0b;
        font-weight: 600;
    }

    .date-future {
        color: #10b981;
    }

    .fine-amount {
        color: #ef4444;
        font-weight: 600;
    }

    .checkbox-cell {
        width: 40px;
        text-align: center;
    }

    .table-actions {
        display: flex;
        gap: 8px;
        flex-wrap: nowrap;
    }

    /* ============================================
       MODAL
       ============================================ */
    .modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: rgba(15, 25, 35, 0.98);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .modal-title {
        color: white;
        font-size: 1.2rem;
        font-weight: 600;
    }

    .close-modal {
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 1.5rem;
        cursor: pointer;
    }

    .close-modal:hover {
        color: #ef4444;
    }

    /* ============================================
       NOTIFICATIONS
       ============================================ */
    .notification {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideDown 0.4s ease;
        background: rgba(255, 255, 255, 0.08);
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

    /* ============================================
       EMPTY STATE
       ============================================ */
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

    /* ============================================
       PAGINATION
       ============================================ */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 25px;
        padding-top: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        flex-wrap: wrap;
    }

    .page-info {
        color: #94a3b8;
        font-size: 0.85rem;
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
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

        .bulk-actions {
            justify-content: center;
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
            <h4><?php echo htmlspecialchars($custom_name); ?></h4>
            <p><?php echo $custom_role_display; ?></p>
        </div>
    </a>

    <div class="sidebar-menu">
        <div class="menu-section">⚙️ Päävalikko</div>
        <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i><span>Kojelauta</span></a>

        <div class="menu-section">📚 Kirjaston Hallinta</div>
        <a href="admin_manage_kirjat.php" class="menu-item"><i class="fas fa-book"></i><span>Hallinnoi Kirjoja</span></a>
        <a href="admin_lisaa_kirja.php" class="menu-item"><i class="fas fa-plus"></i><span>Lisää Kirja</span></a>
        <a href="admin_muokkaa_kirjaa.php" class="menu-item"><i class="fas fa-edit"></i><span>Muokkaa Kirjoja</span></a>

        <div class="menu-section">👥 Jäsenten Hallinta</div>
        <a href="admin_kayttajien_hallinta.php" class="menu-item"><i class="fas fa-users"></i><span>Hallinnoi Jäseniä</span></a>
        <a href="register.php" class="menu-item"><i class="fas fa-user-plus"></i><span>Rekisteröi Jäsen</span></a>

        <div class="menu-section">🔄 Lainaushallinta</div>
        <a href="admin_lainat.php" class="menu-item"><i class="fas fa-list"></i><span>Hallinnoi Lainoja</span></a>
        <a href="admin_varaukset.php" class="menu-item"><i class="fas fa-check-circle"></i><span>Käsittele Lainoja</span></a>
        <a href="admin_palautukset.php" class="menu-item active"><i class="fas fa-undo-alt"></i><span>Hallinnoi Palautuksia</span></a>
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

    <!-- HEADER -->
    <div class="header">
        <div class="page-title">
            <h1><i class="fas fa-undo-alt"></i> Palautusten Hallinta</h1>
            <p><i class="fas fa-book"></i> Hallinnoi ja käsittele kirjojen palautuksia</p>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($custom_name); ?></h3>
                <p style="color: #94a3b8;"><i class="fas fa-envelope" style="color: #667eea;"></i> <?php echo htmlspecialchars($custom_email); ?></p>
                <p style="color: #10b981;"><i class="fas fa-shield-alt" style="color: #10b981;"></i> <?php echo $custom_role_display; ?></p>
                <p style="color: #f59e0b;"><i class="fas fa-key" style="color: #f59e0b;"></i> <?php echo $custom_permissions; ?></p>
            </div>
        </div>
    </div>

    <!-- NOTIFICATIONS -->
    <?php if (!empty($success)): ?>
        <div class="notification notification-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo implode(', ', $success); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="notification notification-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo implode(', ', $errors); ?></span>
        </div>
    <?php endif; ?>

    <!-- STATISTICS CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Kaikki lainat</h3>
                    <div class="stat-number"><?php echo number_format($stats['total_lainat'], 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-book-open"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Aktiiviset</h3>
                    <div class="stat-number"><?php echo number_format($stats['aktiiviset_lainat'], 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-spinner"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Palautetut</h3>
                    <div class="stat-number"><?php echo number_format($stats['palautetut_lainat'], 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Myöhässä</h3>
                    <div class="stat-number"><?php echo number_format($stats['overdue_count'], 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Sakot yhteensä</h3>
                    <div class="stat-number"><?php echo number_format($stats['total_sakot'], 2, ',', ' '); ?>€</div>
                </div>
                <div class="stat-icon"><i class="fas fa-euro-sign"></i></div>
            </div>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div class="section-title">
            <i class="fas fa-filter"></i> Hae ja suodata lainoja
        </div>
        <form method="GET" action="">
            <div class="search-filter">
                <div class="form-group">
                    <label class="form-label">Hae lainoja</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Hae kirjan nimen, tekijän tai jäsenen perusteella..."
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Tila</label>
                    <select name="status" class="form-control">
                        <option value="">Kaikki</option>
                        <option value="aktiivinen" <?php echo ($filter_status == 'aktiivinen') ? 'selected' : ''; ?>>Aktiivinen</option>
                        <option value="palautettu" <?php echo ($filter_status == 'palautettu') ? 'selected' : ''; ?>>Palautettu</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Suodata</label>
                    <select name="type" class="form-control">
                        <option value="">Kaikki lainat</option>
                        <option value="overdue" <?php echo ($filter_type == 'overdue') ? 'selected' : ''; ?>>Myöhässä olevat</option>
                        <option value="today" <?php echo ($filter_type == 'today') ? 'selected' : ''; ?>>Palautus tänään</option>
                        <option value="upcoming" <?php echo ($filter_type == 'upcoming') ? 'selected' : ''; ?>>Tulevat palautukset</option>
                        <option value="active" <?php echo ($filter_type == 'active') ? 'selected' : ''; ?>>Vain aktiiviset</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Hae</button>
                        <a href="admin_palautukset.php" class="btn btn-light"><i class="fas fa-times"></i> Tyhjennä</a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- RETURNS SECTION -->
    <div class="returns-section">
        <div class="section-title">
            <i class="fas fa-list"></i> Lainalista (<?php echo $total_lainat; ?> lainaa)
        </div>

        <form method="POST" action="" id="bulkForm">
            <div class="bulk-actions">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <input type="checkbox" id="select-all" onchange="toggleAllCheckboxes()" style="width: 18px; height: 18px;">
                        <label for="select-all" style="color: #94a3b8;">Valitse kaikki</label>
                    </div>
                    <select name="bulk_action" class="form-control" style="width: auto; min-width: 150px;" onchange="if(this.value && confirm('Haluatko varmasti suorittaa toiminnon valituille lainoille?')) this.form.submit()">
                        <option value="">Valitse toiminto...</option>
                        <option value="return">Merkitse palautetuksi</option>
                    </select>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="returns-table">
                    <thead>
                        <tr>
                            <th class="checkbox-cell"></th>
                            <th>Jäsen</th>
                            <th>Kirja</th>
                            <th>Lainattu</th>
                            <th>Eräpäivä</th>
                            <th>Tila</th>
                            <th>Sakko</th>
                            <th>Toiminnot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lainat)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-book-open"></i>
                                    <p>Ei lainoja löytynyt</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lainat as $laina): ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="selected_lainat[]" value="<?php echo $laina['laina_id']; ?>" class="loan-checkbox" style="width: 18px; height: 18px;">
                                    </td>
                                    <td>
                                        <div class="member-name"><?php echo htmlspecialchars($laina['jasen_etunimi'] . ' ' . $laina['jasen_sukunimi']); ?></div>
                                        <div class="member-info"><?php echo htmlspecialchars($laina['jasen_email']); ?></div>
                                    </td>
                                    <td>
                                        <div class="book-title"><?php echo htmlspecialchars($laina['kirja_nimi']); ?></div>
                                        <div class="book-info"><?php echo htmlspecialchars($laina['kirja_tekija']); ?></div>
                                    </td>
                                    <td><?php echo date('d.m.Y', strtotime($laina['lainauspaiva'])); ?></td>
                                    <td>
                                        <?php
                                        $today = date('Y-m-d');
                                        $due_date = $laina['erapaiva'];
                                        if ($laina['tila'] == 'palautettu') {
                                            echo '<span class="date-future">' . date('d.m.Y', strtotime($due_date)) . '</span>';
                                        } elseif ($due_date < $today) {
                                            echo '<span class="date-overdue">' . date('d.m.Y', strtotime($due_date)) . '</span>';
                                            echo '<div class="member-info">' . $laina['paivia_myohassa'] . ' pv myöhässä</div>';
                                        } elseif ($due_date == $today) {
                                            echo '<span class="date-today">' . date('d.m.Y', strtotime($due_date)) . '</span>';
                                            echo '<div class="member-info">Palautus tänään</div>';
                                        } else {
                                            echo '<span class="date-future">' . date('d.m.Y', strtotime($due_date)) . '</span>';
                                            $days_left = floor((strtotime($due_date) - strtotime($today)) / (60 * 60 * 24));
                                            echo '<div class="member-info">' . $days_left . ' pv jäljellä</div>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($laina['tila'] == 'palautettu') {
                                            $status_class = 'status-returned';
                                            $status_text = 'Palautettu';
                                        } elseif ($laina['tila'] == 'myohassa' || ($laina['erapaiva'] < date('Y-m-d') && $laina['tila'] == 'aktiivinen')) {
                                            $status_class = 'status-overdue';
                                            $status_text = 'Myöhässä';
                                        } else {
                                            $status_class = 'status-active';
                                            $status_text = 'Aktiivinen';
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($laina['sakko'] > 0): ?>
                                            <span class="fine-amount"><?php echo number_format($laina['sakko'], 2, ',', ' '); ?> €</span>
                                        <?php else: ?>
                                            <span style="color: #64748b;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <?php if ($laina['tila'] != 'palautettu'): ?>
                                                <button type="button" class="btn btn-success btn-sm" onclick="openReturnModal(<?php echo $laina['laina_id']; ?>, '<?php echo addslashes($laina['kirja_nimi']); ?>')">
                                                    <i class="fas fa-check"></i> Palauta
                                                </button>
                                            <?php else: ?>
                                                <span style="color: #64748b;">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1 && !empty($lainat)): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($filter_status); ?>&type=<?php echo urlencode($filter_type); ?>" class="btn btn-light btn-sm">
                            <i class="fas fa-chevron-left"></i> Edellinen
                        </a>
                    <?php endif; ?>
                    <span class="page-info">Sivu <?php echo $page; ?> / <?php echo $total_pages; ?></span>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($filter_status); ?>&type=<?php echo urlencode($filter_type); ?>" class="btn btn-light btn-sm">
                            Seuraava <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- RETURN MODAL -->
<div id="returnModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">Merkitse laina palautetuksi</div>
            <button type="button" class="close-modal" onclick="closeReturnModal()">&times;</button>
        </div>
        <form method="POST" action="" id="returnForm">
            <input type="hidden" name="laina_id" id="returnLainaId">
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">Sakko (€)</label>
                <input type="number" id="sakko" name="sakko" class="form-control" step="0.01" min="0" placeholder="Syötä sakon määrä">
                <div style="font-size: 0.7rem; color: #64748b; margin-top: 5px;">Jätä tyhjäksi jos ei sakkoa</div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-light" onclick="closeReturnModal()">Peruuta</button>
                <button type="submit" name="mark_returned" class="btn btn-success"><i class="fas fa-check"></i> Vahvista palautus</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide notifications after 5 seconds
        setTimeout(function() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(function(notification) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-15px)';
                notification.style.transition = 'all 0.3s ease';
                setTimeout(function() {
                    if (notification.parentElement) notification.remove();
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

        document.querySelectorAll('.stat-card, .filter-section, .returns-section').forEach(function(el) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.5s ease';
            observer.observe(el);
        });

        // Mobile sidebar toggle
        if (window.innerWidth <= 1024) {
            const menuToggle = document.createElement('button');
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            menuToggle.style.position = 'fixed';
            menuToggle.style.top = '20px';
            menuToggle.style.left = '20px';
            menuToggle.style.zIndex = '2001';
            menuToggle.style.background = 'linear-gradient(135deg, #667eea, #764ba2)';
            menuToggle.style.border = 'none';
            menuToggle.style.borderRadius = '12px';
            menuToggle.style.padding = '12px';
            menuToggle.style.color = 'white';
            menuToggle.style.cursor = 'pointer';
            document.body.appendChild(menuToggle);

            const sidebar = document.querySelector('.sidebar');
            menuToggle.addEventListener('click', function() {
                if (sidebar.style.transform === 'translateX(-100%)') {
                    sidebar.style.transform = 'translateX(0)';
                } else {
                    sidebar.style.transform = 'translateX(-100%)';
                }
            });
            sidebar.style.transform = 'translateX(-100%)';
        }
    });

    function openReturnModal(lainaId, bookTitle) {
        document.getElementById('returnLainaId').value = lainaId;
        document.getElementById('returnModal').style.display = 'flex';
        document.querySelector('.modal-title').innerHTML = 'Palauta: ' + bookTitle;
    }

    function closeReturnModal() {
        document.getElementById('returnModal').style.display = 'none';
        document.getElementById('returnForm').reset();
    }

    function toggleAllCheckboxes() {
        const checkAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.loan-checkbox');
        checkboxes.forEach(cb => cb.checked = checkAll.checked);
    }

    window.onclick = function(event) {
        const modal = document.getElementById('returnModal');
        if (event.target == modal) closeReturnModal();
    }
</script>

</body>
</html>
