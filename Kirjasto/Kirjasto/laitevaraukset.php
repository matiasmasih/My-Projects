<?php
session_start();
require_once 'connection.php';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Tarkista että käyttäjä on kirjautunut
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Hae käyttäjän tiedot
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT rooli, profile_image, etunimi, sukunimi FROM jasenet WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($rooli, $profile_image, $etunimi, $sukunimi);
$stmt->fetch();
$stmt->close();

// Vain manager ja admin pääsevät tänne
if ($rooli !== 'manager' && $rooli !== 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Get user display info
$kayttajan_nimi = $etunimi . ' ' . $sukunimi;
$custom_name = $etunimi . ' ' . $sukunimi;
$custom_email = "matiasmasih@gmail.com"; // Default email
$custom_role_display = $rooli === 'admin' ? "Ylläpitäjä" : "Manager";
$custom_permissions = $rooli === 'admin' ? "Täydet järjestelmäoikeudet" : "Täydet laiteoikeudet";

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

$message = '';
$error = '';
$edit_mode = false;
$edit_data = null;

// Check if Laitevaraukset table exists, create if not
$table_check = $conn->query("SHOW TABLES LIKE 'Laitevaraukset'");
if ($table_check->num_rows == 0) {
    // Create Laitevaraukset table based on your structure

    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS Laitevaraukset (
        id INT PRIMARY KEY AUTO_INCREMENT,
        laite_id INT NOT NULL,
        jasen_id INT NOT NULL,
        tila ENUM('odottaa','vahvistettu','peruttu','täytetty') DEFAULT 'odottaa',
        varaus_paiva DATE NOT NULL,
        noutoaika TIME,
        noutopaiva DATE,
        vanhenee DATETIME,
        luotu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (laite_id) REFERENCES Laitteet(id) ON DELETE CASCADE,
        FOREIGN KEY (jasen_id) REFERENCES jasenet(id) ON DELETE CASCADE,
        INDEX idx_tila (tila),
        INDEX idx_varaus_paiva (varaus_paiva)
    )";

    if ($conn->query($create_table_sql)) {
        $message = "✅ Laitevaraustaulukko luotu onnistuneesti!";
    } else {
        $error = "❌ Virhe luotaessa laitevaraustaulukkoa: " . $conn->error;
    }
}

// Check if Laitteet table exists
$laitteet_check = $conn->query("SHOW TABLES LIKE 'Laitteet'");
$laitteet_exists = $laitteet_check->num_rows > 0;

// Get current date
$current_date = date('Y-m-d');
$current_datetime = date('Y-m-d H:i:s');

// Käsittele lomakkeet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_reservation'])) {
        $laite_id = intval($_POST['laite_id']);
        $jasen_id = intval($_POST['jasen_id']);
        $varaus_paiva = $_POST['varaus_paiva'];
        $noutoaika = !empty($_POST['noutoaika']) ? $_POST['noutoaika'] : null;
        $noutopaiva = !empty($_POST['noutopaiva']) ? $_POST['noutopaiva'] : null;
        // Calculate expiration (2 days from reservation date)
        $vanhenee = date('Y-m-d H:i:s', strtotime($varaus_paiva . ' +2 days'));

        // Check if device exists and is available
        if ($laitteet_exists) {
            $device_check = $conn->prepare("SELECT tila FROM Laitteet WHERE id = ?");
            $device_check->bind_param("i", $laite_id);
            $device_check->execute();
            $device_result = $device_check->get_result();

            if ($device_result->num_rows === 0) {
                $error = "❌ Laitetta ei löydy!";
            } else {
                $device = $device_result->fetch_assoc();
                if ($device['tila'] !== 'saatavilla') {
                    $error = "❌ Laite ei ole saatavilla!";
                } else {
                    // Check for existing reservations on the same date
                    $existing_check = $conn->prepare("
                        SELECT COUNT(*) as reservation_count
                        FROM Laitevaraukset
                        WHERE laite_id = ?
                        AND varaus_paiva = ?
                        AND tila IN ('odottaa', 'vahvistettu')
                    ");
                    $existing_check->bind_param("is", $laite_id, $varaus_paiva);
                    $existing_check->execute();
                    $existing_result = $existing_check->get_result();
                    $existing = $existing_result->fetch_assoc();

                    if ($existing['reservation_count'] > 0) {
                        $error = "❌ Laite on jo varattu tälle päivälle!";
                    } else {
                        $sql = "INSERT INTO Laitevaraukset (laite_id, jasen_id, varaus_paiva, noutoaika, noutopaiva, vanhenee)
                                VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("iissss", $laite_id, $jasen_id, $varaus_paiva, $noutoaika, $noutopaiva, $vanhenee);

                        if ($stmt->execute()) {
                            $message = "🎉 Varaus lisätty onnistuneesti!";
                        } else {
                            $error = "❌ Virhe lisättäessä varausta: " . $conn->error;
                        }
                        $stmt->close();
                    }
                    $existing_check->close();
                }
            }
            $device_check->close();
        } else {
            $error = "❌ Laitetaulu ei ole käytettävissä!";
        }
    }
    elseif (isset($_POST['update_reservation'])) {
        $id = intval($_POST['id']);
        $laite_id = intval($_POST['laite_id']);
        $jasen_id = intval($_POST['jasen_id']);
        $varaus_paiva = $_POST['varaus_paiva'];
        $noutoaika = !empty($_POST['noutoaika']) ? $_POST['noutoaika'] : null;
        $noutopaiva = !empty($_POST['noutopaiva']) ? $_POST['noutopaiva'] : null;

        $sql = "UPDATE Laitevaraukset SET
                laite_id = ?, jasen_id = ?, varaus_paiva = ?, noutoaika = ?, noutopaiva = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssi", $laite_id, $jasen_id, $varaus_paiva, $noutoaika, $noutopaiva, $id);

        if ($stmt->execute()) {
            $message = "✅ Varaus päivitetty onnistuneesti!";
        } else {
            $error = "❌ Virhe päivittäessä varausta: " . $conn->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['update_status'])) {
        $id = intval($_POST['id']);
        $new_status = $_POST['status'];

        if ($new_status === 'vahvistettu') {
            // For confirmed reservations, update device status to 'varattu'
            $sql = "UPDATE Laitevaraukset SET tila = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_status, $id);

            if ($stmt->execute()) {
                // Get device ID from reservation
                $device_sql = "SELECT laite_id FROM Laitevaraukset WHERE id = ?";
                $device_stmt = $conn->prepare($device_sql);
                $device_stmt->bind_param("i", $id);
                $device_stmt->execute();
                $device_result = $device_stmt->get_result();
                $reservation = $device_result->fetch_assoc();
                $device_stmt->close();

                // Update device status
                if ($reservation && $laitteet_exists) {
                    $update_device = "UPDATE Laitteet SET tila = 'varattu' WHERE id = ?";
                    $update_stmt = $conn->prepare($update_device);
                    $update_stmt->bind_param("i", $reservation['laite_id']);
                    $update_stmt->execute();
                    $update_stmt->close();
                }

                $message = "✅ Varaustila päivitetty onnistuneesti! Laite on nyt varattu.";
            } else {
                $error = "❌ Virhe päivittäessä varaustilaa: " . $conn->error;
            }
            $stmt->close();
        } else {
            // For other status updates
            $sql = "UPDATE Laitevaraukset SET tila = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_status, $id);

            if ($stmt->execute()) {
                $message = "✅ Varaustila päivitetty onnistuneesti!";
            } else {
                $error = "❌ Virhe päivittäessä varaustilaa: " . $conn->error;
            }
            $stmt->close();
        }
    }
    elseif (isset($_POST['delete_reservation'])) {
        $id = intval($_POST['id']);

        $sql = "DELETE FROM Laitevaraukset WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = "🗑️ Varaus poistettu onnistuneesti!";
        } else {
            $error = "❌ Virhe poistettaessa: " . $conn->error;
        }
        $stmt->close();
    }
}

// Check if editing
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_sql = "SELECT * FROM Laitevaraukset WHERE id = ?";
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

// Get all members for dropdown
$members = [];
$members_result = $conn->query("SELECT id, etunimi, sukunimi FROM jasenet ORDER BY sukunimi, etunimi");
if ($members_result) {
    while ($member = $members_result->fetch_assoc()) {
        $members[] = $member;
    }
}

// Get all devices for dropdown
$devices = [];
if ($laitteet_exists) {
    $devices_result = $conn->query("SELECT id, sarjanumero, merkki, malli FROM Laitteet WHERE tila = 'saatavilla' ORDER BY merkki, malli");
    if ($devices_result) {
        while ($device = $devices_result->fetch_assoc()) {
            $devices[] = $device;
        }
    }
}

// Get statistics - IMPORTANT: Use the correct variable names that match HTML
$total_reservations = 0;
$pending_reservations = 0;
$approved_reservations = 0; // This was missing!
$rejected_reservations = 0; // This was missing!
$cancelled_reservations = 0;
$completed_reservations = 0;
$upcoming_reservations = 0; // This was missing!
$today_reservations = 0;

// Get all statistics in one query
$stats_result = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN tila = 'odottaa' THEN 1 ELSE 0 END) as odottaa,
        SUM(CASE WHEN tila = 'vahvistettu' THEN 1 ELSE 0 END) as vahvistettu,
        SUM(CASE WHEN tila = 'peruttu' THEN 1 ELSE 0 END) as peruttu,
        SUM(CASE WHEN tila = 'täytetty' THEN 1 ELSE 0 END) as täytetty
    FROM Laitevaraukset
");

if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
    $total_reservations = $stats['total'] ?? 0;
    $pending_reservations = $stats['odottaa'] ?? 0;
    $approved_reservations = $stats['vahvistettu'] ?? 0; // Map to correct variable
    $confirmed_reservations = $stats['vahvistettu'] ?? 0; // For compatibility
    $cancelled_reservations = $stats['peruttu'] ?? 0;
    $rejected_reservations = $stats['peruttu'] ?? 0; // For HTML compatibility
    $completed_reservations = $stats['täytetty'] ?? 0;
}

// Get today's reservations
$today_result = $conn->query("
    SELECT COUNT(*) as count
    FROM Laitevaraukset
    WHERE varaus_paiva = '$current_date'
    AND tila IN ('odottaa', 'vahvistettu')
");
if ($today_result) {
    $today_reservations = $today_result->fetch_assoc()['count'] ?? 0;
}

// Get upcoming reservations (next 7 days)
$upcoming_result = $conn->query("
    SELECT COUNT(*) as count
    FROM Laitevaraukset
    WHERE varaus_paiva BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND tila IN ('odottaa', 'vahvistettu')
");
if ($upcoming_result) {
    $upcoming_reservations = $upcoming_result->fetch_assoc()['count'] ?? 0;
}

// Get expired reservations (not picked up within 2 days)
$expired_result = $conn->query("
    SELECT COUNT(*) as count
    FROM Laitevaraukset
    WHERE vanhenee < '$current_datetime'
    AND tila = 'odottaa'
");
$expired_reservations = $expired_result ? $expired_result->fetch_assoc()['count'] ?? 0 : 0;

// Get all reservations with member and device info
$sql = "SELECT
            r.*,
            j.etunimi as jasen_etunimi,
            j.sukunimi as jasen_sukunimi,
            l.sarjanumero,
            l.merkki,
            l.malli,
            l.tila as laite_tila
        FROM Laitevaraukset r
        LEFT JOIN jasenet j ON r.jasen_id = j.id
        LEFT JOIN Laitteet l ON r.laite_id = l.id
        ORDER BY r.varaus_paiva DESC, r.luotu DESC";
$result = $conn->query($sql);

// Get search filter
$search = '';
$status_filter = '';
$date_filter = '';

if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
}
if (isset($_GET['status'])) {
    $status_filter = $conn->real_escape_string($_GET['status']);
}
if (isset($_GET['date'])) {
    $date_filter = $conn->real_escape_string($_GET['date']);
}

// Build filtered query
$filter_sql = "SELECT
                    r.*,
                    j.etunimi as jasen_etunimi,
                    j.sukunimi as jasen_sukunimi,
                    l.sarjanumero,
                    l.merkki,
                    l.malli,
                    l.tila as laite_tila
                FROM Laitevaraukset r
                LEFT JOIN jasenet j ON r.jasen_id = j.id
                LEFT JOIN Laitteet l ON r.laite_id = l.id
                WHERE 1=1";

if (!empty($search)) {
    $filter_sql .= " AND (j.etunimi LIKE '%$search%'
                        OR j.sukunimi LIKE '%$search%'
                        OR l.sarjanumero LIKE '%$search%'
                        OR l.merkki LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $filter_sql .= " AND r.tila = '$status_filter'";
}
if (!empty($date_filter)) {
    $filter_sql .= " AND r.varaus_paiva = '$date_filter'";
}

$filter_sql .= " ORDER BY r.varaus_paiva DESC, r.luotu DESC";
$filtered_result = $conn->query($filter_sql);
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjasto - Laitevaraukset</title>
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
            background: linear-gradient(rgba(26, 26, 46, 0.4), rgba(26, 26, 46, 0.4)), url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-4.0.3');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            min-height: 100vh;
            color: #333;
        }

        /* Sidebar Styles */
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
            padding: 25px 20px;
            background: linear-gradient(135deg, var(--primary), var(--dark));
            color: white;
            text-align: center;
            border-bottom: 2px solid var(--info);
        }

        .sidebar-header h2 {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1.2em;
            font-weight: 600;
        }

        .admin-badge {
            background: var(--danger);
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.65em;
            margin-left: auto;
        }

        .sidebar-menu {
            padding: 15px 0;
        }

        .menu-section {
            padding: 12px 20px 5px;
            color: var(--info);
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin: 8px 0;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            margin: 4px 12px;
            border-radius: 6px;
            font-size: 0.9em;
        }

        .menu-item:hover, .menu-item.active {
            background: linear-gradient(135deg, var(--sidebar-hover), #2980B9);
            color: white;
            border-left-color: var(--warning);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .menu-item i {
            width: 20px;
            text-align: center;
            font-size: 1em;
        }

        .logout-item {
            margin-top: 25px;
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.2);
            font-size: 0.85em;
        }

        .logout-item:hover {
            background: linear-gradient(135deg, var(--danger), #C0392B);
            border-left-color: var(--danger);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            max-width: calc(100vw - var(--sidebar-width));
            overflow-x: hidden;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 18px 20px;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
        }

        .header h1 {
            font-size: 1.8em;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--card-bg);
            padding: 10px 16px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.04);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 2px solid var(--info);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-details h3 {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 3px;
            font-size: 0.95em;
        }

        .user-details p {
            color: #666;
            font-size: 0.8em;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-top: 4px solid;
        }

        .stat-card:nth-child(1) { border-color: var(--info); }
        .stat-card:nth-child(2) { border-color: var(--warning); }
        .stat-card:nth-child(3) { border-color: var(--success); }
        .stat-card:nth-child(4) { border-color: var(--danger); }
        .stat-card:nth-child(5) { border-color: var(--purple); }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.12);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-info h3 {
            font-size: 0.8em;
            color: #666;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .stat-number {
            font-size: 2.2em;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6em;
            color: white;
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        .stat-card:nth-child(1) .stat-icon { background: var(--info); }
        .stat-card:nth-child(2) .stat-icon { background: var(--warning); }
        .stat-card:nth-child(3) .stat-icon { background: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: var(--danger); }
        .stat-card:nth-child(5) .stat-icon { background: var(--purple); }

        /* Filter Section */
        .filter-section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow: 0 8px 22px rgba(0,0,0,0.08);
        }

        .section-title {
            font-size: 1.5em;
            color: var(--primary);
            margin-bottom: 22px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--info);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-filter {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 6px;
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

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 0.95em;
            text-decoration: none;
            white-space: nowrap;
            min-width: 120px;
            height: 42px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--info), #2980B9);
            color: white;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.25);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(52, 152, 219, 0.35);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #219653);
            color: white;
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.25);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(39, 174, 96, 0.35);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #E67E22);
            color: white;
            box-shadow: 0 4px 12px rgba(241, 196, 15, 0.25);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(241, 196, 15, 0.35);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #C0392B);
            color: white;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.25);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(231, 76, 60, 0.35);
        }

        .btn-light {
            background: rgba(255, 255, 255, 0.1);
            color: #333;
            border: 2px solid #e8e8e8;
        }

        .btn-light:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Reservations Grid */
        .reservations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .reservation-card {
            background: var(--card-bg);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 22px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .reservation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border-color: var(--info);
        }

        .card-header {
            padding: 20px;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.05));
            border-bottom: 1px solid #e8e8e8;
        }

        .reservation-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .reservation-card h3 {
            font-size: 1.2em;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .card-body {
            padding: 20px;
        }

        .reservation-info {
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #eee;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
        }

        .info-value {
            color: var(--primary);
            font-size: 0.9em;
            text-align: right;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            margin: 5px 0;
        }

        .status-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #F1C40F;
            border: 1px solid rgba(241, 196, 15, 0.2);
        }

        .status-approved {
            background: rgba(39, 174, 96, 0.1);
            color: #27AE60;
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .status-rejected {
            background: rgba(231, 76, 60, 0.1);
            color: #E74C3C;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .status-cancelled {
            background: rgba(149, 165, 166, 0.1);
            color: #95A5A6;
            border: 1px solid rgba(149, 165, 166, 0.2);
        }

        .status-completed {
            background: rgba(52, 152, 219, 0.1);
            color: #3498DB;
            border: 1px solid rgba(52, 152, 219, 0.2);
        }

        /* Action Buttons */
        .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 20px;
        }

        .action-btn {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            font-size: 0.85em;
            text-decoration: none;
        }

        .btn-edit {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
            border: 2px solid rgba(52, 152, 219, 0.2);
        }

        .btn-approve {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border: 2px solid rgba(39, 174, 96, 0.2);
        }

        .btn-reject {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
            border: 2px solid rgba(231, 76, 60, 0.2);
        }

        .btn-delete {
            background: rgba(149, 165, 166, 0.1);
            color: #7F8C8D;
            border: 2px solid rgba(149, 165, 166, 0.2);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, var(--info), #2980B9);
            color: white;
            border-color: var(--info);
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, var(--success), #219653);
            color: white;
            border-color: var(--success);
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, var(--danger), #C0392B);
            color: white;
            border-color: var(--danger);
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #7F8C8D, #616A6B);
            color: white;
            border-color: #7F8C8D;
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
            border-color: #27AE60;
            color: #27AE60;
            background: rgba(39, 174, 96, 0.05);
        }

        .notification-error {
            border-color: #E74C3C;
            color: #E74C3C;
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

/* Beautiful Empty State - NO ANIMATIONS */
.empty-state {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    text-align: center;
    padding: 60px 30px;
    color: #64748b;
    border-radius: 20px;
    margin: 30px 0;
    border: 2px dashed #cbd5e1;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
    grid-column: 1 / -1; /* Make it span full width */
    width: 100%;
}

.empty-state:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    border-color: #3498DB;
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
}

.empty-state::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #3498DB, #27AE60, #F1C40F);
}

.empty-state-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #3498DB 0%, #4f46e5 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    box-shadow: 0 15px 30px rgba(52, 152, 219, 0.3);
    position: relative;
}

.empty-state:hover .empty-state-icon {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 20px 40px rgba(52, 152, 219, 0.4);
}

.empty-state-icon::after {
    content: '';
    position: absolute;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 2px solid rgba(52, 152, 219, 0.2);
}

.empty-state-icon i {
    font-size: 3em;
    color: white;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.empty-state h3 {
    font-size: 1.8em;
    margin-bottom: 15px;
    color: #2C3E50;
    font-weight: 700;
    letter-spacing: -0.5px;
}

.empty-state p {
    max-width: 500px;
    margin: 0 auto 25px;
    font-size: 1.1em;
    line-height: 1.6;
    color: #64748b;
    font-weight: 400;
}

.empty-state-cta {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 32px;
    background: linear-gradient(135deg, #3498DB, #2980B9);
    color: white;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1em;
    transition: all 0.3s ease;
    box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
    border: 2px solid transparent;
    cursor: pointer;
    border: none;
}

.empty-state-cta:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(52, 152, 219, 0.4);
    background: linear-gradient(135deg, #2980B9, #3498DB);
}

.empty-state-cta i {
    font-size: 1.2em;
}

.empty-state-cta:hover i {
    transform: translateX(5px);
}

.empty-state-subtext {
    font-size: 0.9em;
    color: #94a3b8;
    margin-top: 20px;
    font-style: italic;
}

/* Responsive */
@media (max-width: 768px) {
    .empty-state {
        padding: 40px 20px;
        margin: 20px 0;
    }
    
    .empty-state-icon {
        width: 80px;
        height: 80px;
    }
    
    .empty-state-icon i {
        font-size: 2.5em;
    }
    
    .empty-state h3 {
        font-size: 1.5em;
    }
    
    .empty-state p {
        font-size: 1em;
    }
}

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--info);
        }

        .modal-title {
            font-size: 1.3em;
            color: var(--primary);
            font-weight: 600;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5em;
            color: #999;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: var(--danger);
        }

        /* Calendar icon styling */
        .date-input-wrapper {
            position: relative;
        }

        .date-input-wrapper i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--info);
        }

        /* Mobile Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
            .reservations-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 60px;
            }
            .sidebar-header h2 span, .menu-item span, .menu-section {
                display: none;
            }
            .main-content {
                margin-left: 60px;
                padding: 15px;
            }
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .user-info {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            .search-filter {
                grid-template-columns: 1fr;
            }
            .reservations-grid {
                grid-template-columns: 1fr;
            }
            .card-actions {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 50px;
            }
            .main-content {
                margin-left: 50px;
                padding: 12px;
            }
            .header {
                padding: 15px;
            }
            .header h1 {
                font-size: 1.5em;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .stat-card {
                padding: 18px;
            }
            .stat-number {
                font-size: 1.8em;
            }
            .stat-icon {
                width: 45px;
                height: 45px;
                font-size: 1.4em;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
            <a href="laitevaraukset.php" class="menu-item active">
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
            <a href="admin_palvelin_lokit.php" class="menu-item">
                <span>📋 Palvelinlokit</span>
            </a>

            <a href="logout.php" class="menu-item logout-item">
                <span>🚪 Kirjaudu Ulos</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-calendar-alt"></i> Laitevaraukset</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($kayttajan_nimi); ?>&background=3498db&color=fff&size=128'">
                </div>
                <div class="user-details">
                    <h3 style="color: #2C3E50; font-size: 1.1em; margin-bottom: 6px; font-weight: 700;">
                        <?php echo htmlspecialchars($custom_name); ?>
                    </h3>
                    <p class="user-email" style="color: #E74C3C; font-size: 0.85em; margin-bottom: 5px;">
                        <i class="fas fa-envelope" style="color: #E74C3C;"></i> <?php echo htmlspecialchars($custom_email); ?>
                    </p>
                    <p class="user-role" style="color: #3498DB; font-size: 0.9em; margin-bottom: 5px; font-weight: 600;">
                        <i class="fas fa-user-shield" style="color: #3498DB;"></i> <?php echo htmlspecialchars($custom_role_display); ?>
                    </p>
                    <p class="user-permissions" style="color: #27AE60; font-size: 0.8em; margin-bottom: 0; font-style: italic;">
                        <i class="fas fa-key" style="color: #27AE60;"></i> <?php echo htmlspecialchars($custom_permissions); ?>
                    </p>
                </div>
            </div>
        </div>

<!-- Messages -->
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

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-info">
                <h3>Varaukset</h3>
                <div class="stat-number"><?php echo number_format($total_reservations, 0, ',', ' '); ?></div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-info">
                <h3>Odottaa</h3>
                <div class="stat-number"><?php echo number_format($pending_reservations, 0, ',', ' '); ?></div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-info">
                <h3>Hyväksytty</h3>
                <div class="stat-number"><?php echo number_format($approved_reservations, 0, ',', ' '); ?></div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-info">
                <h3>Peruttu</h3>
                <div class="stat-number"><?php echo number_format($rejected_reservations, 0, ',', ' '); ?></div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-info">
                <h3>Lähiajan</h3>
                <div class="stat-number"><?php echo number_format($upcoming_reservations, 0, ',', ' '); ?></div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <h2 class="section-title"><i class="fas fa-search"></i> Hae ja hallinnoi varauksia</h2>

    <form method="GET" id="filterForm">
        <div class="search-filter">
            <div class="form-group">
                <label class="form-label" for="search">
                    <i class="fas fa-search"></i> Haku
                </label>
                <input type="text" name="search" class="form-control"
                       placeholder="Sarjanumero, jäsen, laite..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="device">
                    <i class="fas fa-laptop"></i> Laite
                </label>
                <select name="device" class="form-control form-select">
                    <option value="">Kaikki laitteet</option>
                    <?php foreach ($devices as $device): ?>
                    <option value="<?php echo $device['id']; ?>"
                            <?php echo (isset($_GET['device']) && $_GET['device'] == $device['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($device['sarjanumero'] . ' - ' . $device['merkki'] . ' ' . $device['malli']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">
                    <i class="fas fa-info-circle"></i> Tila
                </label>
                <select name="status" class="form-control form-select">
                    <option value="">Kaikki tilat</option>
                    <option value="odottaa" <?php echo ($status_filter == 'odottaa') ? 'selected' : ''; ?>>🟡 Odottaa</option>
                    <option value="vahvistettu" <?php echo ($status_filter == 'vahvistettu') ? 'selected' : ''; ?>>🟢 Hyväksytty</option>
                    <option value="peruttu" <?php echo ($status_filter == 'peruttu') ? 'selected' : ''; ?>>🔴 Peruttu</option>
                    <option value="täytetty" <?php echo ($status_filter == 'täytetty') ? 'selected' : ''; ?>>🔵 Täytetty</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="date">
                    <i class="fas fa-calendar-alt"></i> Päivämäärä
                </label>
                <div class="date-input-wrapper">
                    <input type="text" class="form-control datepicker" id="date" name="date"
                           placeholder="Valitse päivämäärä"
                           value="<?php echo htmlspecialchars($date_filter); ?>"
                           readonly>
                    <i class="fas fa-calendar"></i>
                </div>
            </div>

            <div class="form-group">
                <label>&nbsp;</label>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Suodata
                    </button>
                    <a href="laitevaraukset.php" class="btn btn-light">
                        <i class="fas fa-sync"></i> Tyhjennä
                    </a>
                    <button type="button" class="btn btn-success" onclick="showModal()">
                        <i class="fas fa-plus"></i> Uusi varaus
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Reservations Grid -->
<div class="reservations-grid" id="reservationsGrid">
    <?php if ($filtered_result && $filtered_result->num_rows > 0): ?>
        <?php
        $icon_colors = ['#3498DB', '#9B59B6', '#E74C3C', '#F39C12', '#1ABC9C', '#34495E', '#E67E22', '#16A085'];
        $icon_list = ['fa-calendar-alt', 'fa-laptop', 'fa-tablet-alt', 'fa-mobile-alt', 'fa-desktop', 'fa-headphones', 'fa-keyboard', 'fa-print', 'fa-camera', 'fa-gamepad'];
        $i = 0;

        // Reset result pointer
        $filtered_result->data_seek(0);
        ?>

        <?php while ($reservation = $filtered_result->fetch_assoc()):
            $color = $icon_colors[$i % count($icon_colors)];
            $icon = $icon_list[$i % count($icon_list)];

            // Determine status badge class
            $status_class = '';
            switch($reservation['tila']) {
                case 'odottaa': $status_class = 'status-pending'; break;
                case 'vahvistettu': $status_class = 'status-approved'; break;
                case 'peruttu': $status_class = 'status-rejected'; break;
                case 'täytetty': $status_class = 'status-completed'; break;
                default: $status_class = 'status-pending';
            }

            $i++;
        ?>
        <div class="reservation-card">
            <div class="card-header">
                <div class="reservation-icon" style="background: <?php echo $color; ?>22; color: <?php echo $color; ?>;">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <h3><?php echo htmlspecialchars($reservation['sarjanumero'] ?? 'Ei sarjanumeroa'); ?></h3>
                <div style="font-size: 0.9em; color: #666;">
                    <?php echo htmlspecialchars($reservation['merkki'] ?: ''); ?>
                    <?php if ($reservation['malli']): ?>
                        - <?php echo htmlspecialchars($reservation['malli']); ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body">
                <!-- Status Badge -->
                <div style="margin-bottom: 15px;">
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php
                        switch($reservation['tila']) {
                            case 'odottaa': echo '🟡 Odottaa hyväksyntää'; break;
                            case 'vahvistettu': echo '🟢 Vahvistettu'; break;
                            case 'peruttu': echo '🔴 Peruttu'; break;
                            case 'täytetty': echo '🔵 Täytetty'; break;
                            default: echo $reservation['tila'];
                        }
                        ?>
                    </span>
                </div>

                <!-- Reservation Info -->
                <div class="reservation-info">
                    <div class="info-item">
                        <span class="info-label">Jäsen:</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars(($reservation['jasen_etunimi'] ?? '') . ' ' . ($reservation['jasen_sukunimi'] ?? '')); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Varauspäivä:</span>
                        <span class="info-value"><?php echo date('d.m.Y', strtotime($reservation['varaus_paiva'])); ?></span>
                    </div>
                    <?php if ($reservation['noutopaiva']): ?>
                    <div class="info-item">
                        <span class="info-label">Noutopäivä:</span>
                        <span class="info-value"><?php echo date('d.m.Y', strtotime($reservation['noutopaiva'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($reservation['noutoaika']): ?>
                    <div class="info-item">
                        <span class="info-label">Noutoaika:</span>
                        <span class="info-value"><?php echo htmlspecialchars($reservation['noutoaika']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">Luotu:</span>
                        <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($reservation['luotu'])); ?></span>
                    </div>
                    <?php if ($reservation['vanhenee']): ?>
                    <div class="info-item">
                        <span class="info-label">Vanhenee:</span>
                        <span class="info-value">
                            <?php
                            $expiry_date = new DateTime($reservation['vanhenee']);
                            $now = new DateTime();
                            if ($expiry_date < $now) {
                                echo '<span style="color: red;">' . date('d.m.Y H:i', strtotime($reservation['vanhenee'])) . ' (Vanhentunut)</span>';
                            } else {
                                echo date('d.m.Y H:i', strtotime($reservation['vanhenee']));
                            }
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="card-actions">
                    <a href="?edit=<?php echo $reservation['id']; ?>" class="action-btn btn-edit">
                        <i class="fas fa-edit"></i> Muokkaa
                    </a>

                    <?php if ($reservation['tila'] == 'odottaa'): ?>
                        <form method="POST" style="display: contents;">
                            <input type="hidden" name="id" value="<?php echo $reservation['id']; ?>">
                            <input type="hidden" name="status" value="vahvistettu">
                            <button type="submit" name="update_status" class="action-btn btn-approve">
                                <i class="fas fa-check"></i> Hyväksy
                            </button>
                        </form>
                        <form method="POST" style="display: contents;">
                            <input type="hidden" name="id" value="<?php echo $reservation['id']; ?>">
                            <input type="hidden" name="status" value="peruttu">
                            <button type="submit" name="update_status" class="action-btn btn-reject">
                                <i class="fas fa-times"></i> Peruuta
                            </button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" style="display: contents;">
                        <input type="hidden" name="id" value="<?php echo $reservation['id']; ?>">
                        <button type="submit" name="delete_reservation" class="action-btn btn-delete"
                                onclick="return confirm('Haluatko varmasti poistaa varauksen?')">
                            <i class="fas fa-trash"></i> Poista
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <!-- Beautiful Empty State -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <h3>Ei varauksia</h3>
            <p>Aloita luomalla ensimmäinen laitevaraus. Varaukset mahdollistavat laitteiden varaamisen tulevaisuuteen.</p>
            <button class="empty-state-cta" onclick="showModal()">
                <i class="fas fa-plus"></i> Luo ensimmäinen varaus
            </button>
            <p class="empty-state-subtext">Hallinnoi varauksia helposti ja tehokkaasti</p>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Reservation Modal -->
<div id="reservationModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <?php if ($edit_mode): ?>
                    <i class="fas fa-edit"></i> Muokkaa Varausta
                <?php else: ?>
                    <i class="fas fa-plus-circle"></i> Luo Uusi Varaus
                <?php endif; ?>
            </div>
            <button class="close-modal" onclick="hideModal()">&times;</button>
        </div>

        <form method="POST" id="reservationForm">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                <input type="hidden" name="update_reservation" value="1">
            <?php else: ?>
                <input type="hidden" name="add_reservation" value="1">
            <?php endif; ?>

            <div class="form-group">
                <label for="laite_id" class="form-label">
                    <i class="fas fa-laptop"></i> Laite *
                </label>
                <select class="form-control form-select" id="laite_id" name="laite_id" required>
                    <option value="">Valitse laite</option>
                    <?php foreach ($devices as $device): ?>
                    <option value="<?php echo $device['id']; ?>"
                            <?php echo ($edit_mode && isset($edit_data['laite_id']) && $edit_data['laite_id'] == $device['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($device['sarjanumero'] . ' - ' . $device['merkki'] . ' ' . $device['malli']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="jasen_id" class="form-label">
                    <i class="fas fa-user"></i> Jäsen *
                </label>
                <select class="form-control form-select" id="jasen_id" name="jasen_id" required>
                    <option value="">Valitse jäsen</option>
                    <?php foreach ($members as $member): ?>
                    <option value="<?php echo $member['id']; ?>"
                            <?php echo ($edit_mode && isset($edit_data['jasen_id']) && $edit_data['jasen_id'] == $member['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($member['etunimi'] . ' ' . $member['sukunimi']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="varaus_paiva" class="form-label">
                    <i class="fas fa-calendar-plus"></i> Varauspäivä *
                </label>
                <div class="date-input-wrapper">
                    <input type="text" class="form-control modal-datepicker" id="varaus_paiva" name="varaus_paiva" required
                           placeholder="Valitse päivämäärä"
                           value="<?php echo $edit_mode ? $edit_data['varaus_paiva'] : date('Y-m-d'); ?>"
                           readonly>
                    <i class="fas fa-calendar"></i>
                </div>
            </div>
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
    <div class="form-group">
        <label for="noutopaiva" class="form-label">
            <i class="fas fa-calendar-check"></i> Noutopäivä
        </label>
        <div class="date-input-wrapper">
            <input type="text" class="form-control modal-datepicker" id="noutopaiva" name="noutopaiva"
                   placeholder="Valitse päivämäärä"
                   value="<?php echo $edit_mode ? ($edit_data['noutopaiva'] ?? '') : ''; ?>"
                   readonly>
            <i class="fas fa-calendar"></i>
        </div>
    </div>

    <div class="form-group">
        <label for="noutoaika" class="form-label">
            <i class="fas fa-clock"></i> Noutoaika
        </label>
        <div class="date-input-wrapper">
            <input type="text" class="form-control timepicker" id="noutoaika" name="noutoaika"
                   placeholder="Valitse aika"
                   value="<?php echo $edit_mode ? ($edit_data['noutoaika'] ?? '') : ''; ?>"
                   readonly>
            <i class="fas fa-clock"></i>
        </div>
    </div>
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

<!-- Add these CDN links to your head section -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>

<script>
// Flatpickr calendar initialization
document.addEventListener('DOMContentLoaded', function() {
// Filter form datepicker
flatpickr("#date", {
    dateFormat: "Y-m-d",
    allowInput: true,
    placeholder: "Valitse päivämäärä",
    locale: "fi"
});

// Modal datepickers
flatpickr("#varaus_paiva", {
    dateFormat: "Y-m-d",
    minDate: "today",
    allowInput: true,
    placeholder: "Valitse päivämäärä",
    locale: "fi"
});

flatpickr("#noutopaiva", {
    dateFormat: "Y-m-d",
    minDate: "today",
    allowInput: true,
    placeholder: "Valitse päivämäärä",
    locale: "fi"
});

// Time picker for noutoaika
flatpickr("#noutoaika", {
    enableTime: true,
    noCalendar: true,
    dateFormat: "H:i",
    time_24hr: true,
    allowInput: true,
    placeholder: "Valitse aika",
    locale: "fi"
 });
});

// Modal functions
function showModal() {
    document.getElementById('reservationModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function hideModal() {
    document.getElementById('reservationModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Show modal if editing
<?php if ($edit_mode): ?>
document.addEventListener('DOMContentLoaded', function() {
    showModal();
});
<?php endif; ?>

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.id === 'reservationModal') {
        hideModal();
    }
}

// Form validation
document.getElementById('reservationForm').onsubmit = function(e) {
    if (!document.getElementById('laite_id').value) {
        e.preventDefault();
        alert('Valitse laite!');
        return false;
    }
    if (!document.getElementById('jasen_id').value) {
        e.preventDefault();
        alert('Valitse jäsen!');
        return false;
    }
    if (!document.getElementById('varaus_paiva').value) {
        e.preventDefault();
        alert('Valitse varauspäivä!');
        return false;
    }
    return true;
};

// Auto-hide notifications
setTimeout(function() {
    var notifications = document.querySelectorAll('.notification');
    notifications.forEach(function(notification) {
        if (notification) {
            notification.style.display = 'none';
        }
    });
}, 5000);

</script>
</body>
</html>
