<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Admin';

// CSRF token generation and validation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function checkCsrfToken($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Initialize messages
$success = $error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token for all POST requests
    if (!isset($_POST['csrf_token']) || !checkCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token. Please reload the page.";
    } else {
        // Avatar upload
        if (isset($_FILES['avatar'])) {
            $file = $_FILES['avatar'];
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

            if ($file['error'] === UPLOAD_ERR_OK && in_array(mime_content_type($file['tmp_name']), $allowedMimeTypes)) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $fileName = "admin_avatar_{$admin_id}." . $ext;
                $uploadDir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $destination = $uploadDir . $fileName;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->execute([$fileName, $admin_id]);
                    $success = "Avatar updated successfully.";
                } else {
                    $error = "Failed to move uploaded file.";
                }
            } else {
                $error = "Invalid file type or upload error.";
            }
        }

        // Change password
        if (isset($_POST['change_password'])) {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!$current || !$new || !$confirm) {
                $error = "All password fields are required.";
            } elseif ($new !== $confirm) {
                $error = "New passwords do not match.";
            } else {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$admin_id]);
                $hash = $stmt->fetchColumn();

                if (!password_verify($current, $hash)) {
                    $error = "Current password is incorrect.";
                } else {
                    $newHash = password_hash($new, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$newHash, $admin_id]);
                    $success = "Password changed successfully.";
                }
            }
        }

        // Update profile
        if (isset($_POST['update_profile'])) {
            $firstname = trim($_POST['firstname'] ?? '');
            $lastname = trim($_POST['lastname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            if (!$firstname || !$lastname || !$email) {
                $error = "All profile fields are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Invalid email format.";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ? WHERE id = ?");
                $stmt->execute([$firstname, $lastname, $email, $admin_id]);
                $success = "Profile updated successfully.";
            }
        }
    }

    // After handling POST, redirect to avoid form resubmission
    if ($success) {
        $_SESSION['success'] = $success;
        header("Location: admin_profile.php");
        exit;
    }
    if ($error) {
        $_SESSION['error'] = $error;
        header("Location: admin_profile.php");
        exit;
    }
}

// Fetch admin data for display
$stmt = $pdo->prepare("SELECT firstname, lastname, email, avatar, created_at FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Borrowed counts for this admin user
$stmtBooks = $pdo->prepare("SELECT COUNT(*) FROM borrowed_books WHERE user_id = ? AND status = 'borrowed'");
$stmtBooks->execute([$admin_id]);
$booksBorrowed = $stmtBooks->fetchColumn();

$stmtDevices = $pdo->prepare("SELECT COUNT(*) FROM device_borrowings WHERE user_id = ? AND status = 'borrowed'");
$stmtDevices->execute([$admin_id]);
$devicesBorrowed = $stmtDevices->fetchColumn();

// Total library stats (all users)
$totalBooks = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalDevices = $pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
$totalBooksBorrowed = $pdo->query("SELECT COUNT(*) FROM borrowed_books WHERE status = 'borrowed'")->fetchColumn();
$totalDevicesBorrowed = $pdo->query("SELECT COUNT(*) FROM device_borrowings WHERE status = 'borrowed'")->fetchColumn();

// Prepare avatar URL or fallback default avatar
$avatarPath = __DIR__ . "/uploads/avatars/" . ($admin['avatar'] ?? '');
$avatar_url = (isset($admin['avatar']) && file_exists($avatarPath))
    ? "uploads/avatars/" . $admin['avatar']
    : "assets/default_avatar.png";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Profile - LibraPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            min-height: 100vh;
            display: flex;
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            min-width: 220px;
            background-color: #343a40;
            color: #fff;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            padding: 1rem;
            height: 100vh;
            position: fixed;
        }
        .sidebar h4 {
            color: #adb5bd;
            margin-bottom: 1.5rem;
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
        .sidebar a:hover,
        .sidebar a.active {
            background-color: #495057;
            color: #fff;
        }
        .content {
            margin-left: 220px;
            flex-grow: 1;
            padding: 2rem;
        }
        .navbar-custom {
            background-color: #343a40;
            color: white;
            padding: 0.5rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.375rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .profile-card {
            max-width: 850px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin: auto;
        }
        .avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #007bff;
            margin-bottom: 1rem;
        }
        .profile-header {
            text-align: center;
            margin-bottom: 25px;
        }
        .stats {
            font-size: 1.1rem;
            color: #333;
        }
        .form-section {
            margin-top: 30px;
        }
        .form-section h5 {
            margin-bottom: 15px;
            color: #007bff;
        }
        .form-control:focus {
            border-color: #495057;
            box-shadow: 0 0 0 0.25rem rgba(73,80,87,.25);
        }
    </style>
</head>
<body>
<nav class="sidebar" aria-label="Sidebar navigation">
    <h4>LibraryPro Admin</h4>
    <a href="admin.php">Dashboard</a>
    <a href="admin_profile.php" class="active">Profile</a>
    <a href="books_admin.php">Books</a>
    <a href="devices_admin.php">Devices</a>
    <a href="admin_wishlist.php">Wishlist</a>
    <a href="logout.php">Logout</a>
</nav>

<main class="content" role="main">
    <div class="navbar-custom">
        <h2>Admin Profile</h2>
        <div>Welcome, <strong><?= htmlspecialchars($username) ?></strong></div>
    </div>

    <?php if ($success = $_SESSION['success'] ?? null): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($success) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if ($error = $_SESSION['error'] ?? null): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

<section class="profile-card" aria-label="Admin profile information and settings">
    <header class="profile-header">
        <img src="<?= htmlspecialchars($avatar_url) ?>" alt="Admin avatar" class="avatar" loading="lazy" />
        <h3><?= htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']) ?></h3>
        <p class="text-muted"><?= htmlspecialchars($admin['email']) ?></p>
        <p class="text-muted">📅 Joined: <?= date("F j, Y", strtotime($admin['created_at'])) ?></p>
        <p class="stats">
            📚 Books Borrowed: <?= (int)$booksBorrowed ?> &nbsp;&nbsp; 💻 Devices Borrowed: <?= (int)$devicesBorrowed ?>
        </p>
        <p class="stats">
            📚 Total Books: <?= (int)$totalBooks ?> &nbsp;&nbsp; 💻 Total Devices: <?= (int)$totalDevices ?>
        </p>
    </header>

<div class="row form-section">
    <!-- Left: Update Profile Details -->
    <div class="col-md-6">
        <h5>Update Profile Details</h5>
        <form method="POST" novalidate>
            <input type="hidden" name="update_profile" value="1" />
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <div class="mb-3">
                <label for="firstname" class="form-label">First Name</label>
                <input type="text" id="firstname" name="firstname" class="form-control" required
                    value="<?= htmlspecialchars($admin['firstname']) ?>" />
            </div>
            <div class="mb-3">
                <label for="lastname" class="form-label">Last Name</label>
                <input type="text" id="lastname" name="lastname" class="form-control" required
                    value="<?= htmlspecialchars($admin['lastname']) ?>" />
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control" required
                    value="<?= htmlspecialchars($admin['email']) ?>" />
            </div>
            <button type="submit" class="btn btn-primary">Update Profile</button>
        </form>
    </div>

    <!-- Right Top: Update Avatar -->
    <div class="col-md-6">
        <h5>Update Avatar</h5>
        <form method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <div class="mb-3">
                <label for="avatar" class="form-label">Upload Avatar (JPEG, PNG, GIF)</label>
                <input type="file" id="avatar" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.gif" required />
            </div>
            <button type="submit" class="btn btn-info">Update Avatar</button>
        </form>

        <!-- Right Bottom: Change Password (Right under Avatar) -->
        <h5 class="mt-4">Change Password</h5>
        <form method="POST" novalidate>
            <input type="hidden" name="change_password" value="1" />
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>" />
            <div class="mb-3">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required />
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6" />
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6" />
            </div>
            <button type="submit" class="btn btn-warning">Change Password</button>
        </form>
    </div>
</div>
</section>
</main>
</body>
</html>
