<?php
// track_order.php — Public order tracking by order number

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
$page_title = t('order_track');
require_once __DIR__ . '/includes/header.php';

$lang = current_language();

$order_number = trim($_GET['order'] ?? '');
$order  = null;
$items  = [];
$symbol = '€';

if ($order_number !== '') {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, b.name AS branch_name
            FROM orders o
            JOIN branches b ON b.id = o.branch_id
            WHERE o.order_number = ?
            LIMIT 1
        ");
        $stmt->execute([$order_number]);
        $order = $stmt->fetch();

        if ($order) {
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
            $stmt->execute([$order['id']]);
            $items = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT symbol FROM currencies WHERE code = ? LIMIT 1");
            $stmt->execute([$order['currency_code'] ?? 'EUR']);
            $r = $stmt->fetch();
            if ($r) $symbol = $r['symbol'];
        }
    } catch (PDOException $e) {
        error_log('track_order error: ' . $e->getMessage());
    }
}

// Status flow for the progress bar
$statuses = ['pending', 'preparing', 'ready', 'delivering', 'delivered'];
$current_index = $order ? array_search($order['status'], $statuses) : -1;
if ($current_index === false) $current_index = -1; // cancelled falls out
?>

<section class="track-page">
    <div class="container">

        <div class="section-header">
            <h2><?= e(t('order_track')) ?></h2>
            <p class="section-subtitle">Enter your order number to see status</p>
        </div>

        <div style="max-width:560px;margin:0 auto 32px;">
            <form method="get" action="track_order.php" style="display:flex;gap:10px;">
                <input type="text" name="order" placeholder="ORD-20261015-0001"
                       value="<?= e($order_number) ?>" required
                       style="flex:1;">
                <button type="submit" class="btn btn-accent">
                    <i class="fas fa-search"></i> Track
                </button>
            </form>
        </div>

        <?php if ($order_number !== '' && !$order): ?>

            <div class="success-card">
                <div class="success-icon" style="background:rgba(220,38,38,0.15);color:var(--danger);">
                    <i class="fas fa-search"></i>
                </div>
                <h3><?= e(t('track_not_found')) ?></h3>
                <p class="text-muted" style="margin-top:10px;">
                    We couldn't find order <strong><?= e($order_number) ?></strong>.
                    Please check the number and try again.
                </p>
            </div>

        <?php elseif ($order): ?>

            <div class="success-card" style="max-width:640px;">

                <h3 style="margin-bottom:20px;"><?= e($order['order_number']) ?></h3>

                <span class="status-badge status-<?= e($order['status']) ?>" style="font-size:0.9rem;padding:6px 16px;">
                    <?= e(ucfirst($order['status'])) ?>
                </span>

                <!-- Progress bar -->
                <?php if ($current_index >= 0): ?>
                    <div style="margin:28px 0;display:flex;justify-content:space-between;position:relative;">
                        <?php foreach ($statuses as $i => $st): ?>
                            <div style="flex:1;text-align:center;position:relative;z-index:2;">
                                <div style="
                                    width:36px;height:36px;margin:0 auto 8px;
                                    border-radius:50%;
                                    display:grid;place-items:center;
                                    background:<?= $i <= $current_index ? 'var(--accent)' : 'var(--bg-alt)' ?>;
                                    color:<?= $i <= $current_index ? '#1c1917' : 'var(--text-muted)' ?>;
                                    font-weight:700;
                                ">
                                    <i class="fas <?= $i <= $current_index ? 'fa-check' : 'fa-circle' ?>" style="font-size:0.8rem;"></i>
                                </div>
                                <div style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;">
                                    <?= e($st) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="success-info">
                    <div class="success-info-row">
                        <span>Branch</span>
                        <strong><?= e($order['branch_name']) ?></strong>
                    </div>
                    <div class="success-info-row">
                        <span><?= e(t('checkout_order_type')) ?></span>
                        <strong><?= e(t('checkout_' . str_replace('-', '', $order['order_type']))) ?></strong>
                    </div>
                    <div class="success-info-row">
                        <span><?= e(t('track_placed')) ?></span>
                        <strong><?= e(date('Y-m-d H:i', strtotime($order['placed_at']))) ?></strong>
                    </div>
                    <div class="success-info-row">
                        <span><?= e(t('cart_total')) ?></span>
                        <strong><?= e($symbol) ?><?= number_format($order['total_cents'] / 100, 2) ?></strong>
                    </div>
                </div>

                <?php if (!empty($items)): ?>
                    <div style="text-align:left;margin:20px 0;">
                        <h4 style="font-size:0.9rem;margin-bottom:10px;color:var(--text-muted);text-transform:uppercase;">
                            Items
                        </h4>
                        <?php foreach ($items as $it): ?>
                            <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:0.9rem;border-bottom:1px solid var(--border);">
                                <span><?= (int)$it['quantity'] ?>× <?= e($it['name_snapshot']) ?></span>
                                <span><?= e($symbol) ?><?= number_format($it['line_total_cents'] / 100, 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <a href="menu.php" class="btn btn-outline" style="margin-top:20px;">
                    <i class="fas fa-book-open"></i> <?= e(t('cart_continue')) ?>
                </a>

            </div>

        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
