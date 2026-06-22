// Register Page JavaScript
$(document).ready(function () {
  // Password strength indicator
  $("#password").on("input", function () {
    const password = $(this).val();
    const strengthBar = $(".strength-bar");
    const strengthText = $(".strength-text");

    // Reset
    strengthBar.removeClass("weak medium strong");

    if (password.length === 0) {
      strengthText.text("Password strength");
      return;
    }

    // Calculate strength
    let strength = 0;

    // Length check
    if (password.length >= 8) strength++;

    // Contains lowercase
    if (/[a-z]/.test(password)) strength++;

    // Contains uppercase
    if (/[A-Z]/.test(password)) strength++;

    // Contains numbers
    if (/[0-9]/.test(password)) strength++;

    // Contains special characters
    if (/[^A-Za-z0-9]/.test(password)) strength++;

    // Update UI
    if (strength <= 2) {
      strengthBar.addClass("weak");
      strengthText.text("Weak password");
    } else if (strength <= 4) {
      strengthBar.addClass("medium");
      strengthText.text("Medium strength");
    } else {
      strengthBar.addClass("strong");
      strengthText.text("Strong password");
    }
  });

  // Form submission handler
  $("#registerForm").on("submit", function (e) {
    e.preventDefault();

    // Validate form
    if (!validateForm()) {
      return;
    }

    // Get form data
    const formData = {
      firstName: $("#firstName").val(),
      lastName: $("#lastName").val(),
      email: $("#email").val().toLowerCase().trim(), // Normalize email
      phone: $("#phone").val(),
      userType: $("#userType").val(),
      password: $("#password").val(),
    };

    // Simulate registration process
    simulateRegistration(formData);
  });

  function validateForm() {
    let isValid = true;

    // Basic required field validation
    if (!$("#registerForm").validateForm()) {
      showNotification("Please fill in all required fields", "error");
      isValid = false;
    }

    // Email validation
    const email = $("#email").val();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      $("#email").addClass("error");
      showNotification("Please enter a valid email address", "error");
      isValid = false;
    }

    // Password confirmation
    const password = $("#password").val();
    const confirmPassword = $("#confirmPassword").val();
    if (password !== confirmPassword) {
      $("#confirmPassword").addClass("error");
      showNotification("Passwords do not match", "error");
      isValid = false;
    }

    // Password length
    if (password.length < 8) {
      $("#password").addClass("error");
      showNotification("Password must be at least 8 characters long", "error");
      isValid = false;
    }

    // Terms agreement
    if (!$("#terms").is(":checked")) {
      showNotification("Please agree to the terms and conditions", "error");
      isValid = false;
    }

    return isValid;
  }

  function simulateRegistration(formData) {
    // Show loading state
    const submitBtn = $('#registerForm button[type="submit"]');
    const originalText = submitBtn.text();
    submitBtn.html(
      '<i class="fas fa-spinner fa-spin"></i> Creating Account...'
    );
    submitBtn.prop("disabled", true);

    // Simulate API call
    setTimeout(() => {
      // Get existing users from localStorage
      const existingUsers = JSON.parse(localStorage.getItem("users") || "[]");

      // Check if email already exists
      const emailExists = existingUsers.some(
        (user) => user.email === formData.email
      );

      if (emailExists) {
        showNotification("Email address is already registered", "error");
        submitBtn.text(originalText);
        submitBtn.prop("disabled", false);
        return;
      }

      // Create new user object
      const newUser = {
        id: Date.now(), // Unique ID
        firstName: formData.firstName,
        lastName: formData.lastName,
        fullName: `${formData.firstName} ${formData.lastName}`,
        email: formData.email,
        phone: formData.phone,
        userType: formData.userType,
        password: formData.password, // In real app, this should be hashed
        createdAt: new Date().toISOString(),
        isActive: true,
      };

      // Add to users array
      existingUsers.push(newUser);

      // Save to localStorage
      localStorage.setItem("users", JSON.stringify(existingUsers));

      // Also set as current user (auto-login after registration)
      localStorage.setItem(
        "user",
        JSON.stringify({
          id: newUser.id,
          name: newUser.fullName,
          email: newUser.email,
          role: newUser.userType,
          avatar: "default-avatar.png",
        })
      );

      showNotification(
        "Account created successfully! Redirecting to dashboard...",
        "success"
      );

      // Redirect to dashboard
      setTimeout(() => {
        window.location.href = "dashboard.html";
      }, 2000);
    }, 1500);
  }

  // Real-time validation
  $("#confirmPassword").on("input", function () {
    const password = $("#password").val();
    const confirmPassword = $(this).val();

    if (confirmPassword && password !== confirmPassword) {
      $(this).addClass("error");
    } else {
      $(this).removeClass("error");
    }
  });

  // Remove error class on input
  $(".form-control").on("input", function () {
    $(this).removeClass("error");
  });

  // Demo credentials filler for testing
  $("#registerForm").on("dblclick", function () {
    $("#firstName").val("Demo");
    $("#lastName").val("User");
    $("#email").val("demo@medicare.com");
    $("#phone").val("+1234567890");
    $("#userType").val("patient");
    $("#password").val("password123");
    $("#confirmPassword").val("password123");
    $("#terms").prop("checked", true);
    showNotification("Demo credentials filled", "info");
  });
});
