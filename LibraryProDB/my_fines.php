<?php
// my_fines.php — Member: view own fines (owed + paid history)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('my_fines');
if (my_access('my_fines') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'My Fines';
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) { header('Location: login.php'); exit; }

// ---------- FILTERS ----------
$tab = $_GET['tab'] ?? 'unpaid';
if (!in_array($tab, ['unpaid','paid','all'], true)) $tab = 'unpaid';

$where = "WHERE f.user_id = ?";
$args  = [$user_id];

if ($tab === 'unpaid') {
    $where .= " AND f.paid = 0 AND f.waived = 0";
} elseif ($tab === 'paid') {
    $where .= " AND (f.paid = 1 OR f.waived = 1)";
}

// ---------- DATA ----------
$sql = "SELECT f.id, f.amount, f.reason, f.description, f.issued_date,
               f.paid, f.paid_date, f.waived, f.waived_by,
               br.id AS borrowing_id,
               b.title, b.cover_image, b.id AS book_id,
               c.barcode
        FROM fines f
        LEFT JOIN borrowings br ON br.id = f.borrowing_id
        LEFT JOIN copies c ON c.id = br.copy_id
        LEFT JOIN books b ON b.id = c.book_id
        $where
        ORDER BY f.issued_date DESC, f.id DESC
        LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$fines = $stmt->fetchAll();

// ---------- STATS ----------
$s = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN paid=0 AND waived=0 THEN amount ELSE 0 END), 0) AS unpaid,
        COUNT(CASE WHEN paid=0 AND waived=0 THEN 1 END) AS unpaid_count,
        COALESCE(SUM(CASE WHEN paid=1 THEN amount ELSE 0 END), 0) AS paid_total,
        COUNT(CASE WHEN paid=1 THEN 1 END) AS paid_count,
        COALESCE(SUM(CASE WHEN waived=1 THEN amount ELSE 0 END), 0) AS waived_total,
        COUNT(CASE WHEN waived=1 THEN 1 END) AS waived_count
     FROM fines WHERE user_id = ?"
);
$s->execute([$user_id]);
$stats = $s->fetch();

include 'header.php';
?>

<style>
    .fines-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:22px; }
    .fstat {
        background:var(--card-bg); border-radius:14px; padding:18px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        text-align:center;
    }
    .fstat .v { font-size:1.75rem; font-weight:700; line-height:1; color:var(--primary-dark); }
    .fstat .l { font-size:.82rem; color:var(--text-muted); margin-top:6px; }
    .fstat.danger .v { color:#dc2626; }
    .fstat.ok .v { color:#15803d; }

    .tabs { display:flex; gap:8px; margin-bottom:20px; border-bottom:2px solid var(--border); }
    .tabs a {
        padding:10px 16px; text-decoration:none; color:var(--text-muted);
        font-weight:600; font-size:.9rem; border-bottom:3px solid transparent;
        margin-bottom:-2px;
    }
    .tabs a.active { color:var(--primary-dark); border-bottom-color:var(--primary); }
    .tabs a:hover { color:var(--primary); }

    .fine-table-wrap {
        background:var(--card-bg); border-radius:var(--radius); overflow:auto;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
    }
    table { width:100%; border-collapse:collapse; font-size:.9rem; }
    thead th {
        position:sticky; top:0; background:var(--card-bg); z-index:2;
        text-align:left; padding:12px 14px; font-weight:600;
        border-bottom:2px solid var(--border); color:var(--text-muted);
        font-size:.8rem; text-transform:uppercase; letter-spacing:.03em;
    }
    tbody td { padding:12px 14px; border-bottom:1px solid rgba(0,0,0,0.05); vertical-align:middle; }
    tbody tr:hover { background:#f8fffe; }

    .badge { display:inline-block; padding:3px 9px; border-radius:8px; font-size:.75rem; font-weight:600; }
    .badge-unpaid { background:#fee2e2; color:#991b1b; }
    .badge-paid { background:#ccfbf1; color:#115e59; }
    .badge-waived { background:#e0e7ff; color:#3730a3; }
    .badge-overdue { background:#fef3c7; color:#92400e; }
    .badge-damage { background:#fed7aa; color:#9a3412; }
    .badge-lost { background:#fee2e2; color:#991b1b; }
    .badge-other { background:#e2e8f0; color:#475569; }

    .mini-cover { width:36px; height:52px; object-fit:cover; border-radius:4px; }
    .mini-ph {
        width:36px; height:52px; border-radius:4px;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        display:grid; place-items:center; color:#fff; font-size:1rem;
    }

    .notice {
        background:#fef3c7; border-left:4px solid #d97706; padding:14px 18px;
        border-radius:10px; margin-bottom:20px; font-size:.9rem; color:#78350f;
    }
</style>

<h2 style="margin-bottom:18px;"><i class="bi bi-cash-coin" style="color:var(--primary);"></i> My Fines</h2>

<?php if ((int)$stats['unpaid_count'] > 0): ?>
    <div class="notice">
        <i class="bi bi-exclamation-triangle-fill"></i>
        You have <strong><?= (int)$stats['unpaid_count'] ?></strong> unpaid fine(s) totalling
        <strong>€<?= number_format((float)$stats['unpaid'], 2) ?></strong>.
        Please visit the library desk to pay.
        <?php if ((float)$stats['unpaid'] > FINE_BLOCK_THRESHOLD): ?>
            <br><strong>Borrowing is blocked until fines are below €<?= number_format(FINE_BLOCK_THRESHOLD,2) ?>.</strong>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- STATS -->
<div class="fines-stats">
    <div class="fstat <?= (float)$stats['unpaid'] > 0 ? 'danger' : '' ?>">
        <div class="v">€<?= number_format((float)$stats['unpaid'], 2) ?></div>
        <div class="l">Unpaid (<?= (int)$stats['unpaid_count'] ?>)</div>
    </div>
    <div class="fstat ok">
        <div class="v">€<?= number_format((float)$stats['paid_total'], 2) ?></div>
        <div class="l">Paid (<?= (int)$stats['paid_count'] ?>)</div>
    </div>
    <div class="fstat">
        <div class="v">€<?= number_format((float)$stats['waived_total'], 2) ?></div>
        <div class="l">Waived (<?= (int)$stats['waived_count'] ?>)</div>
    </div>
</div>

<!-- TABS -->
<div class="tabs">
    <a href="my_fines.php?tab=unpaid" class="<?= $tab==='unpaid'?'active':'' ?>">
        <i class="bi bi-exclamation-circle"></i> Unpaid (<?= (int)$stats['unpaid_count'] ?>)
    </a>
    <a href="my_fines.php?tab=paid" class="<?= $tab==='paid'?'active':'' ?>">
        <i class="bi bi-check-circle"></i> Paid / Waived (<?= (int)$stats['paid_count'] + (int)$stats['waived_count'] ?>)
    </a>
    <a href="my_fines.php?tab=all" class="<?= $tab==='all'?'active':'' ?>">
        <i class="bi bi-list"></i> All
    </a>
</div>

<!-- TABLE -->
<?php if (!$fines): ?>
    <div class="fine-table-wrap" style="text-align:center;padding:50px;">
        <i class="bi bi-emoji-smile" style="font-size:3rem;color:var(--primary);"></i>
        <p style="color:var(--text-muted);margin-top:12px;">
            <?php if ($tab === 'unpaid'): ?>
                No unpaid fines. You're all clear! 🎉
            <?php elseif ($tab === 'paid'): ?>
                No paid fines yet.
            <?php else: ?>
                No fines on record.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <div class="fine-table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:60px;"></th>
                    <th>Reason</th>
                    <th>Description</th>
                    <th>Book</th>
                    <th>Issued</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($fines as $f):
                $reason_class = 'badge-' . ($f['reason'] === 'overdue' ? 'overdue'
                                          : ($f['reason'] === 'damage' ? 'damage'
                                          : ($f['reason'] === 'lost' ? 'lost' : 'other')));
                if ($f['waived']) {
                    $status_badge = '<span class="badge badge-waived">waived</span>';
                } elseif ($f['paid']) {
                    $status_badge = '<span class="badge badge-paid">paid ' . htmlspecialchars($f['paid_date'] ?? '') . '</span>';
                } else {
                    $status_badge = '<span class="badge badge-unpaid">unpaid</span>';
                }
            ?>
                <tr>
                    <td>
                        <?php if (!empty($f['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $f['cover_image'])): ?>
                            <img src="uploads/covers/<?= htmlspecialchars($f['cover_image']) ?>" class="mini-cover" alt="">
                        <?php elseif (!empty($f['book_id'])): ?>
                            <div class="mini-ph"><i class="bi bi-book"></i></div>
                        <?php else: ?>
                            <div class="mini-ph" style="background:#e2e8f0;color:#94a3b8;"><i class="bi bi-info"></i></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $reason_class ?>"><?= htmlspecialchars($f['reason']) ?></span></td>
                    <td style="color:var(--text-muted);font-size:.85rem;"><?= htmlspecialchars($f['description'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($f['book_id'])): ?>
                            <a href="book.php?id=<?= (int)$f['book_id'] ?>" style="color:var(--primary-dark);text-decoration:none;font-weight:500;">
                                <?= htmlspecialchars($f['title']) ?>
                            </a>
                            <?php if (!empty($f['barcode'])): ?>
                                <br><small style="color:var(--text-muted);"><code><?= htmlspecialchars($f['barcode']) ?></code></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($f['issued_date']) ?></td>
                    <td style="font-weight:600;">€<?= number_format((float)$f['amount'], 2) ?></td>
                    <td><?= $status_badge ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="color:var(--text-muted);font-size:.82rem;margin-top:10px;">
        Showing <?= count($fines) ?> fine(s).
    </p>
<?php endif; ?>

<?php include 'footer.php'; ?>
