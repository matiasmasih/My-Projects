<?php
session_start();
require __DIR__ . '/config.php';

// Redirect non-admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$name = $contact_person = $email = $phone = $address = "";
$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect & trim input
    $name = trim($_POST['name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Validation
    if (!$name) $errors[] = "Supplier name is required.";
    if (!$contact_person) $errors[] = "Contact person is required.";
    if (!$email) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (!$phone) $errors[] = "Phone is required.";
    if (!$address) $errors[] = "Address is required.";

    if (!$errors) {
        // Insert into DB
        try {
            $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, email, phone, address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $contact_person, $email, $phone, $address]);
            $success = "Supplier added successfully!";
            // Clear form
            $name = $contact_person = $email = $phone = $address = "";
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Add Supplier - Warehouse</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      background: #f1f5f9;
      padding-top: 70px; /* space for fixed navbar */
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .navbar {
      background-color: #1e293b !important;
    }
    .navbar-brand, .nav-link {
      color: #ffffff !important;
      font-weight: 600;
      font-size: 1rem;
    }

  .navbar .nav-link {
    color: white !important;
  }

  .navbar .nav-link:hover {
    color: #9197a1 !important;
  }

    .nav-icon {
      margin-right: 6px;
    }
    .container {
      max-width: 600px;
      background: #fff;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .form-floating > input, .form-floating > textarea {
      height: 50px;
      font-size: 1rem;
    }
    textarea.form-control {
      min-height: 100px;
      resize: vertical;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top" style="background-color: #1e293b;">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-white" href="admin_dashboard.php">📦 WAREHOUSE</a>
    <button
      class="navbar-toggler"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#navbarWarehouse"
      aria-controls="navbarWarehouse"
      aria-expanded="false"
      aria-label="Toggle navigation"
    >
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarWarehouse">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 gap-1">
        <li class="nav-item">
          <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-chart-line nav-icon"></i>Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_products.php"><i class="fas fa-boxes nav-icon"></i>Products</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="admin_suppliers.php"><i class="fas fa-truck nav-icon"></i>Suppliers</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_locations.php"><i class="fas fa-map-marker-alt nav-icon"></i>Locations</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#"><i class="fas fa-warehouse nav-icon"></i>Inventory</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="admin_stock_movements.php"><i class="fas fa-exchange-alt"></i> Stock Movement</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_location_history.php"><i class="bi bi-clock-history"></i> Location History</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="admin_users.php"><i class="bi bi-people nav-icon"></i>Users</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt nav-icon"></i>Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container">
  <div class="form-container">
    <div class="form-header">
      <h4 class="mb-0">Add New Supplier</h4>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger mt-3">
        <ul class="mb-0">
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php elseif ($success): ?>
      <div class="alert alert-success mt-3"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate class="mt-4">
      <div class="mb-3">
        <label for="name" class="form-label">Supplier Name <span class="text-danger">*</span></label>
        <input type="text" id="name" name="name" class="form-control" required
               value="<?= htmlspecialchars($name ?? '') ?>">
      </div>

      <div class="mb-3">
        <label for="contact_person" class="form-label">Contact Person <span class="text-danger">*</span></label>
        <input type="text" id="contact_person" name="contact_person" class="form-control" required
               value="<?= htmlspecialchars($contact_person ?? '') ?>">
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" id="email" name="email" class="form-control" required
               value="<?= htmlspecialchars($email ?? '') ?>">
      </div>

      <div class="mb-3">
        <label for="phone" class="form-label">Phone <span class="text-danger">*</span></label>
        <input type="text" id="phone" name="phone" class="form-control" required
               value="<?= htmlspecialchars($phone ?? '') ?>">
      </div>

      <div class="mb-3">
        <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
        <textarea id="address" name="address" class="form-control" rows="3" required><?= htmlspecialchars($address ?? '') ?></textarea>
      </div>

      <!-- ✅ Custom Button Group -->
     <div class="d-flex justify-content-between mt-4">
  <!-- Save button on the left -->
  <button type="submit" class="btn btn-success px-4">
    💾 Save
  </button>

  <!-- Back button on the right -->
  <a href="admin_suppliers.php" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left"></i> Back to Suppliers
  </a>
</div>
    </form>
  </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
