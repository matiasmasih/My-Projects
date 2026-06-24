<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

// Error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if book ID is sent via POST
if (!isset($_POST['borrow_book_id']) || !is_numeric($_POST['borrow_book_id'])) {
    $_SESSION['error'] = "Invalid book ID.";
    header("Location: borrow.php");
    exit;
}

$book_id = (int)$_POST['borrow_book_id'];

// Check if book exists and is not borrowed
$stmt = $pdo->prepare("SELECT is_borrowed FROM books WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    $_SESSION['error'] = "Book not found.";
    header("Location: borrow.php");
    exit;
}

if ($book['is_borrowed']) {
    $_SESSION['error'] = "Book is already borrowed.";
    header("Location: borrow.php");
    exit;
}

// Mark book as borrowed in borrowed_books table and update books table
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("INSERT INTO borrowed_books (book_id, user_id, borrow_date, status) VALUES (?, ?, NOW(), 'borrowed')");
    $stmt->execute([$book_id, $user_id]);

    $stmt = $pdo->prepare("UPDATE books SET is_borrowed = 1 WHERE id = ?");
    $stmt->execute([$book_id]);

    $pdo->commit();

    $_SESSION['success'] = "Book borrowed successfully.";
    header("Location: borrow.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Failed to borrow book: " . $e->getMessage();
    header("Location: borrow.php");
    exit;
}
?>
