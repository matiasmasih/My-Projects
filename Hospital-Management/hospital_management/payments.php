<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get selected invoice ID from URL (for Pay button)
$selected_invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
$selected_patient_id = 0;
$selected_amount = 0;

if ($selected_invoice_id > 0) {
    $stmt = $pdo->prepare("SELECT patient_id, total_amount FROM invoices WHERE id = ?");
    $stmt->execute([$selected_invoice_id]);
    $invoice = $stmt->fetch();
    if ($invoice) {
        $selected_patient_id = $invoice['patient_id'];
        $selected_amount = $invoice['total_amount'];
    }
}

// Fetch all payments along with invoice and patient info
try {
    $stmt = $pdo->query("
        SELECT
            pay.id AS payment_id,
            pay.amount,
            pay.method,
            pay.status,
            pay.notes,
            pay.paid_at,
            pay.invoice_id,
            CONCAT(pt.first_name, ' ', pt.last_name) AS patient_name,
            CONCAT(u.first_name, ' ', u.last_name) AS paid_by
        FROM payments pay
        LEFT JOIN invoices inv ON pay.invoice_id = inv.id
        LEFT JOIN patients pt ON inv.patient_id = pt.id
        LEFT JOIN users u ON pay.paid_by = u.id
        ORDER BY pay.paid_at DESC
    ");
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch patients for modal dropdown
try {
    $patients = $pdo->query("
        SELECT id, first_name AS first, last_name AS last
        FROM patients
        ORDER BY first_name
    ")->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* General */
body {
  font-family: 'Montserrat', sans-serif;
  background: #f0f2f5;
  margin: 0;
  overflow-x: hidden;
}

/* Navbar */
.navbar {
  background: linear-gradient(135deg, #4b6cb7, #182848);
}
.navbar .navbar-brand, .navbar .nav-link {
  color: #fff;
}
.navbar .nav-link:hover {
  color: #ffd700;
}

/* Sidebar */
.sidebar {
  background-color: #1e1e2f;
  color: #fff;
  min-height: calc(100vh - 56px);
  width: 230px;
  padding: 20px;
}
.sidebar h4 {
  font-weight: 600;
  color: #ffd700;
  margin-bottom: 1rem;
}
.sidebar a {
  display: flex;
  align-items: center;
  color: #c1c1c1;
  text-decoration: none;
  padding: 12px 15px;
  border-radius: 8px;
  margin-bottom: 5px;
  transition: all 0.3s ease;
}
.sidebar a:hover {
  background-color: #4b6cb7;
  color: #fff;
}
.sidebar a.active {
  background-color: #ffd700;
  color: #1e1e2f;
  font-weight: 600;
}

/* Main Content */
.main-content {
  flex: 1;
  padding: 20px;
  height: calc(100vh - 56px);
  overflow: hidden;
}

.content-wrapper {
  background: white;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  height: 100%;
  display: flex;
  flex-direction: column;
}

/* Search Box */
.search-box {
  max-width: 300px;
  margin-bottom: 1rem;
}

/* Table Container */
.table-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
}

/* Table Wrapper - Scrollable area */
.table-wrapper {
  flex: 1;
  overflow: auto;
  border: 1px solid #ddd;
  border-radius: 12px;
}

/* Table styling - Force single line and full width */
.table-modern {
  width: max-content;
  min-width: 100%;
  border-collapse: collapse;
  margin-bottom: 0;
  table-layout: fixed;
}

/* Ensure all table cells stay in one line */
.table-modern th,
.table-modern td {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  vertical-align: middle;
  text-align: center;
  padding: 12px 15px;
  border-bottom: 1px solid #dee2e6;
}

/* Fixed column widths for payments */
.table-modern th:nth-child(1), .table-modern td:nth-child(1) { width: 60px; }  /* # */
.table-modern th:nth-child(2), .table-modern td:nth-child(2) { width: 150px; } /* Patient */
.table-modern th:nth-child(3), .table-modern td:nth-child(3) { width: 120px; } /* Amount */
.table-modern th:nth-child(4), .table-modern td:nth-child(4) { width: 120px; } /* Method */
.table-modern th:nth-child(5), .table-modern td:nth-child(5) { width: 150px; } /* Paid By */
.table-modern th:nth-child(6), .table-modern td:nth-child(6) { width: 150px; } /* Payment Date */
.table-modern th:nth-child(7), .table-modern td:nth-child(7) { width: 100px; } /* Status */
.table-modern th:nth-child(8), .table-modern td:nth-child(8) { width: 200px; } /* Notes */
.table-modern th:nth-child(9), .table-modern td:nth-child(9) { width: 180px; } /* Actions */

/* Sticky header */
.table-wrapper thead th {
  position: sticky;
  top: 0;
  background: linear-gradient(135deg, #36d1dc, #5b86e5);
  color: #fff;
  z-index: 2;
  border-bottom: 2px solid #fff;
}

/* Table row hover effect */
.table-modern tbody tr:hover {
  background: rgba(75,108,183,0.1);
  transition: 0.3s;
}

/* Scrollbar styling */
.table-wrapper::-webkit-scrollbar {
  width: 10px;
  height: 10px;
}
.table-wrapper::-webkit-scrollbar-track {
  background: #e0e0e0;
  border-radius: 10px;
}
.table-wrapper::-webkit-scrollbar-thumb {
  background: #4b6cb7;
  border-radius: 10px;
}
.table-wrapper::-webkit-scrollbar-thumb:hover {
  background: #182848;
}

/* Buttons */
.btn-edit {
  background: #007bff;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
}
.btn-edit:hover {
  background: #0056b3;
}

.btn-delete {
  background: #dc3545;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
}
.btn-delete:hover {
  background: #c82333;
}

.btn-receipt {
  background: #28a745;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
}
.btn-receipt:hover {
  background: #218838;
}

.btn-add {
  background: #007bff;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 8px 16px;
}
.btn-add:hover {
  background: #0056b3;
}

/* Action buttons container */
.action-buttons {
  display: flex;
  gap: 5px;
  justify-content: center;
  flex-wrap: nowrap;
}

/* Status badges */
.status-paid { 
  background-color: #28a745; 
  color: white; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}
.status-pending { 
  background-color: #ffc107; 
  color: black; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}
.status-failed { 
  background-color: #dc3545; 
  color: white; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}

/* Modal buttons */
.btn-cancel {
  background-color: #ccc;
  color: #000;
  border: none;
  border-radius: 6px;
  padding: 8px 18px;
  font-weight: 500;
}
.btn-cancel:hover {
  background-color: #999;
}

.btn-submit {
  background-color: #1d3557;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 8px 18px;
  font-weight: 500;
}
.btn-submit:hover {
  background-color: #0f2340;
}

/* Ensure content above table doesn't scroll */
.page-header {
  flex-shrink: 0;
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Payments Management</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="d-flex">

  <!-- Sidebar -->
  <div class="sidebar">
    <h4>Dashboard Menu</h4>
    <a href="manager_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
    <a href="users.php"><i class="bi bi-people-fill me-2"></i>Users</a>
    <a href="roles.php"><i class="bi bi-shield-lock me-2"></i>Roles</a>
    <a href="patients.php"><i class="bi bi-person-fill me-2"></i>Patients</a>
    <a href="doctors.php"><i class="bi bi-person-badge me-2"></i>Doctors</a>
    <a href="appointments.php"><i class="bi bi-calendar-check me-2"></i>Appointments</a>
    <a href="invoices.php"><i class="bi bi-receipt me-2"></i>Invoices</a>
    <a href="payments.php" class="active"><i class="bi bi-cash-stack me-2"></i>Payments</a>
    <a href="pharmacy_stock.php"><i class="bi bi-capsule me-2"></i>Pharmacy</a>
    <a href="medicines.php"><i class="bi bi-heart-pulse me-2"></i>Medicines</a>
    <a href="wards.php"><i class="bi bi-house-door me-2"></i>Wards</a>
    <a href="rooms.php"><i class="bi bi-door-closed me-2"></i>Rooms</a>
    <a href="messages.php"><i class="bi bi-chat-dots me-2"></i>Messages</a>
    <a href="admissions.php"><i class="bi bi-journal-plus me-2"></i>Admissions</a>
    <a href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="content-wrapper">
      
      <!-- Page Header - Fixed (no scroll) -->
      <div class="page-header">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="mb-0">All Payments</h2>
          <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
            <i class="bi bi-plus-circle"></i> Add Payment
          </button>
        </div>
        
        <!-- Search Box -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <input type="text" class="form-control search-box" id="searchInput" placeholder="Search payments...">
          <span class="badge bg-primary">Total: <?= count($payments) ?> payments</span>
        </div>
      </div>

      <!-- Table Container - Scrollable area -->
      <div class="table-container">
        <div class="table-wrapper">
          <table class="table table-modern" id="paymentTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Patient</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Paid By</th>
                <th>Payment Date</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $index => $p): 
                // Determine status class
                $statusClass = '';
                if ($p['status'] === 'paid') {
                    $statusClass = 'status-paid';
                } elseif ($p['status'] === 'pending') {
                    $statusClass = 'status-pending';
                } elseif ($p['status'] === 'failed') {
                    $statusClass = 'status-failed';
                }
                
                // Format method names
                $methodNames = [
                    'cash' => 'Cash',
                    'card' => 'Card', 
                    'insurance' => 'Insurance',
                    'bank_transfer' => 'Bank Transfer',
                    'other' => 'Other'
                ];
                $methodDisplay = $methodNames[$p['method']] ?? ucfirst($p['method']);
              ?>
              <tr>
                <td><?= $index + 1 ?></td>
                <td><?= htmlspecialchars($p['patient_name']) ?></td>
                <td><strong>$<?= number_format($p['amount'], 2) ?></strong></td>
                <td><?= htmlspecialchars($methodDisplay) ?></td>
                <td><?= htmlspecialchars($p['paid_by']) ?></td>
                <td><?= !empty($p['paid_at']) ? date('Y-m-d H:i', strtotime($p['paid_at'])) : 'N/A' ?></td>
                <td><span class="<?= $statusClass ?>"><?= ucfirst($p['status']) ?></span></td>
                <td title="<?= htmlspecialchars($p['notes'] ?? '') ?>">
                  <?= !empty($p['notes']) ? htmlspecialchars(substr($p['notes'], 0, 30) . (strlen($p['notes']) > 30 ? '...' : '')) : 'N/A' ?>
                </td>
                <td>
                  <div class="action-buttons">
                    <!-- Edit Payment -->
                    <a href="edit_payment.php?id=<?= $p['payment_id'] ?>" class="btn btn-edit btn-sm" title="Edit Payment">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <!-- Delete Payment -->
                    <a href="delete_payment.php?id=<?= $p['payment_id'] ?>" class="btn btn-delete btn-sm" title="Delete Payment" onclick="return confirm('Are you sure you want to delete this payment?');">
                      <i class="bi bi-trash"></i>
                    </a>
                    <!-- Generate Receipt -->
                    <a href="generate_receipt.php?id=<?= $p['invoice_id'] ?>" class="btn btn-receipt btn-sm" title="Generate Receipt" target="_blank">
                      <i class="bi bi-file-earmark-pdf"></i>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="addPaymentLabel"><i class="bi bi-cash-stack me-2"></i>Add Payment</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form action="save_payment.php" method="POST">
            <input type="hidden" name="invoice_id" value="<?= $selected_invoice_id ?>">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="patient" class="form-label">Patient</label>
                        <select name="patient_id" id="patient" class="form-select" required>
                            <option value="">Select Patient</option>
                            <?php foreach($patients as $pt): ?>
                                <option value="<?= $pt['id'] ?>" <?= $pt['id']==$selected_patient_id?'selected':'' ?>>
                                    <?= htmlspecialchars($pt['first'].' '.$pt['last']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="amount" class="form-label">Amount ($)</label>
                        <input type="number" step="0.01" name="amount" id="amount" class="form-control" value="<?= $selected_amount ?>" required>
                    </div>
                    <div class="col-md-6">
                       <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="method" class="form-label">Payment Method</label>
                        <select name="method" id="method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="insurance">Insurance</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-submit">Save Payment</button>
            </div>
        </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Simple search filter for table
document.getElementById('searchInput').addEventListener('keyup', function() {
  const filter = this.value.toLowerCase();
  const rows = document.querySelectorAll('#paymentTable tbody tr');
  
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(filter) ? '' : 'none';
  });
});

// Auto-open modal if invoice_id is in URL
<?php if($selected_invoice_id > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    var addPaymentModal = new bootstrap.Modal(document.getElementById('addPaymentModal'));
    addPaymentModal.show();
});
<?php endif; ?>
</script>
</body>
</html>
