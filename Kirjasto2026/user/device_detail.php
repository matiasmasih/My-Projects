<?php
// user/device_detail.php — Device Details, Borrow, Reserve & Transfer

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || ($user['role'] !== 'member' && $user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$device_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$stmt = $pdo->prepare("
    SELECT d.*, dt.name as type_name, dt.loan_days, dt.description as type_desc
    FROM devices d
    JOIN device_types dt ON dt.id = d.device_type_id
    WHERE d.id = ?
");
$stmt->execute([$device_id]);
$device = $stmt->fetch();

if (!$device) {
    header('Location: ' . SITE_URL . 'user/devices.php');
    exit;
}

$is_local = ($device['branch_id'] == $branch_id);
$flash = '';
$error = '';

// ---- HANDLE RESERVE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reserve') {
    $check_res = $pdo->prepare("SELECT id FROM device_reservations WHERE user_id = ? AND device_id = ? AND status IN ('pending', 'ready')");
    $check_res->execute([$user_id, $device_id]);

    if ($check_res->fetch()) {
        $error = 'You already have an active reservation for this device.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO device_reservations (user_id, device_id, reserved_at, status) VALUES (?, ?, NOW(), 'pending')")->execute([$user_id, $device_id]);
            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute([$user_id, 'device_reservation.create', "Member reserved Device ID: $device_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            $pdo->commit();
            $_SESSION['flash_success'] = 'Device reserved successfully! You will be notified when it becomes available.';
            header('Location: ' . SITE_URL . 'user/reservations.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to reserve device: ' . $e->getMessage();
        }
    }
}

// ---- HANDLE BORROW ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'borrow') {
    $check_loan = $pdo->prepare("SELECT id FROM device_loans WHERE user_id = ? AND device_id = ? AND status = 'active'");
    $check_loan->execute([$user_id, $device_id]);

    if (!$is_local) {
        $error = 'This device belongs to another branch. You cannot borrow it directly.';
    } elseif ($check_loan->fetch()) {
        $error = 'You already have an active loan for this device.';
    } elseif ($device['status'] === 'available') {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO device_loans (user_id, device_id, borrowed_at, due_at, status, branch_id) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active', ?)");
            $stmt->execute([$user_id, $device_id, $device['loan_days'], $branch_id]);
            $pdo->prepare("UPDATE devices SET status = 'borrowed' WHERE id = ?")->execute([$device_id]);
            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute([$user_id, 'device_loan.borrow_self', "Member borrowed Device ID: $device_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            $pdo->commit();
            $_SESSION['flash_success'] = 'Device borrowed successfully! Please pick it up at the front desk.';
            header('Location: ' . SITE_URL . 'user/device_loans.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to borrow device: ' . $e->getMessage();
        }
    } else {
        $error = 'This device is not currently available.';
    }
}

// ---- HANDLE TRANSFER REQUEST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_transfer') {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id FROM devices WHERE id = ? AND status = 'available' AND branch_id = ?");
        $stmt->execute([$device_id, $device['branch_id']]);
        if (!$stmt->fetch()) throw new Exception('Device is no longer available.');

        $pdo->prepare("INSERT INTO device_transfers (device_id, from_branch_id, to_branch_id, status, requested_by, created_at) VALUES (?, ?, ?, 'pending', ?, NOW())")
            ->execute([$device_id, $device['branch_id'], $branch_id, $user_id]);
        $pdo->prepare("UPDATE devices SET status = 'in_transit' WHERE id = ?")->execute([$device_id]);
        $pdo->commit();
        $_SESSION['flash_success'] = 'Transfer requested! The device is now in transit to your branch.';
        header('Location: ' . SITE_URL . 'user/device_detail.php?id=' . $device_id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to request transfer: ' . $e->getMessage();
    }
}

$page_title = $device['brand'] . ' ' . $device['model'];
require_once __DIR__ . '/../includes/user_header.php';
?>

<div style="margin-bottom: 32px;">
    <a href="<?= e(SITE_URL) ?>user/devices.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 600;">
        <i class="fas fa-arrow-left"></i> Back to Devices
    </a>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="flash flash-success" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; color: #10b981; font-weight: 600;">
        <i class="fas fa-check-circle"></i> <?= e($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if ($error): ?>
    <div class="flash flash-error" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; color: #ef4444; font-weight: 600;">
        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 40px; align-items: start;">
    <div style="position: relative; margin-top: 50%;">
        <div style="position: absolute; top: 20px; left: 20px; width: 100%; height: 100%; background: #10b981; filter: blur(40px); opacity: 0.2; border-radius: 16px; z-index: 0;"></div>
        <div class="card" style="padding: 0; overflow: hidden; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); position: relative; z-index: 1; border: 1px solid rgba(255,255,255,0.1);">
            <div style="height: 320px; background: var(--bg-alt); display: flex; align-items: center; justify-content: center;">
                <?php if (!empty($device['image']) && file_exists(__DIR__ . '/../uploads/' . $device['image'])): ?>
                    <img src="<?= UPLOADS_URL . e($device['image']) ?>" alt="<?= e($device['brand'] . ' ' . $device['model']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted);">
                        <i class="fas fa-laptop" style="font-size: 5rem; opacity: 0.3; margin-bottom: 16px;"></i>
                        <span style="font-weight: 600;">No Image Available</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="margin-top: 24px; display: flex; justify-content: center; flex-direction: column; gap: 8px;">
            <?php if ($is_local && $device['status'] === 'available'): ?>
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 10px 20px; border-radius: 50px; font-weight: 700; font-size: 0.9rem; border: 1px solid rgba(16, 185, 129, 0.3);">
                    <i class="fas fa-check-circle"></i> Available Here
                </div>
            <?php elseif ($is_local && $device['status'] !== 'available'): ?>
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 10px 20px; border-radius: 50px; font-weight: 700; font-size: 0.9rem; border: 1px solid rgba(245, 158, 11, 0.3);">
                    <i class="fas fa-clock"></i> Currently Borrowed
                </div>
            <?php elseif (!$is_local && $device['status'] === 'available'): ?>
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(14, 165, 233, 0.15); color: #0ea5e9; padding: 10px 20px; border-radius: 50px; font-weight: 700; font-size: 0.9rem; border: 1px solid rgba(14, 165, 233, 0.3);">
                    <i class="fas fa-truck"></i> At Another Branch
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <h1 style="font-size: 3rem; font-weight: 800; color: var(--text); margin: 0 0 12px 0; line-height: 1.1;"><?= e($device['brand'] . ' ' . $device['model']) ?></h1>
        <p style="font-size: 1.3rem; color: #10b981; margin: 0 0 32px 0; font-weight: 700;"><?= e($device['type_name']) ?></p>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 40px;">
            <div class="card" style="padding: 16px; text-align: center;">
                <i class="fas fa-barcode" style="color: var(--text-muted); margin-bottom: 8px;"></i>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Serial #</div>
                <div style="font-weight: 700; font-size: 0.85rem; margin-top: 4px;"><?= e($device['serial_number']) ?></div>
            </div>
            <div class="card" style="padding: 16px; text-align: center;">
                <i class="fas fa-shield-alt" style="color: var(--text-muted); margin-bottom: 8px;"></i>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Condition</div>
                <div style="font-weight: 700; font-size: 0.9rem; margin-top: 4px; text-transform: capitalize;"><?= e($device['condition']) ?></div>
            </div>
            <div class="card" style="padding: 16px; text-align: center;">
                <i class="fas fa-map-marker-alt" style="color: var(--text-muted); margin-bottom: 8px;"></i>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Location</div>
                <div style="font-weight: 700; font-size: 0.9rem; margin-top: 4px;"><?= e($device['location'] ?: 'Main Library') ?></div>
            </div>
            <div class="card" style="padding: 16px; text-align: center;">
                <i class="fas fa-clock" style="color: var(--text-muted); margin-bottom: 8px;"></i>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Loan Period</div>
                <div style="font-weight: 700; font-size: 0.9rem; margin-top: 4px;"><?= e($device['loan_days']) ?> Days</div>
            </div>
        </div>

        <div style="margin-bottom: 40px;">
            <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-align-left" style="color: #10b981;"></i> About this <?= e($device['type_name']) ?>
            </h3>
            <p style="color: var(--text-muted); line-height: 1.8; font-size: 1.05rem;">
                <?= nl2br(e($device['type_desc'] ?: 'No description available for this device.')) ?>
            </p>
        </div>

        <!-- Action Area -->
        <div class="card" style="padding: 32px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), transparent); border: 1px solid rgba(16, 185, 129, 0.3);">
            <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 20px;">Choose an action</h3>

            <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                <?php if ($is_local && $device['status'] === 'available'): ?>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="borrow">
                        <button type="submit" class="btn-primary" style="padding: 16px 40px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; font-weight: 700; font-size: 1.1rem; cursor: pointer; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.5); display: inline-flex; align-items: center; gap: 10px; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';">
                            <i class="fas fa-laptop"></i> Borrow Device (<?= e($device['loan_days']) ?> days)
                        </button>
                    </form>
                <?php elseif (!$is_local && $device['status'] === 'available'): ?>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="request_transfer">
                        <button type="submit" class="btn-primary" style="padding: 16px 40px; border-radius: 10px; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; border: none; font-weight: 700; font-size: 1.1rem; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';">
                            <i class="fas fa-truck"></i> Request Transfer to <?= e($branch_name) ?>
                        </button>
                    </form>
                <?php endif; ?>

                <!-- RESERVE BUTTON (Always visible) -->
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="reserve">
                    <button type="submit" class="btn-secondary" style="padding: 16px 40px; border-radius: 10px; background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); font-weight: 700; font-size: 1.1rem; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: all 0.2s;" onmouseover="this.style.background='#f59e0b'; this.style.color='white'; this.style.borderColor='#f59e0b';" onmouseout="this.style.background='var(--bg-alt)'; this.style.color='var(--text)'; this.style.borderColor='var(--border)';">
                        <i class="fas fa-bookmark"></i> Reserve Device
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
