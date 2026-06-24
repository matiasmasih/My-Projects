<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Fetch pharmacy stock items with medicine details
try {
    $stmt = $pdo->query("
        SELECT 
            ps.id,
            ps.medicine_batch_id,
            ps.quantity,
            ps.min_threshold,
            ps.location,
            ps.updated_at,
            mb.batch_number,
            mb.expiry_date,
            mb.cost_price,
            mb.selling_price,
            m.id as medicine_id,
            m.name as medicine_name,
            m.generic_name,
            m.brand,
            m.form as category,
            m.strength,
            m.unit,
            m.description as notes
        FROM pharmacy_stock ps
        LEFT JOIN medicine_batches mb ON ps.medicine_batch_id = mb.id
        LEFT JOIN medicines m ON mb.medicine_id = m.id
        ORDER BY ps.updated_at DESC
    ");
    $stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pharmacy Stock</title>
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

/* Fixed column widths for pharmacy stock */
.table-modern th:nth-child(1), .table-modern td:nth-child(1) { width: 60px; }  /* # */
.table-modern th:nth-child(2), .table-modern td:nth-child(2) { width: 200px; } /* Medicine Name */
.table-modern th:nth-child(3), .table-modern td:nth-child(3) { width: 120px; } /* Category */
.table-modern th:nth-child(4), .table-modern td:nth-child(4) { width: 120px; } /* Batch Number */
.table-modern th:nth-child(5), .table-modern td:nth-child(5) { width: 120px; } /* Quantity */
.table-modern th:nth-child(6), .table-modern td:nth-child(6) { width: 100px; } /* Price */
.table-modern th:nth-child(7), .table-modern td:nth-child(7) { width: 120px; } /* Expiry Date */
.table-modern th:nth-child(8), .table-modern td:nth-child(8) { width: 150px; } /* Brand */
.table-modern th:nth-child(9), .table-modern td:nth-child(9) { width: 100px; } /* Status */
.table-modern th:nth-child(10), .table-modern td:nth-child(10) { width: 200px; } /* Notes */
.table-modern th:nth-child(11), .table-modern td:nth-child(11) { width: 180px; } /* Actions */

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

.btn-restock {
  background: #28a745;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
}
.btn-restock:hover {
  background: #218838;
}

/* Action buttons container */
.action-buttons {
  display: flex;
  gap: 5px;
  justify-content: center;
  flex-wrap: nowrap;
}

/* Status badges */
.status-in-stock { 
  background-color: #28a745; 
  color: white; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}
.status-low-stock { 
  background-color: #ffc107; 
  color: black; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}
.status-out-of-stock { 
  background-color: #dc3545; 
  color: white; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}
.status-expired { 
  background-color: #6c757d; 
  color: white; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}

/* Low stock warning */
.low-stock {
  background-color: #fff3cd !important;
  color: #856404;
}
.low-stock:hover {
  background-color: #ffeaa7 !important;
}

/* Expired warning */
.expired {
  background-color: #f8d7da !important;
  color: #721c24;
}
.expired:hover {
  background-color: #f1b0b7 !important;
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
    <a class="navbar-brand" href="#">Pharmacy Stock Management</a>
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
    <a href="pharmacy_stock.php" class="active"><i class="bi bi-capsule me-2"></i>Pharmacy</a>
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
          <h2 class="mb-0">Pharmacy Stock</h2>
          <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addStockModal">
            <i class="bi bi-plus-circle"></i> Add Stock Item
          </button>
        </div>
        
        <!-- Search Box -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <input type="text" class="form-control search-box" id="searchInput" placeholder="Search stock items...">
          <span class="badge bg-primary">Total: <?= count($stock_items) ?> items</span>
        </div>
      </div>

      <!-- Table Container - Scrollable area -->
      <div class="table-container">
        <div class="table-wrapper">
          <table class="table table-modern" id="stockTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Medicine Name</th>
                <th>Category</th>
                <th>Batch Number</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Expiry Date</th>
                <th>Brand</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($stock_items as $index => $item): 
                // Determine status and row class
                $statusClass = '';
                $rowClass = '';
                $statusText = '';
                
                // Check if expired
                $isExpired = false;
                if (!empty($item['expiry_date']) && strtotime($item['expiry_date']) < strtotime(date('Y-m-d'))) {
                    $statusClass = 'status-expired';
                    $rowClass = 'expired';
                    $statusText = 'Expired';
                } 
                // Check stock levels
                elseif ($item['quantity'] <= 0) {
                    $statusClass = 'status-out-of-stock';
                    $statusText = 'Out of Stock';
                } 
                elseif ($item['quantity'] < ($item['min_threshold'] ?: 10)) {
                    $statusClass = 'status-low-stock';
                    $rowClass = 'low-stock';
                    $statusText = 'Low Stock';
                } 
                else {
                    $statusClass = 'status-in-stock';
                    $statusText = 'In Stock';
                }
              ?>
              <tr class="<?= $rowClass ?>">
                <td><?= $index + 1 ?></td>
                <td>
                  <strong><?= htmlspecialchars($item['medicine_name']) ?></strong>
                  <?php if (!empty($item['generic_name'])): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($item['generic_name']) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($item['category']) ?></td>
                <td><?= htmlspecialchars($item['batch_number']) ?></td>
                <td>
                  <span class="<?= $item['quantity'] < ($item['min_threshold'] ?: 10) ? 'fw-bold text-warning' : '' ?>">
                    <?= $item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?>
                  </span>
                </td>
                <td>$<?= number_format($item['selling_price'], 2) ?></td>
                <td>
                  <?= !empty($item['expiry_date']) ? date('Y-m-d', strtotime($item['expiry_date'])) : 'N/A' ?>
                </td>
                <td><?= htmlspecialchars($item['brand']) ?></td>
                <td><span class="<?= $statusClass ?>"><?= $statusText ?></span></td>
                <td title="<?= htmlspecialchars($item['notes'] ?? '') ?>">
                  <?= !empty($item['notes']) ? htmlspecialchars(substr($item['notes'], 0, 30) . (strlen($item['notes']) > 30 ? '...' : '')) : 'N/A' ?>
                </td>
                <td>
                  <div class="action-buttons">
                    <!-- Edit Stock -->
                    <a href="edit_stock.php?id=<?= $item['id'] ?>" class="btn btn-edit btn-sm" title="Edit Stock">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <!-- Delete Stock -->
                    <a href="delete_stock.php?id=<?= $item['id'] ?>" class="btn btn-delete btn-sm" title="Delete Stock" onclick="return confirm('Are you sure you want to delete this stock item?');">
                      <i class="bi bi-trash"></i>
                    </a>
                    <!-- Restock -->
                    <a href="restock.php?id=<?= $item['id'] ?>" class="btn btn-restock btn-sm" title="Restock">
                      <i class="bi bi-arrow-repeat"></i>
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

<!-- Add Stock Modal -->
<div class="modal fade" id="addStockModal" tabindex="-1" aria-labelledby="addStockLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="addStockLabel"><i class="bi bi-capsule me-2"></i>Add Stock Item</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form action="save_stock.php" method="POST">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="medicine_name" class="form-label">Medicine Name *</label>
                        <input type="text" name="medicine_name" id="medicine_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="category" class="form-label">Category *</label>
                        <select name="category" id="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="Tablet">Tablet</option>
                            <option value="Capsule">Capsule</option>
                            <option value="Syrup">Syrup</option>
                            <option value="Injection">Injection</option>
                            <option value="Ointment">Ointment</option>
                            <option value="Drops">Drops</option>
                            <option value="Inhaler">Inhaler</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="batch_number" class="form-label">Batch Number *</label>
                        <input type="text" name="batch_number" id="batch_number" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="quantity" class="form-label">Quantity *</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-6">
                        <label for="selling_price" class="form-label">Selling Price ($) *</label>
                        <input type="number" step="0.01" name="selling_price" id="selling_price" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-6">
                        <label for="expiry_date" class="form-label">Expiry Date *</label>
                        <input type="date" name="expiry_date" id="expiry_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" name="brand" id="brand" class="form-control">
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Stock Item</button>
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
  const rows = document.querySelectorAll('#stockTable tbody tr');
  
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
