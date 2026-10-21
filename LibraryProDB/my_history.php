<?php
// my_history.php — Member: full borrowing history (returned loans)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('my_history');
if (my_access('my_history') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'My History';
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) { header('Location: login.php'); exit; }

// ---------- FILTERS ----------
$q    = trim($_GET['q'] ?? '');
$year = (int)($_GET['year'] ?? 0);

$where = "WHERE br.user_id = ? AND br.status IN ('returned','lost')";
$args  = [$user_id];

if ($q !== '') {
    $where .= " AND (b.title LIKE ? OR c.barcode LIKE ?)";
    $args[] = "%$q%";
    $args[] = "%$q%";
}
if ($year > 0) {
    $where .= " AND YEAR(br.return_date) = ?";
    $args[] = $year;
}

// ---------- DATA ----------
$sql = "SELECT br.id, br.borrow_date, br.due_date, br.return_date, br.status,
               b.id AS book_id, b.title, b.cover_image,
               c.barcode,
               s.code AS shelf_code,
               brn.name AS branch_name,
               f.amount AS fine_amount, f.paid AS fine_paid, f.reason AS fine_reason
        FROM borrowings br
        JOIN copies c ON c.id = br.copy_id
        JOIN books  b ON b.id = c.book_id
        LEFT JOIN shelves s ON s.id = c.shelf_id
        LEFT JOIN branches brn ON brn.id = s.branch_id
        LEFT JOIN fines f ON f.borrowing_id = br.id
        $where
        ORDER BY br.return_date DESC, br.id DESC
        LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$history = $stmt->fetchAll();

// ---------- YEARS FOR FILTER ----------
$y = $pdo->prepare(
    "SELECT DISTINCT YEAR(return_date) AS y
     FROM borrowings
     WHERE user_id = ? AND return_date IS NOT NULL
     ORDER BY y DESC"
);
$y->execute([$user_id]);
$years = array_column($y->fetchAll(), 'y');

// ---------- STATS ----------
$s = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_loans,
        SUM(CASE WHEN return_date > due_date THEN 1 ELSE 0 END) AS late_returns,
        COALESCE(SUM(f.amount),0) AS total_fines
     FROM borrowings br
     LEFT JOIN fines f ON f.borrowing_id = br.id
     WHERE br.user_id = ? AND br.status IN ('returned','lost')"
);
$s->execute([$user_id]);
$stats = $s->fetch();

include 'header.php';
?>

<style>
    .hist-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin-bottom:22px; }
    .hist-stat {
        background:var(--card-bg); border-radius:12px; padding:16px;
        text-align:center; box-shadow:var(--shadow);
        border:1px solid rgba(13,148,136,0.08);
    }
    .hist-stat .v { font-size:1.6rem; font-weight:700; color:var(--primary-dark); }
    .hist-stat .l { font-size:.82rem; color:var(--text-muted); margin-top:6px; }

    .filters {
        display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px;
        background:var(--card-bg); padding:14px; border-radius:12px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
    }
    .filters input, .filters select {
        padding:9px 12px; border:1px solid var(--border); border-radius:8px;
        background:var(--card-bg); color:var(--text); font-size:.9rem;
    }
    .filters input[type=text] { flex:1; min-width:200px; }

    .hist-table-wrap { background:var(--card-bg); border-radius:var(--radius); box-shadow:var(--shadow);
                       border:1px solid rgba(13,148,136,0.08); overflow:auto; max-height:75vh; }
    table { width:100%; border-collapse:collapse; font-size:.9rem; }
    thead th {
        position:sticky; top:0; background:var(--card-bg); z-index:2;
        text-align:left; padding:12px 14px; font-weight:600;
        border-bottom:2px solid var(--border); color:var(--text-muted);
        font-size:.82rem; text-transform:uppercase; letter-spacing:.03em;
    }
    tbody td { padding:11px 14px; border-bottom:1px solid rgba(0,0,0,0.05); vertical-align:middle; }
    tbody tr:hover { background:#f8fffe; }
    .mini-cover { width:34px; height:50px; object-fit:cover; border-radius:4px; }
    .mini-ph {
        width:34px; height:50px; border-radius:4px;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:.9rem;
    }
    .badge { display:inline-block; padding:3px 9px; border-radius:8px; font-size:.75rem; font-weight:600; }
    .badge-ok { background:#ccfbf1; color:#115e59; }
    .badge-warn { background:#fef3c7; color:#92400e; }
    .badge-danger { background:#fee2e2; color:#991b1b; }
</style>

<h2 style="margin-bottom:18px;"><i class="bi bi-clock-history" style="color:var(--primary);"></i> My History</h2>

<!-- STATS -->
<div class="hist-stats">
    <div class="hist-stat">
        <div class="v"><?= (int)$stats['total_loans'] ?></div>
        <div class="l">Books Borrowed</div>
    </div>
    <div class="hist-stat">
        <div class="v" style="color:<?= (int)$stats['late_returns'] > 0 ? '#dc2626' : 'var(--primary-dark)' ?>;">
            <?= (int)$stats['late_returns'] ?>
        </div>
        <div class="l">Late Returns</div>
    </div>
    <div class="hist-stat">
        <div class="v" style="color:<?= (float)$stats['total_fines'] > 0 ? '#dc2626' : 'var(--primary-dark)' ?>;">
            €<?= number_format((float)$stats['total_fines'], 2) ?>
        </div>
        <div class="l">Total Fines</div>
    </div>
</div>

<!-- FILTERS -->
<form class="filters" method="get" action="my_history.php">
    <input type="text" name="q" placeholder="Search title or barcode…" value="<?= htmlspecialchars($q) ?>">
    <select name="year">
        <option value="0">All years</option>
        <?php foreach ($years as $yy): ?>
            <option value="<?= (int)$yy ?>" <?= $year === (int)$yy ? 'selected' : '' ?>><?= (int)$yy ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-add"><i class="bi bi-search"></i> Filter</button>
    <a href="my_history.php" class="btn-secondary-custom"><i class="bi bi-x-circle"></i> Reset</a>
</form>

<!-- TABLE -->
<?php if (!$history): ?>
    <div class="hist-table-wrap" style="text-align:center;padding:50px;">
        <i class="bi bi-clock-history" style="font-size:3rem;color:var(--text-muted);"></i>
        <p style="color:var(--text-muted);margin-top:12px;">No returned loans yet.</p>
        <a href="catalog.php" class="btn-add" style="text-decoration:none;display:inline-block;margin-top:8px;">
            <i class="bi bi-search"></i> Browse Catalog
        </a>
    </div>
<?php else: ?>
    <div class="hist-table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:60px;"></th>
                    <th>Book</th>
                    <th>Barcode</th>
                    <th>Branch · Shelf</th>
                    <th>Borrowed</th>
                    <th>Due</th>
                    <th>Returned</th>
                    <th>Status</th>
                    <th>Fine</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h):
                    $late = $h['return_date'] && $h['return_date'] > $h['due_date'] . ' 23:59:59';
                ?>
                    <tr>
                        <td>
                            <?php if (!empty($h['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $h['cover_image'])): ?>
                                <img src="uploads/covers/<?= htmlspecialchars($h['cover_image']) ?>" class="mini-cover" alt="">
                            <?php else: ?>
                                <div class="mini-ph"><i class="bi bi-book"></i></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="book.php?id=<?= (int)$h['book_id'] ?>" style="color:var(--primary-dark);text-decoration:none;font-weight:600;">
                                <?= htmlspecialchars($h['title']) ?>
                            </a>
                        </td>
                        <td><code><?= htmlspecialchars($h['barcode']) ?></code></td>
                        <td>
                            <?= $h['branch_name'] ? htmlspecialchars($h['branch_name']) : '—' ?>
                            <?= $h['shelf_code'] ? ' · ' . htmlspecialchars($h['shelf_code']) : '' ?>
                        </td>
                        <td><?= htmlspecialchars(substr($h['borrow_date'],0,10)) ?></td>
                        <td><?= htmlspecialchars($h['due_date']) ?></td>
                        <td><?= $h['return_date'] ? htmlspecialchars(substr($h['return_date'],0,10)) : '—' ?></td>
                        <td>
                            <?php if ($h['status'] === 'lost'): ?>
                                <span class="badge badge-danger">lost</span>
                            <?php elseif ($late): ?>
                                <span class="badge badge-warn">late</span>
                            <?php else: ?>
                                <span class="badge badge-ok">returned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($h['fine_amount'] !== null && (float)$h['fine_amount'] > 0): ?>
                                <span class="badge <?= $h['fine_paid'] ? 'badge-ok' : 'badge-danger' ?>">
                                    €<?= number_format((float)$h['fine_amount'], 2) ?>
                                    <?= $h['fine_paid'] ? '✓ paid' : 'unpaid' ?>
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
    <p style="color:var(--text-muted);font-size:.82rem;margin-top:10px;">
        Showing <?= count($history) ?> record(s) — newest first.
    </p>
<?php endif; ?>

<?php include 'footer.php'; ?>
