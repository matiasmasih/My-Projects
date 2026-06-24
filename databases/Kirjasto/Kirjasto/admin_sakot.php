<?php
session_start();
require_once 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
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
$custom_email = $email ?? 'email@example.com';
$custom_role_display = $rooli === 'admin' ? 'Ylläpitäjä' : 'Manager';
$custom_permissions = $rooli === 'admin' ? 'Täydet järjestelmäoikeudet' : 'Täydet laiteoikeudet';

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
$message_type = '';
$error = '';

// Handle form actions
$action = $_GET['action'] ?? '';
$fine_id = $_GET['id'] ?? 0;

// Handle ADD NEW FINE
if ($action === 'add_fine' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_POST['member_id'];
    $device_id = $_POST['device_id'];
    $fine_amount = $_POST['amount'];
    $reason = $_POST['reason'];
    $due_date = $_POST['due_date'];

    // Insert new fine as a loan record with fine
    $insert_sql = "INSERT INTO Laitelainat (jasen_id, laite_id, lainaus_pvm, erapaiva, myohastyymismaksu, huomiot)
                   VALUES (?, ?, NOW(), ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("iisds", $member_id, $device_id, $due_date, $fine_amount, $reason);

    if ($stmt->execute()) {
        $message = "Uusi sakko lisätty onnistuneesti!";
        $message_type = "success";
    } else {
        $error = "Virhe: " . $conn->error;
    }
    $stmt->close();
}

// Handle MARK AS PAID
if ($action === 'mark_paid' && $fine_id > 0) {
    $update_sql = "UPDATE Laitelainat SET myohastyymismaksu = 0 WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("i", $fine_id);
    if ($stmt->execute()) {
        $message = "Sakko merkitty maksetuksi!";
        $message_type = "success";
    } else {
        $error = "Virhe: " . $conn->error;
    }
    $stmt->close();
}

// Handle UPDATE FINE
if ($action === 'update_fine' && isset($_POST['fine_id'])) {
    $fine_id = $_POST['fine_id'];
    $new_amount = $_POST['amount'];
    $new_reason = $_POST['reason'] ?? '';

    $update_sql = "UPDATE Laitelainat SET myohastyymismaksu = ?, huomiot = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("dsi", $new_amount, $new_reason, $fine_id);
    if ($stmt->execute()) {
        $message = "Sakon tiedot päivitetty!";
        $message_type = "success";
    } else {
        $error = "Virhe: " . $conn->error;
    }
    $stmt->close();
}

// Handle DELETE FINE (only for admin)
if ($action === 'delete_fine' && $fine_id > 0 && $rooli === 'admin') {
    $delete_sql = "DELETE FROM Laitelainat WHERE id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("i", $fine_id);
    if ($stmt->execute()) {
        $message = "Sakko poistettu!";
        $message_type = "success";
    } else {
        $error = "Virhe: " . $conn->error;
    }
    $stmt->close();
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_member = $_GET['member'] ?? '';
$filter_device = $_GET['device'] ?? '';
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';

// Build WHERE clause for filters
$where_clauses = ["l.myohastyymismaksu > 0"];
$params = [];
$param_types = "";

if ($filter_status === 'paid') {
    $where_clauses = ["l.myohastyymismaksu = 0"];
} elseif ($filter_status === 'unpaid') {
    $where_clauses = ["l.myohastyymismaksu > 0"];
}

if (!empty($filter_member) && $filter_status !== 'paid') {
    $where_clauses[] = "(j.etunimi LIKE ? OR j.sukunimi LIKE ? OR j.email LIKE ?)";
    $search_term = "%" . $filter_member . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sss";
}

if (!empty($filter_device) && $filter_status !== 'paid') {
    $where_clauses[] = "(d.merkki LIKE ? OR d.malli LIKE ? OR d.sarjanumero LIKE ?)";
    $search_term = "%" . $filter_device . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sss";
}

if (!empty($filter_start_date) && $filter_status !== 'paid') {
    $where_clauses[] = "l.lainaus_pvm >= ?";
    $params[] = $filter_start_date;
    $param_types .= "s";
}

if (!empty($filter_end_date) && $filter_status !== 'paid') {
    $where_clauses[] = "l.lainaus_pvm <= ?";
    $params[] = $filter_end_date;
    $param_types .= "s";
}

$where_sql = implode(" AND ", $where_clauses);

// Get fines data with filters
$fines_sql = "
    SELECT
        l.id,
        l.lainaus_pvm,
        l.erapaiva,
        l.palautus_pvm,
        l.myohastyymismaksu,
        l.huomiot,
        DATEDIFF(COALESCE(l.palautus_pvm, NOW()), l.erapaiva) as days_overdue,
        j.id as member_id,
        j.etunimi,
        j.sukunimi,
        j.email,
        j.puhelin,
        d.id as device_id,
        d.merkki,
        d.malli,
        d.sarjanumero,
        t.nimi as device_type
    FROM Laitelainat l
    JOIN jasenet j ON l.jasen_id = j.id
    JOIN Laitteet d ON l.laite_id = d.id
    LEFT JOIN Laitetyypit t ON d.laite_tyyppi_id = t.id
    WHERE $where_sql
    ORDER BY l.myohastyymismaksu DESC, l.lainaus_pvm DESC
";

$stmt = $conn->prepare($fines_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$fines = [];
while ($row = $result->fetch_assoc()) {
    $fines[] = $row;
}
$stmt->close();

// Get members for dropdown
$members_result = $conn->query("SELECT id, etunimi, sukunimi, email FROM jasenet WHERE tila = 'aktiivinen' ORDER BY sukunimi, etunimi");
$members = [];
while ($row = $members_result->fetch_assoc()) {
    $members[] = $row;
}

// Get devices for dropdown
$devices_result = $conn->query("SELECT id, merkki, malli, sarjanumero FROM Laitteet WHERE tila = 'vapaa' OR tila = 'lainassa' ORDER BY merkki, malli");
$devices = [];
while ($row = $devices_result->fetch_assoc()) {
    $devices[] = $row;
}

// Calculate totals
$total_fines = 0;
$unpaid_fines = 0;
$paid_fines_count = 0;
foreach ($fines as $fine) {
    $total_fines += $fine['myohastyymismaksu'];
    if ($fine['myohastyymismaksu'] > 0) {
        $unpaid_fines += $fine['myohastyymismaksu'];
    } else {
        $paid_fines_count++;
    }
}

// Handle export requests
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];

    if ($export_type === 'pdf') {
        // Generate PDF (simplified version - in production you'd use a PDF library like TCPDF or Dompdf)
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="sakot_' . date('Y-m-d') . '.pdf"');

        // In production, you'd generate actual PDF here
        echo "%PDF-1.4\n";
        echo "1 0 obj\n";
        echo "<< /Type /Catalog /Pages 2 0 R >>\n";
        echo "endobj\n";
        echo "2 0 obj\n";
        echo "<< /Type /Pages /Kids [3 0 R] /Count 1 >>\n";
        echo "endobj\n";
        echo "3 0 obj\n";
        echo "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\n";
        echo "endobj\n";
        echo "4 0 obj\n";
        echo "<< /Length 100 >>\n";
        echo "stream\n";
        echo "BT\n/F1 12 Tf\n56 750 Td\n(Sakkoraportti - " . date('d.m.Y') . ") Tj\nET\n";
        echo "endstream\n";
        echo "endobj\n";
        echo "xref\n0 5\n0000000000 65535 f\n0000000010 00000 n\n0000000053 00000 n\n0000000102 00000 n\n0000000200 00000 n\n";
        echo "trailer\n<< /Size 5 /Root 1 0 R >>\n";
        echo "startxref\n300\n%%EOF\n";
        exit();

    } elseif ($export_type === 'excel') {
        // Generate Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="sakot_' . date('Y-m-d') . '.xls"');

        echo "<table border='1'>";
        echo "<tr><th>Jäsen</th><th>Laite</th><th>Lainattu</th><th>Eräpäivä</th><th>Sakko (€)</th><th>Tila</th><th>Syy</th></tr>";

        foreach ($fines as $fine) {
            $is_paid = $fine['myohastyymismaksu'] == 0;
            echo "<tr>";
            echo "<td>" . htmlspecialchars($fine['sukunimi'] . ' ' . $fine['etunimi']) . "</td>";
            echo "<td>" . htmlspecialchars($fine['merkki'] . ' ' . $fine['malli']) . "</td>";
            echo "<td>" . date('d.m.Y H:i', strtotime($fine['lainaus_pvm'])) . "</td>";
            echo "<td>" . date('d.m.Y', strtotime($fine['erapaiva'])) . "</td>";
            echo "<td>" . number_format($fine['myohastyymismaksu'], 2, ',', ' ') . "€</td>";
            echo "<td>" . ($is_paid ? 'Maksettu' : 'Maksamatta') . "</td>";
            echo "<td>" . htmlspecialchars($fine['huomiot'] ?? '') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
        exit();

    } elseif ($export_type === 'csv') {
        // Generate CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sakot_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, ['Jäsen', 'Laite', 'Lainattu', 'Eräpäivä', 'Sakko (€)', 'Tila', 'Syy'], ';');

        foreach ($fines as $fine) {
            $is_paid = $fine['myohastyymismaksu'] == 0;
            fputcsv($output, [
                $fine['sukunimi'] . ' ' . $fine['etunimi'],
                $fine['merkki'] . ' ' . $fine['malli'],
                date('d.m.Y H:i', strtotime($fine['lainaus_pvm'])),
                date('d.m.Y', strtotime($fine['erapaiva'])),
                number_format($fine['myohastyymismaksu'], 2, ',', ' ') . '€',
                $is_paid ? 'Maksettu' : 'Maksamatta',
                $fine['huomiot'] ?? ''
            ], ';');
        }

        fclose($output);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjasto - Sakkojen Hallinta</title>
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fi.js"></script>
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
            background:
                linear-gradient(rgba(26, 26, 46, 0.85), rgba(26, 26, 46, 0.85)),
                url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
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
            padding: 30px 25px;
            background: linear-gradient(135deg, var(--primary), var(--dark));
            color: white;
            text-align: center;
            border-bottom: 2px solid var(--info);
        }

        .sidebar-header h2 {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-size: 1.4em;
            font-weight: 600;
            color: white;
        }

        .admin-badge {
            background: var(--secondary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7em;
            margin-left: auto;
        }

        .sidebar-menu {
            padding: 10px 0;
        }

        .menu-section {
            padding: 10px 15px 5px;
            color: var(--info);
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin: 10px 0;
        }

        .menu-item {
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            margin: 5px 15px;
            border-radius: 8px;
        }

        .menu-item:hover, .menu-item.active {
            background: linear-gradient(135deg, var(--sidebar-hover), #2980B9);
            color: white;
            border-left-color: var(--warning);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }

        .menu-item i {
            width: 20px;
            text-align: center;
            font-size: 1.2em;
        }

        .logout-item {
            margin-top: 30px;
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .logout-item:hover {
            background: linear-gradient(135deg, var(--secondary), #C0392B);
            border-left-color: var(--secondary);
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 15px 25px;
            width: calc(100% - var(--sidebar-width));
        }

        /* Top section */
        .top-section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .title-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--info), #2980B9);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3em;
        }

        .page-title {
            font-size: 1.6em;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }

        /* PROFILE SECTION */
        .profile-section {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(245, 247, 250, 0.8);
            padding: 15px 20px;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            text-align: left;
        }

        .profile-name {
            font-size: 1.05em;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 3px;
        }

        .profile-email {
            color: var(--secondary);
            font-size: 0.85em;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 4px;
        }

        .profile-role {
            color: var(--info);
            font-size: 0.8em;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 3px;
        }

        .profile-permissions {
            color: var(--success);
            font-size: 0.8em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* STATS GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-top: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0,0,0,0.12);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-info h3 {
            font-size: 0.85em;
            color: #718096;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 2.2em;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: white;
        }

        /* Stat card colors */
        .stat-card:nth-child(1) { border-color: var(--info); }
        .stat-card:nth-child(2) { border-color: var(--danger); }
        .stat-card:nth-child(3) { border-color: var(--success); }
        .stat-card:nth-child(4) { border-color: var(--warning); }
        .stat-card:nth-child(5) { border-color: var(--purple); }
        .stat-card:nth-child(6) { border-color: var(--secondary); }

        .stat-card:nth-child(1) .stat-icon { background: var(--info); }
        .stat-card:nth-child(2) .stat-icon { background: var(--danger); }
        .stat-card:nth-child(3) .stat-icon { background: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: var(--warning); }
        .stat-card:nth-child(5) .stat-icon { background: var(--purple); }
        .stat-card:nth-child(6) .stat-icon { background: var(--secondary); }

        /* FILTER SECTION */
        .filter-section {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }

        .section-title {
            font-size: 1.3em;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--info);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
        }

        .form-control, .filter-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s;
            background: #fafafa;
        }

        .form-control:focus, .filter-input:focus {
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

        /* Beautiful Calendar Styling */
        .date-input-wrapper {
            position: relative;
        }

        .date-input-wrapper i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--info);
            pointer-events: none;
            z-index: 2;
        }

        /* Flatpickr custom styling */
        .flatpickr-calendar {
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            border: 2px solid var(--info);
            margin-top: 5px;
        }

        .flatpickr-months {
            background: linear-gradient(135deg, var(--info), #2980B9);
            border-radius: 12px 12px 0 0;
            padding: 10px 0;
        }

        .flatpickr-month {
            color: white;
        }

        .flatpickr-current-month .flatpickr-monthDropdown-months {
            background: transparent;
            color: white;
            font-weight: 600;
        }

        .flatpickr-current-month input.cur-year {
            color: white;
            font-weight: 600;
        }

        .flatpickr-weekdays {
            background: rgba(52, 152, 219, 0.1);
        }

        .flatpickr-weekday {
            color: var(--primary);
            font-weight: 600;
        }

        .flatpickr-day.selected, 
        .flatpickr-day.selected:hover {
            background: var(--info);
            border-color: var(--info);
        }

        .flatpickr-day.today {
            border-color: var(--warning);
        }

        .flatpickr-day:hover {
            background: rgba(52, 152, 219, 0.2);
        }

        .flatpickr-prev-month, 
        .flatpickr-next-month {
            color: white;
            padding: 10px;
        }

        .flatpickr-prev-month:hover, 
        .flatpickr-next-month:hover {
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
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
            background: linear-gradient(135deg, var(--danger), #E67E22);
            color: white;
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.25);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(243, 156, 18, 0.35);
        }

        .btn-secondary {
            background: #f7fafc;
            color: var(--primary);
            border: 2px solid #e8e8e8;
        }

        .btn-secondary:hover {
            background: #edf2f7;
            transform: translateY(-2px);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        /* TABLE SECTION */
        .table-section {
            background: var(--card-bg);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            margin-top: 25px;
        }

        .table-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.05));
            border-bottom: 2px solid #e8e8e8;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            font-size: 1.2em;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-container {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        thead {
            background: #f7fafc;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 2px solid #e8e8e8;
            font-size: 0.9em;
        }

        td {
            padding: 12px 20px;
            border-bottom: 1px solid #e8e8e8;
            color: var(--primary);
        }

        tbody tr:hover {
            background: rgba(52, 152, 219, 0.05);
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-maksettava {
            background: rgba(231, 76, 60, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .status-maksettu {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .status-osittain {
            background: rgba(241, 196, 15, 0.1);
            color: var(--warning);
            border: 1px solid rgba(241, 196, 15, 0.2);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 0.9em;
            text-decoration: none;
        }

        .action-btn.pay {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .action-btn.pay:hover {
            background: linear-gradient(135deg, var(--success), #219653);
            color: white;
            transform: translateY(-2px);
        }

        .action-btn.delete {
            background: rgba(231, 76, 60, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .action-btn.delete:hover {
            background: linear-gradient(135deg, var(--secondary), #C0392B);
            color: white;
            transform: translateY(-2px);
        }

        .action-btn.edit {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
            border: 1px solid rgba(52, 152, 219, 0.2);
        }

        .action-btn.edit:hover {
            background: linear-gradient(135deg, var(--info), #2980B9);
            color: white;
            transform: translateY(-2px);
        }

        /* Amount text */
        .amount {
            font-weight: 600;
        }

        .amount-unpaid {
            color: var(--secondary);
        }

        .amount-paid {
            color: var(--success);
        }

        .amount-partial {
            color: var(--warning);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--info);
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
            color: var(--secondary);
        }

        /* Export Buttons */
        .export-buttons {
            display: flex;
            gap: 10px;
        }

        .export-btn {
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            text-decoration: none;
        }

        .export-pdf {
            color: var(--secondary);
            border-color: var(--secondary);
        }

        .export-excel {
            color: var(--success);
            border-color: var(--success);
        }

        .export-csv {
            color: var(--info);
            border-color: var(--info);
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            color: var(--info);
            opacity: 0.8;
        }

        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: var(--primary);
            font-weight: 600;
        }

        .empty-state p {
            max-width: 500px;
            margin: 0 auto 20px;
            font-size: 1.1em;
            line-height: 1.5;
            color: #666;
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
            border-color: var(--success);
            color: var(--success);
            background: rgba(39, 174, 96, 0.05);
        }

        .notification-error {
            border-color: var(--secondary);
            color: var(--secondary);
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

        /* Text utilities */
        .text-danger {
            color: var(--secondary);
            font-weight: 600;
        }

        .text-success {
            color: var(--success);
            font-weight: 600;
        }

        .text-warning {
            color: var(--warning);
            font-weight: 600;
        }

        .text-muted {
            color: #a0aec0;
        }

        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
            background: #edf2f7;
            color: #4a5568;
        }

        /* Mobile Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-header h2 span, .menu-item span, .menu-section {
                display: none;
            }
            .main-content {
                margin-left: 70px;
                padding: 15px;
            }
            .top-section {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .profile-section {
                width: 100%;
                justify-content: center;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            .filter-actions {
                flex-direction: column;
            }
            .filter-actions .btn {
                width: 100%;
            }
            .export-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
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
            <a href="laiteadmin_lainat.php" class="menu-item">
                <span>🔄 Laitelainat</span>
            </a>

            <div class="menu-section">📊 Raportit & Sakot</div>
            <a href="admin_raportit.php" class="menu-item">
                <span>📈 Kirjasto Raportit</span>
            </a>
            <a href="admin_sakot.php" class="menu-item active">
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

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- TOP SECTION WITH TITLE AND PROFILE -->
        <div class="top-section">
            <div class="page-header">
                <div class="title-icon">
                    <i class="fas fa-euro-sign"></i>
                </div>
                <h1 class="page-title">Sakkojen Hallinta</h1>
            </div>

            <div class="profile-section">
                <div class="profile-avatar">
                    <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile">
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo htmlspecialchars($custom_name); ?></div>
                    <div class="profile-email">
                        <i class="fas fa-envelope"></i>
                        <?php echo htmlspecialchars($custom_email); ?>
                    </div>
                    <div class="profile-role">
                        <i class="fas fa-user-shield"></i>
                        <?php echo $custom_role_display; ?>
                    </div>
                    <div class="profile-permissions">
                        <i class="fas fa-key"></i>
                        <?php echo $custom_permissions; ?>
                    </div>
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

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>SAKKOJA YHTEENSÄ</h3>
                        <div class="stat-number"><?php echo count($fines); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>MAKSAMATTA</h3>
                        <div class="stat-number"><?php echo count(array_filter($fines, function($f) { return $f['myohastyymismaksu'] > 0; })); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>MAKSETTU</h3>
                        <div class="stat-number"><?php echo $paid_fines_count; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>SAKKOJA YHT. (€)</h3>
                        <div class="stat-number"><?php echo number_format($total_fines, 2, ',', ' '); ?>€</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>MAKSAMATTA (€)</h3>
                        <div class="stat-number"><?php echo number_format($unpaid_fines, 2, ',', ' '); ?>€</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>MAKSETTU (€)</h3>
                        <div class="stat-number"><?php echo number_format($total_fines - $unpaid_fines, 2, ',', ' '); ?>€</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-double"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTER SECTION -->
        <div class="filter-section">
            <h2 class="section-title">
                <i class="fas fa-search"></i> Hae ja suodata sakkoja
            </h2>

            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label" for="status">
                            <i class="fas fa-filter"></i> Tila
                        </label>
                        <select class="form-control form-select" id="status" name="status">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Kaikki</option>
                            <option value="unpaid" <?php echo $filter_status === 'unpaid' ? 'selected' : ''; ?>>Maksamatta</option>
                            <option value="paid" <?php echo $filter_status === 'paid' ? 'selected' : ''; ?>>Maksettu</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="member">
                            <i class="fas fa-user"></i> Jäsen
                        </label>
                        <input type="text" class="form-control" id="member" name="member" 
                               placeholder="Jäsenen nimi tai email"
                               value="<?php echo htmlspecialchars($filter_member); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="device">
                            <i class="fas fa-laptop"></i> Laite
                        </label>
                        <input type="text" class="form-control" id="device" name="device" 
                               placeholder="Laitteen merkki, malli tai sarjanumero"
                               value="<?php echo htmlspecialchars($filter_device); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="start_date">
                            <i class="fas fa-calendar-alt"></i> Alkupäivä
                        </label>
                        <div class="date-input-wrapper">
                            <input type="text" class="form-control datepicker" id="start_date" name="start_date" 
                                   placeholder="Valitse alkupäivä"
                                   value="<?php echo $filter_start_date; ?>">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="end_date">
                            <i class="fas fa-calendar-alt"></i> Loppupäivä
                        </label>
                        <div class="date-input-wrapper">
                            <input type="text" class="form-control datepicker" id="end_date" name="end_date" 
                                   placeholder="Valitse loppupäivä"
                                   value="<?php echo $filter_end_date; ?>">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Suodata
                            </button>
                            <a href="admin_sakot.php" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Nollaa
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- TABLE SECTION -->
        <div class="table-section">
            <div class="table-header">
                <h3>
                    <i class="fas fa-euro-sign" style="color: var(--info);"></i>
                    Sakkolista (<?php echo count($fines); ?> kpl)
                </h3>
                <div class="export-buttons">
                    <a href="?export=pdf&<?php echo http_build_query($_GET); ?>" class="export-btn export-pdf" onclick="return confirm('Ladataan PDF...')">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="?export=excel&<?php echo http_build_query($_GET); ?>" class="export-btn export-excel" onclick="return confirm('Ladataan Excel...')">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="?export=csv&<?php echo http_build_query($_GET); ?>" class="export-btn export-csv" onclick="return confirm('Ladataan CSV...')">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Jäsen</th>
                            <th>Laite</th>
                            <th>Lainattu</th>
                            <th>Eräpäivä</th>
                            <th>Sakko (€)</th>
                            <th>Tila</th>
                            <th>Syy</th>
                            <th>Toiminnot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($fines)): ?>
                            <?php foreach ($fines as $fine): ?>
                                <?php
                                $is_paid = $fine['myohastyymismaksu'] == 0;
                                $status_class = $is_paid ? 'status-maksettu' : 'status-maksettava';
                                $status_text = $is_paid ? 'Maksettu' : 'Maksamatta';
                                $amount_class = $is_paid ? 'amount-paid' : 'amount-unpaid';
                                ?>
                                <tr>
                                    <td><strong>#<?php echo str_pad($fine['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($fine['sukunimi'] . ' ' . $fine['etunimi']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($fine['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($fine['merkki']): ?>
                                            <strong><?php echo htmlspecialchars($fine['merkki'] . ' ' . $fine['malli']); ?></strong>
                                            <br><small class="badge"><?php echo htmlspecialchars($fine['sarjanumero']); ?></small>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($fine['device_type'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($fine['lainaus_pvm'])); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($fine['erapaiva'])); ?></td>
                                    <td class="amount <?php echo $amount_class; ?>">
                                        <strong><?php echo number_format($fine['myohastyymismaksu'], 2, ',', ' '); ?>€</strong>
                                        <?php if ($fine['days_overdue'] > 0 && !$is_paid): ?>
                                            <br><small class="text-danger">(<?php echo $fine['days_overdue']; ?> pv myöhässä)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($fine['huomiot']): ?>
                                            <span title="<?php echo htmlspecialchars($fine['huomiot']); ?>">
                                                <?php echo htmlspecialchars(substr($fine['huomiot'], 0, 30)) . (strlen($fine['huomiot']) > 30 ? '...' : ''); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if (!$is_paid): ?>
                                                <a href="?action=mark_paid&id=<?php echo $fine['id']; ?>&<?php echo http_build_query($_GET); ?>" 
                                                   class="action-btn pay" title="Merkitse maksetuksi"
                                                   onclick="return confirm('Merkitäänkö sakko maksetuksi?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($rooli === 'admin'): ?>
                                                <a href="?action=delete_fine&id=<?php echo $fine['id']; ?>&<?php echo http_build_query($_GET); ?>" 
                                                   class="action-btn delete" title="Poista"
                                                   onclick="return confirm('Haluatko varmasti poistaa tämän sakon?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <i class="fas fa-euro-sign"></i>
                                    <h3>Ei sakkoja</h3>
                                    <p>Ei näytettäviä sakkoja valituilla suodattimilla.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add New Fine Modal -->
    <div id="addFineModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-plus-circle"></i> Lisää Uusi Sakko
                </div>
                <button class="close-modal" onclick="hideModal('add')">&times;</button>
            </div>

            <form method="POST" action="?action=add_fine">
                <div class="form-group">
                    <label class="form-label" for="member_id">
                        <i class="fas fa-user"></i> Jäsen *
                    </label>
                    <select class="form-control form-select" id="member_id" name="member_id" required>
                        <option value="">Valitse jäsen</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['sukunimi'] . ' ' . $member['etunimi'] . ' (' . $member['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="device_id">
                        <i class="fas fa-laptop"></i> Laite *
                    </label>
                    <select class="form-control form-select" id="device_id" name="device_id" required>
                        <option value="">Valitse laite</option>
                        <?php foreach ($devices as $device): ?>
                            <option value="<?php echo $device['id']; ?>">
                                <?php echo htmlspecialchars($device['merkki'] . ' ' . $device['malli'] . ' (' . $device['sarjanumero'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="amount">
                        <i class="fas fa-euro-sign"></i> Sakon määrä (€) *
                    </label>
                    <input type="number" class="form-control" id="amount" name="amount" 
                           step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="due_date">
                        <i class="fas fa-calendar-alt"></i> Eräpäivä *
                    </label>
                    <div class="date-input-wrapper">
                        <input type="text" class="form-control datepicker-modal" id="due_date" name="due_date" 
                               placeholder="Valitse eräpäivä" required>
                        <i class="fas fa-calendar"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reason">
                        <i class="fas fa-sticky-note"></i> Syy
                    </label>
                    <textarea class="form-control" id="reason" name="reason" rows="3" 
                              placeholder="Sakon syy..."></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px;">
                    <button type="button" class="btn btn-secondary" onclick="hideModal('add')">
                        <i class="fas fa-times"></i> Peruuta
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Tallenna
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize beautiful datepickers
        flatpickr(".datepicker", {
            locale: "fi",
            dateFormat: "Y-m-d",
            allowInput: true,
            altInput: true,
            altFormat: "d.m.Y",
            theme: "material_blue",
            showMonths: 1,
            disableMobile: true,
            nextArrow: '<i class="fas fa-chevron-right"></i>',
            prevArrow: '<i class="fas fa-chevron-left"></i>'
        });

        flatpickr(".datepicker-modal", {
            locale: "fi",
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d.m.Y",
            minDate: "today",
            theme: "material_blue"
        });

        // Modal functions
        function showModal(type) {
            if (type === 'add') {
                document.getElementById('addFineModal').style.display = 'block';
            }
        }

        function hideModal(type) {
            if (type === 'add') {
                document.getElementById('addFineModal').style.display = 'none';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addFineModal');
            if (event.target == addModal) {
                hideModal('add');
            }
        }

        // Auto-hide notifications after 5 seconds
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-15px)';
                notification.style.transition = 'all 0.3s ease';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>
<?php $conn->close(); ?>
