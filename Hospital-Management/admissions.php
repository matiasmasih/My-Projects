<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('admissions', 'login.php');

$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$status_display = [
    'admitted'    => 'Admitted',
    'discharged'  => 'Discharged',
    'transferred' => 'Transferred',
];

// ============================================
// HANDLE DELETE ADMISSION (POST, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admission']) && $access === 'full') {
    $del_id = (int)($_POST['admission_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM admissions WHERE id = ?");
            $stmt->execute([$del_id]);
            $_SESSION['flash_success'] = "Admission record deleted.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    }
    header("Location: admissions.php");
    exit;
}

// ============================================
// FILTERS + PAGINATION
// ============================================
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
if (!in_array($filter_status, ['admitted', 'discharged', 'transferred'], true)) {
    $filter_status = '';
}

$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 25;
$offset   = ($page - 1) * $per_page;

$where = '';
$params = [];
if ($filter_status !== '') {
    $where = " WHERE a.status = ? ";
    $params[] = $filter_status;
}

// Total count
$total_admissions = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admissions a" . $where);
    $stmt->execute($params);
    $total_admissions = (int)$stmt->fetchColumn();
} catch (PDOException $e) {}

$total_pages = max(1, ceil($total_admissions / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// Fetch page
$admissions = [];
try {
    $sql = "
        SELECT a.*,
               p.first_name AS patient_first_name,
               p.last_name AS patient_last_name,
               p.dob AS patient_dob,
               p.gender AS patient_gender,
               p.medical_record_number,
               doc_user.first_name AS doctor_first_name,
               doc_user.last_name AS doctor_last_name,
               w.name AS ward_name,
               r.room_number,
               admitted_user.first_name AS admitted_by_first_name,
               admitted_user.last_name AS admitted_by_last_name
        FROM admissions a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.admitting_doctor_id = d.id
        LEFT JOIN users doc_user ON d.user_id = doc_user.id
        LEFT JOIN wards w ON a.ward_id = w.id
        LEFT JOIN rooms r ON a.room_id = r.id
        LEFT JOIN users admitted_user ON a.admitted_by = admitted_user.id
        $where
        ORDER BY a.admitted_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $admissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Stats
$stats = ['total' => 0, 'admitted' => 0, 'discharged' => 0, 'today' => 0];
try {
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'admitted' THEN 1 ELSE 0 END) AS admitted,
            SUM(CASE WHEN status = 'discharged' THEN 1 ELSE 0 END) AS discharged,
            SUM(CASE WHEN DATE(admitted_at) = CURDATE() THEN 1 ELSE 0 END) AS today
        FROM admissions
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$page_title = 'Admissions';
include 'admissions_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- STATS -->
<div class="d-flex gap-3 mb-3 flex-wrap">
    <span class="badge" style="background: var(--primary); font-size: 0.9rem; padding: 10px 16px;">
        <i class="bi bi-journal-plus"></i> Total: <?= (int)$stats['total'] ?>
    </span>
    <span class="badge" style="background: #f59e0b; font-size: 0.9rem; padding: 10px 16px;">
        <i class="bi bi-hospital"></i> Admitted: <?= (int)$stats['admitted'] ?>
    </span>
    <span class="badge" style="background: #22c55e; font-size: 0.9rem; padding: 10px 16px;">
        <i class="bi bi-check-circle"></i> Discharged: <?= (int)$stats['discharged'] ?>
    </span>
    <span class="badge" style="background: #06b6d4; font-size: 0.9rem; padding: 10px 16px;">
        <i class="bi bi-calendar-day"></i> Today: <?= (int)$stats['today'] ?>
    </span>
</div>

<!-- TOOLBAR -->
<div class="d-flex justify-content-between align-items-center mb-3 gap-2" style="flex-wrap: nowrap;">
    <div class="d-flex align-items-center gap-2" style="flex-wrap: nowrap;">
        <label style="color: var(--text-muted); font-size: 0.9rem; margin: 0; white-space: nowrap;">Status:</label>
        <form method="GET" action="admissions.php" style="margin: 0;">
            <select name="status" class="form-select" style="width: 180px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="admitted"    <?= $filter_status === 'admitted' ? 'selected' : '' ?>>Admitted</option>
                <option value="discharged"  <?= $filter_status === 'discharged' ? 'selected' : '' ?>>Discharged</option>
                <option value="transferred" <?= $filter_status === 'transferred' ? 'selected' : '' ?>>Transferred</option>
            </select>
        </form>
        <input type="text" class="form-control" id="searchInput" placeholder="Search on this page..." style="width: 260px; flex-shrink: 0;">
        <button class="btn btn-add" id="searchButton" style="white-space: nowrap; flex-shrink: 0;">
            <i class="bi bi-search"></i> Search
        </button>
    </div>
    <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px; white-space: nowrap; flex-shrink: 0;">
        <?= $total_admissions ?> admissions total
    </span>
</div>

<!-- ADMISSIONS TABLE -->
<div class="table-card">
    <h5>
        <i class="bi bi-journal-plus"></i>
        Admissions
        (page <?= $page ?> of <?= $total_pages ?>)
    </h5>

    <?php if (count($admissions) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Ward / Room</th>
                    <th>Admitted</th>
                    <th>Status</th>
                    <th>MRN</th>
                    <?php if ($access === 'full'): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="admissionTable">
                <?php foreach ($admissions as $index => $a): ?>
                <tr>
                    <td><?= $offset + $index + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars(($a['patient_first_name'] ?? '') . ' ' . ($a['patient_last_name'] ?? '')) ?></strong>
                        <?php if (!empty($a['patient_dob'])): ?>
                            <br><span style="color: var(--text-muted); font-size: 0.8rem;">
                                DOB: <?= date('M j, Y', strtotime($a['patient_dob'])) ?>
                                <?= !empty($a['patient_gender']) ? ' | ' . ucfirst($a['patient_gender']) : '' ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= !empty($a['doctor_first_name']) ? 'Dr. ' . htmlspecialchars($a['doctor_first_name'] . ' ' . $a['doctor_last_name']) : '<span style="color:var(--text-muted);">-</span>' ?>
                    </td>
                    <td>
                        <?php if (!empty($a['ward_name'])): ?>
                            <strong><?= htmlspecialchars($a['ward_name']) ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($a['room_number'])): ?>
                            <br><span style="color: var(--text-muted); font-size: 0.8rem;">Room <?= htmlspecialchars($a['room_number']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= !empty($a['admitted_at']) ? date('M j, Y', strtotime($a['admitted_at'])) : '-' ?>
                        <br><span style="color: var(--text-muted); font-size: 0.8rem;"><?= !empty($a['admitted_at']) ? date('g:i A', strtotime($a['admitted_at'])) : '' ?></span>
                    </td>
                    <td>
                        <span class="status-badge status-<?= htmlspecialchars($a['status'] ?? 'admitted') ?>">
                            <?= htmlspecialchars($status_display[$a['status']] ?? ucfirst($a['status'] ?? '-')) ?>
                        </span>
                        <?php if (($a['status'] ?? '') === 'discharged' && !empty($a['discharged_at'])): ?>
                            <br><span style="color: var(--text-muted); font-size: 0.78rem;"><?= date('M j, Y', strtotime($a['discharged_at'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars($a['medical_record_number'] ?? '-') ?></code></td>
                    <?php if ($access === 'full'): ?>
                    <td>
                        <a href="edit_admission.php?id=<?= (int)$a['id'] ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>

                        <?php if (($a['status'] ?? '') === 'admitted'): ?>
                        <a href="discharge_admission.php?id=<?= (int)$a['id'] ?>" class="btn-discharge" title="Discharge"><i class="bi bi-box-arrow-right"></i></a>
                        <?php endif; ?>

                        <form method="POST" action="admissions.php" style="display:inline;" onsubmit="return confirm('Delete this admission record?');">
                            <input type="hidden" name="admission_id" value="<?= (int)$a['id'] ?>">
                            <input type="hidden" name="delete_admission" value="1">
                            <button type="submit" class="btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <span style="color: var(--text-muted); font-size: 0.9rem;">
            Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_admissions) ?> of <?= $total_admissions ?>
        </span>
        <div class="d-flex gap-2" style="align-items:center;">
            <?php
            $qs = $_GET; $qs['page'] = max(1, $page - 1);
            $prev_url = 'admissions.php?' . http_build_query($qs);
            $qs['page'] = min($total_pages, $page + 1);
            $next_url = 'admissions.php?' . http_build_query($qs);
            ?>
            <a href="<?= htmlspecialchars($prev_url) ?>" class="btn btn-add" style="text-decoration:none; <?= $page <= 1 ? 'opacity:.4; pointer-events:none;' : '' ?>">
                <i class="bi bi-chevron-left"></i> Prev
            </a>
            <a href="<?= htmlspecialchars($next_url) ?>" class="btn btn-add" style="text-decoration:none; <?= $page >= $total_pages ? 'opacity:.4; pointer-events:none;' : '' ?>">
                Next <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 40px;">
            <i class="bi bi-journal-plus" style="font-size: 3rem; display: block; margin-bottom: 12px;"></i>
            No admissions found.
        </p>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const admissionTable = document.getElementById('admissionTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = admissionTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<?php include 'admissions_footer.php'; ?>
