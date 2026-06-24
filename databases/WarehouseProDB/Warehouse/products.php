<?php
session_start();
require 'config.php'; // your PDO connection in $pdo

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Fetch products with joins for category and supplier names
$sql = "SELECT p.id, p.name, p.description, c.name AS category_name, p.stock, p.unit_price AS price, s.name AS supplier_name, p.created_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Products - WarehousePro</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
<style>
body {
  background-color: #f1f5f9;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* === NAVBAR STYLE === */
.navbar-custom {
  background-color: #1e293b;
  height: 70px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
 }

.navbar-brand {
  font-size: 2rem;
  font-weight: 600;
  color: #ffffff !important;
  letter-spacing: 0;
}

.nav-link {
  font-size: 1rem;
  color: #ffffff !important;
  font-weight: 500;
  display: flex;
  align-items: center;
  transition: color 0.3s ease;
}

.nav-link:hover,
.nav-link.active {
  color: #94a3b8 !important;
}

/* Prevent navbar items from wrapping and shrinking */
.navbar-nav {
  white-space: nowrap;
}
.navbar-nav .nav-item {
  flex-shrink: 0;
}

/* === BUTTONS === */
.btn-outline-light {
  color: #e0e7ff;
  border-color: #4b5563;
  border-radius: 12px !important;
  cursor: pointer;
  display: flex;
  align-items: center;
  white-space: nowrap;
  font-size: 15px;
  line-height: 20px;
  transition: background-color 0.3s ease, color 0.3s ease;
 }

.btn-outline-light:hover {
  background-color: #00f5ff;
  color: #1f2937;
  border-color: #00f5ff;
}


/* USER INFO */
.user-info {
  font-weight: 600;
  font-size: 15px;
  color: #ffffff;
  white-space: nowrap;
  display: flex;
  align-items: center;
}

.user-info i {
  color: #3b82f6;
  font-size: 20px;
  vertical-align: middle;
  margin-right: 1px;
}

/* === MODERN TABLE STYLE ENHANCED === */
.table-wrapper {
  background: #ffffff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  overflow-x: auto;
}

.table-responsive {
  overflow-x: auto;
}

.table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 12px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  font-size: 0.9rem;
  color: #334155;
  min-width: 900px;
  table-layout: fixed;
}

.table thead th {
  background-color: #1e293b;
  color: #f1f5f9;
  font-weight: 600;
  padding: 14px 18px;
  text-align: left;
  border-top-left-radius: 12px;
  border-top-right-radius: 12px;
  position: sticky;
  top: 0;
  z-index: 1;
  white-space: nowrap;
}

.table thead th:last-child {
  border-top-right-radius: 12px;
}

.table tbody tr {
  background-color: #f9fafb;
  transition: all 0.3s ease;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  border-radius: 10px;
}

.table tbody tr:hover {
  background-color: #e0f2fe;
  box-shadow: 0 6px 16px rgba(0, 123, 255, 0.2);
  transform: translateY(-2px);
  cursor: pointer;
}

.table tbody td {
  padding: 14px 18px;
  vertical-align: middle;
  border-top: none;
  border-bottom: none;
  color: #475569;
  max-width: 200px;
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
}

/* Narrow the ID column */
.table thead th:first-child,
.table tbody td:first-child {
  width: 60px;
  max-width: 60px;
  white-space: nowrap;
  text-align: center;
}

/* Badge styles */
.badge {
  font-size: 0.8rem;
  padding: 0.4em 0.7em;
  border-radius: 20px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  user-select: none;
  display: inline-block;
  transition: background-color 0.3s ease;
}

.bg-success {
  background-color: #22c55e;
  color: #ffffff;
}

.bg-danger {
  background-color: #ef4444;
  color: #ffffff;
}

.bg-primary {
  background-color: #3b82f6;
  color: #ffffff;
}

/* Responsive tweaks */
@media (max-width: 768px) {
  .navbar-custom .nav-link {
    padding: 0.4rem 0.75rem;
    font-size: 0.9rem;
  }

  .user-info {
    font-size: 0.85rem;
  }

  .table-wrapper {
    padding: 15px;
  }

  .table {
    min-width: 600px;
    font-size: 0.85rem;
  }

  .table thead th,
  .table tbody td {
    padding: 10px 12px;
  }

  .table tbody td {
    white-space: normal;
  }
}

</style>
</head>
<body>

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-custom">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">
      <i class="bi bi-warehouse me-2"></i> WarehousePro
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
      <span class="navbar-toggler-icon me-1"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="mainNavbar">
      <ul class="navbar-nav mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? ' active' : '' ?>" href="dashboard.php">
            <i class="bi bi-speedometer2 me-1"></i><span>Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? ' active' : '' ?>" href="products.php">
            <i class="bi bi-box-seam me-1"></i><span>Products</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? ' active' : '' ?>" href="categories.php">
            <i class="bi bi-tags me-1"></i><span>Categories</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? ' active' : '' ?>" href="suppliers.php">
            <i class="bi bi-truck me-1"></i><span>Suppliers</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'locations.php' ? ' active' : '' ?>" href="locations.php">
            <i class="bi bi-geo-alt me-1"></i><span>Locations</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? ' active' : '' ?>" href="inventory.php">
            <i class="bi bi-stack me-1"></i><span>Inventory</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'stock_movements.php' ? ' active' : '' ?>" href="stock_movements.php">
            <i class="bi bi-arrow-left-right me-1"></i><span>Stock Movements</span>
          </a>
        </li>
         <?php if ($role === 'admin' || $role === 'staff'): ?>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? ' active' : '' ?>" href="users.php">
            <i class="bi bi-people me-1"></i><span>Users</span>
          </a>
        </li>
        <?php endif; ?>
      </ul>

      <div class="d-flex align-items-center ms-3">
        <a href="logout.php" class="btn btn-outline-light btn-sm me-1">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
        <div class="user-info d-flex align-items-center">
          <i class="bi bi-person-circle me-1"></i>
          <span><?= htmlspecialchars($username) ?></span>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- Main content -->
<div class="table-wrapper max-4 mt-4">
  <h2 class="mb-4 d-flex align-items-center">
    <i class="bi bi-box-seam me-1" style="color: #1690c4;"></i>
    Products
  </h2>
  <div class="table-responsive table-shadow">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Description</th>
          <th>Category</th>
          <th>Stock</th>
          <th>Price</th>
          <th>Supplier</th>
          <th>Created At</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($products) === 0): ?>
          <tr>
            <td colspan="8" class="text-center">No products found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($products as $product): ?>
            <tr>
              <td><?= htmlspecialchars($product['id']) ?></td>
              <td><?= htmlspecialchars($product['name']) ?></td>
              <td><?= htmlspecialchars($product['description']) ?></td>
              <td><?= htmlspecialchars($product['category_name'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars($product['stock']) ?></td>
              <td>$<?= number_format($product['price'], 2) ?></td>
              <td><?= htmlspecialchars($product['supplier_name'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars(date('Y-m-d', strtotime($product['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
