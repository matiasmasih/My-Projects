<?php
session_start();
include("connection.php");

// Check if user is logged in (based on email session)
if (!isset($_SESSION['email'])) {
    header("location: login1.php");
    exit;
}

try {
    // Query to get all tables in the current database
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Error fetching tables: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Tables in Kirjasto Database</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
    <?php include "headerForAdmin.php"; ?>
    <div class="container container-display mt-5">
        <h2>Tables in Kirjasto Database</h2>

        <?php if ($tables): ?>
            <ul class="list-group">
                <?php foreach ($tables as $table): ?>
                    <li class="list-group-item"><?= htmlspecialchars($table) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No tables found in the database.</p>
        <?php endif; ?>
    </div>
</body>
</html>
