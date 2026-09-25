<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Fetch invoices along with patient and staff info
try {
    $stmt = $pdo->query("
        SELECT 
            inv.id,
            inv.invoice_number,
            inv.total_amount,
            inv.status,
            inv.issued_at,
            inv.due_date,
            inv.notes,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            CONCAT(u.first_name, ' ', u.last_name) AS issued_by
        FROM invoices inv
        LEFT JOIN patients p ON inv.patient_id = p.id
        LEFT JOIN users u ON inv.issued_by = u.id
        ORDER BY inv.issued_at DESC
    ");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoices</title>
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

/* Table Container - This is where the magic happens */
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

/* Fixed column widths to prevent wrapping */
.table-modern th:nth-child(1), .table-modern td:nth-child(1) { width: 60px; }  /* # */
.table-modern th:nth-child(2), .table-modern td:nth-child(2) { width: 120px; } /* Invoice # */
.table-modern th:nth-child(3), .table-modern td:nth-child(3) { width: 150px; } /* Patient */
.table-modern th:nth-child(4), .table-modern td:nth-child(4) { width: 150px; } /* Issued By */
.table-modern th:nth-child(5), .table-modern td:nth-child(5) { width: 120px; } /* Total Amount */
.table-modern th:nth-child(6), .table-modern td:nth-child(6) { width: 100px; } /* Status */
.table-modern th:nth-child(7), .table-modern td:nth-child(7) { width: 120px; } /* Issued At */
.table-modern th:nth-child(8), .table-modern td:nth-child(8) { width: 120px; } /* Due Date */
.table-modern th:nth-child(9), .table-modern td:nth-child(9) { width: 200px; } /* Notes */
.table-modern th:nth-child(10), .table-modern td:nth-child(10) { width: 180px; } /* Actions - Increased width for 3 buttons */

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
.btn-view {
  background: #007bff;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
}
.btn-view:hover {
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

.btn-pay {
  background: #28a745;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
}
.btn-pay:hover {
  background: #218838;
}

/* Action buttons container */
.action-buttons {
  display: flex;
  gap: 5px;
  justify-content: center;
  flex-wrap: nowrap;
}

/* Status badges - UPDATED TO INCLUDE DRAFT */
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
.status-overdue { 
  background-color: #dc3545; 
  color: white; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}
.status-draft { 
  background-color: #6c757d; 
  color: white; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
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
    <a class="navbar-brand" href="#">Invoices Management</a>
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
    <a href="invoices.php" class="active"><i class="bi bi-receipt me-2"></i>Invoices</a>
    <a href="payments.php"><i class="bi bi-cash-stack me-2"></i>Payments</a>
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
        <h2 class="mb-4">All Invoices</h2>
        
        <!-- Search Box -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <input type="text" class="form-control search-box" id="searchInput" placeholder="Search invoices...">
          <span class="badge bg-primary">Total: <?= count($invoices) ?> invoices</span>
        </div>
      </div>

      <!-- Table Container - Scrollable area -->
      <div class="table-container">
        <div class="table-wrapper">
          <table class="table table-modern" id="invoiceTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Invoice #</th>
                <th>Patient</th>
                <th>Issued By</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Issued At</th>
                <th>Due Date</th>
                <th>Notes</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($invoices as $index => $inv): 
                // Determine status class - UPDATED TO INCLUDE DRAFT
                $statusClass = '';
                if ($inv['status'] === 'paid') {
                    $statusClass = 'status-paid';
                } elseif ($inv['status'] === 'pending') {
                    $statusClass = 'status-pending';
                } elseif ($inv['status'] === 'overdue') {
                    $statusClass = 'status-overdue';
                } elseif ($inv['status'] === 'draft') {
                    $statusClass = 'status-draft';
                }
              ?>
              <tr>
                <td><?= $index + 1 ?></td>
                <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                <td><?= htmlspecialchars($inv['patient_name']) ?></td>
                <td><?= htmlspecialchars($inv['issued_by'] ?? 'N/A') ?></td>
                <td>$<?= number_format($inv['total_amount'], 2) ?></td>
                <td><span class="<?= $statusClass ?>"><?= ucfirst($inv['status']) ?></span></td>
                <td><?= date('Y-m-d', strtotime($inv['issued_at'])) ?></td>
                <td><?= $inv['due_date'] ? date('Y-m-d', strtotime($inv['due_date'])) : 'N/A' ?></td>
                <td title="<?= htmlspecialchars($inv['notes'] ?? '') ?>">
                  <?= !empty($inv['notes']) ? htmlspecialchars(substr($inv['notes'], 0, 30) . (strlen($inv['notes']) > 30 ? '...' : '')) : 'N/A' ?>
                </td>
                <td>
                  <div class="action-buttons">
                    <!-- View Invoice -->
                    <a href="view_invoice.php?id=<?= $inv['id'] ?>" class="btn btn-view btn-sm me-1" title="View Invoice">
                      <i class="bi bi-eye"></i>
                    </a>
                    <!-- Delete Invoice -->
                    <a href="delete_invoice.php?id=<?= $inv['id'] ?>" class="btn btn-delete btn-sm me-1" title="Delete Invoice" onclick="return confirm('Are you sure you want to delete this invoice?');">
                      <i class="bi bi-trash"></i>
                    </a>
                    <!-- Pay Invoice -->
                    <a href="payments.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-pay btn-sm" title="Pay Invoice">
                      <i class="bi bi-cash-stack"></i> Pay
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Simple search filter for table
document.getElementById('searchInput').addEventListener('keyup', function() {
  const filter = this.value.toLowerCase();
  const rows = document.querySelectorAll('#invoiceTable tbody tr');

  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(filter) ? '' : 'none';
  });
});
</script>
</body>
</html>
