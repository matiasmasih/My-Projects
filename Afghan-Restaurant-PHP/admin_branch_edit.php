<?php
// admin_branch_edit.php — Edit a branch

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    header('Location: ' . SITE_URL . 'admin_branches.php');
    exit;
}

$branch = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $branch = $stmt->fetch();
    if (!$branch) {
        header('Location: ' . SITE_URL . 'admin_branches.php');
        exit;
    }
} catch (PDOException $e) {
    header('Location: ' . SITE_URL . 'admin_branches.php');
    exit;
}

$flash = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch['name']          = trim($_POST['name'] ?? '');
    $branch['city']          = trim($_POST['city'] ?? '');
    $branch['address']       = trim($_POST['address'] ?? '');
    $branch['phone']         = trim($_POST['phone'] ?? '');
    $branch['email']         = trim($_POST['email'] ?? '');
    $branch['default_lang']  = $_POST['default_lang'] ?? 'en';
    $branch['currency_code'] = $_POST['currency_code'] ?? 'EUR';
    $branch['timezone']      = trim($_POST['timezone'] ?? 'Europe/Helsinki');
    $branch['is_active']     = isset($_POST['is_active']) ? 1 : 0;
    $branch['sort_order']    = (int)($_POST['sort_order'] ?? 0);

    if ($branch['name'] === '') $errors[] = 'Name is required.';
    if ($branch['city'] === '') $errors[] = 'City is required.';
    if (!in_array($branch['default_lang'], ['en','fi','fa'], true)) $branch['default_lang'] = 'en';
    if (!in_array($branch['currency_code'], ['EUR','USD','AFN'], true)) $branch['currency_code'] = 'EUR';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE branches SET
                    name = ?, city = ?, address = ?, phone = ?, email = ?,
                    default_lang = ?, currency_code = ?, timezone = ?,
                    is_active = ?, sort_order = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $branch['name'], $branch['city'], $branch['address'], $branch['phone'], $branch['email'],
                $branch['default_lang'], $branch['currency_code'], $branch['timezone'],
                $branch['is_active'], $branch['sort_order'], $id
            ]);
            $flash = t('admin_branch_saved');
        } catch (PDOException $e) {
            error_log('admin_branch_edit: ' . $e->getMessage());
            $errors[] = 'Save failed.';
        }
    }
}

$hours = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM opening_hours WHERE branch_id = ? ORDER BY day_of_week");
    $stmt->execute([$id]);
    $hours = $stmt->fetchAll();
} catch (PDOException $e) {}

$day_names = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

$page_title = t('admin_edit_branch');
require_once __DIR__ . '/includes/admin_header.php';
?>



        <div class="admin-hero">
            <div>
                <h1><?= e(t('admin_edit_branch')) ?></h1>
                <p><?= e($branch['name']) ?></p>
            </div>
            <a href="admin_branches.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-arrow-left"></i> <?= e(t('admin_back_to_list')) ?>
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= e($flash) ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="flash flash-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
            </div>
        <?php endif; ?>

        <form method="post" class="admin-panel">
            <div class="form-row">
                <div class="form-field">
                    <label for="name"><?= e(t('admin_branch_name')) ?> *</label>
                    <input type="text" id="name" name="name" required value="<?= e($branch['name']) ?>">
                </div>
                <div class="form-field">
                    <label for="city"><?= e(t('admin_city')) ?> *</label>
                    <input type="text" id="city" name="city" required value="<?= e($branch['city']) ?>">
                </div>
            </div>

            <div class="form-field">
                <label for="address"><?= e(t('admin_address_label')) ?></label>
                <input type="text" id="address" name="address" value="<?= e($branch['address']) ?>">
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="phone"><?= e(t('admin_phone')) ?></label>
                    <input type="tel" id="phone" name="phone" value="<?= e($branch['phone']) ?>">
                </div>
                <div class="form-field">
                    <label for="email"><?= e(t('admin_email')) ?></label>
                    <input type="email" id="email" name="email" value="<?= e($branch['email']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="default_lang"><?= e(t('admin_default_language')) ?></label>
                    <select id="default_lang" name="default_lang">
                        <option value="en" <?= $branch['default_lang'] === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="fi" <?= $branch['default_lang'] === 'fi' ? 'selected' : '' ?>>Suomi</option>
                        <option value="fa" <?= $branch['default_lang'] === 'fa' ? 'selected' : '' ?>>فارسی</option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="currency_code"><?= e(t('admin_currency')) ?></label>
                    <select id="currency_code" name="currency_code">
                        <option value="EUR" <?= $branch['currency_code'] === 'EUR' ? 'selected' : '' ?>>€ EUR</option>
                        <option value="USD" <?= $branch['currency_code'] === 'USD' ? 'selected' : '' ?>>$ USD</option>
                        <option value="AFN" <?= $branch['currency_code'] === 'AFN' ? 'selected' : '' ?>>؋ AFN</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="timezone"><?= e(t('admin_timezone')) ?></label>
                    <input type="text" id="timezone" name="timezone" value="<?= e($branch['timezone']) ?>">
                </div>
                <div class="form-field">
                    <label for="sort_order"><?= e(t('admin_sort_order')) ?></label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= (int)$branch['sort_order'] ?>">
                </div>
            </div>

            <div class="form-field">
                <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= $branch['is_active'] ? 'checked' : '' ?> style="width:auto;">
                    <?= e(t('admin_active')) ?>
                </label>
            </div>

            <button type="submit" class="btn btn-accent btn-lg" style="margin-top:20px;">
                <i class="fas fa-save"></i> <?= e(t('admin_save_branch')) ?>
            </button>
        </form>

        <?php if (!empty($hours)): ?>
            <div class="admin-panel">
                <h3><i class="fas fa-clock"></i> <?= e(t('admin_opening_hours')) ?></h3>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?= e(t('admin_day')) ?></th>
                                <th><?= e(t('admin_open')) ?></th>
                                <th><?= e(t('admin_close')) ?></th>
                                <th><?= e(t('admin_status')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hours as $h): ?>
                                <tr>
                                    <td><?= e($day_names[$h['day_of_week']] ?? '?') ?></td>
                                    <td><?= $h['is_closed'] ? '—' : e(substr($h['open_time'], 0, 5)) ?></td>
                                    <td><?= $h['is_closed'] ? '—' : e(substr($h['close_time'], 0, 5)) ?></td>
                                    <td>
                                        <?php if ($h['is_closed']): ?>
                                            <span class="status-badge status-cancelled"><?= e(t('admin_closed')) ?></span>
                                        <?php else: ?>
                                            <span class="status-badge status-ready"><?= e(t('admin_open')) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
