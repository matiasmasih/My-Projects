<?php
// admin/devices.php — Global Device Management (multi-branch)
// Kirjasto 2026 — Phase 9

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
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

$flash = '';
$error = '';

// ---- ADD DEVICE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_device') {
    $device_type_id = (int)$_POST['device_type_id'];
    $serial_number  = trim($_POST['serial_number']);
    $brand          = trim($_POST['brand']);
    $model          = trim($_POST['model']);
    $condition      = $_POST['condition'] ?? 'good';
    $location       = trim($_POST['location'] ?? 'Main Desk');
    $branch_id      = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    $image          = null;

    if (empty($serial_number) || $device_type_id <= 0) {
        $error = 'Device type and serial number are required.';
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $image = 'device_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../uploads/' . $image)) {
                    $image = null;
                }
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO devices (device_type_id, serial_number, brand, model, `condition`, status, location, branch_id, image) VALUES (?, ?, ?, ?, ?, 'available', ?, ?, ?)");
            $stmt->execute([$device_type_id, $serial_number, $brand, $model, $condition, $location, $branch_id, $image]);
            $flash = 'Device added successfully.';
        } catch (PDOException $e) {
            $error = 'Failed to add device: ' . $e->getMessage();
        }
    }
}

// ---- CHANGE BRANCH ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_branch') {
    $device_id = (int)$_POST['device_id'];
    $new_branch = $_POST['branch_id'] === '' ? null : (int)$_POST['branch_id'];
    $pdo->prepare("UPDATE devices SET branch_id = ? WHERE id = ?")->execute([$new_branch, $device_id]);
    $flash = 'Device branch updated.';
}

// ---- DELETE DEVICE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_device') {
    $device_id = (int)$_POST['device_id'];
    try {
        $stmt = $pdo->prepare("SELECT image FROM devices WHERE id = ?");
        $stmt->execute([$device_id]);
        $row = $stmt->fetch();
        if ($row && !empty($row['image']) && file_exists(__DIR__ . '/../uploads/' . $row['image'])) {
            @unlink(__DIR__ . '/../uploads/' . $row['image']);
        }

        $pdo->prepare("DELETE FROM devices WHERE id = ?")->execute([$device_id]);
        $flash = 'Device deleted.';
    } catch (PDOException $e) {
        $error = 'Cannot delete — device may have active loans.';
    }
}

// ---- FILTER ----
$filter_branch = $_GET['branch'] ?? 'all';

$sql = "
    SELECT d.*, dt.name AS type_name,
           (SELECT COUNT(*) FROM device_loans WHERE device_id = d.id AND status = 'active') AS active_loan
    FROM devices d
    JOIN device_types dt ON dt.id = d.device_type_id
";
$params = [];
if ($filter_branch !== 'all') {
    if ($filter_branch === 'none') {
        $sql .= " WHERE d.branch_id IS NULL";
    } else {
        $sql .= " WHERE d.branch_id = ?";
        $params[] = (int)$filter_branch;
    }
}
$sql .= " ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll();

$device_types = $pdo->query("SELECT * FROM device_types ORDER BY name")->fetchAll();
$branches = get_branches();

$page_title = 'Manage Devices';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Global Devices</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Manage all equipment across the library system.</p>
    </div>
    <button onclick="document.getElementById('addDeviceModal').style.display='flex'" class="btn-primary" style="width: auto; padding: 12px 24px; background: linear-gradient(135deg, #3b82f6, #2563eb);">
        <i class="fas fa-plus"></i> Add Device
    </button>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<!-- Filter by Branch -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
    <form method="get" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <label style="font-weight: 600; color: var(--text-muted); font-size: 0.9rem;">Filter by branch:</label>
        <select name="branch" onchange="this.form.submit()" style="padding: 10px 16px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 10px; color: var(--text); font-weight: 600; cursor: pointer;">
            <option value="all" <?= $filter_branch === 'all' ? 'selected' : '' ?>>All Branches</option>
            <?php foreach ($branches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $filter_branch == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
            <option value="none" <?= $filter_branch === 'none' ? 'selected' : '' ?>>No Branch Assigned</option>
        </select>
    </form>
</div>

<!-- Devices Grid -->
<?php if (empty($devices)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-laptop" style="font-size: 3rem; opacity: 0.3; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
        <h3 style="color: var(--text-muted);">No Devices Found</h3>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">
        <?php foreach ($devices as $device): ?>
            <div class="card" style="display: flex; flex-direction: column; padding: 0; overflow: hidden;">
                <div style="height: 220px; background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05)); display: flex; align-items: center; justify-content: center; position: relative;">
                    <?php if (!empty($device['image']) && file_exists(__DIR__ . '/../uploads/' . $device['image'])): ?>
                        <img src="<?= UPLOADS_URL . e($device['image']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-laptop" style="font-size: 4rem; color: rgba(59, 130, 246, 0.4);"></i>
                    <?php endif; ?>
                    <div style="position: absolute; top: 12px; right: 12px;">
                        <?php
                            $status_color = 'var(--success)';
                            if ($device['status'] === 'borrowed') $status_color = 'var(--warning)';
                            if ($device['status'] === 'maintenance') $status_color = 'var(--danger)';
                        ?>
                        <span style="padding: 6px 12px; background: <?= $status_color ?>; color: white; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                            <?= e($device['status']) ?>
                        </span>
                    </div>
                </div>
                <div style="padding: 20px; flex: 1; display: flex; flex-direction: column;">
                    <h4 style="font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 4px;"><?= e($device['brand'] . ' ' . $device['model']) ?></h4>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 16px;"><?= e($device['type_name']) ?> · <?= e($device['serial_number']) ?></p>

                    <div style="display: flex; gap: 16px; margin-bottom: 16px; font-size: 0.8rem; color: var(--text-muted);">
                        <span><i class="fas fa-wrench"></i> <?= e($device['condition']) ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?= e($device['location']) ?></span>
                    </div>

                    <!-- Branch selector (inline) -->
                    <form method="post" style="margin-bottom: 16px;">
                        <input type="hidden" name="action" value="change_branch">
                        <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                        <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 6px;">Branch</label>
                        <select name="branch_id" onchange="this.form.submit()" style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--text); font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                            <option value="">— No Branch —</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $device['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <div style="margin-top: auto; display: flex; gap: 8px;">
                        <a href="<?= e(SITE_URL) ?>admin/device_edit.php?id=<?= $device['id'] ?>" class="btn-outline" style="flex: 1; padding: 8px; font-size: 0.8rem;">
                            <i class="fas fa-pen"></i> Edit
                        </a>
                        <form method="post" onsubmit="return confirm('Delete this device?');" style="flex: 1;">
                            <input type="hidden" name="action" value="delete_device">
                            <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                            <button type="submit" class="btn-outline" style="width: 100%; padding: 8px; font-size: 0.8rem; color: var(--danger); border-color: rgba(239, 68, 68, 0.3);">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add Device Modal -->
<div id="addDeviceModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; background: var(--card-solid);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0;"><i class="fas fa-plus-circle" style="color: #3b82f6;"></i> Add New Device</h3>
            <button onclick="document.getElementById('addDeviceModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_device">
            <div class="form-group">
                <label>Device Type *</label>
                <select name="device_type_id" required>
                    <option value="">— Select type —</option>
                    <?php foreach ($device_types as $dt): ?>
                        <option value="<?= $dt['id'] ?>"><?= e($dt['name']) ?> (<?= $dt['loan_days'] ?> days)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" placeholder="Apple">
                </div>
                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" placeholder="MacBook Pro 14">
                </div>
            </div>
            <div class="form-group">
                <label>Serial Number *</label>
                <input type="text" name="serial_number" required placeholder="SN-123456789">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Condition</label>
                    <select name="condition">
                        <option value="excellent">Excellent</option>
                        <option value="good" selected>Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Location (inside branch)</label>
                    <input type="text" name="location" value="Main Desk">
                </div>
            </div>
            <div class="form-group">
                <label>Branch *</label>
                <select name="branch_id" required>
                    <option value="">— Select branch —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Device Image</label>
                <input type="file" name="image" accept="image/*">
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" onclick="document.getElementById('addDeviceModal').style.display='none'" class="btn-outline">Cancel</button>
                <button type="submit" class="btn-primary" style="width: auto; background: linear-gradient(135deg, #3b82f6, #2563eb);">Add Device</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
