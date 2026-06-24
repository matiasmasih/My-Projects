<?php
session_start();
require 'config.php';

// Show errors for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Only admins allowed
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$history = [];
$location = null;

// Fetch all locations for dropdown
try {
    $locations = $pdo->query("SELECT id, name FROM locations ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if (isset($_GET['location_id']) && is_numeric($_GET['location_id'])) {
    $location_id = (int)$_GET['location_id'];

    // Fetch location name
    $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
    $stmt->execute([$location_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$location) {
        die('Location not found.');
    }

    // Fetch location history
    $stmt = $pdo->prepare("
        SELECT lh.*, u.username
        FROM location_history lh
        LEFT JOIN users u ON lh.user_id = u.id
        WHERE lh.location_id = ?
        ORDER BY lh.changed_at DESC
    ");
    $stmt->execute([$location_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Warehouse Admin - Location History</title>
  <link rel="icon" href="assets/favicon.ico" />
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <style>
    body {
      background-color: #f8f9fa;
      padding-top: 70px;
    }

    .navbar {
      background-color: #1e293b;
    }

    .navbar .navbar-brand, .navbar .nav-link {
      color: #fff !important;
    }

    .navbar .nav-link:hover {
      color: #adb5bd !important;
    }

/* Container and card */
.container {
  max-width: 1200px;
}

.card {
  border-radius: 16px;
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
  background-color: #fff;
  padding: 2rem;
}

/* Header */
.page-header {
  font-weight: 700;
  color: #1e293b;
  font-size: 1.8rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.custom-icon {
  color: #1690c4;
  font-size: 1.8rem;
}

/* Search form */
form[role="search"] {
  gap: 0.5rem;
}

form[role="search"] label {
  font-weight: 600;
  color: #334155;
  white-space: nowrap;
}

.form-select {
  max-width: 250px;
}

/* Table responsive container */
.table-responsive {
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(22, 144, 196, 0.15);
  overflow: hidden;
}

/* Table */
.table {
  border-collapse: separate !important;
  border-spacing: 0;
  width: 100%;
  font-size: 0.95rem;
  color: #334155;
}

.table thead {
  background-color: #1f2e3d !important;
  color: #ffffff !important;
}

.table thead th {
  background-color: #1f2e3d !important;
  color: #ffffff !important;
  padding: 0.85rem 1.2rem;
  font-weight: 700;
  text-transform: uppercase;
  border-bottom: 3px solid #1690c4;
  text-align: left;
}

.table tbody tr {
  background-color: #fff;
  transition: background-color 0.3s ease, color 0.3s ease;
}

.table tbody tr:hover {
  background-color: #bae6fd;
  color: #0c4a6e;
}

.table tbody td {
  padding: 0.75rem 1.2rem;
  vertical-align: middle;
  border-bottom: 1px solid #e0e7ff;
}

.table tbody tr:last-child td {
  border-bottom: none;
}

/* Monospace font for Changed At */
.table tbody td:nth-child(4) {
  font-family: 'Courier New', Courier, monospace;
  font-weight: 600;
  color: #475569;
  white-space: nowrap;
}

/* Badges for Action */
.badge-action {
  display: inline-block;
  padding: 0.3em 0.7em;
  font-size: 0.85rem;
  font-weight: 600;
  border-radius: 12px;
  color: #fff;
  text-transform: capitalize;
  user-select: none;
}

.badge-action.create {
  background-color: #22c55e; /* green */
}

.badge-action.update {
  background-color: #f59e0b; /* amber */
}

.badge-action.delete {
  background-color: #ef4444; /* red */
}

.badge-action.other {
  background-color: #3b82f6; /* blue */
}

/* Responsive */
@media (max-width: 767.98px) {
  .page-header {
    font-size: 1.5rem;
  }
  .form-select {
    max-width: 180px;
  }
  .table thead th, .table tbody td {
    padding: 0.5rem 0.8rem;
  }
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-white" href="admin_dashboard.php">📦 WAREHOUSE</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarWarehouse">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarWarehouse">
      <ul class="navbar-nav ms-auto gap-1">
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'admin_dashboard.php' ? 'active' : '' ?>" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'admin_products.php' ? 'active' : '' ?>" href="admin_products.php"><i class="bi bi-boxes"></i> Products</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'admin_suppliers.php' ? 'active' : '' ?>" href="admin_suppliers.php"><i class="bi bi-truck"></i> Suppliers</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'admin_locations.php' ? 'active' : '' ?>" href="admin_locations.php"><i class="bi bi-geo-alt"></i> Locations</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'admin_inventory.php' ? 'active' : '' ?>" href="admin_inventory.php"><i class="fas fa-warehouse"></i> Inventory</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'admin_stock_movements.php' ? 'active' : '' ?>" href="admin_stock_movements.php"><i class="fas fa-exchange-alt"></i> Stock Movement</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'admin_location_history.php' ? 'active' : '' ?>" href="admin_location_history.php"><i class="bi bi-clock-history"></i> Location History</a></li>
        <li class="nav-item"><a class="nav-link <?= $currentPage === 'admin_users.php' ? 'active' : '' ?>" href="admin_users.php"><i class="bi bi-people"></i> Users</a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-4">
  <div class="card p-4 shadow-sm">
    <!-- Header Row: Title + Search -->
    <div class="row align-items-center mb-4">
      <div class="col-md-6">
        <h2 class="page-header">
          <i class="bi bi-clock-history custom-icon"></i> Admin Location History
        </h2>
      </div>
      <div class="col-md-6">
        <form method="GET" class="d-flex justify-content-md-end align-items-center" role="search" aria-label="Search location history">
          <label for="location_id" class="me-2 fw-semibold mb-0">Search:</label>
          <select name="location_id" id="location_id" class="form-select form-select-sm me-2" required>
            <option value="" disabled selected>-- Select location --</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?= htmlspecialchars($loc['id']) ?>" <?= (isset($_GET['location_id']) && $_GET['location_id'] == $loc['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($loc['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary btn-sm" aria-label="Search">
            <i class="bi bi-search"></i> Search
          </button>
        </form>
      </div>
    </div>

    <!-- History Table or Message -->
    <?php if ($location): ?>
      <h6 class="mb-3">
        Showing history for: <strong><?= htmlspecialchars($location['name']) ?></strong>
      </h6>

      <?php if (count($history) > 0): ?>
        <div class="table-responsive">
          <table class="table table-striped table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>Action</th>
                <th>Changes</th>
                <th>User</th>
                <th>Changed At</th>
              </tr>
            </thead>
          <tbody>
            <?php foreach ($history as $item): ?>
              <tr>
                <td><?= htmlspecialchars($item['action']) ?></td>
                <td><?= nl2br(htmlspecialchars($item['changes'])) ?></td>
                <td><?= htmlspecialchars($item['username'] ?? 'Unknown') ?></td>
                <td><?= htmlspecialchars($item['changed_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="alert alert-info">No history records available for this location.</div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert alert-warning">Please select a location to view its history.</div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
