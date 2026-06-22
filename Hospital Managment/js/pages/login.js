// Register Page JavaScript
$(document).ready(function () {
  console.log("Register page loaded");

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
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
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
    console.log("Form submission started");

    // Validate form using simple validation
    if (!validateForm()) {
      return;
    }

    // Get form data
    const formData = {
      firstName: $("#firstName").val().trim(),
      lastName: $("#lastName").val().trim(),
      email: $("#email").val().toLowerCase().trim(),
      phone: $("#phone").val().trim(),
      userType: $("#userType").val(),
      password: $("#password").val(),
    };

    console.log("Form data:", formData);

    // Simulate registration process
    simulateRegistration(formData);
  });

  function validateForm() {
    console.log("Validating form...");
    let isValid = true;

    // Clear previous errors
    $(".form-control").removeClass("error");

    // Check required fields
    const requiredFields = [
      "#firstName",
      "#lastName",
      "#email",
      "#phone",
      "#userType",
      "#password",
      "#confirmPassword",
    ];

    requiredFields.forEach((field) => {
      const value = $(field).val().trim();
      if (!value) {
        $(field).addClass("error");
        isValid = false;
        console.log("Missing field:", field);
      }
    });

    if (!isValid) {
      showNotification("Please fill in all required fields", "error");
      return false;
    }

    // Email validation
    const email = $("#email").val();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      $("#email").addClass("error");
      showNotification("Please enter a valid email address", "error");
      return false;
    }

    // Password confirmation
    const password = $("#password").val();
    const confirmPassword = $("#confirmPassword").val();
    if (password !== confirmPassword) {
      $("#confirmPassword").addClass("error");
      showNotification("Passwords do not match", "error");
      return false;
    }

    // Password length
    if (password.length < 8) {
      $("#password").addClass("error");
      showNotification("Password must be at least 8 characters long", "error");
      return false;
    }

    // Terms agreement
    if (!$("#terms").is(":checked")) {
      showNotification("Please agree to the terms and conditions", "error");
      return false;
    }

    console.log("Form validation passed");
    return true;
  }

  function simulateRegistration(formData) {
    console.log("Starting registration...");

    // Show loading state
    const submitBtn = $('#registerForm button[type="submit"]');
    const originalText = submitBtn.text();
    submitBtn.html(
      '<i class="fas fa-spinner fa-spin"></i> Creating Account...'
    );
    submitBtn.prop("disabled", true);

    // Simulate API call
    setTimeout(() => {
      try {
        // Get existing users from localStorage or create empty array
        let existingUsers = [];
        const storedUsers = localStorage.getItem("users");

        if (storedUsers) {
          existingUsers = JSON.parse(storedUsers);
          console.log("Found existing users:", existingUsers);
        } else {
          console.log("No existing users found, creating new array");
        }

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
          password: formData.password,
          createdAt: new Date().toISOString(),
          isActive: true,
        };

        console.log("Creating new user:", newUser);

        // Add to users array
        existingUsers.push(newUser);

        // Save to localStorage
        localStorage.setItem("users", JSON.stringify(existingUsers));
        console.log("Users saved to localStorage");

        // Also set as current user (auto-login after registration)
        const currentUser = {
          id: newUser.id,
          name: newUser.fullName,
          email: newUser.email,
          role: newUser.userType,
          avatar: "default-avatar.png",
        };
        localStorage.setItem("user", JSON.stringify(currentUser));
        console.log("Current user set:", currentUser);

        showNotification(
          "Account created successfully! Redirecting to dashboard...",
          "success"
        );

        // Redirect to dashboard
        setTimeout(() => {
          window.location.href = "dashboard.html";
        }, 2000);
      } catch (error) {
        console.error("Registration error:", error);
        showNotification("Registration failed. Please try again.", "error");
        submitBtn.text(originalText);
        submitBtn.prop("disabled", false);
      }
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

  // Debug: Check what's in localStorage
  console.log("Current localStorage users:", localStorage.getItem("users"));
});
