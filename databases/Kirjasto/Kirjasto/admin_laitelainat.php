<?php
session_start();
require_once 'connection.php';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT rooli, profile_image, etunimi, sukunimi, email FROM jasenet WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($rooli, $profile_image, $etunimi, $sukunimi, $email);
$stmt->fetch();
$stmt->close();

// Only manager and admin can access
if ($rooli !== 'manager' && $rooli !== 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Get user display info
$kayttajan_nimi = $etunimi . ' ' . $sukunimi;
$custom_name = $etunimi . ' ' . $sukunimi;
$custom_email = isset($email) ? $email : "matiasmasih@gmail.com";
$custom_role_display = $rooli === 'admin' ? "Ylläpitäjä" : "Manager";
$custom_permissions = $rooli === 'admin' ? "Täydet järjestelmäoikeudet" : "Täydet laiteoikeudet";

// Profile image helper function
function getProfileImageUrl($profile_image, $user_name) {
    if (empty($profile_image)) {
        return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=3498db&color=fff&size=128';
    }
    if (filter_var($profile_image, FILTER_VALIDATE_URL)) {
        return $profile_image;
    }
    if (file_exists($profile_image)) {
        return $profile_image;
    }
    if (file_exists('uploads/profiles/' . $profile_image)) {
        return 'uploads/profiles/' . $profile_image;
    }
    $filename = basename($profile_image);
    if (file_exists('uploads/profiles/' . $filename)) {
        return 'uploads/profiles/' . $filename;
    }
    if (file_exists($filename)) {
        return $filename;
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=3498db&color=fff&size=128';
}

$profile_image_url = getProfileImageUrl($profile_image ?? '', $kayttajan_nimi);

$message = '';
$error = '';
$edit_mode = false;
$edit_data = null;

// Check if Laitelainat table exists, create if not
$table_check = $conn->query("SHOW TABLES LIKE 'Laitelainat'");
if ($table_check && $table_check->num_rows == 0) {
    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS Laitelainat (
        id INT PRIMARY KEY AUTO_INCREMENT,
        laite_id INT NOT NULL,
        jasen_id INT NOT NULL,
        varaus_id INT,
        lainaus_pvm DATETIME DEFAULT CURRENT_TIMESTAMP,
        erapaiva DATETIME NOT NULL,
        palautus_pvm DATETIME,
        lainaus_kunto ENUM('erinomainen','hyvä','tyydyttävä','huono'),
        palautus_kunto ENUM('erinomainen','hyvä','tyydyttävä','huono'),
        myohastyymismaksu DECIMAL(10,2) DEFAULT 0.00,
        huomiot TEXT,
        luotu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (laite_id) REFERENCES Laitteet(id) ON DELETE RESTRICT,
        FOREIGN KEY (jasen_id) REFERENCES jasenet(id) ON DELETE CASCADE,
        FOREIGN KEY (varaus_id) REFERENCES Laitevaraukset(id) ON DELETE SET NULL,
        INDEX idx_erapaiva (erapaiva),
        INDEX idx_lainaus_pvm (lainaus_pvm),
        INDEX idx_palautus_pvm (palautus_pvm)
    )";

    if ($conn->query($create_table_sql)) {
        $message = "✅ Laitelainataulukko luotu onnistuneesti!";
    } else {
        $error = "❌ Virhe luotaessa laitelainataulukkoa: " . $conn->error;
    }
}

// Check if Laitteet table exists
$laitteet_check = $conn->query("SHOW TABLES LIKE 'Laitteet'");
$laitteet_exists = $laitteet_check && $laitteet_check->num_rows > 0;

// Check if Laitetyypit table exists
$laitetyypit_check = $conn->query("SHOW TABLES LIKE 'Laitetyypit'");
$laitetyypit_exists = $laitetyypit_check && $laitetyypit_check->num_rows > 0;

// Check if Laitevaraukset table exists
$varaukset_check = $conn->query("SHOW TABLES LIKE 'Laitevaraukset'");
$varaukset_exists = $varaukset_check && $varaukset_check->num_rows > 0;

// Get current datetime
$current_datetime = date('Y-m-d H:i:s');
$current_date = date('Y-m-d');

// Calculate late fees for all overdue loans
function calculateLateFee($due_date, $return_date = null) {
    $due = new DateTime($due_date);
    $now = new DateTime();
    $return = $return_date ? new DateTime($return_date) : $now;

    if ($return > $due) {
        $interval = $due->diff($return);
        $late_days = $interval->days;

        // Jos palautus on samana päivänä eräpäivän jälkeen, se lasketaan 1 päiväksi
        if ($interval->h > 0 || $interval->i > 0 || $interval->s > 0) {
            $late_days = max(1, $late_days);
        }

        // 1 € per päivä per laite
        return $late_days * 1.00;
    }

    return 0.00;
}

// Update all overdue loans with late fees
$update_late_fees_sql = "
    SELECT id, erapaiva, palautus_pvm, myohastyymismaksu 
    FROM Laitelainat 
    WHERE palautus_pvm IS NULL 
    AND erapaiva < NOW()";
$late_fees_result = $conn->query($update_late_fees_sql);

if ($late_fees_result && $late_fees_result->num_rows > 0) {
    while ($loan = $late_fees_result->fetch_assoc()) {
        $late_fee = calculateLateFee($loan['erapaiva']);

        if ($late_fee > 0 && $late_fee != $loan['myohastyymismaksu']) {
            $update_sql = "UPDATE Laitelainat SET myohastyymismaksu = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("di", $late_fee, $loan['id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }
}

// Process forms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_loan'])) {
        $laite_id = intval($_POST['laite_id']);
        $jasen_id = intval($_POST['jasen_id']);
        $varaus_id = !empty($_POST['varaus_id']) ? intval($_POST['varaus_id']) : null;
        $lainaus_pvm = $_POST['lainaus_pvm'];
        $lainaa_paivia = intval($_POST['lainaa_paivia']);
        $lainaus_kunto = $_POST['lainaus_kunto'];
        $huomiot = trim($_POST['huomiot']);

        // Calculate due date - handle calendar days properly
        $due_date = new DateTime($lainaus_pvm);
        $due_date->modify("+{$lainaa_paivia} days");
        $erapaiva = $due_date->format('Y-m-d H:i:s');

        // Check if device exists and is available
        if ($laitteet_exists) {
            $device_check = $conn->prepare("SELECT tila, laite_tyyppi_id FROM Laitteet WHERE id = ?");
            $device_check->bind_param("i", $laite_id);
            $device_check->execute();
            $device_result = $device_check->get_result();

            if ($device_result->num_rows === 0) {
                $error = "❌ Laitetta ei löydy!";
            } else {
                $device = $device_result->fetch_assoc();

                // Check if device is already borrowed
                $loan_check = $conn->prepare("SELECT id FROM Laitelainat WHERE laite_id = ? AND palautus_pvm IS NULL");
                $loan_check->bind_param("i", $laite_id);
                $loan_check->execute();
                $loan_result = $loan_check->get_result();

                if ($loan_result->num_rows > 0) {
                    $error = "❌ Laite on jo lainattu toiselle käyttäjälle!";
                } else {
                    // Get loan period from device type if available
                    $loan_days = $lainaa_paivia;
                    if ($laitetyypit_exists && $device['laite_tyyppi_id']) {
                        $type_sql = "SELECT laina_aika FROM Laitetyypit WHERE id = ?";
                        $type_stmt = $conn->prepare($type_sql);
                        $type_stmt->bind_param("i", $device['laite_tyyppi_id']);
                        $type_stmt->execute();
                        $type_result = $type_stmt->get_result();
                        if ($type_result->num_rows > 0) {
                            $type = $type_result->fetch_assoc();
                            $loan_days = $type['laina_aika'];
                            // Recalculate due date with device type's loan period
                            $due_date = new DateTime($lainaus_pvm);
                            $due_date->modify("+{$loan_days} days");
                            $erapaiva = $due_date->format('Y-m-d H:i:s');
                        }
                        $type_stmt->close();
                    }

                    // Check if there's a reservation
                    if ($varaus_id) {
                        $reservation_check = $conn->prepare("SELECT id, tila FROM Laitevaraukset WHERE id = ?");
                        $reservation_check->bind_param("i", $varaus_id);
                        $reservation_check->execute();
                        $reservation_result = $reservation_check->get_result();

                        if ($reservation_result->num_rows === 0) {
                            $error = "❌ Varausta ei löydy!";
                        } else {
                            $reservation = $reservation_result->fetch_assoc();
                            if ($reservation['tila'] !== 'vahvistettu') {
                                $error = "❌ Varaus ei ole vahvistettu!";
                            }
                        }
                        $reservation_check->close();
                    }

                    if (empty($error)) {
                        // Start transaction
                        $conn->begin_transaction();

                        try {
                            // Insert loan
                            $sql = "INSERT INTO Laitelainat (laite_id, jasen_id, varaus_id, lainaus_pvm, erapaiva, lainaus_kunto, huomiot)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("iiissss", $laite_id, $jasen_id, $varaus_id, $lainaus_pvm, $erapaiva, $lainaus_kunto, $huomiot);

                            if (!$stmt->execute()) {
                                throw new Exception("Virhe lisättäessä lainaa: " . $stmt->error);
                            }
                            $stmt->close();

                            // Update device status to 'lainassa'
                            $update_device = "UPDATE Laitteet SET tila = 'lainassa' WHERE id = ?";
                            $update_stmt = $conn->prepare($update_device);
                            $update_stmt->bind_param("i", $laite_id);

                            if (!$update_stmt->execute()) {
                                throw new Exception("Virhe päivittäessä laitetta: " . $update_stmt->error);
                            }
                            $update_stmt->close();

                            // Update reservation status if exists
                            if ($varaus_id) {
                                $update_reservation = "UPDATE Laitevaraukset SET tila = 'täytetty' WHERE id = ?";
                                $res_stmt = $conn->prepare($update_reservation);
                                $res_stmt->bind_param("i", $varaus_id);

                                if (!$res_stmt->execute()) {
                                    throw new Exception("Virhe päivittäessä varausta: " . $res_stmt->error);
                                }
                                $res_stmt->close();
                            }

                            // Commit transaction
                            $conn->commit();
                            $message = "🎉 Laina lisätty onnistuneesti! Eräpäivä: " . date('d.m.Y', strtotime($erapaiva));

                        } catch (Exception $e) {
                            // Rollback transaction on error
                            $conn->rollback();
                            $error = "❌ " . $e->getMessage();
                        }
                    }
                }
                $loan_check->close();
            }
            $device_check->close();
        } else {
            $error = "❌ Laitetaulu ei ole käytettävissä!";
        }
    }
    elseif (isset($_POST['return_device'])) {
        $id = intval($_POST['id']);
        $palautus_pvm = $_POST['palautus_pvm'];
        $palautus_kunto = $_POST['palautus_kunto'];
        $myohastyymismaksu_input = floatval($_POST['myohastyymismaksu']);
        $palautus_huomiot = trim($_POST['palautus_huomiot']);

        // Get loan details
        $loan_sql = "SELECT laite_id, jasen_id, erapaiva, myohastyymismaksu FROM Laitelainat WHERE id = ?";
        $loan_stmt = $conn->prepare($loan_sql);
        $loan_stmt->bind_param("i", $id);
        $loan_stmt->execute();
        $loan_stmt->bind_result($laite_id, $jasen_id, $erapaiva, $existing_fee);
        $loan_stmt->fetch();
        $loan_stmt->close();

        // Calculate automatic late fee
        $auto_late_fee = calculateLateFee($erapaiva, $palautus_pvm);

        // Use the larger of input fee or calculated fee
        $final_late_fee = max($myohastyymismaksu_input, $auto_late_fee);

        // Start transaction
        $conn->begin_transaction();

        try {
            // Update loan with return information
            $sql = "UPDATE Laitelainat SET
                    palautus_pvm = ?,
                    palautus_kunto = ?,
                    myohastyymismaksu = ?,
                    huomiot = CONCAT(IFNULL(huomiot, ''), '\nPalautus: ', ?)
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdsi", $palautus_pvm, $palautus_kunto, $final_late_fee, $palautus_huomiot, $id);

            if (!$stmt->execute()) {
                throw new Exception("Virhe päivittäessä lainaa: " . $stmt->error);
            }
            $stmt->close();

            // Update device status back to 'saatavilla'
            $update_device = "UPDATE Laitteet SET tila = 'saatavilla', kunto = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_device);
            $update_stmt->bind_param("si", $palautus_kunto, $laite_id);

            if (!$update_stmt->execute()) {
                throw new Exception("Virhe päivittäessä laitetta: " . $update_stmt->error);
            }
            $update_stmt->close();

            // Commit transaction
            $conn->commit();

            // Calculate late days for message
            $due_date = new DateTime($erapaiva);
            $return_date = new DateTime($palautus_pvm);
            if ($return_date > $due_date) {
                $interval = $due_date->diff($return_date);
                $late_days = $interval->days;
                if ($interval->h > 0 || $interval->i > 0 || $interval->s > 0) {
                    $late_days = max(1, $late_days);
                }
                $message = "✅ Laite palautettu onnistuneesti! Myöhässä: {$late_days} päivää, Sakko: {$final_late_fee}€";
            } else {
                $message = "✅ Laite palautettu onnistuneesti! Ei myöhästymismaksua.";
            }

        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "❌ " . $e->getMessage();
        }
    }
    elseif (isset($_POST['delete_loan'])) {
        $id = intval($_POST['id']);

        // Check if loan has been returned
        $check_sql = "SELECT palautus_pvm, laite_id FROM Laitelainat WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $loan = $check_result->fetch_assoc();
        $check_stmt->close();

        if ($loan && !$loan['palautus_pvm']) {
            $error = "❌ Et voi poistaa aktiivista lainaa! Palauta laite ensin.";
        } else {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Delete loan
                $sql = "DELETE FROM Laitelainat WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);

                if (!$stmt->execute()) {
                    throw new Exception("Virhe poistettaessa lainaa: " . $stmt->error);
                }
                $stmt->close();

                // If loan was returned, update device status
                if ($loan['palautus_pvm']) {
                    $update_device = "UPDATE Laitteet SET tila = 'saatavilla' WHERE id = ?";
                    $update_stmt = $conn->prepare($update_device);
                    $update_stmt->bind_param("i", $loan['laite_id']);

                    if (!$update_stmt->execute()) {
                        throw new Exception("Virhe päivittäessä laitetta: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                }

                $conn->commit();
                $message = "🗑️ Laina poistettu onnistuneesti!";

            } catch (Exception $e) {
                $conn->rollback();
                $error = "❌ " . $e->getMessage();
            }
        }
    }
    elseif (isset($_POST['update_late_fee'])) {
        $id = intval($_POST['id']);
        $new_fee = floatval($_POST['new_fee']);

        $update_sql = "UPDATE Laitelainat SET myohastyymismaksu = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("di", $new_fee, $id);

        if ($update_stmt->execute()) {
            $message = "✅ Myöhästymismaksu päivitetty onnistuneesti!";
        } else {
            $error = "❌ Virhe päivittäessä maksua: " . $conn->error;
        }
        $update_stmt->close();
    }
}

// Check if editing/returning
if (isset($_GET['return'])) {
    $return_id = intval($_GET['return']);
    $return_sql = "SELECT l.*, d.sarjanumero, d.merkki, d.malli, j.etunimi, j.sukunimi 
                   FROM Laitelainat l
                   JOIN Laitteet d ON l.laite_id = d.id
                   JOIN jasenet j ON l.jasen_id = j.id
                   WHERE l.id = ?";
    $return_stmt = $conn->prepare($return_sql);
    $return_stmt->bind_param("i", $return_id);
    $return_stmt->execute();
    $return_result = $return_stmt->get_result();

    if ($return_result->num_rows > 0) {
        $edit_mode = true;
        $edit_data = $return_result->fetch_assoc();

        // Calculate late fee for display
        $due_date = new DateTime($edit_data['erapaiva']);
        $now = new DateTime();
        if ($now > $due_date) {
            $interval = $due_date->diff($now);
            $late_days = $interval->days;
            if ($interval->h > 0 || $interval->i > 0 || $interval->s > 0) {
                $late_days = max(1, $late_days);
            }
            $edit_data['calculated_late_fee'] = $late_days * 1.00;
            $edit_data['late_days'] = $late_days;
        } else {
            $edit_data['calculated_late_fee'] = 0.00;
            $edit_data['late_days'] = 0;
        }
    }
    $return_stmt->close();
}

// Get all members for dropdown
$members = [];
$members_result = $conn->query("SELECT id, etunimi, sukunimi, email FROM jasenet ORDER BY sukunimi, etunimi");
if ($members_result) {
    while ($member = $members_result->fetch_assoc()) {
        $members[] = $member;
    }
}

// Get all available devices for dropdown
$devices = [];
if ($laitteet_exists) {
    $devices_result = $conn->query("
        SELECT id, sarjanumero, merkki, malli, tila
        FROM Laitteet
        WHERE tila IN ('saatavilla', 'varattu')
        ORDER BY merkki, malli
    ");
    if ($devices_result) {
        while ($device = $devices_result->fetch_assoc()) {
            $devices[] = $device;
        }
    }
}

// Get active reservations for dropdown
$reservations = [];
if ($varaukset_exists) {
    $reservations_result = $conn->query("
        SELECT v.id, v.laite_id, v.jasen_id, v.varaus_paiva,
               j.etunimi, j.sukunimi,
               l.sarjanumero, l.merkki, l.malli
        FROM Laitevaraukset v
        JOIN jasenet j ON v.jasen_id = j.id
        JOIN Laitteet l ON v.laite_id = l.id
        WHERE v.tila = 'vahvistettu'
        AND NOT EXISTS (
            SELECT 1 FROM Laitelainat
            WHERE varaus_id = v.id
            OR (laite_id = v.laite_id AND palautus_pvm IS NULL)
        )
        ORDER BY v.varaus_paiva DESC
    ");
    if ($reservations_result) {
        while ($reservation = $reservations_result->fetch_assoc()) {
            $reservations[] = $reservation;
        }
    }
}

// Get statistics with updated late fees
$total_loans = 0;
$active_loans = 0;
$overdue_loans = 0;
$returned_loans = 0;
$total_fines = 0.00;
$today_loans = 0;
$today_returns = 0;

// Calculate all statistics
$stats_result = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN palautus_pvm IS NULL THEN 1 ELSE 0 END) as aktiiviset,
        SUM(CASE WHEN palautus_pvm IS NULL AND erapaiva < NOW() THEN 1 ELSE 0 END) as myohassa,
        SUM(CASE WHEN palautus_pvm IS NOT NULL THEN 1 ELSE 0 END) as palautetut,
        SUM(myohastyymismaksu) as sakot,
        SUM(CASE WHEN DATE(lainaus_pvm) = CURDATE() THEN 1 ELSE 0 END) as tanaan_lainattu,
        SUM(CASE WHEN DATE(palautus_pvm) = CURDATE() THEN 1 ELSE 0 END) as tanaan_palautettu
    FROM Laitelainat
");

if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
    $total_loans = $stats['total'] ?? 0;
    $active_loans = $stats['aktiiviset'] ?? 0;
    $overdue_loans = $stats['myohassa'] ?? 0;
    $returned_loans = $stats['palautetut'] ?? 0;
    $total_fines = $stats['sakot'] ?? 0.00;
    $today_loans = $stats['tanaan_lainattu'] ?? 0;
    $today_returns = $stats['tanaan_palautettu'] ?? 0;
}

// Get member late fees summary
$member_late_fees = [];
$late_fees_summary = $conn->query("
    SELECT 
        j.id as jasen_id,
        CONCAT(j.etunimi, ' ', j.sukunimi) as nimi,
        COUNT(l.id) as myohassa_lainoja,
        SUM(l.myohastyymismaksu) as sakkoja_yhteensa
    FROM jasenet j
    LEFT JOIN Laitelainat l ON j.id = l.jasen_id AND l.palautus_pvm IS NULL AND l.erapaiva < NOW()
    GROUP BY j.id
    HAVING sakkoja_yhteensa > 0 OR myohassa_lainoja > 0
    ORDER BY sakkoja_yhteensa DESC
");

if ($late_fees_summary) {
    while ($member = $late_fees_summary->fetch_assoc()) {
        $member_late_fees[] = $member;
    }
}

// Get all loans with member and device info
$sql = "SELECT
            l.*,
            j.etunimi as jasen_etunimi,
            j.sukunimi as jasen_sukunimi,
            j.email as jasen_email,
            d.sarjanumero,
            d.merkki,
            d.malli,
            d.laite_tyyppi_id,
            t.nimi as tyyppi_nimi,
            t.laina_aika,
            v.varaus_paiva,
            CASE 
                WHEN l.palautus_pvm IS NULL AND l.erapaiva < NOW() THEN 'myohassa'
                WHEN l.palautus_pvm IS NULL THEN 'aktiivinen'
                ELSE 'palautettu'
            END as status
        FROM Laitelainat l
        LEFT JOIN jasenet j ON l.jasen_id = j.id
        LEFT JOIN Laitteet d ON l.laite_id = d.id
        LEFT JOIN Laitetyypit t ON d.laite_tyyppi_id = t.id
        LEFT JOIN Laitevaraukset v ON l.varaus_id = v.id
        ORDER BY l.lainaus_pvm DESC";
$result = $conn->query($sql);

// Get search filter
$search = '';
$status_filter = '';
$date_filter = '';
$member_filter = '';

if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
}
if (isset($_GET['status'])) {
    $status_filter = $conn->real_escape_string($_GET['status']);
}
if (isset($_GET['date'])) {
    $date_filter = $conn->real_escape_string($_GET['date']);
}
if (isset($_GET['member'])) {
    $member_filter = intval($_GET['member']);
}

// Build filtered query
$filter_sql = "SELECT
                    l.*,
                    j.etunimi as jasen_etunimi,
                    j.sukunimi as jasen_sukunimi,
                    j.email as jasen_email,
                    d.sarjanumero,
                    d.merkki,
                    d.malli,
                    d.laite_tyyppi_id,
                    t.nimi as tyyppi_nimi,
                    t.laina_aika,
                    v.varaus_paiva,
                    CASE 
                        WHEN l.palautus_pvm IS NULL AND l.erapaiva < NOW() THEN 'myohassa'
                        WHEN l.palautus_pvm IS NULL THEN 'aktiivinen'
                        ELSE 'palautettu'
                    END as status
                FROM Laitelainat l
                LEFT JOIN jasenet j ON l.jasen_id = j.id
                LEFT JOIN Laitteet d ON l.laite_id = d.id
                LEFT JOIN Laitetyypit t ON d.laite_tyyppi_id = t.id
                LEFT JOIN Laitevaraukset v ON l.varaus_id = v.id
                WHERE 1=1";

if (!empty($search)) {
    $filter_sql .= " AND (j.etunimi LIKE '%$search%'
                        OR j.sukunimi LIKE '%$search%'
                        OR d.sarjanumero LIKE '%$search%'
                        OR d.merkki LIKE '%$search%'
                        OR d.malli LIKE '%$search%')";
}
if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $filter_sql .= " AND l.palautus_pvm IS NULL AND l.erapaiva >= NOW()";
    } elseif ($status_filter === 'overdue') {
        $filter_sql .= " AND l.palautus_pvm IS NULL AND l.erapaiva < NOW()";
    } elseif ($status_filter === 'returned') {
        $filter_sql .= " AND l.palautus_pvm IS NOT NULL";
    }
}
if (!empty($date_filter)) {
    $filter_sql .= " AND DATE(l.lainaus_pvm) = '$date_filter'";
}
if (!empty($member_filter)) {
    $filter_sql .= " AND l.jasen_id = $member_filter";
}

$filter_sql .= " ORDER BY l.lainaus_pvm DESC";
$filtered_result = $conn->query($filter_sql);

// Get device types for loan period
$device_types = [];
if ($laitetyypit_exists) {
    $type_result = $conn->query("SELECT id, nimi, laina_aika FROM Laitetyypit ORDER BY nimi");
    if ($type_result) {
        while ($type = $type_result->fetch_assoc()) {
            $device_types[] = $type;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjasto - Laitelainat</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<style>
    :root {
        --primary: #2C3E50;
        --secondary: #E74C3C;
        --success: #27AE60;
        --danger: #F39C12;
        --warning: #F1C40F;
        --info: #3498DB;
        --purple: #9B59B6;
        --dark: #1A1A2E;
        --light: #F8F9FA;
        --sidebar-bg: #1A1A2E;
        --sidebar-text: #E0E0E0;
        --sidebar-hover: #3498DB;
        --sidebar-width: 250px;
        --card-bg: rgba(255, 255, 255, 0.95);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    body {
        background: linear-gradient(rgba(26, 26, 46, 0.4), rgba(26, 26, 46, 0.4)), url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-4.0.3');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        display: flex;
        min-height: 100vh;
        color: #333;
    }

    /* Sidebar Styles */
    .sidebar {
        width: var(--sidebar-width);
        background: var(--sidebar-bg);
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 5px 0 25px rgba(0,0,0,0.3);
    }

    .sidebar-header {
        padding: 25px 20px;
        background: linear-gradient(135deg, var(--primary), var(--dark));
        color: white;
        text-align: center;
        border-bottom: 2px solid var(--info);
    }

    .sidebar-header h2 {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-size: 1.2em;
        font-weight: 600;
    }

    .admin-badge {
        background: var(--danger);
        color: white;
        padding: 3px 8px;
        border-radius: 15px;
        font-size: 0.65em;
        margin-left: auto;
    }

    .sidebar-menu {
        padding: 15px 0;
    }

    .menu-section {
        padding: 12px 20px 5px;
        color: var(--info);
        font-size: 0.75em;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        margin: 8px 0;
    }

    .menu-item {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--sidebar-text);
        text-decoration: none;
        transition: all 0.3s;
        border-left: 3px solid transparent;
        margin: 4px 12px;
        border-radius: 6px;
        font-size: 0.9em;
    }

    .menu-item:hover, .menu-item.active {
        background: linear-gradient(135deg, var(--sidebar-hover), #2980B9);
        color: white;
        border-left-color: var(--warning);
        transform: translateX(4px);
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
    }

    .menu-item i {
        width: 20px;
        text-align: center;
        font-size: 1em;
    }

    .logout-item {
        margin-top: 25px;
        background: rgba(231, 76, 60, 0.1);
        border: 1px solid rgba(231, 76, 60, 0.2);
        font-size: 0.85em;
    }

    .logout-item:hover {
        background: linear-gradient(135deg, var(--danger), #C0392B);
        border-left-color: var(--danger);
    }

    /* Main Content */
    .main-content {
        flex: 1;
        margin-left: var(--sidebar-width);
        padding: 20px;
        max-width: calc(100vw - var(--sidebar-width));
        overflow-x: hidden;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding: 18px 20px;
        background: var(--card-bg);
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    }

    .header h1 {
        font-size: 1.8em;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary), var(--info));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
        background: var(--card-bg);
        padding: 10px 16px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.04);
    }

    .user-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border: 2px solid var(--info);
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .user-details h3 {
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 3px;
        font-size: 0.95em;
    }

    .user-details p {
        color: #666;
        font-size: 0.8em;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 18px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: var(--card-bg);
        padding: 22px;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        border-top: 4px solid;
    }

    .stat-card:nth-child(1) { border-color: var(--info); }
    .stat-card:nth-child(2) { border-color: var(--warning); }
    .stat-card:nth-child(3) { border-color: var(--success); }
    .stat-card:nth-child(4) { border-color: var(--danger); }
    .stat-card:nth-child(5) { border-color: var(--purple); }
    .stat-card:nth-child(6) { border-color: #1ABC9C; }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 28px rgba(0,0,0,0.12);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .stat-info h3 {
        font-size: 0.8em;
        color: #666;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }

    .stat-number {
        font-size: 2.2em;
        font-weight: 700;
        color: var(--primary);
    }

    .stat-icon {
        width: 55px;
        height: 55px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6em;
        color: white;
        box-shadow: 0 6px 15px rgba(0,0,0,0.15);
    }

    .stat-card:nth-child(1) .stat-icon { background: var(--info); }
    .stat-card:nth-child(2) .stat-icon { background: var(--warning); }
    .stat-card:nth-child(3) .stat-icon { background: var(--success); }
    .stat-card:nth-child(4) .stat-icon { background: var(--danger); }
    .stat-card:nth-child(5) .stat-icon { background: var(--purple); }
    .stat-card:nth-child(6) .stat-icon { background: #1ABC9C; }

    /* Late Fees Summary */
    .fees-summary {
        background: var(--card-bg);
        border-radius: 15px;
        padding: 22px;
        margin-bottom: 25px;
        box-shadow: 0 8px 22px rgba(0,0,0,0.08);
    }

    .fees-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .fee-item {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 10px;
        border-left: 4px solid var(--warning);
    }

    .fee-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .fee-name {
        font-weight: 600;
        color: var(--primary);
    }

    .fee-amount {
        font-weight: 700;
        color: var(--danger);
    }

    .fee-details {
        font-size: 0.9em;
        color: #666;
    }

    /* Filter Section */
    .filter-section {
        background: var(--card-bg);
        border-radius: 15px;
        padding: 22px;
        margin-bottom: 25px;
        box-shadow: 0 8px 22px rgba(0,0,0,0.08);
    }

    .section-title {
        font-size: 1.5em;
        color: var(--primary);
        margin-bottom: 22px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--info);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .search-filter {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        align-items: end;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
        color: var(--primary);
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.9em;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e8e8e8;
        border-radius: 8px;
        font-size: 0.95em;
        transition: all 0.3s;
        background: #fafafa;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--info);
        background: white;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }

    .form-select {
        appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233498DB' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 1em;
        padding-right: 40px;
        cursor: pointer;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        font-size: 0.95em;
        text-decoration: none;
        white-space: nowrap;
        min-width: 120px;
        height: 42px;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--info), #2980B9);
        color: white;
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.25);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(52, 152, 219, 0.35);
    }
    .btn-success {
        background: linear-gradient(135deg, var(--success), #219653);
        color: white;
        box-shadow: 0 4px 12px rgba(39, 174, 96, 0.25);
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(39, 174, 96, 0.35);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning), #E67E22);
        color: white;
        box-shadow: 0 4px 12px rgba(241, 196, 15, 0.25);
    }

    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(241, 196, 15, 0.35);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger), #C0392B);
        color: white;
        box-shadow: 0 4px 12px rgba(231, 76, 60, 0.25);
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(231, 76, 60, 0.35);
    }

    .btn-light {
        background: rgba(255, 255, 255, 0.1);
        color: #333;
        border: 2px solid #e8e8e8;
    }

    .btn-light:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    /* Loans Grid */
    .loans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .loan-card {
        background: var(--card-bg);
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 8px 22px rgba(0,0,0,0.08);
        transition: all 0.3s;
        border: 2px solid transparent;
    }

    .loan-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        border-color: var(--info);
    }

    .card-header {
        padding: 20px;
        background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.05));
        border-bottom: 1px solid #e8e8e8;
    }

    .loan-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5em;
        margin-bottom: 15px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .loan-card h3 {
        font-size: 1.2em;
        font-weight: 600;
        color: var(--primary);
        margin-bottom: 5px;
    }

    .card-body {
        padding: 20px;
    }

    .loan-info {
        margin-bottom: 15px;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        padding-bottom: 8px;
        border-bottom: 1px dashed #eee;
    }

    .info-label {
        font-weight: 600;
        color: #666;
        font-size: 0.9em;
    }

    .info-value {
        color: var(--primary);
        font-size: 0.9em;
        text-align: right;
        max-width: 60%;
        word-break: break-word;
    }

    .text-danger {
        color: #E74C3C !important;
    }

    .text-success {
        color: #27AE60 !important;
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        margin: 5px 0;
    }

    .status-active {
        background: rgba(52, 152, 219, 0.1);
        color: #3498DB;
        border: 1px solid rgba(52, 152, 219, 0.2);
    }

    .status-overdue {
        background: rgba(231, 76, 60, 0.1);
        color: #E74C3C;
        border: 1px solid rgba(231, 76, 60, 0.2);
    }

    .status-returned {
        background: rgba(39, 174, 96, 0.1);
        color: #27AE60;
        border: 1px solid rgba(39, 174, 96, 0.2);
    }

    /* Condition Badges */
    .condition-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 15px;
        font-size: 0.8em;
        font-weight: 600;
        margin: 3px;
    }

    .condition-excellent {
        background: rgba(39, 174, 96, 0.1);
        color: #27AE60;
        border: 1px solid rgba(39, 174, 96, 0.2);
    }

    .condition-good {
        background: rgba(52, 152, 219, 0.1);
        color: #3498DB;
        border: 1px solid rgba(52, 152, 219, 0.2);
    }

    .condition-fair {
        background: rgba(241, 196, 15, 0.1);
        color: #F1C40F;
        border: 1px solid rgba(241, 196, 15, 0.2);
    }

    .condition-poor {
        background: rgba(231, 76, 60, 0.1);
        color: #E74C3C;
        border: 1px solid rgba(231, 76, 60, 0.2);
    }

    /* Late Fee Display */
    .late-fee-display {
        background: rgba(231, 76, 60, 0.05);
        border: 2px solid rgba(231, 76, 60, 0.1);
        border-radius: 10px;
        padding: 12px;
        margin: 10px 0;
    }

    .late-fee-amount {
        font-size: 1.2em;
        font-weight: 700;
        color: #E74C3C;
    }

    .late-fee-days {
        font-size: 0.9em;
        color: #666;
    }

    /* Action Buttons */
    .card-actions {
        display: flex;
        gap: 8px;
        margin-top: 20px;
    }

    .action-btn {
        flex: 1;
        padding: 10px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        border: none;
        font-size: 0.85em;
        text-decoration: none;
    }

    .btn-return {
        background: rgba(39, 174, 96, 0.1);
        color: var(--success);
        border: 2px solid rgba(39, 174, 96, 0.2);
    }

    .btn-edit {
        background: rgba(52, 152, 219, 0.1);
        color: var(--info);
        border: 2px solid rgba(52, 152, 219, 0.2);
    }

    .btn-delete {
        background: rgba(149, 165, 166, 0.1);
        color: #7F8C8D;
        border: 2px solid rgba(149, 165, 166, 0.2);
    }

    .action-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }

    .btn-return:hover {
        background: linear-gradient(135deg, var(--success), #219653);
        color: white;
        border-color: var(--success);
    }

    .btn-edit:hover {
        background: linear-gradient(135deg, var(--info), #2980B9);
        color: white;
        border-color: var(--info);
    }

    .btn-delete:hover {
        background: linear-gradient(135deg, #7F8C8D, #616A6B);
        color: white;
        border-color: #7F8C8D;
    }

    /* Notification */
    .notification {
        padding: 12px 18px;
        border-radius: 8px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease-out;
        background: white;
        border: 2px solid;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        font-size: 0.9em;
    }

    .notification-success {
        border-color: #27AE60;
        color: #27AE60;
        background: rgba(39, 174, 96, 0.05);
    }

    .notification-error {
        border-color: #E74C3C;
        color: #E74C3C;
        background: rgba(231, 76, 60, 0.05);
    }

    @keyframes slideIn {
        from {
            transform: translateY(-15px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .empty-state {
        background: var(--card-bg);
        text-align: center;
        padding: 50px 25px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        margin: 30px 0;
        border: 2px solid rgba(52, 152, 219, 0.1);
        position: relative;
        overflow: hidden;
    }
    
    .empty-state::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--info), var(--success));
    }
    
    .empty-state i {
        font-size: 3.5em;
        margin-bottom: 20px;
        color: var(--info);
        display: inline-block;
        opacity: 0.8;
    }
    
    .empty-state h3 {
        font-size: 1.7em;
        margin-bottom: 12px;
        color: var(--primary);
        font-weight: 700;
    }
    
    .empty-state p {
        max-width: 500px;
        margin: 0 auto 25px;
        font-size: 1.1em;
        line-height: 1.6;
        color: #666;
    }
    
    .empty-state .btn {
        padding: 12px 28px;
        font-size: 1em;
        font-weight: 600;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.25);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .empty-state .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(52, 152, 219, 0.35);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .empty-state {
            padding: 40px 20px;
            margin: 20px 0;
        }
    
        .empty-state i {
            font-size: 3em;
        }
    
        .empty-state h3 {
            font-size: 1.5em;
        }
    
        .empty-state p {
            font-size: 1em;
        }
    }

    /* ====== MODAL STYLES WITH SCROLL FIX ====== */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        overflow-y: auto;
        padding: 20px 0;
    }

    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 25px;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        max-height: 85vh;
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--info);
        flex-shrink: 0;
    }

    .modal-title {
        font-size: 1.3em;
        color: var(--primary);
        font-weight: 600;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 1.5em;
        color: #999;
        cursor: pointer;
        transition: color 0.3s;
    }

    .close-modal:hover {
        color: var(--danger);
    }

    /* Modal Body - Scrollable Area */
    .modal-body {
        flex: 1;
        overflow-y: auto;
        max-height: calc(85vh - 120px);
        padding-right: 5px;
    }

    /* Custom scrollbar for modal */
    .modal-body::-webkit-scrollbar {
        width: 6px;
    }

    .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .modal-body::-webkit-scrollbar-thumb {
        background: var(--info);
        border-radius: 10px;
    }

    .modal-body::-webkit-scrollbar-thumb:hover {
        background: var(--primary);
    }

    /* Form styles inside modal */
    .modal-body form {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .modal-body .form-group {
        margin-bottom: 0;
    }

    .modal-body .form-control,
    .modal-body .form-select {
        width: 100%;
        margin-top: 5px;
    }

    .modal-body textarea {
        min-height: 100px;
        resize: vertical;
    }

    /* Calendar icon styling */
    .date-input-wrapper {
        position: relative;
    }
    
    .date-input-wrapper i {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--info);
    }

    /* Button container at bottom */
    .modal-footer {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-shrink: 0;
    }

    /* Responsive fixes for modals */
    @media (max-height: 700px) {
        .modal-content {
            margin: 2% auto;
            max-height: 96vh;
        }
        
        .modal-body {
            max-height: calc(96vh - 120px);
        }
    }

    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
            margin: 10% auto;
            padding: 20px;
            max-height: 90vh;
        }
        
        .modal-body {
            max-height: calc(90vh - 110px);
        }
        
        .modal-title {
            font-size: 1.2em;
        }
        
        .modal-footer {
            flex-direction: column;
        }
        
        .modal-footer .btn {
            width: 100%;
            margin-bottom: 10px;
        }
    }

    /* Mobile Responsive */
    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .loans-grid {
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        }
        .fees-grid {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
    }

    @media (max-width: 992px) {
        .sidebar {
            width: 60px;
        }
        .sidebar-header h2 span, .menu-item span, .menu-section {
            display: none;
        }
        .main-content {
            margin-left: 60px;
            padding: 15px;
        }
        .header {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
        .user-info {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        .search-filter {
            grid-template-columns: 1fr;
        }
        .loans-grid {
            grid-template-columns: 1fr;
        }
        .fees-grid {
            grid-template-columns: 1fr;
        }
        .card-actions {
            flex-wrap: wrap;
        }
    }

    @media (max-width: 576px) {
        .sidebar {
            width: 50px;
        }
        .main-content {
            margin-left: 50px;
            padding: 12px;
        }
        .header {
            padding: 15px;
        }
        .header h1 {
            font-size: 1.5em;
        }
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .stat-card {
            padding: 18px;
        }
        .stat-number {
            font-size: 1.8em;
        }
        .stat-icon {
            width: 45px;
            height: 45px;
            font-size: 1.4em;
        }
    }
</style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>
                <i class="fas fa-crown"></i>
                <span>Admin Panel</span>
                <span class="admin-badge"><?php echo $rooli === 'manager' ? 'Manager' : 'Admin'; ?></span>
            </h2>
        </div>
        <div class="sidebar-menu">
            <div class="menu-section">⚙️ Päävalikko</div>
            <a href="<?php echo $rooli === 'manager' ? 'manager_dashboard.php' : 'admin_dashboard.php'; ?>" class="menu-item">
                <span>🏠 Kojelauta</span>
            </a>

            <div class="menu-section">📚 Kirjaston Hallinta</div>
            <a href="admin_manage_kirjat.php" class="menu-item">
                <span>📖 Hallinnoi Kirjoja</span>
            </a>
            <a href="admin_lisaa_kirja.php" class="menu-item">
                <span>➕ Lisää Kirja</span>
            </a>
            <a href="admin_muokkaa_kirjaa.php" class="menu-item">
                <span>✏️ Muokkaa Kirjoja</span>
            </a>

            <div class="menu-section">👥 Jäsenten Hallinta</div>
            <a href="admin_kayttajien_hallinta.php" class="menu-item">
                <span>👤 Hallinnoi Jäseniä</span>
            </a>
            <a href="register.php" class="menu-item">
                <span>👥 Rekisteröi Jäsen</span>
            </a>

            <div class="menu-section">🔄 Lainaushallinta</div>
            <a href="admin_lainat.php" class="menu-item">
                <span>📋 Hallinnoi Lainoja</span>
            </a>
            <a href="admin_varaukset.php" class="menu-item">
                <span>✅ Käsittele Lainoja</span>
            </a>
            <a href="admin_palautukset.php" class="menu-item">
                <span>↩️ Hallinnoi Palautuksia</span>
            </a>
            <a href="admin_myohassa_kirjat.php" class="menu-item">
                <span>⏰ Myöhässä Olevat</span>
            </a>

            <!-- DEVICE MANAGEMENT SECTION -->
            <div class="menu-section">🖥️ Laitehallinta</div>
            <a href="admin_laitetyypit.php" class="menu-item">
                <span>💻 Laitetyypit</span>
            </a>
            <a href="admin_laitteet.php" class="menu-item">
                <span>📱 Laitteet</span>
            </a>
            <a href="admin_laitevaraukset.php" class="menu-item">
                <span>📅 Laitevaraukset</span>
            </a>
            <a href="laiteadmin_lainat.php" class="menu-item active">
                <span>🔄 Laitelainat</span>
            </a>

            <div class="menu-section">📊 Raportit & Sakot</div>
            <a href="admin_raportit.php" class="menu-item">
                <span>📈 Kirjasto Raportit</span>
            </a>
            <a href="admin_sakot.php" class="menu-item">
                <span>⚠️ Hallinnoi Sakkoja</span>
            </a>

            <div class="menu-section">🔧 Järjestelmä</div>
            <a href="admin_varmuuskopiointi.php" class="menu-item">
                <span>💾 Varmuuskopiot</span>
            </a>
            <a href="admin_kayttooikeudet.php" class="menu-item">
                <span>⚙️ Järjestelmäasetukset</span>
            </a>
            <a href="admin_palvelin_lokit.php" class="menu-item">
                <span>📋 Palvelinlokit</span>
            </a>

            <a href="logout.php" class="menu-item logout-item">
                <span>🚪 Kirjaudu Ulos</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-exchange-alt"></i> Laitelainat</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($kayttajan_nimi); ?>&background=3498db&color=fff&size=128'">
                </div>
                <div class="user-details">
                    <h3 style="color: #2C3E50; font-size: 1.1em; margin-bottom: 6px; font-weight: 700;">
                        <?php echo htmlspecialchars($custom_name); ?>
                    </h3>
                    <p class="user-email" style="color: #E74C3C; font-size: 0.85em; margin-bottom: 5px;">
                        <i class="fas fa-envelope" style="color: #E74C3C;"></i> <?php echo htmlspecialchars($custom_email); ?>
                    </p>
                    <p class="user-role" style="color: #3498DB; font-size: 0.9em; margin-bottom: 5px; font-weight: 600;">
                        <i class="fas fa-user-shield" style="color: #3498DB;"></i> <?php echo htmlspecialchars($custom_role_display); ?>
                    </p>
                    <p class="user-permissions" style="color: #27AE60; font-size: 0.8em; margin-bottom: 0; font-style: italic;">
                        <i class="fas fa-key" style="color: #27AE60;"></i> <?php echo htmlspecialchars($custom_permissions); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="notification notification-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="notification notification-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Kaikki Lainat</h3>
                        <div class="stat-number"><?php echo number_format($total_loans, 0, ',', ' '); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Aktiiviset</h3>
                        <div class="stat-number"><?php echo number_format($active_loans, 0, ',', ' '); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Myöhässä</h3>
                        <div class="stat-number"><?php echo number_format($overdue_loans, 0, ',', ' '); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Palautetut</h3>
                        <div class="stat-number"><?php echo number_format($returned_loans, 0, ',', ' '); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Tänään Lainattu</h3>
                        <div class="stat-number"><?php echo number_format($today_loans, 0, ',', ' '); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>Sakot Yhteensä</h3>
                        <div class="stat-number"><?php echo number_format($total_fines, 2, ',', ' '); ?>€</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Late Fees Summary -->
        <?php if (!empty($member_late_fees)): ?>
        <div class="fees-summary">
            <h2 class="section-title"><i class="fas fa-euro-sign"></i> Myöhästymismaksut</h2>
            <div class="fees-grid">
                <?php foreach ($member_late_fees as $member): ?>
                <div class="fee-item">
                    <div class="fee-header">
                        <span class="fee-name"><?php echo htmlspecialchars($member['nimi']); ?></span>
                        <span class="fee-amount"><?php echo number_format($member['sakkoja_yhteensa'], 2, ',', ' '); ?>€</span>
                    </div>
                    <div class="fee-details">
                        <?php if ($member['myohassa_lainoja'] > 0): ?>
                            <div><i class="fas fa-exclamation-circle" style="color: #E74C3C;"></i> <?php echo $member['myohassa_lainoja']; ?> lainaa myöhässä</div>
                        <?php endif; ?>
                        <?php if ($member['sakkoja_yhteensa'] > 0): ?>
                            <div><i class="fas fa-money-bill-wave" style="color: #27AE60;"></i> Sakkoja kertynyt</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <h2 class="section-title"><i class="fas fa-search"></i> Hae ja hallinnoi lainoja</h2>

            <form method="GET">
                <div class="search-filter">
                    <div class="form-group">
                        <label class="form-label" for="search">
                            <i class="fas fa-search"></i> Haku
                        </label>
                        <input type="text" name="search" class="form-control"
                               placeholder="Sarjanumero, jäsen, laite..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="status">
                            <i class="fas fa-info-circle"></i> Tila
                        </label>
                        <select name="status" class="form-control form-select">
                            <option value="">Kaikki tilat</option>
                            <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>🟢 Aktiiviset</option>
                            <option value="overdue" <?php echo ($status_filter == 'overdue') ? 'selected' : ''; ?>>🔴 Myöhässä</option>
                            <option value="returned" <?php echo ($status_filter == 'returned') ? 'selected' : ''; ?>>🟡 Palautetut</option>
                        </select>
                    </div>

                   <div class="form-group">
                    <label class="form-label" for="date">
                       <i class="fas fa-calendar"></i> Lainauspäivä
                    </label>
                   <div class="date-input-wrapper">
                     <input type="text" class="form-control datepicker" id="date" name="date"
                     placeholder="Valitse päivä..."
                     value="<?php echo htmlspecialchars($date_filter); ?>">
                   <i class="fas fa-calendar"></i>
                </div>
               </div>

                    <div class="form-group">
                        <label class="form-label" for="member">
                            <i class="fas fa-user"></i> Jäsen
                        </label>
                        <select name="member" class="form-control form-select">
                            <option value="">Kaikki jäsenet</option>
                            <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>"
                                    <?php echo ($member_filter == $member['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['sukunimi'] . ' ' . $member['etunimi']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Suodata
                            </button>
                            <a href="laiteadmin_lainat.php" class="btn btn-light">
                                <i class="fas fa-sync"></i> Tyhjennä
                            </a>
                            <button type="button" class="btn btn-success" onclick="showModal('add')">
                                <i class="fas fa-plus"></i> Uusi laina
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Loans Grid -->
        <div class="loans-grid" id="loansGrid">
            <?php if ($filtered_result && $filtered_result->num_rows > 0): ?>
                <?php
                $icon_colors = ['#3498DB', '#9B59B6', '#E74C3C', '#F39C12', '#1ABC9C', '#34495E', '#E67E22', '#16A085'];
                $icon_list = ['fa-laptop', 'fa-tablet-alt', 'fa-mobile-alt', 'fa-desktop', 'fa-headphones', 'fa-keyboard', 'fa-print', 'fa-camera', 'fa-gamepad', 'fa-server'];
                $i = 0;
                
                // Reset result pointer
                $filtered_result->data_seek(0);
                ?>

                <?php while ($loan = $filtered_result->fetch_assoc()):
                    $color = $icon_colors[$i % count($icon_colors)];
                    $icon = $icon_list[$i % count($icon_list)];

                    // Determine status
                    $is_returned = !empty($loan['palautus_pvm']);
                    $is_overdue = !$is_returned && strtotime($loan['erapaiva']) < time();
                    
                    $status_class = '';
                    $status_text = '';
                    
                    if ($is_returned) {
                        $status_class = 'status-returned';
                        $status_text = '🟡 Palautettu';
                    } elseif ($is_overdue) {
                        $status_class = 'status-overdue';
                        $status_text = '🔴 Myöhässä';
                    } else {
                        $status_class = 'status-active';
                        $status_text = '🟢 Aktiivinen';
                    }

                    // Determine condition badge class
                    $condition_class = '';
                    $condition_text = '';
                    $current_condition = $loan['palautus_kunto'] ?? $loan['lainaus_kunto'];
                    
                    switch($current_condition) {
                        case 'erinomainen': 
                            $condition_class = 'condition-excellent'; 
                            $condition_text = 'Erinomainen';
                            break;
                        case 'hyvä': 
                            $condition_class = 'condition-good'; 
                            $condition_text = 'Hyvä';
                            break;
                        case 'tyydyttävä': 
                            $condition_class = 'condition-fair'; 
                            $condition_text = 'Tyydyttävä';
                            break;
                        case 'huono': 
                            $condition_class = 'condition-poor'; 
                            $condition_text = 'Huono';
                            break;
                        default: 
                            $condition_class = 'condition-good'; 
                            $condition_text = 'Ei arvioitu';
                    }

                    // Calculate late days and fee
                    $late_days = 0;
                    $late_fee = $loan['myohastyymismaksu'];
                    if ($is_overdue && !$is_returned) {
                        $due_date = new DateTime($loan['erapaiva']);
                        $now = new DateTime();
                        if ($now > $due_date) {
                            $interval = $due_date->diff($now);
                            $late_days = $interval->days;
                            if ($interval->h > 0 || $interval->i > 0 || $interval->s > 0) {
                                $late_days = max(1, $late_days);
                            }
                        }
                    }

                    $i++;
                ?>
                <div class="loan-card">
                    <div class="card-header">
                        <div class="loan-icon" style="background: <?php echo $color; ?>22; color: <?php echo $color; ?>;">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <h3><?php echo htmlspecialchars($loan['sarjanumero']); ?></h3>
                        <div style="font-size: 0.9em; color: #666;">
                            <?php echo htmlspecialchars($loan['merkki'] ?: ''); ?>
                            <?php if ($loan['malli']): ?>
                                - <?php echo htmlspecialchars($loan['malli']); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-body">
                        <!-- Status and Condition Badges -->
                        <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                            <span class="status-badge <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                            
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span class="condition-badge <?php echo $condition_class; ?>">
                                    <i class="fas fa-check-circle"></i>
                                    <?php echo $condition_text; ?>
                                </span>
                                
                                <?php if ($late_fee > 0 || $is_overdue): ?>
                                <span style="background: rgba(231, 76, 60, 0.1); color: #E74C3C; padding: 6px 12px; border-radius: 15px; font-size: 0.8em; font-weight: 600;">
                                    <i class="fas fa-euro-sign"></i> 
                                    <?php if ($is_overdue && !$is_returned): ?>
                                        <?php echo number_format($late_days * 1.00, 2, ',', ' '); ?>€
                                    <?php else: ?>
                                        <?php echo number_format($late_fee, 2, ',', ' '); ?>€
                                    <?php endif; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Late Fee Display -->
                        <?php if ($is_overdue && !$is_returned && $late_days > 0): ?>
                        <div class="late-fee-display">
                            <div class="late-fee-amount">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Myöhässä: <?php echo $late_days; ?> päivää
                            </div>
                            <div class="late-fee-days">
                                Sakko: <?php echo number_format($late_days * 1.00, 2, ',', ' '); ?>€ 
                                (1 €/päivä)
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Loan Info -->
                        <div class="loan-info">
                            <div class="info-item">
                                <span class="info-label">Jäsen:</span>
                                <span class="info-value">
                                    <?php echo htmlspecialchars($loan['jasen_etunimi'] . ' ' . $loan['jasen_sukunimi']); ?>
                                    <?php if ($loan['jasen_email']): ?>
                                    <br><small style="color: #666; font-size: 0.85em;"><?php echo htmlspecialchars($loan['jasen_email']); ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Lainattu:</span>
                                <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($loan['lainaus_pvm'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Eräpäivä:</span>
                                <span class="info-value <?php echo $is_overdue ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo date('d.m.Y H:i', strtotime($loan['erapaiva'])); ?>
                                    <?php if ($is_overdue && !$is_returned): ?>
                                    <br><small class="text-danger" style="font-weight: 600;">Myöhässä <?php echo $late_days; ?> päivää!</small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if ($loan['palautus_pvm']): ?>
                            <div class="info-item">
                                <span class="info-label">Palautettu:</span>
                                <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($loan['palautus_pvm'])); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($loan['tyyppi_nimi']): ?>
                            <div class="info-item">
                                <span class="info-label">Tyyppi:</span>
                                <span class="info-value"><?php echo htmlspecialchars($loan['tyyppi_nimi']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($loan['huomiot']): ?>
                            <div class="info-item">
                                <span class="info-label">Huomiot:</span>
                                <span class="info-value"><?php echo nl2br(htmlspecialchars($loan['huomiot'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="card-actions">
                            <?php if (!$is_returned): ?>
                                <a href="?return=<?php echo $loan['id']; ?>" class="action-btn btn-return">
                                    <i class="fas fa-undo"></i> Palauta
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($is_returned): ?>
                                <form method="POST" style="display: contents;">
                                    <input type="hidden" name="id" value="<?php echo $loan['id']; ?>">
                                    <button type="submit" name="delete_loan" class="action-btn btn-delete"
                                            onclick="return confirm('Haluatko varmasti poistaa lainan?')">
                                        <i class="fas fa-trash"></i> Poista
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-exchange-alt" style="color: #ccc;"></i>
                    <h3>Ei lainoja</h3>
                    <p>Aloita luomalla ensimmäinen laitelaina. Lainaukset mahdollistavat laitteiden jakamisen jäsenille.</p>
                    <button class="btn btn-success" onclick="showModal('add')" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i> Luo ensimmäinen laina
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Add Loan Modal -->
<div id="addLoanModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-plus-circle"></i> Luo Uusi Laina
            </div>
            <button class="close-modal" onclick="hideModal('add')">&times;</button>
        </div>
        
        <!-- Add this div for scrollable content -->
        <div class="modal-body">
            <form method="POST" id="addLoanForm">
                <input type="hidden" name="add_loan" value="1">

                <div class="form-group">
                    <label for="laite_id" class="form-label">
                        <i class="fas fa-laptop"></i> Laite *
                    </label>
                    <select class="form-control form-select" id="laite_id" name="laite_id" required>
                        <option value="">Valitse laite</option>
                        <?php foreach ($devices as $device): ?>
                        <option value="<?php echo $device['id']; ?>">
                            <?php echo htmlspecialchars($device['sarjanumero'] . ' - ' . $device['merkki'] . ' ' . $device['malli'] . ' (' . $device['tila'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="jasen_id" class="form-label">
                        <i class="fas fa-user"></i> Jäsen *
                    </label>
                    <select class="form-control form-select" id="jasen_id" name="jasen_id" required>
                        <option value="">Valitse jäsen</option>
                        <?php foreach ($members as $member): ?>
                        <option value="<?php echo $member['id']; ?>">
                            <?php echo htmlspecialchars($member['sukunimi'] . ' ' . $member['etunimi'] . ' (' . $member['email'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="varaus_id" class="form-label">
                        <i class="fas fa-calendar-check"></i> Varaus (valinnainen)
                    </label>
                    <select class="form-control form-select" id="varaus_id" name="varaus_id">
                        <option value="">Ei varausta</option>
                        <?php foreach ($reservations as $reservation): ?>
                        <option value="<?php echo $reservation['id']; ?>">
                            <?php echo htmlspecialchars($reservation['sarjanumero'] . ' - ' . $reservation['etunimi'] . ' ' . $reservation['sukunimi'] . ' (' . date('d.m.Y', strtotime($reservation['varaus_paiva'])) . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                    <!-- Loan Date with Icon -->
                    <div class="form-group with-icon">
                        <label for="lainaus_pvm" class="form-label">
                            <i class="fas fa-calendar-plus"></i> Lainauspäivä *
                        </label>
                    <div class="date-input-wrapper">
                      <input type="datetime-local" class="form-control custom-datetime" id="lainaus_pvm" name="lainaus_pvm" required
                        value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="lainaa_paivia" class="form-label">
                            <i class="fas fa-calendar-alt"></i> Laina-aika (päivää) *
                        </label>
                        <input type="number" class="form-control" id="lainaa_paivia" name="lainaa_paivia"
                               min="1" max="365" value="31" required>
                    </div>
                </div>

               <!-- Due Date Display -->
               <div id="dueDateDisplay" style="display: none; margin: 15px 0; padding: 12px; background: rgba(52, 152, 219, 0.1); border-radius: 8px; border-left: 4px solid var(--info); font-size: 0.95em; color: var(--primary);">
                  <strong>Eräpäivä:</strong> <span id="dueDateText"></span>
               </div>

                <div class="form-group">
                    <label for="lainaus_kunto" class="form-label">
                        <i class="fas fa-clipboard-check"></i> Laitteen kunto lainatessa *
                    </label>
                    <select class="form-control form-select" id="lainaus_kunto" name="lainaus_kunto" required>
                        <option value="hyvä" selected>Hyvä</option>
                        <option value="erinomainen">Erinomainen</option>
                        <option value="tyydyttävä">Tyydyttävä</option>
                        <option value="huono">Huono</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="huomiot" class="form-label">
                        <i class="fas fa-sticky-note"></i> Huomiot
                    </label>
                    <textarea class="form-control" id="huomiot" name="huomiot" rows="3"
                              placeholder="Erityistoiveet, lisätiedot..."></textarea>
                </div>
            </form>
        </div>

        <!-- Move buttons to modal-footer -->
        <div class="modal-footer">
            <button type="button" class="btn btn-light" onclick="hideModal('add')">
                <i class="fas fa-times"></i> Peruuta
            </button>
            <button type="button" class="btn btn-success" onclick="validateAndSubmit()">
                <i class="fas fa-save"></i> Tallenna laina
            </button>
        </div>
    </div>
</div>

    <!-- Return Device Modal -->
    <?php if ($edit_mode && isset($edit_data)): ?>
    <div id="returnDeviceModal" class="modal" style="display: block;">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-undo"></i> Palauta Laite
                </div>
                <button class="close-modal" onclick="hideModal('return')">&times;</button>
            </div>

            <form method="POST" id="returnDeviceForm">
                <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                <input type="hidden" name="return_device" value="1">

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-laptop"></i> Laite
                    </label>
                    <div class="form-control" style="background: #f8f9fa;">
                        <?php echo htmlspecialchars($edit_data['sarjanumero'] . ' - ' . $edit_data['merkki'] . ' ' . $edit_data['malli']); ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Jäsen
                    </label>
                    <div class="form-control" style="background: #f8f9fa;">
                        <?php echo htmlspecialchars($edit_data['etunimi'] . ' ' . $edit_data['sukunimi']); ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="palautus_pvm" class="form-label">
                        <i class="fas fa-calendar-check"></i> Palautuspäivä *
                    </label>
                    <div class="date-input-wrapper">
                        <input type="datetime-local" class="form-control" id="palautus_pvm" name="palautus_pvm" required
                               value="<?php echo date('Y-m-d\TH:i'); ?>">
                        <i class="fas fa-calendar"></i>
                    </div>
                </div>

                <?php if (isset($edit_data['calculated_late_fee']) && $edit_data['calculated_late_fee'] > 0): ?>
                <div class="late-fee-display">
                    <div class="late-fee-amount">
                        <i class="fas fa-calculator"></i> 
                        Laskettu sakko: <?php echo number_format($edit_data['calculated_late_fee'], 2, ',', ' '); ?>€
                    </div>
                    <div class="late-fee-days">
                        Myöhässä: <?php echo $edit_data['late_days']; ?> päivää (1 €/päivä)
                    </div>
                </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="palautus_kunto" class="form-label">
                            <i class="fas fa-clipboard-check"></i> Kunto palautettaessa *
                        </label>
                        <select class="form-control form-select" id="palautus_kunto" name="palautus_kunto" required>
                            <option value="hyvä" selected>Hyvä</option>
                            <option value="erinomainen">Erinomainen</option>
                            <option value="tyydyttävä">Tyydyttävä</option>
                            <option value="huono">Huono</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="myohastyymismaksu" class="form-label">
                            <i class="fas fa-euro-sign"></i> Myöhästymismaksu (€)
                        </label>
                        <input type="number" class="form-control" id="myohastyymismaksu" name="myohastyymismaksu" 
                               min="0" max="1000" step="0.01" 
                               value="<?php echo isset($edit_data['calculated_late_fee']) ? number_format($edit_data['calculated_late_fee'], 2, '.', '') : '0.00'; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="palautus_huomiot" class="form-label">
                        <i class="fas fa-sticky-note"></i> Palautushuomiot
                    </label>
                    <textarea class="form-control" id="palautus_huomiot" name="palautus_huomiot" rows="3"
                              placeholder="Vauriot, lisätiedot palautuksesta..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px;">
                    <a href="laiteadmin_lainat.php" class="btn btn-light">
                        <i class="fas fa-times"></i> Peruuta
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Tallenna palautus
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

<script>
    // Modal functions
    function showModal(modalType) {
        if (modalType === 'add') {
            const modal = document.getElementById('addLoanModal');
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
            // Set default dates
            setDefaultDates();
        }
    }

    function hideModal(modalType) {
        if (modalType === 'add') {
            document.getElementById('addLoanModal').style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
        } else if (modalType === 'return') {
            window.location.href = 'laiteadmin_lainat.php';
        }
    }

    // Set default dates for datetime-local inputs
    function setDefaultDates() {
        // Set current datetime for loan date
        const loanDateInput = document.getElementById('lainaus_pvm');
        if (loanDateInput) {
            const now = new Date();
            // Format: YYYY-MM-DDTHH:MM
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            loanDateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
            calculateDueDate(); // Calculate due date immediately
        }

        // Set return date
        const returnDateInput = document.getElementById('palautus_pvm');
        if (returnDateInput && !returnDateInput.value) {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            returnDateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        }
    }

    // Auto-show return modal if in edit mode
    <?php if (isset($edit_mode) && $edit_mode): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const returnModal = document.getElementById('returnDeviceModal');
        if (returnModal) {
            returnModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Set default return date if empty
            const returnDateInput = document.getElementById('palautus_pvm');
            if (returnDateInput && !returnDateInput.value) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                
                returnDateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
            }
        }
    });
    <?php endif; ?>

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            if (event.target.id === 'addLoanModal') {
                hideModal('add');
            } else if (event.target.id === 'returnDeviceModal') {
                window.location.href = 'laiteadmin_lainat.php';
            }
        }
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            if (document.getElementById('addLoanModal')?.style.display === 'block') {
                hideModal('add');
            }
            if (document.getElementById('returnDeviceModal')?.style.display === 'block') {
                window.location.href = 'laiteadmin_lainat.php';
            }
        }
    });

    // Form validation
    document.getElementById('addLoanForm')?.addEventListener('submit', function(e) {
        const laite_id = document.getElementById('laite_id').value;
        const jasen_id = document.getElementById('jasen_id').value;
        const lainaus_pvm = document.getElementById('lainaus_pvm').value;
        const lainaa_paivia = document.getElementById('lainaa_paivia').value;

        if (!laite_id) {
            e.preventDefault();
            showValidationError('Valitse laite!', 'laite_id');
            return false;
        }

        if (!jasen_id) {
            e.preventDefault();
            showValidationError('Valitse jäsen!', 'jasen_id');
            return false;
        }

        if (!lainaus_pvm) {
            e.preventDefault();
            showValidationError('Valitse lainauspäivä!', 'lainaus_pvm');
            return false;
        }

        // Validate date format
        if (!isValidDateTime(lainaus_pvm)) {
            e.preventDefault();
            showValidationError('Virheellinen päivämäärämuoto!', 'lainaus_pvm');
            return false;
        }

        if (!lainaa_paivia || lainaa_paivia < 1 || lainaa_paivia > 365) {
            e.preventDefault();
            showValidationError('Laina-aika pitää olla 1-365 päivää!', 'lainaa_paivia');
            return false;
        }

        return true;
    });

    document.getElementById('returnDeviceForm')?.addEventListener('submit', function(e) {
        const palautus_pvm = document.getElementById('palautus_pvm').value;
        const palautus_kunto = document.getElementById('palautus_kunto').value;

        if (!palautus_pvm) {
            e.preventDefault();
            showValidationError('Valitse palautuspäivä!', 'palautus_pvm');
            return false;
        }

        // Validate date format
        if (!isValidDateTime(palautus_pvm)) {
            e.preventDefault();
            showValidationError('Virheellinen päivämäärämuoto!', 'palautus_pvm');
            return false;
        }

        if (!palautus_kunto) {
            e.preventDefault();
            showValidationError('Valitse laitteen kunto!', 'palautus_kunto');
            return false;
        }

        return true;
    });

    // Helper function to show validation errors
    function showValidationError(message, elementId) {
        // Remove existing error messages
        const existingError = document.querySelector('.validation-error');
        if (existingError) existingError.remove();

        // Create error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'notification notification-error validation-error';
        errorDiv.innerHTML = `
            <i class="fas fa-exclamation-circle"></i>
            <span>${message}</span>
        `;
        errorDiv.style.animation = 'slideIn 0.3s ease-out';
        
        // Insert after the form
        const form = document.querySelector('form');
        if (form) {
            form.parentNode.insertBefore(errorDiv, form);
        } else {
            document.querySelector('.modal-content').insertBefore(errorDiv, document.querySelector('.modal-content form'));
        }

        // Scroll to error
        const element = document.getElementById(elementId);
        if (element) {
            element.focus();
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Auto-remove error after 5 seconds
        setTimeout(() => {
            errorDiv.style.opacity = '0';
            errorDiv.style.transform = 'translateY(-15px)';
            setTimeout(() => errorDiv.remove(), 300);
        }, 5000);
    }

    // Validate datetime format
    function isValidDateTime(dateTimeString) {
        if (!dateTimeString) return false;
        
        // Check if it matches YYYY-MM-DDTHH:MM format
        const regex = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/;
        if (!regex.test(dateTimeString)) return false;
        
        // Check if it's a valid date
        const date = new Date(dateTimeString);
        return date instanceof Date && !isNaN(date);
    }

    // Set default date for return date on focus
    document.getElementById('palautus_pvm')?.addEventListener('focus', function() {
        if (!this.value) {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            this.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        }
    });

    // Auto-hide notifications after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification:not(.validation-error)');
            notifications.forEach(notification => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-15px)';
                notification.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.style.display = 'none';
                    }
                }, 300);
            });
        }, 5000);

        // Initialize date inputs on page load
        initializeDateInputs();
    });

    // Initialize all date inputs with proper attributes
    function initializeDateInputs() {
        // Add min/max attributes to date inputs
        const today = new Date();
        const maxDate = new Date();
        maxDate.setFullYear(today.getFullYear() + 1);
        
        const loanDateInput = document.getElementById('lainaus_pvm');
        if (loanDateInput) {
            const minDate = new Date();
            minDate.setFullYear(today.getFullYear() - 1);
            
            // Format dates for input attributes
            const minDateStr = minDate.toISOString().slice(0, 16);
            const maxDateStr = maxDate.toISOString().slice(0, 16);
            
            loanDateInput.min = minDateStr;
            loanDateInput.max = maxDateStr;
            
            // Add input event for real-time validation
            loanDateInput.addEventListener('change', function() {
                validateDateRange(this, minDateStr, maxDateStr);
            });
        }

        const returnDateInput = document.getElementById('palautus_pvm');
        if (returnDateInput) {
            const minDate = new Date('2020-01-01');
            const minDateStr = minDate.toISOString().slice(0, 16);
            const maxDateStr = maxDate.toISOString().slice(0, 16);
            
            returnDateInput.min = minDateStr;
            returnDateInput.max = maxDateStr;
            
            returnDateInput.addEventListener('change', function() {
                validateDateRange(this, minDateStr, maxDateStr);
            });
        }
    }

    // Validate date range
    function validateDateRange(input, minDate, maxDate) {
        const value = input.value;
        if (!value) return;
        
        if (value < minDate) {
            input.setCustomValidity('Päivämäärä ei voi olla ennen ' + formatDateForDisplay(minDate));
        } else if (value > maxDate) {
            input.setCustomValidity('Päivämäärä ei voi olla jälkeen ' + formatDateForDisplay(maxDate));
        } else {
            input.setCustomValidity('');
        }
    }

    // Format date for display
    function formatDateForDisplay(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleDateString('fi-FI', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Automatically set loan period based on device type
    document.getElementById('laite_id')?.addEventListener('change', function() {
        const deviceId = this.value;
        if (!deviceId) return;

        // In a real implementation, you would make an AJAX call to get the device type
        // For now, we'll use a default value
        document.getElementById('lainaa_paivia').value = 7;
        calculateDueDate(); // Recalculate due date
    });

    // Calculate due date when loan date or days changes
    function calculateDueDate() {
        const loanDate = document.getElementById('lainaus_pvm')?.value;
        const loanDays = document.getElementById('lainaa_paivia')?.value;

        if (loanDate && loanDays) {
            try {
                const date = new Date(loanDate);
                if (isNaN(date.getTime())) {
                    console.error('Invalid loan date');
                    return;
                }
                
                date.setDate(date.getDate() + parseInt(loanDays));
                
                // Display due date to user
                const dueDateElement = document.getElementById('dueDateDisplay') || createDueDateDisplay();
                const formattedDate = date.toLocaleDateString('fi-FI', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                dueDateElement.innerHTML = `<strong>Eräpäivä:</strong> ${formattedDate}`;
                dueDateElement.style.display = 'block';
                
            } catch (error) {
                console.error('Error calculating due date:', error);
            }
        }
    }

    // Create due date display element
    function createDueDateDisplay() {
        const dueDateElement = document.createElement('div');
        dueDateElement.id = 'dueDateDisplay';
        dueDateElement.style.cssText = `
            margin-top: 10px;
            padding: 10px;
            background: rgba(52, 152, 219, 0.1);
            border-radius: 8px;
            border-left: 4px solid #3498db;
            font-size: 0.9em;
            color: #2c3e50;
            display: none;
        `;
        
        const form = document.querySelector('#addLoanForm');
        const dateInput = document.getElementById('lainaa_paivia');
        if (form && dateInput) {
            dateInput.parentNode.insertBefore(dueDateElement, dateInput.nextSibling);
        }
        
        return dueDateElement;
    }

    // Add event listeners for due date calculation
    document.getElementById('lainaus_pvm')?.addEventListener('change', calculateDueDate);
    document.getElementById('lainaa_paivia')?.addEventListener('input', calculateDueDate);

    // Prevent form submission on Enter in input fields (unless in textarea)
    document.querySelectorAll('form input:not([type="submit"])').forEach(input => {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
            }
        });
    });

    // Initialize tooltips for date inputs
    document.querySelectorAll('input[type="datetime-local"]').forEach(input => {
        input.title = 'Klikkaa avataksesi kalenterin';
        input.addEventListener('click', function() {
            this.showPicker ? this.showPicker() : null;
        });
    });
</script>
</body>
</html>
