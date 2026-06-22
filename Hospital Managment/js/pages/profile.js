// Profile Page JavaScript
$(document).ready(function () {
  // Check if user is authenticated
  if (!requireAuth()) return;

  // Load user data
  const user = getCurrentUser();
  if (user) {
    $("#userName").text(user.name);
    loadProfileData();
  }

  // Logout functionality
  $("#logoutBtn").on("click", function (e) {
    e.preventDefault();
    logout();
  });

  // Tab navigation
  $(".nav-item").on("click", function (e) {
    e.preventDefault();

    // Update active tab
    $(".nav-item").removeClass("active");
    $(this).addClass("active");

    // Show corresponding content
    const tab = $(this).data("tab");
    $(".tab-content").removeClass("active");
    $(`#${tab}Tab`).addClass("active");
  });

  // Edit profile modal
  $("#editProfileBtn").on("click", function () {
    openEditProfileModal();
  });

  $("#editModalClose, #editModalCancel").on("click", function () {
    closeEditProfileModal();
  });

  $("#editModalSave").on("click", function () {
    saveProfileChanges();
  });

  // Change password form
  $("#changePasswordForm").on("submit", function (e) {
    e.preventDefault();
    changePassword();
  });

  // Profile Image Upload Functionality
  initializeProfileImageUpload();

  // Close modal when clicking outside
  $(window).on("click", function (e) {
    if ($(e.target).hasClass("modal")) {
      closeEditProfileModal();
    }
  });

  function initializeProfileImageUpload() {
    // Main profile image upload
    const profileImage = $("#profileHeaderImageContainer");
    const profileImagePreview = $("#profileHeaderImage");
    const profileImageInput = $("#profileImageInput");
    const editImageBtn = $("#editAvatarBtn");
    const imageActions = $("#imageActions");
    const saveImageBtn = $("#saveImageBtn");
    const cancelImageBtn = $("#cancelImageBtn");

    // Modal profile image upload
    const modalProfileImage = $("#modalProfileImage");
    const modalProfileImagePreview = $("#modalProfileImagePreview");
    const modalProfileImageInput = $("#modalProfileImageInput");
    const modalEditImageBtn = $("#modalEditImageBtn");
    const modalImageActions = $("#modalImageActions");
    const modalSaveImageBtn = $("#modalSaveImageBtn");
    const modalCancelImageBtn = $("#modalCancelImageBtn");

    // Load saved profile image
    const savedImage = localStorage.getItem("profileImage");
    if (savedImage) {
      updateProfileImages(savedImage);
    }

    // Main profile image click handlers
    editImageBtn.on("click", function () {
      profileImageInput.click();
    });

    profileImage.on("click", function () {
      profileImageInput.click();
    });

    // Main profile file input change
    profileImageInput.on("change", function (e) {
      handleImageUpload(
        e,
        profileImagePreview,
        imageActions,
        profileImage.find("i")
      );
    });

    // Main profile save image
    saveImageBtn.on("click", function () {
      const imageSrc = profileImagePreview.attr("src");
      if (imageSrc) {
        saveProfileImage(imageSrc);
        imageActions.hide();
      }
    });

    // Main profile cancel image
    cancelImageBtn.on("click", function () {
      cancelImageUpload(
        profileImagePreview,
        imageActions,
        profileImage.find("i")
      );
      profileImageInput.val("");
    });

    // Modal profile image click handlers
    modalEditImageBtn.on("click", function () {
      modalProfileImageInput.click();
    });

    modalProfileImage.on("click", function () {
      modalProfileImageInput.click();
    });

    // Modal profile file input change
    modalProfileImageInput.on("change", function (e) {
      handleImageUpload(
        e,
        modalProfileImagePreview,
        modalImageActions,
        modalProfileImage.find("i")
      );
    });

    // Modal profile save image
    modalSaveImageBtn.on("click", function () {
      const imageSrc = modalProfileImagePreview.attr("src");
      if (imageSrc) {
        saveProfileImage(imageSrc);
        modalImageActions.hide();
      }
    });

    // Modal profile cancel image
    modalCancelImageBtn.on("click", function () {
      cancelImageUpload(
        modalProfileImagePreview,
        modalImageActions,
        modalProfileImage.find("i")
      );
      modalProfileImageInput.val("");
    });
  }

  function handleImageUpload(e, previewElement, actionsElement, iconElement) {
    const file = e.target.files[0];
    if (file) {
      // Validate file type
      if (!file.type.startsWith("image/")) {
        showNotification(
          "Please select an image file (JPEG, PNG, etc.)",
          "error"
        );
        return;
      }

      // Validate file size (5MB limit)
      if (file.size > 5 * 1024 * 1024) {
        showNotification("Image size should be less than 5MB", "error");
        return;
      }

      const reader = new FileReader();
      reader.onload = function (e) {
        previewElement.attr("src", e.target.result).show();
        iconElement.hide();
        actionsElement.show();
      };
      reader.onerror = function () {
        showNotification("Error reading image file", "error");
      };
      reader.readAsDataURL(file);
    }
  }

  function saveProfileImage(imageSrc) {
    localStorage.setItem("profileImage", imageSrc);
    updateProfileImages(imageSrc);
    showNotification("Profile image updated successfully", "success");
  }

  function updateProfileImages(imageSrc) {
    // Update main profile image
    $("#profileHeaderImage").attr("src", imageSrc).show();
    $("#profileHeaderIcon").hide();

    // Update modal profile image
    $("#modalProfileImagePreview").attr("src", imageSrc).show();
    $("#modalProfileImage").find("i").hide();
  }

  function cancelImageUpload(previewElement, actionsElement, iconElement) {
    const savedImage = localStorage.getItem("profileImage");
    if (savedImage) {
      previewElement.attr("src", savedImage).show();
      iconElement.hide();
    } else {
      previewElement.hide();
      iconElement.show();
    }
    actionsElement.hide();
  }

  function loadProfileData() {
    const user = getCurrentUser();
    const profileData =
      JSON.parse(localStorage.getItem("profileData")) ||
      getDefaultProfileData();

    // Update header
    $("#profileUserName").text(user.name);
    $("#profileUserRole").text(
      user.role
        ? user.role.charAt(0).toUpperCase() + user.role.slice(1)
        : "Patient"
    );
    $("#profileUserEmail").text(user.email);

    // Update personal info
    $("#infoFullName").text(profileData.fullName);
    $("#infoDob").text(profileData.dob);
    $("#infoGender").text(profileData.gender);
    $("#infoBloodGroup").text(profileData.bloodGroup);
    $("#infoEmail").text(profileData.email);
    $("#infoPhone").text(profileData.phone);
    $("#infoAddress").text(profileData.address);
    $("#infoEmergencyName").text(profileData.emergencyName);
    $("#infoEmergencyRelation").text(profileData.emergencyRelation);
    $("#infoEmergencyPhone").text(profileData.emergencyPhone);

    // Load medical data
    loadMedicalData();

    // Load appointments
    loadProfileAppointments();
  }

  function getDefaultProfileData() {
    const user = getCurrentUser();
    return {
      fullName: user.name || "John Doe",
      dob: "January 15, 1985",
      gender: "Male",
      bloodGroup: "O+",
      email: user.email || "john.doe@example.com",
      phone: "+1 (555) 123-4567",
      address: "123 Main Street, New York, NY 10001",
      emergencyName: "Jane Doe",
      emergencyRelation: "Spouse",
      emergencyPhone: "+1 (555) 123-4568",
    };
  }

  function loadMedicalData() {
    const medicalData =
      JSON.parse(localStorage.getItem("medicalData")) ||
      getDefaultMedicalData();

    // Load allergies
    const allergiesList = $("#allergiesList");
    if (medicalData.allergies.length === 0) {
      allergiesList.html(
        '<div class="empty-state"><i class="fas fa-ban"></i><h4>No Allergies</h4><p>No allergies recorded</p></div>'
      );
    } else {
      allergiesList.html(
        medicalData.allergies
          .map(
            (allergy) => `
        <div class="medical-item">
          <div class="medical-name">${allergy.name}</div>
          <div class="medical-status status-active">${allergy.severity}</div>
        </div>
      `
          )
          .join("")
      );
    }

    // Load medications
    const medicationsList = $("#medicationsList");
    if (medicalData.medications.length === 0) {
      medicationsList.html(
        '<div class="empty-state"><i class="fas fa-pills"></i><h4>No Medications</h4><p>No current medications</p></div>'
      );
    } else {
      medicationsList.html(
        medicalData.medications
          .map(
            (med) => `
        <div class="medical-item">
          <div>
            <div class="medical-name">${med.name}</div>
            <div class="medical-date">${med.dosage} • ${med.frequency}</div>
          </div>
          <div class="medical-status status-active">Active</div>
        </div>
      `
          )
          .join("")
      );
    }

    // Load conditions
    const conditionsList = $("#conditionsList");
    if (medicalData.conditions.length === 0) {
      conditionsList.html(
        '<div class="empty-state"><i class="fas fa-file-medical"></i><h4>No Conditions</h4><p>No medical conditions recorded</p></div>'
      );
    } else {
      conditionsList.html(
        medicalData.conditions
          .map(
            (condition) => `
        <div class="medical-item">
          <div class="medical-name">${condition.name}</div>
          <div class="medical-date">Diagnosed: ${condition.diagnosedDate}</div>
        </div>
      `
          )
          .join("")
      );
    }

    // Load recent visits
    const visitsList = $("#visitsList");
    if (medicalData.recentVisits.length === 0) {
      visitsList.html(
        '<div class="empty-state"><i class="fas fa-calendar-times"></i><h4>No Recent Visits</h4><p>No recent medical visits</p></div>'
      );
    } else {
      visitsList.html(
        medicalData.recentVisits
          .map(
            (visit) => `
        <div class="visit-item">
          <div class="visit-info">
            <h4>${visit.doctor}</h4>
            <p>${visit.department} • ${visit.reason}</p>
          </div>
          <div class="visit-date">${visit.date}</div>
        </div>
      `
          )
          .join("")
      );
    }
  }

  function getDefaultMedicalData() {
    return {
      allergies: [
        { name: "Penicillin", severity: "Severe" },
        { name: "Peanuts", severity: "Moderate" },
      ],
      medications: [
        { name: "Lisinopril", dosage: "10mg", frequency: "Once daily" },
        { name: "Atorvastatin", dosage: "20mg", frequency: "Once daily" },
      ],
      conditions: [
        { name: "Hypertension", diagnosedDate: "March 2022" },
        { name: "High Cholesterol", diagnosedDate: "March 2022" },
      ],
      recentVisits: [
        {
          doctor: "Dr. Sarah Johnson",
          department: "Cardiology",
          reason: "Regular checkup",
          date: "May 20, 2024",
        },
        {
          doctor: "Dr. Michael Chen",
          department: "Dermatology",
          reason: "Skin consultation",
          date: "April 15, 2024",
        },
      ],
    };
  }

  function loadProfileAppointments() {
    const appointments = JSON.parse(localStorage.getItem("appointments")) || [];
    const userAppointments = appointments.filter(
      (apt) =>
        apt.patientName === getCurrentUser().name ||
        apt.patientId === getCurrentUser().id
    );

    const appointmentsList = $("#profileAppointmentsList");

    if (userAppointments.length === 0) {
      appointmentsList.html(`
        <div class="empty-state">
          <i class="fas fa-calendar-times"></i>
          <h4>No Appointments</h4>
          <p>You don't have any upcoming appointments</p>
          <a href="appointments.html" class="btn btn-primary">Book Appointment</a>
        </div>
      `);
    } else {
      appointmentsList.html(
        userAppointments
          .map(
            (apt) => `
        <div class="appointment-item">
          <div class="appointment-info">
            <h4>${apt.doctorName}</h4>
            <div class="appointment-details">
              <div class="appointment-detail">
                <i class="fas fa-stethoscope"></i>
                <span>${apt.department}</span>
              </div>
              <div class="appointment-detail">
                <i class="fas fa-calendar"></i>
                <span>${formatDate(apt.date)}</span>
              </div>
              <div class="appointment-detail">
                <i class="fas fa-clock"></i>
                <span>${apt.time}</span>
              </div>
              <div class="appointment-detail">
                <i class="fas fa-file-medical"></i>
                <span>${apt.reason}</span>
              </div>
            </div>
          </div>
          <div class="appointment-actions">
            <span class="status-badge status-${apt.status}">${apt.status}</span>
            <button class="btn btn-secondary btn-sm">Reschedule</button>
            <button class="btn btn-danger btn-sm">Cancel</button>
          </div>
        </div>
      `
          )
          .join("")
      );
    }
  }

  function openEditProfileModal() {
    const profileData =
      JSON.parse(localStorage.getItem("profileData")) ||
      getDefaultProfileData();
    const form = $("#editProfileForm");

    // Fill form with current data
    const nameParts = profileData.fullName.split(" ");
    $("#editFirstName").val(nameParts[0] || "");
    $("#editLastName").val(nameParts.slice(1).join(" ") || "");
    $("#editEmail").val(profileData.email);
    $("#editPhone").val(profileData.phone);
    $("#editDob").val(convertDisplayDateToInput(profileData.dob));
    $("#editGender").val(profileData.gender.toLowerCase());
    $("#editAddress").val(profileData.address);
    $("#editBloodGroup").val(profileData.bloodGroup);
    $("#editEmergencyName").val(profileData.emergencyName);
    $("#editEmergencyRelation").val(profileData.emergencyRelation);
    $("#editEmergencyPhone").val(profileData.emergencyPhone);

    // Load current profile image in modal
    const savedImage = localStorage.getItem("profileImage");
    if (savedImage) {
      $("#modalProfileImagePreview").attr("src", savedImage).show();
      $("#modalProfileImage").find("i").hide();
    } else {
      $("#modalProfileImagePreview").hide();
      $("#modalProfileImage").find("i").show();
    }

    $("#editProfileModal").addClass("show");
  }

  function closeEditProfileModal() {
    $("#editProfileModal").removeClass("show");
  }

  function saveProfileChanges() {
    const form = $("#editProfileForm");

    if (!form.validateForm()) {
      showNotification("Please fill in all required fields", "error");
      return;
    }

    // Validate email
    const email = $("#editEmail").val();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      showNotification("Please enter a valid email address", "error");
      return;
    }

    const profileData = {
      fullName: `${$("#editFirstName").val()} ${$("#editLastName").val()}`,
      dob: convertInputDateToDisplay($("#editDob").val()),
      gender:
        $("#editGender").val().charAt(0).toUpperCase() +
        $("#editGender").val().slice(1),
      bloodGroup: $("#editBloodGroup").val(),
      email: email,
      phone: $("#editPhone").val(),
      address: $("#editAddress").val(),
      emergencyName: $("#editEmergencyName").val(),
      emergencyRelation: $("#editEmergencyRelation").val(),
      emergencyPhone: $("#editEmergencyPhone").val(),
    };

    // Save to localStorage
    localStorage.setItem("profileData", JSON.stringify(profileData));

    // Update current user data
    const user = getCurrentUser();
    user.name = profileData.fullName;
    user.email = profileData.email;
    localStorage.setItem("user", JSON.stringify(user));

    // Update UI
    loadProfileData();
    $("#userName").text(user.name);

    showNotification("Profile updated successfully", "success");
    closeEditProfileModal();
  }

  function changePassword() {
    const currentPassword = $("#currentPassword").val();
    const newPassword = $("#newPassword").val();
    const confirmPassword = $("#confirmNewPassword").val();

    if (newPassword !== confirmPassword) {
      showNotification("New passwords do not match", "error");
      return;
    }

    if (newPassword.length < 8) {
      showNotification("Password must be at least 8 characters long", "error");
      return;
    }

    // Simulate password change
    showNotification("Password changed successfully", "success");
    $("#changePasswordForm")[0].reset();
  }

  function convertDisplayDateToInput(displayDate) {
    const months = {
      january: "01",
      february: "02",
      march: "03",
      april: "04",
      may: "05",
      june: "06",
      july: "07",
      august: "08",
      september: "09",
      october: "10",
      november: "11",
      december: "12",
    };

    const parts = displayDate.toLowerCase().replace(",", "").split(" ");
    if (parts.length === 3) {
      const month = months[parts[0]];
      const day = parts[1].padStart(2, "0");
      const year = parts[2];
      return `${year}-${month}-${day}`;
    }
    return "";
  }

  function convertInputDateToDisplay(inputDate) {
    const months = [
      "January",
      "February",
      "March",
      "April",
      "May",
      "June",
      "July",
      "August",
      "September",
      "October",
      "November",
      "December",
    ];
    const parts = inputDate.split("-");
    if (parts.length === 3) {
      const year = parts[0];
      const month = months[parseInt(parts[1]) - 1];
      const day = parseInt(parts[2]);
      return `${month} ${day}, ${year}`;
    }
    return inputDate;
  }

  function formatDate(dateString) {
    const options = { year: "numeric", month: "short", day: "numeric" };
    return new Date(dateString).toLocaleDateString("en-US", options);
  }
});
