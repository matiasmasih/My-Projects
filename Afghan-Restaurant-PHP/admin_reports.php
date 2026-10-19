<?php
// admin_reports.php — Full sales and performance reports

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$admin_role   = $_SESSION['admin_role'] ?? 'admin';
$admin_branch = $_SESSION['admin_branch_id'] ?? null;
$lang         = current_language();

$branch_filter = "";
$params = [];
if ($admin_role !== 'admin' && $admin_branch) {
    $branch_filter = "AND o.branch_id = :bid";
    $params[':bid'] = $admin_branch;
}

$symbol = '€';

// ---------- 1. KPI Stats ----------
$stats = [
    'total_rev' => 0, 'month_rev' => 0, 'today_rev' => 0,
    'total_orders' => 0, 'avg_order' => 0, 'total_customers' => 0
];

try {
    // Total Revenue
    $stmt = $pdo->prepare("SELECT SUM(total_cents) FROM orders o WHERE o.status != 'cancelled' $branch_filter");
    $stmt->execute($params);
    $stats['total_rev'] = (int)$stmt->fetchColumn();

    // This Month
    $stmt = $pdo->prepare("SELECT SUM(total_cents) FROM orders o WHERE o.status != 'cancelled' AND MONTH(o.placed_at) = MONTH(CURRENT_DATE()) AND YEAR(o.placed_at) = YEAR(CURRENT_DATE()) $branch_filter");
    $stmt->execute($params);
    $stats['month_rev'] = (int)$stmt->fetchColumn();

    // Today
    $stmt = $pdo->prepare("SELECT SUM(total_cents) FROM orders o WHERE o.status != 'cancelled' AND DATE(o.placed_at) = CURRENT_DATE() $branch_filter");
    $stmt->execute($params);
    $stats['today_rev'] = (int)$stmt->fetchColumn();

    // Total Orders
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM orders o WHERE 1=1 $branch_filter");
    $stmt->execute($params);
    $stats['total_orders'] = (int)$stmt->fetchColumn();

    // Average Order Value
    if ($stats['total_orders'] > 0) {
        $stats['avg_order'] = round($stats['total_rev'] / $stats['total_orders']);
    }

    // Total Customers
    $stats['total_customers'] = (int)$pdo->query("SELECT COUNT(id) FROM customers")->fetchColumn();

} catch (PDOException $e) {
    error_log('reports KPI: ' . $e->getMessage());
}

// ---------- 2. Revenue Last 7 Days (for bar chart) ----------
$daily_rev = [];
try {
    $stmt = $pdo->prepare("
        SELECT DATE(o.placed_at) as day, SUM(o.total_cents) as rev
        FROM orders o
        WHERE o.status != 'cancelled' 
          AND o.placed_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)
          $branch_filter
        GROUP BY DATE(o.placed_at)
        ORDER BY day ASC
    ");
    $stmt->execute($params);
    $daily_rev = $stmt->fetchAll();
} catch (PDOException $e) {}

// Fill missing days with 0
$chart_data = [];
$max_rev = 1;
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_data[$date] = 0;
}
foreach ($daily_rev as $row) {
    $chart_data[$row['day']] = (int)$row['rev'];
}
foreach ($chart_data as $v) {
    if ($v > $max_rev) $max_rev = $v;
}

// ---------- 3. Top Items ----------
$top_items = [];
try {
    $stmt = $pdo->prepare("
        SELECT oi.name_snapshot, SUM(oi.quantity) as qty, SUM(oi.line_total_cents) as rev
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        WHERE o.status != 'cancelled' $branch_filter
        GROUP BY oi.name_snapshot
        ORDER BY rev DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $top_items = $stmt->fetchAll();
} catch (PDOException $e) {}

// ---------- 4. Top Customers ----------
$top_customers = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.full_name, c.email, SUM(o.total_cents) as spent, COUNT(o.id) as orders
        FROM orders o
        JOIN customers c ON c.id = o.customer_id
        WHERE o.status != 'cancelled' AND o.customer_id IS NOT NULL $branch_filter
        GROUP BY c.id
        ORDER BY spent DESC
        LIMIT 10
    ");
    $stmt->execute($params);
    $top_customers = $stmt->fetchAll();
} catch (PDOException $e) {}

// ---------- 5. Order Type & Payment Breakdown ----------
$order_types = [];
$payment_methods = [];
try {
    $stmt = $pdo->prepare("SELECT order_type, COUNT(*) as cnt, SUM(total_cents) as rev FROM orders o WHERE status != 'cancelled' $branch_filter GROUP BY order_type");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) $order_types[$row['order_type']] = $row;

    $stmt = $pdo->prepare("SELECT payment_method, COUNT(*) as cnt, SUM(total_cents) as rev FROM orders o WHERE status != 'cancelled' $branch_filter GROUP BY payment_method");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) $payment_methods[$row['payment_method']] = $row;
} catch (PDOException $e) {}

// ---------- 6. Branch Performance ----------
$branch_stats = [];
if ($admin_role === 'admin') {
    try {
        $branch_stats = $pdo->query("
            SELECT b.name, b.name_fi, b.name_fa,
                   COUNT(o.id) as orders, SUM(o.total_cents) as rev
            FROM branches b
            LEFT JOIN orders o ON o.branch_id = b.id AND o.status != 'cancelled'
            GROUP BY b.id
            ORDER BY rev DESC
        ")->fetchAll();
    } catch (PDOException $e) {}
}

// ---------- 7. Recent Orders ----------
$recent_orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.order_number, o.customer_name, o.total_cents, o.status, o.placed_at, b.name as branch_name, b.name_fi as branch_name_fi, b.name_fa as branch_name_fa
        FROM orders o
        JOIN branches b ON b.id = o.branch_id
        WHERE 1=1 " . str_replace('o.branch_id', 'o.branch_id', $branch_filter) . "
        ORDER BY o.placed_at DESC
        LIMIT 20
    ");
    $stmt->execute($params);
    $recent_orders = $stmt->fetchAll();
} catch (PDOException $e) {}

$page_title = t('rep_title');
require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- ============ KPI CARDS ============ -->
<div class="admin-panel">
    <h3><i class="fas fa-chart-line"></i> <?= e(t('rep_overview')) ?></h3>

    <div class="stat-grid" style="margin-top:20px;margin-bottom:0;">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-euro-sign"></i></div>
            <div>
                <div class="stat-value"><?= e($symbol) ?><?= number_format($stats['total_rev'] / 100, 2) ?></div>
                <div class="stat-label"><?= e(t('rep_total_rev')) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
            <div>
                <div class="stat-value"><?= e($symbol) ?><?= number_format($stats['month_rev'] / 100, 2) ?></div>
                <div class="stat-label"><?= e(t('rep_month_rev')) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-sun"></i></div>
            <div>
                <div class="stat-value"><?= e($symbol) ?><?= number_format($stats['today_rev'] / 100, 2) ?></div>
                <div class="stat-label"><?= e(t('rep_today_rev')) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon terracotta"><i class="fas fa-receipt"></i></div>
            <div>
                <div class="stat-value"><?= number_format($stats['total_orders']) ?></div>
                <div class="stat-label"><?= e(t('rep_total_orders')) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-calculator"></i></div>
            <div>
                <div class="stat-value"><?= e($symbol) ?><?= number_format($stats['avg_order'] / 100, 2) ?></div>
                <div class="stat-label"><?= e(t('rep_avg_order')) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-users"></i></div>
            <div>
                <div class="stat-value"><?= number_format($stats['total_customers']) ?></div>
                <div class="stat-label"><?= e(t('rep_customers')) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ============ REVENUE LAST 7 DAYS (CSS BAR CHART) ============ -->
<div class="admin-panel">
    <h3><i class="fas fa-chart-bar"></i> <?= e(t('rep_rev_trend')) ?></h3>

    <?php if (array_sum($chart_data) === 0): ?>
        <div class="admin-empty"><i class="fas fa-chart-area"></i> <?= e(t('rep_no_data')) ?></div>
    <?php else: ?>
        <div style="display:flex;gap:12px;align-items:flex-end;height:200px;padding:20px 0;">
            <?php foreach ($chart_data as $day => $rev): ?>
                <div style="flex:1;text-align:center;">
                    <div style="height:<?= max(10, ($rev / $max_rev) * 160) ?>px;background:linear-gradient(180deg,var(--accent),var(--accent-dark));border-radius:6px;margin-bottom:8px;"></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= e(date('D d', strtotime($day))) ?></div>
                    <div style="font-size:0.78rem;font-weight:700;color:var(--text);"><?= e($symbol) ?><?= number_format($rev / 100, 0) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ============ TOP ITEMS + TOP CUSTOMERS ============ -->
<div class="grid-2">
    <div class="admin-panel">
        <h3><i class="fas fa-fire"></i> <?= e(t('rep_top_items')) ?></h3>
        <?php if (empty($top_items)): ?>
            <div class="admin-empty"><i class="fas fa-inbox"></i> <?= e(t('rep_no_data')) ?></div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr><th><?= e(t('rep_item')) ?></th><th><?= e(t('rep_qty')) ?></th><th><?= e(t('rep_revenue')) ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_items as $it): ?>
                            <tr>
                                <td><strong><?= e($it['name_snapshot']) ?></strong></td>
                                <td><?= (int)$it['qty'] ?></td>
                                <td><?= e($symbol) ?><?= number_format($it['rev'] / 100, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="admin-panel">
        <h3><i class="fas fa-crown"></i> <?= e(t('rep_top_customers')) ?></h3>
        <?php if (empty($top_customers)): ?>
            <div class="admin-empty"><i class="fas fa-user-friends"></i> <?= e(t('rep_no_data')) ?></div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr><th><?= e(t('rep_customer')) ?></th><th><?= e(t('rep_orders')) ?></th><th><?= e(t('rep_spent')) ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_customers as $c): ?>
                            <tr>
                                <td><strong><?= e($c['full_name']) ?></strong><br><span style="font-size:0.75rem;color:var(--text-muted);"><?= e($c['email']) ?></span></td>
                                <td><?= (int)$c['orders'] ?></td>
                                <td><?= e($symbol) ?><?= number_format($c['spent'] / 100, 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ ORDER TYPES + PAYMENT METHODS ============ -->
<div class="grid-2">
    <div class="admin-panel">
        <h3><i class="fas fa-truck"></i> <?= e(t('rep_order_types')) ?></h3>
        <?php if (empty($order_types)): ?>
            <div class="admin-empty"><?= e(t('rep_no_data')) ?></div>
        <?php else: ?>
            <?php foreach (['delivery','pickup','dine-in'] as $type): ?>
                <?php $row = $order_types[$type] ?? null; ?>
                <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);">
                    <span><?= e(t('rep_' . str_replace('-', '', $type))) ?></span>
                    <strong><?= $row ? (int)$row['cnt'] . ' — ' . e($symbol) . number_format($row['rev'] / 100, 2) : '0' ?></strong>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="admin-panel">
        <h3><i class="fas fa-credit-card"></i> <?= e(t('rep_payments')) ?></h3>
        <?php if (empty($payment_methods)): ?>
            <div class="admin-empty"><?= e(t('rep_no_data')) ?></div>
        <?php else: ?>
            <?php foreach (['cash','card','online'] as $method): ?>
                <?php $row = $payment_methods[$method] ?? null; ?>
                <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);">
                    <span><?= e(t('rep_' . $method)) ?></span>
                    <strong><?= $row ? (int)$row['cnt'] . ' — ' . e($symbol) . number_format($row['rev'] / 100, 2) : '0' ?></strong>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ============ BRANCH PERFORMANCE ============ -->
<?php if ($admin_role === 'admin' && !empty($branch_stats)): ?>
<div class="admin-panel">
    <h3><i class="fas fa-store"></i> <?= e(t('rep_branch')) ?></h3>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr><th><?= e(t('admin_branch')) ?></th><th><?= e(t('rep_orders')) ?></th><th><?= e(t('rep_revenue')) ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($branch_stats as $b): ?>
                    <tr>
                        <td><strong><?= e(pick_branch_name($b, $lang)) ?></strong></td>
                        <td><?= (int)$b['orders'] ?></td>
                        <td><?= e($symbol) ?><?= number_format($b['rev'] / 100, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ============ RECENT ORDERS ============ -->
<div class="admin-panel">
    <h3><i class="fas fa-clock"></i> <?= e(t('rep_recent_orders')) ?></h3>
    <?php if (empty($recent_orders)): ?>
        <div class="admin-empty"><i class="fas fa-inbox"></i> <?= e(t('rep_no_data')) ?></div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><?= e(t('rep_order_num')) ?></th>
                        <th><?= e(t('rep_customer')) ?></th>
                        <th><?= e(t('admin_branch')) ?></th>
                        <th><?= e(t('rep_date')) ?></th>
                        <th><?= e(t('rep_status')) ?></th>
                        <th><?= e(t('rep_total')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $o): ?>
                        <tr>
                            <td><strong><?= e($o['order_number']) ?></strong></td>
                            <td><?= e($o['customer_name']) ?></td>
                            <td><?= e(pick_branch_name($o, $lang)) ?></td>
                            <td><?= e(date('Y-m-d H:i', strtotime($o['placed_at']))) ?></td>
                            <td><span class="status-badge status-<?= e($o['status']) ?>"><?= e(t('rep_' . $o['status'])) ?></span></td>
                            <td><?= e($symbol) ?><?= number_format($o['total_cents'] / 100, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
