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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id          = (int)($_POST['patient_id'] ?? 0);
    $ward_id             = (int)($_POST['ward_id'] ?? 0);
    $room_id             = (int)($_POST['room_id'] ?? 0);
    $admitting_doctor_id = (int)($_POST['admitting_doctor_id'] ?? 0);
    $status              = $_POST['status'] ?? 'admitted';

    if ($patient_id <= 0 || $ward_id <= 0 || $admitting_doctor_id <= 0) {
        $error = "Please fill in all required fields.";
    } elseif (!in_array($status, ['admitted', 'transferred'], true)) {
        $error = "Invalid status for a new admission.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO admissions (patient_id, ward_id, room_id, admitting_doctor_id, admitted_by, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $room_param = $room_id > 0 ? $room_id : null;
            $stmt->execute([$patient_id, $ward_id, $room_param, $admitting_doctor_id, (int)$_SESSION['user_id'], $status]);

            $_SESSION['flash_success'] = "Patient admitted successfully.";
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
    $patients = $pdo->query("
        SELECT id, first_name, last_name, medical_record_number
        FROM patients ORDER BY first_name, last_name
    ")->fetchAll();
} catch (PDOException $e) {}

try {
    $doctors = $pdo->query("
        SELECT d.id, u.first_name, u.last_name
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        ORDER BY u.first_name, u.last_name
    ")->fetchAll();
} catch (PDOException $e) {}

try {
    $wards = $pdo->query("SELECT id, name, type, capacity FROM wards WHERE status='active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {}

try {
    // Only rooms that belong to an active ward
    $rooms = $pdo->query("
        SELECT r.id, r.room_number, r.ward_id
        FROM rooms r
        JOIN wards w ON r.ward_id = w.id
        WHERE w.status = 'active'
        ORDER BY r.room_number
    ")->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Admit Patient';
include 'admissions_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-plus-circle"></i> Admit Patient</h5>

    <form method="POST" id="admissionForm">
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
                <label class="form-label">Admitting Doctor <span style="color:#ef4444;">*</span></label>
                <select name="admitting_doctor_id" class="form-select" required>
                    <option value="">Select Doctor</option>
                    <?php foreach ($doctors as $d): ?>
                        <option value="<?= (int)$d['id'] ?>">
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
                        <option value="<?= (int)$w['id'] ?>">
                            <?= htmlspecialchars($w['name']) ?>
                            (<?= htmlspecialchars($w['type']) ?> — Capacity: <?= (int)$w['capacity'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Room (Optional)</label>
                <select name="room_id" id="room_id" class="form-select">
                    <option value="" data-ward="">Select Room</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" data-ward="<?= (int)$r['ward_id'] ?>">
                            Room <?= htmlspecialchars($r['room_number']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: var(--text-muted); font-size: 0.8rem;" id="roomHint">
                    Pick a ward first, then the room list will filter.
                </small>
            </div>

            <div class="col-md-6">
                <label class="form-label">Initial Status</label>
                <select name="status" class="form-select">
                    <option value="admitted">Admitted</option>
                    <option value="transferred">Transferred</option>
                </select>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Admit Patient</button>
            <a href="admissions.php" class="btn btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back to Admissions</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const wardSel = document.getElementById('ward_id');
    const roomSel = document.getElementById('room_id');
    const hint    = document.getElementById('roomHint');

    // Snapshot all room options (except placeholder) once
    const allRoomOptions = Array.from(roomSel.querySelectorAll('option'))
        .filter(o => o.value !== '')
        .map(o => o.cloneNode(true));

    function refreshRooms() {
        const wardId = wardSel.value;

        // Clear all rooms except placeholder
        Array.from(roomSel.querySelectorAll('option')).forEach(o => {
            if (o.value !== '') o.remove();
        });

        if (!wardId) {
            hint.textContent = "Pick a ward first, then the room list will filter.";
            return;
        }

        let added = 0;
        allRoomOptions.forEach(opt => {
            if (opt.getAttribute('data-ward') === wardId) {
                roomSel.appendChild(opt.cloneNode(true));
                added++;
            }
        });

        hint.textContent = added > 0
            ? added + " room(s) available in this ward."
            : "No rooms available in this ward.";
    }

    wardSel.addEventListener('change', refreshRooms);
});
</script>

<?php include 'admissions_footer.php'; ?>
