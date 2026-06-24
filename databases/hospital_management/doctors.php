<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2) allowed
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: login.php");
    exit;
}

// Fetch doctors with user info
try {
    $stmt = $pdo->query("
        SELECT d.id, d.license_number, d.bio, d.consultation_fee,
               u.first_name, u.last_name, u.email, u.phone
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        ORDER BY d.id DESC
    ");
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch users for Add Doctor dropdown
$stmtUsers = $pdo->query("SELECT id, first_name, last_name FROM users");
$usersList = $stmtUsers->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors Management</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        /* Body & Fonts */
        body { 
            font-family:'Montserrat', sans-serif; 
            background:#f0f2f5; 
            margin:0; 
        }

        /* Navbar */
        .navbar { 
            background: linear-gradient(135deg,#4b6cb7,#182848); 
            color:#fff; 
        }
        .navbar .navbar-brand, .navbar .nav-link { color:#fff; }
        .navbar .nav-link:hover { color:#ffd700; }

        /* Sidebar */
        .sidebar { 
            background-color:#1e1e2f; 
            color:#fff; 
            min-height:100vh; 
            width:230px; 
            padding:20px; 
        }
        .sidebar h4 { 
            font-weight:600; 
            margin-bottom:1rem; 
            color:#ffd700; 
        }
        .sidebar a { 
            display:block; 
            color:#c1c1c1; 
            text-decoration:none; 
            padding:12px 15px; 
            border-radius:8px; 
            margin-bottom:5px; 
            transition:all 0.3s ease; 
        }
        .sidebar a:hover { 
            background-color:#4b6cb7; 
            color:#fff; 
        }
        .sidebar a.active { 
            background-color:#ffd700; 
            color:#1e1e2f; 
            font-weight:600; 
        }

        /* Table */
        .table-modern { 
            background:#fff; 
            border-radius:12px; 
            box-shadow:0 8px 30px rgba(0,0,0,0.12); 
            overflow:hidden; 
        }
        .table-modern th { 
            background:linear-gradient(135deg,#36d1dc,#5b86e5); 
            color:#fff; 
            font-weight:600; 
            text-align:center; 
        }
        .table-modern td, .table-modern th { 
            vertical-align:middle; 
            text-align:center; 
        }
        .table-modern tbody tr:hover { 
            background: rgba(75,108,183,0.1); 
            transform:translateX(3px); 
            transition:0.3s; 
        }

        /* Buttons */
        .btn-edit { background:#56ab2f; color:#fff; border:none; border-radius:6px; }
        .btn-edit:hover { background:#3c7d1b; }
        .btn-delete { background:#ff4e50; color:#fff; border:none; border-radius:6px; }
        .btn-delete:hover { background:#c43a3a; }
        .btn-add { background:#007bff; color:#fff; border:none; border-radius:6px; }
        .btn-add:hover { background:#0056b3; }

        /* Search */
        .search-box { max-width:300px; }

        /* Modal Header */
        .modal-header { 
            background: linear-gradient(135deg,#36d1dc,#5b86e5); 
            color:#fff; 
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Doctors Management</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout <i class="bi bi-box-arrow-right"></i></a>
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
        <a href="doctors.php"  class="active"><i class="bi bi-person-badge me-2"></i>Doctors</a>
        <a href="appointments.php"><i class="bi bi-calendar-check me-2"></i>Appointments</a>
        <a href="invoices.php"><i class="bi bi-receipt me-2"></i>Invoices</a>
        <a href="payments.php"><i class="bi bi-cash-stack me-2"></i>Payments</a>
        <a href="pharmacy_stock.php"><i class="bi bi-capsule me-2"></i>Pharmacy</a>
        <a href="medicines.php"><i class="bi bi-heart-pulse me-2"></i>Medicines</a>
        <a href="wards.php"><i class="bi bi-house-door me-2"></i>Wards</a>
        <a href="rooms.php"><i class="bi bi-door-closed me-2"></i>Rooms</a>
        <a href="messages.php"><i class="bi bi-chat-dots me-2"></i>Messages</a>
        <a href="admissions.php"><i class="bi bi-journal-plus me-2"></i>Admissions</a>
        <a href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a>
    </div>

    <!-- Main content -->
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>All Doctors</h2>
            <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                <i class="bi bi-plus"></i> Add Doctor
            </button>
        </div>

        <!-- Search -->
        <div class="d-flex mb-3">
            <input type="text" class="form-control search-box me-2" id="searchInput" placeholder="Search doctors...">
            <button class="btn btn-primary" id="searchButton"><i class="bi bi-search"></i> Search</button>
        </div>

        <!-- Doctors Table -->
        <table class="table table-modern">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>License</th>
                    <th>Bio</th>
                    <th>Fee</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="doctorTable">
                <?php foreach($doctors as $index => $d): ?>
                <tr>
                    <td><?= $index+1; ?></td>
                    <td><?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']); ?></td>
                    <td><?= htmlspecialchars($d['email']); ?></td>
                    <td><?= htmlspecialchars($d['phone']); ?></td>
                    <td><?= htmlspecialchars($d['license_number']); ?></td>
                    <td><?= htmlspecialchars($d['bio']); ?></td>
                    <td><?= htmlspecialchars($d['consultation_fee']); ?></td>
                    <td>
                        <a href="edit_doctor.php?id=<?= $d['id']; ?>" class="btn btn-edit btn-sm me-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="delete_doctor.php?id=<?= $d['id']; ?>" class="btn btn-delete btn-sm"
                           onclick="return confirm('Are you sure you want to delete this doctor?');">
                           <i class="bi bi-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</div>

<!-- Add Doctor Modal -->
<div class="modal fade" id="addDoctorModal" tabindex="-1" aria-labelledby="addDoctorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="add_doctor.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDoctorLabel">Add Doctor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>User</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Select User</option>
                            <?php foreach($usersList as $user): ?>
                                <option value="<?= $user['id']; ?>">
                                    <?= htmlspecialchars($user['first_name'].' '.$user['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>License Number</label>
                        <input type="text" name="license_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Bio</label>
                        <textarea name="bio" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Consultation Fee</label>
                        <input type="number" step="0.01" name="consultation_fee" class="form-control" value="0.00">
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">Save Doctor</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const searchButton = document.getElementById('searchButton');
    const doctorTable = document.getElementById('doctorTable');

    function filterTable() {
        const filter = searchInput.value.toLowerCase();
        const rows = doctorTable.getElementsByTagName('tr');

        Array.from(rows).forEach(row => {
            const cells = row.getElementsByTagName('td');
            const name = cells[1].textContent.toLowerCase();
            const email = cells[2].textContent.toLowerCase();
            const phone = cells[3].textContent.toLowerCase();

            row.style.display = (name.includes(filter) || email.includes(filter) || phone.includes(filter)) ? '' : 'none';
        });
    }

    searchInput.addEventListener('keyup', filterTable);
    searchButton.addEventListener('click', filterTable);
</script>

</body>
</html>
