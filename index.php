<?php
// index.php — Public landing page (multi-language)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('index');

$page_title = t('home_hero_title');
$hide_welcome_banner = true;

// ---- Public stats ----
$totalBooks   = (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalCopies  = (int)$pdo->query("SELECT COUNT(*) FROM copies")->fetchColumn();
$totalAuthors = (int)$pdo->query("SELECT COUNT(*) FROM authors")->fetchColumn();
$totalMembers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member'")->fetchColumn();

// ---- Recently added books ----
$recent = $pdo->query(
    "SELECT b.id, b.title, b.cover_image, b.publication_year,
            p.name AS publisher_name,
            (SELECT GROUP_CONCAT(CONCAT(a.first_name,' ',a.last_name) SEPARATOR ', ')
             FROM book_authors ba JOIN authors a ON a.id = ba.author_id
             WHERE ba.book_id = b.id) AS authors
     FROM books b
     LEFT JOIN publishers p ON p.id = b.publisher_id
     ORDER BY b.id DESC
     LIMIT 6"
)->fetchAll();

// ---- Popular genres ----
$topGenres = $pdo->query(
    "SELECT g.id, g.name, COUNT(bg.book_id) AS book_count
     FROM genres g
     LEFT JOIN book_genres bg ON bg.genre_id = g.id
     GROUP BY g.id, g.name
     ORDER BY book_count DESC, g.name
     LIMIT 8"
)->fetchAll();

include 'header.php';
?>

<!-- HERO -->
<div class="welcome-banner" style="text-align:center;padding:50px 30px;">
    <h2 style="font-size:2.2rem;margin-bottom:12px;">
        <i class="bi bi-book-half"></i> <?= htmlspecialchars(t('home_hero_title')) ?>
    </h2>
    <p style="font-size:1.05rem;margin-bottom:24px;">
        <?= htmlspecialchars(t('home_hero_text')) ?>
    </p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
        <a href="catalog.php" class="btn-add" style="background:white;color:var(--primary-dark);padding:12px 26px;font-size:1rem;">
            <i class="bi bi-search"></i> <?= htmlspecialchars(t('home_browse')) ?>
        </a>
        <?php if (current_role() === 'guest'): ?>
            <a href="register.php" class="btn-add" style="background:rgba(255,255,255,0.2);padding:12px 26px;font-size:1rem;">
                <i class="bi bi-person-plus"></i> <?= htmlspecialchars(t('register')) ?>
            </a>
        <?php else: ?>
            <a href="my_loans.php" class="btn-add" style="background:rgba(255,255,255,0.2);padding:12px 26px;font-size:1rem;">
                <i class="bi bi-bookmark"></i> <?= htmlspecialchars(t('home_my_loans')) ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- STATS -->
<div class="row g-3 mb-4" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
    <div class="table-card" style="padding:20px;text-align:center;">
        <div style="font-size:2rem;font-weight:800;color:var(--primary);"><?= number_format($totalBooks) ?></div>
        <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);font-weight:600;margin-top:4px;"><?= htmlspecialchars(t('home_stat_books')) ?></div>
    </div>
    <div class="table-card" style="padding:20px;text-align:center;">
        <div style="font-size:2rem;font-weight:800;color:var(--primary);"><?= number_format($totalCopies) ?></div>
        <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);font-weight:600;margin-top:4px;"><?= htmlspecialchars(t('home_stat_copies')) ?></div>
    </div>
    <div class="table-card" style="padding:20px;text-align:center;">
        <div style="font-size:2rem;font-weight:800;color:var(--primary);"><?= number_format($totalAuthors) ?></div>
        <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);font-weight:600;margin-top:4px;"><?= htmlspecialchars(t('home_stat_authors')) ?></div>
    </div>
    <div class="table-card" style="padding:20px;text-align:center;">
        <div style="font-size:2rem;font-weight:800;color:var(--primary);"><?= number_format($totalMembers) ?></div>
        <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);font-weight:600;margin-top:4px;"><?= htmlspecialchars(t('home_stat_members')) ?></div>
    </div>
</div>

<!-- RECENT BOOKS -->
<div class="table-card">
    <h5><i class="bi bi-stars"></i> <?= htmlspecialchars(t('home_recent')) ?></h5>
    <?php if (!$recent): ?>
        <p style="color:var(--text-muted);"><?= htmlspecialchars(t('home_no_books')) ?></p>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
        <?php foreach ($recent as $b): ?>
            <a href="book.php?id=<?= (int)$b['id'] ?>" style="text-decoration:none;color:inherit;">
                <div style="border:1px solid var(--border);border-radius:12px;padding:14px;background:var(--card-bg);height:100%;transition:all .2s;">
                    <div style="font-weight:700;color:var(--text);margin-bottom:6px;">
                        <?= htmlspecialchars($b['title']) ?>
                    </div>
                    <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:4px;">
                        <i class="bi bi-person"></i>
                        <?= htmlspecialchars($b['authors'] ?? t('home_unknown_author')) ?>
                    </div>
                    <?php if ($b['publisher_name']): ?>
                    <div style="font-size:.8rem;color:var(--text-muted);">
                        <i class="bi bi-building"></i> <?= htmlspecialchars($b['publisher_name']) ?>
                        <?= $b['publication_year'] ? '· ' . (int)$b['publication_year'] : '' ?>
                    </div>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- TOP GENRES -->
<div class="table-card">
    <h5><i class="bi bi-tags"></i> <?= htmlspecialchars(t('home_genres')) ?></h5>
    <?php if (!$topGenres): ?>
        <p style="color:var(--text-muted);"><?= htmlspecialchars(t('home_no_genres')) ?></p>
    <?php else: ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php foreach ($topGenres as $g): ?>
            <a href="catalog.php?genre=<?= (int)$g['id'] ?>"
               class="status-badge status-pending"
               style="text-decoration:none;padding:8px 16px;font-size:.9rem;">
                <?= htmlspecialchars($g['name']) ?>
                <span style="opacity:.7;margin-left:4px;">(<?= (int)$g['book_count'] ?>)</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
