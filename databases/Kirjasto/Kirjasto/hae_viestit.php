<?php
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['kayttaja_id'])) {
    echo json_encode([]);
    exit();
}

$oma_id = $_SESSION['user_id'];
$toinen_id = intval($_GET['kayttaja_id']);

// Hae viestit kahden käyttäjän välillä
$stmt = $conn->prepare("
    SELECT * FROM viestit 
    WHERE (lahettaja_id = ? AND vastaanottaja_id = ?) 
       OR (lahettaja_id = ? AND vastaanottaja_id = ?)
    ORDER BY luontiaika ASC
");
$stmt->bind_param("iiii", $oma_id, $toinen_id, $toinen_id, $oma_id);
$stmt->execute();
$result = $stmt->get_result();

$viestit = [];
while ($row = $result->fetch_assoc()) {
    $viestit[] = $row;
}

// Merkitse vastaanotetut viestit luetuiksi
$stmt = $conn->prepare("UPDATE viestit SET luettu = 1 WHERE lahettaja_id = ? AND vastaanottaja_id = ? AND luettu = 0");
$stmt->bind_param("ii", $toinen_id, $oma_id);
$stmt->execute();

echo json_encode($viestit);
?>
