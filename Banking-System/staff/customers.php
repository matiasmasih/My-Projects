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

// Search
$search = trim($_GET['search'] ?? '');
$query = "
    SELECT u.*, 
           (SELECT SUM(balance) FROM accounts WHERE user_id = u.id) AS total_balance,
           (SELECT COUNT(*) FROM accounts WHERE user_id = u.id) AS account_count
    FROM users u 
    WHERE u.role = 'user'
";
$params = [];

if ($search !== '') {
    $query .= " AND (u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)";
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term];
}

$query .= " ORDER BY u.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$page_title = 'Find Customer';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px; display: flex; justify-content: space-between; align-items: flex-end;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Find Customer</h1>
        <p style="color: var(--text-muted);">Search and view all registered customers.</p>
    </div>
    <a href="<?= e(SITE_URL) ?>staff/add_customer.php" class="btn-primary" style="width: auto; padding: 12px 24px; font-size: 0.9rem; background: linear-gradient(135deg, #0ea5e9, #14b8a6);">
        <i class="fas fa-user-plus"></i> Add New Customer
    </a>
</div>

<div class="card">
    <!-- Search Bar -->
    <form method="get" style="display: flex; gap: 12px; margin-bottom: 24px;">
        <input type="text" name="search" placeholder="Search by name, email, or phone..." value="<?= e($search) ?>" style="flex: 1; padding: 14px 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: var(--font); font-size: 1rem; outline: none;">
        <button type="submit" class="btn-primary" style="width: auto; padding: 14px 24px; background: linear-gradient(135deg, #0ea5e9, #14b8a6);">
            <i class="fas fa-search"></i> Search
        </button>
    </form>

    <!-- Customer Table -->
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Accounts</th>
                    <th>Total Balance</th>
                    <th>Joined</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $c): ?>
                    <tr>
                        <td style="display: flex; align-items: center; gap: 16px;">
                            <?php if (!empty($c['photo']) && file_exists(__DIR__ . '/../uploads/' . $c['photo'])): ?>
                                <img src="<?= UPLOADS_URL . e($c['photo']) ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                            <?php else: ?>
                                <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; display: grid; place-items: center; font-weight: bold;">
                                    <?= strtoupper(substr($c['email'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong style="display: block; color: var(--text);"><?= !empty($c['first_name']) ? e($c['first_name'] . ' ' . $c['last_name']) : 'No Name' ?></strong>
                                <small style="color: var(--text-muted);"><?= e($c['email']) ?></small>
                            </div>
                        </td>
                        <td style="color: var(--text-muted);"><?= e($c['phone']) ?></td>
                        <td>
                            <span style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                                <?= $c['account_count'] ?> account<?= $c['account_count'] != 1 ? 's' : '' ?>
                            </span>
                        </td>
                        <td style="color: var(--text); font-weight: 700;">€<?= number_format($c['total_balance'] ?? 0, 2) ?></td>
                        <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                        <td style="text-align: right;">
                            <a href="<?= e(SITE_URL) ?>staff/deposit.php?search=<?= urlencode($c['email']) ?>" class="btn-outline" style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none;">
                                <i class="fas fa-arrow-down"></i> Deposit
                            </a>
                            <a href="<?= e(SITE_URL) ?>staff/withdraw.php?search=<?= urlencode($c['email']) ?>" class="btn-outline" style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none;">
                                <i class="fas fa-arrow-up"></i> Withdraw
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No customers found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
