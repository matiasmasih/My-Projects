<?php
require_once 'includes/connection.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Device ID is missing.");
}

$device_id = (int)$_GET['id'];

// Fetch device details
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$device_id]);
$device = $stmt->fetch();

if (!$device) {
    die("Device not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $serial_number = $_POST['serial_number'] ?? '';
    $category = $_POST['category'] ?? '';
    $year = $_POST['year'] ?? '';
    $image = $device['image']; // Default to existing image

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $uploadDir = 'uploads/devices/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $imageName = time() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $imageName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $image = $imageName;

            // Optionally delete the old image
            if (!empty($device['image']) && file_exists($uploadDir . $device['image'])) {
                unlink($uploadDir . $device['image']);
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE devices SET name = ?, description = ?, serial_number = ?, category = ?, year = ?, image = ? WHERE id = ?");
    $stmt->execute([$name, $description, $serial_number, $category, $year, $image, $device_id]);

    header("Location: devices_admin.php?success=Device updated successfully.");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Device</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #f1f5f9;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .card {
      background: white;
      border-radius: 15px;
      border: none;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .card h3 {
      color: #0d6efd;
      font-weight: bold;
      margin-bottom: 25px;
    }

    .form-label {
      font-weight: 500;
      color: #374151;
    }

    .form-control {
      border-radius: 8px;
      border: 1px solid #d1d5db;
      transition: border-color 0.2s ease-in-out;
    }

    .form-control:focus {
      border-color: #0d6efd;
      box-shadow: none;
    }

    .btn-success {
      background-color: #0d6efd;
      border: none;
      padding: 10px 18px;
      font-weight: 500;
      border-radius: 8px;
    }

    .btn-success:hover {
      background-color: #0b5ed7;
    }

    .btn-secondary {
      margin-left: 10px;
      border-radius: 8px;
    }

    .image-preview {
      max-width: 150px;
      height: auto;
      margin-top: 10px;
      border-radius: 8px;
      border: 1px solid #ccc;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="card p-4 shadow">
      <h3>Edit Device</h3>
      <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
          <label class="form-label">Device Name</label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($device['name']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" required><?= htmlspecialchars($device['description']) ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Serial Number</label>
          <input type="text" name="serial_number" class="form-control" value="<?= htmlspecialchars($device['serial_number']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Category</label>
          <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($device['category']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Year</label>
          <input type="number" name="year" class="form-control" value="<?= htmlspecialchars($device['year']) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Device Image</label>
          <input type="file" name="image" class="form-control">
          <?php if (!empty($device['image'])): ?>
            <img src="uploads/devices/<?= htmlspecialchars($device['image']) ?>" class="image-preview" alt="Current image">
          <?php else: ?>
            <p class="text-muted mt-2">No image uploaded.</p>
          <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-success">Update Device</button>
        <a href="devices_admin.php" class="btn btn-secondary">Cancel</a>
      </form>
    </div>
  </div>
</body>
</html>
