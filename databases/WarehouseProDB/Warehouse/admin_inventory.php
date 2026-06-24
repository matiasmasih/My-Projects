<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Updated SQL to LEFT JOIN locations table to get location name if available
$sql = "SELECT
  p.id, p.name, p.category, p.sku, p.barcode,
  p.description, p.unit_price, p.stock,
  l.address AS location_name,
  p.created_at, p.updated_at
FROM products p
LEFT JOIN locations l ON p.location_id = l.id";

try {
    $stmt = $pdo->query($sql);
    $inventory = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching inventory: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Inventory - Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons CSS (for navbar icons) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Font Awesome CSS (for table icons) -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <!-- Custom CSS -->
<style>
body {
  background-color: #f4f7fc;
  padding-top: 70px;
  font-family: 'Roboto', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Navbar (unchanged) */
.navbar {
  background-color: #1e293b;
}

.navbar .navbar-brand, .navbar .nav-link {
  color: #fff !important;
}

.navbar .nav-link:hover {
  color: #adb5bd !important;
}

.navbar-toggler {
  border-color: rgba(255, 255, 255, 0.3);
}

/* Custom Margin for Gap */
.mb-10 {
  margin-bottom: 30px !important; /* Ensure 4rem (~64px) gap */
}

.custom-icon {
  color: #1690c4; /* Your preferred HEX color */
}

/* Container */
.table-container {
  background-color: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  overflow-x: auto;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.07);
  margin: 2rem auto;
  padding: 1.8rem;
  transition: box-shadow 0.3s ease, transform 0.3s ease;
  width: 100%;
}

.table-container h2 {
  font-size: 3rem; /* Try 3rem or more for bigger text */
  font-weight: 800;
  margin-bottom: 2rem;
  color: #0f172a;
}

.table-container {
  border-radius: 5px; /* Optional: rounds the container */
}

.table {
  border-radius: 5px;
  overflow: hidden; /* Ensures corners stay rounded */
  border-collapse: separate; /* Important for border-radius */
  border-spacing: 0; /* Removes spacing so corners look clean */
  width: 100%;
  table-layout: auto;
  font-size: 0.9rem;
  color: #1e293b;
}

/* Rounded corners for header */
.table thead th:first-child {
  border-top-left-radius: 14px;
}

.table thead th:last-child {
  border-top-right-radius: 14px;
}

/* Rounded corners for last row */
.table tbody tr:last-child td:first-child {
  border-bottom-left-radius: 14px;
}

.table tbody tr:last-child td:last-child {
  border-bottom-right-radius: 14px;
}

/* Head Styling */
.table thead th {
  background-color: #1e293b;
  color: #ffffff;
  padding: 0.6rem 1rem;
  text-align: center;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
}

/* Table Cells */
.table td {
  padding: 0.6rem 1rem;
  text-align: center;
  border-top: 1px solid #e5e7eb;
  white-space: nowrap;
}

/* Hover Highlight */
.table tbody tr:hover {
  background-color: #eff6ff;
}

/* Badge Styles */
.badge {
  display: inline-block;
  padding: 0.3em 0.7em;
  font-size: 0.8em;
  font-weight: 500;
  border-radius: 10px;
  background-color: #f97316;
  color: #fff;
}

/* Button */
.btn-sm {
  padding: 0.4rem 0.8rem;
  font-size: 0.85rem;
  background-color: #1d4ed8;
  color: #ffffff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none; /* Removes underline */
}

.btn-sm:hover {
  background-color: #1e40af;
  transform: scale(1.05);
}

/* Responsive */
@media (max-width: 768px) {
  .table-container {
    padding: 1rem;
  }

  .table thead {
    display: none;
  }

  .table tbody td {
    display: block;
    text-align: left;
    position: relative;
    padding-left: 45%;
    font-size: 0.85rem;
  }

  .table tbody td::before {
    content: attr(data-label);
    position: absolute;
    left: 1rem;
    top: 0.5rem;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 0.8rem;
  }

  .btn-sm {
    width: 100%;
  }
}
</style>
</head>
<body>
  <!-- Navbar (unchanged, restored exactly as provided) -->
  <nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold text-white" href="admin_dashboard.php">📦 WAREHOUSE</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarWarehouse">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarWarehouse">
        <ul class="navbar-nav ms-auto gap-1">
          <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="admin_products.php"><i class="bi bi-boxes"></i> Products</a></li>
          <li class="nav-item"><a class="nav-link" href="admin_suppliers.php"><i class="bi bi-truck"></i> Suppliers</a></li>
          <li class="nav-item"><a class="nav-link" href="admin_locations.php"><i class="bi bi-geo-alt"></i> Locations</a></li>
          <li class="nav-item"><a class="nav-link" href="admin_inventory.php"><i class="fas fa-warehouse"></i> Inventory</a></li>
          <li class="nav-item"><a class="nav-link" href="admin_stock_movements.php"><i class="fas fa-exchange-alt"></i> Stock Movement</a></li>
          <li class="nav-item"><a class="nav-link active" href="admin_location_history.php"><i class="bi bi-clock-history"></i> Location History</a></li>
          <li class="nav-item"><a class="nav-link" href="admin_users.php"><i class="bi bi-people"></i> Users</a></li>
          <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Table Section -->
<div class="table-container">
 <h2 class="mb-10" style="font-size: 2rem;"><i class="fas fa-warehouse me-2" style="color: #1690c4;"></i>Inventory Overview</h2>
  <div class="table-responsive">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>ID</th>
          <th>Product</th>
          <th>Category</th>
          <th>Serial</th>
          <th>Description</th>
          <th>Unit Price</th>
          <th>Stock</th>
          <th>Status</th>
          <th>Location</th>
          <th>Created</th>
          <th>Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($inventory)): ?>
          <?php foreach ($inventory as $item): ?>
            <tr>
              <td data-label="ID"><?= htmlspecialchars($item['id']) ?></td>
              <td data-label="Product"><?= htmlspecialchars($item['name']) ?></td>
              <td data-label="Category"><?= htmlspecialchars($item['category'] ?? 'Unassigned') ?></td>
              <td data-label="Serial"><?= htmlspecialchars($item['sku'] ?? 'N/A') ?></td>
              <td data-label="Description"><?= htmlspecialchars($item['description']) ?></td>
              <td data-label="Unit Price">$<?= number_format((float)$item['unit_price'], 2) ?></td>
              <td data-label="Stock"><?= (int)$item['stock'] ?></td>
              <td data-label="Status">
                <?= (int)$item['stock'] > 0
                  ? '<span class="badge">In Stock</span>'
                  : '<span class="badge" style="background-color:#dc2626;">Out of Stock</span>' ?>
              </td>
              <td data-label="Location"><?= htmlspecialchars($item['location_name'] ?? 'Unassigned') ?></td>
              <td data-label="Created"><?= date('Y-m-d', strtotime($item['created_at'])) ?></td>
              <td data-label="Updated"><?= date('Y-m-d', strtotime($item['updated_at'])) ?></td>
              <td data-label="Actions">
                <a href="update_product.php?id=<?= urlencode($item['id']) ?>" class="btn-sm">
                  <i class="fas fa-edit me-1"></i>Update
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="12" class="text-muted text-center">No inventory data available.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

