<?php
session_start();
require_once 'connection.php';
require_once 'receipt_helper.php'; // Added receipt helper

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$loan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($loan_id === 0) {
    header("Location: admin_laitelainat.php");
    exit();
}

// Get user info
$user_query = "SELECT rooli, etunimi, sukunimi FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Redirect if admin or manager
if ($user['rooli'] == 'admin') {
    header("Location: admin_dashboard.php");
    exit();
} elseif ($user['rooli'] == 'manager') {
    header("Location: manager_dashboard.php");
    exit();
}

// Check if loan belongs to user and is active
$check_query = "SELECT l.id, l.laite_id, l.lainaus_kunto, d.merkki, d.malli 
                FROM Laitelainat l
                JOIN Laitteet d ON l.laite_id = d.id
                WHERE l.id = ? AND l.jasen_id = ? AND l.palautus_pvm IS NULL";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $loan_id, $user_id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) {
    header("Location: admin_laitelainat.php?error=invalid_loan");
    exit();
}

$laite_nimi = $loan['merkki'] . ' ' . $loan['malli'];
$today = date('Y-m-d H:i:s');

// Update loan as returned
$update_query = "UPDATE Laitelainat SET palautus_pvm = ? WHERE id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("si", $today, $loan_id);

if ($stmt->execute()) {
    // Update device status back to available
    $update_device = "UPDATE Laitteet SET tila = 'saatavilla' WHERE id = ?";
    $stmt2 = $conn->prepare($update_device);
    $stmt2->bind_param("i", $loan['laite_id']);
    $stmt2->execute();
    $stmt2->close();

    // ============================================
    // GENERATE RECEIPT FOR DEVICE RETURN
    // ============================================
    createReturnReceipt($user_id, $loan_id, 'device', $laite_nimi, $today);

    header("Location: admin_laitelainat.php?success=returned");
} else {
    header("Location: admin_laitelainat.php?error=return_failed");
}
exit();
?>
