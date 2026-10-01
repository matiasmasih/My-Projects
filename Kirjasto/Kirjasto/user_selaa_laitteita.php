<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info - MUISTA HAKIA 'rooli'!
$user_query = "SELECT etunimi, sukunimi, profile_image, jasennumero, rooli FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// If user is admin or manager, redirect to their dashboard
if ($user['rooli'] == 'admin') {
    header("Location: admin_dashboard.php");
    exit();
} elseif ($user['rooli'] == 'manager') {
    header("Location: manager_dashboard.php");
    exit();
}

$current_page = basename($_SERVER["PHP_SELF"]);
// Get user initials for avatar
$initials = strtoupper(substr($user['etunimi'] ?? '', 0, 1) . substr($user['sukunimi'] ?? '', 0, 1));

// Create membership number
$membership_number = isset($user['jasennumero']) && !empty($user['jasennumero'])
    ? $user['jasennumero']
    : 'JASEN-' . str_pad($user_id, 6, '0', STR_PAD_LEFT);

// Get unread messages count
$unread_messages_count = 0;
try {
    $unread_query = "SELECT COUNT(*) as count FROM viestit WHERE vastaanottaja_id = ? AND luettu = 0";
    $stmt = $conn->prepare($unread_query);
              $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unread_result = $stmt->get_result();
    $unread_data = $unread_result->fetch_assoc();
    $unread_messages_count = $unread_data['count'] ?? 0;
} catch (Exception $e) {
    error_log("Unread messages error: " . $e->getMessage());
}

// 🔥 LISÄTTY TILASTOT LAITTEISTA 🔥
// Get available devices count
$available_devices_count = 0;
try {
    $available_query = "SELECT COUNT(*) as count FROM Laitteet WHERE tila = 'saatavilla'";
    $available_result = $conn->query($available_query);
    if ($available_result) {
        $available_devices_count = $available_result->fetch_assoc()['count'];
    }
} catch (Exception $e) {
    error_log("Available devices count error: " . $e->getMessage());
}

// Get loaned devices count
$loaned_devices_count = 0;
try {
    $loaned_query = "SELECT COUNT(*) as count FROM Laitteet WHERE tila = 'lainassa'";
    $loaned_result = $conn->query($loaned_query);
    if ($loaned_result) {
        $loaned_devices_count = $loaned_result->fetch_assoc()['count'];
    }
} catch (Exception $e) {
    error_log("Loaned devices count error: " . $e->getMessage());
}

// Get maintenance devices count
$maintenance_devices_count = 0;
try {
    $maintenance_query = "SELECT COUNT(*) as count FROM Laitteet WHERE tila = 'huoltotila'";
    $maintenance_result = $conn->query($maintenance_query);
    if ($maintenance_result) {
        $maintenance_devices_count = $maintenance_result->fetch_assoc()['count'];
    }
} catch (Exception $e) {
    error_log("Maintenance devices count error: " . $e->getMessage());
}

// Get total devices count
$total_devices_count = 0;
try {
    $total_query = "SELECT COUNT(*) as count FROM Laitteet";
    $total_result = $conn->query($total_query);
    if ($total_result) {
        $total_devices_count = $total_result->fetch_assoc()['count'];
    }
} catch (Exception $e) {
    error_log("Total devices count error: " . $e->getMessage());
}

// Get current filter status
$current_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$devices_query = "SELECT l.*, lt.nimi as tyyppi_nimi
                  FROM Laitteet l
                  LEFT JOIN Laitetyypit lt ON l.laite_tyyppi_id = lt.id";

$where_clauses = [];
$params = [];
$types = "";

// Add status filter
if ($current_status != 'all') {
    $where_clauses[] = "l.tila = ?";
    $params[] = $current_status;
    $types .= "s";
}

// Add search filter
if (!empty($search)) {
    $where_clauses[] = "(l.merkki LIKE ? OR l.malli LIKE ? OR lt.nimi LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

// Add WHERE clause if needed
if (!empty($where_clauses)) {
    $devices_query .= " WHERE " . implode(" AND ", $where_clauses);
}

$devices_query .= " ORDER BY
    CASE
        WHEN l.tila = 'saatavilla' THEN 1
        WHEN l.tila = 'lainassa' THEN 2
        ELSE 3
    END,
    l.merkki ASC";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($devices_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $devices_result = $stmt->get_result();
} else {
    $devices_result = $conn->query($devices_query);
}
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selaa Laitteita | <?php echo htmlspecialchars($user['etunimi']); ?> <?php echo htmlspecialchars($user['sukunimi']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    min-height: 100vh;
    display: flex;
    position: relative;
    background-color: #0a0c10;
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
    filter: brightness(0.25) blur(4px);
    z-index: -1;
}

:root {
    --bg-primary: rgba(18, 22, 28, 0.85);
    --bg-secondary: rgba(26, 32, 39, 0.9);
    --bg-card: rgba(30, 36, 45, 0.85);
    --bg-sidebar: rgba(13, 17, 23, 0.95);
    --text-primary: #ffffff;
    --text-secondary: #b0b8c5;
    --text-muted: #94a3b8;
    --border-color: rgba(255, 255, 255, 0.08);
    --gradient-1: linear-gradient(135deg, #667eea, #764ba2);
    --gradient-2: linear-gradient(135deg, #f093fb, #f5576c);
    --gradient-3: linear-gradient(135deg, #4facfe, #00f2fe);
    --gradient-4: linear-gradient(135deg, #43e97b, #38f9d7);
    --gradient-5: linear-gradient(135deg, #fa709a, #fee140);
    --shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
    --shadow-hover: 0 15px 40px rgba(0, 0, 0, 0.6);
    --glow: 0 0 20px rgba(102, 126, 234, 0.3);
}

.sidebar {
    width: 280px;
    background: var(--bg-sidebar);
    backdrop-filter: blur(10px);
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    box-shadow: var(--shadow);
    overflow-y: auto;
    z-index: 1000;
    border-right: 1px solid var(--border-color);
}

.sidebar-header {
    padding: 30px 24px;
    border-bottom: 1px solid var(--border-color);
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
}

.logo-icon {
    width: 45px;
    height: 45px;
    background: var(--gradient-1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.4rem;
    box-shadow: var(--glow);
}

.logo-text h2 {
    font-size: 1.4rem;
    font-weight: 700;
    background: var(--gradient-1);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 2px;
}

.logo-text p {
    font-size: 0.7rem;
    color: var(--text-muted);
    letter-spacing: 0.5px;
}

.user-profile-mini {
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-bottom: 1px solid var(--border-color);
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.user-profile-mini:hover {
    background: rgba(255, 255, 255, 0.05);
    transform: translateX(5px);
}

.avatar-mini {
    width: 55px;
    height: 55px;
    border-radius: 14px;
    overflow: hidden;
    background: var(--gradient-1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.3rem;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    border: 2px solid rgba(255, 255, 255, 0.1);
}

.avatar-mini img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-info-mini h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
}

.user-info-mini p {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
}

.user-info-mini p i {
    font-size: 0.7rem;
    color: #10b981;
}

.sidebar-menu {
    padding: 20px 16px;
}

.menu-section {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-muted);
    padding: 20px 16px 8px;
    letter-spacing: 0.5px;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    color: var(--text-secondary);
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.3s;
    margin: 4px 0;
    font-weight: 500;
    font-size: 0.9rem;
}

.menu-item i {
    width: 22px;
    font-size: 1.1rem;
    color: var(--text-muted);
    transition: all 0.3s;
}

.menu-item:hover {
    background: rgba(255, 255, 255, 0.05);
    color: white;
    transform: translateX(5px);
}

.menu-item:hover i {
    color: #667eea;
}

.menu-item.active {
    background: var(--gradient-1);
    color: white;
    box-shadow: 0 10px 20px -5px rgba(102, 126, 234, 0.3);
}

.menu-item.active i {
    color: white;
}

.logout-item {
    margin-top: 30px;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.logout-item i {
    color: #ef4444;
}

.logout-item:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.logout-item:hover i {
    color: #ef4444;
}

.main-content {
    flex: 1;
    margin-left: 280px;
    padding: 30px 40px;
}

.top-bar {
    background: var(--bg-card);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 20px 30px;
    margin-bottom: 30px;
    box-shadow: var(--shadow);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid var(--border-color);
}

.page-title h1 {
    font-size: 1.8rem;
    font-weight: 700;
    background: var(--gradient-1);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 5px;
}

.page-title p {
    color: var(--text-muted);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 5px;
}

.page-title p i {
    font-size: 0.5rem;
    color: #10b981;
}

.top-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.date-badge {
    background: rgba(255, 255, 255, 0.05);
    padding: 8px 16px;
    border-radius: 30px;
    font-weight: 500;
    color: var(--text-secondary);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid var(--border-color);
}

.date-badge i {
    color: #667eea;
}

.notification-icon {
    position: relative;
    width: 45px;
    height: 45px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s;
    border: 1px solid var(--border-color);
}

.notification-icon:hover {
    background: var(--gradient-1);
    color: white;
    transform: rotate(5deg);
}

.badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    font-size: 0.65rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    border: 2px solid var(--bg-card);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-card);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
    border-color: #667eea;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: var(--gradient-1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.3rem;
}

.stat-icon.success {
    background: var(--gradient-4);
}

.stat-icon.warning {
    background: var(--gradient-5);
}

.stat-icon.info {
    background: var(--gradient-3);
}

.stat-info h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.2;
}

.stat-info p {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.filter-section {
    margin-bottom: 20px;
}

.filter-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filter-btn {
    padding: 10px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 30px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.filter-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: #667eea;
}

.filter-btn.active {
    background: var(--gradient-1);
    color: white;
    border-color: transparent;
}

.search-section {
    background: var(--bg-card);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 20px 25px;
    margin-bottom: 30px;
    border: 1px solid var(--border-color);
}

.search-form {
    display: flex;
    gap: 15px;
    align-items: center;
}

.search-input {
    flex: 1;
    padding: 14px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 30px;
    color: var(--text-primary);
    font-size: 1rem;
}

.search-input:focus {
    outline: none;
    border-color: #667eea;
}

.search-btn {
    padding: 14px 30px;
    background: var(--gradient-1);
    border: none;
    border-radius: 30px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-btn:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

/* ========================================
   FIXED DEVICES GRID - ALL IN ONE CARD!
   ======================================== */

.devices-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    align-items: start;
}

.device-card {
    background: var(--bg-card);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    overflow: hidden; /* Keep this hidden for the card itself */
    border: 1px solid var(--border-color);
    transition: all 0.3s;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    flex-direction: column;
}

.device-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
    border-color: #667eea;
}

.device-image {
    height: 140px;
    background: var(--gradient-1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2.8rem;
    position: relative;
    flex-shrink: 0;
    border-radius: 20px 20px 0 0;
    overflow: hidden;
}

.device-type {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.6);
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.65rem;
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.device-content {
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    border-radius: 0 0 20px 20px;
    overflow: visible; /* CRITICAL FIX: Change from hidden to visible */
}

.device-content > div {
    margin: 0;
    padding: 0;
}

.device-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 2px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}

.device-model {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}

.device-specs {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin: 0;
    padding: 0;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.device-specs span {
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
}

.device-specs i {
    color: #667eea;
    width: 14px;
    font-size: 0.7rem;
    flex-shrink: 0;
}

.device-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 500;
    margin-bottom: 4px;
    width: fit-content;
}

.status-available {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-loaned {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-maintenance {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.device-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0 0 0; /* Remove horizontal padding */
    margin: 8px 0 0 0;
    border-top: 1px solid var(--border-color);
    gap: 8px;
    flex-wrap: wrap;
    overflow: visible; /* Ensure this doesn't clip */
}

.condition-badge {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    flex-shrink: 0;
}

.action-buttons {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-shrink: 0;
    overflow: visible; /* Ensure buttons are fully visible */
}

.btn-favorite {
    background: transparent;
    border: 1px solid var(--border-color);
    color: #ef4444;
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.3s;
    cursor: pointer;
}

.btn-favorite:hover {
    background: #ef4444;
    color: white;
    border-color: #ef4444;
}

.btn-favorite:hover i {
    color: white;
}

.btn-favorite-circle {
    width: 28px;
    height: 28px;
    background: transparent;
    border: 1px solid #ef4444;
    border-radius: 50%;
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.3s;
    flex-shrink: 0;
    font-size: 0.65rem;
    z-index: 10; /* Ensure it's above other elements */
    position: relative; /* For z-index to work */
}

.btn-favorite-circle:hover {
    background: #ef4444;
    color: white;
    transform: scale(1.1);
}

.btn-reserve {
    padding: 4px 10px;
    background: var(--gradient-1);
    color: white;
    text-decoration: none;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.3s;
    white-space: nowrap;
    border: none;
    flex-shrink: 0;
    z-index: 10; /* Ensure it's above other elements */
    position: relative; /* For z-index to work */
}

.btn-reserve:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-reserve.disabled {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-muted);
    pointer-events: none;
    cursor: default;
}

.no-results {
    grid-column: 1/-1;
    text-align: center;
    padding: 60px;
    color: var(--text-muted);
    background: var(--bg-card);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    border: 1px solid var(--border-color);
}

.no-results i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #667eea;
}

@media (max-width: 1400px) {
    .devices-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 1100px) {
    .devices-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .search-form {
        flex-direction: column;
    }
    .search-btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .devices-grid {
        grid-template-columns: 1fr;
    }
    .main-content {
        margin-left: 0;
        padding: 20px;
    }
    .top-bar {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    .filter-buttons {
        justify-content: center;
    }
    .device-meta {
        flex-wrap: wrap;
        justify-content: center;
    }
}

/* FORCE FIX - Add this at the very end of your CSS */
.device-meta {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
    width: 100% !important;
}

.condition-badge {
    flex-shrink: 0 !important;
    white-space: nowrap !important;
}

.action-buttons {
    flex-shrink: 0 !important;
    margin-left: auto !important;
    display: flex !important;
    gap: 6px !important;
}
</style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="logo-text">
                    <h2>Kirjasto</h2>
                    <p>Lukemisen iloa</p>
                </div>
            </div>
        </div>

        <!-- Profile Mini -->
        <a href="user_profile.php" class="user-profile-mini">
            <div class="avatar-mini">
                <?php if (!empty($user['profile_image']) && file_exists("uploads/profiles/" . $user['profile_image'])): ?>
                    <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profiilikuva">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="user-info-mini">
                <h4><?php echo htmlspecialchars($user['etunimi'] . ' ' . $user['sukunimi']); ?></h4>
                <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($membership_number); ?></p>
            </div>
        </a>

        <!-- Navigation Menu -->

        <div class="sidebar-menu">
            <div class="menu-section">📊 Päävalikko</div>
            <a href="user_dashboard.php" class="menu-item <?php echo $current_page == 'user_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>

            <div class="menu-section">📚 Kirjat</div>
            <a href="user_selaa_kirjoja.php" class="menu-item <?php echo $current_page == 'user_selaa_kirjoja.php' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> <span>Selaa kirjoja</span>
            </a>
            <a href="user_lainaa_kirja.php" class="menu-item <?php echo $current_page == 'user_lainaa_kirja.php' ? 'active' : ''; ?>">
                <i class="fas fa-hand-holding-heart"></i> <span>Lainaa kirja</span>
            </a>
            <a href="user_lainahistoria.php" class="menu-item <?php echo $current_page == 'user_lainahistoria.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> <span>Lainahistoria</span>
            </a>
            <a href="user_oma_varaukset.php" class="menu-item <?php echo $current_page == 'user_oma_varaukset.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> <span>Omat varaukset</span>
            </a>
            <a href="user_suosikit.php" class="menu-item <?php echo $current_page == 'user_suosikit.php' ? 'active' : ''; ?>">
                <i class="fas fa-heart"></i> <span>Suosikit</span>
            </a>

            <div class="menu-section">💻 Laitteet</div>
            <a href="user_selaa_laitteita.php" class="menu-item <?php echo $current_page == 'user_selaa_laitteita.php' ? 'active' : ''; ?>">
                <i class="fas fa-laptop"></i> <span>Selaa laitteita</span>
            </a>
            <a href="user_laitelainat.php" class="menu-item <?php echo $current_page == 'user_laitelainat.php' ? 'active' : ''; ?>">
                <i class="fas fa-microchip"></i> <span>Laitelainat</span>
            </a>

            <div class="menu-section">💰 Talous</div>
            <a href="user_sakot.php" class="menu-item <?php echo $current_page == 'user_sakot.php' ? 'active' : ''; ?>">
                <i class="fas fa-euro-sign"></i> <span>Sakot</span>
            </a>
            <a href="user_kuitit.php" class="menu-item <?php echo $current_page == 'user_kuitit.php' ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> <span>Kuitit</span>
            </a>

            <div class="menu-section">👤 Oma tili</div>
            <a href="user_profile.php" class="menu-item <?php echo $current_page == 'user_profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i> <span>Profiili</span>
            </a>
            <a href="user_yhteystiedot.php" class="menu-item <?php echo $current_page == 'user_yhteystiedot.php' ? 'active' : ''; ?>">
                <i class="fas fa-address-card"></i> <span>Yhteystiedot</span>
            </a>
            <a href="salasana.php" class="menu-item <?php echo $current_page == 'salasana.php' ? 'active' : ''; ?>">
                <i class="fas fa-key"></i> <span>Vaihda salasana</span>
            </a>
            <a href="user_kayttoehdot.php" class="menu-item <?php echo $current_page == 'user_kayttoehdot.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-contract"></i> <span>Käyttöehdot</span>
            </a>
            <a href="user_ilmoitukset.php" class="menu-item <?php echo $current_page == 'user_ilmoitukset.php' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i> <span>Ilmoitukset</span>
                <?php if ($unread_messages_count > 0): ?>
                    <span class="badge"><?php echo $unread_messages_count; ?></span>
                <?php endif; ?>
            </a>

            <div class="menu-section"></div>
            <a href="logout.php" class="menu-item logout-item">
                <i class="fas fa-sign-out-alt"></i> <span>Kirjaudu ulos</span>
            </a>
        </div>
    </div>
    <!-- ========== SIDEBAR END ========== -->

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Selaa Laitteita</h1>
                <p><i class="fas fa-circle"></i> Kaikki kirjaston laitteet</p>
            </div>
            <div class="top-actions">
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo date('j. F Y'); ?>
                </div>
                <div class="notification-icon">
                    <i class="far fa-bell"></i>
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="badge"><?php echo $unread_messages_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-laptop"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_devices_count; ?></h3>
                    <p>Laitteita yhteensä</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $available_devices_count; ?></h3>
                    <p>Saatavilla</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $loaned_devices_count; ?></h3>
                    <p>Lainassa</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $maintenance_devices_count; ?></h3>
                    <p>Huollossa</p>
                </div>
            </div>
        </div>

        <!-- Filter buttons -->
        <div class="filter-section">
            <div class="filter-buttons">
                <a href="?status=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                   class="filter-btn <?php echo $current_status == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Kaikki (<?php echo $total_devices_count; ?>)
                </a>
                <a href="?status=saatavilla<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                   class="filter-btn <?php echo $current_status == 'saatavilla' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle" style="color: #10b981;"></i> Saatavilla (<?php echo $available_devices_count; ?>)
                </a>
                <a href="?status=lainassa<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                   class="filter-btn <?php echo $current_status == 'lainassa' ? 'active' : ''; ?>">
                    <i class="fas fa-clock" style="color: #f59e0b;"></i> Lainassa (<?php echo $loaned_devices_count; ?>)
                </a>
                <a href="?status=huoltotila<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                   class="filter-btn <?php echo $current_status == 'huoltotila' ? 'active' : ''; ?>">
                    <i class="fas fa-tools" style="color: #ef4444;"></i> Huollossa (<?php echo $maintenance_devices_count; ?>)
                </a>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form class="search-form" method="GET">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($current_status); ?>">
                <input type="text" name="search" class="search-input"
                       placeholder="Hae merkillä, mallilla tai tyypillä..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Hae
                </button>
            </form>
        </div>
<!-- Devices Grid - COMPLETELY FIXED - ALL IN ONE CARD -->
<div class="devices-grid">
    <?php if ($devices_result && $devices_result->num_rows > 0): ?>
        <?php while ($device = $devices_result->fetch_assoc()):
            // Determine icon based on device type
            $icon = 'fa-laptop';
            $type_lower = strtolower($device['tyyppi_nimi'] ?? '');
            if (strpos($type_lower, 'tabletti') !== false) {
                $icon = 'fa-tablet-alt';
            } elseif (strpos($type_lower, 'puhelin') !== false || strpos($type_lower, 'kännykkä') !== false) {
                $icon = 'fa-mobile-alt';
            } elseif (strpos($type_lower, 'kamera') !== false) {
                $icon = 'fa-camera';
            } elseif (strpos($type_lower, 'projektori') !== false) {
                $icon = 'fa-video';
            }

            // Status class and text
            $status_class = '';
            $status_text = '';
            if ($device['tila'] == 'saatavilla') {
                $status_class = 'status-available';
                $status_text = 'Saatavilla';
            } elseif ($device['tila'] == 'lainassa') {
                $status_class = 'status-loaned';
                $status_text = 'Lainassa';
            } else {
                $status_class = 'status-maintenance';
                $status_text = 'Huollossa';
            }
        ?>
            <!-- SINGLE CARD - All device details in ONE card -->
            <div class="device-card">
                <!-- Image Section -->
                <div class="device-image">
                    <i class="fas <?php echo $icon; ?>"></i>
                    <?php if (!empty($device['tyyppi_nimi'])): ?>
                        <span class="device-type"><?php echo htmlspecialchars($device['tyyppi_nimi']); ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Content Section - ALL details in ONE place -->
                <div class="device-content">
                    <!-- Status Badge (Saatavilla/Lainassa/Huollossa) -->
                    <span class="device-status <?php echo $status_class; ?>">
                        <i class="fas fa-<?php echo $device['tila'] == 'saatavilla' ? 'check-circle' : ($device['tila'] == 'lainassa' ? 'clock' : 'tools'); ?>"></i>
                        <?php echo $status_text; ?>
                    </span>

                    <!-- Brand and Model -->
                    <div class="device-title"><?php echo htmlspecialchars($device['merkki']); ?></div>
                    <div class="device-model"><?php echo htmlspecialchars($device['malli']); ?></div>

                    <!-- Specs -->
                    <?php if (!empty($device['ominaisuudet'])):
                        $features = json_decode($device['ominaisuudet'], true);
                        if (is_array($features) && !empty($features)): ?>
                            <div class="device-specs">
                                <?php
                                $count = 0;
                                $important_keys = ['prosessori', 'ram', 'storage', 'naytto', 'sensor', 'megapixels', 'brightness', 'resolution'];
                                foreach ($features as $key => $value):
                                    if ($count >= 2) break;
                                    if (in_array($key, $important_keys) && !empty($value)):
                                        $display_value = is_string($value) ? $value : '';
                                        if (strlen($display_value) > 15) {
                                            $display_value = substr($display_value, 0, 12) . '...';
                                        }
                                ?>
                                        <span>
                                            <i class="fas fa-<?php
                                                echo $key == 'prosessori' ? 'microchip' :
                                                    ($key == 'ram' ? 'memory' :
                                                    ($key == 'storage' ? 'hdd' :
                                                    ($key == 'naytto' ? 'tv' :
                                                    ($key == 'sensor' ? 'camera' : 'circle-info'))));
                                            ?>"></i>
                                            <?php echo $display_value; ?>
                                        </span>
                                    <?php
                                    $count++;
                                    endif;
                                endforeach;
                                ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Condition and Buttons - TOGETHER in ONE LINE -->
                    <div class="device-meta">
                        <span class="condition-badge">
                            <i class="fas fa-check-circle"></i>
                            <?php echo ucfirst($device['kunto'] ?? 'hyvä'); ?>
                        </span>

                        <div class="action-buttons">
                            <!-- Heart Button -->
                            <a href="user_suosikit.php?add=1&type=laite&id=<?php echo $device['id']; ?>" class="btn-favorite-circle">
                                <i class="fas fa-heart"></i>
                            </a>

                            <!-- Reserve Button -->
                            <?php if ($device['tila'] == 'saatavilla'): ?>
                                <a href="user_varaa_laite.php?id=<?php echo $device['id']; ?>" class="btn-reserve">
                                    <i class="fas fa-calendar-plus"></i> Varaa
                                </a>
                            <?php else: ?>
                                <span class="btn-reserve disabled">
                                    <i class="fas fa-ban"></i> Ei saatavilla
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-laptop"></i>
                    <h3 style="margin-bottom: 10px;">Ei laitteita</h3>
                    <p>Ei laitteita valitulla suodattimella</p>
                    <?php if ($current_status != 'all' || !empty($search)): ?>
                        <a href="user_selaa_laitteita.php" class="btn-reserve" style="margin-top: 20px; display: inline-block;">
                            <i class="fas fa-times"></i> Tyhjennä suodattimet
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    setTimeout(function() {
        var alerts = document.getElementsByClassName('alert');
        for (var i = 0; i < alerts.length; i++) {
            if (alerts[i]) {
                alerts[i].style.transition = 'opacity 0.5s';
                alerts[i].style.opacity = '0';
            }
        }
    }, 5000);
    </script>
</body>
</html>
