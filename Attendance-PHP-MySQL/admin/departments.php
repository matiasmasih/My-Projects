<?php
// admin/departments.php — Departments management (modern, card-based)

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$flash = '';
$errors = [];

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $branch_id   = ($_POST['branch_id'] ?? '') !== '' ? (int)$_POST['branch_id'] : null;
        $manager_id  = ($_POST['manager_id'] ?? '') !== '' ? (int)$_POST['manager_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $color       = trim($_POST['color'] ?? '#3b82f6');

        if ($name === '') $errors[] = 'Department name is required.';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#3b82f6';

        if (empty($errors)) {
            try {
                if ($id > 0) {
                    $pdo->prepare("
                        UPDATE departments SET name = ?, branch_id = ?, manager_id = ?, description = ?, color = ?
                        WHERE id = ?
                    ")->execute([$name, $branch_id, $manager_id, $description, $color, $id]);
                    $flash = 'Department updated.';
                } else {
                    $pdo->prepare("
                        INSERT INTO departments (name, branch_id, manager_id, description, color)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([$name, $branch_id, $manager_id, $description, $color]);
                    $flash = 'Department created.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Could not save: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
            $flash = 'Department deleted.';
        } catch (PDOException $e) {
            $flash = 'Cannot delete — department has employees.';
        }
    }
}

// Edit target
$edit_id = (int)($_GET['edit'] ?? 0);
$edit_dept = [
    'id' => 0, 'name' => '', 'branch_id' => null,
    'manager_id' => null, 'description' => '', 'color' => '#3b82f6'
];
if ($edit_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ? LIMIT 1");
        $stmt->execute([$edit_id]);
        $row = $stmt->fetch();
        if ($row) $edit_dept = $row;
    } catch (PDOException $e) {}
}

// Load departments with manager + employee count
$departments = [];
try {
    $departments = $pdo->query("
        SELECT d.*, b.name AS branch_name,
               e.first_name AS mgr_first, e.last_name AS mgr_last,
               (SELECT COUNT(*) FROM employees WHERE department_id = d.id) AS emp_count
        FROM departments d
        LEFT JOIN branches b ON b.id = d.branch_id
        LEFT JOIN employees e ON e.id = d.manager_id
        ORDER BY d.name
    ")->fetchAll();
} catch (PDOException $e) {}

$branches = [];
try {
    $branches = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();
} catch (PDOException $e) {}

$employees = [];
try {
    $employees = $pdo->query("
        SELECT id, first_name, last_name, employee_code
        FROM employees WHERE is_active = 1
        ORDER BY first_name
    ")->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Departments';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Form (Add / Edit) -->
<div class="admin-panel">
    <h3>
        <i class="fas fa-sitemap"></i>
        <?= $edit_dept['id'] > 0 ? 'Edit Department' : 'Add New Department' ?>
    </h3>

    <?php if ($flash): ?>
        <div class="flash flash-success" style="margin-bottom:16px;">
            <i class="fas fa-check-circle"></i> <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="flash flash-error" style="margin-bottom:16px;display:block;">
            <?php foreach ($errors as $err): ?><div><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" style="padding:20px;background:var(--bg-alt);border-radius:12px;">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$edit_dept['id'] ?>">

        <div style="display:grid;grid-template-columns:2fr 2fr 1fr;gap:16px;">
            <div>
                <label>Department Name *</label>
                <input type="text" name="name" required value="<?= e($edit_dept['name']) ?>" placeholder="Sales, IT, HR...">
            </div>
            <div>
                <label>Branch</label>
                <select name="branch_id">
                    <option value="">— All branches —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= $b['id'] == $edit_dept['branch_id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Color</label>
                <input type="color" name="color" value="<?= e($edit_dept['color']) ?>" style="height:44px;cursor:pointer;">
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;margin-top:16px;">
            <div>
                <label>Department Manager</label>
                <select name="manager_id">
                    <option value="">— No manager —</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= (int)$emp['id'] ?>" <?= $emp['id'] == $edit_dept['manager_id'] ? 'selected' : '' ?>>
                            <?= e($emp['first_name'] . ' ' . $emp['last_name']) ?> (<?= e($emp['employee_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Description</label>
                <input type="text" name="description" value="<?= e($edit_dept['description']) ?>" placeholder="Short description of this department...">
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $edit_dept['id'] > 0 ? 'Update Department' : 'Create Department' ?>
            </button>
            <?php if ($edit_dept['id'] > 0): ?>
                <a href="departments.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Department cards -->
<div class="admin-panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Departments (<?= count($departments) ?>)</h3>
        <input type="text" id="searchBox" placeholder="🔍 Search departments..." style="max-width:280px;">
    </div>

    <?php if (empty($departments)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
            <i class="fas fa-sitemap" style="font-size:3rem;opacity:0.4;display:block;margin-bottom:14px;"></i>
            <p>No departments yet. Create your first department above.</p>
        </div>
    <?php else: ?>
        <div id="deptGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;">

            <?php foreach ($departments as $d): ?>
                <div class="dept-card" data-name="<?= e(strtolower($d['name'])) ?>"
                     style="background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:24px;transition:all 0.2s;position:relative;overflow:hidden;">

                    <!-- Color bar -->
                    <div style="position:absolute;top:0;left:0;right:0;height:4px;background:<?= e($d['color']) ?>;"></div>

                    <!-- Icon + Name -->
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                        <div style="width:48px;height:48px;border-radius:12px;background:<?= e($d['color']) ?>20;display:grid;place-items:center;color:<?= e($d['color']) ?>;font-size:1.4rem;flex-shrink:0;">
                            <i class="fas fa-sitemap"></i>
                        </div>
                        <div style="flex:1;">
                            <h4 style="font-size:1.1rem;margin:0;"><?= e($d['name']) ?></h4>
                            <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
                                <i class="fas fa-building"></i> <?= e($d['branch_name'] ?? 'All branches') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($d['description'])): ?>
                        <p style="font-size:0.88rem;color:var(--text-muted);margin-bottom:14px;line-height:1.5;">
                            <?= e($d['description']) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Manager -->
                    <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:8px;">
                        <i class="fas fa-user-tie"></i>
                        <?php if ($d['mgr_first']): ?>
                            Manager: <strong style="color:var(--text);"><?= e($d['mgr_first'] . ' ' . $d['mgr_last']) ?></strong>
                        <?php else: ?>
                            <em>No manager assigned</em>
                        <?php endif; ?>
                    </div>

                    <!-- Employees count -->
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
                        <span class="badge badge-primary">
                            <i class="fas fa-users"></i> <?= (int)$d['emp_count'] ?> employees
                        </span>
                        <div style="display:flex;gap:6px;">
                            <a href="department_detail.php?id=<?= (int)$d['id'] ?>"
                               class="btn btn-outline btn-sm" title="View employees">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="departments.php?edit=<?= (int)$d['id'] ?>"
                               class="btn btn-outline btn-sm" title="Edit">
                                <i class="fas fa-pen"></i>
                            </a>
                            <form method="post" style="display:inline;"
                                  onsubmit="return confirm('Delete department <?= e($d['name']) ?>?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>
</div>

<style>
.dept-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    border-color: var(--primary);
}
</style>

<script>
// Live search
document.getElementById('searchBox')?.addEventListener('input', function(e) {
    var q = e.target.value.toLowerCase();
    document.querySelectorAll('.dept-card').forEach(function(card) {
        var name = card.getAttribute('data-name') || '';
        card.style.display = name.includes(q) ? '' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
