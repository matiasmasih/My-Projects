<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('pharmacy', 'login.php');

$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ============================================
// HANDLE DELETE STOCK (POST, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_stock']) && $access === 'full') {
    $del_id = (int)($_POST['stock_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM pharmacy_stock WHERE id = ?");
            $stmt->execute([$del_id]);
            $_SESSION['flash_success'] = "Stock entry deleted.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    }
    header("Location: pharmacy_stock.php");
    exit;
}

// ============================================
// FETCH STOCK
// ============================================
try {
    $stmt = $pdo->query("
        SELECT
            ps.id,
            ps.medicine_batch_id,
            ps.quantity,
            ps.min_threshold,
            ps.location,
            ps.updated_at,
            mb.batch_number,
            mb.expiry_date,
            mb.cost_price,
            mb.selling_price,
            m.id AS medicine_id,
            m.name AS medicine_name,
            m.generic_name,
            m.brand,
            m.form AS category,
            m.strength,
            m.unit
        FROM pharmacy_stock ps
        LEFT JOIN medicine_batches mb ON ps.medicine_batch_id = mb.id
        LEFT JOIN medicines m ON mb.medicine_id = m.id
        ORDER BY ps.updated_at DESC
    ");
    $stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'Pharmacy Stock';
include 'pharmacy_stock_header.php';
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
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search stock...">
        <button class="btn btn-add" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>
    <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px;">
        Total: <?= count($stock_items) ?> items
    </span>
</div>

<!-- Stock Table -->
<div class="table-card">
    <h5><i class="bi bi-capsule"></i> All Stock (<?= count($stock_items); ?>)</h5>

    <?php if (count($stock_items) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine</th>
                    <th>Category</th>
                    <th>Batch</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Expiry</th>
                    <th>Brand</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="stockTable">
                <?php foreach ($stock_items as $index => $item):
                    $is_expired = !empty($item['expiry_date']) && strtotime($item['expiry_date']) < strtotime(date('Y-m-d'));
                    $qty = (int)($item['quantity'] ?? 0);
                    $min = (int)($item['min_threshold'] ?: 10);

                    if ($is_expired) {
                        $status_class = 'status-expired';
                        $status_text  = 'Expired';
                    } elseif ($qty <= 0) {
                        $status_class = 'status-out-of-stock';
                        $status_text  = 'Out of Stock';
                    } elseif ($qty < $min) {
                        $status_class = 'status-low-stock';
                        $status_text  = 'Low Stock';
                    } else {
                        $status_class = 'status-in-stock';
                        $status_text  = 'In Stock';
                    }
                ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($item['medicine_name'] ?? '-') ?></strong>
                        <?php if (!empty($item['generic_name'])): ?>
                            <br><span style="color: var(--text-muted); font-size: 0.8rem;"><?= htmlspecialchars($item['generic_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($item['category'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($item['batch_number'] ?? '-') ?></td>
                    <td><strong><?= $qty ?></strong> <?= htmlspecialchars($item['unit'] ?? '') ?></td>
                    <td>&euro;<?= number_format((float)($item['selling_price'] ?? 0), 2) ?></td>
                    <td><?= !empty($item['expiry_date']) ? date('M d, Y', strtotime($item['expiry_date'])) : '-' ?></td>
                    <td><?= htmlspecialchars($item['brand'] ?? '-') ?></td>
                    <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $item['location'] ?? '-'))) ?></td>
                    <td>
                        <a href="edit_stock.php?id=<?= (int)$item['id'] ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a href="restock.php?id=<?= (int)$item['id'] ?>" class="btn-restock" title="Restock"><i class="bi bi-arrow-repeat"></i></a>

                        <?php if ($access === 'full'): ?>
                        <form method="POST" action="pharmacy_stock.php" style="display:inline;" onsubmit="return confirm('Delete this stock entry?');">
                            <input type="hidden" name="stock_id" value="<?= (int)$item['id'] ?>">
                            <input type="hidden" name="delete_stock" value="1">
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
            <i class="bi bi-capsule" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No stock items found.
        </p>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const stockTable = document.getElementById('stockTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = stockTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<?php include 'pharmacy_stock_footer.php'; ?>
