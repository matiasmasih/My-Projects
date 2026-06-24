<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get medicine ID from URL
$medicine_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($medicine_id <= 0) {
    die("Invalid medicine ID.");
}

// Fetch medicine data to show what we're deleting
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.name,
            m.generic_name,
            m.brand,
            m.form
        FROM medicines m
        WHERE m.id = ?
    ");
    $stmt->execute([$medicine_id]);
    $medicine = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$medicine) {
        die("Medicine not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete'])) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // First, delete related records from pharmacy_stock
            $stmt = $pdo->prepare("
                DELETE ps FROM pharmacy_stock ps
                INNER JOIN medicine_batches mb ON ps.medicine_batch_id = mb.id
                WHERE mb.medicine_id = ?
            ");
            $stmt->execute([$medicine_id]);

            // Then delete related records from medicine_batches
            $stmt = $pdo->prepare("
                DELETE FROM medicine_batches WHERE medicine_id = ?
            ");
            $stmt->execute([$medicine_id]);

            // Finally delete the medicine
            $stmt = $pdo->prepare("
                DELETE FROM medicines WHERE id = ?
            ");
            $stmt->execute([$medicine_id]);

            // Commit transaction
            $pdo->commit();
            
            // Redirect back to medicines page with success message
            header("Location: medicines.php?success=deleted");
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        // User cancelled, redirect back
        header("Location: medicines.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delete Medicine</title>
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

.card-header {
  background: linear-gradient(135deg, #dc3545, #c82333);
  color: white;
  border-radius: 12px 12px 0 0 !important;
  padding: 20px;
}

.btn-danger {
  background: linear-gradient(135deg, #dc3545, #c82333);
  border: none;
}

.btn-danger:hover {
  background: linear-gradient(135deg, #c82333, #dc3545);
}

.warning-box {
  background-color: #fff3cd;
  border: 1px solid #ffeaa7;
  border-radius: 8px;
  padding: 20px;
  margin: 20px 0;
}

.medicine-details {
  background-color: #f8f9fa;
  border-radius: 8px;
  padding: 15px;
  margin: 15px 0;
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Delete Medicine</a>
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
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Delete Medicine</h4>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <div class="warning-box">
                    <h5 class="text-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i>Warning: This action cannot be undone!</h5>
                    <p class="mb-0">You are about to permanently delete this medicine and all associated data. This will remove:</p>
                    <ul class="mt-2">
                        <li>The medicine record</li>
                        <li>All batch information</li>
                        <li>All stock quantities</li>
                        <li>Any related data</li>
                    </ul>
                </div>

                <div class="medicine-details">
                    <h6>Medicine to be deleted:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Name:</strong> <?= htmlspecialchars($medicine['name']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Generic Name:</strong> <?= htmlspecialchars($medicine['generic_name']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Brand:</strong> <?= htmlspecialchars($medicine['brand']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Form:</strong> <?= htmlspecialchars($medicine['form']) ?>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <div class="alert alert-danger">
                        <strong>Are you sure you want to delete this medicine?</strong><br>
                        This action is permanent and cannot be reversed.
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="confirm_delete" value="1" class="btn btn-danger">
                            <i class="bi bi-trash me-2"></i>Yes, Delete Medicine
                        </button>
                        <a href="medicines.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
  </div>
</div>

</body>
</html>
