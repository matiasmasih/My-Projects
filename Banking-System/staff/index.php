<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Verify Staff Role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'staff') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$staff_id = $_SESSION['user_id'];

// ============ FETCH REAL DASHBOARD DATA ============

// 1. Today's Transactions (process by this staff today)
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT t.*, a.account_number, u.first_name, u.last_name, u.email 
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN users u ON a.user_id = u.id
    WHERE DATE(t.created_at) = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$today]);
$today_transactions = $stmt->fetchAll();

// 2. Total Customers
$total_customers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// 3. Pending KYC (needs attention)
$pending_kyc = $pdo->query("SELECT COUNT(*) FROM kyc_documents WHERE status = 'pending'")->fetchColumn();

// 4. Pending Loans (needs attention)
$pending_loans = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'")->fetchColumn();

// 5. Total Bank Deposits
$total_deposits = $pdo->query("SELECT SUM(balance) FROM accounts")->fetchColumn() ?: 0;

// 6. Total Transactions Today
$stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$today_count = $stmt->fetchColumn();

// 7. Recent Customers (5 latest)
$recent_customers = $pdo->query("
    SELECT id, email, first_name, last_name, phone, photo, created_at 
    FROM users WHERE role = 'user' 
    ORDER BY id DESC LIMIT 5
")->fetchAll();

$page_title = 'Staff Dashboard';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<!-- Welcome Banner -->
<div class="dashboard-header" style="margin-bottom: 32px; display: flex; justify-content: space-between; align-items: flex-end;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Teller Dashboard</h1>
        <p style="color: var(--text-muted); font-size: 1rem;">Welcome back, John. Here's today's overview.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="<?= e(SITE_URL) ?>staff/deposit.php" class="btn-primary" style="width: auto; padding: 12px 24px; font-size: 0.9rem; background: linear-gradient(135deg, #0ea5e9, #14b8a6);">
            <i class="fas fa-money-bill-wave"></i> New Transaction
        </a>
        <a href="<?= e(SITE_URL) ?>staff/customers.php" class="btn-outline" style="width: auto; padding: 12px 24px; font-size: 0.9rem;">
            <i class="fas fa-user-plus"></i> New Customer
        </a>
    </div>
</div>

<!-- KPI Stat Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin-bottom: 32px;">

    <div class="card" style="display: flex; align-items: center; gap: 20px; padding: 24px; border-left: 4px solid #0ea5e9;">
        <div style="width: 56px; height: 56px; border-radius: 16px; background: rgba(14, 165, 233, 0.1); color: #0ea5e9; display: grid; place-items: center; font-size: 1.5rem;">
            <i class="fas fa-users"></i>
        </div>
        <div>
            <h3 style="font-size: 2rem; margin: 0; color: var(--text); font-weight: 800; line-height: 1;"><?= $total_customers ?></h3>
            <p style="color: var(--text-muted); font-weight: 600; margin: 4px 0 0 0; font-size: 0.9rem;">Total Customers</p>
        </div>
    </div>

    <div class="card" style="display: flex; align-items: center; gap: 20px; padding: 24px; border-left: 4px solid #14b8a6;">
        <div style="width: 56px; height: 56px; border-radius: 16px; background: rgba(20, 184, 166, 0.1); color: #14b8a6; display: grid; place-items: center; font-size: 1.5rem;">
            <i class="fas fa-wallet"></i>
        </div>
        <div>
            <h3 style="font-size: 2rem; margin: 0; color: var(--text); font-weight: 800; line-height: 1;">€<?= number_format($total_deposits, 0) ?></h3>
            <p style="color: var(--text-muted); font-weight: 600; margin: 4px 0 0 0; font-size: 0.9rem;">Total Bank Deposits</p>
        </div>
    </div>

    <div class="card" style="display: flex; align-items: center; gap: 20px; padding: 24px; border-left: 4px solid #f59e0b;">
        <div style="width: 56px; height: 56px; border-radius: 16px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; display: grid; place-items: center; font-size: 1.5rem;">
            <i class="fas fa-id-card"></i>
        </div>
        <div>
            <h3 style="font-size: 2rem; margin: 0; color: var(--text); font-weight: 800; line-height: 1;"><?= $pending_kyc ?></h3>
            <p style="color: var(--text-muted); font-weight: 600; margin: 4px 0 0 0; font-size: 0.9rem;">Pending KYC</p>
        </div>
    </div>

    <div class="card" style="display: flex; align-items: center; gap: 20px; padding: 24px; border-left: 4px solid var(--danger);">
        <div style="width: 56px; height: 56px; border-radius: 16px; background: rgba(239, 68, 68, 0.1); color: var(--danger); display: grid; place-items: center; font-size: 1.5rem;">
            <i class="fas fa-home"></i>
        </div>
        <div>
            <h3 style="font-size: 2rem; margin: 0; color: var(--text); font-weight: 800; line-height: 1;"><?= $pending_loans ?></h3>
            <p style="color: var(--text-muted); font-weight: 600; margin: 4px 0 0 0; font-size: 0.9rem;">Pending Loans</p>
        </div>
    </div>
</div>

<!-- Two Column Layout -->
<div class="grid-2" style="align-items: start;">

    <!-- Left: Today's Transactions -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0; font-size: 1.2rem;"><i class="fas fa-receipt" style="color: #0ea5e9;"></i> Today's Transactions</h3>
            <span style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600;"><?= $today_count ?> today</span>
        </div>

        <?php if (empty($today_transactions)): ?>
            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                <i class="fas fa-inbox" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 12px; display: block;"></i>
                <p>No transactions processed today.</p>
            </div>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($today_transactions as $t): ?>
                    <li style="padding: 14px 0; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="display: block; color: var(--text); font-size: 0.9rem;"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></strong>
                            <small style="color: var(--text-muted); font-size: 0.8rem;"><?= e($t['account_number']) ?> · <?= date('H:i', strtotime($t['created_at'])) ?></small>
                        </div>
                        <div style="text-align: right;">
                            <strong style="display: block; color: <?= in_array($t['type'], ['deposit', 'transfer_in']) ? 'var(--success)' : 'var(--danger)' ?>; font-size: 0.95rem;">
                                <?= in_array($t['type'], ['deposit', 'transfer_in']) ? '+' : '-' ?>€<?= number_format($t['amount'], 2) ?>
                            </strong>
                            <small style="color: var(--text-muted); font-size: 0.75rem; text-transform: capitalize;"><?= str_replace('_', ' ', $t['type']) ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Right: Recent Customers + Quick Actions -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- Recent Customers -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="margin: 0; font-size: 1.2rem;"><i class="fas fa-user-clock" style="color: #0ea5e9;"></i> Recent Customers</h3>
                <a href="<?= e(SITE_URL) ?>staff/customers.php" style="color: #0ea5e9; font-weight: 600; font-size: 0.85rem; text-decoration: none;">View All →</a>
            </div>

            <?php foreach ($recent_customers as $c): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border);">
                    <?php if (!empty($c['photo']) && file_exists(__DIR__ . '/../uploads/' . $c['photo'])): ?>
                        <img src="<?= UPLOADS_URL . e($c['photo']) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; display: grid; place-items: center; font-weight: bold; font-size: 0.8rem;">
                            <?= strtoupper(substr($c['email'], 0, 2)) ?>
                        </div>
                    <?php endif; ?>
                    <div style="flex: 1;">
                        <strong style="display: block; color: var(--text); font-size: 0.9rem;"><?= e($c['first_name'] . ' ' . $c['last_name']) ?></strong>
                        <small style="color: var(--text-muted); font-size: 0.8rem;"><?= e($c['email']) ?></small>
                    </div>
                    <a href="<?= e(SITE_URL) ?>staff/deposit.php?customer=<?= $c['id'] ?>" class="btn-outline" style="padding: 6px 12px; font-size: 0.75rem; text-decoration: none;">Process</a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <h3 style="margin: 0 0 20px 0; font-size: 1.2rem;"><i class="fas fa-bolt" style="color: #0ea5e9;"></i> Quick Actions</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <a href="<?= e(SITE_URL) ?>staff/deposit.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 20px; background: rgba(14, 165, 233, 0.05); border: 1px solid rgba(14, 165, 233, 0.2); border-radius: 12px; text-decoration: none; color: var(--text);">
                    <i class="fas fa-arrow-down" style="font-size: 1.5rem; color: #0ea5e9;"></i>
                    <span style="font-size: 0.85rem; font-weight: 600;">Deposit</span>
                </a>
                <a href="<?= e(SITE_URL) ?>staff/withdraw.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 20px; background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; text-decoration: none; color: var(--text);">
                    <i class="fas fa-arrow-up" style="font-size: 1.5rem; color: var(--danger);"></i>
                    <span style="font-size: 0.85rem; font-weight: 600;">Withdraw</span>
                </a>
                <a href="<?= e(SITE_URL) ?>staff/add_customer.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 20px; background: rgba(20, 184, 166, 0.05); border: 1px solid rgba(20, 184, 166, 0.2); border-radius: 12px; text-decoration: none; color: var(--text);">
                    <i class="fas fa-user-plus" style="font-size: 1.5rem; color: #14b8a6;"></i>
                    <span style="font-size: 0.85rem; font-weight: 600;">New Customer</span>
                </a>
                <a href="<?= e(SITE_URL) ?>staff/settings.php" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 20px; background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 12px; text-decoration: none; color: var(--text);">
                    <i class="fas fa-cog" style="font-size: 1.5rem; color: #f59e0b;"></i>
                    <span style="font-size: 0.85rem; font-weight: 600;">Settings</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
