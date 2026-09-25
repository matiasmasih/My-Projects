<?php
session_start();
include 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role_id = $_POST['role_id'];

if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
} else {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$email]);
  if ($stmt->rowCount() > 0) {
    $error = "Email already exists!";
} else {
  $password_hash = password_hash($password, PASSWORD_BCRYPT);
try {
  $stmt = $pdo->prepare("INSERT INTO users (role_id, first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?, ?)");
  $stmt->execute([$role_id, $first_name, $last_name, $email, $password_hash]);
    $success = "Registration successful! You can now login.";
} catch (PDOException $e) {
  $error = "Error: " . $e->getMessage();
    }
   }
  }
}

$roles_stmt = $pdo->query("SELECT id, name FROM roles");
$roles = $roles_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
  <title>Register - MediCare Hospital</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: 'Inter', sans-serif;
}

body {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: url('https://images.unsplash.com/photo-1559757148-5c350d0d3c56?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80') center/cover;
  padding: 20px;
}

.register-container {
  background: white;
  padding: 35px;
  border-radius: 20px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-width: 550px;
}

.hospital-header {
  text-align: center;
  margin-bottom: 20px;
}

.hospital-icon {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #667eea, #764ba2);
  border-radius: 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 10px;
}

.hospital-icon i {
  font-size: 2rem;
  color: white;
}

.hospital-header h1 {
  font-size: 2rem;
  color: #333;
  margin-bottom: 3px;
}

.hospital-header p {
  color: #666;
  font-size: 1rem;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 5px;
  margin-bottom: 9px;
}

.input-group {
  position: relative;
  margin-bottom: 9px;
}

.input-group.full-width {
  grid-column: 1 / -1;
}

.input-group i {
  position: absolute;
  left: 9px;
  top: 50%;
  transform: translateY(-50%);
  color: #667eea;
  font-size: 1.1rem;
}

.input-group input,
.input-group select {
  width: 98%;
  padding: 10px 10px 10px 35px;
  border: 2px solid #e0e0e0;
  border-radius: 10px;
  font-size: 1rem;
  background: white;
  transition: border-color 0.3s;
}

.input-group input:focus,
.input-group select:focus {
  outline: none;
  border-color: #667eea;
}

.register-btn {
  width: 40%;
  padding: 15px;
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.3s;
  margin-bottom: 20px;
}

.register-btn:hover {
  transform: translateY(-2px);
}

.error-message {
  background: #ffebee;
  color: #c62828;
  padding: 15px;
  border-radius: 10px;
  margin-bottom: 20px;
  border-left: 4px solid #c62828;
}

.success-message {
  background: #e8f5e8;
  color: #2e7d32;
  padding: 15px;
  border-radius: 10px;
  margin-bottom: 20px;
  border-left: 4px solid #2e7d32;
}

.login-section {
  text-align: center;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 10px;
  margin-top: 10px;
}

.login-text {
  color: #666;
  margin-bottom: 10px;
  font-size: 1rem;
}

.login-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 12px 25px;
  background: #667eea;
  color: white;
  text-decoration: none;
  border-radius: 8px;
  font-weight: 600;
  transition: background 0.3s;
}

.login-btn:hover {
  background: #5a6fd8;
}

.password-toggle {
  position: absolute;
  right: 15px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #667eea;
  cursor: pointer;
}

@media (max-width: 480px) {
.register-container {
  padding: 30px 20px;
}

.form-grid {
  grid-template-columns: 1fr;
}

.hospital-header h1 {
  font-size: 1.7rem;
 }
}
</style>
</head>
<body>

<div class="register-container">
    <div class="hospital-header">
        <div class="hospital-icon">
            <i class="bi bi-person-plus"></i>
        </div>
        <h1>Join Our Team</h1>
        <p>Create your MediCare Hospital account</p>
    </div>

    <?php if($error): ?>
        <div class="error-message">
            <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="success-message">
            <i class="bi bi-check-circle"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="registerForm">
        <div class="form-grid">
            <div class="input-group">
                <i class="bi bi-person"></i>
                <input type="text" name="first_name" placeholder="First Name" required value="<?= isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' ?>">
            </div>

            <div class="input-group">
                <i class="bi bi-person"></i>
                <input type="text" name="last_name" placeholder="Last Name" required value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>">
            </div>
        </div>

        <div class="input-group full-width">
            <i class="bi bi-envelope"></i>
            <input type="email" name="email" placeholder="Email Address" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
        </div>

        <div class="input-group full-width">
            <i class="bi bi-briefcase"></i>
            <select name="role_id" required>
                <option value="" disabled selected>Select Your Position</option>
                <?php foreach($roles as $role): ?>
                    <option value="<?= $role['id'] ?>" <?= isset($_POST['role_id']) && $_POST['role_id'] == $role['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="input-group full-width">
            <i class="bi bi-lock"></i>
            <input type="password" name="password" id="password" placeholder="Create Password" required>
            <button type="button" class="password-toggle" id="passwordToggle">
                <i class="bi bi-eye"></i>
            </button>
        </div>

        <div class="input-group full-width">
            <i class="bi bi-lock-fill"></i>
            <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm Password" required>
            <button type="button" class="password-toggle" id="confirmPasswordToggle">
                <i class="bi bi-eye"></i>
            </button>
        </div>

        <button type="submit" class="register-btn">
            Create Account
        </button>
    </form>

    <!-- LOGIN LINK SECTION - This should be clearly visible -->
    <div class="login-section">
        <div class="login-text">Already have an account?</div>
        <a href="login.php" class="login-btn">
            <i class="bi bi-box-arrow-in-right"></i>
            Login to Existing Account
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordToggle = document.getElementById('passwordToggle');
    const confirmPasswordToggle = document.getElementById('confirmPasswordToggle');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');

    // Password toggle functionality
    passwordToggle.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
    });

    confirmPasswordToggle.addEventListener('click', function() {
        const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPasswordInput.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
    });

    // Password match validation
    confirmPasswordInput.addEventListener('input', function() {
        if (passwordInput.value !== this.value && this.value.length > 0) {
            this.style.borderColor = '#c62828';
        } else {
            this.style.borderColor = '#e0e0e0';
        }
    });

    passwordInput.addEventListener('input', function() {
        if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.style.borderColor = '#c62828';
        } else if (confirmPasswordInput.value) {
            confirmPasswordInput.style.borderColor = '#4caf50';
        }
    });
});
</script>

</body>
</html>
