<?php
session_start();
require 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch logged-in user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$username = $user['username'];
$role = $user['role'];

// Fetch all users for display
$stmt2 = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile - Warehouse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body {
  background: #f8fafc;
  padding-top: 80px;
}

/* Navbar Styling */
.navbar-custom {
  background-color: #1e293b;
  height: 70px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
}

.navbar-brand {
  color: #fff !important;
  font-size: 1.8rem;
  font-weight: 700;
}

.nav-link {
  color: #fff !important;
  display: flex;              /* Keep icon + text inline */
  align-items: center;        /* Vertically align */
  white-space: nowrap;        /* Prevent wrapping */
}

.nav-link.active,
.nav-link:hover {
  color: #94a3b8 !important;
}

.user-info {
  display: flex;
  align-items: center;
  white-space: nowrap;        /* Prevent wrapping */
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
}

/* Logout / Outline Button */
.btn-outline-light {
  color: #e0e7ff;
  border-color: #4b5563;
  border-radius: 12px !important;
  cursor: pointer;
  font-size: 15px;
  line-height: 20px;
  display: flex;
  align-items: center;
  transition: background-color 0.3s ease, color 0.3s ease;
}

.btn-outline-light:hover {
  background-color: #00f5ff;
  color: #1f2937;
  border-color: #00f5ff;
}

.card {
  border: none;
  border-radius: 1rem;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.09);
  background: #fff;
  min-height: 300px;
  margin-top: 3rem;
  padding: 1.5rem;
}

.profile-icon {
  font-size: 60px;
  color: #0d6efd;
 }

.list-group-item {
  background: transparent;
  border: none;
  padding-left: 0;
}
</style>
</head>
<body>

<!-- Top Navbar -->
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

      <div class="d-flex align-items-center ms-3">
        <a href="logout.php" class="btn btn-outline-light btn-sm me-3">
          <i class="bi bi-box-arrow-right me-1"></i> Logout
        </a>
        <div class="user-info d-flex align-items-center text-white">
          <i class="bi bi-person-circle me-2"></i>
          <span><?= htmlspecialchars($username) ?></span>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- Main Content -->
<div class="container">
  <div class="row justify-content-center">
    <?php foreach ($users as $user): ?>
      <div class="col-md-7 col-lg-6 mb-4">
        <div class="card text-center">
          <div class="mb-4">
            <i class="fas fa-user-circle profile-icon"></i>
            <h3 class="mt-3"><?= htmlspecialchars($user['username']) ?></h3>
            <span class="badge bg-primary text-uppercase"><?= htmlspecialchars($user['role']) ?></span>
          </div>
          <ul class="list-group list-group-flush text-start px-4">
            <li class="list-group-item">
              <i class="bi bi-envelope me-2"></i> <strong>Email:</strong>
              <?= htmlspecialchars($user['email'] ?? '<span class="text-muted">N/A</span>') ?>
            </li>
            <li class="list-group-item">
              <i class="bi bi-telephone me-2"></i> <strong>Phone:</strong>
              <?= $user['phone'] ? htmlspecialchars($user['phone']) : '<span class="text-muted">N/A</span>' ?>
            </li>
            <li class="list-group-item">
              <i class="bi bi-calendar-plus me-2"></i> <strong>Joined:</strong>
              <?= htmlspecialchars($user['created_at']) ?>
            </li>
          </ul>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
