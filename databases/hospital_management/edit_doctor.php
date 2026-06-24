<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: login.php");
    exit;
}

// Get doctor ID from URL
$doctor_id = $_GET['id'] ?? null;
if (!$doctor_id) {
    header("Location: doctors.php");
    exit;
}

// Fetch doctor info
$stmt = $pdo->prepare("
    SELECT d.id, d.user_id, d.license_number, d.bio, d.consultation_fee,
           u.first_name, u.last_name
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.id = :id
");
$stmt->execute([':id' => $doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    die("Doctor not found");
}

// Fetch all users for dropdown
$usersStmt = $pdo->query("SELECT id, first_name, last_name FROM users");
$usersList = $usersStmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $license_number = $_POST['license_number'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $consultation_fee = $_POST['consultation_fee'] ?? 0;

    if (!empty($user_id) && !empty($license_number)) {
        $updateStmt = $pdo->prepare("
            UPDATE doctors 
            SET user_id = :user_id, license_number = :license_number, bio = :bio, consultation_fee = :consultation_fee
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':user_id' => $user_id,
            ':license_number' => $license_number,
            ':bio' => $bio,
            ':consultation_fee' => $consultation_fee,
            ':id' => $doctor_id
        ]);

        header("Location: doctors.php?success=updated");
        exit;
    } else {
        $error = "Please fill all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Doctor</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { font-family:'Montserrat', sans-serif; background:#f0f2f5; padding:20px; }
.container { max-width:700px; background:#fff; padding:30px; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,0.12); }
.btn-save { background:#56ab2f; color:#fff; border:none; border-radius:6px; }
.btn-save:hover { background:#3c7d1b; }
.btn-back { background:#007bff; color:#fff; border:none; border-radius:6px; }
.btn-back:hover { background:#0056b3; }
</style>
</head>
<body>

<div class="container">
<h2 class="mb-4">Edit Doctor</h2>

<?php if(isset($error)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <div class="mb-3">
        <label>User</label>
        <select name="user_id" class="form-select" required>
            <option value="">Select User</option>
            <?php foreach($usersList as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $user['id']==$doctor['user_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label>License Number</label>
        <input type="text" name="license_number" class="form-control" required value="<?= htmlspecialchars($doctor['license_number']) ?>">
    </div>
    <div class="mb-3">
        <label>Bio</label>
        <textarea name="bio" class="form-control"><?= htmlspecialchars($doctor['bio']) ?></textarea>
    </div>
    <div class="mb-3">
        <label>Consultation Fee</label>
        <input type="number" step="0.01" name="consultation_fee" class="form-control" value="<?= htmlspecialchars($doctor['consultation_fee']) ?>">
    </div>
    <div class="d-flex justify-content-between">
        <button type="submit" class="btn btn-save"><i class="bi bi-check2"></i> Save Changes</button>
        <a href="doctors.php" class="btn btn-back"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
</form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
