// Global JavaScript for Hospital Management System

$(document).ready(function () {
  console.log("Hospital Management System initialized");

  // Initialize all functionality
  initializeThemeToggle();
  initializeMobileNavigation();
  initializeGlobalFunctions();
  updateNavigation(); // Add this to update navigation on page load

  // Theme Toggle Functionality
  function initializeThemeToggle() {
    const themeToggle = $("#themeToggle");
    const currentTheme = localStorage.getItem("theme") || "light";

    // Set initial theme
    $("body").attr("data-theme", currentTheme);
    updateThemeIcon(currentTheme);

    // Toggle theme on button click
    themeToggle.on("click", function () {
      const currentTheme = $("body").attr("data-theme");
      const newTheme = currentTheme === "light" ? "dark" : "light";

      console.log("Switching theme to:", newTheme);

      $("body").attr("data-theme", newTheme);
      localStorage.setItem("theme", newTheme);
      updateThemeIcon(newTheme);

      showNotification(`Theme switched to ${newTheme} mode`, "info");
    });

    function updateThemeIcon(theme) {
      const icon = theme === "light" ? "fa-moon" : "fa-sun";
      themeToggle.html(`<i class="fas ${icon}"></i>`);
    }
  }

  // Mobile Navigation Functionality
  function initializeMobileNavigation() {
    const mobileToggle = $("#mobileToggle");
    const nav = $("#mainNav");

    mobileToggle.on("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      console.log("Mobile toggle clicked");
      nav.toggleClass("mobile-active");

      // Toggle hamburger icon
      const icon = mobileToggle.find("i");
      if (nav.hasClass("mobile-active")) {
        icon.removeClass("fa-bars").addClass("fa-times");
      } else {
        icon.removeClass("fa-times").addClass("fa-bars");
      }
    });

    // Close mobile nav when clicking on a link
    nav.find("a").on("click", function () {
      nav.removeClass("mobile-active");
      mobileToggle.find("i").removeClass("fa-times").addClass("fa-bars");
    });

    // Close mobile nav when clicking outside
    $(document).on("click", function (e) {
      if (!$(e.target).closest("#mainNav, #mobileToggle").length) {
        nav.removeClass("mobile-active");
        mobileToggle.find("i").removeClass("fa-times").addClass("fa-bars");
      }
    });

    // Close mobile nav on escape key
    $(document).on("keyup", function (e) {
      if (e.key === "Escape") {
        nav.removeClass("mobile-active");
        mobileToggle.find("i").removeClass("fa-times").addClass("fa-bars");
      }
    });
  }

  // Global Functions
  function initializeGlobalFunctions() {
    // Form validation helper
    $.fn.extend({
      validateForm: function () {
        let isValid = true;
        $(this)
          .find(".form-control")
          .each(function () {
            if ($(this).val().trim() === "") {
              $(this).addClass("error");
              isValid = false;
            } else {
              $(this).removeClass("error");
            }
          });
        return isValid;
      },
    });

    // Notification system
    window.showNotification = function (message, type = "info") {
      // Remove any existing notifications
      $(".notification").remove();

      const notification = $(`
        <div class="notification notification-${type}">
          <span>${message}</span>
          <button class="notification-close"><i class="fas fa-times"></i></button>
        </div>
      `);

      $("body").append(notification);

      // Auto remove after 5 seconds
      setTimeout(() => {
        notification.fadeOut(300, function () {
          $(this).remove();
        });
      }, 5000);

      // Close on click
      notification.find(".notification-close").on("click", function () {
        notification.fadeOut(300, function () {
          $(this).remove();
        });
      });
    };

    // Check if user is logged in (simulated)
    window.isLoggedIn = function () {
      return localStorage.getItem("user") !== null;
    };

    // Get current user (simulated)
    window.getCurrentUser = function () {
      const user = localStorage.getItem("user");
      return user ? JSON.parse(user) : null;
    };

    // Redirect to login if not authenticated (for protected pages)
    window.requireAuth = function () {
      if (!isLoggedIn()) {
        showNotification("Please login to access this page", "warning");
        setTimeout(() => {
          window.location.href = "login.html";
        }, 1500);
        return false;
      }
      return true;
    };

    // Logout function
    window.logout = function () {
      localStorage.removeItem("user");
      showNotification("Logged out successfully", "success");
      setTimeout(() => {
        window.location.href = "index.html";
      }, 1000);
    };

    // Navigation update function - NEW FUNCTION ADDED
    window.updateNavigation = function () {
      const user = getCurrentUser();
      const nav = $(".nav ul");

      console.log("Updating navigation, user logged in:", !!user);

      // Remove existing user menu and login button
      nav.find(".user-menu").remove();
      nav.find("li:has(.btn-login)").remove();

      if (user) {
        // User is logged in - show user menu
        const userName = user.name || "User";
        nav.append(`
          <li class="user-menu">
            <a href="profile.html" class="user-link">
              <i class="fas fa-user-circle"></i>
              <span id="userName">${userName}</span>
            </a>
            <div class="dropdown-menu">
              <a href="profile.html"><i class="fas fa-user"></i> Profile</a>
              <a href="#" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
          </li>
        `);

        // Add logout functionality
        $(document).on("click", "#logoutBtn", function (e) {
          e.preventDefault();
          logout();
        });

        console.log("User menu added for:", userName);
      } else {
        // User is not logged in - show login button
        nav.append('<li><a href="login.html" class="btn-login">Login</a></li>');
        console.log("Login button added");
      }
    };

    // Test notification on load
    setTimeout(() => {
      showNotification("Welcome to MediCare Hospital System!", "info");
    }, 1000);
  }
});
