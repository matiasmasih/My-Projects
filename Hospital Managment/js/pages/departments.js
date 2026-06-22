// Departments Page JavaScript
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

  // Initialize departments
  loadDepartments();
  updateStats();
  loadDoctorsForDropdown();

  // Modal handlers
  $("#newDepartmentBtn").on("click", function () {
    openDepartmentModal();
  });

  $("#modalClose, #modalCancel").on("click", function () {
    closeDepartmentModal();
  });

  $("#modalSave").on("click", function () {
    saveDepartment();
  });

  // Close modal when clicking outside
  $(window).on("click", function (e) {
    if ($(e.target).hasClass("modal")) {
      closeDepartmentModal();
    }
  });

  function loadDepartments() {
    // Show loading state
    const grid = $("#departmentsGrid");
    grid.html(`
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading departments...</p>
            </div>
        `);

    // Simulate API call
    setTimeout(() => {
      const departments = getDepartments();
      displayDepartments(departments);
    }, 1000);
  }

  function getDepartments() {
    // Get from localStorage or use sample data
    let departments = JSON.parse(localStorage.getItem("departments"));

    if (!departments || departments.length === 0) {
      // Sample data
      departments = [
        {
          id: 1,
          name: "Cardiology",
          description: "Specialized in heart and cardiovascular system care",
          headDoctorId: 1,
          headDoctorName: "Dr. Sarah Johnson",
          location: "Main Building, 2nd Floor",
          staffCount: 15,
          totalBeds: 40,
          availableBeds: 12,
          contact: "+1 (555) 123-4001",
          status: "active",
          services: [
            "Echocardiography",
            "Cardiac Catheterization",
            "Pacemaker Implantation",
            "Heart Surgery",
          ],
        },
        {
          id: 2,
          name: "Dermatology",
          description: "Expert care for skin, hair, and nail conditions",
          headDoctorId: 2,
          headDoctorName: "Dr. Michael Chen",
          location: "West Wing, 1st Floor",
          staffCount: 8,
          totalBeds: 20,
          availableBeds: 15,
          contact: "+1 (555) 123-4002",
          status: "active",
          services: [
            "Skin Cancer Screening",
            "Cosmetic Dermatology",
            "Laser Therapy",
            "Acne Treatment",
          ],
        },
        {
          id: 3,
          name: "Pediatrics",
          description: "Comprehensive healthcare for children and adolescents",
          headDoctorId: 3,
          headDoctorName: "Dr. Emily Davis",
          location: "Children's Pavilion, 1st Floor",
          staffCount: 12,
          totalBeds: 35,
          availableBeds: 8,
          contact: "+1 (555) 123-4003",
          status: "active",
          services: [
            "Vaccinations",
            "Well-child Checkups",
            "Childhood Illness Treatment",
            "Developmental Screening",
          ],
        },
        {
          id: 4,
          name: "Orthopedics",
          description: "Specialized in musculoskeletal system and joint care",
          headDoctorId: 4,
          headDoctorName: "Dr. James Wilson",
          location: "Main Building, 3rd Floor",
          staffCount: 10,
          totalBeds: 30,
          availableBeds: 5,
          contact: "+1 (555) 123-4004",
          status: "active",
          services: [
            "Joint Replacement",
            "Sports Medicine",
            "Fracture Care",
            "Arthroscopic Surgery",
          ],
        },
        {
          id: 5,
          name: "Neurology",
          description: "Expert care for nervous system disorders",
          headDoctorId: null,
          headDoctorName: "To be assigned",
          location: "East Wing, 2nd Floor",
          staffCount: 6,
          totalBeds: 25,
          availableBeds: 18,
          contact: "+1 (555) 123-4005",
          status: "maintenance",
          services: [
            "EEG Testing",
            "Stroke Care",
            "Epilepsy Treatment",
            "Neurological Rehabilitation",
          ],
        },
        {
          id: 6,
          name: "Emergency Medicine",
          description: "24/7 emergency care for critical conditions",
          headDoctorId: null,
          headDoctorName: "Dr. Robert Martinez",
          location: "Emergency Building, Ground Floor",
          staffCount: 25,
          totalBeds: 50,
          availableBeds: 15,
          contact: "+1 (555) 123-4111",
          status: "active",
          services: [
            "Trauma Care",
            "Critical Care",
            "Emergency Surgery",
            "Toxicology",
          ],
        },
      ];
      localStorage.setItem("departments", JSON.stringify(departments));
    }

    return departments;
  }

  function displayDepartments(departments) {
    const grid = $("#departmentsGrid");

    if (departments.length === 0) {
      grid.html(`
                <div class="empty-state">
                    <i class="fas fa-building"></i>
                    <h3>No Departments Found</h3>
                    <p>No departments have been added yet.</p>
                    <button class="btn btn-primary" id="newDepartmentBtnEmpty">
                        <i class="fas fa-plus"></i> Add First Department
                    </button>
                </div>
            `);

      $("#newDepartmentBtnEmpty").on("click", function () {
        openDepartmentModal();
      });
      return;
    }

    let html = "";
    departments.forEach((department) => {
      const statusText = getStatusText(department.status);
      const statusClass = `status-${department.status}`;
      const icon = getDepartmentIcon(department.name);
      const occupancyRate = Math.round(
        ((department.totalBeds - department.availableBeds) /
          department.totalBeds) *
          100
      );

      html += `
                <div class="department-card" data-department-id="${
                  department.id
                }">
                    <div class="department-header">
                        <div class="department-icon">
                            <i class="${icon}"></i>
                        </div>
                        <div class="department-name">${department.name}</div>
                        <div class="department-head">${
                          department.headDoctorName
                        }</div>
                        <div class="status-badge ${statusClass}">${statusText}</div>
                    </div>
                    <div class="department-body">
                        <div class="department-info">
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <div class="info-content">
                                    <div class="info-label">Location</div>
                                    <div class="info-value">${
                                      department.location
                                    }</div>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <div class="info-content">
                                    <div class="info-label">Contact</div>
                                    <div class="info-value">${
                                      department.contact
                                    }</div>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-info-circle"></i>
                                <div class="info-content">
                                    <div class="info-label">Description</div>
                                    <div class="info-value">${
                                      department.description
                                    }</div>
                                </div>
                            </div>
                        </div>
                        <div class="department-services">
                            <div class="services-label">Key Services:</div>
                            <div class="services-list">
                                ${department.services
                                  .map(
                                    (service) =>
                                      `<span class="service-tag">${service}</span>`
                                  )
                                  .join("")}
                            </div>
                        </div>
                    </div>
                    <div class="department-footer">
                        <div class="department-stats">
                            <div class="stat">
                                <span class="stat-number">${
                                  department.staffCount
                                }</span>
                                <span class="stat-label">Staff</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number">${
                                  department.availableBeds
                                }/${department.totalBeds}</span>
                                <span class="stat-label">Beds</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number">${occupancyRate}%</span>
                                <span class="stat-label">Occupancy</span>
                            </div>
                        </div>
                        <div class="department-actions">
                            <button class="btn btn-icon btn-secondary view-department" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-icon btn-secondary edit-department" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-icon btn-danger delete-department" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
    });

    grid.html(html);

    // Add event handlers for action buttons
    $(".view-department").on("click", function () {
      const departmentId = $(this)
        .closest(".department-card")
        .data("department-id");
      viewDepartment(departmentId);
    });

    $(".edit-department").on("click", function () {
      const departmentId = $(this)
        .closest(".department-card")
        .data("department-id");
      editDepartment(departmentId);
    });

    $(".delete-department").on("click", function () {
      const departmentId = $(this)
        .closest(".department-card")
        .data("department-id");
      deleteDepartment(departmentId);
    });
  }

  function updateStats() {
    const departments = getDepartments();

    // Calculate stats
    const totalDepartments = departments.length;
    const totalStaff = departments.reduce(
      (sum, dept) => sum + dept.staffCount,
      0
    );
    const availableBeds = departments.reduce(
      (sum, dept) => sum + dept.availableBeds,
      0
    );

    // Update UI
    $("#totalDepartments").text(totalDepartments);
    $("#totalStaff").text(totalStaff);
    $("#availableBeds").text(availableBeds);
  }

  function loadDoctorsForDropdown() {
    const doctors = getDoctors() || [];
    const dropdown = $("#departmentHead");

    dropdown.html('<option value="">Select Head Doctor</option>');
    doctors.forEach((doctor) => {
      const fullName = `Dr. ${doctor.firstName} ${doctor.lastName}`;
      dropdown.append(
        `<option value="${doctor.id}">${fullName} - ${doctor.specialty}</option>`
      );
    });
  }

  function getDoctors() {
    return JSON.parse(localStorage.getItem("doctors")) || [];
  }

  function openDepartmentModal(department = null) {
    const modal = $("#departmentModal");
    const form = $("#departmentForm");

    if (department) {
      // Edit mode
      $("#modalTitle").text("Edit Department");
      form[0].reset();

      // Fill form with department data
      $("#departmentName").val(department.name);
      $("#departmentDescription").val(department.description);
      $("#departmentHead").val(department.headDoctorId || "");
      $("#departmentLocation").val(department.location);
      $("#departmentStaff").val(department.staffCount);
      $("#departmentBeds").val(department.totalBeds);
      $("#departmentContact").val(department.contact);
      $("#departmentStatus").val(department.status);
      $("#departmentServices").val(department.services.join(", "));

      modal.data("editing", department.id);
    } else {
      // New mode
      $("#modalTitle").text("Add New Department");
      form[0].reset();
      modal.removeData("editing");
    }

    modal.addClass("show");
  }

  function closeDepartmentModal() {
    $("#departmentModal").removeClass("show");
  }

  function saveDepartment() {
    const form = $("#departmentForm");

    if (!form.validateForm()) {
      showNotification("Please fill in all required fields", "error");
      return;
    }

    const modal = $("#departmentModal");
    const isEditing = modal.data("editing");

    const headDoctorId = $("#departmentHead").val();
    const headDoctorName = headDoctorId
      ? $("#departmentHead option:selected").text().split(" - ")[0]
      : "To be assigned";

    const departmentData = {
      name: $("#departmentName").val(),
      description: $("#departmentDescription").val(),
      headDoctorId: headDoctorId || null,
      headDoctorName: headDoctorName,
      location: $("#departmentLocation").val(),
      staffCount: parseInt($("#departmentStaff").val()),
      totalBeds: parseInt($("#departmentBeds").val()),
      availableBeds: isEditing
        ? getDepartments().find((d) => d.id === isEditing)?.availableBeds ||
          parseInt($("#departmentBeds").val())
        : parseInt($("#departmentBeds").val()),
      contact: $("#departmentContact").val(),
      status: $("#departmentStatus").val(),
      services: $("#departmentServices")
        .val()
        .split(",")
        .map((s) => s.trim())
        .filter((s) => s),
    };

    let departments = getDepartments();

    if (isEditing) {
      // Update existing department
      const index = departments.findIndex((d) => d.id === isEditing);
      if (index !== -1) {
        departments[index] = { ...departments[index], ...departmentData };
        showNotification("Department updated successfully", "success");
      }
    } else {
      // Create new department
      const newDepartment = {
        id: Date.now(),
        ...departmentData,
      };

      departments.push(newDepartment);
      showNotification("Department added successfully", "success");
    }

    // Save to localStorage
    localStorage.setItem("departments", JSON.stringify(departments));

    // Refresh grid and stats, then close modal
    loadDepartments();
    updateStats();
    closeDepartmentModal();
  }

  function viewDepartment(departmentId) {
    const departments = getDepartments();
    const department = departments.find((d) => d.id === departmentId);

    if (department) {
      showNotification(
        `Viewing details for ${department.name} department`,
        "info"
      );
      // In a real application, this would open a detailed view
    }
  }

  function editDepartment(departmentId) {
    const departments = getDepartments();
    const department = departments.find((d) => d.id === departmentId);

    if (department) {
      openDepartmentModal(department);
    }
  }

  function deleteDepartment(departmentId) {
    if (
      !confirm(
        "Are you sure you want to delete this department? This action cannot be undone."
      )
    ) {
      return;
    }

    let departments = getDepartments();
    departments = departments.filter((d) => d.id !== departmentId);

    localStorage.setItem("departments", JSON.stringify(departments));
    showNotification("Department deleted successfully", "success");
    loadDepartments();
    updateStats();
  }

  function getStatusText(status) {
    const statusMap = {
      active: "Active",
      maintenance: "Maintenance",
      closed: "Closed",
    };
    return statusMap[status] || "Unknown";
  }

  function getDepartmentIcon(departmentName) {
    const iconMap = {
      Cardiology: "fas fa-heart",
      Dermatology: "fas fa-allergies",
      Pediatrics: "fas fa-baby",
      Orthopedics: "fas fa-bone",
      Neurology: "fas fa-brain",
      "Emergency Medicine": "fas fa-ambulance",
      Radiology: "fas fa-x-ray",
      Oncology: "fas fa-band-aid",
      Psychiatry: "fas fa-brain",
      Surgery: "fas fa-procedures",
    };

    return iconMap[departmentName] || "fas fa-hospital";
  }
});
