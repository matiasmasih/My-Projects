<?php
// admin_reservations.php — View and manage table reservations

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$admin_role   = $_SESSION['admin_role']   ?? 'admin';
$admin_branch = $_SESSION['admin_branch_id'] ?? null;

$allowed_statuses = ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no-show'];

$flash = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_id'], $_POST['status'])) {
    $rid    = (int)$_POST['reservation_id'];
    $status = $_POST['status'];

    if (in_array($status, $allowed_statuses, true)) {
        try {
            $sql = "UPDATE reservations SET status = ? WHERE id = ?";
            $params = [$status, $rid];
            if ($admin_role !== 'admin' && $admin_branch) {
                $sql .= " AND branch_id = ?";
                $params[] = $admin_branch;
            }
            $pdo->prepare($sql)->execute($params);
            $flash = 'Reservation updated.';
        } catch (PDOException $e) {
            $flash = 'Update failed.';
        }
    }
}

// Filter
$status_filter = $_GET['status'] ?? '';
$where = "WHERE 1=1";
$params = [];

if (in_array($status_filter, $allowed_statuses, true)) {
    $where .= " AND r.status = :st";
    $params[':st'] = $status_filter;
}
if ($admin_role !== 'admin' && $admin_branch) {
    $where .= " AND r.branch_id = :bid";
    $params[':bid'] = $admin_branch;
}

// Load reservations
$reservations = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.*, b.name AS branch_name
        FROM reservations r
        JOIN branches b ON b.id = r.branch_id
        $where
        ORDER BY r.reservation_date DESC, r.reservation_time DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    $reservations = [];
}

$page_title = 'Reservations';
require_once __DIR__ . '/includes/admin_header.php';
?>



        <div class="admin-hero">
            <div>
                <h1><?= e(t('admin_reservations')) ?></h1>
                <p><?= e(t('admin_res_sub')) ?></p>
            </div>
            <a href="admin_dashboard.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-arrow-left"></i> <?= e(t('admin_dashboard')) ?>
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= e($flash) ?></div>
        <?php endif; ?>

        <!-- Filter tabs -->
        <div class="admin-panel">
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="admin_reservations.php" class="category-tab <?= $status_filter === '' ? 'active' : '' ?>"><?= e(t('admin_all')) ?></a>
                <?php foreach ($allowed_statuses as $st): ?>
                    <a href="admin_reservations.php?status=<?= e($st) ?>"
                       class="category-tab <?= $status_filter === $st ? 'active' : '' ?>">
                        <?= e(t('status_' . $st)) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($reservations)): ?>
            <div class="admin-panel">
                <div class="admin-empty">
                    <i class="fas fa-calendar-xmark"></i>
                    <?= e(t('admin_no_res_found')) ?>
                </div>
            </div>
        <?php else: ?>

            <?php foreach ($reservations as $r): ?>
                <div class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <h3 style="margin-bottom:4px;">
                                <i class="fas fa-calendar-check"></i> <?= e($r['reservation_code']) ?>
                                <span class="status-badge status-<?= $r['status'] === 'confirmed' ? 'ready' : ($r['status'] === 'cancelled' || $r['status'] === 'no-show' ? 'cancelled' : 'pending') ?>" style="margin-left:8px;">
                                    <?= e($r['status']) ?>
                                </span>
                            </h3>
                            <div style="font-size:0.85rem;color:var(--text-muted);">
                                <?= e($r['customer_name']) ?> ·
                                <?= e($r['customer_phone']) ?> ·
                                <?= e($r['branch_name']) ?>
                            </div>
                        </div>

                        <form method="post" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
                            <select name="status" style="padding:6px 10px;font-size:0.85rem;">
                                <?php foreach ($allowed_statuses as $st): ?>
                                    <option value="<?= e($st) ?>" <?= $r['status'] === $st ? 'selected' : '' ?>>
                                        <?= e(t('status_' . $st)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-accent btn-sm"><i class="fas fa-check"></i> <?= e(t('admin_update')) ?></button>
                        </form>
                    </div>

                    <div class="grid-3" style="gap:20px;">
                        <div>
                            <h4 style="font-size:0.78rem;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;"><?= e(t('admin_date_time')) ?></h4>
                            <div style="font-size:0.95rem;">
                                <i class="fas fa-calendar"></i> <?= e(date('D, M j, Y', strtotime($r['reservation_date']))) ?><br>
                                <i class="fas fa-clock"></i> <?= e(substr($r['reservation_time'], 0, 5)) ?>
                            </div>
                        </div>
                        <div>
                            <h4 style="font-size:0.78rem;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;"><?= e(t('admin_party')) ?></h4>
                            <div style="font-size:0.95rem;">
                                <i class="fas fa-users"></i> <?= (int)$r['party_size'] ?> people
                            </div>
                        </div>
                        <div>
                            <h4 style="font-size:0.78rem;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;"><?= e(t('admin_contact')) ?></h4>
                            <div style="font-size:0.9rem;">
                                <?= e($r['customer_email'] ?: '—') ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($r['special_requests'])): ?>
                        <div style="margin-top:16px;padding:12px;background:var(--bg-alt);border-radius:8px;font-size:0.9rem;">
                            <strong><?= e(t('admin_special_requests')) ?>:</strong> <?= e($r['special_requests']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
