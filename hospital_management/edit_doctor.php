<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('doctors', 'login.php');

if ($access !== 'full') {
    header("Location: doctors.php");
    exit;
}

$doctor_id = $_GET['id'] ?? null;
if (!$doctor_id) {
    header("Location: doctors.php");
    exit;
}

// Fetch doctor
$stmt = $pdo->prepare("
    SELECT d.id, d.user_id, d.license_number, d.bio, d.consultation_fee,
           u.first_name, u.last_name
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.id = ?
");
$stmt->execute([$doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) die("Doctor not found.");

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id          = (int)($_POST['user_id'] ?? 0);
    $license_number   = trim($_POST['license_number'] ?? '');
    $bio              = trim($_POST['bio'] ?? '');
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);

    if ($user_id > 0 && $license_number !== '') {
        try {
            $stmt = $pdo->prepare("
                UPDATE doctors
                SET user_id = ?, license_number = ?, bio = ?, consultation_fee = ?
                WHERE id = ?
            ");
            $stmt->execute([$user_id, $license_number, $bio, $consultation_fee, $doctor_id]);

            $_SESSION['flash_success'] = "Doctor updated successfully.";
            header("Location: doctors.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill all required fields.";
    }
}

// Users dropdown (include current user so it stays selected)
$usersStmt = $pdo->query("SELECT id, first_name, last_name FROM users ORDER BY first_name");
$usersList = $usersStmt->fetchAll();

$page_title = 'Edit Doctor';
include 'doctors_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-pencil-square"></i> Edit Doctor — <?= htmlspecialchars(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? '')) ?></h5>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">User <span style="color: #ef4444;">*</span></label>
                <select name="user_id" class="form-select" required>
                    <option value="">Select User</option>
                    <?php foreach ($usersList as $user): ?>
                        <option value="<?= $user['id'] ?>" <?= $user['id'] == $doctor['user_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">License Number <span style="color: #ef4444;">*</span></label>
                <input type="text" name="license_number" class="form-control" required value="<?= htmlspecialchars($doctor['license_number'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Consultation Fee (€)</label>
                <input type="number" step="0.01" name="consultation_fee" class="form-control" value="<?= htmlspecialchars($doctor['consultation_fee'] ?? '0.00') ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Bio</label>
                <textarea name="bio" class="form-control" rows="4"><?= htmlspecialchars($doctor['bio'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Changes</button>
            <a href="doctors.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Doctors</a>
        </div>
    </form>
</div>

<?php include 'doctors_footer.php'; ?>
