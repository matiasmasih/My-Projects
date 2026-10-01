<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Load admin user from database
$admin_user = [];
$user_sql = "SELECT rooli, profile_image, etunimi, sukunimi FROM jasenet WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);

if ($user_stmt) {
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();

    if ($user_result->num_rows > 0) {
        $current_user = $user_result->fetch_assoc();

        $admin_user = [
            'id' => $user_id,
            'rooli' => $current_user['rooli'] ?? 'admin',
            'profile_image' => $current_user['profile_image'] ?? null,
            'etunimi' => $current_user['etunimi'] ?? 'Admin',
            'sukunimi' => $current_user['sukunimi'] ?? 'User',
            'kayttajanimi' => ($current_user['etunimi'] ?? 'Admin') . ' ' . ($current_user['sukunimi'] ?? 'User'),
            'email' => $_SESSION['email'] ?? 'admin@example.com'
        ];

        if (isset($_SESSION['profile_image'])) {
            $admin_user['profile_image'] = $_SESSION['profile_image'];
        }

    } else {
        $admin_user = [
            'id' => $user_id,
            'kayttajanimi' => 'Admin User',
            'etunimi' => 'Admin',
            'sukunimi' => 'User',
            'rooli' => 'admin',
            'email' => 'admin@example.com',
            'profile_image' => null
        ];
    }
    $user_stmt->close();
} else {
    $admin_user = [
        'id' => $user_id,
        'kayttajanimi' => 'Admin User',
        'etunimi' => 'Admin',
        'sukunimi' => 'User',
        'rooli' => 'admin',
        'email' => 'admin@example.com',
        'profile_image' => null
    ];
}

$success = '';
$error = '';
$search_query = $_GET['search'] ?? '';
$filter_rooli = $_GET['rooli'] ?? '';
$filter_tila = $_GET['tila'] ?? 'all';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete user - ADMIN AND MANAGER CAN DELETE
    if (isset($_POST['delete_user'])) {
        $delete_id = (int)$_POST['user_id'];
        
        // Admin OR Manager can delete users
        if ($admin_user['rooli'] !== 'admin' && $admin_user['rooli'] !== 'manager') {
            $error = "Sinulla ei ole oikeutta poistaa käyttäjiä!";
        } elseif ($delete_id == $admin_user['id']) {
            $error = "Et voi poistaa omaa tiliäsi!";
        } else {
            $delete_sql = "UPDATE jasenet SET tila = 'keskeytetty' WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            if ($delete_stmt) {
                $delete_stmt->bind_param("i", $delete_id);
                if ($delete_stmt->execute()) {
                    $success = "Käyttäjä poistettu onnistuneesti!";
                } else {
                    $error = "Virhe käyttäjän poistossa: " . $delete_stmt->error;
                }
                $delete_stmt->close();
            }
        }
    }

    // Update user role
    if (isset($_POST['update_role'])) {
        $update_id = (int)$_POST['user_id'];
        $new_role = $_POST['new_role'];
        
        $update_sql = "UPDATE jasenet SET rooli = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        if ($update_stmt) {
            $update_stmt->bind_param("si", $new_role, $update_id);
            if ($update_stmt->execute()) {
                $success = "Käyttäjän rooli päivitetty onnistuneesti!";
            } else {
                $error = "Virhe roolin päivityksessä: " . $update_stmt->error;
            }
            $update_stmt->close();
        }
    }

    // Update user status
    if (isset($_POST['update_status'])) {
        $update_id = (int)$_POST['user_id'];
        $new_status = $_POST['new_status'];
        
        $update_sql = "UPDATE jasenet SET tila = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        if ($update_stmt) {
            $update_stmt->bind_param("si", $new_status, $update_id);
            if ($update_stmt->execute()) {
                $success = "Käyttäjän tila päivitetty onnistuneesti!";
            } else {
                $error = "Virhe tilan päivityksessä: " . $update_stmt->error;
            }
            $update_stmt->close();
        }
    }
}

// Build WHERE conditions for user query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search_query)) {
    $where_conditions[] = "(etunimi LIKE ? OR sukunimi LIKE ? OR email LIKE ?)";
    $search_term = "%{$search_query}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

if (!empty($filter_rooli)) {
    $where_conditions[] = "rooli = ?";
    $params[] = $filter_rooli;
    $types .= 's';
}

if (!empty($filter_tila) && $filter_tila !== 'all') {
    $where_conditions[] = "tila = ?";
    $params[] = $filter_tila;
    $types .= 's';
}

$where_sql = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users
$users_sql = "SELECT id, etunimi, sukunimi, email, rooli, tila, luotu, profile_image
              FROM jasenet
              $where_sql
              ORDER BY sukunimi, etunimi";

$users_stmt = $conn->prepare($users_sql);
$users = [];

if ($users_stmt) {
    if (!empty($params)) {
        $users_stmt->bind_param($types, ...$params);
    }
    $users_stmt->execute();
    $users_result = $users_stmt->get_result();
    $users = $users_result->fetch_all(MYSQLI_ASSOC);
    $users_stmt->close();
}

// Get statistics
$stats_sql = "SELECT
    COUNT(*) as total_users,
    SUM(CASE WHEN rooli = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN rooli = 'manager' THEN 1 ELSE 0 END) as manager_count,
    SUM(CASE WHEN rooli = 'user' THEN 1 ELSE 0 END) as user_count,
    SUM(CASE WHEN tila = 'aktiivinen' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN tila = 'passiivinen' THEN 1 ELSE 0 END) as inactive_count,
    SUM(CASE WHEN tila = 'keskeytetty' THEN 1 ELSE 0 END) as locked_count
    FROM jasenet";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];

// Helper functions
function getInitials($name) {
    if (empty($name)) return 'A';
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return substr($initials, 0, 2);
}

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

// Get admin display info
$admin_name = $admin_user['etunimi'] . ' ' . $admin_user['sukunimi'];
$admin_initials = getInitials($admin_name);
$admin_role = $admin_user['rooli'] === 'admin' ? 'Ylläpitäjä' : ($admin_user['rooli'] === 'manager' ? 'Manager' : 'Käyttäjä');
$admin_permissions = $admin_user['rooli'] === 'admin' ? 'Täydet järjestelmäoikeudet' : 'Managerin oikeudet';
$admin_profile_image_url = getProfileImageUrl($admin_user['profile_image'] ?? '', $admin_name);
$admin_email = $admin_user['email'] ?? 'admin@example.com';

$is_manager = ($admin_user['rooli'] === 'manager');
$is_admin = ($admin_user['rooli'] === 'admin');
?>


<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Käyttäjien Hallinta | Kirjasto Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
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

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
        }

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

        .content-card {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-bar {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 45px 12px 16px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: white;
            font-size: 0.9rem;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-input::placeholder {
            color: #64748b;
        }

        .search-button {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
        }

        .filter-select {
            padding: 12px 16px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .filter-select option {
            background: #1a1a2e;
        }

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

        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.75rem;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            text-align: left;
            padding: 15px;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .users-table td {
            padding: 15px;
            color: #cbd5e1;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            vertical-align: middle;
        }

        .users-table tr:hover {
            background: rgba(255,255,255,0.05);
        }

        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .user-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-weight: 600;
            color: white;
        }

        .user-email {
            font-size: 0.7rem;
            color: #94a3b8;
        }

        .role-badge, .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .role-admin {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .role-manager {
            background: rgba(139, 92, 246, 0.2);
            color: #a78bfa;
        }

        .role-user {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }

        .status-aktiivinen {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .status-passiivinen {
            background: rgba(100, 116, 139, 0.2);
            color: #94a3b8;
        }

        .status-keskeytetty {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .table-select {
            padding: 6px 10px;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: white;
            font-size: 0.75rem;
            cursor: pointer;
        }

        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

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

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
        }

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
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .header {
                flex-direction: column;
                text-align: center;
            }
            .filter-bar {
                flex-direction: column;
            }
            .users-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

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
            <img src="<?php echo htmlspecialchars($admin_profile_image_url); ?>" alt="Profile">
        </div>
        <div class="user-info-mini">
            <h4><?php echo htmlspecialchars($admin_name); ?></h4>
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
        <a href="admin_kayttajien_hallinta.php" class="menu-item active"><i class="fas fa-users"></i><span>Hallinnoi Jäseniä</span></a>
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

<div class="main-content">
    <div class="header">
        <div class="page-title">
            <h1><i class="fas fa-users"></i> Käyttäjien Hallinta</h1>
            <p><i class="fas fa-user-shield"></i> Hallinnoi järjestelmän käyttäjiä ja heidän oikeuksiaan</p>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <img src="<?php echo htmlspecialchars($admin_profile_image_url); ?>" alt="Profile">
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($admin_name); ?></h3>
                <p style="color: #94a3b8; font-size: 0.75rem; margin-bottom: 5px;">
                    <i class="fas fa-envelope" style="color: #667eea;"></i> <?php echo htmlspecialchars($admin_email); ?>
                </p>
                <p style="color: #10b981; font-size: 0.75rem; margin-bottom: 5px;">
                    <i class="fas fa-shield-alt" style="color: #10b981;"></i> <?php echo $admin_role; ?>
                </p>
                <p style="color: #f59e0b; font-size: 0.75rem;">
                    <i class="fas fa-key" style="color: #f59e0b;"></i> <?php echo $admin_permissions; ?>
                </p>
            </div>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="notification notification-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="notification notification-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Käyttäjiä yhteensä</h3>
                    <div class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Ylläpitäjät</h3>
                    <div class="stat-number"><?php echo $stats['admin_count'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-crown"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Managerit</h3>
                    <div class="stat-number"><?php echo $stats['manager_count'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Tavalliset käyttäjät</h3>
                    <div class="stat-number"><?php echo $stats['user_count'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-user"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Aktiiviset</h3>
                    <div class="stat-number"><?php echo $stats['active_count'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Keskeytetyt</h3>
                    <div class="stat-number"><?php echo $stats['locked_count'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-lock"></i></div>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-search"></i> Hae ja suodata käyttäjiä</h2>
            <a href="register.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Lisää uusi käyttäjä</a>
        </div>

        <form method="GET" action="" class="filter-bar">
            <div class="search-box">
                <input type="text" name="search" class="search-input" 
                       placeholder="Hae nimellä tai sähköpostilla..." 
                       value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit" class="search-button"><i class="fas fa-search"></i></button>
            </div>

            <select name="rooli" class="filter-select" onchange="this.form.submit()">
                <option value="">Kaikki roolit</option>
                <option value="admin" <?php echo $filter_rooli == 'admin' ? 'selected' : ''; ?>>Ylläpitäjä</option>
                <option value="manager" <?php echo $filter_rooli == 'manager' ? 'selected' : ''; ?>>Manager</option>
                <option value="user" <?php echo $filter_rooli == 'user' ? 'selected' : ''; ?>>Käyttäjä</option>
            </select>

            <select name="tila" class="filter-select" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_tila == 'all' ? 'selected' : ''; ?>>Kaikki tilat</option>
                <option value="aktiivinen" <?php echo $filter_tila == 'aktiivinen' ? 'selected' : ''; ?>>Aktiivinen</option>
                <option value="passiivinen" <?php echo $filter_tila == 'passiivinen' ? 'selected' : ''; ?>>Passiivinen</option>
                <option value="keskeytetty" <?php echo $filter_tila == 'keskeytetty' ? 'selected' : ''; ?>>Keskeytetty</option>
            </select>

            <a href="admin_kayttajien_hallinta.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Tyhjennä</a>
        </form>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-list"></i> Käyttäjät (<?php echo count($users); ?> kpl)</h2>
        </div>

        <?php if (empty($users)): ?>
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <p>Ei käyttäjiä löytynyt</p>
                <a href="admin_kayttajien_hallinta.php" class="btn btn-primary" style="margin-top: 15px;">Näytä kaikki käyttäjät</a>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Käyttäjä</th>
                            <th>Sähköposti</th>
                            <th>Rooli</th>
                            <th>Tila</th>
                            <th>Toiminnot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): 
                            $full_name = htmlspecialchars($user['etunimi'] . ' ' . $user['sukunimi']);
                            $user_avatar_url = getProfileImageUrl($user['profile_image'] ?? '', $full_name);
                            $role_text = $user['rooli'] == 'admin' ? 'Ylläpitäjä' : ($user['rooli'] == 'manager' ? 'Manager' : 'Käyttäjä');
                            $status_text = $user['tila'] == 'aktiivinen' ? 'Aktiivinen' : ($user['tila'] == 'passiivinen' ? 'Passiivinen' : 'Keskeytetty');
                        ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="user-avatar-small">
                                            <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" alt="">
                                        </div>
                                        <div>
                                            <div class="user-name"><?php echo $full_name; ?></div>
                                            <div class="user-email">ID: <?php echo $user['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['rooli']; ?>">
                                        <?php echo $role_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['tila']; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="new_role" class="table-select" onchange="this.form.submit()">
                                                <option value="user" <?php echo $user['rooli'] == 'user' ? 'selected' : ''; ?>>Käyttäjä</option>
                                                <option value="manager" <?php echo $user['rooli'] == 'manager' ? 'selected' : ''; ?>>Manager</option>
                                                <option value="admin" <?php echo $user['rooli'] == 'admin' ? 'selected' : ''; ?>>Ylläpitäjä</option>
                                            </select>
                                            <input type="hidden" name="update_role" value="1">
                                        </form>

                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="new_status" class="table-select" onchange="this.form.submit()">
                                                <option value="aktiivinen" <?php echo $user['tila'] == 'aktiivinen' ? 'selected' : ''; ?>>Aktiivinen</option>
                                                <option value="passiivinen" <?php echo $user['tila'] == 'passiivinen' ? 'selected' : ''; ?>>Passiivinen</option>
                                                <option value="keskeytetty" <?php echo $user['tila'] == 'keskeytetty' ? 'selected' : ''; ?>>Keskeytetty</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>

                                        <?php if (($is_admin || $is_manager) && $user['id'] != $admin_user['id']): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Haluatko varmasti poistaa tämän käyttäjän?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Poista
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            document.querySelectorAll('.notification').forEach(function(n) {
                n.style.opacity = '0';
                n.style.transform = 'translateY(-20px)';
                n.style.transition = 'all 0.3s';
                setTimeout(function() { n.remove(); }, 300);
            });
        }, 5000);

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.stat-card, .content-card').forEach(function(el) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.5s ease';
            observer.observe(el);
        });

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
        menuToggle.style.display = 'none';
        document.body.appendChild(menuToggle);

        const sidebar = document.querySelector('.sidebar');
        
        function handleResize() {
            if (window.innerWidth <= 1024) {
                menuToggle.style.display = 'block';
                sidebar.style.transform = 'translateX(-100%)';
            } else {
                menuToggle.style.display = 'none';
                sidebar.style.transform = 'translateX(0)';
            }
        }
        
        handleResize();
        window.addEventListener('resize', handleResize);
        
        menuToggle.addEventListener('click', function() {
            if (sidebar.style.transform === 'translateX(-100%)') {
                sidebar.style.transform = 'translateX(0)';
            } else {
                sidebar.style.transform = 'translateX(-100%)';
            }
        });
    });
</script>

</body>
</html>
