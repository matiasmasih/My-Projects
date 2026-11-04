<?php
// user/fines.php — My Fines & Online Payment

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

// ---- HANDLE ONLINE PAYMENT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_fine_id'])) {
    $fine_id = (int)$_POST['pay_fine_id'];
    $payment_method = $_POST['payment_method'] ?? 'card';

    $stmt = $pdo->prepare("SELECT * FROM fines WHERE id = ? AND user_id = ? AND status IN ('unpaid', 'partial')");
    $stmt->execute([$fine_id, $user_id]);
    $fine = $stmt->fetch();

    if ($fine) {
        $amount_to_pay = $fine['amount'] - $fine['paid_amount'];

        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE fines SET paid_amount = amount, status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$fine_id]);

            $invoice_desc = "Online Payment (" . strtoupper($payment_method) . ") for Fine #" . $fine_id;
            $pdo->prepare("INSERT INTO receipts (user_id, fine_id, amount, description, status, paid_at) VALUES (?, ?, ?, ?, 'paid', NOW())")
                ->execute([$user_id, $fine_id, $amount_to_pay, $invoice_desc]);

            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute([$user_id, 'fine.pay_online', "Paid €$amount_to_pay via $payment_method for Fine ID: $fine_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            $pdo->commit();
            $flash = 'Payment successful! Your receipt has been generated.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Payment failed: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid fine record.';
    }
}

// Fetch all fines for this user
$stmt = $pdo->prepare("
    SELECT f.*,
           (SELECT title FROM books b JOIN book_copies bc ON bc.book_id = b.id JOIN loans l ON l.book_copy_id = bc.id WHERE l.id = f.loan_id LIMIT 1) as book_title
    FROM fines f
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id]);
$fines = $stmt->fetchAll();

$total_unpaid = 0;
$total_paid = 0;
foreach ($fines as $f) {
    if ($f['status'] === 'unpaid' || $f['status'] === 'partial') {
        $total_unpaid += ($f['amount'] - $f['paid_amount']);
    }
    $total_paid += $f['paid_amount'];
}

$page_title = 'My Fines';
require_once __DIR__ . '/../includes/user_header.php';
?>

<!-- Hero Banner -->
<div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); padding: 40px; margin-bottom: 32px; position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
    <div style="position: relative; z-index: 2; max-width: 60%;">
        <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text); margin-bottom: 8px;">My Fines</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; margin: 0;">View your outstanding balances and payment history.</p>
    </div>

    <?php if ($total_unpaid > 0): ?>
    <div style="position: relative; z-index: 2; text-align: right;">
        <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Total Outstanding</div>
        <div style="font-size: 2.5rem; font-weight: 800; color: #ef4444;">€<?= number_format($total_unpaid, 2) ?></div>
    </div>
    <?php endif; ?>

    <i class="fas fa-euro-sign" style="position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: #10b981; opacity: 0.05; z-index: 1; transform: rotate(-15deg);"></i>
</div>

<!-- Messages -->
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

<!-- Stat Cards -->
<div class="stat-grid" style="margin-bottom: 32px;">
    <div class="stat-card">
        <div class="stat-icon red" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div>
            <div class="stat-value">€<?= number_format($total_unpaid, 2) ?></div>
            <div class="stat-label">Unpaid Balance</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div>
            <div class="stat-value">€<?= number_format($total_paid, 2) ?></div>
            <div class="stat-label">Total Paid</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
            <i class="fas fa-list-alt"></i>
        </div>
        <div>
            <div class="stat-value"><?= count($fines) ?></div>
            <div class="stat-label">Total Fines</div>
        </div>
    </div>
</div>

<!-- Fines Table -->
<div class="card">
    <h3 style="margin-bottom: 20px;"><i class="fas fa-receipt"></i> Fine History</h3>

    <?php if (empty($fines)): ?>
        <div style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 4rem; color: var(--success); opacity: 0.8; margin-bottom: 16px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 style="color: var(--text); margin-bottom: 8px;">All clear!</h3>
            <p style="color: var(--text-muted); margin: 0;">You have no fines on your account. Keep it up!</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto; width: 100%;">
            <table class="table" style="width: 100%; min-width: 900px; border-collapse: collapse; text-align: left; white-space: nowrap;">
                <thead style="background: var(--card-bg);">
                    <tr>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border);">Date</th>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border);">Reason</th>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border);">Amount</th>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border);">Balance</th>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border);">Status</th>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border); text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fines as $fine):
                        $balance = $fine['amount'] - $fine['paid_amount'];
                    ?>
                        <tr>
                            <td style="padding: 16px 12px; border-bottom: 1px solid var(--border);">
                                <?= date('d M Y', strtotime($fine['created_at'])) ?>
                            </td>
                            <td style="padding: 16px 12px; border-bottom: 1px solid var(--border);">
                                <strong style="display: block; font-size: 0.9rem; color: var(--text);"><?= e($fine['reason']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($fine['book_title'] ?: 'General') ?></small>
                            </td>
                            <td style="padding: 16px 12px; border-bottom: 1px solid var(--border); font-weight: 600;">
                                €<?= number_format($fine['amount'], 2) ?>
                            </td>
                            <td style="padding: 16px 12px; border-bottom: 1px solid var(--border); font-weight: 700; color: <?= $balance > 0 ? '#ef4444' : 'var(--text-muted)' ?>;">
                                €<?= number_format($balance, 2) ?>
                            </td>
                            <td style="padding: 16px 12px; border-bottom: 1px solid var(--border);">
                                <?php if ($fine['status'] === 'paid'): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Paid</span>
                                <?php elseif ($fine['status'] === 'partial'): ?>
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Partial</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 16px 12px; border-bottom: 1px solid var(--border); text-align: right;">
                                <?php if ($balance > 0): ?>
                                    <button type="button" onclick="openPayModal(<?= $fine['id'] ?>, <?= $balance ?>)" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.8rem; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer;">
                                        <i class="fas fa-credit-card"></i> Pay Now
                                    </button>
                                <?php else: ?>
                                    <?php
                                    $rec_stmt = $pdo->prepare("SELECT id FROM receipts WHERE fine_id = ? LIMIT 1");
                                    $rec_stmt->execute([$fine['id']]);
                                    $receipt_row = $rec_stmt->fetch();
                                    ?>
                                    <?php if ($receipt_row): ?>
                                        <a href="<?= e(SITE_URL) ?>user/receipt.php?id=<?= $receipt_row['id'] ?>" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.8rem; background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                            <i class="fas fa-file-invoice"></i> View Receipt
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Payment Modal (Modernized) -->
<div id="payModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <div class="card" style="width: 100%; max-width: 450px; padding: 32px; position: relative; border: 1px solid rgba(16, 185, 129, 0.3); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); color: white; display: grid; place-items: center; margin: 0 auto 16px; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.5);">
                <i class="fas fa-lock" style="font-size: 1.5rem;"></i>
            </div>
            <h3 style="margin: 0 0 8px 0; font-size: 1.5rem; font-weight: 800;">Secure Payment</h3>
            <p style="color: var(--text-muted); margin: 0; font-size: 0.95rem;">Complete your payment of <strong id="modalAmount" style="color: var(--text); font-size: 1.2rem;"></strong></p>
        </div>

        <form method="POST">
            <input type="hidden" name="pay_fine_id" id="modalFineId">

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Payment Method</label>
                <select name="payment_method" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-weight: 600;">
                    <option value="card">💳 Credit / Debit Card</option>
                    <option value="bank">🏦 Bank Transfer (SEPA)</option>
                    <option value="mobile">📱 MobilePay / Apple Pay</option>
                </select>
            </div>

            <div id="cardDetails" style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Card Number</label>
                <input type="text" placeholder="4242 4242 4242 4242" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); margin-bottom: 12px; font-family: monospace;">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Expiry</label>
                        <input type="text" placeholder="MM/YY" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: monospace;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">CVC</label>
                        <input type="text" placeholder="123" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: monospace;">
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closePayModal()" style="padding: 12px 20px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--text); cursor: pointer; font-weight: 600;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 12px 20px; border-radius: 8px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; cursor: pointer; font-weight: 600; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);">Pay Now</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openPayModal(id, amount) {
        document.getElementById('modalFineId').value = id;
        document.getElementById('modalAmount').innerText = '€' + amount.toFixed(2);
        document.getElementById('payModal').style.display = 'flex';
    }

    function closePayModal() {
        document.getElementById('payModal').style.display = 'none';
    }

    // Hide card details if Bank Transfer or Mobile is selected
    document.querySelector('select[name="payment_method"]').addEventListener('change', function() {
        document.getElementById('cardDetails').style.display = (this.value === 'card') ? 'block' : 'none';
    });
</script>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
