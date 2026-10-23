<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'user') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$flash = '';
$error = '';

// Handle Transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer') {
    $from_acc_no = trim($_POST['from_acc']);
    $to_acc_no = trim($_POST['to_acc']);
    $amount = (float)$_POST['amount'];
    $pin = $_POST['pin'];

    if ($amount <= 0) {
        $error = 'Amount must be greater than 0.';
    } elseif ($from_acc_no === $to_acc_no) {
        $error = 'You cannot transfer to the same account.';
    } else {
        // Verify Source Account (must belong to user)
        $stmtFrom = $pdo->prepare("SELECT * FROM accounts WHERE account_number = ? AND user_id = ?");
        $stmtFrom->execute([$from_acc_no, $user_id]);
        $fromAcc = $stmtFrom->fetch();

        // Verify Destination Account (any account)
        $stmtTo = $pdo->prepare("SELECT * FROM accounts WHERE account_number = ?");
        $stmtTo->execute([$to_acc_no]);
        $toAcc = $stmtTo->fetch();

        if (!$fromAcc) {
            $error = 'Source account not found or does not belong to you.';
        } elseif (!password_verify($pin, $fromAcc['pin_hash'])) {
            $error = 'Invalid PIN.';
        } elseif ($fromAcc['balance'] < $amount) {
            $error = 'Insufficient funds.';
        } elseif (!$toAcc) {
            $error = 'Destination account not found.';
        } else {
            try {
                $pdo->beginTransaction();

                // Deduct from sender
                $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?")->execute([$amount, $fromAcc['id']]);
                $pdo->prepare("INSERT INTO transactions (account_id, type, amount, description, related_account_id) VALUES (?, 'transfer_out', ?, ?, ?)")
                    ->execute([$fromAcc['id'], $amount, 'Transfer to ' . $to_acc_no, $toAcc['id']]);

                // Add to receiver
                $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?")->execute([$amount, $toAcc['id']]);
                $pdo->prepare("INSERT INTO transactions (account_id, type, amount, description, related_account_id) VALUES (?, 'transfer_in', ?, ?, ?)")
                    ->execute([$toAcc['id'], $amount, 'Transfer from ' . $from_acc_no, $fromAcc['id']]);

                $pdo->commit();
                $flash = "Successfully transferred €" . number_format($amount, 2) . " to account " . e($to_acc_no) . ".";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Transfer failed. Please try again.';
            }
        }
    }
}

// Fetch user's accounts for the dropdown
$stmt = $pdo->prepare("SELECT account_number, balance, account_type FROM accounts WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_accounts = $stmt->fetchAll();

$page_title = 'Transfer Money';
require_once __DIR__ . '/../includes/user_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Transfer Money</h1>
    <p style="color: var(--text-muted);">Send funds securely to any account using their IBAN.</p>
</div>

<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($flash): ?><div class="flash flash-success"><?= $flash ?></div><?php endif; ?>

<?php if (empty($my_accounts)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--warning); margin-bottom: 16px;"></i>
        <h3>No Accounts Found</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px;">You need to create an account before you can transfer money.</p>
        <a href="<?= e(SITE_URL) ?>user/dashboard.php" class="btn-primary" style="width: auto; padding: 12px 32px; text-decoration: none; display: inline-block; background: linear-gradient(135deg, #10b981, #059669);">Go to Dashboard</a>
    </div>
<?php else: ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h3><i class="fas fa-exchange-alt" style="color: #10b981;"></i> New Transfer</h3>
        
        <form method="post" style="margin-top: 24px;">
            <input type="hidden" name="action" value="transfer">
            
            <div class="form-group">
                <label>From Your Account</label>
                <select name="from_acc" required>
                    <option value="">— Select account —</option>
                    <?php foreach ($my_accounts as $acc): ?>
                        <option value="<?= e($acc['account_number']) ?>">
                            <?= e($acc['account_number']) ?> (<?= strtoupper($acc['account_type']) ?>) · Balance: €<?= number_format($acc['balance'], 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>To Recipient IBAN</label>
                <input type="text" name="to_acc" required placeholder="FI12 1234 000000 0000">
            </div>

            <div class="form-group">
                <label>Amount (€)</label>
                <input type="number" step="0.01" min="1" name="amount" required placeholder="0.00">
            </div>

            <div class="form-group">
                <label>4-Digit PIN</label>
                <input type="password" name="pin" maxlength="4" required placeholder="••••">
                <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 6px;">Required for security. This is the PIN you set when creating your account.</small>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-paper-plane"></i> Send Money
                </button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
