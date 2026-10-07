<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'connection.php';

// --- ADD THE FUNCTION HERE ---
function redirectByRole($rooli) {
    if ($rooli === 'admin') {
        header("Location: admin_dashboard.php");
    } elseif ($rooli === 'manager') {
        header("Location: manager_dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit();
}

// Tarkista että käyttäjä on kirjautunut
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Hae käyttäjän tiedot
$user_id = $_SESSION['user_id'];
$user_query = "SELECT rooli, etunimi, sukunimi, email, profile_image FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

// ============================================
// FIXED: ONLY MANAGER can access manager dashboard
// ============================================

// If user not found, redirect to login
if (!$current_user) {
    header("Location: login.php");
    exit();
}

// If admin tries to access, redirect to admin dashboard
if ($current_user['rooli'] == 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// If regular user tries to access, redirect to user dashboard
if ($current_user['rooli'] == 'user') {
    header("Location: user_dashboard.php");
    exit();
}

// Only manager can continue
if ($current_user['rooli'] != 'manager') {
    redirectByRole($current_user['rooli']);
}

// ============================================
// END OF ROLE CHECK
// ============================================

// Haetaan järjestelmän tilastot
$stats = [
    'aktiiviset_lainat' => 0,
    'aktiiviset_kirjalainat' => 0,
    'aktiiviset_laitelainat' => 0,
    'myohassa' => 0,
    'varauksia' => 0,
    'viesteja' => 0,
    'kirjoja' => 0,
    'laitteita' => 0,
    'sakkoja' => 0,
    'jasenia' => 0
];

try {
    // Kirjojen määrä
    $result = $conn->query("SELECT COUNT(*) as maara FROM kirjat");
    $stats['kirjoja'] = $result->fetch_assoc()['maara'];

    // Laitteiden määrä
    $result = $conn->query("SELECT COUNT(*) as maara FROM Laitteet");
    $stats['laitteita'] = $result->fetch_assoc()['maara'];

    // Jäsenten määrä (aktiiviset)
    $result = $conn->query("SELECT COUNT(*) as maara FROM jasenet WHERE tila = 'aktiivinen'");
    $stats['jasenia'] = $result->fetch_assoc()['maara'];

    // Aktiiviset kirjalainat
    $result = $conn->query("SELECT COUNT(*) as maara FROM lainat WHERE tila = 'aktiivinen'");
    $stats['aktiiviset_kirjalainat'] = $result->fetch_assoc()['maara'];

    // Aktiiviset laitelainat
    $result = $conn->query("SELECT COUNT(*) as maara FROM Laitelainat WHERE palautus_pvm IS NULL");
    $stats['aktiiviset_laitelainat'] = $result->fetch_assoc()['maara'];

    // Yhteensä aktiiviset lainat
    $stats['aktiiviset_lainat'] = $stats['aktiiviset_kirjalainat'] + $stats['aktiiviset_laitelainat'];

    // Myöhässä olevat lainat
    $result = $conn->query("SELECT COUNT(*) as maara FROM Laitelainat WHERE palautus_pvm IS NULL AND erapaiva < CURDATE()");
    $stats['myohassa'] = $result->fetch_assoc()['maara'];
    $result = $conn->query("SELECT COUNT(*) as maara FROM lainat WHERE tila = 'aktiivinen' AND erapaiva < CURDATE()");
    $stats['myohassa'] += $result->fetch_assoc()['maara'];
    
    // Odottavat varaukset
    $result = $conn->query("SELECT COUNT(*) as maara FROM Laitevaraukset WHERE tila = 'odottaa'");
    $stats['varauksia'] = $result->fetch_assoc()['maara'];
    $result = $conn->query("SELECT COUNT(*) as maara FROM varaukset WHERE tila = 'odottaa'");
    $stats['varauksia'] += $result->fetch_assoc()['maara'];

    // Sakkojen kokonaismäärä
    $result = $conn->query("SELECT COALESCE(SUM(sakko_maara - maksettu_maara), 0) as summa FROM sakot WHERE tila IN ('maksettava', 'osittain')");
    $stats['sakkoja'] = $result->fetch_assoc()['summa'];

    // Lukemattomat viestit (vain henkilökohtaiset)
    $stmt2 = $conn->prepare("SELECT COUNT(*) as maara FROM viestit WHERE vastaanottaja_id = ? AND luettu = 0");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $result = $stmt2->get_result();
    $stats['viesteja'] = $result->fetch_assoc()['maara'];
    $stmt2->close();

} catch (Exception $e) {
    error_log("Manager dashboard error: " . $e->getMessage());
}

// Hae viimeisimmät keskustelut (käyttäjät joilta on viestejä)
$keskustelut = [];
try {
    $stmt3 = $conn->prepare("
        SELECT DISTINCT
            j.id,
            j.etunimi,
            j.sukunimi,
            j.profile_image,
            (SELECT COUNT(*) FROM viestit WHERE lahettaja_id = j.id AND vastaanottaja_id = ? AND luettu = 0) as lukemattomat,
            (SELECT viesti FROM viestit WHERE (lahettaja_id = j.id AND vastaanottaja_id = ?) OR (lahettaja_id = ? AND vastaanottaja_id = j.id) ORDER BY luontiaika DESC LIMIT 1) as viimeisin_viesti,
            (SELECT luontiaika FROM viestit WHERE (lahettaja_id = j.id AND vastaanottaja_id = ?) OR (lahettaja_id = ? AND vastaanottaja_id = j.id) ORDER BY luontiaika DESC LIMIT 1) as viimeisin_aika
        FROM jasenet j
        WHERE j.id IN (
            SELECT lahettaja_id FROM viestit WHERE vastaanottaja_id = ?
            UNION
            SELECT vastaanottaja_id FROM viestit WHERE lahettaja_id = ?
        )
        ORDER BY viimeisin_aika DESC
        LIMIT 5
    ");

    $stmt3->bind_param("iiiiiii",
        $user_id,
        $user_id,
        $user_id,
        $user_id,
        $user_id,
        $user_id,
        $user_id
    );

    $stmt3->execute();
    $result = $stmt3->get_result();
    while ($row = $result->fetch_assoc()) {
        $keskustelut[] = $row;
    }
    $stmt3->close();
} catch (Exception $e) {
    error_log("Error fetching conversations: " . $e->getMessage());
}

// Hae viimeisimmät ryhmäviestit
$ryhmaviestit = [];
try {
    $result = $conn->query("
        SELECT r.*, j.etunimi, j.sukunimi, j.profile_image
        FROM ryhmaviestit r
        JOIN jasenet j ON r.lahettaja_id = j.id
        ORDER BY r.luontiaika DESC
        LIMIT 3
    ");
    while ($row = $result->fetch_assoc()) {
        $ryhmaviestit[] = $row;
    }
} catch (Exception $e) {}

// Tämän päivän tapahtumat
$tanaan = date('Y-m-d');
$paivan_tapahtumat = [
    'lainat' => 0,
    'palautukset' => 0,
    'varaukset' => 0
];

try {
    $result = $conn->query("SELECT COUNT(*) as maara FROM Laitelainat WHERE DATE(lainaus_pvm) = '$tanaan'");
    $paivan_tapahtumat['lainat'] = $result->fetch_assoc()['maara'];
    $result = $conn->query("SELECT COUNT(*) as maara FROM lainat WHERE DATE(lainauspaiva) = '$tanaan'");
    $paivan_tapahtumat['lainat'] += $result->fetch_assoc()['maara'];

    $result = $conn->query("SELECT COUNT(*) as maara FROM Laitelainat WHERE DATE(palautus_pvm) = '$tanaan'");
    $paivan_tapahtumat['palautukset'] = $result->fetch_assoc()['maara'];
    $result = $conn->query("SELECT COUNT(*) as maara FROM lainat WHERE DATE(palautuspaiva) = '$tanaan'");
    $paivan_tapahtumat['palautukset'] += $result->fetch_assoc()['maara'];

    $result = $conn->query("SELECT COUNT(*) as maara FROM Laitevaraukset WHERE DATE(varaus_pvm) = '$tanaan'");
    $paivan_tapahtumat['varaukset'] = $result->fetch_assoc()['maara'];
    $result = $conn->query("SELECT COUNT(*) as maara FROM varaukset WHERE DATE(varaus_pvm) = '$tanaan'");
    $paivan_tapahtumat['varaukset'] += $result->fetch_assoc()['maara'];

} catch (Exception $e) {}

// Hae viimeisimmät tapahtumat
$recent_activities = [];
try {
    $result = $conn->query("
        (SELECT
            'laina' as tyyppi,
            l.lainaus_pvm as aika,
            CONCAT(j.etunimi, ' ', j.sukunimi) as kayttaja,
            CONCAT(d.merkki, ' ', d.malli) as kohde
        FROM Laitelainat l
        JOIN jasenet j ON l.jasen_id = j.id
        JOIN Laitteet d ON l.laite_id = d.id
        ORDER BY l.lainaus_pvm DESC LIMIT 3)
        UNION ALL
        (SELECT
            'laina' as tyyppi,
            l.lainauspaiva as aika,
            CONCAT(j.etunimi, ' ', j.sukunimi) as kayttaja,
            k.nimi as kohde
        FROM lainat l
        JOIN jasenet j ON l.jasen_id = j.id
        JOIN kirjat k ON l.kirja_id = k.id
        ORDER BY l.lainauspaiva DESC LIMIT 3)
        UNION ALL
        (SELECT
            'varaus' as tyyppi,
            v.varaus_pvm as aika,
            CONCAT(j.etunimi, ' ', j.sukunimi) as kayttaja,
            CONCAT(d.merkki, ' ', d.malli) as kohde
        FROM Laitevaraukset v
        JOIN jasenet j ON v.jasen_id = j.id
        JOIN Laitteet d ON v.laite_id = d.id
        ORDER BY v.varaus_pvm DESC LIMIT 3)
        ORDER BY aika DESC LIMIT 5
    ");
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
} catch (Exception $e) {}

// Profiilikuva
$profile_image = $current_user['profile_image'] ?? null;
$initials = strtoupper(substr($current_user['etunimi'] ?? '', 0, 1) . substr($current_user['sukunimi'] ?? '', 0, 1));
$display_image = null;
if ($profile_image && file_exists("uploads/profiles/" . $profile_image)) {
    $display_image = "uploads/profiles/" . $profile_image;
}
?>

<!-- Tästä alkaa sama HTML kuin sinulla oli, mutta korjattu muutama kohta -->

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard | Kirjaston Hallintajärjestelmä</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(rgba(26, 26, 46, 0.3), rgba(26, 26, 46, 0.3)),
                        url('https://images.unsplash.com/photo-1521587760476-6c12a4b040da?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            color: #fff;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-animation span {
            position: absolute;
            display: block;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.05);
            animation: animate 25s linear infinite;
            bottom: -150px;
            border-radius: 50%;
        }

        @keyframes animate {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.5;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
            }
        }

        /* Glassmorphism Sidebar */
        .sidebar {
            position: fixed;
            left: 30px;
            top: 30px;
            bottom: 30px;
            width: 300px;
            background: rgba(25, 25, 25, 0.5);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            padding: 25px 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }

        .sidebar-header {
            padding: 0 25px 25px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            font-size: 1.8em;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
        }

        .manager-badge {
            background: linear-gradient(135deg, var(--secondary), #c0392b);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 20px -5px rgba(231, 76, 60, 0.3);
        }

        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 20px 15px;
        }

        .sidebar-menu::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            background: rgba(25, 25, 25, 0.5);
            border-radius: 10px;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.5);
            border-radius: 10px;
        }

        .menu-section {
            font-size: 0.75em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255, 255, 255, 0.5);
            padding: 20px 15px 8px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 15px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin: 4px 0;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, rgba(25, 25, 25, 0.5), transparent);
            transition: width 0.4s ease;
        }

        .menu-item:hover::before {
            width: 100%;
        }

        .menu-item i {
            width: 24px;
            font-size: 1.3em;
            color: var(--info);
            transition: all 0.3s;
            position: relative;
            z-index: 1;
        }

        .menu-item span {
            position: relative;
            z-index: 1;
        }

        .menu-item:hover {
            transform: translateX(8px);
            color: white;
        }

        .menu-item:hover i {
            transform: scale(1.1);
            color: var(--success);
        }

        .menu-item.active {
            background: linear-gradient(135deg, var(--info), #2980b9);
            color: white;
            box-shadow: 0 10px 20px -5px rgba(52, 152, 219, 0.5);
        }

        .menu-item.active i {
            color: white;
        }

        .view-only {
            opacity: 0.7;
            position: relative;
        }

        .view-only::after {
            content:'';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.9em;
            opacity: 0.5;
        }
        .logout-item {
            margin: 20px 15px;
            background: rgba(25, 25, 25, 0.5);
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .logout-item:hover {
            background: linear-gradient(135deg, var(--secondary), #c0392b);
            border-color: transparent;
        }

        /* Main Content */
        .main-content {
            margin-left: 360px;
            padding: 30px;
            position: relative;
            z-index: 10;
        }

        /* Header */
        .header {
            background: rgba(25, 25, 25, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 25px 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 2.2em;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255, 255, 255, 0.15);
            padding: 12px 25px;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-info:hover {
            background: rgba(25, 25, 25, 0.5);
            transform: translateY(-2px);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--info);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .initials {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--info), #2980b9);
            color: white;
            font-size: 1.3em;
            font-weight: bold;
        }

        .user-info-text {
            color: white;
        }

        .user-info-text .name {
            font-weight: 600;
            font-size: 1.1em;
        }

        .user-info-text .role {
            font-size: 0.85em;
            opacity: 0.8;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(25, 25, 25, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--info), var(--success));
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.5);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-header h3 {
            font-size: 0.95em;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.2), rgba(39, 174, 96, 0.2));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: var(--info);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }

        .stat-trend {
            color: var(--success);
            font-size: 0.85em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Message Center */
        .message-center {
            background: rgba(25, 25, 25, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 30px;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .message-header h3 {
            font-size: 1.3em;
            color: white;
        }

        .message-tabs {
            display: flex;
            gap: 10px;
        }

        .message-tab {
            padding: 8px 16px;
            background: rgba(25, 25, 25, 0.5);
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9em;
            border: 1px solid transparent;
        }

        .message-tab:hover {
            background: rgba(25, 25, 25, 0.5);
        }

        .message-tab.active {
            background: var(--info);
            color: white;
        }

        .conversations-list {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(25, 25, 25, 0.5);
            border-radius: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .conversation-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .conversation-item.unread {
            background: rgba(52, 152, 219, 0.2);
            border-left: 4px solid var(--info);
        }

        .conversation-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, var(--info), var(--success));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .conversation-info {
            flex: 1;
        }

        .conversation-name {
            font-weight: 600;
            color: white;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .conversation-preview {
            font-size: 0.85em;
            color: rgba(255, 255, 255, 0.7);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .conversation-time {
            font-size: 0.75em;
            color: rgba(255, 255, 255, 0.5);
        }

        .unread-count {
            background: var(--secondary);
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.75em;
        }

        .group-messages {
            margin-top: 20px;
        }

        .group-message {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: rgba(25, 25, 25, 0.5);
            border-radius: 15px;
            margin-bottom: 10px;
        }

        .group-message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, var(--purple), var(--info));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .group-message-content {
            flex: 1;
        }

        .group-message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .group-message-sender {
            font-weight: 600;
            color: white;
        }

        .group-message-time {
            font-size: 0.75em;
            color: rgba(255, 255, 255, 0.5);
        }

        .group-message-text {
            color: rgba(255, 255, 255, 0.9);
        }

        .group-message-badge {
            background: var(--warning);
            color: var(--dark);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7em;
            margin-left: 8px;
        }

        .message-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .message-modal-content {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .message-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            color: white;
        }

        .message-modal-close {
            font-size: 1.5em;
            cursor: pointer;
            opacity: 0.7;
            transition: all 0.3s;
        }

        .message-modal-close:hover {
            opacity: 1;
        }

        .message-modal-user {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .message-history {
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
            background: rgba(0,0,0,0.3);
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .message-bubble {
            margin-bottom: 15px;
            max-width: 80%;
        }

        .message-bubble.sent {
            margin-left: auto;
        }

        .message-bubble.received {
            margin-right: auto;
        }

        .bubble-content {
            padding: 10px 15px;
            border-radius: 18px;
            background: var(--info);
            color: white;
            display: inline-block;
            max-width: 100%;
            word-wrap: break-word;
        }

        .message-bubble.received .bubble-content {
            background: rgba(25, 25, 25, 0.5);
        }

        .bubble-time {
            font-size: 0.7em;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 4px;
            text-align: right;
        }

        .message-input-area {
            display: flex;
            gap: 10px;
        }

        .message-input {
            flex: 1;
            padding: 12px 16px;
            background: rgba(25, 25, 25, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            color: white;
            font-size: 0.95em;
        }

        .message-input:focus {
            outline: none;
            border-color: var(--info);
        }

        .message-send {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--info), var(--success));
            border: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .message-send:hover {
            transform: scale(1.1);
        }

        /* Today Stats */
        .today-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .today-card {
            background: rgba(25, 25, 25, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .today-card i {
            font-size: 2em;
            color: var(--info);
            margin-bottom: 10px;
        }

        .today-card .number {
            font-size: 2em;
            font-weight: 700;
            color: white;
        }

        .today-card .label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9em;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .quick-action {
            background: rgba(25, 25, 25, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
        }

        .quick-action:hover {
            transform: translateY(-5px);
            background: rgba(52, 152, 219, 0.2);
            border-color: var(--info);
        }

        .quick-action i {
            font-size: 2em;
            color: var(--info);
            margin-bottom: 10px;
        }

        .quick-action span {
            display: block;
            color: white;
            font-weight: 500;
        }

        /* Dashboard Links */
        .dashboard-links {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .dashboard-link {
            background: rgba(25, 25, 25, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dashboard-link:hover {
            transform: translateY(-5px);
            background: rgba(52, 152, 219, 0.2);
        }

        .dashboard-link i {
            font-size: 2.5em;
            color: var(--info);
            margin-bottom: 10px;
        }

        .dashboard-link h3 {
            color: white;
            margin-bottom: 5px;
        }

        .dashboard-link p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9em;
        }

        /* Alerts Section */
        .alerts-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .alert-card {
            background: rgba(25, 25, 25, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            border-left: 4px solid;
        }

        .alert-card.warning { border-color: var(--warning); }
        .alert-card.danger { border-color: var(--secondary); }
        .alert-card.info { border-color: var(--info); }

        .alert-card h4 {
            color: white;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-card .number {
            font-size: 2em;
            font-weight: 700;
            color: white;
        }

        /* Activity Section */
        .activity-section {
            background: rgba(25, 25, 25, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 40px;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .activity-header h3 {
            font-size: 1.3em;
            color: white;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(25, 25, 25, 0.5);
            border-radius: 15px;
            transition: all 0.3s;
        }

        .activity-item:hover {
            background: rgba(275, 275, 275, 0.3);
            transform: translateX(10px);
        }

        .activity-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--info), var(--success));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            color: white;
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: white;
            margin-bottom: 5px;
        }

        .activity-time {
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.5);
        }

        /* Notification Badge */
        .notification-badge {
            display: inline-block;
            background: rgba(25, 25, 25, 0.5);
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7em;
            margin-left: 8px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Animations */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card, .today-card, .quick-action, .dashboard-link, .alert-card, .activity-section, .message-center {
            animation: slideUp 0.6s ease forwards;
            opacity: 0;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            .alerts-section {
                grid-template-columns: repeat(2, 1fr);
            }
            .today-stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                left: 15px;
                right: 15px;
                top: 15px;
                bottom: auto;
                width: auto;
                height: auto;
                max-height: calc(100vh - 30px);
            }
            .main-content {
                margin-left: 0;
                margin-top: 400px;
                padding: 15px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .today-stats {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                grid-template-columns: 1fr;
            }
            .dashboard-links {
                grid-template-columns: 1fr;
            }
            .alerts-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <span style="left: 10%; animation-duration: 15s;"></span>
        <span style="left: 20%; animation-duration: 20s; animation-delay: 2s;"></span>
        <span style="left: 30%; animation-duration: 18s; animation-delay: 4s;"></span>
        <span style="left: 40%; animation-duration: 22s; animation-delay: 1s;"></span>
        <span style="left: 50%; animation-duration: 25s; animation-delay: 3s;"></span>
        <span style="left: 60%; animation-duration: 16s; animation-delay: 5s;"></span>
        <span style="left: 70%; animation-duration: 19s; animation-delay: 2.5s;"></span>
        <span style="left: 80%; animation-duration: 21s; animation-delay: 4.5s;"></span>
        <span style="left: 90%; animation-duration: 23s; animation-delay: 1.5s;"></span>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Manager Portal</h2>
            <div class="manager-badge">
                <i class="fas fa-user-tie"></i> <?php echo $current_user['rooli'] === 'admin' ? 'Admin' : 'Manager'; ?>
            </div>
        </div>

        <div class="sidebar-menu">
            <div class="menu-section">⚙️ Päävalikko</div>
            <a href="manager_dashboard.php" class="menu-item active">
                <i class="fas fa-home"></i>
                <span>Kojelauta</span>
            </a>

           <div class="menu-section">📊 Muut kojelaudat</div>
           <a href="admin_dashboard.php" class="menu-item">
               <i class="fas fa-crown"></i>
               <span>Admin Dashboard</span>
           </a>

            <a href="user_dashboard.php" class="menu-item">
                <i class="fas fa-user"></i>
                <span>User Dashboard</span>
            </a>

            <div class="menu-section">📚 Kirjaston Hallinta</div>
            <a href="admin_manage_kirjat.php" class="menu-item">
                <i class="fas fa-book"></i>
                <span>Hallinnoi Kirjoja</span>
            </a>
            <a href="admin_lisaa_kirja.php" class="menu-item">
                <i class="fas fa-plus-circle"></i>
                <span>Lisää Kirja</span>
            </a>
            <a href="admin_muokkaa_kirjaa.php" class="menu-item">
                <i class="fas fa-edit"></i>
                <span>Muokkaa Kirjoja</span>
            </a>

            <div class="menu-section">👥 Jäsenten Hallinta</div>
            <a href="admin_kayttajien_hallinta.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Hallinnoi Jäseniä</span>
            </a>
            <a href="register.php" class="menu-item">
                <i class="fas fa-user-plus"></i>
                <span>Rekisteröi Jäsen</span>
            </a>

            <div class="menu-section">🔄 Lainaushallinta</div>
            <a href="admin_lainat.php" class="menu-item">
                <i class="fas fa-exchange-alt"></i>
                <span>Hallinnoi Lainoja</span>
            </a>
            <a href="admin_varaukset.php" class="menu-item">
                <i class="fas fa-check-circle"></i>
                <span>Käsittele Lainoja</span>
            </a>
            <a href="admin_palautukset.php" class="menu-item">
                <i class="fas fa-undo-alt"></i>
                <span>Hallinnoi Palautuksia</span>
            </a>

            <div class="menu-section">🖥️ Laitehallinta</div>
            <a href="admin_laitetyypit.php" class="menu-item">
                <i class="fas fa-tags"></i>
                <span>Laitetyypit</span>
            </a>
            <a href="admin_laitteet.php" class="menu-item">
                <i class="fas fa-laptop"></i>
                <span>Laitteet</span>
            </a>
            <a href="admin_laitevaraukset.php" class="menu-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Laitevaraukset</span>
            </a>
            <a href="laiteadmin_lainat.php" class="menu-item">
                <i class="fas fa-hand-holding"></i>
                <span>Laitelainat</span>
            </a>

            <div class="menu-section">📊 Raportit & Sakot</div>
            <a href="manager_admin_raportit.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Kirjasto Raportit</span>
            </a>
            <a href="manager_admin_sakot.php" class="menu-item">
                <i class="fas fa-euro-sign"></i>
                <span>Hallinnoi Sakkoja</span>
            </a>
            <a href="manager_kuitit.php" class="menu-item">
                 <i class="fas fa-receipt"></i>
                 <span>Kuitit</span>
            </a>

            <div class="menu-section">📨 Viestit</div>
            <a href="manager_viestit.php" class="menu-item">
                <i class="fas fa-envelope"></i>
                <span>Hallinnoi Viestit</span>
            </a>
            <a href="manager_ryhmaviestit.php" class="menu-item">
                <i class="fas fa-bullhorn"></i>
                <span>Ryhmäviestit</span>
            </a>
            <a href="viestiasetukset.php" class="menu-item view-only">
                <i class="fas fa-cog"></i>
                <span>Viestiasetukset</span>
            </a>

            <div class="menu-section">🔧 Järjestelmä</div>
            <a href="admin_varmuuskopiointi.php" class="menu-item view-only">
                <i class="fas fa-database"></i>
                <span>Varmuuskopiot</span>
            </a>
            <a href="admin_kayttooikeudet.php" class="menu-item view-only">
                <i class="fas fa-shield-alt"></i>
                <span>Käyttöoikeudet</span>
            </a>
            <a href="admin_palvelin_lokit.php" class="menu-item">
                <i class="fas fa-history"></i>
                <span>Palvelinlokit</span>
            </a>

            <a href="logout.php" class="menu-item logout-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Kirjaudu Ulos</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1>Manager Dashboard</h1>
            <div class="user-info" onclick="window.location.href='profile.php'">
                <div class="user-avatar">
                    <?php if ($display_image): ?>
                        <img src="<?php echo $display_image; ?>" alt="Profile">
                    <?php else: ?>
                        <div class="initials"><?php echo $initials; ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-info-text">
                    <div class="name"><?php echo htmlspecialchars($current_user['etunimi'] . ' ' . $current_user['sukunimi']); ?></div>
                    <div class="role"><?php echo $current_user['rooli'] === 'admin' ? 'Järjestelmävalvoja' : 'Hallinnoija'; ?></div>
                </div>
            </div>
        </div>

<!-- Stats Grid -->
<div class="stats-grid">
    <!-- Kirjalainat kortti -->
    <div class="stat-card">
        <div class="stat-header">
            <h3>Kirjalainat</h3>
            <div class="stat-icon"><i class="fas fa-book"></i></div>
        </div>
        <div class="stat-number"><?php echo $stats['aktiiviset_kirjalainat']; ?></div>
        <div class="stat-trend">
            <i class="fas fa-check-circle"></i> Aktiiviset kirjalainat
        </div>
    </div>

    <!-- Laitelainat kortti -->
    <div class="stat-card">
        <div class="stat-header">
            <h3>Laitelainat</h3>
            <div class="stat-icon"><i class="fas fa-laptop"></i></div>
        </div>
        <div class="stat-number"><?php echo $stats['aktiiviset_laitelainat']; ?></div>
        <div class="stat-trend">
            <i class="fas fa-check-circle"></i> Aktiiviset laitelainat
        </div>
    </div>

            <div class="stat-card">
                <div class="stat-header">
                    <h3>Myöhässä</h3>
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-number"><?php echo $stats['myohassa']; ?></div>
                <div class="stat-trend" style="color: var(--secondary);">
                    <i class="fas fa-exclamation-circle"></i> Tarkista heti
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <h3>Odottavat varaukset</h3>
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                </div>
                <div class="stat-number"><?php echo $stats['varauksia']; ?></div>
                <div class="stat-trend">
                    <i class="fas fa-clock"></i> Odottaa hyväksyntää
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <h3>Lukemattomat</h3>
                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                </div>
                <div class="stat-number"><?php echo $stats['viesteja']; ?></div>
                <div class="stat-trend">
                    <i class="fas fa-envelope-open"></i> Uutta viestiä
                </div>
            </div>
        </div>

        <!-- Message Center -->
        <div class="message-center">
            <div class="message-header">
                <h3><i class="fas fa-comments"></i> Viestikeskus</h3>
                <div class="message-tabs">
                    <div class="message-tab active" onclick="switchMessageTab('personal')">Henkilökohtaiset</div>
                    <div class="message-tab" onclick="switchMessageTab('group')">Ryhmäviestit</div>
                </div>
            </div>

            <!-- Henkilökohtaiset viestit -->
            <div id="personalMessages" style="display: block;">
                <div class="conversations-list">
                    <?php if (empty($keskustelut)): ?>
                        <div style="text-align: center; padding: 30px; color: rgba(255,255,255,0.5);">
                            <i class="fas fa-inbox" style="font-size: 2em; margin-bottom: 10px;"></i>
                            <p>Ei keskusteluja</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($keskustelut as $keskustelu): ?>
                            <div class="conversation-item <?php echo isset($keskustelu['lukemattomat']) && $keskustelu['lukemattomat'] > 0 ? 'unread' : ''; ?>"
                                 onclick="openConversation(<?php echo $keskustelu['id']; ?>, '<?php echo htmlspecialchars($keskustelu['etunimi'] . ' ' . $keskustelu['sukunimi']); ?>')">
                                <div class="conversation-avatar">
                                    <?php if (!empty($keskustelu['profile_image']) && file_exists("uploads/profiles/" . $keskustelu['profile_image'])): ?>
                                        <img src="uploads/profiles/<?php echo $keskustelu['profile_image']; ?>" alt="">
                                    <?php else: ?>
                                        <span><?php echo strtoupper(substr($keskustelu['etunimi'], 0, 1) . substr($keskustelu['sukunimi'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-info">
                                    <div class="conversation-name">
                                        <?php echo htmlspecialchars($keskustelu['etunimi'] . ' ' . $keskustelu['sukunimi']); ?>
                                        <?php if (isset($keskustelu['lukemattomat']) && $keskustelu['lukemattomat'] > 0): ?>
                                            <span class="unread-count"><?php echo $keskustelu['lukemattomat']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conversation-preview">
                                        <?php echo isset($keskustelu['viimeisin_viesti']) ? htmlspecialchars(substr($keskustelu['viimeisin_viesti'], 0, 50)) . '...' : 'Ei viestejä'; ?>
                                    </div>
                                </div>
                                <div class="conversation-time">
                                    <?php echo isset($keskustelu['viimeisin_aika']) ? date('H:i', strtotime($keskustelu['viimeisin_aika'])) : ''; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ryhmäviestit -->
            <div id="groupMessages" style="display: none;">
                <div class="group-messages">
                    <?php if (empty($ryhmaviestit)): ?>
                        <div style="text-align: center; padding: 30px; color: rgba(255,255,255,0.5);">
                            <i class="fas fa-bullhorn" style="font-size: 2em; margin-bottom: 10px;"></i>
                            <p>Ei ryhmäviestejä</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($ryhmaviestit as $viesti): ?>
                            <div class="group-message">
                                <div class="group-message-avatar">
                                    <?php if (!empty($viesti['profile_image']) && file_exists("uploads/profiles/" . $viesti['profile_image'])): ?>
                                        <img src="uploads/profiles/<?php echo $viesti['profile_image']; ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <span><?php echo strtoupper(substr($viesti['etunimi'], 0, 1) . substr($viesti['sukunimi'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="group-message-content">
                                    <div class="group-message-header">
                                        <span class="group-message-sender">
                                            <?php echo htmlspecialchars($viesti['etunimi'] . ' ' . $viesti['sukunimi']); ?>
                                            <?php if (isset($viesti['on_ilmoitus']) && $viesti['on_ilmoitus']): ?>
                                                <span class="group-message-badge">Ilmoitus</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="group-message-time"><?php echo date('H:i', strtotime($viesti['luontiaika'])); ?></span>
                                    </div>
                                    <div class="group-message-text">
                                        <?php echo nl2br(htmlspecialchars($viesti['viesti'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Today's Stats -->
        <div class="today-stats">
            <div class="today-card">
                <i class="fas fa-plus-circle"></i>
                <div class="number"><?php echo $paivan_tapahtumat['lainat']; ?></div>
                <div class="label">Lainattu tänään</div>
            </div>
            <div class="today-card">
                <i class="fas fa-undo-alt"></i>
                <div class="number"><?php echo $paivan_tapahtumat['palautukset']; ?></div>
                <div class="label">Palautettu tänään</div>
            </div>
            <div class="today-card">
                <i class="fas fa-calendar-plus"></i>
                <div class="number"><?php echo $paivan_tapahtumat['varaukset']; ?></div>
                <div class="label">Uusia varauksia</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action" onclick="window.location.href='uusi_laina.php'">
                <i class="fas fa-plus-circle"></i>
                <span>Uusi laina</span>
            </div>
            <div class="quick-action" onclick="window.location.href='admin_palautukset.php'">
                <i class="fas fa-undo-alt"></i>
                <span>Palautus</span>
            </div>
            <div class="quick-action" onclick="window.location.href='admin_varaukset.php'">
                <i class="fas fa-check-circle"></i>
                <span>Hyväksy varauksia</span>
                <?php if ($stats['varauksia'] > 0): ?>
                    <span class="notification-badge" style="position: absolute; top: 10px; right: 10px;"><?php echo $stats['varauksia']; ?></span>
                <?php endif; ?>
            </div>
            <div class="quick-action" onclick="window.location.href='viestit.php'">
                <i class="fas fa-envelope"></i>
                <span>Viestit</span>
                <?php if ($stats['viesteja'] > 0): ?>
                    <span class="notification-badge" style="position: absolute; top: 10px; right: 10px;"><?php echo $stats['viesteja']; ?></span>
                <?php endif; ?>
            </div>
        </div>

      <!-- Dashboard Links -->
       <div class="dashboard-links">
         <div class="dashboard-link"onclick="window.location.href='admin_dashboard.php'">
            <i class="fas fa-crown"></i>
             <h3>Admin Dashboard</h3>
                <p>Siirry järjestelmän hallintaan</p>
         </div>
         <div class="dashboard-link"onclick="window.location.href='user_dashboard.php'">
             <i class="fas fa-user"></i>
              <h3>User Dashboard</h3>
                <p>Katso miltä kirjasto näyttää jäsenille</p>
        </div>
 </div>
        <!-- Alerts Section -->
        <div class="alerts-section">
            <div class="alert-card warning">
                <h4><i class="fas fa-clock"></i> Myöhässä</h4>
                <div class="number"><?php echo $stats['myohassa']; ?></div>
                <div style="color: rgba(255,255,255,0.7);">lainaa myöhässä</div>
            </div>
            <div class="alert-card danger">
                <h4><i class="fas fa-euro-sign"></i> Sakot</h4>
                <div class="number"><?php echo number_format($stats['sakkoja'], 2, ',', ' '); ?> €</div>
                <div style="color: rgba(255,255,255,0.7);">maksamatta</div>
            </div>
            <div class="alert-card info">
                <h4><i class="fas fa-calendar-plus"></i> Varaukset</h4>
                <div class="number"><?php echo $stats['varauksia']; ?></div>
                <div style="color: rgba(255,255,255,0.7);">odottaa hyväksyntää</div>
            </div>
        </div>

        <!-- Activity Section -->
        <div class="activity-section">
            <div class="activity-header">
                <h3><i class="fas fa-history"></i> Viimeisimmät tapahtumat</h3>
                <span style="color: rgba(255,255,255,0.5);"><?php echo date('d.m.Y'); ?></span>
            </div>
            <div class="activity-list">
                <?php if (!empty($recent_activities)): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-<?php echo $activity['tyyppi'] === 'laina' ? 'hand-holding' : 'calendar-check'; ?>"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-title">
                                    <?php echo htmlspecialchars($activity['kayttaja']); ?>
                                    <?php echo $activity['tyyppi'] === 'laina' ? 'lainasi' : 'varasi'; ?>
                                    <?php echo htmlspecialchars($activity['kohde']); ?>
                                </div>
                                <div class="activity-time"><?php echo date('d.m.Y H:i', strtotime($activity['aika'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-info-circle"></i></div>
                        <div class="activity-details">
                            <div class="activity-title">Ei viimeaikaisia tapahtumia</div>
                            <div class="activity-time">-</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Library Stats Summary -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px;">
            <div style="background: rgba(25,25,25,0.5); backdrop-filter: blur(10px); border-radius: 15px; padding: 15px; text-align: center;">
                <i class="fas fa-book" style="color: var(--info); font-size: 1.5em;"></i>
                <div style="font-size: 1.3em; font-weight: 700; color: white;"><?php echo $stats['kirjoja']; ?></div>
                <div style="color: rgba(255,255,255,0.7);">Kirjaa</div>
            </div>
            <div style="background: rgba(25,25,25,0.5); backdrop-filter: blur(10px); border-radius: 15px; padding: 15px; text-align: center;">
                <i class="fas fa-laptop" style="color: var(--success); font-size: 1.5em;"></i>
                <div style="font-size: 1.3em; font-weight: 700; color: white;"><?php echo $stats['laitteita']; ?></div>
                <div style="color: rgba(255,255,255,0.7);">Laitetta</div>
            </div>
            <div style="background: rgba(25,25,25,0.5); backdrop-filter: blur(10px); border-radius: 15px; padding: 15px; text-align: center;">
                <i class="fas fa-users" style="color: var(--warning); font-size: 1.5em;"></i>
                <div style="font-size: 1.3em; font-weight: 700; color: white;"><?php echo $stats['jasenia']; ?></div>
                <div style="color: rgba(255,255,255,0.7);">Jäsentä</div>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div class="message-modal" id="messageModal">
        <div class="message-modal-content">
            <div class="message-modal-header">
                <h3 id="modalUserName">Viestit</h3>
                <span class="message-modal-close" onclick="closeMessageModal()">&times;</span>
            </div>
            <div class="message-modal-user" id="modalUserInfo"></div>
            <div class="message-history" id="messageHistory"></div>
            <div class="message-input-area">
                <input type="text" class="message-input" id="messageInput" placeholder="Kirjoita viesti..." onkeypress="if(event.key === 'Enter') sendMessage()">
                <button class="message-send" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script>
        // Viestit - JavaScript
        let currentChatUserId = null;
        let currentChatUserName = null;

        function switchMessageTab(tab) {
            const personal = document.getElementById('personalMessages');
            const group = document.getElementById('groupMessages');
            const tabs = document.querySelectorAll('.message-tab');

            tabs.forEach(t => t.classList.remove('active'));

            if (tab === 'personal') {
                personal.style.display = 'block';
                group.style.display = 'none';
                tabs[0].classList.add('active');
            } else {
                personal.style.display = 'none';
                group.style.display = 'block';
                tabs[1].classList.add('active');
            }
        }

        function openConversation(userId, userName) {
            currentChatUserId = userId;
            currentChatUserName = userName;

            document.getElementById('modalUserName').textContent = 'Viestit: ' + userName;
            document.getElementById('modalUserInfo').innerHTML = '<div style="display: flex; align-items: center; gap: 10px;"><i class="fas fa-user"></i> ' + userName + '</div>';
            document.getElementById('messageModal').style.display = 'flex';

            // Lataa viestihistoria
            loadMessages(userId);
        }

        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
        }

        function loadMessages(userId, scrollToBottom = true) {
            fetch('hae_viestit.php?kayttaja_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    const history = document.getElementById('messageHistory');
                    let html = '';

                    data.forEach(msg => {
                        const isMe = msg.lahettaja_id == <?php echo $user_id; ?>;
                        const time = new Date(msg.luontiaika).toLocaleTimeString('fi-FI', {hour: '2-digit', minute:'2-digit'});

                        html += `
                            <div class="message-bubble ${isMe ? 'sent' : 'received'}">
                                <div class="bubble-content">${escapeHtml(msg.viesti)}</div>
                                <div class="bubble-time">${time}</div>
                            </div>
                        `;
                    });

                    if (data.length === 0) {
                        html = '<div style="text-align: center; color: rgba(255,255,255,0.5); padding: 20px;">Ei viestejä</div>';
                    }

                    history.innerHTML = html;

                    if (scrollToBottom) {
                        history.scrollTop = history.scrollHeight;
                    }
                })
                .catch(error => console.error('Error loading messages:', error));
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value.trim();

            if (!message || !currentChatUserId) return;

            const formData = new FormData();
            formData.append('vastaanottaja_id', currentChatUserId);
            formData.append('viesti', message);

            fetch('laheta_viesti.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    loadMessages(currentChatUserId);
                } else {
                    alert('Viestin lähetys epäonnistui: ' + (data.error || 'Tuntematon virhe'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Viestin lähetys epäonnistui');
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Sulje modaali klikkaamalla sen ulkopuolelle
        window.onclick = function(event) {
            const modal = document.getElementById('messageModal');
            if (event.target === modal) {
                closeMessageModal();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
