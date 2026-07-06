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

// Fetch batch details for confirmation message
try {
    $stmt = $pdo->prepare("
        SELECT
            mb.batch_number,
            m.name as medicine_name,
            m.generic_name
        FROM medicine_batches mb
        LEFT JOIN medicines m ON mb.medicine_id = m.id
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

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete'])) {
        try {
            $pdo->beginTransaction();

            // First delete from pharmacy_stock (child table)
            $stmt = $pdo->prepare("DELETE FROM pharmacy_stock WHERE medicine_batch_id = ?");
            $stmt->execute([$batch_id]);

            // Then delete from medicine_batches (parent table)
            $stmt = $pdo->prepare("DELETE FROM medicine_batches WHERE id = ?");
            $stmt->execute([$batch_id]);

            $pdo->commit();

            // Redirect back to pharmacy stock page
            header("Location: medicines.php");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        // User cancelled - redirect back
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
<title>Delete Batch - Hospital Management</title>
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
  max-width: 800px;
  margin: 0 auto;
}

/* Warning Box */
.warning-box {
  background: #f8d7da;
  border: 1px solid #f5c6cb;
  border-radius: 8px;
  padding: 25px;
  margin-bottom: 25px;
  text-align: center;
}

.warning-icon {
  font-size: 3rem;
  color: #dc3545;
  margin-bottom: 15px;
}

.warning-box h4 {
  color: #721c24;
  margin-bottom: 10px;
}

.warning-box p {
  color: #721c24;
  margin-bottom: 0;
}

/* Batch Info */
.batch-info {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 25px;
  margin-bottom: 25px;
  border-left: 4px solid #4b6cb7;
}

.batch-info h5 {
  color: #4b6cb7;
  margin-bottom: 20px;
  font-weight: 600;
}

.info-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 10px;
  padding: 8px 0;
  border-bottom: 1px solid #dee2e6;
}

.info-row:last-child {
  border-bottom: none;
  margin-bottom: 0;
}

.info-label {
  font-weight: 600;
  color: #495057;
}

.info-value {
  color: #6c757d;
}

/* Buttons */
.btn-danger {
  background: linear-gradient(135deg, #dc3545, #c82333);
  border: none;
  border-radius: 6px;
  padding: 12px 30px;
  font-weight: 600;
  font-size: 1.1rem;
}
.btn-danger:hover {
  background: linear-gradient(135deg, #c82333, #a71e2a);
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
}

.btn-secondary {
  background: #6c757d;
  border: none;
  border-radius: 6px;
  padding: 12px 30px;
  font-weight: 600;
  font-size: 1.1rem;
}
.btn-secondary:hover {
  background: #5a6268;
  transform: translateY(-1px);
}

/* Confirmation Alert */
.confirmation-alert {
  background: #fff3cd;
  border: 1px solid #ffeaa7;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 25px;
  text-align: center;
}

.confirmation-alert strong {
  color: #856404;
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

      <!-- Page Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">
          <i class="bi bi-trash me-2"></i>Delete Medicine Batch
        </h2>
        <a href="medicines.php" class="btn btn-secondary">
          <i class="bi bi-arrow-left me-2"></i>Back to Medicines
        </a>
      </div>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>

      <!-- Warning Box -->
      <div class="warning-box">
        <div class="warning-icon">
          <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <h4>Irreversible Action</h4>
        <p>You are about to permanently delete this medicine batch. This action cannot be undone and will remove all associated stock information.</p>
      </div>

      <!-- Batch Information -->
      <div class="batch-info">
        <h5><i class="bi bi-info-circle me-2"></i>Batch Details</h5>
        <div class="info-row">
          <span class="info-label">Medicine Name:</span>
          <span class="info-value"><?= htmlspecialchars($batch['medicine_name']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Generic Name:</span>
          <span class="info-value"><?= htmlspecialchars($batch['generic_name']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Batch Number:</span>
          <span class="info-value"><?= htmlspecialchars($batch['batch_number']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Batch ID:</span>
          <span class="info-value">#<?= $batch_id ?></span>
        </div>
      </div>

      <!-- Confirmation Form -->
      <form method="POST">
        <div class="confirmation-alert">
          <strong>Final Confirmation Required</strong><br>
          Are you absolutely sure you want to delete this batch? This action cannot be reversed.
        </div>

        <div class="d-flex justify-content-center gap-4 mt-4">
          <a href="medicines.php" class="btn btn-secondary btn-lg">
            <i class="bi bi-x-circle me-2"></i>Cancel
          </a>
          <button type="submit" name="confirm_delete" value="1" class="btn btn-danger btn-lg">
            <i class="bi bi-trash me-2"></i>Yes, Delete Permanently
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
