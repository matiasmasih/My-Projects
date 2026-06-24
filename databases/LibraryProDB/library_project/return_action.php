<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/connection.php';

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id || !in_array($type, ['book', 'device'])) {
    $_SESSION['error'] = "Invalid request.";
    $redirectPage = ($type === 'device') ? 'borrow_device.php' : 'borrow.php';
    header("Location: $redirectPage");
    exit;
}

if ($type === 'book') {
    $stmt = $pdo->prepare("SELECT * FROM borrowed_books WHERE book_id = ? AND user_id = ? AND status = 'borrowed' LIMIT 1");
    $stmt->execute([$id, $user_id]);
    $borrowing = $stmt->fetch();

    if (!$borrowing) {
        $_SESSION['error'] = "This book is not currently borrowed.";
        header("Location: borrow.php");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE borrowed_books SET status = 'returned', return_date = NOW() WHERE id = ?");
    $stmt->execute([$borrowing['id']]);

    $stmt = $pdo->prepare("UPDATE books SET is_borrowed = 0 WHERE id = ?");
    $stmt->execute([$id]);

    $_SESSION['success'] = "Book returned successfully.";
    header("Location: borrow.php");
    exit;
}

if ($type === 'device') {
    $stmt = $pdo->prepare("SELECT * FROM device_borrowings WHERE device_id = ? AND user_id = ? AND status = 'borrowed' LIMIT 1");
    $stmt->execute([$id, $user_id]);
    $borrowing = $stmt->fetch();

    if (!$borrowing) {
        $_SESSION['error'] = "This device is not currently borrowed.";
        header("Location: borrow_device.php");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE device_borrowings SET status = 'returned', return_date = NOW() WHERE id = ?");
    $stmt->execute([$borrowing['id']]);

    $stmt = $pdo->prepare("UPDATE devices SET is_borrowed = 0 WHERE id = ?");
    $stmt->execute([$id]);

    $_SESSION['success'] = "Device returned successfully.";
    header("Location: borrow_device.php");
    exit;
}
?>
