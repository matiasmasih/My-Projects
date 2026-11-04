<?php
// user/reports.php — My Reading Reports & Activity
// Kirjasto 2026 — Phase 4

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'member' && $member_user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ---- 1. General Stats ----
$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status = 'returned'");
$stmt->execute([$user_id]);
$total_books_read = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM device_loans WHERE user_id = ? AND status = 'returned'");
$stmt->execute([$user_id]);
$total_devices = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM receipts WHERE user_id = ? AND status = 'paid'");
$stmt->execute([$user_id]);
$total_fines_paid = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM fines WHERE user_id = ? AND status IN ('unpaid', 'partial')");
$stmt->execute([$user_id]);
$total_fines_unpaid = (float)$stmt->fetchColumn();

$reading_goal = (int)get_setting('reading_goal', '12');
$progress_percent = $reading_goal > 0 ? min(100, round(($total_books_read / $reading_goal) * 100)) : 0;

// ---- 2. Monthly Activity ----
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(borrowed_at, '%Y-%m') as month, COUNT(*) as count
    FROM loans
    WHERE user_id = ? AND borrowed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$stmt->execute([$user_id]);
$monthly_activity = $stmt->fetchAll();

// ---- 3. Top Authors ----
$stmt = $pdo->prepare("
    SELECT b.author, COUNT(*) as count
    FROM loans l
    JOIN book_copies bc ON bc.id = l.book_copy_id
    JOIN books b ON b.id = bc.book_id
    WHERE l.user_id = ?
    GROUP BY b.author
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$top_authors = $stmt->fetchAll();

// ---- 4. Full History ----
$stmt = $pdo->prepare("
    SELECT b.title, b.author, l.borrowed_at, l.returned_at, l.status, l.fine_amount
    FROM loans l
    JOIN book_copies bc ON bc.id = l.book_copy_id
    JOIN books b ON b.id = bc.book_id
    WHERE l.user_id = ?
    ORDER BY l.borrowed_at DESC
    LIMIT 20
");
$stmt->execute([$user_id]);
$history = $stmt->fetchAll();

$page_title = 'My Reports';
require_once __DIR__ . '/../includes/user_header.php';
?>

<!-- Hero Banner -->
<div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); padding: 40px; margin-bottom: 32px; position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
    <div style="position: relative; z-index: 2; max-width: 60%;">
        <a href="<?= e(SITE_URL) ?>user/settings.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 600; margin-bottom: 16px;">
            <i class="fas fa-arrow-left"></i> Back to Settings
        </a>
        <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text); margin-bottom: 8px;">My Reading Reports</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; margin: 0;">A complete summary of your library activity.</p>
    </div>
    
    <div style="position: relative; z-index: 2;">
        <button onclick="window.print()" class="btn btn-primary" style="padding: 14px 28px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; font-weight: 700; cursor: pointer; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-print"></i> Print Report
        </button>
    </div>

    <i class="fas fa-chart-bar" style="position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: #10b981; opacity: 0.05; z-index: 1; transform: rotate(-15deg);"></i>
</div>

<!-- Reading Goal Progress -->
<div class="card" style="margin-bottom: 32px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), transparent); border: 1px solid rgba(16, 185, 129, 0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="margin: 0;"><i class="fas fa-bullseye" style="color: #10b981;"></i> <?= date('Y') ?> Reading Goal</h3>
        <span style="font-size: 1.5rem; font-weight: 800; color: #10b981;"><?= $total_books_read ?> / <?= $reading_goal ?></span>
    </div>
    <div style="background: var(--bg-alt); border-radius: 50px; height: 16px; overflow: hidden; margin-bottom: 12px;">
        <div style="width: <?= $progress_percent ?>%; height: 100%; background: linear-gradient(90deg, #10b981, #059669); border-radius: 50px; transition: width 0.5s;"></div>
    </div>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
        <?php if ($progress_percent >= 100): ?>
            🎉 Congratulations! You've achieved your reading goal!
        <?php elseif ($total_books_read > 0): ?>
            You've read <?= $total_books_read ?> of <?= $reading_goal ?> books (<?= $progress_percent ?>%). Keep going!
        <?php else: ?>
            Start your first book of the year to begin your reading goal.
        <?php endif; ?>
    </p>
</div>

<!-- Stat Cards -->
<div class="stat-grid" style="margin-bottom: 32px;">
    <div class="stat-card">
        <div class="stat-icon green" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
            <i class="fas fa-book"></i>
        </div>
        <div>
            <div class="stat-value"><?= $total_books_read ?></div>
            <div class="stat-label">Books Read</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
            <i class="fas fa-laptop"></i>
        </div>
        <div>
            <div class="stat-value"><?= $total_devices ?></div>
            <div class="stat-label">Devices Borrowed</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
            <i class="fas fa-euro-sign"></i>
        </div>
        <div>
            <div class="stat-value">€<?= number_format($total_fines_paid, 2) ?></div>
            <div class="stat-label">Fines Paid</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div>
            <div class="stat-value">€<?= number_format($total_fines_unpaid, 2) ?></div>
            <div class="stat-label">Unpaid Fines</div>
        </div>
    </div>
</div>

<div class="grid-2" style="align-items: start; gap: 32px; margin-bottom: 32px;">
    <!-- Monthly Activity -->
    <div class="card">
        <h3><i class="fas fa-chart-line" style="color: #0ea5e9;"></i> Monthly Activity (Last 6 Months)</h3>
        <?php if (empty($monthly_activity)): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-chart-line" style="font-size: 3rem; color: var(--border); margin-bottom: 16px;"></i>
                <p style="color: var(--text-muted); margin: 0;">No activity yet.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; align-items: flex-end; gap: 16px; height: 200px; padding: 20px 0; margin-top: 20px; border-bottom: 1px solid var(--border);">
                <?php 
                $max_count = max(array_column($monthly_activity, 'count'));
                foreach ($monthly_activity as $m): 
                    $height = ($m['count'] / $max_count) * 150;
                ?>
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <div style="font-weight: 700; color: var(--text);"><?= $m['count'] ?></div>
                        <div style="width: 100%; height: <?= $height ?>px; background: linear-gradient(180deg, #0ea5e9, #0284c7); border-radius: 6px 6px 0 0; min-height: 4px;"></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;"><?= date('M', strtotime($m['month'] . '-01')) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top Authors -->
    <div class="card">
        <h3><i class="fas fa-star" style="color: #f59e0b;"></i> Your Top Authors</h3>
        <?php if (empty($top_authors)): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-user-edit" style="font-size: 3rem; color: var(--border); margin-bottom: 16px;"></i>
                <p style="color: var(--text-muted); margin: 0;">Borrow a book to see your top authors.</p>
            </div>
        <?php else: ?>
            <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($top_authors as $author): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--bg-alt); border-radius: 8px; border: 1px solid var(--border);">
                        <span style="font-weight: 600; color: var(--text);"><?= e($author['author']) ?></span>
                        <span class="badge badge-info"><?= $author['count'] ?> <?= $author['count'] == 1 ? 'book' : 'books' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Full History Table -->
<div class="card">
    <h3><i class="fas fa-history"></i> Complete Loan History</h3>
    <?php if (empty($history)): ?>
        <div style="text-align: center; padding: 40px 20px;">
            <i class="fas fa-book-open" style="font-size: 3rem; color: var(--border); margin-bottom: 16px;"></i>
            <p style="color: var(--text-muted); margin: 0;">You haven't borrowed any books yet.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto; margin-top: 20px;">
            <table class="table" style="width: 100%; border-collapse: collapse; text-align: left; white-space: nowrap;">
                <thead style="background: var(--bg-alt);">
                    <tr>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border);">Book</th>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border);">Author</th>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border);">Borrowed</th>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border);">Returned</th>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border);">Status</th>
                        <th style="padding: 16px 12px; border-bottom: 1px solid var(--border); text-align: right;">Fine</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border); font-weight: 600;"><?= e($h['title']) ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border); color: var(--text-muted);"><?= e($h['author']) ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?= date('d M Y', strtotime($h['borrowed_at'])) ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?= $h['returned_at'] ? date('d M Y', strtotime($h['returned_at'])) : '—' ?></td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                                <?php if ($h['status'] === 'returned'): ?>
                                    <span class="badge badge-success">Returned</span>
                                <?php elseif ($h['status'] === 'overdue'): ?>
                                    <span class="badge badge-danger">Overdue</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Active</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; border-bottom: 1px solid var(--border); text-align: right; font-weight: 600; color: <?= $h['fine_amount'] > 0 ? '#ef4444' : 'var(--text-muted)' ?>;">
                                <?= $h['fine_amount'] > 0 ? '€' . number_format($h['fine_amount'], 2) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
