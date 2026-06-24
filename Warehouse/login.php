<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = strtolower(trim($user['role'])); // crucial

        switch ($_SESSION['role']) {
            case 'admin':
                header('Location: admin_dashboard.php');
                break;
            case 'manager':
                header('Location: manager_dashboard.php');
                break;
            case 'staff':
            case 'user':
                header('Location: dashboard.php');
                break;
            default:
                session_destroy();
                echo 'Unknown role!';
                exit;
        }
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - Warehouse System</title>

  <!-- Bootstrap + FontAwesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

  <style>
    body {
      background: linear-gradient(135deg, #f0f4f8, #d9e2ec);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .login-container {
      max-width: 460px;
      margin: 100px auto;
      padding: 40px;
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .login-title {
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

    .login-link {
      display: block;
      text-align: center;
      margin-top: 15px;
    }

    .login-link a {
      color: #1d3557;
      text-decoration: none;
      font-weight: 500;
    }

    .login-link a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<div class="login-container">
  <h2 class="login-title"><i class="fas fa-sign-in-alt me-2"></i>Warehouse Login</h2>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
  <?php elseif (!empty($success)): ?>
    <div class="alert alert-success text-center"><?= $success ?></div>
  <?php endif; ?>

  <form method="post" action="">
    <div class="mb-3">
      <label for="username" class="form-label">Username or Email</label>
      <input type="text" class="form-control" id="username" name="username" required
        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>

    <div class="mb-4">
      <label for="password" class="form-label">Password</label>
      <input type="password" class="form-control" id="password" name="password" required>
    </div>

    <button type="submit" class="btn btn-primary w-100">
      <i class="fas fa-arrow-right-to-bracket me-1"></i> Login
    </button>
  </form>

  <div class="login-link">
    Don’t have an account? <a href="register.php"><i class="fas fa-user-plus"></i> Register here</a>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
