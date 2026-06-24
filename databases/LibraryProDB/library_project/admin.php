<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

// Fetch all users
try {
    $stmt = $pdo->query("SELECT id, firstname, lastname, email, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - LibraryPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            min-height: 100vh;
            display: flex;
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            min-width: 220px;
            max-width: 220px;
            background-color: #343a40;
            color: #fff;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            padding: 1rem;
        }
        .sidebar a {
            color: #adb5bd;
            text-decoration: none;
            padding: 0.75rem 1rem;
            display: block;
            border-radius: 5px;
            margin-bottom: 0.25rem;
            transition: background-color 0.2s;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: #495057;
            color: #fff;
        }
        .content {
            flex-grow: 1;
            padding: 2rem;
        }
        .navbar-custom {
            background-color: #343a40;
            color: white;
            padding: 0.5rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.375rem;
        }
        .admin-header {
            background-color: #343a40;
            color: #ffffff;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            position: sticky;
            border-radius: 10px;
            top: 0;
            z-index: 1030;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .admin-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #ffffff;
        }
        .admin-header .welcome {
            font-size: 1rem;
            color: #ffffff;
        }
        .table thead {
            background-color: #495057;
            color: white;
        }
        .table tbody tr:hover {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>

<nav class="sidebar">
    <h4 class="mb-4">LibraryPro Admin</h4>
    <a href="admin.php" class="active">Dashboard</a>
    <a href="admin_profile.php">Profile</a>
    <a href="books_admin.php">Books</a>
    <a href="devices_admin.php">Devices</a>
    <a href="admin_wishlist.php">Wishlist</a>
    <a href="logout.php">Logout</a>
</nav>

<main class="content">
    <header class="admin-header">
        <h2>📊 Admin Dashboard</h2>
        <div class="welcome">Welcome, <strong><?= htmlspecialchars($username) ?></strong></div>
    </header>

    <section>
        <h3>User Management</h3>

        <!-- Show status messages -->
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">✅ User deleted successfully.</div>
        <?php elseif (isset($_GET['error'])): ?>
            <?php
                $errorMessages = [
                    'cannot_delete_self' => "⚠️ You cannot delete your own account.",
                    'cannot_delete_admin' => "⚠️ You cannot delete another admin.",
                    'user_not_found' => "❌ User not found.",
                    'delete_failed' => "❌ Failed to delete user.",
                    'invalid_request' => "❌ Invalid request."
                ];
                $errorKey = $_GET['error'];
            ?>
            <div class="alert alert-danger"><?= $errorMessages[$errorKey] ?? "❌ Unknown error." ?></div>
        <?php endif; ?>

        <?php if (empty($users)): ?>
            <p>No users found in the database.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Firstname</th>
                            <th>Lastname</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['firstname']) ?></td>
                                <td><?= htmlspecialchars($user['lastname']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['role']) ?></td>
                                <td><?= htmlspecialchars($user['created_at']) ?></td>
                                <td>
                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                        <form method="post" action="deleteUser.php" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <em>You</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
