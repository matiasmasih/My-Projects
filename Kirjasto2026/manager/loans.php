<?php
// manager/loans.php — Process Loans, Returns, Fines (branch-scoped)

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

$stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$manager_row = $stmt->fetch();
$branch_id = $manager_row['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$flash = '';
$error = '';
$fine_per_day = (float)get_setting('fine_per_day', '0.50');

// ---- RETURN BOOK ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'return_book') {
    $loan_id = (int)$_POST['loan_id'];
    if (!$branch_id) {
        $error = 'Your account is not assigned to a branch. Contact an administrator.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT l.*, bc.id AS copy_id FROM loans l JOIN book_copies bc ON bc.id = l.book_copy_id WHERE l.id = ? AND l.branch_id = ?");
            $stmt->execute([$loan_id, $branch_id]);
            $loan = $stmt->fetch();
            if (!$loan) throw new Exception('Loan not found in your branch.');

            $now = date('Y-m-d H:i:s');
            $due = strtotime($loan['due_at']);
            $returned = strtotime($now);
            $days_late = 0;
            $fine_amount = 0;

            if ($returned > $due) {
                $days_late = ceil(($returned - $due) / 86400);
                $fine_amount = $days_late * $fine_per_day;
            }

            $pdo->prepare("UPDATE loans SET returned_at = ?, status = 'returned', fine_amount = ? WHERE id = ?")->execute([$now, $fine_amount, $loan_id]);
            $pdo->prepare("UPDATE book_copies SET status = 'available' WHERE id = ?")->execute([$loan['copy_id']]);

            if ($fine_amount > 0) {
                $pdo->prepare("INSERT INTO fines (user_id, loan_id, amount, reason, status, due_at) VALUES (?, ?, ?, ?, 'unpaid', ?)")
                    ->execute([$loan['user_id'], $loan_id, $fine_amount, "Overdue return ($days_late days late)", date('Y-m-d', strtotime('+14 days'))]);
                $flash = "Book returned. Fine issued: €" . number_format($fine_amount, 2) . " ($days_late days late).";
            } else {
                $flash = 'Book returned on time. No fine.';
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Return failed: ' . $e->getMessage();
        }
    }
}

// ---- EXTEND LOAN ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'extend_loan') {
    $loan_id = (int)$_POST['loan_id'];
    if (!$branch_id) {
        $error = 'Your account is not assigned to a branch.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND branch_id = ?");
            $stmt->execute([$loan_id, $branch_id]);
            $loan = $stmt->fetch();
            if (!$loan) throw new Exception('Loan not found in your branch.');
            if ($loan['status'] !== 'active') throw new Exception('Only active loans can be extended.');
            if ($loan['extended_count'] >= 2) throw new Exception('Maximum extensions reached (2).');
            $new_due = date('Y-m-d H:i:s', strtotime($loan['due_at'] . ' +7 days'));
            $pdo->prepare("UPDATE loans SET due_at = ?, extended_count = extended_count + 1 WHERE id = ?")->execute([$new_due, $loan_id]);
            $flash = 'Loan extended by 7 days. New due date: ' . date('M j, Y', strtotime($new_due));
        } catch (Exception $e) {
            $error = 'Extend failed: ' . $e->getMessage();
        }
    }
}

// ---- FETCH LOANS (branch-scoped & searchable) ----
$filter = $_GET['filter'] ?? 'active';
$search = trim($_GET['search'] ?? '');

$loans = [];
if ($branch_id) {
    $query = "
        SELECT l.*, b.title AS book_title, b.author AS book_author, b.cover_image,
               u.first_name, u.last_name, u.email, u.profile_image, bc.copy_number,
               DATEDIFF(NOW(), l.due_at) AS days_overdue
        FROM loans l
        JOIN book_copies bc ON bc.id = l.book_copy_id
        JOIN books b ON b.id = bc.book_id
        JOIN users u ON u.id = l.user_id
        WHERE l.branch_id = ?
    ";
    $params = [$branch_id];

    if ($filter === 'active') {
        $query .= " AND l.status = 'active' AND l.due_at >= NOW()";
    } elseif ($filter === 'overdue') {
        $query .= " AND l.status IN ('active', 'overdue') AND l.due_at < NOW()";
    } elseif ($filter === 'returned') {
        $query .= " AND l.status = 'returned'";
    }

    if ($search !== '') {
        $query .= " AND (b.title LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $like = "%$search%";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $query .= " ORDER BY l.due_at ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $loans = $stmt->fetchAll();
}

$page_title = 'Loans';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Manage Loans</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
            <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Process returns, extend loans, and manage overdue books.
        </p>
    </div>
</div>

<?php if (!$branch_id): ?>
    <div class="flash flash-error" style="margin-bottom: 24px;"><i class="fas fa-exclamation-triangle"></i> Your account is not assigned to a branch.</div>
<?php endif; ?>
<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<!-- Filter & Search Bar -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="?filter=active&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'active' ? 'background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-book-open"></i> Active
            </a>
            <a href="?filter=overdue&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'overdue' ? 'background: var(--danger); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-exclamation-triangle"></i> Overdue
            </a>
            <a href="?filter=returned&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'returned' ? 'background: var(--success); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-check-circle"></i> Returned
            </a>
            <a href="?filter=all&search=<?= urlencode($search) ?>" class="btn-outline" style="padding: 10px 20px; <?= $filter === 'all' ? 'background: var(--primary); color: white; border-color: transparent;' : '' ?>">
                <i class="fas fa-list"></i> All
            </a>
        </div>
        
        <form method="GET" style="display: flex; gap: 8px; flex: 1; max-width: 400px;">
            <input type="hidden" name="filter" value="<?= e($filter) ?>">
            <div style="position: relative; flex: 1;">
                <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search member or book..." style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); outline: none;">
            </div>
            <button type="submit" class="btn-primary" style="padding: 0 20px; border-radius: 8px; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: none; cursor: pointer; font-weight: 600;">Search</button>
        </form>
    </div>
</div>

<!-- Loans List -->
<?php if (empty($loans)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.3; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
        <h3 style="color: var(--text-muted); margin-bottom: 8px;">No Loans Found</h3>
        <p style="color: var(--text-muted);">No loans match your search or filter.</p>
    </div>
<?php else: ?>
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Member</th>
                        <th>Borrowed</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): ?>
                        <?php
                            $is_overdue = ($loan['status'] === 'active' && strtotime($loan['due_at']) < time());
                            $status_label = 'Active';
                            $status_color = 'var(--warning)';
                            if ($loan['status'] === 'returned') { $status_label = 'Returned'; $status_color = 'var(--success)'; } 
                            elseif ($is_overdue) { $status_label = 'Overdue'; $status_color = 'var(--danger)'; }
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <?php if (!empty($loan['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $loan['cover_image'])): ?>
                                        <img src="<?= UPLOADS_URL . e($loan['cover_image']) ?>" style="width: 40px; height: 56px; border-radius: 6px; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 56px; border-radius: 6px; background: rgba(245,158,11,0.15); display: grid; place-items: center; color: #f59e0b;"><i class="fas fa-book"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <strong style="display: block; color: var(--text); font-size: 0.9rem;"><?= e($loan['book_title']) ?></strong>
                                        <small style="color: var(--text-muted);"><?= e($loan['book_author']) ?> · Copy <?= e($loan['copy_number']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($loan['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $loan['profile_image'])): ?>
                                        <img src="<?= UPLOADS_URL . e($loan['profile_image']) ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: grid; place-items: center; font-weight: 700; font-size: 0.75rem;"><?= strtoupper(substr($loan['email'], 0, 2)) ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <strong style="display: block; color: var(--text); font-size: 0.85rem;"><?= e($loan['first_name'] . ' ' . $loan['last_name']) ?></strong>
                                        <small style="color: var(--text-muted); font-size: 0.75rem;"><?= e($loan['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;"><?= date('M j, Y', strtotime($loan['borrowed_at'])) ?></td>
                            <td style="color: <?= $is_overdue ? 'var(--danger)' : 'var(--text-muted)' ?>; font-size: 0.85rem; font-weight: <?= $is_overdue ? '700' : '400' ?>;">
                                <?= date('M j, Y', strtotime($loan['due_at'])) ?>
                                <?php if ($is_overdue): ?><br><small style="color: var(--danger);"><?= $loan['days_overdue'] ?> days overdue</small><?php endif; ?>
                            </td>
                            <td><span style="padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; background: <?= $status_color ?>20; color: <?= $status_color ?>;"><?= $status_label ?></span></td>
                            <td style="text-align: right;">
                                <?php if ($loan['status'] === 'active'): ?>
                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Mark this book as returned?');">
                                            <input type="hidden" name="action" value="return_book">
                                            <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                            <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.75rem; background: var(--success); width: auto;"><i class="fas fa-check"></i> Return</button>
                                        </form>
                                        <?php if ($loan['extended_count'] < 2): ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Extend this loan by 7 days?');">
                                                <input type="hidden" name="action" value="extend_loan">
                                                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                                <button type="submit" class="btn-outline" style="padding: 6px 12px; font-size: 0.75rem;"><i class="fas fa-clock"></i> Extend</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
