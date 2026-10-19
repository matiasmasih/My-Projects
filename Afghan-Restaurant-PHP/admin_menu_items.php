<?php
// admin_menu_items.php — List all menu items + delete + toggle

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

$lang = current_language();

function pick_lang(array $row, string $field, string $lang): string {
    $key = $field . '_' . $lang;
    if (!empty($row[$key])) return $row[$key];
    return $row[$field . '_en'] ?? '';
}

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$flash = '';

// ---------- Handle actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            if ($action === 'delete') {
                $pdo->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$id]);
                $flash = 'Menu item deleted.';
            } elseif ($action === 'toggle_available') {
                $pdo->prepare("UPDATE menu_items SET is_available = NOT is_available WHERE id = ?")->execute([$id]);
                $flash = 'Availability updated.';
            } elseif ($action === 'toggle_featured') {
                $pdo->prepare("UPDATE menu_items SET is_featured = NOT is_featured WHERE id = ?")->execute([$id]);
                $flash = 'Featured flag updated.';
            }
        } catch (PDOException $e) {
            error_log('admin_menu_items action: ' . $e->getMessage());
            $flash = 'Action failed.';
        }
    }
}

// ---------- Load items ----------
$items = [];
try {
    $items = $pdo->query("
        SELECT m.id, m.slug, m.name_en, m.name_fi, m.name_fa, m.base_price_cents, m.image,
               m.is_available, m.is_featured, m.sort_order,
               c.name_en AS cat_name_en, c.name_fi AS cat_name_fi, c.name_fa AS cat_name_fa
        FROM menu_items m
        JOIN categories c ON c.id = m.category_id
        ORDER BY c.sort_order, m.sort_order, m.id
    ")->fetchAll();
} catch (PDOException $e) {
    $items = [];
}

$page_title = 'Menu Items';
require_once __DIR__ . '/includes/admin_header.php';
?>



        <div class="admin-hero">
            <div>
                <h1><?= e(t('admin_menu_items')) ?></h1>
                <p><?= e(t('admin_menu_items_sub')) ?></p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="admin_menu_item_edit.php" class="btn btn-accent">
                    <i class="fas fa-plus"></i> <?= e(t('admin_add_item')) ?>
                </a>
                <a href="admin_dashboard.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                    <i class="fas fa-arrow-left"></i> <?= e(t('admin_dashboard')) ?>
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="flash flash-success">
                <i class="fas fa-check-circle"></i> <?= e($flash) ?>
            </div>
        <?php endif; ?>

        <div class="admin-panel">
            <?php if (empty($items)): ?>
                <div class="admin-empty">
                    <i class="fas fa-utensils"></i>
                    <?= e(t('admin_no_items')) ?>
                </div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?= e(t('admin_image')) ?></th>
                                <th><?= e(t('admin_name')) ?></th>
                                <th><?= e(t('admin_category')) ?></th>
                                <th><?= e(t('admin_price')) ?></th>
                                <th><?= e(t('admin_available')) ?></th>
                                <th><?= e(t('admin_featured')) ?></th>
                                <th><?= e(t('admin_actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <?php $img_path = __DIR__ . '/uploads/menu/' . $it['image']; ?>
                                        <?php if ($it['image'] && file_exists($img_path)): ?>
                                            <img src="uploads/menu/<?= e(rawurlencode($it['image'])) ?>" alt="">
                                        <?php else: ?>
                                            <div style="width:50px;height:50px;border-radius:8px;background:var(--bg-alt);display:grid;place-items:center;color:var(--text-muted);">
                                                <i class="fas fa-utensils"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= e(pick_lang($it, 'name', $lang)) ?></strong><br>
                                        <span style="font-size:0.78rem;color:var(--text-muted);"><?= e($it['slug']) ?></span>
                                    </td>
                                    <td><?= e(pick_lang($it, 'cat_name', $lang)) ?></td>
                                    <td><?= e(format_price((int)$it['base_price_cents'], $current_currency)) ?></td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_available">
                                            <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                            <button type="submit" class="status-badge <?= $it['is_available'] ? 'status-ready' : 'status-cancelled' ?>" style="border:none;cursor:pointer;padding:4px 12px;">
                                                <?= $it['is_available'] ? e(t('admin_yes')) : e(t('admin_no')) ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle_featured">
                                            <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                            <button type="submit" class="status-badge <?= $it['is_featured'] ? 'status-delivered' : 'status-pending' ?>" style="border:none;cursor:pointer;padding:4px 12px;">
                                                <?= $it['is_featured'] ? '★' : '—' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="admin-td-actions">
                                        <a href="admin_menu_item_edit.php?id=<?= (int)$it['id'] ?>" class="admin-btn-edit" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this item?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                                            <button type="submit" class="admin-btn-del" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
