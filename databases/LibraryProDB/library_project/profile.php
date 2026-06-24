<?php
session_start();
require_once __DIR__ . '/includes/connection.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$user_id = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

// Avatar upload
if (isset($_FILES['avatar']) && !empty($_FILES['avatar']['tmp_name'])) {
    $file = $_FILES['avatar'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

    // Check it's an actual image
    $check = @getimagesize($file['tmp_name']); // suppress warnings
    $file_type = mime_content_type($file['tmp_name']);

    if ($file['error'] === UPLOAD_ERR_OK && $check !== false && in_array($file_type, $allowed_types)) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $newName = "avatar_" . $user_id . "." . $ext;

        $uploadDir = __DIR__ . '/uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadPath = $uploadDir . $newName;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Save only the filename in DB (not full path)
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$newName, $user_id]);

            $_SESSION['success'] = "Avatar uploaded successfully.";
        } else {
            $_SESSION['error'] = "Failed to move uploaded file.";
        }
    } else {
        $_SESSION['error'] = "Invalid avatar file. Allowed types: JPG, PNG, GIF.";
    }

    header("Location: profile.php");
    exit;
}

    // Handle password change
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            $_SESSION['error'] = "Please fill all password fields.";
        } elseif ($new !== $confirm) {
            $_SESSION['error'] = "New password and confirmation do not match.";
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $userPass = $stmt->fetchColumn();

            if (!$userPass || !password_verify($current, $userPass)) {
                $_SESSION['error'] = "Current password is incorrect.";
            } else {
                // Update password
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$newHash, $user_id]);
                $_SESSION['success'] = "Password changed successfully.";
                header("Location: profile.php");
                exit;
            }
        }
    }
}

// Fetch user info
$stmt = $pdo->prepare("SELECT firstname, lastname, email, avatar, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: dashboard.php");
    exit;
}

// Count borrowed books
$bookStmt = $pdo->prepare("SELECT COUNT(*) FROM borrowed_books WHERE user_id = ? AND status = 'borrowed'");
$bookStmt->execute([$user_id]);
$bookCount = $bookStmt->fetchColumn();

// Count borrowed devices
$deviceStmt = $pdo->prepare("SELECT COUNT(*) FROM device_borrowings WHERE user_id = ? AND status = 'borrowed'");
$deviceStmt->execute([$user_id]);
$deviceCount = $deviceStmt->fetchColumn();

// Helper for avatar URL
$avatar_url = ($user['avatar'] && file_exists(__DIR__ . "/uploads/avatars/" . $user['avatar']))
    ? "uploads/avatars/" . $user['avatar']
    : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>👤 Profile - LibraryPro</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Navbar styles */
    .navbar.bg-primary {
      background-color: #007bff !important;
      box-shadow: 0 4px 10px rgba(0, 123, 255, 0.6);
      height: 50px;
      padding: 0;
    }
    .navbar .container,
    .navbar-nav {
      height: 50px;
      display: flex;
      align-items: center;
    }
    .navbar-brand {
      font-weight: 900;
      font-size: 1.5rem;
      color: #fff !important;
    }
    .nav-link {
      color: #fff !important;
      font-weight: 600;
      padding: 0 10px;
    }
    .nav-link.active,
    .nav-link:hover {
      color: #ffd6e8 !important;
    }
    body {
      padding-top: 70px;
      background-color: #f8f9fa;
    }
    .profile-card {
      background: white;
      border-radius: 1rem;
      box-shadow: 0 0 25px rgba(0,0,0,0.1);
      padding: 2rem;
      max-width: 600px;
      margin: auto;
    }
    .profile-avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: #007bff33;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3rem;
      color: #007bff;
      margin: 0 auto 1rem;
      overflow: hidden;
    }
    .profile-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .profile-name {
      font-size: 1.5rem;
      font-weight: 700;
      text-align: center;
      margin-bottom: 1rem;
    }
    .btn-back {
      margin-top: 1.5rem;
    }
    .form-section {
      margin-top: 2rem;
    }
    .form-section h4 {
      margin-bottom: 1rem;
      font-weight: 600;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
  <div class="container">
    <a class="navbar-brand" href="#">LibraryPro</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="dashboard.php">🏠 Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="borrow.php">📚 Borrow Book</a></li>
        <li class="nav-item"><a class="nav-link" href="borrow_device.php">💻 Borrow Device</a></li>
        <li class="nav-item"><a class="nav-link" href="return_device.php">🔁 Return Device</a></li>
        <li class="nav-item"><a class="nav-link" href="return.php">🔁 Return Books</a></li>
        <li class="nav-item"><a class="nav-link" href="wishlist.php">📝 Wishlist</a></li>
        <li class="nav-item"><a class="nav-link active" href="profile.php">👤 Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">🚪 Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="profile-card mt-5">

  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <div class="profile-avatar">
    <?php if ($avatar_url): ?>
      <img src="<?= htmlspecialchars($avatar_url) ?>" alt="Avatar" />
    <?php else: ?>
      <?= strtoupper(substr($user['firstname'], 0, 1)) ?>
    <?php endif; ?>
  </div>

  <div class="profile-name"><?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?></div>

  <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
  <hr>
  <p><strong>📚 Books Borrowed:</strong> <?= $bookCount ?></p>
  <p><strong>💻 Devices Borrowed:</strong> <?= $deviceCount ?></p>
  <p><strong>📅 Joined:</strong> <?= date("F d, Y", strtotime($user['created_at'])) ?></p>

  <a href="edit_profile.php" class="btn btn-primary mt-3">Edit Profile</a>

  <!-- Update Avatar -->
  <div class="col-md-6">
    <h5>Update Avatar</h5>
    <form method="POST" enctype="multipart/form-data" novalidate>
        <div class="mb-3">
            <label for="avatar" class="form-label">Upload Avatar (JPG, JPEG, PNG, GIF)</label>
            <input type="file" id="avatar" name="avatar" class="form-control" accept=".jpg,.jpeg,.png,.gif" required />
        </div>
        <button type="submit" class="btn btn-info">Update Avatar</button>
    </form>
  </div>

  <div class="form-section">
    <h4>Change Password</h4>
    <form method="post" action="profile.php">
      <input type="hidden" name="change_password" value="1">
      <div class="mb-3">
        <label for="current_password" class="form-label">Current Password</label>
        <input type="password" name="current_password" id="current_password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="new_password" class="form-label">New Password</label>
        <input type="password" name="new_password" id="new_password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label for="confirm_password" class="form-label">Confirm New Password</label>
        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-warning">Change Password</button>
    </form>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
