<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('wards', 'login.php');
if ($access !== 'full') {
    header("Location: wards.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name            = trim($_POST['name'] ?? '');
    $type            = $_POST['type'] ?? 'general';
    $capacity        = (int)($_POST['capacity'] ?? 0);
    $charge_per_day  = (float)($_POST['charge_per_day'] ?? 0);
    $location        = trim($_POST['location'] ?? '');
    $phone_extension = trim($_POST['phone_extension'] ?? '');
    $status          = $_POST['status'] ?? 'active';
    $in_charge_id    = !empty($_POST['in_charge_id']) ? (int)$_POST['in_charge_id'] : null;
    $notes           = trim($_POST['notes'] ?? '');

    if ($name === '' || $location === '') {
        $error = "Ward name and location are required.";
    } elseif ($capacity <= 0) {
        $error = "Capacity must be greater than 0.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO wards
                    (name, type, capacity, charge_per_day, location, phone_extension, status, in_charge_id, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $type, $capacity, $charge_per_day, $location, $phone_extension, $status, $in_charge_id, $notes]);

            $_SESSION['flash_success'] = "Ward added: " . $name;
            header("Location: wards.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch staff for "In Charge" dropdown (doctors + staff)
$staff = [];
try {
    $staff = $pdo->query("
        SELECT id, CONCAT(first_name, ' ', last_name) AS name
        FROM users
        WHERE role_id IN (3, 4)
        ORDER BY first_name
    ")->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Add Ward';
include 'wards_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-plus-circle"></i> Add New Ward</h5>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Ward Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="name" class="form-control" required placeholder="e.g., General Ward A">
            </div>
            <div class="col-md-6">
                <label class="form-label">Ward Type <span style="color:#ef4444;">*</span></label>
                <select name="type" class="form-select" required>
                    <?php foreach (['general','icu','maternity','pediatric','surgical','orthopedic','cardiac','emergency','psychiatric','isolation','other'] as $t): ?>
                        <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Capacity (Beds) <span style="color:#ef4444;">*</span></label>
                <input type="number" name="capacity" class="form-control" min="1" required value="20">
            </div>
            <div class="col-md-6">
                <label class="form-label">Charge per Day (&euro;) <span style="color:#ef4444;">*</span></label>
                <input type="number" step="0.01" name="charge_per_day" class="form-control" min="0" required value="50.00">
            </div>

            <div class="col-md-6">
                <label class="form-label">Location <span style="color:#ef4444;">*</span></label>
                <input type="text" name="location" class="form-control" required placeholder="e.g., First Floor, West Wing">
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone Extension</label>
                <input type="text" name="phone_extension" class="form-control" placeholder="e.g., 1234">
            </div>

            <div class="col-md-6">
                <label class="form-label">Status <span style="color:#ef4444;">*</span></label>
                <select name="status" class="form-select" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="full">Full</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">In Charge</label>
                <select name="in_charge_id" class="form-select">
                    <option value="">— Not assigned —</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Any additional notes about this ward..."></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Ward</button>
            <a href="wards.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Wards</a>
        </div>
    </form>
</div>

<script>
// Auto-set charge & capacity based on ward type
document.querySelector('select[name="type"]').addEventListener('change', function() {
    const type = this.value;
    const chargeField = document.querySelector('input[name="charge_per_day"]');
    const capacityField = document.querySelector('input[name="capacity"]');

    const charges = {
        'general': 50.00, 'icu': 200.00, 'maternity': 80.00, 'pediatric': 60.00,
        'surgical': 75.00, 'orthopedic': 70.00, 'cardiac': 150.00, 'emergency': 100.00,
        'psychiatric': 65.00, 'isolation': 90.00, 'other': 50.00
    };
    const capacities = {
        'general': 20, 'icu': 10, 'maternity': 15, 'pediatric': 18,
        'surgical': 16, 'orthopedic': 15, 'cardiac': 12, 'emergency': 15,
        'psychiatric': 20, 'isolation': 8, 'other': 15
    };

    if (charges[type]) chargeField.value = charges[type];
    if (capacities[type]) capacityField.value = capacities[type];
});
</script>

<?php include 'wards_footer.php'; ?>
