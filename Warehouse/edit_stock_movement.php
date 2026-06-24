<?php
session_start();
require 'config.php';

// Show errors for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['id'])) {
  die("Invalid request. Movement ID missing.");
}

$id = (int) $_GET['id'];

// Fetch the stock movement
$stmt = $pdo->prepare("SELECT * FROM stock_movements WHERE id = ?");
$stmt->execute([$id]);
$movement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$movement) {
  die("Stock movement not found.");
}

// Fetch dropdown data
$products = $pdo->query("SELECT id, name FROM products")->fetchAll(PDO::FETCH_ASSOC);
$locations = $pdo->query("SELECT id, name FROM locations")->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT id, email AS name FROM users")->fetchAll(PDO::FETCH_ASSOC); // use email as 'name'

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Stock Movement</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
<style>
body {
  background: #FFFFFF;
  font-family: 'Segoe UI', sans-serif;
  padding-top: 70px;
}

.navbar {
  background-color: #1e293b;
}

.navbar .nav-link, .navbar-brand {
  color: #fff !important;
}

.navbar .nav-link:hover {
  color: #adb5bd !important;
}

.card {
  border: none;
  border-radius: 16px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.btn-custom {
  background-color: #1d3557;
  color: white;
  border: none;
}

.btn-custom:hover {
  background-color: #16324c;
  color: white;
}

.btn-cancel {
  background-color: #6c757d; /* grey color for cancel */
  color: white;
  border: none;
}

.btn-cancel:hover {
  background-color: #5a6268;
  color: white;
}

</style>
</head>
<body>

<nav class="navbar navbar-expand-lg fixed-top shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="admin_dashboard.php">📦 WAREHOUSE</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarWarehouse">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarWarehouse">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-1">
        <li class="nav-item"><a class="nav-link <?= ($currentPage === 'admin_dashboard.php') ? 'active' : '' ?>" href="admin_dashboard.php"><i class="fas fa-chart-line me-1"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?= ($currentPage === 'admin_products.php') ? 'active' : '' ?>" href="admin_products.php"><i class="fas fa-boxes me-1"></i> Products</a></li>
        <li class="nav-item"><a class="nav-link <?= ($currentPage === 'admin_suppliers.php') ? 'active' : '' ?>" href="admin_suppliers.php"><i class="fas fa-truck me-1"></i> Suppliers</a></li>
        <li class="nav-item"><a class="nav-link <?= ($currentPage === 'admin_locations.php') ? 'active' : '' ?>" href="admin_locations.php"><i class="fas fa-map-marker-alt me-1"></i> Locations</a></li>
        <li class="nav-item"><a class="nav-link <?= ($currentPage === 'admin_inventory.php') ? 'active' : '' ?>" href="admin_inventory.php"><i class="fas fa-warehouse me-1"></i> Inventory</a></li>
        <li class="nav-item"><a class="nav-link <?= ($currentPage === 'admin_stock_movements.php') ? 'active' : '' ?>" href="admin_stock_movements.php"><i class="bi bi-arrow-left-right me-1"></i> Stock Movements</a></li>
        <li class="nav-item"><a class="nav-link <?= ($currentPage === 'admin_location_history.php') ? 'active' : '' ?>" href="admin_location_history.php"><i class="bi bi-clock-history me-1"></i> Location History</a></li>
        <li class="nav-item"><a class="nav-link <?= ($currentPage === 'admin_users.php') ? 'active' : '' ?>" href="admin_users.php"><i class="bi bi-people me-1"></i> Users</a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <div class="card p-4 mt-4">
        <h4 class="mb-3"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Stock Movement</h4>

        <form action="update_stock_movement.php" method="POST">
          <input type="hidden" name="id" value="<?= $movement['id'] ?>">

          <div class="mb-3">
            <label for="product_id" class="form-label">Product</label>
            <select name="product_id" id="product_id" class="form-select" required>
              <option value="">-- Select Product --</option>
              <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $p['id'] == $movement['product_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($p['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="from_location" class="form-label">From Location</label>
              <select name="from_location" class="form-select" required>
                <option value="">-- Select --</option>
                <?php foreach ($locations as $loc): ?>
                  <option value="<?= $loc['id'] ?>" <?= $loc['id'] == $movement['from_location'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($loc['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="to_location" class="form-label">To Location</label>
              <select name="to_location" class="form-select" required>
                <option value="">-- Select --</option>
                <?php foreach ($locations as $loc): ?>
                  <option value="<?= $loc['id'] ?>" <?= $loc['id'] == $movement['to_location'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($loc['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="quantity" class="form-label">Quantity</label>
              <input type="number" class="form-control" name="quantity" min="1" required value="<?= htmlspecialchars($movement['quantity']) ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label for="movement_type" class="form-label">Movement Type</label>
              <select name="movement_type" class="form-select" required>
                <option value="">-- Select Type --</option>
                <option value="transfer" <?= $movement['movement_type'] == 'transfer' ? 'selected' : '' ?>>Transfer</option>
                <option value="adjustment" <?= $movement['movement_type'] == 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label for="reason" class="form-label">Reason</label>
            <input type="text" class="form-control" name="reason" value="<?= htmlspecialchars($movement['reason']) ?>">
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="user_id" class="form-label">User</label>
              <select name="user_id" class="form-select" required>
                <option value="">-- Select User --</option>
                <?php foreach ($users as $u): ?>
                  <option value="<?= $u['id'] ?>" <?= $u['id'] == $movement['user_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label for="timestamp" class="form-label">Date / Time</label>
              <input type="datetime-local" class="form-control" name="timestamp" value="<?= date('Y-m-d\TH:i', strtotime($movement['timestamp'])) ?>" required>
            </div>
          </div>
          <div class="d-flex justify-content-between mt-4">
            <a href="admin_stock_movements.php" class="btn btn-custom text-white"><i class="bi bi-arrow-left"></i> Cancel </a>
            <button type="submit" class="btn btn-custom text-white"><i class="bi bi-save"></i> Update
         </button>
         </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
