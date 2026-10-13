/* =========================================================
   aziz_portfolio — App JS
   Theme toggle, dropdowns, mobile nav
   ========================================================= */

(function () {
    'use strict';

    /* ---------- THEME ---------- */
    const THEME_KEY = 'aziz-theme';

    function getTheme() {
        return localStorage.getItem(THEME_KEY) || 'light';
    }

    function setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);

        // Update any theme dropdown labels
        document.querySelectorAll('[data-theme-label]').forEach(function (el) {
            el.textContent = theme === 'dark' ? '🌙 Dark' : '☀️ Light';
        });

        // Update active state in theme menu
        document.querySelectorAll('[data-theme-option]').forEach(function (el) {
            el.classList.toggle('active', el.dataset.themeOption === theme);
        });
    }

    // Apply saved theme immediately (before paint if possible)
    setTheme(getTheme());

    // Public API — used by buttons in HTML
    window.toggleTheme = function (mode) {
        setTheme(mode);
    };

    /* ---------- DROPDOWNS ---------- */
    function closeAllDropdowns(except) {
        document.querySelectorAll('.dropdown.open').forEach(function (d) {
            if (d !== except) d.classList.remove('open');
        });
    }

    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('.dropdown-toggle');
        if (toggle) {
            const dropdown = toggle.closest('.dropdown');
            if (!dropdown) return;  // safety net — toggle without .dropdown parent
            const wasOpen = dropdown.classList.contains('open');
            closeAllDropdowns();
            if (!wasOpen) dropdown.classList.add('open');
            e.preventDefault();
            return;
        }

        // Click outside — close all
        if (!e.target.closest('.dropdown-menu')) {
            closeAllDropdowns();
        }
    });

    // Close dropdowns on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAllDropdowns();
    });

})();
