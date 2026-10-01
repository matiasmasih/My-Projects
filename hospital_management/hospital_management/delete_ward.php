<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get ward ID from URL
$ward_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ward_id <= 0) {
    header("Location: wards.php");
    exit;
}

// Fetch ward data for confirmation message
try {
    $stmt = $pdo->prepare("SELECT name FROM wards WHERE id = ?");
    $stmt->execute([$ward_id]);
    $ward = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ward) {
        header("Location: wards.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm'])) {
        try {
            // Check if ward has any rooms assigned
            $stmt = $pdo->prepare("SELECT COUNT(*) as room_count FROM rooms WHERE ward_id = ?");
            $stmt->execute([$ward_id]);
            $room_check = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($room_check['room_count'] > 0) {
                $error = "Cannot delete ward. There are " . $room_check['room_count'] . " room(s) assigned to this ward. Please reassign or delete the rooms first.";
            } else {
                // Delete the ward
                $stmt = $pdo->prepare("DELETE FROM wards WHERE id = ?");
                $stmt->execute([$ward_id]);
                
                // Redirect with success message
                header("Location: wards.php?success=delete");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        // User cancelled - redirect back
        header("Location: wards.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delete Ward - Hospital Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    font-family: 'Montserrat', sans-serif;
    background: #f0f2f5;
    margin: 0;
}

.navbar {
    background: linear-gradient(135deg, #4b6cb7, #182848);
}
.navbar .navbar-brand, .navbar .nav-link {
    color: #fff;
}
.navbar .nav-link:hover {
    color: #ffd700;
}

.sidebar {
    background-color: #1e1e2f;
    color: #fff;
    min-height: 100vh;
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

.main-content {
    flex: 1;
    padding: 20px;
}

.content-wrapper {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    max-width: 600px;
    margin: 0 auto;
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545, #c82333);
    border: none;
    padding: 10px 30px;
}
.btn-danger:hover {
    background: linear-gradient(135deg, #c82333, #a71e2a);
}

.btn-secondary {
    background: #6c757d;
    border: none;
    padding: 10px 30px;
}
.btn-secondary:hover {
    background: #5a6268;
}

.warning-box {
    background: #fff3cd;
    border: 2px solid #ffeaa7;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.ward-name {
    color: #dc3545;
    font-weight: bold;
    font-size: 1.2em;
}
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Delete Ward</a>
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
        <a href="medicines.php"><i class="bi bi-heart-pulse me-2"></i>Medicines</a>
        <a href="wards.php" class="active"><i class="bi bi-house-door me-2"></i>Wards</a>
        <a href="rooms.php"><i class="bi bi-door-closed me-2"></i>Rooms</a>
        <a href="messages.php"><i class="bi bi-chat-dots me-2"></i>Messages</a>
        <a href="admissions.php"><i class="bi bi-journal-plus me-2"></i>Admissions</a>
        <a href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-wrapper">
            <div class="text-center mb-4">
                <div class="mb-3">
                    <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
                </div>
                <h2 class="text-danger"><i class="bi bi-trash me-2"></i>Delete Ward</h2>
                <p class="text-muted">Confirm deletion of ward</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="warning-box text-center">
                <h4 class="text-warning"><i class="bi bi-warning me-2"></i>Warning</h4>
                <p class="mb-3">You are about to delete the following ward:</p>
                <p class="ward-name">"<?= htmlspecialchars($ward['name']) ?>"</p>
                <p class="text-danger mb-0">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    This action cannot be undone!
                </p>
            </div>

            <form method="POST">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="d-flex gap-3 justify-content-center">
                            <button type="submit" name="confirm" value="1" class="btn btn-danger">
                                <i class="bi bi-trash me-2"></i>Yes, Delete Ward
                            </button>
                            <a href="wards.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                    <div class="col-12 text-center">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Clicking "Yes, Delete Ward" will permanently remove this ward from the system.
                        </small>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
