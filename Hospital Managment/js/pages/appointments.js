// Appointments Page JavaScript
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

  // Initialize view toggle first
  initializeViewToggle();

  // Initialize appointments
  loadAppointments();
  loadFormData();

  // Modal handlers
  $("#newAppointmentBtn").on("click", function () {
    openAppointmentModal();
  });

  $("#modalClose, #modalCancel").on("click", function () {
    closeAppointmentModal();
  });

  $("#modalSave").on("click", function () {
    saveAppointment();
  });

  // Filter handlers
  $("#statusFilter, #startDate, #endDate, #searchAppointments").on(
    "change input",
    function () {
      filterAppointments();
    }
  );

  // Action buttons
  $("#exportBtn").on("click", exportAppointments);
  $("#refreshBtn").on("click", loadAppointments);

  // Close modal when clicking outside
  $(window).on("click", function (e) {
    if ($(e.target).hasClass("modal")) {
      closeAppointmentModal();
    }
  });

  function initializeViewToggle() {
    // Create view toggle buttons if they don't exist
    if ($(".view-toggle").length === 0) {
      const viewToggleHtml = `
        <div class="view-toggle">
          <button class="btn btn-secondary active" data-view="table">
            <i class="fas fa-table"></i> Table View
          </button>
          <button class="btn btn-secondary" data-view="cards">
            <i class="fas fa-th-large"></i> Card View
          </button>
        </div>
      `;
      $(".filters").after(viewToggleHtml);
    }

    // Create cards view container if it doesn't exist
    if ($(".cards-view").length === 0) {
      const cardsViewHtml = `
        <div class="cards-view">
          <div class="appointment-cards" id="appointmentsCards">
            <!-- Cards will be loaded here dynamically -->
          </div>
        </div>
      `;
      $(".table-view").after(cardsViewHtml);
    }

    // View toggle event handlers
    $(".view-toggle").on("click", ".btn", function () {
      const viewType = $(this).data("view");

      // Update active button
      $(".view-toggle .btn").removeClass("active");
      $(this).addClass("active");

      // Show/hide views
      if (viewType === "table") {
        $(".table-view").addClass("active");
        $(".cards-view").removeClass("active");
      } else {
        $(".table-view").removeClass("active");
        $(".cards-view").addClass("active");
      }
    });
  }

  function loadAppointments() {
    // Show loading state
    const tbody = $("#appointmentsTable tbody");
    tbody.html(`
            <tr>
                <td colspan="6" class="empty-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading appointments...</p>
                </td>
            </tr>
        `);

    // Also show loading in cards view if it exists
    if ($("#appointmentsCards").length) {
      $("#appointmentsCards").html(`
        <div class="empty-state">
          <i class="fas fa-spinner fa-spin"></i>
          <p>Loading appointments...</p>
        </div>
      `);
    }

    // Simulate API call
    setTimeout(() => {
      const appointments = getAppointments();
      displayAppointments(appointments);
    }, 1000);
  }

  function getAppointments() {
    // Get from localStorage or use sample data
    let appointments = JSON.parse(localStorage.getItem("appointments"));

    if (!appointments || appointments.length === 0) {
      // Sample data
      appointments = [
        {
          id: 1,
          patientId: 1,
          patientName: "John Smith",
          doctorId: 1,
          doctorName: "Dr. Sarah Johnson",
          department: "Cardiology",
          date: "2024-06-15",
          time: "10:30",
          reason: "Regular checkup",
          status: "confirmed",
        },
        {
          id: 2,
          patientId: 2,
          patientName: "Maria Garcia",
          doctorId: 2,
          doctorName: "Dr. Michael Chen",
          department: "Dermatology",
          date: "2024-06-16",
          time: "14:15",
          reason: "Skin consultation",
          status: "scheduled",
        },
        {
          id: 3,
          patientId: 3,
          patientName: "Robert Wilson",
          doctorId: 3,
          doctorName: "Dr. Emily Davis",
          department: "Pediatrics",
          date: "2024-06-14",
          time: "09:00",
          reason: "Child vaccination",
          status: "completed",
        },
      ];
      localStorage.setItem("appointments", JSON.stringify(appointments));
    }

    return appointments;
  }

  function displayAppointments(appointments) {
    displayAppointmentsTable(appointments);
    displayAppointmentsCards(appointments);
  }

  function displayAppointmentsTable(appointments) {
    const tbody = $("#appointmentsTable tbody");

    if (appointments.length === 0) {
      tbody.html(`
                <tr>
                    <td colspan="6" class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Appointments Found</h3>
                        <p>No appointments match your current filters.</p>
                    </td>
                </tr>
            `);
      return;
    }

    let html = "";
    appointments.forEach((appointment) => {
      html += `
                <tr>
                    <td>${appointment.patientName}</td>
                    <td>${appointment.doctorName}</td>
                    <td>${appointment.department}</td>
                    <td>${formatDate(appointment.date)} at ${
        appointment.time
      }</td>
                    <td><span class="status-badge status-${
                      appointment.status
                    }">${appointment.status}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-secondary edit-appointment" data-id="${
                              appointment.id
                            }">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-appointment" data-id="${
                              appointment.id
                            }">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
    });

    tbody.html(html);

    // Add event handlers for action buttons
    $(".edit-appointment").on("click", function () {
      const appointmentId = $(this).data("id");
      editAppointment(appointmentId);
    });

    $(".delete-appointment").on("click", function () {
      const appointmentId = $(this).data("id");
      deleteAppointment(appointmentId);
    });
  }

  function displayAppointmentsCards(appointments) {
    const cardsContainer = $("#appointmentsCards");

    if (!cardsContainer.length) {
      return; // Cards container doesn't exist yet
    }

    if (appointments.length === 0) {
      cardsContainer.html(`
        <div class="empty-state">
          <i class="fas fa-calendar-times"></i>
          <h3>No Appointments Found</h3>
          <p>No appointments match your current filters.</p>
        </div>
      `);
      return;
    }

    let html = "";
    appointments.forEach((appointment) => {
      html += `
        <div class="appointment-card">
          <div class="card-header">
            <div class="patient-info">
              <h4>${appointment.patientName}</h4>
              <small>ID: ${appointment.patientId}</small>
            </div>
            <span class="status-badge status-${appointment.status}">${
        appointment.status
      }</span>
          </div>
          <div class="card-body">
            <div class="appointment-details">
              <div class="detail-item">
                <i class="fas fa-user-md"></i>
                <span>${appointment.doctorName}</span>
              </div>
              <div class="detail-item">
                <i class="fas fa-stethoscope"></i>
                <span>${appointment.department}</span>
              </div>
              <div class="detail-item">
                <i class="fas fa-calendar-alt"></i>
                <span>${formatDate(appointment.date)}</span>
              </div>
              <div class="detail-item">
                <i class="fas fa-clock"></i>
                <span>${appointment.time}</span>
              </div>
              ${
                appointment.reason
                  ? `
              <div class="detail-item">
                <i class="fas fa-file-medical"></i>
                <span>${appointment.reason}</span>
              </div>
              `
                  : ""
              }
            </div>
          </div>
          <div class="card-footer">
            <div class="action-buttons">
              <button class="btn btn-sm btn-primary edit-appointment-card" data-id="${
                appointment.id
              }">
                <i class="fas fa-edit"></i> Edit
              </button>
              <button class="btn btn-sm btn-danger delete-appointment-card" data-id="${
                appointment.id
              }">
                <i class="fas fa-trash"></i> Delete
              </button>
            </div>
          </div>
        </div>
      `;
    });

    cardsContainer.html(html);

    // Add event handlers for card action buttons
    $(".edit-appointment-card").on("click", function () {
      const appointmentId = $(this).data("id");
      editAppointment(appointmentId);
    });

    $(".delete-appointment-card").on("click", function () {
      const appointmentId = $(this).data("id");
      deleteAppointment(appointmentId);
    });
  }

  function loadFormData() {
    // Load patients, doctors, and departments for dropdowns
    const patients = [
      { id: 1, name: "John Smith" },
      { id: 2, name: "Maria Garcia" },
      { id: 3, name: "Robert Wilson" },
      { id: 4, name: "Sarah Johnson" },
    ];

    const doctors = [
      { id: 1, name: "Dr. Sarah Johnson", department: "Cardiology" },
      { id: 2, name: "Dr. Michael Chen", department: "Dermatology" },
      { id: 3, name: "Dr. Emily Davis", department: "Pediatrics" },
      { id: 4, name: "Dr. James Wilson", department: "Orthopedics" },
    ];

    const departments = [
      "Cardiology",
      "Dermatology",
      "Pediatrics",
      "Orthopedics",
      "Neurology",
      "Oncology",
    ];

    // Populate dropdowns
    const patientSelect = $("#modalPatient");
    patientSelect.html('<option value="">Select Patient</option>');
    patients.forEach((patient) => {
      patientSelect.append(
        `<option value="${patient.id}">${patient.name}</option>`
      );
    });

    const doctorSelect = $("#modalDoctor");
    doctorSelect.html('<option value="">Select Doctor</option>');
    doctors.forEach((doctor) => {
      doctorSelect.append(
        `<option value="${doctor.id}">${doctor.name} - ${doctor.department}</option>`
      );
    });

    const departmentSelect = $("#modalDepartment");
    departmentSelect.html('<option value="">Select Department</option>');
    departments.forEach((dept) => {
      departmentSelect.append(`<option value="${dept}">${dept}</option>`);
    });
  }

  function openAppointmentModal(appointment = null) {
    const modal = $("#appointmentModal");
    const form = $("#appointmentForm");

    if (appointment) {
      // Edit mode
      $("#modalTitle").text("Edit Appointment");
      form[0].reset();

      // Fill form with appointment data
      $("#modalPatient").val(appointment.patientId);
      $("#modalDoctor").val(appointment.doctorId);
      $("#modalDepartment").val(appointment.department);
      $("#modalDate").val(appointment.date);
      $("#modalTime").val(appointment.time);
      $("#modalReason").val(appointment.reason);
      $("#modalStatus").val(appointment.status);

      modal.data("editing", appointment.id);
    } else {
      // New mode
      $("#modalTitle").text("New Appointment");
      form[0].reset();
      modal.removeData("editing");
    }

    modal.addClass("show");
  }

  function closeAppointmentModal() {
    $("#appointmentModal").removeClass("show");
  }

  function saveAppointment() {
    const form = $("#appointmentForm");

    if (!form.validateForm()) {
      showNotification("Please fill in all required fields", "error");
      return;
    }

    const modal = $("#appointmentModal");
    const isEditing = modal.data("editing");

    const appointmentData = {
      patientId: $("#modalPatient").val(),
      patientName: $("#modalPatient option:selected").text(),
      doctorId: $("#modalDoctor").val(),
      doctorName: $("#modalDoctor option:selected").text().split(" - ")[0],
      department: $("#modalDepartment").val(),
      date: $("#modalDate").val(),
      time: $("#modalTime").val(),
      reason: $("#modalReason").val(),
      status: $("#modalStatus").val(),
    };

    let appointments = getAppointments();

    if (isEditing) {
      // Update existing appointment
      const index = appointments.findIndex((a) => a.id === isEditing);
      if (index !== -1) {
        appointments[index] = { ...appointments[index], ...appointmentData };
        showNotification("Appointment updated successfully", "success");
      }
    } else {
      // Create new appointment
      const newAppointment = {
        id: Date.now(),
        ...appointmentData,
      };
      appointments.push(newAppointment);
      showNotification("Appointment created successfully", "success");
    }

    // Save to localStorage
    localStorage.setItem("appointments", JSON.stringify(appointments));

    // Refresh table and close modal
    loadAppointments();
    closeAppointmentModal();
  }

  function editAppointment(appointmentId) {
    const appointments = getAppointments();
    const appointment = appointments.find((a) => a.id === appointmentId);

    if (appointment) {
      openAppointmentModal(appointment);
    }
  }

  function deleteAppointment(appointmentId) {
    if (!confirm("Are you sure you want to delete this appointment?")) {
      return;
    }

    let appointments = getAppointments();
    appointments = appointments.filter((a) => a.id !== appointmentId);

    localStorage.setItem("appointments", JSON.stringify(appointments));
    showNotification("Appointment deleted successfully", "success");
    loadAppointments();
  }

  function filterAppointments() {
    const statusFilter = $("#statusFilter").val();
    const startDate = $("#startDate").val();
    const endDate = $("#endDate").val();
    const searchTerm = $("#searchAppointments").val().toLowerCase();

    let appointments = getAppointments();

    // Apply filters
    if (statusFilter !== "all") {
      appointments = appointments.filter((a) => a.status === statusFilter);
    }

    if (startDate) {
      appointments = appointments.filter((a) => a.date >= startDate);
    }

    if (endDate) {
      appointments = appointments.filter((a) => a.date <= endDate);
    }

    if (searchTerm) {
      appointments = appointments.filter(
        (a) =>
          a.patientName.toLowerCase().includes(searchTerm) ||
          a.doctorName.toLowerCase().includes(searchTerm) ||
          a.department.toLowerCase().includes(searchTerm)
      );
    }

    displayAppointments(appointments);
  }

  function exportAppointments() {
    // Simple export implementation
    const appointments = getAppointments();
    const csvContent =
      "data:text/csv;charset=utf-8," +
      "Patient,Doctor,Department,Date,Time,Status,Reason\n" +
      appointments
        .map(
          (a) =>
            `"${a.patientName}","${a.doctorName}","${a.department}","${a.date}","${a.time}","${a.status}","${a.reason}"`
        )
        .join("\n");

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "appointments.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    showNotification("Appointments exported successfully", "success");
  }

  function formatDate(dateString) {
    const options = { year: "numeric", month: "short", day: "numeric" };
    return new Date(dateString).toLocaleDateString("en-US", options);
  }
});
