<?php
// admin/book_edit.php — Admin Book Editor

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'admin' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$book_id = (int)($_GET['id'] ?? 0);
if ($book_id <= 0) {
    header('Location: ' . SITE_URL . 'admin/books.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    header('Location: ' . SITE_URL . 'admin/books.php');
    exit;
}

$flash = '';
$error = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $isbn = trim($_POST['isbn']);
    $category = trim($_POST['category']);
    $publisher = trim($_POST['publisher']);
    $year = (int)$_POST['year'];
    $description = trim($_POST['description']);
    $cover_image = $book['cover_image'];

    if (empty($title) || empty($author)) {
        $error = 'Title and author are required.';
    } else {
        // Upload new cover
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $new_cover = 'book_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], __DIR__ . '/../uploads/' . $new_cover)) {
                    if (!empty($book['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $book['cover_image'])) {
                        @unlink(__DIR__ . '/../uploads/' . $book['cover_image']);
                    }
                    $cover_image = $new_cover;
                }
            }
        }

        try {
            $update = $pdo->prepare("UPDATE books SET title = ?, author = ?, isbn = ?, category = ?, publisher = ?, year = ?, description = ?, cover_image = ? WHERE id = ?");
            $update->execute([$title, $author, $isbn, $category, $publisher, $year ?: null, $description, $cover_image, $book_id]);
            $flash = 'Book updated successfully.';

            $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
            $stmt->execute([$book_id]);
            $book = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

// Fetch copy stats
$total_copies_stmt = $pdo->prepare("SELECT COUNT(*) FROM book_copies WHERE book_id = ?");
$total_copies_stmt->execute([$book_id]);
$total_copies = (int)$total_copies_stmt->fetchColumn();

$avail_copies_stmt = $pdo->prepare("SELECT COUNT(*) FROM book_copies WHERE book_id = ? AND status = 'available'");
$avail_copies_stmt->execute([$book_id]);
$available_copies = (int)$avail_copies_stmt->fetchColumn();

$page_title = 'Edit Book';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <a href="<?= e(SITE_URL) ?>admin/books.php" style="display: inline-flex; align-items: center; gap: 6px; color: var(--text-muted); text-decoration: none; font-size: 0.9rem; margin-bottom: 8px;">
            <i class="fas fa-arrow-left"></i> Back to Books
        </a>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Edit Book</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Update book details, cover image, and metadata.</p>
    </div>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2">
    <!-- Left: Cover + Details -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="height: 320px; background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05)); display: flex; align-items: center; justify-content: center;">
            <?php if (!empty($book['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $book['cover_image'])): ?>
                <img src="<?= UPLOADS_URL . e($book['cover_image']) ?>?v=<?= time() ?>" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                <i class="fas fa-book" style="font-size: 5rem; color: rgba(59, 130, 246, 0.4);"></i>
            <?php endif; ?>
        </div>
        <div style="padding: 24px;">
            <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text); margin-bottom: 4px;"><?= e($book['title']) ?></h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 16px;">by <?= e($book['author']) ?></p>

            <div style="display: flex; gap: 24px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid var(--border);">
                <div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--text);"><?= $total_copies ?></div>
                    <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Total Copies</div>
                </div>
                <div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--success);"><?= $available_copies ?></div>
                    <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Available</div>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.9rem;">
                <?php if ($book['isbn']): ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">ISBN</span>
                        <span style="color: var(--text); font-weight: 600;"><?= e($book['isbn']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($book['category']): ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Category</span>
                        <span style="color: var(--text); font-weight: 600;"><?= e($book['category']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($book['publisher']): ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Publisher</span>
                        <span style="color: var(--text); font-weight: 600;"><?= e($book['publisher']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($book['year']): ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--text-muted);">Year</span>
                        <span style="color: var(--text); font-weight: 600;"><?= e($book['year']) ?></span>
                    </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-muted);">Book ID</span>
                    <span style="color: var(--text); font-weight: 600;">#<?= $book['id'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Edit Form -->
    <div class="card">
        <h3><i class="fas fa-pen"></i> Book Details</h3>

        <form method="post" enctype="multipart/form-data">
            <div class="grid-2">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" required value="<?= e($book['title']) ?>">
                </div>
                <div class="form-group">
                    <label>Author *</label>
                    <input type="text" name="author" required value="<?= e($book['author']) ?>">
                </div>
                <div class="form-group">
                    <label>ISBN</label>
                    <input type="text" name="isbn" value="<?= e($book['isbn']) ?>">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" value="<?= e($book['category']) ?>">
                </div>
                <div class="form-group">
                    <label>Publisher</label>
                    <input type="text" name="publisher" value="<?= e($book['publisher']) ?>">
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" value="<?= e($book['year']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4"><?= e($book['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label>Replace Cover Image</label>
                <input type="file" name="cover_image" accept="image/*">
                <small style="color: var(--text-muted); display: block; margin-top: 6px; font-size: 0.8rem;">Leave empty to keep the current cover.</small>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <a href="<?= e(SITE_URL) ?>admin/books.php" class="btn-outline">Cancel</a>
                <button type="submit" class="btn-primary" style="width: auto; background: linear-gradient(135deg, #3b82f6, #2563eb);">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
