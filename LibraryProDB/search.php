<?php
// search.php — Advanced search across books, authors, genres, publishers
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('search');

$page_title = 'Advanced Search';

// ---------- READ FILTERS ----------
$q          = trim($_GET['q'] ?? '');
$genre_id   = (int)($_GET['genre'] ?? 0);
$author_id  = (int)($_GET['author'] ?? 0);
$publisher  = (int)($_GET['publisher'] ?? 0);
$year_from  = (int)($_GET['year_from'] ?? 0);
$year_to    = (int)($_GET['year_to'] ?? 0);
$lang       = trim($_GET['lang'] ?? '');
$available  = isset($_GET['available']) && $_GET['available'] === '1';
$sort       = $_GET['sort'] ?? 'title';
if (!in_array($sort, ['title','year_desc','year_asc','recent','popular'], true)) $sort = 'title';

// ---------- BUILD QUERY ----------
$where = [];
$args  = [];

if ($q !== '') {
    $where[] = "(b.title LIKE ? OR b.subtitle LIKE ? OR b.isbn LIKE ? OR b.description LIKE ?
                 OR EXISTS (SELECT 1 FROM book_authors ba JOIN authors a ON a.id=ba.author_id
                            WHERE ba.book_id = b.id AND (a.first_name LIKE ? OR a.last_name LIKE ?)))";
    $like = "%$q%";
    array_push($args, $like, $like, $like, $like, $like, $like);
}
if ($genre_id > 0) {
    $where[] = "EXISTS (SELECT 1 FROM book_genres bg WHERE bg.book_id=b.id AND bg.genre_id = ?)";
    $args[] = $genre_id;
}
if ($author_id > 0) {
    $where[] = "EXISTS (SELECT 1 FROM book_authors ba WHERE ba.book_id=b.id AND ba.author_id = ?)";
    $args[] = $author_id;
}
if ($publisher > 0) {
    $where[] = "b.publisher_id = ?";
    $args[] = $publisher;
}
if ($year_from > 0) {
    $where[] = "b.publication_year >= ?";
    $args[] = $year_from;
}
if ($year_to > 0) {
    $where[] = "b.publication_year <= ?";
    $args[] = $year_to;
}
if ($lang !== '') {
    $where[] = "b.language = ?";
    $args[] = $lang;
}
if ($available) {
    $where[] = "EXISTS (SELECT 1 FROM copies c WHERE c.book_id=b.id AND c.status='available')";
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---------- SORT ----------
switch ($sort) {
    case 'year_desc': $order = "b.publication_year DESC, b.title ASC"; break;
    case 'year_asc':  $order = "b.publication_year ASC, b.title ASC";  break;
    case 'recent':    $order = "b.created_at DESC";                    break;
    case 'popular':
        $order = "(SELECT COUNT(*) FROM borrowings br JOIN copies c ON c.id=br.copy_id WHERE c.book_id=b.id) DESC, b.title ASC";
        break;
    default:          $order = "b.title ASC";
}

// ---------- LOAD RESULTS ----------
$sql = "SELECT b.id, b.title, b.subtitle, b.isbn, b.publication_year, b.language, b.cover_image,
               p.name AS publisher_name,
               (SELECT GROUP_CONCAT(CONCAT(a.first_name,' ',a.last_name) ORDER BY a.last_name SEPARATOR ', ')
                FROM book_authors ba JOIN authors a ON a.id=ba.author_id WHERE ba.book_id=b.id) AS authors,
               (SELECT COUNT(*) FROM copies c WHERE c.book_id=b.id) AS total_copies,
               (SELECT COUNT(*) FROM copies c WHERE c.book_id=b.id AND c.status='available') AS avail,
               (SELECT ROUND(AVG(r.rating),1) FROM reviews r WHERE r.book_id=b.id AND r.is_approved=1) AS avg_rating,
               (SELECT COUNT(*) FROM reviews r WHERE r.book_id=b.id AND r.is_approved=1) AS review_count
        FROM books b
        LEFT JOIN publishers p ON p.id = b.publisher_id
        $where_sql
        ORDER BY $order
        LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$results = $stmt->fetchAll();

// ---------- FILTER DROPDOWNS ----------
$genres     = $pdo->query("SELECT id, name FROM genres ORDER BY name")->fetchAll();
$authors    = $pdo->query("SELECT id, first_name, last_name FROM authors ORDER BY last_name, first_name")->fetchAll();
$publishers = $pdo->query("SELECT id, name FROM publishers ORDER BY name")->fetchAll();

// Did user search?
$did_search = $q !== '' || $genre_id || $author_id || $publisher || $year_from || $year_to || $lang !== '' || $available;

include 'header.php';
?>

<style>
    .search-panel {
        background:var(--card-bg); border-radius:var(--radius);
        padding:22px; box-shadow:var(--shadow); margin-bottom:22px;
        border:1px solid rgba(13,148,136,0.08);
    }
    .search-panel h3 {
        font-size:1.1rem; margin:0 0 18px; display:flex; align-items:center; gap:8px;
    }
    .search-panel h3 i { color:var(--primary); }

    .filters-grid {
        display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
        gap:14px; margin-bottom:16px;
    }
    .filters-grid label {
        display:block; font-weight:600; font-size:.82rem; margin-bottom:6px;
        color:var(--text-muted); text-transform:uppercase; letter-spacing:.03em;
    }
    .filters-grid input, .filters-grid select {
        width:100%; padding:10px 12px; border:1px solid var(--border);
        border-radius:10px; font-size:.92rem; background:var(--card-bg); color:var(--text);
    }
    .filters-grid input:focus, .filters-grid select:focus {
        outline:none; border-color:var(--primary);
        box-shadow:0 0 0 3px rgba(13,148,136,0.12);
    }
    .search-actions {
        display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;
    }

    .results-header {
        display:flex; justify-content:space-between; align-items:center;
        margin-bottom:16px; flex-wrap:wrap; gap:10px;
    }
    .results-header h3 { margin:0; font-size:1.05rem; }
    .results-header .count { color:var(--text-muted); font-weight:400; font-size:.9rem; }

    .book-list { display:grid; grid-template-columns:1fr; gap:12px; }
    .book-row {
        background:var(--card-bg); border-radius:14px; padding:14px;
        display:flex; gap:16px; box-shadow:var(--shadow);
        border:1px solid rgba(13,148,136,0.08);
        transition:transform .15s ease;
    }
    .book-row:hover { transform:translateY(-2px); }
    .book-thumb {
        width:70px; height:100px; object-fit:cover; border-radius:8px; flex-shrink:0;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:1.8rem;
    }
    .book-info { flex:1; min-width:0; }
    .book-info h4 { margin:0 0 4px; font-size:1.05rem; }
    .book-info h4 a { color:var(--text); text-decoration:none; }
    .book-info h4 a:hover { color:var(--primary-dark); }
    .book-meta { font-size:.85rem; color:var(--text-muted); margin:3px 0; }
    .book-meta i { color:var(--primary); margin-right:4px; }
    .book-tags { margin-top:6px; }
    .tag {
        display:inline-block; padding:2px 8px; background:#f0fdfa; color:#115e59;
        border-radius:6px; font-size:.72rem; font-weight:600; margin-right:4px;
    }
    .book-status {
        display:flex; flex-direction:column; justify-content:center; align-items:flex-end;
        gap:6px; flex-shrink:0; min-width:120px; text-align:right;
    }
    .avail-badge {
        display:inline-block; padding:4px 10px; border-radius:8px;
        font-size:.78rem; font-weight:600;
    }
    .avail-yes { background:#d1fae5; color:#065f46; }
    .avail-no  { background:#fee2e2; color:#991b1b; }
    .rating-stars { color:#f59e0b; font-size:.85rem; }
</style>

<h2 style="margin-bottom:18px;">
    <i class="bi bi-search" style="color:var(--primary);"></i> Advanced Search
</h2>

<!-- SEARCH FORM -->
<div class="search-panel">
    <form method="GET" action="search.php">
        <div style="margin-bottom:16px;">
            <label style="display:block;font-weight:600;font-size:.82rem;margin-bottom:6px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;">
                Keywords
            </label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                   placeholder="Search title, author, ISBN, description…"
                   style="width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:10px;font-size:1rem;">
        </div>

        <div class="filters-grid">
            <div>
                <label>Genre</label>
                <select name="genre">
                    <option value="0">Any genre</option>
                    <?php foreach ($genres as $g): ?>
                        <option value="<?= (int)$g['id'] ?>" <?= $genre_id===(int)$g['id']?'selected':'' ?>>
                            <?= htmlspecialchars($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Author</label>
                <select name="author">
                    <option value="0">Any author</option>
                    <?php foreach ($authors as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= $author_id===(int)$a['id']?'selected':'' ?>>
                            <?= htmlspecialchars($a['last_name'] . ', ' . $a['first_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Publisher</label>
                <select name="publisher">
                    <option value="0">Any publisher</option>
                    <?php foreach ($publishers as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= $publisher===(int)$p['id']?'selected':'' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Language</label>
                <select name="lang">
                    <option value="">Any language</option>
                    <option value="en" <?= $lang==='en'?'selected':'' ?>>English</option>
                    <option value="fi" <?= $lang==='fi'?'selected':'' ?>>Finnish</option>
                    <option value="fa" <?= $lang==='fa'?'selected':'' ?>>Persian</option>
                </select>
            </div>
            <div>
                <label>Year from</label>
                <input type="number" name="year_from" min="0" max="2100" value="<?= $year_from ?: '' ?>" placeholder="e.g. 1900">
            </div>
            <div>
                <label>Year to</label>
                <input type="number" name="year_to" min="0" max="2100" value="<?= $year_to ?: '' ?>" placeholder="e.g. 2026">
            </div>
            <div>
                <label>Sort by</label>
                <select name="sort">
                    <option value="title"     <?= $sort==='title'?'selected':'' ?>>Title A–Z</option>
                    <option value="recent"    <?= $sort==='recent'?'selected':'' ?>>Recently added</option>
                    <option value="year_desc" <?= $sort==='year_desc'?'selected':'' ?>>Newest first</option>
                    <option value="year_asc"  <?= $sort==='year_asc'?'selected':'' ?>>Oldest first</option>
                    <option value="popular"   <?= $sort==='popular'?'selected':'' ?>>Most borrowed</option>
                </select>
            </div>
            <div>
                <label>&nbsp;</label>
                <label style="display:flex;align-items:center;gap:8px;text-transform:none;font-size:.9rem;font-weight:500;color:var(--text);padding:10px 0;">
                    <input type="checkbox" name="available" value="1" <?= $available?'checked':'' ?> style="width:auto;">
                    Only available now
                </label>
            </div>
        </div>

        <div class="search-actions">
            <a href="search.php" class="btn-secondary-custom">
                <i class="bi bi-x-circle"></i> Clear
            </a>
            <button type="submit" class="btn-add">
                <i class="bi bi-search"></i> Search
            </button>
        </div>
    </form>
</div>

<!-- RESULTS -->
<?php if (!$did_search): ?>
    <div class="table-card" style="text-align:center;padding:50px;">
        <i class="bi bi-search" style="font-size:3rem;color:var(--text-muted);"></i>
        <p style="color:var(--text-muted);margin-top:12px;">
            Enter search terms above or pick a filter to begin.
        </p>
        <a href="catalog.php" class="btn-add" style="text-decoration:none;display:inline-block;margin-top:10px;">
            <i class="bi bi-book"></i> Browse Full Catalog
        </a>
    </div>
<?php else: ?>

    <div class="results-header">
        <h3>
            <i class="bi bi-list-ul"></i> Results
            <span class="count">— <?= count($results) ?> book(s) found</span>
        </h3>
        <?php if ($q !== ''): ?>
            <span style="color:var(--text-muted);font-size:.9rem;">
                for "<strong><?= htmlspecialchars($q) ?></strong>"
            </span>
        <?php endif; ?>
    </div>

    <?php if (!$results): ?>
        <div class="table-card" style="text-align:center;padding:50px;">
            <i class="bi bi-emoji-frown" style="font-size:3rem;color:var(--text-muted);"></i>
            <p style="color:var(--text-muted);margin-top:12px;">
                No books match your search. Try different filters.
            </p>
        </div>
    <?php else: ?>
        <div class="book-list">
            <?php foreach ($results as $b): ?>
                <div class="book-row">
                    <a href="book.php?id=<?= (int)$b['id'] ?>" style="text-decoration:none;">
                        <?php if (!empty($b['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $b['cover_image'])): ?>
                            <img src="uploads/covers/<?= htmlspecialchars($b['cover_image']) ?>" class="book-thumb" alt="">
                        <?php else: ?>
                            <div class="book-thumb"><i class="bi bi-book"></i></div>
                        <?php endif; ?>
                    </a>

                    <div class="book-info">
                        <h4>
                            <a href="book.php?id=<?= (int)$b['id'] ?>">
                                <?= htmlspecialchars($b['title']) ?>
                            </a>
                        </h4>
                        <?php if ($b['subtitle']): ?>
                            <div class="book-meta" style="font-style:italic;"><?= htmlspecialchars($b['subtitle']) ?></div>
                        <?php endif; ?>
                        <?php if ($b['authors']): ?>
                            <div class="book-meta"><i class="bi bi-person"></i> <?= htmlspecialchars($b['authors']) ?></div>
                        <?php endif; ?>
                        <div class="book-meta">
                            <?php if ($b['publisher_name']): ?>
                                <i class="bi bi-building"></i> <?= htmlspecialchars($b['publisher_name']) ?>
                            <?php endif; ?>
                            <?php if ($b['publication_year']): ?>
                                · <?= (int)$b['publication_year'] ?>
                            <?php endif; ?>
                            <?php if ($b['isbn']): ?>
                                · ISBN <code><?= htmlspecialchars($b['isbn']) ?></code>
                            <?php endif; ?>
                        </div>
                        <?php if ($b['avg_rating']): ?>
                            <div class="book-meta">
                                <span class="rating-stars"><?= str_repeat('★', (int)round($b['avg_rating'])) ?><?= str_repeat('☆', 5 - (int)round($b['avg_rating'])) ?></span>
                                <?= $b['avg_rating'] ?> (<?= (int)$b['review_count'] ?> review<?= (int)$b['review_count']==1?'':'s' ?>)
                            </div>
                        <?php endif; ?>
                        <div class="book-tags">
                            <span class="tag"><i class="bi bi-collection"></i> <?= (int)$b['total_copies'] ?> total</span>
                            <?php if ((int)$b['avail'] > 0): ?>
                                <span class="tag" style="background:#d1fae5;color:#065f46;">
                                    <i class="bi bi-check-circle"></i> <?= (int)$b['avail'] ?> available
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="book-status">
                        <?php if ((int)$b['avail'] > 0): ?>
                            <span class="avail-badge avail-yes"><i class="bi bi-check-circle-fill"></i> Available</span>
                        <?php elseif ((int)$b['total_copies'] > 0): ?>
                            <span class="avail-badge avail-no"><i class="bi bi-clock-fill"></i> All borrowed</span>
                        <?php else: ?>
                            <span class="avail-badge avail-no"><i class="bi bi-x-circle-fill"></i> No copies</span>
                        <?php endif; ?>
                        <a href="book.php?id=<?= (int)$b['id'] ?>" class="btn-add" style="text-decoration:none;padding:6px 12px;font-size:.82rem;">
                            View <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php include 'footer.php'; ?>
