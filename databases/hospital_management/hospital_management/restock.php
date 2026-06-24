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
            m.unit
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
    $quantity_to_add = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $new_batch_number = isset($_POST['batch_number']) ? trim($_POST['batch_number']) : '';
    $new_expiry_date = isset($_POST['expiry_date']) ? $_POST['expiry_date'] : '';
    $new_cost_price = isset($_POST['cost_price']) ? (float)$_POST['cost_price'] : 0;
    $new_selling_price = isset($_POST['selling_price']) ? (float)$_POST['selling_price'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    $errors = [];

    // Validation
    if ($quantity_to_add <= 0) {
        $errors[] = "Please enter a valid quantity to add.";
    }
    if (empty($new_batch_number)) {
        $errors[] = "Batch number is required.";
    }
    if (empty($new_expiry_date)) {
        $errors[] = "Expiry date is required.";
    }
    if ($new_cost_price < 0) {
        $errors[] = "Cost price cannot be negative.";
    }
    if ($new_selling_price < 0) {
        $errors[] = "Selling price cannot be negative.";
    }
    if ($new_expiry_date && strtotime($new_expiry_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Expiry date cannot be in the past.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Check if we're using same batch or creating new batch
            $use_same_batch = isset($_POST['use_same_batch']) && $_POST['use_same_batch'] == '1';

            if ($use_same_batch) {
                // Update existing stock quantity
                $stmt = $pdo->prepare("
                    UPDATE pharmacy_stock
                    SET quantity = quantity + ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$quantity_to_add, $stock_id]);
            } else {
                // Create new batch and stock entry
                // 1. Create new medicine batch
                $stmt = $pdo->prepare("
                    INSERT INTO medicine_batches (medicine_id, batch_number, expiry_date, cost_price, selling_price)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $stock_item['medicine_id'],
                    $new_batch_number,
                    $new_expiry_date,
                    $new_cost_price,
                    $new_selling_price
                ]);
                $new_batch_id = $pdo->lastInsertId();

                // 2. Create new stock entry
                $stmt = $pdo->prepare("
                    INSERT INTO pharmacy_stock (medicine_batch_id, quantity, min_threshold, location) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $new_batch_id,
                    $quantity_to_add,
                    $stock_item['min_threshold'],
                    $stock_item['location']
                ]);
            }

            // Log the restock activity
            // $stmt = $pdo->prepare("INSERT INTO restock_logs (stock_id, quantity_added, batch_number, expiry_date, notes, restocked_by, restocked_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            // $stmt->execute([$stock_id, $quantity_to_add, $new_batch_number, $new_expiry_date, $notes, $_SESSION['user_id']]);

            $pdo->commit();

            // Redirect back to pharmacy stock page
            header("Location: pharmacy_stock.php?restocked=1");
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
<title>Restock Medicine</title>
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
  max-width: 900px;
  margin: 0 auto;
}

/* Stock Info Card */
.stock-info-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border-radius: 12px;
  padding: 25px;
  margin-bottom: 30px;
}
.stock-info-card h4 {
  margin-bottom: 15px;
  font-weight: 600;
}

/* Form Styling */
.form-container {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 25px;
  border: 1px solid #e9ecef;
}

.btn-restock {
  background: #28a745;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 10px 25px;
  font-weight: 500;
}
.btn-restock:hover {
  background: #218838;
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

.batch-option {
  border: 2px solid #e9ecef;
  border-radius: 8px;
  padding: 15px;
  margin-bottom: 15px;
  cursor: pointer;
  transition: all 0.3s ease;
}
.batch-option:hover {
  border-color: #007bff;
  background-color: #f8f9fa;
}
.batch-option.selected {
  border-color: #28a745;
  background-color: #d4edda;
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Restock Medicine</a>
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

      <!-- Stock Information -->
      <div class="stock-info-card">
        <h4><i class="bi bi-capsule me-2"></i>Restock Medicine</h4>
        <div class="row">
          <div class="col-md-6">
            <h5><?= htmlspecialchars($stock_item['medicine_name']) ?></h5>
            <?php if (!empty($stock_item['generic_name'])): ?>
              <p class="mb-1">Generic: <?= htmlspecialchars($stock_item['generic_name']) ?></p>
            <?php endif; ?>
            <p class="mb-1">Brand: <?= htmlspecialchars($stock_item['brand']) ?></p>
            <p class="mb-1">Form: <?= htmlspecialchars($stock_item['form']) ?> <?= htmlspecialchars($stock_item['strength']) ?></p>
          </div>
          <div class="col-md-6">
            <p class="mb-1">Current Batch: <?= htmlspecialchars($stock_item['batch_number']) ?></p>
            <p class="mb-1">Current Expiry: <?= date('Y-m-d', strtotime($stock_item['expiry_date'])) ?></p>
            <p class="mb-1">Current Stock: <strong><?= $stock_item['quantity'] ?> <?= htmlspecialchars($stock_item['unit']) ?></strong></p>
            <p class="mb-0">Min Threshold: <?= $stock_item['min_threshold'] ?> <?= htmlspecialchars($stock_item['unit']) ?></p>
          </div>
        </div>
      </div>

      <!-- Restock Form -->
      <div class="form-container">
        <h5 class="mb-4"><i class="bi bi-arrow-repeat me-2"></i>Restock Options</h5>

        <?php if (isset($error)): ?>
          <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
          <!-- Batch Options -->
          <div class="mb-4">
            <label class="form-label fw-bold">Restock Option:</label>

            <!-- Option 1: Same Batch -->
            <div class="batch-option" onclick="selectBatchOption('same')">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="use_same_batch" id="same_batch" value="1" checked>
                <label class="form-check-label fw-bold" for="same_batch">
                  Add to Current Batch
                </label>
              </div>
              <div class="ms-4 mt-2">
                <small class="text-muted">Add quantity to existing batch: <strong><?= htmlspecialchars($stock_item['batch_number']) ?></strong> (Expires: <?= date('Y-m-d', strtotime($stock_item['expiry_date'])) ?>)</small>
              </div>
            </div>

            <!-- Option 2: New Batch -->
            <div class="batch-option" onclick="selectBatchOption('new')">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="use_same_batch" id="new_batch" value="0">
                <label class="form-check-label fw-bold" for="new_batch">
                  Create New Batch
                </label>
              </div>
              <div class="ms-4 mt-2">
                <small class="text-muted">Create a new batch with different batch number and expiry date</small>
              </div>
            </div>
          </div>

          <!-- Quantity -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="current_quantity" class="form-label">Current Quantity</label>
              <input type="text" class="form-control" id="current_quantity" value="<?= $stock_item['quantity'] ?> <?= htmlspecialchars($stock_item['unit']) ?>" readonly>
            </div>
            <div class="col-md-6">
              <label for="quantity" class="form-label">Quantity to Add *</label>
              <input type="number" name="quantity" id="quantity" class="form-control" min="1" max="10000" required>
              <div class="form-text">Enter the quantity you want to add.</div>
            </div>
          </div>

          <!-- New Batch Details (Initially hidden) -->
          <div id="new_batch_details" style="display: none;">
            <hr>
            <h6 class="mb-3">New Batch Details</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="batch_number" class="form-label">New Batch Number *</label>
                <input type="text" name="batch_number" id="batch_number" class="form-control" value="<?= htmlspecialchars($stock_item['batch_number']) ?>-NEW">
              </div>
              <div class="col-md-6">
                <label for="expiry_date" class="form-label">New Expiry Date *</label>
                <input type="date" name="expiry_date" id="expiry_date" class="form-control" min="<?= date('Y-m-d') ?>">
              </div>
              <div class="col-md-6">
                <label for="cost_price" class="form-label">Cost Price ($)</label>
                <input type="number" step="0.01" name="cost_price" id="cost_price" class="form-control" value="<?= $stock_item['cost_price'] ?>" min="0">
              </div>
              <div class="col-md-6">
                <label for="selling_price" class="form-label">Selling Price ($)</label>
                <input type="number" step="0.01" name="selling_price" id="selling_price" class="form-control" value="<?= $stock_item['selling_price'] ?>" min="0">
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="mb-4">
            <label for="notes" class="form-label">Restock Notes (Optional)</label>
            <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Any notes about this restock..."></textarea>
          </div>

          <!-- Buttons -->
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-restock">
              <i class="bi bi-check-circle me-2"></i>Confirm Restock
            </button>
            <a href="pharmacy_stock.php" class="btn btn-cancel">
              <i class="bi bi-x-circle me-2"></i>Cancel
            </a>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function selectBatchOption(option) {
    const sameBatchRadio = document.getElementById('same_batch');
    const newBatchRadio = document.getElementById('new_batch');
    const newBatchDetails = document.getElementById('new_batch_details');

    if (option === 'same') {
        sameBatchRadio.checked = true;
        newBatchDetails.style.display = 'none';
        document.querySelectorAll('.batch-option').forEach(el => el.classList.remove('selected'));
        document.querySelector('#same_batch').closest('.batch-option').classList.add('selected');
    } else {
        newBatchRadio.checked = true;
        newBatchDetails.style.display = 'block';
        document.querySelectorAll('.batch-option').forEach(el => el.classList.remove('selected'));
        document.querySelector('#new_batch').closest('.batch-option').classList.add('selected');
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    selectBatchOption('same'); // Default to same batch
    document.getElementById('quantity').focus();

    // Set minimum date for expiry date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('expiry_date').min = today;
});

// Show new total preview
document.getElementById('quantity').addEventListener('input', function() {
    const currentQty = <?= $stock_item['quantity'] ?>;
    const addQty = parseInt(this.value) || 0;
    const newTotal = currentQty + addQty;

    // You could display this preview to the user
    console.log('New total will be:', newTotal);
});
</script>
</body>
</html>
