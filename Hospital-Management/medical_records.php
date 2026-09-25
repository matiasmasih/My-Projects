<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('medical_records', 'login.php');
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_record']) && $access === 'full') {
    $rid = (int)($_POST['record_id'] ?? 0);
    if ($rid > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM medical_records WHERE id = ?");
            $stmt->execute([$rid]);
            $_SESSION['flash_success'] = "Medical record deleted.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    }
    header("Location: medical_records.php");
    exit;
}

// ============================================
// FETCH RECORDS (role-aware)
// ============================================
$records = [];
try {
    $sql = "
        SELECT mr.*,
               p.first_name AS patient_first, p.last_name AS patient_last,
               p.medical_record_number AS patient_mrn,
               doc_user.first_name AS doctor_first, doc_user.last_name AS doctor_last
        FROM medical_records mr
        LEFT JOIN patients p ON mr.patient_id = p.id
        LEFT JOIN doctors d ON mr.doctor_id = d.id
        LEFT JOIN users doc_user ON d.user_id = doc_user.id
    ";

    $params = [];
    if ($role === 'doctor' && $my_doctor_id > 0) {
        $sql .= " WHERE mr.doctor_id = ? ";
        $params[] = $my_doctor_id;
    }

    $sql .= " ORDER BY mr.visit_date DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'Medical Records';
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
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search records...">
        <button class="btn btn-add" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px;">
            Total: <?= count($records); ?> records
        </span>
        <?php if ($access === 'full'): ?>
        <a href="add_record.php" class="btn btn-add" style="white-space: nowrap;">
            <i class="bi bi-plus-circle"></i> New Record
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="table-card">
    <h5><i class="bi bi-file-medical"></i> Medical Records (<?= count($records); ?>)</h5>

    <?php if (count($records) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Visit Type</th>
                    <th>Diagnosis</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="recordsTable">
                <?php foreach ($records as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= !empty($r['visit_date']) ? date('M d, Y H:i', strtotime($r['visit_date'])) : '-' ?></td>
                    <td>
                        <strong><?= htmlspecialchars(($r['patient_first'] ?? '') . ' ' . ($r['patient_last'] ?? '')) ?></strong>
                        <?php if (!empty($r['patient_mrn'])): ?>
                            <br><span style="color: var(--text-muted); font-size: 0.8rem;"><?= htmlspecialchars($r['patient_mrn']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($r['doctor_first']) ? 'Dr. ' . htmlspecialchars($r['doctor_first'] . ' ' . $r['doctor_last']) : '-' ?></td>
                    <td><?= htmlspecialchars($r['visit_type'] ?? '-') ?></td>
                    <td style="white-space: normal; max-width: 260px;">
                        <?= !empty($r['diagnosis']) ? htmlspecialchars(mb_substr($r['diagnosis'], 0, 60)) . (mb_strlen($r['diagnosis']) > 60 ? '…' : '') : '<span style="color:var(--text-muted);">-</span>' ?>
                    </td>
                    <td>
                        <a href="view_record.php?id=<?= (int)$r['id'] ?>" class="btn-edit" title="View"><i class="bi bi-eye"></i></a>
                        <?php if ($access === 'full'): ?>
                        <form method="POST" action="medical_records.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                            <input type="hidden" name="record_id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="delete_record" value="1">
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
            <i class="bi bi-file-medical" style="font-size: 3rem; display: block; margin-bottom: 12px;"></i>
            No medical records yet.
        </p>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const recordsTable = document.getElementById('recordsTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = recordsTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<?php include 'doctor_footer.php'; ?>
