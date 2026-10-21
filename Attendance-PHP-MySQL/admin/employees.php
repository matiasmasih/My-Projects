<?php
// admin/employees.php — Modern employee list

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$flash = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
            $flash = 'Employee deleted.';
        } catch (PDOException $e) {
            $flash = 'Cannot delete — employee has attendance records.';
        }
    }
}

// Filters
$filter_branch = (int)($_GET['branch'] ?? 0);
$filter_dept   = (int)($_GET['dept'] ?? 0);
$filter_status = $_GET['status'] ?? '';

$where = "WHERE 1=1";
$params = [];
if ($filter_branch > 0) { $where .= " AND e.branch_id = ?"; $params[] = $filter_branch; }
if ($filter_dept > 0)   { $where .= " AND e.department_id = ?"; $params[] = $filter_dept; }
if ($filter_status === 'active')   { $where .= " AND e.is_active = 1"; }
if ($filter_status === 'inactive') { $where .= " AND e.is_active = 0"; }

// Load employees
$employees = [];
try {
    $stmt = $pdo->prepare("
        SELECT e.*, b.name AS branch_name, d.name AS department_name, s.name AS shift_name
        FROM employees e
        LEFT JOIN branches b ON b.id = e.branch_id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN shifts s ON s.id = e.shift_id
        $where
        ORDER BY e.id DESC
    ");
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {}

// Filters data
$branches = [];
$departments = [];
try {
    $branches = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();
    $departments = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name")->fetchAll();
} catch (PDOException $e) {}

// Quick stats
$stats = ['total' => count($employees), 'active' => 0];
foreach ($employees as $emp) if ($emp['is_active']) $stats['active']++;

$page_title = 'Employees';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Header banner -->
<div class="admin-panel" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border:none;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <h2 style="color:#fff;font-size:1.6rem;margin-bottom:4px;">
                <i class="fas fa-users"></i> Employees
            </h2>
            <p style="color:rgba(255,255,255,0.85);font-size:0.92rem;">
                <?= $stats['total'] ?> total · <?= $stats['active'] ?> active
            </p>
        </div>
        <a href="employee_edit.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
            <i class="fas fa-plus"></i> Add Employee
        </a>
    </div>
</div>

<!-- Filters + search -->
<div class="admin-panel" style="margin-top:24px;">
    <form method="get" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:flex-end;">
        <div>
            <label style="font-size:0.8rem;margin-bottom:6px;display:block;color:var(--text-muted);">Search</label>
            <input type="text" id="searchBox" placeholder="Search by name, code, email..." value="">
        </div>
        <div>
            <label style="font-size:0.8rem;margin-bottom:6px;display:block;color:var(--text-muted);">Branch</label>
            <select name="branch">
                <option value="0">All branches</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= $filter_branch == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.8rem;margin-bottom:6px;display:block;color:var(--text-muted);">Department</label>
            <select name="dept">
                <option value="0">All departments</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $filter_dept == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:0.8rem;margin-bottom:6px;display:block;color:var(--text-muted);">Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
    </form>
</div>

<?php if ($flash): ?>
    <div class="flash flash-info" style="margin-top:16px;">
        <i class="fas fa-info-circle"></i> <?= e($flash) ?>
    </div>
<?php endif; ?>

<!-- Employee grid -->
<div style="margin-top:24px;">
    <?php if (empty($employees)): ?>
        <div class="admin-panel" style="text-align:center;padding:80px 20px;color:var(--text-muted);">
            <i class="fas fa-user-plus" style="font-size:4rem;opacity:0.3;display:block;margin-bottom:16px;"></i>
            <p style="font-size:1.1rem;margin-bottom:20px;">No employees found.</p>
            <a href="employee_edit.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add First Employee
            </a>
        </div>
    <?php else: ?>
        <div id="empGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;">
            <?php foreach ($employees as $emp): ?>
                <?php
                    $initials = strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1));
                    $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
                    $color = $colors[$emp['id'] % count($colors)];
                    $search_text = strtolower($emp['first_name'] . ' ' . $emp['last_name'] . ' ' . $emp['employee_code'] . ' ' . $emp['email']);
                ?>
                <div class="emp-card" data-search="<?= e($search_text) ?>"
                     style="background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:24px;transition:all 0.2s;position:relative;">

                    <!-- Header: avatar + name + status -->
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;">
                        
                        <!-- FIXED AVATAR WITH PHOTO SUPPORT -->
                        <div class="emp-avatar-circle" style="background:<?= $color ?>;">
                            <?php if (!empty($emp['photo']) && file_exists(__DIR__ . '/../uploads/' . $emp['photo'])): ?>
                                <img src="<?= SITE_URL ?>uploads/<?= e($emp['photo']) ?>" alt="<?= e($emp['first_name']) ?>">
                            <?php else: ?>
                                <?= e($initials) ?>
                            <?php endif; ?>
                        </div>

                        <div style="flex:1;min-width:0;">
                            <h4 style="font-size:1.05rem;margin-bottom:2px;"><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></h4>
                            <div style="font-size:0.78rem;color:var(--text-muted);"><?= e($emp['employee_code']) ?></div>
                        </div>
                        <?php if ($emp['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactive</span>
                        <?php endif; ?>
                    </div>

                    <!-- Position -->
                    <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px;">
                        <i class="fas fa-briefcase"></i> <?= e($emp['position'] ?: 'No position') ?>
                    </div>

                    <!-- Info rows -->
                    <div style="display:flex;flex-direction:column;gap:10px;padding:16px;background:var(--bg-alt);border-radius:10px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;gap:10px;font-size:0.85rem;">
                            <i class="fas fa-envelope" style="color:var(--primary);width:16px;"></i>
                            <span style="color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($emp['email']) ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;font-size:0.85rem;">
                            <i class="fas fa-building" style="color:var(--primary);width:16px;"></i>
                            <span><?= e($emp['branch_name'] ?? 'No branch') ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;font-size:0.85rem;">
                            <i class="fas fa-sitemap" style="color:var(--primary);width:16px;"></i>
                            <span><?= e($emp['department_name'] ?? 'No department') ?></span>
                        </div>
                        <?php if ($emp['shift_name']): ?>
                            <div style="display:flex;align-items:center;gap:10px;font-size:0.85rem;">
                                <i class="fas fa-clock" style="color:var(--primary);width:16px;"></i>
                                <span><?= e($emp['shift_name']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div style="display:flex;gap:8px;">
                        <a href="employee_edit.php?id=<?= (int)$emp['id'] ?>" class="btn btn-outline btn-sm" style="flex:1;">
                            <i class="fas fa-pen"></i> Edit
                        </a>
                        <form method="post" style="display:inline-flex;flex:1;" onsubmit="return confirm('Delete <?= e($emp['first_name'] . ' ' . $emp['last_name']) ?>?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$emp['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.emp-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    border-color: var(--primary);
}

/* FIXED AVATAR CSS */
.emp-avatar-circle {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 1.2rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    overflow: hidden;
}

.emp-avatar-circle img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>

<script>
// Live search
document.getElementById('searchBox')?.addEventListener('input', function(e) {
    var q = e.target.value.toLowerCase();
    document.querySelectorAll('.emp-card').forEach(function(card) {
        var text = card.getAttribute('data-search') || '';
        card.style.display = text.includes(q) ? '' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
