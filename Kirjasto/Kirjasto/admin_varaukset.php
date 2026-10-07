<?php
// admin_varaukset.php - Varausten hallinta (Admin)
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Include the database connection
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user data
$user_sql = "SELECT etunimi, sukunimi, email, jasennumero, profile_image, rooli FROM jasenet WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Store user data in variables
$first_name = $user_data['etunimi'] ?? '';
$last_name = $user_data['sukunimi'] ?? '';
$email = $user_data['email'] ?? '';
$profile_image = $user_data['profile_image'] ?? '';
$user_role = $user_data['rooli'] ?? 'user';
$full_name = $first_name . ' ' . $last_name;

// Generate initials for avatar
$user_initials = '';
if (!empty($first_name) && !empty($last_name)) {
    $user_initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
} elseif (!empty($first_name)) {
    $user_initials = strtoupper(substr($first_name, 0, 1));
} else {
    $user_initials = 'U';
}

// Function to get profile image URL
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
    return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
}

$profile_image_url = getProfileImageUrl($profile_image, $full_name);

$admin_role = $user_role === 'admin' ? 'Ylläpitäjä' : ($user_role === 'manager' ? 'Manager' : 'Käyttäjä');
$admin_permissions = $user_role === 'admin' ? 'Täydet järjestelmäoikeudet' : ($user_role === 'manager' ? 'Laajat hallintaoikeudet' : 'Peruskäyttäjä');

// Initialize variables
$search_query = "";
$filter_status = "";
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get filter parameters
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
}
if (isset($_GET['status'])) {
    $filter_status = trim($_GET['status']);
}

// Initialize messages
$success_messages = [];
$error_messages = [];
if (isset($_SESSION['success_messages'])) {
    $success_messages = $_SESSION['success_messages'];
    unset($_SESSION['success_messages']);
}
if (isset($_SESSION['error_messages'])) {
    $error_messages = $_SESSION['error_messages'];
    unset($_SESSION['error_messages']);
}

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark reservation as completed
    if (isset($_POST['mark_completed']) && isset($_POST['reservation_id'])) {
        $reservation_id = intval($_POST['reservation_id']);

        $sql = "UPDATE varaukset SET tila = 'valmis' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $reservation_id);
            if ($stmt->execute()) {
                $_SESSION['success_messages'][] = "Varaus merkitty valmiiksi!";
            } else {
                $_SESSION['error_messages'][] = "Virhe: " . $stmt->error;
            }
            $stmt->close();
        }
        header("Location: admin_varaukset.php" . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
        exit();
    }

    // Cancel reservation
    if (isset($_POST['cancel_reservation']) && isset($_POST['reservation_id'])) {
        $reservation_id = intval($_POST['reservation_id']);

        $sql = "UPDATE varaukset SET tila = 'peruutettu' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $reservation_id);
            if ($stmt->execute()) {
                $_SESSION['success_messages'][] = "Varaus peruutettu!";
            } else {
                $_SESSION['error_messages'][] = "Virhe: " . $stmt->error;
            }
            $stmt->close();
        }
        header("Location: admin_varaukset.php" . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
        exit();
    }

    // Add new reservation
    if (isset($_POST['add_reservation'])) {
        $kirja_id = intval($_POST['kirja_id']);
        $jasen_id = intval($_POST['jasen_id']);
        $varaus_paiva = date('Y-m-d');

        // Check if book exists
        $book_check = "SELECT id, nimi FROM kirjat WHERE id = ?";
        $book_stmt = $conn->prepare($book_check);
        $book_stmt->bind_param("i", $kirja_id);
        $book_stmt->execute();
        $book_stmt->store_result();

        if ($book_stmt->num_rows > 0) {
            // Check if member exists
            $member_check = "SELECT id FROM jasenet WHERE id = ?";
            $member_stmt = $conn->prepare($member_check);
            $member_stmt->bind_param("i", $jasen_id);
            $member_stmt->execute();
            $member_stmt->store_result();

            if ($member_stmt->num_rows > 0) {
                // Check if reservation already exists
                $res_check = "SELECT id FROM varaukset WHERE kirja_id = ? AND jasen_id = ? AND tila = 'odottaa'";
                $res_stmt = $conn->prepare($res_check);
                $res_stmt->bind_param("ii", $kirja_id, $jasen_id);
                $res_stmt->execute();
                $res_stmt->store_result();

                if ($res_stmt->num_rows == 0) {
                    $sql = "INSERT INTO varaukset (kirja_id, jasen_id, varaus_paiva, tila) VALUES (?, ?, ?, 'odottaa')";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("iis", $kirja_id, $jasen_id, $varaus_paiva);
                        if ($stmt->execute()) {
                            $_SESSION['success_messages'][] = "Uusi varaus lisätty onnistuneesti!";
                        } else {
                            $_SESSION['error_messages'][] = "Virhe: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                } else {
                    $_SESSION['error_messages'][] = "Varaus on jo olemassa tälle kirjalle ja jäsenelle!";
                }
                $res_stmt->close();
            } else {
                $_SESSION['error_messages'][] = "Jäsentä ei löydy!";
            }
            $member_stmt->close();
        } else {
            $_SESSION['error_messages'][] = "Kirjaa ei löydy!";
        }
        $book_stmt->close();

        header("Location: admin_varaukset.php" . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
        exit();
    }
}

// Build query for reservations
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($search_query)) {
    $where_conditions[] = "(k.nimi LIKE ? OR k.tekija LIKE ? OR j.etunimi LIKE ? OR j.sukunimi LIKE ?)";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
    $param_types .= "ssss";
}

if (!empty($filter_status)) {
    $where_conditions[] = "v.tila = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM varaukset v
              JOIN kirjat k ON v.kirja_id = k.id
              JOIN jasenet j ON v.jasen_id = j.id
              $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_stmt->bind_result($total_reservations);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_reservations / $limit);

// Get reservations data
$sql = "SELECT v.*,
               k.nimi as kirja_nimi, k.tekija, k.isbn, k.kategoria,
               j.etunimi as jasen_etunimi, j.sukunimi as jasen_sukunimi, j.email as jasen_email,
               DATEDIFF(CURDATE(), v.varaus_paiva) as days_since_reservation
        FROM varaukset v
        JOIN kirjat k ON v.kirja_id = k.id
        JOIN jasenet j ON v.jasen_id = j.id
        $where_sql
        ORDER BY v.varaus_paiva DESC, v.id DESC
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
    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }
    $stmt->close();
}

// Get statistics
$stats_sql = "SELECT
    (SELECT COUNT(*) FROM varaukset) as total_reservations,
    (SELECT COUNT(*) FROM varaukset WHERE tila = 'odottaa') as pending_reservations,
    (SELECT COUNT(*) FROM varaukset WHERE tila = 'valmis') as completed_reservations,
    (SELECT COUNT(*) FROM varaukset WHERE tila = 'peruutettu') as cancelled_reservations,
    (SELECT COUNT(*) FROM varaukset WHERE tila = 'odottaa' AND DATEDIFF(CURDATE(), varaus_paiva) > 7) as expired_reservations";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
$stats_result->close();

// Get books for dropdown
$books_sql = "SELECT id, nimi, tekija FROM kirjat ORDER BY nimi";
$books_result = $conn->query($books_sql);
$books = [];
while ($row = $books_result->fetch_assoc()) {
    $books[] = $row;
}
$books_result->close();

// Get popular books
$popular_sql = "SELECT k.nimi, COUNT(v.id) as count
                FROM varaukset v
                JOIN kirjat k ON v.kirja_id = k.id
                WHERE v.tila = 'odottaa'
                GROUP BY v.kirja_id
                ORDER BY count DESC
                LIMIT 5";
$popular_result = $conn->query($popular_sql);
$popular_books = [];
while ($row = $popular_result->fetch_assoc()) {
    $popular_books[] = $row;
}
$popular_result->close();
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Varausten Hallinta | Admin | Kirjasto</title>
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

        /* SEARCH CONTAINER */
        .search-container {
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

        .search-form {
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

        /* POPULAR BOOKS */
        .popular-books {
            padding-top: 25px;
            margin-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .popular-books h3 {
            color: white;
            font-size: 1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .book-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .book-tag {
            background: rgba(102,126,234,0.15);
            color: #a78bfa;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(102,126,234,0.3);
        }

        .book-tag:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
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

        /* TABLE CONTAINER */
        .table-container {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.1);
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

        .reservations-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .reservations-table th {
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

        .reservations-table td {
            padding: 15px;
            color: #cbd5e1;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            vertical-align: middle;
        }

        .reservations-table tr:hover td {
            background: rgba(255,255,255,0.05);
        }

        /* STATUS BADGES */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .status-expired {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        /* ACTION BUTTONS */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* ADD FORM */
        .add-form {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }

        /* ALERTS */
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
            .search-form,
            .form-row {
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
            <h4><?php echo htmlspecialchars($full_name); ?></h4>
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
        <a href="admin_lainat.php" class="menu-item"><i class="fas fa-list"></i><span>Hallinnoi Lainoja</span></a>
        <a href="admin_varaukset.php" class="menu-item active"><i class="fas fa-check-circle"></i><span>Käsittele Lainoja</span></a>
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
            <h1><i class="fas fa-calendar-check"></i> Varausten Hallinta</h1>
            <p><i class="fas fa-bookmark"></i> Hallinnoi ja seuraa kaikkia varauksia</p>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($full_name); ?></h3>
                <p style="color: #94a3b8;"><i class="fas fa-envelope" style="color: #667eea;"></i> <?php echo htmlspecialchars($email); ?></p>
                <p style="color: #10b981;"><i class="fas fa-shield-alt" style="color: #10b981;"></i> <?php echo $admin_role; ?></p>
                <p style="color: #f59e0b;"><i class="fas fa-key" style="color: #f59e0b;"></i> <?php echo $admin_permissions; ?></p>
            </div>
        </div>
    </div>

    <!-- NOTIFICATIONS -->
    <?php if (!empty($success_messages)): ?>
        <?php foreach ($success_messages as $msg): ?>
            <div class="notification notification-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($error_messages)): ?>
        <?php foreach ($error_messages as $msg): ?>
            <div class="notification notification-error">
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
                    <h3>Kaikki varaukset</h3>
                    <div class="stat-number"><?php echo $stats['total_reservations'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Aktiiviset</h3>
                    <div class="stat-number"><?php echo $stats['pending_reservations'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Valmiit</h3>
                    <div class="stat-number"><?php echo $stats['completed_reservations'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-info">
                    <h3>Peruutetut</h3>
                    <div class="stat-number"><?php echo $stats['cancelled_reservations'] ?? 0; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>
    </div>

    <!-- SEARCH AND FILTER -->
    <div class="search-container">
        <div class="section-title">
            <i class="fas fa-search"></i> Hae ja suodata varauksia
        </div>

        <form method="GET" action="" class="search-form">
            <div class="form-group">
                <label class="form-label">Hae varauksia</label>
                <input type="text" name="search" class="form-control"
                       placeholder="Hae kirjan nimen, tekijän tai jäsenen perusteella..."
                       value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Tila</label>
                <select name="status" class="form-control">
                    <option value="">Kaikki</option>
                    <option value="odottaa" <?php echo ($filter_status == 'odottaa') ? 'selected' : ''; ?>>Odottaa</option>
                    <option value="valmis" <?php echo ($filter_status == 'valmis') ? 'selected' : ''; ?>>Valmis</option>
                    <option value="peruutettu" <?php echo ($filter_status == 'peruutettu') ? 'selected' : ''; ?>>Peruutettu</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Hae</button>
                    <a href="admin_varaukset.php" class="btn btn-light"><i class="fas fa-times"></i> Tyhjennä</a>
                </div>
            </div>
        </form>

        <?php if (!empty($popular_books)): ?>
            <div class="popular-books">
                <h3><i class="fas fa-star"></i> Suosituimmat varaukset</h3>
                <div class="book-tags">
                    <?php foreach ($popular_books as $book): ?>
                        <span class="book-tag" onclick="searchBook('<?php echo htmlspecialchars($book['nimi']); ?>')">
                            <?php echo htmlspecialchars($book['nimi']); ?> (<?php echo $book['count']; ?>)
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- RESERVATIONS TABLE -->
    <div class="table-container">
        <div class="section-title">
            <i class="fas fa-list"></i> Varauslista (<?php echo $total_reservations; ?> varausta)
        </div>

        <?php if (empty($reservations)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <p>Ei varauksia löytynyt</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="reservations-table">
                    <thead>
                        <tr>
                            <th>Kirja</th>
                            <th>Tekijä</th>
                            <th>Jäsen</th>
                            <th>Varauspäivä</th>
                            <th>Päiviä</th>
                            <th>Tila</th>
                            <th>Toiminnot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $reservation):
                            $is_expired = ($reservation['days_since_reservation'] > 7 && $reservation['tila'] == 'odottaa');
                        ?>
                            <tr>
                                <td>
                                    <div style="color: white; font-weight: 600;"><?php echo htmlspecialchars($reservation['kirja_nimi']); ?></div>
                                    <div style="font-size: 0.7rem; color: #94a3b8;">ISBN: <?php echo htmlspecialchars($reservation['isbn'] ?? '-'); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($reservation['tekija']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($reservation['jasen_etunimi'] . ' ' . $reservation['jasen_sukunimi']); ?><br>
                                    <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo htmlspecialchars($reservation['jasen_email']); ?></div>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($reservation['varaus_paiva'])); ?></td>
                                <td>
                                    <span style="color: <?php echo $is_expired ? '#ef4444' : '#10b981'; ?>">
                                        <?php echo $reservation['days_since_reservation']; ?> pv
                                    </span>
                                    <?php if ($is_expired): ?>
                                        <br><small style="color: #ef4444;">(Vanhentunut)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($is_expired) {
                                        $status_class = 'status-expired';
                                        $status_text = 'Vanhentunut';
                                    } else {
                                        switch($reservation['tila']) {
                                            case 'odottaa':
                                                $status_class = 'status-pending';
                                                $status_text = 'Odottaa';
                                                break;
                                            case 'valmis':
                                                $status_class = 'status-completed';
                                                $status_text = 'Valmis';
                                                break;
                                            case 'peruutettu':
                                                $status_class = 'status-cancelled';
                                                $status_text = 'Peruutettu';
                                                break;
                                            default:
                                                $status_class = 'status-pending';
                                                $status_text = ucfirst($reservation['tila']);
                                        }
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($reservation['tila'] == 'odottaa' && !$is_expired): ?>
                                        <div class="action-buttons">
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Haluatko varmasti merkitä varauksen valmiiksi?')">
                                                <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                <button type="submit" name="mark_completed" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Valmis
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Haluatko varmasti peruuttaa varauksen?')">
                                                <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                <button type="submit" name="cancel_reservation" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i> Peruuta
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif ($reservation['tila'] == 'valmis'): ?>
                                        <span class="btn btn-success btn-sm" style="opacity: 0.6; cursor: default;">
                                            <i class="fas fa-check"></i> Valmis
                                        </span>
                                    <?php elseif ($reservation['tila'] == 'peruutettu'): ?>
                                        <span class="btn btn-danger btn-sm" style="opacity: 0.6; cursor: default;">
                                            <i class="fas fa-times"></i> Peruutettu
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #64748b;">-</span>
                                    <?php endif; ?>
                                 </nbsp
                             </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($filter_status); ?>" class="btn btn-light btn-sm">
                            <i class="fas fa-chevron-left"></i> Edellinen
                        </a>
                    <?php endif; ?>
                    <span class="page-info">Sivu <?php echo $page; ?> / <?php echo $total_pages; ?></span>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($filter_status); ?>" class="btn btn-light btn-sm">
                            Seuraava <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ADD RESERVATION FORM -->
    <div class="add-form">
        <div class="section-title">
            <i class="fas fa-plus"></i> Lisää uusi varaus
        </div>

        <form method="POST" action="" id="addReservationForm">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Valitse kirja</label>
                    <select name="kirja_id" class="form-control" required>
                        <option value="">Valitse kirja...</option>
                        <?php foreach ($books as $book): ?>
                            <option value="<?php echo $book['id']; ?>">
                                <?php echo htmlspecialchars($book['nimi'] . ' - ' . $book['tekija']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Jäsenen ID</label>
                    <input type="number" name="jasen_id" class="form-control" placeholder="Syötä jäsenen ID-numero" required min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" name="add_reservation" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Lisää varaus
                    </button>
                </div>
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

        document.querySelectorAll('.stat-card, .search-container, .table-container, .add-form').forEach(function(el) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.5s ease';
            observer.observe(el);
        });

        // Form validation for add reservation
        const addReservationForm = document.getElementById('addReservationForm');
        if (addReservationForm) {
            addReservationForm.addEventListener('submit', function(e) {
                const bookSelect = this.querySelector('select[name="kirja_id"]');
                const memberId = this.querySelector('input[name="jasen_id"]');

                if (!bookSelect.value) {
                    e.preventDefault();
                    alert('Valitse kirja!');
                    bookSelect.focus();
                    return false;
                }

                if (!memberId.value || memberId.value < 1) {
                    e.preventDefault();
                    alert('Syötä kelvollinen jäsenen ID!');
                    memberId.focus();
                    return false;
                }
            });
        }

        // Quick search with Ctrl+F
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) searchInput.focus();
            }
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

    // Function to search when clicking on popular book tag
    function searchBook(bookName) {
        document.querySelector('input[name="search"]').value = bookName;
        document.querySelector('.search-form').submit();
    }
</script>

</body>
</html>
