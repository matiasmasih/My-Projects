<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('pharmacy', 'login.php');

if ($access !== 'full') {
    header("Location: pharmacy_stock.php");
    exit;
}

$stock_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($stock_id <= 0) die("Invalid stock ID.");

// Fetch current stock item
try {
    $stmt = $pdo->prepare("
        SELECT
            ps.id,
            ps.medicine_batch_id,
            ps.quantity,
            ps.min_threshold,
            ps.location,
            mb.batch_number,
            mb.expiry_date,
            mb.cost_price,
            mb.selling_price,
            m.name AS medicine_name,
            m.generic_name,
            m.unit
        FROM pharmacy_stock ps
        LEFT JOIN medicine_batches mb ON ps.medicine_batch_id = mb.id
        LEFT JOIN medicines m ON mb.medicine_id = m.id
        WHERE ps.id = ?
    ");
    $stmt->execute([$stock_id]);
    $stock_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stock_item) die("Stock item not found.");
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $add_qty = (int)($_POST['add_quantity'] ?? 0);

    if ($add_qty <= 0) {
        $error = "Please enter a quantity greater than 0.";
    } else {
        try {
            $new_qty = (int)$stock_item['quantity'] + $add_qty;

            $stmt = $pdo->prepare("
                UPDATE pharmacy_stock
                SET quantity = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$new_qty, $stock_id]);

            $_SESSION['flash_success'] = "Restocked: +" . $add_qty . " " . ($stock_item['unit'] ?? 'units') . " added. New total: " . $new_qty . ".";
            header("Location: pharmacy_stock.php");
            exit;

        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

$page_title = 'Restock';
include 'pharmacy_stock_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-arrow-repeat"></i> Restock — <?= htmlspecialchars($stock_item['medicine_name'] ?? '') ?></h5>

    <!-- Current Stock Info -->
    <div style="background: #f0fdfa; border-left: 4px solid var(--primary); padding: 16px 20px; border-radius: 8px; margin-bottom: 22px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;">
            <div>
                <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">Medicine</div>
                <div style="font-weight: 700;"><?= htmlspecialchars($stock_item['medicine_name'] ?? '-') ?></div>
                <?php if (!empty($stock_item['generic_name'])): ?>
                    <div style="color: var(--text-muted); font-size: 0.82rem;"><?= htmlspecialchars($stock_item['generic_name']) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">Batch</div>
                <div style="font-weight: 700;"><?= htmlspecialchars($stock_item['batch_number'] ?? '-') ?></div>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">Current Quantity</div>
                <div style="font-weight: 700; color: var(--primary); font-size: 1.2rem;">
                    <?= (int)$stock_item['quantity'] ?> <?= htmlspecialchars($stock_item['unit'] ?? '') ?>
                </div>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px;">Expiry</div>
                <div style="font-weight: 700;"><?= !empty($stock_item['expiry_date']) ? date('M d, Y', strtotime($stock_item['expiry_date'])) : '-' ?></div>
            </div>
        </div>
    </div>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Quantity to Add <span style="color:#ef4444;">*</span></label>
                <input type="number" name="add_quantity" class="form-control" min="1" required placeholder="e.g., 50" autofocus>
                <small style="color: var(--text-muted); font-size: 0.8rem;">Enter how many units you are adding to the stock.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label">New Total After Restock</label>
                <div style="padding: 10px 0; font-weight: 700; color: var(--primary); font-size: 1.1rem;" id="newTotal">
                    <?= (int)$stock_item['quantity'] ?> <?= htmlspecialchars($stock_item['unit'] ?? '') ?>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-plus-circle"></i> Add Stock</button>
            <a href="pharmacy_stock.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Stock</a>
        </div>
    </form>
</div>

<script>
const input = document.querySelector('input[name="add_quantity"]');
const newTotal = document.getElementById('newTotal');
const currentQty = <?= (int)$stock_item['quantity'] ?>;
const unit = '<?= htmlspecialchars($stock_item['unit'] ?? '') ?>';

input.addEventListener('input', function() {
    const add = parseInt(this.value) || 0;
    newTotal.textContent = (currentQty + add) + ' ' + unit;
});
</script>

<?php include 'pharmacy_stock_footer.php'; ?>
