<?php
// user/device_loans.php — My Device Loans & Returns

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fixed role check
if (!$user || ($user['role'] !== 'member' && $user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$flash = '';
$error = '';

// ---- HANDLE DEVICE RETURN ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_loan_id'])) {
    $loan_id = (int)$_POST['return_loan_id'];

    $stmt = $pdo->prepare("SELECT * FROM device_loans WHERE id = ? AND user_id = ? AND status = 'active'");
    $stmt->execute([$loan_id, $user_id]);
    $loan = $stmt->fetch();

    if ($loan) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE device_loans SET returned_at = NOW(), status = 'returned' WHERE id = ?")->execute([$loan_id]);
            $pdo->prepare("UPDATE devices SET status = 'available' WHERE id = ?")->execute([$loan['device_id']]);

            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute([$user_id, 'device_loan.return_self', "Member returned Device Loan ID: $loan_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            $pdo->commit();
            $flash = 'Device returned successfully! Thank you.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to process return. Please try again.';
        }
    } else {
        $error = 'Invalid device loan record.';
    }
}

// ---- SEARCH LOGIC ----
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT dl.*, d.brand, d.model, d.image, d.serial_number, dt.name as type_name
    FROM device_loans dl
    JOIN devices d ON d.id = dl.device_id
    JOIN device_types dt ON dt.id = d.device_type_id
    WHERE dl.user_id = ?
";
$params = [$user_id];

if ($search) {
    $sql .= " AND (d.brand LIKE ? OR d.model LIKE ? OR d.serial_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY dl.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();

$page_title = 'My Device Loans';
require_once __DIR__ . '/../includes/user_header.php';
?>

<!-- Hero Banner with Search -->
<div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); padding: 40px; margin-bottom: 32px; position: relative; overflow: hidden;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; position: relative; z-index: 2;">
        <div style="max-width: 50%;">
            <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text); margin-bottom: 8px;">My Device Loans</h1>
            <p style="color: var(--text-muted); font-size: 1.1rem; margin: 0;">Track your borrowed devices and return them when you are done.</p>
        </div>

        <!-- Search Form -->
        <div style="width: 100%; max-width: 400px;">
            <form method="GET" style="display: flex; gap: 8px; margin: 0;">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search my devices..." style="padding: 14px 20px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: var(--text); font-size: 0.95rem; width: 100%; backdrop-filter: blur(10px);">
                <button type="submit" class="btn btn-primary" style="padding: 14px 24px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; cursor: pointer; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </div>

    <i class="fas fa-laptop" style="position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: #10b981; opacity: 0.05; z-index: 1; transform: rotate(-15deg);"></i>
</div>

<?php if ($flash): ?>
    <div class="flash flash-success" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; color: #10b981; font-weight: 600;">
        <i class="fas fa-check-circle"></i> <?= e($flash) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="flash flash-error" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; color: #ef4444; font-weight: 600;">
        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if (empty($loans)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
        <i class="fas fa-laptop" style="font-size: 4rem; color: var(--border); margin-bottom: 16px;"></i>
        <h3 style="color: var(--text); margin-bottom: 8px;">No device loans found</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px;"><?= $search ? 'No loans match your search.' : 'You haven\'t borrowed any devices yet.' ?></p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
        <?php foreach ($loans as $loan):
            $is_overdue = ($loan['status'] === 'active' && strtotime(date('Y-m-d H:i:s')) > strtotime($loan['due_at']));
        ?>
            <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">

                <!-- Device Image -->
                <div style="height: 200px; background: var(--bg-alt); display: flex; align-items: center; justify-content: center; position: relative;">
                    <?php if (!empty($loan['image']) && file_exists(__DIR__ . '/../uploads/' . $loan['image'])): ?>
                        <img src="<?= UPLOADS_URL . e($loan['image']) ?>" alt="Device" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-laptop" style="font-size: 4rem; color: var(--border);"></i>
                    <?php endif; ?>

                    <!-- Status Badge -->
                    <div style="position: absolute; top: 12px; right: 12px; z-index: 10;">
                        <?php if ($loan['status'] === 'returned'): ?>
                            <span class="badge" style="background: var(--bg-alt); color: var(--text-muted); border: 1px solid var(--border); padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;">Returned</span>
                        <?php elseif ($is_overdue): ?>
                            <span class="badge" style="background: #ef4444; color: white; padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; box-shadow: 0 4px 10px rgba(239,68,68,0.4);">Overdue</span>
                        <?php else: ?>
                            <span class="badge" style="background: #10b981; color: white; padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; box-shadow: 0 4px 10px rgba(16,185,129,0.4);">Active</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Device Info -->
                <div style="padding: 20px; flex-grow: 1; display: flex; flex-direction: column;">
                    <h4 style="margin: 0 0 4px 0; font-size: 1.1rem; color: var(--text); line-height: 1.3;"><?= e($loan['brand'] . ' ' . $loan['model']) ?></h4>
                    <p style="margin: 0 0 16px 0; color: var(--text-muted); font-size: 0.85rem;"><?= e($loan['type_name']) ?> · SN: <?= e($loan['serial_number']) ?></p>

                    <div style="margin-top: auto; border-top: 1px solid var(--border); padding-top: 16px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Due Date</div>
                            <div style="font-weight: 700; font-size: 0.95rem; color: <?= $is_overdue ? '#ef4444' : 'var(--text)' ?>;">
                                <?= date('d M Y', strtotime($loan['due_at'])) ?>
                            </div>
                        </div>

                        <?php if ($loan['status'] === 'active'): ?>
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to return this device?');">
                                <input type="hidden" name="return_loan_id" value="<?= $loan['id'] ?>">
                                <button type="submit" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 6px; background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#ef4444'; this.style.color='white'; this.style.borderColor='#ef4444';" onmouseout="this.style.background='var(--bg-alt)'; this.style.color='var(--text)'; this.style.borderColor='var(--border)';">
                                    <i class="fas fa-undo"></i> Return
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">Returned</span>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
