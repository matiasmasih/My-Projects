<?php
session_start();
require_once 'config.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

// Get site info
$info_sql = "SELECT * FROM personal_info LIMIT 1";
$info_result = $conn->query($info_sql);
$info = $info_result->fetch_assoc();

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? $info['email'] ?? 'admin@localhost.com';

// Handle reply
if (isset($_POST['reply'])) {
    $msg_id = (int)$_POST['msg_id'];
    $reply_message = trim($_POST['reply_message']);
    
    // Get original message
    $msg_sql = "SELECT name, email, subject FROM contact_messages WHERE id = ?";
    $msg_stmt = $conn->prepare($msg_sql);
    $msg_stmt->bind_param("i", $msg_id);
    $msg_stmt->execute();
    $msg_result = $msg_stmt->get_result();
    $original = $msg_result->fetch_assoc();
    
    if ($original && !empty($reply_message)) {
        // In a real application, you would send an email here
        // For now, we'll just mark as replied and save the reply in database
        
        // Update message status to replied
        $update_sql = "UPDATE contact_messages SET status = 'replied', reply = ?, reply_date = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $reply_message, $msg_id);
        
        if ($update_stmt->execute()) {
            $success = 'Vastaus lähetetty onnistuneesti!';
            
            // In production, uncomment this to send actual email:
            /*
            $to = $original['email'];
            $subject = "Re: " . $original['subject'];
            $headers = "From: " . $admin_email . "\r\n";
            $headers .= "Reply-To: " . $admin_email . "\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            $message = "
            <html>
            <body>
                <h2>Hei " . htmlspecialchars($original['name']) . ",</h2>
                <p>" . nl2br(htmlspecialchars($reply_message)) . "</p>
                <br>
                <p>Ystävällisin terveisin,<br>" . htmlspecialchars($admin_name) . "</p>
            </body>
            </html>";
            
            mail($to, $subject, $message, $headers);
            */
        } else {
            $error = 'Virhe vastauksen lähettämisessä!';
        }
    } else {
        $error = 'Viestiä ei löytynyt tai vastaus on tyhjä!';
    }
}

// Handle mark as read
if (isset($_GET['read'])) {
    $msg_id = (int)$_GET['read'];
    $update_sql = "UPDATE contact_messages SET status = 'read' WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $msg_id);
    $update_stmt->execute();
    header('Location: admin_messages.php');
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $msg_id = (int)$_GET['delete'];
    $delete_sql = "DELETE FROM contact_messages WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $msg_id);
    $delete_stmt->execute();
    header('Location: admin_messages.php?msg=deleted');
    exit();
}

// Filter messages
$filter = $_GET['filter'] ?? 'all';
$filter_sql = "";
if ($filter == 'unread') {
    $filter_sql = "WHERE status = 'unread'";
} elseif ($filter == 'read') {
    $filter_sql = "WHERE status = 'read'";
} elseif ($filter == 'replied') {
    $filter_sql = "WHERE status = 'replied'";
}

// Get all messages
$messages_sql = "SELECT * FROM contact_messages $filter_sql ORDER BY created_at DESC";
$messages_result = $conn->query($messages_sql);

// Get statistics
$total_messages = $conn->query("SELECT COUNT(*) as count FROM contact_messages")->fetch_assoc()['count'];
$unread_count = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'unread'")->fetch_assoc()['count'];
$replied_count = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'replied'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viestit | <?php echo htmlspecialchars($info['full_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #010714;
            color: #fff;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(0, 229, 255, 0.08), transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(138, 43, 226, 0.08), transparent 50%);
            z-index: -2;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 25px;
        }

        header {
            position: relative;
            width: 95%;
            max-width: 1400px;
            margin: 20px auto 0;
            padding: 12px 30px;
            border-radius: 80px;
            background: rgba(10, 12, 21, 0.75);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 229, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h1 {
            font-size: 26px;
            font-weight: 800;
            background: linear-gradient(135deg, #00e5ff, #8a2be2, #ff6b6b);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-badge {
            background: rgba(0, 229, 255, 0.12);
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 14px;
            border: 1px solid rgba(0, 229, 255, 0.25);
        }

        .back-btn {
            background: rgba(0, 229, 255, 0.12);
            color: #00e5ff;
            padding: 8px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #00e5ff;
            color: #010714;
        }

        .main-content {
            padding: 40px 0 60px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(3, 11, 39, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(0, 229, 255, 0.15);
            transition: all 0.3s;
            text-decoration: none;
            display: block;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 229, 255, 0.4);
        }

        .stat-card i {
            font-size: 35px;
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 28px;
            font-weight: 700;
        }

        .stat-card p {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
        }

        .stat-card.active {
            border-color: #00e5ff;
            background: rgba(0, 229, 255, 0.1);
        }

        /* Messages Container */
        .messages-container {
            background: rgba(3, 11, 39, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 25px;
            overflow: hidden;
            border: 1px solid rgba(0, 229, 255, 0.15);
        }

        .message-card {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(0, 229, 255, 0.1);
            transition: all 0.3s;
        }

        .message-card:hover {
            background: rgba(0, 229, 255, 0.05);
        }

        .message-card.unread {
            background: rgba(0, 229, 255, 0.08);
            border-left: 3px solid #00e5ff;
        }

        .message-card.replied {
            background: rgba(16, 185, 129, 0.05);
            border-left: 3px solid #10b981;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .message-sender {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .message-sender h3 {
            font-size: 18px;
            color: #00e5ff;
        }

        .message-sender .email {
            font-size: 13px;
            color: rgba(255,255,255,0.5);
        }

        .message-date {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
        }

        .message-subject {
            font-weight: 600;
            margin-bottom: 10px;
            color: #fff;
        }

        .message-content {
            color: rgba(255,255,255,0.7);
            line-height: 1.6;
            margin-bottom: 15px;
            padding-left: 15px;
            border-left: 2px solid rgba(0, 229, 255, 0.3);
        }

        .message-reply {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 15px;
            padding: 15px;
            margin-top: 10px;
            margin-bottom: 15px;
            border-left: 2px solid #10b981;
        }

        .message-reply p {
            color: #10b981;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .message-reply .reply-text {
            color: rgba(255,255,255,0.8);
            font-style: italic;
        }

        .message-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-read, .btn-reply, .btn-delete {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            border: none;
        }

        .btn-read {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .btn-read:hover {
            background: #10b981;
            color: white;
        }

        .btn-reply {
            background: rgba(0, 229, 255, 0.15);
            color: #00e5ff;
            border: 1px solid rgba(0, 229, 255, 0.3);
        }

        .btn-reply:hover {
            background: #00e5ff;
            color: #010714;
        }

        .btn-delete {
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .btn-delete:hover {
            background: #e74c3c;
            color: white;
        }

        /* Reply Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(15px);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(3, 11, 39, 0.95);
            border-radius: 25px;
            max-width: 600px;
            width: 100%;
            padding: 30px;
            border: 1px solid rgba(0, 229, 255, 0.3);
        }

        .modal-content h3 {
            color: #00e5ff;
            margin-bottom: 20px;
        }

        .modal-content textarea {
            width: 100%;
            padding: 15px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(0, 229, 255, 0.25);
            border-radius: 15px;
            color: #fff;
            font-size: 14px;
            min-height: 150px;
            resize: vertical;
        }

        .modal-content textarea:focus {
            outline: none;
            border-color: #00e5ff;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-send, .btn-cancel-modal {
            padding: 10px 24px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-send {
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            color: white;
        }

        .btn-send:hover {
            transform: translateY(-2px);
        }

        .btn-cancel-modal {
            background: rgba(149, 165, 166, 0.2);
            color: #95a5a6;
        }

        .no-messages {
            text-align: center;
            padding: 60px;
            color: rgba(255,255,255,0.5);
        }

        .msg {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid #10b981;
            color: #10b981;
            padding: 12px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
        }

        .error-msg {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .message-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <h1><?php echo htmlspecialchars($info['full_name']); ?></h1>
            </div>
            <div class="admin-info">
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?>
                </div>
                <a href="admin_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Takaisin
                </a>
            </div>
        </div>
    </header>

    <div class="main-content">
        <div class="container">
            <?php if (isset($success)): ?>
                <div class="msg"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="msg error-msg"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="msg">Viesti poistettu onnistuneesti!</div>
            <?php endif; ?>

            <div class="stats-grid">
                <a href="admin_messages.php?filter=all" class="stat-card <?php echo $filter == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i>
                    <h3><?php echo $total_messages; ?></h3>
                    <p>Kaikki viestit</p>
                </a>
                <a href="admin_messages.php?filter=unread" class="stat-card <?php echo $filter == 'unread' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope-open"></i>
                    <h3><?php echo $unread_count; ?></h3>
                    <p>Lukemattomat</p>
                </a>
                <a href="admin_messages.php?filter=replied" class="stat-card <?php echo $filter == 'replied' ? 'active' : ''; ?>">
                    <i class="fas fa-reply-all"></i>
                    <h3><?php echo $replied_count; ?></h3>
                    <p>Vastatut</p>
                </a>
                <a href="admin_messages.php?filter=read" class="stat-card <?php echo $filter == 'read' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i>
                    <h3><?php echo $total_messages - $unread_count - $replied_count; ?></h3>
                    <p>Luettu</p>
                </a>
            </div>

            <div class="messages-container">
                <?php if ($messages_result && $messages_result->num_rows > 0): ?>
                    <?php while($msg = $messages_result->fetch_assoc()): ?>
                        <div class="message-card <?php echo $msg['status']; ?>">
                            <div class="message-header">
                                <div class="message-sender">
                                    <h3><?php echo htmlspecialchars($msg['name']); ?></h3>
                                    <span class="email"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($msg['email']); ?></span>
                                </div>
                                <div class="message-date">
                                    <i class="far fa-calendar-alt"></i> <?php echo date('d.m.Y H:i', strtotime($msg['created_at'])); ?>
                                </div>
                            </div>
                            <div class="message-subject">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($msg['subject']); ?>
                            </div>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            </div>
                            
                            <?php if (!empty($msg['reply'])): ?>
                                <div class="message-reply">
                                    <p><i class="fas fa-reply"></i> Sinun vastauksesi:</p>
                                    <div class="reply-text"><?php echo nl2br(htmlspecialchars($msg['reply'])); ?></div>
                                    <small style="color:rgba(255,255,255,0.4); font-size:11px;">Vastattu: <?php echo date('d.m.Y H:i', strtotime($msg['reply_date'])); ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <div class="message-actions">
                                <?php if ($msg['status'] == 'unread'): ?>
                                    <a href="?read=<?php echo $msg['id']; ?>" class="btn-read">
                                        <i class="fas fa-check"></i> Merkitse luetuksi
                                    </a>
                                <?php endif; ?>
                                <button class="btn-reply" onclick="openReplyModal(<?php echo $msg['id']; ?>, '<?php echo htmlspecialchars($msg['name']); ?>', '<?php echo htmlspecialchars($msg['subject']); ?>')">
                                    <i class="fas fa-reply"></i> Vastaa
                                </button>
                                <a href="?delete=<?php echo $msg['id']; ?>" class="btn-delete" onclick="return confirm('Haluatko varmasti poistaa tämän viestin?')">
                                    <i class="fas fa-trash"></i> Poista
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-messages">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Ei viestejä</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Reply Modal -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-reply"></i> Vastaa viestiin</h3>
            <form method="POST" action="">
                <input type="hidden" name="msg_id" id="reply_msg_id">
                <div style="margin-bottom: 15px;">
                    <strong>Vastaanottaja:</strong> <span id="reply_to_name"></span><br>
                    <strong>Aihe:</strong> Re: <span id="reply_subject"></span>
                </div>
                <textarea name="reply_message" id="reply_message" placeholder="Kirjoita vastauksesi tähän..." required></textarea>
                <div class="modal-buttons">
                    <button type="submit" name="reply" class="btn-send">
                        <i class="fas fa-paper-plane"></i> Lähetä vastaus
                    </button>
                    <button type="button" class="btn-cancel-modal" onclick="closeReplyModal()">
                        <i class="fas fa-times"></i> Peruuta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReplyModal(id, name, subject) {
            document.getElementById('reply_msg_id').value = id;
            document.getElementById('reply_to_name').innerHTML = name;
            document.getElementById('reply_subject').innerHTML = subject;
            document.getElementById('replyModal').classList.add('active');
        }
        
        function closeReplyModal() {
            document.getElementById('replyModal').classList.remove('active');
            document.getElementById('reply_message').value = '';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('replyModal');
            if (event.target == modal) {
                closeReplyModal();
            }
        }
    </script>
</body>
</html>
