<?php
// manager/reports.php — Library Analytics & Reports (Branch Scoped)

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// ---- Resolve manager's branch ----
$stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$manager_row = $stmt->fetch();
$branch_id = $manager_row['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

if (!$branch_id) {
    // If no branch assigned, show empty stats
    $total_copies = $total_members = $total_loans = $active_loans = $overdue_loans = $returned_loans = 0;
    $total_fines = $paid_fines = $unpaid_fines = 0;
    $total_devices = $available_devices = $active_device_loans = $overdue_device_loans = 0;
    $top_books = $top_members = $categories = $monthly = $device_types_stats = [];
} else {
    // ---- Overall Stats (Branch Scoped) ----
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM book_copies WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_copies = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'member' AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_members = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_loans = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE status = 'active' AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $active_loans = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE status = 'active' AND due_at < NOW() AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $overdue_loans = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE status = 'returned' AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $returned_loans = $stmt->fetchColumn();

    // Fines scoped via joining loans
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(f.amount), 0) 
        FROM fines f 
        JOIN loans l ON l.id = f.loan_id 
        WHERE l.branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $total_fines = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(f.paid_amount), 0) 
        FROM fines f 
        JOIN loans l ON l.id = f.loan_id 
        WHERE l.branch_id = ?
    ");
    $stmt->execute([$branch_id]);
    $paid_fines = $stmt->fetchColumn();
    
    $unpaid_fines = $total_fines - $paid_fines;

    // ---- Device Stats (Branch Scoped) ----
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_devices = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE status = 'available' AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $available_devices = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM device_loans WHERE status = 'active' AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $active_device_loans = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM device_loans WHERE status = 'active' AND due_at < NOW() AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $overdue_device_loans = $stmt->fetchColumn();

    // ---- Most Borrowed Books (Branch Scoped) ----
    $stmt = $pdo->prepare("
        SELECT b.title, b.author, b.cover_image,
               COUNT(l.id) AS loan_count
        FROM books b
        JOIN book_copies bc ON bc.book_id = b.id
        JOIN loans l ON l.book_copy_id = bc.id
        WHERE l.branch_id = ?
        GROUP BY b.id
        ORDER BY loan_count DESC
        LIMIT 5
    ");
    $stmt->execute([$branch_id]);
    $top_books = $stmt->fetchAll();

    // ---- Most Active Members (Branch Scoped) ----
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, u.email, u.profile_image,
               COUNT(l.id) AS loan_count
        FROM users u
        JOIN loans l ON l.user_id = u.id
        WHERE u.role = 'member' AND l.branch_id = ?
        GROUP BY u.id
        ORDER BY loan_count DESC
        LIMIT 5
    ");
    $stmt->execute([$branch_id]);
    $top_members = $stmt->fetchAll();

    // ---- Category Distribution (Branch Scoped) ----
    $stmt = $pdo->prepare("
        SELECT COALESCE(b.category, 'Uncategorized') AS category, COUNT(bc.id) AS count
        FROM books b
        JOIN book_copies bc ON bc.book_id = b.id
        WHERE bc.branch_id = ?
        GROUP BY b.category
        ORDER BY count DESC
    ");
    $stmt->execute([$branch_id]);
    $categories = $stmt->fetchAll();

    // ---- Monthly Loans (Last 6 Months) (Branch Scoped) ----
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(borrowed_at, '%Y-%m') AS month,
               COUNT(*) AS count
        FROM loans
        WHERE branch_id = ? AND borrowed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(borrowed_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$branch_id]);
    $monthly = $stmt->fetchAll();

    // ---- Device Types Stats (Branch Scoped) ----
    $stmt = $pdo->prepare("
        SELECT dt.id, dt.name, dt.loan_days, COUNT(d.id) AS device_count
        FROM device_types dt
        LEFT JOIN devices d ON d.device_type_id = dt.id AND d.branch_id = ?
        GROUP BY dt.id
        ORDER BY device_count DESC
    ");
    $stmt->execute([$branch_id]);
    $device_types_stats = $stmt->fetchAll();
}

$page_title = 'Reports';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Library Reports</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Real-time analytics and branch performance metrics.
    </p>
</div>

<?php if (!$branch_id): ?>
    <div class="flash flash-error" style="margin-bottom: 24px;"><i class="fas fa-exclamation-triangle"></i> Your account is not assigned to a branch.</div>
<?php endif; ?>

<!-- Main Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-copy"></i></div>
        <div>
            <div class="stat-value"><?= $total_copies ?></div>
            <div class="stat-label">Physical Copies</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-value"><?= $total_members ?></div>
            <div class="stat-label">Members</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-exchange-alt"></i></div>
        <div>
            <div class="stat-value"><?= $total_loans ?></div>
            <div class="stat-label">Total Book Loans</div>
        </div>
    </div>
</div>

<!-- Device Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-laptop"></i></div>
        <div>
            <div class="stat-value"><?= $total_devices ?></div>
            <div class="stat-label">Total Devices</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check"></i></div>
        <div>
            <div class="stat-value"><?= $available_devices ?></div>
            <div class="stat-label">Available Devices</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-hand-holding"></i></div>
        <div>
            <div class="stat-value"><?= $active_device_loans ?></div>
            <div class="stat-label">Active Device Loans</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <div class="stat-value"><?= $overdue_device_loans ?></div>
            <div class="stat-label">Overdue Returns</div>
        </div>
    </div>
</div>

<!-- Loan Status Breakdown -->
<div class="grid-3" style="margin-bottom: 32px;">
    <div class="card" style="border-left: 4px solid var(--warning);">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Active Book Loans</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--warning); margin-top: 4px;"><?= $active_loans ?></div>
    </div>
    <div class="card" style="border-left: 4px solid var(--danger);">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Overdue Book Loans</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--danger); margin-top: 4px;"><?= $overdue_loans ?></div>
    </div>
    <div class="card" style="border-left: 4px solid var(--success);">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Returned Books</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--success); margin-top: 4px;"><?= $returned_loans ?></div>
    </div>
</div>

<!-- Fines Breakdown -->
<div class="grid-3" style="margin-bottom: 32px;">
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Fines Issued</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;">€<?= number_format($total_fines, 2) ?></div>
    </div>
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Fines Paid</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--success); margin-top: 4px;">€<?= number_format($paid_fines, 2) ?></div>
    </div>
    <div class="card">
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Fines Outstanding</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--danger); margin-top: 4px;">€<?= number_format($unpaid_fines, 2) ?></div>
    </div>
</div>

<div class="grid-2" style="margin-bottom: 32px;">
    <!-- Most Borrowed Books -->
    <div class="card">
        <h3><i class="fas fa-trophy"></i> Most Borrowed Books</h3>
        <?php if (empty($top_books)): ?>
            <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No loans yet.</p>
        <?php else: ?>
            <?php foreach ($top_books as $index => $tb): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border);">
                    <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: grid; place-items: center; font-weight: 800; font-size: 0.85rem;">
                        <?= $index + 1 ?>
                    </div>
                    <?php if (!empty($tb['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $tb['cover_image'])): ?>
                        <img src="<?= UPLOADS_URL . e($tb['cover_image']) ?>" style="width: 32px; height: 44px; border-radius: 4px; object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 32px; height: 44px; border-radius: 4px; background: var(--bg-alt); display: grid; place-items: center; color: var(--text-muted);">
                            <i class="fas fa-book"></i>
                        </div>
                    <?php endif; ?>
                    <div style="flex: 1;">
                        <strong style="display: block; color: var(--text); font-size: 0.9rem;"><?= e($tb['title']) ?></strong>
                        <small style="color: var(--text-muted); font-size: 0.8rem;"><?= e($tb['author']) ?></small>
                    </div>
                    <div style="font-weight: 700; color: #f59e0b;">
                        <?= $tb['loan_count'] ?>x
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Most Active Members -->
    <div class="card">
        <h3><i class="fas fa-star"></i> Most Active Members</h3>
        <?php if (empty($top_members)): ?>
            <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No members have borrowed books yet.</p>
        <?php else: ?>
            <?php foreach ($top_members as $index => $tm): ?>
                <div style="display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border);">
                    <div style="width: 28px; height: 28px; border-radius: 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: grid; place-items: center; font-weight: 800; font-size: 0.85rem;">
                        <?= $index + 1 ?>
                    </div>
                    <?php if (!empty($tm['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $tm['profile_image'])): ?>
                        <img src="<?= UPLOADS_URL . e($tm['profile_image']) ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.8rem;">
                            <?= strtoupper(substr($tm['email'], 0, 2)) ?>
                        </div>
                    <?php endif; ?>
                    <div style="flex: 1;">
                        <strong style="display: block; color: var(--text); font-size: 0.9rem;"><?= e($tm['first_name'] . ' ' . $tm['last_name']) ?></strong>
                        <small style="color: var(--text-muted); font-size: 0.8rem;"><?= e($tm['email']) ?></small>
                    </div>
                    <div style="font-weight: 700; color: var(--success);">
                        <?= $tm['loan_count'] ?> loans
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2">
    <!-- Category Distribution -->
    <div class="card">
        <h3><i class="fas fa-chart-pie"></i> Books by Category</h3>
        <?php if (empty($categories)): ?>
            <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No categories yet.</p>
        <?php else: ?>
            <?php foreach ($categories as $cat): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border);">
                    <span style="color: var(--text); font-weight: 600;"><?= e($cat['category']) ?></span>
                    <span style="background: var(--bg-alt); padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; color: var(--text-muted);">
                        <?= $cat['count'] ?> cop<?= $cat['count'] != 1 ? 'ies' : 'y' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Monthly Loans -->
    <div class="card">
        <h3><i class="fas fa-calendar-alt"></i> Loans (Last 6 Months)</h3>
        <?php if (empty($monthly)): ?>
            <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No loan history yet.</p>
        <?php else: ?>
            <?php $max_count = max(array_column($monthly, 'count')); ?>
            <?php foreach ($monthly as $m): ?>
                <div style="padding: 10px 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span style="color: var(--text); font-weight: 600; font-size: 0.9rem;"><?= date('F Y', strtotime($m['month'] . '-01')) ?></span>
                        <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600;"><?= $m['count'] ?> loans</span>
                    </div>
                    <div style="height: 8px; background: var(--bg-alt); border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: <?= ($m['count'] / $max_count) * 100 ?>%; background: linear-gradient(90deg, #f59e0b, #d97706); border-radius: 4px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Device Types Overview -->
<div class="card" style="margin-top: 32px;">
    <h3><i class="fas fa-laptop"></i> Device Types Overview</h3>
    <?php if (empty($device_types_stats)): ?>
        <p style="color: var(--text-muted); padding: 20px 0;">No device types yet.</p>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-top: 20px;">
            <?php foreach ($device_types_stats as $dt): ?>
                <?php
                    $type_name = strtolower($dt['name']);
                    $icon = 'fa-laptop';
                    if (strpos($type_name, 'projector') !== false) $icon = 'fa-video';
                    elseif (strpos($type_name, 'tablet') !== false) $icon = 'fa-tablet-alt';
                    elseif (strpos($type_name, 'camera') !== false) $icon = 'fa-camera';
                    elseif (strpos($type_name, 'headphone') !== false) $icon = 'fa-headphones';
                    elseif (strpos($type_name, 'phone') !== false) $icon = 'fa-mobile-alt';
                    elseif (strpos($type_name, 'monitor') !== false) $icon = 'fa-desktop';
                    elseif (strpos($type_name, 'printer') !== false) $icon = 'fa-print';
                    elseif (strpos($type_name, 'speaker') !== false) $icon = 'fa-volume-up';

                    // Scoped queries for availability
                    $avail_stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE device_type_id = ? AND status = 'available' AND branch_id = ?");
                    $avail_stmt->execute([$dt['id'], $branch_id]);
                    $available_count = $avail_stmt->fetchColumn();

                    $borrow_stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE device_type_id = ? AND status = 'borrowed' AND branch_id = ?");
                    $borrow_stmt->execute([$dt['id'], $branch_id]);
                    $borrowed_count = $borrow_stmt->fetchColumn();
                ?>
                <div style="padding: 20px; background: var(--bg-alt); border-radius: 16px; border: 1px solid var(--border); transition: all 0.2s;" onmouseover="this.style.borderColor='#f59e0b'" onmouseout="this.style.borderColor='var(--border)'">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: grid; place-items: center; font-size: 1.2rem;">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <div>
                            <div style="font-weight: 700; color: var(--text); font-size: 1rem;"><?= e($dt['name']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= $dt['loan_days'] ?>-day loan</div>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8rem;">
                        <span style="color: var(--success); font-weight: 600;">
                            <i class="fas fa-check-circle"></i> <?= $available_count ?> available
                        </span>
                        <span style="color: var(--warning); font-weight: 600;">
                            <i class="fas fa-clock"></i> <?= $borrowed_count ?> on loan
                        </span>
                    </div>
                    <div style="margin-top: 12px; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                        <?php
                            $total = $available_count + $borrowed_count;
                            $avail_pct = $total > 0 ? ($available_count / $total) * 100 : 0;
                        ?>
                        <div style="height: 100%; width: <?= $avail_pct ?>%; background: linear-gradient(90deg, #f59e0b, #d97706); border-radius: 3px;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
