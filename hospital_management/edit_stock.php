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
            m.id AS medicine_id,
            m.name AS medicine_name,
            m.generic_name,
            m.brand,
            m.form,
            m.strength,
            m.unit,
            m.description
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
    $quantity       = (int)($_POST['quantity'] ?? 0);
    $min_threshold  = (int)($_POST['min_threshold'] ?? 0);
    $location       = trim($_POST['location'] ?? '');
    $batch_number   = trim($_POST['batch_number'] ?? '');
    $expiry_date    = $_POST['expiry_date'] ?? '';
    $cost_price     = (float)($_POST['cost_price'] ?? 0);
    $selling_price  = (float)($_POST['selling_price'] ?? 0);
    $medicine_name  = trim($_POST['medicine_name'] ?? '');
    $generic_name   = trim($_POST['generic_name'] ?? '');
    $brand          = trim($_POST['brand'] ?? '');
    $form           = trim($_POST['form'] ?? '');
    $strength       = trim($_POST['strength'] ?? '');
    $unit           = trim($_POST['unit'] ?? '');
    $description    = trim($_POST['description'] ?? '');

    $errors = [];

    if ($quantity < 0) $errors[] = "Quantity cannot be negative.";
    if ($min_threshold < 0) $errors[] = "Minimum threshold cannot be negative.";
    if ($location === '') $errors[] = "Location is required.";
    if ($batch_number === '') $errors[] = "Batch number is required.";
    if ($expiry_date === '') $errors[] = "Expiry date is required.";
    if ($medicine_name === '') $errors[] = "Medicine name is required.";
    if ($cost_price < 0) $errors[] = "Cost price cannot be negative.";
    if ($selling_price < 0) $errors[] = "Selling price cannot be negative.";
    if ($expiry_date && strtotime($expiry_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Expiry date cannot be in the past.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update medicine
            $stmt = $pdo->prepare("
                UPDATE medicines
                SET name = ?, generic_name = ?, brand = ?, form = ?, strength = ?, unit = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $medicine_name, $generic_name, $brand, $form,
                $strength, $unit, $description, $stock_item['medicine_id']
            ]);

            // Update batch
            $stmt = $pdo->prepare("
                UPDATE medicine_batches
                SET batch_number = ?, expiry_date = ?, cost_price = ?, selling_price = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $batch_number, $expiry_date, $cost_price, $selling_price,
                $stock_item['medicine_batch_id']
            ]);

            // Update stock
            $stmt = $pdo->prepare("
                UPDATE pharmacy_stock
                SET quantity = ?, min_threshold = ?, location = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $min_threshold, $location, $stock_id]);

            $pdo->commit();

            $_SESSION['flash_success'] = "Stock item updated successfully.";
            header("Location: pharmacy_stock.php");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

$page_title = 'Edit Stock';
include 'pharmacy_stock_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= $error ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-pencil-square"></i> Edit Stock — <?= htmlspecialchars($stock_item['medicine_name'] ?? '') ?></h5>

    <form method="POST">

        <!-- MEDICINE INFO -->
        <div class="section-label" style="font-size:14px; color: var(--primary); font-weight:700; text-transform:uppercase; letter-spacing:1px; margin: 14px 0 12px 0;">
            <i class="bi bi-capsule"></i> Medicine Information
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Medicine Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="medicine_name" class="form-control" value="<?= htmlspecialchars($stock_item['medicine_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Generic Name</label>
                <input type="text" name="generic_name" class="form-control" value="<?= htmlspecialchars($stock_item['generic_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Brand</label>
                <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($stock_item['brand'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Form <span style="color:#ef4444;">*</span></label>
                <select name="form" class="form-select" required>
                    <option value="">Select Form</option>
                    <?php foreach (['Tablet','Capsule','Syrup','Injection','Ointment','Drops','Inhaler','Other'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($stock_item['form'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Strength</label>
                <input type="text" name="strength" class="form-control" value="<?= htmlspecialchars($stock_item['strength'] ?? '') ?>" placeholder="e.g., 500mg">
            </div>
            <div class="col-md-6">
                <label class="form-label">Unit <span style="color:#ef4444;">*</span></label>
                <select name="unit" class="form-select" required>
                    <option value="">Select Unit</option>
                    <?php foreach (['tablet','capsule','bottle','vial','tube','inhaler','pack'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($stock_item['unit'] ?? '') === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($stock_item['description'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- BATCH INFO -->
        <div class="section-label" style="font-size:14px; color: var(--primary); font-weight:700; text-transform:uppercase; letter-spacing:1px; margin: 22px 0 12px 0;">
            <i class="bi bi-box"></i> Batch Information
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Batch Number <span style="color:#ef4444;">*</span></label>
                <input type="text" name="batch_number" class="form-control" value="<?= htmlspecialchars($stock_item['batch_number'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Expiry Date <span style="color:#ef4444;">*</span></label>
                <input type="date" name="expiry_date" class="form-control" value="<?= htmlspecialchars($stock_item['expiry_date'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Cost Price (€)</label>
                <input type="number" step="0.01" name="cost_price" class="form-control" value="<?= htmlspecialchars($stock_item['cost_price'] ?? '0.00') ?>" min="0">
            </div>
            <div class="col-md-6">
                <label class="form-label">Selling Price (€)</label>
                <input type="number" step="0.01" name="selling_price" class="form-control" value="<?= htmlspecialchars($stock_item['selling_price'] ?? '0.00') ?>" min="0">
            </div>
        </div>

        <!-- STOCK INFO -->
        <div class="section-label" style="font-size:14px; color: var(--primary); font-weight:700; text-transform:uppercase; letter-spacing:1px; margin: 22px 0 12px 0;">
            <i class="bi bi-clipboard-data"></i> Stock Information
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Quantity <span style="color:#ef4444;">*</span></label>
                <input type="number" name="quantity" class="form-control" value="<?= (int)($stock_item['quantity'] ?? 0) ?>" min="0" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Min Threshold <span style="color:#ef4444;">*</span></label>
                <input type="number" name="min_threshold" class="form-control" value="<?= (int)($stock_item['min_threshold'] ?? 10) ?>" min="0" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Location <span style="color:#ef4444;">*</span></label>
                <select name="location" class="form-select" required>
                    <option value="main_pharmacy" <?= ($stock_item['location'] ?? '') === 'main_pharmacy' ? 'selected' : '' ?>>Main Pharmacy</option>
                    <option value="emergency_store" <?= ($stock_item['location'] ?? '') === 'emergency_store' ? 'selected' : '' ?>>Emergency Store</option>
                    <option value="icu_store" <?= ($stock_item['location'] ?? '') === 'icu_store' ? 'selected' : '' ?>>ICU Store</option>
                    <option value="ward_store" <?= ($stock_item['location'] ?? '') === 'ward_store' ? 'selected' : '' ?>>Ward Store</option>
                    <option value="cold_storage" <?= ($stock_item['location'] ?? '') === 'cold_storage' ? 'selected' : '' ?>>Cold Storage</option>
                </select>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Changes</button>
            <a href="pharmacy_stock.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Stock</a>
        </div>
    </form>
</div>

<?php include 'pharmacy_stock_footer.php'; ?>
