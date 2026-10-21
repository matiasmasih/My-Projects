<?php
// admin_branches.php — List all branches

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$admin_role = $_SESSION['admin_role'] ?? 'admin';
$flash = '';

// Handle toggle active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && $admin_role === 'admin') {
        try {
            $pdo->prepare("UPDATE branches SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
            $flash = t('admin_branch_updated');
        } catch (PDOException $e) {
            $flash = t('admin_branch_update_failed');
        }
    }
}

$branches = [];
try {
    $branches = $pdo->query("
        SELECT b.*,
               (SELECT COUNT(*) FROM orders       WHERE branch_id = b.id) AS order_count,
               (SELECT COUNT(*) FROM reservations WHERE branch_id = b.id) AS reservation_count,
               (SELECT COUNT(*) FROM admin_users  WHERE branch_id = b.id) AS staff_count
        FROM branches b
        ORDER BY b.sort_order
    ")->fetchAll();
} catch (PDOException $e) {
    $branches = [];
}

$page_title = t('admin_branches');
require_once __DIR__ . '/includes/admin_header.php';
?>



        <div class="admin-hero">
            <div>
                <h1><?= e(t('admin_branches')) ?></h1>
                <p><?= e(t('admin_branches_sub')) ?></p>
            </div>
            <a href="admin_dashboard.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-arrow-left"></i> <?= e(t('admin_dashboard')) ?>
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= e($flash) ?></div>
        <?php endif; ?>

        <div class="admin-panel">
            <?php if (empty($branches)): ?>
                <div class="admin-empty">
                    <i class="fas fa-store"></i>
                    <?= e(t('admin_no_branches')) ?>
                </div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?= e(t('admin_branch')) ?></th>
                                <th><?= e(t('admin_city')) ?></th>
                                <th><?= e(t('admin_country')) ?></th>
                                <th><?= e(t('admin_default_lang')) ?></th>
                                <th><?= e(t('admin_currency')) ?></th>
                                <th><?= e(t('admin_orders')) ?></th>
                                <th><?= e(t('admin_reservations')) ?></th>
                                <th><?= e(t('admin_staff')) ?></th>
                                <th><?= e(t('admin_active')) ?></th>
                                <th><?= e(t('admin_actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branches as $b): ?>
                                <tr>
                                    <td><strong><?= e($b['name']) ?></strong><br><span style="font-size:0.78rem;color:var(--text-muted);"><?= e($b['slug']) ?></span></td>
                                    <td><?= e($b['city']) ?></td>
                                    <td><span class="badge"><?= e($b['country_code']) ?></span></td>
                                    <td><?= e(strtoupper($b['default_lang'])) ?></td>
                                    <td><?= e($b['currency_code']) ?></td>
                                    <td><?= (int)$b['order_count'] ?></td>
                                    <td><?= (int)$b['reservation_count'] ?></td>
                                    <td><?= (int)$b['staff_count'] ?></td>
                                    <td>
                                        <?php if ($b['is_active']): ?>
                                            <span class="status-badge status-ready"><?= e(t('admin_yes')) ?></span>
                                        <?php else: ?>
                                            <span class="status-badge status-cancelled"><?= e(t('admin_no')) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="admin-td-actions">
                                        <a href="admin_branch_edit.php?id=<?= (int)$b['id'] ?>" class="admin-btn-edit" title="Edit">
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

    

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
