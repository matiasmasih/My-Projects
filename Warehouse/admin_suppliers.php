<?php
session_start();
require __DIR__ . '/config.php';

// Check admin session correctly (role, not user_role)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']); // e.g., "admin_suppliers.php"

// Fetch suppliers from DB
try {
    $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY id DESC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching suppliers: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Suppliers - Warehouse</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
  background: #f1f5f9;
  padding-top: 70px; /* space for fixed navbar */
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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

.container {
  max-width: 1200px;
  background: #ffffff;
  padding: 30px 40px;
  border-radius: 12px;
  box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.custom-icon {
  color: #1690c4; /* Your preferred HEX color */
}

table {
  border-collapse: separate;
  border-spacing: 0 8px; /* vertical spacing between rows */
}

table thead th:first-child {
  border-top-left-radius: 10px;
  border-bottom-left-radius: 10px;
}

table thead th:last-child {
  border-top-right-radius: 10px;
  border-bottom-right-radius: 10px;
}

table tbody td {
  background-color: #ffffff; /* Make sure it's visible */
}

table tbody tr:hover td {
  background-color: #f1f5f9;
  transition: background-color 0.3s ease;
}

table tbody tr:hover td:first-child {
  border-top-left-radius: 10px;
  border-bottom-left-radius: 10px;
}

table tbody tr:hover td:last-child {
  border-top-right-radius: 10px;
  border-bottom-right-radius: 10px;
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

<!-- Main container -->
<div class="container mt-4">
<h2 class="mt-4">
  <i class="fas fa-truck me-2" style="color: #1690c4;"></i> Manage Suppliers
</h2>

  <div class="mb-3 text-end">
    <a href="add_supplier.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Supplier</a>
  </div>

  <table class="table table-hover align-middle">
    <thead class="table-dark">
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Contact Person</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Address</th>
        <th style="width: 130px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($suppliers)): ?>
        <tr>
          <td colspan="7" class="text-center">No suppliers found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($suppliers as $supplier): ?>
        <tr>
          <td><?= htmlspecialchars($supplier['id']) ?></td>
          <td><?= htmlspecialchars($supplier['name']) ?></td>
          <td><?= htmlspecialchars($supplier['contact_person']) ?></td>
          <td><?= htmlspecialchars($supplier['email']) ?></td>
          <td><?= htmlspecialchars($supplier['phone']) ?></td>
          <td><?= htmlspecialchars($supplier['address']) ?></td>
          <td>
            <a href="edit_supplier.php?id=<?= urlencode($supplier['id']) ?>" class="btn btn-sm btn-warning" title="Edit">
              <i class="fas fa-edit"></i>
            </a>
            <a href="delete_supplier.php?id=<?= urlencode($supplier['id']) ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this supplier?');">
              <i class="fas fa-trash-alt"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
