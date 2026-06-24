<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the database connection
require_once 'connection.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user data including profile image and role
$user_id = $_SESSION['user_id'];

// FIRST: Check if profile image is already in session (from admin dashboard)
$profile_image = $_SESSION['profile_image'] ?? null;

// If not in session, fetch from database
if (empty($profile_image)) {
    $user_sql = "SELECT etunimi, sukunimi, email, jasennumero, profile_image, rooli FROM jasenet WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();

    // Store user data in session
    $_SESSION['first_name'] = $user_data['etunimi'];
    $_SESSION['last_name'] = $user_data['sukunimi'];
    $_SESSION['email'] = $user_data['email'];
    $_SESSION['membership_number'] = $user_data['jasennumero'];
    $_SESSION['profile_image'] = $user_data['profile_image'];
    $_SESSION['role'] = $user_data['rooli'];

    $profile_image = $user_data['profile_image'];
    $user_role = $user_data['rooli'];
} else {
    // Get user data from session
    $user_role = $_SESSION['role'] ?? 'user';
}

// Get user data from session
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$email = $_SESSION['email'];

// Function to get profile image URL
function getProfileImageUrl($profile_image, $user_name) {
    if (empty($profile_image)) {
        return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
    }
    if (filter_var($profile_image, FILTER_VALIDATE_URL)) {
        return $profile_image;
    }
    $possible_paths = [
        $profile_image,
        'uploads/profiles/' . $profile_image,
        'uploads/profiles/' . basename($profile_image),
        basename($profile_image)
    ];
    foreach ($possible_paths as $path) {
        if (file_exists($path) && is_file($path)) {
            return $path;
        }
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
}

$user_name = $first_name . ' ' . $last_name;
$profile_image_url = getProfileImageUrl($profile_image, $user_name);

$admin_role = $user_role === 'admin' ? 'Ylläpitäjä' : 'Manager';
$admin_permissions = $user_role === 'admin' ? 'Täydet järjestelmäoikeudet' : 'Laajat hallintaoikeudet';

// Initialize variables
$search_query = "";
$filter_status = "";
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$limit = 20;
$offset = ($page - 1) * $limit;

// Get filter parameters
if (isset($_GET['search'])) {
    $search_query = $conn->real_escape_string(trim($_GET['search']));
}
if (isset($_GET['status'])) {
    $filter_status = $conn->real_escape_string(trim($_GET['status']));
}

// Process actions
$success_messages = [];
$error_messages = [];

// Mark as returned
if (isset($_POST['mark_returned']) && isset($_POST['loan_id'])) {
    $loan_id = intval($_POST['loan_id']);
    $return_date = date('Y-m-d');

    $sql = "UPDATE lainat SET palautuspaiva = ?, tila = 'palautettu' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("si", $return_date, $loan_id);
        if ($stmt->execute()) {
            $success_messages[] = "Laina merkitty palautetuksi!";
        } else {
            $error_messages[] = "Virhe päivitettäessä lainaa: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Extend loan by 1 month
if (isset($_POST['extend_loan']) && isset($_POST['loan_id'])) {
    $loan_id = intval($_POST['loan_id']);

    $get_sql = "SELECT erapaiva FROM lainat WHERE id = ?";
    $get_stmt = $conn->prepare($get_sql);
    if ($get_stmt) {
        $get_stmt->bind_param("i", $loan_id);
        $get_stmt->execute();
        $get_stmt->bind_result($current_due);
        $get_stmt->fetch();
        $get_stmt->close();

        if ($current_due) {
            $new_due = date('Y-m-d', strtotime($current_due . ' +1 month'));

            $update_sql = "UPDATE lainat SET erapaiva = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param("si", $new_due, $loan_id);
                if ($update_stmt->execute()) {
                    $success_messages[] = "Laina-aikaa jatkettu 1 kuukaudella! Uusi eräpäivä: " . date('d.m.Y', strtotime($new_due));
                } else {
                    $error_messages[] = "Virhe lainan jatkossa: " . $update_stmt->error;
                }
                $update_stmt->close();
            }
        }
    }
}

// Build query for loans
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($search_query)) {
    $where_conditions[] = "(k.nimi LIKE ? OR k.tekija LIKE ?)";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
    $param_types .= "ss";
}

if (!empty($filter_status)) {
    if ($filter_status == 'active') {
        $where_conditions[] = "l.tila = 'aktiivinen' AND l.erapaiva >= CURDATE()";
    } elseif ($filter_status == 'overdue') {
        $where_conditions[] = "l.tila = 'aktiivinen' AND l.erapaiva < CURDATE()";
    } elseif ($filter_status == 'returned') {
        $where_conditions[] = "l.tila = 'palautettu'";
    }
}

$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM lainat l
              JOIN kirjat k ON l.kirja_id = k.id
              $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_stmt->bind_result($total_loans);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_loans / $limit);

// Get loans data
$sql = "SELECT l.*, k.nimi as kirja_nimi, k.tekija, k.isbn,
               DATEDIFF(l.erapaiva, CURDATE()) as days_remaining,
               CASE
                   WHEN l.tila = 'palautettu' THEN 'palautettu'
                   WHEN l.erapaiva < CURDATE() THEN 'myohassa'
                   ELSE 'aktiivinen'
               END as loan_status
        FROM lainat l
        JOIN kirjat k ON l.kirja_id = k.id
        $where_sql
        ORDER BY l.lainauspaiva DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $param_types .= "ii";
        $params[] = $limit;
        $params[] = $offset;
        $stmt->bind_param($param_types, ...$params);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $loans = [];
    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
    }
    $stmt->close();
}

// Get statistics
$stats_sql = "SELECT
    COUNT(*) as total_loans,
    SUM(CASE WHEN tila = 'aktiivinen' AND erapaiva >= CURDATE() THEN 1 ELSE 0 END) as active_loans,
    SUM(CASE WHEN tila = 'aktiivinen' AND erapaiva < CURDATE() THEN 1 ELSE 0 END) as overdue_loans,
    SUM(CASE WHEN tila = 'palautettu' THEN 1 ELSE 0 END) as returned_loans
    FROM lainat";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
$stats_result->close();

// Get recent returns (last 7 days)
$recent_sql = "SELECT l.*, k.nimi as kirja_nimi, k.tekija
               FROM lainat l
               JOIN kirjat k ON l.kirja_id = k.id
               WHERE l.palautuspaiva >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
               ORDER BY l.palautuspaiva DESC
               LIMIT 5";
$recent_result = $conn->query($recent_sql);
$recent_returns = [];
while ($row = $recent_result->fetch_assoc()) {
    $recent_returns[] = $row;
}
$recent_result->close();
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lainojen Hallinta | Admin | Kirjasto</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* ============================================
           MODERN ADMIN DASHBOARD CSS
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

        /* SIDEBAR - FIXED */
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
            border-right: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }

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

        /* MAIN CONTENT - SCROLLS INDEPENDENTLY */
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .filter-form {
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

        .form-control option {
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

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #94a3b8;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.75rem;
        }

        /* TABLE CONTAINER */
        .table-container {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 0;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
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

        .loans-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .loans-table th {
            text-align: left;
            padding: 15px;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }

        .loans-table td {
            padding: 15px;
            color: #cbd5e1;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            vertical-align: middle;
        }

        .loans-table tr:hover td {
            background: rgba(255,255,255,0.05);
        }

        .book-title {
            color: white;
            font-weight: 600;
        }

        .book-isbn {
            font-size: 0.7rem;
            color: #94a3b8;
        }

        /* STATUS BADGES */
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

        /* ACTION BUTTONS */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
        }

        /* ALERTS */
        .alert {
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

        .alert-success {
            border-left-color: #10b981;
            color: #10b981;
        }

        .alert-error {
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

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
            flex-wrap: wrap;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s;
        }

        .page-link:hover {
            background: rgba(102,126,234,0.2);
            color: white;
            border-color: #667eea;
        }

        .page-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: transparent;
        }

        /* RECENT RETURNS */
        .recent-returns {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .recent-returns h3 {
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

        .return-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .return-item:last-child {
            border-bottom: none;
        }

        .return-info h4 {
            color: white;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .return-info p {
            color: #94a3b8;
            font-size: 0.75rem;
        }

        .return-date {
            color: #10b981;
            font-size: 0.75rem;
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
            .filter-form {
                grid-template-columns: 1fr;
            }
            .header {
                flex-direction: column;
                text-align: center;
            }
            .action-buttons {
                flex-direction: column;
            }
            .action-buttons .btn {
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
            <h4><?php echo htmlspecialchars($user_name); ?></h4>
            <p><?php echo $admin_role; ?></p>
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
        <a href="admin_lainat.php" class="menu-item active"><i class="fas fa-list"></i><span>Hallinnoi Lainoja</span></a>
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

    <!-- HEADER -->
    <div class="header">
        <div class="page-title">
            <h1><i class="fas fa-clipboard-list"></i> Lainojen Hallinta</h1>
            <p><i class="fas fa-book"></i> Hallinnoi ja seuraa kaikkia lainoja</p>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($user_name); ?></h3>
                <p style="color: #94a3b8;"><i class="fas fa-envelope" style="color: #667eea;"></i> <?php echo htmlspecialchars($email); ?></p>
                <p style="color: #10b981;"><i class="fas fa-shield-alt" style="color: #10b981;"></i> <?php echo $admin_role; ?></p>
                <p style="color: #f59e0b;"><i class="fas fa-key" style="color: #f59e0b;"></i> <?php echo $admin_permissions; ?></p>
            </div>
        </div>
    </div>

    <!-- NOTIFICATIONS -->
    <?php if (!empty($success_messages)): ?>
        <?php foreach ($success_messages as $msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($error_messages)): ?>
        <?php foreach ($error_messages as $msg): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- STATISTICS CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Kaikki lainat</h3>
                    <div class="stat-number"><?php echo $stats['total_loans'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-book-open"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Aktiiviset lainat</h3>
                    <div class="stat-number"><?php echo $stats['active_loans'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Myöhässä</h3>
                    <div class="stat-number"><?php echo $stats['overdue_loans'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Palautetut</h3>
                    <div class="stat-number"><?php echo $stats['returned_loans'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div class="section-title">
            <i class="fas fa-filter"></i> Suodata lainoja
        </div>
        <form method="GET" action="" class="filter-form">
            <div class="form-group">
                <label class="form-label">Hae kirjoista</label>
                <input type="text" name="search" class="form-control"
                       placeholder="Kirjan nimi tai tekijä..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Lainan tila</label>
                <select name="status" class="form-control">
                    <option value="">Kaikki lainat</option>
                    <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Aktiiviset</option>
                    <option value="overdue" <?php echo $filter_status == 'overdue' ? 'selected' : ''; ?>>Myöhässä</option>
                    <option value="returned" <?php echo $filter_status == 'returned' ? 'selected' : ''; ?>>Palautetut</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Hae</button>
                    <a href="admin_lainat.php" class="btn btn-secondary"><i class="fas fa-times"></i> Tyhjennä</a>
                </div>
            </div>
        </form>
    </div>

    <!-- LOANS TABLE -->
    <div class="table-container">
        <div class="table-wrapper">
            <table class="loans-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kirja</th>
                        <th>Tekijä</th>
                        <th>Lainauspäivä</th>
                        <th>Eräpäivä</th>
                        <th>Tila</th>
                        <th>Toiminnot</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($loans)): ?>
                        <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td>#<?php echo $loan['id']; ?></td>
                                <td>
                                    <div class="book-title"><?php echo htmlspecialchars($loan['kirja_nimi']); ?></div>
                                    <div class="book-isbn">ISBN: <?php echo htmlspecialchars($loan['isbn'] ?? '-'); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($loan['tekija']); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($loan['lainauspaiva'])); ?></td>
                                <td>
                                    <?php echo date('d.m.Y', strtotime($loan['erapaiva'])); ?>
                                    <?php if ($loan['loan_status'] == 'aktiivinen'): ?>
                                        <br><small style="color: #10b981;"><?php echo $loan['days_remaining']; ?> pv jäljellä</small>
                                    <?php elseif ($loan['loan_status'] == 'myohassa'): ?>
                                        <br><small style="color: #ef4444;"><?php echo abs($loan['days_remaining']); ?> pv myöhässä</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($loan['loan_status'] == 'aktiivinen'): ?>
                                        <span class="status-badge status-active"><i class="fas fa-clock"></i> Aktiivinen</span>
                                    <?php elseif ($loan['loan_status'] == 'myohassa'): ?>
                                        <span class="status-badge status-overdue"><i class="fas fa-exclamation-triangle"></i> Myöhässä</span>
                                    <?php else: ?>
                                        <span class="status-badge status-returned"><i class="fas fa-check-circle"></i> Palautettu</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($loan['loan_status'] != 'palautettu'): ?>
                                        <div class="action-buttons">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                <button type="submit" name="mark_returned" class="btn btn-success btn-sm" onclick="return confirm('Haluatko varmasti merkitä tämän lainan palautetuksi?')">
                                                    <i class="fas fa-check"></i> Palauta
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                <button type="submit" name="extend_loan" class="btn btn-warning btn-sm" onclick="return confirm('Haluatko varmasti jatkaa laina-aikaa 1 kuukaudella?')">
                                                    <i class="fas fa-calendar-plus"></i> Jatka
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #64748b;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <i class="fas fa-book"></i>
                                <p>Ei lainoja löytynyt</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($filter_status); ?>" class="page-link">
                    <i class="fas fa-chevron-left"></i> Edellinen
                </a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($filter_status); ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($filter_status); ?>" class="page-link">
                    Seuraava <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- RECENT RETURNS -->
    <?php if (!empty($recent_returns)): ?>
        <div class="recent-returns">
            <h3><i class="fas fa-history"></i> Viimeisimmät palautukset (7 pv)</h3>
            <?php foreach ($recent_returns as $return): ?>
                <div class="return-item">
                    <div class="return-info">
                        <h4><?php echo htmlspecialchars($return['kirja_nimi']); ?></h4>
                        <p><?php echo htmlspecialchars($return['tekija']); ?></p>
                    </div>
                    <div class="return-date">
                        Palautettu: <?php echo date('d.m.Y', strtotime($return['palautuspaiva'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide notifications after 5 seconds
        setTimeout(function() {
            const notifications = document.querySelectorAll('.alert');
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

        document.querySelectorAll('.stat-card, .filter-section, .table-container, .recent-returns').forEach(function(el) {
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
            menuToggle.style.display = 'block';
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
</script>

</body>
</html>
