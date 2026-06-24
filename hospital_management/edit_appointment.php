<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get appointment ID
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($appointment_id <= 0) die("Invalid appointment ID.");

try {
    // Fetch appointment
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    if (!$appointment) die("Appointment not found.");

    // Fetch patients
    $patients = $pdo->query("SELECT id, first_name AS first, last_name AS last FROM patients ORDER BY first_name")->fetchAll();

    // Fetch doctors
    $doctors = $pdo->query("
        SELECT d.id, u.first_name AS first, u.last_name AS last
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        ORDER BY u.first_name
    ")->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Appointment</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
body {
  font-family: 'Montserrat', sans-serif;
  margin: 0;
  background: #f0f2f5;
}

/* Navbar */
.navbar {
  background: linear-gradient(135deg, #4b6cb7, #182848);
}

.navbar .navbar-brand,
.navbar .nav-link {
  color: #fff;
}

.navbar .nav-link:hover {
  color: #ffd700;
}

/* Layout */
.d-flex-wrapper {
  display: flex;
  min-height: 100vh;
}

/* Sidebar */
.sidebar {
  width: 230px;
  background-color: #1e1e2f;
  padding: 20px;
  color: #fff;
  flex-shrink: 0;
}

.sidebar h4 {
  font-weight: 600;
  margin-bottom: 1rem;
  color: #ffd700;
}

.sidebar a {
  display: flex; align-items: center;
  color: #c1c1c1;
  text-decoration: none;
  padding: 12px 15px;
  border-radius: 8px;
  margin-bottom: 5px;
  transition: all 0.3s ease;
  white-space: nowrap;
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

/* Main content */
.main-content {
  flex: 1;
  padding: 30px;
  background: #f0f2f5;
}

.card {
  border-radius: 10px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.btn-cancel {
  background: #ccc;
  color: #333;
}

.btn-cancel:hover {
  background: #bbb;
}

.btn-submit {
  background: #4b6cb7;
  color: #fff;
}

.btn-submit:hover {
  background: #3a539b;
}

</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
<a class="navbar-brand" href="#">Appointments Management</a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu">
    <span class="navbar-toggler-icon"></span>
</button>
<div class="collapse navbar-collapse" id="navbarMenu">
    <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a></li>
    </ul>
</div>
</div>
</nav>

<div class="d-flex-wrapper">

<!-- Sidebar -->
<div class="sidebar">
 <h4>Dashboard Menu</h4>
  <a href="manager_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
  <a href="users.php"><i class="bi bi-people-fill me-2"></i>Users</a>
  <a href="roles.php"><i class="bi bi-shield-lock me-2"></i>Roles</a>
  <a href="patients.php"><i class="bi bi-person-fill me-2"></i>Patients</a>
  <a href="doctors.php"><i class="bi bi-person-badge me-2"></i>Doctors</a>
  <a href="appointments.php" class="active"><i class="bi bi-calendar-check me-2"></i>Appointments</a>
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

<!-- Main content -->
<div class="main-content">
  <div class="card">
   <div class="card-header">
    <i class="bi bi-pencil-square me-2"></i>Edit Appointment
   </div>
   <div class="card-body">
    <form action="update_appointment.php" method="POST">
     <input type="hidden" name="id" value="<?= $appointment['id'] ?>">
    <div class="row g-3">
    <div class="col-md-6">
     <label for="patient" class="form-label">Patient</label>
     <select name="patient_id" id="patient" class="form-select" required>
     <?php foreach($patients as $p): ?>
     <option value="<?= $p['id'] ?>" <?= $p['id']==$appointment['patient_id']?'selected':'' ?>>
      <?= htmlspecialchars($p['first'].' '.$p['last']) ?>
     </option>
      <?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
  <label for="doctor" class="form-label">Doctor</label>
  <select name="doctor_id" id="doctor" class="form-select" required>
   <?php foreach($doctors as $d): ?>
  <option value="<?= $d['id'] ?>" <?= $d['id']==$appointment['doctor_id']?'selected':'' ?>>
   <?= htmlspecialchars($d['first'].' '.$d['last']) ?>
</option>
 <?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
  <label for="scheduled_at" class="form-label">Scheduled At</label>
  <input type="text" name="scheduled_at" id="scheduled_at" class="form-control" value="<?= date('Y-m-d H:i', strtotime($appointment['scheduled_at'])) ?>" required>
</div>

<div class="col-md-6">
  <label for="duration" class="form-label">Duration (minutes)</label>
  <input type="number" name="duration_minutes" id="duration" class="form-control" min="1" value="<?= $appointment['duration_minutes'] ?>" required>
</div>

<div class="col-md-6">
  <label for="status" class="form-label">Status</label>
<select name="status" id="status" class="form-select" required>
<?php
 $statuses = ['scheduled','confirmed','in_progress','completed','cancelled','no_show'];
 foreach($statuses as $s): ?>
<option value="<?= $s ?>" <?= $s==$appointment['status']?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
  <?php endforeach; ?>
</select>
</div>

<div class="col-md-6">
  <label for="reason" class="form-label">Reason</label>
  <input type="text" name="reason" id="reason" class="form-control" value="<?= htmlspecialchars($appointment['reason']) ?>" required>
 </div>
</div>

<div class="mt-4 d-flex justify-content-between">
  <a href="appointments.php" class="btn btn-cancel me-2">Cancel</a>
  <button type="submit" class="btn btn-submit">Update Appointment</button>
     </div>
    </form>
   </div>
  </div>
 </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#scheduled_at", {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    minuteIncrement: 5,
    minDate: "today"
});
</script>
</body>
</html>
