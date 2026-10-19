<?php
// order_success.php — Shown after successful order

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
$page_title = t('order_success_title');
require_once __DIR__ . '/includes/header.php';

$lang = current_language();

// Get order by number
$order_number = $_GET['order'] ?? '';
$order = null;
$items = [];

if ($order_number !== '') {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, b.name AS branch_name, b.slug AS branch_slug
            FROM orders o
            JOIN branches b ON b.id = o.branch_id
            WHERE o.order_number = ?
            LIMIT 1
        ");
        $stmt->execute([$order_number]);
        $order = $stmt->fetch();

        if ($order) {
            $itemStmt = $pdo->prepare("
                SELECT * FROM order_items WHERE order_id = ? ORDER BY id
            ");
            $itemStmt->execute([$order['id']]);
            $items = $itemStmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log('order_success error: ' . $e->getMessage());
    }
}

$symbol = '€';
try {
    $row = $pdo->prepare("SELECT symbol FROM currencies WHERE code = ? LIMIT 1");
    $row->execute([$order['currency_code'] ?? 'EUR']);
    $r = $row->fetch();
    if ($r) $symbol = $r['symbol'];
} catch (PDOException $e) {}
?>

<section class="success-page">
    <div class="container">

        <?php if (!$order): ?>

            <div class="success-card">
                <div class="success-icon" style="background:rgba(220,38,38,0.15);color:var(--danger);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1 class="auth-title">Order not found</h1>
                <p class="auth-subtitle">We couldn't find that order number. Please check your confirmation email or contact us.</p>
                <a href="index.php" class="btn btn-accent" style="margin-top:20px;">
                    <i class="fas fa-home"></i> <?= e(t('nav_home')) ?>
                </a>
            </div>

        <?php else: ?>

            <div class="success-card">

                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>

                <h1 class="auth-title"><?= e(t('order_success_title')) ?></h1>
                <p class="auth-subtitle"><?= e(t('order_success_text')) ?></p>

                <div class="success-order-number">
                    <?= e($order['order_number']) ?>
                </div>

                <div class="success-info">
                    <div class="success-info-row">
                        <span><?= e(t('order_number')) ?></span>
                        <strong><?= e($order['order_number']) ?></strong>
                    </div>
                    <div class="success-info-row">
                        <span><?= e(t('order_branch_label')) ?></span>
                        <strong><?= e($order['branch_name']) ?></strong>
                    </div>
                    <div class="success-info-row">
                        <span><?= e(t('checkout_order_type')) ?></span>
                        <strong><?= e(t('checkout_' . str_replace('-', '', $order['order_type']))) ?></strong>
                    </div>
                    <div class="success-info-row">
                        <span><?= e(t('order_status_label')) ?></span>
                        <span class="status-badge status-<?= e($order['status']) ?>"><?= e($order['status']) ?></span>
                    </div>
                    <div class="success-info-row">
                        <span><?= e(t('cart_total')) ?></span>
                        <strong><?= e($symbol) ?><?= number_format($order['total_cents'] / 100, 2) ?></strong>
                    </div>
                </div>

                <?php if (!empty($items)): ?>
                    <div style="text-align:left;margin:20px 0;">
                        <h3 style="font-size:0.95rem;margin-bottom:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">
                            <?= e(t('order_items_label')) ?>
                        </h3>
                        <?php foreach ($items as $it): ?>
                            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:0.9rem;">
                                <span><?= (int)$it['quantity'] ?>× <?= e($it['name_snapshot']) ?></span>
                                <span><?= e($symbol) ?><?= number_format($it['line_total_cents'] / 100, 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:24px;">
                    <a href="track_order.php?order=<?= e($order['order_number']) ?>" class="btn btn-accent">
                        <i class="fas fa-truck"></i> <?= e(t('order_track')) ?>
                    </a>
                    <a href="menu.php" class="btn btn-outline">
                        <i class="fas fa-book-open"></i> <?= e(t('cart_continue')) ?>
                    </a>
                </div>

            </div>

        <?php endif; ?>

    </div>
</section>

<script>
// Clear cart now that order is placed
if (<?= $order ? 'true' : 'false' ?>) {
    localStorage.removeItem('afghan-cart');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
