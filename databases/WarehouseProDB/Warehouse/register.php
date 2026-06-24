<?php
session_start();
require 'config.php';

$error = '';
$success = '';

// Set your secret admin registration code here
define('ADMIN_REG_CODE', 'SuperSecretAdmin123'); // CHANGE THIS!

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $admin_code = $_POST['admin_code'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif ($role === 'admin' && $admin_code !== ADMIN_REG_CODE) {
        $error = 'Invalid Admin Registration Code.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email already taken.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $db_role = ($role === 'admin') ? 'admin' : 'staff';

            $stmt = $pdo->prepare('INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$username, $email, $hashed_password, $db_role]);

            $success = 'Registration successful! <a href="login.php">Click here to login</a>.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register - Warehouse System</title>

  <!-- Bootstrap + FontAwesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <style>
    body {
      background: linear-gradient(135deg, #f0f4f8, #d9e2ec);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .register-container {
      max-width: 480px;
      margin: 80px auto;
      padding: 40px;
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .register-title {
      font-weight: bold;
      text-align: center;
      margin-bottom: 25px;
      color: #1d3557;
    }

    .form-label {
      font-weight: 500;
    }

    .btn-primary {
      background-color: #1d3557;
      border-color: #1d3557;
    }

    .btn-primary:hover {
      background-color: #16324f;
    }

    .register-link {
      display: block;
      text-align: center;
      margin-top: 15px;
    }

    .register-link a {
      color: #1d3557;
      text-decoration: none;
      font-weight: 500;
    }

    .register-link a:hover {
      text-decoration: underline;
    }
  </style>

  <script>
    function toggleAdminCodeField() {
      const role = document.getElementById('role').value;
      document.getElementById('adminCodeDiv').style.display = (role === 'admin') ? 'block' : 'none';
    }

    window.onload = toggleAdminCodeField;
  </script>
</head>
<body>

<div class="register-container">
  <h2 class="register-title"><i class="fas fa-user-plus me-2"></i>Warehouse Registration</h2>

  <?php if ($error): ?>
    <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
  <?php elseif ($success): ?>
    <div class="alert alert-success text-center"><?= $success ?></div>
  <?php endif; ?>

  <form method="post" action="">
    <div class="mb-3">
      <label for="username" class="form-label">Username</label>
      <input type="text" class="form-control" id="username" name="username" required
        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label for="email" class="form-label">Email Address</label>
      <input type="email" class="form-control" id="email" name="email" required
        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label for="role" class="form-label">Register As</label>
      <select class="form-select" id="role" name="role" onchange="toggleAdminCodeField()" required>
        <option value="user" <?= ($_POST['role'] ?? '') === 'user' ? 'selected' : '' ?>>User (Staff)</option>
        <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
      </select>
    </div>

    <div class="mb-3" id="adminCodeDiv" style="display: none;">
      <label for="admin_code" class="form-label">Admin Registration Code</label>
      <input type="password" class="form-control" id="admin_code" name="admin_code"
        value="<?= htmlspecialchars($_POST['admin_code'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label for="password" class="form-label">Password</label>
      <input type="password" class="form-control" id="password" name="password" required>
    </div>

    <div class="mb-4">
      <label for="confirm_password" class="form-label">Confirm Password</label>
      <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
    </div>

    <button type="submit" class="btn btn-primary w-100">
      <i class="fas fa-user-check me-1"></i> Register
    </button>
  </form>

  <div class="register-link">
    Already have an account? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login here</a>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
