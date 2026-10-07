<?php
// config.php — Database connection + core settings

// Error reporting (dev only — disable in production)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

$host = 'localhost';
$db   = 'LibraryProDB';
$user = 'library_app';
$pass = 'Library@2026';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    exit('Database connection failed: ' . $e->getMessage());
}

// ============================================================
// Site settings (non-database)
// ============================================================
define('SITE_URL', 'http://localhost/LibraryProDB/');

// ============================================================
// Load configurable settings from database.
// Falls back to hardcoded defaults if the settings table is
// missing or a key is not present.
// ============================================================
function _setting($key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            global $pdo;
            $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
            foreach ($rows as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) {
            // settings table not created yet — use defaults
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

// Site name (from DB, fallback to default)
define('SITE_NAME', _setting('site_name', 'LibraryPro'));

// Library rules — from settings table with hardcoded fallbacks
define('LOAN_DAYS',            (int)   _setting('loan_days',            14));     // days a book can be borrowed
define('MAX_RENEWALS',         (int)   _setting('max_renewals',          2));     // how many times a loan can be renewed
define('FINE_PER_DAY',         (float) _setting('fine_per_day',          1.00));  // € per day overdue
define('MAX_FINE_PER_BOOK',    (float) _setting('max_fine_per_book',    20.00));  // cap fine per book
define('MAX_LOANS_PER_MEMBER', (int)   _setting('max_loans_per_member',  5));     // max simultaneous active loans
define('FINE_BLOCK_THRESHOLD', (float) _setting('fine_block_threshold', 10.00));  // block checkout if unpaid fines exceed this
