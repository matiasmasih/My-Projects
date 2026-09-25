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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id          = (int)($_POST['user_id'] ?? 0);
    $license_number   = trim($_POST['license_number'] ?? '');
    $bio              = trim($_POST['bio'] ?? '');
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);

    if ($user_id > 0 && $license_number !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO doctors (user_id, license_number, bio, consultation_fee) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $license_number, $bio, $consultation_fee]);
            $_SESSION['flash_success'] = "Doctor added successfully.";
            header("Location: doctors.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        $error = "Missing required fields.";
    }
}

// Users without a doctor profile
$usersList = [];
try {
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name
        FROM users u
        LEFT JOIN doctors d ON d.user_id = u.id
        WHERE d.id IS NULL
        ORDER BY u.first_name
    ");
    $usersList = $stmt->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Add Doctor';
include 'doctors_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-person-plus-fill"></i> Add New Doctor</h5>

    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 18px;">
        <i class="bi bi-info-circle"></i> Select an existing user to become a doctor.
    </p>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-12">
                <label class="form-label">User <span style="color: #ef4444;">*</span></label>
                <select name="user_id" class="form-select" required>
                    <option value="">Select User</option>
                    <?php foreach ($usersList as $user): ?>
                        <option value="<?= $user['id'] ?>">
                            <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">License Number <span style="color: #ef4444;">*</span></label>
                <input type="text" name="license_number" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Consultation Fee (€)</label>
                <input type="number" step="0.01" name="consultation_fee" class="form-control" value="0.00">
            </div>
            <div class="col-md-12">
                <label class="form-label">Bio</label>
                <textarea name="bio" class="form-control" rows="4"></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Doctor</button>
            <a href="doctors.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Doctors</a>
        </div>
    </form>
</div>

<?php include 'doctors_footer.php'; ?>
