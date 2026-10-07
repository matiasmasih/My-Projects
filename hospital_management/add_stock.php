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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicine_id  = (int)($_POST['medicine_id'] ?? 0);
    $batch_number = trim($_POST['batch_number'] ?? '');
    $expiry_date  = trim($_POST['expiry_date'] ?? '');
    $cost_price   = (float)($_POST['cost_price'] ?? 0);
    $selling_price= (float)($_POST['selling_price'] ?? 0);
    $quantity     = (int)($_POST['quantity'] ?? 0);
    $min_threshold= (int)($_POST['min_threshold'] ?? 10);
    $location     = trim($_POST['location'] ?? 'main_pharmacy');

    if ($medicine_id <= 0 || $batch_number === '' || $expiry_date === '' || $quantity <= 0) {
        $error = "Please fill all required fields.";
    } else {
        try {
            $pdo->beginTransaction();

            // Insert new batch
            $stmt = $pdo->prepare("
                INSERT INTO medicine_batches
                    (medicine_id, batch_number, expiry_date, cost_price, selling_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$medicine_id, $batch_number, $expiry_date, $cost_price, $selling_price]);
            $batch_id = (int)$pdo->lastInsertId();

            // Insert stock row
            $stmt = $pdo->prepare("
                INSERT INTO pharmacy_stock
                    (medicine_batch_id, quantity, min_threshold, location)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$batch_id, $quantity, $min_threshold, $location]);

            $pdo->commit();
            $_SESSION['flash_success'] = "Stock added: " . $batch_number;
            header("Location: pharmacy_stock.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch medicines for dropdown
$medicines = [];
try {
    $medicines = $pdo->query("SELECT id, name, generic_name, unit FROM medicines ORDER BY name")->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Add Stock';
include 'pharmacy_stock_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-box-seam"></i> Add New Stock Batch</h5>

    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 18px;">
        <i class="bi bi-info-circle"></i> Add a new batch of an existing medicine.
    </p>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">Medicine <span style="color: #ef4444;">*</span></label>
                <select name="medicine_id" class="form-select" required>
                    <option value="">Select Medicine</option>
                    <?php foreach ($medicines as $m): ?>
                        <option value="<?= (int)$m['id'] ?>">
                            <?= htmlspecialchars($m['name']) ?>
                            <?= !empty($m['generic_name']) ? ' — ' . htmlspecialchars($m['generic_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Batch Number <span style="color: #ef4444;">*</span></label>
                <input type="text" name="batch_number" class="form-control" required placeholder="e.g. BATCH-PARA-002">
            </div>
            <div class="col-md-6">
                <label class="form-label">Expiry Date <span style="color: #ef4444;">*</span></label>
                <input type="date" name="expiry_date" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Cost Price (€)</label>
                <input type="number" step="0.01" name="cost_price" class="form-control" value="0.00">
            </div>
            <div class="col-md-6">
                <label class="form-label">Selling Price (€)</label>
                <input type="number" step="0.01" name="selling_price" class="form-control" value="0.00">
            </div>

            <div class="col-md-4">
                <label class="form-label">Quantity <span style="color: #ef4444;">*</span></label>
                <input type="number" name="quantity" class="form-control" min="1" required value="1">
            </div>
            <div class="col-md-4">
                <label class="form-label">Min Threshold</label>
                <input type="number" name="min_threshold" class="form-control" value="10">
            </div>
            <div class="col-md-4">
                <label class="form-label">Location</label>
                <select name="location" class="form-select">
                  <option value="main_pharmacy">Main Pharmacy</option>
                  <option value="emergency_store">Emergency Store</option>
                  <option value="icu_store">ICU Store</option>
                  <option value="ward_store">Ward Store</option>
                  <option value="cold_storage">Cold Storage</option>
               </select>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Stock</button>
            <a href="pharmacy_stock.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Stock</a>
        </div>
    </form>
</div>

<?php include 'pharmacy_stock_footer.php'; ?>
