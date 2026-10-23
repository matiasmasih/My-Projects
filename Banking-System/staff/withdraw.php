<?php
require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

if ($current_user['role'] !== 'staff') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';
$error = '';

// Handle Withdraw
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    $account_no = trim($_POST['account_no']);
    $amount = (float)$_POST['amount'];

    if ($amount <= 0) {
        $error = 'Amount must be greater than 0.';
    } else {
        $stmt = $pdo->prepare("SELECT id, account_number, balance FROM accounts WHERE account_number = ?");
        $stmt->execute([$account_no]);
        $account = $stmt->fetch();

        if (!$account) {
            $error = 'Account not found.';
        } elseif ($account['balance'] < $amount) {
            $error = 'Insufficient funds. Balance is €' . number_format($account['balance'], 2);
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?")->execute([$amount, $account['id']]);
                $pdo->prepare("INSERT INTO transactions (account_id, type, amount, description) VALUES (?, 'withdraw', ?, 'Teller Withdrawal')")->execute([$account['id'], $amount]);
                $pdo->commit();
                $flash = "Successfully withdrew €" . number_format($amount, 2) . " from account " . e($account_no) . ".";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to process withdrawal.';
            }
        }
    }
}

// Search Results
$search_query = trim($_GET['search'] ?? '');
$search_results = [];
if ($search_query !== '') {
    $stmt = $pdo->prepare("
        SELECT u.id, u.email, u.first_name, u.last_name, u.photo, a.account_number, a.balance
        FROM users u JOIN accounts a ON a.user_id = u.id
        WHERE u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR a.account_number LIKE ?
        LIMIT 20
    ");
    $term = '%' . $search_query . '%';
    $stmt->execute([$term, $term, $term, $term]);
    $search_results = $stmt->fetchAll();
}

$page_title = 'Process Withdrawal';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Process Withdrawal</h1>
    <p style="color: var(--text-muted);">Remove funds from a customer's account.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2" style="align-items: start;">
    <div class="card">
        <h3><i class="fas fa-search" style="color: var(--danger);"></i> Find Customer Account</h3>
        <form method="get" style="display: flex; gap: 12px; margin-bottom: 24px;">
            <input type="text" name="search" placeholder="Search email or account..." value="<?= e($search_query) ?>" style="flex: 1; padding: 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 12px; color: var(--text); outline: none;">
            <button type="submit" class="btn-primary" style="width: auto; padding: 14px 24px; background: var(--danger);"><i class="fas fa-search"></i></button>
        </form>

        <?php if ($search_query !== ''): ?>
            <?php if (empty($search_results)): ?><p style="color: var(--text-muted); text-align: center;">No accounts found.</p><?php else: ?>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($search_results as $r): ?>
                        <li style="padding: 16px 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <strong style="display: block; color: var(--text);"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($r['account_number']) ?> · €<?= number_format($r['balance'], 2) ?></small>
                            </div>
                            <button onclick="document.getElementById('account_no').value='<?= e($r['account_number']) ?>'" class="btn-outline" style="padding: 6px 12px; font-size: 0.8rem;">Select</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3><i class="fas fa-arrow-up" style="color: var(--danger);"></i> Withdraw Funds</h3>
        <form method="post">
            <input type="hidden" name="action" value="withdraw">
            <div class="form-group">
                <label>Account Number</label>
                <input type="text" name="account_no" id="account_no" required placeholder="FI12...">
            </div>
            <div class="form-group">
                <label>Amount (€)</label>
                <input type="number" step="0.01" min="1" name="amount" required placeholder="0.00">
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 12px; background: var(--danger);">
                <i class="fas fa-minus"></i> Confirm Withdrawal
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
