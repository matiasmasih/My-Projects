<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2) can delete
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get invoice ID from URL
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoice_id <= 0) {
    die("Invalid invoice ID.");
}

try {
    // Optional: check if the invoice exists first
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        die("Invoice not found.");
    }

    // Delete invoice
    $delete = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
    $delete->execute([$invoice_id]);

    // Redirect back to invoices page
    header("Location: invoices.php?msg=Invoice+deleted+successfully");
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
