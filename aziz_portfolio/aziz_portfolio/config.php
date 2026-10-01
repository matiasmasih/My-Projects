<?php
// config.php - Database connection for portfolio

$host = 'localhost';
$username = 'AzizRN';
$password = 'Matias413114312@#$?';
$database = 'aziz_portfolio';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// For debugging (remove in production)
// echo "Connected successfully";
?>
