<?php
session_start();
include 'config.php';

// Enhanced error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check role-based access
$allowed_roles = [1, 2, 3, 4]; // Admin, Manager, Staff, Doctor
if (!in_array($_SESSION['role_id'], $allowed_roles)) {
    header("Location: unauthorized.php");
    exit;
}

// Initialize variables
$success = '';
$error = '';
$admissions = [];
$patients = [];
$doctors = [];
$wards = [];
$rooms = [];

// Generate form token if not exists
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}
$form_token = $_SESSION['form_token'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify form token
    if (isset($_POST['form_token']) && $_POST['form_token'] === $_SESSION['form_token']) {
        
        // Generate new token for next form
        $_SESSION['form_token'] = bin2hex(random_bytes(32));
        
        if (isset($_POST['add_admission'])) {
            $patient_id = $_POST['patient_id'] ?? '';
            $ward_id = $_POST['ward_id'] ?? '';
            $room_id = $_POST['room_id'] ?? '';
            $admitting_doctor_id = $_POST['admitting_doctor_id'] ?? '';
            $status = $_POST['status'] ?? 'admitted';
            $discharge_summary = trim($_POST['discharge_summary'] ?? '');

            // Convert empty room_id to NULL
            $room_id = empty($room_id) ? null : $room_id;

            // Validate inputs
            if (empty($patient_id) || empty($ward_id) || empty($admitting_doctor_id)) {
                $_SESSION['error'] = "Please fill in all required fields.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO admissions (patient_id, ward_id, room_id, admitting_doctor_id, admitted_by, status, discharge_summary) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$patient_id, $ward_id, $room_id, $admitting_doctor_id, $_SESSION['user_id'], $status, $discharge_summary])) {
                        $_SESSION['success'] = "Patient admitted successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to admit patient. Please try again.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                }
            }
            
            header("Location: admissions.php");
            exit;
        }

        if (isset($_POST['update_admission'])) {
            $admission_id = $_POST['admission_id'] ?? '';
            $patient_id = $_POST['patient_id'] ?? '';
            $ward_id = $_POST['ward_id'] ?? '';
            $room_id = $_POST['room_id'] ?? '';
            $admitting_doctor_id = $_POST['admitting_doctor_id'] ?? '';
            $status = $_POST['status'] ?? 'admitted';
            $discharge_summary = trim($_POST['discharge_summary'] ?? '');

            // Convert empty room_id to NULL
            $room_id = empty($room_id) ? null : $room_id;

            if (empty($admission_id) || empty($patient_id) || empty($ward_id) || empty($admitting_doctor_id)) {
                $_SESSION['error'] = "Please fill in all required fields.";
            } else {
                try {
                    // If discharging, set discharge date
                    $discharge_date = null;
                    if ($status === 'discharged') {
                        $discharge_date = date('Y-m-d H:i:s');
                    }
                    
                    $stmt = $pdo->prepare("UPDATE admissions SET patient_id=?, ward_id=?, room_id=?, admitting_doctor_id=?, status=?, discharge_summary=?, discharged_at=? WHERE id=?");
                    if ($stmt->execute([$patient_id, $ward_id, $room_id, $admitting_doctor_id, $status, $discharge_summary, $discharge_date, $admission_id])) {
                        $_SESSION['success'] = "Admission updated successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to update admission. Please try again.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                }
            }
            
            header("Location: admissions.php");
            exit;
        }

        if (isset($_POST['delete_admission'])) {
            $admission_id = $_POST['admission_id'] ?? '';
            if (!empty($admission_id)) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM admissions WHERE id=?");
                    if ($stmt->execute([$admission_id])) {
                        $_SESSION['success'] = "Admission record deleted successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to delete admission record. Please try again.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                }
                
                header("Location: admissions.php");
                exit;
            }
        }
    } else {
        // Invalid token
        $_SESSION['error'] = "Invalid form submission. Please try again.";
        header("Location: admissions.php");
        exit;
    }
}

// Check for session messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

try {
    // Fetch patients for dropdown
    try {
        $patients_stmt = $pdo->query("SELECT id, first_name, last_name, dob, gender, medical_record_number FROM patients ORDER BY first_name, last_name");
        $patients = $patients_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Could not fetch patients: " . $e->getMessage();
    }

    // Fetch doctors for dropdown - FIXED: removed specialization
    try {
        $doctors_stmt = $pdo->query("SELECT d.id, u.first_name, u.last_name FROM doctors d JOIN users u ON d.user_id = u.id WHERE u.is_active = 1 ORDER BY u.first_name, u.last_name");
        $doctors = $doctors_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Could not fetch doctors: " . $e->getMessage();
    }

    // Fetch wards for dropdown
    try {
        $wards_stmt = $pdo->query("SELECT id, name, type, capacity, location FROM wards WHERE status = 'active' ORDER BY name");
        $wards = $wards_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Could not fetch wards: " . $e->getMessage();
    }

    // Fetch rooms for dropdown
    try {
        $rooms_stmt = $pdo->query("SELECT id, room_number, ward_id, room_type, capacity FROM rooms ORDER BY room_number");
        $rooms = $rooms_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Could not fetch rooms: " . $e->getMessage();
    }

    // Fetch admissions with related data - FIXED QUERY: removed doctor_specialization
    try {
        $admissions_stmt = $pdo->query("
            SELECT 
                a.*,
                p.first_name as patient_first_name,
                p.last_name as patient_last_name,
                p.dob as patient_dob,
                p.gender as patient_gender,
                p.medical_record_number,
                doc_user.first_name as doctor_first_name,
                doc_user.last_name as doctor_last_name,
                w.name as ward_name,
                w.type as ward_type,
                w.location as ward_location,
                r.room_number,
                r.room_type,
                admitted_user.first_name as admitted_by_first_name,
                admitted_user.last_name as admitted_by_last_name
            FROM admissions a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN doctors d ON a.admitting_doctor_id = d.id
            LEFT JOIN users doc_user ON d.user_id = doc_user.id
            LEFT JOIN wards w ON a.ward_id = w.id
            LEFT JOIN rooms r ON a.room_id = r.id
            LEFT JOIN users admitted_user ON a.admitted_by = admitted_user.id
            ORDER BY a.admitted_at DESC
        ");
        $admissions = $admissions_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Could not fetch admissions: " . $e->getMessage();
    }

    // Get admission statistics
    $stats = [
        'total' => 0,
        'admitted' => 0,
        'discharged' => 0,
        'today' => 0
    ];
    
    try {
        $stats_stmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'admitted' THEN 1 ELSE 0 END) as admitted,
                SUM(CASE WHEN status = 'discharged' THEN 1 ELSE 0 END) as discharged,
                SUM(CASE WHEN DATE(admitted_at) = CURDATE() THEN 1 ELSE 0 END) as today
            FROM admissions
        ");
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Continue without stats
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Status display names and colors
$status_display = [
    'admitted' => 'Admitted',
    'discharged' => 'Discharged',
    'transferred' => 'Transferred'
];

$status_colors = [
    'admitted' => 'bg-warning',
    'discharged' => 'bg-success',
    'transferred' => 'bg-info'
];

// Room type display names
$room_type_display = [
    'single' => 'Single Room',
    'double' => 'Double Room',
    'icu' => 'ICU',
    'ward' => 'Ward',
    'other' => 'Other'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Patient Admissions</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --primary: #4361ee;
    --secondary: #3f37c9;
    --success: #4cc9f0;
    --info: #4895ef;
    --warning: #f72585;
    --danger: #e63946;
    --light: #f8f9fa;
    --dark: #212529;
    --sidebar-bg: #1a1d29;
    --sidebar-hover: #2d3040;
    --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f5f7fb;
    color: #333;
    overflow-x: hidden;
}

.main-container {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    background: var(--sidebar-bg);
    color: #fff;
    width: 260px;
    padding: 25px 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
}

.sidebar-header {
    padding: 0 25px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 15px;
}

.sidebar-header h4 {
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sidebar-menu {
    list-style: none;
    padding: 0;
}

.sidebar-menu li {
    margin-bottom: 5px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    color: #b0b3c1;
    text-decoration: none;
    padding: 12px 25px;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
    font-weight: 500;
}

.sidebar-menu a:hover,
.sidebar-menu a.active {
    background: var(--sidebar-hover);
    color: #fff;
    border-left: 3px solid var(--primary);
}

.sidebar-menu i {
    margin-right: 12px;
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

.main-content {
    flex: 1;
    padding: 30px;
    margin-left: 260px;
    overflow-y: auto;
}

.navbar {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    padding: 12px 0;
    position: fixed;
    top: 0;
    right: 0;
    left: 260px;
    z-index: 1000;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    margin-bottom: 25px;
}

.card-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-radius: 12px 12px 0 0 !important;
    padding: 15px 20px;
    font-weight: 600;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

/* Table Styles */
.table-container {
    border-radius: 8px;
    overflow: hidden;
    margin-top: 20px;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: white;
}

.table {
    margin-bottom: 0;
    width: 100%;
    min-width: 1300px;
}

.table thead th {
    background: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    color: #495057;
    padding: 15px 12px;
    vertical-align: middle;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table tbody td {
    padding: 15px 12px;
    vertical-align: middle;
    border-color: #f1f3f4;
    white-space: nowrap;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

/* Column Widths */
.table th:nth-child(1),
.table td:nth-child(1) {
    width: 80px;
    min-width: 80px;
    text-align: center;
}

.table th:nth-child(2),
.table td:nth-child(2) {
    width: 200px;
    min-width: 200px;
}

.table th:nth-child(3),
.table td:nth-child(3) {
    width: 180px;
    min-width: 180px;
}

.table th:nth-child(4),
.table td:nth-child(4) {
    width: 120px;
    min-width: 120px;
}

.table th:nth-child(5),
.table td:nth-child(5) {
    width: 120px;
    min-width: 120px;
}

.table th:nth-child(6),
.table td:nth-child(6) {
    width: 120px;
    min-width: 120px;
    text-align: center;
}

.table th:nth-child(7),
.table td:nth-child(7) {
    width: 150px;
    min-width: 150px;
}

.table th:nth-child(8),
.table td:nth-child(8) {
    width: 120px;
    min-width: 120px;
    text-align: center;
}

.table th:nth-child(9),
.table td:nth-child(9) {
    width: 120px;
    min-width: 120px;
    text-align: center;
}

.table th:nth-child(10),
.table td:nth-child(10) {
    width: 180px;
    min-width: 180px;
    text-align: center;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
    align-items: center;
}

.action-buttons .btn {
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}

.action-buttons .btn:hover {
    transform: translateY(-1px);
}

.action-buttons .btn i {
    font-size: 0.8rem;
}

/* Badge Styles */
.status-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    color: white;
    display: inline-block;
}

.room-type-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
}

.room-type-single { background: #e3f2fd; color: #1565c0; }
.room-type-double { background: #e8f5e8; color: #2e7d32; }
.room-type-icu { background: #ffebee; color: #c62828; }
.room-type-ward { background: #f3e5f5; color: #7b1fa2; }
.room-type-other { background: #fff3e0; color: #ef6c00; }

.ward-badge {
    background: #e9ecef;
    color: #495057;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
}

.days-badge {
    background: #d1ecf1;
    color: #0c5460;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

/* Alert Styles */
.alert {
    border: none;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 20px;
    margin-top: 70px;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
    border-left: 4px solid #dc3545;
}

/* Stats Cards */
.stats-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    box-shadow: var(--card-shadow);
    border-left: 4px solid var(--primary);
}

.stats-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 5px;
}

.stats-label {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Modal Styles */
.modal-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-radius: 12px 12px 0 0;
}

.modal-title {
    font-weight: 600;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .sidebar {
        width: 220px;
    }

    .main-content {
        margin-left: 220px;
    }

    .navbar {
        left: 220px;
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        margin-left: 0;
    }

    .main-content {
        margin-left: 0;
        padding: 15px;
    }

    .navbar {
        position: relative;
        left: 0;
    }

    .alert {
        margin-top: 20px;
    }

    .table {
        min-width: 1200px;
    }

    .action-buttons {
        flex-direction: column;
        gap: 5px;
    }

    .action-buttons .btn {
        width: 100%;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 10px;
    }

    .card-body {
        padding: 15px;
    }
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand text-white" href="#">
            <i class="bi bi-hospital"></i>
            <span>MediCare Admin</span>
        </a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link text-white" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="main-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="bi bi-layout-sidebar"></i> Admin Menu</h4>
        </div>
         <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="bi bi-people-fill"></i> Users</a></li>
            <li><a href="roles.php"><i class="bi bi-shield-lock"></i> Roles</a></li>
            <li><a href="patients.php"><i class="bi bi-person-fill"></i> Patients</a></li>
            <li><a href="doctors.php"><i class="bi bi-person-badge"></i> Doctors</a></li>
            <li><a href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
            <li><a href="invoices.php"><i class="bi bi-receipt"></i> Invoices</a></li>
            <li><a href="payments.php"><i class="bi bi-cash-stack"></i> Payments</a></li>
            <li><a href="pharmacy_stock.php"><i class="bi bi-capsule"></i> Pharmacy</a></li>
            <li><a href="medicines.php"><i class="bi bi-heart-pulse"></i> Medicines</a></li>
            <li><a href="wards.php"><i class="bi bi-house-door"></i> Wards</a></li>
            <li><a href="rooms.php"><i class="bi bi-door-closed"></i> Rooms</a></li>
            <li><a href="messages.php"><i class="bi bi-chat-dots"></i> Messages</a></li>
            <li><a href="admissions.php"><i class="bi bi-journal-plus"></i> Admissions</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4" style="margin-top: 70px;">
            <h1 class="h3"><i class="bi bi-journal-plus me-2"></i>Patient Admissions</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdmissionModal">
                <i class="bi bi-plus-circle me-2"></i>Admit Patient
            </button>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stats-label">Total Admissions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['admitted'] ?? 0; ?></div>
                    <div class="stats-label">Currently Admitted</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['discharged'] ?? 0; ?></div>
                    <div class="stats-label">Discharged</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['today'] ?? 0; ?></div>
                    <div class="stats-label">Admissions Today</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul me-2"></i>Admission Records (<?php echo count($admissions); ?>)
            </div>
            <div class="card-body">
                <?php if (empty($admissions)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                        <p class="text-muted">No admission records found. Admit your first patient to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <div class="table-wrapper">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Patient Information</th>
                                        <th>Doctor</th>
                                        <th>Ward & Room</th>
                                        <th>Admission Date</th>
                                        <th>Status</th>
                                        <th>MRN</th>
                                        <th>Admitted By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admissions as $admission): ?>
                                        <tr>
                                            <td><strong>#<?php echo $admission['id']; ?></strong></td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($admission['patient_first_name'] . ' ' . $admission['patient_last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        DOB: <?php echo date('M j, Y', strtotime($admission['patient_dob'])); ?>
                                                        | <?php echo ucfirst($admission['patient_gender']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong>Dr. <?php echo htmlspecialchars($admission['doctor_first_name'] . ' ' . $admission['doctor_last_name']); ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="ward-badge"><?php echo htmlspecialchars($admission['ward_name']); ?></span>
                                                <?php if (!empty($admission['room_number'])): ?>
                                                    <br>
                                                    <span class="room-type-badge room-type-<?php echo $admission['room_type']; ?>">
                                                        Room <?php echo htmlspecialchars($admission['room_number']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y', strtotime($admission['admitted_at'])); ?>
                                                <br>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($admission['admitted_at'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $status_colors[$admission['status']]; ?>">
                                                    <?php echo $status_display[$admission['status']]; ?>
                                                </span>
                                                <?php if ($admission['status'] === 'discharged' && !empty($admission['discharged_at'])): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($admission['discharged_at'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($admission['medical_record_number']); ?></code>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($admission['admitted_by_first_name'] . ' ' . $admission['admitted_by_last_name']); ?></small>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-warning btn-sm text-white"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editAdmissionModal"
                                                            data-admission-id="<?php echo $admission['id']; ?>"
                                                            data-patient-id="<?php echo $admission['patient_id']; ?>"
                                                            data-ward-id="<?php echo $admission['ward_id']; ?>"
                                                            data-room-id="<?php echo $admission['room_id']; ?>"
                                                            data-admitting-doctor-id="<?php echo $admission['admitting_doctor_id']; ?>"
                                                            data-status="<?php echo $admission['status']; ?>"
                                                            data-discharge-summary="<?php echo htmlspecialchars($admission['discharge_summary'] ?? ''); ?>">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this admission record?')">
                                                        <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
                                                        <input type="hidden" name="admission_id" value="<?php echo $admission['id']; ?>">
                                                        <button type="submit" name="delete_admission" class="btn btn-danger btn-sm">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Admission Modal -->
<div class="modal fade" id="addAdmissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Admit Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Patient *</label>
                            <select class="form-select" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                        (MRN: <?php echo htmlspecialchars($patient['medical_record_number']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Admitting Doctor *</label>
                            <select class="form-select" name="admitting_doctor_id" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ward *</label>
                            <select class="form-select" name="ward_id" id="ward_id" required>
                                <option value="">Select Ward</option>
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>">
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                        (<?php echo htmlspecialchars($ward['type']); ?> - Capacity: <?php echo $ward['capacity']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Room</label>
                            <select class="form-select" name="room_id" id="room_id">
                                <option value="">Select Room (Optional)</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>" data-ward-id="<?php echo $room['ward_id']; ?>" style="display: none;">
                                        Room <?php echo htmlspecialchars($room['room_number']); ?>
                                        (<?php echo $room_type_display[$room['room_type']]; ?> - Capacity: <?php echo $room['capacity']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="admitted">Admitted</option>
                            <option value="discharged">Discharged</option>
                            <option value="transferred">Transferred</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discharge Summary</label>
                        <textarea class="form-control" name="discharge_summary" rows="3" placeholder="Enter discharge summary if applicable..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_admission" class="btn btn-primary">Admit Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admission Modal -->
<div class="modal fade" id="editAdmissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
                <input type="hidden" name="admission_id" id="edit_admission_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Admission</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Patient *</label>
                            <select class="form-select" name="patient_id" id="edit_patient_id" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                        (MRN: <?php echo htmlspecialchars($patient['medical_record_number']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Admitting Doctor *</label>
                            <select class="form-select" name="admitting_doctor_id" id="edit_admitting_doctor_id" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ward *</label>
                            <select class="form-select" name="ward_id" id="edit_ward_id" required>
                                <option value="">Select Ward</option>
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>">
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                        (<?php echo htmlspecialchars($ward['type']); ?> - Capacity: <?php echo $ward['capacity']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Room</label>
                            <select class="form-select" name="room_id" id="edit_room_id">
                                <option value="">Select Room (Optional)</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>" data-ward-id="<?php echo $room['ward_id']; ?>">
                                        Room <?php echo htmlspecialchars($room['room_number']); ?>
                                        (<?php echo $room_type_display[$room['room_type']]; ?> - Capacity: <?php echo $room['capacity']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="admitted">Admitted</option>
                            <option value="discharged">Discharged</option>
                            <option value="transferred">Transferred</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Discharge Summary</label>
                        <textarea class="form-control" name="discharge_summary" id="edit_discharge_summary" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_admission" class="btn btn-primary">Update Admission</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Room filtering based on selected ward
    function filterRooms(wardId, roomSelectId) {
        const roomSelect = document.getElementById(roomSelectId);
        const options = roomSelect.querySelectorAll('option');
        
        options.forEach(option => {
            if (option.value === '') {
                option.style.display = 'block';
            } else {
                const optionWardId = option.getAttribute('data-ward-id');
                if (optionWardId === wardId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            }
        });
        
        // Reset selection if current selection is not in filtered list
        const currentValue = roomSelect.value;
        if (currentValue && roomSelect.querySelector(`option[value="${currentValue}"]`).style.display === 'none') {
            roomSelect.value = '';
        }
    }

    // Add admission modal room filtering
    const addWardSelect = document.getElementById('ward_id');
    if (addWardSelect) {
        addWardSelect.addEventListener('change', function() {
            filterRooms(this.value, 'room_id');
        });
    }

    // Edit admission modal room filtering
    const editWardSelect = document.getElementById('edit_ward_id');
    if (editWardSelect) {
        editWardSelect.addEventListener('change', function() {
            filterRooms(this.value, 'edit_room_id');
        });
    }

    // Edit admission modal
    const editAdmissionModal = document.getElementById('editAdmissionModal');
    if (editAdmissionModal) {
        editAdmissionModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            
            document.getElementById('edit_admission_id').value = button.getAttribute('data-admission-id');
            document.getElementById('edit_patient_id').value = button.getAttribute('data-patient-id');
            document.getElementById('edit_ward_id').value = button.getAttribute('data-ward-id');
            document.getElementById('edit_admitting_doctor_id').value = button.getAttribute('data-admitting-doctor-id');
            document.getElementById('edit_status').value = button.getAttribute('data-status');
            document.getElementById('edit_discharge_summary').value = button.getAttribute('data-discharge-summary') || '';
            
            // Set room and filter rooms
            const roomId = button.getAttribute('data-room-id');
            document.getElementById('edit_room_id').value = roomId;
            filterRooms(button.getAttribute('data-ward-id'), 'edit_room_id');
        });
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>
</body>
</html>
