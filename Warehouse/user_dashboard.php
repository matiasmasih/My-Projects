<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
    }
    .sidebar {
      height: 100vh;
      background-color: #1d3557;
      color: white;
      padding-top: 30px;
    }
    .sidebar li {
      padding: 12px 20px;
      list-style: none;
      cursor: pointer;
    }
    .sidebar li:hover,
    .sidebar li.active {
      background-color: #16324f;
    }
    .sidebar i {
      margin-right: 10px;
    }
    .content {
      padding: 30px;
    }
    .card-title {
      color: #1d3557;
    }
  </style>
</head>
<body>

<div class="container-fluid">
  <div class="row">
    <!-- Sidebar -->
    <div class="col-md-3 col-lg-2 sidebar">
      <h4 class="text-center mb-4"><i class="bi bi-box"></i> Warehouse</h4>
      <ul class="p-0">
        <li class="active" onclick="location.href='user_dashboard.php'"><i class="bi bi-speedometer2"></i> Dashboard</li>
        <li onclick="location.href='user_inventory.php'"><i class="bi bi-box-seam"></i> View Inventory</li>
        <li onclick="location.href='user_locations.php'"><i class="bi bi-geo-alt"></i> Locations</li>
        <li onclick="location.href='logout.php'"><i class="bi bi-box-arrow-right"></i> Logout</li>
      </ul>
    </div>

    <!-- Main content -->
    <div class="col-md-9 col-lg-10 content">
      <h2>Welcome, <?= htmlspecialchars($username) ?> 👋</h2>
      <p class="text-muted">Here’s a quick overview of the warehouse system.</p>

      <div class="row g-4 mt-4">
        <div class="col-md-4">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title"><i class="bi bi-box-seam text-primary"></i> Products</h5>
              <p class="card-text">View all available products and stock levels.</p>
              <a href="user_inventory.php" class="btn btn-outline-primary btn-sm">Browse</a>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title"><i class="bi bi-geo-alt text-warning"></i> Locations</h5>
              <p class="card-text">Check where items are stored across warehouse locations.</p>
              <a href="user_locations.php" class="btn btn-outline-warning btn-sm">View</a>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title"><i class="bi bi-info-circle text-info"></i> Instructions</h5>
              <p class="card-text">Need help navigating? Find user instructions here soon.</p>
              <a href="#" class="btn btn-outline-info btn-sm disabled">Coming Soon</a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
