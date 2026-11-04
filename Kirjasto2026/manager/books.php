<?php
// manager/books.php — Global Catalog with Local Inventory Tracking

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
    $description = trim($_POST['description']);
    $copies_count = (int)($_POST['copies_count'] ?? 1);
    $cover_image = null;

    if (!$branch_id) {
        $error = 'Your account is not assigned to a branch.';
    } elseif (empty($title) || empty($author)) {
        $error = 'Title and author are required.';
    } else {
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $cover_image = 'book_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = __DIR__ . '/../uploads/' . $cover_image;
                if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $upload_path)) {
                    $cover_image = null;
                }
            }
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, category, publisher, year, description, cover_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $author, $isbn, $category, $publisher, $year ?: null, $description, $cover_image]);
            $book_id = $pdo->lastInsertId();

            for ($i = 1; $i <= max(1, $copies_count); $i++) {
                $copy_number = 'C' . str_pad($i, 3, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO book_copies (book_id, copy_number, `condition`, status, location, branch_id) VALUES (?, ?, 'good', 'available', 'Main Shelf', ?)")
                    ->execute([$book_id, $copy_number, $branch_id]);
            }

            $pdo->commit();
            $flash = 'Book added with ' . $copies_count . ' copies!';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to add book: ' . $e->getMessage();
        }
    }
}

// ---- ADD COPY TO EXISTING BOOK ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_copy') {
    $book_id = (int)$_POST['book_id'];
    $count = (int)($_POST['count'] ?? 1);
    $condition = $_POST['condition'] ?? 'good';
    $location = trim($_POST['location'] ?? 'Main Shelf');

    if (!$branch_id) {
        $error = 'Your account is not assigned to a branch.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM book_copies WHERE book_id = ? AND branch_id = ?");
            $stmt->execute([$book_id, $branch_id]);
            $existing = (int)$stmt->fetchColumn();

            for ($i = 1; $i <= $count; $i++) {
                $copy_number = 'C' . str_pad($existing + $i, 3, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO book_copies (book_id, copy_number, `condition`, status, location, branch_id) VALUES (?, ?, ?, 'available', ?, ?)")
                    ->execute([$book_id, $copy_number, $condition, $location, $branch_id]);
            }
            $pdo->commit();
            $flash = $count . ' copies added successfully to your branch.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to add copies: ' . $e->getMessage();
        }
    }
}

// ---- DELETE BOOK / REMOVE FROM BRANCH ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_book') {
    $book_id = (int)$_POST['book_id'];
    if (!$branch_id) {
        $error = 'Your account is not assigned to a branch.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check if there are copies in other branches
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM book_copies WHERE book_id = ? AND branch_id != ?");
            $stmt->execute([$book_id, $branch_id]);
            $other_branches = (int)$stmt->fetchColumn();

            if ($other_branches > 0) {
                $pdo->prepare("DELETE FROM book_copies WHERE book_id = ? AND branch_id = ?")->execute([$book_id, $branch_id]);
                $flash = 'All copies of this book removed from your branch. (Book remains in other branches).';
            } else {
                $pdo->prepare("DELETE FROM books WHERE id = ?")->execute([$book_id]);
                $flash = 'Book and all its copies deleted from the system.';
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Cannot delete — book may have active loans.';
        }
    }
}

// ---- SEARCH & FILTER (Global Catalog, Local Inventory) ----
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$books = [];

if ($branch_id) {
    $query = "
        SELECT b.*,
               (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id) AS global_total,
               (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND branch_id = ?) AS local_total,
               (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND branch_id = ? AND status = 'available') AS local_available,
               (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND branch_id = 1 AND status = 'available') AS main_avail,
               (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND branch_id = 2 AND status = 'available') AS east_avail,
               (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND branch_id = 3 AND status = 'available') AS west_avail,
               (SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND branch_id = 4 AND status = 'available') AS north_avail
        FROM books b
    ";
    $params = [$branch_id, $branch_id];
    $conditions = [];

    if ($search !== '') {
        $conditions[] = "(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.category LIKE ?)";
        $term = '%' . $search . '%';
        $params = array_merge($params, [$term, $term, $term, $term]);
    }

    if ($filter === 'available') {
        $conditions[] = "(SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND status = 'available') > 0";
    } elseif ($filter === 'out') {
        $conditions[] = "(SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND status = 'available') = 0";
    } elseif ($filter === 'repair') {
        $conditions[] = "(SELECT COUNT(*) FROM book_copies WHERE book_id = b.id AND status = 'repair') > 0";
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(' AND ', $conditions);
    }
    $query .= " ORDER BY b.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll();
}

$page_title = 'Manage Books';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Manage Books</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
            <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Global Catalog · Local Inventory
        </p>
    </div>
    <button onclick="document.getElementById('addBookModal').style.display='flex'" class="btn-primary" style="width: auto; padding: 12px 24px; background: linear-gradient(135deg, #f59e0b, #d97706);">
        <i class="fas fa-plus"></i> Add New Book
    </button>
</div>

<?php if (!$branch_id): ?>
    <div class="flash flash-error" style="margin-bottom: 24px;"><i class="fas fa-exclamation-triangle"></i> Your account is not assigned to a branch.</div>
<?php endif; ?>
<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<!-- Search & Filter Bar -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <form method="get" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search title, author, ISBN..." style="flex: 1; min-width: 200px; padding: 12px 16px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: var(--font); font-size: 0.95rem; outline: none;">
        <select name="filter" style="padding: 12px 16px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: var(--font); font-size: 0.95rem; outline: none;">
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Books</option>
            <option value="available" <?= $filter === 'available' ? 'selected' : '' ?>>Available Anywhere</option>
            <option value="out" <?= $filter === 'out' ? 'selected' : '' ?>>All Borrowed</option>
            <option value="repair" <?= $filter === 'repair' ? 'selected' : '' ?>>In Repair</option>
        </select>
        <button type="submit" class="btn-primary" style="width: auto; padding: 12px 24px; background: linear-gradient(135deg, #f59e0b, #d97706); border: none; cursor: pointer;">
            <i class="fas fa-search"></i> Search
        </button>
    </form>
</div>

<!-- Books Grid -->
<?php if (empty($books)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-book" style="font-size: 3rem; opacity: 0.3; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
        <h3 style="color: var(--text-muted); margin-bottom: 8px;">No Books Found</h3>
        <p style="color: var(--text-muted);">No books match your search or filter.</p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">
        <?php foreach ($books as $book): ?>
            <div class="card" style="display: flex; flex-direction: column; padding: 0; overflow: hidden;">
                <!-- Cover Image -->
                <div style="height: 200px; background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(245,158,11,0.05)); display: flex; align-items: center; justify-content: center; position: relative;">
                    <?php if (!empty($book['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $book['cover_image'])): ?>
                        <img src="<?= UPLOADS_URL . e($book['cover_image']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-book" style="font-size: 4rem; color: rgba(245, 158, 11, 0.4);"></i>
                    <?php endif; ?>
                    
                    <!-- Real Library Availability Badge -->
                    <div style="position: absolute; top: 12px; right: 12px; display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                        <?php if ($book['local_available'] > 0): ?>
                            <span style="padding: 6px 12px; background: rgba(16, 185, 129, 0.95); color: white; border-radius: 20px; font-size: 0.75rem; font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                <i class="fas fa-check-circle"></i> <?= $book['local_available'] ?> Available Here
                            </span>
                        <?php endif; ?>

                        <?php if ($book['main_avail'] > 0 && $branch_id != 1): ?>
                            <span style="padding: 4px 10px; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); color: white; border-radius: 20px; font-size: 0.7rem; font-weight: 600;">
                                Main: <?= $book['main_avail'] ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($book['east_avail'] > 0 && $branch_id != 2): ?>
                            <span style="padding: 4px 10px; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); color: white; border-radius: 20px; font-size: 0.7rem; font-weight: 600;">
                                East: <?= $book['east_avail'] ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($book['west_avail'] > 0 && $branch_id != 3): ?>
                            <span style="padding: 4px 10px; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); color: white; border-radius: 20px; font-size: 0.7rem; font-weight: 600;">
                                West: <?= $book['west_avail'] ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($book['north_avail'] > 0 && $branch_id != 4): ?>
                            <span style="padding: 4px 10px; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); color: white; border-radius: 20px; font-size: 0.7rem; font-weight: 600;">
                                North: <?= $book['north_avail'] ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($book['global_total'] > 0 && $book['local_available'] == 0 && $book['main_avail'] == 0 && $book['east_avail'] == 0 && $book['west_avail'] == 0 && $book['north_avail'] == 0): ?>
                            <span style="padding: 6px 12px; background: rgba(239, 68, 68, 0.95); color: white; border-radius: 20px; font-size: 0.75rem; font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                <i class="fas fa-times-circle"></i> All Borrowed
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Book Info -->
                <div style="padding: 20px; flex: 1; display: flex; flex-direction: column;">
                    <h4 style="font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: 4px; line-height: 1.3;"><?= e($book['title']) ?></h4>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 12px;">by <?= e($book['author']) ?></p>

                    <!-- Stats row -->
                    <div style="display: flex; gap: 16px; margin-bottom: 16px; font-size: 0.8rem; color: var(--text-muted);">
                        <span><i class="fas fa-globe"></i> <?= $book['global_total'] ?> Total in System</span>
                        <span><i class="fas fa-building"></i> <?= $book['local_total'] ?> Here</span>
                    </div>

                    <!-- Action buttons -->
                    <div style="margin-top: auto; display: flex; gap: 8px; flex-wrap: wrap;">
                        <button onclick="showCopies(<?= $book['id'] ?>)" class="btn-outline" style="flex: 1; padding: 8px; font-size: 0.8rem;">
                            <i class="fas fa-layer-group"></i> Copies
                        </button>
                        <button onclick="showAddCopy(<?= $book['id'] ?>)" class="btn-outline" style="flex: 1; padding: 8px; font-size: 0.8rem;">
                            <i class="fas fa-plus"></i> Add Copy
                        </button>
                        <a href="<?= e(SITE_URL) ?>manager/book_edit.php?id=<?= $book['id'] ?>" class="btn-outline" style="padding: 8px 12px; font-size: 0.8rem;">
                            <i class="fas fa-pen"></i>
                        </a>
                        <form method="post" onsubmit="return confirm('Delete this book? If other branches have copies, it will only remove your branch\'s copies.');" style="display: inline;">
                            <input type="hidden" name="action" value="delete_book">
                            <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                            <button type="submit" class="btn-outline" style="padding: 8px 12px; font-size: 0.8rem; color: var(--danger); border-color: rgba(239, 68, 68, 0.3);">
                                <i class="fas fa-trash"></i>
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
            <h3 style="margin: 0;"><i class="fas fa-plus-circle" style="color: #f59e0b;"></i> Add New Book</h3>
            <button onclick="document.getElementById('addBookModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_book">
            <div class="grid-2">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" required placeholder="The Great Gatsby">
                </div>
                <div class="form-group">
                    <label>Author *</label>
                    <input type="text" name="author" required placeholder="F. Scott Fitzgerald">
                </div>
                <div class="form-group">
                    <label>ISBN</label>
                    <input type="text" name="isbn" placeholder="978-0-7432-7356-5">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" placeholder="Fiction">
                </div>
                <div class="form-group">
                    <label>Publisher</label>
                    <input type="text" name="publisher" placeholder="Scribner">
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" placeholder="1925">
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Brief description..."></textarea>
            </div>
            <div class="form-group">
                <label>Cover Image</label>
                <input type="file" name="cover_image" accept="image/*">
                <small style="color: var(--text-muted); display: block; margin-top: 6px; font-size: 0.8rem;">JPG, PNG, WEBP. Max 5MB.</small>
            </div>
            <div class="form-group">
                <label>Number of Copies (for your branch)</label>
                <input type="number" name="copies_count" value="1" min="1" max="50">
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" onclick="document.getElementById('addBookModal').style.display='none'" class="btn-outline">Cancel</button>
                <button type="submit" class="btn-primary" style="width: auto; background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-save"></i> Add Book
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Copies Modal (populated via JS) -->
<div id="copiesModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto; background: var(--card-solid);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0;"><i class="fas fa-layer-group" style="color: #f59e0b;"></i> Physical Copies</h3>
            <button onclick="document.getElementById('copiesModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>
        <div id="copiesContent">Loading...</div>
    </div>
</div>

<!-- Add Copy Modal -->
<div id="addCopyModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 500px; background: var(--card-solid);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0;"><i class="fas fa-plus" style="color: #f59e0b;"></i> Add Copies to Your Branch</h3>
            <button onclick="document.getElementById('addCopyModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>
        <form method="post" id="addCopyForm">
            <input type="hidden" name="action" value="add_copy">
            <input type="hidden" name="book_id" id="addCopyBookId" value="">

            <div class="form-group">
                <label>Number of Copies</label>
                <input type="number" name="count" value="1" min="1" max="20">
            </div>
            <div class="form-group">
                <label>Condition</label>
                <select name="condition">
                    <option value="excellent">Excellent</option>
                    <option value="good" selected>Good</option>
                    <option value="fair">Fair</option>
                    <option value="poor">Poor</option>
                    <option value="repair">Repair</option>
                </select>
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="Main Shelf" placeholder="Shelf A3">
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('addCopyModal').style.display='none'" class="btn-outline">Cancel</button>
                <button type="submit" class="btn-primary" style="width: auto; background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-plus"></i> Add Copies
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function showCopies(bookId) {
        document.getElementById('copiesModal').style.display = 'flex';
        document.getElementById('copiesContent').innerHTML = '<p style="text-align:center;padding:40px;color:var(--text-muted);">Loading...</p>';

        fetch('<?= SITE_URL ?>manager/get_copies.php?book_id=' + bookId)
            .then(r => r.text())
            .then(html => {
                document.getElementById('copiesContent').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('copiesContent').innerHTML = '<p style="color:var(--danger);">Error loading copies.</p>';
            });
    }

    function showAddCopy(bookId) {
        document.getElementById('addCopyBookId').value = bookId;
        document.getElementById('addCopyModal').style.display = 'flex';
    }
</script>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
