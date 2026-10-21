<?php
// includes/config.php — Database connection + helpers

// ---------- DATABASE ----------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3307);
define('DB_NAME', 'attendance');
define('DB_USER', 'attendance_user');
define('DB_PASS', 'Matias413114312@#$?');

// ---------- SITE ----------
define('SITE_NAME', 'Attendance System');
define('SITE_URL',  'http://localhost/attendance/');
define('ASSETS_URL', SITE_URL . 'assets/');
define('UPLOADS_URL', SITE_URL . 'uploads/');
define('UPLOADS_DIR', __DIR__ . '/../uploads/');

// ---------- SESSION ----------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- DATABASE CONNECTION (PDO) ----------
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// ---------- TIMEZONE ----------
date_default_timezone_set('Europe/Helsinki');

// ---------- HELPERS ----------
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = $GLOBALS['pdo']->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {}
    }
    return $cache[$key] ?? $default;
}
// ---------- MAINTENANCE MODE CHECK ----------
// If maintenance mode is ON and user is not admin, redirect to maintenance page
// Exceptions: maintenance.php itself, login.php, logout.php
(function() {
    // Skip if not yet connected
    if (!isset($GLOBALS['pdo'])) return;

    // Skip on certain pages
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
    $exempt = ['maintenance.php', 'login.php', 'logout.php', 'register.php', 'config.php'];
    if (in_array($current_page, $exempt, true)) return;

    // Check setting
    try {
        $stmt = $GLOBALS['pdo']->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
        $stmt->execute();
        $maintenance = $stmt->fetchColumn();

        if ($maintenance === '1') {
            // Allow admins through
            $role = $_SESSION['user_role'] ?? '';
            if ($role !== 'admin') {
                header('Location: ' . SITE_URL . 'maintenance.php');
                exit;
            }
        }
    } catch (PDOException $e) {
        // Silent fail — don't block site on DB error
    }
})();
