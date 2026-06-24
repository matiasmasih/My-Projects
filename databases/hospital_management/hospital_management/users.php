<?php
session_start();
include 'config.php';

// Only allow admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: login.php");
    exit;
}

$loggedInId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$loggedInRole = isset($_SESSION['role_id']) ? $_SESSION['role_id'] : 0;

// Fetch all users with roles
$stmt = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email, r.name AS role_name, u.role_id
    FROM users u
    JOIN roles r ON u.role_id = r.id
    ORDER BY u.id DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
  font-family: 'Montserrat', sans-serif;
  background: #f0f2f5;
  margin: 0;
}
.navbar {
  background: linear-gradient(135deg,#4b6cb7,#182848);
  color: #fff;
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
  width: 220px;
  padding: 20px;
}
.sidebar h4 {
  font-weight: 600;
  margin-bottom: 1rem;
  color: #ffd700;
}
.sidebar a {
  display: block;
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
.table-modern {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.12);
  overflow: hidden;
}
.table-modern th {
  background: linear-gradient(135deg,#36d1dc,#5b86e5);
  color: #fff;
  font-weight: 600;
  text-align: center;
}
.table-modern td, .table-modern th {
  vertical-align: middle;
  text-align: center;
}
.table-modern tbody tr:hover {
  background: rgba(75,108,183,0.1);
  transform: translateX(3px);
  transition: 0.3s;
}
.btn-edit {
  background: #56ab2f;
  color: #fff;
  border: none;
  border-radius: 6px;
}
.btn-edit:hover { background: #3c7d1b; }
.btn-delete {
  background: #ff4e50;
  color: #fff;
  border: none;
  border-radius: 6px;
}
.btn-delete:hover { background: #c43a3a; }
.search-box { max-width: 300px; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Users Management</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout <i class="bi bi-box-arrow-right"></i></a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="d-flex">
  <div class="sidebar">
    <h4>Dashboard Menu</h4>
    <a href="manager_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
    <a href="users.php" class="active"><i class="bi bi-people-fill me-2"></i>Users</a>
    <a href="roles.php"><i class="bi bi-shield-lock me-2"></i>Roles</a>
    <a href="patients.php"><i class="bi bi-person-fill me-2"></i>Patients</a>
    <a href="doctors.php"><i class="bi bi-person-badge me-2"></i>Doctors</a>
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

  <div class="container-fluid p-4">
    <h2>All Users</h2>

    <div class="d-flex mb-3">
      <input type="text" class="form-control search-box me-2" id="searchInput" placeholder="Search users...">
      <button class="btn btn-primary" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>

    <table class="table table-modern">
      <thead>
        <tr>
          <th>#</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="userTable">
        <?php foreach ($users as $index => $user): ?>
          <tr>
            <td><?= $index + 1 ?></td>
            <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['role_name']) ?></td>
            <td>
              <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-edit btn-sm me-1">
                <i class="bi bi-pencil"></i>
              </a>

              <?php if ((int)$user['id'] !== (int)$loggedInId): ?>
                <a href="delete_user.php?id=<?= $user['id'] ?>"
                   class="btn btn-delete btn-sm"
                   onclick="return confirm('Are you sure you want to delete this user?');">
                   <i class="bi bi-trash"></i>
                </a>
              <?php else: ?>
                <span class="text-muted">You</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const userTable = document.getElementById('userTable');

function filterTable() {
  const filter = searchInput.value.toLowerCase();
  const rows = userTable.getElementsByTagName('tr');
  Array.from(rows).forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(filter) ? '' : 'none';
  });
}
searchInput.addEventListener('keyup', filterTable);
searchButton.addEventListener('click', filterTable);
</script>
</body>
</html>
