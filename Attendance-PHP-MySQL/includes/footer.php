</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-inner">

            <div>
                <div class="footer-logo">
                    <i class="fas fa-clock"></i>
                    <span>Attendance System</span>
                </div>
                <p class="footer-tagline">
                    Modern attendance management for teams and businesses.
                    Track clock-in, clock-out, shifts, and reports — all in one place.
                </p>
            </div>

            <div>
                <h4 class="footer-col-title">Quick Links</h4>
                <div class="footer-links">
                    <a href="<?= e(SITE_URL) ?>index.php">Home</a>
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <a href="<?= e(SITE_URL) ?>logout.php">Logout</a>
                    <?php else: ?>
                        <a href="<?= e(SITE_URL) ?>login.php">Login</a>
                        <a href="<?= e(SITE_URL) ?>register.php">Register</a>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <h4 class="footer-col-title">Contact</h4>
                <div class="footer-links">
                    <a href="mailto:info@attendance.local">
                        <i class="fas fa-envelope"></i> info@attendance.local
                    </a>
                    <a href="tel:+358401234567">
                        <i class="fas fa-phone"></i> +358 40 123 4567
                    </a>
                </div>
            </div>

        </div>

        <div class="footer-bottom">
            &copy; <?= date('Y') ?> Attendance System. All rights reserved.
        </div>
    </div>
</footer>

<script>
    // Theme toggle
    (function() {
        var btn = document.getElementById('themeBtn');
        var icon = document.getElementById('themeIcon');
        var currentTheme = localStorage.getItem('attendance-theme') || 'light';

        if (icon) icon.className = currentTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';

        if (btn) {
            btn.addEventListener('click', function() {
                var h = document.documentElement;
                var t = h.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                h.setAttribute('data-theme', t);
                localStorage.setItem('attendance-theme', t);
                if (icon) icon.className = t === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            });
        }
    })();

    // Dropdowns
    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.dropdown-toggle');
        var insideMenu = e.target.closest('.dropdown-menu');
        if (toggle && toggle.closest('.dropdown') && !toggle.classList.contains('user-avatar')) {
            // handled below
        }
        if (toggle && toggle.closest('.dropdown')) {
            var dd = toggle.closest('.dropdown');
            var wasOpen = dd.classList.contains('open');
            document.querySelectorAll('.dropdown.open').forEach(function(d) { d.classList.remove('open'); });
            if (!wasOpen) dd.classList.add('open');
            return;
        }
        if (!insideMenu) {
            document.querySelectorAll('.dropdown.open').forEach(function(d) { d.classList.remove('open'); });
        }
    });
</script>

</body>
</html>
