<?php
session_start();
require_once 'connection.php';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
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

// Only manager and admin can access
if ($current_user['rooli'] !== 'manager' && $current_user['rooli'] !== 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Get user's full name for display
$kayttajan_nimi = $current_user['etunimi'] . ' ' . $current_user['sukunimi'];

// User info section - USING DATABASE VALUES
$custom_name = $current_user['etunimi'] . ' ' . $current_user['sukunimi'];
$custom_email = isset($current_user['email']) ? $current_user['email'] : "admin@example.com";
$custom_role_display = $current_user['rooli'] === 'admin' ? "Ylläpitäjä" : "Manager";
$custom_permissions = $current_user['rooli'] === 'admin' ? "Täydet järjestelmäoikeudet" : "Täydet laiteoikeudet";

// Profile image helper function
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

// Get the display URL for profile image
$profile_image_url = getProfileImageUrl($current_user['profile_image'] ?? '', $kayttajan_nimi);

// Function to get icon based on device type name
function getDeviceIcon($name) {
    $name = strtolower($name);

    // Check for specific device types
    if (strpos($name, 'kamera') !== false) return 'fa-camera';
    if (strpos($name, 'digitaalikamera') !== false) return 'fa-camera';
    if (strpos($name, 'puhelin') !== false) return 'fa-mobile-alt';
    if (strpos($name, 'kannettava') !== false) return 'fa-laptop';
    if (strpos($name, 'laptop') !== false) return 'fa-laptop';
    if (strpos($name, 'tabletti') !== false) return 'fa-tablet-alt';
    if (strpos($name, 'tablet') !== false) return 'fa-tablet-alt';
    if (strpos($name, 'projektori') !== false) return 'fa-video';
    if (strpos($name, 'videotykki') !== false) return 'fa-video';
    if (strpos($name, 'tulostin') !== false) return 'fa-print';
    if (strpos($name, 'printer') !== false) return 'fa-print';
    if (strpos($name, 'kuulokkeet') !== false) return 'fa-headphones';
    if (strpos($name, 'headphone') !== false) return 'fa-headphones';
    if (strpos($name, 'näppäimistö') !== false) return 'fa-keyboard';
    if (strpos($name, 'keyboard') !== false) return 'fa-keyboard';
    if (strpos($name, 'hiiri') !== false) return 'fa-mouse';
    if (strpos($name, 'mouse') !== false) return 'fa-mouse';
    if (strpos($name, 'server') !== false) return 'fa-server';
    if (strpos($name, 'palvelin') !== false) return 'fa-server';
    if (strpos($name, 'pelikonsoli') !== false) return 'fa-gamepad';
    if (strpos($name, 'kello') !== false) return 'fa-clock';
    if (strpos($name, 'watch') !== false) return 'fa-clock';
    if (strpos($name, 'tietokone') !== false) return 'fa-desktop';
    if (strpos($name, 'desktop') !== false) return 'fa-desktop';

    return 'fa-microchip';
}

// Function to get color based on device type
function getDeviceColor($name) {
    $name = strtolower($name);

    if (strpos($name, 'kamera') !== false) return '#E74C3C';
    if (strpos($name, 'puhelin') !== false) return '#2ECC71';
    if (strpos($name, 'kannettava') !== false) return '#3498DB';
    if (strpos($name, 'laptop') !== false) return '#3498DB';
    if (strpos($name, 'tabletti') !== false) return '#9B59B6';
    if (strpos($name, 'projektori') !== false) return '#E67E22';
    if (strpos($name, 'tulostin') !== false) return '#F39C12';
    if (strpos($name, 'kuulokkeet') !== false) return '#1ABC9C';
    if (strpos($name, 'näppäimistö') !== false) return '#34495E';
    if (strpos($name, 'tietokone') !== false) return '#34495E';
    if (strpos($name, 'server') !== false) return '#7F8C8D';
    if (strpos($name, 'pelikonsoli') !== false) return '#C0392B';
    if (strpos($name, 'kello') !== false) return '#16A085';

    return '#667eea';
}

$message = '';
$error = '';
$edit_mode = false;
$edit_data = null;

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_type'])) {
        $nimi = trim($_POST['nimi']);
        $kuvaus = trim($_POST['kuvaus']);
        $laina_aika = intval($_POST['laina_aika']);

        $sql = "INSERT INTO Laitetyypit (nimi, kuvaus, laina_aika) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $nimi, $kuvaus, $laina_aika);

        if ($stmt->execute()) {
            $message = "Laitetyyppi lisätty onnistuneesti!";
            header("Location: admin_laitetyypit.php?success=1");
            exit();
        } else {
            $error = "Virhe lisättäessä laitetyyppiä: " . $conn->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['update_type'])) {
        $id = intval($_POST['id']);
        $nimi = trim($_POST['nimi']);
        $kuvaus = trim($_POST['kuvaus']);
        $laina_aika = intval($_POST['laina_aika']);

        $sql = "UPDATE Laitetyypit SET nimi = ?, kuvaus = ?, laina_aika = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $nimi, $kuvaus, $laina_aika, $id);

        if ($stmt->execute()) {
            $message = "Laitetyyppi päivitetty onnistuneesti!";
            header("Location: admin_laitetyypit.php?success=1");
            exit();
        } else {
            $error = "Virhe päivittäessä laitetyyppiä: " . $conn->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['delete_type'])) {
        $id = intval($_POST['id']);

        $table_check = $conn->query("SHOW TABLES LIKE 'Laitteet'");
        if ($table_check && $table_check->num_rows > 0) {
            $check_sql = "SELECT COUNT(*) as device_count FROM Laitteet WHERE laite_tyyppi_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result && $check_result->num_rows > 0) {
                $device_count = $check_result->fetch_assoc()['device_count'];
                if ($device_count > 0) {
                    $error = "Laitetyyppiä ei voi poistaa, koska siihen on liitetty $device_count laitett(a). Poista ensin laitteet.";
                } else {
                    deleteType($id);
                }
            } else {
                deleteType($id);
            }
            $check_stmt->close();
        } else {
            deleteType($id);
        }
    }
}

function deleteType($id) {
    global $conn, $message, $error;
    $sql = "DELETE FROM Laitetyypit WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $message = "Laitetyyppi poistettu onnistuneesti!";
        header("Location: admin_laitetyypit.php?success=1");
        exit();
    } else {
        $error = "Virhe poistettaessa: " . $conn->error;
    }
    $stmt->close();
}

// Check if editing
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_sql = "SELECT * FROM Laitetyypit WHERE id = ?";
    $edit_stmt = $conn->prepare($edit_sql);
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();

    if ($edit_result->num_rows > 0) {
        $edit_mode = true;
        $edit_data = $edit_result->fetch_assoc();
    }
    $edit_stmt->close();
}

// Get total device count
$total_devices = 0;
$table_exists = false;
$table_check = $conn->query("SHOW TABLES LIKE 'Laitteet'");
if ($table_check && $table_check->num_rows > 0) {
    $table_exists = true;
    $total_devices_result = $conn->query("SELECT COUNT(*) as total FROM Laitteet");
    if ($total_devices_result) {
        $total_devices = $total_devices_result->fetch_assoc()['total'];
    }
}

// Get all device types - NO LIMIT, GET ALL
$sql = "SELECT * FROM Laitetyypit ORDER BY nimi";
$result = $conn->query($sql);
$total_types = $result ? $result->num_rows : 0;

// Calculate average loan time
$avg_loan_time = 0;
$avg_sql = "SELECT COALESCE(AVG(laina_aika), 0) as avg FROM Laitetyypit";
$avg_result = $conn->query($avg_sql);
if ($avg_result) {
    $avg_data = $avg_result->fetch_assoc();
    $avg_loan_time = round((float)$avg_data['avg']);
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laitetyypit | Admin | Kirjasto</title>
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
            position: relative;
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
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
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
            background: rgba(102,126,234,0.1);
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
            background: rgba(102,126,234,0.15);
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
            background: rgba(239,68,68,0.15);
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
            box-shadow: 0 10px 25px rgba(102,126,234,0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
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

        /* DEVICE TYPES GRID */
        .device-types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .device-type-card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }

        .device-type-card:hover {
            transform: translateY(-5px);
            border-color: #667eea;
            background: rgba(255,255,255,0.12);
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .device-type-card h3 {
            color: white;
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .card-body {
            padding: 20px;
        }

        .type-description {
            color: #94a3b8;
            line-height: 1.5;
            margin-bottom: 20px;
            font-size: 0.85rem;
            min-height: 60px;
        }

        /* Device Stats */
        .device-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
            padding: 12px;
            background: rgba(0,0,0,0.2);
            border-radius: 16px;
        }

        .device-stat {
            text-align: center;
            padding: 10px;
        }

        .device-stat .stat-number {
            font-size: 1.3rem;
            font-weight: 700;
            display: block;
            color: white;
        }

        .device-stat .stat-label {
            font-size: 0.7rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Loan Info */
        .loan-info {
            background: rgba(102,126,234,0.15);
            padding: 12px;
            border-radius: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .loan-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .loan-info div div:first-child {
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .loan-info div div:last-child {
            color: #94a3b8;
            font-size: 0.7rem;
        }

        /* Action Buttons */
        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .action-btn {
            flex: 1;
            padding: 10px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            font-size: 0.8rem;
            text-decoration: none;
        }

        .btn-edit {
            background: rgba(102,126,234,0.15);
            color: #a78bfa;
            border: 1px solid rgba(102,126,234,0.3);
        }

        .btn-delete {
            background: rgba(239,68,68,0.15);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,0.3);
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

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: rgba(15,25,35,0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
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
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.1);
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
            .device-types-grid {
                grid-template-columns: 1fr;
            }
            .card-actions {
                flex-direction: column;
            }
            .action-btn {
                width: 100%;
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
        <a href="admin_palautukset.php" class="menu-item"><i class="fas fa-undo-alt"></i><span>Hallinnoi Palautuksia</span></a>
        <a href="admin_myohassa_kirjat.php" class="menu-item"><i class="fas fa-clock"></i><span>Myöhässä Olevat</span></a>

        <div class="menu-section">🖥️ Laitehallinta</div>
        <a href="admin_laitetyypit.php" class="menu-item active"><i class="fas fa-laptop"></i><span>Laitetyypit</span></a>
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
            <h1><i class="fas fa-layer-group"></i> Laitetyypit</h1>
            <p><i class="fas fa-laptop"></i> Hallinnoi laitteiden tyyppejä ja laina-aikoja</p>
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

    <!-- STATISTICS CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Laitetyypit</h3>
                    <div class="stat-number"><?php echo number_format($total_types, 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Laitteita</h3>
                    <div class="stat-number"><?php echo number_format($total_devices, 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-laptop"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Keskim. laina-aika</h3>
                    <div class="stat-number"><?php echo $avg_loan_time; ?> pv</div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            </div>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div class="section-title">
            <i class="fas fa-search"></i> Hae ja hallitse laitetyyppejä
        </div>
        <div class="search-filter">
            <div class="form-group">
                <label class="form-label">Hae laitetyyppejä</label>
                <input type="text" id="searchInput" class="form-control"
                       placeholder="Etsi laitetyyppiä nimellä..." onkeyup="filterTypes()">
            </div>
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-primary" onclick="showModal()">
                        <i class="fas fa-plus"></i> Lisää uusi
                    </button>
                    <a href="admin_laitetyypit.php" class="btn btn-light">
                        <i class="fas fa-sync"></i> Päivitä
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- DEVICE TYPES GRID - THIS WILL SHOW ALL DEVICES -->
    <div class="device-types-grid" id="deviceTypesGrid">
        <?php if ($total_types > 0): ?>
            <?php 
            // Reset result pointer to beginning
            if ($result && $result->num_rows > 0) {
                $result->data_seek(0);
            }
            ?>
            
            <?php while ($tyyppi = $result->fetch_assoc()): 
                $device_name = $tyyppi['nimi'];
                $icon = getDeviceIcon($device_name);
                $color = getDeviceColor($device_name);
                
                // Get device counts for this type
                $device_count = 0;
                $available_count = 0;
                $borrowed_count = 0;

                if ($table_exists) {
                    $device_count_sql = "SELECT COUNT(*) as count FROM Laitteet WHERE laite_tyyppi_id = ?";
                    $device_stmt = $conn->prepare($device_count_sql);
                    $device_stmt->bind_param("i", $tyyppi['id']);
                    $device_stmt->execute();
                    $device_result = $device_stmt->get_result();
                    if ($device_result) {
                        $device_count = $device_result->fetch_assoc()['count'];
                    }
                    $device_stmt->close();

                    $available_sql = "SELECT COUNT(*) as count FROM Laitteet WHERE laite_tyyppi_id = ? AND tila = 'available'";
                    $available_stmt = $conn->prepare($available_sql);
                    $available_stmt->bind_param("i", $tyyppi['id']);
                    $available_stmt->execute();
                    $available_result = $available_stmt->get_result();
                    if ($available_result) {
                        $available_count = $available_result->fetch_assoc()['count'];
                    }
                    $available_stmt->close();

                    $borrowed_count = $device_count - $available_count;
                }
            ?>
            <div class="device-type-card" data-type-name="<?php echo strtolower($device_name); ?>">
                <div class="card-header">
                    <div class="card-icon" style="background: <?php echo $color; ?>22; color: <?php echo $color; ?>;">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($device_name); ?></h3>
                </div>

                <div class="card-body">
                    <p class="type-description">
                        <?php echo htmlspecialchars($tyyppi['kuvaus'] ?: 'Ei kuvausta saatavilla.'); ?>
                    </p>

                    <div class="device-stats">
                        <div class="device-stat">
                            <span class="stat-number"><?php echo $device_count; ?></span>
                            <span class="stat-label">Laitetta</span>
                        </div>
                        <div class="device-stat">
                            <span class="stat-number" style="color: #10b981;"><?php echo $available_count; ?></span>
                            <span class="stat-label">Vapaana</span>
                        </div>
                        <div class="device-stat">
                            <span class="stat-number" style="color: #f59e0b;"><?php echo $borrowed_count; ?></span>
                            <span class="stat-label">Lainassa</span>
                        </div>
                    </div>

                    <div class="loan-info">
                        <div class="loan-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div>
                            <div>Laina-aika: <?php echo $tyyppi['laina_aika']; ?> päivää</div>
                            <div>
                                <?php if ($tyyppi['laina_aika'] > 14): ?>
                                    Pitkä laina-aika
                                <?php elseif ($tyyppi['laina_aika'] > 7): ?>
                                    Normaali laina-aika
                                <?php else: ?>
                                    Lyhyt laina-aika
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-actions">
                        <a href="?edit=<?php echo $tyyppi['id']; ?>" class="action-btn btn-edit">
                            <i class="fas fa-edit"></i> Muokkaa
                        </a>
                        <form method="POST" style="display: inline; flex: 1;">
                            <input type="hidden" name="id" value="<?php echo $tyyppi['id']; ?>">
                            <button type="submit" name="delete_type" class="action-btn btn-delete"
                                    onclick="return confirm('Haluatko varmasti poistaa laitetyypin \"<?php echo addslashes($tyyppi['nimi']); ?>\"?')">
                                <i class="fas fa-trash"></i> Poista
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-layer-group"></i>
                <h3>Ei laitetyyppejä</h3>
                <p>Aloita lisäämällä ensimmäinen laitetyyppi.</p>
                <button class="btn btn-primary" onclick="showModal()" style="margin-top: 15px;">
                    <i class="fas fa-plus"></i> Lisää ensimmäinen laitetyyppi
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div id="typeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <?php if ($edit_mode): ?>
                    <i class="fas fa-edit"></i> Muokkaa Laitetyyppiä
                <?php else: ?>
                    <i class="fas fa-plus-circle"></i> Lisää Uusi Laitetyyppi
                <?php endif; ?>
            </div>
            <button class="close-modal" onclick="hideModal()">&times;</button>
        </div>

        <form method="POST" id="typeForm">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                <input type="hidden" name="update_type" value="1">
            <?php else: ?>
                <input type="hidden" name="add_type" value="1">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">Nimi *</label>
                <input type="text" class="form-control" id="nimi" name="nimi" required
                       value="<?php echo $edit_mode ? htmlspecialchars($edit_data['nimi']) : ''; ?>"
                       placeholder="Esimerkiksi: Kannettava tietokone">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">Kuvaus</label>
                <textarea class="form-control" id="kuvaus" name="kuvaus" rows="4"
                          placeholder="Kuvaile laitetyyppiä..."><?php echo $edit_mode ? htmlspecialchars($edit_data['kuvaus']) : ''; ?></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">Laina-aika (päiviä) *</label>
                <input type="number" class="form-control" id="laina_aika" name="laina_aika"
                       value="<?php echo $edit_mode ? $edit_data['laina_aika'] : '30'; ?>"
                       min="1" max="365" required>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px;">
                <button type="button" class="btn btn-light" onclick="hideModal()">
                    <i class="fas fa-times"></i> Peruuta
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> <?php echo $edit_mode ? 'Päivitä' : 'Tallenna'; ?>
                </button>
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

        document.querySelectorAll('.stat-card, .filter-section, .device-type-card').forEach(function(el) {
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

    // Modal functions
    function showModal() {
        document.getElementById('typeModal').style.display = 'flex';
    }

    function hideModal() {
        document.getElementById('typeModal').style.display = 'none';
        window.location.href = 'admin_laitetyypit.php';
    }

    <?php if ($edit_mode): ?>
    document.addEventListener('DOMContentLoaded', function() {
        showModal();
    });
    <?php endif; ?>

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('typeModal');
        if (event.target == modal) {
            hideModal();
        }
    }

    // Filter types by search
    function filterTypes() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const cards = document.querySelectorAll('.device-type-card');

        cards.forEach(card => {
            const typeName = card.getAttribute('data-type-name');
            if (typeName && typeName.includes(filter)) {
                card.style.display = 'block';
            } else if (typeName) {
                card.style.display = 'none';
            }
        });
    }

    // Form validation
    const typeForm = document.getElementById('typeForm');
    if (typeForm) {
        typeForm.addEventListener('submit', function(e) {
            const nimi = document.getElementById('nimi').value.trim();
            const laina_aika = document.getElementById('laina_aika').value;

            if (!nimi) {
                e.preventDefault();
                alert('Laitetyypin nimi on pakollinen!');
                document.getElementById('nimi').focus();
                return false;
            }

            if (laina_aika < 1 || laina_aika > 365) {
                e.preventDefault();
                alert('Laina-ajan tulee olla 1-365 päivää!');
                document.getElementById('laina_aika').focus();
                return false;
            }

            return true;
        });
    }
</script>

</body>
</html>
