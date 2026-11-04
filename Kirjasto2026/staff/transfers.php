<?php
// staff/transfers.php — Book Transfers (Send & Receive)

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
    
    if ($action === 'initiate') {
        $book_copy_id = (int)($_POST['book_copy_id'] ?? 0);
        $to_branch_id = (int)($_POST['to_branch_id'] ?? 0);

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT id FROM book_copies WHERE id = ? AND branch_id = ? AND status = 'available'");
            $stmt->execute([$book_copy_id, $branch_id]);
            if (!$stmt->fetch()) throw new Exception('Book copy not available in your branch.');

            $pdo->prepare("INSERT INTO transfers (book_copy_id, from_branch_id, to_branch_id, status, requested_by, created_at) VALUES (?, ?, ?, 'pending', ?, NOW())")
                ->execute([$book_copy_id, $branch_id, $to_branch_id, $_SESSION['user_id']]);

            $pdo->prepare("UPDATE book_copies SET status = 'in_transit' WHERE id = ?")->execute([$book_copy_id]);

            $pdo->commit();
            $flash = 'Book marked as In Transit. It will arrive at the destination branch soon.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Transfer failed: ' . $e->getMessage();
        }
    }

    if ($action === 'receive') {
        $transfer_id = (int)($_POST['transfer_id'] ?? 0);

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ? AND to_branch_id = ? AND status = 'pending'");
            $stmt->execute([$transfer_id, $branch_id]);
            $transfer = $stmt->fetch();
            
            if (!$transfer) throw new Exception('Invalid transfer or already received.');

            $pdo->prepare("UPDATE transfers SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$transfer_id]);
            $pdo->prepare("UPDATE book_copies SET branch_id = ?, status = 'available' WHERE id = ?")->execute([$branch_id, $transfer['book_copy_id']]);

            $pdo->commit();
            $flash = 'Book received and added to your branch inventory.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Receive failed: ' . $e->getMessage();
        }
    }
}

// ---- FETCH DATA ----
$available_copies = [];
$incoming_transfers = [];
$outgoing_transfers = [];
$other_branches = [];

if ($branch_id) {
    $stmt = $pdo->prepare("
        SELECT bc.id, bc.copy_number, b.title, b.author 
        FROM book_copies bc 
        JOIN books b ON b.id = bc.book_id 
        WHERE bc.branch_id = ? AND bc.status = 'available'
        ORDER BY b.title
    ");
    $stmt->execute([$branch_id]);
    $available_copies = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id != ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$branch_id]);
    $other_branches = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT t.*, b.title, b.author, br.name AS from_branch_name 
        FROM transfers t 
        JOIN book_copies bc ON bc.id = t.book_copy_id 
        JOIN books b ON b.id = bc.book_id 
        JOIN branches br ON br.id = t.from_branch_id
        WHERE t.to_branch_id = ? AND t.status = 'pending'
    ");
    $stmt->execute([$branch_id]);
    $incoming_transfers = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT t.*, b.title, b.author, br.name AS to_branch_name 
        FROM transfers t 
        JOIN book_copies bc ON bc.id = t.book_copy_id 
        JOIN books b ON b.id = bc.book_id 
        JOIN branches br ON br.id = t.to_branch_id
        WHERE t.from_branch_id = ? AND t.status = 'pending'
    ");
    $stmt->execute([$branch_id]);
    $outgoing_transfers = $stmt->fetchAll();
}

$page_title = 'Book Transfers';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Book Transfers</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Send books to other branches and receive incoming shipments.
    </p>
</div>

<?php if ($flash): ?><div class="flash flash-success" style="margin-bottom: 24px;"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error" style="margin-bottom: 24px;"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2" style="align-items: start;">

    <!-- Left Column: Send a Book -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <div class="card">
            <h3><i class="fas fa-paper-plane" style="color: #0ea5e9;"></i> Send a Book</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Select an available book from your branch to send to another branch.</p>
            
            <form method="POST">
                <input type="hidden" name="action" value="initiate">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label>Select Available Book</label>
                    <select name="book_copy_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                        <option value="">-- Choose Book --</option>
                        <?php foreach ($available_copies as $copy): ?>
                            <option value="<?= $copy['id'] ?>"><?= e($copy['title']) ?> (Copy #<?= e($copy['copy_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <label>Destination Branch</label>
                    <select name="to_branch_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                        <option value="">-- Choose Branch --</option>
                        <?php foreach ($other_branches as $br): ?>
                            <option value="<?= $br['id'] ?>"><?= e($br['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #0ea5e9, #14b8a6); border: none; border-radius: 8px; font-weight: 600; color: white; cursor: pointer;">
                    <i class="fas fa-truck"></i> Mark as In Transit
                </button>
            </form>
        </div>

        <!-- Outgoing Transfers -->
        <div class="card">
            <h3><i class="fas fa-arrow-up" style="color: var(--warning);"></i> Outgoing (In Transit)</h3>
            <?php if (empty($outgoing_transfers)): ?>
                <p style="color: var(--text-muted); padding: 20px 0; text-align: center;">No outgoing transfers.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 16px;">
                    <?php foreach ($outgoing_transfers as $t): ?>
                        <div style="padding: 12px; background: var(--bg-alt); border-radius: 8px; border: 1px solid var(--border);">
                            <strong style="display: block; color: var(--text);"><?= e($t['title']) ?></strong>
                            <small style="color: var(--text-muted);">Sent to: <?= e($t['to_branch_name']) ?></small>
                            <div style="margin-top: 8px;">
                                <span style="padding: 4px 8px; background: rgba(245, 158, 11, 0.1); color: var(--warning); border-radius: 4px; font-size: 0.75rem; font-weight: 700;">IN TRANSIT</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Receive a Book -->
    <div class="card">
        <h3><i class="fas fa-inbox" style="color: var(--success);"></i> Incoming Shipments</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Books that other branches have sent to you.</p>

        <?php if (empty($incoming_transfers)): ?>
            <p style="color: var(--text-muted); padding: 40px 20px; text-align: center;">No incoming shipments right now.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($incoming_transfers as $t): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--bg-alt); border-radius: 12px; border: 1px solid var(--border);">
                        <div>
                            <strong style="display: block; color: var(--text);"><?= e($t['title']) ?></strong>
                            <small style="color: var(--text-muted);">From: <?= e($t['from_branch_name']) ?></small>
                        </div>
                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Confirm receipt of this book?');">
                            <input type="hidden" name="action" value="receive">
                            <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                            <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.8rem; background: var(--success); border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-check"></i> Receive
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
