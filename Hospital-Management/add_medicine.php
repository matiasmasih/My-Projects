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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name'] ?? '');
    $generic_name   = trim($_POST['generic_name'] ?? '');
    $brand          = trim($_POST['brand'] ?? '');
    $form           = trim($_POST['form'] ?? '');
    $strength       = trim($_POST['strength'] ?? '');
    $unit           = trim($_POST['unit'] ?? '');
    $description    = trim($_POST['description'] ?? '');

    if ($name === '' || $generic_name === '' || $form === '' || $unit === '') {
        $error = "Please fill all required fields.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO medicines (name, generic_name, brand, form, strength, unit, description)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $generic_name, $brand, $form, $strength, $unit, $description]);

            $_SESSION['flash_success'] = "Medicine added: " . $name;
            header("Location: medicines.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

$page_title = 'Add Medicine';
include 'medicines_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-plus-circle"></i> Add New Medicine</h5>

    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 18px;">
        <i class="bi bi-info-circle"></i> Add a medicine to the catalog. Stock can be added later via Pharmacy.
    </p>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Medicine Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Generic Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="generic_name" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Brand</label>
                <input type="text" name="brand" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Form <span style="color:#ef4444;">*</span></label>
                <select name="form" class="form-select" required>
                    <option value="">Select Form</option>
                    <?php foreach (['Tablet','Capsule','Syrup','Injection','Ointment','Cream','Drops','Inhaler','Spray','Other'] as $f): ?>
                        <option value="<?= $f ?>"><?= $f ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Strength</label>
                <input type="text" name="strength" class="form-control" placeholder="e.g., 500mg, 250mg">
            </div>
            <div class="col-md-6">
                <label class="form-label">Unit <span style="color:#ef4444;">*</span></label>
                <select name="unit" class="form-select" required>
                    <?php foreach (['tablet','capsule','bottle','tube','inhaler','ampoule','vial','pack'] as $u): ?>
                        <option value="<?= $u ?>"><?= ucfirst($u) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Medicine description, usage, etc."></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Medicine</button>
            <a href="medicines.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Medicines</a>
        </div>
    </form>
</div>

<?php include 'medicines_footer.php'; ?>
