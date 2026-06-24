<?php
session_start();
require __DIR__ . '/config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['username'])) {
  header("Location: login.php");
  exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'] ?? 'user';

$stmt = $pdo->query("SELECT id, name, description FROM locations ORDER BY id DESC");
$locations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Locations - WarehousePro</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
  background-color: #f8f9fa;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* === NAVBAR STYLE === */
.navbar-custom {
  background-color: #1e293b;
  height: 60px;
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
  padding: 0.5rem 1rem;
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

.navbar-nav
.nav-item {
  flex-shrink: 0;
 }

/* LOGOUT BUTTON */
.btn-outline-light {
  display: inline-flex;
  align-items: center;
  color: #e0e7ff;
  border-color: #4b5563;
  transition: background-color 0.3s ease, color 0.3s ease;
  border-radius: 12px !important;
  cursor: pointer;
}

.btn-outline-light:hover {
  background-color: #00f5ff;
  color: #1f2937;
  border-color: #00f5ff;
}

.btn-outline-light i {
  font-size: 1.2rem;
  line-height: 1;
  display: inline-block;
  vertical-align: middle;
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

/* === PAGE TITLE === */
.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin-top: 10px;
  margin-bottom: 5px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.page-title i {
  font-size: 1.6rem;
  color: #1d3557;
}

/* === MODERN TABLE STYLE === */
.table-wrapper {
  background: #ffffff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
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
  user-select: none;
  border-top-left-radius: 12px;
  border-top-right-radius: 12px;
}

.table thead th:last-child {
  border-top-right-radius: 12px;
}

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

/* Badges and responsiveness as you had */
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
}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-custom">
  <div class="container-fluid">
    <a class="navbar-brand text-white" href="dashboard.php">
      <i class="bi bi-warehouse me-2"></i> WarehousePro
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" ar>
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarContent">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 flex-row">
        <li class="nav-item"><a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? ' active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? ' active' : '' ?>" href="products.php"><i class="bi bi-box-seam me-1"></i>Products</a></li>
        <li class="nav-item"><a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? ' active' : '' ?>" href="categories.php"><i class="bi bi-tags me-1"></i>Categories</a></li>
        <li class="nav-item"><a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? ' active' : '' ?>" href="suppliers.php"><i class="bi bi-truck me-1"></i>Suppliers</a></li>
        <li class="nav-item"><a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'locations.php' ? ' active' : '' ?>" href="locations.php"><i class="bi bi-geo-alt me-1"></i>Locations</a></li>
        <li class="nav-item"><a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? ' active' : '' ?>" href="inventory.php"><i class="bi bi-stack me-1"></i>Inventory</a></li>
        <li class="nav-item"><a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'stock_movements.php' ? ' active' : '' ?>" href="stock_movements.php"><i class="bi bi-arrow-left-right me-1"></i> Stock Movements</a></li>
         <?php if ($role === 'admin' || $role === 'staff'): ?>
        <li class="nav-item"><a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? ' active' : '' ?>" href="users.php"><i class="bi bi-people me-1"></i>Users</a></li>
        <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center">
        <a href="logout.php" class="btn btn-outline-light btn-sm me-3">
          <i class="bi bi-box-arrow-right me-1"></i> Logout
        </a>
        <div class="user-info d-flex align-items-center">
          <i class="bi bi-person-circle me-2"></i>
          <span><?= htmlspecialchars($username ?? '') ?></span>
        </div>
      </div>
    </div>
  </div>
</nav>

<div class="container mt-5">
  <div class="table-wrapper">
    <div class="page-title">
      <i class="bi bi-geo-alt" style="color: #1690c4;"></i>
      Locations
    </div>
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Description</th>
          <th>Created At</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($locations as $index => $location): ?>
        <tr>
          <td><?= $index + 1 ?></td>
          <td><?= htmlspecialchars($location['name']) ?></td>
          <td><?= htmlspecialchars($location['description'] ?? '') ?></td>
          <td>
            <?= isset($location['created_at']) && $location['created_at']
              ? date('Y-m-d', strtotime($location['created_at']))
              : '-' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
