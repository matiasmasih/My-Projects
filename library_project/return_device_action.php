<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/connection.php';

if (!isset($_POST['borrow_id'])) {
    die('Borrow ID missing.');
}

$borrow_id = (int)$_POST['borrow_id'];
$user_id = $_SESSION['user_id'];

try {
    // 1. Check that this borrow record belongs to the logged-in user and is currently borrowed
    $stmt = $pdo->prepare("SELECT device_id FROM device_borrowings WHERE id = :borrow_id AND user_id = :user_id AND status = 'borrowed'");
    $stmt->execute(['borrow_id' => $borrow_id, 'user_id' => $user_id]);
    $borrow = $stmt->fetch();

    if (!$borrow) {
        die('Invalid borrow record or already returned.');
    }

    $device_id = $borrow['device_id'];

    // 2. Update the device_borrowings record to mark it as returned
    $stmt = $pdo->prepare("UPDATE device_borrowings SET status = 'returned', return_date = NOW() WHERE id = :borrow_id");
    $stmt->execute(['borrow_id' => $borrow_id]);

    // 3. Update the devices table to mark the device as not borrowed
    $stmt = $pdo->prepare("UPDATE devices SET is_borrowed = 0 WHERE id = :device_id");
    $stmt->execute(['device_id' => $device_id]);

    // 4. Redirect back with success message
    header('Location: return_device.php?message=Device returned successfully');
    exit;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
