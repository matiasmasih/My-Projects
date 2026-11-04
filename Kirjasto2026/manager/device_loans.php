<?php
// manager/device_loans.php — Process Device Loans & Returns (branch-scoped)

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$manager_row = $stmt->fetch();
$branch_id = $manager_row['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$flash = '';
$error = '';

// ---- RETURN DEVICE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'return_device') {
    $loan_id = (int)$_POST['loan_id'];
    if (!$branch_id) {
        $error = 'Your account is not assigned to a branch.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM device_loans WHERE id = ? AND status = 'active' AND branch_id = ?");
            $stmt->execute([$loan_id, $branch_id]);
            $loan = $stmt->fetch();
            if (!$loan) throw new Exception('Active loan not found in your branch.');

            $now = date('Y-m-d H:i:s');
            $fine = 0;
            if (strtotime($now) > strtotime($loan['due_at'])) {
                $days_late = ceil((strtotime($now) - strtotime($loan['due_at'])) / 86400);
                $fine = $days_late * 1.00;
            }

            $pdo->prepare("UPDATE device_loans SET returned_at = ?, status = 'returned', fine_amount = ? WHERE id = ?")->execute([$now, $fine, $loan_id]);
            $pdo->prepare("UPDATE devices SET status = 'available' WHERE id = ?")->execute([$loan['device_id']]);

            $pdo->commit();
            $flash = 'Device returned.' . ($fine > 0 ? " Fine issued: €" . number_format($fine, 2) : '');
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Return failed: ' . $e->getMessage();
        }
    }
}

// ---- FETCH DEVICE LOANS (branch-scoped & searchable) ----
$filter = $_GET['filter'] ?? 'active';
$search = trim($_GET['search'] ?? '');
$loans = [];

if ($branch_id) {
    $query = "
        SELECT dl.*, d.brand, d.model, d.serial_number, d.image, dt.name AS type_name,
               u.first_name, u.last_name, u.email, u.profile_image,
               DATEDIFF(NOW(), dl.due_at) AS days_overdue
        FROM device_loans dl
        JOIN devices d ON d.id = dl.device_id
        JOIN device_types dt ON dt.id = d.device_type_id
        JOIN users u ON u.id = dl.user_id
        WHERE dl.branch_id = ?
    ";
    $params = [$branch_id];

    if ($filter === 'active') {
        $query .= " AND dl.status = 'active' AND dl.due_at >= NOW()";
    } elseif ($filter === 'overdue') {
        $query .= " AND dl.status IN ('active', 'overdue') AND dl.due_at < NOW()";
    } elseif ($filter === 'returned') {
        $query .= " AND dl.status = 'returned'";
    }

    if ($search !== '') {
        $query .= " AND (d.brand LIKE ? OR d.model LIKE ? OR d.serial_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $like = "%$search%";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $query .= " ORDER BY dl.due_at ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $loans = $stmt->fetchAll();
}

$page_title = 'Device Loans';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Manage Device Loans</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
            <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Process returns and manage device loans.
        </p>
    </div>
</div>

<?php if (!$branch_id): ?>
    <div class="flash flash-error" style="margin-bottom: 24px;"><i class="fas fa-exclamation-triangle"></i> Your account is not assigned to a branch.</div>
<?php endif; ?>
<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<!-- Filter & Search Bar -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="?filter=active&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'active' ? 'background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-laptop"></i> Active
            </a>
            <a href="?filter=overdue&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'overdue' ? 'background: var(--danger); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i> Overdue
            </a>
            <a href="?filter=returned&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'returned' ? 'background: var(--success); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-check-circle"></i> Returned
            </a>
            <a href="?filter=all&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'all' ? 'background: var(--primary); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-list"></i> All
            </a>
        </div>
        
        <form method="GET" style="display: flex; gap: 8px; flex: 1; max-width: 400px;">
            <input type="hidden" name="filter" value="<?= e($filter) ?>">
            <div style="position: relative; flex: 1;">
                <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search member or device..." style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); outline: none;">
            </div>
            <button type="submit" class="btn-primary" style="padding: 0 20px; border-radius: 8px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: none; cursor: pointer; font-weight: 600;">Search</button>
        </form>
    </div>
</div>

<!-- Loans List -->
<?php if (empty($loans)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
        <h3 style="color: var(--text-muted); margin-bottom: 8px;">No Device Loans Found</h3>
        <p style="color: var(--text-muted);">No device loans match your search or filter.</p>
    </div>
<?php else: ?>
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Member</th>
                        <th>Borrowed</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): ?>
                        <?php
                            $is_overdue = ($loan['status'] === 'active' && strtotime($loan['due_at']) < time());
                            $status_label = 'Active';
                            $status_color = 'var(--warning)';
                            if ($loan['status'] === 'returned') { $status_label = 'Returned'; $status_color = 'var(--success)'; } 
                            elseif ($is_overdue) { $status_label = 'Overdue'; $status_color = 'var(--danger)'; }
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <?php if (!empty($loan['image']) && file_exists(__DIR__ . '/../uploads/' . $loan['image'])): ?>
                                        <img src="<?= UPLOADS_URL . e($loan['image']) ?>" style="width: 40px; height: 40px; border-radius: 6px; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; border-radius: 6px; background: rgba(245,158,11,0.15); display: grid; place-items: center; color: #f59e0b;"><i class="fas fa-laptop"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <strong style="display: block; color: var(--text); font-size: 0.9rem;"><?= e($loan['type_name']) ?> — <?= e($loan['brand'] . ' ' . $loan['model']) ?></strong>
                                        <small style="color: var(--text-muted);"><?= e($loan['serial_number']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($loan['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $loan['profile_image'])): ?>
                                        <img src="<?= UPLOADS_URL . e($loan['profile_image']) ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.75rem;"><?= strtoupper(substr($loan['email'], 0, 2)) ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <strong style="display: block; color: var(--text); font-size: 0.85rem;"><?= e($loan['first_name'] . ' ' . $loan['last_name']) ?></strong>
                                        <small style="color: var(--text-muted); font-size: 0.75rem;"><?= e($loan['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;"><?= date('M j, Y', strtotime($loan['borrowed_at'])) ?></td>
                            <td style="color: <?= $is_overdue ? 'var(--danger)' : 'var(--text-muted)' ?>; font-size: 0.85rem; font-weight: <?= $is_overdue ? '700' : '400' ?>;">
                                <?= date('M j, Y', strtotime($loan['due_at'])) ?>
                                <?php if ($is_overdue): ?><br><small style="color: var(--danger);"><?= $loan['days_overdue'] ?> days overdue</small><?php endif; ?>
                            </td>
                            <td><span style="padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: <?= $status_color ?>20; color: <?= $status_color ?>;"><?= $status_label ?></span></td>
                            <td style="text-align: right;">
                                <?php if ($loan['status'] === 'active'): ?>
                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Mark this device as returned?');">
                                            <input type="hidden" name="action" value="return_device">
                                            <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                            <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.75rem; background: var(--success); width: auto;"><i class="fas fa-check"></i> Return</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
