<?php
// book.php — Book detail page
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('book');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: catalog.php'); exit; }

// Load book + publisher
$stmt = $pdo->prepare(
    "SELECT b.*, p.name AS publisher_name
     FROM books b
     LEFT JOIN publishers p ON p.id = b.publisher_id
     WHERE b.id = ?"
);
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book) { header('Location: catalog.php'); exit; }

$page_title = $book['title'];

// Authors
$stmt = $pdo->prepare(
    "SELECT a.id, a.first_name, a.last_name
     FROM authors a
     JOIN book_authors ba ON ba.author_id = a.id
     WHERE ba.book_id = ?
     ORDER BY a.last_name, a.first_name"
);
$stmt->execute([$id]);
$authors = $stmt->fetchAll();

// Genres
$stmt = $pdo->prepare(
    "SELECT g.id, g.name
     FROM genres g
     JOIN book_genres bg ON bg.genre_id = g.id
     WHERE bg.book_id = ?
     ORDER BY g.name"
);
$stmt->execute([$id]);
$genres = $stmt->fetchAll();

// Copies + their branch/shelf info
$stmt = $pdo->prepare(
    "SELECT c.id, c.barcode, c.status, c.copy_condition,
            s.code AS shelf_code, s.section,
            br.name AS branch_name, br.city
     FROM copies c
     LEFT JOIN shelves s  ON s.id  = c.shelf_id
     LEFT JOIN branches br ON br.id = s.branch_id
     WHERE c.book_id = ?
     ORDER BY br.name, s.code"
);
$stmt->execute([$id]);
$copies = $stmt->fetchAll();

// Reviews
$stmt = $pdo->prepare(
    "SELECT r.rating, r.title, r.review_text, r.created_at,
            u.username, u.first_name, u.last_name
     FROM reviews r
     LEFT JOIN users u ON u.id = r.user_id
     WHERE r.book_id = ? AND r.is_approved = 1
     ORDER BY r.created_at DESC"
);
$stmt->execute([$id]);
$reviews = $stmt->fetchAll();

// Review summary
$avgRating = 0; $reviewCount = count($reviews);
if ($reviewCount > 0) {
    $sum = 0; foreach ($reviews as $r) { $sum += (int)$r['rating']; }
    $avgRating = round($sum / $reviewCount, 1);
}

// My review (if logged in)
$myReview = null;
if (current_role() !== 'guest') {
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE book_id = ? AND user_id = ?");
    $stmt->execute([$id, (int)$_SESSION['user_id']]);
    $myReview = $stmt->fetch();
}

// Availability count
$availableCount = 0;
foreach ($copies as $c) { if ($c['status'] === 'available') $availableCount++; }

include 'header.php';
?>

<div class="table-card">
    <div style="display:flex;gap:24px;flex-wrap:wrap;">

        <!-- Cover (real image if exists, placeholder otherwise) -->
        <div style="flex:0 0 180px;">
            <?php if (!empty($book['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $book['cover_image'])): ?>
                <img src="uploads/covers/<?= htmlspecialchars($book['cover_image']) ?>"
                     alt="<?= htmlspecialchars($book['title']) ?>"
                     style="width:180px;height:260px;object-fit:cover;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,0.15);">
            <?php else: ?>
                <div style="width:180px;height:260px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--accent));display:grid;place-items:center;color:white;">
                    <i class="bi bi-book" style="font-size:4rem;opacity:.7;"></i>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main info -->
        <div style="flex:1;min-width:280px;">
            <h2 style="font-size:1.8rem;font-weight:800;margin-bottom:8px;">
                <?= htmlspecialchars($book['title']) ?>
            </h2>
            <?php if ($book['subtitle']): ?>
                <p style="color:var(--text-muted);font-size:1.05rem;margin-bottom:12px;">
                    <?= htmlspecialchars($book['subtitle']) ?>
                </p>
            <?php endif; ?>

            <?php if ($authors): ?>
                <p style="margin-bottom:8px;">
                    <i class="bi bi-person"></i>
                    <?php
                        $authorNames = array_map(fn($a) => htmlspecialchars($a['first_name'] . ' ' . $a['last_name']), $authors);
                        echo implode(', ', $authorNames);
                    ?>
                </p>
            <?php endif; ?>

            <p style="margin-bottom:8px;color:var(--text-muted);">
                <?php if ($book['publisher_name']): ?>
                    <i class="bi bi-building"></i> <?= htmlspecialchars($book['publisher_name']) ?>
                <?php endif; ?>
                <?php if ($book['publication_year']): ?>
                    · <?= (int)$book['publication_year'] ?>
                <?php endif; ?>
                <?php if ($book['pages']): ?>
                    · <?= (int)$book['pages'] ?> pages
                <?php endif; ?>
            </p>

            <?php if ($book['isbn']): ?>
                <p style="margin-bottom:8px;color:var(--text-muted);font-size:.9rem;">
                    ISBN: <code><?= htmlspecialchars($book['isbn']) ?></code>
                </p>
            <?php endif; ?>

            <?php if ($genres): ?>
                <div style="margin-bottom:14px;">
                    <?php foreach ($genres as $g): ?>
                        <a href="catalog.php?genre=<?= (int)$g['id'] ?>" class="status-badge status-info" style="text-decoration:none;">
                            <?= htmlspecialchars($g['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Availability -->
            <div style="margin:16px 0;">
                <?php if ($availableCount > 0): ?>
                    <span class="status-badge status-active" style="font-size:.9rem;padding:8px 14px;">
                        <i class="bi bi-check-circle-fill"></i> <?= $availableCount ?> available
                    </span>
                <?php elseif (count($copies) > 0): ?>
                    <span class="status-badge status-pending" style="font-size:.9rem;padding:8px 14px;">
                        <i class="bi bi-clock-fill"></i> All copies borrowed
                    </span>
                <?php else: ?>
                    <span class="status-badge status-cancelled" style="font-size:.9rem;padding:8px 14px;">
                        No copies in library
                    </span>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <?php if (current_role() !== 'guest' && $availableCount > 0): ?>
                <a href="checkout.php?book_id=<?= (int)$book['id'] ?>" class="btn-add">
                    <i class="bi bi-bookmark-plus"></i> Borrow this book
                </a>
            <?php elseif (current_role() !== 'guest'): ?>
                <a href="place_hold.php?book_id=<?= (int)$book['id'] ?>" class="btn-secondary-custom">
                    <i class="bi bi-bookmark-star"></i> Place a hold
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-add">
                    <i class="bi bi-box-arrow-in-right"></i> Login to borrow
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($book['description']): ?>
        <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border);">
            <h5><i class="bi bi-card-text"></i> Description</h5>
            <p style="line-height:1.7;color:var(--text);"><?= nl2br(htmlspecialchars($book['description'])) ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Copies table -->
<div class="table-card">
    <h5><i class="bi bi-collection"></i> Copies in Library (<?= count($copies) ?>)</h5>
    <?php if (!$copies): ?>
        <p style="color:var(--text-muted);">No physical copies have been added yet.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Barcode</th>
                    <th>Branch</th>
                    <th>Shelf</th>
                    <th>Condition</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($copies as $c): ?>
                <tr>
                    <td><code><?= htmlspecialchars($c['barcode']) ?></code></td>
                    <td><?= htmlspecialchars($c['branch_name'] ?? '—') ?><?= $c['city'] ? ' (' . htmlspecialchars($c['city']) . ')' : '' ?></td>
                    <td><?= htmlspecialchars($c['shelf_code'] ?? '—') ?><?= $c['section'] ? ' · ' . htmlspecialchars($c['section']) : '' ?></td>
                    <td>
                        <span class="status-badge <?= $c['copy_condition'] === 'new' || $c['copy_condition'] === 'good' ? 'status-active' : 'status-warning' ?>">
                            <?= htmlspecialchars($c['copy_condition']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $c['status'] === 'available' ? 'active' : ($c['status'] === 'borrowed' ? 'pending' : 'cancelled') ?>">
                            <?= htmlspecialchars($c['status']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Reviews -->
<div class="table-card">
    <h5>
        <i class="bi bi-star"></i> Reviews
        <?php if ($reviewCount > 0): ?>
            <span style="font-weight:400;color:var(--text-muted);font-size:.9rem;">
                (<?= $avgRating ?> ★ · <?= $reviewCount ?> review<?= $reviewCount == 1 ? '' : 's' ?>)
            </span>
        <?php endif; ?>
    </h5>

    <?php if (!$reviews): ?>
        <p style="color:var(--text-muted);">No reviews yet.</p>
    <?php else: ?>
        <?php foreach ($reviews as $r): ?>
            <div style="padding:14px;border:1px solid var(--border);border-radius:12px;margin-bottom:12px;background:var(--card-bg);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <strong><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                    <span style="color:var(--primary);font-weight:600;">
                        <?= str_repeat('★', (int)$r['rating']) ?><?= str_repeat('☆', 5 - (int)$r['rating']) ?>
                    </span>
                </div>
                <?php if ($r['title']): ?>
                    <div style="font-weight:600;margin-bottom:4px;"><?= htmlspecialchars($r['title']) ?></div>
                <?php endif; ?>
                <p style="color:var(--text);margin:0;line-height:1.6;"><?= nl2br(htmlspecialchars($r['review_text'] ?? '')) ?></p>
                <div style="color:var(--text-muted);font-size:.8rem;margin-top:6px;">
                    <?= htmlspecialchars($r['created_at']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
