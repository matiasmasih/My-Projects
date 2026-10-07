<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check user role
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT rooli, profile_image, etunimi, sukunimi, email FROM jasenet WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($rooli, $profile_image, $etunimi, $sukunimi, $email);
$stmt->fetch();
$stmt->close();

// Admin and Manager can access admin dashboard
if ($rooli != 'admin' && $rooli != 'manager') {
    header("Location: user_dashboard.php");
    exit();
}

$kayttajan_nimi = $etunimi . ' ' . $sukunimi;
$custom_name = $etunimi . ' ' . $sukunimi;
$custom_email = $email ? $email : "admin@example.com";
$custom_role_display = $rooli === 'admin' ? "Ylläpitäjä" : ucfirst($rooli);
$custom_permissions = $rooli === 'admin' ? "Täydet järjestelmäoikeudet" : "Rajoitetut oikeudet";

// Profile image handling (keep your existing code)
$profile_success = '';
$profile_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['avatar_url']) && !empty($_POST['avatar_url'])) {
        $avatar_url = trim($_POST['avatar_url']);
        $update_sql = "UPDATE jasenet SET profile_image = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        if ($update_stmt) {
            $update_stmt->bind_param("si", $avatar_url, $user_id);
            if ($update_stmt->execute()) {
                $_SESSION['profile_image'] = $avatar_url;
                $profile_success = 'Profiilikuva päivitetty onnistuneesti!';
                $profile_image = $avatar_url;
                $_SESSION['profile_success'] = $profile_success;
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
            $update_stmt->close();
        }
    }

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/profiles/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
        $filepath = $upload_dir . $filename;
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['profile_image']['type'];
        if (in_array($file_type, $allowed_types) && $_FILES['profile_image']['size'] <= 5242880 && move_uploaded_file($_FILES['profile_image']['tmp_name'], $filepath)) {
            $update_sql = "UPDATE jasenet SET profile_image = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param("si", $filepath, $user_id);
                if ($update_stmt->execute()) {
                    $_SESSION['profile_image'] = $filepath;
                    $profile_success = 'Profiilikuva päivitetty onnistuneesti!';
                    $profile_image = $filepath;
                    $_SESSION['profile_success'] = $profile_success;
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
                $update_stmt->close();
            }
        }
    }
}

if (isset($_SESSION['profile_success'])) {
    $profile_success = $_SESSION['profile_success'];
    unset($_SESSION['profile_success']);
}
if (isset($_SESSION['profile_error'])) {
    $profile_error = $_SESSION['profile_error'];
    unset($_SESSION['profile_error']);
}
if (isset($_SESSION['profile_image'])) {
    $profile_image = $_SESSION['profile_image'];
}

function getProfileImageUrl($profile_image, $user_name) {
    if (empty($profile_image)) {
        return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
    }
    if (filter_var($profile_image, FILTER_VALIDATE_URL)) {
        return $profile_image;
    }
    $possible_paths = [$profile_image, 'uploads/profiles/' . $profile_image, 'uploads/profiles/' . basename($profile_image), basename($profile_image)];
    foreach ($possible_paths as $path) {
        if (file_exists($path) && is_file($path)) {
            return $path;
        }
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
}

$profile_image_url = getProfileImageUrl($profile_image ?? '', $kayttajan_nimi);

// Handle message actions
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_message') {
        $vastaanottaja_id = intval($_POST['vastaanottaja_id']);
        $viesti = trim($_POST['viesti']);
        if (!empty($viesti) && $vastaanottaja_id > 0) {
            $sql = "INSERT INTO viestit (lahettaja_id, vastaanottaja_id, viesti) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $user_id, $vastaanottaja_id, $viesti);
            if ($stmt->execute()) {
                $message = "✅ Viesti lähetetty onnistuneesti!";
                $message_type = "success";
            } else {
                $message = "❌ Virhe viestin lähetyksessä";
                $message_type = "error";
            }
            $stmt->close();
        }
    }
    if ($_POST['action'] === 'send_announcement') {
        $viesti = trim($_POST['ilmoitus_viesti']);
        if (!empty($viesti)) {
            $sql = "INSERT INTO ryhmaviestit (lahettaja_id, viesti, on_ilmoitus) VALUES (?, ?, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $user_id, $viesti);
            if ($stmt->execute()) {
                $message = "📢 Ilmoitus lähetetty kaikille jäsenille!";
                $message_type = "success";
            } else {
                $message = "❌ Virhe ilmoituksen lähetyksessä";
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

// Get statistics
$kirjoja_yhteensa = $conn->query("SELECT COUNT(*) as maara FROM kirjat")->fetch_assoc()['maara'] ?? 0;
$jasenia_yhteensa = $conn->query("SELECT COUNT(*) as maara FROM jasenet WHERE tila = 'aktiivinen'")->fetch_assoc()['maara'] ?? 0;
$aktiivisia_lainoja = $conn->query("SELECT COUNT(*) as maara FROM lainat WHERE tila = 'aktiivinen'")->fetch_assoc()['maara'] ?? 0;
$myohassa_lainoja = $conn->query("SELECT COUNT(*) as maara FROM lainat WHERE tila = 'aktiivinen' AND erapaiva < CURDATE()")->fetch_assoc()['maara'] ?? 0;
$sakkoja_yhteensa = $conn->query("SELECT SUM(sakko_maara - maksettu_maara) as maara FROM sakot WHERE tila != 'maksettu'")->fetch_assoc()['maara'] ?? 0;
$laitteita_yhteensa = $conn->query("SELECT COUNT(*) as maara FROM Laitteet")->fetch_assoc()['maara'] ?? 0;
$saatavilla_laitteita = $conn->query("SELECT COUNT(*) as maara FROM Laitteet WHERE tila = 'saatavilla'")->fetch_assoc()['maara'] ?? 0;

// Get message stats
$lukemattomia_viesteja = 0;
$lahetettyja_viesteja = 0;
$ilmoituksia = 0;
$members = [];
$recent_messages = [];
$recent_announcements = [];

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as maara FROM viestit WHERE vastaanottaja_id = ? AND luettu = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $lukemattomia_viesteja = $stmt->get_result()->fetch_assoc()['maara'] ?? 0;
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as maara FROM viestit WHERE lahettaja_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $lahetettyja_viesteja = $stmt->get_result()->fetch_assoc()['maara'] ?? 0;
    $stmt->close();

    $ilmoituksia = $conn->query("SELECT COUNT(*) as maara FROM ryhmaviestit WHERE on_ilmoitus = 1")->fetch_assoc()['maara'] ?? 0;

    $stmt = $conn->prepare("SELECT id, etunimi, sukunimi, email, rooli FROM jasenet WHERE tila = 'aktiivinen' AND id != ? ORDER BY sukunimi");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $sql = "SELECT v.*, l.etunimi as lahettaja_etunimi, l.sukunimi as lahettaja_sukunimi, vl.etunimi as vastaanottaja_etunimi, vl.sukunimi as vastaanottaja_sukunimi FROM viestit v LEFT JOIN jasenet l ON v.lahettaja_id = l.id LEFT JOIN jasenet vl ON v.vastaanottaja_id = vl.id WHERE v.vastaanottaja_id = ? OR v.lahettaja_id = ? ORDER BY v.luontiaika DESC LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $recent_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $recent_announcements = $conn->query("SELECT r.*, j.etunimi, j.sukunimi FROM ryhmaviestit r LEFT JOIN jasenet j ON r.lahettaja_id = j.id WHERE r.on_ilmoitus = 1 ORDER BY r.luontiaika DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Kirjasto</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    /* ============================================
       MODERN ADMIN DASHBOARD CSS
       Library Theme with Background Image
       ============================================ */

    /* RESET & BASE STYLES */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        min-height: 100vh;
        display: flex;
        position: relative;
    }

    /* LIBRARY BACKGROUND IMAGE WITH OVERLAY */
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

    /* Optional: Additional dark overlay for better text contrast */
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
       MODERN SIDEBAR
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
        border-right: 1px solid rgba(255,255,255,0.08);
        transition: all 0.3s ease;
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
        color: white;
        font-weight: 600;
        font-size: 1.1rem;
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
       MAIN CONTENT
       ============================================ */
    .main-content {
        flex: 1;
        margin-left: 280px;
        padding: 30px 40px;
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
    }

    .user-details p {
        font-size: 0.75rem;
        color: #94a3b8;
    }

    .change-photo {
        font-size: 0.7rem;
        color: #667eea;
        text-decoration: none;
    }

    .change-photo:hover {
        text-decoration: underline;
    }

    /* STATS GRID */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 25px;
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
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .stat-info h3 {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #94a3b8;
        letter-spacing: 1px;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 800;
        color: white;
        margin-top: 5px;
    }

    .stat-number small {
        font-size: 0.8rem;
        color: #f59e0b;
    }

    .stat-icon {
        width: 55px;
        height: 55px;
        border-radius: 18px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
    }

    /* MESSAGE STATS CARDS */
    .message-stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .message-stat-card {
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.3s;
    }

    .message-stat-card:hover {
        transform: translateY(-3px);
        background: rgba(255,255,255,0.12);
    }

    .message-stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 15px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 1.3rem;
        color: white;
    }

    .message-stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: white;
    }

    .message-stat-label {
        font-size: 0.75rem;
        color: #94a3b8;
    }

    /* MESSAGE FORMS */
    .message-forms-layout {
        display: flex;
        gap: 25px;
        margin-bottom: 35px;
        flex-wrap: wrap;
    }

    .message-buttons-left {
        flex: 0 0 280px;
    }

    .message-buttons-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .message-btn {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 18px;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 16px;
        cursor: pointer;
        transition: all 0.3s;
        width: 100%;
        text-align: left;
    }

    .message-btn:hover {
        background: rgba(102, 126, 234, 0.15);
        border-color: #667eea;
    }

    .message-btn.active {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-color: transparent;
    }

    .btn-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        background: rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .message-btn h3 {
        font-size: 0.9rem;
        font-weight: 600;
        color: white;
        margin-bottom: 3px;
    }

    .message-btn p {
        font-size: 0.7rem;
        color: #94a3b8;
    }

    .message-forms-right {
        flex: 1;
    }

    .message-form {
        display: none;
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 25px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .message-form.active {
        display: block;
        animation: fadeIn 0.4s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .form-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: white;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #94a3b8;
        font-size: 0.85rem;
    }

    .form-control {
        width: 100%;
        padding: 12px 16px;
        background: rgba(0,0,0,0.3);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 12px;
        font-size: 0.9rem;
        color: white;
        transition: all 0.3s;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
    }

    .form-control option {
        background: #1a1a2e;
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    .form-submit-btn {
        padding: 12px 28px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border: none;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
    }

    .form-submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    /* RECENT CONTAINER */
    .recent-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .recent-card {
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 20px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .recent-card h4 {
        font-size: 1rem;
        font-weight: 600;
        color: white;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .items-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .items-list::-webkit-scrollbar {
        width: 6px;
    }

    .items-list::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
        border-radius: 10px;
    }

    .items-list::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 10px;
    }

    .item {
        padding: 15px;
        background: rgba(0,0,0,0.2);
        border-radius: 12px;
        margin-bottom: 12px;
        transition: all 0.3s;
    }

    .item:hover {
        background: rgba(102, 126, 234, 0.1);
    }

    .item.unread {
        border-left: 3px solid #ef4444;
    }

    .item-content {
        color: #cbd5e1;
        font-size: 0.85rem;
        line-height: 1.5;
        margin: 8px 0;
    }

    .item-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.7rem;
        color: #64748b;
    }

    .item-direction {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.65rem;
        margin-left: 8px;
    }

    .direction-incoming {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }

    .direction-outgoing {
        background: rgba(102, 126, 234, 0.2);
        color: #667eea;
    }

    /* QUICK ACTIONS */
    .section-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: white;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .action-card {
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        padding: 25px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .action-card:hover {
        transform: translateY(-5px);
        background: rgba(255,255,255,0.12);
        border-color: #667eea;
    }

    .action-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 1.5rem;
        color: white;
    }

    .action-card h3 {
        font-size: 0.9rem;
        font-weight: 600;
        color: white;
        margin-bottom: 5px;
    }

    .action-card p {
        font-size: 0.7rem;
        color: #94a3b8;
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
    }

    .notification-success {
        background: rgba(16, 185, 129, 0.15);
        border-left: 4px solid #10b981;
        color: #10b981;
    }

    .notification-error {
        background: rgba(239, 68, 68, 0.15);
        border-left: 4px solid #ef4444;
        color: #ef4444;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* EMPTY STATE */
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #64748b;
    }

    .empty-state i {
        font-size: 2.5rem;
        margin-bottom: 10px;
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
        .stats-grid, .message-stats-cards, .recent-container, .actions-grid {
            grid-template-columns: 1fr;
        }
        .header {
            flex-direction: column;
            text-align: center;
        }
        .message-forms-layout {
            flex-direction: column;
        }
        .message-buttons-left {
            flex: auto;
        }
    }
</style>
</head>
<body>

<!-- Sidebar -->
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
            <p><?php echo htmlspecialchars($custom_role_display); ?></p>
        </div>
    </a>

    <div class="sidebar-menu">
        <div class="menu-section">⚙️ Päävalikko</div>
        <a href="admin_dashboard.php" class="menu-item active"><i class="fas fa-tachometer-alt"></i><span>Kojelauta</span></a>

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

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <div class="header">
     <div class="page-title">
         <h1>Admin Kojelauta</h1>
          <p style="color: #a78bfa;">
             <i class="fas fa-chart-line" style="color: #10b981;"></i> 
                 Tervetuloa takaisin, <span style="color: #f59e0b; font-weight: 600;"><?php echo htmlspecialchars($etunimi); ?></span>!
          </p>
         </div>
        <div class="user-info">
            <div class="user-avatar">
                <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
            </div>
            <div class="user-details">
              <h3><?php echo htmlspecialchars($custom_name); ?></h3>
                <p style="color: #94a3b8;"><i class="fas fa-envelope" style="color: #667eea;"></i> <?php echo htmlspecialchars($custom_email); ?></p>
                <p style="color: #10b981;"><i class="fas fa-shield-alt" style="color: #10b981;"></i> <?php echo htmlspecialchars($custom_role_display); ?></p>
                <p style="color: #f59e0b;"><i class="fas fa-key" style="color: #f59e0b;"></i> <?php echo htmlspecialchars($custom_permissions); ?></p>
                <a href="#" class="change-photo" style="color: #667eea;"><i class="fas fa-camera"></i> Vaihda kuvaa</a>
          </div>
        </div>
    </div>

    <!-- Notification -->
    <?php if (!empty($message)): ?>
    <div class="notification notification-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Kirjoja</h3>
                    <div class="stat-number"><?php echo number_format($kirjoja_yhteensa, 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-book"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Jäseniä</h3>
                    <div class="stat-number"><?php echo number_format($jasenia_yhteensa, 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Aktiivisia lainoja</h3>
                    <div class="stat-number"><?php echo number_format($aktiivisia_lainoja, 0, ',', ' '); ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Myöhässä</h3>
                    <div class="stat-number"><?php echo number_format($myohassa_lainoja, 0, ',', ' '); ?></div>
                    <small style="color: #f59e0b;">Sakot: <?php echo number_format($sakkoja_yhteensa, 2, ',', ' '); ?> €</small>
                </div>
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Laitteita</h3>
                    <div class="stat-number"><?php echo number_format($laitteita_yhteensa, 0, ',', ' '); ?></div>
                    <small style="color: #10b981;">Saatavilla: <?php echo $saatavilla_laitteita; ?></small>
                </div>
                <div class="stat-icon"><i class="fas fa-laptop"></i></div>
            </div>
        </div>
    </div>

    <!-- Messaging System -->
    <div class="section-title"><i class="fas fa-comments"></i> Viestijärjestelmä</div>
    
    <div class="message-stats-cards">
        <div class="message-stat-card">
            <div class="message-stat-icon"><i class="fas fa-envelope"></i></div>
            <div class="message-stat-number"><?php echo $lukemattomia_viesteja; ?></div>
            <div class="message-stat-label">Lukemattomat viestit</div>
        </div>
        <div class="message-stat-card">
            <div class="message-stat-icon"><i class="fas fa-paper-plane"></i></div>
            <div class="message-stat-number"><?php echo $lahetettyja_viesteja; ?></div>
            <div class="message-stat-label">Lähetetyt viestit</div>
        </div>
        <div class="message-stat-card">
            <div class="message-stat-icon"><i class="fas fa-bullhorn"></i></div>
            <div class="message-stat-number"><?php echo $ilmoituksia; ?></div>
            <div class="message-stat-label">Ilmoitukset</div>
        </div>
    </div>

    <div class="message-forms-layout">
        <div class="message-buttons-left">
            <div class="message-buttons-container">
                <button class="message-btn send-message active" data-form="send-message-form">
                    <div class="btn-icon"><i class="fas fa-paper-plane"></i></div>
                    <div><h3>Lähetä viesti</h3><p>Lähetä viesti jäsenelle</p></div>
                </button>
                <button class="message-btn send-announcement" data-form="send-announcement-form">
                    <div class="btn-icon"><i class="fas fa-bullhorn"></i></div>
                    <div><h3>Lähetä ilmoitus</h3><p>Lähetä ilmoitus kaikille</p></div>
                </button>
            </div>
        </div>

        <div class="message-forms-right">
            <div id="send-message-form" class="message-form active">
                <div class="form-title"><i class="fas fa-paper-plane"></i> Lähetä viesti jäsenelle</div>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Vastaanottaja:</label>
                        <select name="vastaanottaja_id" class="form-control form-select" required>
                            <option value="">Valitse jäsen...</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo htmlspecialchars($member['etunimi'] . ' ' . $member['sukunimi'] . ' (' . $member['rooli'] . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Viesti:</label>
                        <textarea name="viesti" class="form-control" placeholder="Kirjoita viestisi tähän..." required></textarea>
                    </div>
                    <input type="hidden" name="action" value="send_message">
                    <button type="submit" class="form-submit-btn"><i class="fas fa-paper-plane"></i> Lähetä viesti</button>
                </form>
            </div>

            <div id="send-announcement-form" class="message-form">
                <div class="form-title"><i class="fas fa-bullhorn"></i> Lähetä ilmoitus kaikille jäsenille</div>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Ilmoitusviesti:</label>
                        <textarea name="ilmoitus_viesti" class="form-control" placeholder="Kirjoita ilmoitusviestisi tähän..." required></textarea>
                    </div>
                    <input type="hidden" name="action" value="send_announcement">
                    <button type="submit" class="form-submit-btn"><i class="fas fa-bullhorn"></i> Lähetä ilmoitus</button>
                </form>
            </div>
        </div>
    </div>

    <div class="recent-container">
        <div class="recent-card">
            <h4><i class="fas fa-envelope"></i> Viimeisimmät viestit</h4>
            <div class="items-list">
                <?php if (count($recent_messages) > 0): ?>
                    <?php foreach ($recent_messages as $msg): ?>
                        <div class="item <?php echo ($msg['vastaanottaja_id'] == $user_id && !$msg['luettu']) ? 'unread' : ''; ?>">
                            <div style="font-weight: 600; color: white;">
                                <?php echo htmlspecialchars($msg['lahettaja_etunimi'] . ' ' . $msg['lahettaja_sukunimi']); ?>
                                <?php if ($msg['vastaanottaja_id'] == $user_id): ?>
                                    <span class="item-direction direction-incoming">Saapunut</span>
                                <?php else: ?>
                                    <span class="item-direction direction-outgoing">Lähetetty</span>
                                <?php endif; ?>
                            </div>
                            <div class="item-content"><?php echo nl2br(htmlspecialchars(mb_strimwidth($msg['viesti'], 0, 100, '...'))); ?></div>
                            <div class="item-meta"><span><?php echo date('d.m.Y H:i', strtotime($msg['luontiaika'])); ?></span></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-inbox"></i><p>Ei viestejä</p></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="recent-card">
            <h4><i class="fas fa-bullhorn"></i> Viimeisimmät ilmoitukset</h4>
            <div class="items-list">
                <?php if (count($recent_announcements) > 0): ?>
                    <?php foreach ($recent_announcements as $announcement): ?>
                        <div class="item">
                            <div style="font-weight: 600; color: white;"><?php echo htmlspecialchars($announcement['etunimi'] . ' ' . $announcement['sukunimi']); ?></div>
                            <div class="item-content"><?php echo nl2br(htmlspecialchars(mb_strimwidth($announcement['viesti'], 0, 100, '...'))); ?></div>
                            <div class="item-meta"><span><?php echo date('d.m.Y H:i', strtotime($announcement['luontiaika'])); ?></span></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-bullhorn"></i><p>Ei ilmoituksia</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="section-title"><i class="fas fa-bolt"></i> Pikatoiminnot</div>
    <div class="actions-grid">
        <div class="action-card" onclick="location.href='admin_manage_kirjat.php'"><div class="action-icon"><i class="fas fa-book"></i></div><h3>Kirjojen Hallinta</h3><p>Hallinnoi kokoelmaa</p></div>
        <div class="action-card" onclick="location.href='register.php'"><div class="action-icon"><i class="fas fa-user-plus"></i></div><h3>Rekisteröi Jäsen</h3><p>Lisää uusi jäsen</p></div>
        <div class="action-card" onclick="location.href='admin_varaukset.php'"><div class="action-icon"><i class="fas fa-hand-holding"></i></div><h3>Käsittele Lainoja</h3><p>Hyväksy ja käsittele</p></div>
        <div class="action-card" onclick="location.href='admin_sakot.php'"><div class="action-icon"><i class="fas fa-euro-sign"></i></div><h3>Sakkojen Hallinta</h3><p>Hallinnoi myöhästymisiä</p></div>
        <div class="action-card" onclick="location.href='admin_varmuuskopiointi.php'"><div class="action-icon"><i class="fas fa-database"></i></div><h3>Varmuuskopiot</h3><p>Tee varmuuskopioita</p></div>
        <div class="action-card" onclick="location.href='admin_laitteet.php'"><div class="action-icon"><i class="fas fa-laptop"></i></div><h3>Laitteiden Hallinta</h3><p>Hallinnoi laitteita</p></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Message form switching
        const buttons = document.querySelectorAll('.message-btn');
        const forms = document.querySelectorAll('.message-form');

        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                const formId = this.getAttribute('data-form');
                buttons.forEach(b => b.classList.remove('active'));
                forms.forEach(f => f.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(formId).classList.add('active');
            });
        });

        // Auto-hide notifications
        setTimeout(() => {
            document.querySelectorAll('.notification').forEach(n => {
                n.style.opacity = '0';
                n.style.transform = 'translateY(-20px)';
                n.style.transition = 'all 0.3s';
                setTimeout(() => n.remove(), 300);
            });
        }, 5000);

        // Intersection Observer for animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.stat-card, .message-stat-card, .recent-card, .action-card, .message-forms-layout').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.5s ease';
            observer.observe(el);
        });
    });
</script>

</body>
</html>
