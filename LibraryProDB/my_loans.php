<?php
// my_loans.php — Member: view own current loans + history
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('my_loans');
if (my_access('my_loans') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'My Loans';
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_id <= 0) {
    header('Location: login.php'); exit;
}

// ---------- TAB: current vs history ----------
$tab = $_GET['tab'] ?? 'current';
if (!in_array($tab, ['current', 'history'], true)) $tab = 'current';

// ---------- CURRENT LOANS ----------
$current = $pdo->prepare(
    "SELECT br.id, br.borrow_date, br.due_date, br.renewed_count, br.status,
            b.title, b.cover_image, b.id AS book_id,
            c.barcode,
            s.code AS shelf_code,
            br2.name AS branch_name,
            DATEDIFF(CURDATE(), br.due_date) AS days_overdue
     FROM borrowings br
     JOIN copies c ON c.id = br.copy_id
     JOIN books  b ON b.id = c.book_id
     LEFT JOIN shelves s ON s.id = c.shelf_id
     LEFT JOIN branches br2 ON br2.id = s.branch_id
     WHERE br.user_id = ? AND br.status IN ('borrowed','overdue')
     ORDER BY br.due_date ASC"
);
$current->execute([$user_id]);
$current = $current->fetchAll();

// ---------- HISTORY ----------
$history = $pdo->prepare(
    "SELECT br.id, br.borrow_date, br.due_date, br.return_date, br.status,
            b.title, b.cover_image, b.id AS book_id,
            c.barcode,
            f.amount AS fine_amount, f.paid AS fine_paid
     FROM borrowings br
     JOIN copies c ON c.id = br.copy_id
     JOIN books  b ON b.id = c.book_id
     LEFT JOIN fines f ON f.borrowing_id = br.id
     WHERE br.user_id = ? AND br.status IN ('returned','lost')
     ORDER BY br.return_date DESC
     LIMIT 100"
);
$history->execute([$user_id]);
$history = $history->fetchAll();

// ---------- STATS ----------
$stats = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM borrowings WHERE user_id=? AND status IN ('borrowed','overdue')) AS active,
        (SELECT COUNT(*) FROM borrowings WHERE user_id=? AND status='borrowed' AND due_date < CURDATE()) AS overdue,
        (SELECT COUNT(*) FROM borrowings WHERE user_id=? AND status='returned') AS total_returned,
        (SELECT COALESCE(SUM(amount),0) FROM fines WHERE user_id=? AND paid=0 AND waived=0) AS unpaid_fines"
);
$stats->execute([$user_id, $user_id, $user_id, $user_id]);
$stats = $stats->fetch();

include 'header.php';
?>

<style>
    .loan-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; }
    .loan-card {
        background:var(--card-bg); border-radius:var(--radius);
        padding:16px; box-shadow:var(--shadow);
        display:flex; gap:14px; align-items:flex-start;
        border:1px solid rgba(13,148,136,0.08);
    }
    .loan-cover {
        width:60px; height:90px; object-fit:cover; border-radius:6px; flex-shrink:0;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:1.4rem;
    }
    .loan-body { flex:1; min-width:0; }
    .loan-title { font-weight:600; color:var(--text); margin-bottom:4px; }
    .loan-meta { font-size:.82rem; color:var(--text-muted); margin:2px 0; }
    .loan-badge { display:inline-block; padding:2px 8px; border-radius:8px; font-size:.75rem; font-weight:600; margin-top:6px; }
    .badge-ok      { background:#ccfbf1; color:#115e59; }
    .badge-warn    { background:#fef3c7; color:#92400e; }
    .badge-danger  { background:#fee2e2; color:#991b1b; }
    .badge-muted   { background:#e2e8f0; color:#475569; }

    .tabs { display:flex; gap:8px; margin-bottom:20px; border-bottom:2px solid var(--border); }
    .tabs a {
        padding:10px 16px; text-decoration:none; color:var(--text-muted);
        font-weight:600; font-size:.9rem; border-bottom:3px solid transparent;
        margin-bottom:-2px;
    }
    .tabs a.active { color:var(--primary-dark); border-bottom-color:var(--primary); }
    .tabs a:hover { color:var(--primary); }

    .stat-strip { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:22px; }
    .stat-mini {
        background:var(--card-bg); border-radius:12px; padding:14px;
        box-shadow:var(--shadow); text-align:center;
        border:1px solid rgba(13,148,136,0.08);
    }
    .stat-mini .v { font-size:1.5rem; font-weight:700; color:var(--primary-dark); }
    .stat-mini .l { font-size:.8rem; color:var(--text-muted); margin-top:4px; }
</style>

<h2 style="margin-bottom:18px;"><i class="bi bi-journal-bookmark" style="color:var(--primary);"></i> My Loans</h2>

<!-- STATS STRIP -->
<div class="stat-strip">
    <div class="stat-mini">
        <div class="v"><?= (int)$stats['active'] ?></div>
        <div class="l">Active Loans</div>
    </div>
    <div class="stat-mini">
        <div class="v" style="color:<?= (int)$stats['overdue'] > 0 ? '#dc2626' : 'var(--primary-dark)' ?>;">
            <?= (int)$stats['overdue'] ?>
        </div>
        <div class="l">Overdue</div>
    </div>
    <div class="stat-mini">
        <div class="v"><?= (int)$stats['total_returned'] ?></div>
        <div class="l">Returned (all-time)</div>
    </div>
    <div class="stat-mini">
        <div class="v" style="color:<?= (float)$stats['unpaid_fines'] > 0 ? '#dc2626' : 'var(--primary-dark)' ?>;">
            €<?= number_format((float)$stats['unpaid_fines'], 2) ?>
        </div>
        <div class="l">Unpaid Fines</div>
    </div>
</div>

<!-- TABS -->
<div class="tabs">
    <a href="my_loans.php?tab=current" class="<?= $tab === 'current' ? 'active' : '' ?>">
        <i class="bi bi-book"></i> Current (<?= count($current) ?>)
    </a>
    <a href="my_loans.php?tab=history" class="<?= $tab === 'history' ? 'active' : '' ?>">
        <i class="bi bi-clock-history"></i> History (<?= count($history) ?>)
    </a>
</div>

<?php if ($tab === 'current'): ?>

    <?php if (!$current): ?>
        <div class="table-card" style="text-align:center;padding:40px;">
            <i class="bi bi-inbox" style="font-size:3rem;color:var(--text-muted);"></i>
            <p style="color:var(--text-muted);margin-top:12px;">You have no active loans.</p>
            <a href="catalog.php" class="btn-add" style="text-decoration:none;margin-top:10px;display:inline-block;">
                <i class="bi bi-search"></i> Browse Catalog
            </a>
        </div>
    <?php else: ?>
        <div class="loan-grid">
            <?php foreach ($current as $l):
                $overdue = (int)$l['days_overdue'] > 0;
                $badge_class = $overdue ? 'badge-danger' : 'badge-ok';
                $badge_text = $overdue
                    ? $l['days_overdue'] . ' day(s) overdue'
                    : 'Due ' . $l['due_date'];
            ?>
                <div class="loan-card">
                    <a href="book.php?id=<?= (int)$l['book_id'] ?>" style="text-decoration:none;">
                        <?php if (!empty($l['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $l['cover_image'])): ?>
                            <img src="uploads/covers/<?= htmlspecialchars($l['cover_image']) ?>" alt="" class="loan-cover">
                        <?php else: ?>
                            <div class="loan-cover"><i class="bi bi-book"></i></div>
                        <?php endif; ?>
                    </a>
                    <div class="loan-body">
                        <div class="loan-title">
                            <a href="book.php?id=<?= (int)$l['book_id'] ?>" style="color:inherit;text-decoration:none;">
                                <?= htmlspecialchars($l['title']) ?>
                            </a>
                        </div>
                        <div class="loan-meta"><i class="bi bi-upc"></i> <code><?= htmlspecialchars($l['barcode']) ?></code></div>
                        <div class="loan-meta"><i class="bi bi-calendar-event"></i> Borrowed: <?= htmlspecialchars(substr($l['borrow_date'],0,10)) ?></div>
                        <div class="loan-meta"><i class="bi bi-calendar-check"></i> Due: <?= htmlspecialchars($l['due_date']) ?></div>
                        <?php if (!empty($l['branch_name'])): ?>
                            <div class="loan-meta"><i class="bi bi-building"></i> <?= htmlspecialchars($l['branch_name']) ?><?= $l['shelf_code'] ? ' · ' . htmlspecialchars($l['shelf_code']) : '' ?></div>
                        <?php endif; ?>
                        <?php if ((int)$l['renewed_count'] > 0): ?>
                            <div class="loan-meta"><i class="bi bi-arrow-clockwise"></i> Renewed <?= (int)$l['renewed_count'] ?>/<?= MAX_RENEWALS ?> times</div>
                        <?php endif; ?>
                        <span class="loan-badge <?= $badge_class ?>"><?= htmlspecialchars($badge_text) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>

    <?php if (!$history): ?>
        <div class="table-card" style="text-align:center;padding:40px;">
            <i class="bi bi-clock-history" style="font-size:3rem;color:var(--text-muted);"></i>
            <p style="color:var(--text-muted);margin-top:12px;">No returned loans yet.</p>
        </div>
    <?php else: ?>
        <div class="table-card">
            <div style="overflow:auto;max-height:70vh;">
                <table>
                    <thead>
                        <tr style="position:sticky;top:0;background:var(--card-bg);z-index:2;">
                            <th>Book</th>
                            <th>Barcode</th>
                            <th>Borrowed</th>
                            <th>Due</th>
                            <th>Returned</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td>
                                <a href="book.php?id=<?= (int)$h['book_id'] ?>" style="color:var(--primary-dark);text-decoration:none;font-weight:500;">
                                    <?= htmlspecialchars($h['title']) ?>
                                </a>
                            </td>
                            <td><code><?= htmlspecialchars($h['barcode']) ?></code></td>
                            <td><?= htmlspecialchars(substr($h['borrow_date'],0,10)) ?></td>
                            <td><?= htmlspecialchars($h['due_date']) ?></td>
                            <td><?= $h['return_date'] ? htmlspecialchars(substr($h['return_date'],0,10)) : '—' ?></td>
                            <td>
                                <?php if ($h['fine_amount'] !== null && (float)$h['fine_amount'] > 0): ?>
                                    <span class="loan-badge <?= $h['fine_paid'] ? 'badge-ok' : 'badge-danger' ?>">
                                        €<?= number_format((float)$h['fine_amount'], 2) ?>
                                        <?= $h['fine_paid'] ? '(paid)' : '(unpaid)' ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
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
