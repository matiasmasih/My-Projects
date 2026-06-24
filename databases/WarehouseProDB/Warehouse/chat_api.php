<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

/* ---------- POST : user sends a new message ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = trim($_POST['message'] ?? '');
    if ($msg === '') {
        echo json_encode(['success' => false, 'error' => 'Empty message']);
        exit;
    }

    $pdo->prepare("INSERT INTO messages (user_id, message, status, sent_at)
                   VALUES (?, ?, 'pending', NOW())")
        ->execute([$user_id, $msg]);

    echo json_encode(['success' => true]);
    exit;
}

/* ---------- GET : fetch user’s own messages (+ admin replies) ---------- */
$query = $pdo->prepare("
    SELECT m.id,
           m.user_id,
           u.username,
           m.message,
           m.admin_reply,
           m.sent_at,
           m.replied_at
    FROM   messages m
    JOIN   users u ON m.user_id = u.id
    WHERE  m.user_id = ?
    ORDER  BY m.sent_at ASC
");
$query->execute([$user_id]);

echo json_encode($query->fetchAll(PDO::FETCH_ASSOC));
