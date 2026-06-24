<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$addError = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_device') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $serial_number = trim($_POST['serial_number']);
    $category = trim($_POST['category']);
    $year = intval($_POST['year']) ?: null;
    $is_borrowed = 0;
    $imageFilename = null;

    if (!$name) {
        $addError = "Device name is required.";
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxFileSize = 2 * 1024 * 1024;

            $fileTmpPath = $_FILES['image']['tmp_name'];
            $fileName = basename($_FILES['image']['name']);
            $fileSize = $_FILES['image']['size'];
            $fileType = mime_content_type($fileTmpPath);

            if (!in_array($fileType, $allowedMimeTypes)) {
                $addError = "Invalid image format. Allowed: JPG, PNG, GIF, WEBP.";
            } elseif ($fileSize > $maxFileSize) {
                $addError = "Image size must be less than 2MB.";
            } else {
                $uploadDir = __DIR__ . '/uploads/devices/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                $imageFilename = uniqid('device_', true) . '.' . $ext;
                $destPath = $uploadDir . $imageFilename;

                if (!move_uploaded_file($fileTmpPath, $destPath)) {
                    $addError = "Error uploading the image file.";
                }
            }
        }

        if (!$addError) {
            try {
                $stmt = $pdo->prepare("INSERT INTO devices (name, description, serial_number, category, year, is_borrowed, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $serial_number, $category, $year, $is_borrowed, $imageFilename]);
                header("Location: devices_admin.php?added=1");
                exit;
            } catch (PDOException $e) {
                $addError = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch devices
try {
    $stmt = $pdo->query("SELECT * FROM devices ORDER BY id DESC");
    $devices = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>LibraryPro Admin - Devices</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #f0f2f5;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .navbar {
      background-color: #0d6efd;
    }
    .navbar-brand {
      font-weight: bold;
      font-size: 1.4rem;
    }
    .card {
      border: none;
      border-radius: 1rem;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.08);
    }
    .table thead {
      background-color: #0d6efd;
      color: white;
    }
    .btn-outline-danger {
      padding: 0.25rem 0.75rem;
    }
    .status.borrowed {
      color: #dc3545;
      font-weight: 600;
    }
    .status.available {
      color: #198754;
      font-weight: 600;
    }
    .form-section {
      margin-top: 2rem;
      margin-bottom: 3rem;
    }
    .add-device-card {
      max-width: 700px;
      margin: 0 auto;
      background: #fff;
      border-radius: 1rem;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
      padding: 2rem;
      margin-bottom: 3rem;
    }
    .add-device-card h3 {
      font-weight: 600;
      margin-bottom: 1.5rem;
      color: #0d6efd;
    }
    .table-hover tbody tr:hover {
      background-color: #f3f8ff;
      transition: 0.2s ease-in-out;
    }
    .device-image-thumb {
      max-width: 60px;
      max-height: 40px;
      object-fit: cover;
      border-radius: 0.25rem;
    }
  </style>
</head>
<body>
<nav class="navbar navbar-dark px-4">
  <a class="navbar-brand text-white" href="#">LibraryPro Admin</a>
</nav>

<div class="add-device-card">
  <h3>Add New Device</h3>

  <?php if (isset($_GET['added'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      ✅ Device added successfully!
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($addError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      ⚠️ <?= htmlspecialchars($addError) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add_device">
    <div class="mb-3">
      <label>Device Name *</label>
      <input type="text" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Serial Number</label>
      <input type="text" name="serial_number" class="form-control">
    </div>
    <div class="mb-3">
      <label>Category</label>
      <input type="text" name="category" class="form-control">
    </div>
    <div class="mb-3">
      <label>Year</label>
      <input type="number" name="year" class="form-control" min="1900" max="2100">
    </div>
    <div class="mb-3">
      <label>Description</label>
      <textarea name="description" class="form-control" rows="3"></textarea>
    </div>
    <div class="mb-3">
      <label>Device Image</label>
      <input type="file" name="image" class="form-control" accept="image/*">
    </div>
    <div class="d-flex justify-content-between mt-3">
  <button type="submit" class="btn btn-primary px-4">
    <i class="bi bi-plus-lg me-2"></i> Add Device
  </button>
  <a href="admin.php" class="btn btn-secondary px-4">⬅ Back</a>
</div>
  </form>
</div>

<div class="card p-4 mt-5">
  <h4 class="mb-4">📋 Device List</h4>
  <div class="table-responsive">
    <table class="table table-hover align-middle text-center">
      <thead class="table-light">
        <tr>
          <th>#ID</th>
          <th>Image</th>
          <th>Name</th>
          <th>Description</th>
          <th>Serial Number</th>
          <th>Category</th>
          <th>Year</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($devices): foreach ($devices as $device): ?>
          <tr>
            <td><?= htmlspecialchars($device['id']) ?></td>
            <td>
              <?php if ($device['image']): ?>
                <img src="uploads/devices/<?= htmlspecialchars($device['image']) ?>" alt="Device Image" class="device-image-thumb">
              <?php else: ?>
                <span class="text-muted fst-italic">No image</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($device['name']) ?></td>
            <td><?= nl2br(htmlspecialchars($device['description'])) ?></td>
            <td><?= htmlspecialchars($device['serial_number']) ?></td>
            <td><?= htmlspecialchars($device['category']) ?></td>
            <td><?= htmlspecialchars($device['year']) ?></td>
            <td>
              <?php if ($device['is_borrowed']): ?>
                <span class="status borrowed">Borrowed</span>
              <?php else: ?>
                <span class="status available">Available</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="edit_device.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                <i class="bi bi-pencil-square"></i>
              </a>
              <a href="delete_device.php?id=<?= $device['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this device?')">
                <i class="bi bi-trash"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr>
            <td colspan="9" class="text-muted">No devices found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
