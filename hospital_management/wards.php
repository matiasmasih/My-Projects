<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('wards', 'login.php');

$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ============================================
// HANDLE DELETE WARD (POST, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ward']) && $access === 'full') {
    $del_id = (int)($_POST['ward_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM wards WHERE id = ?");
            $stmt->execute([$del_id]);
            $_SESSION['flash_success'] = "Ward deleted.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    }
    header("Location: wards.php");
    exit;
}

// ============================================
// FETCH WARDS
// ============================================
try {
    $stmt = $pdo->query("
        SELECT w.*,
               CONCAT(u.first_name, ' ', u.last_name) AS in_charge_name
        FROM wards w
        LEFT JOIN users u ON w.in_charge_id = u.id
        ORDER BY w.name
    ");
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'Wards';
include 'wards_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search wards...">
        <button class="btn btn-add" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>
    <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px;">
        Total: <?= count($wards) ?> wards
    </span>
</div>

<!-- Wards Table -->
<div class="table-card">
    <h5><i class="bi bi-house-door"></i> All Wards (<?= count($wards); ?>)</h5>

    <?php if (count($wards) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Ward Name</th>
                    <th>Type</th>
                    <th>Capacity</th>
                    <th>Status</th>
                    <th>Charge/Day</th>
                    <th>Location</th>
                    <th>In Charge</th>
                    <th>Ext</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="wardTable">
                <?php foreach ($wards as $index => $w): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><strong><?= htmlspecialchars($w['name'] ?? '-') ?></strong></td>
                    <td><span class="type-badge"><?= ucfirst(htmlspecialchars($w['type'] ?? '-')) ?></span></td>
                    <td><strong><?= (int)($w['capacity'] ?? 0) ?></strong> beds</td>
                    <td>
                        <span class="status-badge status-<?= htmlspecialchars($w['status'] ?? 'active') ?>">
                            <?= ucfirst(htmlspecialchars($w['status'] ?? '-')) ?>
                        </span>
                    </td>
                    <td>&euro;<?= number_format((float)($w['charge_per_day'] ?? 0), 2) ?></td>
                    <td><?= htmlspecialchars($w['location'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($w['in_charge_name'] ?: 'Not assigned') ?></td>
                    <td><?= htmlspecialchars($w['phone_extension'] ?? '-') ?></td>
                    <td>
                        <a href="edit_ward.php?id=<?= (int)$w['id'] ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="rooms.php?ward_id=<?= (int)$w['id'] ?>" class="btn-rooms" title="View Rooms"><i class="bi bi-door-closed"></i></a>

                        <?php if ($access === 'full'): ?>
                        <form method="POST" action="wards.php" style="display:inline;" onsubmit="return confirm('Delete this ward?');">
                            <input type="hidden" name="ward_id" value="<?= (int)$w['id'] ?>">
                            <input type="hidden" name="delete_ward" value="1">
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
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-house-door" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No wards yet.
        </p>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const wardTable = document.getElementById('wardTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = wardTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<?php include 'wards_footer.php'; ?>
