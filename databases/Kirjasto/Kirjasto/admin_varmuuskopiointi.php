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
$custom_permissions = $rooli === 'admin' ? 'Täydet järjestelmäoikeudet' : 'Varmuuskopiointi ja palautus';

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

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';
$message_type = '';

// Backup directory - use relative path
$backup_dir = 'backups/';
if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0755, true)) {
        $message = "Virhe: Ei voitu luoda backups-hakemistoa!";
        $message_type = "error";
    }
}

// Handle CREATE BACKUP
if ($action === 'create_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backup_path = $backup_dir . $backup_name;

        // Get all tables
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        if ($result) {
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }

            $output = "-- Kirjastojärjestelmän varmuuskopio\n";
            $output .= "-- Luotu: " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- Tekijä: " . $kayttajan_nimi . "\n\n";
            $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

            foreach ($tables as $table) {
                // Drop table if exists
                $output .= "DROP TABLE IF EXISTS `$table`;\n\n";

                // Create table structure
                $create_table = $conn->query("SHOW CREATE TABLE `$table`");
                if ($create_table && $row2 = $create_table->fetch_row()) {
                    $output .= $row2[1] . ";\n\n";
                }

                // Insert data
                $result2 = $conn->query("SELECT * FROM `$table`");
                if ($result2 && $result2->num_rows > 0) {
                    $num_fields = $result2->field_count;

                    while ($row = $result2->fetch_row()) {
                        $output .= "INSERT INTO `$table` VALUES(";
                        for ($j = 0; $j < $num_fields; $j++) {
                            if (isset($row[$j])) {
                                $row[$j] = addslashes($row[$j]);
                                $row[$j] = str_replace("\n", "\\n", $row[$j]);
                                $output .= "'" . $row[$j] . "'";
                            } else {
                                $output .= "NULL";
                            }
                            if ($j < ($num_fields - 1)) {
                                $output .= ',';
                            }
                        }
                        $output .= ");\n";
                    }
                }
                $output .= "\n";
            }

            $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";

            // Save to file
            if (file_put_contents($backup_path, $output)) {
                $message = "✅ Varmuuskopio luotu onnistuneesti!";
                $message_type = "success";
            } else {
                $message = "❌ Virhe varmuuskopion tallentamisessa!";
                $message_type = "error";
            }
        } else {
            $message = "❌ Virhe taulukoiden hakemisessa!";
            $message_type = "error";
        }
    } catch (Exception $e) {
        $message = "❌ Virhe: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle DELETE BACKUP
if ($action === 'delete_backup' && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $backup_path = $backup_dir . $file;

    if (file_exists($backup_path) && unlink($backup_path)) {
        $message = "🗑️ Varmuuskopio poistettu!";
        $message_type = "success";
    } else {
        $message = "❌ Virhe varmuuskopion poistamisessa!";
        $message_type = "error";
    }

    // Ohjaa takaisin sivulle, jotta viesti näkyy
    header("Location: admin_varmuuskopiointi.php?message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// Handle DOWNLOAD BACKUP
if ($action === 'download_backup' && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $backup_path = $backup_dir . $file;

    if (file_exists($backup_path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($backup_path));
        readfile($backup_path);
        exit();
    }
}

// Handle RESTORE BACKUP - REMOVED FOR SAFETY (can be added later)
if ($action === 'restore_backup' && isset($_GET['file'])) {
    $message = "⚠️ Palautustoiminto on tilapäisesti poissa käytöstä turvallisuussyistä.";
    $message_type = "warning";
}

// Get all backup files
$backup_files = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    if ($files) {
        $files = array_diff($files, ['.', '..']);
        rsort($files); // Sort descending

        foreach ($files as $file) {
            $file_path = $backup_dir . $file;
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql' && file_exists($file_path)) {
                $backup_files[] = [
                    'name' => $file,
                    'path' => $file_path,
                    'size' => filesize($file_path),
                    'modified' => filemtime($file_path)
                ];
            }
        }
    }
}

// Get database info with safe queries
$db_info = [
    'member_count' => 0,
    'book_count' => 0,
    'device_count' => 0,
    'table_count' => 0
];

try {
    // Get table count
    $tables_result = $conn->query("SHOW TABLES");
    if ($tables_result) {
        $db_info['table_count'] = $tables_result->num_rows;
    }

    // Check if tables exist and get counts
    $tables_to_check = ['jasenet', 'kirjat', 'Laitteet'];

    foreach ($tables_to_check as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
            if ($count_result) {
                $row = $count_result->fetch_assoc();
                if ($table === 'jasenet') $db_info['member_count'] = $row['count'];
                if ($table === 'kirjat') $db_info['book_count'] = $row['count'];
                if ($table === 'Laitteet') $db_info['device_count'] = $row['count'];
            }
        }
    }
} catch (Exception $e) {
    // Silent fail - just use default values
}

// Calculate total backup size
$total_backup_size = 0;
foreach ($backup_files as $backup) {
    $total_backup_size += $backup['size'];
}

// Format bytes to readable function
function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Get latest backup
$latest_backup = !empty($backup_files) ? $backup_files[0] : null;

// Set safe timezone
date_default_timezone_set('Europe/Helsinki');
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirjasto - Varmuuskopiointi</title>
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
        .stat-card:nth-child(2) { border-color: var(--success); }
        .stat-card:nth-child(3) { border-color: var(--purple); }
        .stat-card:nth-child(4) { border-color: var(--warning); }
        .stat-card:nth-child(5) { border-color: var(--danger); }
        .stat-card:nth-child(6) { border-color: var(--secondary); }

        .stat-card:nth-child(1) .stat-icon { background: var(--info); }
        .stat-card:nth-child(2) .stat-icon { background: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: var(--purple); }
        .stat-card:nth-child(4) .stat-icon { background: var(--warning); }
        .stat-card:nth-child(5) .stat-icon { background: var(--danger); }
        .stat-card:nth-child(6) .stat-icon { background: var(--secondary); }

        /* Backup Actions */
        .actions-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .action-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
            border-color: var(--info);
        }

        .action-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            color: white;
        }

        .action-icon.create {
            background: linear-gradient(135deg, var(--success), #219653);
        }

        .action-icon.restore {
            background: linear-gradient(135deg, var(--warning), #E67E22);
        }

        .action-icon.settings {
            background: linear-gradient(135deg, var(--info), #2980B9);
        }

        .action-title {
            font-size: 1.3em;
            font-weight: 600;
            color: var(--primary);
        }

        .action-description {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
            font-size: 0.95em;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 0.95em;
            text-decoration: none;
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

        .btn-info {
            background: linear-gradient(135deg, var(--info), #2980B9);
            color: white;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.25);
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(52, 152, 219, 0.35);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--secondary), #C0392B);
            color: white;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.25);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(231, 76, 60, 0.35);
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
            max-height: 500px;
            overflow-y: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            width: 34px;
            height: 34px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 0.95em;
            text-decoration: none;
        }

        .action-btn.download {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .action-btn.download:hover {
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

        .action-btn.restore {
            background: rgba(241, 196, 15, 0.1);
            color: var(--warning);
            border: 1px solid rgba(241, 196, 15, 0.2);
        }

        .action-btn.restore:hover {
            background: linear-gradient(135deg, var(--warning), #E67E22);
            color: white;
            transform: translateY(-2px);
        }

        .action-btn.info {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
            border: 1px solid rgba(52, 152, 219, 0.2);
        }

        .action-btn.info:hover {
            background: linear-gradient(135deg, var(--info), #2980B9);
            color: white;
            transform: translateY(-2px);
        }

        /* Status Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .badge-warning {
            background: rgba(241, 196, 15, 0.1);
            color: var(--warning);
            border: 1px solid rgba(241, 196, 15, 0.2);
        }

        .badge-danger {
            background: rgba(231, 76, 60, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .badge-info {
            background: rgba(52, 152, 219, 0.1);
            color: var(--info);
            border: 1px solid rgba(52, 152, 219, 0.2);
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

        .notification-warning {
            border-color: var(--warning);
            color: var(--warning);
            background: rgba(241, 196, 15, 0.05);
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

        /* Info Box */
        .info-box {
            background: rgba(52, 152, 219, 0.05);
            border-left: 4px solid var(--info);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .info-box p {
            color: #666;
            line-height: 1.6;
        }

        .info-box i {
            color: var(--info);
            margin-right: 8px;
        }

        /* Text utilities */
        .text-muted {
            color: #a0aec0;
        }

        .text-success {
            color: var(--success);
        }

        .text-danger {
            color: var(--secondary);
        }

        .text-warning {
            color: var(--warning);
        }

        .text-info {
            color: var(--info);
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
            .actions-section {
                grid-template-columns: 1fr;
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
            <a href="admin_sakot.php" class="menu-item">
                <span>⚠️ Hallinnoi Sakkoja</span>
            </a>

            <div class="menu-section">🔧 Järjestelmä</div>
            <a href="admin_varmuuskopiointi.php" class="menu-item active">
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
                    <i class="fas fa-database"></i>
                </div>
                <h1 class="page-title">Varmuuskopiointi</h1>
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
            <div class="notification notification-<?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : ($message_type === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'); ?>"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>TAULUJA</h3>
                        <div class="stat-number"><?php echo $db_info['table_count']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-table"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>JÄSENIÄ</h3>
                        <div class="stat-number"><?php echo $db_info['member_count']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>KIRJOJA</h3>
                        <div class="stat-number"><?php echo $db_info['book_count']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>LAITTEITA</h3>
                        <div class="stat-number"><?php echo $db_info['device_count']; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-laptop"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>VARMUUSKOPIOT</h3>
                        <div class="stat-number"><?php echo count($backup_files); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-copy"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-info">
                        <h3>KOKO YHT.</h3>
                        <div class="stat-number"><?php echo formatBytes($total_backup_size); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-hdd"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- BACKUP ACTIONS -->
        <div class="actions-section">
            <div class="action-card">
                <div class="action-header">
                    <div class="action-icon create">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-title">Luo uusi varmuuskopio</div>
                </div>
                <div class="action-description">
                    Luo uusi varmuuskopio koko tietokannasta. Varmuuskopio tallennetaan palvelimelle ja se on ladattavissa myöhemmin.
                </div>
                <form method="POST" action="?action=create_backup">
                    <button type="submit" class="btn btn-success" style="width: 100%;" onclick="return confirm('Luodaanko uusi varmuuskopio?')">
                        <i class="fas fa-database"></i> Luo varmuuskopio nyt
                    </button>
                </form>
            </div>

            <div class="action-card">
                <div class="action-header">
                    <div class="action-icon restore">
                        <i class="fas fa-undo-alt"></i>
                    </div>
                    <div class="action-title">Palauta varmuuskopio</div>
                </div>
                <div class="action-description">
                    Palauta tietokanta aiemmasta varmuuskopiosta. Tämä toiminto korvaa nykyisen tietokannan.
                </div>
                <div class="info-box" style="margin-bottom: 15px;">
                    <p><i class="fas fa-shield-alt"></i> Palautustoiminto on poissa käytöstä turvallisuussyistä.</p>
                </div>
                <button class="btn btn-warning" style="width: 100%; opacity: 0.5;" disabled>
                    <i class="fas fa-exclamation-triangle"></i> Palauta (ei käytössä)
                </button>
            </div>

            <div class="action-card">
                <div class="action-header">
                    <div class="action-icon settings">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="action-title">Automaattiset varmuuskopiot</div>
                </div>
                <div class="action-description">
                    Viimeisin varmuuskopio: <strong><?php echo $latest_backup ? date('d.m.Y H:i', $latest_backup['modified']) : 'Ei varmuuskopioita'; ?></strong>
                    <?php if ($latest_backup): ?>
                        <br>Koko: <strong><?php echo formatBytes($latest_backup['size']); ?></strong>
                    <?php endif; ?>
                </div>
                <div class="info-box">
                    <p><i class="fas fa-clock"></i> Automaattiset varmuuskopiot voidaan ottaa käyttöön järjestelmäasetuksissa.</p>
                </div>
            </div>
        </div>

        <!-- BACKUP FILES TABLE -->
        <div class="table-section">
            <div class="table-header">
                <h3>
                    <i class="fas fa-file-archive" style="color: var(--info);"></i>
                    Tallennetut varmuuskopiot (<?php echo count($backup_files); ?>)
                </h3>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tiedostonimi</th>
                            <th>Koko</th>
                            <th>Luotu</th>
                            <th>Tekijä</th>
                            <th>Toiminnot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($backup_files)): ?>
                            <?php foreach ($backup_files as $index => $backup): ?>
                                <tr>
                                    <td><strong>#<?php echo str_pad($index + 1, 2, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td>
                                        <i class="fas fa-file-code text-info"></i>
                                        <?php echo htmlspecialchars($backup['name']); ?>
                                    </td>
                                    <td><?php echo formatBytes($backup['size']); ?></td>
                                    <td>
                                        <?php echo date('d.m.Y H:i', $backup['modified']); ?>
                                        <?php if ($index === 0): ?>
                                            <span class="badge badge-success" style="margin-left: 8px;">Uusin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        // Try to extract creator from backup file (first few lines)
                                        $creator = 'Tuntematon';
                                        $handle = fopen($backup['path'], 'r');
                                        if ($handle) {
                                            $line = fgets($handle); // Skip first comment line
                                            $line = fgets($handle); // Skip second comment line
                                            $line = fgets($handle); // Read creator line
                                            if (strpos($line, '-- Tekijä:') !== false) {
                                                $creator = trim(str_replace('-- Tekijä:', '', $line));
                                            }
                                            fclose($handle);
                                        }
                                        ?>
                                        <?php echo htmlspecialchars($creator); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=download_backup&file=<?php echo urlencode($backup['name']); ?>" 
                                               class="action-btn download" title="Lataa">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="?action=delete_backup&file=<?php echo urlencode($backup['name']); ?>" 
                                               class="action-btn delete" title="Poista"
                                               onclick="return confirm('Haluatko varmasti poistaa tämän varmuuskopion?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <?php if ($rooli === 'admin'): ?>
                                            <a href="?action=restore_backup&file=<?php echo urlencode($backup['name']); ?>" 
                                               class="action-btn restore" title="Palauta (ei käytössä)"
                                               onclick="return false; alert('Palautustoiminto on poissa käytöstä turvallisuussyistä.');">
                                                <i class="fas fa-undo-alt"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fas fa-database"></i>
                                    <h3>Ei varmuuskopioita</h3>
                                    <p>Luo ensimmäinen varmuuskopio napsauttamalla "Luo uusi varmuuskopio" -painiketta.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

<!-- INFO BOX -->
<div class="info-box" style="margin-top: 20px; background: white; border-radius: 12px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.08);">
    <h4 style="color: var(--primary); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; font-size: 1.2em;">
        <i class="fas fa-info-circle" style="color: var(--info);"></i>
        Tietoa varmuuskopioinnista
    </h4>
    <ul style="list-style: none; color: #4a5568; line-height: 2; margin: 0; padding: 0;">
        <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle" style="color: var(--success); font-size: 1.1em;"></i>
            <span>Varmuuskopiot tallennetaan hakemistoon: <code style="background: #f0f0f0; padding: 3px 8px; border-radius: 4px; color: var(--primary);">/backups/</code></span>
        </li>
        <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle" style="color: var(--success); font-size: 1.1em;"></i>
            <span>Varmuuskopiot ovat SQL-muodossa ja ne voidaan palauttaa phpMyAdminin kautta</span>
        </li>
        <li style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-exclamation-triangle" style="color: var(--warning); font-size: 1.1em;"></i>
            <span>Vanhoja varmuuskopioita kannattaa poistaa säännöllisesti tilan säästämiseksi</span>
        </li>
        <li style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-shield-alt" style="color: var(--info); font-size: 1.1em;"></i>
            <span>Turvallisuussyistä suora palautus tietokantaan on poistettu käytöstä</span>
        </li>
    </ul>
</div>

    <script>
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
