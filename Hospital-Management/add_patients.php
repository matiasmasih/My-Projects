<?php
session_start();
include 'config.php';
include_once 'access_control.php';

// Require login + access check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('patients', 'login.php');

// Only full access can add
if ($access !== 'full') {
    header("Location: patients.php");
    exit;
}

$error = '';
$success = '';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Auto-generate MRN: MRN-0001, MRN-0002, ...
    $mrn = 'MRN-0001';
    try {
        $stmt = $pdo->query("SELECT medical_record_number FROM patients WHERE medical_record_number LIKE 'MRN-%' ORDER BY id DESC LIMIT 1");
        $last = $stmt->fetchColumn();
        if ($last) {
            $num = intval(substr($last, 4));
            $mrn = 'MRN-' . str_pad($num + 1, 4, '0', STR_PAD_LEFT);
        }
    } catch (Exception $e) {}

    $first_name      = trim($_POST['first_name'] ?? '');
    $last_name       = trim($_POST['last_name'] ?? '');
    $dob             = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender          = $_POST['gender'] ?? '';
    $national_id     = trim($_POST['national_id'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $emergency_name  = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_phone = trim($_POST['emergency_contact_phone'] ?? '');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO patients
            (medical_record_number, first_name, last_name, dob, gender, national_id, address, phone, email, emergency_contact_name, emergency_contact_phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$mrn, $first_name, $last_name, $dob, $gender, $national_id, $address, $phone, $email, $emergency_name, $emergency_phone]);

        $success = "Patient added successfully! (MRN: " . htmlspecialchars($mrn) . ")";
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

$page_title = 'Add Patient';
include 'patients_header.php';
?>

<!-- Alerts -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?>
        — <a href="patients.php">Back to Patients</a>
    </div>
<?php endif; ?>

<!-- Card -->
<div class="table-card">
    <h5><i class="bi bi-person-plus-fill"></i> Add New Patient</h5>

    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 18px;">
        <i class="bi bi-info-circle"></i> MRN will be generated automatically.
    </p>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="first_name" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="last_name" class="form-control" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="dob" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Gender <span style="color: #ef4444;">*</span></label>
                <select name="gender" class="form-select" required>
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">National ID</label>
                <input type="text" name="national_id" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control">
            </div>

            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control">
            </div>

            <div class="col-md-6">
                <label class="form-label">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Emergency Contact Phone</label>
                <input type="text" name="emergency_contact_phone" class="form-control">
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Patient</button>
            <a href="patients.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Patients</a>
        </div>
    </form>
</div>

<?php include 'patients_footer.php'; ?>
