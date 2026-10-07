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

// Hae käyttäjän keskustelut (ketkä ovat lähettäneet tai saaneet viestejä)
$keskustelut = [];

// Lasketaan montako ? merkkiä SQL:ssä on
$sql = "
    SELECT DISTINCT 
        j.id,
        j.etunimi,
        j.sukunimi,
        j.rooli,
        j.profile_image,
        (SELECT COUNT(*) FROM viestit WHERE lahettaja_id = j.id AND vastaanottaja_id = ? AND luettu = 0) as lukemattomat,
        (SELECT viesti FROM viestit WHERE 
            (lahettaja_id = ? AND vastaanottaja_id = j.id) OR 
            (lahettaja_id = j.id AND vastaanottaja_id = ?) 
            ORDER BY luontiaika DESC LIMIT 1) as viimeisin_viesti,
        (SELECT luontiaika FROM viestit WHERE 
            (lahettaja_id = ? AND vastaanottaja_id = j.id) OR 
            (lahettaja_id = j.id AND vastaanottaja_id = ?) 
            ORDER BY luontiaika DESC LIMIT 1) as viimeisin_aika
    FROM jasenet j
    WHERE j.id IN (
        SELECT DISTINCT lahettaja_id FROM viestit WHERE vastaanottaja_id = ?
        UNION
        SELECT DISTINCT vastaanottaja_id FROM viestit WHERE lahettaja_id = ?
    )
    AND j.id != ?
    ORDER BY viimeisin_aika DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $keskustelut[] = $row;
}
$stmt->close();

// Hae kaikki adminit ja managerit uutta viestiä varten
$yhteyshenkilot = [];
$result = $conn->query("SELECT id, etunimi, sukunimi, rooli FROM jasenet WHERE rooli IN ('admin', 'manager') AND id != $user_id ORDER BY rooli, etunimi");
while ($row = $result->fetch_assoc()) {
    $yhteyshenkilot[] = $row;
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viestit | Kirjasto</title>
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
            max-width: 1200px;
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

        .messages-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            min-height: 600px;
        }

        .conversations-list {
            border-right: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.2);
            overflow-y: auto;
            max-height: 600px;
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

        .new-message-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s;
        }

        .new-message-btn:hover {
            background: #229954;
            transform: translateY(-2px);
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

        .role-tag {
            font-size: 0.6em;
            padding: 2px 8px;
            border-radius: 10px;
            background: rgba(255,255,255,0.2);
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
            max-width: 200px;
        }

        .conversation-time {
            font-size: 0.7em;
            color: rgba(255,255,255,0.5);
        }

        .chat-area {
            display: flex;
            flex-direction: column;
            height: 600px;
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

        .modal {
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

        .modal-content {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close-btn {
            font-size: 1.5em;
            cursor: pointer;
            opacity: 0.7;
            transition: all 0.3s;
        }

        .close-btn:hover {
            opacity: 1;
        }

        .user-select {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            color: white;
            margin-bottom: 15px;
            font-size: 1em;
        }

        .user-select option {
            background: #2c3e50;
        }

        .modal-input {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            color: white;
            margin-bottom: 15px;
            min-height: 100px;
            font-size: 1em;
        }

        .modal-btn {
            width: 100%;
            padding: 12px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s;
        }

        .modal-btn:hover {
            background: #229954;
        }

        .no-chat-selected {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: rgba(255,255,255,0.5);
            font-size: 1.2em;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: rgba(255,255,255,0.5);
        }

        .loading i {
            font-size: 2em;
            animation: spin 1s infinite linear;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-comments"></i> Viestit</h1>
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
                    <div style="font-size: 0.8em; opacity: 0.7;"><?php echo ucfirst($current_user['rooli']); ?></div>
                </div>
                <a href="user_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            </div>
        </div>

        <div class="messages-container">
            <!-- Keskustelulista -->
            <div class="conversations-list">
                <div class="conversations-header">
                    <h3><i class="fas fa-users"></i> Keskustelut</h3>
                    <button class="new-message-btn" onclick="openNewMessageModal()">
                        <i class="fas fa-plus"></i> Uusi
                    </button>
                </div>
                <div id="conversationsList">
                    <?php if (empty($keskustelut)): ?>
                        <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">
                            <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: 10px;"></i>
                            <p>Ei keskusteluja</p>
                            <small>Aloita uusi keskustelu</small>
                        </div>
                    <?php else: ?>
                        <?php foreach ($keskustelut as $k): ?>
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
                                        <span class="role-tag"><?php echo $k['rooli']; ?></span>
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
                    <p>Valitse keskustelu aloittaaksesi</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Uusi viesti modaali -->
    <div class="modal" id="newMessageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-pen"></i> Uusi viesti</h3>
                <span class="close-btn" onclick="closeNewMessageModal()">&times;</span>
            </div>
            <select class="user-select" id="recipientId">
                <option value="">Valitse vastaanottaja</option>
                <?php foreach ($yhteyshenkilot as $yh): ?>
                    <option value="<?php echo $yh['id']; ?>">
                        <?php echo htmlspecialchars($yh['etunimi'] . ' ' . $yh['sukunimi'] . ' (' . $yh['rooli'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <textarea class="modal-input" id="messageText" placeholder="Kirjoita viesti..."></textarea>
            <button class="modal-btn" onclick="sendNewMessage()">Lähetä viesti</button>
        </div>
    </div>

    <script>
        let currentChatUserId = null;
        let currentChatUserName = null;
        let messageInterval = null;

        function selectConversation(userId, userName, userRole) {
            currentChatUserId = userId;
            currentChatUserName = userName;

            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('active');
            });
            event.currentTarget.classList.add('active');

            loadMessages(userId, userName);

            if (messageInterval) clearInterval(messageInterval);
            messageInterval = setInterval(() => loadMessages(userId, userName, false), 3000);
        }

        function loadMessages(userId, userName, scrollToBottom = true) {
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

                    if (scrollToBottom) {
                        const messagesDiv = document.getElementById('chatMessages');
                        if (messagesDiv) messagesDiv.scrollTop = messagesDiv.scrollHeight;
                    }
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

        function openNewMessageModal() {
            document.getElementById('newMessageModal').style.display = 'flex';
        }

        function closeNewMessageModal() {
            document.getElementById('newMessageModal').style.display = 'none';
            document.getElementById('recipientId').value = '';
            document.getElementById('messageText').value = '';
        }

        function sendNewMessage() {
            const recipientId = document.getElementById('recipientId').value;
            const message = document.getElementById('messageText').value.trim();

            if (!recipientId) {
                alert('Valitse vastaanottaja');
                return;
            }

            if (!message) {
                alert('Kirjoita viesti');
                return;
            }

            const formData = new FormData();
            formData.append('vastaanottaja_id', recipientId);
            formData.append('viesti', message);

            fetch('laheta_viesti.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeNewMessageModal();
                    location.reload();
                } else {
                    alert('Virhe: ' + (data.error || 'Viestin lähetys epäonnistui'));
                }
            })
            .catch(error => {
                console.error('Virhe:', error);
                alert('Viestin lähetys epäonnistui');
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        window.onclick = function(event) {
            const modal = document.getElementById('newMessageModal');
            if (event.target === modal) {
                closeNewMessageModal();
            }
        }

        window.addEventListener('beforeunload', function() {
            if (messageInterval) {
                clearInterval(messageInterval);
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
