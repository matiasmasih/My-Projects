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
    $confirm_delete = isset($_POST['confirm_delete']) ? $_POST['confirm_delete'] : '';
    
    if ($confirm_delete === 'yes') {
        try {
            $pdo->beginTransaction();
            
            // Check if this is the only stock entry for this batch
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pharmacy_stock WHERE medicine_batch_id = ?");
            $stmt->execute([$stock_item['medicine_batch_id']]);
            $batch_usage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete the stock entry
            $stmt = $pdo->prepare("DELETE FROM pharmacy_stock WHERE id = ?");
            $stmt->execute([$stock_id]);
            
            // If this was the only stock entry for the batch, delete the batch too
            if ($batch_usage['count'] == 1) {
                $stmt = $pdo->prepare("DELETE FROM medicine_batches WHERE id = ?");
                $stmt->execute([$stock_item['medicine_batch_id']]);
                
                // Optional: Also delete medicine if no batches left? 
                // Probably not, as medicine might be used in other batches
            }
            
            // Log the deletion activity (optional - create deletion_logs table if needed)
            // $stmt = $pdo->prepare("INSERT INTO deletion_logs (table_name, record_id, deleted_by, deleted_at, reason) VALUES (?, ?, ?, NOW(), ?)");
            // $stmt->execute(['pharmacy_stock', $stock_id, $_SESSION['user_id'], 'Manual deletion from pharmacy stock']);
            
            $pdo->commit();
            
            // Redirect back to pharmacy stock page with success message
            header("Location: pharmacy_stock.php?deleted=1");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        // User cancelled deletion
        header("Location: pharmacy_stock.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delete Stock Item</title>
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
  max-width: 700px;
  margin: 0 auto;
}

/* Warning Card */
.warning-card {
  background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
  color: white;
  border-radius: 12px;
  padding: 25px;
  margin-bottom: 30px;
  text-align: center;
}
.warning-card h4 {
  margin-bottom: 15px;
  font-weight: 600;
}

.warning-icon {
  font-size: 3rem;
  margin-bottom: 15px;
  display: block;
}

/* Stock Info Card */
.stock-info-card {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 25px;
  border-left: 4px solid #dc3545;
}

/* Form Styling */
.form-container {
  background: #fff;
  border-radius: 12px;
  padding: 25px;
  border: 2px solid #e9ecef;
}

.btn-delete {
  background: #dc3545;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 10px 25px;
  font-weight: 500;
}
.btn-delete:hover {
  background: #c82333;
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

.confirmation-options {
  display: flex;
  gap: 15px;
  justify-content: center;
  margin-top: 20px;
}

.confirm-option {
  border: 2px solid #e9ecef;
  border-radius: 8px;
  padding: 15px 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
  flex: 1;
  max-width: 200px;
}
.confirm-option:hover {
  transform: translateY(-2px);
}
.confirm-option.yes:hover {
  border-color: #dc3545;
  background-color: #f8d7da;
}
.confirm-option.no:hover {
  border-color: #28a745;
  background-color: #d4edda;
}
.confirm-option.selected {
  transform: translateY(-2px);
}
.confirm-option.yes.selected {
  border-color: #dc3545;
  background-color: #f8d7da;
  color: #dc3545;
}
.confirm-option.no.selected {
  border-color: #28a745;
  background-color: #d4edda;
  color: #28a745;
}

.confirm-option i {
  font-size: 1.5rem;
  margin-bottom: 8px;
  display: block;
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Delete Stock Item</a>
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
      
      <!-- Warning Card -->
      <div class="warning-card">
        <i class="bi bi-exclamation-triangle warning-icon"></i>
        <h4>Delete Stock Item</h4>
        <p class="mb-0">This action cannot be undone. Please confirm you want to delete this stock item.</p>
      </div>

      <!-- Stock Information -->
      <div class="stock-info-card">
        <h5 class="text-danger mb-3"><i class="bi bi-capsule me-2"></i>Item to be Deleted</h5>
        <div class="row">
          <div class="col-md-6">
            <p class="mb-2"><strong>Medicine:</strong> <?= htmlspecialchars($stock_item['medicine_name']) ?></p>
            <?php if (!empty($stock_item['generic_name'])): ?>
              <p class="mb-2"><strong>Generic Name:</strong> <?= htmlspecialchars($stock_item['generic_name']) ?></p>
            <?php endif; ?>
            <p class="mb-2"><strong>Brand:</strong> <?= htmlspecialchars($stock_item['brand']) ?></p>
            <p class="mb-2"><strong>Form:</strong> <?= htmlspecialchars($stock_item['form']) ?> <?= htmlspecialchars($stock_item['strength']) ?></p>
          </div>
          <div class="col-md-6">
            <p class="mb-2"><strong>Batch Number:</strong> <?= htmlspecialchars($stock_item['batch_number']) ?></p>
            <p class="mb-2"><strong>Expiry Date:</strong> <?= date('Y-m-d', strtotime($stock_item['expiry_date'])) ?></p>
            <p class="mb-2"><strong>Current Stock:</strong> <?= $stock_item['quantity'] ?> <?= htmlspecialchars($stock_item['unit']) ?></p>
            <p class="mb-0"><strong>Location:</strong> <?= htmlspecialchars($stock_item['location']) ?></p>
          </div>
        </div>
      </div>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <!-- Confirmation Form -->
      <div class="form-container">
        <h5 class="mb-4 text-center">Confirm Deletion</h5>
        <p class="text-center text-muted mb-4">
          Are you sure you want to delete this stock item? This will permanently remove it from the system.
        </p>

        <form method="POST">
          <!-- Confirmation Options -->
          <div class="confirmation-options">
            <div class="confirm-option yes" onclick="selectOption('yes')">
              <i class="bi bi-check-circle"></i>
              <div class="fw-bold">Yes, Delete</div>
              <small class="text-muted">Permanently remove</small>
            </div>
            <div class="confirm-option no" onclick="selectOption('no')">
              <i class="bi bi-x-circle"></i>
              <div class="fw-bold">No, Cancel</div>
              <small class="text-muted">Keep this item</small>
            </div>
          </div>

          <!-- Hidden input for confirmation -->
          <input type="hidden" name="confirm_delete" id="confirm_delete" value="">

          <!-- Submit Button -->
          <div class="text-center mt-4">
            <button type="submit" class="btn btn-delete" id="submitBtn" disabled>
              <i class="bi bi-trash me-2"></i>Confirm Deletion
            </button>
            <a href="pharmacy_stock.php" class="btn btn-cancel ms-2">
              <i class="bi bi-arrow-left me-2"></i>Back to Stock
            </a>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedOption = '';

function selectOption(option) {
    selectedOption = option;
    const confirmInput = document.getElementById('confirm_delete');
    const submitBtn = document.getElementById('submitBtn');
    
    // Update visual selection
    document.querySelectorAll('.confirm-option').forEach(el => {
        el.classList.remove('selected');
    });
    document.querySelector(`.confirm-option.${option}`).classList.add('selected');
    
    // Update hidden input
    confirmInput.value = option;
    
    // Enable/disable submit button
    submitBtn.disabled = false;
    
    // Update button text based on selection
    if (option === 'yes') {
        submitBtn.innerHTML = '<i class="bi bi-trash me-2"></i>Confirm Deletion';
        submitBtn.classList.remove('btn-success');
        submitBtn.classList.add('btn-delete');
    } else {
        submitBtn.innerHTML = '<i class="bi bi-arrow-left me-2"></i>Back to Stock';
        submitBtn.classList.remove('btn-delete');
        submitBtn.classList.add('btn-success');
    }
}

// Prevent form submission if no option selected
document.querySelector('form').addEventListener('submit', function(e) {
    if (!selectedOption) {
        e.preventDefault();
        alert('Please select an option before proceeding.');
    }
});

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // No option selected by default
});
</script>
</body>
</html>
