<?php
// patients_footer.php - Footer for the shared Patients list page
?>

    </div>
    <!-- End main-content -->
</div>
<!-- End main-container -->

<script>
// ============================================
// THEME TOGGLE
// ============================================
function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('themeIcon');
    if (html.getAttribute('data-theme') === 'dark') {
        html.removeAttribute('data-theme');
        icon.className = 'bi bi-moon-stars';
        localStorage.setItem('theme', 'light');
    } else {
        html.setAttribute('data-theme', 'dark');
        icon.className = 'bi bi-sun';
        localStorage.setItem('theme', 'dark');
    }
}

if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
    const themeIcon = document.getElementById('themeIcon');
    if (themeIcon) {
        themeIcon.className = 'bi bi-sun';
    }
}

// ============================================
// LIVE CLOCK (12-HOUR FORMAT)
// ============================================
function updateClock() {
    const clockEl = document.getElementById('clock');
    const dateEl = document.getElementById('date');
    if (clockEl && dateEl) {
        const now = new Date();
        clockEl.textContent = now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
        dateEl.textContent = now.toLocaleDateString('en-GB', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
}
setInterval(updateClock, 1000);
updateClock();
</script>
</body>
</html>
