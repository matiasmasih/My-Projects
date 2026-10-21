<?php
// admin/branch_edit.php — Add or edit branch

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$is_new = ($id === 0);

$flash = '';
$errors = [];

$branch = [
    'name'     => '',
    'address'  => '',
    'city'     => '',
    'phone'    => '',
    'timezone' => 'Europe/Helsinki',
    'is_active'=> 1,
];

if (!$is_new) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            header('Location: ' . SITE_URL . 'admin/branches.php');
            exit;
        }
        $branch = $row;
    } catch (PDOException $e) {
        header('Location: ' . SITE_URL . 'admin/branches.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch['name']     = trim($_POST['name'] ?? '');
    $branch['address']  = trim($_POST['address'] ?? '');
    $branch['city']     = trim($_POST['city'] ?? '');
    $branch['phone']    = trim($_POST['phone'] ?? '');
    $branch['timezone'] = trim($_POST['timezone'] ?? 'Europe/Helsinki');
    $branch['is_active']= isset($_POST['is_active']) ? 1 : 0;

    if ($branch['name'] === '') $errors[] = 'Branch name is required.';
    if ($branch['city'] === '') $errors[] = 'City is required.';

    if (empty($errors)) {
        try {
            if ($is_new) {
                $stmt = $pdo->prepare("
                    INSERT INTO branches (name, address, city, phone, timezone, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $branch['name'], $branch['address'], $branch['city'],
                    $branch['phone'], $branch['timezone'], $branch['is_active']
                ]);
                header('Location: ' . SITE_URL . 'admin/branches.php');
                exit;
            } else {
                $stmt = $pdo->prepare("
                    UPDATE branches SET
                        name = ?, address = ?, city = ?, phone = ?, timezone = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $branch['name'], $branch['address'], $branch['city'],
                    $branch['phone'], $branch['timezone'], $branch['is_active'], $id
                ]);
                $flash = 'Branch saved.';
            }
        } catch (PDOException $e) {
            error_log('branch_edit: ' . $e->getMessage());
            $errors[] = 'Could not save branch.';
        }
    }
}

$page_title = $is_new ? 'New Branch' : 'Edit Branch';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="admin-panel">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h3 style="margin:0;">
            <i class="fas fa-building"></i> <?= $is_new ? 'Add New Branch' : 'Edit Branch' ?>
        </h3>
        <a href="branches.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="flash flash-success" style="margin-bottom:16px;">
            <i class="fas fa-check-circle"></i> <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="flash flash-error" style="display:block;margin-bottom:16px;">
            <?php foreach ($errors as $err): ?>
                <div style="margin:4px 0;"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">

        <div class="grid-2">
            <div>
                <label for="name">Branch Name *</label>
                <input type="text" id="name" name="name" required
                       value="<?= e($branch['name']) ?>" placeholder="Helsinki Main Office">
            </div>
            <div>
                <label for="city">City *</label>
                <input type="text" id="city" name="city" required
                       value="<?= e($branch['city']) ?>" placeholder="Helsinki">
            </div>
        </div>

        <div style="margin-top:16px;">
            <label for="address">Address</label>
            <input type="text" id="address" name="address"
                   value="<?= e($branch['address']) ?>" placeholder="Mannerheimintie 1">
        </div>

        <div class="grid-2" style="margin-top:16px;">
            <div>
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone"
                       value="<?= e($branch['phone']) ?>" placeholder="+358 40 123 4567">
            </div>
            <div>
                <label for="timezone">Timezone</label>
                <input type="text" id="timezone" name="timezone"
                       value="<?= e($branch['timezone']) ?>">
            </div>
        </div>

        <div style="margin-top:16px;">
            <label>Status</label>
            <label style="display:flex;gap:10px;align-items:center;cursor:pointer;padding:12px;background:var(--bg-alt);border-radius:8px;">
                <input type="checkbox" name="is_active" value="1" <?= $branch['is_active'] ? 'checked' : '' ?> style="width:auto;accent-color:var(--primary);">
                <span>Active (branch is operational)</span>
            </label>
        </div>

        <div style="display:flex;gap:10px;margin-top:28px;">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> <?= $is_new ? 'Create Branch' : 'Save Changes' ?>
            </button>
            <a href="branches.php" class="btn btn-outline btn-lg">Cancel</a>
        </div>

    </form>

</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
