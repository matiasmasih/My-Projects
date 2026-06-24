<?php
session_start();
include 'config.php';

// Only allow admin or manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Check if id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: patients.php?error=missing_id");
    exit;
}

$patient_id = (int) $_GET['id'];

// Optionally: prevent deleting certain critical patients (if any rule)
// e.g. if ($patient_id == 1) { ... }

// Delete patient
try {
    $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);

    header("Location: patients.php?success=patient_deleted");
    exit;
} catch (PDOException $e) {
    // Log error or show message
    header("Location: patients.php?error=delete_failed");
    exit;
}
?>
