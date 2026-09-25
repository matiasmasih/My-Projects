<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Fetch medicines with proper stock calculation
try {
    $stmt = $pdo->query("
        SELECT 
            m.id,
            m.name,
            m.generic_name,
            m.brand,
            m.form,
            m.strength,
            m.unit,
            m.description,
            m.created_at,
            COUNT(DISTINCT mb.id) as batch_count,
            COALESCE(SUM(ps.quantity), 0) as total_stock
        FROM medicines m
        LEFT JOIN medicine_batches mb ON m.id = mb.medicine_id
        LEFT JOIN pharmacy_stock ps ON mb.id = ps.medicine_batch_id
        GROUP BY m.id
        ORDER BY m.created_at DESC
    ");
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medicines</title>
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

/* Fixed column widths for medicines */
.table-modern th:nth-child(1), .table-modern td:nth-child(1) { width: 60px; }  /* # */
.table-modern th:nth-child(2), .table-modern td:nth-child(2) { width: 200px; } /* Medicine Name */
.table-modern th:nth-child(3), .table-modern td:nth-child(3) { width: 180px; } /* Generic Name */
.table-modern th:nth-child(4), .table-modern td:nth-child(4) { width: 150px; } /* Brand */
.table-modern th:nth-child(5), .table-modern td:nth-child(5) { width: 120px; } /* Form */
.table-modern th:nth-child(6), .table-modern td:nth-child(6) { width: 100px; } /* Strength */
.table-modern th:nth-child(7), .table-modern td:nth-child(7) { width: 120px; } /* Total Stock */
.table-modern th:nth-child(8), .table-modern td:nth-child(8) { width: 100px; } /* Batches */
.table-modern th:nth-child(9), .table-modern td:nth-child(9) { width: 200px; } /* Description */
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

.btn-batches {
  background: #28a745;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
}
.btn-batches:hover {
  background: #218838;
}

/* Action buttons container */
.action-buttons {
  display: flex;
  gap: 5px;
  justify-content: center;
  flex-wrap: nowrap;
}

/* Stock status badges */
.stock-adequate { 
  background-color: #28a745; 
  color: white; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}
.stock-low { 
  background-color: #ffc107; 
  color: black; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}
.stock-out { 
  background-color: #dc3545; 
  color: white; 
  padding: 4px 8px; 
  border-radius: 4px; 
  font-size: 0.85em;
}

/* Low stock warning */
.low-stock-row {
  background-color: #fff3cd !important;
}
.low-stock-row:hover {
  background-color: #ffeaa7 !important;
}

/* Out of stock warning */
.out-of-stock-row {
  background-color: #f8d7da !important;
}
.out-of-stock-row:hover {
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
    <a class="navbar-brand" href="#">Medicines Management</a>
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
          <h2 class="mb-0">Medicines Catalog</h2>
          <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addMedicineModal">
            <i class="bi bi-plus-circle"></i> Add Medicine
          </button>
        </div>
        
        <!-- Search Box -->
        <div class="d-flex justify-content-between align-items-center mb-3">
          <input type="text" class="form-control search-box" id="searchInput" placeholder="Search medicines...">
          <span class="badge bg-primary">Total: <?= count($medicines) ?> medicines</span>
        </div>
      </div>

      <!-- Table Container - Scrollable area -->
      <div class="table-container">
        <div class="table-wrapper">
          <table class="table table-modern" id="medicineTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Medicine Name</th>
                <th>Generic Name</th>
                <th>Brand</th>
                <th>Form</th>
                <th>Strength</th>
                <th>Total Stock</th>
                <th>Batches</th>
                <th>Description</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($medicines as $index => $med): 
                // Determine stock status and row class
                $stockStatus = '';
                $rowClass = '';
                
                if ($med['total_stock'] <= 0) {
                    $stockStatus = 'stock-out';
                    $rowClass = 'out-of-stock-row';
                } elseif ($med['total_stock'] < 20) {
                    $stockStatus = 'stock-low';
                    $rowClass = 'low-stock-row';
                } else {
                    $stockStatus = 'stock-adequate';
                }
              ?>
              <tr class="<?= $rowClass ?>">
                <td><?= $index + 1 ?></td>
                <td>
                  <strong><?= htmlspecialchars($med['name']) ?></strong>
                </td>
                <td><?= htmlspecialchars($med['generic_name']) ?></td>
                <td><?= htmlspecialchars($med['brand']) ?></td>
                <td><?= htmlspecialchars($med['form']) ?></td>
                <td><?= htmlspecialchars($med['strength']) ?></td>
                <td>
                  <span class="<?= $stockStatus ?>">
                    <?= $med['total_stock'] ?> <?= htmlspecialchars($med['unit']) ?>
                  </span>
                </td>
                <td>
                  <span class="badge bg-info"><?= $med['batch_count'] ?></span>
                </td>
                <td title="<?= htmlspecialchars($med['description'] ?? '') ?>">
                  <?= !empty($med['description']) ? htmlspecialchars(substr($med['description'], 0, 30) . (strlen($med['description']) > 30 ? '...' : '')) : 'N/A' ?>
                </td>
                <td>
                  <div class="action-buttons">
                    <!-- Edit Medicine -->
                    <a href="edit_medicine.php?id=<?= $med['id'] ?>" class="btn btn-edit btn-sm" title="Edit Medicine">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <!-- Delete Medicine -->
                    <a href="delete_medicine.php?id=<?= $med['id'] ?>" class="btn btn-delete btn-sm" title="Delete Medicine" onclick="return confirm('Are you sure you want to delete this medicine?');">
                      <i class="bi bi-trash"></i>
                    </a>
                    <!-- View Batches -->
                    <a href="medicine_batches.php?medicine_id=<?= $med['id'] ?>" class="btn btn-batches btn-sm" title="View Batches">
                      <i class="bi bi-box-seam"></i>
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

<!-- Add Medicine Modal -->
<div class="modal fade" id="addMedicineModal" tabindex="-1" aria-labelledby="addMedicineLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="addMedicineLabel"><i class="bi bi-heart-pulse me-2"></i>Add New Medicine</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form action="save_medicine.php" method="POST">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Medicine Name *</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="generic_name" class="form-label">Generic Name *</label>
                        <input type="text" name="generic_name" id="generic_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="brand" class="form-label">Brand</label>
                        <input type="text" name="brand" id="brand" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label for="form" class="form-label">Form *</label>
                        <select name="form" id="form" class="form-select" required>
                            <option value="">Select Form</option>
                            <option value="Tablet">Tablet</option>
                            <option value="Capsule">Capsule</option>
                            <option value="Syrup">Syrup</option>
                            <option value="Injection">Injection</option>
                            <option value="Ointment">Ointment</option>
                            <option value="Cream">Cream</option>
                            <option value="Drops">Drops</option>
                            <option value="Inhaler">Inhaler</option>
                            <option value="Spray">Spray</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="strength" class="form-label">Strength</label>
                        <input type="text" name="strength" id="strength" class="form-control" placeholder="e.g., 500mg, 250mg">
                    </div>
                    <div class="col-md-6">
                        <label for="unit" class="form-label">Unit *</label>
                        <select name="unit" id="unit" class="form-select" required>
                            <option value="tablet">Tablet</option>
                            <option value="capsule">Capsule</option>
                            <option value="bottle">Bottle</option>
                            <option value="tube">Tube</option>
                            <option value="inhaler">Inhaler</option>
                            <option value="ampoule">Ampoule</option>
                            <option value="vial">Vial</option>
                            <option value="pack">Pack</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="initial_quantity" class="form-label">Initial Stock Quantity *</label>
                        <input type="number" name="initial_quantity" id="initial_quantity" class="form-control" value="0" min="0" required>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="Medicine description, usage, etc..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Medicine</button>
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
  const rows = document.querySelectorAll('#medicineTable tbody tr');
  
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(filter) ? '' : 'none';
  });
});
</script>
</body>
</html>
