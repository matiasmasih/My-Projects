<?php
require 'config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if product ID is set and valid
if (!isset($_GET['id']) || empty($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Product ID is required and must be a number.");
}

$id = (int)$_GET['id'];

// Fetch product data
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Product not found.");
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $unit_price = $_POST['unit_price'] ?? 0;
    $stock = $_POST['stock'] ?? 0;
    $location = trim($_POST['location'] ?? '');

    // Basic validation example
    if ($name === '' || $sku === '') {
        $error = "Name and SKU are required.";
    } else {
        $sql = "UPDATE products SET
            name = :name,
            category = :category,
            sku = :sku,
            barcode = :barcode,
            description = :description,
            unit_price = :unit_price,
            stock = :stock,
            location = :location,
            updated_at = NOW()
            WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        try {
            $stmt->execute([
                ':name' => $name,
                ':category' => $category,
                ':sku' => $sku,
                ':barcode' => $barcode,
                ':description' => $description,
                ':unit_price' => $unit_price,
                ':stock' => $stock,
                ':location' => $location,
                ':id' => $id,
            ]);
            $success = "Product updated successfully.";
            // Refresh product data after update
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Update Product</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome for icons -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
  background-color: #f2f4f8;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  padding-top: 70px; /* space for fixed navbar */
}

    /* Navbar styling */
.navbar {
  background-color: #007bff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Brand styling */
.navbar .navbar-brand {
  color: #fff;
  font-weight: 700;
  font-size: 1.4rem;
  text-decoration: none;
}

/* No hover effect on brand */
.navbar .navbar-brand:hover {
  color: #fff;
}

/* Navbar links */
.navbar-nav .nav-link {
  color: #dbe9ff;
  font-weight: 500;
  text-decoration: none;
  padding: 0.5rem 1rem;
}

/* No hover or active effects */
.navbar-nav .nav-link:hover,
.navbar-nav .nav-link.active {
  color: #dbe9ff;
  font-weight: 500;
}

/* Hamburger menu icon color */
.navbar-toggler {
  border-color: rgba(255, 255, 255, 0.7);
}

.navbar-toggler-icon {
  background-image: url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba(255, 255, 255, 1)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E");
}

    /* Form container */
.form-container {
  max-width: 900px;
  margin: auto;
  padding: 30px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
}

h2 {
  font-weight: 600;
  color: #007bff;
}

.form-label {
  font-weight: 500;
  color: #333;
}

.form-control {
  border-radius: 10px;
  padding: 10px 15px;
}

textarea {
  resize: none;
}

.btn-primary {
  padding: 10px 30px;
  border-radius: 12px;
}

.btn-outline-secondary {
  padding: 10px 30px;
  border-radius: 12px;
}

.alert {
  border-radius: 12px;
}

</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top" style="background-color: #1e293b;">
  <div class="container-fluid">
    <a class="navbar-brand" href="admin_dashboard.php">📦 WAREHOUSE</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarWarehouse" aria-controls="navbarWarehouse" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarWarehouse">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-1">
        <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_products.php"><i class="fas fa-boxes"></i> Products</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_suppliers.php"><i class="fas fa-truck"></i> Suppliers</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_locations.php"><i class="fas fa-map-marker-alt"></i> Locations</a></li>
        <li class="nav-item"><a class="nav-link active" href="admin_inventory.php"><i class="fas fa-warehouse"></i> Inventory</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_stock_movements.php"><i class="fas fa-exchange-alt"></i> Stock Movement </a></li>
        <li class="nav-item"><a class="nav-link" href="admin_location_history.php"><i class="bi bi-clock-history"></i> Location History</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_users.php"><i class="bi bi-people"></i> Users</a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container form-container">
    <h2 class="text-center mb-4">🛠️ Update Product</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($product): ?>
    <form method="POST" class="needs-validation" novalidate>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Product Name</label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($product['name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Category</label>
                <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($product['category'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Barcode</label>
                <input type="text" name="barcode" class="form-control" value="<?= htmlspecialchars($product['barcode'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Unit Price (€)</label>
                <input type="number" name="unit_price" step="0.01" class="form-control" value="<?= htmlspecialchars($product['unit_price'] ?? 0) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Stock</label>
                <input type="number" name="stock" class="form-control" value="<?= htmlspecialchars($product['stock'] ?? 0) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Locations</label>
                <input type="text" name="locations" class="form-control" value="<?= htmlspecialchars($product['locations'] ?? 0) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-4">
          <button type="submit" class="btn btn-primary">💾 Update</button>
         <a href="admin_products.php" class="btn btn-outline-secondary">↩️ Back</a>
    </div>
    </form>
    <?php endif; ?>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
