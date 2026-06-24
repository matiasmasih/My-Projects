<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    echo "Invalid location ID.";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
$stmt->execute([$id]);
$location = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$location) {
    echo "Location not found.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $address = $_POST['address'] ?? '';
    $description = $_POST['description'] ?? '';

    $update = $pdo->prepare("UPDATE locations SET name = ?, address = ?, description = ? WHERE id = ?");
    $update->execute([$name, $address, $description, $id]);

    header('Location: admin_locations.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Location</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8fafc;
      padding-top: 70px;
    }
    .navbar {
      background-color: #1e293b;
    }
    .navbar .nav-link,
    .navbar .navbar-brand {
      color: white !important;
    }
    .navbar .nav-link:hover {
      color: #cbd5e1 !important;
    }
    .form-container {
      max-width: 600px;
      margin: auto;
      background: white;
      border-radius: 12px;
      box-shadow: 0 0 10px rgba(0,0,0,0.08);
      padding: 30px;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="admin_dashboard.php">📦 WAREHOUSE</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarWarehouse">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarWarehouse">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-3">
        <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_products.php"><i class="fas fa-boxes"></i> Products</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_suppliers.php"><i class="fas fa-truck"></i> Suppliers</a></li>
        <li class="nav-item"><a class="nav-link active" href="admin_locations.php"><i class="fas fa-map-marker-alt"></i> Locations</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_inventory.php"><i class="fas fa-warehouse"></i> Inventory</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_low_stock.php"><i class="fas fa-exclamation-triangle"></i> Low Stock</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Form Section -->
<div class="container mt-5">
  <div class="form-container">
    <h4 class="mb-4"><i class="fas fa-pen-to-square"></i> Edit Location</h4>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($location['name'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Address</label>
        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($location['address'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control"><?= htmlspecialchars($location['description'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Location</button>
      <a href="admin_locations.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Cancel</a>
    </form>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
