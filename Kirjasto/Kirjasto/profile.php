<?php
// Try to increase upload limits at runtime
@ini_set('upload_max_filesize', '50M');
@ini_set('post_max_size', '55M');
@ini_set('max_execution_time', '300');
@ini_set('memory_limit', '256M');
@ini_set('max_input_time', '300');

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'connection.php';

// Tarkista että käyttäjä on kirjautunut
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Hae käyttäjän tiedot
$stmt = $conn->prepare("
    SELECT
        j.id,
        j.etunimi,
        j.sukunimi,
        j.email,
        j.puhelin,
        j.osoite,
        j.jasentyyppi,
        j.jasennumero,
        j.liittymispaiva,
        j.tila,
        j.rooli,
        j.profile_image,
        j.luotu
    FROM jasenet j
    WHERE j.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Tarkista onko käyttäjä manager
if ($user['rooli'] != 'manager') {
    if ($user['rooli'] == 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit();
}

// Haetaan käyttäjän tilastot
$stats = [];

// Käyttäjän aktiiviset lainat
$stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM lainat WHERE jasen_id = ? AND tila = 'aktiivinen'");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$stats['aktiiviset_lainat'] = $result2->fetch_assoc()['count'] ?? 0;
$stmt2->close();

// Käyttäjän lainat yhteensä
$stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM lainat WHERE jasen_id = ?");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$stats['lainat_yhteensa'] = $result2->fetch_assoc()['count'] ?? 0;
$stmt2->close();

// Käyttäjän odottavat varaukset
$stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM varaukset WHERE jasen_id = ? AND tila = 'odottaa'");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$stats['varauksia'] = $result2->fetch_assoc()['count'] ?? 0;
$stmt2->close();

// Käyttäjän sakot
$stmt2 = $conn->prepare("SELECT COALESCE(SUM(sakko_maara - maksettu_maara), 0) as summa FROM sakot WHERE jasen_id = ? AND tila IN ('maksettava', 'osittain')");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$stats['sakkoja'] = $result2->fetch_assoc()['summa'] ?? 0;
$stmt2->close();

// Päivitä profiilikuva
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image'])) {
    if ($_FILES['profile_image']['error'] == 0) {
        $max_file_size = 20 * 1024 * 1024;
        if ($_FILES['profile_image']['size'] > $max_file_size) {
            $error_message = "Virhe: Tiedosto on liian suuri. Maksimikoko on 20MB.";
        } else {
            $target_dir = "uploads/profiles/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
            $new_filename = "manager_" . $user_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;

            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_extension, $allowed_types)) {
                $check = @getimagesize($_FILES["profile_image"]["tmp_name"]);
                if($check !== false) {
                    if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                        if (!empty($user['profile_image']) && file_exists($target_dir . $user['profile_image'])) {
                            unlink($target_dir . $user['profile_image']);
                        }

                        $update = $conn->prepare("UPDATE jasenet SET profile_image = ? WHERE id = ?");
                        $update->bind_param("si", $new_filename, $user_id);
                        $update->execute();
                        $update->close();

                        $user['profile_image'] = $new_filename;
                        $success_message = "Profiilikuva päivitetty onnistuneesti!";
                    } else {
                        $error_message = "Kuvan lataaminen epäonnistui.";
                    }
                } else {
                    $error_message = "Tiedosto ei ole kelvollinen kuva.";
                }
            } else {
                $error_message = "Vain JPG, JPEG, PNG ja GIF tiedostot ovat sallittuja.";
            }
        }
    }
}

// Päivitä perustiedot
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $etunimi = trim($_POST['etunimi']);
    $sukunimi = trim($_POST['sukunimi']);
    $email = trim($_POST['email']);
    $puhelin = trim($_POST['puhelin']);
    $osoite = trim($_POST['osoite']);

    $check_email = $conn->prepare("SELECT id FROM jasenet WHERE email = ? AND id != ?");
    $check_email->bind_param("si", $email, $user_id);
    $check_email->execute();
    $email_result = $check_email->get_result();

    if ($email_result->num_rows > 0) {
        $error_message = "Sähköpostiosoite on jo käytössä toisella käyttäjällä.";
    } else {
        $update = $conn->prepare("UPDATE jasenet SET etunimi = ?, sukunimi = ?, email = ?, puhelin = ?, osoite = ? WHERE id = ?");
        $update->bind_param("sssssi", $etunimi, $sukunimi, $email, $puhelin, $osoite, $user_id);

        if ($update->execute()) {
            $success_message = "Profiili päivitetty onnistuneesti!";
            $user['etunimi'] = $etunimi;
            $user['sukunimi'] = $sukunimi;
            $user['email'] = $email;
            $user['puhelin'] = $puhelin;
            $user['osoite'] = $osoite;
        } else {
            $error_message = "Profiilin päivitys epäonnistui.";
        }
        $update->close();
    }
    $check_email->close();
}

// Poista profiilikuva
if (isset($_GET['remove_image'])) {
    if (!empty($user['profile_image']) && file_exists("uploads/profiles/" . $user['profile_image'])) {
        unlink("uploads/profiles/" . $user['profile_image']);
        $update = $conn->prepare("UPDATE jasenet SET profile_image = NULL WHERE id = ?");
        $update->bind_param("i", $user_id);
        $update->execute();
        $update->close();
        $user['profile_image'] = null;
        $success_message = "Profiilikuva poistettu.";
    }
}

// Get unread messages count
$unread_messages_count = 0;
try {
    $unread_query = "SELECT COUNT(*) as count FROM viestit WHERE vastaanottaja_id = ? AND luettu = 0";
    $stmt = $conn->prepare($unread_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unread_result = $stmt->get_result();
    $unread_data = $unread_result->fetch_assoc();
    $unread_messages_count = $unread_data['count'] ?? 0;
} catch (Exception $e) {
    error_log("Unread messages error: " . $e->getMessage());
}

$initials = strtoupper(substr($user['etunimi'] ?? '', 0, 1) . substr($user['sukunimi'] ?? '', 0, 1));
$join_date = !empty($user['liittymispaiva']) ? $user['liittymispaiva'] : $user['luotu'];
$full_name = htmlspecialchars($user['etunimi'] . ' ' . $user['sukunimi']);
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Managerin profiili | <?php echo htmlspecialchars($user['etunimi']); ?> <?php echo htmlspecialchars($user['sukunimi']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

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
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    body {
        min-height: 100vh;
        position: relative;
        background: linear-gradient(rgba(26, 26, 46, 0.3), rgba(26, 26, 46, 0.3)),
                    url('https://images.unsplash.com/photo-1521587760476-6c12a4b040da?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        color: #fff;
        overflow-x: hidden;
    }

    /* Animated Background */
    .bg-animation {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        overflow: hidden;
    }

    .bg-animation span {
        position: absolute;
        display: block;
        width: 20px;
        height: 20px;
        background: rgba(255, 255, 255, 0.05);
        animation: animate 25s linear infinite;
        bottom: -150px;
        border-radius: 50%;
    }

    @keyframes animate {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 0.5;
        }
        100% {
            transform: translateY(-1000px) rotate(720deg);
            opacity: 0;
        }
    }

    .container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
        width: 100%;
        position: relative;
    }

    /* Top Bar - Glassmorphism */
    .top-bar {
        background: rgba(25, 25, 25, 0.5);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 30px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        padding: 20px 30px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-title h1 {
        font-size: 1.8rem;
        font-weight: 700;
        background: linear-gradient(135deg, #fff, var(--info));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 5px;
    }

    .page-title p {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .page-title p i {
        font-size: 0.5rem;
        color: var(--success);
    }

    .top-actions {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .date-badge {
        background: rgba(25, 25, 25, 0.5);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 8px 16px;
        border-radius: 50px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .date-badge i {
        color: var(--info);
    }

    .notification-icon {
        position: relative;
        width: 45px;
        height: 45px;
        background: rgba(25, 25, 25, 0.5);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: rgba(255, 255, 255, 0.8);
        cursor: pointer;
        transition: all 0.3s;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .notification-icon:hover {
        background: linear-gradient(135deg, var(--secondary), #c0392b);
        color: white;
        transform: rotate(5deg);
    }

    .badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: linear-gradient(135deg, var(--secondary), #c0392b);
        color: white;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        font-size: 0.65rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        border: 2px solid rgba(25, 25, 25, 0.8);
    }

    .back-btn {
        background: rgba(25, 25, 25, 0.5);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 50px;
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s;
    }

    .back-btn:hover {
        background: linear-gradient(135deg, var(--secondary), #c0392b);
        color: white;
        transform: translateX(-5px);
    }

    /* Profile Grid */
    .profile-grid {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 25px;
    }

    /* Profile Card - Glassmorphism */
    .profile-card {
        background: rgba(25, 25, 25, 0.5);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 30px;
        padding: 30px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        height: fit-content;
    }

    .profile-image-container {
        position: relative;
        width: 150px;
        height: 150px;
        margin: 0 auto 20px;
    }

    .profile-image {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .profile-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .initials-large {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, var(--info), #2980b9);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3em;
        font-weight: 700;
        color: white;
    }

    .edit-image-btn {
        position: absolute;
        bottom: 5px;
        right: 5px;
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--secondary), #c0392b);
        border: 2px solid rgba(25, 25, 25, 0.8);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 10px 20px -5px rgba(231, 76, 60, 0.3);
    }

    .edit-image-btn:hover {
        transform: scale(1.1);
    }

    .profile-name {
        font-size: 1.5rem;
        font-weight: 700;
        text-align: center;
        margin-bottom: 5px;
        background: linear-gradient(135deg, #fff, var(--info));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .profile-role {
        display: inline-block;
        padding: 5px 15px;
        background: linear-gradient(135deg, var(--secondary), #c0392b);
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
        color: white;
        margin: 0 auto 20px;
        width: fit-content;
        box-shadow: 0 10px 20px -5px rgba(231, 76, 60, 0.3);
    }

    .profile-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin: 20px 0;
        padding: 20px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--info);
    }

    .stat-label {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.7);
    }

    .profile-info-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px;
        background: rgba(25, 25, 25, 0.3);
        border-radius: 15px;
        margin-bottom: 10px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s;
    }

    .profile-info-item:hover {
        background: rgba(25, 25, 25, 0.5);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .info-icon {
        width: 40px;
        height: 40px;
        background: rgba(52, 152, 219, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--info);
        font-size: 1.1rem;
    }

    .info-content {
        flex: 1;
    }

    .info-label {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
        margin-bottom: 2px;
    }

    .info-value {
        font-weight: 600;
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.95rem;
    }

    /* Edit Card - Glassmorphism */
    .edit-card {
        background: rgba(25, 25, 25, 0.5);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 30px;
        padding: 30px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }

    .edit-card h2 {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
    }

    .edit-card h2 i {
        color: var(--info);
    }

    .info-note {
        background: rgba(52, 152, 219, 0.1);
        border: 1px solid rgba(52, 152, 219, 0.3);
        border-radius: 15px;
        padding: 12px 15px;
        margin-bottom: 25px;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-note i {
        color: var(--info);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: rgba(255, 255, 255, 0.8);
        font-weight: 500;
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 12px 16px;
        background: rgba(25, 25, 25, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        color: white;
        font-size: 0.95rem;
        transition: all 0.3s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--info);
        background: rgba(25, 25, 25, 0.5);
    }

    .form-control[readonly] {
        background: rgba(25, 25, 25, 0.2);
        color: rgba(255, 255, 255, 0.5);
        cursor: not-allowed;
    }

    textarea.form-control {
        min-height: 100px;
        resize: vertical;
    }

    /* Buttons */
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 0.95rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--info), #2980b9);
        color: white;
        width: 100%;
        box-shadow: 0 10px 20px -5px rgba(52, 152, 219, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 30px -5px rgba(52, 152, 219, 0.5);
    }

    .btn-danger {
        background: rgba(231, 76, 60, 0.2);
        color: var(--secondary);
        border: 1px solid rgba(231, 76, 60, 0.3);
        width: 100%;
        margin-top: 15px;
    }

    .btn-danger:hover {
        background: linear-gradient(135deg, var(--secondary), #c0392b);
        color: white;
    }

    /* Alerts */
    .alert {
        padding: 15px 20px;
        border-radius: 15px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease;
        background: rgba(25, 25, 25, 0.5);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-success {
        color: var(--success);
        border-color: rgba(39, 174, 96, 0.3);
    }

    .alert-success i {
        color: var(--success);
    }

    .alert-error {
        color: var(--secondary);
        border-color: rgba(231, 76, 60, 0.3);
    }

    .alert-error i {
        color: var(--secondary);
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }

    .modal-content {
        background: rgba(25, 25, 25, 0.8);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 30px;
        padding: 30px;
        width: 90%;
        max-width: 400px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        animation: slideUp 0.4s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-content h3 {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
    }

    .modal-content h3 i {
        color: var(--info);
    }

    .file-input {
        width: 100%;
        padding: 10px;
        background: rgba(25, 25, 25, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        color: white;
        margin-bottom: 20px;
    }

    .file-input::-webkit-file-upload-button {
        background: linear-gradient(135deg, var(--info), #2980b9);
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        margin-right: 10px;
        font-weight: 500;
    }

    .modal-actions {
        display: flex;
        gap: 10px;
    }

    .modal-btn {
        flex: 1;
        padding: 12px;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }

    .modal-btn-primary {
        background: linear-gradient(135deg, var(--info), #2980b9);
        color: white;
    }

    .modal-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -5px rgba(52, 152, 219, 0.3);
    }

    .modal-btn-secondary {
        background: rgba(25, 25, 25, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.8);
    }

    .modal-btn-secondary:hover {
        background: rgba(25, 25, 25, 0.5);
    }

    /* Responsive */
    @media (max-width: 992px) {
        .profile-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .top-bar {
            flex-direction: column;
            gap: 15px;
            text-align: center;
        }
        
        .top-actions {
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .profile-card, .edit-card {
            padding: 20px;
        }
    }

    /* Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: rgba(25, 25, 25, 0.3);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--info), #2980b9);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, var(--secondary), #c0392b);
    }
</style>
</head>
<body>
    <div class="container">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Managerin profiili</h1>
                <p><i class="fas fa-circle"></i> Hallitse profiilitietojasi</p>
            </div>
            <div class="top-actions">
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo date('j. F Y'); ?>
                </div>
                <div class="notification-icon">
                    <i class="far fa-bell"></i>
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="badge"><?php echo $unread_messages_count; ?></span>
                    <?php endif; ?>
                </div>
                <a href="manager_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Takaisin
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Profile Grid -->
        <div class="profile-grid">
            <!-- Left Column - Profile Card -->
            <div class="profile-card">
                <div class="profile-image-container">
                    <div class="profile-image">
                        <?php if (!empty($user['profile_image']) && file_exists("uploads/profiles/" . $user['profile_image'])): ?>
                            <img src="uploads/profiles/<?php echo $user['profile_image']; ?>" alt="Profiilikuva">
                        <?php else: ?>
                            <div class="initials-large"><?php echo $initials; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="edit-image-btn" onclick="openImageModal()">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>

                <div class="profile-name">
                    <?php echo htmlspecialchars($user['etunimi'] . ' ' . $user['sukunimi']); ?>
                </div>
                <div class="profile-role">
                    <i class="fas fa-user-tie"></i> Manager
                </div>

                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['aktiiviset_lainat']; ?></div>
                        <div class="stat-label">Aktiivisia</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['lainat_yhteensa']; ?></div>
                        <div class="stat-label">Lainoja</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($stats['sakkoja'], 2, ',', ' '); ?> €</div>
                        <div class="stat-label">Sakot</div>
                    </div>
                </div>

                <div class="profile-info-item">
                    <div class="info-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Jäsennumero</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['jasennumero'] ?? 'Ei asetettu'); ?></div>
                    </div>
                </div>

                <div class="profile-info-item">
                    <div class="info-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Liittymispäivä</div>
                        <div class="info-value"><?php echo date('d.m.Y', strtotime($join_date)); ?></div>
                    </div>
                </div>

                <div class="profile-info-item">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Sähköposti</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                </div>

                <div class="profile-info-item">
                    <div class="info-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Puhelin</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['puhelin'] ?? 'Ei asetettu'); ?></div>
                    </div>
                </div>

                <div class="profile-info-item">
                    <div class="info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="info-content">
                        <div class="info-label">Osoite</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['osoite'] ?? 'Ei asetettu'); ?></div>
                    </div>
                </div>

                <?php if (!empty($user['profile_image'])): ?>
                    <a href="?remove_image=1" class="btn btn-danger" onclick="return confirm('Haluatko varmasti poistaa profiilikuvan?')">
                        <i class="fas fa-trash"></i> Poista profiilikuva
                    </a>
                <?php endif; ?>
            </div>

            <!-- Right Column - Edit Form -->
            <div class="edit-card">
                <h2>
                    <i class="fas fa-edit"></i>
                    Muokkaa profiilia
                </h2>

                <div class="info-note">
                    <i class="fas fa-info-circle"></i>
                    Jäsennumero ja liittymispäivä ovat järjestelmän asettamia eikä niitä voi muokata.
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Etunimi</label>
                        <input type="text" name="etunimi" class="form-control" value="<?php echo htmlspecialchars($user['etunimi']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Sukunimi</label>
                        <input type="text" name="sukunimi" class="form-control" value="<?php echo htmlspecialchars($user['sukunimi']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Sähköposti</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Puhelin</label>
                        <input type="tel" name="puhelin" class="form-control" value="<?php echo htmlspecialchars($user['puhelin'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Osoite</label>
                        <textarea name="osoite" class="form-control"><?php echo htmlspecialchars($user['osoite'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Jäsennumero (vain luku)</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['jasennumero'] ?? 'Ei asetettu'); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Liittymispäivä (vain luku)</label>
                        <input type="text" class="form-control" value="<?php echo date('d.m.Y', strtotime($join_date)); ?>" readonly>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Tallenna muutokset
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Profile Image Upload Modal -->
    <div class="modal" id="imageModal">
        <div class="modal-content">
            <h3><i class="fas fa-camera"></i> Vaihda profiilikuva</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="profile_image" class="file-input" accept="image/*" required>
                <div class="modal-actions">
                    <button type="submit" class="modal-btn modal-btn-primary">
                        <i class="fas fa-upload"></i> Lataa
                    </button>
                    <button type="button" class="modal-btn modal-btn-secondary" onclick="closeImageModal()">
                        <i class="fas fa-times"></i> Peruuta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openImageModal() {
            document.getElementById('imageModal').style.display = 'flex';
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                closeImageModal();
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.getElementsByClassName('alert');
            for (var i = 0; i < alerts.length; i++) {
                if (alerts[i]) {
                    alerts[i].style.transition = 'opacity 0.5s';
                    alerts[i].style.opacity = '0';
                    setTimeout(function() {
                        if (alerts[i] && alerts[i].parentNode) {
                            alerts[i].parentNode.removeChild(alerts[i]);
                        }
                    }, 500);
                }
            }
        }, 5000);
    </script>
</body>
</html>
<?php $conn->close(); ?>
