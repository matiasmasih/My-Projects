<?php
session_start();
require 'config.php';

// Debugging (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check login
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

$loggedInUserId = (int) $_SESSION['user_id'];
$loggedInUserRole = $_SESSION['role'];

// Validate deletion target
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_users.php?error=invalid_id');
    exit;
}

$userIdToDelete = (int) $_GET['id'];

// Prevent self-deletion
if ($loggedInUserId === $userIdToDelete) {
    header('Location: admin_users.php?error=cannot_delete_self');
    exit;
}

// Fetch user to delete
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
$stmt->execute([$userIdToDelete]);
$userToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userToDelete) {
    header('Location: admin_users.php?error=user_not_found');
    exit;
}

// Role hierarchy
$rolesHierarchy = [
    'staff' => 1,
    'admin' => 2,
    'manager' => 3,
];

$loggedInLevel = $rolesHierarchy[$loggedInUserRole] ?? 0;
$targetLevel = $rolesHierarchy[$userToDelete['role']] ?? 0;

// Permission check
$allowedToDelete = false;

if ($loggedInUserRole === 'manager') {
    // Managers can delete anyone (except self — already checked)
    $allowedToDelete = true;
} elseif ($loggedInUserRole === 'admin') {
    // Admins cannot delete managers
    if ($targetLevel <= 2) {
        $allowedToDelete = true;
    }
}

// Staff cannot delete anyone
if (!$allowedToDelete) {
    header('Location: admin_users.php?error=not_allowed_to_delete');
    exit;
}

// Proceed to delete
$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
if ($stmt->execute([$userIdToDelete])) {
    header('Location: admin_users.php?deleted=success');
} else {
    header('Location: admin_users.php?error=delete_failed');
}
exit;
