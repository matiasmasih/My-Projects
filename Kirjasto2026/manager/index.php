<?php
// manager/index.php — Kirjasto 2026 Manager Dashboard (branch-scoped)
// Phase 9 — Multi-Branch

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$no_branch = ($branch_id === null);

// ---- Fetch Stats (scoped to branch) ----
if ($no_branch) {
    $total_books = 0;
    $total_members = 0;
    $active_loans = 0;
    $overdue_loans = 0;
    $total_devices = 0;
    $unpaid_fines = 0;
    $recent_loans = [];
} else {
    // Books available at this branch (copies, not titles)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM book_copies WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_books = $stmt->fetchColumn();

    // Members assigned to this branch
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'member' AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_members = $stmt->fetchColumn();

    // Active loans at this branch (Joining book_copies for legacy data support)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM loans l
        JOIN book_copies bc ON bc.id = l.book_copy_id
        WHERE l.status = 'active' AND bc.branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $active_loans = $stmt->fetchColumn();

    // Overdue loans at this branch
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM loans l
        JOIN book_copies bc ON bc.id = l.book_copy_id
        WHERE (l.status = 'overdue' OR (l.status = 'active' AND l.due_at < NOW())) 
        AND bc.branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $overdue_loans = $stmt->fetchColumn();

    // Devices at this branch
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_devices = $stmt->fetchColumn();

    // Unpaid fines (scoped via loan_id → book_copies.branch_id)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(f.amount - f.paid_amount), 0)
        FROM fines f
        JOIN loans l ON l.id = f.loan_id
        JOIN book_copies bc ON bc.id = l.book_copy_id
        WHERE f.status != 'paid' AND bc.branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $unpaid_fines = $stmt->fetchColumn();

    // Recent loans at this branch
    $stmt = $pdo->prepare("
        SELECT l.*,
               b.title AS book_title,
               b.author AS book_author,
               u.first_name, u.last_name, u.email, u.profile_image
        FROM loans l
        JOIN book_copies bc ON bc.id = l.book_copy_id
        JOIN books b ON b.id = bc.book_id
        JOIN users u ON u.id = l.user_id
        WHERE bc.branch_id = ?
        ORDER BY l.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$branch_id]);
    $recent_loans = $stmt->fetchAll();
}

$page_title = $branch_name ? $branch_name . ' Manager' : 'Manager Dashboard';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<?php if ($no_branch): ?>
    <div class="card" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); padding: 24px; margin-bottom: 32px; display: flex; align-items: flex-start; gap: 16px;">
        <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem; color: #f59e0b; flex-shrink: 0;"></i>
        <div>
            <h3 style="margin: 0 0 6px 0; color: #f59e0b; font-size: 1.1rem;">No Branch Assigned</h3>
            <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem; line-height: 1.6;">
                Your account is not assigned to a library branch. All statistics below show zero until an admin assigns you to a branch.
                <br><strong style="color: var(--text);">Contact your system administrator</strong> to get assigned to a branch.
            </p>
        </div>
    </div>
<?php endif; ?>

<!-- Welcome Header -->
<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800; letter-spacing: -0.02em;">
        <?= $branch_name ? e($branch_name) . ' Manager' : 'Library Overview' ?>
    </h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <?= $branch_name ? 'Welcome back to the ' . e($branch_name) . ' branch.' : 'Welcome back to the Kirjasto 2026 Manager Portal.' ?>
    </p>
</div>

<!-- Stats Grid -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-book"></i></div>
        <div>
            <div class="stat-value"><?= $total_books ?></div>
            <div class="stat-label"><?= $no_branch ? 'Total Books' : 'Book Copies' ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-value"><?= $total_members ?></div>
            <div class="stat-label">Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-list"></i></div>
        <div>
            <div class="stat-value"><?= $active_loans ?></div>
            <div class="stat-label">Active Loans</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value"><?= $overdue_loans ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>
</div>

<!-- Secondary Stats -->
<div class="grid-2" style="margin-bottom: 32px;">
    <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Total Devices</div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $total_devices ?></div>
        </div>
        <div style="width: 56px; height: 56px; border-radius: 16px; background: rgba(59, 130, 246, 0.12); color: var(--primary); display: grid; place-items: center; font-size: 1.5rem;">
            <i class="fas fa-laptop"></i>
        </div>
    </div>
    <div class="card" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">Unpaid Fines</div>
            <div style="font-size: 2rem; font-weight: 800; color: var(--danger); margin-top: 4px;">€<?= number_format($unpaid_fines, 2) ?></div>
        </div>
        <div style="width: 56px; height: 56px; border-radius: 16px; background: rgba(239, 68, 68, 0.12); color: var(--danger); display: grid; place-items: center; font-size: 1.5rem;">
            <i class="fas fa-euro-sign"></i>
        </div>
    </div>
</div>

<!-- Recent Loans Table -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h3 style="margin: 0;"><i class="fas fa-history"></i> Recent Loans<?= $branch_name ? ' — ' . e($branch_name) : '' ?></h3>
        <a href="<?= e(SITE_URL) ?>manager/loans.php" class="btn-outline" style="padding: 8px 16px; font-size: 0.85rem;">View All →</a>
    </div>

    <?php if (empty($recent_loans)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
            <i class="fas fa-inbox" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 12px; display: block;"></i>
            <p><?= $no_branch ? 'Assign a branch to see loans.' : 'No loans yet at this branch.' ?></p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Book</th>
                        <th>Borrowed</th>
                        <th>Due</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_loans as $loan): ?>
                        <tr>
                            <td style="display: flex; align-items: center; gap: 12px;">
                                <?php if (!empty($loan['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $loan['profile_image'])): ?>
                                    <img src="<?= UPLOADS_URL . e($loan['profile_image']) ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.8rem;">
                                        <?= strtoupper(substr($loan['email'], 0, 2)) ?>
                                    </div>
                                <?php endif; ?>
                                <span style="font-weight: 600; color: var(--text);"><?= e($loan['first_name'] . ' ' . $loan['last_name']) ?></span>
                            </td>
                            <td>
                                <strong style="display: block; color: var(--text);"><?= e($loan['book_title']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($loan['book_author']) ?></small>
                            </td>
                            <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($loan['borrowed_at'])) ?></td>
                            <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($loan['due_at'])) ?></td>
                            <td>
                                <?php
                                    $status_color = 'var(--warning)';
                                    if ($loan['status'] === 'returned') $status_color = 'var(--success)';
                                    if ($loan['status'] === 'overdue' || ($loan['status'] === 'active' && strtotime($loan['due_at']) < time())) $status_color = 'var(--danger)';
                                ?>
                                <span style="padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: <?= $status_color ?>20; color: <?= $status_color ?>;">
                                    <?= e($loan['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="card" style="margin-top: 32px;">
    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div class="grid-2" style="margin-top: 20px;">
        <a href="<?= e(SITE_URL) ?>manager/books.php" class="btn-outline" style="padding: 20px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
            <i class="fas fa-book" style="font-size: 1.8rem; color: #f59e0b;"></i>
            <span>Manage Books</span>
        </a>
        <a href="<?= e(SITE_URL) ?>manager/loans.php" class="btn-outline" style="padding: 20px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
            <i class="fas fa-list" style="font-size: 1.8rem; color: #f59e0b;"></i>
            <span>View Loans</span>
        </a>
        <a href="<?= e(SITE_URL) ?>manager/reports.php" class="btn-outline" style="padding: 20px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
            <i class="fas fa-chart-line" style="font-size: 1.8rem; color: #f59e0b;"></i>
            <span>Reports</span>
        </a>
        <a href="<?= e(SITE_URL) ?>manager/messages.php" class="btn-outline" style="padding: 20px; flex-direction: column; text-align: center; gap: 12px; height: auto;">
            <i class="fas fa-comments" style="font-size: 1.8rem; color: #f59e0b;"></i>
            <span>Messages</span>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
