<?php
// ref.php — Human-readable reference number generator
// Format: PREFIX-YYYY-NNNNN
// Example: BR-2026-00003

/**
 * Generate the next reference number for a given prefix.
 *
 * @param PDO    $pdo    Database connection
 * @param string $prefix Short prefix (e.g. 'BR' for borrow, 'RE' for reserve)
 * @return string        Next reference, e.g. "BR-2026-00003"
 */
function next_ref(PDO $pdo, string $prefix): string {
    $year = date('Y');

    // We store the counter in a small helper table.
    // Create it if it doesn't exist.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ref_counter (
            prefix VARCHAR(10) NOT NULL,
            year INT NOT NULL,
            last_number INT NOT NULL DEFAULT 0,
            PRIMARY KEY (prefix, year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Lock the row, increment, return
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT last_number FROM ref_counter WHERE prefix = ? AND year = ? FOR UPDATE"
    );
    $stmt->execute([$prefix, $year]);
    $row = $stmt->fetch();

    if ($row) {
        $next = (int)$row['last_number'] + 1;
        $upd  = $pdo->prepare(
            "UPDATE ref_counter SET last_number = ? WHERE prefix = ? AND year = ?"
        );
        $upd->execute([$next, $prefix, $year]);
    } else {
        $next = 1;
        $ins = $pdo->prepare(
            "INSERT INTO ref_counter (prefix, year, last_number) VALUES (?, ?, ?)"
        );
        $ins->execute([$prefix, $year, $next]);
    }

    $pdo->commit();

    return sprintf('%s-%d-%05d', $prefix, $year, $next);
}
?>
