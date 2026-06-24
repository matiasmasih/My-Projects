<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

// Only logged-in admins can perform this action
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Only accept POST requests with admin ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_id'])) {
    $admin_id = (int) $_POST['admin_id'];

    // Prevent self-deletion
    if ($_SESSION['user_id'] == $admin_id) {
        header("Location: admin_users.php?error=cannot_delete_self");
        exit;
    }

    // Check if the target is an admin
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();

    if ($admin && $admin['role'] === 'admin') {
        // Delete admin
        $delete = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $delete->execute([$admin_id]);

        header("Location: admin_users.php?deleted=1");
        exit;
    } else {
        header("Location: admin_users.php?error=not_admin");
        exit;
    }
} else {
    header("Location: admin_users.php");
    exit;
}
