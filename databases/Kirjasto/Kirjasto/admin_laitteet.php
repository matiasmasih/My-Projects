<?php
session_start();
require_once 'connection.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT rooli, profile_image, etunimi, sukunimi, email FROM jasenet WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$stmt->close();

// Check access
if ($current_user['rooli'] !== 'manager' && $current_user['rooli'] !== 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// User display info
$full_name = $current_user['etunimi'] . ' ' . $current_user['sukunimi'];
$user_email = $current_user['email'] ?? 'admin@example.com';
$user_role = $current_user['rooli'] === 'admin' ? 'Ylläpitäjä' : 'Manager';
$user_permissions = $current_user['rooli'] === 'admin' ? 'Täydet järjestelmäoikeudet' : 'Täydet laiteoikeudet';

// Profile image
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
    return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
}

$profile_image_url = getProfileImageUrl($current_user['profile_image'] ?? '', $full_name);

// Device icon function
function getDeviceIcon($name) {
    $name = strtolower($name);
    if (strpos($name, 'kannettava') !== false || strpos($name, 'laptop') !== false) return 'fa-laptop';
    if (strpos($name, 'tabletti') !== false) return 'fa-tablet-alt';
    if (strpos($name, 'puhelin') !== false) return 'fa-mobile-alt';
    if (strpos($name, 'projektori') !== false) return 'fa-video';
    if (strpos($name, 'kamera') !== false) return 'fa-camera';
    if (strpos($name, 'kuulokkeet') !== false) return 'fa-headphones';
    if (strpos($name, 'tulostin') !== false) return 'fa-print';
    return 'fa-microchip';
}

function getDeviceColor($name) {
    $name = strtolower($name);
    if (strpos($name, 'kannettava') !== false) return '#3498DB';
    if (strpos($name, 'tabletti') !== false) return '#9B59B6';
    if (strpos($name, 'puhelin') !== false) return '#2ECC71';
    if (strpos($name, 'projektori') !== false) return '#E67E22';
    if (strpos($name, 'kamera') !== false) return '#E74C3C';
    if (strpos($name, 'kuulokkeet') !== false) return '#1ABC9C';
    if (strpos($name, 'tulostin') !== false) return '#F39C12';
    return '#667eea';
}

$message = '';
$error = '';
$edit_mode = false;
$edit_data = null;

// Create table if not exists
$table_check = $conn->query("SHOW TABLES LIKE 'Laitteet'");
if ($table_check && $table_check->num_rows == 0) {
    $create_sql = "CREATE TABLE IF NOT EXISTS Laitteet (
        id INT PRIMARY KEY AUTO_INCREMENT,
        laite_tyyppi_id INT NOT NULL,
        sarjanumero VARCHAR(100) UNIQUE NOT NULL,
        merkki VARCHAR(100),
        malli VARCHAR(100),
        kunto ENUM('erinomainen','hyvä','tyydyttävä','huono') DEFAULT 'hyvä',
        tila ENUM('saatavilla','lainassa','varattu','huoltotila') DEFAULT 'saatavilla',
        sijainti VARCHAR(100),
        huomiot TEXT,
        hankintapaiva DATE,
        viime_huolto DATE,
        luotu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (laite_tyyppi_id) REFERENCES Laitetyypit(id) ON DELETE RESTRICT
    )";
    $conn->query($create_sql);
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_device'])) {
        $stmt = $conn->prepare("INSERT INTO Laitteet (laite_tyyppi_id, sarjanumero, merkki, malli, kunto, tila, sijainti, huomiot, hankintapaiva, viime_huolto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssssss",
            $_POST['laite_tyyppi_id'], $_POST['sarjanumero'], $_POST['merkki'], $_POST['malli'],
            $_POST['kunto'], $_POST['tila'], $_POST['sijainti'], $_POST['huomiot'],
            $_POST['hankintapaiva'] ?: null, $_POST['viime_huolto'] ?: null
        );
        if ($stmt->execute()) {
            $message = "Laite lisätty onnistuneesti!";
            header("Location: admin_laitteet.php?success=1");
            exit();
        } else {
            $error = "Virhe: " . $conn->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['update_device'])) {
        $stmt = $conn->prepare("UPDATE Laitteet SET laite_tyyppi_id=?, sarjanumero=?, merkki=?, malli=?, kunto=?, tila=?, sijainti=?, huomiot=?, hankintapaiva=?, viime_huolto=? WHERE id=?");
        $stmt->bind_param("isssssssssi",
            $_POST['laite_tyyppi_id'], $_POST['sarjanumero'], $_POST['merkki'], $_POST['malli'],
            $_POST['kunto'], $_POST['tila'], $_POST['sijainti'], $_POST['huomiot'],
            $_POST['hankintapaiva'] ?: null, $_POST['viime_huolto'] ?: null, $_POST['id']
        );
        if ($stmt->execute()) {
            $message = "Laite päivitetty onnistuneesti!";
            header("Location: admin_laitteet.php?success=1");
            exit();
        } else {
            $error = "Virhe: " . $conn->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['delete_device'])) {
        $stmt = $conn->prepare("DELETE FROM Laitteet WHERE id = ?");
        $stmt->bind_param("i", $_POST['id']);
        if ($stmt->execute()) {
            $message = "Laite poistettu onnistuneesti!";
            header("Location: admin_laitteet.php?success=1");
            exit();
        } else {
            $error = "Virhe: " . $conn->error;
        }
        $stmt->close();
    }
}

// Edit mode
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM Laitteet WHERE id = ?");
    $stmt->bind_param("i", $_GET['edit']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_mode = true;
        $edit_data = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get device types
$device_types = [];
$types_result = $conn->query("SELECT id, nimi FROM Laitetyypit ORDER BY nimi");
if ($types_result) {
    while ($type = $types_result->fetch_assoc()) {
        $device_types[] = $type;
    }
}

// Get statistics - FIXED to show correct counts
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN tila = 'saatavilla' THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN tila = 'lainassa' THEN 1 ELSE 0 END) as borrowed,
    SUM(CASE WHEN tila = 'huoltotila' THEN 1 ELSE 0 END) as maintenance
    FROM Laitteet")->fetch_assoc();

// Filters
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

$filter_sql = "SELECT l.*, lt.nimi as tyyppi_nimi, lt.laina_aika 
               FROM Laitteet l 
               LEFT JOIN Laitetyypit lt ON l.laite_tyyppi_id = lt.id 
               WHERE 1=1";

if (!empty($search)) {
    $filter_sql .= " AND (l.sarjanumero LIKE '%$search%' OR l.merkki LIKE '%$search%' OR l.malli LIKE '%$search%')";
}
if (!empty($type_filter)) {
    $filter_sql .= " AND l.laite_tyyppi_id = $type_filter";
}
if (!empty($status_filter)) {
    $filter_sql .= " AND l.tila = '$status_filter'";
}
$filter_sql .= " ORDER BY l.luotu DESC";
$filtered_result = $conn->query($filter_sql);
?>


<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laitteet | Admin | Kirjasto</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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
           SIDEBAR
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
            border-right: 1px solid rgba(255, 255, 255, 0.08);
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
           MAIN CONTENT
           ============================================ */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
            height: 100vh;
            overflow-y: auto;
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
    color: white !important;
    margin-bottom: 5px;
}

.user-details .user-email {
    color: #94a3b8 !important;
    font-size: 0.75rem;
    margin-bottom: 5px;
}

.user-details .user-email i {
    color: #667eea !important;
}

.user-details .user-role {
    color: #10b981 !important;
    font-size: 0.75rem;
    margin-bottom: 5px;
}

.user-details .user-role i {
    color: #10b981 !important;
}

.user-details .user-permissions {
    color: #f59e0b !important;
    font-size: 0.75rem;
}

.user-details .user-permissions i {
    color: #f59e0b !important;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            color: #a78bfa;
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
            color: white;
        }

        /* ============================================
           BUTTONS
           ============================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 20px;
            height: 42px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            text-decoration: none;
            white-space: nowrap;
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

        .btn-light {
            background: rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-light:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        /* ============================================
           DEVICES GRID
           ============================================ */
        .devices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .device-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .device-card:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            background: rgba(255, 255, 255, 0.12);
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .device-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .device-card h3 {
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .card-body {
            padding: 20px;
        }

        .device-description {
            color: #cbd5e1;
            font-size: 0.85rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .device-description strong {
            color: #a78bfa;
        }

        /* ============================================
           BADGES
           ============================================ */
        .status-badge,
        .condition-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin: 3px 0;
        }

        .status-available {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .status-borrowed {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }

        .status-maintenance {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .condition-excellent {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .condition-good {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }

        .condition-poor {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        /* ============================================
           DEVICE INFO
           ============================================ */
        .device-info {
            background: rgba(0, 0, 0, 0.2);
            padding: 12px;
            border-radius: 16px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .device-info-icon {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .device-info div {
            color: #cbd5e1;
        }

        /* ============================================
           CARD ACTIONS
           ============================================ */
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            flex: 1;
            padding: 8px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            font-size: 0.75rem;
            text-decoration: none;
            height: 38px;
        }

        .btn-edit {
            background: rgba(102, 126, 234, 0.15);
            color: #a78bfa;
            border: 1px solid rgba(102, 126, 234, 0.3);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
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
            max-width: 600px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-height: 85vh;
            overflow-y: auto;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-content .form-label {
            color: #a78bfa;
        }

        .modal-content .form-control {
            color: white;
            background: rgba(0, 0, 0, 0.3);
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
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #667eea;
        }

        .empty-state h3 {
            color: white;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #94a3b8;
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
            .search-filter,
            .devices-grid,
            .form-row {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                text-align: center;
            }

            .card-actions {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
            }

            .search-filter .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<!-- ============================================ -->
<!-- SIDEBAR
<!-- ============================================ -->
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
            <h4><?php echo htmlspecialchars($full_name); ?></h4>
            <p><?php echo $user_role; ?></p>
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
        <a href="admin_palautukset.php" class="menu-item"><i class="fas fa-undo-alt"></i><span>Hallinnoi Palautuksia</span></a>
        <a href="admin_myohassa_kirjat.php" class="menu-item"><i class="fas fa-clock"></i><span>Myöhässä Olevat</span></a>

        <div class="menu-section">🖥️ Laitehallinta</div>
        <a href="admin_laitetyypit.php" class="menu-item"><i class="fas fa-laptop"></i><span>Laitetyypit</span></a>
        <a href="admin_laitteet.php" class="menu-item active"><i class="fas fa-microchip"></i><span>Laitteet</span></a>
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

<!-- ============================================ -->
<!-- MAIN CONTENT
<!-- ============================================ -->
<div class="main-content">

<!-- HEADER -->
<div class="header">
    <div class="page-title">
        <h1><i class="fas fa-microchip"></i> Laitteet</h1>
        <p><i class="fas fa-laptop"></i> Hallinnoi kaikkia kirjaston laitteita</p>
    </div>
    <div class="user-info">
        <div class="user-avatar">
            <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
        </div>
        <div class="user-details">
            <h3><?php echo htmlspecialchars($full_name); ?></h3>
            <p class="user-email">
                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user_email); ?>
            </p>
            <p class="user-role">
                <i class="fas fa-shield-alt"></i> <?php echo $user_role; ?>
            </p>
            <p class="user-permissions">
                <i class="fas fa-key"></i> <?php echo $user_permissions; ?>
            </p>
        </div>
    </div>
</div>

    <!-- NOTIFICATIONS -->
    <?php if ($message): ?>
        <div class="notification notification-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notification notification-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- STATISTICS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info"><h3>Laitteet</h3><div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div></div>
                <div class="stat-icon"><i class="fas fa-laptop"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info"><h3>Vapaana</h3><div class="stat-number"><?php echo $stats['available'] ?? 0; ?></div></div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info"><h3>Lainassa</h3><div class="stat-number"><?php echo $stats['borrowed'] ?? 0; ?></div></div>
                <div class="stat-icon"><i class="fas fa-handshake"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info"><h3>Huollossa</h3><div class="stat-number"><?php echo $stats['maintenance'] ?? 0; ?></div></div>
                <div class="stat-icon"><i class="fas fa-tools"></i></div>
            </div>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div class="section-title"><i class="fas fa-search"></i> Hae ja hallitse laitteita</div>
        <form method="GET" action="">
            <div class="search-filter">
                <div class="form-group">
                    <label class="form-label">Haku</label>
                    <input type="text" name="search" class="form-control" placeholder="Sarjanumero, malli..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Laitetyyppi</label>
                    <select name="type" class="form-control">
                        <option value="">Kaikki tyypit</option>
                        <?php foreach ($device_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo ($type_filter == $type['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['nimi']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tila</label>
                    <select name="status" class="form-control">
                        <option value="">Kaikki tilat</option>
                        <option value="saatavilla" <?php echo ($status_filter == 'saatavilla') ? 'selected' : ''; ?>>🟢 Saatavilla</option>
                        <option value="lainassa" <?php echo ($status_filter == 'lainassa') ? 'selected' : ''; ?>>🔵 Lainassa</option>
                        <option value="huoltotila" <?php echo ($status_filter == 'huoltotila') ? 'selected' : ''; ?>>🟡 Huoltotila</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Hae</button>
                        <a href="admin_laitteet.php" class="btn btn-light"><i class="fas fa-times"></i> Tyhjennä</a>
                        <button type="button" class="btn btn-success" onclick="showModal()"><i class="fas fa-plus"></i> Lisää uusi</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- DEVICES GRID -->
    <div class="devices-grid">
        <?php if ($filtered_result && $filtered_result->num_rows > 0): ?>
            <?php while ($device = $filtered_result->fetch_assoc()):
                $tyyppi_nimi = $device['tyyppi_nimi'] ?? 'Laitetyyppi';
                $icon = getDeviceIcon($tyyppi_nimi);
                $color = getDeviceColor($tyyppi_nimi);

                if ($device['tila'] == 'saatavilla') {
                    $status_class = 'status-available';
                    $status_text = '🟢 Saatavilla';
                } elseif ($device['tila'] == 'lainassa') {
                    $status_class = 'status-borrowed';
                    $status_text = '🔵 Lainassa';
                } else {
                    $status_class = 'status-maintenance';
                    $status_text = '🟡 Huoltotila';
                }

                if ($device['kunto'] == 'erinomainen') {
                    $condition_class = 'condition-excellent';
                    $condition_text = '⭐ Erinomainen';
                } elseif ($device['kunto'] == 'hyvä') {
                    $condition_class = 'condition-good';
                    $condition_text = '👍 Hyvä';
                } else {
                    $condition_class = 'condition-poor';
                    $condition_text = '⚠️ Huono';
                }
            ?>
            <div class="device-card">
                <div class="card-header">
                    <div class="device-icon" style="background: <?php echo $color; ?>22; color: <?php echo $color; ?>;">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($device['sarjanumero']); ?></h3>
                    <div style="color: #94a3b8; font-size: 0.75rem;"><?php echo htmlspecialchars($device['merkki']); ?> <?php echo htmlspecialchars($device['malli']); ?></div>
                </div>
                <div class="card-body">
                    <div class="device-description">
                        <strong>Laitetyyppi:</strong> <?php echo htmlspecialchars($tyyppi_nimi); ?><br>
                        <strong>Laina-aika:</strong> <?php echo $device['laina_aika']; ?> päivää
                    </div>
                    <div>
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        <span class="condition-badge <?php echo $condition_class; ?>"><?php echo $condition_text; ?></span>
                    </div>
                    <div class="device-info">
                        <div class="device-info-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div><?php echo htmlspecialchars($device['sijainti'] ?: 'Ei määritetty'); ?></div>
                    </div>
                    <div class="card-actions">
                        <a href="?edit=<?php echo $device['id']; ?>" class="action-btn btn-edit"><i class="fas fa-edit"></i> Muokkaa</a>
                        <form method="POST" style="display: inline; flex: 1;">
                            <input type="hidden" name="id" value="<?php echo $device['id']; ?>">
                            <button type="submit" name="delete_device" class="action-btn btn-delete" onclick="return confirm('Haluatko varmasti poistaa laitteen?')"><i class="fas fa-trash"></i> Poista</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-laptop"></i>
                <h3>Ei laitteita</h3>
                <p>Aloita lisäämällä ensimmäinen laite.</p>
                <button class="btn btn-primary" onclick="showModal()" style="margin-top: 15px;"><i class="fas fa-plus"></i> Lisää ensimmäinen laite</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL
<!-- ============================================ -->
<div id="deviceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title"><?php echo $edit_mode ? 'Muokkaa Laitetta' : 'Lisää Uusi Laite'; ?></div>
            <button class="close-modal" onclick="hideModal()">&times;</button>
        </div>
        <form method="POST">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                <input type="hidden" name="update_device" value="1">
            <?php else: ?>
                <input type="hidden" name="add_device" value="1">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Sarjanumero *</label>
                    <input type="text" class="form-control" name="sarjanumero" required value="<?php echo $edit_mode ? htmlspecialchars($edit_data['sarjanumero']) : ''; ?>" placeholder="SN-2024-001">
                </div>
                <div class="form-group">
                    <label class="form-label">Laitetyyppi *</label>
                    <select class="form-control" name="laite_tyyppi_id" required>
                        <option value="">Valitse laitetyyppi</option>
                        <?php foreach ($device_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo ($edit_mode && $edit_data['laite_tyyppi_id'] == $type['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['nimi']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group"><label class="form-label">Merkki</label><input type="text" class="form-control" name="merkki" value="<?php echo $edit_mode ? htmlspecialchars($edit_data['merkki']) : ''; ?>" placeholder="Dell, Apple"></div>
                <div class="form-group"><label class="form-label">Malli</label><input type="text" class="form-control" name="malli" value="<?php echo $edit_mode ? htmlspecialchars($edit_data['malli']) : ''; ?>" placeholder="Latitude 5420"></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Kunto *</label>
                    <select class="form-control" name="kunto" required>
                        <option value="erinomainen" <?php echo ($edit_mode && $edit_data['kunto'] == 'erinomainen') ? 'selected' : ''; ?>>⭐ Erinomainen</option>
                        <option value="hyvä" <?php echo ($edit_mode && $edit_data['kunto'] == 'hyvä') ? 'selected' : ''; ?>>👍 Hyvä</option>
                        <option value="tyydyttävä" <?php echo ($edit_mode && $edit_data['kunto'] == 'tyydyttävä') ? 'selected' : ''; ?>>✅ Tyydyttävä</option>
                        <option value="huono" <?php echo ($edit_mode && $edit_data['kunto'] == 'huono') ? 'selected' : ''; ?>>⚠️ Huono</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tila *</label>
                    <select class="form-control" name="tila" required>
                        <option value="saatavilla" <?php echo ($edit_mode && $edit_data['tila'] == 'saatavilla') ? 'selected' : ''; ?>>🟢 Saatavilla</option>
                        <option value="lainassa" <?php echo ($edit_mode && $edit_data['tila'] == 'lainassa') ? 'selected' : ''; ?>>🔵 Lainassa</option>
                        <option value="huoltotila" <?php echo ($edit_mode && $edit_data['tila'] == 'huoltotila') ? 'selected' : ''; ?>>🟡 Huoltotila</option>
                    </select>
                </div>
            </div>

            <div class="form-group"><label class="form-label">Sijainti</label><input type="text" class="form-control" name="sijainti" value="<?php echo $edit_mode ? htmlspecialchars($edit_data['sijainti']) : ''; ?>" placeholder="Hylly A3"></div>
            <div class="form-group"><label class="form-label">Huomiot</label><textarea class="form-control" name="huomiot" rows="3" placeholder="Lisätietoja..."><?php echo $edit_mode ? htmlspecialchars($edit_data['huomiot']) : ''; ?></textarea></div>

            <div class="form-row">
                <div class="form-group"><label class="form-label">Hankintapäivä</label><input type="date" class="form-control" name="hankintapaiva" value="<?php echo $edit_mode ? $edit_data['hankintapaiva'] : ''; ?>"></div>
                <div class="form-group"><label class="form-label">Viimeisin huolto</label><input type="date" class="form-control" name="viime_huolto" value="<?php echo $edit_mode ? $edit_data['viime_huolto'] : ''; ?>"></div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-light" onclick="hideModal()">Peruuta</button>
                <button type="submit" class="btn btn-success">Tallenna</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showModal() {
        document.getElementById('deviceModal').style.display = 'flex';
    }

    function hideModal() {
        window.location.href = 'admin_laitteet.php';
    }

    <?php if ($edit_mode): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showModal();
        });
    <?php endif; ?>

    window.onclick = function(event) {
        if (event.target == document.getElementById('deviceModal')) {
            hideModal();
        }
    }

    // Auto-hide notifications after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.notification').forEach(function(n) {
            n.style.opacity = '0';
            n.style.transform = 'translateY(-15px)';
            n.style.transition = 'all 0.3s ease';
            setTimeout(function() {
                if (n.parentElement) n.remove();
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

    document.querySelectorAll('.stat-card, .filter-section, .device-card').forEach(function(el) {
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
</script>

</body>
</html>
