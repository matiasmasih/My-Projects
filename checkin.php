<?php
// checkin.php — Librarian: return a borrowed copy, compute overdue fine
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('checkin');
if (my_access('checkin') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'Check-In';
$error = '';
$success = '';

// ---------- HANDLE RETURN ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $borrowing_id = (int)($_POST['borrowing_id'] ?? 0);
    $notes        = trim($_POST['notes'] ?? '');

    if ($borrowing_id <= 0) {
        $error = 'Please select a loan to return.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Load the borrowing (lock row)
            $b = $pdo->prepare(
                "SELECT br.id, br.user_id, br.copy_id, br.due_date, br.status, br.return_date,
                        c.barcode, c.id AS copy_id, b.title, u.username
                 FROM borrowings br
                 JOIN copies c ON c.id = br.copy_id
                 JOIN books  b ON b.id = c.book_id
                 JOIN users  u ON u.id = br.user_id
                 WHERE br.id = ? FOR UPDATE"
            );
            $b->execute([$borrowing_id]);
            $loan = $b->fetch();

            if (!$loan) {
                throw new Exception('Loan not found.');
            }
            if ($loan['status'] !== 'borrowed' && $loan['status'] !== 'overdue') {
                throw new Exception('This loan is already ' . $loan['status'] . '.');
            }

            $today = date('Y-m-d');
            $today_dt = date('Y-m-d H:i:s');

            // 2. Compute overdue days + fine
            $fine_amount = 0.00;
            $days_late = 0;
            if (strtotime($today) > strtotime($loan['due_date'])) {
                $days_late = (int)floor((strtotime($today) - strtotime($loan['due_date'])) / 86400);
                $fine_amount = min($days_late * FINE_PER_DAY, MAX_FINE_PER_BOOK);
            }

            // 3. Mark borrowing returned
            $upd = $pdo->prepare(
                "UPDATE borrowings
                 SET status = 'returned', return_date = ?, checked_in_by = ?,
                     notes = CONCAT(COALESCE(notes,''), ?)
                 WHERE id = ?"
            );
            $note_append = $notes !== '' ? "\n[checkin] " . $notes : '';
            $upd->execute([$today_dt, $_SESSION['user_id'] ?? null, $note_append, $borrowing_id]);

            // 4. Flip copy back to available
            $pdo->prepare("UPDATE copies SET status = 'available' WHERE id = ?")
                ->execute([$loan['copy_id']]);

            // 5. Create fine if overdue
            if ($fine_amount > 0) {
                $f = $pdo->prepare(
                    "INSERT INTO fines (user_id, borrowing_id, amount, reason, description, issued_date, paid)
                     VALUES (?, ?, ?, 'overdue', ?, CURDATE(), 0)"
                );
                $f->execute([
                    $loan['user_id'],
                    $borrowing_id,
                    $fine_amount,
                    "$days_late day(s) late on \"{$loan['title']}\" ({$loan['barcode']})"
                ]);
            }

            // 6. Audit log
            $desc = "Returned copy {$loan['barcode']} (\"{$loan['title']}\") from {$loan['username']}";
            if ($fine_amount > 0) {
                $desc .= " — €" . number_format($fine_amount, 2) . " fine issued ({$days_late} days late)";
            }
            $log = $pdo->prepare(
                "INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                 VALUES (?, 'checkin', ?, 'borrowing', ?)"
            );
            $log->execute([$_SESSION['user_id'] ?? null, $desc, $borrowing_id]);

            $pdo->commit();

            $success = "Returned \"{$loan['title']}\" ({$loan['barcode']}) from {$loan['username']}.";
            if ($fine_amount > 0) {
                $success .= " Overdue fine: €" . number_format($fine_amount, 2) . " ({$days_late} days late).";
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// ---------- LOAD ACTIVE LOANS ----------
$loans = $pdo->query(
    "SELECT br.id, br.borrow_date, br.due_date, br.status,
            u.username, u.first_name, u.last_name, u.card_number,
            b.title, c.barcode,
            DATEDIFF(CURDATE(), br.due_date) AS days_overdue
     FROM borrowings br
     JOIN users u ON u.id = br.user_id
     JOIN copies c ON c.id = br.copy_id
     JOIN books b ON b.id = c.book_id
     WHERE br.status IN ('borrowed','overdue')
     ORDER BY br.due_date ASC"
)->fetchAll();

include 'header.php';
?>

<h2 style="margin-bottom:20px;"><i class="bi bi-box-arrow-in-left" style="color:var(--primary);"></i> Check-In a Book</h2>

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

<div class="table-card">
    <h5 style="margin-bottom:16px;"><i class="bi bi-arrow-return-left"></i> Active Loans (<?= count($loans) ?>)</h5>

    <?php if (!$loans): ?>
        <p style="color:var(--text-muted);">No active loans. Nothing to check in.</p>
    <?php else: ?>
    <div style="overflow:auto;max-height:70vh;">
        <table>
            <thead>
                <tr style="position:sticky;top:0;background:var(--card-bg);z-index:2;">
                    <th>Member</th>
                    <th>Book</th>
                    <th>Barcode</th>
                    <th>Borrowed</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($loans as $l): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars(trim($l['first_name'].' '.$l['last_name'])) ?>
                        <br><small style="color:var(--text-muted);"><?= htmlspecialchars($l['username']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($l['title']) ?></td>
                    <td><code><?= htmlspecialchars($l['barcode']) ?></code></td>
                    <td><?= htmlspecialchars(substr($l['borrow_date'],0,10)) ?></td>
                    <td>
                        <?= htmlspecialchars($l['due_date']) ?>
                        <?php if ((int)$l['days_overdue'] > 0): ?>
                            <br><small style="color:#dc2626;font-weight:600;">
                                <?= (int)$l['days_overdue'] ?> day(s) overdue
                                — est. fine €<?= number_format(min((int)$l['days_overdue'] * FINE_PER_DAY, MAX_FINE_PER_BOOK), 2) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$l['days_overdue'] > 0): ?>
                            <span class="status-badge status-danger">overdue</span>
                        <?php else: ?>
                            <span class="status-badge status-pending">borrowed</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" action="checkin.php" style="display:inline;"
                              onsubmit="return confirm('Return this copy?');">
                            <input type="hidden" name="borrowing_id" value="<?= (int)$l['id'] ?>">
                            <button type="submit" class="btn-add" style="padding:6px 12px;font-size:.85rem;">
                                <i class="bi bi-arrow-return-left"></i> Return
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
