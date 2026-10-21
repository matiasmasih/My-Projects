<?php
// admin/department_detail.php — Department details + employee list

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: ' . SITE_URL . 'admin/departments.php');
    exit;
}

// Load department
$dept = null;
try {
    $stmt = $pdo->prepare("
        SELECT d.*, b.name AS branch_name,
               e.first_name AS mgr_first, e.last_name AS mgr_last, e.email AS mgr_email,
               e.employee_code AS mgr_code
        FROM departments d
        LEFT JOIN branches b ON b.id = d.branch_id
        LEFT JOIN employees e ON e.id = d.manager_id
        WHERE d.id = ? LIMIT 1
    ");
    $stmt->execute([$id]);
    $dept = $stmt->fetch();
    if (!$dept) {
        header('Location: ' . SITE_URL . 'admin/departments.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: ' . SITE_URL . 'admin/departments.php');
    exit;
}

// Load employees in this department
$employees = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM employees 
        WHERE department_id = ? 
        ORDER BY first_name
    ");
    $stmt->execute([$id]);
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {}

// Attendance stats for this department (this month)
$stats = ['present' => 0, 'late' => 0, 'absent' => 0, 'total_hours' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent,
            SUM(a.total_minutes) AS total_minutes
        FROM attendance a
        JOIN employees e ON e.id = a.employee_id
        WHERE e.department_id = ? 
          AND MONTH(a.date) = MONTH(CURRENT_DATE())
          AND YEAR(a.date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $stats['present'] = (int)($row['present'] ?? 0);
    $stats['late']    = (int)($row['late'] ?? 0);
    $stats['absent']  = (int)($row['absent'] ?? 0);
    $stats['total_hours'] = round(((int)($row['total_minutes'] ?? 0)) / 60, 1);
} catch (PDOException $e) {}

$page_title = 'Department: ' . $dept['name'];
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Breadcrumb + actions -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <a href="departments.php" class="btn btn-outline">
        <i class="fas fa-arrow-left"></i> Back to Departments
    </a>
    <a href="departments.php?edit=<?= (int)$dept['id'] ?>" class="btn btn-primary">
        <i class="fas fa-pen"></i> Edit Department
    </a>
</div>

<!-- Department header card -->
<div class="admin-panel" style="border-top:4px solid <?= e($dept['color']) ?>;">
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        <div style="width:72px;height:72px;border-radius:16px;background:<?= e($dept['color']) ?>20;display:grid;place-items:center;color:<?= e($dept['color']) ?>;font-size:2rem;flex-shrink:0;">
            <i class="fas fa-sitemap"></i>
        </div>
        <div style="flex:1;min-width:240px;">
            <h1 style="font-size:1.6rem;margin-bottom:6px;"><?= e($dept['name']) ?></h1>
            <div style="font-size:0.9rem;color:var(--text-muted);">
                <i class="fas fa-building"></i> <?= e($dept['branch_name'] ?? 'All branches') ?>
            </div>
            <?php if (!empty($dept['description'])): ?>
                <p style="font-size:0.92rem;color:var(--text-muted);margin-top:10px;max-width:640px;">
                    <?= e($dept['description']) ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($dept['mgr_first']): ?>
        <div style="margin-top:20px;padding:14px 18px;background:var(--bg-alt);border-radius:10px;display:flex;align-items:center;gap:14px;">
            <div class="user-avatar-initials" style="width:40px;height:40px;font-size:0.95rem;">
                <?= e(strtoupper(substr($dept['mgr_first'], 0, 1) . substr($dept['mgr_last'], 0, 1))) ?>
            </div>
            <div>
                <div style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Department Manager</div>
                <div style="font-weight:700;color:var(--text);"><?= e($dept['mgr_first'] . ' ' . $dept['mgr_last']) ?></div>
                <div style="font-size:0.82rem;color:var(--text-muted);">
                    <?= e($dept['mgr_code']) ?> · <?= e($dept['mgr_email']) ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div style="margin-top:20px;padding:14px 18px;background:var(--bg-alt);border-radius:10px;color:var(--text-muted);font-size:0.9rem;">
            <i class="fas fa-info-circle"></i> No manager assigned yet.
            <a href="departments.php?edit=<?= (int)$dept['id'] ?>" style="color:var(--primary);font-weight:600;margin-left:6px;">Assign one →</a>
        </div>
    <?php endif; ?>
</div>

<!-- KPI cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-value"><?= count($employees) ?></div>
            <div class="stat-label">Total Employees</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-value"><?= $stats['present'] ?></div>
            <div class="stat-label">Present (This Month)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value"><?= $stats['late'] ?></div>
            <div class="stat-label">Late (This Month)</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon sky"><i class="fas fa-hourglass-half"></i></div>
        <div>
            <div class="stat-value"><?= $stats['total_hours'] ?>h</div>
            <div class="stat-label">Hours Worked</div>
        </div>
    </div>
</div>

<!-- Employee list -->
<div class="admin-panel">
    <h3><i class="fas fa-users"></i> Employees in this Department (<?= count($employees) ?>)</h3>

    <?php if (empty($employees)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
            <i class="fas fa-user-slash" style="font-size:3rem;opacity:0.4;display:block;margin-bottom:14px;"></i>
            <p style="font-size:1.05rem;">No employees assigned to this department yet.</p>
            <a href="employee_edit.php" class="btn btn-primary" style="margin-top:16px;">
                <i class="fas fa-plus"></i> Add Employee
            </a>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Code</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Position</th>
                        <th>Hire Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="user-avatar-initials" style="width:32px;height:32px;font-size:0.72rem;">
                                        <?= e(strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1))) ?>
                                    </div>
                                    <div>
                                        <strong><?= e($emp['first_name'] . ' ' . $emp['last_name']) ?></strong>
                                        <?php if ($emp['id'] == $dept['manager_id']): ?>
                                            <span class="badge badge-primary" style="margin-left:6px;">Manager</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($emp['employee_code']) ?></td>
                            <td><?= e($emp['email']) ?></td>
                            <td><?= e($emp['phone'] ?: '—') ?></td>
                            <td><?= e($emp['position'] ?: '—') ?></td>
                            <td><?= $emp['hire_date'] ? e(date('M j, Y', strtotime($emp['hire_date']))) : '—' ?></td>
                            <td>
                                <?php if ($emp['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="employee_edit.php?id=<?= (int)$emp['id'] ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-pen"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
