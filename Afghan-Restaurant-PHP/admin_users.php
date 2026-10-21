<?php
// admin_users.php — List + manage staff/admin accounts

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$lang = current_language();
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($id === (int)$_SESSION['admin_id']) {
        $flash = t('admin_cannot_modify_self');
    } elseif ($id > 0) {
        try {
            if ($action === 'delete') {
                $pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$id]);
                $flash = t('admin_user_deleted');
            } elseif ($action === 'toggle_active') {
                $pdo->prepare("UPDATE admin_users SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
                $flash = t('admin_user_status_updated');
            }
        } catch (PDOException $e) {
            error_log('admin_users action: ' . $e->getMessage());
            $flash = t('admin_generic_fail');
        }
    }
}

$users = [];
try {
    $users = $pdo->query("
        SELECT a.*, b.name AS name, b.name_fi AS name_fi, b.name_fa AS name_fa
        FROM admin_users a
        LEFT JOIN branches b ON b.id = a.branch_id
        ORDER BY a.id
    ")->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

$role_labels = [
    'staff'   => t('admin_role_staff'),
    'manager' => t('admin_role_manager'),
    'admin'   => t('admin_role_admin'),
];

$page_title = t('admin_users');
require_once __DIR__ . '/includes/admin_header.php';
?>



        <div class="admin-hero">
            <div>
                <h1><?= e(t('admin_users')) ?></h1>
                <p><?= e(t('admin_users_sub')) ?></p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="admin_user_edit.php" class="btn btn-accent">
                    <i class="fas fa-plus"></i> <?= e(t('admin_add_user')) ?>
                </a>
                <a href="admin_dashboard.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                    <i class="fas fa-arrow-left"></i> <?= e(t('admin_dashboard')) ?>
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash flash-info">
                <i class="fas fa-info-circle"></i> <?= e($flash) ?>
            </div>
        <?php endif; ?>

        <div class="admin-panel">
            <?php if (empty($users)): ?>
                <div class="admin-empty">
                    <i class="fas fa-users"></i>
                    <?= e(t('admin_no_users')) ?>
                </div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?= e(t('admin_user_id')) ?></th>
                                <th><?= e(t('admin_user_username')) ?></th>
                                <th><?= e(t('admin_user_fullname')) ?></th>
                                <th><?= e(t('admin_user_email')) ?></th>
                                <th><?= e(t('admin_user_role')) ?></th>
                                <th><?= e(t('admin_user_branch')) ?></th>
                                <th><?= e(t('admin_user_last_login')) ?></th>
                                <th><?= e(t('admin_user_active')) ?></th>
                                <th><?= e(t('admin_actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <?php $is_self = ($u['id'] == $_SESSION['admin_id']); ?>
                                <tr>
                                    <td><?= (int)$u['id'] ?></td>
                                    <td>
                                        <strong><?= e($u['username']) ?></strong>
                                        <?php if ($is_self): ?>
                                            <span class="badge badge-accent" style="margin-left:6px;"><?= e(t('admin_you')) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($u['full_name']) ?></td>
                                    <td><?= e($u['email']) ?></td>
                                    <td>
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="badge badge-accent"><?= e($role_labels['admin']) ?></span>
                                        <?php else: ?>
                                            <span class="badge"><?= e($role_labels[$u['role']] ?? $u['role']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $u['branch_id'] ? e(pick_branch_name($u, $lang)) : '—' ?></td>
                                    <td><?= $u['last_login'] ? e(date('Y-m-d H:i', strtotime($u['last_login']))) : '—' ?></td>
                                    <td>
                                        <?php if ($is_self): ?>
                                            <span class="status-badge status-ready"><?= e(t('admin_yes')) ?></span>
                                        <?php else: ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_active">
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit" class="status-badge <?= $u['is_active'] ? 'status-ready' : 'status-cancelled' ?>" style="border:none;cursor:pointer;">
                                                    <?= $u['is_active'] ? e(t('admin_yes')) : e(t('admin_no')) ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td class="admin-td-actions">
                                        <a href="admin_user_edit.php?id=<?= (int)$u['id'] ?>" class="admin-btn-edit" title="<?= e(t('admin_edit')) ?>">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <?php if (!$is_self): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('<?= e(t('admin_confirm_delete_user')) ?>');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit" class="admin-btn-del" title="<?= e(t('admin_delete')) ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
