        </div><!-- .admin-content -->

    </div><!-- .admin-main -->

</div><!-- .admin-layout -->

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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

    // Flatpickr modern date pickers
    document.addEventListener('DOMContentLoaded', function() {
        var theme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';

        // Date only
        document.querySelectorAll('input[type="date"]').forEach(function(el) {
            flatpickr(el, {
                dateFormat: 'Y-m-d',
                allowInput: true,
                theme: theme
            });
        });

        // Date + time
        document.querySelectorAll('input[type="datetime-local"]').forEach(function(el) {
            flatpickr(el, {
                enableTime: true,
                dateFormat: 'Y-m-d H:i',
                time_24hr: true,
                allowInput: true,
                theme: theme
            });
        });

        // Time only
        document.querySelectorAll('input[type="time"]').forEach(function(el) {
            flatpickr(el, {
                enableTime: true,
                noCalendar: true,
                dateFormat: 'H:i',
                time_24hr: true,
                allowInput: true,
                theme: theme
            });
        });
    });
</script>

</body>
</html>
