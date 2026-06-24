<?php
session_start();
require 'config.php';

// Check admin login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Check if product ID is valid
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid product ID.";
    header('Location: admin_products.php');
    exit;
}

$productId = (int)$_GET['id'];

// Check if product exists
$stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
$stmt->execute([$productId]);
if (!$stmt->fetch()) {
    $_SESSION['error'] = "Product not found.";
    header('Location: admin_products.php');
    exit;
}

// Delete product
$deleteStmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
if ($deleteStmt->execute([$productId])) {
    $_SESSION['success'] = "Product deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete product.";
}

header('Location: admin_products.php');
exit;
