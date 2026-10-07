<?php
// manage_devices.php — Librarian: CRUD for library devices (laptops, tablets, etc.)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('manage_devices');
if (my_access('manage_devices') !== 'full') {
    header('Location: index.php'); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Manage Devices';
$action     = $_GET['action'] ?? 'list';
$error      = '';

// ============================================================
// ADD / EDIT (POST)
// ============================================================
if (in_array($action, ['add','edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $device_id    = (int)($_POST['device_id'] ?? 0);
    $name         = trim($_POST['name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $serial       = trim($_POST['serial_number'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $brand        = trim($_POST['brand'] ?? '');
    $model        = trim($_POST['model'] ?? '');
    $purchase     = (int)($_POST['purchase_year'] ?? 0) ?: null;
    $branch_id    = (int)($_POST['branch_id'] ?? 0) ?: null;
    $status       = $_POST['status'] ?? 'available';
    $condition    = $_POST['device_condition'] ?? 'good';

    if ($name === '') {
        $error = 'Device name is required.';
    } elseif ($serial === '') {
        $error = 'Serial number is required.';
    } elseif (!in_array($status, ['available','borrowed','maintenance','retired'], true)) {
        $error = 'Invalid status.';
    } elseif (!in_array($condition, ['new','good','worn','damaged'], true)) {
        $error = 'Invalid condition.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM devices WHERE serial_number = ? AND id <> ?");
        $chk->execute([$serial, $device_id]);
        if ($chk->fetchColumn()) $error = 'That serial number is already registered.';
    }

    $image_name = $_POST['existing_image'] ?? null;
    if (!$error && !empty($_FILES['image']['name'])) {
        $allowed = ['jpg','jpeg','png','webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $error = 'Image must be JPG / PNG / WEBP.';
        } elseif ($_FILES['image']['size'] > 3 * 1024 * 1024) {
            $error = 'Image must be under 3 MB.';
        } else {
            $dir = __DIR__ . '/uploads/devices';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $new_name = 'd_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $dir . '/' . $new_name)) {
                $error = 'Could not save image.';
            } else {
                $image_name = $new_name;
            }
        }
    }

    if (!$error) {
        try {
            if ($action === 'add') {
                $pdo->prepare(
                    "INSERT INTO devices (name, description, serial_number, category, brand, model,
                                          purchase_year, branch_id, status, device_condition, image)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)"
                )->execute([
                    $name,
                    $description !== '' ? $description : null,
                    $serial,
                    $category !== '' ? $category : null,
                    $brand !== '' ? $brand : null,
                    $model !== '' ? $model : null,
                    $purchase, $branch_id, $status, $condition, $image_name
                ]);
                $device_id = (int)$pdo->lastInsertId();
                $log_desc = "Added device #{$device_id} \"$name\" (serial $serial)";
                $log_action = 'device_added';
            } else {
                $pdo->prepare(
                    "UPDATE devices SET name=?, description=?, serial_number=?, category=?, brand=?,
                                        model=?, purchase_year=?, branch_id=?, status=?, device_condition=?, image=?
                     WHERE id=?"
                )->execute([
                    $name,
                    $description !== '' ? $description : null,
                    $serial,
                    $category !== '' ? $category : null,
                    $brand !== '' ? $brand : null,
                    $model !== '' ? $model : null,
                    $purchase, $branch_id, $status, $condition, $image_name, $device_id
                ]);
                $log_desc = "Updated device #{$device_id} \"$name\"";
                $log_action = 'device_updated';
            }

            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, ?, ?, 'device', ?)")
                ->execute([$_SESSION['user_id'] ?? null, $log_action, $log_desc, $device_id]);

            header('Location: manage_devices.php?saved=' . ($action === 'add' ? 'added' : 'updated'));
            exit;

        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// ============================================================
// DELETE (POST, admin-only)
// ============================================================
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (current_role() !== 'admin') {
        header('Location: manage_devices.php?error=forbidden');
        exit;
    }
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $device_id = (int)($_POST['device_id'] ?? 0);
    if ($device_id > 0) {
        $chk = $pdo->prepare("SELECT status, name FROM devices WHERE id = ?");
        $chk->execute([$device_id]);
        $d = $chk->fetch();
        if (!$d) {
            header('Location: manage_devices.php');
            exit;
        }
        if ($d['status'] === 'borrowed') {
            header('Location: manage_devices.php?error=borrowed');
            exit;
        }
        $pdo->prepare("DELETE FROM devices WHERE id = ?")->execute([$device_id]);
        $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                       VALUES (?, 'device_deleted', ?, 'device', ?)")
            ->execute([$_SESSION['user_id'] ?? null, "Deleted device #{$device_id} \"{$d['name']}\"", $device_id]);
    }
    header('Location: manage_devices.php?deleted=success');
    exit;
}

// ============================================================
// LOAD FORM DATA
// ============================================================
$all_branches = $pdo->query("SELECT id, name, city FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM devices WHERE category IS NOT NULL AND category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$edit_device = null;
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare(
        "SELECT d.*, b.name AS branch_name
         FROM devices d LEFT JOIN branches b ON b.id = d.branch_id
         WHERE d.id = ?"
    );
    $st->execute([$id]);
    $edit_device = $st->fetch();
    if (!$edit_device) { header('Location: manage_devices.php'); exit; }
}

// ============================================================
// LIST DATA
// ============================================================
if ($action === 'list') {
    $q        = trim($_GET['q'] ?? '');
    $status   = $_GET['status'] ?? 'all';
    $category = $_GET['category'] ?? 'all';
    $branch   = (int)($_GET['branch'] ?? 0);

    $where = []; $args = [];
    if ($q !== '') {
        $where[] = "(d.name LIKE ? OR d.serial_number LIKE ? OR d.brand LIKE ? OR d.model LIKE ?
                     OR d.category LIKE ?)";
        $like = "%$q%";
        array_push($args, $like, $like, $like, $like, $like);
    }
    if ($status !== 'all') {
        $where[] = "d.status = ?";
        $args[]  = $status;
    }
    if ($category !== 'all' && $category !== '') {
        $where[] = "d.category = ?";
        $args[]  = $category;
    }
    if ($branch > 0) {
        $where[] = "d.branch_id = ?";
        $args[]  = $branch;
    }
    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $st = $pdo->prepare(
        "SELECT d.*, b.name AS branch_name
         FROM devices d
         LEFT JOIN branches b ON b.id = d.branch_id
         $where_sql
         ORDER BY d.name, d.serial_number
         LIMIT 500"
    );
    $st->execute($args);
    $devices = $st->fetchAll();
}

include 'header.php';
?>

<style>
    /* =========================================================
       FIX: prevent the wide table from squeezing the sidebar
       ========================================================= */
    .main-content {
        min-width: 0;
        max-width: 100%;
        overflow-x: hidden;
    }
    .main-container {
        min-width: 0;
    }

    /* =========================================================
       DEVICE TABLE & CONTAINER STYLES
       ========================================================= */
    .dev-toolbar {
        display: flex; gap: 10px; flex-wrap: nowrap; align-items: center;
        background: var(--card-bg, #fff); padding: 14px; border-radius: 12px;
        box-shadow: var(--shadow, 0 2px 8px rgba(0,0,0,0.05)); border: 1px solid rgba(13,148,136,0.12);
        margin-bottom: 18px; overflow-x: auto;
    }
    .dev-toolbar input, .dev-toolbar select {
        padding: 9px 12px; border: 1px solid var(--border, #ccc); border-radius: 8px;
        background: var(--card-bg, #fff); color: var(--text, #333); font-size: .9rem;
        white-space: nowrap;
    }
    .dev-toolbar input[type=text] { flex: 1; min-width: 200px; }

    .dev-card {
        background: var(--card-bg, #fff); border-radius: 12px;
        box-shadow: var(--shadow, 0 2px 8px rgba(0,0,0,0.05)); border: 1px solid rgba(13,148,136,0.12);
        overflow: hidden;
        width: 100%;
        max-width: 100%;
    }

    /* Scroll wrapper: horizontal scroll inside the card, nothing escapes */
    .dev-scroll {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        overflow-y: hidden !important;
        display: block !important;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 6px;
    }

    /* Scrollbar styling */
    .dev-scroll::-webkit-scrollbar { height: 8px; }
    .dev-scroll::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
    .dev-scroll::-webkit-scrollbar-thumb { background: #0d9488; border-radius: 4px; }
    .dev-scroll::-webkit-scrollbar-thumb:hover { background: #0f766e; }

    /* Table — full text visible, scrolls when needed */
    table.dev-table {
        width: 100% !important;
        min-width: 1400px !important;   /* wide enough for full text + scrollbar */
        border-collapse: collapse;
        table-layout: auto;             /* let columns size to content */
    }
    table.dev-table th, table.dev-table td {
        padding: 12px 14px;
        vertical-align: middle;
        white-space: nowrap;            /* single-line rows */
        border-bottom: 1px solid rgba(0,0,0,0.05);
        text-align: left;
        /* removed overflow:hidden + text-overflow:ellipsis so text is not truncated */
    }
    table.dev-table thead th {
        background: var(--card-bg, #fff); font-weight: 600; font-size: .82rem;
        color: var(--text-muted, #666); text-transform: uppercase; letter-spacing: .03em;
        border-bottom: 2px solid var(--border, #eee);
    }

    .dev-thumb {
        width: 38px; height: 38px; object-fit: cover; border-radius: 8px;
        background: linear-gradient(135deg, var(--primary, #0d9488), var(--accent, #0f766e));
        display: grid; place-items: center; color: #fff; font-size: 1rem;
        flex-shrink: 0;
    }
    .dev-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }

    /* Badges & action buttons */
    .d-badge { display: inline-block; padding: 3px 9px; border-radius: 8px; font-size: .72rem; font-weight: 600; white-space: nowrap; }
    .d-available   { background: #d1fae5; color: #065f46; }
    .d-borrowed    { background: #dbeafe; color: #1e40af; }
    .d-maintenance { background: #fef3c7; color: #92400e; }
    .d-retired     { background: #e2e8f0; color: #475569; }
    .d-new         { background: #d1fae5; color: #065f46; }
    .d-good        { background: #ccfbf1; color: #115e59; }
    .d-worn        { background: #fef3c7; color: #92400e; }
    .d-damaged     { background: #fee2e2; color: #991b1b; }

    .dev-actions { display: flex; gap: 6px; align-items: center; white-space: nowrap; }
    .dev-actions a, .dev-actions button {
        padding: 6px 12px; border-radius: 6px; font-size: .78rem; font-weight: 600;
        text-decoration: none; display: inline-flex; align-items: center; gap: 4px;
        border: none; cursor: pointer; height: 32px; box-sizing: border-box;
    }
    .d-btn-edit { background: #0d9488; color: #fff !important; }
    .d-btn-edit:hover { background: #0f766e; }
    .d-btn-del  { background: #dc2626; color: #fff !important; }
    .d-btn-del:hover { background: #b91c1c; }

    /* Add/Edit form */
    .dev-form {
        background: var(--card-bg, #fff); border-radius: var(--radius, 12px); padding: 22px;
        box-shadow: var(--shadow, 0 2px 8px rgba(0,0,0,0.05)); border: 1px solid rgba(13,148,136,0.12);
        max-width: 900px; margin: 0 auto;
    }
    .dev-form h3 { margin: 0 0 18px; display: flex; align-items: center; gap: 8px; }
    .dev-form h3 i { color: var(--primary, #0d9488); }
    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width:700px){ .grid2 { grid-template-columns: 1fr; } }
    .grid2 label { display: block; font-weight: 600; font-size: .85rem; margin-bottom: 6px; }
    .grid2 input, .grid2 select, .grid2 textarea {
        width: 100%; padding: 10px 12px; border: 1px solid var(--border, #ccc);
        border-radius: 10px; font-size: .92rem; background: var(--card-bg, #fff); color: var(--text, #333);
        font-family: inherit;
    }
    .grid2 textarea { resize: vertical; }
    .span2 { grid-column: span 2; }
    @media (max-width:700px){ .span2 { grid-column: span 1; } }
</style>

<?php if (isset($_GET['saved'])): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i> Device <?= htmlspecialchars($_GET['saved']) ?> successfully.
    </div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i> Device deleted.
    </div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= $_GET['error'] === 'borrowed'
            ? 'Cannot delete — this device is currently borrowed.'
            : 'You do not have permission for that action.' ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (in_array($action, ['add','edit'], true)): ?>

    <!-- ============ ADD / EDIT FORM ============ -->
    <div class="dev-form">
        <h3>
            <i class="bi bi-<?= $action === 'add' ? 'plus-circle' : 'pencil-square' ?>"></i>
            <?= $action === 'add' ? 'Add Device' : 'Edit Device' ?>
        </h3>

        <form method="POST" action="manage_devices.php?action=<?= $action ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="device_id" value="<?= (int)($edit_device['id'] ?? 0) ?>">
            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($edit_device['image'] ?? '') ?>">

            <div class="grid2">
                <div class="span2">
                    <label>Device Name *</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($edit_device['name'] ?? '') ?>"
                           placeholder="e.g. Lenovo ThinkPad L14">
                </div>
                <div class="span2">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Optional notes about this device"><?= htmlspecialchars($edit_device['description'] ?? '') ?></textarea>
                </div>

                <div>
                    <label>Serial Number *</label>
                    <input type="text" name="serial_number" required value="<?= htmlspecialchars($edit_device['serial_number'] ?? '') ?>">
                </div>
                <div>
                    <label>Category</label>
                    <input type="text" name="category" list="cat-list" value="<?= htmlspecialchars($edit_device['category'] ?? '') ?>"
                           placeholder="e.g. Laptop, Tablet, eReader">
                    <datalist id="cat-list">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div>
                    <label>Brand</label>
                    <input type="text" name="brand" value="<?= htmlspecialchars($edit_device['brand'] ?? '') ?>">
                </div>
                <div>
                    <label>Model</label>
                    <input type="text" name="model" value="<?= htmlspecialchars($edit_device['model'] ?? '') ?>">
                </div>

                <div>
                    <label>Purchase Year</label>
                    <input type="number" name="purchase_year" min="1980" max="2100"
                           value="<?= (int)($edit_device['purchase_year'] ?? 0) ?: '' ?>">
                </div>
                <div>
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="0">— None —</option>
                        <?php foreach ($all_branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= ((int)($edit_device['branch_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['available'=>'Available','borrowed'=>'Borrowed','maintenance'=>'Maintenance','retired'=>'Retired'] as $c=>$l): ?>
                            <option value="<?= $c ?>" <?= ($edit_device['status'] ?? 'available')===$c?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Condition</label>
                    <select name="device_condition">
                        <?php foreach (['new'=>'New','good'=>'Good','worn'=>'Worn','damaged'=>'Damaged'] as $c=>$l): ?>
                            <option value="<?= $c ?>" <?= ($edit_device['device_condition'] ?? 'good')===$c?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="span2">
                    <label>Image (JPG/PNG/WEBP, max 3 MB)</label>
                    <input type="file" name="image" accept="image/*">
                    <?php if (!empty($edit_device['image']) && file_exists(__DIR__ . '/uploads/devices/' . $edit_device['image'])): ?>
                        <div style="margin-top:8px;">
                            <img src="uploads/devices/<?= htmlspecialchars($edit_device['image']) ?>"
                                 style="height:80px;border-radius:6px;" alt="">
                            <span style="color:var(--text-muted);font-size:.82rem;margin-left:8px;">current image</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                <a href="manage_devices.php" class="btn-secondary-custom">← Back</a>
                <button type="submit" class="btn-add">
                    <i class="bi bi-check-circle"></i> <?= $action === 'add' ? 'Create Device' : 'Save Changes' ?>
                </button>
            </div>
        </form>
    </div>

<?php else: ?>

    <!-- ============ LIST ============ -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;"><i class="bi bi-laptop" style="color:var(--primary);"></i> Devices (<?= count($devices) ?>)</h2>
        <a href="manage_devices.php?action=add" class="btn-add" style="text-decoration:none;">
            <i class="bi bi-plus-circle-fill"></i> Add Device
        </a>
    </div>

    <form class="dev-toolbar" method="get" action="manage_devices.php">
        <input type="text" name="q" placeholder="Search name, serial, brand…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">

        <select name="status">
            <?php foreach (['all'=>'All statuses','available'=>'Available','borrowed'=>'Borrowed','maintenance'=>'Maintenance','retired'=>'Retired'] as $c=>$l): ?>
                <option value="<?= $c ?>" <?= (($_GET['status'] ?? 'all')===$c)?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>

        <select name="category">
            <option value="all">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= (($_GET['category'] ?? 'all')===$c)?'selected':'' ?>>
                    <?= htmlspecialchars($c) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="branch">
            <option value="0">All branches</option>
            <?php foreach ($all_branches as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= ((int)($_GET['branch'] ?? 0)===(int)$b['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-add" style="white-space:nowrap;"><i class="bi bi-search"></i> Filter</button>
        <a href="manage_devices.php" class="btn-secondary-custom" style="white-space:nowrap;">Reset</a>
    </form>

    <?php if (!$devices): ?>
        <div class="dev-card" style="text-align:center;padding:50px;">
            <i class="bi bi-laptop" style="font-size:3rem;color:var(--text-muted);"></i>
            <p style="color:var(--text-muted);margin-top:12px;">No devices registered.</p>
            <a href="manage_devices.php?action=add" class="btn-add" style="text-decoration:none;display:inline-block;margin-top:10px;">
                <i class="bi bi-plus-circle"></i> Add the First Device
            </a>
        </div>
    <?php else: ?>
    <div class="dev-card">
        <div class="dev-scroll">
            <table class="dev-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Name</th>
                        <th>Serial</th>
                        <th>Category</th>
                        <th>Brand / Model</th>
                        <th>Branch</th>
                        <th>Year</th>
                        <th>Status</th>
                        <th>Condition</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($devices as $d): ?>
                    <tr>
                        <td>
                            <?php
                                // Pick icon + color by category (fallback to hdd-stack)
                                $cat_lower = strtolower($d['category'] ?? '');
                                $icon_map = [
                                    'laptop'     => ['bi-laptop',       '#0d9488', '#0f766e'],
                                    'tablet'     => ['bi-tablet',       '#657aa8', '#4f6088'],
                                    'ereader'    => ['bi-book',         '#7c3aed', '#5b21b6'],
                                    'e-reader'   => ['bi-book',         '#7c3aed', '#5b21b6'],
                                    'projector'  => ['bi-projector',    '#d97706', '#b45309'],
                                    'webcam'     => ['bi-camera-video', '#0891b2', '#0e7490'],
                                    'kit'        => ['bi-cpu',          '#dc2626', '#b91c1c'],
                                    'phone'      => ['bi-phone',        '#10b981', '#059669'],
                                    'camera'     => ['bi-camera',       '#f59e0b', '#d97706'],
                                    'headphones' => ['bi-headphones',   '#8b5cf6', '#7c3aed'],
                                    'printer'    => ['bi-printer',      '#64748b', '#475569'],
                                    'scanner'    => ['bi-upc-scan',     '#0ea5e9', '#0284c7'],
                                    'mouse'      => ['bi-mouse',        '#94a3b8', '#64748b'],
                                    'keyboard'   => ['bi-keyboard',     '#6b7280', '#4b5563'],
                                ];
                                $ic = $icon_map[$cat_lower] ?? ['bi-hdd-stack', '#0d9488', '#0f766e'];
                            ?>
                            <div class="dev-thumb" style="background:linear-gradient(135deg, <?= $ic[1] ?>, <?= $ic[2] ?>);">
                                <?php if (!empty($d['image']) && file_exists(__DIR__ . '/uploads/devices/' . $d['image'])): ?>
                                    <img src="uploads/devices/<?= htmlspecialchars($d['image']) ?>" alt="">
                                <?php else: ?>
                                    <i class="bi <?= $ic[0] ?>"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <a href="manage_devices.php?action=edit&id=<?= (int)$d['id'] ?>"
                               style="color:var(--primary-dark);text-decoration:none;font-weight:600;">
                                <?= htmlspecialchars($d['name']) ?>
                            </a>
                        </td>
                        <td><code style="font-size:.78rem;"><?= htmlspecialchars($d['serial_number']) ?></code></td>
                        <td><?= htmlspecialchars($d['category'] ?? '—') ?></td>
                        <td>
                            <?= htmlspecialchars($d['brand'] ?? '—') ?>
                            <?= $d['model'] ? ' · ' . htmlspecialchars($d['model']) : '' ?>
                        </td>
                        <td><?= htmlspecialchars($d['branch_name'] ?? '—') ?></td>
                        <td><?= (int)$d['purchase_year'] ?: '—' ?></td>
                        <td><span class="d-badge d-<?= htmlspecialchars($d['status']) ?>"><?= htmlspecialchars($d['status']) ?></span></td>
                        <td><span class="d-badge d-<?= htmlspecialchars($d['device_condition']) ?>"><?= htmlspecialchars($d['device_condition']) ?></span></td>
                        <td>
                            <div class="dev-actions">
                                <a href="manage_devices.php?action=edit&id=<?= (int)$d['id'] ?>" class="d-btn-edit" title="Edit">
                                    <i class="bi bi-pencil">Edit</i>
                                </a>
                                <?php if (current_role() === 'admin'): ?>
                                    <form method="POST" action="manage_devices.php?action=delete"
                                          onsubmit="return confirm('Delete this device? Cannot be undone.');" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>">
                                        <button type="submit" class="d-btn-del" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p style="color:var(--text-muted);font-size:.82rem;margin-top:10px;">
        Showing <?= count($devices) ?> device(s).
    </p>
    <?php endif; ?>

<?php endif; ?>

<?php include 'footer.php'; ?>
