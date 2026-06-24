<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Fetch wards data with staff information
try {
    $stmt = $pdo->query("
        SELECT
            w.*,
            CONCAT(u.first_name, ' ', u.last_name) as in_charge_name
        FROM wards w
        LEFT JOIN users u ON w.in_charge_id = u.id
        ORDER BY w.name
    ");
    $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $wards = [];
}

// Fetch staff for the modal dropdown (doctors and nurses)
try {
    $staff = $pdo->query("
        SELECT id, CONCAT(first_name, ' ', last_name) as name
        FROM users
        WHERE role_id IN (3, 4)  -- Assuming 3=doctors, 4=nurses
        ORDER BY first_name
    ")->fetchAll();
} catch (PDOException $e) {
    $staff = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ward Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --primary: #4361ee;
    --secondary: #3f37c9;
    --success: #4cc9f0;
    --info: #4895ef;
    --warning: #f72585;
    --danger: #e63946;
    --light: #f8f9fa;
    --dark: #212529;
    --sidebar-bg: #1a1d29;
    --sidebar-hover: #2d3040;
    --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f5f7fb;
    color: #333;
    overflow-x: hidden;
}

.main-container {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    background: var(--sidebar-bg);
    color: #fff;
    width: 260px;
    padding: 25px 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
}

.sidebar-header {
    padding: 0 25px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 15px;
}

.sidebar-header h4 {
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sidebar-menu {
    list-style: none;
    padding: 0;
}

.sidebar-menu li {
    margin-bottom: 5px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    color: #b0b3c1;
    text-decoration: none;
    padding: 12px 25px;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
    font-weight: 500;
}

.sidebar-menu a:hover,
.sidebar-menu a.active {
    background: var(--sidebar-hover);
    color: #fff;
    border-left: 3px solid var(--primary);
}

.sidebar-menu i {
    margin-right: 12px;
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

.main-content {
    flex: 1;
    padding: 30px;
    margin-left: 260px;
    overflow-y: auto;
}

.navbar {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    padding: 12px 0;
    position: fixed;
    top: 0;
    right: 0;
    left: 260px;
    z-index: 1000;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    margin-bottom: 25px;
}

.card-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-radius: 12px 12px 0 0 !important;
    padding: 15px 20px;
    font-weight: 600;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

/* Table Styles */
.table-container {
    border-radius: 8px;
    overflow: hidden;
    margin-top: 20px;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: white;
}

.table {
    margin-bottom: 0;
    width: 100%;
    min-width: 1200px;
}

.table thead th {
    background: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    color: #495057;
    padding: 15px 12px;
    vertical-align: middle;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table tbody td {
    padding: 15px 12px;
    vertical-align: middle;
    border-color: #f1f3f4;
    white-space: nowrap;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

/* Column Widths */
.table th:nth-child(1),
.table td:nth-child(1) {
    width: 80px;
    min-width: 80px;
    text-align: center;
}

.table th:nth-child(2),
.table td:nth-child(2) {
    width: 150px;
    min-width: 150px;
}

.table th:nth-child(3),
.table td:nth-child(3) {
    width: 120px;
    min-width: 120px;
    text-align: center;
}

.table th:nth-child(4),
.table td:nth-child(4) {
    width: 100px;
    min-width: 100px;
    text-align: center;
}

.table th:nth-child(5),
.table td:nth-child(5) {
    width: 120px;
    min-width: 120px;
    text-align: center;
}

.table th:nth-child(6),
.table td:nth-child(6) {
    width: 120px;
    min-width: 120px;
    text-align: center;
}

.table th:nth-child(7),
.table td:nth-child(7) {
    width: 150px;
    min-width: 150px;
}

.table th:nth-child(8),
.table td:nth-child(8) {
    width: 150px;
    min-width: 150px;
}

.table th:nth-child(9),
.table td:nth-child(9) {
    width: 100px;
    min-width: 100px;
    text-align: center;
}

.table th:nth-child(10),
.table td:nth-child(10) {
    width: 180px;
    min-width: 180px;
    text-align: center;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
    align-items: center;
}

.action-buttons .btn {
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.action-buttons .btn:hover {
    transform: translateY(-2px);
}

.action-buttons .btn i {
    font-size: 0.9rem;
}

/* Badge Styles */
.status-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.status-active { background: #e8f5e8; color: #2e7d32; }
.status-inactive { background: #ffebee; color: #c62828; }
.status-maintenance { background: #fff3e0; color: #ef6c00; }
.status-full { background: #f3e5f5; color: #7b1fa2; }

.ward-type-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.type-general { background: #e3f2fd; color: #1565c0; }
.type-icu { background: #ffebee; color: #c62828; }
.type-maternity { background: #f3e5f5; color: #7b1fa2; }
.type-pediatric { background: #e8f5e8; color: #2e7d32; }
.type-surgical { background: #fff3e0; color: #ef6c00; }
.type-orthopedic { background: #e0f2f1; color: #00695c; }
.type-cardiac { background: #fce4ec; color: #ad1457; }
.type-emergency { background: #fff8e1; color: #ff8f00; }
.type-psychiatric { background: #e8eaf6; color: #283593; }
.type-isolation { background: #f5f5f5; color: #424242; }
.type-other { background: #fafafa; color: #616161; }

.capacity-badge {
    background: #d1ecf1;
    color: #0c5460;
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-block;
}

.charge-badge {
    background: #e8f5e8;
    color: #2e7d32;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-block;
}

.alert {
    border: none;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 20px;
    margin-top: 70px;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
    border-left: 4px solid #dc3545;
}

/* Scrollbar Styling */
.table-wrapper::-webkit-scrollbar {
    height: 8px;
}

.table-wrapper::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.table-wrapper::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.table-wrapper::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Modal Styles */
.modal-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-radius: 12px 12px 0 0;
}

.modal-title {
    font-weight: 600;
}
.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .sidebar {
        width: 220px;
    }

    .main-content {
        margin-left: 220px;
    }

    .navbar {
        left: 220px;
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        margin-left: 0;
    }

    .main-content {
        margin-left: 0;
        padding: 15px;
    }

    .navbar {
        position: relative;
        left: 0;
    }

    .alert {
        margin-top: 20px;
    }

    .table {
        min-width: 1100px;
    }

    .action-buttons {
        flex-direction: column;
        gap: 5px;
    }

    .action-buttons .btn {
        width: 100%;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 10px;
    }

    .card-body {
        padding: 15px;
    }
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand text-white" href="#">
            <i class="bi bi-hospital"></i>
            <span>MediCare Admin</span>
        </a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link text-white" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="main-container">
 <div class="sidebar">
  <div class="sidebar-header">
   <h4><i class="bi bi-layout-sidebar"></i> Admin Menu</h4>
</div>
 <ul class="sidebar-menu">
    <a href="manager_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
    <a href="users.php"><i class="bi bi-people-fill me-2"></i>Users</a>
    <a href="roles.php"><i class="bi bi-shield-lock me-2"></i>Roles</a>
    <a href="patients.php"><i class="bi bi-person-fill me-2"></i>Patients</a>
    <a href="doctors.php"><i class="bi bi-person-badge me-2"></i>Doctors</a>
    <a href="appointments.php"><i class="bi bi-calendar-check me-2"></i>Appointments</a>
    <a href="invoices.php"><i class="bi bi-receipt me-2"></i>Invoices</a>
    <a href="payments.php"><i class="bi bi-cash-stack me-2"></i>Payments</a>
    <a href="pharmacy_stock.php"><i class="bi bi-capsule me-2"></i>Pharmacy</a>
    <a href="medicines.php"><i class="bi bi-heart-pulse me-2"></i>Medicines</a>
    <a href="wards.php" class="active"><i class="bi bi-house-door me-2"></i>Wards</a>
    <a href="rooms.php"><i class="bi bi-door-closed me-2"></i>Rooms</a>
    <a href="messages.php"><i class="bi bi-chat-dots me-2"></i>Messages</a>
    <a href="admissions.php"><i class="bi bi-journal-plus me-2"></i>Admissions</a>
    <a href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a>
 </ul> 
</div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4"  style="margin-top: 45px;">
            <h1 class="h3"><i class="bi bi-house-door me-2"></i>Ward Management</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWardModal">
                <i class="bi bi-plus-circle me-2"></i>Add New Ward
            </button>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul me-2"></i>All Wards (<?php echo count($wards); ?>)
            </div>
            <div class="card-body">
                <?php if (empty($wards)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                        <p class="text-muted">No wards found. Add your first ward to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <div class="table-wrapper">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Ward Name</th>
                                        <th>Type</th>
                                        <th>Capacity</th>
                                        <th>Status</th>
                                        <th>Charge/Day</th>
                                        <th>Location</th>
                                        <th>In Charge</th>
                                        <th>Phone Ext</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wards as $ward): ?>
                                        <tr>
                                            <td><strong>#<?php echo $ward['id']; ?></strong></td>
                                            <td><strong><?php echo htmlspecialchars($ward['name']); ?></strong></td>
                                            <td>
                                                <span class="ward-type-badge type-<?php echo $ward['type']; ?>">
                                                    <?php echo ucfirst($ward['type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="capacity-badge"><?php echo $ward['capacity']; ?> beds</span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $ward['status']; ?>">
                                                    <?php echo ucfirst($ward['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="charge-badge">$<?php echo number_format($ward['charge_per_day'], 2); ?></span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($ward['location']); ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($ward['in_charge_name'])): ?>
                                                    <strong><?php echo htmlspecialchars($ward['in_charge_name']); ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($ward['phone_extension'])): ?>
                                                    <code><?php echo htmlspecialchars($ward['phone_extension']); ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="edit_ward.php?id=<?php echo $ward['id']; ?>" class="btn btn-warning text-white btn-sm">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                    <a href="delete_ward.php?id=<?php echo $ward['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this ward?')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </a>
                                                    <a href="rooms.php?ward_id=<?php echo $ward['id']; ?>" class="btn btn-info text-white btn-sm">
                                                        <i class="bi bi-door-closed"></i> Rooms
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Ward Modal -->
<div class="modal fade" id="addWardModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="save_ward.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Ward</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ward Name *</label>
                            <input type="text" class="form-control" name="name" required placeholder="e.g., General Ward A">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ward Type *</label>
                            <select class="form-select" name="type" required>
                                <option value="general">General</option>
                                <option value="icu">ICU</option>
                                <option value="maternity">Maternity</option>
                                <option value="pediatric">Pediatric</option>
                                <option value="surgical">Surgical</option>
                                <option value="orthopedic">Orthopedic</option>
                                <option value="cardiac">Cardiac</option>
                                <option value="emergency">Emergency</option>
                                <option value="psychiatric">Psychiatric</option>
                                <option value="isolation">Isolation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Capacity (Beds) *</label>
                            <input type="number" class="form-control" name="capacity" min="1" required value="20">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Charge Per Day ($) *</label>
                            <input type="number" step="0.01" class="form-control" name="charge_per_day" min="0" required value="50.00">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location *</label>
                            <input type="text" class="form-control" name="location" required placeholder="e.g., First Floor, West Wing">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Extension</label>
                            <input type="text" class="form-control" name="phone_extension" placeholder="e.g., 1234">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="full">Full</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">In Charge</label>
                            <select class="form-select" name="in_charge_id">
                                <option value="">Select Staff</option>
                                <?php foreach($staff as $person): ?>
                                    <option value="<?php echo $person['id']; ?>"><?php echo htmlspecialchars($person['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Any additional notes about this ward..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Ward</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-set charge per day based on ward type
document.getElementById('type').addEventListener('change', function() {
    const type = this.value;
    const chargeField = document.querySelector('input[name="charge_per_day"]');
    const capacityField = document.querySelector('input[name="capacity"]');

    const charges = {
        'general': 50.00,
        'icu': 200.00,
        'maternity': 80.00,
        'pediatric': 60.00,
        'surgical': 75.00,
        'orthopedic': 70.00,
        'cardiac': 150.00,
        'emergency': 100.00,
        'psychiatric': 65.00,
        'isolation': 90.00,
        'other': 50.00
    };

    const capacities = {
        'general': 20,
        'icu': 10,
        'maternity': 15,
        'pediatric': 18,
        'surgical': 16,
        'orthopedic': 15,
        'cardiac': 12,
        'emergency': 15,
        'psychiatric': 20,
        'isolation': 8,
        'other': 15
    };

    if (charges[type]) {
        chargeField.value = charges[type];
    }
    if (capacities[type]) {
        capacityField.value = capacities[type];
    }
});

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>
</body>
</html>
