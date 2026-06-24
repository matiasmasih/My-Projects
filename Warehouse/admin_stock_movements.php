<?php
session_start();
require 'config.php';

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Pagination setup
$recordsPerPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// Count total records for pagination
$totalStmt = $pdo->query("SELECT COUNT(*) FROM stock_movements");
$totalRecords = $totalStmt->fetchColumn();
$totalPages = ceil($totalRecords / $recordsPerPage);

try {
    $sql = "SELECT sm.id, p.name AS product_name,
                   fl.name AS from_location,
                   tl.name AS to_location,
                   sm.quantity, sm.movement_type, sm.reason,
                   u.username AS user_name,
                   sm.timestamp
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN locations fl ON sm.from_location = fl.id
            LEFT JOIN locations tl ON sm.to_location = tl.id
            LEFT JOIN users u ON sm.user_id = u.id
            ORDER BY sm.timestamp DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $stockMovements = $stmt->fetchAll();
} catch (PDOException $e) {
     die("Error fetching stock movements: " . $e->getMessage());
}

// Handle deletion if requested (basic CSRF not implemented here - you can add it)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    $delStmt = $pdo->prepare("DELETE FROM stock_movements WHERE id = ?");
    $delStmt->execute([$deleteId]);
    header("Location: admin_stock_movements.php?page=$page");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Stock Movements - Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
<style>
body {
  background-color: #f8fafc;
  padding-top: 70px;
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

.header-section {
  background-color: #1e293b;
  padding: 1rem 1.5rem;
  color: #fff;
  margin-bottom: 1rem;
  border-radius: 0.375rem;
}

.table-modern {
  border-radius: 0.5rem;
  overflow: hidden;
  box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.075);
}

.table-modern thead th {
  text-transform: uppercase;
  letter-spacing: 0.05em;
  background-color: #0f172a;
  color: #e2e8f0;
  border-bottom: 2px solid #334155;
}

.table-modern tbody tr:nth-child(even) {
  background-color: #f1f5f9;
}

.table-modern tbody tr:hover {
  background-color: #e2e8f0;
  transition: background-color 0.2s ease-in-out;
}

.table-modern td, .table-modern th {
  vertical-align: middle;
}

.custom-icon {
  color: #1690c4;
}

.text-nowrap {
  white-space: nowrap;
}

.btn-sm i {
  transition: transform 0.2s ease;
}

.btn-sm:hover i {
  transform: scale(1.15);
}

</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-white" href="admin_dashboard.php">📦 WAREHOUSE</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarWarehouse">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarWarehouse">
      <ul class="navbar-nav ms-auto gap-1">
        <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_products.php"><i class="bi bi-boxes"></i> Products</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_suppliers.php"><i class="bi bi-truck"></i> Suppliers</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_locations.php"><i class="bi bi-geo-alt"></i> Locations</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_inventory.php"><i class="fas fa-warehouse"></i> Inventory</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_stock_movements.php"><i class="fas fa-exchange-alt"></i> Stock Movement</a></li>
        <li class="nav-item"><a class="nav-link active" href="admin_location_history.php"><i class="bi bi-clock-history"></i> Location History</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_users.php"><i class="bi bi-people"></i> Users</a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Table Card -->
<div class="container-fluid px-2 mt-2">
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <h3 class="fw-bold text-dark mb-4">
        <i class="fas fa-exchange-alt me-2" style="color: #1690c4;"></i> Stock Movements
      </h3>
     <div class="table-responsive">
      <table class="table table-modern align-middle text-center">
        <thead>
          <tr>
           <th>ID</th>
           <th>Product</th>
           <th>From Location</th>
           <th>To Location</th>
           <th>Quantity</th>
           <th>Movement Type</th>
           <th>Reason</th>
           <th>User</th>
           <th>Date/Time</th>
           <th>Actions</th>
         </tr>
      </thead>
     <tbody>
      <?php if (!empty($stockMovements)): ?>
      <?php foreach ($stockMovements as $movement): ?>
     <tr>
     <td><?= htmlspecialchars($movement['id']) ?></td>
     <td><?= htmlspecialchars($movement['product_name'] ?? 'N/A') ?></td>
     <td><?= htmlspecialchars($movement['from_location'] ?? 'N/A') ?></td>
     <td><?= htmlspecialchars($movement['to_location'] ?? 'N/A') ?></td>
     <td><?= (int)$movement['quantity'] ?></td>
     <td class="text-capitalize"><?= htmlspecialchars($movement['movement_type']) ?></td>
     <td class="text-wrap" style="max-width: 180px;"><?= htmlspecialchars($movement['reason']) ?></td>
     <td><?= htmlspecialchars($movement['user_name'] ?? 'Unknown') ?></td>
     <td class="text-nowrap"><?= date('Y-m-d H:i', strtotime($movement['timestamp'])) ?></td>
       <td class="text-nowrap">
         <a href="edit_stock_movement.php?id=<?= urlencode($movement['id']) ?>" class="btn btn-primary btn-sm" title="Edit">
           <i class="fas fa-edit"></i>
         </a>
         <a href="?delete=<?= urlencode($movement['id']) ?>&page=<?= $page ?>" class="btn btn-danger btn-sm" title="Delete" onclick="return confirm('Are you sure you want to delete this movement?');">
            <i class="fas fa-trash-alt"></i>
         </a>
         </td>
         </tr>
         <?php endforeach; ?>
           <?php else: ?>
             <tr><td colspan="10" class="text-muted">No stock movements found.</td></tr>
           <?php endif; ?>
         </tbody>
         </table>
         </div>

<!-- Pagination -->
<nav class="mt-3">
  <ul class="pagination justify-content-center">
    <?php if ($page > 1): ?>
      <li class="page-item">
         <a class="page-link" href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
      </li>
    <?php endif; ?>
<?php for ($i = 1; $i <= $totalPages; $i++): ?>
   <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
     <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
   </li>
<?php endfor; ?>
<?php if ($page < $totalPages): ?>
 <li class="page-item">
   <a class="page-link" href="?page=<?= $page + 1 ?>">Next &raquo;</a>
 </li>
<?php endif; ?>
  </ul>
  </nav>
 </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
