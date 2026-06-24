<?php
session_start();
require 'config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Normalize role
$role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : null;

// Allow only admin and manager roles
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'manager'])) {
    header('Location: login.php');
    exit;
}

// Counts
$productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$supplierCount = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$locationCount = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$lowStockCount = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity < 10")->fetchColumn();

// Counts for dashboard cards
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE status = 'pending'");
$unreadStmt->execute();
$unreadCount = $unreadStmt->fetchColumn();

// Messages for admin
$adminId = $_SESSION['user_id'] ?? null;
if (!$adminId) {
    exit('Access denied. Please log in.');
}

// Handle admin reply POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_id'], $_POST['reply'])) {
    $messageId = intval($_POST['message_id']);
    $replyMsg = trim($_POST['reply']);
    if ($replyMsg !== '') {
        $stmt = $pdo->prepare("
            UPDATE messages
            SET admin_reply = ?, status = 'replied', replied_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$replyMsg, $messageId]);

        header('Location: admin_dashboard.php?view=messages');
        exit;
    }
}

// Fetch messages only if ?view=messages is set
$messages = [];
if (isset($_GET['view']) && $_GET['view'] === 'messages') {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username AS sender_name
        FROM messages m
        JOIN users u ON m.user_id = u.id
        ORDER BY m.sent_at DESC
    ");
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Dashboard - Warehouse</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />

<style>
body {
  min-height: 100vh;
  display: flex;
  background: #f5f7fa;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  overflow-x: hidden;
}

/* === Sidebar === */
#sidebar {
  position: fixed;
  left: 0;
  top: 0;
  height: 100vh;
  width: 230px;
  background-color: #1f2937;
  color: #e2e8f0;
  transition: width 0.3s ease, transform 0.3s ease;
  overflow: hidden;
  box-shadow: 2px 0 12px rgba(0, 0, 0, 0.15);
  z-index: 1030;
}

#sidebar.collapsed {
  width: 70px;
  overflow: visible;
}

/* ✅ Hide sidebar header (WAREHOUSE) when collapsed */
#sidebar.collapsed .sidebar-header {
  display: none;
}

#sidebar .sidebar-header {
  font-size: 1.5rem;
  font-weight: 700;
  text-align: center;
  padding: 1.2rem 0;
  letter-spacing: 2px;
  border-bottom: 1px solid #374151;
}

#sidebar ul {
  list-style: none;
  padding-left: 0;
  margin: 0;
}

#sidebar ul li {
  padding: 0.6rem 1.5rem; /* ✅ Reduced padding */
  cursor: pointer;
  white-space: nowrap;
  transition: background-color 0.3s ease;
  display: flex;
  align-items: center;
}

#sidebar ul li i {
  font-size: 1.5rem;
  margin-right: 15px;
  min-width: 24px;
  text-align: center;
}

#sidebar ul li:hover,
#sidebar ul li.active {
  background-color: #2563eb;
  color: white;
}

#sidebar.collapsed ul li span {
  display: none;
}

#sidebar.collapsed ul li {
  justify-content: center;
}

/* === Top Navbar === */
.navbar-custom {
  background-color: #2563eb;
  color: white;
  padding: 0.5rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-radius: 10px;
  margin-bottom: 30px;
}

.navbar-custom h2 {
  margin: 0;
  font-weight: 600;
}

.btn-toggle {
  background: transparent;
  border: none;
  color: white;
  font-size: 1.8rem;
  cursor: pointer;
  padding: 0;
}

/* === Main Content Area === */
#content {
  margin-left: 230px;
  padding: 30px;
  width: calc(100% - 230px);
  transition: margin-left 0.3s ease, width 0.3s ease;
}

#sidebar.collapsed + #content {
  margin-left: 70px;
  width: calc(100% - 70px);
}

/* === Dashboard Cards Grid === */
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 24px;
}

/* === Clickable Dashboard Card Link === */
.dashboard-link {
  text-decoration: none !important;
  color: inherit;
  display: block;
  transition: transform 0.2s;
}

.dashboard-link:hover {
  transform: scale(1.02);
}

/* === Dashboard Card Styling === */
.dashboard-card {
  background-color: #f8fafc;
  padding: 20px;
  border-radius: 12px;
  text-align: center;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  transition: background-color 0.3s ease, transform 0.3s ease;
}

.dashboard-card:hover {
  background-color: #e2e8f0;
}

.dashboard-card i {
  font-size: 2.5rem;
  margin-bottom: 10px;
}

.dashboard-card h3 {
  font-size: 1.2rem;
  margin: 10px 0 5px;
  font-weight: normal;
}

.dashboard-card p {
  font-size: 1.5rem;
  font-weight: normal;
  margin: 0;
}

/* === Responsive Sidebar and Layout === */
@media (max-width: 768px) {
  #sidebar {
    position: fixed;
    z-index: 1045;
    transform: translateX(-100%);
    width: 230px !important;
  }

  #sidebar.show {
    transform: translateX(0);
  }

  /* ✅ Ensure fully hidden when collapsed on mobile */
  #sidebar.collapsed {
    width: 230px !important;
    transform: translateX(-100%) !important;
  }

  #content {
    margin-left: 0 !important;
    width: 100% !important;
    padding: 15px !important;
  }
}

</style>
</head>
<body>

<!-- Sidebar -->
<nav id="sidebar">
  <div class="sidebar-header">WAREHOUSE</div>
  <ul>
    <li onclick="location.href='admin_dashboard.php'"><i class="bi bi-speedometer2" style="color:#222836;"></i><span>Dashboard</span></li>
    <li onclick="location.href='admin_products.php'"><i class="bi bi-box-seam" style="color:#19a8b3;"></i><span>Products</span></li>
    <li onclick="location.href='admin_suppliers.php'"><i class="bi bi-truck" style="color:#198754;"></i><span>Suppliers</span></li>
    <li onclick="location.href='admin_locations.php'"><i class="bi bi-geo-alt" style="color:#fd7e14;"></i><span>Locations</span></li>
    <li onclick="location.href='admin_inventory.php'"><i class="bi bi-clipboard-data" style="color:#0dcaf0;"></i><span>Inventory</span></li>
    <li onclick="location.href='admin_stock_movements.php'"> <i class="bi bi-arrow-left-right"style="color:#ffc107;"></i><span> Stock Movements</span></li>
    <li onclick="location.href='admin_location_history.php'"> <i class="bi bi-clock-history" style="color:#0d6efd;"></i><span>Location History</span></li>
    <li onclick="location.href='messages.php'"><i class="bi bi-chat-dots" style="color: #0d6efd;"></i><span>Messages</span></li>
    <li onclick="location.href='admin_users.php'"><i class="bi bi-people" style="color:#6f42c1;"></i><span>Users</span></li>
    <li onclick="location.href='logout.php'"><i class="bi bi-box-arrow-right" style="color:#dc3545;"></i><span>Logout</span></li>
  </ul>
</nav>

<!-- Content -->
<div id="content">
  <div class="navbar-custom">
    <button id="btnToggle" class="btn-toggle" aria-label="Toggle sidebar">
      <i class="bi bi-list"></i>
    </button>
    <h2>Admin Dashboard</h2>
  </div>
<div class="dashboard-grid">
  <a href="admin_products.php" class="dashboard-link">
    <div class="dashboard-card">
      <i class="bi bi-box-seam" style="color:#19a8b3;"></i>
      <h3>Products</h3>
      <p><?= $productCount ?></p>
    </div>
  </a>

  <a href="admin_suppliers.php" class="dashboard-link">
    <div class="dashboard-card">
      <i class="bi bi-truck" style="color:#198754;"></i>
      <h3>Suppliers</h3>
      <p><?= $supplierCount ?></p>
    </div>
  </a>

  <a href="admin_locations.php" class="dashboard-link">
    <div class="dashboard-card">
      <i class="bi bi-geo-alt" style="color:#fd7e14;"></i>
      <h3>Locations</h3>
      <p><?= $locationCount ?></p>
    </div>
  </a>

 <a href="admin_stock_movements.php" class="dashboard-link">
    <div class="dashboard-card">
      <i class="bi bi-arrow-left-right" style="color:#dc3545;"></i>
      <h3>Stock Movements Items</h3>
      <p><?= $lowStockCount ?></p>
    </div>
  </a>

  <a href="admin_location_history.php" class="dashboard-link">
  <div class="dashboard-card">
    <i class="bi bi-clock-history" style="color:#0d6efd;"></i>
    <h3>Location History</h3>
    <p><?= $historyCount ?? 0 ?></p>
  </div>
</a>

  <a href="admin_users.php" class="dashboard-link">
    <div class="dashboard-card">
      <i class="bi bi-people" style="color:#6f42c1;"></i>
      <h3>Users</h3>
      <p><?= $userCount ?></p>
    </div>
  </a>

<a href="messages.php" class="dashboard-link">
  <div class="dashboard-card">
    <i class="bi bi-chat-dots" style="color:#0d6efd;"></i>
    <h3>Messages</h3>
    <p><?= $unreadCount ?> pending</p>
  </div>
</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const btnToggle = document.getElementById('btnToggle');
  const sidebar = document.getElementById('sidebar');
  btnToggle.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
  });
</script>
</body>
</html>

