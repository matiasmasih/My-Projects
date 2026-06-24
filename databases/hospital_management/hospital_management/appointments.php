<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Fetch appointments
try {
    $stmt = $pdo->query("
        SELECT 
            a.id, 
            a.scheduled_at, 
            a.duration_minutes, 
            a.status, 
            a.reason,
            p.first_name AS patient_first, 
            p.last_name AS patient_last,
            d.id AS doctor_id, 
            u.first_name AS doctor_first, 
            u.last_name AS doctor_last
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        ORDER BY a.scheduled_at DESC
    ");
    $appointments = $stmt->fetchAll();

    // Debug output (optional)
    // echo "<pre>"; print_r($appointments); echo "</pre>";
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch patients and doctors for modal dropdowns
try {
    $patients = $pdo->query("SELECT id, first_name AS first, last_name AS last FROM patients ORDER BY first_name")->fetchAll();
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
<title>Appointments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
body {
  font-family:'Montserrat', sans-serif;
  background:#f0f2f5;
  margin:0;
}

/* Navbar */
.navbar {
  background: linear-gradient(135deg,#4b6cb7,#182848);
  color:#fff;
}

.navbar .navbar-brand,
.navbar .nav-link {
  color:#fff;
}

.navbar .nav-link:hover {
  color:#ffd700;
}

/* Sidebar */
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

/* Table */
.table-modern {
  background:#fff;
  border-radius:12px;
  box-shadow:0 8px 30px rgba(0,0,0,0.12);
  overflow:hidden;
}

.table-modern th {
  background:linear-gradient(135deg,#36d1dc,#5b86e5);
  color:#fff;
  font-weight:600;
  text-align:center;
}

.table-modern td, .table-modern th {
  vertical-align:middle;
  text-align:center;
}

.table-modern tbody tr:hover {
  background: rgba(75,108,183,0.1);
  transform:translateX(3px);
  transition:0.3s;
}

/* Buttons */
.btn-edit {
  background:#56ab2f;
  color:#fff;
  border:none;
  border-radius:6px;
}

.btn-edit:hover {
  background:#3c7d1b;
}

.btn-delete {
  background:#ff4e50;
  color:#fff;
  border:none;
  border-radius:6px;
}

.btn-delete:hover {
  background:#c43a3a;
}

.btn-submit {
  background: #012d5c !important;
  color: #fff !important;
  border-radius: 6px;
}

.btn-submit:hover {
  background: #012d5c !important;
  color: #94d8d8 !important;
}

/* Default white close button */
.btn-close.btn-close-white {
    filter: brightness(1); /* ensures it stays white */
}

/* Hover effect: change × icon color */
.btn-close.btn-close-white:hover {
    filter: brightness(0) invert(0.75) sepia(1) saturate(5) hue-rotate(50deg);
    /* This will give a yellow-ish hover effect */
}

.btn-cancel {
  background-color: #ccc;
  color: #000;
  border: none;
  border-radius: 6px;
  padding: 8px 18px;
  font-weight: 500;
  transition: 0.3s ease;
}
.btn-cancel:hover {
  background-color: #999;
}

.btn-submit {
  background-color: #1d3557;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 8px 18px;
  font-weight: 500;
  transition: 0.3s ease;
}
.btn-submit:hover {
  background-color: #0f2340;
}

/* Search */
.search-box {
  max-width:300px;
}

/* Modal Header */
.modal-header {
   background: linear-gradient(135deg,#36d1dc,#5b86e5);
   color:#fff;
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
<div class="container-fluid">
<a class="navbar-brand" href="#">Appointments Management</a>
<div class="collapse navbar-collapse">
<ul class="navbar-nav ms-auto">
<li class="nav-item"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
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
<div class="container-fluid p-4">
<div class="d-flex justify-content-between align-items-center mb-3">
<h2>All Appointments</h2>
<button class="btn btn-submit" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
    <i class="bi bi-plus-circle"></i> Add Appointment
</button>
</div>

<!-- Search -->
<div class="d-flex mb-3">
<input type="text" class="form-control search-box me-2" id="searchInput" placeholder="Search appointments...">
<button class="btn btn-primary" id="searchButton"><i class="bi bi-search"></i> Search</button>
</div>

<!-- Appointments Table -->
<table class="table table-modern">
<thead>
<tr>
    <th>#</th>
    <th>Patient</th>
    <th>Doctor</th>
    <th>Scheduled At</th>
    <th>Duration</th>
    <th>Status</th>
    <th>Reason</th>
    <th>Actions</th>
</tr>
</thead>
<tbody id="appointmentTable">
<?php foreach($appointments as $index => $a): ?>
    <?php
    // Check if invoice already exists for this appointment
    $invoiceCheck = $pdo->prepare("SELECT id FROM invoices WHERE appointment_id = ?");
    $invoiceCheck->execute([$a['id']]);
    $invoiceExists = $invoiceCheck->fetch();
    ?>
<tr>
    <td><?= $index+1 ?></td>
    <td><?= htmlspecialchars($a['patient_first'].' '.$a['patient_last']) ?></td>
    <td><?= htmlspecialchars($a['doctor_first'].' '.$a['doctor_last']) ?></td>
    <td><?= date('Y-m-d H:i', strtotime($a['scheduled_at'])) ?></td>
    <td><?= htmlspecialchars($a['duration_minutes']) ?> min</td>
    <td><?= htmlspecialchars($a['status']) ?></td>
    <td><?= htmlspecialchars($a['reason']) ?></td>
    <td>
        <!-- Edit Button -->
        <a href="edit_appointment.php?id=<?= $a['id'] ?>" class="btn btn-edit btn-sm me-1">
            <i class="bi bi-pencil"></i>
        </a>

        <!-- Delete Button -->
        <a href="delete_appointment.php?id=<?= $a['id'] ?>" class="btn btn-delete btn-sm me-1" onclick="return confirm('Are you sure you want to delete this appointment?');">
            <i class="bi bi-trash"></i>
        </a>

        <!-- Generate Invoice Button -->
        <?php if (!$invoiceExists): ?>
          <a href="generate_invoice.php?appointment_id=<?= $a['id'] ?>" class="btn btn-warning btn-sm">
        <i class="bi bi-receipt"></i> Invoice
      </a>
     <?php else: ?>
         <a href="view_invoice.php?id=<?= $invoiceExists['id'] ?>" class="btn btn-success btn-sm">
         <i class="bi bi-check-circle"></i> View Invoice
       </a>
      <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<!-- Add Appointment Modal -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1" aria-labelledby="addAppointmentLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addAppointmentLabel"><i class="bi bi-calendar-plus me-2"></i>Add Appointment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="save_appointment.php" method="POST">
      <div class="modal-body">
          <div class="row g-3">
              <div class="col-md-6">
                  <label for="patient" class="form-label">Patient</label>
                  <select name="patient_id" id="patient" class="form-select" required>
                      <option value="">Select Patient</option>
                      <?php foreach($patients as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['first'].' '.$p['last']) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <div class="col-md-6">
                  <label for="doctor" class="form-label">Doctor</label>
                  <select name="doctor_id" id="doctor" class="form-select" required>
                      <option value="">Select Doctor</option>
                      <?php foreach($doctors as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['first'].' '.$d['last']) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <div class="col-md-6">
                  <label for="scheduled_at" class="form-label">Scheduled At</label>
                  <input type="text" name="scheduled_at" id="scheduled_at" class="form-control" required>
              </div>
              <div class="col-md-6">
                  <label for="duration" class="form-label">Duration (minutes)</label>
                  <input type="number" name="duration_minutes" id="duration" class="form-control" min="1" required>
              </div>
              <div class="col-md-6">
                  <label for="status" class="form-label">Status</label>
                  <select name="status" id="status" class="form-select" required>
                      <option value="scheduled">Scheduled</option>
                      <option value="confirmed">Confirmed</option>
                      <option value="in_progress">In Progress</option>
                      <option value="completed">Completed</option>
                      <option value="cancelled">Cancelled</option>
                      <option value="no_show">No Show</option>
                  </select>
              </div>
              <div class="col-md-6">
                  <label for="reason" class="form-label">Reason</label>
                  <input type="text" name="reason" id="reason" class="form-control" placeholder="Reason for appointment" required>
              </div>
          </div>
      </div>
      <div class="modal-footer">
          <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-submit">Save Appointment</button>
      </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Initialize Flatpickr
flatpickr("#scheduled_at", {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    minuteIncrement: 5,
    minDate: "today"
});

// Table search
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const appointmentTable = document.getElementById('appointmentTable');

function filterTable() {
    const filter = searchInput.value.toLowerCase();
    const rows = appointmentTable.getElementsByTagName('tr');
    Array.from(rows).forEach(row => {
        const patient = row.cells[1].textContent.toLowerCase();
        const doctor = row.cells[2].textContent.toLowerCase();
        const scheduled = row.cells[3].textContent.toLowerCase();
        const status = row.cells[5].textContent.toLowerCase();
        const reason = row.cells[6].textContent.toLowerCase();

        if(patient.includes(filter) || doctor.includes(filter) || scheduled.includes(filter) || status.includes(filter) || reason.includes(filter)){
            row.style.display='';
        } else {
            row.style.display='none';
        }
    });
}

searchInput.addEventListener('keyup', filterTable);
searchButton.addEventListener('click', filterTable);
</script>
</body>
</html>
