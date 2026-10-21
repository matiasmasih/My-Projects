/* =========================================================
   Afghan Restaurant — Frontend JS
   Handles: theme toggle, dropdowns, mobile nav, cart, toasts
   ========================================================= */

(function () {
    'use strict';

    /* ---------- THEME TOGGLE ---------- */
    const THEME_KEY = 'afghan-theme';

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);

        // Update all theme toggle icons
        document.querySelectorAll('[data-theme-toggle] i').forEach(function (icon) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });
    }

    function initTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(saved || (prefersDark ? 'dark' : 'light'));
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-theme-toggle]');
        if (!btn) return;
        const current = document.documentElement.getAttribute('data-theme');
        applyTheme(current === 'dark' ? 'light' : 'dark');
    });

    initTheme();

    /* ---------- DROPDOWNS ---------- */
    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('.dropdown-toggle');
        const insideMenu = e.target.closest('.dropdown-menu');

        if (toggle) {
            const dropdown = toggle.closest('.dropdown');
            const wasOpen = dropdown.classList.contains('open');

            document.querySelectorAll('.dropdown.open').forEach(function (d) {
                d.classList.remove('open');
            });

            if (!wasOpen) dropdown.classList.add('open');
            return;
        }

        if (!insideMenu) {
            document.querySelectorAll('.dropdown.open').forEach(function (d) {
                d.classList.remove('open');
            });
        }
    });

    /* ---------- MOBILE NAV ---------- */
    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('[data-nav-toggle]');
        if (!toggle) return;

        const menu = document.getElementById('navMenu');
        if (menu) menu.classList.toggle('open');
    });

    /* ---------- CART (localStorage) ---------- */
    const CART_KEY = 'afghan-cart';

    function getCart() {
        try {
            return JSON.parse(localStorage.getItem(CART_KEY)) || [];
        } catch (e) {
            return [];
        }
    }

    function saveCart(cart) {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
        updateCartBadge();
    }

    function updateCartBadge() {
        const cart = getCart();
        const count = cart.reduce(function (sum, item) {
            return sum + (parseInt(item.qty, 10) || 0);
        }, 0);

        document.querySelectorAll('.nav-cart').forEach(function (el) {
            let badge = el.querySelector('.nav-cart-count');

            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'nav-cart-count';
                    el.appendChild(badge);
                }
                badge.textContent = count;
            } else if (badge) {
                badge.remove();
            }
        });
    }

    /* ---------- ADD TO CART ---------- */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-add-to-cart]');
        if (!btn) return;

        const id    = btn.getAttribute('data-id');
        const name  = btn.getAttribute('data-name');
        const price = parseInt(btn.getAttribute('data-price'), 10);
        const image = btn.getAttribute('data-image') || '';

        if (!id || !name || isNaN(price)) return;

        const cart = getCart();
        const existing = cart.find(function (it) { return String(it.id) === String(id); });

        if (existing) {
            existing.qty += 1;
        } else {
            cart.push({ id: id, name: name, price: price, image: image, qty: 1 });
        }

        saveCart(cart);
        toast(name + ' — added to cart');
    });

    /* ---------- TOASTS ---------- */
    function toast(message) {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            Object.assign(container.style, {
                position: 'fixed',
                bottom: '20px',
                right: '20px',
                zIndex: '9999',
                display: 'flex',
                flexDirection: 'column',
                gap: '10px',
                maxWidth: '320px',
            });
            document.body.appendChild(container);
        }

        const el = document.createElement('div');
        el.textContent = message;
        Object.assign(el.style, {
            padding: '12px 18px',
            borderRadius: '10px',
            background: '#1c1917',
            color: '#fef9ec',
            fontSize: '0.9rem',
            fontWeight: '500',
            boxShadow: '0 4px 20px rgba(0,0,0,0.2)',
            opacity: '0',
            transform: 'translateY(10px)',
            transition: 'all 0.25s ease',
        });

        container.appendChild(el);
        requestAnimationFrame(function () {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        });

        setTimeout(function () {
            el.style.opacity = '0';
            el.style.transform = 'translateY(10px)';
            setTimeout(function () { el.remove(); }, 300);
        }, 2500);
    }

    /* ---------- INIT BADGE ON LOAD ---------- */
    updateCartBadge();

})();
