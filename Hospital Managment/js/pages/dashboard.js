// Dashboard Page JavaScript
$(document).ready(function () {
  // Check if user is authenticated
  if (!requireAuth()) return;

  // Load user data
  const user = getCurrentUser();
  if (user) {
    $("#userName").text(user.name);
    $("#welcomeUser").text(user.name);
  }

  // Logout functionality
  $("#logoutBtn").on("click", function (e) {
    e.preventDefault();
    logout();
  });

  // Load dashboard data
  loadDashboardData();

  function loadDashboardData() {
    // In a real application, this would be an API call
    // For now, we'll use simulated data

    // Simulate loading
    setTimeout(() => {
      // Update stats with simulated data
      updateStats({
        appointments: 12,
        patients: 245,
        doctors: 38,
        departments: 12,
      });
    }, 500);
  }

  function updateStats(data) {
    // Update stat numbers with animation
    $(".stat-number").each(function () {
      const $this = $(this);
      const target =
        data[$(this).closest(".stat-card").find("h3").text().toLowerCase()];

      if (target) {
        animateValue($this, 0, target, 1000);
      }
    });
  }

  // Animate number counting
  function animateValue($element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
      if (!startTimestamp) startTimestamp = timestamp;
      const progress = Math.min((timestamp - startTimestamp) / duration, 1);
      $element.text(Math.floor(progress * (end - start) + start));
      if (progress < 1) {
        window.requestAnimationFrame(step);
      }
    };
    window.requestAnimationFrame(step);
  }

  // Initialize any charts or additional dashboard components here
  // This would be expanded with real chart libraries in a production environment
});
