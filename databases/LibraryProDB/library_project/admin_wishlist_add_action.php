<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

// Check admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Validate input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $book_id = filter_input(INPUT_POST, 'book_id', FILTER_VALIDATE_INT);

    if (!$user_id || !$book_id) {
        echo "Invalid input. Please enter valid User ID and Book ID.";
        exit;
    }

    try {
        // Insert into wishlist table
        $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, book_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $book_id]);

        // Redirect back to admin wishlist page or success message
        header('Location: admin_wishlist.php?msg=added');
        exit;
    } catch (PDOException $e) {
        echo "Database error: " . htmlspecialchars($e->getMessage());
        exit;
    }
} else {
    echo "Invalid request method.";
}
