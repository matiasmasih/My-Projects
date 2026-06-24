<?php
session_start();
require 'config.php';

// Only admins can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Validate user ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_users.php?error=invalid_id');
    exit;
}

$user_id = (int)$_GET['id'];

// Fetch user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: admin_users.php?error=user_not_found');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];

    if ($username && $email && $role) {
        $update = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ?, role = ? WHERE id = ?");
        $update->execute([$username, $email, $phone, $role, $user_id]);
        header('Location: admin_users.php?updated=success');
        exit;
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit User</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
<style>
body {
  background-color: #f1f5f9;
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

.form-container {
  background-color: #ffffff;
  padding: 30px;
  border-radius: 12px;
  max-width: 600px;
  margin: auto;
  box-shadow: 0 0 12px rgba(0,0,0,0.08);
}

</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="admin_dashboard.php">📦 WAREHOUSE</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarWarehouse">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarWarehouse">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-1">
        <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_products.php"><i class="fas fa-boxes"></i> Products</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_suppliers.php"><i class="fas fa-truck"></i> Suppliers</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_locations.php"><i class="fas fa-map-marker-alt"></i> Locations</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_inventory.php"><i class="fas fa-warehouse"></i> Inventory</a></li>
        <li class="nav-item"><a class="nav-link" href="admin_stock_movements.php"><i class="fas fa-exchange-alt"></i> Stock Movements </a></li>
        <li class="nav-item"><a class="nav-link" href="admin_location_history.php"><i class="bi bi-clock-history"></i> Location History</a></li>
        <li class="nav-item"><a class="nav-link active" href="admin_users.php"><i class="bi bi-people"></i> Users</a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Page Content -->
<div class="container mt-4">
  <div class="form-container">
    <h4 class="fw-bold mb-4"><i class="fas fa-user-edit"></i> Edit User</h4>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select" required>
          <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
          <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
          <option value="staff" <?= $user['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
        </select>
      </div>

      <div class="d-flex justify-content-between">
        <a href="admin_users.php" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Back
        </a>
        <button type="submit" class="btn btn-success">
          <i class="fas fa-save"></i> Update
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
