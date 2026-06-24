<?php
session_start();
require __DIR__ . '/config.php';

// Assuming you set these session variables when user logs in
$username = $_SESSION['username'] ?? 'Guest';
$role = $_SESSION['role'] ?? 'user';

try {
    $stmt = $pdo->query("SELECT id, name, contact_person, email, phone, address FROM suppliers ORDER BY name ASC");
    $suppliers = $stmt->fetchAll();
} catch (Exception $e) {
    die("Error loading suppliers: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>WarehousePro - Suppliers</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />

<style>
body {
  background-color: #f8fafc;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  color: #334155;
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
  gap: 2px;
  user-select: none;
}

.user-info i {
  color: #3b82f6;
  font-size: 1.3rem;
  vertical-align: middle;
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

/* Navbar toggler */
.navbar-toggler {
  border: none;
  padding: 0.25rem 0.5rem;
  cursor: pointer;
}

.navbar-toggler-icon {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='%23cbd5e1' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(203, 213, 225, 0.7)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
}

.navbar-collapse {
  display: flex !important;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

/* RESPONSIVE ADJUSTMENTS */
@media (max-width: 991.98px) {
  .navbar-nav {
    flex-direction: column;
    margin-top: 1rem;
  }

  .navbar-collapse {
    flex-direction: column;
    align-items: flex-start;
  }

  .d-flex.align-items-center.ms-3 {
    margin-top: 1rem !important;
    justify-content: flex-start;
  }
}

/* === CONTAINER === */
.container {
  max-width: 1200px;
  margin-top: 30px;
  margin-bottom: 60px;
  padding: 0 1rem;
}

/* === CARD === */
.card {
  box-shadow: 0 10px 30px rgb(0 0 0 / 0.1);
  border-radius: 16px;
  border: none;
  overflow: hidden;
  padding: 1.5rem;
  background-color: #fff;
}

/* Card Header */
.card-header.bg-transparent {
  background-color: transparent !important;
  border: none !important;
  padding: 1rem 1.5rem !important;
  margin-bottom: 0;
  color: #1e293b;
  font-weight: 600;
  font-size: 1.5rem;
  user-select: none;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.card-header.bg-transparent i {
  font-size: 1.6rem;
  color: #3b82f6;
}

/* === TABLE - Modern Clean Style === */
table {
  width: 100%;
  border-collapse: separate;    /* Keep spacing for rounded corners */
  border-spacing: 0 14px;        /* vertical spacing between rows */
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  font-size: 1rem;
  color: #334155;                /* Dark slate text */
  table-layout: fixed;
  min-width: 600px;
  background: transparent;
}

/* Table Head */
thead th {
  background-color: #1e293b !important; /* dark blue-gray header */
  color: #f1f5f9 !important;            /* very light text */
  font-weight: 700 !important;
  padding: 1rem 1.5rem !important;
  text-transform: uppercase !important;
  letter-spacing: 0.1em !important;
  border: none !important;
  white-space: nowrap;
  border-radius: 12px 12px 0 0;
  user-select: none;
  box-shadow: inset 0 -2px 4px rgb(0 0 0 / 0.15);
}

/* Set column widths */
thead th:nth-child(1),
tbody td:nth-child(1) { width: 5%; }
thead th:nth-child(2),
tbody td:nth-child(2) { width: 15%; }
thead th:nth-child(3),
tbody td:nth-child(3) { width: 15%; }
thead th:nth-child(4),
tbody td:nth-child(4) { width: 25%; }
thead th:nth-child(5),
tbody td:nth-child(5) { width: 30%; }
thead th:nth-child(6),
tbody td:nth-child(6) { width: 20%; }

/* Table Body Rows */
tbody tr {
  background: #ffffff;
  box-shadow: 0 3px 10px rgb(0 0 0 / 0.08);
  border-radius: 12px;
  transition: box-shadow 0.25s ease, transform 0.25s ease;
  cursor: default;
  display: table-row;
}

/* Row Hover */
tbody tr:hover {
  box-shadow: 0 8px 25px rgb(0 0 0 / 0.15);
  transform: translateY(-3px);
}

/* Table Cells */
tbody td {
  padding: 1.1rem 1.5rem;
  vertical-align: middle;
  border: none;
  color: #475569;
  background: transparent;
  overflow-wrap: break-word;
  word-wrap: break-word;
  border-radius: 12px;
  transition: background-color 0.3s ease;
  user-select: text;
}

/* Cell Hover Effect */
tbody td:hover {
  background-color: #e0f2fe;  /* soft sky blue highlight */
}

/* Rounded corners on first and last cells for each row */
tbody td:first-child {
  border-top-left-radius: 12px;
  border-bottom-left-radius: 12px;
}

tbody td:last-child {
  border-top-right-radius: 12px;
  border-bottom-right-radius: 12px;
}

/* Responsive horizontal scroll for smaller screens */
.table-responsive {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  border-radius: 12px;  /* rounded corners for scrollbar container */
}

/* Scrollbar style for Webkit browsers */
.table-responsive::-webkit-scrollbar {
  height: 8px;
}

.table-responsive::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 8px;
}

.table-responsive::-webkit-scrollbar-thumb {
  background-color: #3b82f6;
  border-radius: 8px;
}

/* Optional: smooth text selection color */
::selection {
  background-color: #3b82f6;
  color: white;
}

</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top navbar-custom">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
      <i class="bi bi-warehouse me-2"></i> WarehousePro
    </a>

    <!-- Toggle for mobile -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
      aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Navigation links -->
    <div class="collapse navbar-collapse justify-content-between" id="mainNavbar">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 d-flex flex-row align-items-center">
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? ' active' : '' ?>" href="dashboard.php">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? ' active' : '' ?>" href="products.php">
            <i class="bi bi-box-seam me-1"></i>Products
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? ' active' : '' ?>" href="categories.php">
            <i class="bi bi-tags me-1"></i>Categories
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? ' active' : '' ?>" href="suppliers.php">
            <i class="bi bi-truck me-1"></i>Suppliers
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'locations.php' ? ' active' : '' ?>" href="locations.php">
            <i class="bi bi-geo-alt me-1"></i>Locations
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? ' active' : '' ?>" href="inventory.php">
            <i class="bi bi-stack me-1"></i>Inventory
          </a>
        </li>
       <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'stock_movements.php' ? ' active' : '' ?>" href="stock_movements.php">
            <i class="bi bi-arrow-left-right me-1"></i>Stock Movements
          </a>
        </li>
         <?php if ($role === 'admin' || $role === 'staff'): ?>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? ' active' : '' ?>" href="users.php">
            <i class="bi bi-people me-1"></i>Users
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </div>

    <!-- Right side: Logout and user -->
    <div class="d-flex align-items-center ms-3">
      <a href="logout.php" class="btn btn-outline-light btn-sm me-2">
        <i class="bi bi-box-arrow-right me-1"></i>Logout
      </a>
      <div class="user-info d-flex align-items-center text-white fw-bold">
        <i class="bi bi-person-circle me-2"></i>
        <span><?= htmlspecialchars($username) ?></span>
      </div>
    </div>
  </div>
</nav>


<!-- Main content -->
<div class="card mx-2 mt-5">
  <div class="card-header bg-transparent">
    <div class="d-flex align-items-center">
      <i class="bi bi-truck me-1" style="color: #1690c4;"></i>
      <h2>Supplier List</h2>
    </div>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Supplier Name</th>
          <th>Contact Name</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Address</th>
        </tr>
      </thead>
        <tbody>
          <?php if (!empty($suppliers)): ?>
            <?php foreach ($suppliers as $supplier): ?>
              <tr>
                <td><?= htmlspecialchars($supplier['id']) ?></td>
                <td><?= htmlspecialchars($supplier['name']) ?></td>
                <td><?= htmlspecialchars($supplier['contact_person']) ?></td>
                <td><?= htmlspecialchars($supplier['phone']) ?></td>
                <td>
                  <?php if (!empty($supplier['email'])): ?>
                    <a href="https://mail.google.com/mail/?view=cm&to=<?= urlencode($supplier['email']) ?>" target="_blank">
                      <?= htmlspecialchars($supplier['email']) ?>
                    </a>
                  <?php else: ?> - <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($supplier['address']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center text-muted">No suppliers found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Bootstrap JS Bundle (Popper + Bootstrap JS) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
