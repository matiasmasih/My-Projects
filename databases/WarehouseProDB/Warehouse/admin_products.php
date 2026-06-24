<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']); // e.g., "admin_products.php"

// Use a SQL JOIN query to get category_name and supplier_name
$sql = "SELECT
            p.*,
            c.name AS category_name,
            s.name AS supplier_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        ORDER BY p.id DESC";

$products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin - Products</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

<style>
body {
  min-height: 100vh;
  background: #f5f7fa;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  overflow-x: hidden;
  padding-top: 80px;
}

.navbar {
  background-color: #1e293b;
}

.navbar .nav-link, .navbar .navbar-brand {
  color: #fff !important;
}

.navbar .nav-link:hover {
  color: #adb5bd !important;
}

.table-container {
  background-color: white;
  border-radius: 10px;
  box-shadow: 0 0 12px rgba(0, 0, 0, 0.08);
  overflow: hidden;
  padding: 1rem 1.5rem 1.5rem;
}

.table-header {
  margin-bottom: 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
}

.table-header h4 {
  color: #1e293b;
  margin: 0;
  font-size: 2rem;
  font-weight: 600;
}

.table-header .btn {
  background-color: #00bfff;
  color: white;
  font-weight: 500;
  border-radius: 8px;
  transition: 0.3s ease;
  padding: 0.4rem 0.8rem;
  font-size: 1rem;
}

.table-header .btn:hover {
  background-color: #009acd;
  color: white;
}

.table {
  border-collapse: separate;
  border-spacing: 0 10px;
  width: 100%;
}

.table thead {
  background-color: #1e2d3b !important;
}

.table thead th {
  background-color: #1e2d3b !important;
  color: white !important;
  border: none;
  padding: 0.75rem;
  font-size: 0.95rem;
}

.table thead th:first-child {
  border-top-left-radius: 10px;
}

.table thead th:last-child {
  border-top-right-radius: 10px;
}

.table tbody tr {
  background-color: #182938;
  color: white;
}

.table tbody tr td:first-child {
  border-top-left-radius: 10px;
  border-bottom-left-radius: 10px;
}

.table tbody tr td:last-child {
  border-top-right-radius: 10px;
  border-bottom-right-radius: 10px;
}

.table td,
.table th {
  vertical-align: middle;
  padding: 0.75rem;
  font-size: 0.95rem;
  border: none !important;
}

.btn i {
  margin-right: 4px;
  vertical-align: middle;
}

@media (max-width: 768px) {
  .table-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }

  .table {
    font-size: 0.9rem;
  }

  .table th,
  .table td {
    padding: 0.5rem;
  }
}

</style>
</head>
<body>

<nav class="navbar navbar-expand-lg fixed-top shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-white" href="admin_dashboard.php">📦 WAREHOUSE</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarWarehouse">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarWarehouse">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-1">
        <li class="nav-item">
          <a class="nav-link <?php if ($currentPage === 'admin_dashboard.php') echo 'active'; ?>" href="admin_dashboard.php">
            <i class="fas fa-chart-line nav-icon"></i> Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if ($currentPage === 'admin_products.php') echo 'active'; ?>" href="admin_products.php">
            <i class="fas fa-boxes nav-icon"></i> Products
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if ($currentPage === 'admin_suppliers.php') echo 'active'; ?>" href="admin_suppliers.php">
            <i class="fas fa-truck nav-icon"></i> Suppliers
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if ($currentPage === 'admin_locations.php') echo 'active'; ?>" href="admin_locations.php">
            <i class="fas fa-map-marker-alt nav-icon"></i> Locations
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if ($currentPage === 'admin_inventory.php') echo 'active'; ?>" href="admin_inventory.php">
            <i class="fas fa-warehouse nav-icon"></i> Inventory
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if ($currentPage === 'admin_stock_movements.php') echo 'active'; ?>" href="admin_stock_movements.php">
            <i class="bi bi-arrow-left-right"></i> Stock Movements
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if ($currentPage === 'admin_location_history.php') echo 'active'; ?>" href="admin_location_history.php">
            <i class="bi bi-clock-history"></i> Location History
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php if ($currentPage === 'admin_users.php') echo 'active'; ?>" href="admin_users.php">
            <i class="bi bi-people nav-icon"></i> Users
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-danger" href="logout.php">
            <i class="fas fa-sign-out-alt nav-icon"></i> Logout
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Table box with header inside -->
<div class="table-container mt-4">
  <div class="table-header">
    <h4>📦 Products</h4>
    <a href="admin_add_product.php" class="btn">
      <i class="bi bi-plus-circle-fill"></i> Add Product
    </a>
  </div>

  <div class="table-responsive">
    <table class="table mb-0">
      <thead>
        <tr>
          <th style="width: 5%;">ID</th>
          <th style="width: 15%;">Name</th>
          <th style="width: 20%;">Description</th>
          <th style="width: 10%;">Category</th>
          <th style="width: 10%;">Stock</th>
          <th style="width: 10%;">Price</th>
          <th style="width: 10%;">Supplier</th>
          <th style="width: 10%;">Created At</th>
          <th style="width: 10%;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['id']) ?></td>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td><?= htmlspecialchars($p['description']) ?></td>
            <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
            <td><?= isset($p['stock']) ? (int)$p['stock'] : '-' ?></td>
            <td><?= isset($p['unit_price']) ? '$' . number_format($p['unit_price'], 2) : 'N/A' ?></td>
            <td><?= htmlspecialchars($p['supplier_name'] ?? '-') ?></td>
            <td><?= date('Y-m-d', strtotime($p['created_at'])) ?></td>
            <td>
              <a href="admin_edit_product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
              <a href="admin_delete_product.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete product?')">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
