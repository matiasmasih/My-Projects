<?php
// my_reviews.php — Member: view / edit / delete own book reviews
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('my_reviews');
if (my_access('my_reviews') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'My Reviews';
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) { header('Location: login.php'); exit; }

$success = '';
$error   = '';

// ---------- HANDLE POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $review_id = (int)($_POST['review_id'] ?? 0);

    if ($action === 'delete') {
        $chk = $pdo->prepare("SELECT id FROM reviews WHERE id = ? AND user_id = ?");
        $chk->execute([$review_id, $user_id]);
        if ($chk->fetchColumn()) {
            $pdo->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ?")->execute([$review_id, $user_id]);
            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'review_deleted', 'Deleted own review', 'review', ?)")
                ->execute([$user_id, $review_id]);
            $success = 'Review deleted.';
        } else {
            $error = 'Review not found.';
        }
    }

    if ($action === 'update') {
        $rating = (int)($_POST['rating'] ?? 0);
        $rtitle = trim($_POST['title'] ?? '');
        $rtext  = trim($_POST['review_text'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $error = 'Rating must be between 1 and 5.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM reviews WHERE id = ? AND user_id = ?");
            $chk->execute([$review_id, $user_id]);
            if (!$chk->fetchColumn()) {
                $error = 'Review not found.';
            } else {
                $pdo->prepare(
                    "UPDATE reviews SET rating = ?, title = ?, review_text = ? WHERE id = ? AND user_id = ?"
                )->execute([
                    $rating,
                    $rtitle !== '' ? $rtitle : null,
                    $rtext  !== '' ? $rtext  : null,
                    $review_id, $user_id
                ]);
                $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                               VALUES (?, 'review_updated', 'Updated own review', 'review', ?)")
                    ->execute([$user_id, $review_id]);
                $success = 'Review updated.';
            }
        }
    }
}

// ---------- LOAD MY REVIEWS ----------
$st = $pdo->prepare(
    "SELECT r.id, r.book_id, r.rating, r.title, r.review_text, r.is_approved,
            r.created_at, r.updated_at,
            b.title AS book_title, b.cover_image
     FROM reviews r
     JOIN books b ON b.id = r.book_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC"
);
$st->execute([$user_id]);
$reviews = $st->fetchAll();

// ---------- STATS ----------
$count = count($reviews);
$avg = 0;
if ($count > 0) {
    $sum = 0; foreach ($reviews as $r) { $sum += (int)$r['rating']; }
    $avg = round($sum / $count, 1);
}

// ---------- BOOKS I BORROWED BUT HAVEN'T REVIEWED ----------
// Only books with a completed borrowing, that the user hasn't reviewed yet
$st = $pdo->prepare(
    "SELECT DISTINCT b.id, b.title, b.cover_image
     FROM borrowings br
     JOIN copies c ON c.id = br.copy_id
     JOIN books  b ON b.id = c.book_id
     WHERE br.user_id = ?
       AND br.status IN ('returned','lost')
       AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.user_id = ? AND r.book_id = b.id)
     ORDER BY b.title
     LIMIT 12"
);
$st->execute([$user_id, $user_id]);
$toReview = $st->fetchAll();

// ---------- EDIT MODE ----------
$edit_id = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($edit_id > 0) {
    $st = $pdo->prepare(
        "SELECT r.*, b.title AS book_title FROM reviews r
         JOIN books b ON b.id = r.book_id
         WHERE r.id = ? AND r.user_id = ?"
    );
    $st->execute([$edit_id, $user_id]);
    $edit = $st->fetch();
}

include 'header.php';
?>

<style>
    .rev-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin-bottom:22px; }
    .rev-stat {
        background:var(--card-bg); border-radius:12px; padding:16px;
        box-shadow:var(--shadow); text-align:center;
        border:1px solid rgba(13,148,136,0.08);
    }
    .rev-stat .v { font-size:1.6rem; font-weight:700; color:var(--primary-dark); }
    .rev-stat .l { font-size:.82rem; color:var(--text-muted); margin-top:6px; }

    .review-card {
        background:var(--card-bg); border-radius:14px; padding:16px;
        display:flex; gap:16px; box-shadow:var(--shadow);
        border:1px solid rgba(13,148,136,0.08); margin-bottom:14px;
    }
    .review-thumb {
        width:60px; height:88px; object-fit:cover; border-radius:6px; flex-shrink:0;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:1.4rem;
    }
    .review-body { flex:1; min-width:0; }
    .review-body h4 { margin:0 0 6px; font-size:1rem; }
    .review-body h4 a { color:var(--text); text-decoration:none; }
    .review-body h4 a:hover { color:var(--primary-dark); }
    .review-stars { color:#f59e0b; font-size:.95rem; margin-bottom:4px; }
    .review-text { color:var(--text); line-height:1.6; font-size:.9rem; margin:6px 0; }
    .review-meta { color:var(--text-muted); font-size:.78rem; }
    .review-actions { display:flex; gap:8px; margin-top:10px; }
    .review-actions a, .review-actions button {
        padding:5px 12px; border-radius:8px; font-size:.8rem; font-weight:600;
        text-decoration:none; cursor:pointer; border:none;
    }
    .btn-edit   { background:var(--primary); color:#fff; }
    .btn-edit:hover { background:var(--primary-dark); }
    .btn-del    { background:transparent; border:1px solid #ef4444; color:#ef4444; }
    .btn-del:hover { background:#ef4444; color:#fff; }
    .badge-pending { background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:6px; font-size:.72rem; font-weight:600; margin-left:6px; }

    .edit-panel {
        background:#f0fdfa; border:2px solid var(--primary); border-radius:14px;
        padding:18px; margin-bottom:18px;
    }

    .review-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; }
    .review-tile {
        text-decoration:none; color:inherit; text-align:center;
        background:var(--card-bg); border-radius:12px; padding:10px;
        box-shadow:var(--shadow); transition:transform .15s ease;
        border:1px solid rgba(13,148,136,0.08);
    }
    .review-tile:hover { transform:translateY(-3px); }
    .review-tile img { width:100%; height:150px; object-fit:cover; border-radius:8px; margin-bottom:8px; }
    .review-tile .ph {
        width:100%; height:150px; border-radius:8px; margin-bottom:8px;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:1.8rem;
    }
    .review-tile .t { font-size:.8rem; font-weight:600; line-height:1.3; }

    .field-label { display:block; font-weight:600; font-size:.85rem; margin-bottom:6px; }
    .field-input, .field-textarea {
        width:100%; padding:10px 12px; border:1px solid var(--border);
        border-radius:10px; font-size:.92rem; background:#fff; color:var(--text);
    }
    .field-textarea { resize:vertical; font-family:inherit; }
    .star-input { display:flex; gap:4px; font-size:1.6rem; user-select:none; }
    .star-input span { cursor:pointer; color:#d1d5db; transition:color .1s; }
    .star-input span.on { color:#f59e0b; }
</style>

<h2 style="margin-bottom:18px;"><i class="bi bi-star" style="color:var(--primary);"></i> My Reviews</h2>

<?php if ($success): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- STATS -->
<div class="rev-stats">
    <div class="rev-stat">
        <div class="v"><?= $count ?></div>
        <div class="l">Reviews Written</div>
    </div>
    <div class="rev-stat">
        <div class="v">
            <?php if ($avg): ?>
                <?= $avg ?> <span style="color:#f59e0b;">★</span>
            <?php else: ?>
                —
            <?php endif; ?>
        </div>
        <div class="l">Average Rating</div>
    </div>
    <div class="rev-stat">
        <div class="v"><?= count($toReview) ?></div>
        <div class="l">Books Waiting to Review</div>
    </div>
</div>

<!-- EDIT PANEL -->
<?php if ($edit): ?>
    <div class="edit-panel">
        <h4 style="margin:0 0 14px;"><i class="bi bi-pencil-square"></i> Edit Review — <?= htmlspecialchars($edit['book_title']) ?></h4>

        <form method="POST" action="my_reviews.php">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="review_id" value="<?= (int)$edit['id'] ?>">
            <input type="hidden" name="rating" id="ratingInput" value="<?= (int)$edit['rating'] ?>">

            <div style="margin-bottom:12px;">
                <label class="field-label">Rating *</label>
                <div class="star-input" id="starInput">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span data-val="<?= $i ?>" class="<?= $i <= (int)$edit['rating'] ? 'on' : '' ?>">★</span>
                    <?php endfor; ?>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label class="field-label">Title</label>
                <input type="text" name="title" class="field-input"
                       value="<?= htmlspecialchars($edit['title'] ?? '') ?>"
                       placeholder="Short summary">
            </div>

            <div style="margin-bottom:14px;">
                <label class="field-label">Review</label>
                <textarea name="review_text" class="field-textarea" rows="4"
                          placeholder="What did you think?"><?= htmlspecialchars($edit['review_text'] ?? '') ?></textarea>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <a href="my_reviews.php" class="btn-secondary-custom">Cancel</a>
                <button type="submit" class="btn-add"><i class="bi bi-check-circle"></i> Save Changes</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- MY REVIEWS -->
<?php if (!$reviews): ?>
    <div class="table-card" style="text-align:center;padding:50px;">
        <i class="bi bi-star" style="font-size:3rem;color:var(--text-muted);"></i>
        <p style="color:var(--text-muted);margin-top:12px;">You haven't written any reviews yet.</p>
        <a href="catalog.php" class="btn-add" style="text-decoration:none;display:inline-block;margin-top:10px;">
            <i class="bi bi-book"></i> Browse Books to Review
        </a>
    </div>
<?php else: ?>
    <?php foreach ($reviews as $r): ?>
        <div class="review-card">
            <a href="book.php?id=<?= (int)$r['book_id'] ?>" style="text-decoration:none;">
                <?php if (!empty($r['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $r['cover_image'])): ?>
                    <img src="uploads/covers/<?= htmlspecialchars($r['cover_image']) ?>" class="review-thumb" alt="">
                <?php else: ?>
                    <div class="review-thumb"><i class="bi bi-book"></i></div>
                <?php endif; ?>
            </a>
            <div class="review-body">
                <h4>
                    <a href="book.php?id=<?= (int)$r['book_id'] ?>"><?= htmlspecialchars($r['book_title']) ?></a>
                    <?php if (!$r['is_approved']): ?>
                        <span class="badge-pending">pending approval</span>
                    <?php endif; ?>
                </h4>
                <div class="review-stars">
                    <?= str_repeat('★', (int)$r['rating']) ?><?= str_repeat('☆', 5 - (int)$r['rating']) ?>
                    <span style="color:var(--text-muted);font-size:.8rem;margin-left:6px;"><?= (int)$r['rating'] ?>/5</span>
                </div>
                <?php if ($r['title']): ?>
                    <div style="font-weight:600;font-size:.92rem;margin:4px 0;"><?= htmlspecialchars($r['title']) ?></div>
                <?php endif; ?>
                <?php if ($r['review_text']): ?>
                    <p class="review-text"><?= nl2br(htmlspecialchars($r['review_text'])) ?></p>
                <?php endif; ?>
                <div class="review-meta">
                    Written <?= htmlspecialchars(substr($r['created_at'],0,10)) ?>
                    <?php if ($r['updated_at'] && $r['updated_at'] !== $r['created_at']): ?>
                        · edited <?= htmlspecialchars(substr($r['updated_at'],0,10)) ?>
                    <?php endif; ?>
                </div>
                <div class="review-actions">
                    <a href="my_reviews.php?edit=<?= (int)$r['id'] ?>" class="btn-edit">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this review?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="btn-del"><i class="bi bi-trash"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- BOOKS TO REVIEW -->
<?php if ($toReview): ?>
    <h3 style="margin:30px 0 14px;font-size:1.05rem;">
        <i class="bi bi-pencil-fill" style="color:var(--primary);"></i> Books you can review
    </h3>
    <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:14px;">
        You borrowed these books — share what you thought.
    </p>
    <div class="review-grid">
        <?php foreach ($toReview as $b): ?>
            <a class="review-tile" href="book.php?id=<?= (int)$b['id'] ?>#write-review">
                <?php if (!empty($b['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $b['cover_image'])): ?>
                    <img src="uploads/covers/<?= htmlspecialchars($b['cover_image']) ?>" alt="">
                <?php else: ?>
                    <div class="ph"><i class="bi bi-book"></i></div>
                <?php endif; ?>
                <div class="t"><?= htmlspecialchars($b['title']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
// Star rating widget
(function(){
    const container = document.getElementById('starInput');
    const hidden    = document.getElementById('ratingInput');
    if (!container) return;

    container.querySelectorAll('span').forEach(star => {
        star.addEventListener('click', () => {
            const val = parseInt(star.dataset.val, 10);
            hidden.value = val;
            container.querySelectorAll('span').forEach(s => {
                s.classList.toggle('on', parseInt(s.dataset.val, 10) <= val);
            });
        });
    });
})();
</script>

<?php include 'footer.php'; ?>
