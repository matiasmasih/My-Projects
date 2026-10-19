<?php
// admin_dashboard.php — Overview of orders, revenue, customers

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

// Require admin login
if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$admin_name  = $_SESSION['admin_name']  ?? 'Admin';
$admin_role  = $_SESSION['admin_role']  ?? 'admin';
$admin_branch = $_SESSION['admin_branch_id'] ?? null;

$lang = current_language();
$symbol = '€';

// Branch filter — admins see all, managers/staff see their branch only
$branch_where = '';
$branch_params = [];
if ($admin_role !== 'admin' && $admin_branch) {
    $branch_where = "WHERE branch_id = :bid";
    $branch_params[':bid'] = $admin_branch;
}

// ---------- Stats ----------
$stats = [
    'today_orders'    => 0,
    'today_revenue'   => 0,
    'pending_orders'  => 0,
    'total_customers' => 0,
    'unread_messages' => 0,
    'reservations_today' => 0,
];

try {
    // Today's orders
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS cnt, COALESCE(SUM(total_cents), 0) AS rev
        FROM orders
        WHERE DATE(placed_at) = CURDATE() " .
        ($branch_where ? "AND " . substr($branch_where, 6) : "")
    );
    if ($branch_params) $stmt->execute($branch_params);
    else $stmt->execute();
    $row = $stmt->fetch();
    $stats['today_orders']  = (int)($row['cnt'] ?? 0);
    $stats['today_revenue'] = (int)($row['rev'] ?? 0);

    // Pending orders
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM orders
        WHERE status = 'pending' " .
        ($branch_where ? "AND " . substr($branch_where, 6) : "")
    );
    if ($branch_params) $stmt->execute($branch_params);
    else $stmt->execute();
    $stats['pending_orders'] = (int)$stmt->fetchColumn();

    // Total customers
    try {
        $stats['total_customers'] = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    } catch (PDOException $e) {}

    // Unread messages
    try {
        $stats['unread_messages'] = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
    } catch (PDOException $e) {}

    // Today's reservations
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reservations
        WHERE reservation_date = CURDATE() " .
        ($branch_where ? "AND " . substr($branch_where, 6) : "")
    );
    if ($branch_params) $stmt->execute($branch_params);
    else $stmt->execute();
    $stats['reservations_today'] = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    error_log('dashboard stats error: ' . $e->getMessage());
}

// ---------- Recent orders ----------
$recent_orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, order_number, customer_name, total_cents, status, placed_at
        FROM orders
        " . $branch_where . "
        ORDER BY placed_at DESC
        LIMIT 10
    ");
    if ($branch_params) $stmt->execute($branch_params);
    else $stmt->execute();
    $recent_orders = $stmt->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/includes/admin_header.php';
?>



        <!-- Admin Hero -->
        <div class="admin-hero">
            <div>
                <h1><?= e(t('admin_welcome')) ?>, <?= e($admin_name) ?></h1>
                <p>
                    <?= e(t('admin_role')) ?>: <strong><?= e(ucfirst($admin_role)) ?></strong>
                    <?php if ($admin_branch): ?>
                        · Branch ID: <?= (int)$admin_branch ?>
                    <?php else: ?>
                        · <?= e(t('admin_all_branches')) ?>
                    <?php endif; ?>
                </p>
            </div>
            <a href="admin_logout.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-right-from-bracket"></i> <?= e(t('admin_logout')) ?>
            </a>
        </div>

        <!-- Stats Grid -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon terracotta"><i class="fas fa-receipt"></i></div>
                <div>
                    <div class="stat-value"><?= $stats['today_orders'] ?></div>
                    <div class="stat-label"><?= e(t('admin_orders_today')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon gold"><i class="fas fa-coins"></i></div>
                <div>
                    <div class="stat-value"><?= e($symbol) ?><?= number_format($stats['today_revenue'] / 100, 0) ?></div>
                    <div class="stat-label"><?= e(t('admin_revenue_today')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-value"><?= $stats['pending_orders'] ?></div>
                    <div class="stat-label"><?= e(t('admin_pending_orders')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-value"><?= $stats['total_customers'] ?></div>
                    <div class="stat-label"><?= e(t('admin_customers')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon terracotta"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <div class="stat-value"><?= $stats['reservations_today'] ?></div>
                    <div class="stat-label"><?= e(t('admin_res_today')) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon gold"><i class="fas fa-envelope"></i></div>
                <div>
                    <div class="stat-value"><?= $stats['unread_messages'] ?></div>
                    <div class="stat-label"><?= e(t('admin_unread_msgs')) ?></div>
                </div>
            </div>
        </div>

        <!-- Quick links -->
        <div class="admin-panel">
            <h3><i class="fas fa-bolt"></i> <?= e(t('admin_quick_actions')) ?></h3>
              <div style="display:flex;gap:10px;flex-wrap:wrap;">
                 <a href="admin_orders.php"        class="btn btn-primary"><i class="fas fa-receipt"></i> <?= e(t('admin_orders')) ?></a>
                 <a href="admin_menu_items.php"    class="btn btn-primary"><i class="fas fa-utensils"></i> <?= e(t('admin_menu_items')) ?></a>
                 <a href="admin_categories.php"    class="btn btn-primary"><i class="fas fa-tags"></i> <?= e(t('admin_categories')) ?></a>
                 <a href="admin_reservations.php"  class="btn btn-primary"><i class="fas fa-calendar-check"></i> <?= e(t('admin_reservations')) ?></a>
                 <a href="admin_messages.php"      class="btn btn-primary"><i class="fas fa-envelope"></i> <?= e(t('admin_messages')) ?></a>
                 <a href="admin_branches.php"      class="btn btn-primary"><i class="fas fa-store"></i> <?= e(t('admin_branches')) ?></a>
                 <a href="admin_users.php"         class="btn btn-primary"><i class="fas fa-users"></i> <?= e(t('admin_customers')) ?></a>
                 <a href="admin_settings.php"      class="btn btn-primary"><i class="fas fa-gear"></i> Settings</a>
           </div>
        </div>

        <!-- Recent orders -->
        <div class="admin-panel">
            <div class="admin-panel-header">
                <h3><i class="fas fa-clock"></i> <?= e(t('admin_recent_orders')) ?></h3>
                <a href="admin_orders.php" class="btn btn-outline btn-sm"><?= e(t('admin_view_all')) ?></a>
            </div>

            <?php if (empty($recent_orders)): ?>
                <div class="admin-empty">
                    <i class="fas fa-inbox"></i>
                    <?= e(t('admin_no_orders')) ?>
                </div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?= e(t('order_number')) ?></th>
                                <th><?= e(t('admin_customers')) ?></th>
                                <th><?= e(t('admin_total')) ?></th>
                                <th><?= e(t('admin_status')) ?></th>
                                <th><?= e(t('track_placed')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $o): ?>
                                <tr>
                                    <td><strong><?= e($o['order_number']) ?></strong></td>
                                    <td><?= e($o['customer_name']) ?></td>
                                    <td><?= e($symbol) ?><?= number_format($o['total_cents'] / 100, 2) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= e($o['status']) ?>">
                                            <?= e($o['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= e(date('Y-m-d H:i', strtotime($o['placed_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
