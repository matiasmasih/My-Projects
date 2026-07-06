<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: login.php");
    exit;
}

// Get doctor ID from URL
$doctor_id = $_GET['id'] ?? null;

if (!$doctor_id) {
    header("Location: doctors.php?error=Invalid ID");
    exit;
}

// Check if doctor exists
$stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = :id");
$stmt->execute([':id' => $doctor_id]);
$doctor = $stmt->fetch();

if (!$doctor) {
    header("Location: doctors.php?error=Doctor not found");
    exit;
}

// Delete doctor
try {
    $deleteStmt = $pdo->prepare("DELETE FROM doctors WHERE id = :id");
    $deleteStmt->execute([':id' => $doctor_id]);
    
    header("Location: doctors.php?success=deleted");
    exit;
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
