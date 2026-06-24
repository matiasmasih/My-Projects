<?php
session_start();
require 'config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $password         = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $role             = $_POST['role'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = "All fields except phone are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($phone !== '' && !preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
        // Basic phone validation: digits, spaces, dashes, optional + at start
        $error = "Invalid phone number format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user (assuming 'phone' column exists in users table)
        $stmt = $pdo->prepare("INSERT INTO users (username, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$username, $email, $phone, $hashedPassword, $role])) {
            $success = "User added successfully.";
        } else {
            $error = "Error adding user.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Add User - Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
<style>
body {
  background-color: #f1f5f9;
  padding-top: 70px;
}

.navbar {
  background-color: #1e293b;
}

.navbar .nav-link,
.navbar .navbar-brand {
  color: #fff !important;
}

.navbar .nav-link:hover {
  color: #cbd5e1 !important;
}

.form-container {
  background: white;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-white" href="admin_dashboard.php">📦 WAREHOUSE</a>
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

<!-- Main Content -->
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="form-container bg-white p-5 shadow rounded-4 border border-light-subtle">
        <h4 class="mb-4 fw-bold text-primary">
          <i class="fas fa-user-plus me-2"></i>Add New User
        </h4>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" onsubmit="return validatePasswords()">
          <div class="mb-3">
            <label for="username" class="form-label fw-semibold">Username</label>
            <input type="text" name="username" id="username" class="form-control form-control-lg rounded-3" required />
          </div>

          <div class="mb-3">
            <label for="email" class="form-label fw-semibold">Email Address</label>
            <input type="email" name="email" id="email" class="form-control form-control-lg rounded-3" required />
          </div>

          <div class="mb-3">
            <label for="phone" class="form-label fw-semibold">Phone (optional)</label>
            <input type="tel" name="phone" id="phone" class="form-control form-control-lg rounded-3" placeholder="+1234567890" />
          </div>

          <div class="mb-3">
            <label for="password" class="form-label fw-semibold">Password</label>
            <input type="password" name="password" id="password" class="form-control form-control-lg rounded-3" required />
          </div>

          <div class="mb-3">
            <label for="confirm_password" class="form-label fw-semibold">Confirm Password</label>
            <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-lg rounded-3" required />
          </div>

          <div class="mb-3">
            <label for="role" class="form-label fw-semibold">Role</label>
            <select name="role" id="role" class="form-select form-select-lg rounded-3" required>
              <option value="">Select Role</option>
              <option value="manager">Manager</option>
              <option value="admin">Admin</option>
              <option value="staff">Staff</option>
            </select>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-2">
            <button type="submit" class="btn btn-success btn-lg px-2">
              <i class="bi bi-person-plus-fill me-1"></i> Add User
            </button>
            <a href="admin_users.php" class="btn btn-outline-secondary btn-lg">
              <i class="bi bi-arrow-right-circle ms-1"></i> Back
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  function validatePasswords() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    if (password !== confirmPassword) {
      alert('Passwords do not match!');
      return false;  // prevent form submission
    }
    return true;
  }
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
