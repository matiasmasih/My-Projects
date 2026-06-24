<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid location ID.');
}

$location_id = (int)$_GET['id'];

// Fetch location name before deletion (for confirmation/logging if needed)
$stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
$stmt->execute([$location_id]);
$location = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$location) {
    die('Location not found.');
}

try {
    // Delete location (history will be deleted automatically)
    $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
    $stmt->execute([$location_id]);

    // Optional: log deletion to location_history if you want, but be careful it references location_id
    // Better not to insert log referencing deleted location (or insert with null location_id if schema allows)

    header('Location: admin_locations.php?message=deleted');
    exit;
} catch (PDOException $e) {
    die("Error deleting location: " . htmlspecialchars($e->getMessage()));
}


exit;
?>
