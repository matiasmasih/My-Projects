// Add this to the $(document).ready function after the existing code
$("#modalPaymentMethod").on("change", function () {
  togglePaymentDetails($(this).val());
});

function togglePaymentDetails(paymentMethod) {
  // Hide all sections first
  $("#cardInfoSection").hide();
  $("#insuranceInfoSection").hide();

  // Clear all card and insurance fields
  $("#cardNumber, #expiryDate, #cvv, #cardHolder").val("");
  $("#insuranceProvider, #policyNumber, #groupNumber").val("");

  // Show relevant sections based on payment method
  if (paymentMethod === "credit_card" || paymentMethod === "debit_card") {
    $("#cardInfoSection").show();
    // Make card fields required
    $("#cardNumber, #expiryDate, #cvv, #cardHolder").prop("required", true);
  } else if (paymentMethod === "insurance") {
    $("#insuranceInfoSection").show();
    // Make insurance fields required
    $("#insuranceProvider, #policyNumber").prop("required", true);
  } else {
    // Remove required attribute for other payment methods
    $("#cardNumber, #expiryDate, #cvv, #cardHolder").prop("required", false);
    $("#insuranceProvider, #policyNumber, #groupNumber").prop(
      "required",
      false
    );
  }
}

// Add input formatting for card number and expiry date
$("#cardNumber").on("input", function () {
  let value = $(this)
    .val()
    .replace(/\s+/g, "")
    .replace(/[^0-9]/gi, "");
  let formattedValue = value.match(/.{1,4}/g)?.join(" ") || value;
  $(this).val(formattedValue);
});

$("#expiryDate").on("input", function () {
  let value = $(this)
    .val()
    .replace(/\s+/g, "")
    .replace(/[^0-9]/gi, "");
  if (value.length >= 2) {
    value = value.substring(0, 2) + "/" + value.substring(2, 4);
  }
  $(this).val(value);
});

$("#cvv").on("input", function () {
  $(this).val(
    $(this)
      .val()
      .replace(/[^0-9]/gi, "")
  );
});

// Update the savePayment function to include card/insurance data
function savePayment() {
  const form = $("#paymentForm");

  // Basic validation
  const patient = $("#modalPatient").val();
  const service = $("#modalService").val();
  const amount = $("#modalAmount").val();
  const method = $("#modalPaymentMethod").val();

  if (!patient || !service || !amount || !method) {
    showNotification("Please fill in all required fields", "error");
    return;
  }

  // Additional validation for card payments
  if (method === "credit_card" || method === "debit_card") {
    const cardNumber = $("#cardNumber").val().replace(/\s+/g, "");
    const expiryDate = $("#expiryDate").val();
    const cvv = $("#cvv").val();
    const cardHolder = $("#cardHolder").val();

    if (!cardNumber || cardNumber.length !== 16) {
      showNotification("Please enter a valid 16-digit card number", "error");
      return;
    }

    if (!expiryDate || !/^\d{2}\/\d{2}$/.test(expiryDate)) {
      showNotification("Please enter a valid expiry date (MM/YY)", "error");
      return;
    }

    if (!cvv || cvv.length !== 3) {
      showNotification("Please enter a valid 3-digit CVV", "error");
      return;
    }

    if (!cardHolder) {
      showNotification("Please enter cardholder name", "error");
      return;
    }
  }

  // Additional validation for insurance
  if (method === "insurance") {
    const insuranceProvider = $("#insuranceProvider").val();
    const policyNumber = $("#policyNumber").val();

    if (!insuranceProvider) {
      showNotification("Please enter insurance provider", "error");
      return;
    }

    if (!policyNumber) {
      showNotification("Please enter policy number", "error");
      return;
    }
  }

  const modal = $("#paymentModal");
  const isEditing = modal.data("editing");

  const paymentData = {
    patientId: patient,
    patientName: $("#modalPatient option:selected").text(),
    appointmentId: $("#modalAppointment").val(),
    service: service,
    serviceName: $("#modalService option:selected").text(),
    amount: parseFloat(amount),
    date: new Date().toISOString().split("T")[0],
    paymentMethod: method,
    methodName: $("#modalPaymentMethod option:selected").text(),
    description: $("#modalDescription").val(),
    status: $("#modalStatus").val(),
  };

  // Add card information if applicable
  if (method === "credit_card" || method === "debit_card") {
    paymentData.cardInfo = {
      lastFour: $("#cardNumber").val().slice(-4),
      expiryDate: $("#expiryDate").val(),
      cardHolder: $("#cardHolder").val(),
      // Note: In a real application, you would NEVER store full card numbers or CVV
    };
  }

  // Add insurance information if applicable
  if (method === "insurance") {
    paymentData.insuranceInfo = {
      provider: $("#insuranceProvider").val(),
      policyNumber: $("#policyNumber").val(),
      groupNumber: $("#groupNumber").val() || "",
    };
  }

  let payments = getPayments();

  if (isEditing) {
    // Update existing payment
    const index = payments.findIndex((p) => p.id === isEditing);
    if (index !== -1) {
      payments[index] = { ...payments[index], ...paymentData };
      showNotification("Payment updated successfully", "success");
    }
  } else {
    // Create new payment
    const newPayment = {
      id: "PAY" + String(payments.length + 1).padStart(3, "0"),
      ...paymentData,
    };
    payments.push(newPayment);
    showNotification("Payment created successfully", "success");
  }

  // Save to localStorage
  localStorage.setItem("payments", JSON.stringify(payments));

  // Refresh table and close modal
  loadPayments();
  closePaymentModal();
}

// Update the openPaymentModal function to handle card/insurance data
function openPaymentModal(payment = null) {
  const modal = $("#paymentModal");
  const form = $("#paymentForm");

  if (payment) {
    // Edit mode
    $("#modalTitle").text("Edit Payment");
    form[0].reset();

    // Fill form with payment data
    $("#modalPatient").val(payment.patientId);
    $("#modalAppointment").val(payment.appointmentId || "");
    $("#modalService").val(payment.service);
    $("#modalAmount").val(payment.amount);
    $("#modalPaymentMethod").val(payment.paymentMethod);
    $("#modalDescription").val(payment.description);
    $("#modalStatus").val(payment.status);

    // Fill card information if exists
    if (payment.cardInfo) {
      $("#cardNumber").val("**** **** **** " + payment.cardInfo.lastFour);
      $("#expiryDate").val(payment.cardInfo.expiryDate);
      $("#cardHolder").val(payment.cardInfo.cardHolder);
    }

    // Fill insurance information if exists
    if (payment.insuranceInfo) {
      $("#insuranceProvider").val(payment.insuranceInfo.provider);
      $("#policyNumber").val(payment.insuranceInfo.policyNumber);
      $("#groupNumber").val(payment.insuranceInfo.groupNumber);
    }

    // Show relevant sections
    togglePaymentDetails(payment.paymentMethod);

    modal.data("editing", payment.id);
  } else {
    // New mode
    $("#modalTitle").text("New Payment");
    form[0].reset();
    // Hide all additional sections
    $("#cardInfoSection").hide();
    $("#insuranceInfoSection").hide();
    modal.removeData("editing");
  }

  modal.addClass("show");
}

// Payments Page JavaScript
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

  // Initialize payments
  loadPayments();
  loadFormData();
  updateStats();

  // Modal handlers
  $("#newPaymentBtn").on("click", function () {
    openPaymentModal();
  });

  $("#modalClose, #modalCancel").on("click", function () {
    closePaymentModal();
  });

  $("#modalSave").on("click", function () {
    savePayment();
  });

  // Filter handlers
  $("#statusFilter, #startDate, #endDate, #searchPayments").on(
    "change input",
    function () {
      filterPayments();
    }
  );

  // Action buttons
  $("#exportBtn").on("click", exportPayments);
  $("#refreshBtn").on("click", loadPayments);

  // Close modal when clicking outside
  $(window).on("click", function (e) {
    if ($(e.target).hasClass("modal")) {
      closePaymentModal();
    }
  });

  function loadPayments() {
    // Show loading state
    const tbody = $("#paymentsTable tbody");
    tbody.html(`
            <tr>
                <td colspan="8" class="empty-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading payments...</p>
                </td>
            </tr>
        `);

    // Simulate API call
    setTimeout(() => {
      const payments = getPayments();
      displayPayments(payments);
      updateStats(payments);
    }, 1000);
  }

  function getPayments() {
    // Get from localStorage or use sample data
    let payments = JSON.parse(localStorage.getItem("payments"));

    if (!payments || payments.length === 0) {
      // Sample data
      payments = [
        {
          id: "PAY001",
          patientId: 1,
          patientName: "John Smith",
          appointmentId: 1,
          service: "consultation",
          serviceName: "Consultation",
          amount: 150.0,
          date: "2024-06-15",
          paymentMethod: "credit_card",
          methodName: "Credit Card",
          description: "Regular checkup consultation",
          status: "completed",
        },
        {
          id: "PAY002",
          patientId: 2,
          patientName: "Maria Garcia",
          appointmentId: 2,
          service: "lab_test",
          serviceName: "Lab Test",
          amount: 75.5,
          date: "2024-06-16",
          paymentMethod: "insurance",
          methodName: "Insurance",
          description: "Blood test and analysis",
          status: "pending",
        },
        {
          id: "PAY003",
          patientId: 3,
          patientName: "Robert Wilson",
          service: "medication",
          serviceName: "Medication",
          amount: 45.25,
          date: "2024-06-14",
          paymentMethod: "cash",
          methodName: "Cash",
          description: "Prescription medication",
          status: "completed",
        },
      ];
      localStorage.setItem("payments", JSON.stringify(payments));
    }

    return payments;
  }

  function displayPayments(payments) {
    const tbody = $("#paymentsTable tbody");

    if (payments.length === 0) {
      tbody.html(`
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class="fas fa-money-bill-wave"></i>
                        <h3>No Payments Found</h3>
                        <p>No payments match your current filters.</p>
                    </td>
                </tr>
            `);
      return;
    }

    let html = "";
    payments.forEach((payment) => {
      html += `
                <tr>
                    <td><strong>${payment.id}</strong></td>
                    <td>${payment.patientName}</td>
                    <td>${payment.serviceName}</td>
                    <td>$${payment.amount.toFixed(2)}</td>
                    <td>${formatDate(payment.date)}</td>
                    <td>${payment.methodName}</td>
                    <td><span class="status-badge status-${payment.status}">${
        payment.status
      }</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-primary view-payment" data-id="${
                              payment.id
                            }">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-secondary edit-payment" data-id="${
                              payment.id
                            }">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-payment" data-id="${
                              payment.id
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
    $(".view-payment").on("click", function () {
      const paymentId = $(this).data("id");
      viewPayment(paymentId);
    });

    $(".edit-payment").on("click", function () {
      const paymentId = $(this).data("id");
      editPayment(paymentId);
    });

    $(".delete-payment").on("click", function () {
      const paymentId = $(this).data("id");
      deletePayment(paymentId);
    });
  }

  function loadFormData() {
    // Load patients for dropdown
    const patients = [
      { id: 1, name: "John Smith" },
      { id: 2, name: "Maria Garcia" },
      { id: 3, name: "Robert Wilson" },
      { id: 4, name: "Sarah Johnson" },
    ];

    // Load appointments for dropdown
    const appointments = [
      {
        id: 1,
        patientName: "John Smith",
        date: "2024-06-15",
        service: "Consultation",
      },
      {
        id: 2,
        patientName: "Maria Garcia",
        date: "2024-06-16",
        service: "Lab Test",
      },
      {
        id: 3,
        patientName: "Robert Wilson",
        date: "2024-06-14",
        service: "Checkup",
      },
    ];

    // Populate patient dropdown
    const patientSelect = $("#modalPatient");
    patientSelect.html('<option value="">Select Patient</option>');
    patients.forEach((patient) => {
      patientSelect.append(
        `<option value="${patient.id}">${patient.name}</option>`
      );
    });

    // Populate appointment dropdown
    const appointmentSelect = $("#modalAppointment");
    appointmentSelect.html(
      '<option value="">Select Appointment (Optional)</option>'
    );
    appointments.forEach((appointment) => {
      appointmentSelect.append(
        `<option value="${appointment.id}">${appointment.patientName} - ${
          appointment.service
        } (${formatDate(appointment.date)})</option>`
      );
    });
  }

  function openPaymentModal(payment = null) {
    const modal = $("#paymentModal");
    const form = $("#paymentForm");

    if (payment) {
      // Edit mode
      $("#modalTitle").text("Edit Payment");
      form[0].reset();

      // Fill form with payment data
      $("#modalPatient").val(payment.patientId);
      $("#modalAppointment").val(payment.appointmentId || "");
      $("#modalService").val(payment.service);
      $("#modalAmount").val(payment.amount);
      $("#modalPaymentMethod").val(payment.paymentMethod);
      $("#modalDescription").val(payment.description);
      $("#modalStatus").val(payment.status);

      modal.data("editing", payment.id);
    } else {
      // New mode
      $("#modalTitle").text("New Payment");
      form[0].reset();
      modal.removeData("editing");
    }

    modal.addClass("show");
  }

  function closePaymentModal() {
    $("#paymentModal").removeClass("show");
  }

  function savePayment() {
    const form = $("#paymentForm");

    // Basic validation
    const patient = $("#modalPatient").val();
    const service = $("#modalService").val();
    const amount = $("#modalAmount").val();
    const method = $("#modalPaymentMethod").val();

    if (!patient || !service || !amount || !method) {
      showNotification("Please fill in all required fields", "error");
      return;
    }

    const modal = $("#paymentModal");
    const isEditing = modal.data("editing");

    const paymentData = {
      patientId: patient,
      patientName: $("#modalPatient option:selected").text(),
      appointmentId: $("#modalAppointment").val(),
      service: service,
      serviceName: $("#modalService option:selected").text(),
      amount: parseFloat(amount),
      date: new Date().toISOString().split("T")[0],
      paymentMethod: method,
      methodName: $("#modalPaymentMethod option:selected").text(),
      description: $("#modalDescription").val(),
      status: $("#modalStatus").val(),
    };

    let payments = getPayments();

    if (isEditing) {
      // Update existing payment
      const index = payments.findIndex((p) => p.id === isEditing);
      if (index !== -1) {
        payments[index] = { ...payments[index], ...paymentData };
        showNotification("Payment updated successfully", "success");
      }
    } else {
      // Create new payment
      const newPayment = {
        id: "PAY" + String(payments.length + 1).padStart(3, "0"),
        ...paymentData,
      };
      payments.push(newPayment);
      showNotification("Payment created successfully", "success");
    }

    // Save to localStorage
    localStorage.setItem("payments", JSON.stringify(payments));

    // Refresh table and close modal
    loadPayments();
    closePaymentModal();
  }

  function viewPayment(paymentId) {
    const payments = getPayments();
    const payment = payments.find((p) => p.id === paymentId);

    if (payment) {
      const message = `
                Payment Details:
                ID: ${payment.id}
                Patient: ${payment.patientName}
                Service: ${payment.serviceName}
                Amount: $${payment.amount.toFixed(2)}
                Date: ${formatDate(payment.date)}
                Method: ${payment.methodName}
                Status: ${payment.status}
                ${
                  payment.description
                    ? `Description: ${payment.description}`
                    : ""
                }
            `;
      alert(message);
    }
  }

  function editPayment(paymentId) {
    const payments = getPayments();
    const payment = payments.find((p) => p.id === paymentId);

    if (payment) {
      openPaymentModal(payment);
    }
  }

  function deletePayment(paymentId) {
    if (!confirm("Are you sure you want to delete this payment record?")) {
      return;
    }

    let payments = getPayments();
    payments = payments.filter((p) => p.id !== paymentId);

    localStorage.setItem("payments", JSON.stringify(payments));
    showNotification("Payment deleted successfully", "success");
    loadPayments();
  }

  function filterPayments() {
    const statusFilter = $("#statusFilter").val();
    const startDate = $("#startDate").val();
    const endDate = $("#endDate").val();
    const searchTerm = $("#searchPayments").val().toLowerCase();

    let payments = getPayments();

    // Apply filters
    if (statusFilter !== "all") {
      payments = payments.filter((p) => p.status === statusFilter);
    }

    if (startDate) {
      payments = payments.filter((p) => p.date >= startDate);
    }

    if (endDate) {
      payments = payments.filter((p) => p.date <= endDate);
    }

    if (searchTerm) {
      payments = payments.filter(
        (p) =>
          p.patientName.toLowerCase().includes(searchTerm) ||
          p.serviceName.toLowerCase().includes(searchTerm) ||
          p.id.toLowerCase().includes(searchTerm)
      );
    }

    displayPayments(payments);
    updateStats(payments);
  }

  function updateStats(payments = null) {
    if (!payments) {
      payments = getPayments();
    }

    const totalRevenue = payments
      .filter((p) => p.status === "completed")
      .reduce((sum, payment) => sum + payment.amount, 0);

    const pendingPayments = payments.filter(
      (p) => p.status === "pending"
    ).length;
    const completedPayments = payments.filter(
      (p) => p.status === "completed"
    ).length;

    $("#totalRevenue").text("$" + totalRevenue.toFixed(2));
    $("#pendingPayments").text(pendingPayments);
    $("#completedPayments").text(completedPayments);
  }

  function exportPayments() {
    const payments = getPayments();
    const csvContent =
      "data:text/csv;charset=utf-8," +
      "Payment ID,Patient,Service,Amount,Date,Method,Status,Description\n" +
      payments
        .map(
          (p) =>
            `"${p.id}","${p.patientName}","${p.serviceName}","${p.amount}","${p.date}","${p.methodName}","${p.status}","${p.description}"`
        )
        .join("\n");

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "payments.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    showNotification("Payments exported successfully", "success");
  }

  function formatDate(dateString) {
    const options = { year: "numeric", month: "short", day: "numeric" };
    return new Date(dateString).toLocaleDateString("en-US", options);
  }
});
