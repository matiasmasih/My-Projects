<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($appointment_id <= 0) {
    die("Invalid appointment ID.");
}

// Delete appointment
try {
    $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->execute([$appointment_id]);
    header("Location: appointments.php?msg=deleted");
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
