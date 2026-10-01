<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$current_page = basename($_SERVER["PHP_SELF"]);
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

// Jos käyttäjää ei löydy
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Tarkista että käyttäjä on user (tavallinen käyttäjä)
if ($user['rooli'] != 'user') {
    if ($user['rooli'] == 'admin') {
        header("Location: admin_profile.php");
    } elseif ($user['rooli'] == 'manager') {
        header("Location: manager_profile.php");
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
$stats['aktiiviset_lainat'] = $result2->fetch_assoc()['count'];
$stmt2->close();

// Käyttäjän lainat yhteensä
$stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM lainat WHERE jasen_id = ?");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$stats['lainat_yhteensa'] = $result2->fetch_assoc()['count'];
$stmt2->close();

// Käyttäjän odottavat varaukset
$stmt2 = $conn->prepare("SELECT COUNT(*) as count FROM varaukset WHERE jasen_id = ? AND tila = 'odottaa'");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$stats['varauksia'] = $result2->fetch_assoc()['count'];
$stmt2->close();

// Käyttäjän sakot
$stmt2 = $conn->prepare("SELECT COALESCE(SUM(sakko_maara - maksettu_maara), 0) as summa FROM sakot WHERE jasen_id = ? AND tila IN ('maksettava', 'osittain')");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$stats['sakkoja'] = $result2->fetch_assoc()['summa'];
$stmt2->close();

// Päivitä profiilikuva
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $target_dir = "uploads/profiles/";

    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
    $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;

    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($file_extension, $allowed_types)) {
        $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
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

// Päivitä perustiedot
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $etunimi = trim($_POST['etunimi']);
    $sukunimi = trim($_POST['sukunimi']);
    $email = trim($_POST['email']);
    $puhelin = trim($_POST['puhelin']);
    $osoite = trim($_POST['osoite']);

    // Tarkista että sähköposti on uniikki (paitsi tämä käyttäjä)
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

// Muodosta nimikirjaimet
$initials = strtoupper(substr($user['etunimi'] ?? '', 0, 1) . substr($user['sukunimi'] ?? '', 0, 1));

// Määritä liittymispäivä (käytä liittymispaiva tai luotu)
$join_date = !empty($user['liittymispaiva']) ? $user['liittymispaiva'] : $user['luotu'];
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oma profiili | <?php echo htmlspecialchars($user['etunimi']); ?> <?php echo htmlspecialchars($user['sukunimi']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            position: relative;
            background-color: #0a0c10;
        }

        /* Background Image with Overlay */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            filter: brightness(0.25) blur(4px);
            z-index: -1;
        }

        /* Dark Mode Colors */
        :root {
            --bg-primary: rgba(18, 22, 28, 0.85);
            --bg-secondary: rgba(26, 32, 39, 0.9);
            --bg-card: rgba(30, 36, 45, 0.85);
            --bg-sidebar: rgba(13, 17, 23, 0.95);
            --text-primary: #ffffff;
            --text-secondary: #b0b8c5;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
            --gradient-1: linear-gradient(135deg, #667eea, #764ba2);
            --gradient-2: linear-gradient(135deg, #f093fb, #f5576c);
            --gradient-3: linear-gradient(135deg, #4facfe, #00f2fe);
            --gradient-4: linear-gradient(135deg, #43e97b, #38f9d7);
            --gradient-5: linear-gradient(135deg, #fa709a, #fee140);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-hover: 0 15px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 20px rgba(102, 126, 234, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            width: 100%;
        }

        .top-bar {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-color);
        }

        .page-title h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }

        .page-title p {
            color: var(--text-muted);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .page-title p i {
            font-size: 0.5rem;
            color: #10b981;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .date-badge {
            background: rgba(255, 255, 255, 0.05);
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-color);
        }

        .date-badge i {
            color: #667eea;
        }

        .notification-icon {
            position: relative;
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }

        .notification-icon:hover {
            background: var(--gradient-1);
            color: white;
            transform: rotate(5deg);
        }

        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            border: 2px solid var(--bg-card);
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: var(--gradient-1);
            color: white;
            transform: translateX(-5px);
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 25px;
        }

        .profile-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
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
            border: 3px solid transparent;
            background: var(--gradient-1);
            padding: 3px;
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .initials-large {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--gradient-1);
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
            background: var(--gradient-1);
            border: 2px solid var(--bg-card);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: var(--shadow);
        }

        .edit-image-btn:hover {
            transform: scale(1.1);
            box-shadow: var(--glow);
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            text-align: center;
            margin-bottom: 5px;
        }

        .profile-role {
            display: inline-block;
            padding: 5px 15px;
            background: rgba(102, 126, 234, 0.15);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 30px;
            font-size: 0.85rem;
            color: #667eea;
            margin: 0 auto 20px;
            width: fit-content;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
            padding: 20px 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .profile-info-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .profile-info-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: #667eea;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 1.1rem;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .edit-card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .edit-card h2 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .edit-card h2 i {
            color: #667eea;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control[readonly] {
            background: rgba(255, 255, 255, 0.02);
            color: var(--text-muted);
            cursor: not-allowed;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
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
            background: var(--gradient-1);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(102, 126, 234, 0.5);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            width: 100%;
            margin-top: 15px;
        }

        .btn-danger:hover {
            background: #ef4444;
            color: white;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
            backdrop-filter: blur(10px);
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
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .modal-content {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            width: 90%;
            max-width: 400px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-hover);
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
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-content h3 i {
            color: #667eea;
        }

        .file-input {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            margin-bottom: 20px;
        }

        .file-input::-webkit-file-upload-button {
            background: var(--gradient-1);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-right: 10px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
        }

        .modal-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .modal-btn-primary {
            background: var(--gradient-1);
            color: white;
        }

        .modal-btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .modal-btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .modal-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .info-note {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 10px;
            padding: 10px 15px;
            margin-bottom: 20px;
            color: var(--text-secondary);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-note i {
            color: #667eea;
        }

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
            .profile-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h1>Oma profiili</h1>
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
                <a href="user_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Takaisin
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
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
                    <i class="fas fa-user"></i> Käyttäjä
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

