<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('appointments', 'login.php');
// full | view | own | no

// Flash messages (PRG)
$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// Determine who is viewing
$role = current_role();
$my_email = $_SESSION['email'] ?? '';

// For doctor: find their doctor_id
$my_doctor_id = 0;
if ($role === 'doctor') {
    try {
        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id'] ?? 0]);
        $my_doctor_id = (int)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {}
}

// For patient: find their patient_id
$my_patient_id = 0;
if ($role === 'patient') {
    try {
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE email = ?");
        $stmt->execute([$my_email]);
        $my_patient_id = (int)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {}
}

// ============================================
// HANDLE ADD APPOINTMENT (POST, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_appointment']) && $access === 'full') {
    $patient_id       = (int)($_POST['patient_id'] ?? 0);
    $doctor_id        = (int)($_POST['doctor_id'] ?? 0);
    $scheduled_at     = trim($_POST['scheduled_at'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 30);
    $status           = $_POST['status'] ?? 'scheduled';
    $reason           = trim($_POST['reason'] ?? '');

    if ($patient_id > 0 && $doctor_id > 0 && $scheduled_at !== '') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO appointments (patient_id, doctor_id, scheduled_at, duration_minutes, status, reason)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$patient_id, $doctor_id, $scheduled_at, $duration_minutes, $status, $reason]);
            $_SESSION['flash_success'] = "Appointment added successfully.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Database Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Missing required fields.";
    }
    header("Location: appointments.php");
    exit;
}

// ============================================
// HANDLE DELETE APPOINTMENT (POST, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_appointment']) && $access === 'full') {
    $del_id = (int)($_POST['appointment_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
            $stmt->execute([$del_id]);
            $_SESSION['flash_success'] = "Appointment deleted successfully.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Invalid appointment ID.";
    }
    header("Location: appointments.php");
    exit;
}

// ============================================
// FETCH APPOINTMENTS (role-aware)
// ============================================
try {
    $sql = "
        SELECT a.id, a.scheduled_at, a.duration_minutes, a.status, a.reason,
               a.patient_id, a.doctor_id,
               p.first_name AS patient_first, p.last_name AS patient_last,
               p.medical_record_number AS patient_mrn,
               u.first_name AS doctor_first, u.last_name AS doctor_last
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
    ";

    $params = [];
    if ($role === 'doctor' && $my_doctor_id > 0) {
        $sql .= " WHERE a.doctor_id = ?";
        $params[] = $my_doctor_id;
    } elseif ($role === 'patient' && $my_patient_id > 0) {
        $sql .= " WHERE a.patient_id = ?";
        $params[] = $my_patient_id;
    }

    $sql .= " ORDER BY a.scheduled_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ============================================
// DROPDOWN DATA (only needed if full access)
// ============================================
$patients = [];
$doctors  = [];
if ($access === 'full') {
    try {
        $patients = $pdo->query("SELECT id, first_name, last_name FROM patients ORDER BY first_name")->fetchAll();
        $doctors  = $pdo->query("
            SELECT d.id, u.first_name, u.last_name
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            ORDER BY u.first_name
        ")->fetchAll();
    } catch (PDOException $e) {}
}

$page_title = 'Appointments';
include 'appointments_header.php';
?>

<!-- Alerts -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search appointments...">
        <button class="btn btn-add" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>

    <?php if ($access === 'full'): ?>
    <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
        <i class="bi bi-plus-circle"></i> Add Appointment
    </button>
    <?php endif; ?>
</div>

<!-- Appointments Table -->
<div class="table-card">
    <h5><i class="bi bi-calendar3"></i> <?= ($role === 'doctor' || $role === 'patient') ? 'My Appointments' : 'All Appointments' ?> (<?= count($appointments); ?>)</h5>

    <?php if (count($appointments) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>MRN</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Scheduled At</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <?php if ($access === 'full'): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="appointmentTable">
                <?php foreach ($appointments as $index => $a): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($a['patient_mrn'] ?? '-') ?></td>
                    <td><strong><?= htmlspecialchars(($a['patient_first'] ?? '') . ' ' . ($a['patient_last'] ?? '')) ?></strong></td>
                    <td><?= htmlspecialchars(($a['doctor_first'] ?? '') . ' ' . ($a['doctor_last'] ?? '')) ?></td>
                    <td><?= $a['scheduled_at'] ? date('M d, Y H:i', strtotime($a['scheduled_at'])) : '-' ?></td>
                    <td><?= (int)($a['duration_minutes'] ?? 0) ?> min</td>
                    <td>
                        <span class="status-badge status-<?= htmlspecialchars($a['status'] ?? 'scheduled') ?>">
                            <?= str_replace('_', ' ', htmlspecialchars($a['status'] ?? '-')) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($a['reason'] ?? '-') ?></td>
                    <?php if ($access === 'full'): ?>
                    <td>
                        <a href="edit_appointment.php?id=<?= $a['id'] ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>

                        <form method="POST" action="appointments.php" style="display:inline;" onsubmit="return confirm('Delete this appointment?');">
                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="delete_appointment" value="1">
                            <button type="submit" class="btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>

                        <?php
                        $invoiceCheck = $pdo->prepare("SELECT id FROM invoices WHERE appointment_id = ?");
                        $invoiceCheck->execute([$a['id']]);
                        $invoiceExists = $invoiceCheck->fetch();
                        ?>
                        <?php if (!$invoiceExists): ?>
                            <a href="generate_invoice.php?appointment_id=<?= $a['id'] ?>" class="btn-invoice" title="Generate Invoice"><i class="bi bi-receipt"></i></a>
                        <?php else: ?>
                            <a href="view_invoice.php?id=<?= $invoiceExists['id'] ?>" class="btn-invoice" title="View Invoice"><i class="bi bi-check-circle"></i></a>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-calendar-x" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No appointments found.
        </p>
    <?php endif; ?>
</div>

<!-- ADD APPOINTMENT MODAL -->
<?php if ($access === 'full'): ?>
<div class="modal fade" id="addAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white;">
                <h5 class="modal-title">Add Appointment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="appointments.php">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Patient</label>
                            <select name="patient_id" class="form-select" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Doctor</label>
                            <select name="doctor_id" class="form-select" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Scheduled At</label>
                            <input type="text" name="scheduled_at" id="scheduled_at" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" name="duration_minutes" class="form-control" min="1" value="30" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="no_show">No Show</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reason</label>
                            <input type="text" name="reason" class="form-control" placeholder="Reason for appointment" required>
                        </div>
                    </div>
                    <input type="hidden" name="add_appointment" value="1">
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="submit" class="btn btn-add">Save Appointment</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CLIENT-SIDE SEARCH + FLATPICKR -->
<script>
// Flatpickr for scheduled_at (only if the field exists, i.e., full access)
if (document.getElementById('scheduled_at')) {
    flatpickr("#scheduled_at", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        minuteIncrement: 5,
        minDate: "today"
    });
}

// Search
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const appointmentTable = document.getElementById('appointmentTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = appointmentTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        const cells = row.getElementsByTagName('td');
        if (cells.length < 6) return;
        const mrn       = (cells[1]?.textContent || '').toLowerCase();
        const patient   = (cells[1]?.textContent || '').toLowerCase();
        const doctor    = (cells[2]?.textContent || '').toLowerCase();
        const scheduled = (cells[3]?.textContent || '').toLowerCase();
        const status    = (cells[5]?.textContent || '').toLowerCase();
        const reason    = (cells[6]?.textContent || '').toLowerCase();
        row.style.display = (mrn.includes(filter) || patient.includes(filter) || doctor.includes(filter) || scheduled.includes(filter) || status.includes(filter) || reason.includes(filter)) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<!-- Bootstrap JS (for modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php include 'appointments_footer.php'; ?>
