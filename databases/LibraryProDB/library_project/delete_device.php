<?php
require_once __DIR__ . '/includes/connection.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid or missing device ID.");
}

$id = (int)$_GET['id'];

// Check if device exists
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch();

if (!$device) {
    die("Device not found.");
}

// Optionally delete the device's image if stored
if (!empty($device['image'])) {
    $imagePath = __DIR__ . '/uploads/devices/' . $device['image'];
    if (file_exists($imagePath)) {
        unlink($imagePath);
    }
}

// Delete the device from DB
$deleteStmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
if ($deleteStmt->execute([$id])) {
    header("Location: devices_admin.php?deleted=1");
    exit;
} else {
    die("Failed to delete the device.");
}
