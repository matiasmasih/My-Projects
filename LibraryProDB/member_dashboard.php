<?php
// member_dashboard.php — Member home page
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('member_dashboard');
if (my_access('member_dashboard') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'My Dashboard';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id <= 0) { header('Location: login.php'); exit; }

$first_name = $_SESSION['first_name'] ?? ($_SESSION['username'] ?? 'Member');

// ---------- STATS ----------
$s = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM borrowings WHERE user_id=? AND status IN ('borrowed','overdue')) AS active_loans,
        (SELECT COUNT(*) FROM borrowings WHERE user_id=? AND status='borrowed' AND due_date < CURDATE()) AS overdue,
        (SELECT COUNT(*) FROM borrowings WHERE user_id=? AND status='returned') AS returned_total,
        (SELECT COUNT(*) FROM holds WHERE user_id=? AND status IN ('pending','ready')) AS active_holds,
        (SELECT COALESCE(SUM(amount),0) FROM fines WHERE user_id=? AND paid=0 AND waived=0) AS unpaid_fines
    "
);
$s->execute([$user_id,$user_id,$user_id,$user_id,$user_id]);
$stats = $s->fetch();

// ---------- CURRENT LOANS (up to 4) ----------
$loans = $pdo->prepare(
    "SELECT br.id, br.borrow_date, br.due_date,
            b.title, b.cover_image, b.id AS book_id,
            c.barcode,
            DATEDIFF(CURDATE(), br.due_date) AS days_overdue
     FROM borrowings br
     JOIN copies c ON c.id = br.copy_id
     JOIN books  b ON b.id = c.book_id
     WHERE br.user_id = ? AND br.status IN ('borrowed','overdue')
     ORDER BY br.due_date ASC
     LIMIT 4"
);
$loans->execute([$user_id]);
$loans = $loans->fetchAll();

// ---------- RECENT NOTIFICATIONS (up to 5) ----------
$notifs = $pdo->prepare(
    "SELECT id, type, title, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 5"
);
$notifs->execute([$user_id]);
$notifs = $notifs->fetchAll();

// ---------- RECOMMENDED / NEW BOOKS (up to 6) ----------
$newbooks = $pdo->query(
    "SELECT b.id, b.title, b.cover_image,
            (SELECT COUNT(*) FROM copies c WHERE c.book_id=b.id AND c.status='available') AS avail
     FROM books b
     ORDER BY b.id DESC
     LIMIT 6"
)->fetchAll();

include 'header.php';
?>

<style>
    .welcome-banner {
        background:linear-gradient(135deg,var(--primary),var(--primary-dark));
        color:#fff; border-radius:var(--radius); padding:24px 28px;
        margin-bottom:22px; display:flex; align-items:center; justify-content:space-between;
        flex-wrap:wrap; gap:16px;
        box-shadow:0 6px 24px rgba(13,148,136,0.25);
    }
    .welcome-banner h2 { margin:0; font-weight:700; }
    .welcome-banner p { margin:6px 0 0; opacity:.9; font-size:.95rem; }
    .welcome-banner .wb-icon { font-size:3rem; opacity:.25; }

    .mini-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin-bottom:26px; }
    .mini {
        background:var(--card-bg); border-radius:14px; padding:16px;
        box-shadow:var(--shadow); text-align:center;
        border:1px solid rgba(13,148,136,0.08); text-decoration:none; color:inherit;
        transition:transform .15s ease;
    }
    .mini:hover { transform:translateY(-2px); }
    .mini .v { font-size:1.7rem; font-weight:700; color:var(--primary-dark); line-height:1; }
    .mini .l { font-size:.82rem; color:var(--text-muted); margin-top:6px; }
    .mini.alert .v { color:#dc2626; }

    .section-title { font-size:1.05rem; font-weight:600; margin:0 0 14px; display:flex; align-items:center; gap:8px; }
    .section-title i { color:var(--primary); }

    .loan-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:14px; margin-bottom:26px; }
    .loan-card {
        background:var(--card-bg); border-radius:12px; padding:14px;
        display:flex; gap:12px; box-shadow:var(--shadow);
        border:1px solid rgba(13,148,136,0.08);
    }
    .loan-cover {
        width:52px; height:78px; object-fit:cover; border-radius:5px; flex-shrink:0;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:1.2rem;
    }
    .loan-body { flex:1; min-width:0; }
    .loan-title { font-weight:600; margin-bottom:4px; font-size:.9rem; }
    .loan-meta { font-size:.78rem; color:var(--text-muted); }
    .loan-badge { display:inline-block; padding:2px 8px; border-radius:8px; font-size:.72rem; font-weight:600; margin-top:6px; }
    .badge-ok { background:#ccfbf1; color:#115e59; }
    .badge-danger { background:#fee2e2; color:#991b1b; }

    .book-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:14px; margin-bottom:26px; }
    .book-tile {
        text-decoration:none; color:inherit; text-align:center;
        background:var(--card-bg); border-radius:12px; padding:10px;
        box-shadow:var(--shadow); transition:transform .15s ease;
        border:1px solid rgba(13,148,136,0.08);
    }
    .book-tile:hover { transform:translateY(-3px); }
    .book-tile img { width:100%; height:160px; object-fit:cover; border-radius:8px; margin-bottom:8px; }
    .book-tile .ph {
        width:100%; height:160px; border-radius:8px; margin-bottom:8px;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:2rem;
    }
    .book-tile .t { font-size:.82rem; font-weight:600; line-height:1.3; }
    .book-tile .a { font-size:.72rem; color:var(--text-muted); margin-top:4px; }

    .notif-item {
        padding:10px 0; border-bottom:1px solid rgba(0,0,0,0.05);
        display:flex; gap:10px; align-items:flex-start; font-size:.88rem;
    }
    .notif-item:last-child { border-bottom:none; }
    .notif-item.unread { background:#f0fdfa; padding-left:8px; border-radius:6px; }
    .notif-item .ic { color:var(--primary); font-size:1.1rem; }
    .notif-item .when { color:var(--text-muted); font-size:.75rem; margin-top:2px; }
</style>

<!-- WELCOME BANNER -->
<div class="welcome-banner">
    <div>
        <h2>Welcome back, <?= htmlspecialchars($first_name) ?> 👋</h2>
        <p>Here's a quick look at your library activity.</p>
    </div>
    <i class="bi bi-person-circle wb-icon"></i>
</div>

<!-- MINI STATS -->
<div class="mini-stats">
    <a class="mini" href="my_loans.php">
        <div class="v"><?= (int)$stats['active_loans'] ?></div>
        <div class="l">Active Loans</div>
    </a>
    <a class="mini <?= (int)$stats['overdue'] > 0 ? 'alert' : '' ?>" href="my_loans.php">
        <div class="v"><?= (int)$stats['overdue'] ?></div>
        <div class="l">Overdue</div>
    </a>
    <a class="mini" href="my_holds.php">
        <div class="v"><?= (int)$stats['active_holds'] ?></div>
        <div class="l">Active Holds</div>
    </a>
    <a class="mini <?= (float)$stats['unpaid_fines'] > 0 ? 'alert' : '' ?>" href="my_fines.php">
        <div class="v">€<?= number_format((float)$stats['unpaid_fines'], 2) ?></div>
        <div class="l">Unpaid Fines</div>
    </a>
</div>

<!-- CURRENT LOANS -->
<h3 class="section-title"><i class="bi bi-book"></i> Your Current Loans</h3>

<?php if (!$loans): ?>
    <div class="table-card" style="text-align:center;padding:30px;margin-bottom:26px;">
        <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--text-muted);"></i>
        <p style="color:var(--text-muted);margin-top:10px;">You have no active loans.</p>
        <a href="catalog.php" class="btn-add" style="text-decoration:none;display:inline-block;margin-top:8px;">
            <i class="bi bi-search"></i> Browse Catalog
        </a>
    </div>
<?php else: ?>
    <div class="loan-grid">
        <?php foreach ($loans as $l):
            $overdue = (int)$l['days_overdue'] > 0;
        ?>
            <div class="loan-card">
                <a href="book.php?id=<?= (int)$l['book_id'] ?>" style="text-decoration:none;">
                    <?php if (!empty($l['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $l['cover_image'])): ?>
                        <img src="uploads/covers/<?= htmlspecialchars($l['cover_image']) ?>" class="loan-cover" alt="">
                    <?php else: ?>
                        <div class="loan-cover"><i class="bi bi-book"></i></div>
                    <?php endif; ?>
                </a>
                <div class="loan-body">
                    <div class="loan-title"><?= htmlspecialchars($l['title']) ?></div>
                    <div class="loan-meta"><code><?= htmlspecialchars($l['barcode']) ?></code></div>
                    <div class="loan-meta">Due: <?= htmlspecialchars($l['due_date']) ?></div>
                    <span class="loan-badge <?= $overdue ? 'badge-danger' : 'badge-ok' ?>">
                        <?= $overdue ? $l['days_overdue'] . ' days overdue' : 'On time' ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p style="margin-top:-14px;margin-bottom:26px;">
        <a href="my_loans.php" style="color:var(--primary-dark);font-weight:600;text-decoration:none;">
            View all my loans →
        </a>
    </p>
<?php endif; ?>

<!-- NOTIFICATIONS -->
<?php if ($notifs): ?>
    <h3 class="section-title"><i class="bi bi-bell"></i> Recent Notifications</h3>
    <div class="table-card" style="margin-bottom:26px;">
        <?php foreach ($notifs as $n): ?>
            <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
                <i class="bi bi-info-circle ic"></i>
                <div style="flex:1;">
                    <div><strong><?= htmlspecialchars($n['title']) ?></strong></div>
                    <div><?= htmlspecialchars($n['message']) ?></div>
                    <div class="when"><?= htmlspecialchars($n['created_at']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- NEW BOOKS -->
<h3 class="section-title"><i class="bi bi-stars"></i> Recently Added Books</h3>
<div class="book-grid">
    <?php foreach ($newbooks as $bk): ?>
        <a class="book-tile" href="book.php?id=<?= (int)$bk['id'] ?>">
            <?php if (!empty($bk['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $bk['cover_image'])): ?>
                <img src="uploads/covers/<?= htmlspecialchars($bk['cover_image']) ?>" alt="">
            <?php else: ?>
                <div class="ph"><i class="bi bi-book"></i></div>
            <?php endif; ?>
            <div class="t"><?= htmlspecialchars($bk['title']) ?></div>
            <div class="a">
                <?= (int)$bk['avail'] > 0 ? $bk['avail'] . ' available' : 'No copies' ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php include 'footer.php'; ?>
