<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($firstname) || empty($lastname) || empty($email)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error = "Email address already in use by another account.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ? WHERE id = ?");
            $stmt->execute([$firstname, $lastname, $email, $user_id]);
            $_SESSION['success'] = "Profile updated successfully.";
            header("Location: profile.php");
            exit;
        }
    }
}

$stmt = $pdo->prepare("SELECT firstname, lastname, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: profile.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Edit Profile</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

<!-- Google Fonts: Poppins -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />

<style>
  body {
    font-family: 'Poppins', sans-serif;
    background: white;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
  }
  .card {
    max-width: 450px;
    width: 100%;
    padding: 30px 40px;
    border-radius: 15px;
    box-shadow: 0 8px 24px rgba(100, 100, 111, 0.2);
    background-color: #ffffffcc;
    backdrop-filter: saturate(180%) blur(10px);
  }
  h1 {
    font-weight: 600;
    color: #4b3ca7;
    margin-bottom: 30px;
    text-align: center;
  }
  label {
    font-weight: 600;
    color: #555;
  }
  .form-control {
    border-radius: 10px;
    padding: 12px 15px;
    font-size: 1rem;
    border: 1.8px solid #ddd;
    transition: border-color 0.3s ease;
  }
  .form-control:focus {
    border-color: #6f42c1;
    box-shadow: 0 0 8px rgba(111, 66, 193, 0.3);
    outline: none;
  }
  .btn-primary {
    background: #6f42c1;
    border: none;
    padding: 12px 25px;
    font-weight: 600;
    border-radius: 10px;
    transition: background 0.3s ease;
    width: 100%;
  }
  .btn-primary:hover {
    background: #5936a2;
  }
  .btn-secondary {
    border-radius: 10px;
    padding: 12px 25px;
    font-weight: 600;
    width: 100%;
    margin-top: 10px;
  }
  .alert {
    border-radius: 10px;
    font-weight: 600;
  }
</style>
</head>
<body>

<div class="card shadow-sm">
  <h1>Edit Profile</h1>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <div class="mb-4">
      <label for="firstname" class="form-label">First Name</label>
      <input
        type="text"
        id="firstname"
        name="firstname"
        class="form-control"
        required
        value="<?php echo htmlspecialchars($user['firstname']); ?>"
      />
    </div>

    <div class="mb-4">
      <label for="lastname" class="form-label">Last Name</label>
      <input
        type="text"
        id="lastname"
        name="lastname"
        class="form-control"
        required
        value="<?php echo htmlspecialchars($user['lastname']); ?>"
      />
    </div>

    <div class="mb-4">
      <label for="email" class="form-label">Email Address</label>
      <input
        type="email"
        id="email"
        name="email"
        class="form-control"
        required
        value="<?php echo htmlspecialchars($user['email']); ?>"
      />
    </div>

    <button type="submit" class="btn btn-primary">Save Changes</button>
    <a href="profile.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
