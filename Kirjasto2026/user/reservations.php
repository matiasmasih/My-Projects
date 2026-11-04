<?php
// user/reservations.php — My Reservations (Books & Devices)

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || ($user['role'] !== 'member' && $user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : 'Main Library';

$flash = '';
$error = '';

// ---- HANDLE CANCEL BOOK RESERVATION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_book_id'])) {
    $reservation_id = (int)$_POST['cancel_book_id'];
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND user_id = ? AND status IN ('pending', 'ready')");
    $stmt->execute([$reservation_id, $user_id]);
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?")->execute([$reservation_id]);
        $flash = 'Book reservation cancelled.';
    }
}

// ---- HANDLE CANCEL DEVICE RESERVATION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_device_id'])) {
    $reservation_id = (int)$_POST['cancel_device_id'];
    $stmt = $pdo->prepare("SELECT * FROM device_reservations WHERE id = ? AND user_id = ? AND status IN ('pending', 'ready')");
    $stmt->execute([$reservation_id, $user_id]);
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE device_reservations SET status = 'cancelled' WHERE id = ?")->execute([$reservation_id]);
        $flash = 'Device reservation cancelled.';
    }
}

// ---- FETCH BOOK RESERVATIONS (with Transfer Status) ----
$stmt = $pdo->prepare("
    SELECT r.id, r.status, r.reserved_at, r.book_id as item_id, 'book' as type,
           b.title as name, b.author as author, b.cover_image,
           t.status as transfer_status, t.from_branch_id, t.created_at as transfer_date,
           br.name as from_branch_name
    FROM reservations r
    JOIN books b ON b.id = r.book_id
    LEFT JOIN transfers t ON t.requested_by = r.user_id AND t.status IN ('pending', 'completed')
    LEFT JOIN branches br ON br.id = t.from_branch_id
    WHERE r.user_id = ? AND (t.id IS NULL OR t.id = (SELECT MAX(id) FROM transfers WHERE requested_by = r.user_id))
");
$stmt->execute([$user_id]);
$book_res = $stmt->fetchAll();

// ---- FETCH DEVICE RESERVATIONS ----
$stmt = $pdo->prepare("
    SELECT dr.id, dr.status, dr.reserved_at, dr.device_id as item_id, 'device' as type,
           CONCAT(d.brand, ' ', d.model) as name, dt.name as author, d.image as cover_image,
           dt2.status as transfer_status, dt2.from_branch_id, dt2.created_at as transfer_date,
           br.name as from_branch_name
    FROM device_reservations dr
    JOIN devices d ON d.id = dr.device_id
    JOIN device_types dt ON dt.id = d.device_type_id
    LEFT JOIN device_transfers dt2 ON dt2.requested_by = dr.user_id AND dt2.status IN ('pending', 'completed')
    LEFT JOIN branches br ON br.id = dt2.from_branch_id
    WHERE dr.user_id = ? AND (dt2.id IS NULL OR dt2.id = (SELECT MAX(id) FROM device_transfers WHERE requested_by = dr.user_id))
");
$stmt->execute([$user_id]);
$device_res = $stmt->fetchAll();

// ---- COMBINE & SORT ----
$all_reservations = array_merge($book_res, $device_res);
usort($all_reservations, function($a, $b) {
    return strtotime($b['reserved_at']) - strtotime($a['reserved_at']);
});

$page_title = 'My Reservations';
require_once __DIR__ . '/../includes/user_header.php';
?>

<!-- Hero Banner -->
<div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); padding: 40px; margin-bottom: 32px; position: relative; overflow: hidden;">
    <div style="position: relative; z-index: 2; max-width: 60%;">
        <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text); margin-bottom: 8px;">My Reservations</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; margin: 0;">Books and devices you're waiting to borrow.</p>
    </div>
    <i class="fas fa-bookmark" style="position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: #10b981; opacity: 0.05; z-index: 1; transform: rotate(-15deg);"></i>
</div>

<!-- Messages -->
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

<?php if (empty($all_reservations)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
        <i class="fas fa-bookmark" style="font-size: 4rem; color: var(--border); margin-bottom: 16px;"></i>
        <h3 style="color: var(--text); margin-bottom: 8px;">No reservations yet</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px;">You haven't reserved any books or devices.</p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
        <?php foreach ($all_reservations as $res):
            $status_map = [
                'pending'   => ['label' => 'Pending',   'bg' => '#f59e0b', 'text' => 'white'],
                'ready'     => ['label' => 'Ready',     'bg' => '#10b981', 'text' => 'white'],
                'cancelled' => ['label' => 'Cancelled', 'bg' => 'var(--bg-alt)', 'text' => 'var(--text-muted)'],
                'completed' => ['label' => 'Completed', 'bg' => 'var(--bg-alt)', 'text' => 'var(--text-muted)'],
            ];
            $badge = $status_map[$res['status']] ?? ['label' => $res['status'], 'bg' => 'var(--bg-alt)', 'text' => 'var(--text)'];
            $is_device = ($res['type'] === 'device');
            
            // Check for active transfer
            $has_transfer = !empty($res['transfer_status']);
            $in_transit = ($res['transfer_status'] === 'pending');
            $transfer_done = ($res['transfer_status'] === 'completed');
        ?>
            <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">

                <div style="height: 180px; background: var(--bg-alt); display: flex; align-items: center; justify-content: center; position: relative;">
                    <?php if (!empty($res['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $res['cover_image'])): ?>
                        <img src="<?= UPLOADS_URL . e($res['cover_image']) ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.8;">
                    <?php else: ?>
                        <i class="fas <?= $is_device ? 'fa-laptop' : 'fa-book' ?>" style="font-size: 4rem; color: var(--border);"></i>
                    <?php endif; ?>

                    <!-- Status Badge -->
                    <div style="position: absolute; top: 12px; right: 12px; z-index: 10;">
                        <span class="badge" style="background: <?= $badge['bg'] ?>; color: <?= $badge['text'] ?>; padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; box-shadow: 0 4px 10px rgba(0,0,0,0.3);"><?= e($badge['label']) ?></span>
                    </div>

                    <!-- Type Badge -->
                    <div style="position: absolute; top: 12px; left: 12px; z-index: 10;">
                        <span style="background: rgba(0,0,0,0.6); color: white; padding: 4px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas <?= $is_device ? 'fa-laptop' : 'fa-book' ?>" style="margin-right: 4px;"></i><?= $is_device ? 'Device' : 'Book' ?>
                        </span>
                    </div>

                    <!-- Transfer Status Overlay -->
                    <?php if ($in_transit): ?>
                        <div style="position: absolute; inset: 0; background: rgba(14, 165, 233, 0.85); display: flex; flex-direction: column; align-items: center; justify-content: center; color: white; backdrop-filter: blur(2px);">
                            <i class="fas fa-truck" style="font-size: 2rem; margin-bottom: 8px;"></i>
                            <div style="font-weight: 800; font-size: 1rem; letter-spacing: 1px; text-transform: uppercase;">In Transit</div>
                            <div style="font-size: 0.8rem; opacity: 0.9; margin-top: 4px;">From: <?= e($res['from_branch_name'] ?: 'Another Branch') ?></div>
                        </div>
                    <?php elseif ($transfer_done): ?>
                        <div style="position: absolute; inset: 0; background: rgba(16, 185, 129, 0.85); display: flex; flex-direction: column; align-items: center; justify-content: center; color: white; backdrop-filter: blur(2px);">
                            <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 8px;"></i>
                            <div style="font-weight: 800; font-size: 1rem; letter-spacing: 1px; text-transform: uppercase;">Ready for Pickup</div>
                            <div style="font-size: 0.8rem; opacity: 0.9; margin-top: 4px;">Arrived at <?= e($branch_name) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="padding: 20px; flex-grow: 1; display: flex; flex-direction: column;">
                    <h4 style="margin: 0 0 4px 0; font-size: 1.1rem; color: var(--text); line-height: 1.3;"><?= e($res['name']) ?></h4>
                    <p style="margin: 0 0 16px 0; color: var(--text-muted); font-size: 0.85rem;"><?= e($res['author']) ?></p>

                    <div style="margin-top: auto; border-top: 1px solid var(--border); padding-top: 16px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Reserved On</div>
                            <div style="font-weight: 700; font-size: 0.9rem;"><?= date('d M Y', strtotime($res['reserved_at'])) ?></div>
                        </div>

                        <?php if (in_array($res['status'], ['pending', 'ready']) && !$in_transit): ?>
                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Cancel this reservation?');">
                                <?php if ($is_device): ?>
                                    <input type="hidden" name="cancel_device_id" value="<?= $res['id'] ?>">
                                <?php else: ?>
                                    <input type="hidden" name="cancel_book_id" value="<?= $res['id'] ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-secondary" style="padding: 8px 16px; border-radius: 6px; background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#ef4444'; this.style.color='white'; this.style.borderColor='#ef4444';" onmouseout="this.style.background='var(--bg-alt)'; this.style.color='var(--text)'; this.style.borderColor='var(--border)';">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
