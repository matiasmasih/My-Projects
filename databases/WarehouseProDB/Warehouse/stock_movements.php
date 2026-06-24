<?php
session_start();
require __DIR__ . '/config.php';

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';

$sql = "
SELECT
    sm.id,
    p.name AS product_name,
    from_loc.name AS from_location,
    to_loc.name AS to_location,
    sm.quantity,
    sm.movement_type,
    sm.reason,
    sm.timestamp AS movement_date
FROM stock_movements sm
JOIN products p ON sm.product_id = p.id
LEFT JOIN locations from_loc ON sm.from_location = from_loc.id
LEFT JOIN locations to_loc ON sm.to_location = to_loc.id
ORDER BY sm.timestamp DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Stock Movements</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
 background-color: #f8fafc;
 padding-top: 70px;
}

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
}

.nav-link.active,
.nav-link:hover {
  color: #94a3b8 !important;
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
  transition: background-color 0.3s ease, color 0.3s ease;
  border-radius: 12px !important;
  cursor: pointer;
}

.btn-outline-light:hover {
  background-color: #00f5ff;
  color: #1f2937;
  border-color: #00f5ff;
}

/* === Table wrapper for padding and background === */ 
.table-wrapper {
  background: #ffffff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  overflow-x: auto;
}

/* Responsive table container */
.table-responsive {
  overflow-x: auto;
}

/* Base table style */
.table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 12px; /* vertical spacing between rows */
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  font-size: 0.9rem;
  color: #334155;
  min-width: 900px; /* prevent squashing on smaller screens */
}

/* Header styling */
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

/* Remove bottom border from the last header */
.table thead th:last-child {
  border-top-right-radius: 12px;
}

/* Body row styling */
.table tbody tr {
  background-color: #f9fafb;
  border-radius: 10px;
  transition: background-color 0.25s ease;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

/* Hover effect */
.table tbody tr:hover {
  background-color: #e0f2fe;
  box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
  cursor: pointer;
}

/* Table cells */
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

/* Badges styling */
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

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php"><i class="bi bi-warehouse me-2"></i>WarehousePro</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse justify-content-end" id="mainNavbar">
      <ul class="navbar-nav mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>" href="products.php"><i class="bi bi-box-seam"></i> Products</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '' ?>" href="categories.php"><i class="bi bi-tags"></i> Categories</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : '' ?>" href="suppliers.php"><i class="bi bi-truck"></i> Suppliers</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'locations.php' ? 'active' : '' ?>" href="locations.php"><i class="bi bi-geo-alt"></i> Locations</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : '' ?>" href="inventory.php"><i class="bi bi-stack"></i> Inventory</a></li>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'stock_movements.php' ? 'active' : '' ?>" href="stock_movements.php"><i class="bi bi-arrow-left-right"></i> Stock Movements</a></li>
          <?php if ($role === 'admin' || $role === 'staff'): ?>
        <li class="nav-item"><a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>" href="users.php"><i class="bi bi-people"></i> Users</a></li>
        <?php endif; ?>
      </ul>

      <div class="d-flex align-items-center ms-3">
        <a href="logout.php" class="btn btn-outline-light btn-sm me-3"><i class="bi bi-box-arrow-right"></i> Logout</a>
        <div class="user-info d-flex align-items-center">
          <i class="bi bi-person-circle me-2" style="font-size: 1.2rem;"></i>
          <span><?= htmlspecialchars($username) ?></span>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- Main Content -->
<div class="container mt-5">
  <div class="table-wrapper">
     <h2 class="mb-4" style="color: #1e293b; font-weight: 600;">
        <i class="bi bi-arrow-left-right me-2" style="color: #1690c4;"></i>
        Stock Movements
     </h2>

    <?php if (count($movements) === 0): ?>
      <div class="alert alert-warning mb-0">No stock movement records found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Product</th>
            <th>From</th>
            <th>To</th>
            <th>Quantity</th>
            <th>Type</th>
            <th>Reason</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movements as $row): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['product_name']) ?></td>
              <td><?= htmlspecialchars($row['from_location'] ?? '-') ?></td>
              <td><?= htmlspecialchars($row['to_location'] ?? '-') ?></td>
              <td>
                <span class="badge <?= $row['quantity'] >= 0 ? 'bg-success' : 'bg-danger' ?>">
                  <?= (int)$row['quantity'] ?>
                </span>
              </td>
              <td>
                <span class="badge bg-primary">
                  <?= ucfirst(htmlspecialchars($row['movement_type'])) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($row['reason'] ?? '-') ?></td>
              <td><?= date('Y-m-d H:i', strtotime($row['movement_date'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
