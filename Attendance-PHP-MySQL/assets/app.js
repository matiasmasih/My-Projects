// assets/app.js — Global Theme Toggle & UI Logic

document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');

    // 1. Get current theme (default to light)
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';

    // 2. Update the icon based on the current theme
    function updateIcon(theme) {
        if (themeIcon) {
            if (theme === 'dark') {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
        }
    }

    updateIcon(currentTheme);

    // 3. Handle the click event
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';

            // Change the HTML attribute
            document.documentElement.setAttribute('data-theme', next);

            // Save to localStorage
            localStorage.setItem('theme', next);

            // Update the icon
            updateIcon(next);
        });
    }
});
