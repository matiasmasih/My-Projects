<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/config.php';

// Initialize variables
$name = $sku = $barcode = $description = $unit_price = $weight = $dimensions = "";
$category_id = $supplier_id = null;
$errors = [];
$success = false;

// Fetch categories and suppliers
try {
    $stmtCat = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $stmtCat->fetchAll();

    $stmtSup = $pdo->query("SELECT id, name FROM suppliers ORDER BY name");
    $suppliers = $stmtSup->fetchAll();
} catch (Exception $e) {
    die("Error loading categories or suppliers: " . $e->getMessage());
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $supplier_id = $_POST['supplier_id'] !== '' ? (int)$_POST['supplier_id'] : null;
    $unit_price = $_POST['unit_price'] !== '' ? (float)$_POST['unit_price'] : null;
    $weight = $_POST['weight'] !== '' ? (float)$_POST['weight'] : null;
    $dimensions = trim($_POST['dimensions'] ?? '');

    if ($name === '') $errors[] = "Product name is required.";
    if ($sku === '') $errors[] = "SKU is required.";

    if (empty($errors)) {
        try {
            $sql = "INSERT INTO products
                (name, sku, barcode, description, category_id, supplier_id, unit_price, weight, dimensions)
                VALUES
                (:name, :sku, :barcode, :description, :category_id, :supplier_id, :unit_price, :weight, :dimensions)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':sku' => $sku,
                ':barcode' => $barcode ?: null,
                ':description' => $description ?: null,
                ':category_id' => $category_id,
                ':supplier_id' => $supplier_id,
                ':unit_price' => $unit_price,
                ':weight' => $weight,
                ':dimensions' => $dimensions ?: null,
            ]);
            $success = true;

            // Reset form
            $name = $sku = $barcode = $description = $unit_price = $weight = $dimensions = "";
            $category_id = $supplier_id = null;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Add Product - Admin</title>
  <!-- Bootstrap 5 + Font Awesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
  /* Body with padding to prevent navbar overlap */
  body {
    background: #f1f5f9;
    padding-top: 70px; /* Adjust if navbar height changes */
    margin: 0;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
  }

  /* Navbar background and height */
  .navbar {
    background-color: #1e293b;
    height: 70px; /* Slightly taller for better spacing */
    line-height: 70px; /* Vertically center inline content */
  }

  /* Navbar brand and nav links styling */
  .navbar-brand,
  .nav-link {
    color: #ffffff !important;
    font-weight: 600;
    font-size: 1rem;
    line-height: 1; /* Prevent extra vertical spacing */
  }

  .navbar .nav-link {
    color: white !important;
  }

  .navbar .nav-link:hover {
    color: #9197a1 !important;
  }

  /* Icon spacing */
  .nav-icon {
    margin-right: 6px;
    vertical-align: middle; /* Align icon with text */
  }

  /* Container styling */
  .container {
    max-width: 720px;
    margin: 20px auto 60px auto;
    background: #ffffff;
    padding: 30px 40px;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
  }

  /* Input focus styles */
  .form-control:focus {
    box-shadow: 0 0 8px rgba(13, 110, 253, 0.5);
    border-color: #0d6efd;
  }

  /* Headings spacing */
  h2 {
    margin-bottom: 30px;
  }

  /* Responsive fix: reduce navbar height on small screens */
  @media (max-width: 576px) {
    .navbar {
      height: auto;
      padding: 10px 15px;
      line-height: normal;
    }
    body {
      padding-top: 110px; /* Account for stacked navbar toggle */
    }
  }
</style>
</head>
<body>

<!-- 🔷 Modern Navbar -->
<nav class="navbar navbar-expand-lg fixed-top" style="background-color: #1e293b;">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-white" href="admin_dashboard.php">📦 WAREHOUSE</a>
    <button
      class="navbar-toggler"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#navbarWarehouse"
      aria-controls="navbarWarehouse"
      aria-expanded="false"
      aria-label="Toggle navigation"
    >
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarWarehouse">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-1">
        <li class="nav-item">
          <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-line nav-icon"></i>Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_products.php"><i class="fas fa-boxes nav-icon"></i>Products</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_suppliers.php"><i class="fas fa-truck nav-icon"></i>Suppliers</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_locations.php"><i class="fas fa-map-marker-alt nav-icon"></i>Locations</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#"><i class="fas fa-warehouse nav-icon"></i>Inventory</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="admin_stock_movements.php"><i class="fas fa-exchange-alt"></i> Stock Movement</a>
        </li>
         <li class="nav-item">
          <a class="nav-link" href="admin_location_history.php"><i class="bi bi-clock-history nav-icon"></i> Location History</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_users.php"><i class="bi bi-people nav-icon"></i>Users</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt nav-icon"></i>Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- 🧾 Add Product Form -->
<div class="container">
  <h2 class="text-primary">Add New Product</h2>

  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      Product added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <div class="mb-3">
      <label for="name" class="form-label fw-semibold">Product Name *</label>
      <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" class="form-control" required />
    </div>
    <div class="mb-3">
      <label for="sku" class="form-label fw-semibold">Stock Keeping Unit *</label>
      <input type="text" id="sku" name="sku" value="<?= htmlspecialchars($sku) ?>" class="form-control" required />
    </div>
    <div class="mb-3">
      <label for="barcode" class="form-label fw-semibold">Barcode</label>
      <input type="text" id="barcode" name="barcode" value="<?= htmlspecialchars($barcode) ?>" class="form-control" />
    </div>
    <div class="mb-3">
      <label for="description" class="form-label fw-semibold">Description</label>
      <textarea id="description" name="description" rows="3" class="form-control"><?= htmlspecialchars($description) ?></textarea>
    </div>
    <div class="mb-3">
      <label for="category_id" class="form-label fw-semibold">Category</label>
      <select id="category_id" name="category_id" class="form-select">
        <option value="">-- Select category --</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label for="supplier_id" class="form-label fw-semibold">Supplier</label>
      <select id="supplier_id" name="supplier_id" class="form-select">
        <option value="">-- Select supplier --</option>
        <?php foreach ($suppliers as $sup): ?>
          <option value="<?= $sup['id'] ?>" <?= $supplier_id == $sup['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($sup['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label for="unit_price" class="form-label fw-semibold">Unit Price</label>
      <input type="number" step="0.01" min="0" id="unit_price" name="unit_price" value="<?= htmlspecialchars($unit_price) ?>" class="form-control" />
    </div>
    <div class="mb-3">
      <label for="weight" class="form-label fw-semibold">Weight</label>
      <input type="number" step="0.01" min="0" id="weight" name="weight" value="<?= htmlspecialchars($weight) ?>" class="form-control" />
    </div>
    <div class="mb-3">
      <label for="dimensions" class="form-label fw-semibold">Dimensions</label>
      <input type="text" id="dimensions" name="dimensions" value="<?= htmlspecialchars($dimensions) ?>" class="form-control" />
    </div>
    <button type="submit" class="btn btn-primary w-100 fw-semibold">➕ Add Product</button>
  </form>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</body>
</html>
