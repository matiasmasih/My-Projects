<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Database connection
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user ID
$user_id = (int) $_SESSION['user_id'];

// Function to create default user array
function createDefaultUser($user_id, $session_data = []) {
    return [
        'id' => $user_id,
        'kayttajanimi' => $session_data['username'] ?? 'Admin User',
        'etunimi' => $session_data['etunimi'] ?? 'Admin',
        'sukunimi' => $session_data['sukunimi'] ?? 'User',
        'rooli' => $session_data['rooli'] ?? 'Admin',
        'email' => $session_data['email'] ?? 'admin@example.com',
        'profile_image' => null
    ];
}

// Initialize user array
$user = createDefaultUser($user_id, [
    'username' => $_SESSION['username'] ?? null,
    'etunimi' => $_SESSION['etunimi'] ?? null,
    'sukunimi' => $_SESSION['sukunimi'] ?? null,
    'rooli' => $_SESSION['rooli'] ?? null,
    'email' => $_SESSION['email'] ?? null
]);

// Load user from database
$user_query = "SELECT rooli, profile_image, etunimi, sukunimi, email FROM jasenet WHERE id = ?";
$user_stmt = $conn->prepare($user_query);

if ($user_stmt) {
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();

    if ($user_result->num_rows > 0) {
        $current_user = $user_result->fetch_assoc();

        $user = [
            'id' => $user_id,
            'rooli' => $current_user['rooli'] ?? 'admin',
            'profile_image' => $current_user['profile_image'] ?? null,
            'etunimi' => $current_user['etunimi'] ?? 'Admin',
            'sukunimi' => $current_user['sukunimi'] ?? 'User',
            'email' => $current_user['email'] ?? 'admin@example.com',
            'kayttajanimi' => ($current_user['etunimi'] ?? 'Admin') . ' ' . ($current_user['sukunimi'] ?? 'User')
        ];

        $_SESSION['username'] = $user['kayttajanimi'];
        $_SESSION['etunimi'] = $user['etunimi'];
        $_SESSION['sukunimi'] = $user['sukunimi'];
        $_SESSION['rooli'] = $user['rooli'];
        $_SESSION['email'] = $user['email'];
    }
    $user_stmt->close();
}

/*
|--------------------------------------------------------------------------
| INITIAL VARIABLES
|--------------------------------------------------------------------------
*/
$mode = 'list';
$book_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$book = [];
$success = '';
$error = '';
$kategoriat = [];
$stats = [];
$books = [];

/*
|--------------------------------------------------------------------------
| EDIT MODE
|--------------------------------------------------------------------------
*/
if ($book_id > 0) {
    $mode = 'edit';

    $sql = "SELECT * FROM kirjat WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $book = $result->fetch_assoc();
        $stmt->close();

        if (!$book) {
            $mode = 'list';
            $error = "Kirjaa ei löytynyt!";
        } else {
            $kategoria_query = "
                SELECT DISTINCT kategoria
                FROM kirjat
                WHERE kategoria IS NOT NULL AND kategoria != ''
                ORDER BY kategoria
                LIMIT 50
            ";
            $kategoria_result = $conn->query($kategoria_query);
            if ($kategoria_result) {
                while ($row = $kategoria_result->fetch_assoc()) {
                    $kategoriat[] = htmlspecialchars($row['kategoria']);
                }
            }
        }
    } else {
        $error = "Tietokantavirhe: " . htmlspecialchars($conn->error);
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE BOOK
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_book']) &&
    $mode === 'edit' &&
    !empty($book)
) {
    $nimi = trim($_POST['nimi'] ?? '');
    $tekija = trim($_POST['tekija'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $kategoria = trim($_POST['kategoria'] ?? '');
    $julkaisuvuosi = isset($_POST['julkaisuvuosi']) && $_POST['julkaisuvuosi'] !== '' ?
        (int)$_POST['julkaisuvuosi'] : null;
    $kustantaja = trim($_POST['kustantaja'] ?? '');
    $kokonaismaara = isset($_POST['kokonaismaara']) ? (int)$_POST['kokonaismaara'] : 0;

    if (empty($nimi) || empty($tekija)) {
        $error = "Kirjan nimi ja kirjailija ovat pakollisia!";
    } elseif ($kokonaismaara < 1) {
        $error = "Kokonaismäärän tulee olla vähintään 1!";
    } elseif ($julkaisuvuosi !== null && ($julkaisuvuosi < 1000 || $julkaisuvuosi > date('Y'))) {
        $error = "Julkaisuvuoden tulee olla välillä 1000-" . date('Y');
    } else {
        $lainassa = $book['kokonaismaara'] - $book['saatavilla'];
        $new_saatavilla = max(0, $kokonaismaara - $lainassa);

        $update_sql = "
            UPDATE kirjat SET
                nimi = ?,
                tekija = ?,
                isbn = ?,
                kategoria = ?,
                julkaisuvuosi = ?,
                kustantaja = ?,
                kokonaismaara = ?,
                saatavilla = ?
            WHERE id = ?
        ";

        $update_stmt = $conn->prepare($update_sql);
        if ($update_stmt) {
            $update_stmt->bind_param(
                "ssssisiii",
                $nimi,
                $tekija,
                $isbn,
                $kategoria,
                $julkaisuvuosi,
                $kustantaja,
                $kokonaismaara,
                $new_saatavilla,
                $book_id
            );

            if ($update_stmt->execute()) {
                $success = "Kirjan tiedot päivitetty onnistuneesti!";
                $book = array_merge($book, [
                    'nimi' => $nimi,
                    'tekija' => $tekija,
                    'isbn' => $isbn,
                    'kategoria' => $kategoria,
                    'julkaisuvuosi' => $julkaisuvuosi,
                    'kustantaja' => $kustantaja,
                    'kokonaismaara' => $kokonaismaara,
                    'saatavilla' => $new_saatavilla
                ]);
            } else {
                $error = "Virhe päivityksessä: " . htmlspecialchars($update_stmt->error);
            }
            $update_stmt->close();
        } else {
            $error = "Tietokantavirhe: " . htmlspecialchars($conn->error);
        }
    }
}

/*
|--------------------------------------------------------------------------
| LIST MODE
|--------------------------------------------------------------------------
*/
if ($mode === 'list') {
    $check_columns = $conn->query("SHOW COLUMNS FROM kirjat LIKE 'kokonaismaara'");
    $has_kokonaismaara = $check_columns && $check_columns->num_rows > 0;

    $check_columns2 = $conn->query("SHOW COLUMNS FROM kirjat LIKE 'saatavilla'");
    $has_saatavilla = $check_columns2 && $check_columns2->num_rows > 0;

    if ($has_kokonaismaara && $has_saatavilla) {
        $stats_sql = "
            SELECT
                COUNT(*) AS total_books,
                SUM(kokonaismaara) AS total_copies,
                SUM(saatavilla) AS available_copies,
                SUM(kokonaismaara) - SUM(saatavilla) AS borrowed_copies,
                COUNT(DISTINCT kategoria) AS categories
            FROM kirjat
        ";
    } else {
        $stats_sql = "
            SELECT
                COUNT(*) AS total_books,
                COUNT(DISTINCT kategoria) AS categories
            FROM kirjat
        ";
    }

    $stats_result = $conn->query($stats_sql);
    $stats = $stats_result ? $stats_result->fetch_assoc() : [];

    if ($has_kokonaismaara && $has_saatavilla) {
        $books_query = "
            SELECT id, nimi, tekija, isbn, kategoria, kokonaismaara, saatavilla
            FROM kirjat
            ORDER BY nimi
            LIMIT 100
        ";
    } else {
        $books_query = "
            SELECT id, nimi, tekija, isbn, kategoria
            FROM kirjat
            ORDER BY nimi
            LIMIT 100
        ";
    }

    $books_result = $conn->query($books_query);
    if ($books_result) {
        while ($row = $books_result->fetch_assoc()) {
            $books[] = [
                'id' => (int)$row['id'],
                'nimi' => htmlspecialchars($row['nimi'] ?? ''),
                'tekija' => htmlspecialchars($row['tekija'] ?? ''),
                'isbn' => htmlspecialchars($row['isbn'] ?? ''),
                'kategoria' => htmlspecialchars($row['kategoria'] ?? ''),
                'kokonaismaara' => isset($row['kokonaismaara']) ? (int)$row['kokonaismaara'] : null,
                'saatavilla' => isset($row['saatavilla']) ? (int)$row['saatavilla'] : null
            ];
        }
    }
}

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/
function getInitials($name) {
    if (empty($name)) return 'A';
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2);
}

function getProfileImageUrl($profile_image, $user_name) {
    if (empty($user_name)) {
        $user_name = "User";
    }

    if (empty($profile_image) || $profile_image === 'null') {
        return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
    }

    if (filter_var($profile_image, FILTER_VALIDATE_URL)) {
        return $profile_image;
    }

    if (file_exists($profile_image)) {
        return $profile_image;
    }

    $profile_path = 'uploads/profiles/' . $profile_image;
    if (file_exists($profile_path)) {
        return $profile_path;
    }

    $filename = basename($profile_image);
    $profile_path = 'uploads/profiles/' . $filename;
    if (file_exists($profile_path)) {
        return $profile_path;
    }

    if (file_exists($filename)) {
        return $filename;
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=667eea&color=fff&size=128&bold=true&length=2';
}

$user_name = isset($user['etunimi']) && !empty($user['etunimi']) ?
    trim($user['etunimi'] . ' ' . ($user['sukunimi'] ?? '')) :
    trim($user['kayttajanimi'] ?? 'Ylläpitäjä');

$user_initials = getInitials($user_name);

$db_role = strtolower(trim($user['rooli'] ?? ''));
if (in_array($db_role, ['admin', 'administrator', 'superadmin', 'ylläpitäjä'])) {
    $user_role = 'Ylläpitäjä';
} else {
    $user_role = ucfirst($user['rooli'] ?? 'Ylläpitäjä');
}

$profile_image_url = getProfileImageUrl($user['profile_image'] ?? '', $user_name);

$display_name = htmlspecialchars($user_name);
$display_email = htmlspecialchars($user['email'] ?? 'admin@example.com');
$display_role = htmlspecialchars($user_role);
$display_permissions = 'Täydet järjestelmäoikeudet';
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muokkaa Kirjaa | Admin | Kirjasto</title>
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
        background: rgba(255,255,255,0.05);
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

    /* ============================================
       CONTENT CARD
       ============================================ */
    .content-card {
        background: rgba(255,255,255,0.08);
        backdrop-filter: blur(10px);
        border-radius: 24px;
        padding: 25px;
        margin-bottom: 30px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #667eea;
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

    /* ============================================
       FORM STYLES
       ============================================ */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
        margin-bottom: 25px;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-label {
        display: block;
        color: #94a3b8;
        font-size: 0.75rem;
        margin-bottom: 8px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .required::after {
        content: " *";
        color: #ef4444;
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

    .form-hint {
        display: block;
        margin-top: 6px;
        font-size: 0.7rem;
        color: #64748b;
    }

    /* INFO BOX */
    .info-box {
        background: rgba(0,0,0,0.2);
        padding: 15px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.05);
        margin-bottom: 25px;
    }

    .info-box p {
        color: #94a3b8;
        font-size: 0.8rem;
        margin-bottom: 5px;
    }

    /* STATUS BADGE */
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .status-available {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }

    .status-warning {
        background: rgba(245, 158, 11, 0.2);
        color: #f59e0b;
    }

    .status-borrowed {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
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

    .button-group {
        display: flex;
        gap: 15px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid rgba(255,255,255,0.1);
        flex-wrap: wrap;
    }

    /* ============================================
       TABLE WITH HORIZONTAL SCROLL ONLY
       ============================================ */
    /* TABLE WRAPPER - ONLY HORIZONTAL SCROLL WHEN NEEDED */
    .table-wrapper {
        overflow-x: auto;
        margin-top: 15px;
        width: 100%;
    }

    /* Scrollbar styling for the table wrapper */
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
        min-width: 1000px; /* Forces horizontal scroll on smaller screens */
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
        white-space: nowrap;
    }

    .books-table td {
        padding: 15px;
        color: #cbd5e1;
        font-size: 0.85rem;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        vertical-align: middle;
        white-space: nowrap;
    }

    .books-table tr:hover td {
        background: rgba(255,255,255,0.05);
    }

    .book-title {
        color: white;
        font-weight: 600;
    }

    .book-author {
        color: #94a3b8;
        font-size: 0.8rem;
    }

    /* TABLE ACTIONS - BUTTONS IN ONE LINE */
    .table-actions {
        display: flex;
        gap: 8px;
        flex-wrap: nowrap;
        white-space: nowrap;
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
        .form-grid {
            grid-template-columns: 1fr;
        }
        .header {
            flex-direction: column;
            text-align: center;
        }
        .button-group {
            flex-direction: column;
        }
        .button-group .btn {
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
            <h4><?php echo htmlspecialchars($display_name); ?></h4>
            <p><?php echo htmlspecialchars($display_role); ?></p>
        </div>
    </a>

    <div class="sidebar-menu">
        <div class="menu-section">⚙️ Päävalikko</div>
        <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i><span>Kojelauta</span></a>

        <div class="menu-section">📚 Kirjaston Hallinta</div>
        <a href="admin_manage_kirjat.php" class="menu-item"><i class="fas fa-book"></i><span>Hallinnoi Kirjoja</span></a>
        <a href="admin_lisaa_kirja.php" class="menu-item"><i class="fas fa-plus"></i><span>Lisää Kirja</span></a>
        <a href="admin_muokkaa_kirjaa.php" class="menu-item active"><i class="fas fa-edit"></i><span>Muokkaa Kirjoja</span></a>

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

    <!-- HEADER -->
    <div class="header">
        <div class="page-title">
            <h1><i class="fas fa-edit"></i> <?php echo $mode == 'edit' && !empty($book) ? 'Muokkaa Kirjaa' : 'Valitse Muokattava Kirja'; ?></h1>
            <p><i class="fas fa-book"></i> Hallinnoi ja muokkaa kirjaston kokoelman tietoja</p>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($display_name); ?></h3>
                <p style="color: #94a3b8;"><i class="fas fa-envelope" style="color: #667eea;"></i> <?php echo htmlspecialchars($display_email); ?></p>
                <p style="color: #10b981;"><i class="fas fa-shield-alt" style="color: #10b981;"></i> <?php echo htmlspecialchars($display_role); ?></p>
                <p style="color: #f59e0b;"><i class="fas fa-key" style="color: #f59e0b;"></i> <?php echo htmlspecialchars($display_permissions); ?></p>
            </div>
        </div>
    </div>

    <!-- NOTIFICATIONS -->
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

    <?php if ($mode == 'edit' && !empty($book)): ?>
        <!-- EDIT MODE -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Kokonaismäärä</h3>
                        <div class="stat-number"><?php echo isset($book['kokonaismaara']) ? $book['kokonaismaara'] : 0; ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-copy"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Saatavilla</h3>
                        <div class="stat-number"><?php echo isset($book['saatavilla']) ? $book['saatavilla'] : 0; ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Lainassa</h3>
                        <div class="stat-number">
                            <?php
                            $kokonaismaara = isset($book['kokonaismaara']) ? $book['kokonaismaara'] : 0;
                            $saatavilla = isset($book['saatavilla']) ? $book['saatavilla'] : 0;
                            echo $kokonaismaara - $saatavilla;
                            ?>
                        </div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-handshake"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Saatavuus</h3>
                        <?php
                        $kokonaismaara = isset($book['kokonaismaara']) ? $book['kokonaismaara'] : 0;
                        $saatavilla = isset($book['saatavilla']) ? $book['saatavilla'] : 0;
                        $percentage = $kokonaismaara > 0 ? round(($saatavilla / $kokonaismaara) * 100) : 0;
                        ?>
                        <div class="stat-number"><?php echo $percentage; ?>%</div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="content-card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-book-open"></i> Kirjan tiedot</div>
                <?php
                $saatavilla = isset($book['saatavilla']) ? $book['saatavilla'] : 0;
                $status_class = $saatavilla > 0 ? 'status-available' : 'status-borrowed';
                $status_text = $saatavilla > 0 ? 'Saatavilla' : 'Ei saatavilla';
                ?>
                <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </div>

            <form method="POST" action="" id="editForm">
                <div class="form-grid">
                    <div>
                        <div class="form-group">
                            <label class="form-label required">Kirjan nimi</label>
                            <input type="text" id="nimi" name="nimi" class="form-control"
                                   value="<?php echo isset($book['nimi']) ? htmlspecialchars($book['nimi']) : ''; ?>" required>
                            <div class="form-hint">Kirjan koko nimi</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Kirjailija</label>
                            <input type="text" id="tekija" name="tekija" class="form-control"
                                   value="<?php echo isset($book['tekija']) ? htmlspecialchars($book['tekija']) : ''; ?>" required>
                            <div class="form-hint">Kirjailijan tai kirjailijoiden nimet</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ISBN-numero</label>
                            <input type="text" id="isbn" name="isbn" class="form-control"
                                   value="<?php echo isset($book['isbn']) ? htmlspecialchars($book['isbn']) : ''; ?>">
                            <div class="form-hint">Voit jättää tyhjäksi</div>
                        </div>
                    </div>
                    <div>
                        <div class="form-group">
                            <label class="form-label">Kategoria</label>
                            <input type="text" id="kategoria" name="kategoria" class="form-control"
                                   value="<?php echo isset($book['kategoria']) ? htmlspecialchars($book['kategoria']) : ''; ?>"
                                   list="kategoriat-list">
                            <?php if (!empty($kategoriat)): ?>
                            <datalist id="kategoriat-list">
                                <?php foreach ($kategoriat as $kat): ?>
                                    <option value="<?php echo htmlspecialchars($kat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <?php endif; ?>
                            <div class="form-hint">Kirjoita uusi tai valitse listasta</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Julkaisuvuosi</label>
                            <input type="number" id="julkaisuvuosi" name="julkaisuvuosi" class="form-control"
                                   value="<?php echo !empty($book['julkaisuvuosi']) ? htmlspecialchars($book['julkaisuvuosi']) : ''; ?>"
                                   min="1000" max="<?php echo date('Y'); ?>">
                            <div class="form-hint">Esimerkiksi: 2023</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kustantaja</label>
                            <input type="text" id="kustantaja" name="kustantaja" class="form-control"
                                   value="<?php echo isset($book['kustantaja']) ? htmlspecialchars($book['kustantaja']) : ''; ?>">
                            <div class="form-hint">Kirjan julkaisija</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Kokonaismäärä</label>
                            <input type="number" id="kokonaismaara" name="kokonaismaara" class="form-control"
                                   value="<?php echo isset($book['kokonaismaara']) ? $book['kokonaismaara'] : 1; ?>"
                                   min="1" max="1000" required>
                            <div class="form-hint">Kirjojen kokonaismäärä varastossa</div>
                        </div>
                    </div>
                </div>

                <div class="info-box">
                    <p><i class="fas fa-info-circle" style="color: #667eea;"></i> <strong>Huomio:</strong> Saatavilla-kenttä päivittyy automaattisesti lainausten perusteella. Nykyinen arvo: <strong><?php echo isset($book['saatavilla']) ? $book['saatavilla'] : 0; ?></strong></p>
                </div>

                <div class="button-group">
                    <button type="submit" name="update_book" class="btn btn-primary">
                        <i class="fas fa-save"></i> Tallenna muutokset
                    </button>
                    <a href="admin_muokkaa_kirjaa.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Takaisin listaukseen
                    </a>
                    <a href="admin_lisaa_kirja.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Lisää uusi kirja
                    </a>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- LIST MODE -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Kirjoja yhteensä</h3>
                        <div class="stat-number"><?php echo isset($stats['total_books']) ? $stats['total_books'] : 0; ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Kappaleita</h3>
                        <div class="stat-number"><?php echo isset($stats['total_copies']) ? $stats['total_copies'] : 0; ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-copy"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Saatavilla</h3>
                        <div class="stat-number"><?php echo isset($stats['available_copies']) ? $stats['available_copies'] : 0; ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Lainassa</h3>
                        <div class="stat-number"><?php echo isset($stats['borrowed_copies']) ? $stats['borrowed_copies'] : 0; ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-handshake"></i></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Kategorioita</h3>
                        <div class="stat-number"><?php echo isset($stats['categories']) ? $stats['categories'] : 0; ?></div>
                    </div>
                    <div class="stat-icon"><i class="fas fa-tags"></i></div>
                </div>
            </div>
        </div>

        <!-- Books List -->
        <div class="content-card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-list"></i> Valitse muokattava kirja (<?php echo count($books); ?> kpl)</div>
                <a href="admin_lisaa_kirja.php" class="btn btn-success"><i class="fas fa-plus"></i> Lisää uusi kirja</a>
            </div>

            <?php if (empty($books)): ?>
                <div class="empty-state">
                    <i class="fas fa-book"></i>
                    <p>Ei kirjoja löytynyt</p>
                    <a href="admin_lisaa_kirja.php" class="btn btn-primary" style="margin-top: 15px;">Lisää ensimmäinen kirja</a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="books-table">
                        <thead>
                            <tr><th>Kirjan nimi</th><th>Kirjailija</th><th>Kategoria</th><th>Kappaleet</th><th>Tila</th><th>Toiminnot</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book_item):
                                $kokonaismaara = isset($book_item['kokonaismaara']) ? $book_item['kokonaismaara'] : 0;
                                $saatavilla = isset($book_item['saatavilla']) ? $book_item['saatavilla'] : 0;
                                $available_percentage = $kokonaismaara > 0 ? ($saatavilla / $kokonaismaara) * 100 : 0;
                                if ($available_percentage > 50) {
                                    $status_class = 'status-available';
                                    $status_text = 'Saatavilla';
                                } elseif ($available_percentage > 0) {
                                    $status_class = 'status-warning';
                                    $status_text = 'Vähissä';
                                } else {
                                    $status_class = 'status-borrowed';
                                    $status_text = 'Ei saatavilla';
                                }
                            ?>
                            <tr>
                                <td class="book-title"><?php echo htmlspecialchars($book_item['nimi']); ?></td>
                                <td class="book-author"><?php echo htmlspecialchars($book_item['tekija']); ?></td>
                                <td><?php echo htmlspecialchars($book_item['kategoria']); ?></td>
                                <td><?php echo $saatavilla; ?>/<?php echo $kokonaismaara; ?></td>
                                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td class="table-actions">
                                    <a href="admin_muokkaa_kirjaa.php?id=<?php echo $book_item['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Muokkaa</a>
                                    <a href="nayta_kirja.php?id=<?php echo $book_item['id']; ?>" class="btn btn-warning btn-sm"><i class="fas fa-eye"></i> Näytä</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($mode == 'edit' && !empty($book)): ?>
            document.getElementById('nimi').focus();
        <?php endif; ?>

        // ISBN formatting
        const isbnInput = document.getElementById('isbn');
        if (isbnInput) {
            isbnInput.addEventListener('blur', function() {
                let isbn = this.value.replace(/[-\s]/g, '');
                if (isbn.length === 13 && /^\d+$/.test(isbn)) {
                    this.value = isbn.replace(/^(\d{3})(\d{3})(\d{5})(\d{1})(\d{1})$/, '$1-$2-$3-$4-$5');
                } else if (isbn.length === 10 && /^[\dX]+$/i.test(isbn)) {
                    this.value = isbn.replace(/^(\d{1,5})(\d{1,7})(\d{1,6})([\dX])$/i, '$1-$2-$3-$4');
                }
            });
        }

        // Form validation
        const form = document.getElementById('editForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const nimi = document.getElementById('nimi').value.trim();
                const tekija = document.getElementById('tekija').value.trim();
                const kokonaismaara = document.getElementById('kokonaismaara').value;

                if (!nimi) {
                    e.preventDefault();
                    alert('Kirjan nimi on pakollinen kenttä!');
                    document.getElementById('nimi').focus();
                    return false;
                }
                if (!tekija) {
                    e.preventDefault();
                    alert('Kirjailijan nimi on pakollinen kenttä!');
                    document.getElementById('tekija').focus();
                    return false;
                }
                if (!kokonaismaara || kokonaismaara < 1) {
                    e.preventDefault();
                    alert('Kokonaismäärän tulee olla vähintään 1!');
                    document.getElementById('kokonaismaara').focus();
                    return false;
                }
            });
        }

        // Auto-hide notifications
        setTimeout(function() {
            document.querySelectorAll('.notification').forEach(function(n) {
                n.style.opacity = '0';
                n.style.transform = 'translateY(-15px)';
                n.style.transition = 'all 0.3s ease';
                setTimeout(function() { n.style.display = 'none'; }, 300);
            });
        }, 5000);

        // Intersection Observer for animations
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

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const submitBtn = document.querySelector('button[name="update_book"]');
                if (submitBtn) submitBtn.click();
            }
            if (e.key === 'Escape') {
                window.location.href = 'admin_muokkaa_kirjaa.php';
            }
        });
    });
</script>

</body>
</html>
