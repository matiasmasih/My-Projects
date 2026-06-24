<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$name = $address = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);

    if (empty($name)) {
        $errors[] = 'Location name is required.';
    }
    if (empty($address)) {
        $errors[] = 'Address is required.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO locations (name, address, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$name, $address]);
        $_SESSION['success'] = 'Location added successfully.';
        header('Location: admin_locations.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Location - Admin</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <!-- ✅ Working Font Awesome CDN (Free) -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body {
  background-color: #f8fafc;
  padding-top: 70px;
}

.navbar {
  background-color: #1e293b;
}

.navbar-brand, .nav-link {
  color: #fff !important;
}

.form-section {
  max-width: 600px;
  margin: 0 auto;
}

.navbar .nav-link {
  color: white !important;
}

.navbar .nav-link:hover {
  color: #9197a1 !important;
}

</style>
</head>
<body>

<!-- Main Top Navbar -->
<nav class="navbar navbar-expand-lg fixed-top shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-white" href="admin_dashboard.php">📦 WAREHOUSE</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarWarehouse" aria-controls="navbarWarehouse" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarWarehouse">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-1">
        <li class="nav-item">
          <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_products.php"><i class="fas fa-boxes"></i> Products</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_suppliers.php"><i class="fas fa-truck"></i> Suppliers</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="admin_locations.php"><i class="fas fa-map-marker-alt"></i> Locations</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_inventory.php"><i class="fas fa-warehouse"></i> Inventory</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="admin_stock_movements.php"><i class="fas fa-exchange-alt"></i> Stock Movement </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_location_history.php"><i class="bi bi-clock-history"></i> Location History</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_users.php"><i class="bi bi-people"></i> Users</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Main Content -->
<div class="container mt-5">
  <div class="form-section bg-white p-5 shadow rounded-4 border border-light-subtle">
    <h3 class="mb-4 text-primary fw-bold"><i class="bi bi-geo-alt-fill me-2"></i>Add New Location</h3>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
          <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="mb-4">
        <label for="name" class="form-label fw-semibold">📍 Location Name</label>
        <input type="text" class="form-control form-control-lg rounded-3" id="name" name="name"
               value="<?= htmlspecialchars($name) ?>" placeholder="e.g. Helsinki Central Warehouse" required>
      </div>

      <div class="mb-4">
        <label for="address" class="form-label fw-semibold">🏢 Location Address</label>
        <textarea class="form-control form-control-lg rounded-3" id="address" name="address"
                  rows="3" placeholder="e.g. Itäkatu 1, 00930 Helsinki" required><?= htmlspecialchars($address) ?></textarea>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-4">
        <button type="submit" class="btn btn-success btn-lg px-4">
          <i class="bi bi-check-circle me-1"></i> Save Location
        </button>
        <a href="admin_locations.php" class="btn btn-outline-secondary btn-lg">
          <i class="bi bi-arrow-right-circle ms-1"></i> Back
        </a>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
