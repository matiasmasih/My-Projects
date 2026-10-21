<?php
// cart.php — Shopping cart page (client-side via localStorage)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
$page_title = t('cart_title');
require_once __DIR__ . '/includes/header.php';
?>

<section class="cart-page">
    <div class="container">

        <div class="section-header">
            <h2><?= e(t('cart_title')) ?></h2>
            <p class="section-subtitle"><?= e(t('menu_subtitle')) ?></p>
        </div>

        <div class="cart-layout" id="cartPageRoot">

            <!-- Cart list -->
            <div>
                <div class="cart-list" id="cartList"></div>

                <div id="cartEmpty" style="display:none;">
                    <div class="empty-state">
                        <i class="fas fa-cart-plus"></i>
                        <p><?= e(t('cart_empty')) ?></p>
                        <a href="menu.php" class="btn btn-accent" style="margin-top:16px;">
                            <i class=""></i> <?= e(t('cart_continue')) ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="cart-summary" id="cartSummary" style="display:none;">
                <h3><?= e(t('cart_title')) ?></h3>

                <div class="cart-summary-row">
                    <span><?= e(t('cart_subtotal')) ?></span>
                    <span id="cartSubtotal">—</span>
                </div>
                <div class="cart-summary-row">
                    <span><?= e(t('cart_delivery')) ?></span>
                    <span id="cartDelivery">—</span>
                </div>
                <div class="cart-summary-row total">
                    <span><?= e(t('cart_total')) ?></span>
                    <span id="cartTotal">—</span>
                </div>

                <div class="cart-actions">
                    <a href="menu.php" class="btn btn-outline">
                        <?= e(t('cart_continue')) ?>
                    </a>
                    <a href="checkout.php" class="btn btn-accent btn-checkout">
                        <?= e(t('cart_checkout')) ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
/* =========================================================
   CART PAGE — reads localStorage, renders, allows edit
   ========================================================= */
(function () {
    const CART_KEY = 'afghan-cart';
    const DELIVERY_FEE_CENTS = <?= (int)(setting('delivery_fee_cents', 500)) ?>;
    const CURRENCY_CODE = <?= json_encode($current_currency) ?>;
    const CURRENCY_SYMBOL = <?= json_encode($currency_symbol) ?>;

    function getCart() {
        try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; }
        catch (e) { return []; }
    }
    function saveCart(c) {
        localStorage.setItem(CART_KEY, JSON.stringify(c));
    }

    // Format cents as price with the current currency
    // Rates come from the currencies table (fetched inline below)
    const RATES = {
        EUR: { symbol: '€', rate: 1.0, decimals: 2 },
        USD: { symbol: '$', rate: 0.92, decimals: 2 },
        AFN: { symbol: '؋', rate: 0.01, decimals: 0 }
    };

    function formatPrice(cents) {
        const info = RATES[CURRENCY_CODE] || RATES.EUR;
        const converted = (cents / 100) / info.rate;
        return info.symbol + converted.toFixed(info.decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function render() {
        const cart = getCart();
        const list = document.getElementById('cartList');
        const empty = document.getElementById('cartEmpty');
        const summary = document.getElementById('cartSummary');

        if (cart.length === 0) {
            list.innerHTML = '';
            empty.style.display = 'block';
            summary.style.display = 'none';
            return;
        }

        empty.style.display = 'none';
        summary.style.display = 'block';

        let html = '';
        let subtotal = 0;

        cart.forEach(function (item, i) {
            const line = item.price * item.qty;
            subtotal += line;

            const imgHtml = item.image
                ? `<img src="uploads/menu/${encodeURIComponent(item.image)}" alt="">`
                : '<div class="cart-item-placeholder"><i class="fas fa-utensils"></i></div>';

            html += `
                <div class="cart-item">
                    <div class="cart-item-image">${imgHtml}</div>
                    <div class="cart-item-info">
                        <div class="cart-item-title">${escapeHtml(item.name)}</div>
                        <div class="cart-item-price">${formatPrice(item.price)} ${window.CURRENCY_LABEL || ''}</div>
                    </div>
                    <div class="cart-qty">
                        <button type="button" data-cart-dec="${i}">−</button>
                        <input type="number" min="1" value="${item.qty}" data-cart-qty="${i}">
                        <button type="button" data-cart-inc="${i}">+</button>
                    </div>
                    <div class="cart-item-total">${formatPrice(line)}</div>
                    <button class="cart-item-remove" data-cart-remove="${i}" title="Remove">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
        });

        list.innerHTML = html;

        const delivery = subtotal >= 5000 ? 0 : DELIVERY_FEE_CENTS;
        const total = subtotal + delivery;

        document.getElementById('cartSubtotal').textContent = formatPrice(subtotal);
        document.getElementById('cartDelivery').textContent = delivery === 0 ? '—' : formatPrice(delivery);
        document.getElementById('cartTotal').textContent = formatPrice(total);
    }

    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    // Event delegation
    document.addEventListener('click', function (e) {
        const inc = e.target.closest('[data-cart-inc]');
        const dec = e.target.closest('[data-cart-dec]');
        const rem = e.target.closest('[data-cart-remove]');

        if (inc || dec || rem) {
            const cart = getCart();

            if (inc) {
                const i = parseInt(inc.getAttribute('data-cart-inc'), 10);
                cart[i].qty += 1;
            } else if (dec) {
                const i = parseInt(dec.getAttribute('data-cart-dec'), 10);
                cart[i].qty = Math.max(1, cart[i].qty - 1);
            } else if (rem) {
                const i = parseInt(rem.getAttribute('data-cart-remove'), 10);
                cart.splice(i, 1);
            }

            saveCart(cart);
            render();
        }
    });

    document.addEventListener('change', function (e) {
        const qty = e.target.closest('[data-cart-qty]');
        if (qty) {
            const i = parseInt(qty.getAttribute('data-cart-qty'), 10);
            const cart = getCart();
            cart[i].qty = Math.max(1, parseInt(qty.value, 10) || 1);
            saveCart(cart);
            render();
        }
    });

    render();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
