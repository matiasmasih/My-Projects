<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('doctors', 'login.php');

// Flash messages (PRG)
$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ============================================
// HANDLE ADD DOCTOR (POST, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor']) && $access === 'full') {
    $user_id          = (int)($_POST['user_id'] ?? 0);
    $license_number   = trim($_POST['license_number'] ?? '');
    $bio              = trim($_POST['bio'] ?? '');
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);

    if ($user_id > 0 && $license_number !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO doctors (user_id, license_number, bio, consultation_fee) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $license_number, $bio, $consultation_fee]);
            $_SESSION['flash_success'] = "Doctor added successfully.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Database Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Missing required fields.";
    }
    header("Location: doctors.php");
    exit;
}

// ============================================
// HANDLE DELETE DOCTOR (POST, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_doctor']) && $access === 'full') {
    $del_id = (int)($_POST['doctor_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM doctors WHERE id = ?");
            $stmt->execute([$del_id]);
            $_SESSION['flash_success'] = "Doctor deleted successfully.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Invalid doctor ID.";
    }
    header("Location: doctors.php");
    exit;
}

// ============================================
// FETCH DOCTORS
// ============================================
try {
    $stmt = $pdo->query("
        SELECT d.id, d.license_number, d.bio, d.consultation_fee,
               u.first_name, u.last_name, u.email, u.phone
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        ORDER BY d.id DESC
    ");
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ============================================
// USERS AVAILABLE TO BECOME DOCTORS
// (only users not already doctors)
// ============================================
$usersList = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name
        FROM users u
        LEFT JOIN doctors d ON d.user_id = u.id
        WHERE d.id IS NULL
        ORDER BY u.first_name
    ");
    $usersList = $stmt->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Doctors';
include 'doctors_header.php';
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
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search doctors...">
        <button class="btn btn-add" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>

    <?php if ($access === 'full'): ?>
    <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
        <i class="bi bi-plus-circle"></i> Add Doctor
    </button>
    <?php endif; ?>
</div>

<!-- Doctors Table -->
<div class="table-card">
    <h5><i class="bi bi-person-badge"></i> All Doctors (<?= count($doctors); ?>)</h5>

    <?php if (count($doctors) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>License</th>
                    <th>Bio</th>
                    <th>Fee</th>
                    <?php if ($access === 'full'): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="doctorTable">
                <?php foreach ($doctors as $index => $d): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><strong><?= htmlspecialchars(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?></strong></td>
                    <td><?= htmlspecialchars($d['email'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['phone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['license_number'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($d['bio'] ?? '-') ?></td>
                    <td>€<?= number_format((float)($d['consultation_fee'] ?? 0), 2) ?></td>
                    <?php if ($access === 'full'): ?>
                    <td>
                        <a href="edit_doctor.php?id=<?= $d['id'] ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>

                        <form method="POST" action="doctors.php" style="display:inline;" onsubmit="return confirm('Delete this doctor?');">
                            <input type="hidden" name="doctor_id" value="<?= $d['id'] ?>">
                            <input type="hidden" name="delete_doctor" value="1">
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
            No doctors found.
        </p>
    <?php endif; ?>
</div>

<!-- ADD DOCTOR MODAL -->
<?php if ($access === 'full'): ?>
<div class="modal fade" id="addDoctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white;">
                <h5 class="modal-title">Add Doctor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="doctors.php">
                <div class="modal-body">
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 12px;">
                        <i class="bi bi-info-circle"></i> Select an existing user to become a doctor.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">User</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Select User</option>
                                <?php foreach ($usersList as $user): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">License Number</label>
                            <input type="text" name="license_number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Consultation Fee (€)</label>
                            <input type="number" step="0.01" name="consultation_fee" class="form-control" value="0.00">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Bio</label>
                            <textarea name="bio" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <input type="hidden" name="add_doctor" value="1">
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="submit" class="btn btn-add">Save Doctor</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CLIENT-SIDE SEARCH -->
<script>
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const doctorTable = document.getElementById('doctorTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = doctorTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        const cells = row.getElementsByTagName('td');
        if (cells.length < 5) return;
        const name  = (cells[1]?.textContent || '').toLowerCase();
        const email = (cells[2]?.textContent || '').toLowerCase();
        const phone = (cells[3]?.textContent || '').toLowerCase();
        row.style.display = (name.includes(filter) || email.includes(filter) || phone.includes(filter)) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<!-- Bootstrap JS (for modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php include 'doctors_footer.php'; ?>
