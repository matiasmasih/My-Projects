<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2) can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// Handle Add Patient Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_patient'])) {
    $mrn = trim($_POST['medical_record_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $dob = $_POST['dob'] ?: null;
    $gender = $_POST['gender'];
    $national_id = trim($_POST['national_id']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO patients
            (medical_record_number, first_name, last_name, dob, gender, national_id, address, phone, email, emergency_contact_name, emergency_contact_phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$mrn, $first_name, $last_name, $dob, $gender, $national_id, $address, $phone, $email, $emergency_name, $emergency_phone]);
        $success = "Patient added successfully!";
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Fetch all patients
try {
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY id DESC");
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patients Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* General body styling */
body {
    font-family: 'Montserrat', sans-serif;
    background: #f0f2f5;
    margin: 0;
}

/* Navbar */
.navbar {
    background: linear-gradient(135deg, #4b6cb7, #182848);
    color: #fff;
}

.navbar .navbar-brand,
.navbar .nav-link {
    color: #fff;
}

.navbar .nav-link:hover {
    color: #ffd700;
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
    margin-bottom: 1rem;
    color: #ffd700;
}

.sidebar a {
    display: block;
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

/* Table container for horizontal scroll */
.table-responsive {
    overflow-x: auto;
}

/* Table */
.table-modern {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    overflow: hidden;
    width: 100%;
}

.table-modern th {
    background: linear-gradient(135deg, #36d1dc, #5b86e5);
    color: #fff;
    font-weight: 600;
    text-align: center;
}

.table-modern td,
.table-modern th {
    vertical-align: middle;
    text-align: center;
    white-space: nowrap; /* prevent text wrapping */
}

.table-modern tbody tr:hover {
    background: rgba(75, 108, 183, 0.1);
    transform: translateX(3px);
    transition: 0.3s;
}

/* Buttons */
.btn-edit {
    background: #56ab2f;
    color: #fff;
    border: none;
    border-radius: 6px;
}

.btn-edit:hover {
    background: #3c7d1b;
}

.btn-delete {
    background: #ff4e50;
    color: #fff;
    border: none;
    border-radius: 6px;
}

.btn-delete:hover {
    background: #c43a3a;
}

.btn-add {
    background: #007bff;
    color: #fff;
    border: none;
    border-radius: 6px;
}

.btn-add:hover {
    background: #0056b3;
}

/* Search input */
.search-box {
    max-width: 300px;
}

/* Modal header */
.modal-header {
    background: linear-gradient(135deg, #36d1dc, #5b86e5);
    color: #fff;
}
</style>

</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
<div class="container-fluid">
<a class="navbar-brand" href="#">Patients Management</a>
<ul class="navbar-nav ms-auto">
<li class="nav-item"><a class="nav-link" href="logout.php">Logout <i class="bi bi-box-arrow-right"></i></a></li>
</ul>
</div>
</nav>

<div class="d-flex">

<!-- Sidebar -->
<div class="sidebar">
  <h4>Dashboard Menu</h4>
   <a href="manager_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
   <a href="users.php"><i class="bi bi-people-fill me-2"></i>Users</a>
   <a href="roles.php"><i class="bi bi-shield-lock me-2"></i>Roles</a>
   <a href="patients.php" class="active"><i class="bi bi-person-fill me-2"></i>Patients</a>
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

<!-- Main content -->
<div class="container-fluid p-4">

<h2>All Patients</h2>

<?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<!-- Add Patient Button -->
<div class="d-flex justify-content-end mb-2">
<button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addPatientModal">
    <i class="bi bi-plus-circle"></i> Add Patient
</button>
</div>

<!-- Search -->
<div class="d-flex mb-3">
   <input type="text" class="form-control search-box me-2" id="searchInput" placeholder="Search doctors...">
   <button class="btn btn-primary" id="searchButton"><i class="bi bi-search"></i> Search</button>
</div>

<!-- Patients Table -->
<table class="table table-modern">
<thead>
<tr>
<th>#</th>
<th>MRN</th>
<th>Full Name</th>
<th>Email</th>
<th>Phone</th>
<th>Gender</th>
<th>DOB</th>
<th>National ID</th>
<th>Address</th>
<th>Actions</th>
</tr>
</thead>
<tbody id="patientTable">
<?php foreach($patients as $index => $p): ?>
<tr>
<td><?= $index+1 ?></td>
<td><?= htmlspecialchars($p['medical_record_number']) ?></td>
<td><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></td>
<td><?= htmlspecialchars($p['email']) ?></td>
<td><?= htmlspecialchars($p['phone']) ?></td>
<td><?= htmlspecialchars($p['gender']) ?></td>
<td><?= htmlspecialchars($p['dob']) ?></td>
<td><?= htmlspecialchars($p['national_id']) ?></td>
<td><?= htmlspecialchars($p['address']) ?></td>
<td>

<a href="edit_patient.php?id=<?= $p['id'] ?>" class="btn btn-edit btn-sm me-1"><i class="bi bi-pencil"></i></a>
<a href="delete_patient.php?id=<?= $p['id'] ?>" class="btn btn-delete btn-sm" onclick="return confirm('Are you sure?');"><i class="bi bi-trash"></i></a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
</div>

<!-- Add Patient Modal -->
<div class="modal fade" id="addPatientModal" tabindex="-1" aria-labelledby="addPatientModalLabel" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="addPatientModalLabel">Add Patient</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
<form method="POST" action="patients.php">
<div class="row g-3">
<div class="col-md-4"><label class="form-label">MRN</label><input type="text" name="medical_record_number" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
<div class="col-md-3"><label class="form-label">DOB</label><input type="date" name="dob" class="form-control"></div>
<div class="col-md-3"><label class="form-label">Gender</label><select name="gender" class="form-select" required>
<option value="">Select</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
</select></div>
<div class="col-md-3"><label class="form-label">National ID</label><input type="text" name="national_id" class="form-control"></div>
<div class="col-md-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Address</label><input type="text" name="address" class="form-control"></div>
</div>
<input type="hidden" name="add_patient" value="1">
<div class="modal-footer d-flex justify-content-between">
    <!-- Save Patient on the left -->
    <button type="submit" class="btn btn-primary">Save Patient</button>

    <!-- Cancel on the right -->
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
</div>
</div>
</form>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Optional: client-side table search
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const patientTable = document.getElementById('patientTable');

function filterTable() {
    const filter = searchInput.value.toLowerCase();
    const rows = patientTable.getElementsByTagName('tr');
    Array.from(rows).forEach(row => {
        const cells = row.getElementsByTagName('td');
        const name = cells[2].textContent.toLowerCase();
        const email = cells[3].textContent.toLowerCase();
        const phone = cells[4].textContent.toLowerCase();
        row.style.display = (name.includes(filter) || email.includes(filter) || phone.includes(filter)) ? '' : 'none';
    });
}
if(searchInput) searchInput.addEventListener('keyup', filterTable);
if(searchButton) searchButton.addEventListener('click', filterTable);
</script>

</body>
</html>
