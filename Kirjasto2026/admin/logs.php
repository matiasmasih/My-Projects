<?php
// admin/logs.php — Global Audit Logs

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
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

// ---- SEARCH & FILTER ----
$search = trim($_GET['search'] ?? '');
$filter_action = $_GET['action'] ?? 'all';

$query = "
    SELECT a.*, u.email 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
";
$params = [];
$conditions = [];

if ($search !== '') {
    $conditions[] = "(u.email LIKE ? OR a.action LIKE ? OR a.ip_address LIKE ?)";
    $term = '%' . $search . '%';
    $params = array_merge($params, [$term, $term, $term]);
}

if ($filter_action !== 'all') {
    $conditions[] = "a.action = ?";
    $params[] = $filter_action;
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}
$query .= " ORDER BY a.created_at DESC LIMIT 200";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch distinct actions for the dropdown
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Audit Logs';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Audit Logs</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Monitor all system activity for security and compliance.</p>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <form method="get" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by email, action, or IP..." style="flex: 1; min-width: 200px; padding: 12px 16px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: var(--font); font-size: 0.95rem; outline: none;">
        
        <select name="action" style="padding: 12px 16px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: var(--font); font-size: 0.95rem; outline: none;">
            <option value="all" <?= $filter_action === 'all' ? 'selected' : '' ?>>All Actions</option>
            <?php foreach ($actions as $act): ?>
                <option value="<?= e($act) ?>" <?= $filter_action === $act ? 'selected' : '' ?>>
                    <?= e(str_replace('_', ' ', ucfirst($act))) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-primary" style="width: auto; padding: 12px 24px; background: linear-gradient(135deg, #3b82f6, #2563eb);">
            <i class="fas fa-filter"></i> Filter
        </button>
    </form>
</div>

<!-- Logs Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--text);">
                            <?= e($log['email'] ?? 'Guest / System') ?>
                        </td>
                        <td>
                            <span style="padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                <?= e(str_replace('_', ' ', $log['action'])) ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.85rem; max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($log['details'] ?? '') ?>">
                            <?= e($log['details'] ?: '—') ?>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.85rem; font-family: monospace;">
                            <?= e($log['ip_address'] ?: '—') ?>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;">
                            <?= date('M j, Y H:i:s', strtotime($log['created_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">No logs found matching your criteria.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
