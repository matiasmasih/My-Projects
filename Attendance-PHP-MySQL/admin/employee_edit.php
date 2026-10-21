<?php
// admin/employee_edit.php — Modern add/edit employee form

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$is_new = ($id === 0);

$flash = '';
$errors = [];

// Defaults
$employee = [
    'employee_code' => '',
    'first_name'    => '',
    'last_name'     => '',
    'email'         => '',
    'phone'         => '',
    'position'      => '',
    'branch_id'     => '',
    'department_id' => '',
    'shift_id'      => '',
    'hire_date'     => '',
    'photo'         => '',
    'is_active'     => 1,
];

// Load existing
if (!$is_new) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            header('Location: ' . SITE_URL . 'admin/employees.php');
            exit;
        }
        $employee = $row;
    } catch (PDOException $e) {
        header('Location: ' . SITE_URL . 'admin/employees.php');
        exit;
    }
}

// Load dropdowns
$branches = [];
$departments = [];
$shifts = [];
try {
    $branches = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();
    $departments = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll();
    $shifts = $pdo->query("SELECT id, name, start_time, end_time FROM shifts WHERE is_active = 1 ORDER BY start_time")->fetchAll();
} catch (PDOException $e) {}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee['employee_code'] = trim($_POST['employee_code'] ?? '');
    $employee['first_name']    = trim($_POST['first_name'] ?? '');
    $employee['last_name']     = trim($_POST['last_name'] ?? '');
    $employee['email']         = trim($_POST['email'] ?? '');
    $employee['phone']         = trim($_POST['phone'] ?? '');
    $employee['position']      = trim($_POST['position'] ?? '');
    $employee['branch_id']     = ($_POST['branch_id'] ?? '') !== '' ? (int)$_POST['branch_id'] : null;
    $employee['department_id'] = ($_POST['department_id'] ?? '') !== '' ? (int)$_POST['department_id'] : null;
    $employee['shift_id']      = ($_POST['shift_id'] ?? '') !== '' ? (int)$_POST['shift_id'] : null;
    $employee['hire_date']     = trim($_POST['hire_date'] ?? '');
    $employee['photo']         = trim($_POST['photo'] ?? '');
    $employee['is_active']     = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if ($employee['employee_code'] === '') $errors[] = 'Employee code is required.';
    if ($employee['first_name'] === '')    $errors[] = 'First name is required.';
    if ($employee['last_name'] === '')     $errors[] = 'Last name is required.';
    if (!filter_var($employee['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

    // Uniqueness
    if (empty($errors)) {
        try {
            $sql = "SELECT id FROM employees WHERE (employee_code = ? OR email = ?)";
            $params = [$employee['employee_code'], $employee['email']];
            if (!$is_new) { $sql .= " AND id != ?"; $params[] = $id; }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) $errors[] = 'Employee code or email already exists.';
        } catch (PDOException $e) {}
    }

    // Save
    if (empty($errors)) {
        try {
            if ($is_new) {
                $stmt = $pdo->prepare("
                    INSERT INTO employees 
                    (employee_code, first_name, last_name, email, phone, position, 
                     branch_id, department_id, shift_id, hire_date, photo, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $employee['employee_code'], $employee['first_name'], $employee['last_name'],
                    $employee['email'], $employee['phone'], $employee['position'],
                    $employee['branch_id'], $employee['department_id'], $employee['shift_id'],
                    $employee['hire_date'] ?: null, $employee['photo'], $employee['is_active']
                ]);
                header('Location: ' . SITE_URL . 'admin/employees.php');
                exit;
            } else {
                $stmt = $pdo->prepare("
                    UPDATE employees SET
                        employee_code = ?, first_name = ?, last_name = ?, email = ?,
                        phone = ?, position = ?, branch_id = ?, department_id = ?,
                        shift_id = ?, hire_date = ?, photo = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $employee['employee_code'], $employee['first_name'], $employee['last_name'],
                    $employee['email'], $employee['phone'], $employee['position'],
                    $employee['branch_id'], $employee['department_id'], $employee['shift_id'],
                    $employee['hire_date'] ?: null, $employee['photo'], $employee['is_active'], $id
                ]);
                $flash = 'Employee saved successfully.';
            }
        } catch (PDOException $e) {
            error_log('employee_edit: ' . $e->getMessage());
            $errors[] = 'Could not save: ' . $e->getMessage();
        }
    }
}

$page_title = $is_new ? 'New Employee' : 'Edit Employee';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Gradient header -->
<div class="admin-panel" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border:none;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <h2 style="color:#fff;font-size:1.5rem;margin-bottom:4px;">
                <i class="fas fa-user<?= $is_new ? '-plus' : '-pen' ?>"></i>
                <?= $is_new ? 'Add New Employee' : 'Edit Employee' ?>
            </h2>
            <p style="color:rgba(255,255,255,0.85);font-size:0.9rem;">
                <?= $is_new ? 'Create a new employee record' : e($employee['first_name'] . ' ' . $employee['last_name']) ?>
            </p>
        </div>
        <a href="employees.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if ($flash): ?>
    <div class="flash flash-success" style="margin-top:20px;">
        <i class="fas fa-check-circle"></i> <?= e($flash) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="flash flash-error" style="margin-top:20px;display:block;">
        <?php foreach ($errors as $err): ?>
            <div style="margin:4px 0;"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" style="margin-top:20px;">

    <!-- SECTION 1: Basic Info -->
    <div class="admin-panel">
        <h3><i class="fas fa-info-circle"></i> Basic Information</h3>

        <div class="grid-2" style="margin-top:16px;">
            <div>
                <label>Employee Code * <span style="color:var(--text-muted);font-weight:400;font-size:0.8rem;">(e.g. EMP001)</span></label>
                <input type="text" name="employee_code" required 
                       value="<?= e($employee['employee_code']) ?>"
                       placeholder="EMP001" pattern="[A-Z0-9_-]+" style="text-transform:uppercase;">
            </div>
            <div>
                <label>Position</label>
                <input type="text" name="position" 
                       value="<?= e($employee['position']) ?>"
                       placeholder="Sales Manager">
            </div>
        </div>

        <div class="grid-2" style="margin-top:16px;">
            <div>
                <label>First Name *</label>
                <input type="text" name="first_name" required 
                       value="<?= e($employee['first_name']) ?>">
            </div>
            <div>
                <label>Last Name *</label>
                <input type="text" name="last_name" required 
                       value="<?= e($employee['last_name']) ?>">
            </div>
        </div>

        <div class="grid-2" style="margin-top:16px;">
            <div>
                <label>Email *</label>
                <input type="email" name="email" required 
                       value="<?= e($employee['email']) ?>">
            </div>
            <div>
                <label>Phone</label>
                <input type="tel" name="phone" 
                       value="<?= e($employee['phone']) ?>"
                       placeholder="+358 40 123 4567">
            </div>
        </div>
    </div>

    <!-- SECTION 2: Assignment -->
    <div class="admin-panel" style="margin-top:20px;">
        <h3><i class="fas fa-building"></i> Assignment</h3>

        <div class="grid-2" style="margin-top:16px;">
            <div>
                <label>Branch</label>
                <select name="branch_id">
                    <option value="">— No branch —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= $b['id'] == $employee['branch_id'] ? 'selected' : '' ?>>
                            <?= e($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Department</label>
                <select name="department_id">
                    <option value="">— No department —</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= $d['id'] == $employee['department_id'] ? 'selected' : '' ?>>
                            <?= e($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid-2" style="margin-top:16px;">
            <div>
                <label>Shift</label>
                <select name="shift_id">
                    <option value="">— No shift —</option>
                    <?php foreach ($shifts as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $s['id'] == $employee['shift_id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?> (<?= e(substr($s['start_time'], 0, 5)) ?>-<?= e(substr($s['end_time'], 0, 5)) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size:0.78rem;color:var(--text-muted);margin-top:6px;">
                    <i class="fas fa-info-circle"></i> Required for auto-absence tracking
                </div>
            </div>
            <div>
                <label>Hire Date</label>
                <input type="date" name="hire_date" 
                       value="<?= e($employee['hire_date']) ?>">
            </div>
        </div>
    </div>

    <!-- SECTION 3: Additional -->
    <div class="admin-panel" style="margin-top:20px;">
        <h3><i class="fas fa-image"></i> Additional</h3>

        <div style="margin-top:16px;">
            <label>Photo URL (optional)</label>
            <input type="text" name="photo" 
                   value="<?= e($employee['photo']) ?>"
                   placeholder="assets/images/employees/ahmed.jpg">
            <div style="font-size:0.78rem;color:var(--text-muted);margin-top:6px;">
                Leave empty to use auto-generated initials avatar
            </div>
        </div>

        <div style="margin-top:20px;">
            <label style="display:flex;gap:10px;align-items:center;cursor:pointer;padding:14px;background:var(--bg-alt);border-radius:8px;">
                <input type="checkbox" name="is_active" value="1" 
                       <?= $employee['is_active'] ? 'checked' : '' ?>
                       style="width:auto;accent-color:var(--primary);">
                <div>
                    <div style="font-weight:600;">Active Employee</div>
                    <div style="font-size:0.82rem;color:var(--text-muted);">
                        Active employees can clock in/out and appear in reports
                    </div>
                </div>
            </label>
        </div>
    </div>

    <!-- Action buttons -->
    <div style="display:flex;gap:12px;margin-top:24px;">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save"></i> <?= $is_new ? 'Create Employee' : 'Save Changes' ?>
        </button>
        <a href="employees.php" class="btn btn-outline btn-lg">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>

</form>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
