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
$user_query = "SELECT etunimi, sukunimi, rooli, profile_image FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

// Varmista että käyttäjä on admin
if ($current_user['rooli'] != 'admin') {
    header("Location: viestit.php");
    exit();
}

// Hae ADMINILLE osoitetut viestit (vain ne missä admin on vastaanottaja)
$admin_viestit = [];
$stmt = $conn->prepare("
    SELECT DISTINCT 
        j.id,
        j.etunimi,
        j.sukunimi,
        j.rooli,
        j.profile_image,
        (SELECT COUNT(*) FROM viestit WHERE lahettaja_id = j.id AND vastaanottaja_id = ? AND luettu = 0) as lukemattomat,
        (SELECT viesti FROM viestit WHERE 
            lahettaja_id = j.id AND vastaanottaja_id = ?
            ORDER BY luontiaika DESC LIMIT 1) as viimeisin_viesti,
        (SELECT luontiaika FROM viestit WHERE 
            lahettaja_id = j.id AND vastaanottaja_id = ?
            ORDER BY luontiaika DESC LIMIT 1) as viimeisin_aika
    FROM jasenet j
    WHERE j.id IN (
        SELECT DISTINCT lahettaja_id FROM viestit WHERE vastaanottaja_id = ?
    )
    ORDER BY viimeisin_aika DESC
");
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $admin_viestit[] = $row;
}
$stmt->close();

// Hae MANAGEREILLE osoitetut viestit (valvontaa varten)
$manager_viestit = [];
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
        vastaanottaja.id as manager_id,
        vastaanottaja.etunimi as manager_etunimi,
        vastaanottaja.sukunimi as manager_sukunimi
    FROM viestit v
    JOIN jasenet lahettaja ON v.lahettaja_id = lahettaja.id
    JOIN jasenet vastaanottaja ON v.vastaanottaja_id = vastaanottaja.id
    WHERE vastaanottaja.rooli = 'manager'
    ORDER BY v.luontiaika DESC
    LIMIT 50
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $manager_viestit[] = $row;
}
$stmt->close();

// Tilastot
$stats = [];
$result = $conn->query("SELECT COUNT(*) as count FROM viestit WHERE vastaanottaja_id = $user_id");
$stats['omat'] = $result->fetch_assoc()['count'];
$result = $conn->query("SELECT COUNT(*) as count FROM viestit WHERE vastaanottaja_id = $user_id AND luettu = 0");
$stats['lukemattomat'] = $result->fetch_assoc()['count'];
$result = $conn->query("SELECT COUNT(*) as count FROM viestit WHERE vastaanottaja_id IN (SELECT id FROM jasenet WHERE rooli = 'manager')");
$stats['manager_viestit'] = $result->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Viestit | Kirjasto</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(rgba(26, 26, 46, 0.9), rgba(26, 26, 46, 0.9)),
                        url('https://images.unsplash.com/photo-1521587760476-6c12a4b040da?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80');
            background-size: cover;
            min-height: 100vh;
            color: white;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 2em;
            background: linear-gradient(135deg, #fff, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, #3498db, #27ae60);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2em;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-info h3 {
            font-size: 0.9em;
            color: rgba(255,255,255,0.7);
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #3498db, #27ae60);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
        }

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
        }

        .tab:hover {
            background: rgba(255,255,255,0.2);
        }

        .tab.active {
            background: #3498db;
        }

        .messages-container {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            min-height: 600px;
        }

        .admin-messages-view, .manager-messages-view {
            display: grid;
            grid-template-columns: 400px 1fr;
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

        .search-box {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: white;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .conversation-item:hover {
            background: rgba(255,255,255,0.15);
        }

        .conversation-item.active {
            background: rgba(52, 152, 219, 0.3);
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #27ae60);
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
            border-radius: 50%;
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

        .unread-badge {
            background: #e74c3c;
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

        .manager-message-item {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            cursor: pointer;
            transition: all 0.3s;
        }

        .manager-message-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .manager-message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .manager-message-sender {
            font-weight: bold;
            color: #f39c12;
        }

        .manager-message-receiver {
            font-size: 0.85em;
            color: rgba(255,255,255,0.7);
        }

        .manager-message-content {
            margin: 10px 0;
            padding: 10px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
        }

        .manager-message-time {
            font-size: 0.8em;
            color: rgba(255,255,255,0.5);
            text-align: right;
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
            background: #3498db;
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
            border-color: #3498db;
        }

        .send-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .send-btn:hover {
            background: #2980b9;
            transform: scale(1.1);
        }

        /* No chat selected - centered text */
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-crown"></i> Admin Viestit</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (!empty($current_user['profile_image']) && file_exists("uploads/profiles/" . $current_user['profile_image'])): ?>
                        <img src="uploads/profiles/<?php echo $current_user['profile_image']; ?>" alt="">
                    <?php else: ?>
                        <?php echo strtoupper(substr($current_user['etunimi'], 0, 1) . substr($current_user['sukunimi'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <strong><?php echo htmlspecialchars($current_user['etunimi'] . ' ' . $current_user['sukunimi']); ?></strong>
                    <div style="font-size: 0.8em; opacity: 0.7;">Admin</div>
                </div>
                <a href="admin_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            </div>
        </div>

        <!-- Tilastot -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Omat viestit</h3>
                    <div class="stat-number"><?php echo $stats['omat']; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-envelope"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Lukemattomat</h3>
                    <div class="stat-number"><?php echo $stats['lukemattomat']; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-envelope-open"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Managerien viestit</h3>
                    <div class="stat-number"><?php echo $stats['manager_viestit']; ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('admin')">Omat viestit</div>
            <div class="tab" onclick="switchTab('manager')">Managerien viestit</div>
        </div>

        <div class="messages-container">
            <!-- Adminin omat viestit -->
            <div id="adminView" style="display: block;">
                <div class="admin-messages-view">
                    <div class="conversations-list">
                        <div class="conversations-header">
                            <h3><i class="fas fa-users"></i> Keskustelut</h3>
                        </div>
                        <input type="text" class="search-box" placeholder="Hae..." onkeyup="searchUsers(this.value)">
                        <div id="conversationsList">
                            <?php if (empty($admin_viestit)): ?>
                                <div class="no-data">
                                    <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: 10px;"></i>
                                    <p>Ei keskusteluja</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($admin_viestit as $k): ?>
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
                                            <div class="conversation-name">
                                                <?php echo htmlspecialchars($k['etunimi'] . ' ' . $k['sukunimi']); ?>
                                                <?php if ($k['lukemattomat'] > 0): ?>
                                                    <span class="unread-badge"><?php echo $k['lukemattomat']; ?></span>
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
                            <i class="fas fa-comments" style="font-size: 4em; margin-bottom: 15px;"></i>
                            <p>Valitse käyttäjä aloittaaksesi keskustelun</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Managerien viestit -->
            <div id="managerView" style="display: none;">
                <div class="manager-messages-view">
                    <div class="conversations-list">
                        <div class="conversations-header">
                            <h3><i class="fas fa-tasks"></i> Managerien viestit</h3>
                        </div>
                        <div class="manager-messages-list">
                            <?php if (empty($manager_viestit)): ?>
                                <div class="no-data">
                                    <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: 10px;"></i>
                                    <p>Ei viestejä managereille</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($manager_viestit as $mv): ?>
                                    <div class="manager-message-item">
                                        <div class="manager-message-header">
                                            <span class="manager-message-sender">
                                                <i class="fas fa-user"></i> 
                                                <?php echo htmlspecialchars($mv['lahettaja_etunimi'] . ' ' . $mv['lahettaja_sukunimi']); ?>
                                                (<?php echo $mv['lahettaja_rooli']; ?>)
                                            </span>
                                            <span class="manager-message-time">
                                                <?php echo date('d.m.Y H:i', strtotime($mv['luontiaika'])); ?>
                                            </span>
                                        </div>
                                        <div class="manager-message-receiver">
                                            <i class="fas fa-arrow-right"></i> 
                                            Vastaanottaja: <?php echo htmlspecialchars($mv['manager_etunimi'] . ' ' . $mv['manager_sukunimi']); ?> (Manager)
                                        </div>
                                        <div class="manager-message-content">
                                            <?php echo nl2br(htmlspecialchars($mv['viesti'])); ?>
                                        </div>
                                        <div class="manager-message-status">
                                            <?php if ($mv['luettu'] == 0): ?>
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
                    <div class="chat-area" style="justify-content: center; align-items: center; display: flex;">
                        <div class="no-chat-selected">
                            <i class="fas fa-eye" style="font-size: 4em; margin-bottom: 15px;"></i>
                            <p>Seuraa managerien viestintää</p>
                            <small style="opacity: 0.7;">Tässä näet kaikki managerien saamat viestit</small>
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

            if (tab === 'admin') {
                document.getElementById('adminView').style.display = 'block';
                document.getElementById('managerView').style.display = 'none';
            } else {
                document.getElementById('adminView').style.display = 'none';
                document.getElementById('managerView').style.display = 'block';
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
