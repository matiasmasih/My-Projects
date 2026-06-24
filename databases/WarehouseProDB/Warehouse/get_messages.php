<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT m.id, m.user_id, u.username, m.message, m.sent_at
        FROM messages m
        JOIN users u ON m.user_id = u.id
        ORDER BY m.sent_at ASC
        LIMIT 50
    ");
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
