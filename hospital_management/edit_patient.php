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

// Only full access can edit
if ($access !== 'full') {
    header("Location: patients.php");
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

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $dob          = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender       = $_POST['gender'] ?? '';
    $national_id  = trim($_POST['national_id'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $emergency_name  = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_phone = trim($_POST['emergency_contact_phone'] ?? '');

    try {
        $stmt = $pdo->prepare("
            UPDATE patients SET
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
            $first_name, $last_name, $dob, $gender,
            $national_id, $address, $phone, $email,
            $emergency_name, $emergency_phone,
            $patient_id
        ]);

        $success = "Patient updated successfully!";

        // Refresh patient data
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch();

    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$page_title = 'Edit Patient';
include 'patients_header.php';
?>

<!-- Alerts -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Card -->
<div class="table-card">
    <h5><i class="bi bi-pencil-square"></i> Edit Patient — <?= htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')) ?></h5>

    <div style="background: #f8fafc; padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: 0.9rem;">
        <i class="bi bi-hash"></i> MRN: <strong><?= htmlspecialchars($patient['medical_record_number'] ?? '-') ?></strong>
        <span style="color: var(--text-muted); margin-left: 10px;">(cannot be changed)</span>
    </div>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($patient['first_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($patient['last_name'] ?? '') ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($patient['dob'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select">
                    <?php foreach (['male','female','other','unspecified'] as $g): ?>
                        <option value="<?= $g ?>" <?= ($patient['gender'] ?? '') === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">National ID</label>
                <input type="text" name="national_id" class="form-control" value="<?= htmlspecialchars($patient['national_id'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($patient['address'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($patient['emergency_contact_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Emergency Contact Phone</label>
                <input type="text" name="emergency_contact_phone" class="form-control" value="<?= htmlspecialchars($patient['emergency_contact_phone'] ?? '') ?>">
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Changes</button>
            <a href="patients.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Patients</a>
        </div>
    </form>
</div>

<?php include 'patients_footer.php'; ?>
