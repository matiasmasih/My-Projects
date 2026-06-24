<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /library_project/login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard - LibraryPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            height: 100vh;
            background-color: #343a40;
            padding-top: 1rem;
            color: white;
            position: fixed;
            width: 220px;
        }
        .sidebar a {
            color: #ffffff;
            text-decoration: none;
            display: block;
            padding: 10px 20px;
            margin-bottom: 5px;
            border-radius: 4px;
            transition: background-color 0.2s ease-in-out;
        }
        .sidebar a:hover {
            background-color: #495057;
        }
        .main-content {
            margin-left: 220px;
            padding: 2rem;
        }
        .card {
            transition: transform 0.2s ease-in-out;
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card:hover {
            transform: scale(1.02);
        }
        .header-bar {
            background-color: #495057;
            color: #ffffff;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            border-radius: 10px;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h4 class="text-center">LibraryPro</h4>
    <hr>
    <a href="/library_project/dashboard.php">🏠 Dashboard</a>
    <a href="/library_project/borrow.php">📚 Borrow Book</a>
    <a href="/library_project/borrow_device.php">💻 Borrow Device</a>
    <a href="/library_project/return_device.php">🔁 Return Device</a>
    <a href="/library_project/return.php">🔁 Return Books</a>
    <a href="/library_project/wishlist.php">📝 Wishlist</a>
    <a href="/library_project/profile.php">👤 Profile</a>
    <a href="/library_project/logout.php">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <div class="header-bar">
        <h4>User Dashboard</h4>
        <span>Welcome, <strong><?= htmlspecialchars($username) ?></strong></span>
    </div>
<br>

    <div class="row">
<!-- Borrow Book -->
<div class="col-md-3 mb-3">
    <div class="card text-white bg-primary">
        <div class="card-body">
            <h5 class="card-title">Borrow a Book</h5>
            <p class="card-text">Browse and borrow available books.</p>
            <a href="/library_project/borrow.php" class="btn btn-light">Go</a>
        </div>
    </div>
</div>

<!-- Borrow Device -->
<div class="col-md-3 mb-3">
    <div class="card text-white bg-info">
        <div class="card-body">
            <h5 class="card-title">Borrow a Device</h5>
            <p class="card-text">Check out available devices for borrowing.</p>
            <a href="/library_project/borrow_device.php" class="btn btn-light">Go</a>
        </div>
    </div>
</div>

<!-- Return Device -->
<div class="col-md-3 mb-3">
    <div class="card text-white bg-success">
        <div class="card-body">
            <h5 class="card-title">Return a Device</h5>
            <p class="card-text">Return borrowed devices easily.</p>
            <a href="/library_project/return_device.php" class="btn btn-light">Go</a>
        </div>
    </div>
</div>

<!-- Return Books -->
<div class="col-md-3 mb-3">
    <div class="card text-white bg-secondary">
        <div class="card-body">
            <h5 class="card-title">Return a Book</h5>
            <p class="card-text">Manage and return borrowed books.</p>
            <a href="/library_project/return.php" class="btn btn-light">Go</a>
        </div>
    </div>
</div>

<!-- Wishlist -->
<div class="col-md-3 mb-3">
    <div class="card text-white bg-warning">
        <div class="card-body">
            <h5 class="card-title">My Wishlist</h5>
            <p class="card-text">View or update your saved items.</p>
            <a href="/library_project/wishlist.php" class="btn btn-light">Go</a>
        </div>
    </div>
</div>
    </div>
</div>

</body>
</html>
