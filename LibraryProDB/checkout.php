<?php
// checkout.php — Librarian: check out an available copy to a member
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('checkout');
if (my_access('checkout') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'Checkout';
$error = '';
$success = '';

// ---------- HANDLE SUBMIT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $member_id = (int)($_POST['member_id'] ?? 0);
    $copy_id   = (int)($_POST['copy_id']   ?? 0);
    $notes     = trim($_POST['notes'] ?? '');

    if ($member_id <= 0) {
        $error = 'Please select a member.';
    } elseif ($copy_id <= 0) {
        $error = 'Please select a copy.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Validate member
            $m = $pdo->prepare("SELECT id, username, first_name, last_name, role, status FROM users WHERE id = ? FOR UPDATE");
            $m->execute([$member_id]);
            $member = $m->fetch();

            if (!$member) {
                throw new Exception('Member not found.');
            }
            if ($member['role'] !== 'member') {
                throw new Exception('Selected user is not a member (they are ' . $member['role'] . ').');
            }
            if ($member['status'] !== 'active') {
                throw new Exception('Member account is ' . $member['status'] . ' — cannot borrow.');
            }

            // 2. Borrow-limit check
            $c = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status = 'borrowed'");
            $c->execute([$member_id]);
            $active = (int)$c->fetchColumn();
            if ($active >= MAX_LOANS_PER_MEMBER) {
                throw new Exception("Member already has $active active loans (limit " . MAX_LOANS_PER_MEMBER . ").");
            }

            // 3. Unpaid fines check
            $f = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fines WHERE user_id = ? AND paid = 0 AND waived = 0");
            $f->execute([$member_id]);
            $owed = (float)$f->fetchColumn();
            if ($owed > FINE_BLOCK_THRESHOLD) {
                throw new Exception('Member owes €' . number_format($owed, 2) . ' in unpaid fines (limit €' . number_format(FINE_BLOCK_THRESHOLD, 2) . ').');
            }

            // 4. Validate copy
            $cp = $pdo->prepare(
                "SELECT c.id, c.barcode, c.status, c.book_id, c.shelf_id,
                        b.title,
                        s.branch_id AS shelf_branch_id
                 FROM copies c
                 JOIN books b ON b.id = c.book_id
                 LEFT JOIN shelves s ON s.id = c.shelf_id
                 WHERE c.id = ? FOR UPDATE"
            );
            $cp->execute([$copy_id]);
            $copy = $cp->fetch();

            if (!$copy) {
                throw new Exception('Copy not found.');
            }
            if ($copy['status'] !== 'available') {
                throw new Exception('Copy ' . $copy['barcode'] . ' is not available (status: ' . $copy['status'] . ').');
            }

            // 5. Same member can't have same copy already out
            $dup = $pdo->prepare("SELECT id FROM borrowings WHERE user_id = ? AND copy_id = ? AND status = 'borrowed'");
            $dup->execute([$member_id, $copy_id]);
            if ($dup->fetchColumn()) {
                throw new Exception('This member already has this copy on loan.');
            }

            // 6. Compute due date
            $due_date = date('Y-m-d', strtotime('+' . LOAN_DAYS . ' days'));

            // 7. Branch = copy's branch, else librarian's branch
            $branch_id = $copy['shelf_branch_id'] ?: ($_SESSION['branch_id'] ?? null);

            // 8. Insert borrowing
            $ins = $pdo->prepare(
                "INSERT INTO borrowings
                 (user_id, copy_id, branch_id, due_date, status, notes, checked_out_by)
                 VALUES (?, ?, ?, ?, 'borrowed', ?, ?)"
            );
            $ins->execute([
                $member_id,
                $copy_id,
                $branch_id,
                $due_date,
                $notes !== '' ? $notes : null,
                $_SESSION['user_id'] ?? null
            ]);
            $borrowing_id = (int)$pdo->lastInsertId();

            // 9. Flip copy status
            $pdo->prepare("UPDATE copies SET status = 'borrowed' WHERE id = ?")->execute([$copy_id]);

            // 10. Audit log
            $log = $pdo->prepare(
                "INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                 VALUES (?, 'checkout', ?, 'borrowing', ?)"
            );
            $log->execute([
                $_SESSION['user_id'] ?? null,
                "Checked out copy {$copy['barcode']} ({$copy['title']}) to {$member['username']} — due {$due_date}",
                $borrowing_id
            ]);

            $pdo->commit();

            $success = "Checked out “{$copy['title']}” ({$copy['barcode']}) to {$member['username']}. Due {$due_date}.";

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

// ---------- LOAD FORM DATA ----------
$members = $pdo->query(
    "SELECT id, username, first_name, last_name, card_number
     FROM users
     WHERE role = 'member' AND status = 'active'
     ORDER BY last_name, first_name"
)->fetchAll();

$available = $pdo->query(
    "SELECT c.id, c.barcode, b.title,
            s.code AS shelf_code, br.name AS branch_name
     FROM copies c
     JOIN books b ON b.id = c.book_id
     LEFT JOIN shelves s ON s.id = c.shelf_id
     LEFT JOIN branches br ON br.id = s.branch_id
     WHERE c.status = 'available'
     ORDER BY b.title, c.barcode"
)->fetchAll();

include 'header.php';
?>

<h2 style="margin-bottom:20px;"><i class="bi bi-box-arrow-right" style="color:var(--primary);"></i> Checkout a Book</h2>

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

<div class="table-card" style="max-width:820px;margin:0 auto;">
    <h5 style="margin-bottom:16px;"><i class="bi bi-clipboard-check"></i> New Loan</h5>

    <?php if (!$members): ?>
        <p style="color:var(--text-muted);">No active members in the system.</p>
    <?php elseif (!$available): ?>
        <p style="color:var(--text-muted);">No copies are currently available for checkout.</p>
    <?php else: ?>
    <form method="POST" action="checkout.php">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">

            <div style="grid-column:span 2;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Member *</label>
                <select name="member_id" required
                        style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;font-size:1rem;">
                    <option value="">— Select member —</option>
                    <?php foreach ($members as $m): ?>
                        <option value="<?= (int)$m['id'] ?>">
                            <?= htmlspecialchars(trim($m['first_name'].' '.$m['last_name'])) ?>
                            (<?= htmlspecialchars($m['username']) ?>)
                            <?= $m['card_number'] ? ' · ' . htmlspecialchars($m['card_number']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="grid-column:span 2;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Available Copy *</label>
                <select name="copy_id" required
                        style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;font-size:1rem;">
                    <option value="">— Select copy —</option>
                    <?php foreach ($available as $c): ?>
                        <option value="<?= (int)$c['id'] ?>">
                            <?= htmlspecialchars($c['title']) ?>
                            · <?= htmlspecialchars($c['barcode']) ?>
                            <?= $c['branch_name'] ? ' · ' . htmlspecialchars($c['branch_name']) : '' ?>
                            <?= $c['shelf_code'] ? ' (' . htmlspecialchars($c['shelf_code']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="grid-column:span 2;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Notes (optional)</label>
                <textarea name="notes" rows="2"
                          style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;font-size:1rem;resize:vertical;"></textarea>
            </div>
        </div>

        <p style="margin-top:14px;color:var(--text-muted);font-size:.85rem;">
            Loan period: <strong><?= LOAN_DAYS ?> days</strong>.
            Limit: <strong><?= MAX_LOANS_PER_MEMBER ?></strong> active loans per member.
            Unpaid fines above <strong>€<?= number_format(FINE_BLOCK_THRESHOLD, 2) ?></strong> block checkout.
        </p>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:22px;">
            <a href="index.php" class="btn-secondary-custom">← Back</a>
            <button type="submit" class="btn-add">
                <i class="bi bi-check-circle"></i> Check Out
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
