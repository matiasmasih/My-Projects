<?php
// staff/device_loans.php — Process Device Loans & Returns (Branch Scoped)

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fixed role check
if ($user['role'] !== 'staff' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;
$daily_fine = (float) get_setting('fine_per_day', 0.50);

// ---- Handle POST Actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'borrow') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $device_id = (int)($_POST['device_id'] ?? 0);

        // Verify member belongs to this branch
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'member' AND branch_id = ?");
        $stmt->execute([$user_id, $branch_id]);
        if (!$stmt->fetch()) {
            $_SESSION['flash_error'] = 'Invalid member for your branch.';
        } else {
            // Verify device belongs to this branch and is available
            $stmt = $pdo->prepare("SELECT id, device_type_id FROM devices WHERE id = ? AND status = 'available' AND branch_id = ?");
            $stmt->execute([$device_id, $branch_id]);
            $device = $stmt->fetch();

            if (!$device) {
                $_SESSION['flash_error'] = 'Device is not available in your branch.';
            } else {
                $stmt = $pdo->prepare("SELECT loan_days FROM device_types WHERE id = ?");
                $stmt->execute([$device['device_type_id']]);
                $loan_days = (int)$stmt->fetchColumn() ?: 7;

                $stmt = $pdo->prepare("INSERT INTO device_loans (user_id, device_id, borrowed_at, due_at, status, branch_id) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active', ?)");
                $stmt->execute([$user_id, $device_id, $loan_days, $branch_id]);

                $pdo->prepare("UPDATE devices SET status = 'borrowed' WHERE id = ?")->execute([$device_id]);

                $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
                $log_stmt->execute([$_SESSION['user_id'], 'device_loan.borrow', "Member ID: $user_id, Device ID: $device_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

                $_SESSION['flash_success'] = 'Device loan processed successfully.';
            }
        }
        header('Location: ' . SITE_URL . 'staff/device_loans.php');
        exit;
    }

    if ($action === 'return') {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        // Verify loan belongs to this branch
        $stmt = $pdo->prepare("SELECT * FROM device_loans WHERE id = ? AND status = 'active' AND branch_id = ?");
        $stmt->execute([$loan_id, $branch_id]);
        $loan = $stmt->fetch();

        if ($loan) {
            $fine_amount = 0;
            if (strtotime(date('Y-m-d H:i:s')) > strtotime($loan['due_at'])) {
                $days_late = floor((time() - strtotime($loan['due_at'])) / 86400);
                if ($days_late > 0) {
                    $fine_amount = $days_late * $daily_fine;
                    $pdo->prepare("INSERT INTO fines (user_id, device_loan_id, amount, status, reason) VALUES (?, ?, ?, 'unpaid', 'Overdue device return')")->execute([$loan['user_id'], $loan['id'], $fine_amount]);
                }
            }
            $pdo->prepare("UPDATE device_loans SET returned_at = NOW(), status = 'returned', fine_amount = ? WHERE id = ?")->execute([$fine_amount, $loan_id]);
            $pdo->prepare("UPDATE devices SET status = 'available' WHERE id = ?")->execute([$loan['device_id']]);

            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], 'device_loan.return', "Loan ID: $loan_id, Fine: €$fine_amount", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            $_SESSION['flash_success'] = 'Device returned successfully. ' . ($fine_amount > 0 ? "Fine issued: €$fine_amount" : "No fine issued.");
        } else {
            $_SESSION['flash_error'] = 'Invalid or already returned device loan.';
        }
        header('Location: ' . SITE_URL . 'staff/device_loans.php');
        exit;
    }
}

// ---- Fetch Data (Branch Scoped) ----
$search = trim($_GET['q'] ?? '');
$active_loans = [];
$members = [];
$devices = [];

if ($branch_id) {
    // Active Loans
    $sql = "SELECT dl.*, d.brand, d.model, d.serial_number, dt.name as type_name, u.first_name, u.last_name, u.membership_number 
            FROM device_loans dl 
            JOIN devices d ON d.id = dl.device_id 
            JOIN device_types dt ON dt.id = d.device_type_id 
            JOIN users u ON u.id = dl.user_id 
            WHERE dl.status = 'active' AND dl.branch_id = ?";
    $params = [$branch_id];
    
    if ($search) {
        $sql .= " AND (d.brand LIKE ? OR d.model LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.membership_number LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]);
    }
    $sql .= " ORDER BY dl.due_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $active_loans = $stmt->fetchAll();

    // Fetch Members
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, membership_number FROM users WHERE role = 'member' AND branch_id = ? ORDER BY first_name");
    $stmt->execute([$branch_id]);
    $members = $stmt->fetchAll();

    // Fetch Devices
    $stmt = $pdo->prepare("SELECT d.id, d.brand, d.model, d.serial_number, dt.name as type_name FROM devices d JOIN device_types dt ON dt.id = d.device_type_id WHERE d.status = 'available' AND d.branch_id = ? ORDER BY d.brand, d.model");
    $stmt->execute([$branch_id]);
    $devices = $stmt->fetchAll();
}

$page_title = 'Process Device Loans';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Device Loans</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Process new device loans and handle returns.
    </p>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="flash flash-success" style="margin-bottom: 24px;"><i class="fas fa-check-circle"></i> <?= e($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['flash_error'])): ?>
    <div class="flash flash-error" style="margin-bottom: 24px;"><i class="fas fa-exclamation-triangle"></i> <?= e($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<?php if (!$branch_id): ?>
    <div class="flash flash-error" style="margin-bottom: 24px;"><i class="fas fa-exclamation-triangle"></i> Your account is not assigned to a branch.</div>
<?php endif; ?>

<div class="grid-2" style="align-items: start;">

    <!-- Left: Borrow Form -->
    <div class="card">
        <h3><i class="fas fa-laptop" style="color: #0ea5e9;"></i> Issue New Device</h3>
        <form method="POST" style="margin-top: 20px; display: flex; flex-direction: column; gap: 16px;">
            <input type="hidden" name="action" value="borrow">
            <div>
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Select Member</label>
                <select name="user_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                    <option value="">-- Choose Member --</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= e($m['first_name'] . ' ' . $m['last_name']) ?> (<?= e($m['membership_number']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Select Available Device</label>
                <select name="device_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                    <option value="">-- Choose Device --</option>
                    <?php foreach ($devices as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= e($d['brand'] . ' ' . $d['model']) ?> (<?= e($d['type_name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary" style="padding: 14px; border-radius: 8px; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; border: none; cursor: pointer; font-weight: 600; margin-top: 8px;">
                <i class="fas fa-check"></i> Process Loan
            </button>
        </form>
    </div>

    <!-- Right: Active Loans List -->
    <div class="card" style="display: flex; flex-direction: column; height: 520px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-shrink: 0;">
            <h3 style="margin: 0;"><i class="fas fa-list"></i> Active Device Loans</h3>
            <form method="GET" style="display: flex; gap: 8px; margin: 0;">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search..." style="padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 0.85rem; width: 140px;">
                <button type="submit" class="btn-outline" style="padding: 8px 12px; border-radius: 8px; cursor: pointer;"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <?php if (empty($active_loans)): ?>
            <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No active device loans found.</p>
        <?php else: ?>
            <div style="overflow-y: auto; overflow-x: auto; flex-grow: 1; margin: 0; position: relative; width: 100%; display: block;">
                <table class="modern-table" style="width: 100%; min-width: 700px; border-collapse: collapse; text-align: left; white-space: nowrap;">
                    <thead style="position: sticky; top: 0; z-index: 10; background: var(--card-bg);">
                        <tr>
                            <th style="padding: 12px 8px; background: var(--card-bg); border-bottom: 1px solid var(--border); white-space: nowrap;">Member</th>
                            <th style="padding: 12px 8px; background: var(--card-bg); border-bottom: 1px solid var(--border); white-space: nowrap;">Device</th>
                            <th style="padding: 12px 8px; background: var(--card-bg); border-bottom: 1px solid var(--border); white-space: nowrap;">Due Date</th>
                            <th style="padding: 12px 8px; background: var(--card-bg); border-bottom: 1px solid var(--border); white-space: nowrap;">Status</th>
                            <th style="padding: 12px 8px; background: var(--card-bg); border-bottom: 1px solid var(--border); text-align: right; white-space: nowrap;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_loans as $loan):
                            $is_overdue = strtotime(date('Y-m-d H:i:s')) > strtotime($loan['due_at']);
                        ?>
                            <tr>
                                <td style="padding: 12px 8px; border-bottom: 1px solid var(--border);">
                                    <strong style="display: block; font-size: 0.9rem; white-space: nowrap;"><?= e($loan['first_name'] . ' ' . $loan['last_name']) ?></strong>
                                    <small style="color: var(--text-muted);"><?= e($loan['membership_number']) ?></small>
                                </td>
                                <td style="padding: 12px 8px; border-bottom: 1px solid var(--border);">
                                    <strong style="display: block; font-size: 0.9rem; white-space: nowrap;"><?= e($loan['brand'] . ' ' . $loan['model']) ?></strong>
                                    <small style="color: var(--text-muted);"><?= e($loan['type_name']) ?></small>
                                </td>
                                <td style="padding: 12px 8px; border-bottom: 1px solid var(--border);">
                                    <span style="white-space: nowrap; font-size: 0.85rem;"><?= date('d M Y', strtotime($loan['due_at'])) ?></span>
                                </td>
                                <td style="padding: 12px 8px; border-bottom: 1px solid var(--border);">
                                    <?php if ($is_overdue): ?>
                                        <span style="padding: 4px 10px; background: rgba(239, 68, 68, 0.1); color: var(--danger); border-radius: 20px; font-size: 0.75rem; font-weight: 700;">Overdue</span>
                                    <?php else: ?>
                                        <span style="padding: 4px 10px; background: rgba(16, 185, 129, 0.1); color: var(--success); border-radius: 20px; font-size: 0.75rem; font-weight: 700;">On Time</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 8px; border-bottom: 1px solid var(--border); text-align: right;">
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Process return for this device?');">
                                        <input type="hidden" name="action" value="return">
                                        <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                        <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.75rem; background: <?= $is_overdue ? 'var(--danger)' : 'var(--success)' ?>; color: white; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap;">
                                            <i class="fas fa-undo"></i> Return
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
