<?php
// staff/device_transfers.php — Device Transfers (Send & Receive)

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'staff' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$flash = '';
$error = '';

// ---- HANDLE ACTIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. INITIATE TRANSFER (Send a device)
    if ($action === 'initiate') {
        $device_id = (int)($_POST['device_id'] ?? 0);
        $to_branch_id = (int)($_POST['to_branch_id'] ?? 0);

        try {
            $pdo->beginTransaction();
            // Verify device belongs to this branch and is available
            $stmt = $pdo->prepare("SELECT id FROM devices WHERE id = ? AND branch_id = ? AND status = 'available'");
            $stmt->execute([$device_id, $branch_id]);
            if (!$stmt->fetch()) throw new Exception('Device not available in your branch.');

            // Create transfer
            $pdo->prepare("INSERT INTO device_transfers (device_id, from_branch_id, to_branch_id, status, requested_by, created_at) VALUES (?, ?, ?, 'pending', ?, NOW())")
                ->execute([$device_id, $branch_id, $to_branch_id, $_SESSION['user_id']]);

            // Set device to in_transit
            $pdo->prepare("UPDATE devices SET status = 'in_transit' WHERE id = ?")->execute([$device_id]);

            $pdo->commit();
            $flash = 'Device marked as In Transit. It will arrive at the destination branch soon.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Transfer failed: ' . $e->getMessage();
        }
    }

    // 2. RECEIVE TRANSFER (Accept incoming device)
    if ($action === 'receive') {
        $transfer_id = (int)($_POST['transfer_id'] ?? 0);

        try {
            $pdo->beginTransaction();
            // Verify transfer is destined for this branch and is pending
            $stmt = $pdo->prepare("SELECT * FROM device_transfers WHERE id = ? AND to_branch_id = ? AND status = 'pending'");
            $stmt->execute([$transfer_id, $branch_id]);
            $transfer = $stmt->fetch();
            
            if (!$transfer) throw new Exception('Invalid transfer or already received.');

            // Update transfer status
            $pdo->prepare("UPDATE device_transfers SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$transfer_id]);

            // Move the device to this branch and make it available
            $pdo->prepare("UPDATE devices SET branch_id = ?, status = 'available' WHERE id = ?")->execute([$branch_id, $transfer['device_id']]);

            $pdo->commit();
            $flash = 'Device received and added to your branch inventory.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Receive failed: ' . $e->getMessage();
        }
    }
}

// ---- FETCH DATA ----
$available_devices = [];
$incoming_transfers = [];
$outgoing_transfers = [];
$other_branches = [];

if ($branch_id) {
    // Get all available devices in this branch to send
    $stmt = $pdo->prepare("
        SELECT d.id, d.brand, d.model, d.serial_number, dt.name AS type_name 
        FROM devices d 
        JOIN device_types dt ON dt.id = d.device_type_id
        WHERE d.branch_id = ? AND d.status = 'available'
        ORDER BY d.brand, d.model
    ");
    $stmt->execute([$branch_id]);
    $available_devices = $stmt->fetchAll();

    // Get all other branches
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id != ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$branch_id]);
    $other_branches = $stmt->fetchAll();

    // Incoming Transfers (Coming to my branch)
    $stmt = $pdo->prepare("
        SELECT t.*, d.brand, d.model, d.serial_number, br.name AS from_branch_name 
        FROM device_transfers t 
        JOIN devices d ON d.id = t.device_id 
        JOIN branches br ON br.id = t.from_branch_id
        WHERE t.to_branch_id = ? AND t.status = 'pending'
    ");
    $stmt->execute([$branch_id]);
    $incoming_transfers = $stmt->fetchAll();

    // Outgoing Transfers (Sent by my branch)
    $stmt = $pdo->prepare("
        SELECT t.*, d.brand, d.model, d.serial_number, br.name AS to_branch_name 
        FROM device_transfers t 
        JOIN devices d ON d.id = t.device_id 
        JOIN branches br ON br.id = t.to_branch_id
        WHERE t.from_branch_id = ? AND t.status = 'pending'
    ");
    $stmt->execute([$branch_id]);
    $outgoing_transfers = $stmt->fetchAll();
}

$page_title = 'Device Transfers';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Device Transfers</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Send devices to other branches and receive incoming shipments.
    </p>
</div>

<?php if ($flash): ?><div class="flash flash-success" style="margin-bottom: 24px;"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error" style="margin-bottom: 24px;"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2" style="align-items: start;">

    <!-- Left Column: Send a Device -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <div class="card">
            <h3><i class="fas fa-paper-plane" style="color: #0ea5e9;"></i> Send a Device</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Select an available device from your branch to send to another branch.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="initiate">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label>Select Available Device</label>
                    <select name="device_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                        <option value="">-- Choose Device --</option>
                        <?php foreach ($available_devices as $dev): ?>
                            <option value="<?= $dev['id'] ?>">
                                <?= e($dev['brand'] . ' ' . $dev['model']) ?> (<?= e($dev['serial_number']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <label>Destination Branch</label>
                    <select name="to_branch_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                        <option value="">-- Choose Branch --</option>
                        <?php foreach ($other_branches as $br): ?>
                            <option value="<?= $br['id'] ?>"><?= e($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #0ea5e9, #14b8a6); border: none; border-radius: 8px; font-weight: 600; color: white; cursor: pointer;">
                    <i class="fas fa-truck"></i> Mark as In Transit
                </button>
            </form>
        </div>

        <!-- Outgoing Transfers -->
        <div class="card">
            <h3><i class="fas fa-arrow-up" style="color: var(--warning);"></i> Outgoing (In Transit)</h3>
            <?php if (empty($outgoing_transfers)): ?>
                <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No outgoing device transfers.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 16px;">
                    <?php foreach ($outgoing_transfers as $t): ?>
                        <div style="padding: 12px; background: var(--bg-alt); border-radius: 8px; border: 1px solid var(--border);">
                            <strong style="display: block; color: var(--text);"><?= e($t['brand'] . ' ' . $t['model']) ?></strong>
                            <small style="color: var(--text-muted);">Sent to: <?= e($t['to_branch_name']) ?></small>
                            <div style="margin-top: 8px;">
                                <span style="padding: 4px 8px; background: rgba(245, 158, 11, 0.1); color: var(--warning); border-radius: 4px; font-size: 0.75rem; font-weight: 700;">IN TRANSIT</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Receive a Device -->
    <div class="card">
        <h3><i class="fas fa-inbox" style="color: var(--success);"></i> Incoming Shipments</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Devices that other branches have sent to you.</p>

        <?php if (empty($incoming_transfers)): ?>
            <p style="color: var(--text-muted); padding: 40px 20px; text-align: center;">No incoming device shipments right now.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($incoming_transfers as $t): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--bg-alt); border-radius: 12px; border: 1px solid var(--border);">
                        <div>
                            <strong style="display: block; color: var(--text);"><?= e($t['brand'] . ' ' . $t['model']) ?></strong>
                            <small style="color: var(--text-muted);">From: <?= e($t['from_branch_name']) ?></small>
                        </div>
                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Confirm receipt of this device?');">
                            <input type="hidden" name="action" value="receive">
                            <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.8rem; background: var(--success); border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-check"></i> Receive
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
