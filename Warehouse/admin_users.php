<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['role'];

// Prevent staff from accessing this page
if ($role === 'staff') {
    header('Location: unauthorized.php'); // Or dashboard
    exit;
}

// Prepare SQL query based on role
if ($role === 'manager') {
    // Managers see all users
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
} elseif ($role === 'admin') {
    // Admins see only admins and staff (not managers)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role IN ('admin', 'staff') ORDER BY id DESC");
    $stmt->execute();
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin - Users</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

<style>
body {
  background-color: #f8fafc;
  padding-top: 70px;
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

.table-container {
  border: 1px solid #dee2e6;
  padding: 15px;
  background-color: #f8f9fa;
  border-radius: 5px;
  margin-top: 1rem;
  overflow-x: auto; /* Keep horizontal scroll if needed */
}

/* Make sure the Bootstrap table inside respects the border-radius */
.table-responsive {
  border-radius: 5px;
  overflow: hidden; /* clip overflow so rounded corners show */
  border: none; /* remove default bootstrap border to avoid double borders */
}

/* Bootstrap's .table styles are mostly fine, but we tweak borders and radius */
.table {
  border-collapse: separate !important; /* needed for radius */
  border-spacing: 0;
  width: 100%;
  font-size: 0.9rem;
  color: #1e293b;
  border-radius: 5px;
  overflow: hidden;
}

/* Header background and text */
.table thead th {
  background-color: #1f2e3d; /* Bootstrap primary lighter */
  color: #FFFFFF; /* Bootstrap primary dark */
  padding: 0.75rem 1rem;
  text-align: center;
  font-weight: 600;
  font-size: 0.9rem;
  text-transform: uppercase;
  border: none;
}

/* Round top corners on first and last header cell */
.table thead th:first-child {
  border-top-left-radius: 5px;
}
.table thead th:last-child {
  border-top-right-radius: 5px;
}

/* Set width for Actions column */
.table thead th.actions-header,
.table tbody td.actions-cell {
  width: 15%; /* adjust as needed */
  min-width: 120px; /* ensure enough space */
  text-align: center;
}

/* Table body cells */
.table tbody td {
  padding: 0.6rem 1rem;
  text-align: center;
  border-top: 1px solid #dee2e6;
  background-color: #fff;
  vertical-align: middle;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Remove text clipping on actions cell */
.actions-cell {
  overflow: visible !important;
  white-space: normal !important;
  text-overflow: clip !important;
  display: flex;
  gap: 6px;
  justify-content: center;
  flex-wrap: nowrap;
}

/* Buttons inside actions-cell */
.actions-cell .btn {
  white-space: nowrap;
  padding: 0.3rem 0.6rem;
  font-size: 0.85rem;
  border-radius: 8px;
  margin: 0; /* reset margin */
}

/* Round bottom corners on last row cells */
.table tbody tr:last-child td:first-child {
  border-bottom-left-radius: 5px;
}
.table tbody tr:last-child td:last-child {
  border-bottom-right-radius: 5px;
}

/* Hover highlight */
.table tbody tr:hover {
  background-color: #e9f0ff;
}

/* Buttons inside actions */
.table .btn {
  font-size: 0.85rem;
  padding: 0.3rem 0.6rem;
  border-radius: 5px;
}

/* Responsive tweaks (optional) */
@media (max-width: 768px) {
  .table-responsive {
    border-radius: 0; /* Let container radius do the rounding on mobile */
  }
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

<!-- Page Content -->
<div class="container-fluid px-4">

  <div class="table-container mt-3">
    <div class="d-flex justify-content-between align-items-center" style="margin-bottom: 30px;">
     <h4 class="fw-bold text-dark">
        <i class="bi bi-people" style="color: #1690c4;"></i> Manage Users
     </h4>
      <a href="admin_add_user.php" class="btn btn-primary"><i class="bi bi-person-plus-fill"></i> Add User</a>
    </div>

    <!-- Alert messages for delete success/errors -->
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] === 'success'): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        User deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php
          switch ($_GET['error']) {
            case 'invalid_id': echo "Invalid user ID."; break;
            case 'cannot_delete_self': echo "You cannot delete your own account."; break;
            case 'user_not_found': echo "User not found."; break;
            case 'not_allowed_to_delete': echo "You are not allowed to delete this user."; break;
            case 'delete_failed': echo "Failed to delete user. Please try again."; break;
            default: echo "An unknown error occurred."; break;
          }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-striped table-bordered">
        <thead class="table-primary">
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th> 
            <th>Phone</th>
            <th>Role</th>
            <th>Created At</th>
            <th class="actions-header">Actions</th> <!-- Add class for styling -->
          </tr>
        </thead>
        <tbody>
          <?php if (count($users) > 0): ?>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><?= htmlspecialchars($user['id']) ?></td>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= htmlspecialchars($user['phone']) ?></td>
                <td><?= htmlspecialchars($user['role']) ?></td>
                <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                <td>
                  <a href="admin_users_edit.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-warning">
                    <i class="bi bi-pencil-fill"></i> Edit
                  </a>
                  <a href="delete_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">
                    <i class="bi bi-trash-fill"></i> Delete
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7" class="text-center">No users found.</td></tr>
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
