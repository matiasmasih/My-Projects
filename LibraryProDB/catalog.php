<?php
// catalog.php — Browse books (with cover thumbnails + filters + search + sort)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('catalog');

$page_title = t('nav_browse');

// ---- Filters ----
$q      = trim($_GET['q'] ?? '');
$genre  = (int)($_GET['genre'] ?? 0);
$sort   = $_GET['sort'] ?? 'title';
$dirRaw = strtolower($_GET['dir'] ?? 'asc');
$dir    = $dirRaw === 'desc' ? 'DESC' : 'ASC';

$allowedSort = ['title','author','year','recent'];
if (!in_array($sort, $allowedSort, true)) $sort = 'title';

// ---- Build query ----
$where = []; $args = [];

if ($q !== '') {
    $where[] = "(b.title LIKE ? OR b.isbn LIKE ? OR EXISTS (
        SELECT 1 FROM book_authors ba2
        JOIN authors a2 ON a2.id = ba2.author_id
        WHERE ba2.book_id = b.id
          AND (a2.first_name LIKE ? OR a2.last_name LIKE ?)
    ))";
    array_push($args, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($genre > 0) {
    $where[] = "EXISTS (SELECT 1 FROM book_genres bg WHERE bg.book_id = b.id AND bg.genre_id = ?)";
    $args[] = $genre;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orderBy = match ($sort) {
    'author' => "authors $dir",
    'year'   => "b.publication_year $dir",
    'recent' => "b.id $dir",
    default  => "b.title $dir",
};

$sql = "SELECT b.id, b.title, b.isbn, b.publication_year, b.cover_image,
               p.name AS publisher_name,
               (SELECT GROUP_CONCAT(CONCAT(a.first_name,' ',a.last_name) SEPARATOR ', ')
                FROM book_authors ba JOIN authors a ON a.id = ba.author_id
                WHERE ba.book_id = b.id) AS authors,
               (SELECT COUNT(*) FROM copies c WHERE c.book_id = b.id AND c.status = 'available') AS available_copies,
               (SELECT COUNT(*) FROM copies c WHERE c.book_id = b.id) AS total_copies
        FROM books b
        LEFT JOIN publishers p ON p.id = b.publisher_id
        $whereSql
        ORDER BY $orderBy
        LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$books = $stmt->fetchAll();

// Genre list for filter dropdown
$genres = $pdo->query("SELECT id, name FROM genres ORDER BY name")->fetchAll();

// Helper: sort link
function sort_link($key, $label, $current, $dirRaw) {
    $next  = ($current === $key && $dirRaw === 'asc') ? 'desc' : 'asc';
    $arrow = ($current === $key) ? ($dirRaw === 'asc' ? ' ▲' : ' ▼') : '';
    $qs = http_build_query(array_merge($_GET, ['sort' => $key, 'dir' => $next]));
    return '<a href="?' . $qs . '" style="color:inherit;text-decoration:none;">' . htmlspecialchars($label) . $arrow . '</a>';
}

include 'header.php';
?>

<div class="table-card">
    <h5><i class="bi bi-book"></i> <?= htmlspecialchars(t('nav_browse')) ?> (<?= count($books) ?>)</h5>

    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
               placeholder="<?= htmlspecialchars(t('nav_search')) ?>…"
               style="flex:1;min-width:200px;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--card-bg);color:var(--text);">

        <select name="genre" style="padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--card-bg);color:var(--text);">
            <option value="0">— <?= htmlspecialchars(t('home_genres')) ?> —</option>
            <?php foreach ($genres as $g): ?>
                <option value="<?= (int)$g['id'] ?>" <?= $genre === (int)$g['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($g['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-add"><i class="bi bi-search"></i></button>
        <a href="catalog.php" class="btn-secondary-custom"><i class="bi bi-x-circle"></i></a>
    </form>

    <?php if (!$books): ?>
        <p style="color:var(--text-muted);"><?= htmlspecialchars(t('home_no_books')) ?></p>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table style="width:100%;">
            <thead>
                <tr>
                    <th style="width:70px;"></th>
                    <th><?= sort_link('title',  'Title',  $sort, $dirRaw) ?></th>
                    <th><?= sort_link('author', 'Author', $sort, $dirRaw) ?></th>
                    <th>Publisher</th>
                    <th><?= sort_link('year',   'Year',   $sort, $dirRaw) ?></th>
                    <th>Availability</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($books as $b): ?>
                <tr onclick="window.location.href='book.php?id=<?= (int)$b['id'] ?>'" style="cursor:pointer;">
                    <td style="padding:8px;vertical-align:middle;">
                        <?php if (!empty($b['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $b['cover_image'])): ?>
                            <img src="uploads/covers/<?= htmlspecialchars($b['cover_image']) ?>"
                                 alt=""
                                 style="width:60px;height:90px;object-fit:cover;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.15);">
                        <?php else: ?>
                            <div style="width:60px;height:90px;border-radius:6px;background:linear-gradient(135deg,var(--primary),var(--accent));display:grid;place-items:center;color:white;">
                                <i class="bi bi-book" style="font-size:1.8rem;opacity:.8;"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align:middle;">
                        <span style="color:var(--accent-dark);font-weight:600;">
                            <?= htmlspecialchars($b['title']) ?>
                        </span>
                        <?php if ($b['isbn']): ?>
                            <div style="font-size:.75rem;color:var(--text-muted);">ISBN <?= htmlspecialchars($b['isbn']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align:middle;"><?= htmlspecialchars($b['authors'] ?? t('home_unknown_author')) ?></td>
                    <td style="vertical-align:middle;"><?= htmlspecialchars($b['publisher_name'] ?? '—') ?></td>
                    <td style="vertical-align:middle;"><?= $b['publication_year'] ? (int)$b['publication_year'] : '—' ?></td>
                    <td style="vertical-align:middle;">
                        <?php if ((int)$b['available_copies'] > 0): ?>
                            <span class="status-badge status-active">
                                <?= (int)$b['available_copies'] ?> / <?= (int)$b['total_copies'] ?>
                            </span>
                        <?php elseif ((int)$b['total_copies'] > 0): ?>
                            <span class="status-badge status-pending">
                                0 / <?= (int)$b['total_copies'] ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-cancelled">No copies</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
