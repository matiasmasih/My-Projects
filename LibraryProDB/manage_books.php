<?php
// manage_books.php — Librarian: list / add / edit / delete books
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('manage_books');
if (my_access('manage_books') !== 'full') {
    header('Location: index.php'); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Manage Books';
$action = $_GET['action'] ?? 'list';
$error  = '';

// ============================================================
// ADD / EDIT (POST)
// ============================================================
if (in_array($action, ['add','edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $book_id     = (int)($_POST['book_id'] ?? 0);
    $isbn        = trim($_POST['isbn'] ?? '');
    $title       = trim($_POST['title'] ?? '');
    $subtitle    = trim($_POST['subtitle'] ?? '');
    $publisher   = (int)($_POST['publisher_id'] ?? 0) ?: null;
    $edition     = trim($_POST['edition'] ?? '');
    $year        = (int)($_POST['publication_year'] ?? 0) ?: null;
    $pages       = (int)($_POST['pages'] ?? 0) ?: null;
    $language    = trim($_POST['language'] ?? 'en');
    $description = trim($_POST['description'] ?? '');
    $author_ids  = array_map('intval', $_POST['author_ids'] ?? []);
    $genre_ids   = array_map('intval', $_POST['genre_ids'] ?? []);

    // Cover upload
    $cover_name = $_POST['existing_cover'] ?? null;

    if ($title === '') {
        $error = 'Title is required.';
    } elseif ($isbn !== '' && !preg_match('/^[0-9Xx\-]{10,20}$/', $isbn)) {
        $error = 'ISBN looks invalid (10–20 digits, X, or dash).';
    } else {
        if ($isbn !== '') {
            $chk = $pdo->prepare("SELECT id FROM books WHERE isbn = ? AND id <> ?");
            $chk->execute([$isbn, $book_id]);
            if ($chk->fetchColumn()) $error = 'That ISBN is already used.';
        }
    }

    // Cover image upload
    if (!$error && !empty($_FILES['cover']['name'])) {
        $allowed = ['jpg','jpeg','png','webp','gif'];
        $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $error = 'Cover must be JPG / PNG / WEBP / GIF.';
        } elseif ($_FILES['cover']['size'] > 3 * 1024 * 1024) {
            $error = 'Cover must be under 3 MB.';
        } else {
            $dir = __DIR__ . '/uploads/covers';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $new_name = 'b_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['cover']['tmp_name'], $dir . '/' . $new_name)) {
                $error = 'Could not save cover image.';
            } else {
                $cover_name = $new_name;
            }
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            if ($action === 'add') {
                $ins = $pdo->prepare(
                    "INSERT INTO books (isbn, title, subtitle, publisher_id, edition, publication_year,
                                        pages, language, description, cover_image)
                     VALUES (?,?,?,?,?,?,?,?,?,?)"
                );
                $ins->execute([
                    $isbn !== '' ? $isbn : null,
                    $title,
                    $subtitle !== '' ? $subtitle : null,
                    $publisher,
                    $edition !== '' ? $edition : null,
                    $year, $pages, $language,
                    $description !== '' ? $description : null,
                    $cover_name
                ]);
                $book_id = (int)$pdo->lastInsertId();
                $log_action = 'book_added';
                $log_desc   = "Added book #{$book_id} \"$title\"";
            } else {
                $pdo->prepare(
                    "UPDATE books SET isbn=?, title=?, subtitle=?, publisher_id=?, edition=?,
                                      publication_year=?, pages=?, language=?, description=?, cover_image=?
                     WHERE id=?"
                )->execute([
                    $isbn !== '' ? $isbn : null,
                    $title,
                    $subtitle !== '' ? $subtitle : null,
                    $publisher,
                    $edition !== '' ? $edition : null,
                    $year, $pages, $language,
                    $description !== '' ? $description : null,
                    $cover_name,
                    $book_id
                ]);
                $log_action = 'book_updated';
                $log_desc   = "Updated book #{$book_id} \"$title\"";
            }

            // Authors (rebuild)
            $pdo->prepare("DELETE FROM book_authors WHERE book_id = ?")->execute([$book_id]);
            if ($author_ids) {
                $ins_a = $pdo->prepare("INSERT INTO book_authors (book_id, author_id) VALUES (?, ?)");
                foreach (array_unique($author_ids) as $aid) {
                    if ($aid > 0) $ins_a->execute([$book_id, $aid]);
                }
            }

            // Genres (rebuild)
            $pdo->prepare("DELETE FROM book_genres WHERE book_id = ?")->execute([$book_id]);
            if ($genre_ids) {
                $ins_g = $pdo->prepare("INSERT INTO book_genres (book_id, genre_id) VALUES (?, ?)");
                foreach (array_unique($genre_ids) as $gid) {
                    if ($gid > 0) $ins_g->execute([$book_id, $gid]);
                }
            }

            // Log
            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, ?, ?, 'book', ?)")
                ->execute([$_SESSION['user_id'] ?? null, $log_action, $log_desc, $book_id]);

            $pdo->commit();
            header('Location: manage_books.php?saved=' . ($action === 'add' ? 'added' : 'updated'));
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// ============================================================
// DELETE (POST, admin-only)
// ============================================================
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (current_role() !== 'admin') {
        header('Location: manage_books.php?error=forbidden');
        exit;
    }
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        // Block if any copies exist
        $c = $pdo->prepare("SELECT COUNT(*) FROM copies WHERE book_id = ?");
        $c->execute([$id]);
        if ((int)$c->fetchColumn() > 0) {
            header('Location: manage_books.php?error=has_copies');
            exit;
        }
        $t = $pdo->prepare("SELECT title FROM books WHERE id=?");
        $t->execute([$id]);
        $t = $t->fetchColumn();

        $pdo->prepare("DELETE FROM books WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                       VALUES (?, 'book_deleted', ?, 'book', ?)")
            ->execute([$_SESSION['user_id'] ?? null, "Deleted book #{$id} \"$t\"", $id]);
    }
    header('Location: manage_books.php?deleted=success');
    exit;
}

// ============================================================
// FORM DATA (for add/edit)
// ============================================================
$all_authors    = $pdo->query("SELECT id, first_name, last_name FROM authors ORDER BY last_name, first_name")->fetchAll();
$all_genres     = $pdo->query("SELECT id, name FROM genres ORDER BY name")->fetchAll();
$all_publishers = $pdo->query("SELECT id, name FROM publishers ORDER BY name")->fetchAll();

$edit_book    = null;
$edit_authors = [];
$edit_genres  = [];

if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $st->execute([$id]);
    $edit_book = $st->fetch();
    if (!$edit_book) { header('Location: manage_books.php'); exit; }

    $a = $pdo->prepare("SELECT author_id FROM book_authors WHERE book_id = ?");
    $a->execute([$id]);
    $edit_authors = array_column($a->fetchAll(), 'author_id');

    $g = $pdo->prepare("SELECT genre_id FROM book_genres WHERE book_id = ?");
    $g->execute([$id]);
    $edit_genres = array_column($g->fetchAll(), 'genre_id');
}

// ============================================================
// LIST DATA
// ============================================================
if ($action === 'list') {
    $q     = trim($_GET['q'] ?? '');
    $genre = (int)($_GET['genre'] ?? 0);

    $where = []; $args = [];
    if ($q !== '') {
        $where[] = "(b.title LIKE ? OR b.isbn LIKE ? OR EXISTS
                     (SELECT 1 FROM book_authors ba JOIN authors a ON a.id=ba.author_id
                      WHERE ba.book_id=b.id AND (a.first_name LIKE ? OR a.last_name LIKE ?)))";
        array_push($args, "%$q%", "%$q%", "%$q%", "%$q%");
    }
    if ($genre > 0) {
        $where[] = "EXISTS (SELECT 1 FROM book_genres bg WHERE bg.book_id=b.id AND bg.genre_id=?)";
        $args[]  = $genre;
    }
    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare(
        "SELECT b.id, b.title, b.subtitle, b.isbn, b.publication_year, b.language, b.cover_image,
                p.name AS publisher_name,
                (SELECT GROUP_CONCAT(CONCAT(a.first_name,' ',a.last_name) ORDER BY a.last_name SEPARATOR ', ')
                 FROM book_authors ba JOIN authors a ON a.id=ba.author_id WHERE ba.book_id=b.id) AS authors,
                (SELECT COUNT(*) FROM copies c WHERE c.book_id=b.id) AS total_copies,
                (SELECT COUNT(*) FROM copies c WHERE c.book_id=b.id AND c.status='available') AS avail
         FROM books b
         LEFT JOIN publishers p ON p.id = b.publisher_id
         $where_sql
         ORDER BY b.title
         LIMIT 500"
    );
    $stmt->execute($args);
    $books = $stmt->fetchAll();
}

include 'header.php';
?>

<style>
    .toolbar {
        display:flex; gap:10px; flex-wrap:nowrap; margin-bottom:18px; align-items:center;
        background:var(--card-bg); padding:14px; border-radius:12px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        overflow-x:auto;
    }
    .toolbar input, .toolbar select {
        padding:9px 12px; border:1px solid var(--border); border-radius:8px;
        background:var(--card-bg); color:var(--text); font-size:.9rem;
        white-space:nowrap;
    }
    .toolbar input[type=text] { flex:1; min-width:200px; }
    .toolbar .right { margin-left:auto; }

    .book-cover-mini { width:40px; height:58px; object-fit:cover; border-radius:4px; }
    .cover-ph {
        width:40px; height:58px; border-radius:4px;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:1rem;
    }

    .row-actions {
        display: flex;
        gap: 6px;
        align-items: center;
        white-space: nowrap;
    }
    .row-actions form {
        display: inline-flex;
        margin: 0;
    }
    .row-actions a, .row-actions button {
        padding: 5px 12px;
        border-radius: 6px;
        font-size: .78rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        cursor: pointer;
        height: 30px;
        box-sizing: border-box;
        white-space: nowrap;
    }

    .btn-edit-sm { background:#0d9488; color:#fff; }
    .btn-del-sm  { background:#dc2626; color:#fff; }
    .btn-edit-sm:hover { background:#0f766e; }
    .btn-del-sm:hover  { background:#b91c1c; }

    .form-panel {
        background:var(--card-bg); border-radius:var(--radius); padding:22px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        max-width:900px; margin:0 auto;
    }
    .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    @media (max-width:700px){ .grid2 { grid-template-columns:1fr; } }
    .grid2 label { display:block; font-weight:600; font-size:.85rem; margin-bottom:6px; }
    .grid2 input, .grid2 select, .grid2 textarea {
        width:100%; padding:10px 12px; border:1px solid var(--border);
        border-radius:10px; font-size:.92rem; background:var(--card-bg); color:var(--text);
    }
    .grid2 textarea { resize:vertical; font-family:inherit; }
    .span2 { grid-column:span 2; }
    @media (max-width:700px){ .span2 { grid-column:span 1; } }

    .picklist {
        max-height:180px; overflow-y:auto; border:1px solid var(--border);
        border-radius:10px; padding:10px; background:#fafafa;
    }
    .picklist label { display:flex; gap:8px; align-items:center; font-weight:400; font-size:.88rem; padding:4px 0; margin:0; }
    .picklist input { width:auto; }

    /* === Table: horizontal scroll + single-line rows === */
    .books-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 10px;
    }
    table.books-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1300px;
    }
    table.books-table th,
    table.books-table td {
        padding: 12px 14px;
        vertical-align: middle;
        white-space: nowrap;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        text-align: left;
    }
    table.books-table thead th {
        background: var(--card-bg);
        font-weight: 600;
        font-size: .82rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .03em;
        border-bottom: 2px solid var(--border);
    }
    .table-card { min-width:0; max-width:100%; }
    .main-content { min-width:0; }
    .books-scroll { max-height:70vh; overflow-y:auto; }
    table.books-table thead th { position:sticky; top:0; z-index:2; }
</style>

<?php if (isset($_GET['saved'])): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i> Book <?= htmlspecialchars($_GET['saved']) ?> successfully.
    </div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i> Book deleted.
    </div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'has_copies'): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> Cannot delete — this book still has physical copies. Delete the copies first.
    </div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'forbidden'): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> Only administrators can delete books.
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (in_array($action, ['add','edit'], true)): ?>
    <!-- ========== ADD / EDIT FORM ========== -->
    <div class="form-panel">
        <h3 style="margin-bottom:18px;">
            <i class="bi bi-<?= $action === 'add' ? 'plus-circle' : 'pencil-square' ?>"></i>
            <?= $action === 'add' ? 'Add Book' : 'Edit Book' ?>
        </h3>

        <form method="POST" action="manage_books.php?action=<?= $action ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="book_id" value="<?= (int)($edit_book['id'] ?? 0) ?>">
            <input type="hidden" name="existing_cover" value="<?= htmlspecialchars($edit_book['cover_image'] ?? '') ?>">

            <div class="grid2">
                <div class="span2">
                    <label>Title *</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($edit_book['title'] ?? '') ?>">
                </div>
                <div class="span2">
                    <label>Subtitle</label>
                    <input type="text" name="subtitle" value="<?= htmlspecialchars($edit_book['subtitle'] ?? '') ?>">
                </div>

                <div>
                    <label>ISBN</label>
                    <input type="text" name="isbn" value="<?= htmlspecialchars($edit_book['isbn'] ?? '') ?>" placeholder="978...">
                </div>
                <div>
                    <label>Language</label>
                    <select name="language">
                        <?php foreach (['en'=>'English','fi'=>'Finnish','fa'=>'Persian'] as $c=>$l): ?>
                            <option value="<?= $c ?>" <?= ($edit_book['language'] ?? 'en')===$c?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Publisher</label>
                    <select name="publisher_id">
                        <option value="0">— None —</option>
                        <?php foreach ($all_publishers as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= (($edit_book['publisher_id'] ?? 0) == $p['id'])?'selected':'' ?>>
                                <?= htmlspecialchars($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Edition</label>
                    <input type="text" name="edition" value="<?= htmlspecialchars($edit_book['edition'] ?? '') ?>">
                </div>

                <div>
                    <label>Publication Year</label>
                    <input type="number" name="publication_year" min="0" max="2100" value="<?= (int)($edit_book['publication_year'] ?? 0) ?: '' ?>">
                </div>
                <div>
                    <label>Pages</label>
                    <input type="number" name="pages" min="0" value="<?= (int)($edit_book['pages'] ?? 0) ?: '' ?>">
                </div>

                <div class="span2">
                    <label>Description</label>
                    <textarea name="description" rows="4"><?= htmlspecialchars($edit_book['description'] ?? '') ?></textarea>
                </div>

                <div class="span2">
                    <label>Cover Image (JPG/PNG/WEBP, max 3 MB)</label>
                    <input type="file" name="cover" accept="image/*">
                    <?php if (!empty($edit_book['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $edit_book['cover_image'])): ?>
                        <div style="margin-top:8px;">
                            <img src="uploads/covers/<?= htmlspecialchars($edit_book['cover_image']) ?>"
                                 style="height:80px;border-radius:6px;" alt="">
                            <span style="color:var(--text-muted);font-size:.82rem;margin-left:8px;">current cover</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label>Authors (Ctrl/Cmd-click for multiple)</label>
                    <div class="picklist">
                        <?php foreach ($all_authors as $a): ?>
                            <label>
                                <input type="checkbox" name="author_ids[]" value="<?= (int)$a['id'] ?>"
                                       <?= in_array($a['id'], $edit_authors, true) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($a['last_name'] . ', ' . $a['first_name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label>Genres</label>
                    <div class="picklist">
                        <?php foreach ($all_genres as $g): ?>
                            <label>
                                <input type="checkbox" name="genre_ids[]" value="<?= (int)$g['id'] ?>"
                                       <?= in_array($g['id'], $edit_genres, true) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($g['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                <a href="manage_books.php" class="btn-secondary-custom">← Back</a>
                <button type="submit" class="btn-add">
                    <i class="bi bi-check-circle"></i> <?= $action === 'add' ? 'Create Book' : 'Save Changes' ?>
                </button>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- ========== LIST ========== -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;"><i class="bi bi-book" style="color:var(--primary);"></i> Books (<?= count($books) ?>)</h2>
        <a href="manage_books.php?action=add" class="btn-add" style="text-decoration:none;">
            <i class="bi bi-plus-circle-fill"></i> Add Book
        </a>
    </div>

    <form class="toolbar" method="get" action="manage_books.php">
        <input type="text" name="q" placeholder="Search title, ISBN, author…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        <select name="genre">
            <option value="0">All genres</option>
            <?php foreach ($all_genres as $g): ?>
                <option value="<?= (int)$g['id'] ?>" <?= ((int)($_GET['genre'] ?? 0))===(int)$g['id']?'selected':'' ?>>
                    <?= htmlspecialchars($g['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-add" style="white-space:nowrap;"><i class="bi bi-search"></i> Filter</button>
        <a href="manage_books.php" class="btn-secondary-custom" style="white-space:nowrap;">Reset</a>
    </form>

    <?php if (!$books): ?>
        <div class="table-card" style="text-align:center;padding:40px;">
            <i class="bi bi-book" style="font-size:3rem;color:var(--text-muted);"></i>
            <p style="color:var(--text-muted);margin-top:12px;">No books match.</p>
        </div>
    <?php else: ?>
    <div class="table-card">
        <div class="books-scroll">
             <table class="books-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Title</th>
                        <th>Authors</th>
                        <th>Publisher</th>
                        <th>Year</th>
                        <th>ISBN</th>
                        <th>Copies</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($books as $b): ?>
                    <tr>
                        <td>
                            <?php if (!empty($b['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $b['cover_image'])): ?>
                                <img src="uploads/covers/<?= htmlspecialchars($b['cover_image']) ?>" class="book-cover-mini" alt="">
                            <?php else: ?>
                                <div class="cover-ph"><i class="bi bi-book"></i></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="book.php?id=<?= (int)$b['id'] ?>" style="color:var(--primary-dark);text-decoration:none;font-weight:600;">
                                <?= htmlspecialchars($b['title']) ?>
                            </a>
                            <?php if ($b['subtitle']): ?>
                                <small style="color:var(--text-muted);margin-left:8px;"><?= htmlspecialchars($b['subtitle']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($b['authors'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($b['publisher_name'] ?? '—') ?></td>
                        <td><?= (int)$b['publication_year'] ?: '—' ?></td>
                        <td><code style="font-size:.8rem;"><?= htmlspecialchars($b['isbn'] ?? '—') ?></code></td>
                        <td>
                            <span class="status-badge status-<?= ((int)$b['avail'] > 0) ? 'active' : 'pending' ?>">
                                <?= (int)$b['avail'] ?> / <?= (int)$b['total_copies'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="manage_books.php?action=edit&id=<?= (int)$b['id'] ?>" class="btn-edit-sm">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <?php if (current_role() === 'admin'): ?>
                                    <form method="POST" action="manage_books.php?action=delete"
                                          onsubmit="return confirm('Delete this book? It must have zero copies.');"
                                          style="display:inline; margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="btn-del-sm">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php include 'footer.php'; ?>
