<?php
// manager/branches.php — Manage Branches (Manager View)
// Kirjasto 2026 — Phase 9

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager' && $user['role'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branches = $pdo->query("
    SELECT b.*,
           (SELECT COUNT(*) FROM book_copies WHERE branch_id = b.id) as book_count,
           (SELECT COUNT(*) FROM devices WHERE branch_id = b.id) as device_count,
           (SELECT COUNT(*) FROM users WHERE branch_id = b.id) as user_count
    FROM branches b
    ORDER BY b.name ASC
")->fetchAll();

$my_branch_id = $user['branch_id'];

$page_title = 'Manage Branch';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Manage Branch</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Select a branch to manage its inventory, loans, and staff.</p>
</div>

<!-- Branch List -->
<div class="card" style="padding: 0; overflow: hidden; border-top: 4px solid #f59e0b;">
    <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); background: var(--bg-alt); display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-building" style="color: #f59e0b; font-size: 1.2rem;"></i>
            <h3 style="margin: 0; color: var(--text);">All Library Branches</h3>
        </div>
        <span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; padding: 6px 12px; border-radius: 50px; font-weight: 700;"><?= count($branches) ?> Branches</span>
    </div>

    <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse; text-align: left; white-space: nowrap;">
            <thead style="background: var(--card-bg);">
                <tr>
                    <th style="padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Branch</th>
                    <th style="padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Inventory</th>
                    <th style="padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Status</th>
                    <th style="padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $b): 
                    $is_my_branch = ($b['id'] == $my_branch_id);
                ?>
                    <tr style="transition: all 0.2s; <?= $is_my_branch ? 'background: rgba(245, 158, 11, 0.05);' : '' ?>" onmouseover="this.style.background='rgba(245, 158, 11, 0.08)'" onmouseout="this.style.background='<?= $is_my_branch ? 'rgba(245, 158, 11, 0.05)' : 'transparent' ?>'">
                        <td style="padding: 20px 24px; border-bottom: 1px solid var(--border);">
                            <div style="display: flex; align-items: center; gap: 16px;">
                                <div style="width: 44px; height: 44px; border-radius: 12px; background: <?= $is_my_branch ? 'linear-gradient(135deg, #f59e0b, #ea580c)' : 'rgba(245, 158, 11, 0.1)' ?>; color: <?= $is_my_branch ? 'white' : '#f59e0b' ?>; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div>
                                    <strong style="display: block; color: var(--text); font-size: 1rem; margin-bottom: 2px;">
                                        <?= e($b['name']) ?>
                                        <?php if ($is_my_branch): ?>
                                            <span style="font-size: 0.7rem; background: #f59e0b; color: white; padding: 2px 8px; border-radius: 50px; margin-left: 8px; font-weight: 700; text-transform: uppercase;">My Branch</span>
                                        <?php endif; ?>
                                    </strong>
                                    <small style="color: var(--text-muted);"><i class="fas fa-map-pin"></i> <?= e($b['address'] ?: 'No address') ?></small>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 20px 24px; border-bottom: 1px solid var(--border);">
                            <div style="display: flex; gap: 20px; font-size: 0.85rem; color: var(--text-muted);">
                                <span title="Book Copies" style="display: flex; align-items: center; gap: 6px;"><i class="fas fa-book" style="color: #10b981;"></i> <?= $b['book_count'] ?></span>
                                <span title="Devices" style="display: flex; align-items: center; gap: 6px;"><i class="fas fa-laptop" style="color: #0ea5e9;"></i> <?= $b['device_count'] ?></span>
                                <span title="Users" style="display: flex; align-items: center; gap: 6px;"><i class="fas fa-users" style="color: #8b5cf6;"></i> <?= $b['user_count'] ?></span>
                            </div>
                        </td>
                        <td style="padding: 20px 24px; border-bottom: 1px solid var(--border);">
                            <?php if ($b['is_active']): ?>
                                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Active</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 20px 24px; border-bottom: 1px solid var(--border); text-align: right;">
                            <a href="<?= e(SITE_URL) ?>manager/manage_branch.php?id=<?= $b['id'] ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: <?= $is_my_branch ? 'linear-gradient(135deg, #f59e0b, #ea580c)' : 'var(--bg-alt)' ?>; color: <?= $is_my_branch ? 'white' : 'var(--text)' ?>; border: 1px solid <?= $is_my_branch ? 'transparent' : 'var(--border)' ?>; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 0.85rem; transition: all 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                                <i class="fas fa-cog"></i> Manage
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
