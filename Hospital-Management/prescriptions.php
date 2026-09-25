<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('prescriptions', 'login.php');
$role   = current_role();

$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

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

// ============================================
// HANDLE DELETE (POST, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_prescription']) && $access === 'full') {
    $pid = (int)($_POST['prescription_id'] ?? 0);
    if ($pid > 0) {
        try {
            // Delete items first
            $stmt = $pdo->prepare("DELETE FROM prescription_items WHERE prescription_id = ?");
            $stmt->execute([$pid]);

            // Delete prescription
            $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE id = ?");
            $stmt->execute([$pid]);

            $_SESSION['flash_success'] = "Prescription deleted.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    }
    header("Location: prescriptions.php");
    exit;
}

// ============================================
// FETCH PRESCRIPTIONS (role-aware)
// ============================================
$prescriptions = [];
try {
    $sql = "
        SELECT pr.*,
               mr.patient_id AS patient_id,
               p.first_name AS patient_first, p.last_name AS patient_last,
               p.medical_record_number AS patient_mrn,
               doc_user.first_name AS doctor_first, doc_user.last_name AS doctor_last,
               (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = pr.id) AS item_count
        FROM prescriptions pr
        LEFT JOIN medical_records mr ON pr.medical_record_id = mr.id
        LEFT JOIN patients p ON mr.patient_id = p.id
        LEFT JOIN doctors d ON pr.prescribed_by = d.id
        LEFT JOIN users doc_user ON d.user_id = doc_user.id
    ";

    $params = [];
    if ($role === 'doctor' && $my_doctor_id > 0) {
        $sql .= " WHERE pr.prescribed_by = ? ";
        $params[] = $my_doctor_id;
    }

    $sql .= " ORDER BY pr.prescribed_at DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'Prescriptions';
include 'doctor_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search prescriptions...">
        <button class="btn btn-add" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px;">
            Total: <?= count($prescriptions); ?> prescriptions
        </span>
        <?php if ($access === 'full'): ?>
        <a href="add_prescription.php" class="btn btn-add" style="white-space: nowrap;">
            <i class="bi bi-plus-circle"></i> New Prescription
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="table-card">
    <h5><i class="bi bi-capsule"></i> Prescriptions (<?= count($prescriptions); ?>)</h5>

    <?php if (count($prescriptions) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Items</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="prescTable">
                <?php foreach ($prescriptions as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= !empty($p['prescribed_at']) ? date('M d, Y H:i', strtotime($p['prescribed_at'])) : '-' ?></td>
                    <td>
                        <strong><?= htmlspecialchars(($p['patient_first'] ?? '') . ' ' . ($p['patient_last'] ?? '')) ?></strong>
                        <?php if (!empty($p['patient_mrn'])): ?>
                            <br><span style="color: var(--text-muted); font-size: 0.8rem;"><?= htmlspecialchars($p['patient_mrn']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($p['doctor_first']) ? 'Dr. ' . htmlspecialchars($p['doctor_first'] . ' ' . $p['doctor_last']) : '-' ?></td>
                    <td>
                        <span class="status-badge"><?= (int)$p['item_count'] ?> item<?= (int)$p['item_count'] !== 1 ? 's' : '' ?></span>
                    </td>
                    <td style="white-space: normal; max-width: 260px;">
                        <?= !empty($p['notes']) ? htmlspecialchars(mb_substr($p['notes'], 0, 60)) . (mb_strlen($p['notes']) > 60 ? '…' : '') : '<span style="color:var(--text-muted);">-</span>' ?>
                    </td>
                    <td>
                        <a href="view_prescription.php?id=<?= (int)$p['id'] ?>" class="btn-edit" title="View"><i class="bi bi-eye"></i></a>
                        <?php if ($access === 'full'): ?>
                        <form method="POST" action="prescriptions.php" style="display:inline;" onsubmit="return confirm('Delete this prescription?');">
                            <input type="hidden" name="prescription_id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="delete_prescription" value="1">
                            <button type="submit" class="btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 40px;">
            <i class="bi bi-capsule" style="font-size: 3rem; display: block; margin-bottom: 12px;"></i>
            No prescriptions yet.
        </p>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const prescTable = document.getElementById('prescTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = prescTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<?php include 'doctor_footer.php'; ?>
