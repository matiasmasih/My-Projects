<?php
// my_preferences.php — Member/Librarian/Admin: personal preferences
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('my_preferences');
if (my_access('my_preferences') !== 'full') {
    header('Location: index.php'); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Preferences';
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) { header('Location: login.php'); exit; }

$success = '';
$error   = '';

// ============================================================
// SAVE (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $default_branch_id = (int)($_POST['default_branch_id'] ?? 0) ?: null;
    $notify_email      = isset($_POST['notify_email']) ? 1 : 0;
    $notify_due_soon   = isset($_POST['notify_due_soon']) ? 1 : 0;
    $notify_overdue    = isset($_POST['notify_overdue']) ? 1 : 0;
    $notify_hold_ready = isset($_POST['notify_hold_ready']) ? 1 : 0;
    $notify_fine_issued= isset($_POST['notify_fine_issued']) ? 1 : 0;
    $hide_history      = isset($_POST['hide_reading_history']) ? 1 : 0;
    $favourite_genres  = trim($_POST['favourite_genres'] ?? '');
    $notes             = trim($_POST['notes'] ?? '');

    // Validate branch exists
    if ($default_branch_id) {
        $chk = $pdo->prepare("SELECT id FROM branches WHERE id = ? AND is_active = 1");
        $chk->execute([$default_branch_id]);
        if (!$chk->fetchColumn()) {
            $error = 'Invalid branch.';
            $default_branch_id = null;
        }
    }

    if (!$error) {
        try {
            // Upsert
            $pdo->prepare(
                "INSERT INTO user_preferences
                 (user_id, default_branch_id, notify_email, notify_due_soon, notify_overdue,
                  notify_hold_ready, notify_fine_issued, hide_reading_history, favourite_genres, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                  default_branch_id = VALUES(default_branch_id),
                  notify_email      = VALUES(notify_email),
                  notify_due_soon   = VALUES(notify_due_soon),
                  notify_overdue    = VALUES(notify_overdue),
                  notify_hold_ready = VALUES(notify_hold_ready),
                  notify_fine_issued= VALUES(notify_fine_issued),
                  hide_reading_history = VALUES(hide_reading_history),
                  favourite_genres  = VALUES(favourite_genres),
                  notes             = VALUES(notes)"
            )->execute([
                $user_id,
                $default_branch_id,
                $notify_email, $notify_due_soon, $notify_overdue,
                $notify_hold_ready, $notify_fine_issued,
                $hide_history,
                $favourite_genres !== '' ? $favourite_genres : null,
                $notes !== '' ? $notes : null
            ]);

            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'preferences_updated', 'Updated own preferences', 'user', ?)")
                ->execute([$user_id, $user_id]);

            $success = 'Preferences saved.';
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// ============================================================
// LOAD DATA
// ============================================================
$prefs = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$prefs->execute([$user_id]);
$prefs = $prefs->fetch() ?: [
    'default_branch_id'    => null,
    'notify_email'         => 1,
    'notify_due_soon'      => 1,
    'notify_overdue'       => 1,
    'notify_hold_ready'    => 1,
    'notify_fine_issued'   => 1,
    'hide_reading_history' => 0,
    'favourite_genres'     => '',
    'notes'                => ''
];

$branches = $pdo->query("SELECT id, name, city FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();
$genres   = $pdo->query("SELECT id, name FROM genres ORDER BY name")->fetchAll();

include 'header.php';
?>

<style>
    /* === Modern Preferences UI === */
    .pref-wrap {
        max-width: 900px;
        margin: 0 auto;
    }

    .pref-panel {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 28px 30px;
        box-shadow: 0 2px 12px rgba(13,148,136,0.06);
        border: 1px solid rgba(13,148,136,0.08);
        margin-bottom: 18px;
        transition: box-shadow .2s;
    }
    .pref-panel:hover {
        box-shadow: 0 4px 20px rgba(13,148,136,0.10);
    }

    .pref-panel h3 {
        margin: 0 0 22px;
        font-size: 1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--text);
        letter-spacing: -.01em;
    }
    .pref-panel h3 i {
        display: grid;
        place-items: center;
        width: 32px; height: 32px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff;
        border-radius: 10px;
        font-size: .9rem;
        box-shadow: 0 3px 10px rgba(13,148,136,0.25);
    }

    /* Rows for toggles */
    .pref-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 24px;
        padding: 16px 0;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .pref-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    .pref-row:first-of-type { padding-top: 0; }
    .pref-row .text { flex: 1; min-width: 0; }
    .pref-row .label {
        font-weight: 600;
        color: var(--text);
        font-size: .94rem;
        margin-bottom: 3px;
    }
    .pref-row .desc {
        font-size: .82rem;
        color: var(--text-muted);
        line-height: 1.45;
    }
    .pref-row .control { flex-shrink: 0; }

    /* Fields */
    .pref-field { margin-bottom: 20px; }
    .pref-field:last-child { margin-bottom: 0; }
    .pref-field label {
        display: block;
        font-weight: 600;
        font-size: .88rem;
        margin-bottom: 8px;
        color: var(--text);
    }
    .pref-field input,
    .pref-field select,
    .pref-field textarea {
        width: 100%;
        padding: 11px 14px;
        border: 1.5px solid var(--border);
        border-radius: 12px;
        font-size: .94rem;
        background: var(--card-bg);
        color: var(--text);
        font-family: inherit;
        transition: border-color .15s, box-shadow .15s;
    }
    .pref-field input:focus,
    .pref-field select:focus,
    .pref-field textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(13,148,136,0.10);
    }
    .pref-field textarea {
        resize: vertical;
        min-height: 90px;
        line-height: 1.6;
    }

    /* Modern toggle switch */
    .switch {
        position: relative;
        display: inline-block;
        width: 48px;
        height: 28px;
    }
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background-color: #cbd5e1;
        transition: .25s;
        border-radius: 28px;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.08);
    }
    .slider:before {
        content: "";
        position: absolute;
        height: 22px;
        width: 22px;
        left: 3px;
        bottom: 3px;
        background: #fff;
        transition: .25s;
        border-radius: 50%;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    .switch input:checked + .slider {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    }
    .switch input:checked + .slider:before {
        transform: translateX(20px);
    }
    .switch input:focus-visible + .slider {
        box-shadow: 0 0 0 4px rgba(13,148,136,0.20);
    }

    /* Genre pills — modern */
    .genre-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 4px;
    }
    .genre-pill {
        display: inline-flex;
        align-items: center;
        padding: 8px 16px;
        border-radius: 24px;
        background: #f8fafc;
        color: var(--text);
        font-size: .86rem;
        font-weight: 500;
        cursor: pointer;
        user-select: none;
        border: 1.5px solid transparent;
        transition: all .18s ease;
    }
    .genre-pill:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        transform: translateY(-1px);
    }
    .genre-pill input { display: none; }
    .genre-pill.on {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(13,148,136,0.28);
        font-weight: 600;
    }
    .genre-pill.on:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(13,148,136,0.35);
    }

    /* Save bar */
    .pref-savebar {
        position: sticky;
        bottom: 20px;
        background: var(--card-bg);
        border-radius: 14px;
        padding: 16px 22px;
        box-shadow: 0 8px 32px rgba(13,148,136,0.15);
        border: 1px solid rgba(13,148,136,0.15);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 4px;
        backdrop-filter: blur(8px);
    }
    .pref-savebar .hint {
        color: var(--text-muted);
        font-size: .85rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .btn-save-pref {
        padding: 11px 24px;
        border-radius: 10px;
        border: none;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff;
        font-weight: 700;
        font-size: .92rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 14px rgba(13,148,136,0.30);
        transition: all .15s;
    }
    .btn-save-pref:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(13,148,136,0.40);
    }
    .btn-save-pref:active { transform: translateY(0); }
</style>

<div class="pref-wrap">

<h2 style="margin: 0 0 22px; font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em;">
    <i class="bi bi-sliders" style="color:var(--primary);"></i> My Preferences
</h2>

<?php if ($success): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST" action="my_preferences.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <!-- LIBRARY & READING -->
    <div class="pref-panel">
        <h3><i class="bi bi-book"></i> Library &amp; Reading</h3>

        <div class="pref-field">
            <label>Preferred branch (for hold pickups)</label>
            <select name="default_branch_id">
                <option value="0">— Any branch —</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= (int)$prefs['default_branch_id']===(int)$b['id']?'selected':'' ?>>
                        <?= htmlspecialchars($b['name']) ?><?= $b['city'] ? ' · ' . htmlspecialchars($b['city']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="pref-field">
            <label>Favourite genres</label>
             <div class="genre-pills" id="genrePills">
                <?php
                    $favs = array_filter(array_map('trim', explode(',', (string)$prefs['favourite_genres'])));
                ?>
                <?php foreach ($genres as $g): ?>
                    <?php $is_on = in_array($g['name'], $favs, true); ?>
                    <label class="genre-pill <?= $is_on ? 'on' : '' ?>">
                        <input type="checkbox" value="<?= htmlspecialchars($g['name']) ?>"
                               <?= $is_on ? 'checked' : '' ?>>
                        <?= htmlspecialchars($g['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="favourite_genres" id="favGenresInput"
                   value="<?= htmlspecialchars($prefs['favourite_genres'] ?? '') ?>">
        </div>
    </div>

    <!-- NOTIFICATIONS -->
    <div class="pref-panel">
        <h3><i class="bi bi-bell"></i> Notifications</h3>

        <div class="pref-row">
            <div class="text">
                <div class="label">Email notifications</div>
                <div class="desc">Master switch — turn off to disable all email notifications.</div>
            </div>
            <div class="control">
                <label class="switch">
                    <input type="checkbox" name="notify_email" value="1" <?= $prefs['notify_email'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="pref-row">
            <div class="text">
                <div class="label">Books due soon</div>
                <div class="desc">Remind me 3 days before a loan is due.</div>
            </div>
            <div class="control">
                <label class="switch">
                    <input type="checkbox" name="notify_due_soon" value="1" <?= $prefs['notify_due_soon'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="pref-row">
            <div class="text">
                <div class="label">Overdue books</div>
                <div class="desc">Alert me when a loan becomes overdue.</div>
            </div>
            <div class="control">
                <label class="switch">
                    <input type="checkbox" name="notify_overdue" value="1" <?= $prefs['notify_overdue'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="pref-row">
            <div class="text">
                <div class="label">Hold ready for pickup</div>
                <div class="desc">Notify me when a reserved book is available.</div>
            </div>
            <div class="control">
                <label class="switch">
                    <input type="checkbox" name="notify_hold_ready" value="1" <?= $prefs['notify_hold_ready'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <div class="pref-row">
            <div class="text">
                <div class="label">Fine issued</div>
                <div class="desc">Alert me when a new fine is added to my account.</div>
            </div>
            <div class="control">
                <label class="switch">
                    <input type="checkbox" name="notify_fine_issued" value="1" <?= $prefs['notify_fine_issued'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>
    </div>

    <!-- PRIVACY -->
    <div class="pref-panel">
        <h3><i class="bi bi-shield-check"></i> Privacy</h3>

        <div class="pref-row">
            <div class="text">
                <div class="label">Hide my reading history</div>
                <div class="desc">When enabled, your borrowing history is not shown to other members.</div>
            </div>
            <div class="control">
                <label class="switch">
                    <input type="checkbox" name="hide_reading_history" value="1" <?= $prefs['hide_reading_history'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>
    </div>

    <!-- NOTES -->
    <div class="pref-panel">
        <h3><i class="bi bi-journal-text"></i> Personal Notes</h3>
        <div class="pref-field">
            <label>Only visible to you</label>
            <textarea name="notes" rows="4"
                      placeholder="e.g. books I want to read next"><?= htmlspecialchars($prefs['notes'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- SAVE BAR -->
    <div class="pref-savebar">
        <div class="hint">
            <i class="bi bi-info-circle"></i> Changes apply immediately
        </div>
        <button type="submit" class="btn-save-pref">
            <i class="bi bi-check-circle-fill"></i> Save Preferences
        </button>
    </div>
</form>

</div>

<script>
(function () {
    const pills = document.querySelectorAll('#genrePills .genre-pill');
    const hidden = document.getElementById('favGenresInput');

    function sync() {
        const on = [];
        pills.forEach(p => {
            const cb = p.querySelector('input');
            if (cb.checked) { p.classList.add('on'); on.push(cb.value); }
            else            { p.classList.remove('on'); }
        });
        hidden.value = on.join(', ');
    }

    pills.forEach(p => {
        const cb = p.querySelector('input');
        cb.addEventListener('change', sync);
    });

    sync();
})();
</script>

<?php include 'footer.php'; ?>
