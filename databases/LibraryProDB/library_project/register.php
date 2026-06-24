<?php
require_once 'includes/connection.php';
session_start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'] ?? 'member';  // Default to member if missing

    // Basic validation
    if (!$firstname || !$lastname || !$email || !$password || !$confirm_password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($role, ['member', 'admin'])) {
        $error = 'Invalid role selected.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered.';
        } else {
            // Insert new user
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (firstname, lastname, email, password, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$firstname, $lastname, $email, $hashed, $role]);
            $_SESSION['success'] = 'Registration successful. Please login.';
            header('Location: login.php');
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register - LibraPro</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      background: linear-gradient(135deg, #667eea, #764ba2);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .register-container {
      background: white;
      padding: 2.5rem 3rem;
      border-radius: 12px;
      box-shadow: 0 15px 30px rgba(0,0,0,0.2);
      max-width: 420px;
      width: 100%;
    }
    .register-container h2 {
      font-weight: 700;
      color: #4b2995;
      margin-bottom: 1.5rem;
      text-align: center;
    }
    .form-label {
      font-weight: 600;
      color: #4b2995;
    }
    .btn-primary {
      background: #764ba2;
      border: none;
    }
    .btn-primary:hover {
      background: #5a2c85;
    }
    .form-select {
      color: #4b2995;
      font-weight: 600;
    }
    .error-msg {
      color: #e74c3c;
      font-weight: 600;
      margin-bottom: 1rem;
      text-align: center;
    }
    .success-msg {
      color: #27ae60;
      font-weight: 600;
      margin-bottom: 1rem;
      text-align: center;
    }
  </style>
</head>
<body>

  <div class="register-container">
    <h2>Create Your Account</h2>

    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php elseif (!empty($_SESSION['success'])): ?>
      <div class="success-msg"><?= htmlspecialchars($_SESSION['success']) ?></div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="mb-3">
        <label for="firstname" class="form-label">First Name</label>
        <input type="text" id="firstname" name="firstname" class="form-control" required value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>" />
      </div>
      <div class="mb-3">
        <label for="lastname" class="form-label">Last Name</label>
        <input type="text" id="lastname" name="lastname" class="form-control" required value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>" />
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <input type="email" id="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" id="password" name="password" class="form-control" required />
      </div>
      <div class="mb-3">
        <label for="confirm_password" class="form-label">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required />
      </div>

      <div class="mb-4">
        <label for="role" class="form-label">Register as</label>
        <select id="role" name="role" class="form-select" required>
          <option value="member" <?= (($_POST['role'] ?? '') === 'member') ? 'selected' : '' ?>>User (Member)</option>
          <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2">Register</button>
    </form>

    <p class="mt-3 text-center text-muted">Already have an account? <a href="login.php" class="text-decoration-none">Login here</a></p>
  </div>

</body>
</html>
