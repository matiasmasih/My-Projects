// Patients Page JavaScript
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

  // Initialize patients
  loadPatients();
  updateStats();

  // Modal handlers
  $("#newPatientBtn").on("click", function () {
    openPatientModal();
  });

  $("#modalClose, #modalCancel").on("click", function () {
    closePatientModal();
  });

  $("#modalSave").on("click", function () {
    savePatient();
  });

  // Search handler
  $("#searchPatients").on("input", function () {
    filterPatients();
  });

  // Action buttons
  $("#exportPatientsBtn").on("click", exportPatients);
  $("#refreshPatientsBtn").on("click", function () {
    loadPatients();
    updateStats();
  });

  // Close modal when clicking outside
  $(window).on("click", function (e) {
    if ($(e.target).hasClass("modal")) {
      closePatientModal();
    }
  });

  function loadPatients() {
    // Show loading state
    const grid = $("#patientsGrid");
    grid.html(`
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading patients...</p>
            </div>
        `);

    // Simulate API call
    setTimeout(() => {
      const patients = getPatients();
      displayPatients(patients);
    }, 1000);
  }

  function getPatients() {
    // Get from localStorage or use sample data
    let patients = JSON.parse(localStorage.getItem("patients"));

    if (!patients || patients.length === 0) {
      // Sample data
      patients = [
        {
          id: 1,
          patientId: "P001",
          firstName: "John",
          lastName: "Smith",
          email: "john.smith@email.com",
          phone: "+1 (555) 123-4567",
          dob: "1985-03-15",
          gender: "male",
          address: "123 Main St, New York, NY",
          emergencyContact: "Jane Smith",
          emergencyPhone: "+1 (555) 123-4568",
          medicalHistory: "Hypertension, Allergic to penicillin",
          lastVisit: "2024-05-20",
          status: "active",
        },
        {
          id: 2,
          patientId: "P002",
          firstName: "Maria",
          lastName: "Garcia",
          email: "maria.garcia@email.com",
          phone: "+1 (555) 234-5678",
          dob: "1990-07-22",
          gender: "female",
          address: "456 Oak Ave, Los Angeles, CA",
          emergencyContact: "Carlos Garcia",
          emergencyPhone: "+1 (555) 234-5679",
          medicalHistory: "Asthma, No known allergies",
          lastVisit: "2024-06-10",
          status: "active",
        },
        {
          id: 3,
          patientId: "P003",
          firstName: "Robert",
          lastName: "Wilson",
          email: "robert.wilson@email.com",
          phone: "+1 (555) 345-6789",
          dob: "1978-11-30",
          gender: "male",
          address: "789 Pine St, Chicago, IL",
          emergencyContact: "Sarah Wilson",
          emergencyPhone: "+1 (555) 345-6790",
          medicalHistory: "Diabetes type 2",
          lastVisit: "2024-05-15",
          status: "in_treatment",
        },
      ];
      localStorage.setItem("patients", JSON.stringify(patients));
    }

    return patients;
  }

  function displayPatients(patients) {
    const grid = $("#patientsGrid");

    if (patients.length === 0) {
      grid.html(`
                <div class="empty-state">
                    <i class="fas fa-user-injured"></i>
                    <h3>No Patients Found</h3>
                    <p>No patients match your current search.</p>
                    <button class="btn btn-primary" id="newPatientBtnEmpty">
                        <i class="fas fa-plus"></i> Add First Patient
                    </button>
                </div>
            `);

      $("#newPatientBtnEmpty").on("click", function () {
        openPatientModal();
      });
      return;
    }

    let html = "";
    patients.forEach((patient) => {
      const fullName = `${patient.firstName} ${patient.lastName}`;
      const age = calculateAge(patient.dob);
      const lastVisit = patient.lastVisit
        ? formatDate(patient.lastVisit)
        : "Never";

      html += `
                <div class="patient-card" data-patient-id="${patient.id}">
                    <div class="patient-header">
                        <div class="patient-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="patient-name">${fullName}</div>
                        <div class="patient-id">${patient.patientId}</div>
                    </div>
                    <div class="patient-body">
                        <div class="patient-info">
                            <div class="info-item">
                                <i class="fas fa-envelope"></i>
                                <span>${patient.email}</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <span>${patient.phone}</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-birthday-cake"></i>
                                <span>${age} years</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>${patient.address.split(",")[0]}</span>
                            </div>
                        </div>
                    </div>
                    <div class="patient-footer">
                        <div class="last-visit">
                            Last visit: ${lastVisit}
                        </div>
                        <div class="patient-actions">
                            <button class="btn btn-icon btn-secondary view-patient" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-icon btn-secondary edit-patient" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-icon btn-danger delete-patient" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
    });

    grid.html(html);

    // Add event handlers for action buttons
    $(".view-patient").on("click", function () {
      const patientId = $(this).closest(".patient-card").data("patient-id");
      viewPatient(patientId);
    });

    $(".edit-patient").on("click", function () {
      const patientId = $(this).closest(".patient-card").data("patient-id");
      editPatient(patientId);
    });

    $(".delete-patient").on("click", function () {
      const patientId = $(this).closest(".patient-card").data("patient-id");
      deletePatient(patientId);
    });
  }

  function updateStats() {
    const patients = getPatients();
    const today = new Date().toISOString().split("T")[0];

    // Calculate stats
    const totalPatients = patients.length;
    const todayAppointments = patients.filter(
      (p) => p.lastVisit === today
    ).length;
    const inTreatment = patients.filter(
      (p) => p.status === "in_treatment"
    ).length;

    // Update UI with animation
    animateValue($("#totalPatients"), 0, totalPatients, 1000);
    animateValue($("#todayAppointments"), 0, todayAppointments, 1000);
    animateValue($("#inTreatment"), 0, inTreatment, 1000);
  }

  function openPatientModal(patient = null) {
    const modal = $("#patientModal");
    const form = $("#patientForm");

    if (patient) {
      // Edit mode
      $("#modalTitle").text("Edit Patient");
      form[0].reset();

      // Fill form with patient data
      $("#patientFirstName").val(patient.firstName);
      $("#patientLastName").val(patient.lastName);
      $("#patientEmail").val(patient.email);
      $("#patientPhone").val(patient.phone);
      $("#patientDob").val(patient.dob);
      $("#patientGender").val(patient.gender);
      $("#patientAddress").val(patient.address);
      $("#patientEmergencyContact").val(patient.emergencyContact);
      $("#patientEmergencyPhone").val(patient.emergencyPhone);
      $("#patientMedicalHistory").val(patient.medicalHistory);

      modal.data("editing", patient.id);
    } else {
      // New mode
      $("#modalTitle").text("New Patient");
      form[0].reset();
      modal.removeData("editing");
    }

    modal.addClass("show");
  }

  function closePatientModal() {
    $("#patientModal").removeClass("show");
  }

  function savePatient() {
    const form = $("#patientForm");

    if (!form.validateForm()) {
      showNotification("Please fill in all required fields", "error");
      return;
    }

    // Validate email
    const email = $("#patientEmail").val();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      showNotification("Please enter a valid email address", "error");
      return;
    }

    const modal = $("#patientModal");
    const isEditing = modal.data("editing");

    const patientData = {
      firstName: $("#patientFirstName").val(),
      lastName: $("#patientLastName").val(),
      email: email,
      phone: $("#patientPhone").val(),
      dob: $("#patientDob").val(),
      gender: $("#patientGender").val(),
      address: $("#patientAddress").val(),
      emergencyContact: $("#patientEmergencyContact").val(),
      emergencyPhone: $("#patientEmergencyPhone").val(),
      medicalHistory: $("#patientMedicalHistory").val(),
      lastVisit: new Date().toISOString().split("T")[0],
      status: "active",
    };

    let patients = getPatients();

    if (isEditing) {
      // Update existing patient
      const index = patients.findIndex((p) => p.id === isEditing);
      if (index !== -1) {
        patients[index] = { ...patients[index], ...patientData };
        showNotification("Patient updated successfully", "success");
      }
    } else {
      // Create new patient
      const newPatient = {
        id: Date.now(),
        patientId: generatePatientId(),
        ...patientData,
      };

      // Check if email already exists
      const emailExists = patients.some(
        (p) => p.email === patientData.email && p.id !== isEditing
      );
      if (emailExists) {
        showNotification("A patient with this email already exists", "error");
        return;
      }

      patients.push(newPatient);
      showNotification("Patient created successfully", "success");
    }

    // Save to localStorage
    localStorage.setItem("patients", JSON.stringify(patients));

    // Refresh grid and stats, then close modal
    loadPatients();
    updateStats();
    closePatientModal();
  }

  function viewPatient(patientId) {
    const patients = getPatients();
    const patient = patients.find((p) => p.id === patientId);

    if (patient) {
      // In a real application, this would open a detailed view
      // For now, we'll show an alert with basic info
      const fullName = `${patient.firstName} ${patient.lastName}`;
      showNotification(`Viewing details for ${fullName}`, "info");
    }
  }

  function editPatient(patientId) {
    const patients = getPatients();
    const patient = patients.find((p) => p.id === patientId);

    if (patient) {
      openPatientModal(patient);
    }
  }

  function deletePatient(patientId) {
    if (
      !confirm(
        "Are you sure you want to delete this patient? This action cannot be undone."
      )
    ) {
      return;
    }

    let patients = getPatients();
    patients = patients.filter((p) => p.id !== patientId);

    localStorage.setItem("patients", JSON.stringify(patients));
    showNotification("Patient deleted successfully", "success");
    loadPatients();
    updateStats();
  }

  function filterPatients() {
    const searchTerm = $("#searchPatients").val().toLowerCase();

    let patients = getPatients();

    if (searchTerm) {
      patients = patients.filter(
        (patient) =>
          patient.firstName.toLowerCase().includes(searchTerm) ||
          patient.lastName.toLowerCase().includes(searchTerm) ||
          patient.patientId.toLowerCase().includes(searchTerm) ||
          patient.email.toLowerCase().includes(searchTerm) ||
          patient.phone.toLowerCase().includes(searchTerm)
      );
    }

    displayPatients(patients);
  }

  function exportPatients() {
    const patients = getPatients();
    const csvContent =
      "data:text/csv;charset=utf-8," +
      "Patient ID,First Name,Last Name,Email,Phone,Date of Birth,Gender,Address,Emergency Contact,Emergency Phone,Medical History,Last Visit\n" +
      patients
        .map(
          (p) =>
            `"${p.patientId}","${p.firstName}","${p.lastName}","${p.email}","${p.phone}","${p.dob}","${p.gender}","${p.address}","${p.emergencyContact}","${p.emergencyPhone}","${p.medicalHistory}","${p.lastVisit}"`
        )
        .join("\n");

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "patients.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    showNotification("Patients data exported successfully", "success");
  }

  function generatePatientId() {
    const patients = getPatients();
    const lastId =
      patients.length > 0
        ? parseInt(patients[patients.length - 1].patientId.substring(1))
        : 0;
    return `P${String(lastId + 1).padStart(3, "0")}`;
  }

  function calculateAge(dob) {
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (
      monthDiff < 0 ||
      (monthDiff === 0 && today.getDate() < birthDate.getDate())
    ) {
      age--;
    }

    return age;
  }

  function formatDate(dateString) {
    const options = { year: "numeric", month: "short", day: "numeric" };
    return new Date(dateString).toLocaleDateString("en-US", options);
  }

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
});
