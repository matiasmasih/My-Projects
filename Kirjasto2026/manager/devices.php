<?php
// manager/devices.php — Local Branch Device Inventory

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// ---- Resolve manager's branch ----
$stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$manager_row = $stmt->fetch();
$branch_id = $manager_row['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$flash = '';
$error = '';

// ---- LOAN OUT DEVICE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'loan_out') {
    $device_id = (int)$_POST['device_id'];
    $user_id = (int)$_POST['user_id'];
    $days = (int)($_POST['days'] ?? 14);

    if (!$branch_id) {
        $error = 'Your account is not assigned to a branch.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND status = 'available' AND branch_id = ?");
            $stmt->execute([$device_id, $branch_id]);
            $device = $stmt->fetch();
            if (!$device) throw new Exception('Device not available in your branch.');

            $borrowed_at = date('Y-m-d H:i:s');
            $due_at = date('Y-m-d H:i:s', strtotime("+$days days"));

            $pdo->prepare("INSERT INTO device_loans (user_id, device_id, borrowed_at, due_at, status, branch_id) VALUES (?, ?, ?, ?, 'active', ?)")
                ->execute([$user_id, $device_id, $borrowed_at, $due_at, $branch_id]);

            $pdo->prepare("UPDATE devices SET status = 'borrowed' WHERE id = ?")->execute([$device_id]);

            $pdo->commit();
            $flash = 'Device loaned out successfully. Due on ' . date('M j, Y', strtotime($due_at)) . '.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Loan failed: ' . $e->getMessage();
        }
    }
}

// ---- ADD DEVICE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_device') {
    $device_type_id = (int)$_POST['device_type_id'];
    $serial_number = trim($_POST['serial_number']);
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $condition = $_POST['condition'] ?? 'good';
    $location = trim($_POST['location'] ?? 'Main Desk');
    $image = null;

    if (!$branch_id) {
        $error = 'Your account is not assigned to a branch.';
    } elseif (empty($serial_number) || $device_type_id <= 0) {
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
            $stmt = $pdo->prepare("INSERT INTO devices (device_type_id, serial_number, brand, model, `condition`, status, location, image, branch_id) VALUES (?, ?, ?, ?, ?, 'available', ?, ?, ?)");
            $stmt->execute([$device_type_id, $serial_number, $brand, $model, $condition, $location, $image, $branch_id]);
            $flash = 'Device added successfully to your branch.';
        } catch (PDOException $e) {
            $error = 'Failed to add device: ' . $e->getMessage();
        }
    }
}

// ---- DELETE DEVICE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_device') {
    $device_id = (int)$_POST['device_id'];
    if (!$branch_id) {
        $error = 'Your account is not assigned to a branch.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT image FROM devices WHERE id = ? AND branch_id = ?");
            $stmt->execute([$device_id, $branch_id]);
            $row = $stmt->fetch();
            
            if (!$row) throw new Exception('Device not found in your branch.');

            if (!empty($row['image']) && file_exists(__DIR__ . '/../uploads/' . $row['image'])) {
                @unlink(__DIR__ . '/../uploads/' . $row['image']);
            }

            $pdo->prepare("DELETE FROM devices WHERE id = ?")->execute([$device_id]);
            $flash = 'Device deleted from your branch.';
        } catch (Exception $e) {
            $error = 'Cannot delete — ' . $e->getMessage();
        }
    }
}

// ---- SEARCH & FILTER (Local Only) ----
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$devices = [];

if ($branch_id) {
    $query = "
        SELECT d.*, dt.name AS type_name, dt.loan_days
        FROM devices d
        JOIN device_types dt ON dt.id = d.device_type_id
        WHERE d.branch_id = ?
    ";
    $params = [$branch_id];
    $conditions = [];

    if ($search !== '') {
        $conditions[] = "(d.brand LIKE ? OR d.model LIKE ? OR d.serial_number LIKE ?)";
        $term = '%' . $search . '%';
        $params = array_merge($params, [$term, $term, $term]);
    }

    if ($filter === 'available') {
        $conditions[] = "d.status = 'available'";
    } elseif ($filter === 'borrowed') {
        $conditions[] = "d.status = 'borrowed'";
    } elseif ($filter === 'maintenance') {
        $conditions[] = "d.status = 'maintenance'";
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(' AND ', $conditions);
    }
    $query .= " ORDER BY d.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $devices = $stmt->fetchAll();
}

$device_types = $pdo->query("SELECT * FROM device_types ORDER BY name")->fetchAll();

$members = [];
if ($branch_id) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE role = 'member' AND branch_id = ? ORDER BY first_name");
    $stmt->execute([$branch_id]);
    $members = $stmt->fetchAll();
}

$page_title = 'Devices';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Device Inventory</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
            <?= $branch_name ? e($branch_name) . ' Branch' : 'Branch Inventory' ?>
        </p>
    </div>
    <button onclick="document.getElementById('addDeviceModal').style.display='flex'" class="btn-primary" style="width: auto; padding: 12px 24px; background: linear-gradient(135deg, #f59e0b, #d97706);">
        <i class="fas fa-plus"></i> Add Device
    </button>
</div>

<?php if (!$branch_id): ?>
    <div class="flash flash-error" style="margin-bottom: 24px;"><i class="fas fa-exclamation-triangle"></i> Your account is not assigned to a branch.</div>
<?php endif; ?>
<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<!-- Filter & Search Bar -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="?filter=all&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'all' ? 'background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-list"></i> All
            </a>
            <a href="?filter=available&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'available' ? 'background: var(--success); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-check"></i> Available
            </a>
            <a href="?filter=borrowed&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'borrowed' ? 'background: var(--warning); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-clock"></i> On Loan
            </a>
            <a href="?filter=maintenance&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'maintenance' ? 'background: var(--danger); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-tools"></i> Maintenance
            </a>
        </div>
        
        <form method="GET" style="display: flex; gap: 8px; flex: 1; max-width: 400px;">
            <input type="hidden" name="filter" value="<?= e($filter) ?>">
            <div style="position: relative; flex: 1;">
                <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search brand, model, serial..." style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); outline: none;">
            </div>
            <button type="submit" class="btn-primary" style="padding: 0 20px; border-radius: 8px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: none; cursor: pointer; font-weight: 600;">Search</button>
        </form>
    </div>
</div>

<!-- Devices Grid -->
<?php if (empty($devices)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-laptop" style="font-size: 3rem; opacity: 0.3; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
        <h3 style="color: var(--text-muted); margin-bottom: 8px;">No Devices Found</h3>
        <p style="color: var(--text-muted);">Your branch doesn't have any devices matching this filter.</p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">
        <?php foreach ($devices as $device): ?>
            <div class="card" style="display: flex; flex-direction: column; padding: 0; overflow: hidden;">
                <!-- Device Image -->
                <div style="height: 220px; background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(245,158,11,0.05)); display: flex; align-items: center; justify-content: center; position: relative;">
                    <?php if (!empty($device['image']) && file_exists(__DIR__ . '/../uploads/' . $device['image'])): ?>
                        <img src="<?= UPLOADS_URL . e($device['image']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-laptop" style="font-size: 4rem; color: rgba(245, 158, 11, 0.4);"></i>
                    <?php endif; ?>
                    
                    <div style="position: absolute; top: 12px; right: 12px; display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                        <?php
                            $status_color = 'var(--success)';
                            if ($device['status'] === 'borrowed') $status_color = 'var(--warning)';
                            if ($device['status'] === 'maintenance') $status_color = 'var(--danger)';
                            if ($device['status'] === 'in_transit') $status_color = '#0ea5e9';
                            if ($device['status'] === 'lost') $status_color = 'var(--text-muted)';
                        ?>
                        <span style="padding: 6px 12px; background: <?= $status_color ?>; color: white; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                            <?= e(str_replace('_', ' ', $device['status'])) ?>
                        </span>
                    </div>
                </div>

                <!-- Device Info -->
                <div style="padding: 20px; flex: 1; display: flex; flex-direction: column;">
                    <h4 style="font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 4px;"><?= e($device['brand'] . ' ' . $device['model']) ?></h4>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 16px;"><?= e($device['type_name']) ?> · <?= e($device['serial_number']) ?></p>

                    <div style="display: flex; gap: 16px; margin-bottom: 16px; font-size: 0.8rem; color: var(--text-muted);">
                        <span><i class="fas fa-wrench"></i> <?= e($device['condition']) ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?= e($device['location']) ?></span>
                    </div>

                    <div style="margin-top: auto; display: flex; gap: 8px;">
                        <?php if ($device['status'] === 'available'): ?>
                            <button onclick="openLoanModal(<?= $device['id'] ?>, '<?= e(addslashes($device['brand'] . ' ' . $device['model'])) ?>')" class="btn-primary" style="flex: 1; padding: 10px; font-size: 0.85rem; background: linear-gradient(135deg, #f59e0b, #d97706); border: none; cursor: pointer; border-radius: 8px; font-weight: 600;">
                                <i class="fas fa-hand-holding"></i> Loan Out
                            </button>
                        <?php elseif ($device['status'] === 'borrowed'): ?>
                            <a href="<?= e(SITE_URL) ?>manager/device_loans.php" class="btn-primary" style="flex: 1; padding: 10px; font-size: 0.85rem; background: var(--success); text-align: center; text-decoration: none; border-radius: 8px; font-weight: 600;">
                                <i class="fas fa-undo"></i> Return
                            </a>
                        <?php else: ?>
                            <button disabled class="btn-outline" style="flex: 1; padding: 10px; font-size: 0.85rem; opacity: 0.5; cursor: not-allowed; border-radius: 8px;">
                                <i class="fas fa-ban"></i> Unavailable
                            </button>
                        <?php endif; ?>

                        <form method="post" onsubmit="return confirm('Delete this device?');" style="display: inline; margin: 0;">
                            <input type="hidden" name="action" value="delete_device">
                            <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                            <button type="submit" class="btn-outline" style="padding: 10px 14px; font-size: 0.85rem; color: var(--danger); border-color: rgba(239, 68, 68, 0.3); border-radius: 8px;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Loan Out Modal -->
<div id="loanModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 500px; background: var(--card-solid);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0;"><i class="fas fa-hand-holding" style="color: #f59e0b;"></i> Loan Out Device</h3>
            <button onclick="document.getElementById('loanModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="loan_out">
            <input type="hidden" name="device_id" id="loanDeviceId" value="">
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">Device</label>
                <input type="text" id="loanDeviceName" readonly style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--text-muted);">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">Member</label>
                <select name="user_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                    <option value="">— Select member —</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= e($m['first_name'] . ' ' . $m['last_name']) ?> (<?= e($m['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 24px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text);">Loan Duration (Days)</label>
                <input type="number" name="days" value="14" min="1" max="90" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #f59e0b, #d97706); border: none; border-radius: 8px; font-weight: 600; color: white;">
                <i class="fas fa-check"></i> Confirm Loan
            </button>
        </form>
    </div>
</div>

<!-- Add Device Modal -->
<div id="addDeviceModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; background: var(--card-solid);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0;"><i class="fas fa-plus-circle" style="color: #f59e0b;"></i> Add New Device</h3>
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
                    <label>Location</label>
                    <input type="text" name="location" value="Main Desk" placeholder="Front Desk">
                </div>
            </div>
            <div class="form-group">
                <label>Device Image</label>
                <input type="file" name="image" accept="image/*">
                <small style="color: var(--text-muted); display: block; margin-top: 6px; font-size: 0.8rem;">JPG, PNG, WEBP. Max 5MB.</small>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" onclick="document.getElementById('addDeviceModal').style.display='none'" class="btn-outline">Cancel</button>
                <button type="submit" class="btn-primary" style="width: auto; background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-save"></i> Add Device
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openLoanModal(deviceId, deviceName) {
        document.getElementById('loanDeviceId').value = deviceId;
        document.getElementById('loanDeviceName').value = deviceName;
        document.getElementById('loanModal').style.display = 'flex';
    }
</script>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
