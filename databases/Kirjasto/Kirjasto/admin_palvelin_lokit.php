<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get current user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT rooli, profile_image, etunimi, sukunimi, email FROM jasenet WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($rooli, $profile_image, $etunimi, $sukunimi, $email);
$stmt->fetch();
$stmt->close();

// Both admin and manager can access server logs page
if ($rooli !== 'admin' && $rooli !== 'manager') {
    header("Location: user_dashboard.php");
    exit();
}

// Get user display info
$kayttajan_nimi = $etunimi . ' ' . $sukunimi;
$custom_name = $etunimi . ' ' . $sukunimi;
$custom_email = $email ?? 'email@example.com';
$custom_role_display = $rooli === 'admin' ? 'Ylläpitäjä' : 'Manager';
$custom_permissions = $rooli === 'admin' ? 'Täydet järjestelmäoikeudet' : 'Lokien tarkastelu';

// Profile image helper function
function getProfileImageUrl($profile_image, $user_name) {
    if (empty($profile_image)) {
        return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=3498db&color=fff&size=128';
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
    return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=3498db&color=fff&size=128';
}

$profile_image_url = getProfileImageUrl($profile_image ?? '', $kayttajan_nimi);

// Create logs table if not exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS palvelin_lokit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    aikaleima DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    taso ENUM('INFO', 'SUCCESS', 'WARNING', 'ERROR', 'SECURITY') NOT NULL DEFAULT 'INFO',
    tyyppi VARCHAR(50) NOT NULL,
    kayttaja VARCHAR(100) NOT NULL,
    viesti TEXT NOT NULL,
    ip_osoite VARCHAR(45),
    selain TEXT,
    INDEX idx_aikaleima (aikaleima),
    INDEX idx_taso (taso),
    INDEX idx_tyyppi (tyyppi),
    INDEX idx_kayttaja (kayttaja)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (!$conn->query($create_table_sql)) {
    // Silent error
}

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';
$message_type = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Log types
$log_types = [
    'all' => 'Kaikki lokit',
    'system' => 'Järjestelmä',
    'login' => 'Kirjautumiset',
    'activity' => 'Käyttäjätoiminnot',
    'error' => 'Virheet',
    'security' => 'Turvallisuus'
];

// Log levels with icons and colors
$log_levels = [
    'INFO' => ['icon' => 'fa-info-circle', 'color' => '#3498DB', 'bg' => 'rgba(52, 152, 219, 0.1)'],
    'SUCCESS' => ['icon' => 'fa-check-circle', 'color' => '#27AE60', 'bg' => 'rgba(39, 174, 96, 0.1)'],
    'WARNING' => ['icon' => 'fa-exclamation-triangle', 'color' => '#F39C12', 'bg' => 'rgba(243, 156, 18, 0.1)'],
    'ERROR' => ['icon' => 'fa-times-circle', 'color' => '#E74C3C', 'bg' => 'rgba(231, 76, 60, 0.1)'],
    'SECURITY' => ['icon' => 'fa-shield-alt', 'color' => '#9B59B6', 'bg' => 'rgba(155, 89, 182, 0.1)']
];

// Handle DELETE SINGLE LOG
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_log'])) {
    $log_id = intval($_POST['log_id']);
    $stmt = $conn->prepare("DELETE FROM palvelin_lokit WHERE id = ?");
    $stmt->bind_param("i", $log_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Loki poistettu onnistuneesti!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Virhe lokin poistossa!";
        $_SESSION['message_type'] = "error";
    }
    $stmt->close();
    header("Location: admin_palvelin_lokit.php");
    exit();
}

// Handle DELETE ALL LOGS
if ($action === 'delete_all_logs' && isset($_GET['confirmed']) && $_GET['confirmed'] === 'true' && $rooli === 'admin') {
    $conn->query("TRUNCATE TABLE palvelin_lokit");
    $_SESSION['message'] = "Kaikki lokit poistettu!";
    $_SESSION['message_type'] = "success";
    header("Location: admin_palvelin_lokit.php");
    exit();
}

// Get filter parameters
$filter_type = $_GET['type'] ?? 'all';
$filter_level = $_GET['level'] ?? 'all';
$filter_user = $_GET['user'] ?? '';
$filter_start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$filter_end_date = $_GET['end_date'] ?? date('Y-m-d');
$filter_search = $_GET['search'] ?? '';

// Build SQL query
$sql = "SELECT * FROM palvelin_lokit WHERE 1=1";
$params = [];
$types = "";

if ($filter_type !== 'all') {
    $sql .= " AND tyyppi = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($filter_level !== 'all') {
    $sql .= " AND taso = ?";
    $params[] = $filter_level;
    $types .= "s";
}

if (!empty($filter_user)) {
    $sql .= " AND kayttaja LIKE ?";
    $params[] = "%$filter_user%";
    $types .= "s";
}

if (!empty($filter_search)) {
    $sql .= " AND (viesti LIKE ? OR kayttaja LIKE ?)";
    $params[] = "%$filter_search%";
    $params[] = "%$filter_search%";
    $types .= "ss";
}

// Apply date filter
$filter_start_datetime = $filter_start_date . ' 00:00:00';
$filter_end_datetime = $filter_end_date . ' 23:59:59';

$sql .= " AND aikaleima BETWEEN ? AND ?";
$params[] = $filter_start_datetime;
$params[] = $filter_end_datetime;
$types .= "ss";

// ORDER BY id ASC - pienin ID ensin (1,2,3,4,5)
$sql .= " ORDER BY id ASC LIMIT 1000";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $filtered_logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $conn->query($sql);
    $filtered_logs = $result->fetch_all(MYSQLI_ASSOC);
}

// Get statistics
$total_logs = count($filtered_logs);
$error_count = 0;
$warning_count = 0;
$security_count = 0;
$success_count = 0;
$info_count = 0;
$unique_users = [];

foreach ($filtered_logs as $log) {
    if ($log['taso'] === 'ERROR') $error_count++;
    if ($log['taso'] === 'WARNING') $warning_count++;
    if ($log['taso'] === 'SECURITY') $security_count++;
    if ($log['taso'] === 'SUCCESS') $success_count++;
    if ($log['taso'] === 'INFO') $info_count++;
    if (!in_array($log['kayttaja'], $unique_users)) {
        $unique_users[] = $log['kayttaja'];
    }
}

// Get oldest and newest dates
$oldest_date = !empty($filtered_logs) ? end($filtered_logs)['aikaleima'] : null;
$newest_date = !empty($filtered_logs) ? $filtered_logs[0]['aikaleima'] : null;
$days_of_logs = $oldest_date ? floor((time() - strtotime($oldest_date)) / 86400) : 0;

date_default_timezone_set('Europe/Helsinki');

function getTimeAgo($timestamp) {
    if (!$timestamp) return 'tuntematon';
    $time = strtotime($timestamp);
    $diff = time() - $time;
    if ($diff < 60) return 'juuri nyt';
    if ($diff < 3600) return floor($diff / 60) . ' min sitten';
    if ($diff < 86400) return floor($diff / 3600) . ' h sitten';
    return floor($diff / 86400) . ' pv sitten';
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjasto - Palvelinlokit</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fi.js"></script>
    <style>
        :root {
            --primary: #2C3E50;
            --secondary: #E74C3C;
            --success: #27AE60;
            --danger: #F39C12;
            --warning: #F1C40F;
            --info: #3498DB;
            --purple: #9B59B6;
            --dark: #1A1A2E;
            --light: #F8F9FA;
            --sidebar-bg: #1A1A2E;
            --sidebar-text: #E0E0E0;
            --sidebar-hover: #3498DB;
            --sidebar-width: 250px;
            --card-bg: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(rgba(26, 26, 46, 0.9), rgba(26, 26, 46, 0.9)),
                        url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 5px 0 25px rgba(0,0,0,0.3);
        }

        .sidebar-header {
            padding: 30px 25px;
            background: linear-gradient(135deg, var(--primary), var(--dark));
            color: white;
            text-align: center;
            border-bottom: 2px solid var(--info);
        }

        .sidebar-header h2 {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 1.4em;
            font-weight: 600;
            color: white;
        }

        .admin-badge {
            background: var(--secondary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7em;
            margin-left: auto;
        }

        .sidebar-menu {
            padding: 10px 0;
        }

        .menu-section {
            padding: 10px 15px 5px;
            color: var(--info);
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin: 10px 0;
        }

        .menu-item {
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            margin: 5px 15px;
            border-radius: 8px;
        }

        .menu-item:hover, .menu-item.active {
            background: linear-gradient(135deg, var(--sidebar-hover), #2980B9);
            color: white;
            border-left-color: var(--warning);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }

        .menu-item i {
            width: 20px;
            text-align: center;
            font-size: 1.2em;
        }

        .logout-item {
            margin-top: 30px;
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .logout-item:hover {
            background: linear-gradient(135deg, var(--secondary), #C0392B);
            border-left-color: var(--secondary);
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            width: calc(100% - var(--sidebar-width));
        }

        /* Top section */
        .top-section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .title-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--info), #2980B9);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5em;
        }

        .page-title {
            font-size: 1.8em;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }

        /* PROFILE SECTION */
        .profile-section {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(245, 247, 250, 0.8);
            padding: 12px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            text-align: left;
        }

        .profile-name {
            font-size: 1.1em;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .profile-email {
            color: var(--secondary);
            font-size: 0.85em;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 4px;
        }

        .profile-role {
            color: var(--info);
            font-size: 0.8em;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 4px;
        }

        .profile-permissions {
            color: var(--success);
            font-size: 0.8em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* STATS GRID - 6 cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-top: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.12);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-info h3 {
            font-size: 0.85em;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 2.2em;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: white;
        }

        /* Stat card colors - 6 cards */
        .stat-card:nth-child(1) { border-color: var(--info); }
        .stat-card:nth-child(2) { border-color: var(--secondary); }
        .stat-card:nth-child(3) { border-color: var(--warning); }
        .stat-card:nth-child(4) { border-color: var(--purple); }
        .stat-card:nth-child(5) { border-color: var(--success); }
        .stat-card:nth-child(6) { border-color: var(--primary); }

        .stat-card:nth-child(1) .stat-icon { background: var(--info); }
        .stat-card:nth-child(2) .stat-icon { background: var(--secondary); }
        .stat-card:nth-child(3) .stat-icon { background: var(--warning); }
        .stat-card:nth-child(4) .stat-icon { background: var(--purple); }
        .stat-card:nth-child(5) .stat-icon { background: var(--success); }
        .stat-card:nth-child(6) .stat-icon { background: var(--primary); }

        /* FILTER SECTION */
        .filter-section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .section-title {
            font-size: 1.3em;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--info);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s;
            background: #fafafa;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--info);
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233498DB' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 1em;
            padding-right: 40px;
            cursor: pointer;
        }

        .date-input-wrapper {
            position: relative;
        }

        .date-input-wrapper i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--info);
            pointer-events: none;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 0.95em;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--info), #2980B9);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #f7fafc;
            color: var(--primary);
            border: 2px solid #e8e8e8;
        }

        .btn-secondary:hover {
            background: #edf2f7;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--secondary), #C0392B);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #219653);
            color: white;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* TABLE SECTION */
        .table-section {
            background: var(--card-bg);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .table-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.05));
            border-bottom: 2px solid #e8e8e8;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h3 {
            font-size: 1.2em;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 600px;
            position: relative;
        }

        /* Scrollbar styling */
        .table-container::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: var(--info);
            border-radius: 10px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #2980b9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        thead {
            background: #f7fafc;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 2px solid #e8e8e8;
            white-space: nowrap;
        }

        td {
            padding: 12px 20px;
            border-bottom: 1px solid #e8e8e8;
            color: var(--primary);
            white-space: nowrap;
        }

        tbody tr:hover {
            background: rgba(52, 152, 219, 0.05);
        }

        /* Colored icons */
        .icon-info { color: #3498DB; }
        .icon-success { color: #27AE60; }
        .icon-warning { color: #F39C12; }
        .icon-error { color: #E74C3C; }
        .icon-security { color: #9B59B6; }

        .log-level {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .log-level i {
            font-size: 1.1em;
        }

        .time-ago {
            font-size: 0.8em;
            color: #718096;
            margin-top: 4px;
            white-space: nowrap;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            background: rgba(231, 76, 60, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .action-btn:hover {
            background: linear-gradient(135deg, var(--secondary), #C0392B);
            color: white;
        }

        .notification {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            border: 2px solid;
        }

        .notification-success {
            border-color: var(--success);
            color: var(--success);
        }

        .notification-error {
            border-color: var(--secondary);
            color: var(--secondary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: var(--info);
            opacity: 0.5;
        }

        .text-muted {
            color: #a0aec0;
        }

        /* Mobile Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-header h2 span, .menu-item span, .menu-section {
                display: none;
            }
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            .top-section {
                flex-direction: column;
                gap: 15px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .filter-actions {
                flex-direction: column;
            }
            .filter-actions .btn {
                width: 100%;
            }
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>
                <i class="fas fa-crown"></i>
                <span>Admin Panel</span>
                <span class="admin-badge"><?php echo $rooli === 'manager' ? 'Manager' : 'Admin'; ?></span>
            </h2>
        </div>
        <div class="sidebar-menu">
            <div class="menu-section">⚙️ Päävalikko</div>
            <a href="<?php echo $rooli === 'manager' ? 'manager_dashboard.php' : 'admin_dashboard.php'; ?>" class="menu-item">
                <span>🏠 Kojelauta</span>
            </a>

            <div class="menu-section">📚 Kirjaston Hallinta</div>
            <a href="admin_manage_kirjat.php" class="menu-item">
                <span>📖 Hallinnoi Kirjoja</span>
            </a>
            <a href="admin_lisaa_kirja.php" class="menu-item">
                <span>➕ Lisää Kirja</span>
            </a>
            <a href="admin_muokkaa_kirjaa.php" class="menu-item">
                <span>✏️ Muokkaa Kirjoja</span>
            </a>

            <div class="menu-section">👥 Jäsenten Hallinta</div>
            <a href="admin_kayttajien_hallinta.php" class="menu-item">
                <span>👤 Hallinnoi Jäseniä</span>
            </a>
            <a href="register.php" class="menu-item">
                <span>👥 Rekisteröi Jäsen</span>
            </a>

            <div class="menu-section">🔄 Lainaushallinta</div>
            <a href="admin_lainat.php" class="menu-item">
                <span>📋 Hallinnoi Lainoja</span>
            </a>
            <a href="admin_varaukset.php" class="menu-item">
                <span>✅ Käsittele Lainoja</span>
            </a>
            <a href="admin_palautukset.php" class="menu-item">
                <span>↩️ Hallinnoi Palautuksia</span>
            </a>
            <a href="admin_myohassa_kirjat.php" class="menu-item">
                <span>⏰ Myöhässä Olevat</span>
            </a>

            <!-- DEVICE MANAGEMENT SECTION -->
            <div class="menu-section">🖥️ Laitehallinta</div>
            <a href="admin_laitetyypit.php" class="menu-item">
                <span>💻 Laitetyypit</span>
            </a>
            <a href="admin_laitteet.php" class="menu-item">
                <span>📱 Laitteet</span>
            </a>
            <a href="admin_laitevaraukset.php" class="menu-item">
                <span>📅 Laitevaraukset</span>
            </a>
            <a href="laiteadmin_lainat.php" class="menu-item">
                <span>🔄 Laitelainat</span>
            </a>

            <div class="menu-section">📊 Raportit & Sakot</div>
            <a href="admin_raportit.php" class="menu-item">
                <span>📈 Kirjasto Raportit</span>
            </a>
            <a href="admin_sakot.php" class="menu-item">
                <span>⚠️ Hallinnoi Sakkoja</span>
            </a>

            <div class="menu-section">🔧 Järjestelmä</div>
            <a href="admin_varmuuskopiointi.php" class="menu-item">
                <span>💾 Varmuuskopiot</span>
            </a>
            <a href="admin_kayttooikeudet.php" class="menu-item">
                <span>⚙️ Järjestelmäasetukset</span>
            </a>
            <a href="admin_palvelin_lokit.php" class="menu-item active">
                <span>📋 Palvelinlokit</span>
            </a>
            <a href="logout.php" class="menu-item logout-item">
                <span>🚪 Kirjaudu Ulos</span>
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- TOP SECTION -->
        <div class="top-section">
            <div class="page-header">
                <div class="title-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h1 class="page-title">Palvelinlokit</h1>
            </div>

            <div class="profile-section">
                <div class="profile-avatar">
                    <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo htmlspecialchars($custom_name); ?></div>
                    <div class="profile-email">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($custom_email); ?>
                    </div>
                    <div class="profile-role">
                        <i class="fas fa-user-shield"></i> <?php echo $custom_role_display; ?>
                    </div>
                    <div class="profile-permissions">
                        <i class="fas fa-key"></i> <?php echo $custom_permissions; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="notification notification-<?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- STATS GRID - 6 cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>LOKEJA</h3>
                        <div class="stat-number"><?php echo $total_logs; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>INFO</h3>
                        <div class="stat-number"><?php echo $info_count; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>SUCCESS</h3>
                        <div class="stat-number"><?php echo $success_count; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>VAROITUKSIA</h3>
                        <div class="stat-number"><?php echo $warning_count; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>VIRHEITÄ</h3>
                        <div class="stat-number"><?php echo $error_count; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>TURVALLISUUS</h3>
                        <div class="stat-number"><?php echo $security_count; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTER SECTION -->
        <div class="filter-section">
            <h2 class="section-title">
                <i class="fas fa-search"></i> Suodata lokeja
            </h2>

            <form method="GET">
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label" for="type"><i class="fas fa-filter"></i> Tyyppi</label>
                        <select class="form-control form-select" id="type" name="type">
                            <?php foreach ($log_types as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filter_type === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="level"><i class="fas fa-chart-line"></i> Taso</label>
                        <select class="form-control form-select" id="level" name="level">
                            <option value="all">Kaikki tasot</option>
                            <?php foreach ($log_levels as $key => $level): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filter_level === $key ? 'selected' : ''; ?>>
                                    <?php echo $key; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="user"><i class="fas fa-user"></i> Käyttäjä</label>
                        <input type="text" class="form-control" id="user" name="user" placeholder="Käyttäjätunnus" value="<?php echo htmlspecialchars($filter_user); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="search"><i class="fas fa-search"></i> Haku</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Hae viestistä..." value="<?php echo htmlspecialchars($filter_search); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="start_date"><i class="fas fa-calendar-alt"></i> Alkupäivä</label>
                        <div class="date-input-wrapper">
                            <input type="text" class="form-control datepicker" id="start_date" name="start_date" value="<?php echo $filter_start_date; ?>">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="end_date"><i class="fas fa-calendar-alt"></i> Loppupäivä</label>
                        <div class="date-input-wrapper">
                            <input type="text" class="form-control datepicker" id="end_date" name="end_date" value="<?php echo $filter_end_date; ?>">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Suodata
                    </button>
                    <a href="admin_palvelin_lokit.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Nollaa
                    </a>
                    <?php if ($rooli === 'admin'): ?>
                        <a href="?action=delete_all_logs&confirmed=true" class="btn btn-danger" onclick="return confirm('⚠️ Haluatko varmasti poistaa kaikki lokit?')">
                            <i class="fas fa-trash"></i> Poista kaikki
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- TABLE SECTION -->
        <div class="table-section">
            <div class="table-header">
                <h3><i class="fas fa-history"></i> Lokitapahtumat (<?php echo $total_logs; ?> kpl)</h3>
                <div class="filter-actions">
                    <span class="text-muted"><i class="fas fa-info-circle"></i> Yhteensä <?php echo count($unique_users); ?> käyttäjää</span>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Aikaleima</th>
                            <th>Taso</th>
                            <th>Tyyppi</th>
                            <th>Käyttäjä</th>
                            <th>Viesti</th>
                            <th>IP-osoite</th>
                            <th>Toiminnot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($filtered_logs)): ?>
                            <?php foreach ($filtered_logs as $log): ?>
                                <?php
                                $icon_class = '';
                                if ($log['taso'] === 'INFO') $icon_class = 'icon-info';
                                if ($log['taso'] === 'SUCCESS') $icon_class = 'icon-success';
                                if ($log['taso'] === 'WARNING') $icon_class = 'icon-warning';
                                if ($log['taso'] === 'ERROR') $icon_class = 'icon-error';
                                if ($log['taso'] === 'SECURITY') $icon_class = 'icon-security';
                                ?>
                                <tr>
                                    <td><strong><?php echo $log['id']; ?></strong></td>
                                    <td>
                                        <?php echo date('d.m.Y H:i', strtotime($log['aikaleima'])); ?>
                                        <div class="time-ago"><?php echo getTimeAgo($log['aikaleima']); ?></div>
                                    </td>
                                    <td>
                                        <div class="log-level">
                                            <i class="fas <?php echo $log_levels[$log['taso']]['icon']; ?> <?php echo $icon_class; ?>"></i>
                                            <?php echo $log['taso']; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $log['tyyppi']; ?></td>
                                    <td><?php echo htmlspecialchars($log['kayttaja']); ?></td>
                                    <td><?php echo htmlspecialchars($log['viesti']); ?></td>
                                    <td><?php echo $log['ip_osoite'] ?? '-'; ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Haluatko poistaa tämän lokin?')">
                                            <input type="hidden" name="delete_log" value="1">
                                            <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                                            <button type="submit" class="action-btn" title="Poista">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <h3>Ei lokeja</h3>
                                    <p>Ei näytettäviä lokitapahtumia.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fi.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tarkistetaan että flatpickr on ladattu
            if (typeof flatpickr !== 'function') {
                console.error('Flatpickr ei latautunut!');
                return;
            }

            console.log('Flatpickr ladattu onnistuneesti');

            // Etsitään kaikki päivämääräkentät
            const dateInputs = document.querySelectorAll('input[name="start_date"], input[name="end_date"], .datepicker');

            if (dateInputs.length === 0) {
                console.log('Ei löytynyt päivämääräkenttiä');
                return;
            }

            console.log('Löytyi ' + dateInputs.length + ' päivämääräkenttää');

            // Alustetaan jokainen kenttä
            dateInputs.forEach(function(input) {
                try {
                    flatpickr(input, {
                        locale: "fi",
                        dateFormat: "Y-m-d",
                        altInput: true,
                        altFormat: "d.m.Y",
                        allowInput: true,
                        theme: "material_blue",
                        showMonths: 1,
                        disableMobile: true,
                        nextArrow: '<i class="fas fa-chevron-right"></i>',
                        prevArrow: '<i class="fas fa-chevron-left"></i>',
                        minDate: null,
                        maxDate: null,
                        yearSelector: true,
                        onChange: function(selectedDates, dateStr, instance) {
                            console.log('Valittu: ' + dateStr);

                            // Tarkistetaan päivämäärien järjestys
                            const startInput = document.querySelector("input[name='start_date']");
                            const endInput = document.querySelector("input[name='end_date']");

                            if (startInput && endInput) {
                                if (instance.input === startInput && endInput.value && dateStr > endInput.value) {
                                    alert('Alkupäivä ei voi olla loppupäivän jälkeen!');
                                    instance.clear();
                                }
                                if (instance.input === endInput && startInput.value && dateStr < startInput.value) {
                                    alert('Loppupäivä ei voi olla alkupäivää aikaisemmin!');
                                    instance.clear();
                                }
                            }
                        }
                    });
                    console.log('Flatpickr alustettu kentälle:', input.name || input.id);
                } catch (e) {
                    console.error('Virhe flatpickr alustuksessa:', e);
                }
            });

            // Asetetaan oletuspäivämäärät
            const startInput = document.querySelector("input[name='start_date']");
            const endInput = document.querySelector("input[name='end_date']");

            if (startInput && !startInput.value) {
                const sevenDaysAgo = new Date();
                sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
                startInput.value = sevenDaysAgo.toISOString().split('T')[0];
                if (startInput._flatpickr) {
                    startInput._flatpickr.setDate(startInput.value);
                }
            }

            if (endInput && !endInput.value) {
                const today = new Date();
                endInput.value = today.toISOString().split('T')[0];
                if (endInput._flatpickr) {
                    endInput._flatpickr.setDate(endInput.value);
                }
            }
        });

        // Reset filters
        function resetFilters() {
            window.location.href = 'admin_palvelin_lokit.php';
        }

        // Delete all logs confirmation
        function confirmDeleteAllLogs() {
            if (confirm('VAROITUS: Tämä poistaa KAIKKI lokit pysyvästi!\n\nHaluatko varmasti jatkaa?')) {
                if (confirm('Oletko täysin varma? Tätä toimintoa ei voi perua!')) {
                    window.location.href = '?action=delete_all_logs&confirmed=true';
                }
            }
        }

        // Auto-hide notifications after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.notification').forEach(n => {
                n.style.opacity = '0';
                n.style.transition = 'all 0.3s ease';
                setTimeout(() => n.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>
<?php $conn->close(); ?>
