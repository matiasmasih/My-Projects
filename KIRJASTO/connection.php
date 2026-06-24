<?php
// connection.php

$host = "localhost";
$dbname = "Kirjasto";
$username = "AzizRN";
$password = "Matias413114312@#$?";

try {
    // Set DSN (Data Source Name)
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

    // Create PDO instance with error mode set to Exception
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,    // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch assoc arrays by default
        PDO::ATTR_EMULATE_PREPARES => false,            // Use real prepared statements
    ]);
} catch (PDOException $e) {
    // Handle connection error
    die("Database connection failed: " . $e->getMessage());
}
