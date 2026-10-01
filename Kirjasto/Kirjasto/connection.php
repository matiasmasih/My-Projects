<?php
// connection.php - Tietokantayhteys
define('DB_HOST', 'localhost');
define('DB_NAME', 'Kirjasto');
define('DB_USER', 'AzizRN');
define('DB_PASS', 'Matias413114312@#$?');

// Virheraportointi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Luo tietokantayhteys
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Aseta merkistö
    $conn->set_charset("utf8mb4");

    // Tarkista yhteys
    if ($conn->connect_error) {
        throw new Exception("Yhteys epäonnistui: " . $conn->connect_error);
    }

} catch (Exception $e) {
    // Kirjaa virhe ja näytä käyttäjäystävällinen viesti
    error_log("Tietokantavirhe: " . $e->getMessage());
    die("Tietokantayhteyden muodostaminen epäonnistui. Yritä myöhemmin uudelleen.");
}

// Funktio yhteyden sulkemiseen
function closeConnection($connection) {
    if ($connection) {
        $connection->close();
    }
}
?>
