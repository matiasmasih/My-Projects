// Doctors Page JavaScript
$(document).ready(function () {
  // Check if user is authenticated
  if (!requireAuth()) return;

  // Load user data
  const user = getCurrentUser();
  if (user) {
    $("#userName").text(user.name);
  }

  // Logout functionality
  $("#logoutBtn").on("click", function (e) {
    e.preventDefault();
    logout();
  });

  // Initialize doctors
  loadDoctors();
  updateStats();

  // Modal handlers
  $("#newDoctorBtn").on("click", function () {
    openDoctorModal();
  });

  $("#modalClose, #modalCancel").on("click", function () {
    closeDoctorModal();
  });

  $("#modalSave").on("click", function () {
    saveDoctor();
  });

  // Search and filter handlers
  $("#searchDoctors").on("input", function () {
    filterDoctors();
  });

  $("#departmentFilter, #specialtyFilter").on("change", function () {
    filterDoctors();
  });

  // Close modal when clicking outside
  $(window).on("click", function (e) {
    if ($(e.target).hasClass("modal")) {
      closeDoctorModal();
    }
  });

  function loadDoctors() {
    // Show loading state
    const grid = $("#doctorsGrid");
    grid.html(`
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading doctors...</p>
            </div>
        `);

    // Simulate API call
    setTimeout(() => {
      const doctors = getDoctors();
      displayDoctors(doctors);
    }, 1000);
  }

  function getDoctors() {
    // Get from localStorage or use sample data
    let doctors = JSON.parse(localStorage.getItem("doctors"));

    if (!doctors || doctors.length === 0) {
      // Sample data
      doctors = [
        {
          id: 1,
          doctorId: "D001",
          firstName: "Sarah",
          lastName: "Johnson",
          email: "sarah.johnson@medicare.com",
          phone: "+1 (555) 123-4567",
          department: "cardiology",
          specialty: "Cardiologist",
          experience: 12,
          education: "MD, Harvard Medical School",
          bio: "Specialized in interventional cardiology with over 12 years of experience.",
          consultationFee: 150,
          rating: 4.8,
          availability: "available",
        },
        {
          id: 2,
          doctorId: "D002",
          firstName: "Michael",
          lastName: "Chen",
          email: "michael.chen@medicare.com",
          phone: "+1 (555) 234-5678",
          department: "dermatology",
          specialty: "Dermatologist",
          experience: 8,
          education: "MD, Stanford University",
          bio: "Expert in cosmetic and medical dermatology treatments.",
          consultationFee: 120,
          rating: 4.6,
          availability: "available",
        },
        {
          id: 3,
          doctorId: "D003",
          firstName: "Emily",
          lastName: "Davis",
          email: "emily.davis@medicare.com",
          phone: "+1 (555) 345-6789",
          department: "pediatrics",
          specialty: "Pediatrician",
          experience: 10,
          education: "MD, Johns Hopkins University",
          bio: "Dedicated to providing comprehensive care for children of all ages.",
          consultationFee: 100,
          rating: 4.9,
          availability: "on_leave",
        },
        {
          id: 4,
          doctorId: "D004",
          firstName: "James",
          lastName: "Wilson",
          email: "james.wilson@medicare.com",
          phone: "+1 (555) 456-7890",
          department: "orthopedics",
          specialty: "Orthopedic Surgeon",
          experience: 15,
          education: "MD, Mayo Medical School",
          bio: "Specialized in joint replacement and sports medicine.",
          consultationFee: 200,
          rating: 4.7,
          availability: "available",
        },
      ];
      localStorage.setItem("doctors", JSON.stringify(doctors));
    }

    return doctors;
  }

  function displayDoctors(doctors) {
    const grid = $("#doctorsGrid");

    if (doctors.length === 0) {
      grid.html(`
                <div class="empty-state">
                    <i class="fas fa-user-md"></i>
                    <h3>No Doctors Found</h3>
                    <p>No doctors match your current filters.</p>
                    <button class="btn btn-primary" id="newDoctorBtnEmpty">
                        <i class="fas fa-plus"></i> Add First Doctor
                    </button>
                </div>
            `);

      $("#newDoctorBtnEmpty").on("click", function () {
        openDoctorModal();
      });
      return;
    }

    let html = "";
    doctors.forEach((doctor) => {
      const fullName = `Dr. ${doctor.firstName} ${doctor.lastName}`;
      const title = `${doctor.specialty}`;
      const department = capitalizeFirst(doctor.department);
      const availabilityText = getAvailabilityText(doctor.availability);
      const availabilityClass = `availability-${doctor.availability}`;
      const stars = generateStars(doctor.rating);

      html += `
                <div class="doctor-card" data-doctor-id="${doctor.id}">
                    <div class="doctor-header">
                        <div class="doctor-avatar">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="doctor-name">${fullName}</div>
                        <div class="doctor-title">${title}</div>
                        <div class="doctor-department">${department}</div>
                        <div class="availability-badge ${availabilityClass}">${availabilityText}</div>
                    </div>
                    <div class="doctor-body">
                        <div class="doctor-info">
                            <div class="info-item">
                                <i class="fas fa-envelope"></i>
                                <div class="info-content">
                                    <div class="info-label">Email</div>
                                    <div class="info-value">${doctor.email}</div>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <div class="info-content">
                                    <div class="info-label">Phone</div>
                                    <div class="info-value">${doctor.phone}</div>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-graduation-cap"></i>
                                <div class="info-content">
                                    <div class="info-label">Experience</div>
                                    <div class="info-value">${doctor.experience} years</div>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-star"></i>
                                <div class="info-content">
                                    <div class="info-label">Rating</div>
                                    <div class="info-value">
                                        <div class="rating">
                                            <div class="stars">${stars}</div>
                                            <span class="rating-value">${doctor.rating}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-file-medical"></i>
                                <div class="info-content">
                                    <div class="info-label">Bio</div>
                                    <div class="info-value">${doctor.bio}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="doctor-footer">
                        <div class="consultation-fee">$${doctor.consultationFee}</div>
                        <div class="doctor-actions">
                            <button class="btn btn-icon btn-secondary view-doctor" title="View Profile">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-icon btn-secondary edit-doctor" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-icon btn-danger delete-doctor" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
    });

    grid.html(html);

    // Add event handlers for action buttons
    $(".view-doctor").on("click", function () {
      const doctorId = $(this).closest(".doctor-card").data("doctor-id");
      viewDoctor(doctorId);
    });

    $(".edit-doctor").on("click", function () {
      const doctorId = $(this).closest(".doctor-card").data("doctor-id");
      editDoctor(doctorId);
    });

    $(".delete-doctor").on("click", function () {
      const doctorId = $(this).closest(".doctor-card").data("doctor-id");
      deleteDoctor(doctorId);
    });
  }

  function updateStats() {
    const doctors = getDoctors();

    // Calculate stats
    const totalDoctors = doctors.length;
    const availableDoctors = doctors.filter(
      (d) => d.availability === "available"
    ).length;
    const averageRating =
      doctors.length > 0
        ? (
            doctors.reduce((sum, doctor) => sum + doctor.rating, 0) /
            doctors.length
          ).toFixed(1)
        : 0;

    // Update UI
    $("#totalDoctors").text(totalDoctors);
    $("#availableDoctors").text(availableDoctors);
    $("#averageRating").text(averageRating);
  }

  function openDoctorModal(doctor = null) {
    const modal = $("#doctorModal");
    const form = $("#doctorForm");

    if (doctor) {
      // Edit mode
      $("#modalTitle").text("Edit Doctor");
      form[0].reset();

      // Fill form with doctor data
      $("#doctorFirstName").val(doctor.firstName);
      $("#doctorLastName").val(doctor.lastName);
      $("#doctorEmail").val(doctor.email);
      $("#doctorPhone").val(doctor.phone);
      $("#doctorDepartment").val(doctor.department);
      $("#doctorSpecialty").val(doctor.specialty);
      $("#doctorExperience").val(doctor.experience);
      $("#doctorEducation").val(doctor.education);
      $("#doctorBio").val(doctor.bio);
      $("#doctorConsultationFee").val(doctor.consultationFee);
      $("#doctorAvailability").val(doctor.availability);

      modal.data("editing", doctor.id);
    } else {
      // New mode
      $("#modalTitle").text("Add New Doctor");
      form[0].reset();
      modal.removeData("editing");
    }

    modal.addClass("show");
  }

  function closeDoctorModal() {
    $("#doctorModal").removeClass("show");
  }

  function saveDoctor() {
    const form = $("#doctorForm");

    if (!form.validateForm()) {
      showNotification("Please fill in all required fields", "error");
      return;
    }

    // Validate email
    const email = $("#doctorEmail").val();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      showNotification("Please enter a valid email address", "error");
      return;
    }

    const modal = $("#doctorModal");
    const isEditing = modal.data("editing");

    const doctorData = {
      firstName: $("#doctorFirstName").val(),
      lastName: $("#doctorLastName").val(),
      email: email,
      phone: $("#doctorPhone").val(),
      department: $("#doctorDepartment").val(),
      specialty: $("#doctorSpecialty").val(),
      experience: parseInt($("#doctorExperience").val()),
      education: $("#doctorEducation").val(),
      bio: $("#doctorBio").val(),
      consultationFee: parseFloat($("#doctorConsultationFee").val()),
      availability: $("#doctorAvailability").val(),
      rating: isEditing
        ? getDoctors().find((d) => d.id === isEditing)?.rating || 4.5
        : 4.5,
    };

    let doctors = getDoctors();

    if (isEditing) {
      // Update existing doctor
      const index = doctors.findIndex((d) => d.id === isEditing);
      if (index !== -1) {
        doctors[index] = { ...doctors[index], ...doctorData };
        showNotification("Doctor updated successfully", "success");
      }
    } else {
      // Create new doctor
      const newDoctor = {
        id: Date.now(),
        doctorId: generateDoctorId(),
        ...doctorData,
      };

      // Check if email already exists
      const emailExists = doctors.some(
        (d) => d.email === doctorData.email && d.id !== isEditing
      );
      if (emailExists) {
        showNotification("A doctor with this email already exists", "error");
        return;
      }

      doctors.push(newDoctor);
      showNotification("Doctor added successfully", "success");
    }

    // Save to localStorage
    localStorage.setItem("doctors", JSON.stringify(doctors));

    // Refresh grid and stats, then close modal
    loadDoctors();
    updateStats();
    closeDoctorModal();
  }

  function viewDoctor(doctorId) {
    const doctors = getDoctors();
    const doctor = doctors.find((d) => d.id === doctorId);

    if (doctor) {
      const fullName = `Dr. ${doctor.firstName} ${doctor.lastName}`;
      showNotification(`Viewing profile for ${fullName}`, "info");
      // In a real application, this would open a detailed profile view
    }
  }

  function editDoctor(doctorId) {
    const doctors = getDoctors();
    const doctor = doctors.find((d) => d.id === doctorId);

    if (doctor) {
      openDoctorModal(doctor);
    }
  }

  function deleteDoctor(doctorId) {
    if (
      !confirm(
        "Are you sure you want to delete this doctor? This action cannot be undone."
      )
    ) {
      return;
    }

    let doctors = getDoctors();
    doctors = doctors.filter((d) => d.id !== doctorId);

    localStorage.setItem("doctors", JSON.stringify(doctors));
    showNotification("Doctor deleted successfully", "success");
    loadDoctors();
    updateStats();
  }

  function filterDoctors() {
    const searchTerm = $("#searchDoctors").val().toLowerCase();
    const departmentFilter = $("#departmentFilter").val();
    const specialtyFilter = $("#specialtyFilter").val();

    let doctors = getDoctors();

    if (searchTerm) {
      doctors = doctors.filter(
        (doctor) =>
          doctor.firstName.toLowerCase().includes(searchTerm) ||
          doctor.lastName.toLowerCase().includes(searchTerm) ||
          doctor.specialty.toLowerCase().includes(searchTerm) ||
          doctor.department.toLowerCase().includes(searchTerm)
      );
    }

    if (departmentFilter) {
      doctors = doctors.filter(
        (doctor) => doctor.department === departmentFilter
      );
    }

    if (specialtyFilter) {
      doctors = doctors.filter((doctor) =>
        doctor.specialty.toLowerCase().includes(specialtyFilter)
      );
    }

    displayDoctors(doctors);
  }

  function generateDoctorId() {
    const doctors = getDoctors();
    const lastId =
      doctors.length > 0
        ? parseInt(doctors[doctors.length - 1].doctorId.substring(1))
        : 0;
    return `D${String(lastId + 1).padStart(3, "0")}`;
  }

  function getAvailabilityText(availability) {
    const availabilityMap = {
      available: "Available",
      unavailable: "Unavailable",
      on_leave: "On Leave",
    };
    return availabilityMap[availability] || "Unknown";
  }

  function generateStars(rating) {
    const fullStars = Math.floor(rating);
    const halfStar = rating % 1 >= 0.5;
    const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);

    let stars = "";

    // Full stars
    for (let i = 0; i < fullStars; i++) {
      stars += '<i class="fas fa-star"></i>';
    }

    // Half star
    if (halfStar) {
      stars += '<i class="fas fa-star-half-alt"></i>';
    }

    // Empty stars
    for (let i = 0; i < emptyStars; i++) {
      stars += '<i class="far fa-star"></i>';
    }

    return stars;
  }

  function capitalizeFirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
  }
});
