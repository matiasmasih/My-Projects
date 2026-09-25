<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('medical_records', 'login.php');
if ($access !== 'full') {
    header("Location: medical_records.php");
    exit;
}

$role = current_role();
$my_id = (int)$_SESSION['user_id'];

// Doctor's own doctor row
$my_doctor_id = 0;
if ($role === 'doctor') {
    try {
        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $stmt->execute([$my_id]);
        $my_doctor_id = (int)($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) {}
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id  = (int)($_POST['patient_id'] ?? 0);
    $doctor_id   = (int)($_POST['doctor_id'] ?? 0);
    $visit_date  = trim($_POST['visit_date'] ?? '');
    $visit_type  = trim($_POST['visit_type'] ?? '');
    $summary     = trim($_POST['summary'] ?? '');
    $diagnosis   = trim($_POST['diagnosis'] ?? '');
    $treatment   = trim($_POST['treatment'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    if ($patient_id <= 0 || $doctor_id <= 0 || $visit_date === '' || $visit_type === '') {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO medical_records
                    (patient_id, doctor_id, visit_date, visit_type, summary, diagnosis, treatment, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$patient_id, $doctor_id, $visit_date, $visit_type, $summary, $diagnosis, $treatment, $notes]);

            $_SESSION['flash_success'] = "Medical record created.";
            header("Location: medical_records.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Dropdowns
$patients = [];
$doctors_list = [];
try {
    $patients = $pdo->query("
        SELECT id, first_name, last_name, medical_record_number
        FROM patients ORDER BY first_name, last_name
    ")->fetchAll();
} catch (PDOException $e) {}

try {
    $doctors_list = $pdo->query("
        SELECT d.id, u.first_name, u.last_name
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        ORDER BY u.first_name, u.last_name
    ")->fetchAll();
} catch (PDOException $e) {}

$page_title = 'New Medical Record';
include 'doctor_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="medical_records.php" class="btn btn-secondary-custom">
        <i class="bi bi-arrow-left"></i> Back to Records
    </a>
</div>

<div class="table-card">
    <h5><i class="bi bi-file-medical"></i> New Medical Record</h5>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Patient <span style="color:#ef4444;">*</span></label>
                <select name="patient_id" class="form-select" required>
                    <option value="">Select Patient</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= (int)$p['id'] ?>">
                            <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?>
                            (MRN: <?= htmlspecialchars($p['medical_record_number'] ?? '-') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Doctor <span style="color:#ef4444;">*</span></label>
                <select name="doctor_id" class="form-select" required>
                    <option value="">Select Doctor</option>
                    <?php foreach ($doctors_list as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= ($role === 'doctor' && (int)$d['id'] === $my_doctor_id) ? 'selected' : '' ?>>
                            Dr. <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Visit Date <span style="color:#ef4444;">*</span></label>
                <input type="datetime-local" name="visit_date" class="form-control" required
                       value="<?= date('Y-m-d\TH:i') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Visit Type <span style="color:#ef4444;">*</span></label>
                <select name="visit_type" class="form-select" required>
                    <option value="">Select Type</option>
                    <?php foreach (['Consultation','Follow-up','Lab Test','Vaccination','Emergency','Surgery','Other'] as $t): ?>
                        <option value="<?= $t ?>"><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-12">
                <label class="form-label">Summary</label>
                <textarea name="summary" class="form-control" rows="3" placeholder="Brief summary of the visit..."></textarea>
            </div>

            <div class="col-md-12">
                <label class="form-label">Diagnosis</label>
                <textarea name="diagnosis" class="form-control" rows="3" placeholder="Medical diagnosis..."></textarea>
            </div>

            <div class="col-md-12">
                <label class="form-label">Treatment</label>
                <textarea name="treatment" class="form-control" rows="3" placeholder="Treatment plan, medications, follow-up..."></textarea>
            </div>

            <div class="col-md-12">
                <label class="form-label">Additional Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Any other relevant notes..."></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Record</button>
            <a href="medical_records.php" class="btn btn-secondary-custom"><i class="bi bi-x-lg"></i> Cancel</a>
        </div>
    </form>
</div>

<?php include 'doctor_footer.php'; ?>
