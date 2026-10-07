<?php
// admin_dashboard.php — Admin overview with stats and quick actions
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('admin_dashboard');

$page_title = 'Admin Dashboard';

// ---------- STATS ----------
$stats = [
    'books'      => (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'copies'     => (int)$pdo->query("SELECT COUNT(*) FROM copies")->fetchColumn(),
    'members'    => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member'")->fetchColumn(),
    'staff'      => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','librarian')")->fetchColumn(),
    'branches'   => (int)$pdo->query("SELECT COUNT(*) FROM branches WHERE is_active=1")->fetchColumn(),
    'active_loans' => (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='borrowed'")->fetchColumn(),
    'overdue'    => (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='borrowed' AND due_date < CURDATE()")->fetchColumn(),
    'holds'      => (int)$pdo->query("SELECT COUNT(*) FROM holds WHERE status IN ('pending','ready')")->fetchColumn(),
];

// Copies available vs borrowed
$copies_available = (int)$pdo->query("SELECT COUNT(*) FROM copies WHERE status='available'")->fetchColumn();
$copies_borrowed  = (int)$pdo->query("SELECT COUNT(*) FROM copies WHERE status='borrowed'")->fetchColumn();

// ---------- RECENT ACTIVITY ----------
$recent = $pdo->query(
    "SELECT a.action, a.description, a.created_at, u.username
     FROM activity_log a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.id DESC
     LIMIT 8"
)->fetchAll();

// ---------- RECENT BORROWINGS ----------
$recent_loans = $pdo->query(
    "SELECT br.id, br.borrow_date, br.due_date, br.status,
            u.username, b.title, c.barcode
     FROM borrowings br
     JOIN users u ON u.id = br.user_id
     JOIN copies c ON c.id = br.copy_id
     JOIN books b ON b.id = c.book_id
     ORDER BY br.id DESC
     LIMIT 5"
)->fetchAll();

// ---------- LOW STOCK (books with no available copies) ----------
$no_copies = $pdo->query(
    "SELECT b.id, b.title,
            (SELECT COUNT(*) FROM copies c WHERE c.book_id=b.id) AS total_copies,
            (SELECT COUNT(*) FROM copies c WHERE c.book_id=b.id AND c.status='available') AS avail
     FROM books b
     HAVING avail = 0
     ORDER BY b.title
     LIMIT 5"
)->fetchAll();

include 'header.php';
?>

<style>
    .dash-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:24px; }
    .stat-card {
        background: var(--card-bg);
        border-radius: var(--radius);
        padding: 20px;
        box-shadow: var(--shadow);
        display:flex; align-items:center; gap:16px;
        border:1px solid rgba(13,148,136,0.08);
        transition: transform .15s ease;
    }
    .stat-card:hover { transform: translateY(-2px); }
    .stat-icon {
        width:52px; height:52px; border-radius:14px;
        display:grid; place-items:center;
        font-size:1.5rem; color:#fff; flex-shrink:0;
    }
    .stat-icon.teal  { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); }
    .stat-icon.blue  { background:linear-gradient(135deg,var(--accent),var(--accent-dark)); }
    .stat-icon.amber { background:linear-gradient(135deg,#f59e0b,#d97706); }
    .stat-icon.red   { background:linear-gradient(135deg,#ef4444,#b91c1c); }
    .stat-value { font-size:1.75rem; font-weight:700; line-height:1; color:var(--text); }
    .stat-label { font-size:.85rem; color:var(--text-muted); margin-top:4px; }

    .panel {
        background:var(--card-bg); border-radius:var(--radius);
        padding:20px; box-shadow:var(--shadow); margin-bottom:24px;
        border:1px solid rgba(13,148,136,0.08);
    }
    .panel h3 { font-size:1.05rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; color:var(--text); }
    .panel h3 i { color:var(--primary); }

    .two-col { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
    @media (max-width:768px){ .two-col { grid-template-columns:1fr; } }

    .activity-item { padding:10px 0; border-bottom:1px solid rgba(0,0,0,0.05); font-size:.9rem; }
    .activity-item:last-child { border-bottom:none; }
    .activity-item .who { font-weight:600; color:var(--primary-dark); }
    .activity-item .when { color:var(--text-muted); font-size:.78rem; }

    .quick-actions { display:flex; flex-wrap:wrap; gap:10px; }
    .quick-actions a {
        text-decoration:none; padding:10px 16px; border-radius:12px;
        background:var(--card-bg); border:1px solid var(--border);
        color:var(--text); font-weight:500; font-size:.9rem;
        display:inline-flex; align-items:center; gap:8px;
        transition: all .15s ease;
    }
    .quick-actions a:hover {
        background:var(--primary); color:#fff; border-color:var(--primary);
        transform: translateY(-1px);
    }

    .progress-row { margin-bottom:14px; }
    .progress-label { display:flex; justify-content:space-between; font-size:.85rem; margin-bottom:6px; }
    .progress-bar-bg { height:10px; background:#e2e8f0; border-radius:6px; overflow:hidden; }
    .progress-bar-fill { height:100%; background:linear-gradient(90deg,var(--primary),var(--accent)); }
</style>

<h2 style="margin-bottom:20px;"><i class="bi bi-speedometer2" style="color:var(--primary);"></i> Admin Dashboard</h2>

<!-- ===== STAT CARDS ===== -->
<div class="dash-grid">
    <div class="stat-card">
        <div class="stat-icon teal"><i class="bi bi-book"></i></div>
        <div><div class="stat-value"><?= $stats['books'] ?></div><div class="stat-label">Book Titles</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-collection"></i></div>
        <div><div class="stat-value"><?= $stats['copies'] ?></div><div class="stat-label">Physical Copies</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><i class="bi bi-people"></i></div>
        <div><div class="stat-value"><?= $stats['members'] ?></div><div class="stat-label">Members</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-person-badge"></i></div>
        <div><div class="stat-value"><?= $stats['staff'] ?></div><div class="stat-label">Staff</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><i class="bi bi-building"></i></div>
        <div><div class="stat-value"><?= $stats['branches'] ?></div><div class="stat-label">Branches</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-arrow-left-right"></i></div>
        <div><div class="stat-value"><?= $stats['active_loans'] ?></div><div class="stat-label">Active Loans</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="bi bi-clock-history"></i></div>
        <div><div class="stat-value"><?= $stats['overdue'] ?></div><div class="stat-label">Overdue</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="bi bi-bookmark-star"></i></div>
        <div><div class="stat-value"><?= $stats['holds'] ?></div><div class="stat-label">Active Holds</div></div>
    </div>
</div>

<!-- ===== QUICK ACTIONS ===== -->
<div class="panel">
    <h3><i class="bi bi-lightning-charge-fill"></i> Quick Actions</h3>
    <div class="quick-actions">
        <a href="manage_copies.php?action=add"><i class="bi bi-plus-circle"></i> Add Copy</a>
        <a href="manage_copies.php"><i class="bi bi-collection"></i> Manage Copies</a>
        <a href="catalog.php"><i class="bi bi-search"></i> Browse Catalog</a>
        <a href="register.php"><i class="bi bi-person-plus"></i> Register User</a>
    </div>
</div>

<!-- ===== COPY STATUS ===== -->
<div class="panel">
    <h3><i class="bi bi-bar-chart-fill"></i> Copy Availability</h3>
    <?php
      $real_total = $copies_available + $copies_borrowed;
         if ($real_total > 0) {
         $pct_a = round($copies_available / $real_total * 100);
         $pct_b = round($copies_borrowed / $real_total * 100);
          } else {
         $pct_a = 0;
         $pct_b = 0;
      }
    ?>
    <div class="progress-row">
        <div class="progress-label"><span><i class="bi bi-check-circle" style="color:#0d9488;"></i> Available</span><span><?= $copies_available ?></span></div>
        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:<?= $pct_a ?>%;"></div></div>
    </div>
    <div class="progress-row">
        <div class="progress-label"><span><i class="bi bi-box-arrow-right" style="color:#657aa8;"></i> Borrowed</span><span><?= $copies_borrowed ?></span></div>
        <div class="progress-bar-bg"><div class="progress-bar-fill" style="width:<?= $pct_b ?>%;background:linear-gradient(90deg,#657aa8,#4f6088);"></div></div>
    </div>
</div>

<!-- ===== TWO COLUMNS ===== -->
<div class="two-col">

    <!-- Recent Activity -->
    <div class="panel">
        <h3><i class="bi bi-activity"></i> Recent Activity</h3>
        <?php if (!$recent): ?>
            <p style="color:var(--text-muted);font-size:.9rem;">No activity yet.</p>
        <?php else: ?>
            <?php foreach ($recent as $r): ?>
                <div class="activity-item">
                    <div><span class="who"><?= htmlspecialchars($r['username'] ?? 'system') ?></span> — <?= htmlspecialchars($r['description'] ?? $r['action']) ?></div>
                    <div class="when"><?= htmlspecialchars($r['created_at']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Books with no available copies -->
    <div class="panel">
        <h3><i class="bi bi-exclamation-triangle-fill"></i> Titles With No Available Copies</h3>
        <?php if (!$no_copies): ?>
            <p style="color:var(--text-muted);font-size:.9rem;">All titles have at least one copy available. 🎉</p>
        <?php else: ?>
            <?php foreach ($no_copies as $b): ?>
                <div class="activity-item">
                    <div><a href="book.php?id=<?= (int)$b['id'] ?>" style="color:var(--primary-dark);text-decoration:none;font-weight:600;"><?= htmlspecialchars($b['title']) ?></a></div>
                    <div class="when"><?= (int)$b['total_copies'] ?> total copies</div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- ===== RECENT LOANS ===== -->
<div class="panel">
    <h3><i class="bi bi-journal-bookmark-fill"></i> Recent Borrowings</h3>
    <?php if (!$recent_loans): ?>
        <p style="color:var(--text-muted);font-size:.9rem;">No borrowings yet.</p>
    <?php else: ?>
        <div style="overflow:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                <thead>
                    <tr style="text-align:left;border-bottom:2px solid var(--border);">
                        <th style="padding:8px;">User</th>
                        <th style="padding:8px;">Book</th>
                        <th style="padding:8px;">Barcode</th>
                        <th style="padding:8px;">Borrowed</th>
                        <th style="padding:8px;">Due</th>
                        <th style="padding:8px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_loans as $l): ?>
                        <tr style="border-bottom:1px solid rgba(0,0,0,0.05);">
                            <td style="padding:8px;"><?= htmlspecialchars($l['username']) ?></td>
                            <td style="padding:8px;"><?= htmlspecialchars($l['title']) ?></td>
                            <td style="padding:8px;"><code><?= htmlspecialchars($l['barcode']) ?></code></td>
                            <td style="padding:8px;"><?= htmlspecialchars($l['borrow_date']) ?></td>
                            <td style="padding:8px;"><?= htmlspecialchars($l['due_date']) ?></td>
                            <td style="padding:8px;">
                                <span class="status-badge status-<?= $l['status']==='borrowed'?'pending':($l['status']==='returned'?'active':'cancelled') ?>">
                                    <?= htmlspecialchars($l['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
