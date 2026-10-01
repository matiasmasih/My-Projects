<?php
session_start();
require_once 'includes/connection.php';

// Only allow admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);

    if ($userId === $_SESSION['user_id']) {
        header('Location: admin.php?error=cannot_delete_self');
        exit;
    }

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();

    if ($targetUser) {
        $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($deleteStmt->execute([$userId])) {
            header('Location: admin.php?deleted=1');
        } else {
            header('Location: admin.php?error=delete_failed');
        }
    } else {
        header('Location: admin.php?error=user_not_found');
    }
} else {
    header('Location: admin.php?error=invalid_request');
}
exit;
