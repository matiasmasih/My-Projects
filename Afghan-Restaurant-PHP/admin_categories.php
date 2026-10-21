<?php
// admin_categories.php — List all categories

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

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            // Check if any menu items use it
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE category_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $flash = 'Cannot delete — category has menu items.';
            } else {
                $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
                $flash = 'Category deleted.';
            }
        } catch (PDOException $e) {
            error_log('admin_categories delete: ' . $e->getMessage());
            $flash = 'Could not delete category.';
        }
    }
}

// Load categories with item counts
$categories = [];
try {
    $categories = $pdo->query("
        SELECT c.*,
               (SELECT COUNT(*) FROM menu_items WHERE category_id = c.id) AS item_count
        FROM categories c
        ORDER BY c.sort_order, c.id
    ")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

$page_title = 'Categories';
require_once __DIR__ . '/includes/admin_header.php';
?>



        <div class="admin-hero">
            <div>
                <h1><?= e(t('admin_categories')) ?></h1>
                <p><?= e(t('admin_categories_sub')) ?></p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="admin_category_edit.php" class="btn btn-accent">
                    <i class="fas fa-plus"></i> <?= e(t('admin_add_category')) ?>
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
            <?php if (empty($categories)): ?>
                <div class="admin-empty">
                    <i class="fas fa-tags"></i>
                    <?= e(t('admin_no_categories')) ?>
                </div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?= e(t('admin_slug')) ?></th>
                                <th><?= e(t('admin_name')) ?> (EN)</th>
                                <th><?= e(t('admin_name')) ?> (FI)</th>
                                <th><?= e(t('admin_name')) ?> (FA)</th>
                                <th><?= e(t('admin_items_count')) ?></th>
                                <th><?= e(t('admin_active')) ?></th>
                                <th><?= e(t('admin_actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $c): ?>
                                <tr>
                                    <td><code><?= e($c['slug']) ?></code></td>
                                    <td><strong><?= e(pick_lang($c, 'name', $lang)) ?></strong></td>
                                    <td><?= e($c['name_fi']) ?></td>
                                    <td dir="rtl"><?= e($c['name_fa']) ?></td>
                                    <td><span class="badge"><?= (int)$c['item_count'] ?></span></td>
                                    <td>
                                        <?php if ($c['is_active']): ?>
                                            <span class="status-badge status-ready">Yes</span>
                                        <?php else: ?>
                                            <span class="status-badge status-cancelled">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="admin-td-actions">
                                        <a href="admin_category_edit.php?id=<?= (int)$c['id'] ?>" class="admin-btn-edit" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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
