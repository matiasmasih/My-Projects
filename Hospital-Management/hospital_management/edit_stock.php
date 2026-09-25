<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get the stock item ID from URL
$stock_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($stock_id <= 0) {
    die("Invalid stock ID.");
}

// Fetch current stock details
try {
    $stmt = $pdo->prepare("
        SELECT
            ps.id,
            ps.medicine_batch_id,
            ps.quantity,
            ps.min_threshold,
            ps.location,
            mb.batch_number,
            mb.expiry_date,
            mb.cost_price,
            mb.selling_price,
            m.id as medicine_id,
            m.name as medicine_name,
            m.generic_name,
            m.brand,
            m.form,
            m.strength,
            m.unit,
            m.description
        FROM pharmacy_stock ps
        LEFT JOIN medicine_batches mb ON ps.medicine_batch_id = mb.id
        LEFT JOIN medicines m ON mb.medicine_id = m.id
        WHERE ps.id = ?
    ");
    $stmt->execute([$stock_id]);
    $stock_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stock_item) {
        die("Stock item not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $min_threshold = isset($_POST['min_threshold']) ? (int)$_POST['min_threshold'] : 0;
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $batch_number = isset($_POST['batch_number']) ? trim($_POST['batch_number']) : '';
    $expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : '';
    $cost_price = isset($_POST['cost_price']) ? (float)$_POST['cost_price'] : 0;
    $selling_price = isset($_POST['selling_price']) ? (float)$_POST['selling_price'] : 0;
    $medicine_name = isset($_POST['medicine_name']) ? trim($_POST['medicine_name']) : '';
    $generic_name = isset($_POST['generic_name']) ? trim($_POST['generic_name']) : '';
    $brand = isset($_POST['brand']) ? trim($_POST['brand']) : '';
    $form = isset($_POST['form']) ? trim($_POST['form']) : '';
    $strength = isset($_POST['strength']) ? trim($_POST['strength']) : '';
    $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    $errors = [];

    // Validation
    if ($quantity < 0) {
        $errors[] = "Quantity cannot be negative.";
    }
    if ($min_threshold < 0) {
        $errors[] = "Minimum threshold cannot be negative.";
    }
    if (empty($location)) {
        $errors[] = "Location is required.";
    }
    if (empty($batch_number)) {
        $errors[] = "Batch number is required.";
    }
    if (empty($expiry_date)) {
        $errors[] = "Expiry date is required.";
    }
    if (empty($medicine_name)) {
        $errors[] = "Medicine name is required.";
    }
    if ($cost_price < 0) {
        $errors[] = "Cost price cannot be negative.";
    }
    if ($selling_price < 0) {
        $errors[] = "Selling price cannot be negative.";
    }
    if ($expiry_date && strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Expiry date cannot be in the past.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update medicine details
            $stmt = $pdo->prepare("
                UPDATE medicines
                SET name = ?, generic_name = ?, brand = ?, form = ?, strength = ?, unit = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $medicine_name,
                $generic_name,
                $brand,
                $form,
                $strength,
                $unit,
                $description,
                $stock_item['medicine_id']
            ]);

            // Update batch details
            $stmt = $pdo->prepare("
                UPDATE medicine_batches
                SET batch_number = ?, expiry_date = ?, cost_price = ?, selling_price = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $batch_number,
                $expiry_date,
                $cost_price,
                $selling_price,
                $stock_item['medicine_batch_id']
            ]);

            // Update stock details
            $stmt = $pdo->prepare("
                UPDATE pharmacy_stock
                SET quantity = ?, min_threshold = ?, location = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $quantity,
                $min_threshold,
                $location,
                $stock_id
            ]);

            $pdo->commit();

            // Redirect back to pharmacy stock page with success message
            header("Location: pharmacy_stock.php?updated=1");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Stock Item</title>
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
  max-width: 1000px;
  margin: 0 auto;
}

/* Header Card */
.header-card {
  background: linear-gradient(135deg, #36d1dc, #5b86e5);
  color: white;
  border-radius: 12px;
  padding: 25px;
  margin-bottom: 30px;
}
.header-card h4 {
  margin-bottom: 10px;
  font-weight: 600;
}

/* Form Styling */
.form-container {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 25px;
  border: 1px solid #e9ecef;
}

.form-section {
  background: white;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 25px;
  border-left: 4px solid #36d1dc;
}
.form-section h6 {
  color: #36d1dc;
  margin-bottom: 20px;
  font-weight: 600;
}

.btn-update {
  background: #007bff;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 10px 25px;
  font-weight: 500;
}
.btn-update:hover {
  background: #0056b3;
}

.btn-cancel {
  background: #6c757d;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 10px 25px;
  font-weight: 500;
}
.btn-cancel:hover {
  background: #5a6268;
}

.alert {
  border-radius: 8px;
  border: none;
}

/* Status indicator */
.status-indicator {
  display: inline-block;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  margin-right: 8px;
}
.status-in-stock { background-color: #28a745; }
.status-low-stock { background-color: #ffc107; }
.status-out-of-stock { background-color: #dc3545; }
.status-expired { background-color: #6c757d; }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Edit Stock Item</a>
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
    <a href="pharmacy_stock.php" class="active"><i class="bi bi-capsule me-2"></i>Pharmacy</a>
    <a href="medicines.php"><i class="bi bi-heart-pulse me-2"></i>Medicines</a>
    <a href="wards.php"><i class="bi bi-house-door me-2"></i>Wards</a>
    <a href="rooms.php"><i class="bi bi-door-closed me-2"></i>Rooms</a>
    <a href="messages.php"><i class="bi bi-chat-dots me-2"></i>Messages</a>
    <a href="admissions.php"><i class="bi bi-journal-plus me-2"></i>Admissions</a>
    <a href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="content-wrapper">

      <!-- Header -->
      <div class="header-card">
        <h4><i class="bi bi-pencil-square me-2"></i>Edit Stock Item</h4>
        <p class="mb-0">Update the details of this medicine stock item.</p>
      </div>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <!-- Edit Form -->
      <div class="form-container">
        <form method="POST">

          <!-- Medicine Information Section -->
          <div class="form-section">
            <h6><i class="bi bi-capsule me-2"></i>Medicine Information</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="medicine_name" class="form-label">Medicine Name *</label>
                <input type="text" name="medicine_name" id="medicine_name" class="form-control"
                       value="<?= htmlspecialchars($stock_item['medicine_name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label for="generic_name" class="form-label">Generic Name</label>
                <input type="text" name="generic_name" id="generic_name" class="form-control"
                       value="<?= htmlspecialchars($stock_item['generic_name']) ?>">
              </div>
              <div class="col-md-6">
                <label for="brand" class="form-label">Brand</label>
                <input type="text" name="brand" id="brand" class="form-control"
                       value="<?= htmlspecialchars($stock_item['brand']) ?>">
              </div>
              <div class="col-md-6">
                <label for="form" class="form-label">Form *</label>
                <select name="form" id="form" class="form-select" required>
                  <option value="">Select Form</option>
                  <option value="Tablet" <?= $stock_item['form'] == 'Tablet' ? 'selected' : '' ?>>Tablet</option>
                  <option value="Capsule" <?= $stock_item['form'] == 'Capsule' ? 'selected' : '' ?>>Capsule</option>
                  <option value="Syrup" <?= $stock_item['form'] == 'Syrup' ? 'selected' : '' ?>>Syrup</option>
                  <option value="Injection" <?= $stock_item['form'] == 'Injection' ? 'selected' : '' ?>>Injection</option>
                  <option value="Ointment" <?= $stock_item['form'] == 'Ointment' ? 'selected' : '' ?>>Ointment</option>
                  <option value="Drops" <?= $stock_item['form'] == 'Drops' ? 'selected' : '' ?>>Drops</option>
                  <option value="Inhaler" <?= $stock_item['form'] == 'Inhaler' ? 'selected' : '' ?>>Inhaler</option>
                  <option value="Other" <?= $stock_item['form'] == 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="strength" class="form-label">Strength</label>
                <input type="text" name="strength" id="strength" class="form-control"
                       value="<?= htmlspecialchars($stock_item['strength']) ?>" placeholder="e.g., 500mg">
              </div>
              <div class="col-md-6">
                <label for="unit" class="form-label">Unit *</label>
                <select name="unit" id="unit" class="form-select" required>
                  <option value="">Select Unit</option>
                  <option value="tablet" <?= $stock_item['unit'] == 'tablet' ? 'selected' : '' ?>>Tablet</option>
                  <option value="capsule" <?= $stock_item['unit'] == 'capsule' ? 'selected' : '' ?>>Capsule</option>
                  <option value="bottle" <?= $stock_item['unit'] == 'bottle' ? 'selected' : '' ?>>Bottle</option>
                  <option value="vial" <?= $stock_item['unit'] == 'vial' ? 'selected' : '' ?>>Vial</option>
                  <option value="tube" <?= $stock_item['unit'] == 'tube' ? 'selected' : '' ?>>Tube</option>
                  <option value="inhaler" <?= $stock_item['unit'] == 'inhaler' ? 'selected' : '' ?>>Inhaler</option>
                  <option value="pack" <?= $stock_item['unit'] == 'pack' ? 'selected' : '' ?>>Pack</option>
                </select>
              </div>
              <div class="col-12">
                <label for="description" class="form-label">Description</label>
                <textarea name="description" id="description" class="form-control" rows="3"><?= htmlspecialchars($stock_item['description']) ?></textarea>
              </div>
            </div>
          </div>

          <!-- Batch Information Section -->
          <div class="form-section">
            <h6><i class="bi bi-box me-2"></i>Batch Information</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="batch_number" class="form-label">Batch Number *</label>
                <input type="text" name="batch_number" id="batch_number" class="form-control"
                       value="<?= htmlspecialchars($stock_item['batch_number']) ?>" required>
              </div>
              <div class="col-md-6">
                <label for="expiry_date" class="form-label">Expiry Date *</label>
                <input type="date" name="expiry_date" id="expiry_date" class="form-control"
                       value="<?= $stock_item['expiry_date'] ?>" required>
              </div>
              <div class="col-md-6">
                <label for="cost_price" class="form-label">Cost Price ($)</label>
                <input type="number" step="0.01" name="cost_price" id="cost_price" class="form-control"
                       value="<?= $stock_item['cost_price'] ?>" min="0">
              </div>
              <div class="col-md-6">
                <label for="selling_price" class="form-label">Selling Price ($)</label>
                <input type="number" step="0.01" name="selling_price" id="selling_price" class="form-control"
                       value="<?= $stock_item['selling_price'] ?>" min="0">
              </div>
            </div>
          </div>

          <!-- Stock Information Section -->
          <div class="form-section">
            <h6><i class="bi bi-clipboard-data me-2"></i>Stock Information</h6>
            <div class="row g-3">
              <div class="col-md-4">
                <label for="quantity" class="form-label">Current Quantity *</label>
                <input type="number" name="quantity" id="quantity" class="form-control" 
                       value="<?= $stock_item['quantity'] ?>" min="0" required>
                <div class="form-text">
                  <?php
                  $statusClass = '';
                  $statusText = '';
                  if (strtotime($stock_item['expiry_date']) < strtotime(date('Y-m-d'))) {
                      $statusClass = 'status-expired';
                      $statusText = 'Expired';
                  } elseif ($stock_item['quantity'] <= 0) {
                      $statusClass = 'status-out-of-stock';
                      $statusText = 'Out of Stock';
                  } elseif ($stock_item['quantity'] < $stock_item['min_threshold']) {
                      $statusClass = 'status-low-stock';
                      $statusText = 'Low Stock';
                  } else {
                      $statusClass = 'status-in-stock';
                      $statusText = 'In Stock';
                  }
                  ?>
                  Status: <span class="<?= $statusClass ?>"></span> <?= $statusText ?>
                </div>
              </div>
              <div class="col-md-4">
                <label for="min_threshold" class="form-label">Minimum Threshold *</label>
                <input type="number" name="min_threshold" id="min_threshold" class="form-control" 
                       value="<?= $stock_item['min_threshold'] ?>" min="0" required>
                <div class="form-text">Low stock alert when quantity falls below this.</div>
              </div>
              <div class="col-md-4">
                <label for="location" class="form-label">Location *</label>
                <select name="location" id="location" class="form-select" required>
                  <option value="">Select Location</option>
                  <option value="main_pharmacy" <?= $stock_item['location'] == 'main_pharmacy' ? 'selected' : '' ?>>Main Pharmacy</option>
                  <option value="ward_a" <?= $stock_item['location'] == 'ward_a' ? 'selected' : '' ?>>Ward A</option>
                  <option value="ward_b" <?= $stock_item['location'] == 'ward_b' ? 'selected' : '' ?>>Ward B</option>
                  <option value="emergency" <?= $stock_item['location'] == 'emergency' ? 'selected' : '' ?>>Emergency Department</option>
                  <option value="surgery" <?= $stock_item['location'] == 'surgery' ? 'selected' : '' ?>>Surgery Department</option>
                  <option value="storage" <?= $stock_item['location'] == 'storage' ? 'selected' : '' ?>>Storage Room</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="d-flex gap-2 justify-content-end">
            <a href="pharmacy_stock.php" class="btn btn-cancel">
              <i class="bi bi-x-circle me-2"></i>Cancel
            </a>
            <button type="submit" class="btn btn-update">
              <i class="bi bi-check-circle me-2"></i>Update Stock Item
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

    // Auto-focus on first input
    document.getElementById('medicine_name').focus();
});

// Real-time status update
document.getElementById('quantity').addEventListener('input', updateStatus);
document.getElementById('min_threshold').addEventListener('input', updateStatus);
document.getElementById('expiry_date').addEventListener('change', updateStatus);

function updateStatus() {
    const quantity = parseInt(document.getElementById('quantity').value) || 0;
    const minThreshold = parseInt(document.getElementById('min_threshold').value) || 0;
    const expiryDate = document.getElementById('expiry_date').value;
    const today = new Date().toISOString().split('T')[0];

    let statusText = '';
    let statusClass = '';

    if (expiryDate && expiryDate < today) {
        statusText = 'Expired';
        statusClass = 'status-expired';
    } else if (quantity <= 0) {
        statusText = 'Out of Stock';
        statusClass = 'status-out-of-stock';
    } else if (quantity < minThreshold) {
        statusText = 'Low Stock';
        statusClass = 'status-low-stock';
    } else {
        statusText = 'In Stock';
        statusClass = 'status-in-stock';
    }

    // Update status display (you might want to add a status display element)
    console.log('Status:', statusText);
}
</script>
</body>
</html>
Featur
