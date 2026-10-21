<?php
// admin_settings.php — Site settings
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/i18n.php';

require_login('admin_login.php');

$success = '';
$error   = '';

// -------- SAVE --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['settings'] ?? [];

    if (!empty($posted) && is_array($posted)) {
        $stmt = $conn->prepare(
            "UPDATE site_settings SET setting_value = ? WHERE setting_key = ?"
        );
        foreach ($posted as $key => $val) {
            $key = trim($key);
            $val = trim((string)$val);
            if ($key !== '') {
                $stmt->bind_param("ss", $val, $key);
                $stmt->execute();
            }
        }
        $success = t('settings_saved');
    }
}

// -------- LOAD --------
$settings = [];
$result = $conn->query("SELECT * FROM site_settings ORDER BY id");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[] = $row;
    }
}

$page_title = t('settings_title');
require_once __DIR__ . '/includes/header.php';
?>

<section class="dash-page">
    <div class="container">

        <div class="editor-top">
            <a href="admin_dashboard.php" class="post-back">
                <i class="fas fa-arrow-left"></i> <?= htmlspecialchars(t('post_back_dash')) ?>
            </a>
            <h1><?= htmlspecialchars(t('settings_title')) ?></h1>
        </div>

        <?php if ($success): ?>
            <div class="dash-flash dash-flash-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="dash-flash dash-flash-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_settings.php" class="settings-form">
            <div class="settings-grid">
                <?php foreach ($settings as $s):
                    $key   = $s['setting_key'];
                    $val   = $s['setting_value'];
                    $type  = $s['setting_type'];
                    $label = t('setting_' . $key);
                    if ($label === 'setting_' . $key) $label = ucwords(str_replace('_', ' ', $key));
                ?>
                    <div class="settings-field <?= $type === 'textarea' ? 'span2' : '' ?>">
                        <label for="s_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>

                        <?php if ($type === 'textarea'): ?>
                            <textarea id="s_<?= htmlspecialchars($key) ?>"
                                      name="settings[<?= htmlspecialchars($key) ?>]"
                                      rows="3"><?= htmlspecialchars($val) ?></textarea>

                        <?php elseif ($type === 'color'): ?>
                            <div class="color-input-wrap">
                                <input type="color" id="s_<?= htmlspecialchars($key) ?>"
                                       name="settings[<?= htmlspecialchars($key) ?>]"
                                       value="<?= htmlspecialchars($val) ?>">
                                <input type="text" class="color-text"
                                       value="<?= htmlspecialchars($val) ?>"
                                       readonly>
                            </div>

                        <?php elseif ($type === 'boolean'): ?>
                            <label class="switch">
                                <input type="checkbox" name="settings[<?= htmlspecialchars($key) ?>]"
                                       value="1" <?= $val ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>

                        <?php else: ?>
                            <input type="text" id="s_<?= htmlspecialchars($key) ?>"
                                   name="settings[<?= htmlspecialchars($key) ?>]"
                                   value="<?= htmlspecialchars($val) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="settings-actions">
                <a href="admin_dashboard.php" class="btn btn-outline"><?= htmlspecialchars(t('post_cancel')) ?></a>
                <button type="submit" class="btn btn-accent">
                    <i class="fas fa-check"></i> <?= htmlspecialchars(t('settings_save')) ?>
                </button>
            </div>
        </form>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
