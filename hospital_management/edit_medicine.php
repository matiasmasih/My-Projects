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

// Fetch medicine data with current stock
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.name,
            m.generic_name,
            m.brand,
            m.form,
            m.strength,
            m.unit,
            m.description,
            COALESCE(SUM(ps.quantity), 0) as current_stock
        FROM medicines m
        LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
        LEFT JOIN pharmacy_stock ps ON mb.id = ps.medicine_batch_id
        WHERE m.id = ?
        GROUP BY m.id
    ");
    $stmt->execute([$medicine_id]);
    $medicine = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$medicine) {
        die("Medicine not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    $name = trim($_POST['name']);
    $generic_name = trim($_POST['generic_name']);
    $brand = trim($_POST['brand']);
    $form = trim($_POST['form']);
    $strength = trim($_POST['strength']);
    $unit = trim($_POST['unit']);
    $description = trim($_POST['description']);
    $current_stock = isset($_POST['current_stock']) ? (int)$_POST['current_stock'] : 0;

    // Validate required fields
    if (empty($name) || empty($generic_name) || empty($form) || empty($unit)) {
        $error = "Please fill all required fields.";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Update medicine in database
            $stmt = $pdo->prepare("
                UPDATE medicines 
                SET name = ?, generic_name = ?, brand = ?, form = ?, strength = ?, unit = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name,
                $generic_name,
                $brand,
                $form,
                $strength,
                $unit,
                $description,
                $medicine_id
            ]);

            // Update stock quantity if changed
            if ($current_stock != $medicine['current_stock']) {
                // Get the first batch for this medicine
                $stmt = $pdo->prepare("
                    SELECT mb.id 
                    FROM medicine_batches mb 
                    WHERE mb.medicine_id = ? 
                    LIMIT 1
                ");
                $stmt->execute([$medicine_id]);
                $batch = $stmt->fetch();
                
                if ($batch) {
                    $stmt = $pdo->prepare("
                        UPDATE pharmacy_stock 
                        SET quantity = ? 
                        WHERE medicine_batch_id = ?
                    ");
                    $stmt->execute([$current_stock, $batch['id']]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect back to medicines page with success message
            header("Location: medicines.php?success=edit");
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Medicine</title>
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
  background: linear-gradient(135deg, #36d1dc, #5b86e5);
  color: white;
  border-radius: 12px 12px 0 0 !important;
  padding: 20px;
}

.btn-primary {
  background: linear-gradient(135deg, #36d1dc, #5b86e5);
  border: none;
}

.btn-primary:hover {
  background: linear-gradient(135deg, #5b86e5, #36d1dc);
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Edit Medicine</a>
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
                <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Medicine</h4>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Medicine Name *</label>
                            <input type="text" name="name" id="name" class="form-control" 
                                   value="<?= htmlspecialchars($medicine['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="generic_name" class="form-label">Generic Name *</label>
                            <input type="text" name="generic_name" id="generic_name" class="form-control" 
                                   value="<?= htmlspecialchars($medicine['generic_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="brand" class="form-label">Brand</label>
                            <input type="text" name="brand" id="brand" class="form-control" 
                                   value="<?= htmlspecialchars($medicine['brand']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="form" class="form-label">Form *</label>
                            <select name="form" id="form" class="form-select" required>
                                <option value="">Select Form</option>
                                <option value="Tablet" <?= $medicine['form'] == 'Tablet' ? 'selected' : '' ?>>Tablet</option>
                                <option value="Capsule" <?= $medicine['form'] == 'Capsule' ? 'selected' : '' ?>>Capsule</option>
                                <option value="Syrup" <?= $medicine['form'] == 'Syrup' ? 'selected' : '' ?>>Syrup</option>
                                <option value="Injection" <?= $medicine['form'] == 'Injection' ? 'selected' : '' ?>>Injection</option>
                                <option value="Ointment" <?= $medicine['form'] == 'Ointment' ? 'selected' : '' ?>>Ointment</option>
                                <option value="Cream" <?= $medicine['form'] == 'Cream' ? 'selected' : '' ?>>Cream</option>
                                <option value="Drops" <?= $medicine['form'] == 'Drops' ? 'selected' : '' ?>>Drops</option>
                                <option value="Inhaler" <?= $medicine['form'] == 'Inhaler' ? 'selected' : '' ?>>Inhaler</option>
                                <option value="Spray" <?= $medicine['form'] == 'Spray' ? 'selected' : '' ?>>Spray</option>
                                <option value="Other" <?= $medicine['form'] == 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="strength" class="form-label">Strength</label>
                            <input type="text" name="strength" id="strength" class="form-control" 
                                   value="<?= htmlspecialchars($medicine['strength']) ?>" placeholder="e.g., 500mg, 250mg">
                        </div>
                        <div class="col-md-6">
                            <label for="unit" class="form-label">Unit *</label>
                            <select name="unit" id="unit" class="form-select" required>
                                <option value="tablet" <?= $medicine['unit'] == 'tablet' ? 'selected' : '' ?>>Tablet</option>
                                <option value="capsule" <?= $medicine['unit'] == 'capsule' ? 'selected' : '' ?>>Capsule</option>
                                <option value="bottle" <?= $medicine['unit'] == 'bottle' ? 'selected' : '' ?>>Bottle</option>
                                <option value="tube" <?= $medicine['unit'] == 'tube' ? 'selected' : '' ?>>Tube</option>
                                <option value="inhaler" <?= $medicine['unit'] == 'inhaler' ? 'selected' : '' ?>>Inhaler</option>
                                <option value="ampoule" <?= $medicine['unit'] == 'ampoule' ? 'selected' : '' ?>>Ampoule</option>
                                <option value="vial" <?= $medicine['unit'] == 'vial' ? 'selected' : '' ?>>Vial</option>
                                <option value="pack" <?= $medicine['unit'] == 'pack' ? 'selected' : '' ?>>Pack</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="current_stock" class="form-label">Current Stock Quantity *</label>
                            <input type="number" name="current_stock" id="current_stock" class="form-control" 
                                   value="<?= $medicine['current_stock'] ?>" min="0" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="4"><?= htmlspecialchars($medicine['description']) ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Update Medicine
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
