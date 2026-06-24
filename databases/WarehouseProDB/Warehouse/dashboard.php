<?php
session_start();
require 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);


// Role check: only 'staff' or 'user' allowed here
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role']), ['staff', 'user'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = strtolower(trim($_SESSION['role']));

// Fetch dashboard stats
$totalProducts       = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCategories     = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalSuppliers      = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$totalLocations      = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
$totalStock          = $pdo->query("SELECT IFNULL(SUM(quantity),0) FROM inventory")->fetchColumn();
$totalUsers          = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalStockMovements = $pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>WarehousePro Dashboard</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <!-- Google Fonts (optional for modern look) -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap" rel="stylesheet">

<style>
  body {
    min-height: 100vh;
    margin: 0;
    background-color: #f8f9fa;
    display: flex;
    flex-direction: column;
  }

  .content {
    margin-left: 250px;
    padding: 20px;
    flex-grow: 1;
    min-height: 100vh;
    transition: margin-left 0.3s ease;
  }

  .content.collapsed {
    margin-left: 70px;
  }

  /* Sidebar */
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 200px;
    height: 100vh;
    background-color: #283a4d;
    color: white;
    display: flex;
    flex-direction: column;
    transition: width 0.3s ease;
    z-index: 1040;
    overflow-y: auto;
  }

  .sidebar.collapsed {
    width: 70px;
  }

  .sidebar .sidebar-brand {
    font-weight: bold;
    font-size: 1.5rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #444;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: opacity 0.3s ease;
  }

  .sidebar.collapsed .sidebar-brand .brand-text {
    display: none;
  }

  .sidebar .nav-link {
    color: #ccc;
    padding: 15px 20px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    transition: background-color 0.2s;
  }

  .sidebar .nav-link i {
    font-size: 1.2rem;
    min-width: 24px;
    text-align: center;
  }

  .sidebar.collapsed .nav-link .link-text {
    display: none;
  }

  .sidebar .nav-link.active,
  .sidebar .nav-link:hover {
    background-color: #1e3242;
    color: white;
    text-decoration: none;
  }

  /* Navbar */
.navbar-custom {
  background-color: #1d3557; /* change this to your desired background */
  border-radius: 10px;
  padding: 10px 20px;
  font-size: 20px; /* slightly larger font */
  color: #fff;
  margin: 10px 20px; /* optional: spacing from edges */
}

/* Make sure text inside brand and button is white */
.navbar-custom .navbar-brand,
.navbar-custom .toggle-btn,
.navbar-custom i {
  color: #ffffff;
  font-size: 25px;  /* Adjust size as needed */
  font-weight: 700;   /* Makes the font bold */

}

/* Optional: hover effect for the toggle button */
.toggle-btn:hover {
  background-color: rgba(255, 255, 255, 0.1);
  border-radius: 6px;
}

.toggle-btn {
  cursor: pointer;
  color: white;
  font-size: 1.5rem;
  padding: 10px 15px;
  border:none;
  background: #1d3557;  /* fixed spelling and single # */
}

/* Table wrapper */
.table-wrapper {
  background: #ffffff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

  .badge {
    font-size: 0.9rem;
  }

  .table thead th {
    background-color: #1e293b;
    color: #ffffff;
  }

  .table-hover tbody tr:hover {
    background-color: #f2f2f2;
  }

  /* Dashboard cards */
  .dashboard-card {
    border-radius: 10px;
    box-shadow: 0 2px 6px rgb(0 0 0 / 0.1);
    padding: 20px;
    background-color: white;
    margin-bottom: 20px;
    text-align: center;
    transition: transform 0.2s ease;
  }

  .dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgb(0 0 0 / 0.15);
  }

  /* Chat toggle button */
  #chatToggleBtn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1100;
    border-radius: 50%;
    width: 56px;
    height: 56px;
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #0d6efd;
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  }

  /* Chat box */
  #chatBox {
    position: fixed;
    bottom: 80px;
    right: 20px;
    width: 320px;
    max-height: 400px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 1100;
    font-family: Arial, sans-serif;
  }

  #chatBoxHeader {
    background-color: #007BFF;
    color: white;
    padding: 10px 15px;
    font-weight: bold;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  #chatMessages {
    flex-grow: 1;
    padding: 10px;
    overflow-y: auto;
    background: #f1f1f1;
  }

  #chatForm {
    display: flex;
    border-top: 1px solid #ddd;
  }

  #chatInput {
    flex-grow: 1;
    border: none;
    padding: 10px;
    font-size: 1rem;
  }

  #chatInput:focus {
    outline: none;
  }

  #chatForm button {
    border: none;
    background-color: #007BFF;
    color: white;
    padding: 0 15px;
    cursor: pointer;
    transition: background-color 0.2s ease;
  }

  #chatForm button:hover {
    background-color: #0056b3;
  }

  /* Chat message bubbles */
  .chat-message {
    margin-bottom: 10px;
    padding: 8px 12px;
    border-radius: 15px;
    max-width: 80%;
    clear: both;
    word-wrap: break-word;
  }

  .chat-message.self {
    background-color: #0d6efd;
    color: white;
    float: right;
    border-bottom-right-radius: 0;
  }

  .chat-message.other {
    background-color: #e2e3e5;
    color: #212529;
    float: left;
    border-bottom-left-radius: 0;
  }

  .chat-message.admin {
    background-color: #f8d7da;
    color: #842029;
    float: left;
    border-radius: 15px;
    border-bottom-left-radius: 0;
  }

  /* Responsive tweaks */
  @media (max-width: 768px) {
    .sidebar {
      position: fixed;
      transform: translateX(-100%);
      width: 250px !important;
      z-index: 1050;
    }

    .sidebar.show {
      transform: translateX(0);
    }

    .content {
      margin-left: 0 !important;
    }

    #chatBox {
      width: 90vw;
      right: 5vw;
      bottom: 80px;
    }
  }
</style>
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-warehouse" style="color:#ffffff;"></i>
        <span class="brand-text">WarehousePro</span>
    </div>
    <a href="dashboard.php" class="nav-link">
        <i class="bi bi-speedometer2" style="color:#3288b3;"></i> <span class="link-text">Dashboard</span>
    </a>
    <a href="products.php" class="nav-link">
        <i class="bi bi-box-seam" style="color:#19a8b3;"></i> <span class="link-text">Products</span>
    </a>
    <a href="categories.php" class="nav-link">
        <i class="bi bi-tags" style="color:#198754;"></i> <span class="link-text">Categories</span>
    </a>
    <a href="suppliers.php" class="nav-link">
        <i class="bi bi-truck" style="color:#fd7e14;"></i> <span class="link-text">Suppliers</span>
    </a>
    <a href="locations.php" class="nav-link">
        <i class="bi bi-geo-alt" style="color:#0dcaf0;"></i> <span class="link-text">Locations</span>
    </a>
    <a href="inventory.php" class="nav-link">
        <i class="bi bi-stack" style="color:#6f42c1;"></i> <span class="link-text">Inventory</span>
    </a>
    <a href="stock_movements.php" class="nav-link">
        <i class="bi bi-arrow-left-right" style="color:#dc3545;"></i> <span class="link-text">Stock Movements</span>
    </a>
      <?php if ($role === 'admin' || $role === 'staff'): ?>
    <a href="users.php" class="nav-link">
        <i class="bi bi-people" style="color:#20c997;"></i> <span class="link-text">Users</span>
    </a>
    <?php endif; ?>
    <a href="logout.php" class="nav-link">
        <i class="bi bi-box-arrow-right" style="color:#ff6b6b;"></i> <span class="link-text">Logout</span>
    </a>
</nav>

<!-- Page Content -->
<div class="content" id="content">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <button class="toggle-btn" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <a class="navbar-brand ms-4" href="users.php">Welcome, <?= htmlspecialchars($username) ?></a>
        </div>
    </nav>

    <!-- Main dashboard content -->
    <div class="container-fluid mt-4">
        <h1 class="mb-4">Dashboard Overview</h1>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="bi bi-box-seam fs-1 text-primary mb-2"></i>
                    <h5>Products</h5>
                    <p class="h4 text-primary"><?= $totalProducts ?></p>
                    <p>Manage warehouse products</p>
                    <a href="products.php" class="btn btn-primary btn-sm">View</a>
                </div>
            </div>

            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="bi bi-tags fs-1 text-success mb-2"></i>
                    <h5>Categories</h5>
                    <p class="h4 text-success"><?= $totalCategories ?></p>
                    <p>Organize product categories</p>
                    <a href="categories.php" class="btn btn-success btn-sm">View</a>
                </div>
            </div>

            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="bi bi-truck fs-1 text-warning mb-2"></i>
                    <h5>Suppliers</h5>
                    <p class="h4 text-warning"><?= $totalSuppliers ?></p>
                    <p>Manage supplier information</p>
                    <a href="suppliers.php" class="btn btn-warning btn-sm">View</a>
                </div>
            </div>

            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="bi bi-geo-alt fs-1 text-info mb-2"></i>
                    <h5>Locations</h5>
                    <p class="h4 text-info"><?= $totalLocations ?></p>
                    <p>Track warehouse locations</p>
                    <a href="locations.php" class="btn btn-info btn-sm">View</a>
                </div>
            </div>

            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="bi bi-stack fs-1 text-secondary mb-2"></i>
                    <h5>Inventory</h5>
                    <p class="h4 text-secondary"><?= $totalStock ?></p>
                    <p>Current inventory quantity</p>
                    <a href="inventory.php" class="btn btn-secondary btn-sm">View</a>
                </div>
            </div>

            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="bi bi-people fs-1 text-success mb-2"></i>
                    <h5>Users</h5>
                    <p class="h4 text-success"><?= $totalUsers ?></p>
                    <p>Warehouse system users</p>
                    <a href="users.php" class="btn btn-success btn-sm">View</a>
                </div>
            </div>

         <div class="col-md-3">
          <div class="dashboard-card">
           <i class="bi bi-arrow-left-right fs-1 text-danger mb-2"></i>
            <h5>Total Stock Movements</h5>
              <p class="h4 text-danger"><?= $totalStockMovements ?></p>
              <p>All recorded stock movements</p>
              <a href="stock_movements.php" class="btn btn-danger btn-sm">View</a>
           </div>
         </div>
        </div>
    </div>
</div>

<!-- Chat toggle button -->
<button id="chatToggleBtn" class="btn btn-primary" title="Open Chat">
    <i class="bi bi-chat-dots"></i>
</button>

<!-- Chat box -->
<div id="chatBox" role="region" aria-label="Chat messages">
    <div id="chatBoxHeader">
        Chat
        <button type="button" id="chatCloseBtn" class="btn btn-sm btn-light" aria-label="Close Chat">&times;</button>
    </div>
    <div id="chatMessages" tabindex="0" aria-live="polite" aria-atomic="false" style="display:flex; flex-direction: column;"></div>
    <form id="chatForm" autocomplete="off">
        <input type="text" id="chatInput" placeholder="Type your message..." aria-label="Chat message input" required />
        <button type="submit" aria-label="Send message">Send</button>
    </form>
</div>

<!-- jQuery + Bootstrap JS (make sure you load these) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(function () {
  const chatToggleBtn = $('#chatToggleBtn');
  const chatBox       = $('#chatBox');
  const chatCloseBtn  = $('#chatCloseBtn');
  const chatMessages  = $('#chatMessages');
  const chatInput     = $('#chatInput');
  const chatForm      = $('#chatForm');

  // Safely get current user ID from PHP (fallback to null if not set)
  const currentUserId = <?php echo isset($userId) ? json_encode($userId) : 'null'; ?>;

  /* Open chat */
  chatToggleBtn.on('click', function () {
    console.log("Chat toggle button clicked");
    chatBox.show();
    chatToggleBtn.hide();
    loadMessages();
    chatInput.focus();
  });

  /* Close chat */
  chatCloseBtn.on('click', function () {
    chatBox.hide();
    chatToggleBtn.show();
  });

  function loadMessages () {
    $.getJSON('chat_api.php')
      .done(data => {
        chatMessages.empty();
        data.forEach(row => {
          const mine   = (row.user_id == currentUserId);
          const uCls   = mine ? 'self' : 'other';
          const uName  = $('<div>').text(row.username).html();
          const uMsg   = $('<div>').text(row.message).html();

          chatMessages.append(
            `<div class="chat-message ${uCls}">
               <strong>${uName}:</strong> ${uMsg}
             </div>`
          );

          if (row.admin_reply) {
            const aMsg = $('<div>').text(row.admin_reply).html();
            chatMessages.append(
              `<div class="chat-message admin">
                 <strong>Admin:</strong> ${aMsg}
               </div>`
            );
          }
        });
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
      })
      .fail(err => {
        console.error('Failed to load chat messages', err);
      });
  }

  chatForm.on('submit', function (e) {
    e.preventDefault();
    const txt = chatInput.val().trim();
    if (!txt) return;

    $.post('chat_api.php', { message: txt }, res => {
      if (res.success) {
        chatInput.val('');
        loadMessages();
      } else {
        alert(res.error || 'Send error');
      }
    }, 'json')
    .fail(() => alert('Error sending message'));
  });

  setInterval(() => {
    if (chatBox.is(':visible')) loadMessages();
  }, 5000);
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar   = document.getElementById('sidebar');
  const content   = document.getElementById('content');

  toggleBtn.addEventListener('click', function () {
    sidebar.classList.toggle('collapsed');
    content.classList.toggle('collapsed');
  });
});
</script>

</body>
</html>
