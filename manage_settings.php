<?php
// manage_settings.php — Admin + Librarian: edit library-wide rules & settings
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('manage_settings');
if (my_access('manage_settings') !== 'full') {
    header('Location: index.php'); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Settings';
$error = '';

// ============================================================
// SAVE (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    try {
        $pdo->beginTransaction();

        // Load all current settings into a map
        $current = [];
        foreach ($pdo->query("SELECT setting_key, setting_value, setting_type FROM settings")->fetchAll() as $r) {
            $current[$r['setting_key']] = $r;
        }

        $changes = [];
        $posted  = $_POST['settings'] ?? [];

        foreach ($posted as $key => $val) {
            if (!isset($current[$key])) continue;

            $val = trim((string)$val);
            $type = $current[$key]['setting_type'];

            // Validate by type
            if ($type === 'int' && !preg_match('/^-?\d+$/', $val)) {
                throw new Exception("'{$key}' must be an integer.");
            }
            if ($type === 'decimal' && !preg_match('/^-?\d+(\.\d+)?$/', $val)) {
                throw new Exception("'{$key}' must be a number.");
            }

            // Range limits (business rules)
            $ranges = [
                'loan_days'            => [1, 365],
                'max_renewals'         => [0, 20],
                'max_loans_per_member' => [1, 100],
                'fine_per_day'         => [0, 100],
                'max_fine_per_book'    => [0, 1000],
                'fine_block_threshold' => [0, 1000],
            ];
            if (isset($ranges[$key])) {
                [$min, $max] = $ranges[$key];
                $num = (float)$val;
                if ($num < $min || $num > $max) {
                    throw new Exception("'{$key}' must be between {$min} and {$max}.");
                }
            }

            // Only update if changed
            if ($val !== (string)$current[$key]['setting_value']) {
                $pdo->prepare("UPDATE settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?")
                    ->execute([$val, $_SESSION['user_id'] ?? null, $key]);
                $changes[] = "{$key}: {$current[$key]['setting_value']} → {$val}";
            }
        }

        if ($changes) {
            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type)
                           VALUES (?, 'settings_updated', ?, 'settings')")
                ->execute([
                    $_SESSION['user_id'] ?? null,
                    "Updated settings — " . implode('; ', $changes)
                ]);
        }

        $pdo->commit();
        header('Location: manage_settings.php?saved=1');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// ============================================================
// LOAD SETTINGS
// ============================================================
$rows = $pdo->query(
    "SELECT s.*, u.username AS updater
     FROM settings s
     LEFT JOIN users u ON u.id = s.updated_by
     ORDER BY s.category, s.setting_key"
)->fetchAll();

$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['category']][] = $r;
}

$category_titles = [
    'circulation' => ['icon' => 'bi-arrow-left-right', 'label' => 'Circulation Rules'],
    'fines'       => ['icon' => 'bi-cash-coin',       'label' => 'Fines & Charges'],
    'general'     => ['icon' => 'bi-gear',            'label' => 'General'],
];

include 'header.php';
?>

<style>
    .main-content { min-width: 0; max-width: 100%; overflow-x: hidden; }
    .main-container { min-width: 0; }

    .settings-card {
        background: var(--card-bg); border-radius: 14px; padding: 22px;
        box-shadow: var(--shadow); border: 1px solid rgba(13,148,136,0.08);
        margin-bottom: 22px;
    }
    .settings-card h3 {
        margin: 0 0 18px; display: flex; align-items: center; gap: 8px;
        font-size: 1.05rem; color: var(--text);
        padding-bottom: 12px; border-bottom: 1px solid rgba(0,0,0,0.06);
    }
    .settings-card h3 i { color: var(--primary); }

    .setting-row {
        display: grid; grid-template-columns: 1fr 260px;
        gap: 20px; padding: 14px 0;
        border-bottom: 1px solid rgba(0,0,0,0.04);
        align-items: start;
    }
    .setting-row:last-child { border-bottom: none; padding-bottom: 0; }
    @media (max-width: 700px) { .setting-row { grid-template-columns: 1fr; } }

    .setting-label { font-weight: 600; color: var(--text); font-size: .95rem; margin-bottom: 4px; }
    .setting-desc { color: var(--text-muted); font-size: .82rem; line-height: 1.4; }
    .setting-meta { color: var(--text-muted); font-size: .72rem; margin-top: 6px; font-style: italic; }

    .setting-input-wrap { display: flex; align-items: center; gap: 8px; }
    .setting-input-wrap input,
    .setting-input-wrap select {
        width: 100%; padding: 9px 12px; border: 1px solid var(--border);
        border-radius: 8px; font-size: .92rem; background: var(--card-bg); color: var(--text);
        font-family: inherit;
    }
    .setting-input-wrap input:focus {
        outline: none; border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(13,148,136,0.12);
    }
    .setting-unit { color: var(--text-muted); font-size: .82rem; font-weight: 600; white-space: nowrap; }

    .saved-banner {
        display: block; padding: 12px 16px; margin-bottom: 14px;
        background: #d1fae5; color: #065f46; border-radius: 10px;
        font-weight: 600; font-size: .9rem;
    }
    .save-bar {
        position: sticky; bottom: 16px;
        background: var(--card-bg); padding: 14px 20px; border-radius: 12px;
        box-shadow: 0 6px 20px rgba(13,148,136,0.15);
        border: 1px solid rgba(13,148,136,0.15);
        display: flex; justify-content: space-between; align-items: center;
        margin-top: 20px;
    }
</style>

<h2 style="margin-bottom:18px;">
    <i class="bi bi-sliders" style="color:var(--primary);"></i> Library Settings
</h2>

<?php if (isset($_GET['saved'])): ?>
    <div class="saved-banner">
        <i class="bi bi-check-circle-fill"></i> Settings saved. They take effect immediately across the app.
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<p style="color:var(--text-muted);font-size:.9rem;margin-bottom:20px;">
    These rules apply to the whole library. Changes take effect immediately —
    for example, changing "Loan period" affects new checkouts right away.
    Every change is recorded in the <a href="activity_log.php" style="color:var(--primary-dark);">Activity Log</a>.
</p>

<form method="POST" action="manage_settings.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <?php foreach ($category_titles as $cat_key => $meta): ?>
        <?php if (empty($grouped[$cat_key])) continue; ?>

        <div class="settings-card">
            <h3>
                <i class="bi <?= $meta['icon'] ?>"></i>
                <?= htmlspecialchars($meta['label']) ?>
            </h3>

            <?php foreach ($grouped[$cat_key] as $s):
                // Determine input type + unit
                $input_type = 'text';
                $unit = '';
                if ($s['setting_type'] === 'int')     { $input_type = 'number'; $unit = ''; }
                if ($s['setting_type'] === 'decimal') { $input_type = 'number'; $unit = 'EUR'; }

                // Special-case unit per key
                if ($s['setting_key'] === 'loan_days')            $unit = 'days';
                if ($s['setting_key'] === 'max_renewals')         $unit = 'times';
                if ($s['setting_key'] === 'max_loans_per_member') $unit = 'books';
            ?>
                <div class="setting-row">
                    <div>
                        <div class="setting-label"><?= htmlspecialchars($s['label']) ?></div>
                        <div class="setting-desc"><?= htmlspecialchars($s['description'] ?? '') ?></div>
                        <?php if ($s['updater']): ?>
                            <div class="setting-meta">
                                last changed by @<?= htmlspecialchars($s['updater']) ?>
                                on <?= htmlspecialchars(substr($s['updated_at'],0,16)) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="setting-input-wrap">
                            <?php if ($s['setting_key'] === 'default_language'): ?>
                                <select name="settings[<?= htmlspecialchars($s['setting_key']) ?>]">
                                    <?php foreach (['en'=>'English','fi'=>'Finnish','fa'=>'Persian'] as $code=>$label): ?>
                                        <option value="<?= $code ?>" <?= $s['setting_value']===$code?'selected':'' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="<?= $input_type ?>"
                                       name="settings[<?= htmlspecialchars($s['setting_key']) ?>]"
                                       value="<?= htmlspecialchars($s['setting_value']) ?>"
                                       <?= $input_type === 'number' ? 'step="any"' : '' ?>>
                                <?php if ($unit): ?>
                                    <span class="setting-unit"><?= htmlspecialchars($unit) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="save-bar">
        <div style="color:var(--text-muted);font-size:.85rem;">
            <i class="bi bi-info-circle"></i> Changes apply immediately to all users.
        </div>
        <button type="submit" class="btn-add">
            <i class="bi bi-check-circle"></i> Save Settings
        </button>
    </div>
</form>

<?php include 'footer.php'; ?>
