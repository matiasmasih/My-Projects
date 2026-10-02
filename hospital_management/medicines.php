<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('medicines', 'login.php');

$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ============================================
// HANDLE DELETE MEDICINE (POST, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_medicine']) && $access === 'full') {
    $del_id = (int)($_POST['medicine_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM medicines WHERE id = ?");
            $stmt->execute([$del_id]);
            $_SESSION['flash_success'] = "Medicine deleted.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    }
    header("Location: medicines.php");
    exit;
}

// ============================================
// FETCH MEDICINES
// ============================================
try {
    $stmt = $pdo->query("
        SELECT
            m.id,
            m.name,
            m.generic_name,
            m.brand,
            m.form,
            m.strength,
            m.unit,
            m.description,
            m.created_at,
            COUNT(DISTINCT mb.id) AS batch_count,
            COALESCE(SUM(ps.quantity), 0) AS total_stock
        FROM medicines m
        LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
        LEFT JOIN pharmacy_stock ps ON mb.id = ps.medicine_batch_id
        GROUP BY m.id
        ORDER BY m.created_at DESC
    ");
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'Medicines';
include 'medicines_header.php';
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
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search medicines...">
        <button class="btn btn-add" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>
    <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px;">
        Total: <?= count($medicines) ?> medicines
    </span>
</div>

<!-- Medicines Table -->
<div class="table-card">
    <h5><i class="bi bi-heart-pulse"></i> All Medicines (<?= count($medicines); ?>)</h5>

    <?php if (count($medicines) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Generic</th>
                    <th>Brand</th>
                    <th>Form</th>
                    <th>Strength</th>
                    <th>Stock</th>
                    <th>Batches</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="medicineTable">
                <?php foreach ($medicines as $index => $med):
                    $stock = (int)($med['total_stock'] ?? 0);
                    if ($stock <= 0) {
                        $stock_class = 'stock-out';
                        $stock_text = $stock . ' ' . htmlspecialchars($med['unit'] ?? '');
                    } elseif ($stock < 20) {
                        $stock_class = 'stock-low';
                        $stock_text = $stock . ' ' . htmlspecialchars($med['unit'] ?? '');
                    } else {
                        $stock_class = 'stock-adequate';
                        $stock_text = $stock . ' ' . htmlspecialchars($med['unit'] ?? '');
                    }
                ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><strong><?= htmlspecialchars($med['name'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($med['generic_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($med['brand'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($med['form'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($med['strength'] ?? '-') ?></td>
                    <td><span class="status-badge <?= $stock_class ?>"><?= $stock_text ?></span></td>
                    <td>
                        <span style="color: var(--text-muted); font-size: 0.9rem;">
                            <?= (int)($med['batch_count'] ?? 0) ?>
                        </span>
                    </td>
                    <td>
                        <a href="edit_medicine.php?id=<?= (int)$med['id'] ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>

                        <?php if ($access === 'full'): ?>
                        <form method="POST" action="medicines.php" style="display:inline;" onsubmit="return confirm('Delete this medicine? This will also remove its batches and stock.');">
                            <input type="hidden" name="medicine_id" value="<?= (int)$med['id'] ?>">
                            <input type="hidden" name="delete_medicine" value="1">
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
            <i class="bi bi-heart-pulse" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No medicines in catalog yet.
        </p>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const medicineTable = document.getElementById('medicineTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = medicineTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<?php include 'medicines_footer.php'; ?>
