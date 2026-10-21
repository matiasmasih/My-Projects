<?php
// librarian_dashboard.php — Staff home: lending desk overview
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('librarian_dashboard');
if (my_access('librarian_dashboard') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'Librarian Dashboard';

// ---------- STATS ----------
$stats = [
    'active_loans'    => (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='borrowed'")->fetchColumn(),
    'overdue'         => (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='borrowed' AND due_date < CURDATE()")->fetchColumn(),
    'due_today'       => (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='borrowed' AND due_date = CURDATE()")->fetchColumn(),
    'due_soon'        => (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE status='borrowed' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)")->fetchColumn(),
    'available_copies'=> (int)$pdo->query("SELECT COUNT(*) FROM copies WHERE status='available'")->fetchColumn(),
    'borrowed_copies' => (int)$pdo->query("SELECT COUNT(*) FROM copies WHERE status='borrowed'")->fetchColumn(),
    'pending_holds'   => (int)$pdo->query("SELECT COUNT(*) FROM holds WHERE status='pending'")->fetchColumn(),
    'ready_holds'     => (int)$pdo->query("SELECT COUNT(*) FROM holds WHERE status='ready'")->fetchColumn(),
    'unpaid_fines'    => (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM fines WHERE paid=0 AND waived=0")->fetchColumn(),
];

// ---------- LOANS DUE SOON / OVERDUE (up to 8) ----------
$due = $pdo->query(
    "SELECT br.id, br.due_date, br.status,
            u.username, u.first_name, u.last_name, u.card_number,
            b.title, c.barcode,
            DATEDIFF(CURDATE(), br.due_date) AS days_overdue
     FROM borrowings br
     JOIN users u ON u.id = br.user_id
     JOIN copies c ON c.id = br.copy_id
     JOIN books b ON b.id = c.book_id
     WHERE br.status='borrowed' AND br.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
     ORDER BY br.due_date ASC
     LIMIT 8"
)->fetchAll();

// ---------- PENDING HOLDS (up to 5) ----------
$holds = $pdo->query(
    "SELECT h.id, h.placed_date, h.status, h.queue_position,
            u.username, u.first_name, u.last_name,
            b.title, b.id AS book_id
     FROM holds h
     JOIN users u ON u.id = h.user_id
     JOIN books b ON b.id = h.book_id
     WHERE h.status IN ('pending','ready')
     ORDER BY h.status DESC, h.placed_date ASC
     LIMIT 5"
)->fetchAll();

// ---------- RECENT ACTIVITY (up to 8) ----------
$recent = $pdo->query(
    "SELECT a.action, a.description, a.created_at, u.username
     FROM activity_log a
     LEFT JOIN users u ON u.id = a.user_id
     ORDER BY a.id DESC
     LIMIT 8"
)->fetchAll();

include 'header.php';
?>

<style>
    .dash-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px; margin-bottom:24px; }
    .stat-card {
        background:var(--card-bg); border-radius:var(--radius); padding:18px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        display:flex; align-items:center; gap:14px; transition:transform .15s ease;
    }
    .stat-card:hover { transform:translateY(-2px); }
    .stat-icon {
        width:48px; height:48px; border-radius:14px; display:grid; place-items:center;
        font-size:1.4rem; color:#fff; flex-shrink:0;
    }
    .stat-icon.teal  { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); }
    .stat-icon.blue  { background:linear-gradient(135deg,var(--accent),var(--accent-dark)); }
    .stat-icon.amber { background:linear-gradient(135deg,#f59e0b,#d97706); }
    .stat-icon.red   { background:linear-gradient(135deg,#ef4444,#b91c1c); }
    .stat-icon.green { background:linear-gradient(135deg,#22c55e,#15803d); }
    .stat-value { font-size:1.6rem; font-weight:700; line-height:1; color:var(--text); }
    .stat-label { font-size:.82rem; color:var(--text-muted); margin-top:4px; }

    .panel {
        background:var(--card-bg); border-radius:var(--radius); padding:20px;
        box-shadow:var(--shadow); margin-bottom:22px;
        border:1px solid rgba(13,148,136,0.08);
    }
    .panel h3 { font-size:1.05rem; margin:0 0 16px; display:flex; align-items:center; gap:8px; color:var(--text); }
    .panel h3 i { color:var(--primary); }

    .two-col { display:grid; grid-template-columns:1fr 1fr; gap:22px; }
    @media (max-width:820px){ .two-col { grid-template-columns:1fr; } }

    .quick-actions { display:flex; flex-wrap:wrap; gap:10px; }
    .quick-actions a {
        text-decoration:none; padding:12px 18px; border-radius:12px;
        background:var(--card-bg); border:1px solid var(--border);
        color:var(--text); font-weight:600; font-size:.9rem;
        display:inline-flex; align-items:center; gap:8px;
        transition:all .15s ease;
    }
    .quick-actions a:hover {
        background:var(--primary); color:#fff; border-color:var(--primary);
        transform:translateY(-2px); box-shadow:0 6px 16px rgba(13,148,136,0.25);
    }
    .quick-actions a.primary {
        background:linear-gradient(135deg,var(--primary),var(--primary-dark));
        color:#fff; border:none;
    }

    table { width:100%; border-collapse:collapse; font-size:.88rem; }
    thead th {
        text-align:left; padding:10px; font-weight:600; font-size:.78rem;
        color:var(--text-muted); text-transform:uppercase; letter-spacing:.03em;
        border-bottom:2px solid var(--border);
    }
    tbody td { padding:9px 10px; border-bottom:1px solid rgba(0,0,0,0.05); }
    tbody tr:last-child td { border-bottom:none; }

    .badge { display:inline-block; padding:3px 9px; border-radius:8px; font-size:.72rem; font-weight:600; }
    .badge-danger { background:#fee2e2; color:#991b1b; }
    .badge-warn { background:#fef3c7; color:#92400e; }
    .badge-info { background:#dbeafe; color:#1e40af; }
    .badge-ok { background:#ccfbf1; color:#115e59; }
</style>

<h2 style="margin-bottom:20px;"><i class="bi bi-person-workspace" style="color:var(--primary);"></i> Librarian Dashboard</h2>

<!-- ===== STAT CARDS ===== -->
<div class="dash-grid">
    <div class="stat-card">
        <div class="stat-icon teal"><i class="bi bi-arrow-left-right"></i></div>
        <div><div class="stat-value"><?= $stats['active_loans'] ?></div><div class="stat-label">Active Loans</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="bi bi-exclamation-triangle"></i></div>
        <div><div class="stat-value"><?= $stats['overdue'] ?></div><div class="stat-label">Overdue</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="bi bi-calendar-day"></i></div>
        <div><div class="stat-value"><?= $stats['due_today'] ?></div><div class="stat-label">Due Today</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="bi bi-clock"></i></div>
        <div><div class="stat-value"><?= $stats['due_soon'] ?></div><div class="stat-label">Due in 3 days</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
        <div><div class="stat-value"><?= $stats['available_copies'] ?></div><div class="stat-label">Available Copies</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-box-arrow-right"></i></div>
        <div><div class="stat-value"><?= $stats['borrowed_copies'] ?></div><div class="stat-label">Borrowed Copies</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="bi bi-bookmark-star"></i></div>
        <div><div class="stat-value"><?= $stats['pending_holds'] ?></div><div class="stat-label">Pending Holds</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-bell"></i></div>
        <div><div class="stat-value"><?= $stats['ready_holds'] ?></div><div class="stat-label">Ready Holds</div></div>
    </div>
</div>

<!-- ===== QUICK ACTIONS ===== -->
<div class="panel">
    <h3><i class="bi bi-lightning-charge-fill"></i> Lending Desk Actions</h3>
    <div class="quick-actions">
        <a href="checkout.php" class="primary"><i class="bi bi-box-arrow-right"></i> Check Out</a>
        <a href="checkin.php" class="primary"><i class="bi bi-box-arrow-in-left"></i> Check In</a>
        <a href="manage_copies.php"><i class="bi bi-collection"></i> Manage Copies</a>
        <a href="manage_copies.php?action=add"><i class="bi bi-plus-circle"></i> Add Copy</a>
        <a href="catalog.php"><i class="bi bi-search"></i> Browse Catalog</a>
    </div>
</div>

<!-- ===== DUE SOON / OVERDUE ===== -->
<div class="panel">
    <h3><i class="bi bi-alarm"></i> Attention Needed — Due Soon &amp; Overdue</h3>
    <?php if (!$due): ?>
        <p style="color:var(--text-muted);font-size:.9rem;">Nothing due in the next 3 days. 🎉</p>
    <?php else: ?>
        <div style="overflow:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Book</th>
                        <th>Barcode</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($due as $d):
                    $overdue = (int)$d['days_overdue'] > 0;
                    $is_today = $d['due_date'] === date('Y-m-d');
                ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars(trim($d['first_name'].' '.$d['last_name'])) ?>
                            <br><small style="color:var(--text-muted);">@<?= htmlspecialchars($d['username']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($d['title']) ?></td>
                        <td><code><?= htmlspecialchars($d['barcode']) ?></code></td>
                        <td><?= htmlspecialchars($d['due_date']) ?></td>
                        <td>
                            <?php if ($overdue): ?>
                                <span class="badge badge-danger"><?= (int)$d['days_overdue'] ?>d overdue</span>
                            <?php elseif ($is_today): ?>
                                <span class="badge badge-warn">Due today</span>
                            <?php else: ?>
                                <span class="badge badge-info">Due soon</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="checkin.php" class="btn-add" style="padding:5px 10px;font-size:.78rem;text-decoration:none;">
                                <i class="bi bi-arrow-return-left"></i> Return
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ===== TWO COLUMNS ===== -->
<div class="two-col">

    <!-- Holds -->
    <div class="panel">
        <h3><i class="bi bi-bookmark-star"></i> Pending &amp; Ready Holds</h3>
        <?php if (!$holds): ?>
            <p style="color:var(--text-muted);font-size:.9rem;">No active holds.</p>
        <?php else: ?>
            <?php foreach ($holds as $h): ?>
                <div style="padding:10px 0;border-bottom:1px solid rgba(0,0,0,0.05);">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                        <div style="font-size:.9rem;">
                            <strong><?= htmlspecialchars($h['title']) ?></strong><br>
                            <small style="color:var(--text-muted);">
                                <?= htmlspecialchars(trim($h['first_name'].' '.$h['last_name'])) ?>
                                · @<?= htmlspecialchars($h['username']) ?>
                                · placed <?= htmlspecialchars(substr($h['placed_date'],0,10)) ?>
                            </small>
                        </div>
                        <span class="badge <?= $h['status']==='ready'?'badge-ok':'badge-warn' ?>">
                            <?= htmlspecialchars($h['status']) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Recent activity -->
    <div class="panel">
        <h3><i class="bi bi-activity"></i> Recent Activity</h3>
        <?php if (!$recent): ?>
            <p style="color:var(--text-muted);font-size:.9rem;">No activity yet.</p>
        <?php else: ?>
            <?php foreach ($recent as $r): ?>
                <div style="padding:9px 0;border-bottom:1px solid rgba(0,0,0,0.05);font-size:.86rem;">
                    <div><span style="font-weight:600;color:var(--primary-dark);"><?= htmlspecialchars($r['username'] ?? 'system') ?></span> — <?= htmlspecialchars($r['description'] ?? $r['action']) ?></div>
                    <div style="color:var(--text-muted);font-size:.75rem;"><?= htmlspecialchars($r['created_at']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php include 'footer.php'; ?>
