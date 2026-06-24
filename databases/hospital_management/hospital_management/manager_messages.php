<?php
session_start();
include 'config.php';

// Check if user is Manager
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit;
}

// Initialize variables
$success = '';
$error = '';
$messages = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['mark_read'])) {
        $message_id = $_POST['message_id'];
        try {
            $pdo->prepare("UPDATE messages SET status = 'read' WHERE id = ?")->execute([$message_id]);
            $_SESSION['success'] = "Message marked as read!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }

    if (isset($_POST['delete_message'])) {
        $message_id = $_POST['message_id'];
        try {
            $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$message_id]);
            $_SESSION['success'] = "Message deleted successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }

    if (isset($_POST['reply_message'])) {
        $original_message_id = $_POST['original_message_id'];
        $reply_subject = trim($_POST['reply_subject']);
        $reply_body = trim($_POST['reply_body']);
        $recipient_id = $_POST['recipient_id'];

        if (empty($reply_subject) || empty($reply_body)) {
            $_SESSION['error'] = "Please fill in both subject and message for the reply.";
        } else {
            try {
                // Insert the reply message
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, recipient_type, subject, body, priority, status, parent_id) VALUES (?, ?, 'user', ?, ?, 'normal', 'unread', ?)");
                $stmt->execute([$_SESSION['user_id'], $recipient_id, $reply_subject, $reply_body, $original_message_id]);
                $_SESSION['success'] = "Reply sent successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error sending reply: " . $e->getMessage();
            }
        }
    }

    header("Location: manager_messages.php");
    exit;
}

// Check for session messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Fetch messages for manager
try {
    $messages_stmt = $pdo->prepare("
        SELECT m.*, 
               u.first_name, 
               u.last_name,
               u.role_id,
               u.email,
               (SELECT COUNT(*) FROM messages WHERE parent_id = m.id) as reply_count
        FROM messages m 
        LEFT JOIN users u ON m.sender_id = u.id 
        WHERE m.recipient_type = 'manager' 
        ORDER BY m.created_at DESC
    ");
    $messages_stmt->execute();
    $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count statistics
    $total_messages = count($messages);
    $unread_count = count(array_filter($messages, function($m) { return $m['status'] == 'unread'; }));
    $read_count = $total_messages - $unread_count;

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$role_config = [1 => 'Admin', 2 => 'Manager', 3 => 'Staff', 4 => 'Doctor', 5 => 'Nurse', 6 => 'Patient'];
$current_user_name = ($_SESSION['first_name'] ?? 'Manager') . ' ' . ($_SESSION['last_name'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Messages - Hospital Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --warning: #f72585;
            --sidebar-bg: #1a1d29;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            color: #333;
        }

        .main-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            background: var(--sidebar-bg);
            color: #fff;
            width: 260px;
            padding: 25px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 15px;
        }

        .sidebar-header h4 {
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: #b0b3c1;
            text-decoration: none;
            padding: 12px 25px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-weight: 500;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #2d3040;
            color: #fff;
            border-left: 3px solid var(--primary);
        }

        .sidebar-menu i {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            margin-left: 260px;
        }

        .navbar {
            background: linear-gradient(135deg, #fd7e14, #e56a00);
            padding: 12px 0;
            position: fixed;
            top: 0;
            right: 0;
            left: 260px;
            z-index: 1000;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 25px;
        }

        .card-header {
            background: linear-gradient(135deg, #fd7e14, #e56a00);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: var(--card-shadow);
            border-top: 4px solid #fd7e14;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .message-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #fd7e14;
            transition: all 0.3s ease;
        }

        .message-item:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .message-item.unread {
            background: linear-gradient(135deg, #fff, #fff3e6);
            border-left-color: #ffa94d;
        }

        .sender-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fd7e14, #e56a00);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .message-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-sm {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
        }

        .message-time {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .badge-role {
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 10px;
        }

        .reply-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            border-left: 3px solid #28a745;
        }

        .reply-indicator {
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand text-white" href="#">
            <i class="bi bi-hospital"></i>
            <span>MediCare Hospital</span>
        </a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link text-white">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo htmlspecialchars($current_user_name); ?>
                        (Manager)
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="main-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="bi bi-layout-sidebar"></i> Hospital Menu</h4>
        </div>
        <ul class="sidebar-menu">
            <li><a href="manager_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="admin_dashboard.php"><i class="bi bi-shield-check"></i> Admin Dashboard</a></li>
            <li><a href="patients.php"><i class="bi bi-person-fill"></i> Patients</a></li>
            <li><a href="doctors.php"><i class="bi bi-person-badge"></i> Doctors</a></li>
            <li><a href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
            <li><a href="invoices.php"><i class="bi bi-receipt"></i> Invoices</a></li>
            <li><a href="payments.php"><i class="bi bi-cash-stack"></i> Payments</a></li>
            <li><a href="pharmacy_stock.php"><i class="bi bi-capsule"></i> Pharmacy</a></li>
            <li><a href="medicines.php"><i class="bi bi-heart-pulse"></i> Medicines</a></li>
            <li><a href="wards.php"><i class="bi bi-house-door"></i> Wards</a></li>
            <li><a href="rooms.php"><i class="bi bi-door-closed"></i> Rooms</a></li>
            <li><a href="manager_messages.php" class="active"><i class="bi bi-chat-dots"></i> Messages</a></li>
            <li><a href="admissions.php"><i class="bi bi-journal-plus"></i> Admissions</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4" style="margin-top: 70px;">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-chat-dots me-2"></i>Manager Messages</h1>
                <p class="text-muted">Manage and view all messages sent to managers</p>
            </div>
            <div class="d-flex gap-2">
                <a href="manager_dashboard.php" class="btn btn-outline-warning">
                    <i class="bi bi-arrow-left me-2"></i>Back to User Messages
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-primary"><?php echo $total_messages; ?></div>
                    <div class="stats-label">Total Messages</div>
                    <i class="bi bi-inbox display-6 text-primary mt-2"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-warning"><?php echo $unread_count; ?></div>
                    <div class="stats-label">Unread Messages</div>
                    <i class="bi bi-envelope display-6 text-warning mt-2"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-success"><?php echo $read_count; ?></div>
                    <div class="stats-label">Read Messages</div>
                    <i class="bi bi-envelope-open display-6 text-success mt-2"></i>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-info"><?php echo count(array_unique(array_column($messages, 'sender_id'))); ?></div>
                    <div class="stats-label">Unique Senders</div>
                    <i class="bi bi-people display-6 text-info mt-2"></i>
                </div>
            </div>
        </div>

        <!-- Messages List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-list-ul me-2"></i>All Messages
                    <span class="badge bg-light text-dark ms-2"><?php echo $total_messages; ?> messages</span>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-warning"><?php echo $unread_count; ?> unread</span>
                    <span class="badge bg-success"><?php echo $read_count; ?> read</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No messages yet</h4>
                        <p class="text-muted">Messages sent to managers will appear here</p>
                    </div>
                <?php else: ?>
                    <div class="messages-container">
                        <?php foreach ($messages as $message): ?>
                            <div class="message-item <?php echo $message['status'] == 'unread' ? 'unread' : ''; ?>">
                                <div class="row">
                                    <div class="col-md-1">
                                        <div class="sender-avatar">
                                            <?php echo strtoupper(substr($message['first_name'], 0, 1)); ?>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center mb-2">
                                            <h6 class="mb-1 fw-bold me-3"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                            <span class="priority-badge me-2" style="background: <?php 
                                                if ($message['priority'] == 'urgent') echo '#ef4444';
                                                elseif ($message['priority'] == 'high') echo '#f59e0b';
                                                elseif ($message['priority'] == 'low') echo '#10b981';
                                                else echo '#3b82f6';
                                            ?>; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.7rem;">
                                                <?php echo ucfirst($message['priority']); ?>
                                            </span>
                                            <?php if ($message['reply_count'] > 0): ?>
                                                <span class="badge bg-info">
                                                    <i class="bi bi-reply-all me-1"></i><?php echo $message['reply_count']; ?> replies
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="fw-medium"><?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?></span>
                                            <span class="badge badge-role bg-secondary"><?php echo $role_config[$message['role_id']] ?? 'User'; ?></span>
                                            <span class="text-muted">•</span>
                                            <span class="text-muted"><?php echo $message['email']; ?></span>
                                        </div>
                                        <p class="mb-2 text-dark"><?php echo nl2br(htmlspecialchars($message['body'])); ?></p>
                                        <div class="message-time">
                                            <i class="bi bi-clock me-1"></i>
                                            <?php echo date('F j, Y \a\t g:i A', strtotime($message['created_at'])); ?>
                                            <?php if ($message['status'] == 'unread'): ?>
                                                <span class="badge bg-warning ms-2"><i class="bi bi-star-fill me-1"></i>New</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Reply Form -->
                                        <div class="reply-form" id="reply-form-<?php echo $message['id']; ?>" style="display: none;">
                                            <div class="reply-indicator">
                                                <i class="bi bi-reply-fill me-2"></i>
                                                Replying to: <strong><?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?></strong>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="original_message_id" value="<?php echo $message['id']; ?>">
                                                <input type="hidden" name="recipient_id" value="<?php echo $message['sender_id']; ?>">
                                                <div class="mb-2">
                                                    <label class="form-label">Subject</label>
                                                    <input type="text" class="form-control" name="reply_subject" value="Re: <?php echo htmlspecialchars($message['subject']); ?>" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Your Reply</label>
                                                    <textarea class="form-control" name="reply_body" rows="3" placeholder="Type your reply here..." required></textarea>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button type="submit" name="reply_message" class="btn btn-success btn-sm">
                                                        <i class="bi bi-send me-1"></i>Send Reply
                                                    </button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="hideReplyForm(<?php echo $message['id']; ?>)">
                                                        <i class="bi bi-x me-1"></i>Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <div class="message-actions justify-content-end">
                                            <button type="button" class="btn btn-info btn-sm" onclick="showReplyForm(<?php echo $message['id']; ?>)">
                                                <i class="bi bi-reply me-1"></i>Reply
                                            </button>
                                            <?php if ($message['status'] == 'unread'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                    <button type="submit" name="mark_read" class="btn btn-success btn-sm">
                                                        <i class="bi bi-check2 me-1"></i>Mark Read
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this message?')">
                                                <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                                <button type="submit" name="delete_message" class="btn btn-outline-danger btn-sm">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">ID: #<?php echo $message['id']; ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showReplyForm(messageId) {
    // Hide all other reply forms
    document.querySelectorAll('[id^="reply-form-"]').forEach(form => {
        form.style.display = 'none';
    });
    // Show the selected reply form
    document.getElementById('reply-form-' + messageId).style.display = 'block';
}

function hideReplyForm(messageId) {
    document.getElementById('reply-form-' + messageId).style.display = 'none';
}

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>
</body>
</html>
