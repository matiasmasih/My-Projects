<?php
session_start();
require_once 'connection.php';

// Check which session variable exists
if (isset($_SESSION['user_id'])) {
    // Using jasenet table for login
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT rooli, profile_image, etunimi, sukunimi, email FROM jasenet WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($rooli, $profile_image, $etunimi, $sukunimi, $email);
    $stmt->fetch();
    $stmt->close();
    $kayttajan_nimi = $etunimi . ' ' . $sukunimi;

} elseif (isset($_SESSION['kayttaja_id'])) {
    // Using kayttajat table for login
    $user_id = $_SESSION['kayttaja_id'];
    $stmt = $conn->prepare("SELECT rooli, email, etunimi, sukunimi, profile_image FROM kayttajat WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($rooli, $email, $etunimi, $sukunimi, $profile_image);
    $stmt->fetch();
    $stmt->close();
    $kayttajan_nimi = $etunimi ? $etunimi . ' ' . $sukunimi : $email;

} else {
    // Not logged in
    header("Location: login.php");
    exit();
}

// ============================================
// FIXED: Admin AND Manager can access permissions page
// ============================================
if ($rooli !== 'admin' && $rooli !== 'manager') {
    header("Location: " . (isset($_SESSION['user_id']) ? 'user_dashboard.php' : 'dashboard.php'));
    exit();
}

// Get user display info
$kayttajan_nimi = $etunimi . ' ' . $sukunimi;
$custom_name = $etunimi . ' ' . $sukunimi;
$custom_email = $email ?? 'email@example.com';
$custom_role_display = $rooli === 'admin' ? 'Ylläpitäjä' : 'Manager';
$custom_permissions = $rooli === 'admin' ? 'Täydet järjestelmäoikeudet' : 'Käyttöoikeuksien hallinta';

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

// Handle actions
$action = $_GET['action'] ?? '';
$user_id_to_update = $_GET['id'] ?? 0;
$message = '';
$message_type = '';

// Available roles
$available_roles = [
    'user' => 'Peruskäyttäjä',
    'manager' => 'Manager',
    'admin' => 'Ylläpitäjä'
];

// Available statuses
$available_statuses = [
    'aktiivinen' => 'Aktiivinen',
    'passiivinen' => 'Passiivinen'
];

// Handle UPDATE ROLE
if ($action === 'update_role' && isset($_POST['user_id'])) {
    $user_id_to_update = intval($_POST['user_id']);
    $new_role = $_POST['role'];

    // Prevent self-demotion
    $current_user_id = isset($_SESSION['kayttaja_id']) ? $_SESSION['kayttaja_id'] : $_SESSION['user_id'];
    if ($user_id_to_update == $current_user_id && $new_role !== 'admin') {
        $message = "Et voi muuttaa omaa rooliasi!";
        $message_type = "error";
    } else {
        $update_sql = "UPDATE kayttajat SET rooli = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $new_role, $user_id_to_update);

        if ($stmt->execute()) {
            $message = "Käyttäjän rooli päivitetty!";
            $message_type = "success";
        } else {
            $message = "Virhe roolin päivityksessä: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Handle UPDATE STATUS
if ($action === 'update_status' && isset($_POST['user_id'])) {
    $user_id_to_update = intval($_POST['user_id']);
    $new_status = $_POST['status'];

    // Prevent self-deactivation
    $current_user_id = isset($_SESSION['kayttaja_id']) ? $_SESSION['kayttaja_id'] : $_SESSION['user_id'];
    if ($user_id_to_update == $current_user_id && $new_status !== 'aktiivinen') {
        $message = "Et voi muuttaa omaa tilaa!";
        $message_type = "error";
    } else {
        $update_sql = "UPDATE kayttajat SET tila = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $new_status, $user_id_to_update);

        if ($stmt->execute()) {
            $message = "Käyttäjän tila päivitetty!";
            $message_type = "success";
        } else {
            $message = "Virhe tilan päivityksessä: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Handle RESET PASSWORD
if ($action === 'reset_password' && isset($_POST['user_id'])) {
    $user_id_to_update = intval($_POST['user_id']);
    $default_password = password_hash('salasana123', PASSWORD_DEFAULT);

    // Prevent self-password reset via this method
    $current_user_id = isset($_SESSION['kayttaja_id']) ? $_SESSION['kayttaja_id'] : $_SESSION['user_id'];
    if ($user_id_to_update == $current_user_id) {
        $message = "Käytä profiilisivua oman salasanan vaihtoon!";
        $message_type = "error";
    } else {
        $update_sql = "UPDATE kayttajat SET salasana = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $default_password, $user_id_to_update);

        if ($stmt->execute()) {
            $message = "Käyttäjän salasana nollattu! Oletussalasana: salasana123";
            $message_type = "success";
        } else {
            $message = "Virhe salasanan nollaamisessa: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Handle ADD NEW USER
if ($action === 'add_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $status = $_POST['status'];

    // Check if username exists
    $check_sql = "SELECT id FROM kayttajat WHERE kayttajanimi = ? OR email = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $message = "Käyttäjänimi tai sähköposti on jo käytössä!";
        $message_type = "error";
    } else {
        $default_password = password_hash('salasana123', PASSWORD_DEFAULT);
        $insert_sql = "INSERT INTO kayttajat (kayttajanimi, etunimi, sukunimi, salasana, rooli, email, tila) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt2 = $conn->prepare($insert_sql);
        $stmt2->bind_param("sssssss", $username, $username, $username, $default_password, $role, $email, $status);

        if ($stmt2->execute()) {
            $message = "Uusi käyttäjä lisätty! Oletussalasana: salasana123";
            $message_type = "success";
        } else {
            $message = "Virhe käyttäjän lisäämisessä: " . $conn->error;
            $message_type = "error";
        }
        $stmt2->close();
    }
    $stmt->close();
}

// Handle DELETE USER
if ($action === 'delete_user' && isset($_GET['id'])) {
    $user_id_to_delete = intval($_GET['id']);

    // Prevent self-deletion
    $current_user_id = isset($_SESSION['kayttaja_id']) ? $_SESSION['kayttaja_id'] : $_SESSION['user_id'];
    if ($user_id_to_delete == $current_user_id) {
        $message = "Et voi poistaa omaa käyttäjätiliäsi!";
        $message_type = "error";
    } else {
        $delete_sql = "DELETE FROM kayttajat WHERE id = ?";
        $stmt = $conn->prepare($delete_sql);
        $stmt->bind_param("i", $user_id_to_delete);

        if ($stmt->execute()) {
            $message = "Käyttäjä poistettu!";
            $message_type = "success";
        } else {
            $message = "Virhe käyttäjän poistamisessa: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Get all system users - CORRECTED VERSION
$users_sql = "
    SELECT
        k.id,
        k.kayttajanimi,
        k.etunimi,
        k.sukunimi,
        k.email,
        k.rooli,
        k.tila,
        k.profile_image,
        k.luotu
    FROM kayttajat k
    ORDER BY
        FIELD(k.rooli, 'admin', 'manager', 'user'),
        k.kayttajanimi
";
$users_result = $conn->query($users_sql);
$users = [];
if ($users_result) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Debug: Check if users exist
if (empty($users)) {
    // If no users, add current user as admin
    $check_admin = $conn->query("SELECT COUNT(*) as count FROM kayttajat WHERE rooli = 'admin'");
    $admin_exists = $check_admin->fetch_assoc()['count'] > 0;

    if (!$admin_exists) {
        // Add current user as admin
        $default_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_sql = "INSERT INTO kayttajat (kayttajanimi, etunimi, sukunimi, salasana, rooli, email, tila)
                       VALUES (?, ?, ?, ?, 'admin', ?, 'aktiivinen')";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sssss", $kayttajan_nimi, $etunimi, $sukunimi, $default_password, $email);
        $stmt->execute();
        $stmt->close();

        // Refresh users list
        $users_result = $conn->query($users_sql);
        $users = [];
        while ($row = $users_result->fetch_assoc()) {
            $users[] = $row;
        }
    }
}

// Count users by role
$role_counts = [
    'admin' => 0,
    'manager' => 0,
    'user' => 0,
    'total' => count($users)
];

foreach ($users as $user) {
    if (isset($role_counts[$user['rooli']])) {
        $role_counts[$user['rooli']]++;
    }
}

// Get user activity stats
$activity_sql = "
    SELECT
        rooli,
        COUNT(*) as count,
        SUM(CASE WHEN tila = 'aktiivinen' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN tila = 'passiivinen' THEN 1 ELSE 0 END) as inactive
    FROM kayttajat
    GROUP BY rooli
";

$activity_result = $conn->query($activity_sql);
$activity_stats = [];
if ($activity_result) {
    while ($row = $activity_result->fetch_assoc()) {
        $activity_stats[$row['rooli']] = $row;
    }
}

// Calculate active and inactive totals
$active_total = 0;
$inactive_total = 0;
foreach ($activity_stats as $stat) {
    $active_total += $stat['active'] ?? 0;
    $inactive_total += $stat['inactive'] ?? 0;
}

// Set timezone for date display
date_default_timezone_set('Europe/Helsinki');
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjasto - Käyttöoikeudet</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            --card-bg: rgba(255, 255, 255, 0.95);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background:
                linear-gradient(rgba(26, 26, 46, 0.85), rgba(26, 26, 46, 0.85)),
                url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 19%;
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
            padding: 15px 25px;
            width: calc(100% - var(--sidebar-width));
        }

        /* Top section */
        .top-section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 15px;
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
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--info), #2980B9);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3em;
        }

        .page-title {
            font-size: 1.6em;
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
            padding: 15px 20px;
            border-radius: 15px;
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
            font-size: 1.05em;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 3px;
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
            margin-bottom: 3px;
        }

        .profile-permissions {
            color: var(--success);
            font-size: 0.8em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-top: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.12);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-info h3 {
            font-size: 0.85em;
            color: #718096;
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

        /* Stat card colors */
        .stat-card:nth-child(1) { border-color: var(--info); }
        .stat-card:nth-child(2) { border-color: var(--success); }
        .stat-card:nth-child(3) { border-color: var(--purple); }
        .stat-card:nth-child(4) { border-color: var(--warning); }
        .stat-card:nth-child(5) { border-color: var(--danger); }
        .stat-card:nth-child(6) { border-color: var(--secondary); }

        .stat-card:nth-child(1) .stat-icon { background: var(--info); }
        .stat-card:nth-child(2) .stat-icon { background: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: var(--purple); }
        .stat-card:nth-child(4) .stat-icon { background: var(--warning); }
        .stat-card:nth-child(5) .stat-icon { background: var(--danger); }
        .stat-card:nth-child(6) .stat-icon { background: var(--secondary); }

        /* QUICK ACTIONS */
        .actions-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .action-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 2px solid transparent;
            cursor: pointer;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
            border-color: var(--info);
        }

        .action-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            color: white;
        }

        .action-icon.add {
            background: linear-gradient(135deg, var(--success), #219653);
        }

        .action-icon.roles {
            background: linear-gradient(135deg, var(--info), #2980B9);
        }

        .action-icon.security {
            background: linear-gradient(135deg, var(--purple), #8E44AD);
        }

        .action-title {
            font-size: 1.2em;
            font-weight: 600;
            color: var(--primary);
        }

        .action-description {
            color: #666;
            font-size: 0.9em;
            line-height: 1.5;
        }

        /* TABLE SECTION */
        .table-section {
            background: var(--card-bg);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            margin-top: 25px;
        }

        .table-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.05));
            border-bottom: 2px solid #e8e8e8;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            max-height: 500px;
            overflow-y: auto;
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
        }

        th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 2px solid #e8e8e8;
            font-size: 0.9em;
        }

        td {
            padding: 12px 20px;
            border-bottom: 1px solid #e8e8e8;
            color: var(--primary);
        }

        tbody tr:hover {
            background: rgba(52, 152, 219, 0.05);
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-aktiivinen {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .status-passiivinen {
            background: rgba(231, 76, 60, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        /* Role Badges */
        .role-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .role-admin {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
            border: 1px solid rgba(52, 152, 219, 0.2);
        }

        .role-manager {
            background: rgba(155, 89, 182, 0.1);
            color: var(--purple);
            border: 1px solid rgba(155, 89, 182, 0.2);
        }

        .role-user {
            background: rgba(52, 73, 94, 0.1);
            color: var(--primary);
            border: 1px solid rgba(52, 73, 94, 0.2);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
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
            font-size: 0.9em;
            text-decoration: none;
        }

        .action-btn.edit {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
            border: 1px solid rgba(52, 152, 219, 0.2);
        }

        .action-btn.edit:hover {
            background: linear-gradient(135deg, var(--info), #2980B9);
            color: white;
            transform: translateY(-2px);
        }

        .action-btn.reset {
            background: rgba(241, 196, 15, 0.1);
            color: var(--warning);
            border: 1px solid rgba(241, 196, 15, 0.2);
        }

        .action-btn.reset:hover {
            background: linear-gradient(135deg, var(--warning), #E67E22);
            color: white;
            transform: translateY(-2px);
        }

        .action-btn.delete {
            background: rgba(231, 76, 60, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .action-btn.delete:hover {
            background: linear-gradient(135deg, var(--secondary), #C0392B);
            color: white;
            transform: translateY(-2px);
        }

        /* ===== FIXED MODERN MODAL STYLES WITH SCROLL ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            animation: modalFadeIn 0.3s ease;
            overflow-y: auto; /* Salli scrollaus koko modaalille */
        }

        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 2% auto; /* Pienempi marginaali ylhäältä */
            padding: 25px;
            border-radius: 20px;
            width: 90%;
            max-width: 550px;
            max-height: 90vh; /* Maksimikorkeus */
            overflow-y: auto; /* Salli scrollaus sisällössä */
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            animation: modalSlideUp 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
        }

        /* Scrollbar styling */
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: var(--info);
            border-radius: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #2980B9;
        }

        @keyframes modalSlideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--info);
            padding-bottom: 15px;
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .modal-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.4em;
            color: var(--primary);
            font-weight: 600;
        }

        .modal-title i {
            color: var(--info);
            font-size: 1.2em;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 2em;
            color: #999;
            cursor: pointer;
            transition: all 0.3s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .close-modal:hover {
            color: var(--secondary);
            background: rgba(231, 76, 60, 0.1);
            transform: rotate(90deg);
        }

        /* MODERN FORM STYLES */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.95em;
        }

        .form-label i {
            color: var(--info);
            width: 20px;
        }

        .required-star {
            color: var(--secondary);
            margin-left: 4px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            z-index: 1;
            transition: color 0.3s;
        }

        .form-control {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 0.95em;
            transition: all 0.3s;
            background: #fafafa;
        }

        .form-control:hover {
            border-color: #d0d0d0;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--info);
            background: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-control:focus + .input-icon {
            color: var(--info);
        }

        select.form-control {
            padding: 14px 15px;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233498DB' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 1.2em;
            padding-right: 45px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-hint {
            margin-top: 5px;
            font-size: 0.8em;
            color: #999;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-hint i {
            color: var(--info);
            font-size: 0.9em;
        }

        .form-error {
            color: var(--secondary);
            font-size: 0.85em;
            margin-top: 5px;
            display: none;
        }

        .form-error.show {
            display: block;
            animation: shake 0.3s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Info Card */
        .info-card {
            background: linear-gradient(135deg, #f0f9ff 0%, #e6f3ff 100%);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            border-left: 4px solid var(--info);
        }

        .password-badge {
            background: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-weight: 600;
            color: var(--info);
            border: 1px solid rgba(52, 152, 219, 0.3);
            font-size: 0.95em;
            font-family: monospace;
        }

        /* Preview Section */
        .preview-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            border: 1px solid #e8e8e8;
        }

        .preview-title {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            font-size: 0.9em;
        }

        .preview-item {
            padding: 8px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
        }

        .preview-label {
            color: #718096;
            font-size: 0.85em;
            margin-bottom: 4px;
        }

        .preview-value {
            font-weight: 600;
            color: var(--primary);
        }

        /* ===== FIXED BUTTON STYLES ===== */
        .btn {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95em;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            white-space: nowrap;
            line-height: 1.5;
            min-width: 120px;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #219653) !important;
            color: white !important;
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.25);
            border: none !important;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(39, 174, 96, 0.35);
        }

        .btn-success i {
            color: white;
        }

        .btn-secondary {
            background: white !important;
            color: #4a5568 !important;
            border: 2px solid #e8e8e8 !important;
        }

        .btn-secondary:hover {
            background: #f7fafc !important;
            transform: translateY(-2px);
            border-color: #d0d0d0 !important;
        }

        .btn-secondary i {
            color: #4a5568;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info), #2980B9) !important;
            color: white !important;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--secondary), #C0392B) !important;
            color: white !important;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #E67E22) !important;
            color: white !important;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(241, 196, 15, 0.3);
        }

        /* MODAL ACTION BUTTONS - KIINNITETÄÄN ALAREUNAAN */
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            border-top: 2px solid #e8e8e8;
            padding-top: 20px;
            margin-top: 20px;
            position: sticky;
            bottom: 0;
            background: white;
            z-index: 10;
            padding-bottom: 5px;
        }

        /* Varmistetaan että painikkeet näkyvät */
        .modal-content .btn,
        .modal-content button {
            display: inline-flex !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: var(--info);
            opacity: 0.8;
        }

        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: var(--primary);
            font-weight: 600;
        }

        .empty-state p {
            max-width: 500px;
            margin: 0 auto 20px;
            font-size: 1.1em;
            line-height: 1.5;
            color: #666;
        }

        /* Notification */
        .notification {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
            background: white;
            border: 2px solid;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            font-size: 0.9em;
        }

        .notification-success {
            border-color: var(--success);
            color: var(--success);
            background: rgba(39, 174, 96, 0.05);
        }

        .notification-error {
            border-color: var(--secondary);
            color: var(--secondary);
            background: rgba(231, 76, 60, 0.05);
        }

        @keyframes slideIn {
            from {
                transform: translateY(-15px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Text utilities */
        .text-muted {
            color: #a0aec0;
        }

        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            background: #edf2f7;
            color: #4a5568;
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
                padding: 15px;
            }
            .top-section {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .profile-section {
                width: 100%;
                justify-content: center;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .actions-section {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .preview-grid {
                grid-template-columns: 1fr;
            }
            .modal-actions {
                flex-direction: column;
            }
            .modal-actions .btn {
                width: 100%;
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
            <a href="admin_kayttooikeudet.php" class="menu-item active">
                <span>⚙️ Järjestelmäasetukset</span>
            </a>
            <a href="admin_palvelin_lokit.php" class="menu-item">
                <span>📋 Palvelinlokit</span>
            </a>
            <a href="logout.php" class="menu-item logout-item">
                <span>🚪 Kirjaudu Ulos</span>
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- TOP SECTION WITH TITLE AND PROFILE -->
        <div class="top-section">
            <div class="page-header">
                <div class="title-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="page-title">Käyttöoikeudet</h1>
            </div>

            <div class="profile-section">
                <div class="profile-avatar">
                    <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo htmlspecialchars($custom_name); ?></div>
                    <div class="profile-email">
                        <i class="fas fa-envelope"></i>
                        <?php echo htmlspecialchars($custom_email); ?>
                    </div>
                    <div class="profile-role">
                        <i class="fas fa-user-shield"></i>
                        <?php echo $custom_role_display; ?>
                    </div>
                    <div class="profile-permissions">
                        <i class="fas fa-key"></i>
                        <?php echo $custom_permissions; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="notification notification-<?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>KÄYTTÄJÄT</h3>
                        <div class="stat-number"><?php echo $role_counts['total']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>YLLÄPITÄJÄT</h3>
                        <div class="stat-number"><?php echo $role_counts['admin']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>MANAGERIT</h3>
                        <div class="stat-number"><?php echo $role_counts['manager']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-tie"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>KÄYTTÄJÄT</h3>
                        <div class="stat-number"><?php echo $role_counts['user']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>AKTIIVISET</h3>
                        <div class="stat-number">
                            <?php 
                            $active_total = 0;
                            foreach ($activity_stats as $stat) {
                                $active_total += $stat['active'] ?? 0;
                            }
                            echo $active_total;
                            ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>PASSIIVISET</h3>
                        <div class="stat-number">
                            <?php 
                            $inactive_total = 0;
                            foreach ($activity_stats as $stat) {
                                $inactive_total += $stat['inactive'] ?? 0;
                            }
                            echo $inactive_total;
                            ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="actions-section">
            <div class="action-card" onclick="showAddUserModal()">
                <div class="action-header">
                    <div class="action-icon add">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="action-title">Lisää uusi käyttäjä</div>
                </div>
                <div class="action-description">
                    Luo uusi käyttäjätili järjestelmään. Oletussalasanana on "salasana123".
                </div>
            </div>

            <div class="action-card" onclick="showRoleInfo()">
                <div class="action-header">
                    <div class="action-icon roles">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="action-title">Käyttäjäroolit</div>
                </div>
                <div class="action-description">
                    Ylläpitäjä: täydet oikeudet<br>
                    Manager: hallinnointioikeudet<br>
                    Käyttäjä: perusoikeudet
                </div>
            </div>

            <div class="action-card" onclick="showSecurityInfo()">
                <div class="action-header">
                    <div class="action-icon security">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="action-title">Turvallisuus</div>
                </div>
                <div class="action-description">
                    Salasanat suojattu bcryptillä. Oletussalasana "salasana123".
                </div>
            </div>
        </div>

        <!-- USERS TABLE -->
        <div class="table-section">
            <div class="table-header">
                <h3>
                    <i class="fas fa-users" style="color: var(--info);"></i>
                    Järjestelmän käyttäjät (<?php echo count($users); ?>)
                </h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Käyttäjätunnus</th>
                            <th>Nimi</th>
                            <th>Sähköposti</th>
                            <th>Rooli</th>
                            <th>Tila</th>
                            <th>Luotu</th>
                            <th>Toiminnot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><strong>#<?php echo str_pad($user['id'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['kayttajanimi']); ?></td>
                                    <td>
                                        <?php if ($user['etunimi'] || $user['sukunimi']): ?>
                                            <?php echo htmlspecialchars($user['etunimi'] . ' ' . $user['sukunimi']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php
                                        $role_class = '';
                                        $role_text = '';
                                        if ($user['rooli'] === 'admin') {
                                            $role_class = 'role-admin';
                                            $role_text = 'Ylläpitäjä';
                                        } elseif ($user['rooli'] === 'manager') {
                                            $role_class = 'role-manager';
                                            $role_text = 'Manager';
                                        } else {
                                            $role_class = 'role-user';
                                            $role_text = 'Käyttäjä';
                                        }
                                        ?>
                                        <span class="role-badge <?php echo $role_class; ?>">
                                            <?php echo $role_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['tila'] === 'aktiivinen'): ?>
                                            <span class="status-badge status-aktiivinen">
                                                <i class="fas fa-check-circle"></i> Aktiivinen
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-passiivinen">
                                                <i class="fas fa-clock"></i> Passiivinen
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d.m.Y', strtotime($user['luotu'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- Edit Role Form -->
                                            <form method="POST" action="?action=update_role" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <select name="role" onchange="this.form.submit()" class="action-btn edit" style="width: auto; padding: 0 8px;">
                                                    <option value="user" <?php echo $user['rooli'] === 'user' ? 'selected' : ''; ?>>Käyttäjä</option>
                                                    <option value="manager" <?php echo $user['rooli'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                                    <option value="admin" <?php echo $user['rooli'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                            </form>

                                            <!-- Edit Status Form -->
                                            <form method="POST" action="?action=update_status" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <select name="status" onchange="this.form.submit()" class="action-btn reset" style="width: auto; padding: 0 8px;">
                                                    <option value="aktiivinen" <?php echo $user['tila'] === 'aktiivinen' ? 'selected' : ''; ?>>Aktiivinen</option>
                                                    <option value="passiivinen" <?php echo $user['tila'] === 'passiivinen' ? 'selected' : ''; ?>>Passiivinen</option>
                                                </select>
                                            </form>

                                            <!-- Reset Password -->
                                            <form method="POST" action="?action=reset_password" style="display: inline;" 
                                                  onsubmit="return confirm('Nollataanko käyttäjän salasana? Uusi salasana: salasana123')">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="action-btn reset" title="Nollaa salasana">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            </form>

                                            <!-- Delete User (only if not current user) -->
                                            <?php 
                                            $current_user_id = isset($_SESSION['kayttaja_id']) ? $_SESSION['kayttaja_id'] : $_SESSION['user_id'];
                                            if ($user['id'] != $current_user_id): 
                                            ?>
                                            <a href="?action=delete_user&id=<?php echo $user['id']; ?>" 
                                               class="action-btn delete" 
                                               title="Poista käyttäjä"
                                               onclick="return confirm('Haluatko varmasti poistaa tämän käyttäjän?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h3>Ei käyttäjiä</h3>
                                    <p>Järjestelmässä ei ole vielä käyttäjiä.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODERN ADD USER MODAL -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-user-plus"></i>
                    Lisää uusi käyttäjä
                </div>
                <button class="close-modal" onclick="hideAddUserModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST" action="?action=add_user" id="addUserForm" onsubmit="return validateAddUserForm()">
                <!-- Käyttäjätunnus -->
                <div class="form-group">
                    <label class="form-label" for="username">
                        <i class="fas fa-user"></i>
                        Käyttäjätunnus <span class="required-star">*</span>
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username" 
                               required 
                               placeholder="esim. jvirtanen"
                               minlength="3"
                               pattern="[a-zA-Z0-9]+"
                               title="Vain kirjaimia ja numeroita, vähintään 3 merkkiä"
                               oninput="validateUsername(this)">
                    </div>
                    <div class="form-hint">
                        <i class="fas fa-info-circle"></i>
                        Vähintään 3 merkkiä, vain kirjaimia ja numeroita
                    </div>
                    <div class="form-error" id="usernameError"></div>
                </div>

                <!-- Sähköposti -->
                <div class="form-group">
                    <label class="form-label" for="email">
                        <i class="fas fa-envelope"></i>
                        Sähköposti <span class="required-star">*</span>
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" 
                               class="form-control" 
                               id="email" 
                               name="email" 
                               required 
                               placeholder="etunimi.sukunimi@esimerkki.fi"
                               oninput="validateEmail(this)">
                    </div>
                    <div class="form-error" id="emailError"></div>
                </div>

                <!-- Rooli ja Tila grid -->
                <div class="form-row">
                    <!-- Rooli -->
                    <div class="form-group">
                        <label class="form-label" for="role">
                            <i class="fas fa-tag"></i>
                            Rooli <span class="required-star">*</span>
                        </label>
                        <div class="input-wrapper">
                            <select class="form-control" id="role" name="role" required onchange="updatePreview()">
                                <option value="user">👤 Peruskäyttäjä</option>
                                <option value="manager">👔 Manager</option>
                                <option value="admin">👑 Ylläpitäjä</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tila -->
                    <div class="form-group">
                        <label class="form-label" for="status">
                            <i class="fas fa-circle"></i>
                            Tila <span class="required-star">*</span>
                        </label>
                        <div class="input-wrapper">
                            <select class="form-control" id="status" name="status" required onchange="updatePreview()">
                                <option value="aktiivinen">🟢 Aktiivinen</option>
                                <option value="passiivinen">🔴 Passiivinen</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Password Info Card -->
                <div class="info-card">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                        <div style="width: 36px; height: 36px; background: var(--info); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="fas fa-key"></i>
                        </div>
                        <span style="font-weight: 600; color: var(--primary); font-size: 1.1em;">Oletussalasana</span>
                    </div>
                    <p style="margin: 0 0 0 48px; color: #2C3E50; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span>Käyttäjä luodaan salasanalla:</span>
                        <span class="password-badge">salasana123</span>
                    </p>
                    <p style="margin: 10px 0 0 48px; color: #666; font-size: 0.9em;">
                        <i class="fas fa-info-circle" style="color: var(--info);"></i>
                        Käyttäjä voi vaihtaa salasanan ensimmäisen kirjautumisen jälkeen
                    </p>
                </div>

                <!-- Live Preview -->
                <div class="preview-section">
                    <div class="preview-title">
                        <i class="fas fa-eye" style="color: var(--info);"></i>
                        Esikatselu
                    </div>
                    <div class="preview-grid" id="formPreview">
                        <!-- Täytetään JavaScriptillä -->
                    </div>
                </div>

                <!-- Action Buttons - KORJATTU INLINE-STYLEILLÄ -->
             <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #e8e8e8; padding-top: 25px; margin-top: 10px;">
               <button type="button" onclick="hideAddUserModal()" style="padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s; background: white; color: #4a5568; border: 2px solid #e8e8e8; display: inline-flex; align-items: center; gap: 8px; font-size: 0.95em;">
                    <i class="fas fa-times"></i>
                    Peruuta
               </button>
               <button type="submit" style="padding: 12px 28px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s; background: linear-gradient(135deg, #27AE60, #219653); color: white; border: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(39, 174, 96, 0.25); font-size: 0.95em;">
                  <i class="fas fa-save"></i>
                  Tallenna käyttäjä
               </button>
             </div>
            </form>
        </div>
    </div>

    <script>
        // ==================== MODAL FUNCTIONS ====================
        function showAddUserModal() {
            const modal = document.getElementById('addUserModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling

            // Focus first input
            setTimeout(() => {
                document.getElementById('username').focus();
            }, 300);

            // Update preview
            updatePreview();
        }

        function hideAddUserModal() {
            const modal = document.getElementById('addUserModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Allow background scrolling

            // Clear form
            document.getElementById('username').value = '';
            document.getElementById('email').value = '';
            document.getElementById('role').value = 'user';
            document.getElementById('status').value = 'aktiivinen';

            // Clear errors
            document.querySelectorAll('.form-error').forEach(el => {
                el.classList.remove('show');
                el.textContent = '';
            });
        }

        // ==================== VALIDATION FUNCTIONS ====================
        function validateUsername(input) {
            const errorDiv = document.getElementById('usernameError');
            const username = input.value.trim();

            if (username.length < 3) {
                errorDiv.textContent = 'Käyttäjätunnuksen tulee olla vähintään 3 merkkiä pitkä!';
                errorDiv.classList.add('show');
                return false;
            }

            const usernameRegex = /^[a-zA-Z0-9]+$/;
            if (!usernameRegex.test(username)) {
                errorDiv.textContent = 'Käyttäjätunnus voi sisältää vain kirjaimia ja numeroita!';
                errorDiv.classList.add('show');
                return false;
            }

            errorDiv.classList.remove('show');
            errorDiv.textContent = '';
            return true;
        }

        function validateEmail(input) {
            const errorDiv = document.getElementById('emailError');
            const email = input.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!emailRegex.test(email)) {
                errorDiv.textContent = 'Syötä kelvollinen sähköpostiosoite!';
                errorDiv.classList.add('show');
                return false;
            }

            errorDiv.classList.remove('show');
            errorDiv.textContent = '';
            return true;
        }

        function validateAddUserForm() {
            const username = document.getElementById('username');
            const email = document.getElementById('email');

            const isUsernameValid = validateUsername(username);
            const isEmailValid = validateEmail(email);

            if (!isUsernameValid || !isEmailValid) {
                return false;
            }

            return confirm('Haluatko varmasti lisätä uuden käyttäjän?');
        }

        // ==================== LIVE PREVIEW ====================
        function updatePreview() {
            const username = document.getElementById('username').value || 'ei asetettu';
            const email = document.getElementById('email').value || 'ei asetettu';
            const role = document.getElementById('role').value;
            const status = document.getElementById('status').value;

            let roleText = '';
            let roleIcon = '';
            switch(role) {
                case 'admin': 
                    roleText = 'Ylläpitäjä'; 
                    roleIcon = '👑';
                    break;
                case 'manager': 
                    roleText = 'Manager'; 
                    roleIcon = '👔';
                    break;
                default: 
                    roleText = 'Peruskäyttäjä'; 
                    roleIcon = '👤';
            }

            let statusText = status === 'aktiivinen' ? 'Aktiivinen' : 'Passiivinen';
            let statusColor = status === 'aktiivinen' ? '#27AE60' : '#E74C3C';
            let statusIcon = status === 'aktiivinen' ? '🟢' : '🔴';

            const previewHtml = `
                <div class="preview-item">
                    <div class="preview-label">Käyttäjätunnus</div>
                    <div class="preview-value">${username}</div>
                </div>
                <div class="preview-item">
                    <div class="preview-label">Sähköposti</div>
                    <div class="preview-value">${email}</div>
                </div>
                <div class="preview-item">
                    <div class="preview-label">Rooli</div>
                    <div class="preview-value">${roleIcon} ${roleText}</div>
                </div>
                <div class="preview-item">
                    <div class="preview-label">Tila</div>
                    <div class="preview-value" style="color: ${statusColor};">${statusIcon} ${statusText}</div>
                </div>
            `;

            document.getElementById('formPreview').innerHTML = previewHtml;
        }

        // ==================== INFO FUNCTIONS ====================
        function showRoleInfo() {
            alert('👑 Käyttäjäroolit:\n\n' +
                  '👑 Ylläpitäjä (Admin) - Täydet järjestelmäoikeudet\n' +
                  '👔 Manager - Hallinnointioikeudet\n' +
                  '👤 Käyttäjä (User) - Perusoikeudet');
        }

        function showSecurityInfo() {
            alert('🔒 Turvallisuus:\n\n' +
                  '- Salasanat suojattu bcryptillä\n' +
                  '- Oletussalasana: salasana123\n' +
                  '- Käyttäjät voivat vaihtaa salasanan profiilisivulla\n' +
                  '- Kirjautumisyritykset lokitetaan');
        }

        // ==================== EVENT LISTENERS ====================
        document.addEventListener('DOMContentLoaded', function() {
            // Add input listeners for live preview
            const usernameInput = document.getElementById('username');
            const emailInput = document.getElementById('email');
            const roleSelect = document.getElementById('role');
            const statusSelect = document.getElementById('status');

            if (usernameInput) {
                usernameInput.addEventListener('input', function() {
                    validateUsername(this);
                    updatePreview();
                });
            }

            if (emailInput) {
                emailInput.addEventListener('input', function() {
                    validateEmail(this);
                    updatePreview();
                });
            }

            if (roleSelect) {
                roleSelect.addEventListener('change', updatePreview);
            }

            if (statusSelect) {
                statusSelect.addEventListener('change', updatePreview);
            }

            // Initial preview
            updatePreview();
        });

        // ==================== CLICK OUTSIDE MODAL ====================
        window.onclick = function(event) {
            const modal = document.getElementById('addUserModal');
            if (event.target == modal) {
                hideAddUserModal();
            }
        }

        // ==================== AUTO-HIDE NOTIFICATIONS ====================
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-15px)';
                notification.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 300);
            });
        }, 5000);

        // ==================== KEYBOARD SHORTCUTS ====================
        document.addEventListener('keydown', function(e) {
            // ESC key closes modal
            if (e.key === 'Escape') {
                const modal = document.getElementById('addUserModal');
                if (modal.style.display === 'block') {
                    hideAddUserModal();
                }
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
