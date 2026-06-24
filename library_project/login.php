<?php
require_once 'includes/connection.php';
session_start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!$email || !$password) {
        $error = 'Please enter email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: admin.php');
            } else {
                header('Location: dashboard.php');
            }
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Login - Library Project</title>
<style>
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f7f9fc;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
  }
  .container {
    background: white;
    padding: 2rem 3rem;
    border-radius: 8px;
    box-shadow: 0 8px 20px rgb(0 0 0 / 0.1);
    width: 100%;
    max-width: 400px;
  }
  h2 {
    text-align: center;
    margin-bottom: 1.5rem;
    color: #333;
  }
  form input[type="email"],
  form input[type="password"] {
    width: 100%;
    padding: 0.75rem;
    margin-bottom: 1.2rem;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
    transition: border-color 0.3s;
  }
  form input[type="email"]:focus,
  form input[type="password"]:focus {
    border-color: #0077ff;
    outline: none;
  }
  button {
    width: 100%;
    padding: 0.75rem;
    background-color: #0077ff;
    border: none;
    border-radius: 5px;       
    color: white;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: background-color 0.3s;
  }
  button:hover {
    background-color: #005bb5;
  }
  .error {
    background-color: #ffe6e6;
    color: #cc0000;
    border: 1px solid #cc0000;
    padding: 0.8rem;
    border-radius: 5px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
  }
  .success {
    background-color: #e6f4ea;
    color: #2d6a4f;
    border: 1px solid #2d6a4f;
    padding: 0.8rem;
    border-radius: 5px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
  }
  .footer-link {
    text-align: center;
    margin-top: 1.2rem;
    font-size: 0.9rem;
  }
  .footer-link a {
    color: #0077ff;
    text-decoration: none;
  }
  .footer-link a:hover {
    text-decoration: underline;
  }
  .note {
    font-size: 0.85rem;
    color: #555;
    margin-bottom: 1.2rem;
    text-align: center;
  }
</style>
</head>
<body>
  <div class="container">
    <h2>Login to Your Account</h2>
    <?php if (!empty($_SESSION['success'])): ?>
      <div class="success"><?= htmlspecialchars($_SESSION['success']) ?></div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="note">Login as <strong>Member</strong> or <strong>Admin</strong> using your registered email.</div>

    <form method="post" action="login.php" novalidate>
      <input type="email" name="email" placeholder="Email Address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
      <input type="password" name="password" placeholder="Password" required />
      <button type="submit">Login</button>
    </form>
    <div class="footer-link">
      Don't have an account? <a href="register.php">Register here</a>
    </div>
  </div>
</body>
</html>
