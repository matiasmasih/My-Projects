<?php
// admin/copies.php — Manage Physical Copies of a Book
// Kirjasto 2026 — Phase 9

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$book_id = (int)($_GET['book_id'] ?? 0);
if ($book_id <= 0) {
    header('Location: ' . SITE_URL . 'admin/books.php');
    exit;
}

// Fetch the book
$stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    header('Location: ' . SITE_URL . 'admin/books.php');
    exit;
}

$flash = '';
$error = '';

// ---- ADD NEW COPY ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_copy') {
    $branch_id = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
    $condition = $_POST['condition'] ?? 'good';
    $location = trim($_POST['location'] ?? '');

    if (!$branch_id) {
        $error = 'Please select a branch for the new copy.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM book_copies WHERE book_id = ?");
            $stmt->execute([$book_id]);
            $count = (int)$stmt->fetchColumn() + 1;
            $copy_number = 'C' . str_pad($count, 3, '0', STR_PAD_LEFT);

            $pdo->prepare("INSERT INTO book_copies (book_id, copy_number, `condition`, status, location, branch_id) VALUES (?, ?, ?, 'available', ?, ?)")
                ->execute([$book_id, $copy_number, $condition, $location, $branch_id]);

            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)")
                ->execute([$_SESSION['user_id'], 'copy.add', "Added copy $copy_number to book ID $book_id at branch $branch_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            $flash = "New copy $copy_number added successfully.";
        } catch (PDOException $e) {
            $error = 'Failed to add copy: ' . $e->getMessage();
        }
    }
}

// ---- CHANGE BRANCH ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_branch') {
    $copy_id = (int)$_POST['copy_id'];
    $new_branch = $_POST['branch_id'] === '' ? null : (int)$_POST['branch_id'];
    try {
        $pdo->prepare("UPDATE book_copies SET branch_id = ? WHERE id = ? AND book_id = ?")->execute([$new_branch, $copy_id, $book_id]);
        $flash = 'Copy branch updated.';
    } catch (PDOException $e) {
        $error = 'Failed to update branch: ' . $e->getMessage();
    }
}

// ---- CHANGE STATUS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_status') {
    $copy_id = (int)$_POST['copy_id'];
    $new_status = $_POST['status'];
    try {
        $pdo->prepare("UPDATE book_copies SET status = ? WHERE id = ? AND book_id = ?")->execute([$new_status, $copy_id, $book_id]);
        $flash = 'Copy status updated.';
    } catch (PDOException $e) {
        $error = 'Failed to update status: ' . $e->getMessage();
    }
}

// ---- DELETE COPY ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_copy') {
    $copy_id = (int)$_POST['copy_id'];
    $stmt = $pdo->prepare("SELECT status FROM book_copies WHERE id = ? AND book_id = ?");
    $stmt->execute([$copy_id, $book_id]);
    $copy = $stmt->fetch();
    if ($copy && $copy['status'] === 'borrowed') {
        $error = 'Cannot delete a copy that is currently borrowed.';
    } elseif ($copy) {
        try {
            $pdo->prepare("DELETE FROM book_copies WHERE id = ?")->execute([$copy_id]);
            $flash = 'Copy deleted.';
        } catch (PDOException $e) {
            $error = 'Cannot delete — this copy may be referenced by loans or transfers.';
        }
    }
}

// ---- FETCH COPIES ----
$stmt = $pdo->prepare("
    SELECT bc.*, br.name AS branch_name
    FROM book_copies bc
    LEFT JOIN branches br ON br.id = bc.branch_id
    WHERE bc.book_id = ?
    ORDER BY bc.copy_number ASC
");
$stmt->execute([$book_id]);
$copies = $stmt->fetchAll();

$total = count($copies);
$available = 0;
foreach ($copies as $c) { if ($c['status'] === 'available') $available++; }

$branches = get_branches();

$page_title = 'Manage Copies';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="margin-bottom: 32px;">
    <a href="<?= e(SITE_URL) ?>admin/books.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); text-decoration: none; font-size: 0.9rem; margin-bottom: 8px;">
        <i class="fas fa-arrow-left"></i> Back to Books
    </a>
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Physical Copies</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <strong style="color: var(--text);"><?= e($book['title']) ?></strong> by <?= e($book['author']) ?>
    </p>
</div>

<?php if ($flash): ?><div class="flash flash-success" style="margin-bottom: 24px;"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error" style="margin-bottom: 24px;"><?= e($error) ?></div><?php endif; ?>

<!-- Single 2-column grid: everything aligns -->
<div style="display: grid; grid-template-columns: 1.6fr 1fr; gap: 24px; align-items: start;">

    <!-- Row 1, Left: Total Copies -->
    <div class="stat-card">
        <div class="stat-icon blue" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
            <i class="fas fa-book"></i>
        </div>
        <div>
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-label">Total Copies</div>
        </div>
    </div>

    <!-- Row 1, Right: Available -->
    <div class="stat-card">
        <div class="stat-icon green" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div>
            <div class="stat-value"><?= $available ?></div>
            <div class="stat-label">Available</div>
        </div>
    </div>

    <!-- Row 2, Left: All Copies Table -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); background: var(--bg-alt);">
            <h3 style="margin: 0;"><i class="fas fa-list" style="color: #3b82f6;"></i> All Copies</h3>
        </div>

        <?php if (empty($copies)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-book" style="font-size: 3rem; color: var(--border); margin-bottom: 16px;"></i>
                <p style="color: var(--text-muted); margin: 0;">No copies yet. Add the first one on the right.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead style="background: var(--card-bg);">
                        <tr>
                            <th style="padding: 14px 16px; border-bottom: 1px solid var(--border); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Copy #</th>
                            <th style="padding: 14px 16px; border-bottom: 1px solid var(--border); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Branch</th>
                            <th style="padding: 14px 16px; border-bottom: 1px solid var(--border); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);">Status</th>
                            <th style="padding: 14px 16px; border-bottom: 1px solid var(--border); font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($copies as $c): ?>
                            <tr>
                                <td style="padding: 14px 16px; border-bottom: 1px solid var(--border);">
                                    <strong style="color: var(--text);"><?= e($c['copy_number']) ?></strong>
                                    <?php if (!empty($c['location'])): ?>
                                        <br><small style="color: var(--text-muted);"><i class="fas fa-map-pin"></i> <?= e($c['location']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 14px 16px; border-bottom: 1px solid var(--border);">
                                    <form method="post" style="margin: 0; display: flex; gap: 6px; align-items: center;">
                                        <input type="hidden" name="action" value="change_branch">
                                        <input type="hidden" name="copy_id" value="<?= $c['id'] ?>">
                                        <select name="branch_id" style="padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--text); font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                                            <option value="">— None —</option>
                                            <?php foreach ($branches as $b): ?>
                                                <option value="<?= $b['id'] ?>" <?= $c['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" style="padding: 6px 10px; font-size: 0.7rem; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; text-transform: uppercase;">Save</button>
                                    </form>
                                </td>
                                <td style="padding: 14px 16px; border-bottom: 1px solid var(--border);">
                                    <form method="post" style="margin: 0; display: flex; gap: 6px; align-items: center;">
                                        <input type="hidden" name="action" value="change_status">
                                        <input type="hidden" name="copy_id" value="<?= $c['id'] ?>">
                                        <select name="status" style="padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--text); font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                                            <option value="available" <?= $c['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                            <option value="borrowed" <?= $c['status'] === 'borrowed' ? 'selected' : '' ?>>Borrowed</option>
                                            <option value="reserved" <?= $c['status'] === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                                            <option value="in_transit" <?= $c['status'] === 'in_transit' ? 'selected' : '' ?>>In Transit</option>
                                            <option value="lost" <?= $c['status'] === 'lost' ? 'selected' : '' ?>>Lost</option>
                                            <option value="repair" <?= $c['status'] === 'repair' ? 'selected' : '' ?>>Repair</option>
                                        </select>
                                        <button type="submit" style="padding: 6px 10px; font-size: 0.7rem; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; text-transform: uppercase;">Save</button>
                                    </form>
                                </td>
                                <td style="padding: 14px 16px; border-bottom: 1px solid var(--border); text-align: right;">
                                    <form method="post" style="margin: 0;" onsubmit="return confirm('Delete copy <?= e($c['copy_number']) ?>?');">
                                        <input type="hidden" name="action" value="delete_copy">
                                        <input type="hidden" name="copy_id" value="<?= $c['id'] ?>">
                                        <button type="submit" style="padding: 6px 12px; font-size: 0.75rem; border-radius: 50px; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.1); color: #ef4444; cursor: pointer; font-weight: 700; text-transform: uppercase;">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Row 2, Right: Add New Copy Form -->
    <div class="card">
        <h3><i class="fas fa-plus-circle" style="color: #10b981;"></i> Add New Copy</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 8px 0 24px 0;">Register a new physical copy of this book.</p>

        <form method="post">
            <input type="hidden" name="action" value="add_copy">

            <div class="form-group">
                <label>Branch *</label>
                <select name="branch_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                    <option value="">— Select branch —</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Condition</label>
                <select name="condition" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                    <option value="excellent">Excellent</option>
                    <option value="good" selected>Good</option>
                    <option value="fair">Fair</option>
                    <option value="poor">Poor</option>
                    <option value="repair">Repair</option>
                </select>
            </div>

            <div class="form-group">
                <label>Location (shelf/section)</label>
                <input type="text" name="location" placeholder="e.g. Shelf A3, Fiction" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="submit" class="btn-primary" style="padding: 12px 28px; background: linear-gradient(135deg, #10b981, #059669); border: none; border-radius: 8px; font-weight: 700; cursor: pointer;">
                    <i class="fas fa-plus"></i> Add Copy
                </button>
            </div>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
