<?php
session_start();
include 'config.php';

// Only allow admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: login.php");
    exit;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $license_number = $_POST['license_number'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $consultation_fee = $_POST['consultation_fee'] ?? 0;

    if (!empty($user_id) && !empty($license_number)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO doctors (user_id, license_number, bio, consultation_fee)
                VALUES (:user_id, :license_number, :bio, :consultation_fee)
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':license_number' => $license_number,
                ':bio' => $bio,
                ':consultation_fee' => $consultation_fee
            ]);

            header("Location: doctors.php?success=1");
            exit;

        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
    } else {
        header("Location: doctors.php?error=Missing required fields");
        exit;
    }
} else {
    header("Location: doctors.php");
    exit;
}
?>
