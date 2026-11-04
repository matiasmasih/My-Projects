<?php
// admin/device_edit.php — Admin Device Editor (multi-branch)
// Kirjasto 2026 — Phase 9

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$device_id = (int)($_GET['id'] ?? 0);
if ($device_id <= 0) {
    header('Location: ' . SITE_URL . 'admin/devices.php');
    exit;
}

$stmt = $pdo->prepare("SELECT d.*, dt.name AS type_name FROM devices d JOIN device_types dt ON dt.id = d.device_type_id WHERE d.id = ?");
$stmt->execute([$device_id]);
$device = $stmt->fetch();

if (!$device) {
    header('Location: ' . SITE_URL . 'admin/devices.php');
    exit;
}

$flash = '';
$error = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $device_type_id = (int)$_POST['device_type_id'];
    $serial_number = trim($_POST['serial_number']);
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $condition = $_POST['condition'];
    $status = $_POST['status'];
    $location = trim($_POST['location']);
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    $image = $device['image'];

    if (empty($serial_number) || $device_type_id <= 0) {
        $error = 'Device type and serial number are required.';
    } else {
        // New image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $new_image = 'device_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../uploads/' . $new_image)) {
                    if (!empty($device['image']) && file_exists(__DIR__ . '/../uploads/' . $device['image'])) {
                        @unlink(__DIR__ . '/../uploads/' . $device['image']);
                    }
                    $image = $new_image;
                }
            }
        }

        try {
            $update = $pdo->prepare("UPDATE devices SET device_type_id = ?, serial_number = ?, brand = ?, model = ?, `condition` = ?, status = ?, location = ?, branch_id = ?, image = ? WHERE id = ?");
            $update->execute([$device_type_id, $serial_number, $brand, $model, $condition, $status, $location, $branch_id, $image, $device_id]);
            $flash = 'Device updated successfully.';

            $stmt = $pdo->prepare("SELECT d.*, dt.name AS type_name FROM devices d JOIN device_types dt ON dt.id = d.device_type_id WHERE d.id = ?");
            $stmt->execute([$device_id]);
            $device = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

// Fetch device types and branches
$device_types = $pdo->query("SELECT * FROM device_types ORDER BY name")->fetchAll();
$branches = get_branches();

$page_title = 'Edit Device';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="margin-bottom: 32px;">
    <a href="<?= e(SITE_URL) ?>admin/devices.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); text-decoration: none; font-size: 0.9rem; margin-bottom: 8px;">
        <i class="fas fa-arrow-left"></i> Back to Devices
    </a>
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Edit Device</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Update device details, status, branch, and image.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2">
    <!-- Left: Preview -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="height: 280px; background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05)); display: flex; align-items: center; justify-content: center;">
            <?php if (!empty($device['image']) && file_exists(__DIR__ . '/../uploads/' . $device['image'])): ?>
                <img src="<?= UPLOADS_URL . e($device['image']) ?>?v=<?= time() ?>" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <i class="fas fa-laptop" style="font-size: 5rem; color: rgba(59, 130, 246, 0.4);"></i>
            <?php endif; ?>
        </div>
        <div style="padding: 24px;">
            <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text); margin-bottom: 4px;"><?= e($device['brand'] . ' ' . $device['model']) ?></h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 16px;"><?= e($device['type_name']) ?> · <?= e($device['serial_number']) ?></p>

            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.9rem;">
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                    <span style="color: var(--text-muted);">Serial Number</span>
                    <span style="color: var(--text); font-weight: 600;"><?= e($device['serial_number']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                    <span style="color: var(--text-muted);">Condition</span>
                    <span style="color: var(--text); font-weight: 600; text-transform: capitalize;"><?= e($device['condition']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                    <span style="color: var(--text-muted);">Status</span>
                    <span style="color: var(--text); font-weight: 600; text-transform: uppercase;"><?= e($device['status']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                    <span style="color: var(--text-muted);">Branch</span>
                    <span style="color: var(--text); font-weight: 600;"><?= e(get_branch_name($device['branch_id'])) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                    <span style="color: var(--text-muted);">Location</span>
                    <span style="color: var(--text); font-weight: 600;"><?= e($device['location']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 10px 0;">
                    <span style="color: var(--text-muted);">Device ID</span>
                    <span style="color: var(--text); font-weight: 600;">#<?= $device['id'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Edit Form -->
    <div class="card">
        <h3><i class="fas fa-pen"></i> Device Details</h3>

        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>Device Type *</label>
                <select name="device_type_id" required>
                    <?php foreach ($device_types as $dt): ?>
                        <option value="<?= $dt['id'] ?>" <?= $dt['id'] == $device['device_type_id'] ? 'selected' : '' ?>>
                            <?= e($dt['name']) ?> (<?= $dt['loan_days'] ?> days)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" value="<?= e($device['brand']) ?>">
                </div>
                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" value="<?= e($device['model']) ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Serial Number *</label>
                <input type="text" name="serial_number" value="<?= e($device['serial_number']) ?>" required>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Condition</label>
                    <select name="condition">
                        <option value="excellent" <?= $device['condition'] === 'excellent' ? 'selected' : '' ?>>Excellent</option>
                        <option value="good" <?= $device['condition'] === 'good' ? 'selected' : '' ?>>Good</option>
                        <option value="fair" <?= $device['condition'] === 'fair' ? 'selected' : '' ?>>Fair</option>
                        <option value="poor" <?= $device['condition'] === 'poor' ? 'selected' : '' ?>>Poor</option>
                        <option value="maintenance" <?= $device['condition'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="available" <?= $device['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="borrowed" <?= $device['status'] === 'borrowed' ? 'selected' : '' ?>>Borrowed</option>
                        <option value="reserved" <?= $device['status'] === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                        <option value="maintenance" <?= $device['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                        <option value="lost" <?= $device['status'] === 'lost' ? 'selected' : '' ?>>Lost</option>
                        <option value="in_transit" <?= $device['status'] === 'in_transit' ? 'selected' : '' ?>>In Transit</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Branch</label>
                <select name="branch_id">
                    <option value="">— No Branch —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $device['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Location (inside branch)</label>
                <input type="text" name="location" value="<?= e($device['location']) ?>">
            </div>
            <div class="form-group">
                <label>Replace Device Image</label>
                <input type="file" name="image" accept="image/*">
                <small style="color: var(--text-muted); display: block; margin-top: 6px; font-size: 0.8rem;">Leave empty to keep current image.</small>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <a href="<?= e(SITE_URL) ?>admin/devices.php" class="btn-outline">Cancel</a>
                <button type="submit" class="btn-primary" style="width: auto; background: linear-gradient(135deg, #3b82f6, #2563eb);">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
