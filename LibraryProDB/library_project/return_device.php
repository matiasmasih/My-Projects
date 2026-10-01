<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/includes/connection.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Fetch devices borrowed by the user and not yet returned
try {
    $stmt = $pdo->prepare("
        SELECT db.id AS borrow_id, d.name, d.serial_number, db.borrow_date
        FROM device_borrowings db
        JOIN devices d ON db.device_id = d.id
        WHERE db.user_id = ? AND db.status = 'borrowed'
        ORDER BY db.borrow_date DESC
    ");
    $stmt->execute([$user_id]);
    $devices = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Return Device - LibraryPro</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .navbar.bg-primary {
      background-color: #007bff !important;
      box-shadow: 0 4px 10px rgba(0, 123, 255, 0.6);
      height: 50px;
      padding-top: 0;
      padding-bottom: 0;
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
      padding: 0;
    }

    .navbar-nav .nav-link {
      color: #fff !important;
      font-weight: 600;
      padding-top: 0;
      padding-bottom: 0;
      line-height: 50px;
      font-size: 1rem;
    }

    .navbar-nav .nav-link.active,
    .navbar-nav .nav-link:hover {
      color: #ffd6e8 !important;
    }

    body {
      padding-top: 60px;
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
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
        <li class="nav-item"><a class="nav-link active" href="return_device.php">🔁 Return Device</a></li>
        <li class="nav-item"><a class="nav-link" href="return.php">🔁 Return Book</a></li>
        <li class="nav-item"><a class="nav-link" href="wishlist.php">📝 Wishlist</a></li>
        <li class="nav-item"><a class="nav-link" href="profile.php">👤 Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">🚪 Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- MAIN CONTENT -->
<div class="container mt-4">
  <h2 class="mb-4">Return Borrowed Devices</h2>

  <?php if (count($devices) > 0): ?>
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Device Name</th>
          <th>Serial Number</th>
          <th>Borrow Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($devices as $device): ?>
        <tr>
          <td><?= htmlspecialchars($device['name']) ?></td>
          <td><?= htmlspecialchars($device['serial_number']) ?></td>
          <td><?= htmlspecialchars($device['borrow_date']) ?></td>
          <td>
            <form method="post" action="return_device_action.php" onsubmit="return confirm('Are you sure you want to return this device?');">
              <input type="hidden" name="borrow_id" value="<?= $device['borrow_id'] ?>">
              <button type="submit" class="btn btn-sm btn-success">Return</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="alert alert-info">You have no borrowed devices to return.</div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
