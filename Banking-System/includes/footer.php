</main>

<footer style="text-align: center; padding: 24px; border-top: 1px solid var(--border); color: var(--text-muted); font-size: 0.9rem; margin-top: 60px;">
    &copy; <?= date('Y') ?> Modern Bank. All rights reserved.
</footer>

<script>
    // Theme Toggle Logic
    (function() {
        var btn = document.getElementById('themeBtn');
        var icon = document.getElementById('themeIcon');
        var currentTheme = localStorage.getItem('bank-theme') || 'light';

        // Set initial icon
        if (icon) {
            icon.className = currentTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Handle click
        if (btn) {
            btn.addEventListener('click', function() {
                var h = document.documentElement;
                var t = h.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                h.setAttribute('data-theme', t);
                localStorage.setItem('bank-theme', t);
                if (icon) {
                    icon.className = t === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
                }
            });
        }
    })();
</script>

</body>
</html>
