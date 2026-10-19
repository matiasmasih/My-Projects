<?php
// checkout.php — Order form (guest or logged in)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
$page_title = t('checkout_title');
require_once __DIR__ . '/includes/header.php';

$lang       = current_language();
$is_logged  = !empty($_SESSION['customer_id']);

// Load customer info if logged in
$customer = [
    'full_name' => '',
    'email'     => '',
    'phone'     => '',
];
if ($is_logged) {
    try {
        $stmt = $pdo->prepare("SELECT full_name, email, phone FROM customers WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['customer_id']]);
        $row = $stmt->fetch();
        if ($row) $customer = $row;
    } catch (PDOException $e) {}
}
?>

<section class="form-page">
    <div class="container">

        <div class="section-header">
            <h2><?= e(t('checkout_title')) ?></h2>
        </div>

        <!-- Order type tabs -->
        <div class="category-tabs" style="margin-bottom:32px;">
            <button type="button" class="category-tab active" data-order-type="delivery">
                <i class="fas fa-motorcycle"></i> <?= e(t('checkout_delivery')) ?>
            </button>
            <button type="button" class="category-tab" data-order-type="pickup">
                <i class="fas fa-bag-shopping"></i> <?= e(t('checkout_pickup')) ?>
            </button>
            <button type="button" class="category-tab" data-order-type="dine-in">
                <i class="fas fa-utensils"></i> <?= e(t('checkout_dinein')) ?>
            </button>
        </div>

        <form id="checkoutForm" class="form-layout" method="post" action="order_process.php">

            <!-- LEFT: Customer details -->
            <div class="form-card">

                <?php if (!$is_logged): ?>
                    <div class="flash flash-info" style="margin-bottom:20px;">
                        <i class="fas fa-info-circle"></i>
                        <span>
                            <?= e(t('checkout_guest')) ?> —
                            <a href="login.php" style="color:inherit;text-decoration:underline;"><?= e(t('checkout_login')) ?></a>
                            ·
                            <a href="register.php" style="color:inherit;text-decoration:underline;"><?= e(t('checkout_register')) ?></a>
                        </span>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="order_type" id="orderTypeField" value="delivery">

                <h3 style="margin-bottom:20px;"><i class="fas fa-user"></i> Contact</h3>

                <div class="form-row">
                    <div class="form-field">
                        <label for="fullName"><?= e(t('checkout_name')) ?> *</label>
                        <input type="text" id="fullName" name="customer_name" required
                               value="<?= e($customer['full_name']) ?>">
                    </div>
                    <div class="form-field">
                        <label for="phone"><?= e(t('checkout_phone')) ?> *</label>
                        <input type="tel" id="phone" name="customer_phone" required
                               value="<?= e($customer['phone']) ?>">
                    </div>
                </div>

                <div class="form-field">
                    <label for="email"><?= e(t('checkout_email')) ?></label>
                    <input type="email" id="email" name="customer_email"
                           value="<?= e($customer['email']) ?>">
                </div>

                <!-- Delivery address — shown only for delivery -->
                <div id="addressBlock">
                    <h3 style="margin:24px 0 20px;"><i class="fas fa-location-dot"></i> <?= e(t('checkout_address')) ?></h3>

                    <div class="form-field">
                        <label for="address"><?= e(t('checkout_address')) ?> *</label>
                        <input type="text" id="address" name="delivery_address"
                               placeholder="Street address">
                    </div>
                    <div class="form-row">
                        <div class="form-field">
                            <label for="city"><?= e(t('checkout_city')) ?> *</label>
                            <input type="text" id="city" name="delivery_city" value="">
                        </div>
                        <div class="form-field">
                            <label for="postal"><?= e(t('checkout_postal')) ?> *</label>
                            <input type="text" id="postal" name="delivery_postal" value="">
                        </div>
                    </div>
                </div>

                <h3 style="margin:24px 0 20px;"><i class="fas fa-credit-card"></i> <?= e(t('checkout_payment')) ?></h3>

                <div class="order-type-group">
                    <div class="order-type-option">
                        <input type="radio" id="payCash" name="payment_method" value="cash" checked>
                        <label for="payCash"><i class="fas fa-money-bill-wave"></i> <?= e(t('checkout_cash')) ?></label>
                    </div>
                    <div class="order-type-option">
                        <input type="radio" id="payCard" name="payment_method" value="card">
                        <label for="payCard"><i class="fas fa-credit-card"></i> <?= e(t('checkout_card')) ?></label>
                    </div>
                </div>

                <div class="form-field" style="margin-top:24px;">
                    <label for="notes"><?= e(t('checkout_notes')) ?></label>
                    <textarea id="notes" name="notes" rows="3" placeholder="..."></textarea>
                </div>

            </div>

            <!-- RIGHT: Order summary -->
            <div class="cart-summary">
                <h3><?= e(t('cart_title')) ?></h3>

                <div id="summaryItems" style="margin-bottom:16px;font-size:0.9rem;"></div>

                <div class="cart-summary-row">
                    <span><?= e(t('cart_subtotal')) ?></span>
                    <span id="sumSubtotal">—</span>
                </div>
                <div class="cart-summary-row" id="sumDeliveryRow">
                    <span><?= e(t('cart_delivery')) ?></span>
                    <span id="sumDelivery">—</span>
                </div>
                <div class="cart-summary-row total">
                    <span><?= e(t('cart_total')) ?></span>
                    <span id="sumTotal">—</span>
                </div>

                <button type="submit" class="btn btn-accent btn-block" style="margin-top:20px;">
                    <i class="fas fa-check"></i> <?= e(t('checkout_place')) ?>
                </button>
            </div>

            <!-- Cart data passed to order_process.php -->
            <input type="hidden" name="cart_json" id="cartJsonField" value="">
            <input type="hidden" name="currency_code" value="<?= e($current_currency) ?>">
        </form>

    </div>
</section>

<script>
(function () {
    const CART_KEY = 'afghan-cart';
    const DELIVERY_FEE_CENTS = <?= (int)(setting('delivery_fee_cents', 500)) ?>;
    const CURRENCY_CODE = <?= json_encode($current_currency) ?>;
    const RATES = {
        EUR: { symbol: '€', rate: 1.0,   decimals: 2 },
        USD: { symbol: '$', rate: 0.92,  decimals: 2 },
        AFN: { symbol: '؋', rate: 0.01,  decimals: 0 }
    };

    function getCart() {
        try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; }
        catch (e) { return []; }
    }

    function formatPrice(cents) {
        const info = RATES[CURRENCY_CODE] || RATES.EUR;
        const converted = (cents / 100) / info.rate;
        return info.symbol + converted.toFixed(info.decimals);
    }

    function render() {
        const cart = getCart();
        const itemsEl = document.getElementById('summaryItems');

        if (cart.length === 0) {
            itemsEl.innerHTML = '<p style="color:var(--text-muted);">' + <?= json_encode(t('cart_empty')) ?> + '</p>';
            document.getElementById('sumSubtotal').textContent = '—';
            document.getElementById('sumDelivery').textContent = '—';
            document.getElementById('sumTotal').textContent = '—';
            return;
        }

        let html = '';
        let subtotal = 0;
        cart.forEach(function (it) {
            const line = it.price * it.qty;
            subtotal += line;
            html += `<div style="display:flex;justify-content:space-between;padding:4px 0;">
                        <span>${it.qty}× ${escapeHtml(it.name)}</span>
                        <span>${formatPrice(line)}</span>
                    </div>`;
        });
        itemsEl.innerHTML = html;

        const orderType = document.getElementById('orderTypeField').value;
        const delivery = (orderType === 'delivery' && subtotal < 5000) ? DELIVERY_FEE_CENTS : 0;
        const total = subtotal + delivery;

        document.getElementById('sumSubtotal').textContent = formatPrice(subtotal);
        document.getElementById('sumDelivery').textContent = delivery === 0 ? '—' : formatPrice(delivery);
        document.getElementById('sumTotal').textContent = formatPrice(total);

        // Update the hidden cart JSON for submit
        document.getElementById('cartJsonField').value = JSON.stringify(cart);
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // Order type switcher
    document.querySelectorAll('[data-order-type]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-order-type]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const type = btn.getAttribute('data-order-type');
            document.getElementById('orderTypeField').value = type;

            // Show/hide address block
            const addrBlock = document.getElementById('addressBlock');
            if (type === 'delivery') {
                addrBlock.style.display = 'block';
                addrBlock.querySelectorAll('input').forEach(i => i.required = true);
            } else {
                addrBlock.style.display = 'none';
                addrBlock.querySelectorAll('input').forEach(i => i.required = false);
            }

            render();
        });
    });

    // Prevent submit if cart is empty
    document.getElementById('checkoutForm').addEventListener('submit', function (e) {
        const cart = getCart();
        if (cart.length === 0) {
            e.preventDefault();
            alert(<?= json_encode(t('cart_empty')) ?>);
            return;
        }
        document.getElementById('cartJsonField').value = JSON.stringify(cart);
    });

    render();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
