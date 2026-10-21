<?php
// admin/branches.php — Modern branch list

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
            $pdo->prepare("DELETE FROM branches WHERE id = ?")->execute([$id]);
            $flash = 'Branch deleted.';
        } catch (PDOException $e) {
            $flash = 'Cannot delete — branch has employees or departments.';
        }
    }
}

// Load branches with stats
$branches = [];
try {
    $branches = $pdo->query("
        SELECT b.*, 
               (SELECT COUNT(*) FROM employees WHERE branch_id = b.id) AS emp_count,
               (SELECT COUNT(*) FROM departments WHERE branch_id = b.id) AS dept_count
        FROM branches b
        ORDER BY b.name
    ")->fetchAll();
} catch (PDOException $e) {}

// Quick stats
$total_emps = 0;
$total_depts = 0;
foreach ($branches as $b) {
    $total_emps += $b['emp_count'];
    $total_depts += $b['dept_count'];
}

$page_title = 'Branches';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Gradient header -->
<div class="admin-panel" style="background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border:none;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <h2 style="color:#fff;font-size:1.6rem;margin-bottom:4px;">
                <i class="fas fa-building"></i> Branches
            </h2>
            <p style="color:rgba(255,255,255,0.85);font-size:0.92rem;">
                <?= count($branches) ?> branch<?= count($branches) !== 1 ? 'es' : '' ?>
                · <?= $total_emps ?> employee<?= $total_emps !== 1 ? 's' : '' ?>
                · <?= $total_depts ?> department<?= $total_depts !== 1 ? 's' : '' ?>
            </p>
        </div>
        <a href="branch_edit.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
            <i class="fas fa-plus"></i> Add Branch
        </a>
    </div>
</div>

<?php if ($flash): ?>
    <div class="flash flash-info" style="margin-top:20px;">
        <i class="fas fa-info-circle"></i> <?= e($flash) ?>
    </div>
<?php endif; ?>

<!-- Branch cards -->
<?php if (empty($branches)): ?>
    <div class="admin-panel" style="margin-top:20px;text-align:center;padding:80px 20px;color:var(--text-muted);">
        <i class="fas fa-building" style="font-size:4rem;opacity:0.3;display:block;margin-bottom:16px;"></i>
        <p style="font-size:1.1rem;margin-bottom:20px;">No branches yet.</p>
        <a href="branch_edit.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add First Branch
        </a>
    </div>
<?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;margin-top:20px;">
        <?php foreach ($branches as $b): ?>
            <?php
                $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
                $color = $colors[$b['id'] % count($colors)];
            ?>
            <div class="branch-card" 
                 style="background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:24px;transition:all 0.2s;position:relative;overflow:hidden;">
                
                <!-- Color bar -->
                <div style="position:absolute;top:0;left:0;right:0;height:4px;background:<?= $color ?>;"></div>

                <!-- Header -->
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;margin-top:8px;">
                    <div style="width:56px;height:56px;border-radius:14px;background:<?= $color ?>20;color:<?= $color ?>;display:grid;place-items:center;font-size:1.5rem;flex-shrink:0;">
                        <i class="fas fa-building"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <h4 style="font-size:1.05rem;margin-bottom:2px;"><?= e($b['name']) ?></h4>
                        <div style="font-size:0.82rem;color:var(--text-muted);">
                            <i class="fas fa-location-dot"></i> <?= e($b['city'] ?: '—') ?>
                        </div>
                    </div>
                    <?php if ($b['is_active']): ?>
                        <span class="badge badge-success">Active</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Inactive</span>
                    <?php endif; ?>
                </div>

                <!-- Info rows -->
                <div style="display:flex;flex-direction:column;gap:10px;padding:16px;background:var(--bg-alt);border-radius:10px;margin-bottom:16px;">
                    <?php if ($b['address']): ?>
                        <div style="display:flex;align-items:center;gap:10px;font-size:0.85rem;">
                            <i class="fas fa-location-dot" style="color:<?= $color ?>;width:16px;"></i>
                            <span><?= e($b['address']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($b['phone']): ?>
                        <div style="display:flex;align-items:center;gap:10px;font-size:0.85rem;">
                            <i class="fas fa-phone" style="color:<?= $color ?>;width:16px;"></i>
                            <span><?= e($b['phone']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($b['timezone']): ?>
                        <div style="display:flex;align-items:center;gap:10px;font-size:0.85rem;">
                            <i class="fas fa-clock" style="color:<?= $color ?>;width:16px;"></i>
                            <span><?= e($b['timezone']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Stats -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
                    <div style="text-align:center;padding:12px;background:var(--bg-alt);border-radius:8px;">
                        <div style="font-size:1.3rem;font-weight:800;color:var(--text);"><?= (int)$b['emp_count'] ?></div>
                        <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Employees</div>
                    </div>
                    <div style="text-align:center;padding:12px;background:var(--bg-alt);border-radius:8px;">
                        <div style="font-size:1.3rem;font-weight:800;color:var(--text);"><?= (int)$b['dept_count'] ?></div>
                        <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Departments</div>
                    </div>
                </div>

                <!-- Actions -->
                <div style="display:flex;gap:8px;">
                    <a href="branch_edit.php?id=<?= (int)$b['id'] ?>" class="btn btn-outline btn-sm" style="flex:1;">
                        <i class="fas fa-pen"></i> Edit
                    </a>
                    <form method="post" style="display:inline-flex;flex:1;" 
                          onsubmit="return confirm('Delete branch <?= e($b['name']) ?>?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.branch-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    border-color: var(--primary);
}
</style>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
