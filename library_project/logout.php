<?php
session_start();

// Clear session data
$_SESSION = [];
session_unset();
session_destroy();

// Optional: delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logged Out</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      background-color: #f8f9fa;
    }
    .logout-box {
      background: white;
      padding: 2rem 3rem;
      border-radius: 12px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="logout-box">
    <h2 class="mb-3">You have been logged out</h2>
    <p class="mb-4">Thank you for using LibraPro.</p>
    <a href="/library_project/login.php" class="btn btn-primary">Login Again</a>
  </div>
</body>
</html>
