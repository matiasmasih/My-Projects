<?php
session_start();
require_once 'connection.php';

// Tarkista että käyttäjä on kirjautunut
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Hae käyttäjän tiedot
$user_query = "SELECT etunimi, sukunimi, rooli, profile_image, email, jasennumero, liittymispaiva FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

if ($current_user['rooli'] != 'manager' && $current_user['rooli'] != 'admin') {
 header("Location: viestit.php");
    exit();
}

// ===== HAETAAN MANAGERILLE TULEVAT VIESTIT =====
$manager_viestit = [];
$stmt = $conn->prepare("
    SELECT DISTINCT
        j.id,
        j.etunimi,
        j.sukunimi,
        j.rooli,
        j.profile_image,
        j.email,
        j.jasennumero,
        j.liittymispaiva,
        (SELECT COUNT(*) FROM viestit WHERE lahettaja_id = j.id AND vastaanottaja_id = ? AND luettu = 0) as lukemattomat,
        (SELECT COUNT(*) FROM viestit WHERE lahettaja_id = j.id AND vastaanottaja_id = ?) as viesteja_yhteensa,
        (SELECT viesti FROM viestit WHERE lahettaja_id = j.id AND vastaanottaja_id = ? ORDER BY luontiaika DESC LIMIT 1) as viimeisin_viesti,
        (SELECT luontiaika FROM viestit WHERE lahettaja_id = j.id AND vastaanottaja_id = ? ORDER BY luontiaika DESC LIMIT 1) as viimeisin_aika
    FROM jasenet j
    WHERE j.id IN (
        SELECT DISTINCT lahettaja_id FROM viestit WHERE vastaanottaja_id = ?
    )
    ORDER BY viimeisin_aika DESC
");
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $manager_viestit[] = $row;
}
$stmt->close();

// ===== HAETAAN KAIKKI ADMININ VIESTIT (manager näkee kaikki) =====
$admin_viestit = [];
$stmt = $conn->prepare("
    SELECT
        v.id as viesti_id,
        v.viesti,
        v.luontiaika,
        v.luettu,
        lahettaja.id as lahettaja_id,
        lahettaja.etunimi as lahettaja_etunimi,
        lahettaja.sukunimi as lahettaja_sukunimi,
        lahettaja.rooli as lahettaja_rooli,
        lahettaja.profile_image as lahettaja_kuva,
        vastaanottaja.id as admin_id,
        vastaanottaja.etunimi as admin_etunimi,
        vastaanottaja.sukunimi as admin_sukunimi,
        vastaanottaja.profile_image as admin_kuva
    FROM viestit v
    JOIN jasenet lahettaja ON v.lahettaja_id = lahettaja.id
    JOIN jasenet vastaanottaja ON v.vastaanottaja_id = vastaanottaja.id
    WHERE vastaanottaja.rooli = 'admin' OR lahettaja.rooli = 'admin'
    ORDER BY v.luontiaika DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $admin_viestit[] = $row;
}
$stmt->close();

// ===== TILASTOT =====
$stats = [];
// Managerin omat tilastot
$result = $conn->query("SELECT COUNT(*) as count FROM viestit WHERE vastaanottaja_id = $user_id");
$stats['omat'] = $result->fetch_assoc()['count'];
$result = $conn->query("SELECT COUNT(*) as count FROM viestit WHERE vastaanottaja_id = $user_id AND luettu = 0");
$stats['lukemattomat'] = $result->fetch_assoc()['count'];

// Adminien viestitilastot
$result = $conn->query("SELECT COUNT(*) as count FROM viestit WHERE vastaanottaja_id IN (SELECT id FROM jasenet WHERE rooli = 'admin')");
$stats['admin_viestit'] = $result->fetch_assoc()['count'];

// Aktiiviset keskustelut
$result = $conn->query("SELECT COUNT(DISTINCT lahettaja_id) as count FROM viestit WHERE vastaanottaja_id = $user_id");
$stats['aktiiviset'] = $result->fetch_assoc()['count'];

// Vastausprosentti
$result = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM viestit WHERE lahettaja_id = $user_id AND vastaanottaja_id IN (SELECT id FROM jasenet)) as lahetetyt,
        (SELECT COUNT(*) FROM viestit WHERE vastaanottaja_id = $user_id) as saadut
");
$row = $result->fetch_assoc();
$stats['lahetetyt'] = $row['lahetetyt'];
$stats['saadut'] = $row['saadut'];
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Viestit | Kirjasto</title>
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(rgba(26, 26, 46, 0.85), rgba(26, 26, 46, 0.85)),
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

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.1);
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
            background: rgba(255, 255, 255, 0.25);
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

        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.3s;
            margin-left: 15px;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
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

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 25px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            flex: 1;
            text-align: center;
        }

        .tab:hover {
            background: rgba(255,255,255,0.2);
        }

        .tab.active {
            background: linear-gradient(135deg, var(--info), #2980b9);
            color: white;
            box-shadow: 0 10px 20px -5px rgba(52, 152, 219, 0.5);
        }

        /* Messages Container */
        .messages-container {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            overflow: hidden;
            min-height: 600px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        .manager-messages-view, .admin-messages-view {
            display: grid;
            grid-template-columns: 350px 1fr;
            height: 600px;
        }

        .conversations-list {
            border-right: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.2);
            overflow-y: auto;
        }

        .conversations-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
        }

        .conversations-header h3 {
            font-size: 1.1em;
            color: white;
        }

        .conversations-header h3 i {
            color: var(--info);
            margin-right: 8px;
        }

        .search-box {
            width: 100%;
            padding: 10px 15px;
            margin: 10px 0;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: white;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--info);
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .conversation-item:hover {
            background: rgba(255,255,255,0.15);
            transform: translateX(5px);
        }

        .conversation-item.active {
            background: rgba(52, 152, 219, 0.2);
            border-left: 4px solid var(--info);
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, var(--info), var(--success));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
        }

        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .role-badge {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #2c3e50;
        }

        .role-badge.admin { background: #e74c3c; }
        .role-badge.manager { background: #f39c12; }
        .role-badge.user { background: #27ae60; }

        .conversation-info {
            flex: 1;
        }

        .conversation-name {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .role-tag {
            font-size: 0.7em;
            padding: 2px 8px;
            border-radius: 10px;
            background: rgba(255,255,255,0.2);
        }

        .unread-badge {
            background: var(--secondary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            font-weight: bold;
        }

        .conversation-preview {
            font-size: 0.85em;
            color: rgba(255,255,255,0.7);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-time {
            font-size: 0.7em;
            color: rgba(255,255,255,0.5);
        }

        .admin-message-item {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.3s;
        }

        .admin-message-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .admin-message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .admin-message-sender {
            font-weight: bold;
            color: #e74c3c;
        }

        .admin-message-receiver {
            font-size: 0.85em;
            color: rgba(255,255,255,0.7);
            margin-bottom: 5px;
        }

        .admin-message-content {
            margin: 10px 0;
            padding: 15px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            border-left: 4px solid #e74c3c;
        }

        .admin-message-time {
            font-size: 0.8em;
            color: rgba(255,255,255,0.5);
            text-align: right;
        }

        .admin-message-status {
            font-size: 0.8em;
            margin-top: 5px;
        }

        .chat-area {
            display: flex;
            flex-direction: column;
            height: 600px;
            position: relative;
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(0,0,0,0.2);
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message {
            max-width: 70%;
            padding: 12px 18px;
            border-radius: 18px;
            position: relative;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.sent {
            align-self: flex-end;
            background: linear-gradient(135deg, var(--info), #2980b9);
            color: white;
            border-bottom-right-radius: 5px;
        }

        .message.received {
            align-self: flex-start;
            background: rgba(255,255,255,0.2);
            border-bottom-left-radius: 5px;
        }

        .message-time {
            font-size: 0.7em;
            margin-top: 5px;
            opacity: 0.7;
            text-align: right;
        }

        .chat-input-area {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
            display: flex;
            gap: 10px;
            background: rgba(0,0,0,0.2);
        }

        .chat-input {
            flex: 1;
            padding: 12px 18px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 25px;
            color: white;
            font-size: 1em;
        }

        .chat-input:focus {
            outline: none;
            border-color: var(--info);
        }

        .send-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--info), #2980b9);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
        }

        .send-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }

        .no-chat-selected {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
            color: rgba(255,255,255,0.5);
            text-align: center;
            padding: 20px;
            position: absolute;
            top: 0;
            left: 0;
        }

        .no-chat-selected i {
            font-size: 4em;
            margin-bottom: 15px;
            opacity: 0.5;
            color: var(--info);
        }

        .no-chat-selected p {
            font-size: 1.2em;
            margin-bottom: 5px;
        }

        .no-chat-selected small {
            opacity: 0.7;
            font-size: 0.9em;
        }

        .no-data {
            text-align: center;
            padding: 50px;
            color: rgba(255,255,255,0.5);
        }

        .no-data i {
            font-size: 3em;
            margin-bottom: 10px;
            opacity: 0.5;
            color: var(--info);
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

        .stat-card, .tab, .messages-container {
            animation: slideUp 0.6s ease forwards;
            opacity: 0;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .manager-messages-view, .admin-messages-view {
                grid-template-columns: 1fr;
            }
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user-tie"></i> Manager Viestit</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (!empty($current_user['profile_image']) && file_exists("uploads/profiles/" . $current_user['profile_image'])): ?>
                        <img src="uploads/profiles/<?php echo $current_user['profile_image']; ?>" alt="">
                    <?php else: ?>
                        <?php echo strtoupper(substr($current_user['etunimi'], 0, 1) . substr($current_user['sukunimi'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($current_user['etunimi'] . ' ' . $current_user['sukunimi']); ?></div>
                    <div class="user-role">Manager</div>
                    <div class="user-meta">
                        <i class="far fa-envelope"></i> <?php echo htmlspecialchars($current_user['email']); ?><br>
                        <i class="fas fa-id-card"></i> Jäsennumero: <?php echo htmlspecialchars($current_user['jasennumero'] ?? 'Ei asetettu'); ?>
                    </div>
                </div>
                <a href="manager_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Takaisin</a>
            </div>
        </div>

        <!-- Tilastot (4 korttia) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Omat viestit</h3>
                    <div class="stat-number"><?php echo $stats['omat']; ?></div>
                    <div class="stat-desc">Saapuneet yhteensä</div>
                </div>
                <div class="stat-icon"><i class="fas fa-inbox"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Lukemattomat</h3>
                    <div class="stat-number"><?php echo $stats['lukemattomat']; ?></div>
                    <div class="stat-desc">Odottavat vastausta</div>
                </div>
                <div class="stat-icon"><i class="fas fa-envelope-open"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Aktiiviset</h3>
                    <div class="stat-number"><?php echo $stats['aktiiviset']; ?></div>
                    <div class="stat-desc">Keskustelukumppania</div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Lähetetyt / Saadut</h3>
                    <div class="stat-number"><?php echo $stats['lahetetyt']; ?> / <?php echo $stats['saadut']; ?></div>
                    <div class="stat-desc">Viestit yhteensä</div>
                </div>
                <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('manager')">
                <i class="fas fa-user"></i> Omat keskustelut (<?php echo $stats['omat']; ?>)
            </div>
            <div class="tab" onclick="switchTab('admin')">
                <i class="fas fa-crown"></i> Adminien viestit (<?php echo $stats['admin_viestit']; ?>)
            </div>
        </div>

        <div class="messages-container">
            <!-- Managerin omat viestit -->
            <div id="managerView" style="display: block;">
                <div class="manager-messages-view">
                    <div class="conversations-list">
                        <div class="conversations-header">
                            <h3><i class="fas fa-users"></i> Keskustelut</h3>
                        </div>
                        <input type="text" class="search-box" placeholder="Hae käyttäjää..." onkeyup="searchUsers(this.value)">
                        <div id="conversationsList">
                            <?php if (empty($manager_viestit)): ?>
                                <div class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <p>Ei keskusteluja</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($manager_viestit as $k): ?>
                                    <div class="conversation-item" onclick="selectConversation(<?php echo $k['id']; ?>, '<?php echo htmlspecialchars($k['etunimi'] . ' ' . $k['sukunimi']); ?>', '<?php echo $k['rooli']; ?>')">
                                        <div class="conversation-avatar">
                                            <?php if (!empty($k['profile_image']) && file_exists("uploads/profiles/" . $k['profile_image'])): ?>
                                                <img src="uploads/profiles/<?php echo $k['profile_image']; ?>" alt="">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($k['etunimi'], 0, 1) . substr($k['sukunimi'], 0, 1)); ?>
                                            <?php endif; ?>
                                            <div class="role-badge <?php echo $k['rooli']; ?>"></div>
                                        </div>
                                        <div class="conversation-info">
                                            <div class="conversation-header">
                                                <span class="conversation-name">
                                                    <?php echo htmlspecialchars($k['etunimi'] . ' ' . $k['sukunimi']); ?>
                                                    <span class="role-tag"><?php echo $k['rooli']; ?></span>
                                                </span>
                                                <?php if ($k['lukemattomat'] > 0): ?>
                                                    <span class="unread-badge"><?php echo $k['lukemattomat']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="conversation-stats">
                                                <i class="far fa-comments"></i> <?php echo $k['viesteja_yhteensa'] ?? 0; ?> viestiä
                                                <?php if (!empty($k['jasennumero'])): ?>
                                                    · <i class="fas fa-id-card"></i> #<?php echo $k['jasennumero']; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="conversation-preview">
                                                <?php echo !empty($k['viimeisin_viesti']) ? htmlspecialchars(substr($k['viimeisin_viesti'], 0, 50)) . '...' : 'Ei viestejä'; ?>
                                            </div>
                                        </div>
                                        <div class="conversation-time">
                                            <?php echo !empty($k['viimeisin_aika']) ? date('H:i', strtotime($k['viimeisin_aika'])) : ''; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Viestialue -->
                    <div class="chat-area" id="chatArea">
                        <div class="no-chat-selected">
                            <i class="fas fa-comments"></i>
                            <p>Valitse käyttäjä aloittaaksesi keskustelun</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Adminien viestit (kaikki adminille tulleet ja lähteneet) -->
            <div id="adminView" style="display: none;">
                <div class="admin-messages-view">
                    <div class="conversations-list">
                        <div class="conversations-header">
                            <h3><i class="fas fa-crown"></i> Adminien viestit</h3>
                        </div>
                        <div class="admin-messages-grid">
                            <?php if (empty($admin_viestit)): ?>
                                <div class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <p>Ei viestejä adminille</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($admin_viestit as $av): ?>
                                    <div class="admin-message-card">
                                        <div class="admin-message-header">
                                            <div class="admin-message-avatar">
                                                <?php if (!empty($av['lahettaja_kuva']) && file_exists("uploads/profiles/" . $av['lahettaja_kuva'])): ?>
                                                    <img src="uploads/profiles/<?php echo $av['lahettaja_kuva']; ?>" alt="">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($av['lahettaja_etunimi'], 0, 1) . substr($av['lahettaja_sukunimi'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="admin-message-sender">
                                                <?php echo htmlspecialchars($av['lahettaja_etunimi'] . ' ' . $av['lahettaja_sukunimi']); ?>
                                                <small><?php echo $av['lahettaja_rooli']; ?></small>
                                            </div>
                                        </div>
                                        <div class="admin-message-content">
                                            <?php echo nl2br(htmlspecialchars($av['viesti'])); ?>
                                        </div>
                                        <div class="admin-message-footer">
                                            <span>
                                                <i class="fas fa-arrow-right"></i> 
                                                <?php echo htmlspecialchars($av['admin_etunimi'] . ' ' . $av['admin_sukunimi']); ?> (Admin)
                                            </span>
                                            <span>
                                                <i class="far fa-clock"></i> 
                                                <?php echo date('d.m.Y H:i', strtotime($av['luontiaika'])); ?>
                                            </span>
                                        </div>
                                        <div style="margin-top: 8px;">
                                            <?php if ($av['luettu'] == 0): ?>
                                                <span style="color: #e74c3c;"><i class="fas fa-circle"></i> Lukematon</span>
                                            <?php else: ?>
                                                <span style="color: #27ae60;"><i class="fas fa-check-circle"></i> Luettu</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="chat-area">
                        <div class="no-chat-selected">
                            <i class="fas fa-eye"></i>
                            <p>Seuraa adminien viestintää</p>
                            <small>Tässä näet kaikki adminien lähettämät ja vastaanottamat viestit</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentChatUserId = null;
        let currentChatUserName = null;

        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.currentTarget.classList.add('active');

            if (tab === 'manager') {
                document.getElementById('managerView').style.display = 'block';
                document.getElementById('adminView').style.display = 'none';
            } else {
                document.getElementById('managerView').style.display = 'none';
                document.getElementById('adminView').style.display = 'block';
            }
        }

        function selectConversation(userId, userName, userRole) {
            currentChatUserId = userId;
            currentChatUserName = userName;

            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            event.currentTarget.classList.add('active');

            loadMessages(userId, userName);
        }

        function loadMessages(userId, userName) {
            fetch('hae_viestit.php?kayttaja_id=' + userId)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div class="chat-header">
                            <div class="conversation-avatar">
                                ${userName.charAt(0)}
                            </div>
                            <h3>${userName}</h3>
                        </div>
                        <div class="chat-messages" id="chatMessages">
                    `;

                    if (data.length === 0) {
                        html += '<div style="text-align: center; color: rgba(255,255,255,0.5); padding: 40px;">Ei viestejä. Aloita keskustelu!</div>';
                    } else {
                        data.forEach(msg => {
                            const isMe = msg.lahettaja_id == <?php echo $user_id; ?>;
                            const time = new Date(msg.luontiaika).toLocaleTimeString('fi-FI', {hour: '2-digit', minute:'2-digit'});
                            const date = new Date(msg.luontiaika).toLocaleDateString('fi-FI');

                            html += `
                                <div class="message ${isMe ? 'sent' : 'received'}">
                                    ${escapeHtml(msg.viesti)}
                                    <div class="message-time">${date} ${time}</div>
                                </div>
                            `;
                        });
                    }

                    html += `
                        </div>
                        <div class="chat-input-area">
                            <input type="text" class="chat-input" id="messageInput" placeholder="Kirjoita viesti..." onkeypress="if(event.key === 'Enter') sendMessage()">
                            <button class="send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    `;

                    document.getElementById('chatArea').innerHTML = html;

                    const messagesDiv = document.getElementById('chatMessages');
                    if (messagesDiv) messagesDiv.scrollTop = messagesDiv.scrollHeight;
                });
        }

        function sendMessage() {
            const input = document.getElementById('messageInput');
            if (!input) return;

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
                    loadMessages(currentChatUserId, currentChatUserName);
                } else {
                    alert('Virhe: ' + (data.error || 'Viestin lähetys epäonnistui'));
                }
            })
            .catch(error => {
                console.error('Virhe:', error);
                alert('Viestin lähetys epäonnistui');
            });
        }

        function searchUsers(query) {
            document.querySelectorAll('.conversation-item').forEach(item => {
                const name = item.querySelector('.conversation-name').textContent.toLowerCase();
                if (name.includes(query.toLowerCase())) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
