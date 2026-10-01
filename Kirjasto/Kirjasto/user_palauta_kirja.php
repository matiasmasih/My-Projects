<?php
// user_palauta_kirja.php - User returns a book
session_start();
require_once 'connection.php';
require_once 'receipt_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$laina_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$laina_id) {
    header("Location: user_lainahistoria.php?error=invalid_loan");
    exit();
}

// Verify this loan belongs to user and is active
$check_sql = "SELECT l.*, k.nimi as kirja_nimi 
              FROM lainat l 
              JOIN kirjat k ON l.kirja_id = k.id 
              WHERE l.id = ? AND l.jasen_id = ? AND l.tila = 'aktiivinen'";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ii", $laina_id, $user_id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) {
    header("Location: user_lainahistoria.php?error=invalid_loan");
    exit();
}

// Calculate fine if overdue
$return_date = date('Y-m-d');
$due_date = $loan['erapaiva'];
$fine = 0;

if ($return_date > $due_date) {
    $days_overdue = (strtotime($return_date) - strtotime($due_date)) / (60 * 60 * 24);
    $fine = $days_overdue * 1.00;
}

// Mark as returned
$update_sql = "UPDATE lainat SET tila = 'palautettu', palautuspaiva = ?, sakot = ? WHERE id = ?";
$stmt = $conn->prepare($update_sql);
$stmt->bind_param("sdi", $return_date, $fine, $laina_id);

if ($stmt->execute()) {
    // Generate return receipt
    createReturnReceipt($user_id, $laina_id, 'book', $loan['kirja_nimi'], $return_date);
    
    $message = urlencode("Kirja '" . $loan['kirja_nimi'] . "' palautettu onnistuneesti!");
    if ($fine > 0) {
        $message .= urlencode(" Sakko: " . number_format($fine, 2, ',', ' ') . " €");
    }
    header("Location: user_lainahistoria.php?success=" . $message);
} else {
    header("Location: user_lainahistoria.php?error=return_failed");
}
?>
