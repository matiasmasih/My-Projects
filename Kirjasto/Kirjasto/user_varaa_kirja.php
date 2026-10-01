<?php
session_start();
$current_page = basename($_SERVER["PHP_SELF"]);
require_once 'connection.php';
require_once 'receipt_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if book ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: user_selaa_kirjoja.php");
    exit();
}

$book_id = $_GET['id'];

// Get book name
$book_query = "SELECT nimi FROM kirjat WHERE id = ?";
$stmt = $conn->prepare($book_query);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();

if (!$book) {
    header("Location: user_selaa_kirjoja.php");
    exit();
}

$kirja_nimi = $book['nimi'];

// Get available copy
$copy_query = "SELECT id FROM Kirjakopiot WHERE kirja_id = ? AND tila = 'saatavilla' LIMIT 1";
$stmt = $conn->prepare($copy_query);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$copy = $stmt->get_result()->fetch_assoc();

if (!$copy) {
    header("Location: user_selaa_kirjoja.php?error=not_available");
    exit();
}

$copy_id = $copy['id'];

// Create loan
$lainauspaiva = date('Y-m-d');
$erapaiva = date('Y-m-d', strtotime('+14 days'));

$loan_query = "INSERT INTO lainat (jasen_id, kirja_id, lainauspaiva, erapaiva, tila)
               VALUES (?, ?, ?, ?, 'aktiivinen')";
$stmt = $conn->prepare($loan_query);
$stmt->bind_param("iiss", $user_id, $book_id, $lainauspaiva, $erapaiva);

if ($stmt->execute()) {
    $loan_id = $conn->insert_id;

    // Update copy status
    $update_copy = "UPDATE Kirjakopiot SET tila = 'lainassa' WHERE id = ?";
    $stmt2 = $conn->prepare($update_copy);
    $stmt2->bind_param("i", $copy_id);
    $stmt2->execute();
    $stmt2->close();

    // Generate loan receipt
    generateReceipt($user_id, 0, "LAINAKUITTI: " . $kirja_nimi . " - Lainattu " . date('d.m.Y', strtotime($lainauspaiva)), $loan_id, null, null);

    header("Location: user_lainahistoria.php?success=1");
} else {
    header("Location: user_selaa_kirjoja.php?error=loan_failed");
}
exit();
?>
