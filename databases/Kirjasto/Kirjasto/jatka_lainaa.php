<?php
// jatka_lainaa.php - Extend loan period for books AND devices
session_start();
require_once 'connection.php';
require_once 'receipt_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$loan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'book'; // 'book' or 'device'

if ($loan_id === 0) {
    header("Location: user_lainahistoria.php?error=invalid_loan");
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
if ($type == 'book') {
    $check_query = "SELECT l.id, l.erapaiva, l.jatkettu, k.nimi as kohde_nimi 
                    FROM lainat l
                    JOIN kirjat k ON l.kirja_id = k.id
                    WHERE l.id = ? AND l.jasen_id = ? AND l.tila = 'aktiivinen'";
} else {
    $check_query = "SELECT l.id, l.erapaiva, l.jatkettu, d.nimi as kohde_nimi 
                    FROM Laitelainat l
                    JOIN Laitteet d ON l.laite_id = d.id
                    WHERE l.id = ? AND l.jasen_id = ? AND l.palautus_pvm IS NULL";
}

$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $loan_id, $user_id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();

if (!$loan) {
    header("Location: user_lainahistoria.php?error=invalid_loan");
    exit();
}

// Check if already extended (max 1 extension)
if (isset($loan['jatkettu']) && $loan['jatkettu'] == 1) {
    header("Location: user_lainahistoria.php?error=already_extended");
    exit();
}

// Extend loan by 14 days
$new_erapaiva = date('Y-m-d', strtotime($loan['erapaiva'] . ' +30 days'));

if ($type == 'book') {
    $update_query = "UPDATE lainat SET erapaiva = ?, jatkettu = 1 WHERE id = ?";
} else {
    $update_query = "UPDATE Laitelainat SET erapaiva = ?, jatkettu = 1 WHERE id = ?";
}

$stmt = $conn->prepare($update_query);
$stmt->bind_param("si", $new_erapaiva, $loan_id);

if ($stmt->execute()) {
    // Create extension receipt
    $kuvaus = "🔄 JATKOKUITTI - " . ucfirst($type) . ": " . $loan['kohde_nimi'] . " - Uusi eräpäivä: " . date('d.m.Y', strtotime($new_erapaiva));

    $receipt_query = "INSERT INTO kuitit (jasen_id, summa, kuvaus, tila, maksupaiva) 
                      VALUES (?, 0, ?, 'maksettu', NOW())";
    $receipt_stmt = $conn->prepare($receipt_query);
    $receipt_stmt->bind_param("is", $user_id, $kuvaus);
    $receipt_stmt->execute();

    $success_msg = urlencode("Laina-aikaa jatkettu 14 päivällä! Uusi eräpäivä: " . date('d.m.Y', strtotime($new_erapaiva)));
    header("Location: user_lainahistoria.php?success=" . $success_msg);
} else {
    header("Location: user_lainahistoria.php?error=extend_failed");
}
?>
