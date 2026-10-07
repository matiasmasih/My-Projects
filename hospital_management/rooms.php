<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('rooms', 'login.php');

$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

$room_type_display = [
    'single' => 'Single Room',
    'double' => 'Double Room',
    'icu'    => 'ICU',
    'ward'   => 'Ward',
    'other'  => 'Other',
];

// ============================================
// HANDLE DELETE ROOM
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_room']) && $access === 'full') {
    $del_id = (int)($_POST['room_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$del_id]);
            $_SESSION['flash_success'] = "Room deleted successfully.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    }
    header("Location: rooms.php");
    exit;
}

// ============================================
// FETCH WARDS (for filter dropdown)
// ============================================
$wards_list = [];
try {
    $wards_list = $pdo->query("SELECT id, name FROM wards ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ============================================
// FILTERS + PAGINATION
// ============================================
$filter_ward  = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : 0;
$page         = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page     = 50;
$offset       = ($page - 1) * $per_page;

// Build WHERE clause
$where = '';
$params = [];

if ($filter_ward > 0) {
    $where = " WHERE r.ward_id = ? ";
    $params[] = $filter_ward;
}

// Count total
$total_rooms = 0;
try {
    $count_sql = "SELECT COUNT(*) FROM rooms r" . $where;
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_rooms = (int)$stmt->fetchColumn();
} catch (PDOException $e) {}

$total_pages = max(1, ceil($total_rooms / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// Fetch page of rooms
$rooms = [];
try {
    $sql = "
        SELECT r.*, w.name AS ward_name, w.type AS ward_type, w.location
        FROM rooms r
        LEFT JOIN wards w ON r.ward_id = w.id
        $where
        ORDER BY w.name, r.room_number
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'Rooms';
include 'rooms_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-3 gap-2" style="flex-wrap: nowrap;">
    <div class="d-flex align-items-center gap-2" style="flex-wrap: nowrap;">
        <label style="color: var(--text-muted); font-size: 0.9rem; margin: 0; white-space: nowrap;">Filter by Ward:</label>
        <form method="GET" action="rooms.php" style="margin: 0;">
            <select name="ward_id" class="form-select" style="width: 220px;" onchange="this.form.submit()">
                <option value="0">All Wards</option>
                <?php foreach ($wards_list as $w): ?>
                    <option value="<?= (int)$w['id'] ?>" <?= $filter_ward === (int)$w['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($w['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <input type="text" class="form-control" id="searchInput" placeholder="Search on this page..." style="max-width: 260px;">
        <button class="btn btn-add" id="searchButton" style="white-space: nowrap;"><i class="bi bi-search"></i> Search</button>
    </div>
    <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px; white-space: nowrap;">
        <?= $total_rooms ?> rooms total
    </span>
</div>

<!-- Rooms Table -->
<div class="table-card">
    <h5>
        <i class="bi bi-door-closed"></i>
        <?php if ($filter_ward > 0): ?>
            Rooms — <?= htmlspecialchars($wards_list[array_search($filter_ward, array_column($wards_list, 'id'))]['name'] ?? '') ?>
        <?php else: ?>
            All Rooms
        <?php endif; ?>
        (page <?= $page ?> of <?= $total_pages ?>)
    </h5>

    <?php if (count($rooms) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Room #</th>
                    <th>Ward</th>
                    <th>Ward Type</th>
                    <th>Room Type</th>
                    <th>Capacity</th>
                    <th>Notes</th>
                    <?php if ($access === 'full'): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="roomTable">
                <?php foreach ($rooms as $index => $r): ?>
                <tr>
                    <td><?= $offset + $index + 1 ?></td>
                    <td><strong><?= htmlspecialchars($r['room_number'] ?? '-') ?></strong></td>
                    <td>
                        <strong><?= htmlspecialchars($r['ward_name'] ?? '-') ?></strong>
                        <?php if (!empty($r['location'])): ?>
                            <br><span style="color: var(--text-muted); font-size: 0.8rem;"><?= htmlspecialchars($r['location']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="type-badge"><?= ucfirst(htmlspecialchars($r['ward_type'] ?? '-')) ?></span></td>
                    <td><span class="status-badge"><?= htmlspecialchars($room_type_display[$r['room_type']] ?? $r['room_type']) ?></span></td>
                    <td><strong><?= (int)($r['capacity'] ?? 0) ?></strong></td>
                    <td style="white-space: normal; max-width: 220px;">
                        <?= !empty($r['notes']) ? htmlspecialchars($r['notes']) : '<span style="color: var(--text-muted);">-</span>' ?>
                    </td>
                    <?php if ($access === 'full'): ?>
                    <td>
                        <a href="edit_room.php?id=<?= (int)$r['id'] ?>" class="btn-edit" title="Edit"><i class="bi bi-pencil"></i></a>

                        <form method="POST" action="rooms.php" style="display:inline;" onsubmit="return confirm('Delete this room?');">
                            <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="delete_room" value="1">
                            <button type="submit" class="btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <span style="color: var(--text-muted); font-size: 0.9rem;">
            Showing <?= $offset + 1 ?>–<?= min($offset + $per_page, $total_rooms) ?> of <?= $total_rooms ?>
        </span>
        <div class="d-flex gap-2" style="align-items: center;">
            <?php
            $qs = $_GET;
            $qs['page'] = max(1, $page - 1);
            $prev_url = 'rooms.php?' . http_build_query($qs);
            $qs['page'] = min($total_pages, $page + 1);
            $next_url = 'rooms.php?' . http_build_query($qs);
            ?>
            <a href="<?= htmlspecialchars($prev_url) ?>"
               class="btn btn-add"
               style="text-decoration:none; <?= $page <= 1 ? 'opacity:.4; pointer-events:none;' : '' ?>">
                <i class="bi bi-chevron-left"></i> Prev
            </a>

            <?php
            // Show a compact page range: first, ..., current-1, current, current+1, ..., last
            $show = [];
            $show[] = 1;
            for ($i = $page - 1; $i <= $page + 1; $i++) {
                if ($i > 1 && $i < $total_pages) $show[] = $i;
            }
            if ($total_pages > 1) $show[] = $total_pages;
            $show = array_unique($show);
            sort($show);

            $lastShown = 0;
            foreach ($show as $p):
                if ($lastShown && $p - $lastShown > 1):
            ?>
                <span style="color: var(--text-muted); padding: 0 4px;">…</span>
            <?php endif; ?>
                <?php
                $qs2 = $_GET;
                $qs2['page'] = $p;
                $purl = 'rooms.php?' . http_build_query($qs2);
                $is_active = ($p === $page);
                ?>
                <a href="<?= htmlspecialchars($purl) ?>"
                   class="btn btn-add"
                   style="text-decoration:none; min-width: 42px; justify-content: center;
                          <?= $is_active ? 'background: var(--primary-dark);' : 'background: #cbd5e1; color: #0f172a;' ?>">
                    <?= $p ?>
                </a>
            <?php $lastShown = $p; endforeach; ?>

            <a href="<?= htmlspecialchars($next_url) ?>"
               class="btn btn-add"
               style="text-decoration:none; <?= $page >= $total_pages ? 'opacity:.4; pointer-events:none;' : '' ?>">
                Next <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-door-closed" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No rooms found.
        </p>
    <?php endif; ?>
</div>

<script>
// Search on the current page only
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const roomTable = document.getElementById('roomTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = roomTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<?php include 'rooms_footer.php'; ?>
