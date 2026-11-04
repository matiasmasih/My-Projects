<?php
// staff/index.php — Staff Dashboard (Branch Scoped)

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'staff' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;
$no_branch = ($branch_id === null);

$today = date('Y-m-d');

// ---- Today's Stats (Branch Scoped) ----
$today_book_loans = 0;
$today_device_loans = 0;
$today_book_returns = 0;
$today_device_returns = 0;
$active_book_loans = 0;
$overdue_book_loans = 0;
$active_device_loans = 0;
$recent_loans = [];

if (!$no_branch) {
    // Book Loans Today (Joining book_copies for legacy support)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM loans l 
        JOIN book_copies bc ON bc.id = l.book_copy_id 
        WHERE DATE(l.borrowed_at) = ? AND bc.branch_id = ?
    ");
    $stmt->execute([$today, $branch_id]);
    $today_book_loans = $stmt->fetchColumn();

    // Device Loans Today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM device_loans WHERE DATE(borrowed_at) = ? AND branch_id = ?");
    $stmt->execute([$today, $branch_id]);
    $today_device_loans = $stmt->fetchColumn();

    // Book Returns Today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM loans l 
        JOIN book_copies bc ON bc.id = l.book_copy_id 
        WHERE DATE(l.returned_at) = ? AND bc.branch_id = ?
    ");
    $stmt->execute([$today, $branch_id]);
    $today_book_returns = $stmt->fetchColumn();

    // Device Returns Today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM device_loans WHERE DATE(returned_at) = ? AND branch_id = ?");
    $stmt->execute([$today, $branch_id]);
    $today_device_returns = $stmt->fetchColumn();

    // Active & Overdue Book Loans
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM loans l 
        JOIN book_copies bc ON bc.id = l.book_copy_id 
        WHERE l.status = 'active' AND bc.branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $active_book_loans = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM loans l 
        JOIN book_copies bc ON bc.id = l.book_copy_id 
        WHERE l.status = 'active' AND l.due_at < NOW() AND bc.branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $overdue_book_loans = $stmt->fetchColumn();

    // Active Device Loans
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM device_loans WHERE status = 'active' AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $active_device_loans = $stmt->fetchColumn();

    // Recent Book Loans
    $stmt = $pdo->prepare("
        SELECT l.*, b.title AS book_title, u.first_name, u.last_name, u.email
        FROM loans l
        JOIN book_copies bc ON bc.id = l.book_copy_id
        JOIN books b ON b.id = bc.book_id
        JOIN users u ON u.id = l.user_id
        WHERE bc.branch_id = ?
        ORDER BY l.created_at DESC LIMIT 5
    ");
    $stmt->execute([$branch_id]);
    $recent_loans = $stmt->fetchAll();
}

$page_title = 'Staff Dashboard';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Teller Dashboard</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Welcome back. Here is the status for today.
    </p>
</div>

<?php if ($no_branch): ?>
    <div class="flash flash-error" style="margin-bottom: 24px;">
        <i class="fas fa-exclamation-triangle"></i> Your account is not assigned to a branch.
    </div>
<?php endif; ?>

<!-- Today's Activity -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-book-open"></i></div>
        <div>
            <div class="stat-value"><?= $today_book_loans ?></div>
            <div class="stat-label">Books Loaned Today</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-undo"></i></div>
        <div>
            <div class="stat-value"><?= $today_book_returns ?></div>
            <div class="stat-label">Books Returned Today</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-laptop"></i></div>
        <div>
            <div class="stat-value"><?= $today_device_loans ?></div>
            <div class="stat-label">Devices Loaned Today</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-undo-alt"></i></div>
        <div>
            <div class="stat-value"><?= $today_device_returns ?></div>
            <div class="stat-label">Devices Returned Today</div>
        </div>
    </div>
</div>

<!-- Active Overview -->
<div class="grid-3" style="margin-bottom: 32px;">
    <div class="card" style="border-left: 4px solid var(--warning);">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Active Book Loans</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $active_book_loans ?></div>
    </div>
    <div class="card" style="border-left: 4px solid var(--danger);">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Overdue Books</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--danger); margin-top: 4px;"><?= $overdue_book_loans ?></div>
    </div>
    <div class="card" style="border-left: 4px solid var(--primary);">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Active Device Loans</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $active_device_loans ?></div>
    </div>
</div>

<div class="grid-2">
    <!-- Quick Actions -->
    <div class="card">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="grid-2" style="margin-top: 20px;">
            <a href="<?= e(SITE_URL) ?>staff/members.php" class="btn-outline" style="padding: 24px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
                <i class="fas fa-user-plus" style="font-size: 2rem; color: #0ea5e9;"></i>
                <span style="font-weight: 600;">New Member</span>
            </a>
            <a href="<?= e(SITE_URL) ?>staff/loans.php" class="btn-outline" style="padding: 24px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
                <i class="fas fa-book-reader" style="font-size: 2rem; color: #0ea5e9;"></i>
                <span style="font-weight: 600;">Process Book Loan</span>
            </a>
            <a href="<?= e(SITE_URL) ?>staff/device_loans.php" class="btn-outline" style="padding: 24px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
                <i class="fas fa-laptop" style="font-size: 2rem; color: #0ea5e9;"></i>
                <span style="font-weight: 600;">Process Device Loan</span>
            </a>
            <a href="<?= e(SITE_URL) ?>staff/fines.php" class="btn-outline" style="padding: 24px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
                <i class="fas fa-money-bill-wave" style="font-size: 2rem; color: #0ea5e9;"></i>
                <span style="font-weight: 600;">Manage Fines</span>
            </a>
        </div>
    </div>

    <!-- Recent Loans -->
    <div class="card">
        <h3><i class="fas fa-history"></i> Recent Book Loans</h3>
        <?php if (empty($recent_loans)): ?>
            <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No recent loans.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 16px;">
                <?php foreach ($recent_loans as $loan): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid var(--border);">
                        <div>
                            <strong style="display: block; color: var(--text); font-size: 0.9rem;"><?= e($loan['book_title']) ?></strong>
                            <small style="color: var(--text-muted); font-size: 0.8rem;">
                                To: <?= e($loan['first_name'] . ' ' . $loan['last_name']) ?> · <?= date('H:i', strtotime($loan['borrowed_at'])) ?>
                            </small>
                        </div>
                        <span style="padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; background: var(--bg-alt); color: var(--text-muted); text-transform: uppercase;">
                            <?= e($loan['status']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
