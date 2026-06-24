<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get batch ID from URL
$batch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($batch_id <= 0) {
    die("Invalid batch ID.");
}

// Fetch batch details with medicine information
try {
    $stmt = $pdo->prepare("
        SELECT 
            mb.*,
            m.name as medicine_name,
            m.generic_name,
            m.brand,
            m.form,
            m.strength,
            m.unit,
            ps.quantity,
            ps.min_threshold,
            ps.location
        FROM medicine_batches mb
        LEFT JOIN medicines m ON mb.medicine_id = m.id
        LEFT JOIN pharmacy_stock ps ON mb.id = ps.medicine_batch_id
        WHERE mb.id = ?
    ");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        die("Batch not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_number = $_POST['batch_number'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? '';
    $cost_price = $_POST['cost_price'] ?? 0;
    $selling_price = $_POST['selling_price'] ?? 0;
    $quantity = $_POST['quantity'] ?? 0;
    $min_threshold = $_POST['min_threshold'] ?? 0;
    $location = $_POST['location'] ?? '';

    try {
        $pdo->beginTransaction();

        // Update medicine_batches table
        $stmt = $pdo->prepare("
            UPDATE medicine_batches 
            SET batch_number = ?, expiry_date = ?, cost_price = ?, selling_price = ?
            WHERE id = ?
        ");
        $stmt->execute([$batch_number, $expiry_date, $cost_price, $selling_price, $batch_id]);

        // Update pharmacy_stock table
        $stmt = $pdo->prepare("
            UPDATE pharmacy_stock 
            SET quantity = ?, min_threshold = ?, location = ?
            WHERE medicine_batch_id = ?
        ");
        $stmt->execute([$quantity, $min_threshold, $location, $batch_id]);

        $pdo->commit();

        // Redirect back to pharmacy stock page
        header("Location: medicines.php");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Batch - Hospital Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* General */
body {
  font-family: 'Montserrat', sans-serif;
  background: #f0f2f5;
  margin: 0;
  overflow-x: hidden;
}

/* Navbar */
.navbar {
  background: linear-gradient(135deg, #4b6cb7, #182848);
}
.navbar .navbar-brand, .navbar .nav-link {
  color: #fff;
}
.navbar .nav-link:hover {
  color: #ffd700;
}

/* Sidebar */
.sidebar {
  background-color: #1e1e2f;
  color: #fff;
  min-height: calc(100vh - 56px);
  width: 230px;
  padding: 20px;
}
.sidebar h4 {
  font-weight: 600;
  color: #ffd700;
  margin-bottom: 1rem;
}
.sidebar a {
  display: flex;
  align-items: center;
  color: #c1c1c1;
  text-decoration: none;
  padding: 12px 15px;
  border-radius: 8px;
  margin-bottom: 5px;
  transition: all 0.3s ease;
}
.sidebar a:hover {
  background-color: #4b6cb7;
  color: #fff;
}
.sidebar a.active {
  background-color: #ffd700;
  color: #1e1e2f;
  font-weight: 600;
}

/* Main Content */
.main-content {
  flex: 1;
  padding: 20px;
  height: calc(100vh - 56px);
  overflow: auto;
}

.content-wrapper {
  background: white;
  border-radius: 12px;
  padding: 30px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

/* Form Styling */
.form-container {
  max-width: 900px;
  margin: 0 auto;
}

.form-section {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
}

.form-section h5 {
  color: #4b6cb7;
  border-bottom: 2px solid #4b6cb7;
  padding-bottom: 10px;
  margin-bottom: 20px;
}

/* Buttons */
.btn-primary {
  background: linear-gradient(135deg, #4b6cb7, #182848);
  border: none;
  border-radius: 6px;
  padding: 10px 20px;
  font-weight: 500;
}
.btn-primary:hover {
  background: linear-gradient(135deg, #3a5a9f, #121f3d);
}

.btn-secondary {
  background: #6c757d;
  border: none;
  border-radius: 6px;
  padding: 10px 20px;
  font-weight: 500;
}
.btn-secondary:hover {
  background: #5a6268;
}

/* Readonly fields */
.form-control[readonly] {
  background-color: #e9ecef;
  opacity: 1;
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">
      <i class="bi bi-capsule me-2"></i>Hospital Management
    </a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="d-flex">

  <!-- Sidebar -->
  <div class="sidebar">
    <h4>Dashboard Menu</h4>
    <a href="manager_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
    <a href="users.php"><i class="bi bi-people-fill me-2"></i>Users</a>
    <a href="roles.php"><i class="bi bi-shield-lock me-2"></i>Roles</a>
    <a href="patients.php"><i class="bi bi-person-fill me-2"></i>Patients</a>
    <a href="doctors.php"><i class="bi bi-person-badge me-2"></i>Doctors</a>
    <a href="appointments.php"><i class="bi bi-calendar-check me-2"></i>Appointments</a>
    <a href="invoices.php"><i class="bi bi-receipt me-2"></i>Invoices</a>
    <a href="payments.php"><i class="bi bi-cash-stack me-2"></i>Payments</a>
    <a href="pharmacy_stock.php"><i class="bi bi-capsule me-2"></i>Pharmacy</a>
    <a href="medicines.php" class="active"><i class="bi bi-heart-pulse me-2"></i>Medicines</a>
    <a href="wards.php"><i class="bi bi-house-door me-2"></i>Wards</a>
    <a href="rooms.php"><i class="bi bi-door-closed me-2"></i>Rooms</a>
    <a href="messages.php"><i class="bi bi-chat-dots me-2"></i>Messages</a>
    <a href="admissions.php"><i class="bi bi-journal-plus me-2"></i>Admissions</a>
    <a href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="content-wrapper">

      <!-- Page Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
          <i class="bi bi-pencil-square me-2"></i>Edit Medicine Batch
        </h2>
        <a href="medicines.php" class="btn btn-secondary">
          <i class="bi bi-arrow-left me-2"></i>Back to Medicines
        </a>
      </div>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <div class="form-container">
        <form method="POST">

          <!-- Medicine Information Section -->
          <div class="form-section">
            <h5><i class="bi bi-capsule me-2"></i>Medicine Information</h5>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Medicine Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($batch['medicine_name']) ?>" readonly>
              </div>

              <div class="col-md-6">
                <label class="form-label">Generic Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($batch['generic_name']) ?>" readonly>
              </div>

              <div class="col-md-6">
                <label class="form-label">Brand</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($batch['brand']) ?>" readonly>
              </div>

              <div class="col-md-6">
                <label class="form-label">Form & Strength</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($batch['form'] . ' - ' . $batch['strength']) ?>" readonly>
              </div>
            </div>
          </div>

          <!-- Batch Information Section -->
          <div class="form-section">
            <h5><i class="bi bi-box-seam me-2"></i>Batch Information</h5>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="batch_number" class="form-label">Batch Number *</label>
                <input type="text" class="form-control" id="batch_number" name="batch_number" 
                       value="<?= htmlspecialchars($batch['batch_number']) ?>" required>
              </div>

              <div class="col-md-6">
                <label for="expiry_date" class="form-label">Expiry Date *</label>
                <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                       value="<?= htmlspecialchars($batch['expiry_date']) ?>" required>
              </div>

              <div class="col-md-6">
                <label for="cost_price" class="form-label">Cost Price ($) *</label>
                <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price" 
                       value="<?= htmlspecialchars($batch['cost_price']) ?>" required>
              </div>

              <div class="col-md-6">
                <label for="selling_price" class="form-label">Selling Price ($) *</label>
                <input type="number" step="0.01" class="form-control" id="selling_price" name="selling_price" 
                       value="<?= htmlspecialchars($batch['selling_price']) ?>" required>
              </div>
            </div>
          </div>

          <!-- Stock Information Section -->
          <div class="form-section">
            <h5><i class="bi bi-clipboard-data me-2"></i>Stock Information</h5>
            <div class="row g-3">
              <div class="col-md-4">
                <label for="quantity" class="form-label">Quantity *</label>
                <input type="number" class="form-control" id="quantity" name="quantity" 
                       value="<?= htmlspecialchars($batch['quantity']) ?>" min="0" required>
              </div>

              <div class="col-md-4">
                <label for="min_threshold" class="form-label">Minimum Threshold *</label>
                <input type="number" class="form-control" id="min_threshold" name="min_threshold" 
                       value="<?= htmlspecialchars($batch['min_threshold']) ?>" min="0" required>
              </div>

              <div class="col-md-4">
                <label for="location" class="form-label">Location *</label>
                <select class="form-select" id="location" name="location" required>
                  <option value="main_pharmacy" <?= $batch['location'] == 'main_pharmacy' ? 'selected' : '' ?>>Main Pharmacy</option>
                  <option value="ward_a" <?= $batch['location'] == 'ward_a' ? 'selected' : '' ?>>Ward A</option>
                  <option value="ward_b" <?= $batch['location'] == 'ward_b' ? 'selected' : '' ?>>Ward B</option>
                  <option value="emergency" <?= $batch['location'] == 'emergency' ? 'selected' : '' ?>>Emergency</option>
                  <option value="storage" <?= $batch['location'] == 'storage' ? 'selected' : '' ?>>Storage</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Form Actions -->
          <div class="d-flex justify-content-end gap-3 mt-4">
            <a href="medicines.php" class="btn btn-secondary btn-lg">
              <i class="bi bi-x-circle me-2"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="bi bi-check-circle me-2"></i>Update Batch
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Set minimum date for expiry date to today
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('expiry_date').min = today;

    // Validate that selling price is higher than cost price
    document.getElementById('selling_price').addEventListener('change', function() {
        const costPrice = parseFloat(document.getElementById('cost_price').value);
        const sellingPrice = parseFloat(this.value);

        if (sellingPrice < costPrice) {
            alert('Selling price should be higher than cost price!');
            this.value = costPrice;
        }
    });
});
</script>
</body>
</html>
