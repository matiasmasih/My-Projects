<?php
// user/dashboard.php — Member Dashboard (Branch Scoped)

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Fetch Member Info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || ($user['role'] !== 'member' && $user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $user['id'];
$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

// ---- FETCH DASHBOARD DATA ----

// 1. Active Book Loans
$stmt = $pdo->prepare("
    SELECT l.*, b.title, b.cover_image, b.author
    FROM loans l
    JOIN book_copies bc ON bc.id = l.book_copy_id
    JOIN books b ON b.id = bc.book_id
    WHERE l.user_id = ? AND l.status = 'active'
    ORDER BY l.due_at ASC
");
$stmt->execute([$user_id]);
$active_loans = $stmt->fetchAll();

// 2. Active Device Loans
$stmt = $pdo->prepare("
    SELECT dl.*, d.brand, d.model, d.serial_number
    FROM device_loans dl
    JOIN devices d ON d.id = dl.device_id
    WHERE dl.user_id = ? AND dl.status = 'active'
    ORDER BY dl.due_at ASC
");
$stmt->execute([$user_id]);
$active_device_loans = $stmt->fetchAll();

// 3. Unpaid Fines
$stmt = $pdo->prepare("SELECT SUM(amount - paid_amount) FROM fines WHERE user_id = ? AND status IN ('unpaid', 'partial')");
$stmt->execute([$user_id]);
$unpaid_fines = (float)$stmt->fetchColumn();

// 4. New Arrivals (Latest 4 books available in the member's branch)
$new_books = [];
if ($branch_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT b.* 
        FROM books b 
        JOIN book_copies bc ON bc.book_id = b.id 
        WHERE bc.branch_id = ? AND bc.status = 'available'
        ORDER BY b.created_at DESC 
        LIMIT 4
    ");
    $stmt->execute([$branch_id]);
    $new_books = $stmt->fetchAll();
} else {
    $new_books = $pdo->query("SELECT * FROM books ORDER BY created_at DESC LIMIT 4")->fetchAll();
}

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/user_header.php';
?>

<!-- Hero Banner -->
<div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); padding: 40px; margin-bottom: 32px; position: relative; overflow: hidden;">
    <div style="position: relative; z-index: 2; max-width: 60%;">
        <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text); margin-bottom: 8px;">Welcome back, <?= e($user['first_name']) ?>!</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 24px;">
            <?= $branch_name ? 'You are registered at the <strong>' . e($branch_name) . '</strong> branch.' : 'Here is what\'s happening with your library account today.' ?>
        </p>

        <div style="display: flex; gap: 16px;">
            <a href="<?= e(SITE_URL) ?>user/books.php" class="btn btn-primary" style="padding: 12px 24px; border-radius: 8px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; font-weight: 600; text-decoration: none;">
                <i class="fas fa-search"></i> Browse Catalog
            </a>
            <a href="<?= e(SITE_URL) ?>user/loans.php" class="btn btn-secondary" style="padding: 12px 24px; border-radius: 8px; background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); font-weight: 600; text-decoration: none;">
                View My Loans
            </a>
        </div>
    </div>
    <!-- Decorative background icon -->
    <i class="fas fa-book-open" style="position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: #10b981; opacity: 0.05; z-index: 1; transform: rotate(-15deg);"></i>
</div>

<!-- Stats Grid -->
<div class="stat-grid" style="margin-bottom: 32px;">
    <div class="stat-card">
        <div class="stat-icon green" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
            <i class="fas fa-book"></i>
        </div>
        <div>
            <div class="stat-value"><?= count($active_loans) ?></div>
            <div class="stat-label">Active Books</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
            <i class="fas fa-laptop"></i>
        </div>
        <div>
            <div class="stat-value"><?= count($active_device_loans) ?></div>
            <div class="stat-label">Borrowed Devices</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon red" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
            <i class="fas fa-euro-sign"></i>
        </div>
        <div>
            <div class="stat-value">€<?= number_format($unpaid_fines, 2) ?></div>
            <div class="stat-label">Unpaid Fines</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon amber" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div>
            <div class="stat-value">
                <?= count($active_loans) > 0 ? date('d M', strtotime($active_loans[0]['due_at'])) : 'None' ?>
            </div>
            <div class="stat-label">Next Due Date</div>
        </div>
    </div>
</div>

<!-- Currently Reading Section -->
<div style="margin-bottom: 32px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text);">Currently Reading</h2>
    </div>

    <?php if (empty($active_loans)): ?>
        <div class="card" style="text-align: center; padding: 40px 20px;">
            <i class="fas fa-book-open" style="font-size: 3rem; color: var(--border); margin-bottom: 16px;"></i>
            <h3 style="color: var(--text); margin-bottom: 8px;">You have no active loans.</h3>
            <p style="color: var(--text-muted); margin-bottom: 24px;">Find your next favorite book in our catalog.</p>
            <a href="<?= e(SITE_URL) ?>user/books.php" class="btn btn-primary" style="display: inline-block; padding: 10px 24px; border-radius: 8px; background: #10b981; color: white; text-decoration: none; font-weight: 600;">Browse Books</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
            <?php foreach ($active_loans as $loan):
                $is_overdue = strtotime(date('Y-m-d H:i:s')) > strtotime($loan['due_at']);
            ?>
                <div class="card" style="display: flex; flex-direction: column; padding: 0; overflow: hidden;">
                    <!-- Book Cover Area -->
                    <div style="height: 140px; background: var(--bg-alt); display: flex; align-items: center; justify-content: center; position: relative;">
                        <?php if (!empty($loan['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $loan['cover_image'])): ?>
                            <img src="<?= UPLOADS_URL . e($loan['cover_image']) ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-book" style="font-size: 3rem; color: var(--border);"></i>
                        <?php endif; ?>

                        <span class="badge <?= $is_overdue ? 'badge-danger' : 'badge-success' ?>" style="position: absolute; top: 12px; right: 12px;">
                            <?= $is_overdue ? 'Overdue' : 'On Time' ?>
                        </span>
                    </div>

                    <!-- Book Info -->
                    <div style="padding: 20px; flex-grow: 1; display: flex; flex-direction: column;">
                        <h4 style="margin: 0 0 4px 0; font-size: 1.1rem; color: var(--text);"><?= e($loan['title']) ?></h4>
                        <p style="margin: 0 0 16px 0; color: var(--text-muted); font-size: 0.85rem;"><?= e($loan['author']) ?></p>

                        <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 12px;">
                            <span style="font-size: 0.8rem; color: var(--text-muted);">Due: <?= date('d M Y', strtotime($loan['due_at'])) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- New Arrivals Section -->
<div style="margin-bottom: 32px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text);">New Arrivals in Your Branch</h2>
        <a href="<?= e(SITE_URL) ?>user/books.php" style="color: #10b981; text-decoration: none; font-weight: 600; font-size: 0.9rem;">View All <i class="fas fa-arrow-right"></i></a>
    </div>

    <?php if (empty($new_books)): ?>
        <div class="card" style="text-align: center; padding: 40px 20px;">
            <p style="color: var(--text-muted); margin: 0;">No new books available in your branch yet.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px;">
            <?php foreach ($new_books as $book): ?>
                <div class="card" style="padding: 0; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <div style="height: 200px; background: var(--bg-alt); display: flex; align-items: center; justify-content: center;">
                        <?php if (!empty($book['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $book['cover_image'])): ?>
                            <img src="<?= UPLOADS_URL . e($book['cover_image']) ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-book" style="font-size: 4rem; color: var(--border);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="padding: 16px;">
                        <h4 style="margin: 0 0 4px 0; font-size: 1rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($book['title']) ?></h4>
                        <p style="margin: 0 0 12px 0; color: var(--text-muted); font-size: 0.8rem;"><?= e($book['author']) ?></p>
                        <a href="<?= e(SITE_URL) ?>user/book_detail.php?id=<?= $book['id'] ?>" class="btn btn-secondary" style="display: block; text-align: center; padding: 8px; border-radius: 6px; background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); text-decoration: none; font-size: 0.85rem; font-weight: 600;">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
