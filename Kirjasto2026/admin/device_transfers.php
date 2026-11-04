<?php
// admin/device_transfers.php — Track devices in transit between branches
// Kirjasto 2026 — Phase 9

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';
$error = '';

// ---- COMPLETE TRANSFER ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_transfer') {
    $transfer_id = (int)$_POST['transfer_id'];
    $stmt = $pdo->prepare("SELECT * FROM device_transfers WHERE id = ? AND status = 'pending'");
    $stmt->execute([$transfer_id]);
    $transfer = $stmt->fetch();

    if ($transfer) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE device_transfers SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$transfer_id]);
            $pdo->prepare("UPDATE devices SET branch_id = ?, status = 'available' WHERE id = ?")->execute([$transfer['to_branch_id'], $transfer['device_id']]);
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'device_transfer.complete', "Device transfer ID $transfer_id completed", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            $pdo->commit();
            $flash = 'Device transfer completed. Device is now at the destination branch.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to complete transfer: ' . $e->getMessage();
        }
    }
}

// ---- CANCEL TRANSFER ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_transfer') {
    $transfer_id = (int)$_POST['transfer_id'];
    $stmt = $pdo->prepare("SELECT * FROM device_transfers WHERE id = ? AND status = 'pending'");
    $stmt->execute([$transfer_id]);
    $transfer = $stmt->fetch();
    if ($transfer) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE device_transfers SET status = 'cancelled' WHERE id = ?")->execute([$transfer_id]);
        $pdo->prepare("UPDATE devices SET status = 'available' WHERE id = ?")->execute([$transfer['device_id']]);
        $pdo->commit();
        $flash = 'Device transfer cancelled.';
    }
}

// ---- FETCH ----
$transfers = $pdo->query("
    SELECT t.*, CONCAT(d.brand, ' ', d.model) AS device_name, d.serial_number,
           f.name AS from_branch_name, tb.name AS to_branch_name,
           u.first_name AS req_first, u.last_name AS req_last
    FROM device_transfers t
    JOIN devices d ON d.id = t.device_id
    JOIN branches f ON f.id = t.from_branch_id
    JOIN branches tb ON tb.id = t.to_branch_id
    JOIN users u ON u.id = t.requested_by
    ORDER BY CASE WHEN t.status = 'pending' THEN 0 ELSE 1 END, t.created_at DESC
")->fetchAll();

$pending_count = 0;
foreach ($transfers as $t) { if ($t['status'] === 'pending') $pending_count++; }

$page_title = 'Device Transfers';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Device Transfers</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        Track devices in transit between branches.
        <?php if ($pending_count > 0): ?><strong style="color: #f59e0b;"><?= $pending_count ?> in transit</strong><?php endif; ?>
    </p>
</div>

<?php if ($flash): ?><div class="flash flash-success" style="margin-bottom: 24px;"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error" style="margin-bottom: 24px;"><?= e($error) ?></div><?php endif; ?>

<?php if (empty($transfers)): ?>
    <div class="card" style="text-align: center; padding: 80px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
        <i class="fas fa-truck-loading" style="font-size: 4rem; color: var(--border); margin-bottom: 16px;"></i>
        <h3 style="color: var(--text); margin-bottom: 8px;">No device transfers</h3>
        <p style="color: var(--text-muted); margin: 0;">Devices will appear here when they travel between branches.</p>
    </div>
<?php else: ?>
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; text-align: left; white-space: nowrap;">
                <thead style="background: var(--bg-alt);">
                    <tr>
                        <th style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Device</th>
                        <th style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Route</th>
                        <th style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Requested By</th>
                        <th style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted);">Status</th>
                        <th style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfers as $t): ?>
                        <tr>
                            <td style="padding: 16px 20px; border-bottom: 1px solid var(--border);">
                                <strong style="display: block; color: var(--text);"><?= e($t['device_name']) ?></strong>
                                <small style="color: var(--text-muted);">SN: <?= e($t['serial_number']) ?></small>
                            </td>
                            <td style="padding: 16px 20px; border-bottom: 1px solid var(--border);">
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem;">
                                    <span style="color: var(--text-muted);"><?= e($t['from_branch_name']) ?></span>
                                    <i class="fas fa-arrow-right" style="color: #3b82f6;"></i>
                                    <strong style="color: var(--text);"><?= e($t['to_branch_name']) ?></strong>
                                </div>
                            </td>
                            <td style="padding: 16px 20px; border-bottom: 1px solid var(--border); color: var(--text-muted);"><?= e($t['req_first'] . ' ' . $t['req_last']) ?></td>
                            <td style="padding: 16px 20px; border-bottom: 1px solid var(--border);">
                                <?php if ($t['status'] === 'pending'): ?>
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;">In Transit</span>
                                <?php elseif ($t['status'] === 'completed'): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;">Received</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;">Cancelled</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 16px 20px; border-bottom: 1px solid var(--border); text-align: right;">
                                <?php if ($t['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline-block; margin-right: 8px;" onsubmit="return confirm('Confirm this device arrived?');">
                                        <input type="hidden" name="action" value="complete_transfer">
                                        <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                                        <button type="submit" style="padding: 6px 14px; font-size: 0.75rem; border-radius: 50px; border: 1px solid rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.1); color: #10b981; cursor: pointer; font-weight: 700;"><i class="fas fa-check"></i> Received</button>
                                    </form>
                                    <form method="POST" style="display: inline-block;" onsubmit="return confirm('Cancel this transfer?');">
                                        <input type="hidden" name="action" value="cancel_transfer">
                                        <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                                        <button type="submit" style="padding: 6px 14px; font-size: 0.75rem; border-radius: 50px; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.1); color: #ef4444; cursor: pointer; font-weight: 700;"><i class="fas fa-times"></i> Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
