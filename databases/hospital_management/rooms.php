<?php
session_start();
include 'config.php';

// Enhanced error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check role-based access (Admin=1, Manager=2)
$allowed_roles = [1, 2];
if (!in_array($_SESSION['role_id'], $allowed_roles)) {
    header("Location: unauthorized.php");
    exit;
}

// Initialize variables
$success = '';
$error = '';
$wards = [];
$rooms = [];

// Room type options based on your ENUM
$room_type_options = ['single', 'double', 'icu', 'ward', 'other'];

// Generate form token if not exists
if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}
$form_token = $_SESSION['form_token'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify form token to prevent CSRF and duplicate submissions
    if (isset($_POST['form_token']) && $_POST['form_token'] === $_SESSION['form_token']) {

        // Generate new token for next form
        $_SESSION['form_token'] = bin2hex(random_bytes(32));

        if (isset($_POST['add_room'])) {
            $room_number = trim($_POST['room_number'] ?? '');
            $ward_id = $_POST['ward_id'] ?? '';
            $room_type = $_POST['room_type'] ?? 'ward';
            $capacity = intval($_POST['capacity'] ?? 1);
            $notes = trim($_POST['notes'] ?? '');

            // Validate room type
            if (!in_array($room_type, $room_type_options)) {
                $room_type = 'ward';
            }

            // Validate inputs
            if (empty($room_number) || empty($ward_id)) {
                $_SESSION['error'] = "Please fill in all required fields.";
            } elseif ($capacity < 1 || $capacity > 100) {
                $_SESSION['error'] = "Capacity must be between 1 and 100.";
            } else {
                try {
                    // Check if room number already exists in the same ward
                    $check_stmt = $pdo->prepare("SELECT id FROM rooms WHERE room_number = ? AND ward_id = ?");
                    $check_stmt->execute([$room_number, $ward_id]);

                    if ($check_stmt->fetch()) {
                        $_SESSION['error'] = "Room number already exists in this ward.";
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO rooms (room_number, ward_id, room_type, capacity, notes) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt->execute([$room_number, $ward_id, $room_type, $capacity, $notes])) {
                            $_SESSION['success'] = "Room added successfully!";
                        } else {
                            $_SESSION['error'] = "Failed to add room. Please try again.";
                        }
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                }
            }

            header("Location: rooms.php");
            exit;
        }

        if (isset($_POST['update_room'])) {
            $room_id = $_POST['room_id'] ?? '';
            $room_number = trim($_POST['room_number'] ?? '');
            $ward_id = $_POST['ward_id'] ?? '';
            $room_type = $_POST['room_type'] ?? 'ward';
            $capacity = intval($_POST['capacity'] ?? 1);
            $notes = trim($_POST['notes'] ?? '');

            if (!in_array($room_type, $room_type_options)) {
                $room_type = 'ward';
            }

            if (empty($room_id) || empty($room_number) || empty($ward_id)) {
                $_SESSION['error'] = "Please fill in all required fields.";
            } elseif ($capacity < 1 || $capacity > 100) {
                $_SESSION['error'] = "Capacity must be between 1 and 100.";
            } else {
                try {
                    // Check if room number already exists in the same ward (excluding current room)
                    $check_stmt = $pdo->prepare("SELECT id FROM rooms WHERE room_number = ? AND ward_id = ? AND id != ?");
                    $check_stmt->execute([$room_number, $ward_id, $room_id]);

                    if ($check_stmt->fetch()) {
                        $_SESSION['error'] = "Room number already exists in this ward.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE rooms SET room_number=?, ward_id=?, room_type=?, capacity=?, notes=? WHERE id=?");
                        if ($stmt->execute([$room_number, $ward_id, $room_type, $capacity, $notes, $room_id])) {
                            $_SESSION['success'] = "Room updated successfully!";
                        } else {
                            $_SESSION['error'] = "Failed to update room. Please try again.";
                        }
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                }
            }

            header("Location: rooms.php");
            exit;
        }

        if (isset($_POST['delete_room'])) {
            $room_id = $_POST['room_id'] ?? '';
            if (!empty($room_id)) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM rooms WHERE id=?");
                    if ($stmt->execute([$room_id])) {
                        $_SESSION['success'] = "Room deleted successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to delete room. Please try again.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database error: " . $e->getMessage();
                }

                header("Location: rooms.php");
                exit;
            }
        }
    } else {
        // Invalid token
        $_SESSION['error'] = "Invalid form submission. Please try again.";
        header("Location: rooms.php");
        exit;
    }
}

// Check for session messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

try {
    // Fetch wards for dropdown
    try {
        $wards_stmt = $pdo->query("SELECT id, name, location, type FROM wards ORDER BY name");
        $wards = $wards_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Could not fetch wards: " . $e->getMessage();
    }

    // Fetch all rooms with ward information
    try {
        $stmt = $pdo->query("
            SELECT r.*, w.name as ward_name, w.type as ward_type, w.location
            FROM rooms r
            LEFT JOIN wards w ON r.ward_id = w.id
            ORDER BY r.room_number
        ");
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback if join fails
        $stmt = $pdo->query("SELECT * FROM rooms ORDER BY room_number");
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ward_names = [];
        foreach ($wards as $ward) {
            $ward_names[$ward['id']] = $ward['name'];
        }

        foreach ($rooms as &$room) {
            $room['ward_name'] = $ward_names[$room['ward_id']] ?? 'Unknown Ward';
            $room['ward_type'] = '';
            $room['location'] = '';
        }
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Room type display names
$room_type_display = [
    'single' => 'Single Room',
    'double' => 'Double Room',
    'icu' => 'ICU',
    'ward' => 'Ward',
    'other' => 'Other'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Room Management</title>
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
    min-width: 1000px;
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
    width: 120px;
    min-width: 120px;
}

.table th:nth-child(3),
.table td:nth-child(3) {
    width: 200px;
    min-width: 200px;
}

.table th:nth-child(4),
.table td:nth-child(4) {
    width: 120px;
    min-width: 120px;
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
    width: 100px;
    min-width: 100px;
    text-align: center;
}

.table th:nth-child(7),
.table td:nth-child(7) {
    width: 250px;
    min-width: 250px;
    white-space: normal;
}

.table th:nth-child(8),
.table td:nth-child(8) {
    width: 140px;
    min-width: 140px;
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
.room-type-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.room-type-single { background: #e3f2fd; color: #1565c0; }
.room-type-double { background: #e8f5e8; color: #2e7d32; }
.room-type-icu { background: #ffebee; color: #c62828; }
.room-type-ward { background: #f3e5f5; color: #7b1fa2; }
.room-type-other { background: #fff3e0; color: #ef6c00; }

.ward-badge {
    background: #e9ecef;
    color: #495057;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-block;
}

.capacity-badge {
    background: #d1ecf1;
    color: #0c5460;
    padding: 8px 14px;
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
        min-width: 900px;
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

    .table th:nth-child(4),
    .table td:nth-child(4) {
        display: none;
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
            <li><a href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="bi bi-people-fill"></i> Users</a></li>
            <li><a href="roles.php"><i class="bi bi-shield-lock"></i> Roles</a></li>
            <li><a href="patients.php"><i class="bi bi-person-fill"></i> Patients</a></li>
            <li><a href="doctors.php"><i class="bi bi-person-badge"></i> Doctors</a></li>
            <li><a href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
            <li><a href="invoices.php"><i class="bi bi-receipt"></i> Invoices</a></li>
            <li><a href="payments.php"><i class="bi bi-cash-stack"></i> Payments</a></li>
            <li><a href="pharmacy_stock.php"><i class="bi bi-capsule"></i> Pharmacy</a></li>
            <li><a href="medicines.php"><i class="bi bi-heart-pulse"></i> Medicines</a></li>
            <li><a href="wards.php"><i class="bi bi-house-door"></i> Wards</a></li>
            <li><a href="rooms.php" class="active"><i class="bi bi-door-closed"></i> Rooms</a></li>
            <li><a href="messages.php"><i class="bi bi-chat-dots"></i> Messages</a></li>
            <li><a href="admissions.php"><i class="bi bi-journal-plus"></i> Admissions</a></li>
        </ul>
    </div>
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4" style="margin-top: 70px;">
            <h1 class="h3"><i class="bi bi-door-closed me-2"></i>Room Management</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                <i class="bi bi-plus-circle me-2"></i>Add New Room
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
                <i class="bi bi-list-ul me-2"></i>All Rooms (<?php echo count($rooms); ?>)
            </div>
            <div class="card-body">
                <?php if (empty($rooms)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                        <p class="text-muted">No rooms found. Add your first room to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <div class="table-wrapper">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Room Number</th>
                                        <th>Ward</th>
                                        <th>Ward Type</th>
                                        <th>Room Type</th>
                                        <th>Capacity</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rooms as $room): ?>
                                        <tr>
                                            <td><strong>#<?php echo $room['id']; ?></strong></td>
                                            <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($room['ward_name']); ?></strong>
                                                    <?php if (!empty($room['location'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($room['location']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="ward-badge"><?php echo htmlspecialchars($room['ward_type']); ?></span>
                                            </td>
                                            <td>
                                                <span class="room-type-badge room-type-<?php echo $room['room_type']; ?>">
                                                    <?php echo $room_type_display[$room['room_type']]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="capacity-badge"><?php echo $room['capacity']; ?></span>
                                            </td>
                                            <td>
                                                <?php if (!empty($room['notes'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($room['notes']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-warning text-white"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editRoomModal"
                                                            data-room-id="<?php echo $room['id']; ?>"
                                                            data-room-number="<?php echo $room['room_number']; ?>"
                                                            data-ward-id="<?php echo $room['ward_id']; ?>"
                                                            data-room-type="<?php echo $room['room_type']; ?>"
                                                            data-capacity="<?php echo $room['capacity']; ?>"
                                                            data-notes="<?php echo htmlspecialchars($room['notes']); ?>">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this room?')">
                                                        <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
                                                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                                        <button type="submit" name="delete_room" class="btn btn-sm btn-danger">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </form>
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

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Room Number *</label>
                            <input type="text" class="form-control" name="room_number" required placeholder="e.g., 101A, ICU-01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ward *</label>
                            <select class="form-select" name="ward_id" required>
                                <option value="">Select Ward</option>
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>">
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                        (<?php echo htmlspecialchars($ward['type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Room Type</label>
                            <select class="form-select" name="room_type">
                                <?php foreach ($room_type_options as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $type == 'ward' ? 'selected' : ''; ?>>
                                        <?php echo $room_type_display[$type]; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" class="form-control" name="capacity" min="1" max="10" value="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Additional notes about the room..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_room" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="form_token" value="<?php echo $form_token; ?>">
                <input type="hidden" name="room_id" id="edit_room_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Room Number *</label>
                            <input type="text" class="form-control" name="room_number" id="edit_room_number" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ward *</label>
                            <select class="form-select" name="ward_id" id="edit_ward_id" required>
                                <option value="">Select Ward</option>
                                <?php foreach ($wards as $ward): ?>
                                    <option value="<?php echo $ward['id']; ?>">
                                        <?php echo htmlspecialchars($ward['name']); ?>
                                        (<?php echo htmlspecialchars($ward['type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Room Type</label>
                            <select class="form-select" name="room_type" id="edit_room_type">
                                <?php foreach ($room_type_options as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $room_type_display[$type]; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" class="form-control" name="capacity" id="edit_capacity" min="1" max="10">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="edit_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_room" class="btn btn-primary">Update Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editRoomModal = document.getElementById('editRoomModal');

    if (editRoomModal) {
        editRoomModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;

            // Set all the form values
            document.getElementById('edit_room_id').value = button.getAttribute('data-room-id');
            document.getElementById('edit_room_number').value = button.getAttribute('data-room-number');
            document.getElementById('edit_ward_id').value = button.getAttribute('data-ward-id');
            document.getElementById('edit_room_type').value = button.getAttribute('data-room-type');
            document.getElementById('edit_capacity').value = button.getAttribute('data-capacity');
            document.getElementById('edit_notes').value = button.getAttribute('data-notes') || '';
        });
    }

    // Auto-hide alerts after 5 seconds
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
