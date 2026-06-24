<?php
session_start();
require 'config.php';

// Protect page
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Fetch categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Categories - WarehousePro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
  background-color: #f8f9fa;
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

/* === MODERN TABLE CARD STYLE === */
.table-container {
  background: #fff;
  padding: 2rem;
  border-radius: 16px;
  box-shadow: 0 8px 25px rgba(29, 53, 87, 0.12);
  max-width: 1200px;
  margin: 2.5rem auto 4rem;
  transition: box-shadow 0.3s ease;
}

.table-container:hover {
  box-shadow: 0 12px 35px rgba(29, 53, 87, 0.18);
}

.table-container h2 {
  font-weight: 700;
  font-size: 1.5rem;
  margin-bottom: 1.5rem;
  color: #1f2937;
  display: flex;
  align-items: center;
}

.table-container h2 i {
  margin-right: 0.5rem;
  color: #0d6efd;
}

/* === TABLE === */
table {
  width: 100%;
  border-collapse: separate !important;
  border-spacing: 0 12px;
  table-layout: fixed;
  font-size: 0.95rem;
  color: #475569;
}

thead th {
  background-color: #202c3d !important;
  font-weight: 600 !important;
  color: #ffffff !important;
  border-bottom: none !important;
  padding: 1rem 1.5rem !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05em !important;
  white-space: nowrap;
  border-radius: 10px 10px 0 0;
}

tbody tr {
  background: #ffffff;
  box-shadow: 0 6px 18px rgba(29, 53, 87, 0.1);
  border-radius: 12px;
  transition: transform 0.25s ease, box-shadow 0.25s ease;
  cursor: default;
}

tbody tr:hover {
  transform: translateY(-6px);
  box-shadow: 0 14px 36px rgba(29, 53, 87, 0.15);
}

tbody td {
  padding: 1rem 1.5rem;
  vertical-align: middle;
  border: none;
  overflow-wrap: break-word;
  word-wrap: break-word;
  color: #475569;
  background-color: #fff;
}

thead th:first-child,
tbody td:first-child {
  width: 60px;
  max-width: 60px;
  min-width: 50px;
  text-align: center;
  padding-left: 0.5rem !important;
  padding-right: 0.5rem !important;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

tbody td:last-child {
  padding-right: 1.8rem;
}

/* Responsive tweaks */
@media (max-width: 768px) {
  .table-container {
    padding: 1.2rem;
  }

  .table-container h2 {
    font-size: 1.2rem;
  }

  table {
    font-size: 0.85rem;
  }

  thead th,
  tbody td {
    padding: 0.8rem 1rem !important;
  }
}

</style>
</head>
<body>

<!-- Header -->
<nav class="navbar navbar-expand-lg navbar-custom">
  <div class="container-fluid">
    <a class="navbar-brand text-white" href="dashboard.php">
      <i class="bi bi-warehouse me-2"></i> WarehousePro
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="mainNavbar">
      <ul class="navbar-nav mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? ' active' : '' ?>" href="dashboard.php">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? ' active' : '' ?>" href="products.php">
            <i class="bi bi-box-seam me-1"></i> Products
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? ' active' : '' ?>" href="categories.php">
            <i class="bi bi-tags me-1"></i> Categories
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? ' active' : '' ?>" href="suppliers.php">
            <i class="bi bi-truck me-1"></i> Suppliers
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'locations.php' ? ' active' : '' ?>" href="locations.php">
            <i class="bi bi-geo-alt me-1"></i> Locations
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? ' active' : '' ?>" href="inventory.php">
            <i class="bi bi-stack me-1"></i> Inventory
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'stock_movements.php' ? ' active' : '' ?>" href="stock_movements.php">
            <i class="bi bi-arrow-left-right me-1"></i> Stock Movements
          </a>
        </li>
         <?php if ($role === 'admin' || $role === 'staff'): ?>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? ' active' : '' ?>" href="users.php">
            <i class="bi bi-people me-1"></i> Users
          </a>
        </li>
        <?php endif; ?>
      </ul>

      <!-- User Info and Logout -->
      <div class="d-flex align-items-center ms-3">
        <a href="logout.php" class="btn btn-outline-light btn-sm me-3">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
        <div class="user-info d-flex align-items-center text-white">
          <i class="bi bi-person-circle me-2" style="font-size: 1.4rem;"></i>
          <span><?= htmlspecialchars($username) ?></span>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- Main Content -->
<div class="container mt-4">
  <div class="table-container">
    <h2><i class="bi bi-tags me-1" style="color: #1690c4;"></i>Categories</h2>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($categories) === 0): ?>
            <tr>
              <td colspan="3" class="text-center">No categories found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($categories as $category): ?>
              <tr>
                <td><?= htmlspecialchars($category['id']) ?></td>
                <td><?= htmlspecialchars($category['name']) ?></td>
                <td><?= htmlspecialchars($category['description']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
