<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['vastaanottaja_id']) || !isset($_POST['viesti'])) {
    echo json_encode(['success' => false, 'error' => 'Puuttuvat tiedot']);
    exit();
}

$lahettaja_id = $_SESSION['user_id'];
$vastaanottaja_id = intval($_POST['vastaanottaja_id']);
$viesti = trim($_POST['viesti']);

if (empty($viesti)) {
    echo json_encode(['success' => false, 'error' => 'Viesti on tyhjä']);
    exit();
}

// Tarkista että vastaanottaja on olemassa
$check = $conn->prepare("SELECT id, rooli FROM jasenet WHERE id = ?");
$check->bind_param("i", $vastaanottaja_id);
$check->execute();
$result = $check->get_result();
$vastaanottaja = $result->fetch_assoc();

if (!$vastaanottaja) {
    echo json_encode(['success' => false, 'error' => 'Vastaanottajaa ei löydy']);
    exit();
}

// Tarkista lähettäjän rooli
$check2 = $conn->prepare("SELECT rooli FROM jasenet WHERE id = ?");
$check2->bind_param("i", $lahettaja_id);
$check2->execute();
$result2 = $check2->get_result();
$lahettaja = $result2->fetch_assoc();

// Jos lähettäjä on tavallinen käyttäjä, voi lähettää vain adminille tai managerille
if ($lahettaja['rooli'] == 'user' && !in_array($vastaanottaja['rooli'], ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'error' => 'Voit lähettää viestejä vain adminille tai managerille']);
    exit();
}

// Tallenna viesti
$stmt = $conn->prepare("INSERT INTO viestit (lahettaja_id, vastaanottaja_id, viesti, luettu) VALUES (?, ?, ?, 0)");
$stmt->bind_param("iis", $lahettaja_id, $vastaanottaja_id, $viesti);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Tietokantavirhe']);
}
?>
