<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2) can access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: login.php");
    exit;
}

// Get patient ID
$patient_id = $_GET['id'] ?? null;
if (!$patient_id) {
    die("Patient ID is required.");
}

// Fetch patient
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();
if (!$patient) die("Patient not found.");

// Initialize messages
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mrn = trim($_POST['medical_record_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $dob = $_POST['dob'] ?: null;
    $gender = $_POST['gender'];
    $national_id = trim($_POST['national_id']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);

    try {
        $stmt = $pdo->prepare("
            UPDATE patients SET
                medical_record_number = ?,
                first_name = ?,
                last_name = ?,
                dob = ?,
                gender = ?,
                national_id = ?,
                address = ?,
                phone = ?,
                email = ?,
                emergency_contact_name = ?,
                emergency_contact_phone = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $mrn, $first_name, $last_name, $dob, $gender,
            $national_id, $address, $phone, $email,
            $emergency_contact_name, $emergency_contact_phone,
            $patient_id
        ]);

        $success = "Patient updated successfully!";
        // Refresh patient data
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch();

    } catch (PDOException $e) {
        $error = "Error: ".$e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Patient</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
/* ===================== BODY ===================== */
body {
    font-family: 'Montserrat', sans-serif;
    background-color: #f4f7fa;
    margin: 0;
}

/* ===================== SIDEBAR ===================== */
.sidebar {
    width: 220px;
    background: #1e293b;
    min-height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    padding: 20px;
    color: #fff;
}
.sidebar h3 {
    color: #fff;
    margin-bottom: 30px;
    font-weight: 700;
    text-align: center;
}
.sidebar a {
    display: block;
    padding: 12px 15px;
    color: #cbd5e1;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 5px;
    transition: all 0.3s ease;
}
.sidebar a:hover, .sidebar a.active {
    background-color: #3b82f6;
    color: #fff;
    font-weight: 600;
}

/* ===================== MAIN CONTENT ===================== */
.main-content {
    margin-left: 240px;
    padding: 40px 30px;
}

/* ===================== CARD ===================== */
.card-modern {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

/* ===================== FORM FIELDS ===================== */
.form-label {
    font-weight: 600;
}
input.form-control, textarea.form-control, select.form-select {
    border-radius: 8px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}
input.form-control:focus, textarea.form-control:focus, select.form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 8px rgba(59,130,246,0.2);
}

/* ===================== BUTTONS ===================== */
.btn-primary {
    border-radius: 8px;
    padding: 8px 18px;
}
.btn-secondary {
    border-radius: 8px;
    padding: 8px 18px;
}
.btn-back {
    float: right;
}

/* ===================== ALERTS ===================== */
.alert {
    border-radius: 10px;
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
<h4>Dashboard Menu</h4>
<a href="manager_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
<a href="patients.php" class="active"><i class="bi bi-person-fill me-2"></i>All Patients</a>
<a href="users.php"><i class="bi bi-people-fill me-2"></i>Users</a>
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
    <div class="card-modern">
        <h2 class="mb-4">Edit Patient</h2>

        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">MRN</label>
                    <input type="text" name="medical_record_number" class="form-control" value="<?php echo htmlspecialchars($patient['medical_record_number']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">National ID</label>
                    <input type="text" name="national_id" class="form-control" value="<?php echo htmlspecialchars($patient['national_id']); ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($patient['first_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($patient['last_name']); ?>" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($patient['dob']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <?php
                        $genders = ['male','female','other','unspecified'];
                        foreach($genders as $g){
                            $selected = ($patient['gender']==$g)?'selected':'';
                            echo "<option value='$g' $selected>".ucfirst($g)."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($patient['phone']); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($patient['email']); ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control"><?php echo htmlspecialchars($patient['address']); ?></textarea>
            </div>

             <div class="mb-3">
                 <label>Emergency Contact Name</label>
                 <input type="text" name="emergency_contact_name" class="form-control"value="<?php echo htmlspecialchars($patient['emergency_contact_name']); ?>">
           </div>

           <div class="mb-3">
                 <label>Emergency Contact Phone</label>
                 <input type="text" name="emergency_contact_phone" class="form-control"value="<?php echo htmlspecialchars($patient['emergency_contact_phone']); ?>">
           </div>

            <div class="d-flex justify-content-between">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
                <a href="patients.php" class="btn btn-secondary btn-back"><i class="bi bi-arrow-left"></i> Back to Patients</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
