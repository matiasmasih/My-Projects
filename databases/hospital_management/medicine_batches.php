<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get medicine ID from URL
$medicine_id = isset($_GET['medicine_id']) ? (int)$_GET['medicine_id'] : 0;

if ($medicine_id <= 0) {
    die("Invalid medicine ID.");
}

// Fetch medicine details
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.name,
            m.generic_name,
            m.brand,
            m.form,
            m.strength,
            m.unit
        FROM medicines m
        WHERE m.id = ?
    ");
    $stmt->execute([$medicine_id]);
    $medicine = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$medicine) {
        die("Medicine not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch batches for this medicine
try {
    $stmt = $pdo->prepare("
        SELECT 
            mb.id,
            mb.batch_number,
            mb.expiry_date,
            mb.cost_price,
            mb.selling_price,
            mb.created_at,
            ps.quantity,
            ps.min_threshold,
            ps.location
        FROM medicine_batches mb
        LEFT JOIN pharmacy_stock ps ON mb.id = ps.medicine_batch_id
        WHERE mb.medicine_id = ?
        ORDER BY mb.expiry_date ASC
    ");
    $stmt->execute([$medicine_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medicine Batches</title>
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

/* Fixed column widths for batches */
.table-modern th:nth-child(1), .table-modern td:nth-child(1) { width: 60px; }  /* # */
.table-modern th:nth-child(2), .table-modern td:nth-child(2) { width: 150px; } /* Batch Number */
.table-modern th:nth-child(3), .table-modern td:nth-child(3) { width: 120px; } /* Expiry Date */
.table-modern th:nth-child(4), .table-modern td:nth-child(4) { width: 120px; } /* Cost Price */
.table-modern th:nth-child(5), .table-modern td:nth-child(5) { width: 120px; } /* Selling Price */
.table-modern th:nth-child(6), .table-modern td:nth-child(6) { width: 100px; } /* Quantity */
.table-modern th:nth-child(7), .table-modern td:nth-child(7) { width: 100px; } /* Min Threshold */
.table-modern th:nth-child(8), .table-modern td:nth-child(8) { width: 120px; } /* Location */
.table-modern th:nth-child(9), .table-modern td:nth-child(9) { width: 150px; } /* Created Date */
.table-modern th:nth-child(10), .table-modern td:nth-child(10) { width: 180px; } /* Actions */

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

.btn-back {
  background: #6c757d;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 8px 16px;
}
.btn-back:hover {
  background: #545b62;
}

/* Action buttons container */
.action-buttons {
  display: flex;
  gap: 5px;
  justify-content: center;
  flex-wrap: nowrap;
}

/* Status badges */
.status-expired { 
  background-color: #dc3545; 
  color: white; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}
.status-expiring-soon { 
  background-color: #ffc107; 
  color: black; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}
.status-good { 
  background-color: #28a745; 
  color: white; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}

/* Expired row */
.expired-row {
  background-color: #f8d7da !important;
}
.expired-row:hover {
  background-color: #f1b0b7 !important;
}

/* Expiring soon row */
.expiring-soon-row {
  background-color: #fff3cd !important;
}
.expiring-soon-row:hover {
  background-color: #ffeaa7 !important;
}

/* Medicine header */
.medicine-header {
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
  border-radius: 8px;
  padding: 15px;
  margin-bottom: 20px;
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
    <a class="navbar-brand" href="#">Medicine Batches</a>
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
    <a href="payments.php"><i class="bi bi-cash-stack me-2"></i>Payments</a>
    <a href="pharmacy_stock.php"><i class="bi bi-capsule me-2"></i>Pharmacy</a>
    <a href="medicines.php" class="active"><i class="bi bi-heart-pulse me-2"></i>Medicines</a>
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
          <div>
            <h2 class="mb-0">Medicine Batches</h2>
            <p class="text-muted mb-0">Manage batches for: <strong><?= htmlspecialchars($medicine['name']) ?></strong></p>
          </div>
          <div>
            <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addBatchModal">
              <i class="bi bi-plus-circle"></i> Add Batch
            </button>
            <a href="medicines.php" class="btn btn-back">
              <i class="bi bi-arrow-left"></i> Back to Medicines
            </a>
          </div>
        </div>

        <!-- Medicine Info Header -->
        <div class="medicine-header">
          <div class="row">
            <div class="col-md-3">
              <strong>Medicine:</strong> <?= htmlspecialchars($medicine['name']) ?>
            </div>
            <div class="col-md-3">
              <strong>Generic:</strong> <?= htmlspecialchars($medicine['generic_name']) ?>
            </div>
            <div class="col-md-2">
              <strong>Form:</strong> <?= htmlspecialchars($medicine['form']) ?>
            </div>
            <div class="col-md-2">
              <strong>Strength:</strong> <?= htmlspecialchars($medicine['strength']) ?>
            </div>
            <div class="col-md-2">
              <strong>Unit:</strong> <?= htmlspecialchars($medicine['unit']) ?>
            </div>
          </div>
        </div>
        
        <!-- Search Box -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <input type="text" class="form-control search-box" id="searchInput" placeholder="Search batches...">
          <span class="badge bg-primary">Total: <?= count($batches) ?> batches</span>
        </div>
      </div>

      <!-- Table Container - Scrollable area -->
      <div class="table-container">
        <div class="table-wrapper">
          <table class="table table-modern" id="batchTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Batch Number</th>
                <th>Expiry Date</th>
                <th>Cost Price</th>
                <th>Selling Price</th>
                <th>Quantity</th>
                <th>Min Threshold</th>
                <th>Location</th>
                <th>Created Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($batches as $index => $batch): 
                // Determine expiry status
                $expiryStatus = '';
                $rowClass = '';
                $today = new DateTime();
                $expiryDate = new DateTime($batch['expiry_date']);
                $daysUntilExpiry = $today->diff($expiryDate)->days;
                
                if ($expiryDate < $today) {
                    $expiryStatus = 'status-expired';
                    $rowClass = 'expired-row';
                    $statusText = 'Expired';
                } elseif ($daysUntilExpiry <= 30) {
                    $expiryStatus = 'status-expiring-soon';
                    $rowClass = 'expiring-soon-row';
                    $statusText = 'Expiring Soon';
                } else {
                    $expiryStatus = 'status-good';
                    $statusText = 'Good';
                }
              ?>
              <tr class="<?= $rowClass ?>">
                <td><?= $index + 1 ?></td>
                <td>
                  <strong><?= htmlspecialchars($batch['batch_number']) ?></strong>
                </td>
                <td>
                  <span class="<?= $expiryStatus ?>" title="<?= $statusText ?>">
                    <?= date('Y-m-d', strtotime($batch['expiry_date'])) ?>
                  </span>
                </td>
                <td>$<?= number_format($batch['cost_price'], 2) ?></td>
                <td>$<?= number_format($batch['selling_price'], 2) ?></td>
                <td>
                  <span class="<?= $batch['quantity'] < $batch['min_threshold'] ? 'fw-bold text-warning' : '' ?>">
                    <?= $batch['quantity'] ?>
                  </span>
                </td>
                <td><?= $batch['min_threshold'] ?></td>
                <td><?= htmlspecialchars($batch['location']) ?></td>
                <td><?= date('Y-m-d', strtotime($batch['created_at'])) ?></td>
                <td>
                  <div class="action-buttons">
                    <!-- Edit Batch -->
                    <a href="edit_batch.php?id=<?= $batch['id'] ?>" class="btn btn-edit btn-sm" title="Edit Batch">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <!-- Delete Batch -->
                    <a href="delete_batch.php?id=<?= $batch['id'] ?>" class="btn btn-delete btn-sm" title="Delete Batch" onclick="return confirm('Are you sure you want to delete this batch?');">
                      <i class="bi bi-trash"></i>
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

<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1" aria-labelledby="addBatchLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="addBatchLabel"><i class="bi bi-plus-circle me-2"></i>Add New Batch</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form action="save_batch.php" method="POST">
            <input type="hidden" name="medicine_id" value="<?= $medicine_id ?>">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="batch_number" class="form-label">Batch Number *</label>
                        <input type="text" name="batch_number" id="batch_number" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="expiry_date" class="form-label">Expiry Date *</label>
                        <input type="date" name="expiry_date" id="expiry_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="cost_price" class="form-label">Cost Price ($) *</label>
                        <input type="number" step="0.01" name="cost_price" id="cost_price" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-6">
                        <label for="selling_price" class="form-label">Selling Price ($) *</label>
                        <input type="number" step="0.01" name="selling_price" id="selling_price" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-6">
                        <label for="quantity" class="form-label">Quantity *</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-6">
                        <label for="min_threshold" class="form-label">Minimum Threshold</label>
                        <input type="number" name="min_threshold" id="min_threshold" class="form-control" min="0" value="10">
                    </div>
                    <div class="col-md-6">
                        <label for="location" class="form-label">Location</label>
                        <select name="location" id="location" class="form-select">
                            <option value="main_pharmacy">Main Pharmacy</option>
                            <option value="ward_a">Ward A</option>
                            <option value="ward_b">Ward B</option>
                            <option value="emergency">Emergency</option>
                            <option value="storage">Storage</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Batch</button>
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
  const rows = document.querySelectorAll('#batchTable tbody tr');
  
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(filter) ? '' : 'none';
  });
});

// Set minimum date for expiry date to today
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('expiry_date').min = today;
});
</script>
</body>
</html>
