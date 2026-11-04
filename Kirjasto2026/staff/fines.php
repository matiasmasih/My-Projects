<?php
// staff/fines.php — Manage Fines (Branch Scoped)

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

// ---- Handle POST Actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'pay') {
        $fine_id = (int)($_POST['fine_id'] ?? 0);
        $amount_paid = (float)($_POST['amount_paid'] ?? 0);

        // Verify fine belongs to a member in this branch
        $stmt = $pdo->prepare("
            SELECT f.* FROM fines f 
            JOIN users u ON u.id = f.user_id 
            WHERE f.id = ? AND f.status IN ('unpaid', 'partial') AND u.branch_id = ?
        ");
        $stmt->execute([$fine_id, $branch_id]);
        $fine = $stmt->fetch();

        if ($fine && $amount_paid > 0) {
            $new_paid_amount = $fine['paid_amount'] + $amount_paid;
            $new_status = ($new_paid_amount >= $fine['amount']) ? 'paid' : 'partial';
            $paid_at = ($new_status === 'paid') ? date('Y-m-d H:i:s') : null;

            $stmt = $pdo->prepare("UPDATE fines SET paid_amount = ?, status = ?, paid_at = ? WHERE id = ?");
            $stmt->execute([$new_paid_amount, $new_status, $paid_at, $fine_id]);

            $stmt = $pdo->prepare("INSERT INTO receipts (user_id, fine_id, amount, description, status, paid_at) VALUES (?, ?, ?, 'Fine Payment', 'paid', NOW())");
            $stmt->execute([$fine['user_id'], $fine_id, $amount_paid]);

            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute([$_SESSION['user_id'], 'fine.pay', "Fine ID: $fine_id, Amount Paid: €$amount_paid", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            $_SESSION['flash_success'] = "Payment of €$amount_paid processed successfully.";
        } else {
            $_SESSION['flash_error'] = 'Invalid fine, payment amount, or fine belongs to another branch.';
        }
        header('Location: ' . SITE_URL . 'staff/fines.php');
        exit;
    }
}

// ---- Fetch Data (Branch Scoped) ----
$search = trim($_GET['q'] ?? '');
$fines = [];

if ($branch_id) {
    $sql = "
        SELECT f.*, u.first_name, u.last_name, u.membership_number, b.title as book_title
        FROM fines f
        JOIN users u ON u.id = f.user_id
        LEFT JOIN loans l ON l.id = f.loan_id
        LEFT JOIN book_copies bc ON bc.id = l.book_copy_id
        LEFT JOIN books b ON b.id = bc.book_id
        WHERE f.status IN ('unpaid', 'partial') AND u.branch_id = ?
    ";
    $params = [$branch_id];
    
    if ($search) {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.membership_number LIKE ?)";
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
    $sql .= " ORDER BY f.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $fines = $stmt->fetchAll();
}

$total_outstanding = 0;
foreach ($fines as $f) {
    $total_outstanding += ($f['amount'] - $f['paid_amount']);
}

$page_title = 'Manage Fines';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Manage Fines</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Process fine payments and view outstanding balances for library members.
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

<!-- Stat Cards -->
<div class="stat-grid" style="margin-bottom: 32px;">
    <div class="stat-card">
        <div class="stat-icon red" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
            <i class="fas fa-euro-sign"></i>
        </div>
        <div>
            <div class="stat-value">€<?= number_format($total_outstanding, 2) ?></div>
            <div class="stat-label">Total Outstanding</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
            <i class="fas fa-file-invoice"></i>
        </div>
        <div>
            <div class="stat-value"><?= count($fines) ?></div>
            <div class="stat-label">Unpaid Invoices</div>
        </div>
    </div>
</div>

<!-- Full Width Card for Fines List -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;"><i class="fas fa-list"></i> Unpaid & Partial Fines</h3>
        <form method="GET" style="display: flex; gap: 8px; margin: 0;">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search member..." style="padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 0.85rem; width: 200px;">
            <button type="submit" class="btn-outline" style="padding: 8px 12px; border-radius: 8px; cursor: pointer;"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <?php if (empty($fines)): ?>
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 400px; width: 100%; text-align: center;">
            <div style="font-size: 5rem; color: var(--success); opacity: 0.9; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 style="color: var(--text); margin-bottom: 8px; font-size: 1.5rem; font-weight: 800;">All clear!</h3>
            <p style="color: var(--text-muted); margin: 0; font-size: 1.1rem;">There are no unpaid fines for your branch at the moment.</p>
        </div>

    <?php else: ?>
        <div style="overflow-x: auto; width: 100%;">
            <table class="modern-table" style="width: 100%; min-width: 800px; border-collapse: collapse; text-align: left; white-space: nowrap;">
                <thead style="background: var(--card-bg);">
                    <tr>
                        <th style="padding: 12px 8px; border-bottom: 1px solid var(--border);">Member</th>
                        <th style="padding: 12px 8px; border-bottom: 1px solid var(--border);">Reason</th>
                        <th style="padding: 12px 8px; border-bottom: 1px solid var(--border);">Total Fine</th>
                        <th style="padding: 12px 8px; border-bottom: 1px solid var(--border);">Paid</th>
                        <th style="padding: 12px 8px; border-bottom: 1px solid var(--border);">Balance</th>
                        <th style="padding: 12px 8px; border-bottom: 1px solid var(--border); text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fines as $fine):
                        $balance = $fine['amount'] - $fine['paid_amount'];
                    ?>
                        <tr>
                            <td style="padding: 12px 8px; border-bottom: 1px solid var(--border);">
                                <strong style="display: block; font-size: 0.9rem;"><?= e($fine['first_name'] . ' ' . $fine['last_name']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($fine['membership_number']) ?></small>
                            </td>
                            <td style="padding: 12px 8px; border-bottom: 1px solid var(--border);">
                                <?= e($fine['reason']) ?><br>
                                <small style="color: var(--text-muted);"><?= e($fine['book_title'] ?: 'Book Return') ?></small>
                            </td>
                            <td style="padding: 12px 8px; border-bottom: 1px solid var(--border);">
                                €<?= number_format($fine['amount'], 2) ?>
                            </td>
                            <td style="padding: 12px 8px; border-bottom: 1px solid var(--border); color: var(--success);">
                                €<?= number_format($fine['paid_amount'], 2) ?>
                            </td>
                            <td style="padding: 12px 8px; border-bottom: 1px solid var(--border); font-weight: 700; color: var(--danger);">
                                €<?= number_format($balance, 2) ?>
                            </td>
                            <td style="padding: 12px 8px; border-bottom: 1px solid var(--border); text-align: right;">
                                <button type="button" class="btn-primary" onclick="openPayModal(<?= $fine['id'] ?>, '<?= e(addslashes($fine['first_name'] . ' ' . $fine['last_name'])) ?>', <?= $balance ?>)" style="padding: 6px 12px; font-size: 0.75rem; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap;">
                                    <i class="fas fa-money-bill-wave"></i> Collect
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Collect Payment Modal -->
<div id="payModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 400px; padding: 32px; position: relative;">
        <h3 style="margin-top: 0; margin-bottom: 16px;"><i class="fas fa-cash-register" style="color: #0ea5e9;"></i> Collect Payment</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px;">Processing payment for <strong id="modalMemberName" style="color: var(--text);"></strong></p>

        <form method="POST">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="fine_id" id="modalFineId">

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Amount to Pay (€)</label>
                <input type="number" name="amount_paid" id="modalAmount" step="0.01" min="0.01" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 1.2rem; font-weight: 700;">
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn-outline" onclick="closePayModal()" style="padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 600;">Cancel</button>
                <button type="submit" class="btn-primary" style="padding: 10px 16px; border-radius: 8px; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; border: none; cursor: pointer; font-weight: 600;">Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPayModal(id, name, balance) {
        document.getElementById('modalFineId').value = id;
        document.getElementById('modalMemberName').innerText = name;
        document.getElementById('modalAmount').value = balance.toFixed(2);
        document.getElementById('payModal').style.display = 'flex';
    }

    function closePayModal() {
        document.getElementById('payModal').style.display = 'none';
    }
</script>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
