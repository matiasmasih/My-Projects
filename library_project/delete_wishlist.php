<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

// Ensure only admin can delete
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wishlist_id'])) {
    $wishlist_id = $_POST['wishlist_id'];

    // Prepare and execute delete query safely
    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id = ?");
    $stmt->execute([$wishlist_id]);

    // Redirect back with success message
    header("Location: admin_wishlist.php?deleted=1");
    exit;
} else {
    // Redirect if accessed improperly
    header("Location: admin_wishlist.php");
    exit;
}
