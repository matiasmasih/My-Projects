<?php
// receipt_helper.php - Automatic receipt creation helper

function generateReceipt($jasen_id, $summa, $kuvaus, $laina_id = null, $laitelaina_id = null, $sakko_id = null, $tyyppi = 'maksu') {
    global $conn;

    $query = "INSERT INTO kuitit (jasen_id, laina_id, laitelaina_id, sakko_id, summa, kuvaus, maksupaiva, tila)
              VALUES (?, ?, ?, ?, ?, ?, NOW(), 'maksettu')";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiiis", $jasen_id, $laina_id, $laitelaina_id, $sakko_id, $summa, $kuvaus);

    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

function createLoanReceipt($jasen_id, $laina_id, $tyyppi, $nimi, $paivamaara) {
    if ($tyyppi == 'book') {
        $kuvaus = "📚 LAINAKUITTI - Kirja: $nimi - Lainattu: " . date('d.m.Y', strtotime($paivamaara));
    } else {
        $kuvaus = "💻 LAINAKUITTI - Laite: $nimi - Lainattu: " . date('d.m.Y', strtotime($paivamaara));
    }

    return generateReceipt($jasen_id, 0, $kuvaus, ($tyyppi == 'book' ? $laina_id : null), ($tyyppi == 'device' ? $laina_id : null));
}

function createReturnReceipt($jasen_id, $laina_id, $tyyppi, $nimi, $paivamaara) {
    if ($tyyppi == 'book') {
        $kuvaus = "✅ PALAUTUSKUITTI - Kirja: $nimi - Palautettu: " . date('d.m.Y', strtotime($paivamaara));
    } else {
        $kuvaus = "✅ PALAUTUSKUITTI - Laite: $nimi - Palautettu: " . date('d.m.Y', strtotime($paivamaara));
    }

    return generateReceipt($jasen_id, 0, $kuvaus, ($tyyppi == 'book' ? $laina_id : null), ($tyyppi == 'device' ? $laina_id : null));
}

// ============================================
// ADDITIONAL HELPER FUNCTIONS
// ============================================

/**
 * Create receipt for fine payment
 */
function createFineReceipt($jasen_id, $sakko_id, $summa, $syy) {
    $kuvaus = "💰 MAKSOKUITTI - Sakko: " . $syy . " - Maksettu: " . date('d.m.Y') . " - Summa: " . number_format($summa, 2) . " €";
    return generateReceipt($jasen_id, $summa, $kuvaus, null, null, $sakko_id);
}

/**
 * Create receipt for manual payment
 */
function createManualReceipt($jasen_id, $summa, $kuvaus) {
    return generateReceipt($jasen_id, $summa, $kuvaus);
}
?>
