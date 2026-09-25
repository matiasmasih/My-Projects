<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('medicines', 'login.php');

if ($access !== 'full') {
    header("Location: medicines.php");
    exit;
}

$medicine_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($medicine_id <= 0) die("Invalid medicine ID.");

// Fetch medicine
try {
    $stmt = $pdo->prepare("
        SELECT
            m.id, m.name, m.generic_name, m.brand, m.form,
            m.strength, m.unit, m.description,
            COALESCE(SUM(ps.quantity), 0) AS current_stock
        FROM medicines m
        LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
        LEFT JOIN pharmacy_stock ps ON mb.id = ps.medicine_batch_id
        WHERE m.id = ?
        GROUP BY m.id
    ");
    $stmt->execute([$medicine_id]);
    $medicine = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$medicine) die("Medicine not found.");
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $generic_name  = trim($_POST['generic_name'] ?? '');
    $brand         = trim($_POST['brand'] ?? '');
    $form          = trim($_POST['form'] ?? '');
    $strength      = trim($_POST['strength'] ?? '');
    $unit          = trim($_POST['unit'] ?? '');
    $description   = trim($_POST['description'] ?? '');

    if ($name === '' || $generic_name === '' || $form === '' || $unit === '') {
        $error = "Please fill all required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE medicines
                SET name = ?, generic_name = ?, brand = ?, form = ?,
                    strength = ?, unit = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $generic_name, $brand, $form, $strength, $unit, $description, $medicine_id]);

            $_SESSION['flash_success'] = "Medicine updated successfully.";
            header("Location: medicines.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

$page_title = 'Edit Medicine';
include 'medicines_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-pencil-square"></i> Edit Medicine — <?= htmlspecialchars($medicine['name'] ?? '') ?></h5>

    <div style="background: #f0fdfa; border-left: 4px solid var(--primary); padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
        <i class="bi bi-info-circle"></i> Current stock: <strong><?= (int)($medicine['current_stock'] ?? 0) ?> <?= htmlspecialchars($medicine['unit'] ?? '') ?></strong>
        &nbsp;<span style="color: var(--text-muted);">(To change stock, use <a href="pharmacy_stock.php">Pharmacy</a>)</span>
    </div>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Medicine Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($medicine['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Generic Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="generic_name" class="form-control" value="<?= htmlspecialchars($medicine['generic_name'] ?? '') ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Brand</label>
                <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($medicine['brand'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Form <span style="color:#ef4444;">*</span></label>
                <select name="form" class="form-select" required>
                    <option value="">Select Form</option>
                    <?php foreach (['Tablet','Capsule','Syrup','Injection','Ointment','Cream','Drops','Inhaler','Spray','Other'] as $f): ?>
                        <option value="<?= $f ?>" <?= ($medicine['form'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Strength</label>
                <input type="text" name="strength" class="form-control" value="<?= htmlspecialchars($medicine['strength'] ?? '') ?>" placeholder="e.g., 500mg">
            </div>
            <div class="col-md-6">
                <label class="form-label">Unit <span style="color:#ef4444;">*</span></label>
                <select name="unit" class="form-select" required>
                    <?php foreach (['tablet','capsule','bottle','tube','inhaler','ampoule','vial','pack'] as $u): ?>
                        <option value="<?= $u ?>" <?= ($medicine['unit'] ?? '') === $u ? 'selected' : '' ?>><?= ucfirst($u) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($medicine['description'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Changes</button>
            <a href="medicines.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Medicines</a>
        </div>
    </form>
</div>

<?php include 'medicines_footer.php'; ?>
