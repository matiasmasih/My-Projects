<?php
// admin/shifts.php — Modern shift management with toggle

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$flash = '';
$errors = [];

$all_days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $days        = implode(',', array_map('intval', $_POST['days'] ?? []));
        $start_time  = $_POST['start_time'] ?? '';
        $end_time    = $_POST['end_time'] ?? '';
        $break       = (int)($_POST['break_minutes'] ?? 30);
        $color       = trim($_POST['color'] ?? '#3b82f6');
        $description = trim($_POST['description'] ?? '');

        if ($name === '')       $errors[] = 'Shift name is required.';
        if ($days === '')       $errors[] = 'Select at least one day.';
        if ($start_time === '') $errors[] = 'Start time is required.';
        if ($end_time === '')   $errors[] = 'End time is required.';
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#3b82f6';

        if (empty($errors)) {
            try {
                if ($id > 0) {
                    $pdo->prepare("
                        UPDATE shifts SET name = ?, days = ?, start_time = ?, end_time = ?, 
                                          break_minutes = ?, color = ?, description = ?
                        WHERE id = ?
                    ")->execute([$name, $days, $start_time, $end_time, $break, $color, $description, $id]);
                    $flash = 'Shift updated.';
                } else {
                    $pdo->prepare("
                        INSERT INTO shifts (name, days, start_time, end_time, break_minutes, color, description)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$name, $days, $start_time, $end_time, $break, $color, $description]);
                    $flash = 'Shift created.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Could not save: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare("UPDATE shifts SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
            $flash = 'Shift status updated.';
        } catch (PDOException $e) {
            $flash = 'Could not update shift.';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM shifts WHERE id = ?")->execute([$id]);
            $flash = 'Shift deleted.';
        } catch (PDOException $e) {
            $flash = 'Could not delete shift.';
        }
    }
}

// Edit target
$edit_id = (int)($_GET['edit'] ?? 0);
$edit_shift = [
    'id' => 0, 'name' => '', 'days' => '1,2,3,4,5',
    'start_time' => '09:00', 'end_time' => '17:00',
    'break_minutes' => 30, 'color' => '#3b82f6', 'description' => ''
];
if ($edit_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = ? LIMIT 1");
        $stmt->execute([$edit_id]);
        $row = $stmt->fetch();
        if ($row) $edit_shift = $row;
    } catch (PDOException $e) {}
}
$edit_days = explode(',', $edit_shift['days']);

$shifts = [];
try {
    $shifts = $pdo->query("SELECT * FROM shifts ORDER BY start_time")->fetchAll();
} catch (PDOException $e) {}

$page_title = 'Shifts';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Add/Edit form -->
<div class="admin-panel">
    <h3>
        <i class="fas fa-clock"></i>
        <?= $edit_shift['id'] > 0 ? 'Edit Shift' : 'Add New Shift' ?>
    </h3>

    <?php if ($flash): ?>
        <div class="flash flash-success" style="margin-bottom:16px;">
            <i class="fas fa-check-circle"></i> <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="flash flash-error" style="margin-bottom:16px;display:block;">
            <?php foreach ($errors as $err): ?><div><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" style="padding:20px;background:var(--bg-alt);border-radius:12px;">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$edit_shift['id'] ?>">

        <div style="display:grid;grid-template-columns:2fr 2fr 1fr;gap:16px;">
            <div>
                <label>Shift Name *</label>
                <input type="text" name="name" required value="<?= e($edit_shift['name']) ?>" placeholder="Morning Shift">
            </div>
            <div>
                <label>Description</label>
                <input type="text" name="description" value="<?= e($edit_shift['description']) ?>" placeholder="Optional description">
            </div>
            <div>
                <label>Color</label>
                <input type="color" name="color" value="<?= e($edit_shift['color']) ?>" style="height:44px;cursor:pointer;">
            </div>
        </div>

        <div style="margin-top:16px;">
            <label>Working Days *</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                <?php foreach ($all_days as $num => $label): ?>
                    <label style="display:flex;align-items:center;gap:6px;padding:10px 16px;background:var(--card-bg);border:1.5px solid var(--border);border-radius:8px;cursor:pointer;font-weight:600;font-size:0.85rem;transition:all 0.15s;">
                        <input type="checkbox" name="days[]" value="<?= $num ?>" 
                               <?= in_array((string)$num, $edit_days) ? 'checked' : '' ?>
                               style="accent-color:var(--primary);">
                        <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:16px;">
            <div>
                <label>Start Time *</label>
                <input type="time" name="start_time" required value="<?= e(substr($edit_shift['start_time'], 0, 5)) ?>">
            </div>
            <div>
                <label>End Time *</label>
                <input type="time" name="end_time" required value="<?= e(substr($edit_shift['end_time'], 0, 5)) ?>">
            </div>
            <div>
                <label>Break (minutes)</label>
                <input type="number" name="break_minutes" min="0" max="180" value="<?= (int)$edit_shift['break_minutes'] ?>">
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $edit_shift['id'] > 0 ? 'Update Shift' : 'Create Shift' ?>
            </button>
            <?php if ($edit_shift['id'] > 0): ?>
                <a href="shifts.php" class="btn btn-outline">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Shifts cards -->
<div class="admin-panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <h3 style="margin:0;"><i class="fas fa-list"></i> All Shifts (<?= count($shifts) ?>)</h3>
        <input type="text" id="searchBox" placeholder="🔍 Search shifts..." style="max-width:280px;">
    </div>

    <?php if (empty($shifts)): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
            <i class="fas fa-clock" style="font-size:3rem;opacity:0.4;display:block;margin-bottom:14px;"></i>
            <p>No shifts yet. Create your first shift above.</p>
        </div>
    <?php else: ?>
        <div id="shiftGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;">
            <?php foreach ($shifts as $s): ?>
                <?php $s_days = explode(',', $s['days']); ?>
                <div class="shift-card <?= !$s['is_active'] ? 'inactive-card' : '' ?>" 
                     data-name="<?= e(strtolower($s['name'])) ?>" 
                     style="background:var(--card-bg);border:1px solid <?= $s['is_active'] ? 'var(--border)' : '#ef4444' ?>;border-radius:16px;padding:24px;position:relative;overflow:hidden;transition:all 0.2s;">

                    <!-- Color bar (red if inactive) -->
                    <div style="position:absolute;top:0;left:0;right:0;height:4px;background:<?= $s['is_active'] ? e($s['color']) : '#ef4444' ?>;"></div>

                    <!-- INACTIVE badge -->
                    <?php if (!$s['is_active']): ?>
                        <div style="position:absolute;top:16px;right:16px;background:#ef4444;color:#fff;padding:4px 10px;border-radius:6px;font-size:0.68rem;font-weight:800;letter-spacing:0.05em;text-transform:uppercase;box-shadow:0 2px 8px rgba(239,68,68,0.4);">
                            Inactive
                        </div>
                    <?php endif; ?>

                    <!-- Icon + Name -->
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                        <div style="width:48px;height:48px;border-radius:12px;background:<?= $s['is_active'] ? e($s['color']) . '20' : 'rgba(239,68,68,0.15)' ?>;display:grid;place-items:center;color:<?= $s['is_active'] ? e($s['color']) : '#ef4444' ?>;font-size:1.4rem;flex-shrink:0;">
                            <i class="fas fa-<?= $s['is_active'] ? 'clock' : 'clock-rotate-left' ?>"></i>
                        </div>
                        <div style="flex:1;">
                            <h4 style="font-size:1.1rem;margin:0;"><?= e($s['name']) ?></h4>
                            <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
                                <i class="fas fa-hourglass-half"></i> <?= e(substr($s['start_time'], 0, 5)) ?> — <?= e(substr($s['end_time'], 0, 5)) ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($s['description'])): ?>
                        <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:12px;line-height:1.5;">
                            <?= e($s['description']) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Days pills -->
                    <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:14px;">
                        <?php foreach ($all_days as $num => $label): ?>
                            <span style="padding:3px 8px;border-radius:6px;font-size:0.72rem;font-weight:700;
                                        background:<?= in_array((string)$num, $s_days) ? ($s['is_active'] ? e($s['color']) . '20' : 'rgba(239,68,68,0.15)') : 'var(--bg-alt)' ?>;
                                        color:<?= in_array((string)$num, $s_days) ? ($s['is_active'] ? e($s['color']) : '#ef4444') : 'var(--text-light)' ?>;">
                                <?= e($label) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($s['break_minutes'] > 0): ?>
                        <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:14px;">
                            <i class="fas fa-mug-hot"></i> <?= (int)$s['break_minutes'] ?> min break
                        </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                            <button type="submit" class="badge <?= $s['is_active'] ? 'badge-success' : 'badge-danger' ?>" 
                                    style="border:none;cursor:pointer;padding:6px 12px;font-family:inherit;"
                                    title="Click to toggle">
                                <i class="fas fa-<?= $s['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i>
                                <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                            </button>
                        </form>
                        <div style="display:flex;gap:6px;">
                            <a href="shifts.php?edit=<?= (int)$s['id'] ?>" class="btn btn-outline btn-sm" title="Edit">
                                <i class="fas fa-pen"></i>
                            </a>
                            <form method="post" style="display:inline;" 
                                  onsubmit="return confirm('Delete shift <?= e($s['name']) ?>?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.shift-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    border-color: var(--primary);
}
.shift-card.inactive-card {
    opacity: 0.7;
    background: rgba(239,68,68,0.03);
}
.shift-card.inactive-card:hover {
    border-color: #ef4444 !important;
    opacity: 1;
}
</style>

<script>
document.getElementById('searchBox')?.addEventListener('input', function(e) {
    var q = e.target.value.toLowerCase();
    document.querySelectorAll('.shift-card').forEach(function(card) {
        var name = card.getAttribute('data-name') || '';
        card.style.display = name.includes(q) ? '' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
