<?php
// manager/manage_branch.php — Manage a Specific Branch
// Kirjasto 2026 — Phase 9

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager' && $user['role'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branch_id = (int)($_GET['id'] ?? 0);
if (!$branch_id) {
    header('Location: ' . SITE_URL . 'manager/branches.php');
    exit;
}

$flash = '';
$error = '';

// Handle Move Book Copy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'move_book') {
    $copy_id = (int)$_POST['copy_id'];
    $new_branch = (int)$_POST['new_branch_id'];
    try {
        $pdo->prepare("UPDATE book_copies SET branch_id = ? WHERE id = ?")->execute([$new_branch, $copy_id]);
        $flash = 'Book copy moved successfully.';
    } catch (PDOException $e) {
        $error = 'Failed to move book: ' . $e->getMessage();
    }
}

// Handle Move Device
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'move_device') {
    $device_id = (int)$_POST['device_id'];
    $new_branch = (int)$_POST['new_branch_id'];
    try {
        $pdo->prepare("UPDATE devices SET branch_id = ? WHERE id = ?")->execute([$new_branch, $device_id]);
        $flash = 'Device moved successfully.';
    } catch (PDOException $e) {
        $error = 'Failed to move device: ' . $e->getMessage();
    }
}

// Fetch Branch Details
$stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch = $stmt->fetch();

if (!$branch) {
    header('Location: ' . SITE_URL . 'manager/branches.php');
    exit;
}

// Fetch Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM book_copies WHERE branch_id = ?"); $stmt->execute([$branch_id]);
$book_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE branch_id = ?"); $stmt->execute([$branch_id]);
$device_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE branch_id = ?"); $stmt->execute([$branch_id]);
$user_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE branch_id = ? AND status = 'active'"); $stmt->execute([$branch_id]);
$loan_count = $stmt->fetchColumn();

// Fetch Inventory (Books & Devices)
$stmt = $pdo->prepare("
    SELECT bc.*, b.title, b.author 
    FROM book_copies bc 
    JOIN books b ON b.id = bc.book_id 
    WHERE bc.branch_id = ? ORDER BY b.title
");
$stmt->execute([$branch_id]);
$books = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT d.*, dt.name as type_name 
    FROM devices d 
    JOIN device_types dt ON dt.id = d.device_type_id 
    WHERE d.branch_id = ?
");
$stmt->execute([$branch_id]);
$devices = $stmt->fetchAll();

// Fetch Users
$stmt = $pdo->prepare("
    SELECT * FROM users WHERE branch_id = ? ORDER BY role, first_name
");
$stmt->execute([$branch_id]);
$users = $stmt->fetchAll();

$all_branches = get_branches();

$page_title = 'Manage ' . $branch['name'];
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div style="margin-bottom: 32px;">
    <a href="<?= e(SITE_URL) ?>manager/branches.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); text-decoration: none; font-size: 0.9rem; margin-bottom: 8px;">
        <i class="fas fa-arrow-left"></i> Back to All Branches
    </a>
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;"><?= e($branch['name']) ?></h1>
            <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;"><i class="fas fa-map-pin"></i> <?= e($branch['address'] ?: 'No address provided') ?></p>
        </div>
        <div>
            <?php if ($branch['is_active']): ?>
                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 6px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase;">Active</span>
            <?php else: ?>
                <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 6px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase;">Inactive</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($flash): ?><div class="flash flash-success" style="margin-bottom: 24px;"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error" style="margin-bottom: 24px;"><?= e($error) ?></div><?php endif; ?>

<!-- Stats Grid -->
<div class="stat-grid" style="margin-bottom: 32px;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-book"></i></div>
        <div><div class="stat-value"><?= $book_count ?></div><div class="stat-label">Books</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-laptop"></i></div>
        <div><div class="stat-value"><?= $device_count ?></div><div class="stat-label">Devices</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-list"></i></div>
        <div><div class="stat-value"><?= $loan_count ?></div><div class="stat-label">Active Loans</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-users"></i></div>
        <div><div class="stat-value"><?= $user_count ?></div><div class="stat-label">Users</div></div>
    </div>
</div>

<div class="grid-2" style="align-items: start; gap: 32px; margin-bottom: 32px;">
    
    <!-- Inventory Management -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 20px; border-bottom: 1px solid var(--border); background: var(--bg-alt);">
            <h3 style="margin: 0;"><i class="fas fa-exchange-alt" style="color: #f59e0b;"></i> Manage Inventory</h3>
        </div>
        <div style="max-height: 400px; overflow-y: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead style="background: var(--card-bg); position: sticky; top: 0;">
                    <tr>
                        <th style="padding: 12px; border-bottom: 1px solid var(--border); text-align: left;">Item</th>
                        <th style="padding: 12px; border-bottom: 1px solid var(--border); text-align: right;">Move To</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $b): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                                <strong style="display: block; color: var(--text); font-size: 0.9rem;"><?= e($b['title']) ?></strong>
                                <small style="color: var(--text-muted);">Copy #<?= e($b['copy_number']) ?> (Book)</small>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border); text-align: right;">
                                <form method="POST" style="display: flex; gap: 6px; justify-content: flex-end;">
                                    <input type="hidden" name="action" value="move_book">
                                    <input type="hidden" name="copy_id" value="<?= $b['id'] ?>">
                                    <select name="new_branch_id" style="padding: 4px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 0.8rem;">
                                        <?php foreach ($all_branches as $ab): ?>
                                            <option value="<?= $ab['id'] ?>" <?= $ab['id'] == $branch_id ? 'selected' : '' ?>><?= e($ab['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" style="padding: 4px 10px; font-size: 0.75rem; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 700;">Move</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($devices as $d): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                                <strong style="display: block; color: var(--text); font-size: 0.9rem;"><?= e($d['brand'] . ' ' . $d['model']) ?></strong>
                                <small style="color: var(--text-muted);">SN: <?= e($d['serial_number']) ?> (Device)</small>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border); text-align: right;">
                                <form method="POST" style="display: flex; gap: 6px; justify-content: flex-end;">
                                    <input type="hidden" name="action" value="move_device">
                                    <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
                                    <select name="new_branch_id" style="padding: 4px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 0.8rem;">
                                        <?php foreach ($all_branches as $ab): ?>
                                            <option value="<?= $ab['id'] ?>" <?= $ab['id'] == $branch_id ? 'selected' : '' ?>><?= e($ab['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" style="padding: 4px 10px; font-size: 0.75rem; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 700;">Move</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Staff & Members -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 20px; border-bottom: 1px solid var(--border); background: var(--bg-alt);">
            <h3 style="margin: 0;"><i class="fas fa-users" style="color: #f59e0b;"></i> Staff & Members</h3>
        </div>
        <div style="max-height: 400px; overflow-y: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead style="background: var(--card-bg); position: sticky; top: 0;">
                    <tr>
                        <th style="padding: 12px; border-bottom: 1px solid var(--border); text-align: left;">Name</th>
                        <th style="padding: 12px; border-bottom: 1px solid var(--border); text-align: left;">Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                                <strong style="display: block; color: var(--text); font-size: 0.9rem;"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($u['email']) ?></small>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                                <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                                    <?= e($u['role']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
