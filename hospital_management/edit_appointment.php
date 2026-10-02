<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('appointments', 'login.php');

if ($access !== 'full') {
    header("Location: appointments.php");
    exit;
}

$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($appointment_id <= 0) die("Invalid appointment ID.");

try {
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    if (!$appointment) die("Appointment not found.");

    $patients = $pdo->query("SELECT id, first_name, last_name, medical_record_number FROM patients ORDER BY first_name")->fetchAll();
    $doctors  = $pdo->query("
        SELECT d.id, u.first_name, u.last_name
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        ORDER BY u.first_name
    ")->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id       = (int)($_POST['patient_id'] ?? 0);
    $doctor_id        = (int)($_POST['doctor_id'] ?? 0);
    $scheduled_at     = trim($_POST['scheduled_at'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 30);
    $status           = $_POST['status'] ?? 'scheduled';
    $reason           = trim($_POST['reason'] ?? '');

    // Normalize datetime for MySQL
    if ($scheduled_at !== '') {
        $scheduled_at = str_replace('T', ' ', $scheduled_at);
        if (strlen($scheduled_at) === 16) {
            $scheduled_at .= ':00';
        }
    }

    if ($patient_id > 0 && $doctor_id > 0 && $scheduled_at !== '') {
        try {
            $stmt = $pdo->prepare("
                UPDATE appointments SET
                    patient_id = ?, doctor_id = ?, scheduled_at = ?,
                    duration_minutes = ?, status = ?, reason = ?
                WHERE id = ?
            ");
            $stmt->execute([$patient_id, $doctor_id, $scheduled_at, $duration_minutes, $status, $reason, $appointment_id]);

            $_SESSION['flash_success'] = "Appointment updated successfully.";
            header("Location: appointments.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill all required fields.";
    }
}

// Current values for the form
$current_scheduled = $appointment['scheduled_at']
    ? date('Y-m-d H:i', strtotime($appointment['scheduled_at']))
    : '';

$current_mrn = '';
foreach ($patients as $p) {
    if ((int)$p['id'] === (int)$appointment['patient_id']) {
        $current_mrn = $p['medical_record_number'] ?? '';
        break;
    }
}

$page_title = 'Edit Appointment';
include 'appointments_header.php';
?>

<style>
    /* Modern flatpickr styling */
    .flatpickr-calendar {
        border-radius: 14px !important;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15) !important;
        border: 1px solid var(--border) !important;
        font-family: 'Inter', sans-serif !important;
        padding: 6px !important;
    }
    .flatpickr-day.selected,
    .flatpickr-day.selected:hover {
        background: var(--primary) !important;
        border-color: var(--primary) !important;
    }
    .flatpickr-day:hover {
        background: rgba(20, 184, 166, 0.15) !important;
        border-color: transparent !important;
    }
    .flatpickr-day.today {
        border-color: var(--primary) !important;
    }
    .flatpickr-time input:hover,
    .flatpickr-time .flatpickr-am-pm:hover {
        background: rgba(20, 184, 166, 0.1) !important;
    }
    .flatpickr-time .flatpickr-am-pm:focus,
    .flatpickr-time input:focus {
        background: rgba(20, 184, 166, 0.15) !important;
    }
    .flatpickr-months .flatpickr-month,
    .flatpickr-current-month .flatpickr-monthDropdown-months {
        font-weight: 600;
    }
    .flatpickr-months .flatpickr-prev-month:hover svg,
    .flatpickr-months .flatpickr-next-month:hover svg {
        fill: var(--primary) !important;
    }
    input#scheduled_at {
        background: #fff;
        cursor: pointer;
    }
</style>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-pencil-square"></i> Edit Appointment #<?= (int)$appointment['id'] ?></h5>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Patient <span style="color: #ef4444;">*</span></label>
                <select name="patient_id" id="patient_select" class="form-select" required onchange="fillMRN()">
                    <option value="" data-mrn="">Select Patient</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"
                                data-mrn="<?= htmlspecialchars($p['medical_record_number'] ?? '') ?>"
                                <?= (int)$p['id'] === (int)$appointment['patient_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">MRN</label>
                <input type="text" id="patient_mrn" class="form-control" readonly
                       value="<?= htmlspecialchars($current_mrn) ?>"
                       placeholder="Auto-fills when you pick a patient"
                       style="background: #f8fafc;">
            </div>

            <div class="col-md-6">
                <label class="form-label">Doctor <span style="color: #ef4444;">*</span></label>
                <select name="doctor_id" class="form-select" required>
                    <option value="">Select Doctor</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)$d['id'] === (int)$appointment['doctor_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Duration (minutes) <span style="color: #ef4444;">*</span></label>
                <input type="number" name="duration_minutes" class="form-control" min="1"
                       value="<?= (int)$appointment['duration_minutes'] ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Scheduled At <span style="color: #ef4444;">*</span></label>
                <input type="text" name="scheduled_at" id="scheduled_at" class="form-control"
                       value="<?= htmlspecialchars($current_scheduled) ?>"
                       placeholder="Pick date & time" required autocomplete="off">
            </div>
            <div class="col-md-6">
                <label class="form-label">Status <span style="color: #ef4444;">*</span></label>
                <select name="status" class="form-select" required>
                    <?php foreach (['scheduled','confirmed','in_progress','completed','cancelled','no_show'] as $s): ?>
                        <option value="<?= $s ?>" <?= $s === $appointment['status'] ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', $s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-12">
                <label class="form-label">Reason <span style="color: #ef4444;">*</span></label>
                <input type="text" name="reason" class="form-control"
                       value="<?= htmlspecialchars($appointment['reason'] ?? '') ?>" required>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Changes</button>
            <a href="appointments.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Appointments</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// ---- Auto-fill MRN from patient dropdown ----
function fillMRN() {
    const sel = document.getElementById('patient_select');
    const mrnField = document.getElementById('patient_mrn');
    const opt = sel.options[sel.selectedIndex];
    const mrn = opt.getAttribute('data-mrn') || '';
    mrnField.value = mrn;
}

// ---- Modern Flatpickr calendar ----
flatpickr("#scheduled_at", {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    altInput: true,
    altFormat: "F j, Y  ·  h:i K",
    minuteIncrement: 5,
    time_24hr: false,
    disableMobile: true
});
</script>

<?php include 'appointments_footer.php'; ?>
