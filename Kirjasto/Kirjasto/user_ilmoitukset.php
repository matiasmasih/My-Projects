<?php
// ============================================
// FILE: user_ilmoitukset.php
// PURPOSE: User notifications and messages page with reply functionality
// ============================================

session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info
$user_query = "SELECT etunimi, sukunimi, profile_image, jasennumero, rooli FROM jasenet WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user['rooli'] == 'admin') {
    header("Location: admin_dashboard.php");
    exit();
} elseif ($user['rooli'] == 'manager') {
    header("Location: manager_dashboard.php");
    exit();
}

// Set current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get user initials
$initials = strtoupper(substr($user['etunimi'] ?? '', 0, 1) . substr($user['sukunimi'] ?? '', 0, 1));

// Membership number
$membership_number = isset($user['jasennumero']) && !empty($user['jasennumero'])
    ? $user['jasennumero']
    : 'JASEN-' . str_pad($user_id, 6, '0', STR_PAD_LEFT);

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

// ============================================
// HANDLE REPLY TO MESSAGE
// ============================================
$reply_success = '';
$reply_error = '';
$reply_message_id = null;
$reply_to_name = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_message'])) {
    $original_message_id = (int)$_POST['message_id'];
    $reply_text = trim($_POST['reply_text']);
    $to_user_id = (int)$_POST['to_user_id'];
    
    if (empty($reply_text)) {
        $reply_error = "Viesti ei voi olla tyhjä.";
    } else {
        // Insert reply
        $reply_sql = "INSERT INTO viestit (lahettaja_id, vastaanottaja_id, viesti, luettu, luontiaika) 
                      VALUES (?, ?, ?, 0, NOW())";
        $reply_stmt = $conn->prepare($reply_sql);
        $reply_stmt->bind_param("iis", $user_id, $to_user_id, $reply_text);
        
        if ($reply_stmt->execute()) {
            $reply_success = "Viestisi on lähetetty onnistuneesti!";
            $reply_message_id = $original_message_id;
        } else {
            $reply_error = "Viestin lähetys epäonnistui. Yritä uudelleen.";
        }
    }
}

// Get user's messages
$messages_query = "SELECT v.*,
                          l.etunimi as lahettaja_etunimi,
                          l.sukunimi as lahettaja_sukunimi,
                          l.rooli as lahettaja_rooli
                   FROM viestit v
                   LEFT JOIN jasenet l ON v.lahettaja_id = l.id
                   WHERE v.vastaanottaja_id = ?
                   ORDER BY v.luontiaika DESC";
$stmt = $conn->prepare($messages_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$messages_result = $stmt->get_result();

// Get sent messages (user's own replies)
$sent_query = "SELECT v.*,
                      l.etunimi as vastaanottaja_etunimi,
                      l.sukunimi as vastaanottaja_sukunimi,
                      l.rooli as vastaanottaja_rooli
               FROM viestit v
               LEFT JOIN jasenet l ON v.vastaanottaja_id = l.id
               WHERE v.lahettaja_id = ?
               ORDER BY v.luontiaika DESC
               LIMIT 20";
$stmt = $conn->prepare($sent_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sent_result = $stmt->get_result();

// Get system notifications
$notifications = [];

// Check overdue loans
$overdue_query = "SELECT COUNT(*) as count FROM lainat WHERE jasen_id = ? AND tila = 'myohassa'";
$stmt = $conn->prepare($overdue_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$overdue_count = $stmt->get_result()->fetch_assoc()['count'];

if ($overdue_count > 0) {
    $notifications[] = [
        'type' => 'warning',
        'icon' => 'fa-exclamation-triangle',
        'title' => 'Myöhässä olevia lainoja',
        'message' => "Sinulla on {$overdue_count} myöhässä olevaa lainaa. Palauta kirjat välttääksesi sakot.",
        'date' => date('Y-m-d H:i:s'),
        'link' => 'user_lainahistoria.php'
    ];
}

// Check reservations ready
$ready_query = "SELECT v.*, k.nimi
                FROM varaukset v
                JOIN kirjat k ON v.kirja_id = k.id
                WHERE v.jasen_id = ? AND v.tila = 'odottaa'
                AND (SELECT COUNT(*) FROM Kirjakopiot kp WHERE kp.kirja_id = k.id AND kp.tila = 'saatavilla') > 0";
$stmt = $conn->prepare($ready_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ready_reservations = $stmt->get_result();

while ($reservation = $ready_reservations->fetch_assoc()) {
    $notifications[] = [
        'type' => 'success',
        'icon' => 'fa-check-circle',
        'title' => 'Varaus valmis noudettavaksi',
        'message' => "Kirja '{$reservation['nimi']}' on nyt saatavilla. Voit noutaa sen kirjastosta.",
        'date' => date('Y-m-d H:i:s'),
        'link' => 'user_oma_varaukset.php'
    ];
}

// Check due dates (3 days before)
$due_query = "SELECT l.*, k.nimi
              FROM lainat l
              JOIN kirjat k ON l.kirja_id = k.id
              WHERE l.jasen_id = ? AND l.tila = 'aktiivinen'
              AND l.erapaiva BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)";
$stmt = $conn->prepare($due_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$due_loans = $stmt->get_result();

while ($loan = $due_loans->fetch_assoc()) {
    $days_left = (strtotime($loan['erapaiva']) - time()) / (60 * 60 * 24);
    $notifications[] = [
        'type' => 'info',
        'icon' => 'fa-clock',
        'title' => 'Laina erääntyy pian',
        'message' => "Kirja '{$loan['nimi']}' erääntyy " . date('d.m.Y', strtotime($loan['erapaiva'])) . " (" . round($days_left) . " päivän päästä).",
        'date' => date('Y-m-d H:i:s'),
        'link' => 'user_lainahistoria.php'
    ];
}

// Mark messages as read when viewed
$mark_read_query = "UPDATE viestit SET luettu = 1 WHERE vastaanottaja_id = ? AND luettu = 0";
$stmt = $conn->prepare($mark_read_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$profile_image = $user['profile_image'] ?? null;
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ilmoitukset | <?php echo htmlspecialchars($user['etunimi']); ?> <?php echo htmlspecialchars($user['sukunimi']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           RESET STYLES
           ============================================ */
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

        /* ============================================
           CSS VARIABLES
           ============================================ */
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
            --gradient-success: linear-gradient(135deg, #10b981, #34d399);
            --gradient-warning: linear-gradient(135deg, #f59e0b, #fbbf24);
            --gradient-danger: linear-gradient(135deg, #ef4444, #f87171);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-hover: 0 15px 40px rgba(0, 0, 0, 0.6);
            --glow: 0 0 20px rgba(102, 126, 234, 0.3);
        }

        /* ============================================
           SIDEBAR STYLES
           ============================================ */
        .sidebar {
            width: 280px;
            background: var(--bg-sidebar);
            backdrop-filter: blur(10px);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: var(--shadow);
            overflow-y: auto;
            z-index: 1000;
            border-right: 1px solid var(--border-color);
        }

        .sidebar-header {
            padding: 30px 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: var(--gradient-1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            box-shadow: var(--glow);
        }

        .logo-text h2 {
            font-size: 1.4rem;
            font-weight: 700;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2px;
        }

        .logo-text p {
            font-size: 0.7rem;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .user-profile-mini {
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .user-profile-mini:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(5px);
        }

        .avatar-mini {
            width: 55px;
            height: 55px;
            border-radius: 14px;
            overflow: hidden;
            background: var(--gradient-1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.3rem;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .avatar-mini img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info-mini h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .user-info-mini p {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .user-info-mini p i {
            font-size: 0.7rem;
            color: #10b981;
        }

        .sidebar-menu {
            padding: 20px 16px;
        }

        .menu-section {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 20px 16px 8px;
            letter-spacing: 0.5px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            margin: 4px 0;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .menu-item i {
            width: 22px;
            font-size: 1.1rem;
            color: var(--text-muted);
            transition: all 0.3s;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            transform: translateX(5px);
        }

        .menu-item:hover i {
            color: #667eea;
        }

        .menu-item.active {
            background: var(--gradient-1);
            color: white;
            box-shadow: 0 10px 20px -5px rgba(102, 126, 234, 0.3);
        }

        .menu-item.active i {
            color: white;
        }

        .logout-item {
            margin-top: 30px;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .logout-item i {
            color: #ef4444;
        }

        .logout-item:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .logout-item:hover i {
            color: #ef4444;
        }

        /* ============================================
           MAIN CONTENT STYLES
           ============================================ */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
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

        /* ============================================
           NOTIFICATIONS STYLES
           ============================================ */
        .notifications-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .notification-section {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 25px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .section-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 i {
            color: #667eea;
        }

        .section-count {
            background: rgba(102, 126, 234, 0.2);
            color: #a78bfa;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid rgba(102, 126, 234, 0.3);
        }

        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 18px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }

        .notification-item:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: #667eea;
            transform: translateX(5px);
        }

        .notification-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .icon-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .icon-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .icon-info {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }

        .icon-message {
            background: rgba(102, 126, 234, 0.15);
            color: #667eea;
        }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 8px;
        }

        .notification-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .notification-time {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .notification-message {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .notification-sender {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-left: 6px;
        }

        .role-admin {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .role-manager {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .unread-badge {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 30px;
            font-size: 0.65rem;
            margin-left: 8px;
        }

        .reply-btn {
            margin-top: 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(102, 126, 234, 0.15);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 30px;
            font-size: 0.75rem;
            color: #667eea;
            cursor: pointer;
            transition: all 0.3s;
        }

        .reply-btn:hover {
            background: var(--gradient-1);
            color: white;
            border-color: transparent;
        }

        .no-notifications {
            text-align: center;
            padding: 50px;
            color: var(--text-muted);
        }

        .no-notifications i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #667eea;
        }

        /* ============================================
           MODAL STYLES
           ============================================ */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            width: 90%;
            max-width: 550px;
            box-shadow: var(--shadow-hover);
            animation: slideUp 0.4s ease;
            border: 1px solid var(--border-color);
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

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: #667eea;
        }

        .close-modal {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            width: 36px;
            height: 36px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal:hover {
            background: #ef4444;
            color: white;
            transform: rotate(90deg);
        }

        .modal-body {
            margin-bottom: 25px;
        }

        .message-preview {
            background: rgba(255, 255, 255, 0.03);
            padding: 15px;
            border-radius: 12px;
            border-left: 3px solid #667eea;
            margin-bottom: 20px;
        }

        .message-preview p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.95rem;
            resize: vertical;
            min-height: 120px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-send {
            padding: 10px 24px;
            background: var(--gradient-1);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-cancel {
            padding: 10px 24px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-color: #ef4444;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .notification-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .notification-item {
                padding: 15px;
            }
            .notification-icon-box {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            .modal-content {
                padding: 20px;
                margin: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- ========== SIDEBAR START ========== -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="logo-text">
                    <h2>Kirjasto</h2>
                    <p>Lukemisen iloa</p>
                </div>
            </div>
        </div>

        <a href="user_profile.php" class="user-profile-mini">
            <div class="avatar-mini">
                <?php if (!empty($profile_image) && file_exists("uploads/profiles/" . $profile_image)): ?>
                    <img src="uploads/profiles/<?php echo htmlspecialchars($profile_image); ?>" alt="Profiilikuva">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="user-info-mini">
                <h4><?php echo htmlspecialchars($user['etunimi'] . ' ' . $user['sukunimi']); ?></h4>
                <p><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($membership_number); ?></p>
            </div>
        </a>

        <div class="sidebar-menu">
            <div class="menu-section">📊 Päävalikko</div>
            <a href="user_dashboard.php" class="menu-item <?php echo $current_page == 'user_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>

            <div class="menu-section">📚 Kirjat</div>
            <a href="user_selaa_kirjoja.php" class="menu-item <?php echo $current_page == 'user_selaa_kirjoja.php' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> <span>Selaa kirjoja</span>
            </a>
            <a href="user_lainaa_kirja.php" class="menu-item <?php echo $current_page == 'user_lainaa_kirja.php' ? 'active' : ''; ?>">
                <i class="fas fa-hand-holding-heart"></i> <span>Lainaa kirja</span>
            </a>
            <a href="user_lainahistoria.php" class="menu-item <?php echo $current_page == 'user_lainahistoria.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> <span>Lainahistoria</span>
            </a>
            <a href="user_oma_varaukset.php" class="menu-item <?php echo $current_page == 'user_oma_varaukset.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> <span>Omat varaukset</span>
            </a>
            <a href="user_suosikit.php" class="menu-item <?php echo $current_page == 'user_suosikit.php' ? 'active' : ''; ?>">
                <i class="fas fa-heart"></i> <span>Suosikit</span>
            </a>

            <div class="menu-section">💻 Laitteet</div>
            <a href="user_selaa_laitteita.php" class="menu-item <?php echo $current_page == 'user_selaa_laitteita.php' ? 'active' : ''; ?>">
                <i class="fas fa-laptop"></i> <span>Selaa laitteita</span>
            </a>
            <a href="user_laitelainat.php" class="menu-item <?php echo $current_page == 'user_laitelainat.php' ? 'active' : ''; ?>">
                <i class="fas fa-microchip"></i> <span>Laitelainat</span>
            </a>

            <div class="menu-section">💰 Talous</div>
            <a href="user_sakot.php" class="menu-item <?php echo $current_page == 'user_sakot.php' ? 'active' : ''; ?>">
                <i class="fas fa-euro-sign"></i> <span>Sakot</span>
            </a>
            <a href="user_kuitit.php" class="menu-item <?php echo $current_page == 'user_kuitit.php' ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> <span>Kuitit</span>
            </a>

            <div class="menu-section">👤 Oma tili</div>
            <a href="user_profile.php" class="menu-item <?php echo $current_page == 'user_profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i> <span>Profiili</span>
            </a>
            <a href="user_yhteystiedot.php" class="menu-item <?php echo $current_page == 'user_yhteystiedot.php' ? 'active' : ''; ?>">
                <i class="fas fa-address-card"></i> <span>Yhteystiedot</span>
            </a>
            <a href="salasana.php" class="menu-item <?php echo $current_page == 'salasana.php' ? 'active' : ''; ?>">
                <i class="fas fa-key"></i> <span>Vaihda salasana</span>
            </a>
            <a href="user_kayttoehdot.php" class="menu-item <?php echo $current_page == 'user_kayttoehdot.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-contract"></i> <span>Käyttöehdot</span>
            </a>
            <a href="user_ilmoitukset.php" class="menu-item <?php echo $current_page == 'user_ilmoitukset.php' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i> <span>Ilmoitukset</span>
                <?php if ($unread_messages_count > 0): ?>
                    <span class="badge"><?php echo $unread_messages_count; ?></span>
                <?php endif; ?>
            </a>

            <div class="menu-section"></div>
            <a href="logout.php" class="menu-item logout-item">
                <i class="fas fa-sign-out-alt"></i> <span>Kirjaudu ulos</span>
            </a>
        </div>
    </div>
    <!-- ========== SIDEBAR END ========== -->

    <!-- ========== MAIN CONTENT START ========== -->
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h1>Ilmoitukset</h1>
                <p><i class="fas fa-circle"></i> Tarkastele ilmoituksia ja viestejä</p>
            </div>
            <div class="top-actions">
                <div class="date-badge">
                    <i class="far fa-calendar-alt"></i> <?php echo date('j. F Y'); ?>
                </div>
                <div class="notification-icon">
                    <i class="far fa-bell"></i>
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="badge"><?php echo $unread_messages_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($reply_success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $reply_success; ?>
            </div>
        <?php endif; ?>

        <?php if ($reply_error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $reply_error; ?>
            </div>
        <?php endif; ?>

        <div class="notifications-container">
            <!-- System Notifications -->
            <?php if (!empty($notifications)): ?>
            <div class="notification-section">
                <div class="section-header">
                    <h2><i class="fas fa-info-circle"></i> Järjestelmäilmoitukset</h2>
                    <span class="section-count"><?php echo count($notifications); ?> uutta</span>
                </div>
                <div class="notification-list">
                    <?php foreach ($notifications as $notif): ?>
                        <a href="<?php echo $notif['link']; ?>" class="notification-item">
                            <div class="notification-icon-box icon-<?php echo $notif['type']; ?>">
                                <i class="fas <?php echo $notif['icon']; ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-header">
                                    <span class="notification-title"><?php echo $notif['title']; ?></span>
                                    <span class="notification-time"><?php echo date('d.m.Y H:i', strtotime($notif['date'])); ?></span>
                                </div>
                                <div class="notification-message">
                                    <?php echo $notif['message']; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Received Messages -->
            <div class="notification-section">
                <div class="section-header">
                    <h2><i class="fas fa-envelope"></i> Viestit (Saapuneet)</h2>
                    <?php if ($messages_result && $messages_result->num_rows > 0): ?>
                        <span class="section-count"><?php echo $messages_result->num_rows; ?> viestiä</span>
                    <?php endif; ?>
                </div>

                <?php if ($messages_result && $messages_result->num_rows > 0): ?>
                    <div class="notification-list">
                        <?php while ($message = $messages_result->fetch_assoc()):
                            $is_unread = $message['luettu'] == 0;
                        ?>
                            <div class="notification-item" data-message-id="<?php echo $message['id']; ?>" 
                                 data-sender-name="<?php echo htmlspecialchars($message['lahettaja_etunimi'] . ' ' . $message['lahettaja_sukunimi']); ?>"
                                 data-sender-id="<?php echo $message['lahettaja_id']; ?>"
                                 data-message-content="<?php echo htmlspecialchars($message['viesti']); ?>"
                                 style="<?php echo $is_unread ? 'border-left: 3px solid #667eea;' : ''; ?>">
                                <div class="notification-icon-box icon-message">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-header">
                                        <span class="notification-title">
                                            <?php echo htmlspecialchars($message['lahettaja_etunimi'] . ' ' . $message['lahettaja_sukunimi']); ?>
                                            <?php if ($message['lahettaja_rooli']): ?>
                                                <span class="role-badge role-<?php echo $message['lahettaja_rooli']; ?>">
                                                    <?php echo $message['lahettaja_rooli'] == 'admin' ? 'Admin' : 'Manager'; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($is_unread): ?>
                                                <span class="unread-badge">Uusi</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="notification-time">
                                            <?php echo date('d.m.Y H:i', strtotime($message['luontiaika'])); ?>
                                        </span>
                                    </div>
                                    <div class="notification-message">
                                        <?php echo nl2br(htmlspecialchars(substr($message['viesti'], 0, 150))); ?>
                                        <?php if (strlen($message['viesti']) > 150): ?>...<?php endif; ?>
                                    </div>
                                    <div class="notification-sender">
                                        <i class="fas fa-user"></i>
                                        Lähettäjä: <?php echo htmlspecialchars($message['lahettaja_etunimi'] . ' ' . $message['lahettaja_sukunimi']); ?>
                                    </div>
                                    <button class="reply-btn" onclick="openReplyModal(<?php echo $message['id']; ?>, '<?php echo htmlspecialchars($message['lahettaja_etunimi'] . ' ' . $message['lahettaja_sukunimi']); ?>', <?php echo $message['lahettaja_id']; ?>, '<?php echo htmlspecialchars(addslashes($message['viesti'])); ?>')">
                                        <i class="fas fa-reply"></i> Vastaa
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-notifications">
                        <i class="fas fa-inbox"></i>
                        <p>Ei viestejä</p>
                        <small>Sinulla ei ole vielä viestejä</small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sent Messages (User's Replies) -->
            <?php if ($sent_result && $sent_result->num_rows > 0): ?>
            <div class="notification-section">
                <div class="section-header">
                    <h2><i class="fas fa-paper-plane"></i> Lähetetyt viestit</h2>
                    <span class="section-count"><?php echo $sent_result->num_rows; ?> viestiä</span>
                </div>
                <div class="notification-list">
                    <?php while ($sent = $sent_result->fetch_assoc()): ?>
                        <div class="notification-item">
                            <div class="notification-icon-box icon-message">
                                <i class="fas fa-reply-all"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-header">
                                    <span class="notification-title">
                                        Vastaus: <?php echo htmlspecialchars($sent['vastaanottaja_etunimi'] . ' ' . $sent['vastaanottaja_sukunimi']); ?>
                                        <?php if ($sent['vastaanottaja_rooli']): ?>
                                            <span class="role-badge role-<?php echo $sent['vastaanottaja_rooli']; ?>">
                                                <?php echo $sent['vastaanottaja_rooli'] == 'admin' ? 'Admin' : 'Manager'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="notification-time">
                                        <?php echo date('d.m.Y H:i', strtotime($sent['luontiaika'])); ?>
                                    </span>
                                </div>
                                <div class="notification-message">
                                    <?php echo nl2br(htmlspecialchars(substr($sent['viesti'], 0, 150))); ?>
                                    <?php if (strlen($sent['viesti']) > 150): ?>...<?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- ========== MAIN CONTENT END ========== -->

    <!-- ========== REPLY MODAL ========== -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-reply"></i> Vastaa viestiin</h3>
                <button class="close-modal" onclick="closeReplyModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="message_id" id="reply_message_id">
                <input type="hidden" name="to_user_id" id="reply_to_user_id">
                <div class="modal-body">
                    <div class="message-preview">
                        <strong style="color: #667eea;">Alkuperäinen viesti:</strong>
                        <p id="original_message_preview"></p>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-pen"></i> Vastaus:</label>
                        <textarea name="reply_text" id="reply_text" placeholder="Kirjoita vastauksesi tähän..." required></textarea>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeReplyModal()">Peruuta</button>
                    <button type="submit" name="reply_message" class="btn-send">
                        <i class="fas fa-paper-plane"></i> Lähetä vastaus
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ============================================
        // USER_ILMOITUKSET.PHP - JAVASCRIPT
        // ============================================

        function openReplyModal(messageId, senderName, senderId, messageContent) {
            document.getElementById('reply_message_id').value = messageId;
            document.getElementById('reply_to_user_id').value = senderId;
            document.getElementById('original_message_preview').innerHTML = '<strong>' + senderName + ':</strong> ' + messageContent;
            document.getElementById('reply_text').value = '';
            document.getElementById('replyModal').style.display = 'flex';
        }

        function closeReplyModal() {
            document.getElementById('replyModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('replyModal');
            if (event.target == modal) {
                closeReplyModal();
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    if (alert && alert.parentNode) alert.remove();
                }, 500);
            });
        }, 5000);

        // Fade in animations
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.notification-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    section.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 150);
            });
        });
    </script>

</body>
</html>
