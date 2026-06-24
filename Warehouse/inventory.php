<?php
session_start();
require __DIR__ . '/config.php';

// Fallbacks for session variables
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'user'; // assume 'user' if not set

// Fetch inventory data with product and location details
$sql = "SELECT
            i.id AS inventory_id,
            p.name AS product_name,
            p.category,
            p.sku,
            p.description,
            i.quantity,
            CASE WHEN i.quantity > 0 THEN 'In Stock' ELSE 'Out of Stock' END AS stock_status,
            l.name AS location_name,
            i.last_updated
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        JOIN locations l ON i.location_id = l.id
        ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
  background-color: #f8f9fa;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  margin: 0px;
  padding-top: 70px; /* Push content below fixed navbar */
}

/* === NAVBAR STYLE === */
.navbar-custom {
  background-color: #1e293b;
  height: 60px; /* Reduced height */
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
  transition: color 0.3s ease;
}

.nav-link:hover,
.nav-link.active {
  color: #94a3b8 !important;
}

.navbar-nav {
  white-space: nowrap;
}

.navbar-nav .nav-item {
  flex-shrink: 0;
}

/* USER INFO */
.user-info {
  font-weight: 600;
  font-size: 0.95rem;
  color: #ffffff;
  white-space: nowrap;
  display: flex;
  align-items: center;
}

.user-info i {
  color: #3b82f6;
  font-size: 1.3rem;
  vertical-align: middle;
}

.btn-outline-light {
  color: #e0e7ff;
  border-color: #4b5563;
  border-radius: 12px;
  transition: background-color 0.3s ease, color 0.3s ease;
}

.btn-outline-light:hover {
  background-color: #00f5ff;
  color: #1f2937;
  border-color: #00f5ff;
}

.navbar-toggler {
  border: none;
  padding: 0.25rem 0.5rem;
}

.navbar-toggler-icon {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='%23cbd5e1' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(203, 213, 225, 0.7)' /%3e%3c/svg%3e");
}

/* === Ensure enough space below fixed navbar === */
body {
  padding-top: 80px; /* Adjust to match your navbar height */
  background-color: #f8f9fa;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* === Main container spacing === */
.container,
main,
.content-wrapper {
  margin-top: 1rem;
}

/* === Table Box Wrapper === */
.table-wrapper {
  background: #ffffff;
  padding: 2rem;
  border-radius: 16px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
  overflow-x: auto;
  margin-bottom: 2rem;
}

/* === Table Title === */
.table-title {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  display: flex;
  align-items: center;
}

/* === Table Responsive Container === */
.table-responsive {
  overflow-x: auto;
}

/* === Table Styling === */
.table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 12px;
  font-size: 0.9rem;
  color: #334155;
  min-width: 1000px;
  table-layout: fixed;
}

/* === Table Header === */
.table thead th {
  background-color: #1e293b;
  color: #f1f5f9;
  font-weight: 600;
  padding: 14px 18px;
  text-align: left;
  user-select: none;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  border-top-left-radius: 12px;
  border-top-right-radius: 12px;
  white-space: nowrap;
}

.table thead th:first-child {
  width: 60px;
  max-width: 60px;
  min-width: 50px;
  text-align: center;
  padding-left: 0.5rem !important;
  padding-right: 0.5rem !important;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* === Table Body Rows === */
.table tbody tr {
  background-color: #f9fafb;
  border-radius: 10px;
  transition: background-color 0.25s ease;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

.table tbody tr:hover {
  background-color: #e0f2fe;
  box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
  cursor: pointer;
}

/* === Table Body Cells === */
.table tbody td {
  padding: 14px 18px;
  vertical-align: middle;
  border: none;
  color: #475569;
  max-width: 220px;
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
  background-color: #fff;
}

.table tbody td:first-child {
  text-align: center;
  width: 60px;
  padding-left: 0.5rem;
  padding-right: 0.5rem;
}

/* === Badges === */
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

/* === Responsive Adjustments === */
@media (max-width: 768px) {
  .table-wrapper {
    padding: 1.2rem;
  }

  .table-title {
    font-size: 1.25rem;
  }

  .table {
    min-width: 600px;
    font-size: 0.85rem;
  }

  .table thead th,
  .table tbody td {
    padding: 10px 12px;
  }
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">
      <i class="bi bi-warehouse me-2"></i> WarehousePro
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="mainNavbar">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? ' active' : '' ?>" href="dashboard.php">
            <i class="bi bi-speedometer2"></i> Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? ' active' : '' ?>" href="products.php">
            <i class="bi bi-box-seam"></i> Products
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? ' active' : '' ?>" href="categories.php">
            <i class="bi bi-tags"></i> Categories
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? ' active' : '' ?>" href="suppliers.php">
            <i class="bi bi-truck"></i> Suppliers
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'locations.php' ? ' active' : '' ?>" href="locations.php">
            <i class="bi bi-geo-alt"></i> Locations
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? ' active' : '' ?>" href="inventory.php">
            <i class="bi bi-stack"></i> Inventory
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'stock_movements.php' ? ' active' : '' ?>" href="stock_movements.php">
            <i class="bi bi-arrow-left-right"></i> Stock Movements
          </a>
        </li>
         <?php if ($role === 'admin' || $role === 'staff'): ?>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? ' active' : '' ?>" href="users.php">
            <i class="bi bi-people"></i> Users
          </a>
        </li>
        <?php endif; ?>
      </ul>

      <div class="d-flex align-items-center ms-3">
        <a href="logout.php" class="btn btn-outline-light btn-sm me-3">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
        <div class="user-info d-flex align-items-center text-white">
          <i class="bi bi-person-circle me-2"></i>
          <span><?= htmlspecialchars($username) ?></span>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- Page Content -->
<div class="container mt-4">
  <?php if (count($inventoryItems) === 0): ?>
    <div class="alert alert-warning">No inventory items found.</div>
  <?php else: ?>
    <div class="table-wrapper">

      <!-- ✅ Table Title with spacing -->
      <div class="d-flex justify-content-between align-items-center mb-4 mt-2"> 
         <h2 class="table-title">
         <i class="bi bi-stack me-2" style="color: #1690c4;"></i> Inventory Overview
         </h2>
      </div>

      <!-- ✅ Responsive Table -->
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Product</th>
              <th>Category</th>
              <th>SKU</th>
              <th>Description</th>
              <th>Quantity</th>
              <th>Status</th>
              <th>Location</th>
              <th>Last Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($inventoryItems as $item): ?>
              <tr>
                <td><?= htmlspecialchars($item['inventory_id']) ?></td>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td><?= htmlspecialchars($item['category']) ?></td>
                <td><?= htmlspecialchars($item['sku']) ?></td>
                <td><?= htmlspecialchars($item['description']) ?></td>
                <td><?= (int)$item['quantity'] ?></td>
                <td>
                  <?php if ($item['stock_status'] === 'In Stock'): ?>
                    <span class="badge bg-success">In Stock</span>
                  <?php else: ?>
                    <span class="badge bg-danger">Out of Stock</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($item['location_name']) ?></td>
                <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($item['last_updated']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
