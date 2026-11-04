<?php
// admin/transfers.php — Track books in transit between branches
// Kirjasto 2026 — Phase 9

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';
$error = '';

// ---- MARK TRANSFER AS COMPLETED ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_transfer') {
    $transfer_id = (int)$_POST['transfer_id'];

    $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ? AND status = 'pending'");
    $stmt->execute([$transfer_id]);
    $transfer = $stmt->fetch();

    if ($transfer) {
        try {
            $pdo->beginTransaction();

            // 1. Mark transfer completed
            $pdo->prepare("UPDATE transfers SET status = 'completed', completed_at = NOW() WHERE id = ?")
                ->execute([$transfer_id]);

            // 2. Move the book copy to the destination branch, set as available
            $pdo->prepare("UPDATE book_copies SET branch_id = ?, status = 'available' WHERE id = ?")
                ->execute([$transfer['to_branch_id'], $transfer['book_copy_id']]);

            // 3. Audit log
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)")
                ->execute([
                    $_SESSION['user_id'],
                    'transfer.complete',
                    "Transfer ID $transfer_id completed — Copy ID {$transfer['book_copy_id']} moved to branch {$transfer['to_branch_id']}",
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                ]);

            $pdo->commit();
            $flash = 'Transfer marked as received. Book copy is now available at the destination branch.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to complete transfer: ' . $e->getMessage();
        }
    } else {
        $error = 'Transfer not found or already completed.';
    }
}

// ---- CANCEL TRANSFER ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_transfer') {
    $transfer_id = (int)$_POST['transfer_id'];

    $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ? AND status = 'pending'");
    $stmt->execute([$transfer_id]);
    $transfer = $stmt->fetch();

    if ($transfer) {
        try {
            $pdo->beginTransaction();

            // Cancel transfer
            $pdo->prepare("UPDATE transfers SET status = 'cancelled' WHERE id = ?")
                ->execute([$transfer_id]);

            // Restore copy to available at original branch
            $pdo->prepare("UPDATE book_copies SET status = 'available' WHERE id = ?")
                ->execute([$transfer['book_copy_id']]);

            $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)")
                ->execute([
                    $_SESSION['user_id'],
                    'transfer.cancel',
                    "Transfer ID $transfer_id cancelled",
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                ]);

            $pdo->commit();
            $flash = 'Transfer cancelled. Book copy is available again at its original branch.';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to cancel transfer: ' . $e->getMessage();
        }
    } else {
        $error = 'Transfer not found or already completed.';
    }
}

// ---- FETCH ALL TRANSFERS ----
$stmt = $pdo->query("
    SELECT t.*,
           b.title AS book_title,
           b.author AS book_author,
           bc.copy_number,
           f.name AS from_branch_name,
           tb.name AS to_branch_name,
           u.first_name AS req_first,
           u.last_name AS req_last
    FROM transfers t
    JOIN book_copies bc ON bc.id = t.book_copy_id
    JOIN books b ON b.id = bc.book_id
    JOIN branches f ON f.id = t.from_branch_id
    JOIN branches tb ON tb.id = t.to_branch_id
    JOIN users u ON u.id = t.requested_by
    ORDER BY 
        CASE WHEN t.status = 'pending' THEN 0 ELSE 1 END,
        t.created_at DESC
");
$transfers = $stmt->fetchAll();

$pending_count = 0;
foreach ($transfers as $t) {
    if ($t['status'] === 'pending') $pending_count++;
}

$page_title = 'Book Transfers';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Book Transfers</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        Track books in transit between branches.
        <?php if ($pending_count > 0): ?>
            <strong style="color: #f59e0b;"><?= $pending_count ?> in transit</strong>
        <?php endif; ?>
    </p>
</div>

<?php if ($flash): ?>
    <div class="flash flash-success" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; color: #10b981; font-weight: 600;">
        <i class="fas fa-check-circle"></i> <?= e($flash) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="flash flash-error" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; color: #ef4444; font-weight: 600;">
        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if (empty($transfers)): ?>
    <div class="card" style="text-align: center; padding: 80px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
        <i class="fas fa-truck" style="font-size: 4rem; color: var(--border); margin-bottom: 16px;"></i>
        <h3 style="color: var(--text); margin-bottom: 8px;">No transfers</h3>
        <p style="color: var(--text-muted); margin: 0;">Books will appear here when a member reserves a book that needs to travel between branches.</p>
    </div>
<?php else: ?>
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; text-align: left; white-space: nowrap;">
                <thead style="background: var(--bg-alt);">
                    <tr>
                        <th style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Book</th>
                        <th style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Route</th>
                        <th style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Requested By</th>
                        <th style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Date</th>
                        <th style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Status</th>
                        <th style="padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfers as $t): ?>
                        <tr>
                            <td style="padding: 16px 20px; border-bottom: 1px solid var(--border);">
                                <strong style="display: block; color: var(--text);"><?= e($t['book_title']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($t['book_author']) ?> · Copy #<?= e($t['copy_number']) ?></small>
                            </td>
                            <td style="padding: 16px 20px; border-bottom: 1px solid var(--border);">
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem;">
                                    <span style="color: var(--text-muted);"><?= e($t['from_branch_name']) ?></span>
                                    <i class="fas fa-arrow-right" style="color: #3b82f6;"></i>
                                    <strong style="color: var(--text);"><?= e($t['to_branch_name']) ?></strong>
                                </div>
                            </td>
                            <td style="padding: 16px 20px; border-bottom: 1px solid var(--border); color: var(--text-muted); font-size: 0.9rem;">
                                <?= e($t['req_first'] . ' ' . $t['req_last']) ?>
                            </td>
                            <td style="padding: 16px 20px; border-bottom: 1px solid var(--border); color: var(--text-muted); font-size: 0.85rem;">
                                <?= date('d M Y, H:i', strtotime($t['created_at'])) ?>
                            </td>
                            <td style="padding: 16px 20px; border-bottom: 1px solid var(--border);">
                                <?php if ($t['status'] === 'pending'): ?>
                                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">In Transit</span>
                                <?php elseif ($t['status'] === 'completed'): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Received</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Cancelled</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 16px 20px; border-bottom: 1px solid var(--border); text-align: right;">
                                <?php if ($t['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline-block; margin-right: 8px;" onsubmit="return confirm('Confirm this book has physically arrived at <?= e($t['to_branch_name']) ?>?');">
                                        <input type="hidden" name="action" value="complete_transfer">
                                        <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                                        <button type="submit" style="padding: 6px 14px; font-size: 0.75rem; border-radius: 50px; border: 1px solid rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.1); color: #10b981; cursor: pointer; font-weight: 700; text-transform: uppercase;" onmouseover="this.style.background='#10b981'; this.style.color='white';" onmouseout="this.style.background='rgba(16,185,129,0.1)'; this.style.color='#10b981';">
                                            <i class="fas fa-check"></i> Received
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline-block;" onsubmit="return confirm('Cancel this transfer?');">
                                        <input type="hidden" name="action" value="cancel_transfer">
                                        <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                                        <button type="submit" style="padding: 6px 14px; font-size: 0.75rem; border-radius: 50px; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.1); color: #ef4444; cursor: pointer; font-weight: 700; text-transform: uppercase;" onmouseover="this.style.background='#ef4444'; this.style.color='white';" onmouseout="this.style.background='rgba(239,68,68,0.1)'; this.style.color='#ef4444';">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>

