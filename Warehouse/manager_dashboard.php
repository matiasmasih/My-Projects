<?php
session_start();
require 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : null;
if (!isset($_SESSION['user_id']) || $role !== 'manager') {
    header('Location: login.php');
    exit;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Manager';

// Initialize counts with default values
$productCount = $categoryCount = $supplierCount = $locationCount = $inventoryCount = $stockMovementsCount = $locationHistoryCount = $messagesCount = $usersCount = 0;

try {
    $productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $categoryCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $supplierCount = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
    $locationCount = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
    $inventoryCount = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
    $stockMovementsCount = $pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();
    $locationHistoryCount = $pdo->query("SELECT COUNT(*) FROM location_history")->fetchColumn();
    $messagesCount = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
    $usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

// Stock movements chart data
try {
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(timestamp, '%Y-%m') AS month, COUNT(*) AS count FROM stock_movements GROUP BY month ORDER BY month DESC LIMIT 6");
    $stmt->execute();
    $stockMovementsDataRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stockMovementLabels = array_reverse(array_column($stockMovementsDataRaw, 'month'));
    $stockMovementData = array_reverse(array_map('intval', array_column($stockMovementsDataRaw, 'count')));
} catch (PDOException $e) {
    error_log("Stock Movement query error: " . $e->getMessage());
    $stockMovementLabels = [];
    $stockMovementData = [];
}

// Inventory by location chart data
try {
    $stmt = $pdo->prepare("SELECT l.name AS location_name, COALESCE(SUM(i.quantity), 0) AS total_quantity
                           FROM locations l
                           LEFT JOIN inventory i ON i.location_id = l.id
                           GROUP BY l.id");
    $stmt->execute();
    $inventoryByLocation = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $inventoryLabels = array_column($inventoryByLocation, 'location_name');
    $inventoryData = array_map('intval', array_column($inventoryByLocation, 'total_quantity'));
} catch (PDOException $e) {
    error_log("Inventory by Location query error: " . $e->getMessage());
    $inventoryLabels = [];
    $inventoryData = [];
}

// User roles chart data
try {
    $stmt = $pdo->prepare("SELECT role, COUNT(*) AS count FROM users GROUP BY role");
    $stmt->execute();
    $userRolesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $userRoleLabels = array_column($userRolesData, 'role');
    $userRoleCounts = array_map('intval', array_column($userRolesData, 'count'));
} catch (PDOException $e) {
    error_log("Users Overview query error: " . $e->getMessage());
    $userRoleLabels = [];
    $userRoleCounts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manager Dashboard - WarehousePro</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<style>
:root {
  --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
  --info-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
  --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
  --sidebar-width: 280px;
  --sidebar-collapsed-width: 60px;
  --topbar-height: 70px;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
  min-height: 100vh;
  overflow-x: hidden;
}

/* Sidebar Styles */
#sidebar {
  position: fixed;
  top: 0;
  left: 0;
  width: var(--sidebar-width);
  height: 100vh;
  background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
  color: white;
  z-index: 1000;
  transition: transform 0.3s ease, width 0.3s ease;
  box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
}

.sidebar-header {
  padding: 10px 5px;
  text-align: left;
}

.sidebar-header h3 {
  font-size: 24px;
  font-weight: 700;
  margin: 0;
  color: white;
  transition: opacity 0.3s ease;
}

.nav-item {
  margin: 5px 15px;
  border-radius: 12px;
  overflow: hidden;
}

.nav-link {
  display: flex;
  align-items: center;
  padding: 12px 15px;
  color: #cbd5e1;
  text-decoration: none;
  transition: all 0.3s ease;
  border-radius: 12px;
  position: relative;
  white-space: nowrap;
}

.nav-link:hover,
.nav-link.active {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  transform: translateX(5px);
}

.nav-link i {
  font-size: 20px;
  margin-right: 15px;
  width: 25px;
  text-align: center;
  flex-shrink: 0;
  display: inline-block;
}

.nav-text {
  transition: opacity 0.3s ease;
}

.top-navbar {
  position: fixed;
  top: 0;
  left: var(--sidebar-width);
  right: 0;
  height: var(--topbar-height);
  background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  z-index: 999;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  transition: left 0.3s ease;
}

.hamburger {
  cursor: pointer;
  font-size: 22px;
  color: white;
  display: flex;
  align-items: center;
  padding: 8px;
  border-radius: 4px;
  transition: background-color 0.3s ease;
}

.hamburger:hover {
  background-color: rgba(255, 255, 255, 0.1);
}

.user-info {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(255, 255, 255, 0.1);
  padding: 8px 20px;
  border-radius: 25px;
  color: white;
  font-weight: 500;
}

.user-info i {
  font-size: 20px;
}

/* Collapsed Sidebar Styles */
.sidebar-collapsed #sidebar {
  width: var(--sidebar-collapsed-width);
}

.sidebar-collapsed #sidebar .sidebar-header h3 {
  opacity: 0;
  pointer-events: none;
}

.sidebar-collapsed #sidebar .nav-text {
  opacity: 0;
  display: none;
}

.sidebar-collapsed #sidebar .nav-item {
  margin: 5px 5px;
}

.sidebar-collapsed #sidebar .nav-link {
  justify-content: center;
  padding: 10px 5px;
  margin: 0;
  display: flex;
  align-items: left;
}

.sidebar-collapsed #sidebar .nav-link i {
  margin-right: 0;
  font-size: 22px;
}

.sidebar-collapsed .top-navbar {
  left: var(--sidebar-collapsed-width);
}

.sidebar-collapsed .main-content {
  margin-left: var(--sidebar-collapsed-width);
}

/* Main Content */
.main-content {
  margin-left: var(--sidebar-width);
  margin-top: var(--topbar-height);
  padding: 30px;
  min-height: calc(100vh - var(--topbar-height));
  transition: margin-left 0.3s ease;
}

.page-header {
  margin-bottom: 40px;
}

.page-title {
  font-size: 32px;
  font-weight: 700;
  color: #000000;
  margin-bottom: 8px;
}

/* Dashboard Cards */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 10px;
  margin-bottom: 40px;
}

.stat-card {
  background: white;
  border-radius: 20px;
  padding: 30px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  text-decoration: none;
  color: inherit;
}

.stat-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
  text-decoration: none;
  color: inherit;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--card-gradient, var(--primary-gradient));
}

.stat-icon {
  width: 60px;
  height: 60px;
  border-radius: 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
  background: var(--card-gradient, var(--primary-gradient));
  color: white;
  font-size: 24px;
}

.stat-number {
  font-weight: bold;
  font-weight: 700;
  font-size: 36px;
  font-weight: 700;
  color: #0a0a0;
  margin-bottom: 8px;
}

.stat-label {
  font-weight: 700; /* this is bold */
  color: #0a0a0;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Card color variations */
.card-blue {
  --card-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card-pink {
  --card-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.card-cyan {
  --card-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.card-green {
  --card-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.card-orange {
  --card-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.card-purple {
  --card-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
}

.card-red {
  --card-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
}

.card-teal {
  --card-gradient: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);
}

/* Charts Section */
.charts-section {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 10px;
  margin-top: 40px;
}

.chart-container {
  background: white;
  border-radius: 20px;
  padding: 20px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
  position: relative;
}

.chart-title {
  font-size: 18px;
  font-weight: 600;
  color: #1a202c;
  margin-bottom: 15px;
  text-align: center;
}

.chart-canvas {
  position: relative;
  height: 200px !important;
}

/* Responsive Design */
@media (max-width: 768px) {
#sidebar {
  transform: translateX(-100%);
}
.main-content {
  margin-left: 0;
}

.top-navbar {
  left: 0;
}

.stats-grid {
  grid-template-columns: 1fr;
}

.charts-section {
  grid-template-columns: 1fr;
}

.sidebar-collapsed #sidebar {
  transform: translateX(-100%);
 }
}

/* Loading animation */
.loading {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 3px solid rgba(255, 255, 255, .3);
  border-radius: 50%;
  border-top-color: #fff;
  animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
to {
  transform: rotate(360deg);
 }
}

/* Smooth scrollbar */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: #555;
}

</style>
</head>
<body>

<!-- Sidebar -->
<nav id="sidebar">
 <div class="sidebar-header">
  <h3><i class="bi bi-box-seam me-1"></i> Warehouse</h3>
</div>
<ul class="sidebar-nav list-unstyled">
 <li class="nav-item">
  <a href="admin_dashboard.php" class="nav-link" target="_blank">
  <i class="bi bi-speedometer2"></i><span class="nav-text">Dashboard</span></a>
</li>
<li class="nav-item">
  <a href="admin_products.php" class="nav-link" target="_blank">
  <i class="bi bi-box-seam"></i><span class="nav-text">Manage Products</span></a>
</li>
<li class="nav-item">
  <a href="admin_suppliers.php" class="nav-link" target="_blank">
  <i class="bi bi-truck"></i><span class="nav-text">Manage Suppliers</span></a>
</li>
<li class="nav-item">
  <a href="admin_locations.php" class="nav-link" target="_blank">
  <i class="bi bi-geo-alt"></i><span class="nav-text">Manage Locations</span></a>
</li>
<li class="nav-item">
  <a href="admin_inventory.php" class="nav-link" target="_blank">
  <i class="bi bi-clipboard-data"></i><span class="nav-text">Manage Inventory</span></a>
</li>
<li class="nav-item">
  <a href="admin_stock_movements.php" class="nav-link" target="_blank">
  <i class="bi bi-arrow-left-right"></i><span class="nav-text">Manage Stock Movements</span></a>
</li>
<li class="nav-item">
  <a href="admin_location_history.php" class="nav-link" target="_blank">
  <i class="bi bi-clock-history"></i><span class="nav-text">Manage Locations History</span></a>
</li>
<li class="nav-item">
  <a href="messages.php" class="nav-link" target="_blank">
  <i class="bi bi-chat-dots"></i><span class="nav-text">Manage Messages</span></a>
</li>
<li class="nav-item">
  <a href="admin_users.php" class="nav-link" target="_blank">
  <i class="bi bi-people"></i><span class="nav-text">Manage Users</span></a>
</li>
<li class="nav-item">
  <a href="logout.php" class="nav-link" target="_blank">
  <i class="bi bi-box-arrow-right"></i><span class="nav-text">Logout</span></a>
</li>
</ul>
</nav>

<!-- Top Navigation -->
<div class="top-navbar">
 <!-- Hamburger Icon -->
<div class="hamburger" id="sidebarToggle">
  <i class="bi bi-list"></i>
</div>

<!-- User Info -->
<div class="user-info d-flex align-items-center gap-2">
  <i class="bi bi-person-circle"></i>
  <a href="logout.php" class="text-white text-decoration-none">
    <?= htmlspecialchars($username) ?>
  </a>
</div>
</div>

<!-- Main Content -->
 <div class="main-content">
<div class="page-header">
  <h1 class="page-title">Manager Dashboard</h1>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
<a href="products.php" class="stat-card card-pink" target="_blank">
  <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
  <div class="stat-label">Products</div>
  <div class="stat-number"><?= $productCount; ?></div>
</a>

<a href="suppliers.php" class="stat-card card-teal" target="_blank">
  <div class="stat-icon"><i class="bi bi-truck"></i></div>
  <div class="stat-label">Suppliers</div>
  <div class="stat-number"><?= $supplierCount; ?></div>
</a>

<a href="locations.php" class="stat-card card-cyan" target="_blank">
  <div class="stat-icon"><i class="bi bi-geo-alt-fill"></i></div>
  <div class="stat-label">Locations</div>
  <div class="stat-number"><?= $locationCount; ?></div>
</a>

<a href="inventory.php" class="stat-card card-green" target="_blank">
  <div class="stat-icon"><i class="bi bi-kanban-fill"></i></div>
  <div class="stat-label">Inventory Items</div>
  <div class="stat-number"><?= $inventoryCount; ?></div>
</a>

<a href="stock_movements.php" class="stat-card card-orange" target="_blank">
  <div class="stat-icon"><i class="bi bi-arrow-left-right"></i></div>
  <div class="stat-label">Stock Movements</div>
  <div class="stat-number"><?= $stockMovementsCount; ?></div>
</a>

<a href="admin_location_history.php" class="stat-card card-purple" target="_blank">
  <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
  <div class="stat-label">Location History</div>
  <div class="stat-number"><?= $locationHistoryCount; ?></div>
</a>

<a href="messages.php" class="stat-card card-red" target="_blank">
  <div class="stat-icon"><i class="bi bi-chat-dots-fill"></i></div>
  <div class="stat-label">Messages</div>
  <div class="stat-number"><?= $messagesCount; ?></div>
</a>

<a href="users.php" class="stat-card card-blue" target="_blank">
  <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
  <div class="stat-label">Users</div>
  <div class="stat-number"><?= $usersCount; ?></div>
</a>
</div>

<!-- Charts Section -->
<div class="charts-section">
 <div class="charts-header">
  <h1 class="page-title">Carts</h1>
   <p class="page-description"> Overview of key metrics including stock movements, inventory distribution, and user roles.</p>
 </div>
</div>

<!-- Charts Section -->
<div class="charts-section">
 <div class="chart-container">
  <h3 class="chart-title">Stock Movements (Last 6 Months)</h3>
<div class="chart-canvas">
  <canvas id="stockMovementsChart"></canvas>
</div>
</div>

<div class="chart-container">
  <h3 class="chart-title">Inventory by Location</h3>
<div class="chart-canvas">
  <canvas id="inventoryChart"></canvas>
</div>
</div>

<div class="chart-container">
  <h3 class="chart-title">Users by Role</h3>
<div class="chart-canvas">
  <canvas id="usersChart"></canvas>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fixed hamburger toggle functionality
document.getElementById('sidebarToggle').addEventListener('click', function () {
  document.body.classList.toggle('sidebar-collapsed');
});

// Chart.js configuration and initialization
 Chart.defaults.font.family = 'Inter, sans-serif';
 Chart.defaults.color = '#64748b';

// Stock Movements Chart
const stockMovementsCtx = document.getElementById('stockMovementsChart').getContext('2d');
const stockMovementsChart = new Chart(stockMovementsCtx, {
 type: 'bar',
  data: {
   labels: <?= json_encode($stockMovementLabels) ?>,
    datasets: [{
     label: 'Stock Movements',
     data: <?= json_encode($stockMovementData) ?>,
     backgroundColor: 'rgba(102, 126, 234, 0.8)',
     borderColor: 'rgba(102, 126, 234, 1)',
     borderWidth: 2,
     borderRadius: 8,
     borderSkipped: false,
}]
},
options: {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
   legend: {
    display: false
},
tooltip: {
  backgroundColor: 'rgba(0, 0, 0, 0.8)',
  titleColor: 'white',
  bodyColor: 'white',
  cornerRadius: 8,
 }
},
scales: {
 y: {
  beginAtZero: true,
  grid: {
   color: 'rgba(0, 0, 0, 0.05)',
},
ticks: {
  stepSize: 1
}
},
x: {
  grid: {
   display: false
    }
   }
  } 
 }
});


// Inventory by Location Chart
const inventoryCtx = document.getElementById('inventoryChart').getContext('2d');
const inventoryChart = new Chart(inventoryCtx, {
 type: 'doughnut',
  data: {
   labels: <?= json_encode($inventoryLabels) ?>,
    datasets: [{
     data: <?= json_encode($inventoryData) ?>,
      backgroundColor: [
       'rgba(102, 126, 234, 0.8)',
       'rgba(245, 87, 108, 0.8)',
       'rgba(79, 172, 254, 0.8)',
       'rgba(67, 233, 123, 0.8)',
       'rgba(250, 112, 154, 0.8)',
       'rgba(168, 237, 234, 0.8)',
       'rgba(255, 154, 158, 0.8)',
       'rgba(161, 196, 253, 0.8)'
],
 borderWidth: 0,
 hoverOffset: 4
 }]
},
options: {
 responsive: true,
 maintainAspectRatio: false,
 cutout: '60%',
 plugins: {
  legend: {
   position: 'bottom',
    labels: {
    usePointStyle: true,
    padding: 20,
    font: {
    size: 12
  }
 }
},
tooltip: {
 backgroundColor: 'rgba(0, 0, 0, 0.8)',
 titleColor: 'white',
 bodyColor: 'white',
 cornerRadius: 8,
   }
  }
 }
});

// Users by Role Chart
const usersCtx = document.getElementById('usersChart').getContext('2d');
const usersChart = new Chart(usersCtx, {
 type: 'pie',
 data: {
  labels: <?= json_encode($userRoleLabels) ?>,
  datasets: [{
   data: <?= json_encode($userRoleCounts) ?>,
   backgroundColor: [
    'rgba(102, 126, 234, 0.8)',
    'rgba(245, 87, 108, 0.8)',
    'rgba(67, 233, 123, 0.8)',
    'rgba(250, 112, 154, 0.8)',
    'rgba(168, 237, 234, 0.8)'
],
 borderWidth: 0,
 hoverOffset: 4
 }]
},
options: {
 responsive: true,
 maintainAspectRatio: false,
 plugins: {
  legend: {
  position: 'bottom',
  labels: {
   usePointStyle: true,
   padding: 20,
   font: {
    size: 12
  }
 }
},
tooltip: {
 backgroundColor: 'rgba(0, 0, 0, 0.8)',
 titleColor: 'white',
 bodyColor: 'white',
 cornerRadius: 8,
   }
  }
 }
});

// Add smooth animations
document.addEventListener('DOMContentLoaded', function () {
// Animate stat cards
const statCards = document.querySelectorAll('.stat-card');
 statCards.forEach((card, index) => {
  card.style.opacity = '0';
  card.style.transform = 'translateY(20px)';
   setTimeout(() => {
    card.style.transition = 'all 0.6s ease';
    card.style.opacity = '1';
    card.style.transform = 'translateY(0)';
  }, index * 100);
});

// Animate chart containers
const chartContainers = document.querySelectorAll('.chart-container');
 chartContainers.forEach((container, index) => {
  container.style.opacity = '0';
  container.style.transform = 'translateY(30px)';
  setTimeout(() => {
   container.style.transition = 'all 0.6s ease';
   container.style.opacity = '1';
   container.style.transform = 'translateY(0)';
   }, 600 + index * 200);
  });
 });
</script>
</body>
</html>
