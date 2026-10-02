<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('rooms', 'login.php');
if ($access !== 'full') {
    header("Location: rooms.php");
    exit;
}

$room_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($room_id <= 0) {
    header("Location: rooms.php");
    exit;
}

$room_type_options = ['single', 'double', 'icu', 'ward', 'other'];
$room_type_display = [
    'single' => 'Single Room',
    'double' => 'Double Room',
    'icu'    => 'ICU',
    'ward'   => 'Ward',
    'other'  => 'Other',
];

// Fetch room
try {
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) {
        header("Location: rooms.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = trim($_POST['room_number'] ?? '');
    $ward_id     = (int)($_POST['ward_id'] ?? 0);
    $room_type   = $_POST['room_type'] ?? 'ward';
    $capacity    = (int)($_POST['capacity'] ?? 1);
    $notes       = trim($_POST['notes'] ?? '');

    if (!in_array($room_type, $room_type_options)) $room_type = 'ward';

    if ($room_number === '' || $ward_id <= 0) {
        $error = "Please fill in all required fields.";
    } elseif ($capacity < 1 || $capacity > 100) {
        $error = "Capacity must be between 1 and 100.";
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM rooms WHERE room_number = ? AND ward_id = ? AND id != ?");
            $check->execute([$room_number, $ward_id, $room_id]);
            if ($check->fetch()) {
                $error = "Room number already exists in this ward.";
            } else {
                $stmt = $pdo->prepare("UPDATE rooms SET room_number=?, ward_id=?, room_type=?, capacity=?, notes=? WHERE id=?");
                $stmt->execute([$room_number, $ward_id, $room_type, $capacity, $notes, $room_id]);
                $_SESSION['flash_success'] = "Room updated successfully.";
                header("Location: rooms.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch wards
$wards = [];
try {
    $wards = $pdo->query("SELECT id, name, type FROM wards ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$page_title = 'Edit Room';
include 'rooms_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-pencil-square"></i> Edit Room — <?= htmlspecialchars($room['room_number'] ?? '') ?></h5>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Room Number <span style="color:#ef4444;">*</span></label>
                <input type="text" name="room_number" class="form-control" required value="<?= htmlspecialchars($room['room_number'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Ward <span style="color:#ef4444;">*</span></label>
                <select name="ward_id" class="form-select" required>
                    <option value="">Select Ward</option>
                    <?php foreach ($wards as $ward): ?>
                        <option value="<?= (int)$ward['id'] ?>" <?= (int)$room['ward_id'] === (int)$ward['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ward['name']) ?> (<?= htmlspecialchars($ward['type']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Room Type</label>
                <select name="room_type" class="form-select">
                    <?php foreach ($room_type_options as $t): ?>
                        <option value="<?= $t ?>" <?= ($room['room_type'] ?? '') === $t ? 'selected' : '' ?>>
                            <?= $room_type_display[$t] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Capacity</label>
                <input type="number" name="capacity" class="form-control" min="1" max="100" value="<?= (int)($room['capacity'] ?? 1) ?>">
            </div>

            <div class="col-md-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($room['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Changes</button>
            <a href="rooms.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Rooms</a>
        </div>
    </form>
</div>

<?php include 'rooms_footer.php'; ?>
