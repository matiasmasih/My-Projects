<?php
// user/book_detail.php — Book Details, Borrow, Reserve & Transfer

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || ($user['role'] !== 'member' && $user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$book_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    header('Location: ' . SITE_URL . 'user/books.php');
    exit;
}

$flash = '';
$error = '';

// ---- HANDLE RESERVE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reserve') {
    $check_res = $pdo->prepare("SELECT id FROM reservations WHERE user_id = ? AND book_id = ? AND status IN ('pending', 'ready')");
    $check_res->execute([$user_id, $book_id]);

    if ($check_res->fetch()) {
        $error = 'You already have an active reservation for this book.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO reservations (user_id, book_id, reserved_at, status, branch_id) VALUES (?, ?, NOW(), 'pending', ?)")->execute([$user_id, $book_id, $branch_id]);

            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute([$user_id, 'reservation.create', "Member reserved Book ID: $book_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            $pdo->commit();
            $_SESSION['flash_success'] = 'Book reserved successfully! You will be notified when it becomes available.';
            header('Location: ' . SITE_URL . 'user/reservations.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to reserve book: ' . $e->getMessage();
        }
    }
}

// ---- HANDLE BORROW ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'borrow') {
    $stmt = $pdo->prepare("SELECT id FROM book_copies WHERE book_id = ? AND branch_id = ? AND status = 'available' LIMIT 1");
    $stmt->execute([$book_id, $branch_id]);
    $copy = $stmt->fetch();

    $check_loan = $pdo->prepare("SELECT l.id FROM loans l JOIN book_copies bc ON bc.id = l.book_copy_id WHERE l.user_id = ? AND bc.book_id = ? AND l.status = 'active'");
    $check_loan->execute([$user_id, $book_id]);

    if ($check_loan->fetch()) {
        $error = 'You already have an active loan for this book. Please return it before borrowing again.';
    } elseif ($copy) {
        $copy_id = $copy['id'];
        $loan_days = (int) get_setting('default_loan_days', 14);

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO loans (user_id, book_copy_id, borrowed_at, due_at, status, branch_id) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), 'active', ?)");
            $stmt->execute([$user_id, $copy_id, $loan_days, $branch_id]);
            $pdo->prepare("UPDATE book_copies SET status = 'borrowed' WHERE id = ?")->execute([$copy_id]);

            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute([$user_id, 'loan.borrow_self', "Member borrowed Book ID: $book_id, Copy ID: $copy_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            $pdo->commit();
            $_SESSION['flash_success'] = 'Book borrowed successfully! Please pick it up at the front desk.';
            header('Location: ' . SITE_URL . 'user/loans.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to process borrow request. Error: ' . $e->getMessage();
        }
    } else {
        $error = 'This book is not available at your branch.';
    }
}

// ---- HANDLE TRANSFER REQUEST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_transfer') {
    $from_branch_id = (int)$_POST['from_branch_id'];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id FROM book_copies WHERE book_id = ? AND branch_id = ? AND status = 'available' LIMIT 1");
        $stmt->execute([$book_id, $from_branch_id]);
        $copy = $stmt->fetch();

        if (!$copy) throw new Exception('Book is no longer available at the source branch.');

        $pdo->prepare("INSERT INTO transfers (book_copy_id, from_branch_id, to_branch_id, status, requested_by, created_at) VALUES (?, ?, ?, 'pending', ?, NOW())")
            ->execute([$copy['id'], $from_branch_id, $branch_id, $user_id]);

        $pdo->prepare("UPDATE book_copies SET status = 'in_transit' WHERE id = ?")->execute([$copy['id']]);

        $pdo->commit();
        $_SESSION['flash_success'] = 'Transfer requested! The book is now in transit to your branch.';
        header('Location: ' . SITE_URL . 'user/book_detail.php?id=' . $book_id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to request transfer: ' . $e->getMessage();
    }
}

// ---- CHECK AVAILABILITY ----
$stmt = $pdo->prepare("SELECT COUNT(*) FROM book_copies WHERE book_id = ? AND branch_id = ? AND status = 'available'");
$stmt->execute([$book_id, $branch_id]);
$local_copies = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT bc.branch_id, br.name AS branch_name, COUNT(*) AS copies
    FROM book_copies bc
    JOIN branches br ON br.id = bc.branch_id
    WHERE bc.book_id = ? AND bc.branch_id != ? AND bc.status = 'available'
    GROUP BY bc.branch_id, br.name
");
$stmt->execute([$book_id, $branch_id]);
$other_branch_copies = $stmt->fetchAll();

$page_title = $book['title'];
require_once __DIR__ . '/../includes/user_header.php';
?>

<!-- Back Button -->
<div style="margin-bottom: 32px;">
    <a href="<?= e(SITE_URL) ?>user/books.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 600;">
        <i class="fas fa-arrow-left"></i> Back to Catalog
    </a>
</div>

<?php if (isset($_SESSION['flash_success'])): ?>
    <div class="flash flash-success" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; color: #10b981; font-weight: 600;">
        <i class="fas fa-check-circle"></i> <?= e($_SESSION['flash_success']) ?>
    </div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<?php if ($error): ?>
    <div class="flash flash-error" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; color: #ef4444; font-weight: 600;">
        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 40px; align-items: start;">
    <!-- Left: Cover -->
    <div style="position: relative;">
        <div style="position: absolute; top: 20px; left: 20px; width: 100%; height: 100%; background: #10b981; filter: blur(40px); opacity: 0.2; border-radius: 16px; z-index: 0;"></div>
        <div class="card" style="padding: 0; overflow: hidden; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); position: relative; z-index: 1;">
            <div style="aspect-ratio: 2/3; background: var(--bg-alt); display: flex; align-items: center; justify-content: center;">
                <?php if (!empty($book['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $book['cover_image'])): ?>
                    <img src="<?= UPLOADS_URL . e($book['cover_image']) ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted);">
                        <i class="fas fa-book" style="font-size: 5rem; opacity: 0.3; margin-bottom: 16px;"></i>
                        <span style="font-weight: 600;">No Cover Available</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="margin-top: 24px; display: flex; justify-content: center;">
            <?php if ($local_copies > 0): ?>
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 10px 20px; border-radius: 50px; font-weight: 700; font-size: 0.9rem; border: 1px solid rgba(16, 185, 129, 0.3);">
                    <i class="fas fa-check-circle"></i> Available at Your Branch
                </div>
            <?php else: ?>
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 10px 20px; border-radius: 50px; font-weight: 700; font-size: 0.9rem; border: 1px solid rgba(245, 158, 11, 0.3);">
                    <i class="fas fa-clock"></i> Not at Your Branch
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: Details -->
    <div>
        <h1 style="font-size: 3.5rem; font-weight: 800; color: var(--text); margin: 0 0 12px 0; line-height: 1.1;"><?= e($book['title']) ?></h1>
        <p style="font-size: 1.4rem; color: var(--text-muted); margin: 0 0 32px 0;">by <strong style="color: #10b981;"><?= e($book['author']) ?></strong></p>

        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 40px;">
            <div class="card" style="padding: 16px; text-align: center;">
                <i class="fas fa-barcode" style="color: var(--text-muted); margin-bottom: 8px;"></i>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">ISBN</div>
                <div style="font-weight: 700; font-size: 0.9rem; margin-top: 4px;"><?= e($book['isbn'] ?: 'N/A') ?></div>
            </div>
            <div class="card" style="padding: 16px; text-align: center;">
                <i class="fas fa-folder" style="color: var(--text-muted); margin-bottom: 8px;"></i>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Category</div>
                <div style="font-weight: 700; font-size: 0.9rem; margin-top: 4px;"><?= e($book['category'] ?: 'General') ?></div>
            </div>
            <div class="card" style="padding: 16px; text-align: center;">
                <i class="fas fa-building" style="color: var(--text-muted); margin-bottom: 8px;"></i>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Publisher</div>
                <div style="font-weight: 700; font-size: 0.9rem; margin-top: 4px;"><?= e($book['publisher'] ?: 'Unknown') ?></div>
            </div>
            <div class="card" style="padding: 16px; text-align: center;">
                <i class="fas fa-calendar" style="color: var(--text-muted); margin-bottom: 8px;"></i>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Year</div>
                <div style="font-weight: 700; font-size: 0.9rem; margin-top: 4px;"><?= e($book['year'] ?: 'N/A') ?></div>
            </div>
        </div>

        <div style="margin-bottom: 40px;">
            <h3 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-align-left" style="color: #10b981;"></i> About this book
            </h3>
            <p style="color: var(--text-muted); line-height: 1.8; font-size: 1.05rem;">
                <?= nl2br(e($book['description'] ?: 'No description available for this book.')) ?>
            </p>
        </div>

        <!-- Action Area -->
        <div class="card" style="padding: 32px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), transparent); border: 1px solid rgba(16, 185, 129, 0.3);">
            <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 20px;">Choose an action</h3>

            <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                <?php if ($local_copies > 0): ?>
                    <!-- LOCAL BORROW -->
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="borrow">
                        <button type="submit" class="btn-primary" style="padding: 16px 40px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; font-weight: 700; font-size: 1.1rem; cursor: pointer; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.5); display: inline-flex; align-items: center; gap: 10px; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';">
                            <i class="fas fa-book-reader"></i> Borrow Book (<?= e(get_setting('default_loan_days', 14)) ?> days)
                        </button>
                    </form>
                <?php elseif (!empty($other_branch_copies)): ?>
                    <!-- REMOTE TRANSFER -->
                    <?php foreach ($other_branch_copies as $remote): ?>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="request_transfer">
                            <input type="hidden" name="from_branch_id" value="<?= $remote['branch_id'] ?>">
                            <button type="submit" class="btn-primary" style="padding: 16px 40px; border-radius: 10px; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; border: none; font-weight: 700; font-size: 1.1rem; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';">
                                <i class="fas fa-truck"></i> Request Transfer from <?= e($remote['branch_name']) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- RESERVE BUTTON (Always visible) -->
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="reserve">
                    <button type="submit" class="btn-secondary" style="padding: 16px 40px; border-radius: 10px; background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); font-weight: 700; font-size: 1.1rem; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; transition: all 0.2s;" onmouseover="this.style.background='#f59e0b'; this.style.color='white'; this.style.borderColor='#f59e0b';" onmouseout="this.style.background='var(--bg-alt)'; this.style.color='var(--text)'; this.style.borderColor='var(--border)';">
                        <i class="fas fa-bookmark"></i> Reserve Book
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
