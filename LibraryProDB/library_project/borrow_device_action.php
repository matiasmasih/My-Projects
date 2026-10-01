<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

// Error reporting (for debugging – remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($type !== 'device' || !$id) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: borrow_device.php");
    exit;
}

// Fetch device info
$stmt = $pdo->prepare("SELECT is_borrowed FROM devices WHERE id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch();

if (!$device) {
    $_SESSION['error'] = "Device not found.";
    header("Location: borrow_device.php");
    exit;
}

if ($device['is_borrowed']) {
    $_SESSION['error'] = "Device is already borrowed.";
    header("Location: borrow_device.php");
    exit;
}

// Mark as borrowed
$stmt = $pdo->prepare("INSERT INTO device_borrowings (device_id, user_id, borrow_date, status) VALUES (?, ?, NOW(), 'borrowed')");
$stmt->execute([$id, $user_id]);

$stmt = $pdo->prepare("UPDATE devices SET is_borrowed = 1 WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['success'] = "Device borrowed successfully.";
header("Location: borrow_device.php");
exit;
?>

