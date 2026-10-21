/* aziz_portfolio - Visitor preferences
   Theme, font size, motion - stored in localStorage */

(function () {
    'use strict';

    var KEY_THEME   = 'aziz-theme';
    var KEY_FONT    = 'aziz-font';
    var KEY_MOTION  = 'aziz-motion';

    function applyAll() {
        var theme = localStorage.getItem(KEY_THEME) || 'light';
        document.documentElement.setAttribute('data-theme', theme);

        var font = localStorage.getItem(KEY_FONT) || 'normal';
        document.documentElement.setAttribute('data-font', font);

        var motion = localStorage.getItem(KEY_MOTION) || 'on';
        document.documentElement.setAttribute('data-motion', motion);
    }

    applyAll();

    window.prefsSetTheme = function (val) {
        localStorage.setItem(KEY_THEME, val);
        applyAll();
        syncUI();
    };

    window.prefsSetFont = function (val) {
        localStorage.setItem(KEY_FONT, val);
        applyAll();
        syncUI();
    };

    window.prefsSetMotion = function (val) {
        localStorage.setItem(KEY_MOTION, val);
        applyAll();
        syncUI();
    };

    function syncUI() {
        var theme  = localStorage.getItem(KEY_THEME) || 'light';
        var font   = localStorage.getItem(KEY_FONT) || 'normal';
        var motion = localStorage.getItem(KEY_MOTION) || 'on';

        document.querySelectorAll('[data-pref-theme]').forEach(function (el) {
            el.classList.toggle('active', el.dataset.prefTheme === theme);
        });
        document.querySelectorAll('[data-pref-font]').forEach(function (el) {
            el.classList.toggle('active', el.dataset.prefFont === font);
        });
        document.querySelectorAll('[data-pref-motion]').forEach(function (el) {
            el.classList.toggle('active', el.dataset.prefMotion === motion);
        });
    }

    document.addEventListener('click', function (e) {
        var openBtn = e.target.closest('[data-prefs-open]');
        var closeBtn = e.target.closest('[data-prefs-close]');
        var panel = document.getElementById('prefsPanel');
        if (!panel) return;

        if (openBtn) {
            e.preventDefault();
            panel.classList.add('open');
            syncUI();
        }
        if (closeBtn || e.target === panel) {
            panel.classList.remove('open');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var panel = document.getElementById('prefsPanel');
            if (panel) panel.classList.remove('open');
        }
    });

    document.addEventListener('DOMContentLoaded', syncUI);
})();
