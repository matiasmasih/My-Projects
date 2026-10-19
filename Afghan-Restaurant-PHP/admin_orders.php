<?php
// admin_orders.php — View and manage orders

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$admin_role   = $_SESSION['admin_role']   ?? 'admin';
$admin_branch = $_SESSION['admin_branch_id'] ?? null;

$symbol = '€';
$allowed_statuses = ['pending', 'preparing', 'ready', 'delivering', 'delivered', 'cancelled'];

// ---------- Handle status update ----------
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['status'];

    if (in_array($status, $allowed_statuses, true)) {
        try {
            // Managers/staff can only update their branch orders
            $sql = "UPDATE orders SET status = ? WHERE id = ?";
            $params = [$status, $oid];
            if ($admin_role !== 'admin' && $admin_branch) {
                $sql .= " AND branch_id = ?";
                $params[] = $admin_branch;
            }
            $pdo->prepare($sql)->execute($params);
            $flash = t('admin_order_updated');
        } catch (PDOException $e) {
            error_log('admin_orders update: ' . $e->getMessage());
            $flash = t('admin_order_update_failed');
        }
    }
}

// ---------- Filter ----------
$status_filter = $_GET['status'] ?? '';
$status_where  = '';
$params        = [];

if (in_array($status_filter, $allowed_statuses, true)) {
    $status_where = "AND o.status = :st";
    $params[':st'] = $status_filter;
}

if ($admin_role !== 'admin' && $admin_branch) {
    $status_where .= " AND o.branch_id = :bid";
    $params[':bid'] = $admin_branch;
}

// ---------- Load orders ----------
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*, b.name AS branch_name
        FROM orders o
        JOIN branches b ON b.id = o.branch_id
        WHERE 1=1 $status_where
        ORDER BY o.placed_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Load items for each order
    if (!empty($orders)) {
        $order_ids = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY id");
        $stmt->execute($order_ids);
        $items_by_order = [];
        foreach ($stmt->fetchAll() as $it) {
            $items_by_order[$it['order_id']][] = $it;
        }
    } else {
        $items_by_order = [];
    }
} catch (PDOException $e) {
    error_log('admin_orders query: ' . $e->getMessage());
    $orders = [];
    $items_by_order = [];
}

$page_title = 'Orders';
require_once __DIR__ . '/includes/admin_header.php';
?>



        <div class="admin-hero">
            <div>
                <h1><?= e(t('admin_orders')) ?></h1>
                <p><?= e(t('admin_orders_sub')) ?></p>
            </div>
            <a href="admin_dashboard.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-arrow-left"></i> <?= e(t('admin_dashboard')) ?>
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="flash flash-success">
                <i class="fas fa-check-circle"></i> <?= e($flash) ?>
            </div>
        <?php endif; ?>

        <!-- Status filter -->
        <div class="admin-panel">
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="admin_orders.php" class="category-tab <?= $status_filter === '' ? 'active' : '' ?>"><?= e(t('admin_all')) ?></a>
                <?php foreach ($allowed_statuses as $st): ?>
                    <a href="admin_orders.php?status=<?= e($st) ?>"
                       class="category-tab <?= $status_filter === $st ? 'active' : '' ?>">
                        <?= e(t('status_' . $st)) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="admin-panel">
                <div class="admin-empty">
                    <i class="fas fa-inbox"></i>
                    <?= e(t('admin_no_orders_found')) ?>
                </div>
            </div>
        <?php else: ?>

            <?php foreach ($orders as $o): ?>
                <div class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <h3 style="margin-bottom:4px;">
                                <i class="fas fa-receipt"></i> <?= e($o['order_number']) ?>
                                <span class="status-badge status-<?= e($o['status']) ?>" style="margin-left:8px;">
                                    <?= e($o['status']) ?>
                                </span>
                            </h3>
                            <div style="font-size:0.85rem;color:var(--text-muted);">
                                <?= e($o['customer_name']) ?> ·
                                <?= e($o['customer_phone']) ?> ·
                                <?= e($o['branch_name']) ?> ·
                                <?= e(date('Y-m-d H:i', strtotime($o['placed_at']))) ?>
                            </div>
                        </div>

                        <form method="post" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                            <select name="status" style="padding:6px 10px;font-size:0.85rem;">
                                <?php foreach ($allowed_statuses as $st): ?>
                                    <option value="<?= e($st) ?>" <?= $o['status'] === $st ? 'selected' : '' ?>>
                                        <?= e(t('status_' . $st)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-accent btn-sm">
                                <i class="fas fa-check"></i> <?= e(t('admin_update')) ?>
                            </button>
                        </form>
                    </div>

                    <div class="grid-2" style="gap:24px;">
                        <div>
                            <h4 style="font-size:0.85rem;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;"><?= e(t('admin_items')) ?></h4>
                            <?php foreach (($items_by_order[$o['id']] ?? []) as $it): ?>
                                <div style="display:flex;justify-content:space-between;font-size:0.9rem;padding:4px 0;">
                                    <span><?= (int)$it['quantity'] ?>× <?= e($it['name_snapshot']) ?></span>
                                    <span><?= e($symbol) ?><?= number_format($it['line_total_cents'] / 100, 2) ?></span>
                                </div>
                            <?php endforeach; ?>

                            <div style="display:flex;justify-content:space-between;font-weight:800;font-size:1rem;padding-top:8px;margin-top:8px;border-top:1px solid var(--border);">
                                <span><?= e(t('admin_total')) ?></span>
                                <span><?= e($symbol) ?><?= number_format($o['total_cents'] / 100, 2) ?></span>
                            </div>
                        </div>

                        <div>
                            <h4 style="font-size:0.85rem;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;"><?= e(t('admin_details')) ?></h4>
                            <div style="font-size:0.88rem;line-height:1.8;">
                                <div><strong><?= e(t('admin_type')) ?>:</strong> <?= e($o['order_type']) ?></div>
                                <div><strong><?= e(t('admin_payment')) ?>:</strong> <?= e($o['payment_method']) ?> (<?= e($o['payment_status']) ?>)</div>
                                <?php if ($o['order_type'] === 'delivery'): ?>
                                    <div><strong><?= e(t('admin_address')) ?>:</strong> <?= e($o['delivery_address']) ?>, <?= e($o['delivery_city']) ?> <?= e($o['delivery_postal']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($o['notes'])): ?>
                                    <div><strong><?= e(t('admin_notes')) ?>:</strong> <?= e($o['notes']) ?></div>
                                <?php endif; ?>
                                <div><strong><?= e(t('admin_currency')) ?>:</strong> <?= e($o['currency_code']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
