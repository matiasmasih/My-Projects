// =========================================================
// KIRJASTO 2026 — Global JavaScript
// Theme toggle, sidebar toggle, and utilities
// =========================================================

document.addEventListener('DOMContentLoaded', function() {

    // ---------- THEME TOGGLE ----------
    const themeBtn = document.getElementById('themeBtn');
    const themeIcon = document.getElementById('themeIcon');
    const currentTheme = localStorage.getItem('kirjasto-theme') || 'light';

    // Set initial icon
    if (themeIcon) {
        themeIcon.className = currentTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }

    // Toggle on click
    if (themeBtn) {
        themeBtn.addEventListener('click', function() {
            const html = document.documentElement;
            const nextTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', nextTheme);
            localStorage.setItem('kirjasto-theme', nextTheme);
            if (themeIcon) {
                themeIcon.className = nextTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        });
    }

    // ---------- AUTO-CLOSE SIDEBAR ON MOBILE (click outside) ----------
    const sidebar = document.getElementById('adminSidebar');
    if (sidebar) {
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024) {
                const isInside = sidebar.contains(e.target);
                const isToggle = e.target.closest('.admin-sidebar-toggle');
                if (!isInside && !isToggle && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    // ---------- SMOOTH FLASH FADE ----------
    const flashMessages = document.querySelectorAll('.flash');
    flashMessages.forEach(function(flash) {
        setTimeout(function() {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity 0.4s ease';
            setTimeout(function() { flash.style.display = 'none'; }, 400);
        }, 5000);
    });

    // ---------- CONFIRM DELETE ----------
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
});
