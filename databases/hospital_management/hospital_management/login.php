<?php
session_start();
include 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Fetch user by email
    $stmt = $pdo->prepare("SELECT id, password_hash, role_id, first_name, last_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];

        // Redirect based on role
        switch($user['role_id']){
            case 1: header("Location: admin_dashboard.php"); break;
            case 2: header("Location: manager_dashboard.php"); break;
            case 3: 
            case 4: header("Location: doctor_dashboard.php"); break;
            default: header("Location: staff_dashboard.php"); break;
        }
        exit;
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MediCare Hospital | Login</title>
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

body::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background:
    radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%);
  animation: float 6s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-20px); }
}

.login-container {
  background: transparent;
  backdrop-filter: blur(20px);
  padding: 50px 40px;
  border-radius: 24px;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.1);
  width: 100%;
  max-width: 440px;
  position: relative;
  z-index: 2;
  transition: all 0.4s ease;
}

.login-container:hover {
  transform: translateY(-5px);
  box-shadow: 0 35px 60px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.15);
}

.hospital-header {
  text-align: center;
  margin-bottom: 40px;
}

.hospital-icon {
  width: 80px;
  height: 80px;
  background: linear-gradient(135deg, #667eea, #764ba2);
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
  box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
}

.hospital-icon i {
  font-size: 2.5rem;
  color: white;
}

.hospital-header h1 {
  font-size: 2.2rem;
  font-weight: 700;
  background: linear-gradient(135deg, #667eea, #764ba2);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  margin-bottom: 8px;
}

.hospital-header p {
  color: white;
  font-size: 1rem;
  font-weight: 400;
}

.input-group {
  position: relative;
  margin-bottom: 25px;
}

.input-group i {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  color: #667eea;
  font-size: 1.2rem;
  z-index: 2;
  transition: all 0.3s ease;
}

.input-group input {
  width: 95%;
  padding: 16px 20px 16px 50px;
  border: 2px solid #e9ecef;
  border-radius: 14px;
  font-size: 1rem;
  font-weight: 400;
  background: transparent;
  transition: all 0.3s ease;
  color: black;
}

.input-group input:focus {
  outline: none;
  border-color: #667eea;
  background: transparent;
  box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.input-group input:focus + i {
  color: #764ba2;
  transform: translateY(-50%) scale(1.1);
}

.input-group input.error {
  border-color: #ff6b6b;
  background: #fff5f5;
}

/* WHITE PLACEHOLDER STYLES */
.input-group input::placeholder {
  color: white;
  opacity: 0.9;
}

.input-group select option[value=""][disabled] {
  color: white;
  opacity: 0.8;
}

.input-group select option:not([value=""]) {
  color: #333;
}

/* Vendor prefixes for placeholder */
.input-group input::-webkit-input-placeholder {
  color: white;
  opacity: 0.9;
}

.input-group input::-moz-placeholder {
  color: white;
  opacity: 0.9;
}

.input-group input:-ms-input-placeholder {
  color: white;
  opacity: 0.9;
}

.input-group input:-moz-placeholder {
  color: white;
  opacity: 0.9;
}

.login-btn {
  width: 30%;
  padding: 16px;
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white;
  border: none;
  border-radius: 14px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 10px;
  position: relative;
  overflow: hidden;
}

.login-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
}

.login-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  transform: none;
}

.login-btn .spinner {
  display: none;
  width: 20px;
  height: 20px;
  border: 2px solid transparent;
  border-top: 2px solid #ffffff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error-message {
  background: linear-gradient(135deg, #ff6b6b, #ee5a52);
  color: white;
  padding: 14px 18px;
  border-radius: 12px;
  margin-bottom: 25px;
  font-weight: 500;
  font-size: 0.95rem;
  text-align: center;
  box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
  display: none;
}

.error-message.show {
  display: block;
  animation: slideDown 0.3s ease;
}

@keyframes slideDown {
from {
  opacity: 0;
  transform: translateY(-10px);
}

to {
  opacity: 1;
  transform: translateY(0);
 }
}

.login-footer {
  margin-top: 30px;
  text-align: center;
  color: #ffffff;
  font-size: 0.95rem;
}

.register-link {
  color: #667eea;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s ease;
}

.register-link:hover {
  color: #764ba2;
  text-decoration: underline;
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
  font-size: 1.1rem;
  transition: all 0.3s ease;
}

.password-toggle:hover {
  color: #764ba2;
  transform: translateY(-50%) scale(1.1);
 }

@media (max-width: 480px) {
.login-container {
  padding: 35px 25px;
  margin: 20px;
}

.hospital-header h1 {
  font-size: 1.8rem;
}

.hospital-icon {
  width: 60px;
  height: 60px;
}

.hospital-icon i {
  font-size: 2rem;
 }
}
</style>
</head>
<body>

<div class="login-container">
    <div class="hospital-header">
        <div class="hospital-icon">
            <i class="bi bi-heart-pulse"></i>
        </div>
        <h1>MediCare Hospital</h1>
        <p>Secure Access Portal</p>
    </div>

    <div class="error-message" id="errorMessage">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="errorText"></span>
    </div>

    <form method="POST" action="" id="loginForm">
        <div class="input-group">
            <i class="bi bi-envelope-fill"></i>
            <input type="email" name="email" id="email" placeholder="Enter your email" required autofocus>
        </div>

        <div class="input-group">
            <i class="bi bi-lock-fill"></i>
            <input type="password" name="password" id="password" placeholder="Enter your password" required>
            <button type="button" class="password-toggle" id="passwordToggle">
                <i class="bi bi-eye"></i>
            </button>
        </div>

        <button type="submit" class="login-btn" id="loginButton">
            <span id="buttonText">Sign In</span>
            <div class="spinner" id="spinner"></div>
        </button>
    </form>

    <div class="login-footer">
        Don't have an account? <a href="register.php" class="register-link">Register here</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('passwordToggle');
    const loginButton = document.getElementById('loginButton');
    const buttonText = document.getElementById('buttonText');
    const spinner = document.getElementById('spinner');
    const errorMessage = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');

    // Password toggle functionality
    passwordToggle.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
    });

    // Form validation
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Reset previous errors
        hideError();
        emailInput.classList.remove('error');
        passwordInput.classList.remove('error');

        // Basic validation
        let isValid = true;
        let errorMsg = '';

        if (!emailInput.value.trim()) {
            emailInput.classList.add('error');
            errorMsg = 'Please enter your email address.';
            isValid = false;
        } else if (!isValidEmail(emailInput.value)) {
            emailInput.classList.add('error');
            errorMsg = 'Please enter a valid email address.';
            isValid = false;
        } else if (!passwordInput.value) {
            passwordInput.classList.add('error');
            errorMsg = 'Please enter your password.';
            isValid = false;
        }

        if (!isValid) {
            showError(errorMsg);
            return;
        }

        // Show loading state
        setLoadingState(true);

        // Simulate form submission (remove this timeout in production)
        setTimeout(() => {
            // In a real application, you would submit the form here
            // For demo purposes, we'll just submit the form
            loginForm.submit();
        }, 1500);
    });

    // Real-time validation
    emailInput.addEventListener('input', function() {
        if (this.value.trim() && !isValidEmail(this.value)) {
            this.classList.add('error');
        } else {
            this.classList.remove('error');
        }
    });

    passwordInput.addEventListener('input', function() {
        if (this.value.length > 0 && this.value.length < 6) {
            this.classList.add('error');
        } else {
            this.classList.remove('error');
        }
    });

    // Enter key support
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            loginForm.dispatchEvent(new Event('submit'));
        }
    });

    // Auto-focus email field
    emailInput.focus();

    // Helper functions
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function setLoadingState(loading) {
        if (loading) {
            buttonText.style.display = 'none';
            spinner.style.display = 'block';
            loginButton.disabled = true;
            loginButton.style.opacity = '0.8';
        } else {
            buttonText.style.display = 'block';
            spinner.style.display = 'none';
            loginButton.disabled = false;
            loginButton.style.opacity = '1';
        }
    }

    function showError(message) {
        errorText.textContent = message;
        errorMessage.classList.add('show');

        // Auto-hide error after 5 seconds
        setTimeout(hideError, 5000);
    }

    function hideError() {
        errorMessage.classList.remove('show');
    }

    // Add some interactive effects
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
        });

        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    });

    // Add pulse animation to login button when page loads
    setTimeout(() => {
        loginButton.style.animation = 'pulse 2s infinite';
    }, 1000);
});
</script>
</body>
</html>
