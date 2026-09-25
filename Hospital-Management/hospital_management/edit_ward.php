<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get ward ID from URL
$ward_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ward_id <= 0) {
    header("Location: wards.php");
    exit;
}

// Fetch ward data
try {
    $stmt = $pdo->prepare("
        SELECT w.*, CONCAT(u.first_name, ' ', u.last_name) as in_charge_name 
        FROM wards w 
        LEFT JOIN users u ON w.in_charge_id = u.id 
        WHERE w.id = ?
    ");
    $stmt->execute([$ward_id]);
    $ward = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ward) {
        header("Location: wards.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch staff for dropdown
try {
    $staff = $pdo->query("
        SELECT id, CONCAT(first_name, ' ', last_name) as name 
        FROM users 
        WHERE role_id IN (3, 4) OR id IN (1, 2, 4)  -- Include existing admins/managers too
        ORDER BY first_name
    ")->fetchAll();
} catch (PDOException $e) {
    $staff = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $capacity = (int)$_POST['capacity'];
    $charge_per_day = (float)$_POST['charge_per_day'];
    $location = trim($_POST['location']);
    $phone_extension = trim($_POST['phone_extension']);
    $status = $_POST['status'];
    $in_charge_id = !empty($_POST['in_charge_id']) ? (int)$_POST['in_charge_id'] : null;
    $notes = trim($_POST['notes']);

    try {
        $stmt = $pdo->prepare("
            UPDATE wards SET 
                name = ?, type = ?, capacity = ?, charge_per_day = ?, 
                location = ?, phone_extension = ?, status = ?, 
                in_charge_id = ?, notes = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $name, $type, $capacity, $charge_per_day,
            $location, $phone_extension, $status,
            $in_charge_id, $notes, $ward_id
        ]);
        
        // Redirect back to wards page with success message
        header("Location: wards.php?success=1");
        exit;
        
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Ward - Hospital Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    font-family: 'Montserrat', sans-serif;
    background: #f0f2f5;
    margin: 0;
}

.navbar {
    background: linear-gradient(135deg, #4b6cb7, #182848);
}
.navbar .navbar-brand, .navbar .nav-link {
    color: #fff;
}
.navbar .nav-link:hover {
    color: #ffd700;
}

.sidebar {
    background-color: #1e1e2f;
    color: #fff;
    min-height: 100vh;
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

.main-content {
    flex: 1;
    padding: 20px;
}

.content-wrapper {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.btn-primary {
    background: linear-gradient(135deg, #4b6cb7, #182848);
    border: none;
    padding: 10px 30px;
}
.btn-primary:hover {
    background: linear-gradient(135deg, #3a5998, #152642);
}

.btn-secondary {
    background: #6c757d;
    border: none;
    padding: 10px 30px;
}
.btn-secondary:hover {
    background: #5a6268;
}

.form-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    padding: 10px 15px;
    transition: all 0.3s ease;
}
.form-control:focus, .form-select:focus {
    border-color: #4b6cb7;
    box-shadow: 0 0 0 0.2rem rgba(75, 108, 183, 0.25);
}

.alert {
    border-radius: 8px;
    border: none;
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Edit Ward</a>
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
        <a href="wards.php" class="active"><i class="bi bi-house-door me-2"></i>Wards</a>
        <a href="rooms.php"><i class="bi bi-door-closed me-2"></i>Rooms</a>
        <a href="messages.php"><i class="bi bi-chat-dots me-2"></i>Messages</a>
        <a href="admissions.php"><i class="bi bi-journal-plus me-2"></i>Admissions</a>
        <a href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-pencil-square me-2"></i>Edit Ward</h2>
                <a href="wards.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Wards
                </a>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Ward Name *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?= htmlspecialchars($ward['name']) ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="type" class="form-label">Ward Type *</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="general" <?= $ward['type'] == 'general' ? 'selected' : '' ?>>General</option>
                            <option value="icu" <?= $ward['type'] == 'icu' ? 'selected' : '' ?>>ICU</option>
                            <option value="maternity" <?= $ward['type'] == 'maternity' ? 'selected' : '' ?>>Maternity</option>
                            <option value="pediatric" <?= $ward['type'] == 'pediatric' ? 'selected' : '' ?>>Pediatric</option>
                            <option value="surgical" <?= $ward['type'] == 'surgical' ? 'selected' : '' ?>>Surgical</option>
                            <option value="orthopedic" <?= $ward['type'] == 'orthopedic' ? 'selected' : '' ?>>Orthopedic</option>
                            <option value="cardiac" <?= $ward['type'] == 'cardiac' ? 'selected' : '' ?>>Cardiac</option>
                            <option value="emergency" <?= $ward['type'] == 'emergency' ? 'selected' : '' ?>>Emergency</option>
                            <option value="psychiatric" <?= $ward['type'] == 'psychiatric' ? 'selected' : '' ?>>Psychiatric</option>
                            <option value="isolation" <?= $ward['type'] == 'isolation' ? 'selected' : '' ?>>Isolation</option>
                            <option value="other" <?= $ward['type'] == 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="capacity" class="form-label">Capacity (Beds) *</label>
                        <input type="number" class="form-control" id="capacity" name="capacity" 
                               value="<?= $ward['capacity'] ?>" min="1" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="charge_per_day" class="form-label">Charge Per Day ($) *</label>
                        <input type="number" step="0.01" class="form-control" id="charge_per_day" name="charge_per_day" 
                               value="<?= $ward['charge_per_day'] ?>" min="0" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="location" class="form-label">Location *</label>
                        <input type="text" class="form-control" id="location" name="location" 
                               value="<?= htmlspecialchars($ward['location']) ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="phone_extension" class="form-label">Phone Extension</label>
                        <input type="text" class="form-control" id="phone_extension" name="phone_extension" 
                               value="<?= htmlspecialchars($ward['phone_extension'] ?? '') ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?= $ward['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $ward['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="maintenance" <?= $ward['status'] == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="full" <?= $ward['status'] == 'full' ? 'selected' : '' ?>>Full</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="in_charge_id" class="form-label">In Charge</label>
                        <select class="form-select" id="in_charge_id" name="in_charge_id">
                            <option value="">Not Assigned</option>
                            <?php foreach($staff as $person): ?>
                                <option value="<?= $person['id'] ?>" 
                                    <?= $ward['in_charge_id'] == $person['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($person['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4"><?= htmlspecialchars($ward['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Update Ward
                            </button>
                            <a href="wards.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-set charge per day and capacity based on ward type
document.getElementById('type').addEventListener('change', function() {
    const type = this.value;
    const chargeField = document.getElementById('charge_per_day');
    const capacityField = document.getElementById('capacity');
    
    const charges = {
        'general': 50.00,
        'icu': 200.00,
        'maternity': 80.00,
        'pediatric': 60.00,
        'surgical': 75.00,
        'orthopedic': 70.00,
        'cardiac': 150.00,
        'emergency': 100.00,
        'psychiatric': 65.00,
        'isolation': 90.00,
        'other': 50.00
    };
    
    const capacities = {
        'general': 20,
        'icu': 10,
        'maternity': 15,
        'pediatric': 18,
        'surgical': 16,
        'orthopedic': 15,
        'cardiac': 12,
        'emergency': 15,
        'psychiatric': 20,
        'isolation': 8,
        'other': 15
    };
    
    if (charges[type] && !chargeField.value) {
        chargeField.value = charges[type];
    }
    if (capacities[type] && !capacityField.value) {
        capacityField.value = capacities[type];
    }
});
</script>
</body>
</html>
