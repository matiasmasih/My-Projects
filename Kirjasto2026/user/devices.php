<?php
// user/devices.php — Browse Local Branch Devices Only

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
$branch_name = $branch_id ? get_branch_name($branch_id) : null;
$flash = '';
$error = '';

// ---- HANDLE TOGGLE FAVORITE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_fav'])) {
    $item_id = (int)$_POST['item_id'];
    $item_type = $_POST['item_type'];

    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND item_type = ? AND item_id = ?");
    $stmt->execute([$user_id, $item_type, $item_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->prepare("DELETE FROM favorites WHERE id = ?")->execute([$existing['id']]);
        $flash = 'Removed from favorites.';
    } else {
        $pdo->prepare("INSERT INTO favorites (user_id, item_type, item_id, created_at) VALUES (?, ?, ?, NOW())")->execute([$user_id, $item_type, $item_id]);
        $flash = 'Added to favorites!';
    }
    header('Location: ' . SITE_URL . 'user/devices.php?q=' . urlencode($_GET['q'] ?? ''));
    exit;
}

$search = trim($_GET['q'] ?? '');

// Fetch ONLY devices from this branch
$sql = "
    SELECT d.*, dt.name as type_name, dt.loan_days
    FROM devices d
    JOIN device_types dt ON dt.id = d.device_type_id
    WHERE d.branch_id = ?
";
$params = [$branch_id];

if ($search) {
    $sql .= " AND (d.brand LIKE ? OR d.model LIKE ? OR d.serial_number LIKE ? OR dt.name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
$sql .= " ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll();

// Fetch user's favorites
$stmt = $pdo->prepare("SELECT item_id FROM favorites WHERE user_id = ? AND item_type = 'device'");
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Browse Devices';
require_once __DIR__ . '/../includes/user_header.php';
?>

<!-- Hero Banner with Search Bar -->
<div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); padding: 40px; margin-bottom: 32px; position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
    <div style="position: relative; z-index: 2; max-width: 50%;">
        <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text); margin-bottom: 8px;">Browse Devices</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; margin: 0;">
            <?= $branch_name ? 'Borrow equipment from <strong>' . e($branch_name) . '</strong>.' : 'Borrow equipment from the library.' ?>
        </p>
    </div>

    <div style="position: relative; z-index: 2; width: 100%; max-width: 400px;">
        <form method="GET" style="display: flex; gap: 8px; margin: 0;">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search brand, model, or type..." style="padding: 14px 20px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: var(--text); font-size: 0.95rem; width: 100%; backdrop-filter: blur(10px);">
            <button type="submit" class="btn btn-primary" style="padding: 14px 24px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; cursor: pointer; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>

    <i class="fas fa-laptop" style="position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: #10b981; opacity: 0.05; z-index: 1; transform: rotate(-15deg);"></i>
</div>

<?php if ($flash): ?>
    <div class="flash flash-success" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; color: #10b981; font-weight: 600;">
        <i class="fas fa-check-circle"></i> <?= e($flash) ?>
    </div>
<?php endif; ?>

<?php if (empty($devices)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-laptop" style="font-size: 3rem; color: var(--border); margin-bottom: 16px;"></i>
        <h3 style="color: var(--text); margin-bottom: 8px;">No devices found.</h3>
        <p style="color: var(--text-muted);">Your branch currently has no devices matching this criteria.</p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 24px; width: 100%;">
        <?php foreach ($devices as $device):
            $is_fav = in_array($device['id'], $favorites);
            $is_available = ($device['status'] === 'available');
        ?>
            <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">

                <!-- Device Image / Cover Area -->
                <div style="height: 220px; background: var(--bg-alt); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                    <?php if (!empty($device['image']) && file_exists(__DIR__ . '/../uploads/' . $device['image'])): ?>
                        <img src="<?= UPLOADS_URL . e($device['image']) ?>" alt="<?= e($device['brand'] . ' ' . $device['model']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted);">
                            <i class="fas fa-laptop" style="font-size: 5rem; opacity: 0.5; margin-bottom: 8px;"></i>
                            <span style="font-size: 0.8rem;">No Image</span>
                        </div>
                    <?php endif; ?>

                    <!-- Heart Favorites Button -->
                    <div style="position: absolute; top: 12px; left: 12px; z-index: 10;">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="toggle_fav" value="1">
                            <input type="hidden" name="item_id" value="<?= $device['id'] ?>">
                            <input type="hidden" name="item_type" value="device">
                            <button type="submit" style="background: rgba(0,0,0,0.5); color: <?= $is_fav ? '#ef4444' : 'white' ?>; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: none; cursor: pointer; transition: all 0.2s; backdrop-filter: blur(5px);" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                <i class="<?= $is_fav ? 'fas' : 'far' ?> fa-heart"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Availability Badge -->
                    <div style="position: absolute; top: 12px; right: 12px; z-index: 10;">
                        <?php if ($is_available): ?>
                            <span class="badge" style="background: #10b981; color: white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); border: none; padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Available</span>
                        <?php else: ?>
                            <span class="badge" style="background: #f59e0b; color: white; box-shadow: 0 2px 10px rgba(0,0,0,0.3); border: none; padding: 6px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Borrowed</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Device Info -->
                <div style="padding: 20px; flex-grow: 1; display: flex; flex-direction: column;">
                    <h4 style="margin: 0 0 4px 0; font-size: 1.1rem; color: var(--text); line-height: 1.3;"><?= e($device['brand'] . ' ' . $device['model']) ?></h4>
                    <p style="margin: 0 0 12px 0; color: var(--text-muted); font-size: 0.85rem;"><?= e($device['type_name']) ?></p>

                    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;">
                        <span style="font-size: 0.75rem; background: var(--bg-alt); padding: 4px 8px; border-radius: 4px; color: var(--text-muted);"><i class="fas fa-barcode"></i> <?= e($device['serial_number']) ?></span>
                        <span style="font-size: 0.75rem; background: var(--bg-alt); padding: 4px 8px; border-radius: 4px; color: var(--text-muted);"><i class="fas fa-clock"></i> <?= e($device['loan_days']) ?> days</span>
                    </div>

                    <div style="margin-top: auto;">
                        <a href="<?= e(SITE_URL) ?>user/device_detail.php?id=<?= $device['id'] ?>" class="btn btn-secondary" style="display: block; width: 100%; text-align: center; padding: 10px 12px; border-radius: 8px; background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='#10b981'; this.style.color='white'; this.style.borderColor='#10b981';" onmouseout="this.style.background='var(--bg-alt)'; this.style.color='var(--text)'; this.style.borderColor='var(--border)';">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
