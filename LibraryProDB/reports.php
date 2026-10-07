<?php
// reports.php — Admin: circulation, popular, overdue, branch, fines, top members
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('reports');
if (my_access('reports') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'Reports';

// ---------- FILTERS ----------
$tab       = $_GET['tab'] ?? 'summary';
$date_from = trim($_GET['from'] ?? date('Y-m-01'));
$date_to   = trim($_GET['to']   ?? date('Y-m-d'));
$branch_id = (int)($_GET['branch'] ?? 0);

$valid_tabs = ['summary','popular','overdue','branches','fines','members'];
if (!in_array($tab, $valid_tabs, true)) $tab = 'summary';

$all_branches = $pdo->query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();

// Helper: build branch WHERE + args
$br_where = ''; $br_args = [];
if ($branch_id > 0) {
    $br_where = ' AND br.branch_id = ?';
    $br_args  = [$branch_id];
}

// ============================================================
// SUMMARY
// ============================================================
$summary = null;
if ($tab === 'summary') {
    $base_args = [$date_from, $date_to];
    if ($branch_id > 0) $base_args[] = $branch_id;

    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM borrowings br
             WHERE DATE(br.borrow_date) BETWEEN ? AND ? $br_where) AS checkouts,
            (SELECT COUNT(*) FROM borrowings br
             WHERE br.return_date IS NOT NULL
               AND DATE(br.return_date) BETWEEN ? AND ? $br_where) AS returns_,
            (SELECT COUNT(*) FROM borrowings br
             WHERE br.status IN ('borrowed','overdue') $br_where) AS active_loans,
            (SELECT COUNT(*) FROM borrowings br
             WHERE br.status IN ('borrowed','overdue') AND br.due_date < CURDATE() $br_where) AS overdue_count,
            (SELECT COUNT(*) FROM users WHERE role='member' AND status='active') AS members,
            (SELECT COUNT(*) FROM books) AS titles,
            (SELECT COUNT(*) FROM copies) AS copies,
            (SELECT COUNT(*) FROM copies WHERE status='available') AS copies_available,
            (SELECT COUNT(*) FROM copies WHERE status='borrowed') AS copies_borrowed,
            (SELECT ROUND(AVG(DATEDIFF(return_date, borrow_date)),1) FROM borrowings br
             WHERE br.return_date IS NOT NULL
               AND DATE(br.return_date) BETWEEN ? AND ? $br_where) AS avg_loan_days"
    );
    // args in order of the placeholders
    $a = [];
    // First subquery
    $a[] = $date_from; $a[] = $date_to; if ($branch_id > 0) $a[] = $branch_id;
    // Second subquery
    $a[] = $date_from; $a[] = $date_to; if ($branch_id > 0) $a[] = $branch_id;
    // Active loans subquery
    if ($branch_id > 0) $a[] = $branch_id;
    // Overdue subquery
    if ($branch_id > 0) $a[] = $branch_id;
    // avg_loan_days subquery
    $a[] = $date_from; $a[] = $date_to; if ($branch_id > 0) $a[] = $branch_id;
    $stmt->execute($a);
    $summary = $stmt->fetch();
}

// ============================================================
// POPULAR BOOKS
// ============================================================
$popular = [];
if ($tab === 'popular') {
    $args = [$date_from, $date_to];
    $bw = '';
    if ($branch_id > 0) { $bw = ' AND br.branch_id = ?'; $args[] = $branch_id; }

    $stmt = $pdo->prepare(
        "SELECT b.id, b.title, b.cover_image,
                p.name AS publisher_name,
                (SELECT GROUP_CONCAT(CONCAT(a.first_name,' ',a.last_name) ORDER BY a.last_name SEPARATOR ', ')
                 FROM book_authors ba JOIN authors a ON a.id=ba.author_id WHERE ba.book_id=b.id) AS authors,
                COUNT(br.id) AS borrow_count,
                SUM(CASE WHEN br.return_date IS NULL THEN 1 ELSE 0 END) AS still_out
         FROM books b
         LEFT JOIN publishers p ON p.id = b.publisher_id
         LEFT JOIN copies c ON c.book_id = b.id
         LEFT JOIN borrowings br ON br.copy_id = c.id
              AND DATE(br.borrow_date) BETWEEN ? AND ? $bw
         GROUP BY b.id
         HAVING borrow_count > 0
         ORDER BY borrow_count DESC, b.title
         LIMIT 30"
    );
    $stmt->execute($args);
    $popular = $stmt->fetchAll();
}

// ============================================================
// OVERDUE
// ============================================================
$overdue = [];
if ($tab === 'overdue') {
    $args = [];
    $bw = '';
    if ($branch_id > 0) { $bw = ' AND br.branch_id = ?'; $args[] = $branch_id; }

    $stmt = $pdo->prepare(
        "SELECT br.id, br.borrow_date, br.due_date, br.renewed_count,
                u.id AS user_id, u.username, u.first_name, u.last_name, u.card_number, u.email,
                b.id AS book_id, b.title,
                c.barcode,
                bn.name AS branch_name,
                DATEDIFF(CURDATE(), br.due_date) AS days_overdue,
                LEAST(DATEDIFF(CURDATE(), br.due_date) * " . FINE_PER_DAY . ", " . MAX_FINE_PER_BOOK . ") AS est_fine
         FROM borrowings br
         JOIN users u ON u.id = br.user_id
         JOIN copies c ON c.id = br.copy_id
         JOIN books b ON b.id = c.book_id
         LEFT JOIN branches bn ON bn.id = br.branch_id
         WHERE br.status IN ('borrowed','overdue') AND br.due_date < CURDATE() $bw
         ORDER BY days_overdue DESC"
    );
    $stmt->execute($args);
    $overdue = $stmt->fetchAll();
}

// ============================================================
// BRANCH PERFORMANCE
// ============================================================
$branches_rep = [];
if ($tab === 'branches') {
    $stmt = $pdo->prepare(
        "SELECT bn.id, bn.name, bn.city,
                (SELECT COUNT(*) FROM copies c JOIN shelves s ON s.id=c.shelf_id
                 WHERE s.branch_id = bn.id) AS copies,
                (SELECT COUNT(*) FROM users u WHERE u.branch_id = bn.id AND u.role='member') AS members,
                (SELECT COUNT(*) FROM borrowings br WHERE br.branch_id = bn.id
                 AND DATE(br.borrow_date) BETWEEN ? AND ?) AS checkouts,
                (SELECT COUNT(*) FROM borrowings br WHERE br.branch_id = bn.id
                 AND br.status IN ('borrowed','overdue')) AS active_loans,
                (SELECT COUNT(*) FROM borrowings br WHERE br.branch_id = bn.id
                 AND br.status IN ('borrowed','overdue') AND br.due_date < CURDATE()) AS overdue
         FROM branches bn
         WHERE bn.is_active = 1
         ORDER BY bn.name"
    );
    $stmt->execute([$date_from, $date_to]);
    $branches_rep = $stmt->fetchAll();
}

// ============================================================
// FINES & REVENUE
// ============================================================
$fines_rep = [];
$fines_summary = ['collected'=>0,'waived'=>0,'unpaid'=>0,'count'=>0];
if ($tab === 'fines') {
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(issued_date, '%Y-%m') AS ym,
                COUNT(*) AS issued_count,
                SUM(amount) AS issued_total,
                SUM(CASE WHEN paid=1 THEN amount ELSE 0 END) AS collected,
                SUM(CASE WHEN waived=1 THEN amount ELSE 0 END) AS waived,
                SUM(CASE WHEN paid=0 AND waived=0 THEN amount ELSE 0 END) AS unpaid
         FROM fines
         WHERE issued_date BETWEEN ? AND ?
         GROUP BY ym
         ORDER BY ym DESC"
    );
    $stmt->execute([$date_from, $date_to]);
    $fines_rep = $stmt->fetchAll();

    $s = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN paid=1 THEN amount ELSE 0 END),0) AS collected,
            COALESCE(SUM(CASE WHEN waived=1 THEN amount ELSE 0 END),0) AS waived,
            COALESCE(SUM(CASE WHEN paid=0 AND waived=0 THEN amount ELSE 0 END),0) AS unpaid,
            COUNT(*) AS count
         FROM fines WHERE issued_date BETWEEN ? AND ?"
    );
    $s->execute([$date_from, $date_to]);
    $fines_summary = $s->fetch();
}

// ============================================================
// TOP MEMBERS
// ============================================================
$top_members = [];
if ($tab === 'members') {
    $args = [$date_from, $date_to];
    $bw = '';
    if ($branch_id > 0) { $bw = ' AND br.branch_id = ?'; $args[] = $branch_id; }

    $stmt = $pdo->prepare(
        "SELECT u.id, u.username, u.first_name, u.last_name, u.card_number,
                COUNT(br.id) AS borrow_count,
                SUM(CASE WHEN br.return_date IS NOT NULL AND br.return_date > CONCAT(br.due_date, ' 23:59:59') THEN 1 ELSE 0 END) AS late_returns,
                COALESCE((SELECT SUM(amount) FROM fines f WHERE f.user_id = u.id AND f.paid=0 AND f.waived=0),0) AS unpaid_fines
         FROM users u
         LEFT JOIN borrowings br ON br.user_id = u.id
              AND DATE(br.borrow_date) BETWEEN ? AND ? $bw
         WHERE u.role = 'member'
         GROUP BY u.id
         HAVING borrow_count > 0
         ORDER BY borrow_count DESC, late_returns ASC
         LIMIT 30"
    );
    $stmt->execute($args);
    $top_members = $stmt->fetchAll();
}

include 'header.php';
?>

<style>
    .rep-tabs {
        display:flex; gap:6px; margin-bottom:20px; border-bottom:2px solid var(--border);
        flex-wrap:wrap;
    }
    .rep-tabs a {
        padding:10px 16px; text-decoration:none; color:var(--text-muted);
        font-weight:600; font-size:.88rem; border-bottom:3px solid transparent;
        margin-bottom:-2px; white-space:nowrap;
    }
    .rep-tabs a.active { color:var(--primary-dark); border-bottom-color:var(--primary); }
    .rep-tabs a:hover { color:var(--primary); }

    .rep-toolbar {
        display:flex; gap:10px; flex-wrap:wrap; align-items:center;
        background:var(--card-bg); padding:14px; border-radius:12px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        margin-bottom:20px;
    }
    .rep-toolbar input, .rep-toolbar select {
        padding:9px 12px; border:1px solid var(--border); border-radius:8px;
        background:var(--card-bg); color:var(--text); font-size:.9rem;
    }
    .rep-toolbar label { font-size:.82rem; font-weight:600; color:var(--text-muted); }

    .stat-grid {
        display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
        gap:14px; margin-bottom:22px;
    }
    .stat-card {
        background:var(--card-bg); border-radius:14px; padding:18px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        text-align:center;
    }
    .stat-card .v { font-size:1.7rem; font-weight:700; color:var(--primary-dark); line-height:1; }
    .stat-card .l { font-size:.82rem; color:var(--text-muted); margin-top:6px; }
    .stat-card.danger .v { color:#dc2626; }
    .stat-card.ok .v     { color:#059669; }
    .stat-card.warn .v   { color:#d97706; }

    .rep-card {
        background:var(--card-bg); border-radius:12px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        overflow:hidden; margin-bottom:20px;
    }
    .rep-scroll {
        width:100%; overflow-x:auto; overflow-y:visible;
        -webkit-overflow-scrolling:touch; padding-bottom:10px;
    }
    table.rep-table { width:100%; border-collapse:collapse; min-width:900px; }
    table.rep-table th, table.rep-table td {
        padding:12px 14px; vertical-align:middle;
        white-space:nowrap; border-bottom:1px solid rgba(0,0,0,0.05);
        text-align:left; font-size:.88rem;
    }
    table.rep-table thead th {
        background:var(--card-bg); font-weight:600; font-size:.78rem;
        color:var(--text-muted); text-transform:uppercase; letter-spacing:.03em;
        border-bottom:2px solid var(--border);
    }
    table.rep-table tbody tr:hover { background:#f8fffe; }

    .badge { display:inline-block; padding:3px 9px; border-radius:8px; font-size:.72rem; font-weight:600; white-space:nowrap; }
    .b-danger { background:#fee2e2; color:#991b1b; }
    .b-warn   { background:#fef3c7; color:#92400e; }
    .b-ok     { background:#d1fae5; color:#065f46; }
    .b-info   { background:#dbeafe; color:#1e40af; }

    .rank {
        display:inline-grid; place-items:center;
        width:28px; height:28px; border-radius:8px;
        background:#f0fdfa; color:var(--primary-dark); font-weight:700; font-size:.85rem;
    }
    .rank.top1 { background:#fef3c7; color:#92400e; }
    .rank.top2 { background:#e2e8f0; color:#475569; }
    .rank.top3 { background:#fed7aa; color:#9a3412; }

    .book-mini { width:36px; height:52px; object-fit:cover; border-radius:4px; }
    .cover-ph {
        width:36px; height:52px; border-radius:4px;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:1rem;
    }
</style>

<h2 style="margin-bottom:18px;"><i class="bi bi-bar-chart-fill" style="color:var(--primary);"></i> Reports</h2>

<!-- TABS -->
<div class="rep-tabs">
    <a href="reports.php?tab=summary&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>&branch=<?= $branch_id ?>" class="<?= $tab==='summary'?'active':'' ?>"><i class="bi bi-speedometer2"></i> Summary</a>
    <a href="reports.php?tab=popular&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>&branch=<?= $branch_id ?>" class="<?= $tab==='popular'?'active':'' ?>"><i class="bi bi-fire"></i> Popular Books</a>
    <a href="reports.php?tab=overdue&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>&branch=<?= $branch_id ?>" class="<?= $tab==='overdue'?'active':'' ?>"><i class="bi bi-exclamation-triangle"></i> Overdue</a>
    <a href="reports.php?tab=branches&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>&branch=<?= $branch_id ?>" class="<?= $tab==='branches'?'active':'' ?>"><i class="bi bi-building"></i> Branches</a>
    <a href="reports.php?tab=fines&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>&branch=<?= $branch_id ?>" class="<?= $tab==='fines'?'active':'' ?>"><i class="bi bi-cash-coin"></i> Fines &amp; Revenue</a>
    <a href="reports.php?tab=members&from=<?= urlencode($date_from) ?>&to=<?= urlencode($date_to) ?>&branch=<?= $branch_id ?>" class="<?= $tab==='members'?'active':'' ?>"><i class="bi bi-people"></i> Top Members</a>
</div>

<!-- FILTERS -->
<form class="rep-toolbar" method="get" action="reports.php">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

    <label>From</label>
    <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>">

    <label>To</label>
    <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>">

    <label>Branch</label>
    <select name="branch">
        <option value="0">All branches</option>
        <?php foreach ($all_branches as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= $branch_id===(int)$b['id']?'selected':'' ?>>
                <?= htmlspecialchars($b['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn-add" style="white-space:nowrap;"><i class="bi bi-search"></i> Apply</button>
    <a href="reports.php?tab=<?= htmlspecialchars($tab) ?>" class="btn-secondary-custom" style="white-space:nowrap;">Reset dates</a>
</form>

<?php /* ============================================================
       TAB: SUMMARY
   ============================================================ */ ?>
<?php if ($tab === 'summary'): ?>
    <?php if ($summary): ?>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="v"><?= (int)$summary['checkouts'] ?></div>
            <div class="l">Check-outs</div>
        </div>
        <div class="stat-card">
            <div class="v"><?= (int)$summary['returns_'] ?></div>
            <div class="l">Returns</div>
        </div>
        <div class="stat-card">
            <div class="v"><?= (int)$summary['active_loans'] ?></div>
            <div class="l">Active Loans</div>
        </div>
        <div class="stat-card <?= (int)$summary['overdue_count'] > 0 ? 'danger' : '' ?>">
            <div class="v"><?= (int)$summary['overdue_count'] ?></div>
            <div class="l">Overdue</div>
        </div>
        <div class="stat-card">
            <div class="v"><?= (int)$summary['members'] ?></div>
            <div class="l">Active Members</div>
        </div>
        <div class="stat-card">
            <div class="v"><?= (int)$summary['titles'] ?></div>
            <div class="l">Titles</div>
        </div>
        <div class="stat-card ok">
            <div class="v"><?= (int)$summary['copies_available'] ?></div>
            <div class="l">Available Copies</div>
        </div>
        <div class="stat-card">
            <div class="v"><?= (int)$summary['copies_borrowed'] ?></div>
            <div class="l">Borrowed Copies</div>
        </div>
        <div class="stat-card">
            <div class="v"><?= $summary['avg_loan_days'] !== null ? htmlspecialchars($summary['avg_loan_days']) . 'd' : '—' ?></div>
            <div class="l">Avg Loan Duration</div>
        </div>
    </div>
    <p style="color:var(--text-muted);font-size:.82rem;">
        Period: <strong><?= htmlspecialchars($date_from) ?></strong> → <strong><?= htmlspecialchars($date_to) ?></strong>
        · <?= $branch_id ? 'Branch filtered' : 'All branches' ?>
    </p>
    <?php endif; ?>
<?php endif; ?>

<?php /* ============================================================
       TAB: POPULAR
   ============================================================ */ ?>
<?php if ($tab === 'popular'): ?>
    <?php if (!$popular): ?>
        <div class="rep-card" style="text-align:center;padding:50px;">
            <i class="bi bi-fire" style="font-size:3rem;color:var(--text-muted);"></i>
            <p style="color:var(--text-muted);margin-top:12px;">No borrowings in this period.</p>
        </div>
    <?php else: ?>
        <div class="rep-card">
            <div class="rep-scroll">
                <table class="rep-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">Rank</th>
                            <th style="width:60px;"></th>
                            <th>Title</th>
                            <th>Authors</th>
                            <th>Publisher</th>
                            <th style="width:120px;">Borrows</th>
                            <th style="width:120px;">Still Out</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($popular as $i => $p): $r = $i + 1; ?>
                        <tr>
                            <td><span class="rank <?= $r===1?'top1':($r===2?'top2':($r===3?'top3':'')) ?>"><?= $r ?></span></td>
                            <td>
                                <?php if (!empty($p['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $p['cover_image'])): ?>
                                    <img src="uploads/covers/<?= htmlspecialchars($p['cover_image']) ?>" class="book-mini" alt="">
                                <?php else: ?>
                                    <div class="cover-ph"><i class="bi bi-book"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="book.php?id=<?= (int)$p['id'] ?>" style="color:var(--primary-dark);text-decoration:none;font-weight:600;">
                                    <?= htmlspecialchars($p['title']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($p['authors'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($p['publisher_name'] ?? '—') ?></td>
                            <td><span class="badge b-info"><?= (int)$p['borrow_count'] ?></span></td>
                            <td>
                                <?php if ((int)$p['still_out'] > 0): ?>
                                    <span class="badge b-warn"><?= (int)$p['still_out'] ?> out</span>
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

<?php /* ============================================================
       TAB: OVERDUE
   ============================================================ */ ?>
<?php if ($tab === 'overdue'): ?>
    <?php if (!$overdue): ?>
        <div class="rep-card" style="text-align:center;padding:50px;">
            <i class="bi bi-emoji-smile" style="font-size:3rem;color:#059669;"></i>
            <p style="color:var(--text-muted);margin-top:12px;">No overdue loans. 🎉</p>
        </div>
    <?php else: ?>
        <?php
            $total_est = 0;
            foreach ($overdue as $o) $total_est += (float)$o['est_fine'];
        ?>
        <div class="stat-grid">
            <div class="stat-card danger">
                <div class="v"><?= count($overdue) ?></div>
                <div class="l">Overdue Loans</div>
            </div>
            <div class="stat-card danger">
                <div class="v">€<?= number_format($total_est, 2) ?></div>
                <div class="l">Estimated Fines</div>
            </div>
        </div>
        <div class="rep-card">
            <div class="rep-scroll">
                <table class="rep-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Card #</th>
                            <th>Book</th>
                            <th>Barcode</th>
                            <th>Branch</th>
                            <th>Borrowed</th>
                            <th>Due</th>
                            <th>Days Late</th>
                            <th>Est. Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($overdue as $o): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars(trim($o['first_name'] . ' ' . $o['last_name'])) ?>
                                <br><small style="color:var(--text-muted);">@<?= htmlspecialchars($o['username']) ?></small>
                            </td>
                            <td><code style="font-size:.78rem;"><?= htmlspecialchars($o['card_number'] ?? '—') ?></code></td>
                            <td>
                                <a href="book.php?id=<?= (int)$o['book_id'] ?>" style="color:var(--primary-dark);text-decoration:none;font-weight:600;">
                                    <?= htmlspecialchars($o['title']) ?>
                                </a>
                            </td>
                            <td><code style="font-size:.78rem;"><?= htmlspecialchars($o['barcode']) ?></code></td>
                            <td><?= htmlspecialchars($o['branch_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars(substr($o['borrow_date'],0,10)) ?></td>
                            <td><?= htmlspecialchars($o['due_date']) ?></td>
                            <td><span class="badge b-danger"><?= (int)$o['days_overdue'] ?> d</span></td>
                            <td style="font-weight:600;">€<?= number_format((float)$o['est_fine'],2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php /* ============================================================
       TAB: BRANCHES
   ============================================================ */ ?>
<?php if ($tab === 'branches'): ?>
    <?php if (!$branches_rep): ?>
        <div class="rep-card" style="text-align:center;padding:50px;">
            <p style="color:var(--text-muted);">No branches.</p>
        </div>
    <?php else: ?>
        <div class="rep-card">
            <div class="rep-scroll">
                <table class="rep-table">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>City</th>
                            <th>Copies</th>
                            <th>Members</th>
                            <th>Check-outs (period)</th>
                            <th>Active Loans</th>
                            <th>Overdue</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($branches_rep as $b): ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($b['name']) ?></td>
                            <td><?= htmlspecialchars($b['city'] ?? '—') ?></td>
                            <td><?= (int)$b['copies'] ?></td>
                            <td><?= (int)$b['members'] ?></td>
                            <td><span class="badge b-info"><?= (int)$b['checkouts'] ?></span></td>
                            <td><?= (int)$b['active_loans'] ?></td>
                            <td>
                                <?php if ((int)$b['overdue'] > 0): ?>
                                    <span class="badge b-danger"><?= (int)$b['overdue'] ?></span>
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
        <p style="color:var(--text-muted);font-size:.82rem;">
            "Check-outs" reflects the period <strong><?= htmlspecialchars($date_from) ?></strong> → <strong><?= htmlspecialchars($date_to) ?></strong>
        </p>
    <?php endif; ?>
<?php endif; ?>

<?php /* ============================================================
       TAB: FINES
   ============================================================ */ ?>
<?php if ($tab === 'fines'): ?>
    <div class="stat-grid">
        <div class="stat-card ok">
            <div class="v">€<?= number_format((float)$fines_summary['collected'],2) ?></div>
            <div class="l">Collected</div>
        </div>
        <div class="stat-card warn">
            <div class="v">€<?= number_format((float)$fines_summary['waived'],2) ?></div>
            <div class="l">Waived</div>
        </div>
        <div class="stat-card danger">
            <div class="v">€<?= number_format((float)$fines_summary['unpaid'],2) ?></div>
            <div class="l">Unpaid</div>
        </div>
        <div class="stat-card">
            <div class="v"><?= (int)$fines_summary['count'] ?></div>
            <div class="l">Fines Issued</div>
        </div>
    </div>

    <?php if (!$fines_rep): ?>
        <div class="rep-card" style="text-align:center;padding:50px;">
            <p style="color:var(--text-muted);">No fines issued in this period.</p>
        </div>
    <?php else: ?>
        <div class="rep-card">
            <div class="rep-scroll">
                <table class="rep-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Issued</th>
                            <th>Issued Total</th>
                            <th>Collected</th>
                            <th>Waived</th>
                            <th>Unpaid</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($fines_rep as $f): ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($f['ym']) ?></td>
                            <td><?= (int)$f['issued_count'] ?></td>
                            <td>€<?= number_format((float)$f['issued_total'],2) ?></td>
                            <td style="color:#059669;font-weight:600;">€<?= number_format((float)$f['collected'],2) ?></td>
                            <td style="color:#d97706;font-weight:600;">€<?= number_format((float)$f['waived'],2) ?></td>
                            <td style="color:#dc2626;font-weight:600;">€<?= number_format((float)$f['unpaid'],2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php /* ============================================================
       TAB: MEMBERS
   ============================================================ */ ?>
<?php if ($tab === 'members'): ?>
    <?php if (!$top_members): ?>
        <div class="rep-card" style="text-align:center;padding:50px;">
            <p style="color:var(--text-muted);">No borrowing activity in this period.</p>
        </div>
    <?php else: ?>
        <div class="rep-card">
            <div class="rep-scroll">
                <table class="rep-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">Rank</th>
                            <th>Member</th>
                            <th>Card #</th>
                            <th>Borrows</th>
                            <th>Late Returns</th>
                            <th>Unpaid Fines</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($top_members as $i => $m): $r = $i + 1; ?>
                        <tr>
                            <td><span class="rank <?= $r===1?'top1':($r===2?'top2':($r===3?'top3':'')) ?>"><?= $r ?></span></td>
                            <td>
                                <?= htmlspecialchars(trim($m['first_name'] . ' ' . $m['last_name'])) ?>
                                <br><small style="color:var(--text-muted);">@<?= htmlspecialchars($m['username']) ?></small>
                            </td>
                            <td><code style="font-size:.78rem;"><?= htmlspecialchars($m['card_number'] ?? '—') ?></code></td>
                            <td><span class="badge b-info"><?= (int)$m['borrow_count'] ?></span></td>
                            <td>
                                <?php if ((int)$m['late_returns'] > 0): ?>
                                    <span class="badge b-warn"><?= (int)$m['late_returns'] ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((float)$m['unpaid_fines'] > 0): ?>
                                    <span class="badge b-danger">€<?= number_format((float)$m['unpaid_fines'],2) ?></span>
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
