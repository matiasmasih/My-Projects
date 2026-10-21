<?php
// includes/employee_footer.php — Close employee layout & load JS
?>
        </div> <!-- /.admin-content -->
    </main> <!-- /.admin-main -->

    <script>
        // Master Clock Script (Handles both Banner and Dashboard)
        function updateAllClocks() {
            const now = new Date();

            // Format for 12-hour with AM/PM
            const timeString = now.toLocaleTimeString('en-US', {
                hour12: true,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });

            // Format date
            const dateString = now.toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });

            // 1. Update Banner Clock (if it exists on the page)
            const bannerTime = document.getElementById('bannerTime');
            const bannerDate = document.getElementById('bannerDate');
            if (bannerTime) bannerTime.textContent = timeString;
            if (bannerDate) bannerDate.textContent = dateString;

            // 2. Update Big Dashboard Clock (if it exists on the page)
            const liveClock = document.getElementById('liveClock');
            if (liveClock) liveClock.textContent = timeString;
        }

        // Run immediately, then every 1 second
        updateAllClocks();
        setInterval(updateAllClocks, 1000);
    </script>

    <!-- Load the Theme Toggle Logic -->
    <script src="<?= SITE_URL ?>assets/app.js"></script>
</body>
</html>
