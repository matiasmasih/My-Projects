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

// Fetch All Transactions (with customer details)
$transactions = $pdo->query("
    SELECT t.*, a.account_number, u.first_name, u.last_name, u.email 
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN users u ON a.user_id = u.id
    ORDER BY t.created_at DESC 
    LIMIT 100
")->fetchAll();

$page_title = 'Transaction History';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Transaction History</h1>
    <p style="color: var(--text-muted);">View all past deposits, withdrawals, and transfers across the bank.</p>
</div>

<div class="card">
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td>
                            <strong style="display: block; color: var(--text);"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></strong>
                            <small style="color: var(--text-muted);"><?= e($t['email']) ?></small>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;"><?= e($t['account_number']) ?></td>
                        <td style="text-transform: capitalize; color: var(--text); font-weight: 600;"><?= str_replace('_', ' ', $t['type']) ?></td>
                        <td>
                            <strong style="color: <?= in_array($t['type'], ['deposit', 'transfer_in']) ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= in_array($t['type'], ['deposit', 'transfer_in']) ? '+' : '-' ?>€<?= number_format($t['amount'], 2) ?>
                            </strong>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;"><?= date('M j, Y - H:i', strtotime($t['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">No transactions found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
