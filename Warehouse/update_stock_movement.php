<?php
require 'config.php';
session_start();

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $quantity = $_POST['quantity'] ?? null;
    $movement_type = $_POST['movement_type'] ?? null;

    if ($id && $quantity && $movement_type) {
        $stmt = $pdo->prepare("UPDATE stock_movements SET quantity = ?, movement_type = ? WHERE id = ?");
        if ($stmt->execute([$quantity, $movement_type, $id])) {
            header("Location: admin_stock_movements.php?update=success");
            exit;
        } else {
            echo "Failed to update record.";
        }
    } else {
        echo "Missing fields.";
    }
} else {
    echo "Invalid request.";
}
?>
