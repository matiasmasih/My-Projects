
<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $recipient_type = $_POST['recipient_type'];
    $subject = trim($_POST['subject']);
    $message_content = trim($_POST['message']);
    
    if (!empty($subject) && !empty($message_content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, recipient_type, subject, body, status) VALUES (?, 0, ?, ?, ?, 'unread')");
            $stmt->execute([$_SESSION['user_id'], $recipient_type, $subject, $message_content]);
            $success = "Message sent to " . ucfirst($recipient_type) . " successfully!";
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$current_user_name = ($_SESSION['first_name'] ?? 'User') . ' ' . ($_SESSION['last_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Message - Hospital System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', sans-serif;
        }
        .message-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 15px;
        }
        .icon-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            text-decoration: none;
            color: inherit;
            display: block;
            cursor: pointer;
        }
        .icon-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .icon-large {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .message-form-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
            display: none;
        }
    </style>
</head>
<body>
<div class="message-container">
    <!-- Header -->
    <div class="text-center text-white mb-5">
        <h1 class="display-5 fw-bold"><i class="bi bi-chat-heart"></i> Send Message</h1>
        <p class="lead">Choose who you want to message</p>
    </div>

    <!-- Alerts -->
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Icon Grid -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="icon-card" onclick="showMessageForm('admin')">
                <div class="icon-large text-danger">
                    <i class="bi bi-person-shield"></i>
                </div>
                <h5>Message Admin</h5>
                <p class="text-muted">System administrator</p>
                <span class="badge bg-danger">Admin</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="icon-card" onclick="showMessageForm('manager')">
                <div class="icon-large text-warning">
                    <i class="bi bi-person-gear"></i>
                </div>
                <h5>Message Manager</h5>
                <p class="text-muted">Hospital manager</p>
                <span class="badge bg-warning">Manager</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="icon-card" onclick="showMessageForm('doctor')">
                <div class="icon-large text-primary">
                    <i class="bi bi-heart-pulse"></i>
                </div>
                <h5>Message Doctor</h5>
                <p class="text-muted">Medical doctors</p>
                <span class="badge bg-primary">Doctor</span>
            </div>
        </div>
    </div>

    <!-- Message Form -->
    <div class="message-form-container" id="messageForm">
        <form method="POST" id="messageFormElement">
            <input type="hidden" name="recipient_type" id="recipientType">
            <div class="text-center mb-4">
                <h3 id="formTitle">Send Message</h3>
                <p class="text-muted" id="formSubtitle">To: <span id="recipientName"></span></p>
            </div>
            <div class="mb-3">
                <label class="form-label">Subject</label>
                <input type="text" class="form-control" name="subject" required placeholder="What is this about?">
            </div>
            <div class="mb-3">
                <label class="form-label">Message</label>
                <textarea class="form-control" name="message" rows="5" required placeholder="Type your message..."></textarea>
            </div>
            <div class="text-end">
                <button type="button" class="btn btn-secondary" onclick="hideMessageForm()">Cancel</button>
                <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
            </div>
        </form>
    </div>
</div>

<script>
function showMessageForm(recipientType) {
    const form = document.getElementById('messageForm');
    const recipientTypeField = document.getElementById('recipientType');
    const recipientName = document.getElementById('recipientName');
    const formTitle = document.getElementById('formTitle');
    
    // Set recipient info
    recipientTypeField.value = recipientType;
    recipientName.textContent = recipientType.charAt(0).toUpperCase() + recipientType.slice(1);
    formTitle.textContent = 'Message ' + recipientName.textContent;
    
    // Show form
    form.style.display = 'block';
    form.scrollIntoView({ behavior: 'smooth' });
}

function hideMessageForm() {
    document.getElementById('messageForm').style.display = 'none';
    document.getElementById('messageFormElement').reset();
}
</script>
</body>
</html>
