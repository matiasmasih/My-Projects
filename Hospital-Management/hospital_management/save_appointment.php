<?php
session_start();
include 'config.php';

// Only admin/manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Collect POST data
$patient_id = $_POST['patient_id'] ?? null;
$doctor_id = $_POST['doctor_id'] ?? null;
$scheduled_at = $_POST['scheduled_at'] ?? null;
$duration = $_POST['duration_minutes'] ?? 30;
$status = $_POST['status'] ?? 'scheduled';
$reason = $_POST['reason'] ?? null;
$created_by = $_SESSION['user_id']; // must be a valid user ID

// Basic validation
if (!$patient_id || !$doctor_id || !$scheduled_at) {
    die("Missing required fields.");
}

// Insert appointment
$stmt = $pdo->prepare("
    INSERT INTO appointments
    (patient_id, doctor_id, scheduled_at, duration_minutes, status, reason, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$patient_id, $doctor_id, $scheduled_at, $duration, $status, $reason, $created_by]);

// Redirect back to appointments page
header("Location: appointments.php?success=1");
exit;
?>
