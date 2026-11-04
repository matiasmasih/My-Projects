<?php
// staff/reservations.php — Manage Member Reservations (Branch Scoped)

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'staff' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$flash = '';
$error = '';

// ---- HANDLE ACTIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $reservation_id = (int)($_POST['reservation_id'] ?? 0);

    if ($action === 'fulfill') {
        try {
            $pdo->beginTransaction();
            // Fetch reservation
            $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND branch_id = ? AND status = 'pending'");
            $stmt->execute([$reservation_id, $branch_id]);
            $res = $stmt->fetch();

            if ($res) {
                // Check if book copy is available in this branch
                $stmt = $pdo->prepare("SELECT id FROM book_copies WHERE book_id = ? AND branch_id = ? AND status = 'available' LIMIT 1");
                $stmt->execute([$res['book_id'], $branch_id]);
                $copy = $stmt->fetch();

                if ($copy) {
                    // Create loan
                    $due_at = date('Y-m-d H:i:s', strtotime('+14 days'));
                    $pdo->prepare("INSERT INTO loans (user_id, book_copy_id, borrowed_at, due_at, status, branch_id) VALUES (?, ?, NOW(), ?, 'active', ?)")
                        ->execute([$res['user_id'], $copy['id'], $due_at, $branch_id]);
                    
                    // Update copy
                    $pdo->prepare("UPDATE book_copies SET status = 'borrowed' WHERE id = ?")->execute([$copy['id']]);
                    
                    // Update reservation
                    $pdo->prepare("UPDATE reservations SET status = 'fulfilled' WHERE id = ?")->execute([$reservation_id]);
                    
                    $flash = 'Reservation fulfilled and book loaned out.';
                } else {
                    $error = 'No available copies of this book in your branch to fulfill the reservation.';
                }
            } else {
                $error = 'Reservation not found or already processed.';
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to fulfill: ' . $e->getMessage();
        }
    }

    if ($action === 'cancel') {
        $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ? AND branch_id = ?")->execute([$reservation_id, $branch_id]);
        $flash = 'Reservation cancelled.';
    }
}

// ---- FETCH RESERVATIONS ----
$reservations = [];
if ($branch_id) {
    $stmt = $pdo->prepare("
        SELECT r.*, b.title, b.author, b.cover_image, u.first_name, u.last_name, u.email 
        FROM reservations r
        JOIN books b ON b.id = r.book_id
        JOIN users u ON u.id = r.user_id
        WHERE r.branch_id = ? AND r.status = 'pending'
        ORDER BY r.created_at ASC
    ");
    $stmt->execute([$branch_id]);
    $reservations = $stmt->fetchAll();
}

$page_title = 'Reservations';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Member Reservations</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Process books that members have put on hold.
    </p>
</div>

<?php if ($flash): ?><div class="flash flash-success" style="margin-bottom: 24px;"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error" style="margin-bottom: 24px;"><?= e($error) ?></div><?php endif; ?>

<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Member</th>
                    <th>Reserved On</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $res): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php if (!empty($res['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $res['cover_image'])): ?>
                                    <img src="<?= UPLOADS_URL . e($res['cover_image']) ?>" style="width: 40px; height: 56px; border-radius: 6px; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 40px; height: 56px; border-radius: 6px; background: rgba(14, 165, 233, 0.15); display: grid; place-items: center; color: #0ea5e9;"><i class="fas fa-book"></i></div>
                                <?php endif; ?>
                                <div>
                                    <strong style="display: block; color: var(--text);"><?= e($res['title']) ?></strong>
                                    <small style="color: var(--text-muted);"><?= e($res['author']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong style="display: block; color: var(--text);"><?= e($res['first_name'] . ' ' . $res['last_name']) ?></strong>
                            <small style="color: var(--text-muted);"><?= e($res['email']) ?></small>
                        </td>
                        <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($res['created_at'])) ?></td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <form method="post" style="margin: 0;" onsubmit="return confirm('Fulfill this reservation and loan the book?');">
                                    <input type="hidden" name="action" value="fulfill">
                                    <input type="hidden" name="reservation_id" value="<?= $res['id'] ?>">
                                    <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.75rem; background: var(--success); border: none; border-radius: 6px; cursor: pointer;">
                                        <i class="fas fa-check"></i> Fulfill
                                    </button>
                                </form>
                                <form method="post" style="margin: 0;" onsubmit="return confirm('Cancel this reservation?');">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="reservation_id" value="<?= $res['id'] ?>">
                                    <button type="submit" class="btn-outline" style="padding: 6px 12px; font-size: 0.75rem; color: var(--danger); border-color: rgba(239, 68, 68, 0.3); border-radius: 6px; cursor: pointer;">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($reservations)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">No pending reservations in your branch.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
