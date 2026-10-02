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

$ward_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ward_id <= 0) {
    header("Location: wards.php");
    exit;
}

// Fetch ward
try {
    $stmt = $pdo->prepare("
        SELECT w.*, CONCAT(u.first_name, ' ', u.last_name) AS in_charge_name
        FROM wards w
        LEFT JOIN users u ON w.in_charge_id = u.id
        WHERE w.id = ?
    ");
    $stmt->execute([$ward_id]);
    $ward = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ward) {
        header("Location: wards.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Staff for "In Charge"
$staff = [];
try {
    $staff = $pdo->query("
        SELECT id, CONCAT(first_name, ' ', last_name) AS name
        FROM users
        WHERE role_id IN (1, 2, 3, 4)
        ORDER BY first_name
    ")->fetchAll();
} catch (PDOException $e) {}

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
                UPDATE wards SET
                    name = ?, type = ?, capacity = ?, charge_per_day = ?,
                    location = ?, phone_extension = ?, status = ?,
                    in_charge_id = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $type, $capacity, $charge_per_day,
                $location, $phone_extension, $status,
                $in_charge_id, $notes, $ward_id
            ]);

            $_SESSION['flash_success'] = "Ward updated successfully.";
            header("Location: wards.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

$page_title = 'Edit Ward';
include 'wards_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-pencil-square"></i> Edit Ward — <?= htmlspecialchars($ward['name'] ?? '') ?></h5>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Ward Name <span style="color:#ef4444;">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($ward['name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Ward Type <span style="color:#ef4444;">*</span></label>
                <select name="type" class="form-select" required>
                    <?php foreach (['general','icu','maternity','pediatric','surgical','orthopedic','cardiac','emergency','psychiatric','isolation','other'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($ward['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Capacity (Beds) <span style="color:#ef4444;">*</span></label>
                <input type="number" name="capacity" class="form-control" min="1" required value="<?= (int)($ward['capacity'] ?? 0) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Charge per Day (&euro;) <span style="color:#ef4444;">*</span></label>
                <input type="number" step="0.01" name="charge_per_day" class="form-control" min="0" required value="<?= htmlspecialchars($ward['charge_per_day'] ?? '0.00') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Location <span style="color:#ef4444;">*</span></label>
                <input type="text" name="location" class="form-control" required value="<?= htmlspecialchars($ward['location'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone Extension</label>
                <input type="text" name="phone_extension" class="form-control" value="<?= htmlspecialchars($ward['phone_extension'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Status <span style="color:#ef4444;">*</span></label>
                <select name="status" class="form-select" required>
                    <?php foreach (['active','inactive','maintenance','full'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($ward['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">In Charge</label>
                <select name="in_charge_id" class="form-select">
                    <option value="">— Not assigned —</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= (int)($ward['in_charge_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($ward['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Changes</button>
            <a href="wards.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Wards</a>
        </div>
    </form>
</div>

<?php include 'wards_footer.php'; ?>
