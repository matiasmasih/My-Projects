<?php
session_start();
require 'config.php';

// Check admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$replyToId = isset($_GET['reply_to']) ? (int)$_GET['reply_to'] : null;

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle admin reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'], $_POST['message_id'], $_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $msgId = (int)$_POST['message_id'];
    $reply = trim($_POST['reply']);
    if ($reply !== '') {
        $stmt = $pdo->prepare("UPDATE messages SET admin_reply = ?, replied_at = NOW(), status = 'replied' WHERE id = ?");
        $stmt->execute([$reply, $msgId]);
        header('Location: admin_messages.php');
        exit;
    }
}

// Fetch all messages
$stmt = $pdo->query("
    SELECT m.*, u.username
    FROM messages m
    JOIN users u ON m.user_id = u.id
    ORDER BY m.sent_at DESC
");
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Messages</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
  --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  --dark-bg: #0f172a;
  --card-bg: #ffffff;
  --text-primary: #1e293b;
  --text-secondary: #64748b;
  --border-color: #e2e8f0;
  --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
  --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
  --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
}

* {
  box-sizing: border-box;
}

body {
  background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
  min-height: 100vh;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
  color: var(--text-primary);
  padding-top: 80px;
  padding-bottom: 2rem;
}

.navbar-darknav {
  background: var(--dark-bg);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: var(--shadow-lg);
  transition: all 0.3s ease;
}

/* ✅ Navbar spacing and alignment fix */
.navbar-nav {
  gap: 1px !important; /* exactly 1px gap between items */
}

.nav-link {
  color: #cbd5e1 !important;
  font-weight: 500;
  font-size: 16px;
  border-radius: 0.75rem;
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
}

.nav-link i {
  margin-right: 6px; /* space between icon and text */
  display: inline-block;
  vertical-align: middle;
}

.navbar-brand {
  font-weight: 800;
  font-size: 20px;
  background: var(--primary-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.container {
  max-width: 1400px;
  padding: 0 2rem;
}

.page-header {
  margin-bottom: 2rem;
  text-align: center;
}

.page-title {
  font-size: 2.5rem;
  font-weight: 800;
  background: var(--primary-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 0.5rem;
}

.page-subtitle {
  color: var(--text-secondary);
  font-size: 1.1rem;
  font-weight: 400;
}

.messages-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
  gap: 1.5rem;
  margin-top: 2rem;
}

.message-card {
  background: var(--card-bg);
  border-radius: 1rem;
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-sm);
  transition: all 0.3s ease;
  overflow: hidden;
  position: relative;
}

.message-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
}

.message-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--primary-gradient);
}

.message-card.replied::before {
  background: var(--success-gradient);
}

.message-card.pending::before {
  background: var(--secondary-gradient);
}

.card-body {
  padding: 1.5rem;
}

.message-header {
  display: flex;
  justify-content: between;
  align-items: flex-start;
  margin-bottom: 1rem;
  gap: 1rem;
}

.user-info {
  flex: 1;
}

.username {
  font-weight: 700;
  font-size: 1.1rem;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.message-meta {
  display: flex;
  align-items: center;
  gap: 1rem;
  color: var(--text-secondary);
  font-size: 0.85rem;
}
.status-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 2rem;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-badge.replied {
  background: linear-gradient(135deg, #dcfce7, #bbf7d0);
  color: #166534;
}

.status-badge.pending {
  background: linear-gradient(135deg, #fef3c7, #fed7aa);
  color: #92400e;
}

.message-text {
  background: #f8fafc;
  padding: 1rem;
  border-radius: 0.75rem;
  margin: 1rem 0;
  border-left: 3px solid #e2e8f0;
  font-size: 0.95rem;
  line-height: 1.6;
  white-space: pre-wrap;
}

.admin-reply {
  background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
  border: 1px solid #0ea5e9;
  border-radius: 0.75rem;
  padding: 1rem;
  margin-top: 1rem;
}

.admin-reply-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #0369a1;
  font-weight: 700;
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
}

.admin-reply-text {
  color: #0c4a6e;
  line-height: 1.5;
  margin-bottom: 0.5rem;
}

.admin-reply-time {
  text-align: right;
  color: #0369a1;
  font-size: 0.8rem;
  font-weight: 500;
}

.reply-form {
  background: #f8fafc;
  border-radius: 0.75rem;
  padding: 1.5rem;
  margin-top: 1rem;
  border: 1px solid var(--border-color);
}

.reply-textarea {
  width: 100%;
  min-height: 120px;
  padding: 1rem;
  border: 1px solid var(--border-color);
  border-radius: 0.5rem;
  font-family: inherit;
  font-size: 0.9rem;
  resize: vertical;
  transition: border-color 0.2s ease;
}
.reply-textarea:focus {
  outline: none;
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-modern {
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  font-weight: 600;
  font-size: 0.9rem;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-primary-modern {
  background: var(--primary-gradient);
  color: white;
}

.btn-primary-modern:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
  color: white;
}

.btn-success-modern {
  background: var(--success-gradient);
  color: white;
}

.btn-success-modern:hover {
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
  color: white;
}
.btn-secondary-modern {
  background: #f1f5f9;
  color: var(--text-secondary);
  border: 1px solid var(--border-color);
}

.btn-secondary-modern:hover {
  background: #e2e8f0;
  color: var(--text-primary);
}

.btn-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1rem;
}

.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  background: var(--card-bg);
  border-radius: 1rem;
  border: 1px solid var(--border-color);
  margin-top: 2rem;
}

.empty-state i {
  font-size: 4rem;
  color: var(--text-secondary);
  margin-bottom: 1rem;
}

.empty-state h3 {
  color: var(--text-primary);
  margin-bottom: 0.5rem;
}

.empty-state p {
  color: var(--text-secondary);
}

@media (max-width: 768px) {
  .container {
    padding: 0 1rem;
  }

  .messages-grid {
    grid-template-columns: 1fr;
  }

  .page-title {
    font-size: 2rem;
  }

  .message-header {
    flex-direction: column;
    gap: 0.5rem;
  }

  .btn-actions {
    flex-direction: column;
  }
}

@media (max-width: 576px) {
  body {
    padding-top: 70px;
  }

  .nav-link {
    padding: 0.5rem 0.75rem !important;
    font-size: 0.85rem;
  }
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top shadow-sm navbar-darknav">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="admin_dashboard.php">📦 WAREHOUSE</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarWarehouse">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarWarehouse">
      <ul class="navbar-nav ms-auto mb-1 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-line"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_products.php"><i class="fas fa-boxes"></i>Products</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_suppliers.php"><i class="fas fa-truck"></i>Suppliers</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_locations.php"><i class="fas fa-map-marker-alt"></i>Locations</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_inventory.php"><i class="fas fa-warehouse"></i>Inventory</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_stock_movements.php"><i class="bi bi-arrow-left-right"></i>Stock Movements</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_location_history.php"><i class="bi bi-clock-history"></i>Location History</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_users.php"><i class="bi bi-people"></i>Users</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container">
  <div class="page-header">
    <h1 class="page-title">Message Center</h1>
    <p class="page-subtitle">Manage and respond to user messages</p>
  </div>

  <?php if ($messages): ?>
    <div class="messages-grid">
      <?php foreach ($messages as $msg): ?>
        <div class="message-card <?= $msg['status'] === 'replied' ? 'replied' : 'pending' ?>">
          <div class="card-body">
            <div class="message-header">
              <div class="user-info">
                <div class="username"><?= htmlspecialchars($msg['username']) ?></div>
                <div class="message-meta">
                  <span><i class="far fa-clock"></i> <?= htmlspecialchars($msg['sent_at']) ?></span>
                  <span class="status-badge <?= $msg['status'] === 'replied' ? 'replied' : 'pending' ?>">
                    <?= $msg['status'] === 'replied' ? 'Replied' : 'Pending' ?>
                  </span>
                </div>
              </div>
            </div>

            <div class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>

            <?php if ($msg['status'] === 'replied'): ?>
              <div class="admin-reply">
                <div class="admin-reply-header">
                  <i class="fas fa-user-shield"></i>
                  Admin Reply:
                </div>
                <div class="admin-reply-text"><?= nl2br(htmlspecialchars($msg['admin_reply'])) ?></div>
                <div class="admin-reply-time">Replied on: <?= htmlspecialchars($msg['replied_at']) ?></div>
              </div>
            <?php elseif ($replyToId === $msg['id']): ?>
              <div class="reply-form">
                <form method="post" novalidate>
                  <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                  <!-- ✅ Added CSRF token field -->
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                  <textarea name="reply" class="reply-textarea" placeholder="Type your reply..." required></textarea>
                  <div class="btn-actions">
                    <button type="submit" class="btn-modern btn-success-modern">
                      <i class="fas fa-paper-plane"></i>Send Reply
                    </button>
                    <a href="messages.php" class="btn-modern btn-secondary-modern">
                      <i class="fas fa-times"></i>Cancel
                    </a>
                  </div>
                </form>
              </div>
            <?php else: ?>
              <div class="btn-actions">
                <a href="?reply_to=<?= $msg['id'] ?>" class="btn-modern btn-primary-modern">
                  <i class="fas fa-reply"></i>Reply
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <i class="fas fa-inbox"></i>
      <h3>No Messages Yet</h3>
      <p>When users send messages, they will appear here for you to review and respond to.</p>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
