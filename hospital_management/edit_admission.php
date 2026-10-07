<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('admissions', 'login.php');
if ($access !== 'full') {
    header("Location: admissions.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: admissions.php");
    exit;
}

// Fetch admission
try {
    $stmt = $pdo->prepare("SELECT * FROM admissions WHERE id = ?");
    $stmt->execute([$id]);
    $admission = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admission) {
        header("Location: admissions.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id          = (int)($_POST['patient_id'] ?? 0);
    $ward_id             = (int)($_POST['ward_id'] ?? 0);
    $room_id             = (int)($_POST['room_id'] ?? 0);
    $admitting_doctor_id = (int)($_POST['admitting_doctor_id'] ?? 0);
    $status              = $_POST['status'] ?? 'admitted';

    if ($patient_id <= 0 || $ward_id <= 0 || $admitting_doctor_id <= 0) {
        $error = "Please fill in all required fields.";
    } elseif (!in_array($status, ['admitted', 'discharged', 'transferred'], true)) {
        $error = "Invalid status.";
    } else {
        try {
            $discharge_date = null;
            if ($status === 'discharged' && empty($admission['discharged_at'])) {
                $discharge_date = date('Y-m-d H:i:s');
            } elseif ($status === 'discharged') {
                $discharge_date = $admission['discharged_at'];
            }

            $room_param = $room_id > 0 ? $room_id : null;

            $stmt = $pdo->prepare("
                UPDATE admissions SET
                    patient_id = ?, ward_id = ?, room_id = ?,
                    admitting_doctor_id = ?, status = ?, discharged_at = ?
                WHERE id = ?
            ");
            $stmt->execute([$patient_id, $ward_id, $room_param, $admitting_doctor_id, $status, $discharge_date, $id]);

            $_SESSION['flash_success'] = "Admission updated successfully.";
            header("Location: admissions.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch dropdowns
$patients = $doctors = $wards = $rooms = [];

try {
    $patients = $pdo->query("SELECT id, first_name, last_name, medical_record_number FROM patients ORDER BY first_name, last_name")->fetchAll();
} catch (PDOException $e) {}

try {
    $doctors = $pdo->query("SELECT d.id, u.first_name, u.last_name FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY u.first_name, u.last_name")->fetchAll();
} catch (PDOException $e) {}

try {
    $wards = $pdo->query("SELECT id, name, type, capacity FROM wards WHERE status='active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {}

try {
    $rooms = $pdo->query("SELECT id, room_number, ward_id FROM rooms ORDER BY room_number")->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Edit Admission';
include 'admissions_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-pencil-square"></i> Edit Admission #<?= (int)$admission['id'] ?></h5>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Patient <span style="color:#ef4444;">*</span></label>
                <select name="patient_id" class="form-select" required>
                    <option value="">Select Patient</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$admission['patient_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?>
                            (MRN: <?= htmlspecialchars($p['medical_record_number'] ?? '-') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Admitting Doctor <span style="color:#ef4444;">*</span></label>
                <select name="admitting_doctor_id" class="form-select" required>
                    <option value="">Select Doctor</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)$d['id'] === (int)$admission['admitting_doctor_id'] ? 'selected' : '' ?>>
                            Dr. <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Ward <span style="color:#ef4444;">*</span></label>
                <select name="ward_id" id="ward_id" class="form-select" required>
                    <option value="">Select Ward</option>
                    <?php foreach ($wards as $w): ?>
                        <option value="<?= (int)$w['id'] ?>" <?= (int)$w['id'] === (int)$admission['ward_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($w['name']) ?>
                            (<?= htmlspecialchars($w['type']) ?> — Capacity: <?= (int)$w['capacity'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Room (Optional)</label>
                <select name="room_id" id="room_id" class="form-select">
                    <option value="">Select Room</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" data-ward="<?= (int)$r['ward_id'] ?>"
                            <?= (int)$r['id'] === (int)($admission['room_id'] ?? 0) ? 'selected' : '' ?>>
                            Room <?= htmlspecialchars($r['room_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Status <span style="color:#ef4444;">*</span></label>
                <select name="status" class="form-select" required>
                    <?php foreach (['admitted' => 'Admitted', 'discharged' => 'Discharged', 'transferred' => 'Transferred'] as $key => $label): ?>
                        <option value="<?= $key ?>" <?= ($admission['status'] ?? '') === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Changes</button>
            <a href="admissions.php" class="btn btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back to Admissions</a>
        </div>
    </form>
</div>

<script>
// Filter rooms by selected ward
function filterRooms() {
    const wardId = document.getElementById('ward_id').value;
    const roomSel = document.getElementById('room_id');
    const currentVal = roomSel.value;

    Array.from(roomSel.options).forEach(opt => {
        if (opt.value === '') { opt.style.display = 'block'; return; }
        opt.style.display = (opt.getAttribute('data-ward') === wardId) ? 'block' : 'none';
    });

    // If current selection is now hidden, clear it
    const selectedOpt = roomSel.querySelector('option[value="' + currentVal + '"]');
    if (selectedOpt && selectedOpt.style.display === 'none') {
        roomSel.value = '';
    }
}

document.getElementById('ward_id').addEventListener('change', filterRooms);

// Apply filter on page load so only matching rooms show
document.addEventListener('DOMContentLoaded', function() {
    const wardId = document.getElementById('ward_id').value;
    if (wardId) filterRooms();
});
</script>

<?php include 'admissions_footer.php'; ?>
