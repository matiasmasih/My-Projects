<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Redirect if user not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Search & pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 9;
$offset = ($page - 1) * $itemsPerPage;

$params = [];
$sqlWhere = "";

if ($search !== '') {
    $sqlWhere = " AND (name LIKE ? OR description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Count total
$sqlCount = "SELECT COUNT(*) FROM devices WHERE 1" . $sqlWhere;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalItems = $stmtCount->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Fetch devices
$sql = "SELECT * FROM devices WHERE 1" . $sqlWhere . " ORDER BY name ASC LIMIT $itemsPerPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Borrow Devices - LibraryPro</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    /* Your existing styles unchanged */
    .navbar.bg-primary {
      background-color: #007bff !important;
      box-shadow: 0 4px 10px rgba(0, 123, 255, 0.6);
      height: 50px;
      line-height: 50px;
      padding-top: 0;
      padding-bottom: 0;
    }

    .navbar .container {
      height: 50px;
      display: flex;
      align-items: center;
    }

    .navbar-brand {
      font-weight: 900;
      font-size: 1.5rem;
      color: #fff !important;
    }

    .navbar-nav {
      display: flex;
      align-items: center;
    }
    .navbar-nav .nav-link {
      color: #fff !important;
      font-weight: 600;
      padding-top: 0;
      padding-bottom: 0;
      line-height: 50px;
    }

    .navbar-nav .nav-link.active,
    .navbar-nav .nav-link:hover {
      color: #ffd6e8 !important;
    }

    .navbar-toggler {
      border-color: #fff !important;
      padding: 0.25rem 0.75rem;
      height: 40px;
      margin-top: 5px;
    }

    .navbar-toggler-icon {
      filter: brightness(0) invert(1);
      width: 30px;
      height: 30px;
    }

    .card:hover {
      box-shadow: 0 8px 20px rgba(0,123,255,0.3);
      transform: translateY(-5px);
      transition: 0.3s ease;
    }

    body {
      padding-top: 60px;
      background-color: #f9f9fb;
    }

    .badge {
      font-size: 0.9rem;
    }
  </style>
</head>
<body>
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
        <li class="nav-item"><a class="nav-link active" href="borrow_device.php">💻 Borrow Device</a></li>
        <li class="nav-item"><a class="nav-link" href="return_device.php">🔁 Return Device</a></li>
        <li class="nav-item"><a class="nav-link" href="return.php">🔁 Return Books</a></li>
        <li class="nav-item"><a class="nav-link" href="wishlist.php">📝 Wishlist</a></li>
        <li class="nav-item"><a class="nav-link" href="profile.php">👤 Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">🚪 Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-5 mt-5">
  <h2 class="mb-4">Hello, <?= htmlspecialchars($username) ?> – Borrow Devices</h2>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php elseif (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form class="input-group mb-4" method="get">
    <input type="search" name="search" class="form-control" placeholder="Search devices by name or description" value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-primary" type="submit">Search</button>
  </form>

  <?php if (empty($devices)): ?>
    <div class="alert alert-warning text-dark">No devices found.</div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-md-3 g-4">
      <?php foreach ($devices as $device): ?>
        <div class="col">
          <div class="card h-100 shadow-sm">
            <?php if (!empty($device['image'])): ?>
              <img src="uploads/devices/<?= htmlspecialchars($device['image']) ?>"
                   class="card-img-top" style="height:200px; object-fit:cover;" alt="<?= htmlspecialchars($device['name']) ?>">
            <?php else: ?>
              <svg class="bd-placeholder-img card-img-top" width="100%" height="200" xmlns="http://www.w3.org/2000/svg" role="img">
                <title>No image</title>
                <rect width="100%" height="100%" fill="#868e96"></rect>
                <text x="50%" y="50%" fill="#dee2e6" dy=".3em" text-anchor="middle">No Image</text>
              </svg>
            <?php endif; ?>

            <div class="card-body d-flex flex-column">
              <h5 class="card-title"><?= htmlspecialchars($device['name']) ?></h5>
              <p class="card-text flex-grow-1"><?= htmlspecialchars($device['description']) ?></p>
              <p>
                <span class="badge <?= $device['is_borrowed'] ? 'bg-danger' : 'bg-success' ?>">
                  <?= $device['is_borrowed'] ? 'Borrowed' : 'Available' ?>
                </span>
              </p>
              <?php if (!$device['is_borrowed']): ?>
                <a href="borrow_device_action.php?type=device&id=<?= $device['id'] ?>" class="btn btn-sm btn-primary mt-auto">Borrow</a>
              <?php else: ?>
                <a href="return_action.php?type=device&id=<?= $device['id'] ?>" class="btn btn-sm btn-warning mt-auto">Return</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
              <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
