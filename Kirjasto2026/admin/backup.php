<?php
// admin/backup.php — Database Backup

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';
$error = '';

// ---- DOWNLOAD BACKUP ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
    try {
        // Get all tables
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sql = "-- Kirjasto 2026 Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: " . DB_NAME . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // Drop table
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            
            // Create table
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $sql .= $row[1] . ";\n\n";

            // Insert data
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $col_list = implode('`, `', $columns);
                
                foreach ($rows as $row_data) {
                    $values = array_map(function($val) use ($pdo) {
                        if ($val === null) return 'NULL';
                        return $pdo->quote($val);
                    }, $row_data);
                    
                    $sql .= "INSERT INTO `$table` (`$col_list`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Download
        $filename = 'kirjasto2026_backup_' . date('Y-m-d_His') . '.sql';
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        echo $sql;
        exit;

    } catch (PDOException $e) {
        $error = 'Backup failed: ' . $e->getMessage();
    }
}

// ---- FETCH BACKUP STATS ----
$total_tables = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetchColumn();
$db_size = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetchColumn();
$last_backup = $pdo->query("SELECT created_at FROM audit_logs WHERE action = 'backup' ORDER BY created_at DESC LIMIT 1")->fetchColumn();

$page_title = 'Database Backup';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Database Backup</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Download a full SQL dump of the entire library system.</p>
</div>

<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2">
    <!-- Left: Backup Info -->
    <div class="card">
        <h3><i class="fas fa-database" style="color: #3b82f6;"></i> Database Status</h3>
        
        <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; padding: 16px; background: var(--bg-alt); border-radius: 12px;">
                <span style="color: var(--text-muted);">Total Tables</span>
                <span style="color: var(--text); font-weight: 700; font-size: 1.1rem;"><?= $total_tables ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 16px; background: var(--bg-alt); border-radius: 12px;">
                <span style="color: var(--text-muted);">Database Size</span>
                <span style="color: var(--text); font-weight: 700; font-size: 1.1rem;"><?= $db_size ?> MB</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 16px; background: var(--bg-alt); border-radius: 12px;">
                <span style="color: var(--text-muted);">Last Backup</span>
                <span style="color: var(--text); font-weight: 700; font-size: 1.1rem;"><?= $last_backup ? date('M j, Y H:i', strtotime($last_backup)) : 'Never' ?></span>
            </div>
        </div>

        <div style="margin-top: 24px; padding: 16px; background: rgba(245, 158, 11, 0.05); border-radius: 12px; border: 1px solid rgba(245, 158, 11, 0.2);">
            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">
                <i class="fas fa-info-circle" style="color: #f59e0b;"></i> 
                A full backup includes all tables, data, and schema. Store it safely — you can restore the system from it at any time.
            </p>
        </div>
    </div>

    <!-- Right: Download Action -->
    <div class="card" style="text-align: center; display: flex; flex-direction: column; justify-content: center; padding: 40px 24px;">
        <div style="width: 80px; height: 80px; margin: 0 auto 24px; border-radius: 24px; background: linear-gradient(135deg, #3b82f6, #2563eb); display: grid; place-items: center; color: white; font-size: 2rem; box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.5);">
            <i class="fas fa-download"></i>
        </div>
        <h3 style="font-size: 1.3rem; margin-bottom: 8px;">Download Full Backup</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px;">Generate and download a complete SQL dump of the Kirjasto 2026 database.</p>
        
        <form method="post">
            <input type="hidden" name="action" value="backup">
            <button type="submit" class="btn-primary" style="width: auto; padding: 14px 40px; background: linear-gradient(135deg, #3b82f6, #2563eb);">
                <i class="fas fa-file-download"></i> Download Backup (.sql)
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
