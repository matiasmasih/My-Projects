<?php
// get_book.php - Hae kirjan tiedot AJAX-pyyntöön

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Ei kirjautunut']);
    exit();
}

if (!isset($_POST['book_id'])) {
    echo json_encode(['success' => false, 'message' => 'Kirjan ID puuttuu']);
    exit();
}

$book_id = (int)$_POST['book_id'];

// Hae käyttäjän rooli
$user_id = $_SESSION['user_id'];
$user_query = "SELECT rooli FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Vain admin pääsee
if (!$user || $user['rooli'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Ei oikeuksia']);
    exit();
}

// Hae kirjan tiedot
$query = "SELECT * FROM kirjat WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();
$book = $result->fetch_assoc();

if ($book) {
    echo json_encode([
        'success' => true,
        'id' => $book['id'],
        'nimi' => $book['nimi'],
        'kirjailija' => $book['kirjailija'],
        'isbn' => $book['isbn'],
        'genre' => $book['genre'],
        'julkaisuvuosi' => $book['julkaisuvuosi'],
        'kustantaja' => $book['kustantaja'],
        'sivumaara' => $book['sivumaara'],
        'kuvaus' => $book['kuvaus'],
        'hinta' => $book['hinta'],
        'kopioiden_maara' => $book['kopioiden_maara'],
        'laina_statussa' => $book['laina_statussa'],
        'sijainti' => $book['sijainti']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Kirjaa ei löytynyt']);
}
?>
