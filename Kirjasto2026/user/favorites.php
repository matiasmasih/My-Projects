<?php
// user/favorites.php — My Favorites (Books & Devices)

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fixed role check ($user instead of $member_user)
if (!$user || ($user['role'] !== 'member' && $user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'book'; // 'book' or 'device'

$flash = '';
$error = '';

// ---- HANDLE REMOVE FAVORITE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_fav_id'])) {
    $fav_id = (int)$_POST['remove_fav_id'];

    // Ensure it belongs to this user
    $stmt = $pdo->prepare("SELECT * FROM favorites WHERE id = ? AND user_id = ?");
    $stmt->execute([$fav_id, $user_id]);

    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM favorites WHERE id = ?")->execute([$fav_id]);

        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $log_stmt->execute([$user_id, 'favorite.remove', "Member removed Favorite ID: $fav_id", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

        $flash = 'Removed from favorites.';
    } else {
        $error = 'Invalid favorite item.';
    }
}

// ---- FETCH FAVORITES ----
if ($type === 'book') {
    $stmt = $pdo->prepare("
        SELECT f.id as fav_id, b.*
        FROM favorites f
        JOIN books b ON b.id = f.item_id
        WHERE f.user_id = ? AND f.item_type = 'book'
        ORDER BY f.created_at DESC
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT f.id as fav_id, d.*, dt.name as type_name
        FROM favorites f
        JOIN devices d ON d.id = f.item_id
        JOIN device_types dt ON dt.id = d.device_type_id
        WHERE f.user_id = ? AND f.item_type = 'device'
        ORDER BY f.created_at DESC
    ");
}
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll();

$page_title = 'Favorites';
require_once __DIR__ . '/../includes/user_header.php';
?>

<!-- Hero Banner -->
<div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); padding: 40px; margin-bottom: 32px; position: relative; overflow: hidden; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
    <div style="position: relative; z-index: 2; max-width: 60%;">
        <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text); margin-bottom: 8px;">My Favorites</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; margin: 0;">Your personal collection of saved books and devices.</p>
    </div>

    <!-- Toggle Tabs -->
    <div style="position: relative; z-index: 2; display: flex; background: var(--bg-alt); border-radius: 10px; padding: 4px; border: 1px solid var(--border);">
        <a href="?type=book" style="padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 0.9rem; transition: all 0.2s; <?= $type === 'book' ? 'background: #10b981; color: white;' : 'color: var(--text-muted);' ?>">
            <i class="fas fa-book"></i> Books
        </a>
        <a href="?type=device" style="padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 0.9rem; transition: all 0.2s; <?= $type === 'device' ? 'background: #10b981; color: white;' : 'color: var(--text-muted);' ?>">
            <i class="fas fa-laptop"></i> Devices
        </a>
    </div>

    <i class="fas fa-heart" style="position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: #10b981; opacity: 0.05; z-index: 1; transform: rotate(-15deg);"></i>
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

<?php if (empty($favorites)): ?>
    <div class="card" style="text-align: center; padding: 60px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
        <i class="fas fa-heart-broken" style="font-size: 4rem; color: var(--border); margin-bottom: 16px;"></i>
        <h3 style="color: var(--text); margin-bottom: 8px;">No favorite <?= $type === 'book' ? 'books' : 'devices' ?> yet</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px;">Click the heart icon on any <?= $type === 'book' ? 'book' : 'device' ?> to save it here.</p>
        <a href="<?= e(SITE_URL) ?>user/<?= $type === 'book' ? 'books' : 'devices' ?>.php" class="btn-primary" style="display: inline-block; padding: 12px 24px; border-radius: 8px; background: #10b981; color: white; text-decoration: none; font-weight: 600;">
            Browse <?= $type === 'book' ? 'Books' : 'Devices' ?>
        </a>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 24px;">
        <?php foreach ($favorites as $fav): ?>
            <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 25px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">

                <div style="height: 280px; background: var(--bg-alt); display: flex; align-items: center; justify-content: center; position: relative;">
                    <?php if ($type === 'book' && !empty($fav['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $fav['cover_image'])): ?>
                        <img src="<?= UPLOADS_URL . e($fav['cover_image']) ?>" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted);">
                            <i class="fas <?= $type === 'book' ? 'fa-book' : 'fa-laptop' ?>" style="font-size: 4rem; margin-bottom: 8px; opacity: 0.5;"></i>
                            <span style="font-size: 0.8rem;"><?= $type === 'book' ? 'Book' : 'Device' ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Heart Toggle (Removes from favorites) -->
                    <div style="position: absolute; top: 12px; right: 12px; z-index: 10;">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="remove_fav_id" value="<?= $fav['fav_id'] ?>">
                            <button type="submit" style="background: rgba(16, 185, 129, 0.9); color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: none; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'; this.style.background='#ef4444';" onmouseout="this.style.transform='scale(1)'; this.style.background='rgba(16, 185, 129, 0.9)';">
                                <i class="fas fa-heart"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div style="padding: 20px; flex-grow: 1; display: flex; flex-direction: column;">
                    <h4 style="margin: 0 0 4px 0; font-size: 1.1rem; color: var(--text); line-height: 1.3;">
                        <?= $type === 'book' ? e($fav['title']) : e($fav['brand'] . ' ' . $fav['model']) ?>
                    </h4>
                    <p style="margin: 0 0 20px 0; color: var(--text-muted); font-size: 0.85rem;">
                        <?= $type === 'book' ? e($fav['author']) : e($fav['type_name']) ?>
                    </p>

                    <div style="margin-top: auto;">
                        <a href="<?= e(SITE_URL) ?>user/<?= $type === 'book' ? 'book_detail' : 'device_detail' ?>.php?id=<?= $fav['id'] ?>" class="btn btn-secondary" style="display: block; width: 100%; text-align: center; padding: 10px 12px; border-radius: 8px; background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); text-decoration: none; font-size: 0.85rem; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='#10b981'; this.style.color='white'; this.style.borderColor='#10b981';" onmouseout="this.style.background='var(--bg-alt)'; this.style.color='var(--text)'; this.style.borderColor='var(--border)';">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
