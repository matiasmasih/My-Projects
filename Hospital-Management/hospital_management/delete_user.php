<?php
session_start();
include 'config.php';

// Check login
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: login.php");
    exit;
}

$loggedInId = $_SESSION['user_id'];
$loggedInRole = $_SESSION['role_id'];

// Check if 'id' is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: users.php?error=missing_id");
    exit;
}

$userId = (int)$_GET['id'];

// Prevent self-deletion
if ($userId === (int)$loggedInId) {
    header("Location: users.php?error=self_delete");
    exit;
}

// Fetch the user to delete
$stmt = $pdo->prepare("SELECT id, role_id FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userToDelete = $stmt->fetch();

if (!$userToDelete) {
    header("Location: users.php?error=user_not_found");
    exit;
}

// Role-based protection
$targetRole = (int)$userToDelete['role_id'];

// Rules:
// - Manager (2) can delete anyone except themselves
// - Admin (1) can delete anyone except themselves
// (You can add more rules later)
if ($loggedInRole === 2 || $loggedInRole === 1) {
    try {
        $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $deleteStmt->execute([$userId]);
        header("Location: users.php?success=user_deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: users.php?error=delete_failed");
        exit;
    }
} else {
    header("Location: users.php?error=unauthorized");
    exit;
}
?>
