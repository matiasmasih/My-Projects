<?php
session_start();
include 'config.php';

// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit;
}

// --- Fetch data ---
$usersStmt = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email, u.role_id, r.name AS role 
    FROM users u 
    JOIN roles r ON u.role_id = r.id
");
$users = $usersStmt->fetchAll();

$rolesStmt = $pdo->query("SELECT id, name FROM roles");
$roles = $rolesStmt->fetchAll();

// --- Handle role update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['role_id'])) {
    $userId = (int) $_POST['user_id'];
    $roleId = (int) $_POST['role_id'];

    $updateStmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
    $updateStmt->execute([$roleId, $userId]);

    $_SESSION['message'] = "Role updated successfully!";
    header("Location: roles.php");
    exit;
}

// --- Handle add new role ---
if (isset($_POST['new_role'])) {
    $roleName = trim($_POST['new_role']);
    if (!empty($roleName)) {
        $insert = $pdo->prepare("INSERT INTO roles (name) VALUES (?)");
        $insert->execute([$roleName]);
        $_SESSION['message'] = "New role added successfully!";
        header("Location: roles.php");
        exit;
    }
}

// --- Handle delete role ---
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    $delete = $pdo->prepare("DELETE FROM roles WHERE id = ?");
    $delete->execute([$deleteId]);
    $_SESSION['message'] = "Role deleted successfully!";
    header("Location: roles.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Roles</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background: #f8f9fc;
      font-family: 'Poppins', sans-serif;
    }
    .navbar {
      background: linear-gradient(90deg, #1d3557, #457b9d);
    }
    .navbar-brand {
      color: #fff !important;
      font-weight: 600;
    }
    .card {
      border: none;
      border-radius: 1rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    .btn-primary {
      background-color: #1d3557;
      border: none;
    }
    .btn-primary:hover {
      background-color: #16324f;
    }
    .table th {
      background-color: #e9ecef;
      font-weight: 600;
    }
    .search-box {
      border-radius: 25px;
      padding-left: 2.5rem;
      border: 1px solid #ddd;
    }
    .search-icon {
      position: absolute;
      left: 15px;
      top: 8px;
      color: #888;
    }
  </style>
</head>
<body>
  <!-- Top Navbar -->
  <nav class="navbar navbar-expand-lg shadow-sm">
    <div class="container-fluid">
      <a class="navbar-brand" href="admin_dashboard.php"><i class="bi bi-shield-lock"></i> Hospital Admin</a>
      <div class="d-flex align-items-center text-white">
        <i class="bi bi-person-circle me-2"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>
      </div>
    </div>
  </nav>

  <div class="container my-4">
    <?php if (isset($_SESSION['message'])): ?>
      <div class="alert alert-success text-center"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <div class="card p-4 mb-4">
      <h4 class="mb-3"><i class="bi bi-people"></i> Manage User Roles</h4>

      <!-- Search -->
      <div class="position-relative mb-3">
        <i class="bi bi-search search-icon"></i>
        <input type="text" id="searchInput" class="form-control search-box" placeholder="Search users...">
      </div>

      <!-- Table -->
      <div class="table-responsive">
        <table class="table table-striped align-middle" id="rolesTable">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Current Role</th>
              <th>Change Role</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
              <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
              <td><?= htmlspecialchars($user['email']); ?></td>
              <td><span class="badge bg-secondary"><?= htmlspecialchars($user['role']); ?></span></td>
              <td>
                <form method="POST" action="roles.php" class="d-flex">
                  <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                  <select name="role_id" class="form-select form-select-sm me-2">
                    <?php foreach ($roles as $role): ?>
                      <option value="<?= $role['id']; ?>" <?= $role['id'] == $user['role_id'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($role['name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Manage Roles Section -->
    <div class="card p-4">
      <h4 class="mb-3"><i class="bi bi-shield-check"></i> Roles Management</h4>

      <!-- Add Role -->
      <form method="POST" class="d-flex mb-3">
        <input type="text" name="new_role" class="form-control me-2" placeholder="Add new role..." required>
        <button type="submit" class="btn btn-success"><i class="bi bi-plus-lg"></i> Add Role</button>
      </form>

      <!-- Roles List -->
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Role ID</th>
              <th>Role Name</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($roles as $r): ?>
            <tr>
              <td><?= $r['id']; ?></td>
              <td><?= htmlspecialchars($r['name']); ?></td>
              <td>
                <a href="?delete=<?= $r['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this role?')">
                  <i class="bi bi-trash"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Search filter
    document.getElementById('searchInput').addEventListener('keyup', function() {
      const filter = this.value.toLowerCase();
      const rows = document.querySelectorAll('#rolesTable tbody tr');
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
      });
    });
  </script>
</body>
</html>
