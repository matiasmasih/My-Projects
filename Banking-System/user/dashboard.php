<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Verify User Role
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

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. CREATE ACCOUNT (Generates 18-digit IBAN)
    if ($action === 'create_account') {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $initial_depo = (float)$_POST['initial_depo'];
        $pin = $_POST['pin'];
        $account_type = $_POST['account_type'];

        if (strlen($pin) !== 4 || !is_numeric($pin)) {
            $error = 'PIN must be a 4-digit number.';
        } else {
            // Generate 18-digit IBAN: FI12 1234 000001 1234
            $country = "FI12";
            $bank_code = "1234";
            $user_padded = str_pad($user_id, 6, '0', STR_PAD_LEFT);
            $random_suffix = rand(1000, 9999);
            $acc_no = $country . $bank_code . $user_padded . $random_suffix;
            
            $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);
            
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO accounts (user_id, account_number, iban, account_type, first_name, last_name, balance, pin_hash, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'EUR')");
                $stmt->execute([$user_id, $acc_no, $acc_no, $account_type, $first_name, $last_name, $initial_depo, $hashed_pin]);
                $account_id = $pdo->lastInsertId();

                if ($initial_depo > 0) {
                    $pdo->prepare("INSERT INTO transactions (account_id, type, amount, description) VALUES (?, 'deposit', ?, 'Initial Deposit')")->execute([$account_id, $initial_depo]);
                }
                $pdo->commit();
                $flash = "Account Created! Your IBAN is: <strong>$acc_no</strong>. Please save it.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to create account.';
            }
        }
    }

    // 2. QUICK DEPOSIT
    if ($action === 'deposit') {
        $acc_no = trim($_POST['acc_no']);
        $amount = (float)$_POST['amount'];
        
        $stmt = $pdo->prepare("SELECT id FROM accounts WHERE account_number = ? AND user_id = ?");
        $stmt->execute([$acc_no, $user_id]);
        $account = $stmt->fetch();

        if ($account && $amount > 0) {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?")->execute([$amount, $account['id']]);
            $pdo->prepare("INSERT INTO transactions (account_id, type, amount, description) VALUES (?, 'deposit', ?, 'Self Deposit')")->execute([$account['id'], $amount]);
            $pdo->commit();
            $flash = "Successfully deposited €" . number_format($amount, 2) . ".";
        } else {
            $error = 'Invalid account or amount.';
        }
    }
}

// Fetch Data
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ?");
$stmt->execute([$user_id]);
$accounts = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE account_id IN (SELECT id FROM accounts WHERE user_id = ?) ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll();

$page_title = 'My Accounts';
require_once __DIR__ . '/../includes/user_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">My Accounts</h1>
    <p style="color: var(--text-muted);">Manage your bank accounts and funds.</p>
</div>

<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($flash): ?><div class="flash flash-success"><?= $flash ?></div><?php endif; ?>

<?php if (count($accounts) === 0): ?>
    <!-- Create First Account -->
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h3><i class="fas fa-plus-circle" style="color: #10b981;"></i> Create Your First Account</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px;">Open a new bank account and get an 18-digit IBAN.</p>
        
        <form method="post">
            <input type="hidden" name="action" value="create_account">
            
            <div class="grid-2">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" required placeholder="John">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required placeholder="Doe">
                </div>
                <div class="form-group">
                    <label>Account Type</label>
                    <select name="account_type">
                        <option value="checking">Checking</option>
                        <option value="savings">Savings</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Initial Deposit (€)</label>
                    <input type="number" name="initial_depo" value="0" min="0">
                </div>
            </div>
            
            <div class="form-group">
                <label>4-Digit PIN</label>
                <input type="password" name="pin" maxlength="4" required placeholder="1234">
            </div>
            
            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-check"></i> Create Account
                </button>
            </div>
        </form>
    </div>
<?php else: ?>
    <!-- Accounts Overview -->
    <div class="grid-2" style="margin-bottom: 32px;">
        <?php foreach ($accounts as $acc): ?>
            <div class="card" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: white; border: none; position: relative; overflow: hidden;">
                <div style="position: absolute; top: -50%; right: -20%; width: 200px; height: 200px; background: rgba(16, 185, 129, 0.2); border-radius: 50%; pointer-events: none;"></div>
                
                <div style="font-size: 0.9rem; color: #94a3b8; letter-spacing: 2px; margin-bottom: 10px;">
                    <?= substr($acc['account_number'], 0, 4) ?> <?= substr($acc['account_number'], 4, 4) ?> <?= substr($acc['account_number'], 8, 6) ?> <?= substr($acc['account_number'], 14, 4) ?>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; margin-bottom: 5px;">€<?= number_format($acc['balance'], 2) ?></div>
                <div style="font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 15px;"><?= strtoupper($acc['account_type']) ?> (<?= $acc['currency'] ?>)</div>
                
                <div style="font-size: 0.8rem; color: #64748b; display: flex; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 10px;">
                    <span>Opened on</span>
                    <span><?= date('M j, Y', strtotime($acc['created_at'])) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid-2">
        <!-- Quick Deposit -->
        <div class="card">
            <h3><i class="fas fa-arrow-down" style="color: #10b981;"></i> Quick Deposit</h3>
            <form method="post">
                <input type="hidden" name="action" value="deposit">
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="acc_no" required placeholder="FI12...">
                </div>
                <div class="form-group">
                    <label>Amount (€)</label>
                    <input type="number" step="0.01" min="1" name="amount" required placeholder="0.00">
                </div>
                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fas fa-plus"></i> Deposit
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <h3><i class="fas fa-history" style="color: #10b981;"></i> Recent Activity</h3>
            <?php if (empty($recent_transactions)): ?>
                <div style="text-align: center; padding: 20px; color: var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.3; margin-bottom: 8px; display: block;"></i>
                    <p>No transactions yet.</p>
                </div>
            <?php else: ?>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach($recent_transactions as $t): ?>
                        <li style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--border);">
                            <span style="text-transform: capitalize; color: var(--text); font-weight: 500;"><?= $t['type'] ?></span>
                            <strong style="color: <?= $t['type'] === 'deposit' ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= $t['type'] === 'deposit' ? '+' : '-' ?>€<?= number_format($t['amount'], 2) ?>
                            </strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
