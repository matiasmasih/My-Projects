<?php
// admin/books.php — Global Book Management

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
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

$flash = '';
$error = '';

// ---- ADD BOOK ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_book') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $isbn = trim($_POST['isbn']);
    $category = trim($_POST['category']);
    $publisher = trim($_POST['publisher']);
    $year = (int)$_POST['year'];
    $copies_count = (int)($_POST['copies_count'] ?? 1);
    $cover_image = null;

    if (empty($title) || empty($author)) {
        $error = 'Title and author are required.';
    } else {
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $cover_image = 'book_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], __DIR__ . '/../uploads/' . $cover_image)) {
                    $cover_image = null;
                }
            }
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, category, publisher, year, cover_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $author, $isbn, $category, $publisher, $year ?: null, $cover_image]);
            $book_id = $pdo->lastInsertId();

            for ($i = 1; $i <= max(1, $copies_count); $i++) {
                $copy_number = 'C' . str_pad($i, 3, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO book_copies (book_id, copy_number, `condition`, status, location) VALUES (?, ?, 'good', 'available', 'Main Shelf')")
                    ->execute([$book_id, $copy_number]);
            }

            $pdo->commit();
            $flash = 'Book added with ' . $copies_count . ' copies!';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to add book.';
        }
    }
}

// ---- DELETE BOOK ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_book') {
    $book_id = (int)$_POST['book_id'];
    try {
        $pdo->prepare("DELETE FROM books WHERE id = ?")->execute([$book_id]);
        $flash = 'Book deleted successfully.';
    } catch (PDOException $e) {
        $error = 'Cannot delete — book may have active loans.';
    }
}

// ---- SEARCH & FILTER ----
$search = trim($_GET['search'] ?? '');
$query = "
    SELECT b.*, 
           (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id) AS total_copies,
           (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND status = 'available') AS available_copies
    FROM books b
";
$params = [];
if ($search !== '') {
    $query .= " WHERE b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?";
    $term = '%' . $search . '%';
    $params = [$term, $term, $term];
}
$query .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$books = $stmt->fetchAll();

$page_title = 'Manage Books';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Global Books</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Manage all books across the library system.</p>
    </div>
    <button onclick="document.getElementById('addBookModal').style.display='flex'" class="btn-primary" style="width: auto; padding: 12px 24px; background: linear-gradient(135deg, #3b82f6, #2563eb);">
        <i class="fas fa-plus"></i> Add Book
    </button>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<!-- Search -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <form method="get" style="display: flex; gap: 12px;">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by title, author, or ISBN..." style="flex: 1; padding: 12px 16px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: var(--font); font-size: 0.95rem; outline: none;">
        <button type="submit" class="btn-primary" style="width: auto; padding: 12px 24px; background: linear-gradient(135deg, #3b82f6, #2563eb);">
            <i class="fas fa-search"></i> Search
        </button>
    </form>
</div>

<!-- Books Grid -->
<?php if (empty($books)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-book" style="font-size: 3rem; opacity: 0.3; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
        <h3 style="color: var(--text-muted);">No Books Found</h3>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">
        <?php foreach ($books as $book): ?>
            <div class="card" style="display: flex; flex-direction: column; padding: 0; overflow: hidden;">
                <div style="height: 200px; background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(59,130,246,0.05)); display: flex; align-items: center; justify-content: center; position: relative;">
                    <?php if (!empty($book['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $book['cover_image'])): ?>
                        <img src="<?= UPLOADS_URL . e($book['cover_image']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-book" style="font-size: 4rem; color: rgba(59, 130, 246, 0.4);"></i>
                    <?php endif; ?>
                    <div style="position: absolute; top: 12px; right: 12px;">
                        <span style="padding: 6px 12px; background: <?= $book['available_copies'] > 0 ? 'var(--success)' : 'var(--danger)' ?>; color: white; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">
                            <?= $book['available_copies'] ?>/<?= $book['total_copies'] ?> Available
                        </span>
                    </div>
                </div>
                <div style="padding: 20px; flex: 1; display: flex; flex-direction: column;">
                    <h4 style="font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 4px;"><?= e($book['title']) ?></h4>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 16px;">by <?= e($book['author']) ?></p>
                    <div style="margin-top: auto; display: flex; gap: 8px;">
                        <a href="<?= e(SITE_URL) ?>admin/book_edit.php?id=<?= $book['id'] ?>" class="btn-outline" style="flex: 1; padding: 8px; font-size: 0.8rem;">
                            <i class="fas fa-pen"></i> Edit
                        </a>
                        <a href="<?= e(SITE_URL) ?>admin/copies.php?book_id=<?= $book['id'] ?>" class="btn-outline" style="flex: 1; padding: 8px; font-size: 0.8rem; color: #10b981; border-color: rgba(16, 185, 129, 0.3);">
                            <i class="fas fa-book"></i> Copies
                        </a>
                        <form method="post" onsubmit="return confirm('Delete this book?');" style="flex: 1;">
                            <input type="hidden" name="action" value="delete_book">
                            <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                            <button type="submit" class="btn-outline" style="width: 100%; padding: 8px; font-size: 0.8rem; color: var(--danger); border-color: rgba(239, 68, 68, 0.3);">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add Book Modal -->
<div id="addBookModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto; background: var(--card-solid);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0;"><i class="fas fa-plus-circle" style="color: #3b82f6;"></i> Add New Book</h3>
            <button onclick="document.getElementById('addBookModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_book">
            <div class="grid-2">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Author *</label>
                    <input type="text" name="author" required>
                </div>
                <div class="form-group">
                    <label>ISBN</label>
                    <input type="text" name="isbn">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category">
                </div>
                <div class="form-group">
                    <label>Publisher</label>
                    <input type="text" name="publisher">
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year">
                </div>
            </div>
            <div class="form-group">
                <label>Cover Image</label>
                <input type="file" name="cover_image" accept="image/*">
            </div>
            <div class="form-group">
                <label>Number of Copies</label>
                <input type="number" name="copies_count" value="1" min="1" max="50">
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" onclick="document.getElementById('addBookModal').style.display='none'" class="btn-outline">Cancel</button>
                <button type="submit" class="btn-primary" style="width: auto; background: linear-gradient(135deg, #3b82f6, #2563eb);">Add Book</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
