<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']); // e.g., "admin_locations.php"

$locations = $pdo->query("SELECT * FROM locations ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - Locations</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
body {
  background-color: #f8fafc;
  padding-top: 70px; /* For fixed-top navbar */
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

.btn-sky {
  background-color: #1159a6 !important;
  color: white !important;
  border: none !important;
}
.btn-sky:hover {
  background-color: #0e568c !important;
  color: white !important;
}

.table-container {
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 0 10px rgba(0,0,0,0.08);
  margin-top: 20px;
}

/* Icon color */
.custom-icon {
  color: #1690c4;
}

/* Table layout */
.custom-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 10px; /* spacing between rows */
  table-layout: fixed;
}

/* Column sizing */
.custom-table th,
.custom-table td {
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: middle;
  white-space: nowrap;
}

/* Column widths */
.custom-table th:nth-child(1),
.custom-table td:nth-child(1) {
  width: 5%;
}

.custom-table th:nth-child(2),
.custom-table td:nth-child(2) {
  width: 20%;
}

.custom-table th:nth-child(3),
.custom-table td:nth-child(3) {
  width: 35%;
}

.custom-table th:nth-child(4),
.custom-table td:nth-child(4) {
  width: 15%;
}

.custom-table th:nth-child(5),
.custom-table td:nth-child(5) {
  width: 25%;
}

/* Header styling */
.custom-table thead th {
  background-color: #1d3557;
  color: #fff;
  border: none;
  padding: 12px;
}

/* Rounded header corners */
.custom-table thead th:first-child {
  border-top-left-radius: 10px;
}

.custom-table thead th:last-child {
  border-top-right-radius: 10px;
}

/* Body row styling */
.custom-table tbody td {
  background-color: #f8fafc;
  padding: 12px;
  border: none;
}

/* Rounded corners for rows */
.custom-table tbody tr td:first-child {
  border-top-left-radius: 10px;
  border-bottom-left-radius: 10px;
}

.custom-table tbody tr td:last-child {
  border-top-right-radius: 10px;
  border-bottom-right-radius: 10px;
}

/* Hover effect */
.custom-table tbody tr:hover td {
  background-color: #e9ecef;
  transition: background-color 0.3s ease;
}

/* Action buttons */
.custom-table td .btn {
  margin-right: 6px;
  margin-bottom: 4px;
  font-size: 0.8rem;
  padding: 0.3rem 0.6rem;
  white-space: nowrap;
}

/* Make action buttons inline */
.actions-cell {
  display: flex;
  gap: 6px;
  flex-wrap: nowrap;
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

<!-- Page Content -->
<div class="container-fluid px-3 py-3">
  <div class="card shadow-sm border-0 table-container">
    <div class="p-2">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
        <h2 class="mb-0 fw-bold text-dark">
          <i class="bi bi-geo-alt-fill me-2 custom-icon"></i>Storage Locations
        </h2>
        <a href="admin_add_location.php" class="btn btn-sky">
          <i class="bi bi-plus-circle-fill"></i> Add Location
        </a>
      </div>
    </div>

    <div class="card-body p-3">
      <div class="table-responsive mt-3">
        <table class="custom-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Address</th>
              <th>Created At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($locations) > 0): ?>
              <?php foreach ($locations as $loc): ?>
                <tr>
                  <td><?= htmlspecialchars($loc['id']) ?></td>
                  <td><?= htmlspecialchars($loc['name']) ?></td>
                  <td><?= htmlspecialchars($loc['address']) ?></td>
                  <td><?= date('Y-m-d', strtotime($loc['created_at'])) ?></td>
                   <td>
                    <div class="actions-cell">
                      <a href="edit_location.php?id=<?= $loc['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                      <a href="admin_delete_location.php?id=<?= $loc['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this location?')">Delete</a>
                      <a href="admin_location_history.php?location_id=<?= $loc['id'] ?>" class="btn btn-sm btn-info">History</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="text-center">No locations found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
