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
// $access = 'full' | 'view' | 'own'

// PRG: read flash message from session (set by a previous POST)
$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);


// ============================================
// HANDLE ADD PATIENT
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_patient']) && $access === 'full') {

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
              $_SESSION['flash_success'] = "Patient added successfully! (MRN: " . $mrn . ")";
        header("Location: patients.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = "Database Error: " . $e->getMessage();
        header("Location: patients.php");
        exit;
    }
}


// ============================================
// HANDLE DELETE PATIENT (POST only, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_patient']) && $access === 'full') {
    $del_id = (int)($_POST['patient_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
            $stmt->execute([$del_id]);
            $_SESSION['flash_success'] = "Patient deleted successfully.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Invalid patient ID.";
    }
    header("Location: patients.php");
    exit;
}

// ============================================
// FETCH PATIENTS (role-aware)
// ============================================
try {
    if ($access === 'own') {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE email = ? ORDER BY id DESC");
        $stmt->execute([$_SESSION['email'] ?? '']);
    } else {
        $stmt = $pdo->query("SELECT * FROM patients ORDER BY id DESC");
    }
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'Patients';
include 'patients_header.php';
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
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search patients...">
        <button class="btn btn-add" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>

    <?php if ($access === 'full'): ?>
    <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addPatientModal">
        <i class="bi bi-plus-circle"></i> Add Patient
    </button>
    <?php endif; ?>
</div>

<!-- Patients Table -->
<div class="table-card">
    <h5><i class="bi bi-person-lines-fill"></i> All Patients (<?= count($patients); ?>)</h5>

    <?php if (count($patients) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
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
                    <?php if ($access === 'full'): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="patientTable">
                <?php foreach ($patients as $index => $p): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($p['medical_record_number'] ?? '-') ?></td>
                    <td><strong><?= htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?></strong></td>
                    <td><?= htmlspecialchars($p['email'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['gender'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['dob'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['national_id'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['address'] ?? '-') ?></td>
                    <?php if ($access === 'full'): ?>
                    <td>
                        <a href="edit_patient.php?id=<?= $p['id'] ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>

                        <form method="POST" action="patients.php" style="display:inline;" onsubmit="return confirm('Delete this patient? This cannot be undone.');">
                            <input type="hidden" name="patient_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="delete_patient" value="1">
                            <button type="submit" class="btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-person-x" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No patients found.
        </p>
    <?php endif; ?>
</div>

<!-- ADD PATIENT MODAL -->
<?php if ($access === 'full'): ?>
<div class="modal fade" id="addPatientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white;">
                <h5 class="modal-title">Add Patient</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="patients.php">
                <div class="modal-body">
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 12px;">
                        <i class="bi bi-info-circle"></i> MRN will be generated automatically.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required></div>

                        <div class="col-md-3"><label class="form-label">DOB</label><input type="date" name="dob" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label">National ID</label><input type="text" name="national_id" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>

                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Address</label><input type="text" name="address" class="form-control"></div>

                        <div class="col-md-6"><label class="form-label">Emergency Contact Name</label><input type="text" name="emergency_contact_name" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Emergency Contact Phone</label><input type="text" name="emergency_contact_phone" class="form-control"></div>
                    </div>
                    <input type="hidden" name="add_patient" value="1">
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="submit" class="btn btn-add">Save Patient</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CLIENT-SIDE SEARCH -->
<script>
const searchInput  = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const patientTable = document.getElementById('patientTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = patientTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        const cells = row.getElementsByTagName('td');
        if (cells.length < 5) return;
        const name  = (cells[2]?.textContent || '').toLowerCase();
        const email = (cells[3]?.textContent || '').toLowerCase();
        const phone = (cells[4]?.textContent || '').toLowerCase();
        row.style.display = (name.includes(filter) || email.includes(filter) || phone.includes(filter)) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<!-- Bootstrap JS (for modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php include 'patients_footer.php'; ?>
