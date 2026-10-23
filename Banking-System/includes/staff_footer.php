        </div><!-- /.admin-content -->
    </div><!-- /.admin-main -->
</div><!-- /.admin-layout -->

<script>
    // Theme Toggle Logic
    (function() {
        var btn = document.getElementById('themeBtn');
        var icon = document.getElementById('themeIcon');
        var currentTheme = localStorage.getItem('bank-theme') || 'light';

        if (icon) {
            icon.className = currentTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

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
