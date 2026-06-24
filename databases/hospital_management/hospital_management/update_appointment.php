<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Validate POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $patient_id = $_POST['patient_id'] ?? '';
    $doctor_id = $_POST['doctor_id'] ?? '';
    $scheduled_at = $_POST['scheduled_at'] ?? '';
    $duration = $_POST['duration_minutes'] ?? 30;
    $status = $_POST['status'] ?? 'scheduled';
    $reason = $_POST['reason'] ?? '';
    $updated_by = $_SESSION['user_id'];

    if ($id <= 0 || !$patient_id || !$doctor_id || !$scheduled_at || !$reason) {
        die("All required fields must be filled.");
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE appointments SET
                patient_id = :patient_id,
                doctor_id = :doctor_id,
                scheduled_at = :scheduled_at,
                duration_minutes = :duration,
                status = :status,
                reason = :reason,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':patient_id' => $patient_id,
            ':doctor_id' => $doctor_id,
            ':scheduled_at' => $scheduled_at,
            ':duration' => $duration,
            ':status' => $status,
            ':reason' => $reason,
            ':id' => $id
        ]);

        // Redirect with success
        $_SESSION['success'] = "Appointment updated successfully!";
        header("Location: appointments.php");
        exit;
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
} else {
    die("Invalid request method.");
}
?>
