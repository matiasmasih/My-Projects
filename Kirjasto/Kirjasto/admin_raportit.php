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

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT rooli, profile_image, etunimi, sukunimi, email FROM jasenet WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($rooli, $profile_image, $etunimi, $sukunimi, $email);
$stmt->fetch();
$stmt->close();

// Only manager and admin can access
if ($rooli !== 'manager' && $rooli !== 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Get user display info
$kayttajan_nimi = $etunimi . ' ' . $sukunimi;
$custom_name = $etunimi . ' ' . $sukunimi;
$custom_email = isset($email) ? $email : "matiasmasih@gmail.com";
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
    return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=3498db&color=fff&size=128';
}

$profile_image_url = getProfileImageUrl($profile_image ?? '', $kayttajan_nimi);

// Get date filters
$today = date('Y-m-d');
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? $today;
$report_type = $_GET['report_type'] ?? 'loans';
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$member_id = $_GET['member_id'] ?? '';

// Get all members for filter dropdown
$members = [];
$members_result = $conn->query("SELECT id, etunimi, sukunimi, email FROM jasenet ORDER BY sukunimi, etunimi");
if ($members_result) {
    while ($member = $members_result->fetch_assoc()) {
        $members[] = $member;
    }
}

// Get statistics for stats cards
$stats = [];

// Total loans
$result = $conn->query("SELECT COUNT(*) as count FROM Laitelainat");
$stats['total_loans'] = $result->fetch_assoc()['count'];

// Active loans
$result = $conn->query("SELECT COUNT(*) as count FROM Laitelainat WHERE palautus_pvm IS NULL");
$stats['active_loans'] = $result->fetch_assoc()['count'];

// Overdue loans
$result = $conn->query("SELECT COUNT(*) as count FROM Laitelainat WHERE palautus_pvm IS NULL AND erapaiva < NOW()");
$stats['overdue_loans'] = $result->fetch_assoc()['count'];

// Returned loans
$result = $conn->query("SELECT COUNT(*) as count FROM Laitelainat WHERE palautus_pvm IS NOT NULL");
$stats['returned_loans'] = $result->fetch_assoc()['count'];

// Today's loans
$result = $conn->query("SELECT COUNT(*) as count FROM Laitelainat WHERE DATE(lainaus_pvm) = CURDATE()");
$stats['today_loans'] = $result->fetch_assoc()['count'];

// Total fines
$result = $conn->query("SELECT COALESCE(SUM(myohastyymismaksu), 0) as total FROM Laitelainat");
$stats['total_fines'] = $result->fetch_assoc()['total'];

// Get chart data
$chart_data = [];
$chart_result = $conn->query("
    SELECT 
        DATE_FORMAT(lainaus_pvm, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN palautus_pvm IS NULL THEN 1 ELSE 0 END) as active,
        COALESCE(SUM(myohastyymismaksu), 0) as fines
    FROM Laitelainat 
    WHERE lainaus_pvm >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(lainaus_pvm, '%Y-%m')
    ORDER BY month
");
while ($row = $chart_result->fetch_assoc()) {
    $chart_data[] = $row;
}

// Get report data based on type
$report_data = [];

if ($report_type === 'loans') {
    // Loans report
    $sql = "SELECT 
                l.id,
                l.lainaus_pvm,
                l.erapaiva,
                l.palautus_pvm,
                l.myohastyymismaksu,
                l.lainaus_kunto,
                l.palautus_kunto,
                d.merkki,
                d.malli,
                d.sarjanumero,
                j.etunimi as jasen_etunimi,
                j.sukunimi as jasen_sukunimi,
                j.email as jasen_email,
                CASE 
                    WHEN l.palautus_pvm IS NOT NULL THEN 'palautettu'
                    WHEN l.erapaiva < NOW() THEN 'myohassa'
                    ELSE 'aktiivinen'
                END as tila
            FROM Laitelainat l
            LEFT JOIN Laitteet d ON l.laite_id = d.id
            LEFT JOIN jasenet j ON l.jasen_id = j.id
            WHERE 1=1";

    if ($search) {
        $sql .= " AND (d.merkki LIKE '%$search%' OR d.malli LIKE '%$search%' OR d.sarjanumero LIKE '%$search%' OR j.sukunimi LIKE '%$search%' OR j.etunimi LIKE '%$search%')";
    }
    if ($status) {
        if ($status === 'aktiivinen') {
            $sql .= " AND l.palautus_pvm IS NULL AND l.erapaiva >= NOW()";
        } elseif ($status === 'myohassa') {
            $sql .= " AND l.palautus_pvm IS NULL AND l.erapaiva < NOW()";
        } elseif ($status === 'palautettu') {
            $sql .= " AND l.palautus_pvm IS NOT NULL";
        }
    }
    if ($member_id) {
        $sql .= " AND l.jasen_id = " . intval($member_id);
    }

    $sql .= " ORDER BY l.lainaus_pvm DESC LIMIT 100";
    $report_data = $conn->query($sql);

} elseif ($report_type === 'devices') {
    // Devices report
    $sql = "SELECT 
                d.id,
                d.merkki,
                d.malli,
                d.sarjanumero,
                d.kunto,
                d.tila,
                d.sijainti,
                d.hankintapaiva,
                d.viime_huolto,
                t.nimi as tyyppi_nimi,
                t.laina_aika,
                COUNT(l.id) as lainauskertoja,
                MAX(l.lainaus_pvm) as viimeisin_lainaus,
                SUM(CASE WHEN l.palautus_pvm IS NULL THEN 1 ELSE 0 END) as aktiivisia_lainoja
            FROM Laitteet d
            LEFT JOIN Laitetyypit t ON d.laite_tyyppi_id = t.id
            LEFT JOIN Laitelainat l ON d.id = l.laite_id
            GROUP BY d.id
            ORDER BY lainauskertoja DESC
            LIMIT 100";
    $report_data = $conn->query($sql);

} elseif ($report_type === 'members') {
    // Members report
    $sql = "SELECT 
                j.id,
                j.etunimi,
                j.sukunimi,
                j.email,
                j.puhelin,
                j.jasentyyppi,
                j.jasennumero,
                j.liittymispaiva,
                j.tila as jasen_tila,
                COUNT(l.id) as lainauskertoja,
                SUM(CASE WHEN l.palautus_pvm IS NULL THEN 1 ELSE 0 END) as aktiivisia_lainoja,
                SUM(CASE WHEN l.palautus_pvm IS NULL AND l.erapaiva < NOW() THEN 1 ELSE 0 END) as myohassa_lainoja,
                COALESCE(SUM(l.myohastyymismaksu), 0) as sakkoja_yhteensa,
                MAX(l.lainaus_pvm) as viimeisin_lainaus
            FROM jasenet j
            LEFT JOIN Laitelainat l ON j.id = l.jasen_id
            GROUP BY j.id
            ORDER BY lainauskertoja DESC
            LIMIT 100";
    $report_data = $conn->query($sql);

} elseif ($report_type === 'fines') {
    // Fines report
    $sql = "SELECT 
                s.id,
                s.sakko_maara,
                s.sakko_paiva,
                s.maksettu_maara,
                s.maksettu_paiva,
                s.tila as sakko_tila,
                s.syy,
                j.etunimi as jasen_etunimi,
                j.sukunimi as jasen_sukunimi,
                j.email as jasen_email,
                l.id as laina_id,
                d.merkki,
                d.malli,
                d.sarjanumero
            FROM sakot s
            LEFT JOIN jasenet j ON s.jasen_id = j.id
            LEFT JOIN Laitelainat l ON s.laina_id = l.id
            LEFT JOIN Laitteet d ON l.laite_id = d.id
            ORDER BY s.sakko_paiva DESC
            LIMIT 100";
    $report_data = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjasto - Raportit</title>
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fi.js"></script>
    <style>
        :root {
            --primary: #2C3E50; /* Dark Blue */
            --secondary: #E74C3C; /* Red */
            --success: #27AE60; /* Green */
            --danger: #F39C12; /* Orange */
            --warning: #F1C40F; /* Yellow */
            --info: #3498DB; /* Light Blue */
            --purple: #9B59B6; /* Purple */
            --dark: #1A1A2E; /* Very Dark Blue */
            --light: #F8F9FA; /* Light Gray */
            --sidebar-bg: #1A1A2E;
            --sidebar-text: #E0E0E0;
            --sidebar-hover: #3498DB;
            --sidebar-width: 300px;
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
            background: var(--danger);
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
            background: linear-gradient(135deg, var(--danger), #C0392B);
            border-left-color: var(--danger);
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

        /* STATS GRID - Matching Screenshot */
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
        .stat-card:nth-child(3) { border-color: var(--warning); }
        .stat-card:nth-child(4) { border-color: var(--danger); }
        .stat-card:nth-child(5) { border-color: var(--purple); }
        .stat-card:nth-child(6) { border-color: #1ABC9C; }

        .stat-card:nth-child(1) .stat-icon { background: var(--info); }
        .stat-card:nth-child(2) .stat-icon { background: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: var(--warning); }
        .stat-card:nth-child(4) .stat-icon { background: var(--danger); }
        .stat-card:nth-child(5) .stat-icon { background: var(--purple); }
        .stat-card:nth-child(6) .stat-icon { background: #1ABC9C; }

        /* REPORT TABS */
        .report-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 25px;
            background: var(--card-bg);
            border: 2px solid rgba(52, 152, 219, 0.2);
            border-radius: 10px;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1em;
            text-decoration: none;
            backdrop-filter: blur(5px);
        }

        .tab-btn:hover {
            background: linear-gradient(135deg, var(--info), #2980B9);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, var(--info), #2980B9);
            color: white;
            border-color: var(--info);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        /* CHARTS SECTION */
        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }

        .chart-title {
            font-size: 1.1em;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-container {
            height: 250px;
            position: relative;
        }

        /* FILTER SECTION */
        .filter-section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
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

        .form-control, .filter-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s;
            background: #fafafa;
        }

        .form-control:focus, .filter-input:focus {
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

        /* Beautiful Calendar Styling */
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
            z-index: 2;
        }

        /* Flatpickr custom styling */
        .flatpickr-calendar {
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            border: 2px solid var(--info);
            margin-top: 5px;
        }

        .flatpickr-months {
            background: linear-gradient(135deg, var(--info), #2980B9);
            border-radius: 12px 12px 0 0;
            padding: 10px 0;
        }

        .flatpickr-month {
            color: white;
        }

        .flatpickr-current-month .flatpickr-monthDropdown-months {
            background: transparent;
            color: white;
            font-weight: 600;
        }

        .flatpickr-current-month input.cur-year {
            color: white;
            font-weight: 600;
        }

        .flatpickr-weekdays {
            background: rgba(52, 152, 219, 0.1);
        }

        .flatpickr-weekday {
            color: var(--primary);
            font-weight: 600;
        }

        .flatpickr-day.selected, 
        .flatpickr-day.selected:hover {
            background: var(--info);
            border-color: var(--info);
        }

        .flatpickr-day.today {
            border-color: var(--warning);
        }

        .flatpickr-day:hover {
            background: rgba(52, 152, 219, 0.2);
        }

        .flatpickr-prev-month, 
        .flatpickr-next-month {
            color: white;
            padding: 10px;
        }

        .flatpickr-prev-month:hover, 
        .flatpickr-next-month:hover {
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
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

        .btn-secondary {
            background: #f7fafc;
            color: var(--primary);
            border: 2px solid #e8e8e8;
        }

        .btn-secondary:hover {
            background: #edf2f7;
            transform: translateY(-2px);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
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
            min-width: 1000px;
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
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
            border: 1px solid rgba(52, 152, 219, 0.2);
        }

        .status-myohassa {
            background: rgba(231, 76, 60, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .status-palautettu {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        /* Export Buttons */
        .export-buttons {
            display: flex;
            gap: 10px;
        }

        .export-btn {
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            text-decoration: none;
        }

        .export-pdf {
            color: var(--secondary);
            border-color: var(--secondary);
        }

        .export-excel {
            color: var(--success);
            border-color: var(--success);
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin: 20px 0;
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
        .text-danger {
            color: var(--secondary);
            font-weight: 600;
        }

        .text-success {
            color: var(--success);
            font-weight: 600;
        }

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
            .charts-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .export-buttons {
                flex-direction: column;
            }
            .filter-actions {
                flex-direction: column;
            }
            .filter-actions .btn {
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
            <!-- END DEVICE MANAGEMENT SECTION -->

            <div class="menu-section">📊 Raportit & Sakot</div>
            <a href="admin_raportit.php" class="menu-item active">
                <span>📈 Kirjasto Raportit</span>
            </a>
            <a href="admin_sakot.php" class="menu-item">
                <span>⚠️ Hallinnoi Sakkoja</span>
            </a>

           <div class="menu-section">📨 Viestit</div>
           <a href="admin_viestit.php" class="menu-item">
               <span>💬 Hallinnoi Viestit</span>
           </a>
           <a href="ryhmaviestit.php" class="menu-item">
               <span>📢 Ryhmäviestit</span>
           </a>
           <a href="viestiasetukset.php" class="menu-item">
               <span>⚙️ Viestiasetukset</span>
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

<!-- MAIN CONTENT -->
<div class="main-content">
    <!-- TOP SECTION WITH TITLE AND PROFILE -->
    <div class="top-section">
        <div class="page-header">
            <div class="title-icon">
                📈
            </div>
            <h1 class="page-title">Kirjasto Raportit</h1>
        </div>

            <div class="profile-section">
                <div class="profile-avatar">
                    <img src="<?php echo $profile_image_url; ?>" alt="Profile">
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo $custom_name; ?></div>
                    <div class="profile-email">
                        <i class="fas fa-envelope"></i>
                        <?php echo $custom_email; ?>
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

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>KAIKKI LAINAT</h3>
                        <div class="stat-number"><?php echo $stats['total_loans']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>AKTIIVISET</h3>
                        <div class="stat-number"><?php echo $stats['active_loans']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>MYÖHÄSSÄ</h3>
                        <div class="stat-number"><?php echo $stats['overdue_loans']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>PALAUTETUT</h3>
                        <div class="stat-number"><?php echo $stats['returned_loans']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>TÄNÄÄN LAINATTU</h3>
                        <div class="stat-number"><?php echo $stats['today_loans']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>SAKOT YHTEENSÄ</h3>
                        <div class="stat-number"><?php echo number_format($stats['total_fines'], 2, ',', ''); ?>€</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- REPORT TABS -->
        <div class="report-tabs">
            <a href="?report_type=loans" class="tab-btn <?php echo $report_type === 'loans' ? 'active' : ''; ?>">
                <i class="fas fa-exchange-alt"></i> Lainat
            </a>
            <a href="?report_type=devices" class="tab-btn <?php echo $report_type === 'devices' ? 'active' : ''; ?>">
                <i class="fas fa-laptop"></i> Laitteet
            </a>
            <a href="?report_type=members" class="tab-btn <?php echo $report_type === 'members' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Jäsenet
            </a>
            <a href="?report_type=fines" class="tab-btn <?php echo $report_type === 'fines' ? 'active' : ''; ?>">
                <i class="fas fa-euro-sign"></i> Sakot
            </a>
        </div>

        <!-- CHARTS SECTION (only on loans tab) -->
        <?php if ($report_type === 'loans' && !empty($chart_data)): ?>
        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-bar" style="color: var(--info);"></i> Kuukausittaiset lainat
                </div>
                <div class="chart-container">
                    <canvas id="loansChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-pie" style="color: var(--success);"></i> Lainojen jakautuminen
                </div>
                <div class="chart-container">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- FILTER SECTION WITH BEAUTIFUL CALENDARS -->
        <div class="filter-section">
            <h2 class="section-title">
                <i class="fas fa-search"></i> Hae ja suodata raportteja
            </h2>

            <form method="GET" id="filterForm">
                <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
                
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label" for="search">
                            <i class="fas fa-search"></i> Haku
                        </label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Sarjanumero, jäsen, laite..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="status">
                            <i class="fas fa-filter"></i> Tila
                        </label>
                        <select class="form-control form-select" id="status" name="status">
                            <option value="">Kaikki tilat</option>
                            <option value="aktiivinen" <?php echo $status === 'aktiivinen' ? 'selected' : ''; ?>>Aktiiviset</option>
                            <option value="myohassa" <?php echo $status === 'myohassa' ? 'selected' : ''; ?>>Myöhässä</option>
                            <option value="palautettu" <?php echo $status === 'palautettu' ? 'selected' : ''; ?>>Palautetut</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="start_date">
                            <i class="fas fa-calendar-alt"></i> Alkupäivä
                        </label>
                        <div class="date-input-wrapper">
                            <input type="text" class="form-control datepicker" id="start_date" name="start_date" 
                                   placeholder="Valitse alkupäivä..."
                                   value="<?php echo $start_date; ?>">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="end_date">
                            <i class="fas fa-calendar-alt"></i> Loppupäivä
                        </label>
                        <div class="date-input-wrapper">
                            <input type="text" class="form-control datepicker" id="end_date" name="end_date" 
                                   placeholder="Valitse loppupäivä..."
                                   value="<?php echo $end_date; ?>">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="member_id">
                            <i class="fas fa-user"></i> Jäsen
                        </label>
                        <select class="form-control form-select" id="member_id" name="member_id">
                            <option value="">Kaikki jäsenet</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo $member_id == $member['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['sukunimi'] . ' ' . $member['etunimi']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Suodata
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                                <i class="fas fa-redo"></i> Nollaa
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- TABLE SECTION -->
        <div class="table-section">
            <div class="table-header">
                <h3>
                    <i class="fas fa-table" style="color: var(--info);"></i>
                    <?php
                    $titles = [
                        'loans' => 'Lainaraportti',
                        'devices' => 'Laiteraportti',
                        'members' => 'Jäsenraportti',
                        'fines' => 'Sakkoraportti'
                    ];
                    echo $titles[$report_type];
                    ?>
                </h3>
                <div class="export-buttons">
                    <button class="export-btn export-pdf" onclick="exportPDF()">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button class="export-btn export-excel" onclick="exportExcel()">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <?php if ($report_type === 'loans'): ?>
                                <th>Laina ID</th>
                                <th>Laite</th>
                                <th>Sarjanumero</th>
                                <th>Jäsen</th>
                                <th>Lainauspvm</th>
                                <th>Eräpäivä</th>
                                <th>Palautuspvm</th>
                                <th>Tila</th>
                                <th>Sakko (€)</th>
                            <?php elseif ($report_type === 'devices'): ?>
                                <th>Laite</th>
                                <th>Tyyppi</th>
                                <th>Sarjanumero</th>
                                <th>Kunto</th>
                                <th>Tila</th>
                                <th>Sijainti</th>
                                <th>Lainauskertoja</th>
                                <th>Viimeisin lainaus</th>
                            <?php elseif ($report_type === 'members'): ?>
                                <th>Jäsen</th>
                                <th>Email</th>
                                <th>Jäsentyyppi</th>
                                <th>Lainoja</th>
                                <th>Aktiivisia</th>
                                <th>Myöhässä</th>
                                <th>Sakot (€)</th>
                                <th>Viimeisin lainaus</th>
                            <?php elseif ($report_type === 'fines'): ?>
                                <th>Jäsen</th>
                                <th>Laina ID</th>
                                <th>Laite</th>
                                <th>Sakko (€)</th>
                                <th>Sakkopäivä</th>
                                <th>Tila</th>
                                <th>Syy</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($report_data && $report_data->num_rows > 0): ?>
                            <?php while ($row = $report_data->fetch_assoc()): ?>
                                <tr>
                                    <?php if ($report_type === 'loans'): ?>
                                        <td><strong>#<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['merkki'] . ' ' . $row['malli']); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($row['sarjanumero']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['jasen_sukunimi'] . ' ' . $row['jasen_etunimi']); ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($row['lainaus_pvm'])); ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($row['erapaiva'])); ?></td>
                                        <td>
                                            <?php if ($row['palautus_pvm']): ?>
                                                <?php echo date('d.m.Y', strtotime($row['palautus_pvm'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            if ($row['tila'] == 'aktiivinen') {
                                                $status_class = 'status-aktiivinen';
                                                $status_text = 'Aktiivinen';
                                            } elseif ($row['tila'] == 'myohassa') {
                                                $status_class = 'status-myohassa';
                                                $status_text = 'Myöhässä';
                                            } else {
                                                $status_class = 'status-palautettu';
                                                $status_text = 'Palautettu';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td class="<?php echo $row['myohastyymismaksu'] > 0 ? 'text-danger' : ''; ?>">
                                            <?php echo number_format($row['myohastyymismaksu'], 2, ',', ' '); ?>€
                                        </td>
                                    <?php elseif ($report_type === 'devices'): ?>
                                        <td><strong><?php echo htmlspecialchars($row['merkki'] . ' ' . $row['malli']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['tyyppi_nimi'] ?? '-'); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($row['sarjanumero']); ?></span></td>
                                        <td><?php echo ucfirst($row['kunto']); ?></td>
                                        <td><?php echo ucfirst($row['tila']); ?></td>
                                        <td><?php echo htmlspecialchars($row['sijainti'] ?? '-'); ?></td>
                                        <td><strong><?php echo $row['lainauskertoja']; ?></strong></td>
                                        <td>
                                            <?php if ($row['viimeisin_lainaus']): ?>
                                                <?php echo date('d.m.Y', strtotime($row['viimeisin_lainaus'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php elseif ($report_type === 'members'): ?>
                                        <td><strong><?php echo htmlspecialchars($row['sukunimi'] . ' ' . $row['etunimi']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td><?php echo ucfirst($row['jasentyyppi'] ?? 'perus'); ?></td>
                                        <td><strong><?php echo $row['lainauskertoja']; ?></strong></td>
                                        <td><?php echo $row['aktiivisia_lainoja']; ?></td>
                                        <td class="<?php echo $row['myohassa_lainoja'] > 0 ? 'text-danger' : ''; ?>">
                                            <?php echo $row['myohassa_lainoja']; ?>
                                        </td>
                                        <td class="<?php echo $row['sakkoja_yhteensa'] > 0 ? 'text-danger' : ''; ?>">
                                            <?php echo number_format($row['sakkoja_yhteensa'], 2, ',', ' '); ?>€
                                        </td>
                                        <td>
                                            <?php if ($row['viimeisin_lainaus']): ?>
                                                <?php echo date('d.m.Y', strtotime($row['viimeisin_lainaus'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php elseif ($report_type === 'fines'): ?>
                                        <td><?php echo htmlspecialchars($row['jasen_sukunimi'] . ' ' . $row['jasen_etunimi']); ?></td>
                                        <td><strong>#<?php echo str_pad($row['laina_id'] ?? 0, 4, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo htmlspecialchars(($row['merkki'] ?? '') . ' ' . ($row['malli'] ?? '')); ?></td>
                                        <td class="text-danger"><strong><?php echo number_format($row['sakko_maara'], 2, ',', ' '); ?>€</strong></td>
                                        <td><?php echo date('d.m.Y', strtotime($row['sakko_paiva'])); ?></td>
                                        <td>
                                            <?php
                                            $sakko_class = $row['sakko_tila'] == 'maksettu' ? 'status-palautettu' : 'status-myohassa';
                                            ?>
                                            <span class="status-badge <?php echo $sakko_class; ?>">
                                                <?php echo ucfirst($row['sakko_tila']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($row['syy'] ?? '', 0, 30)); ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="20" class="empty-state">
                                    <i class="fas fa-info-circle"></i>
                                    <h3>Ei tietoja näytettäväksi</h3>
                                    <p>Valitse eri suodattimet tai tarkastele toista raporttityyppiä.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Initialize beautiful datepickers
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".datepicker", {
                locale: "fi",
                dateFormat: "Y-m-d",
                allowInput: true,
                altInput: true,
                altFormat: "d.m.Y",
                theme: "material_blue",
                showMonths: 1,
                disableMobile: true,
                nextArrow: '<i class="fas fa-chevron-right"></i>',
                prevArrow: '<i class="fas fa-chevron-left"></i>',
                onChange: function(selectedDates, dateStr, instance) {
                    // Optional: Add validation
                    if (instance.input.id === 'start_date' && document.getElementById('end_date').value) {
                        const startDate = selectedDates[0];
                        const endDate = new Date(document.getElementById('end_date').value);
                        if (startDate && endDate && startDate > endDate) {
                            alert('Alkupäivä ei voi olla loppupäivän jälkeen!');
                            instance.clear();
                        }
                    }
                }
            });

            // Sync start and end date limits
            const startDatePicker = document.getElementById('start_date')._flatpickr;
            const endDatePicker = document.getElementById('end_date')._flatpickr;

            if (startDatePicker && endDatePicker) {
                startDatePicker.config.onChange.push(function(selectedDates) {
                    if (selectedDates[0]) {
                        endDatePicker.set('minDate', selectedDates[0]);
                    }
                });

                endDatePicker.config.onChange.push(function(selectedDates) {
                    if (selectedDates[0]) {
                        startDatePicker.set('maxDate', selectedDates[0]);
                    }
                });
            }
        });

        function resetFilters() {
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            url.searchParams.delete('status');
            url.searchParams.delete('start_date');
            url.searchParams.delete('end_date');
            url.searchParams.delete('member_id');
            window.location.href = url.toString();
        }

        function exportPDF() {
            alert('PDF vienti tulossa...');
        }

        function exportExcel() {
            alert('Excel vienti tulossa...');
        }

        <?php if ($report_type === 'loans' && !empty($chart_data)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx1 = document.getElementById('loansChart').getContext('2d');
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_reverse(array_column($chart_data, 'month'))); ?>,
                    datasets: [{
                        label: 'Lainat',
                        data: <?php echo json_encode(array_reverse(array_column($chart_data, 'total'))); ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 2,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'white',
                            titleColor: '#2C3E50',
                            bodyColor: '#666',
                            borderColor: '#3498DB',
                            borderWidth: 2
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.05)' },
                            ticks: { color: '#666' }
                        },
                        x: { 
                            grid: { display: false },
                            ticks: { color: '#666' }
                        }
                    }
                }
            });

            const ctx2 = document.getElementById('distributionChart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Aktiiviset (ei myöhässä)', 'Myöhässä', 'Palautetut'],
                    datasets: [{
                        data: [
                            <?php echo max(0, $stats['active_loans'] - $stats['overdue_loans']); ?>,
                            <?php echo $stats['overdue_loans']; ?>,
                            <?php echo $stats['returned_loans']; ?>
                        ],
                        backgroundColor: [
                            'rgba(52, 152, 219, 0.8)',
                            'rgba(231, 76, 60, 0.8)',
                            'rgba(46, 204, 113, 0.8)'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'bottom',
                            labels: { color: '#666' }
                        },
                        tooltip: {
                            backgroundColor: 'white',
                            titleColor: '#2C3E50',
                            bodyColor: '#666',
                            borderColor: '#3498DB',
                            borderWidth: 2
                        }
                    },
                    cutout: '65%'
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>
