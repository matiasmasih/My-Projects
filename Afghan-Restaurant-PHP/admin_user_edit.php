<?php
// admin_user_edit.php — Add or edit staff/admin account

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . 'admin_login.php');
    exit;
}

$lang = current_language();

$id = (int)($_GET['id'] ?? 0);
$is_new = ($id === 0);

$errors = [];
$flash  = '';

// Defaults
$user = [
    'username'  => '',
    'full_name' => '',
    'email'     => '',
    'role'      => 'staff',
    'branch_id' => null,
    'is_active' => 1,
];

// Load existing user
if (!$is_new) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            header('Location: ' . SITE_URL . 'admin_users.php');
            exit;
        }
        $user = $row;
    } catch (PDOException $e) {
        header('Location: ' . SITE_URL . 'admin_users.php');
        exit;
    }
}

// Load branches for dropdown
$branches = [];
try {
    $branches = $pdo->query("SELECT id, name, name_fi, name_fa FROM branches WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
} catch (PDOException $e) {}

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user['username']  = trim($_POST['username'] ?? '');
    $user['full_name'] = trim($_POST['full_name'] ?? '');
    $user['email']     = trim($_POST['email'] ?? '');
    $user['role']      = $_POST['role'] ?? 'staff';
    $user['branch_id'] = ($_POST['branch_id'] ?? '') !== '' ? (int)$_POST['branch_id'] : null;
    $user['is_active'] = isset($_POST['is_active']) ? 1 : 0;

    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validate
    if ($user['username'] === '')  $errors[] = t('admin_missing_fields');
    if ($user['full_name'] === '') $errors[] = t('admin_missing_fields');
    if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) $errors[] = t('admin_email_taken');
    if (!in_array($user['role'], ['staff','manager','admin'], true)) $user['role'] = 'staff';

    // Staff and Manager MUST have a branch
    if ($user['role'] !== 'admin' && $user['branch_id'] === null) {
        $errors[] = t('admin_role_hint');
    }

    // Password rules
    if ($is_new) {
        if (strlen($password) < 6)         $errors[] = t('admin_user_password_min');
        if ($password !== $password2)      $errors[] = t('admin_user_password_mismatch');
    } else {
        if ($password !== '' || $password2 !== '') {
            if (strlen($password) < 6)     $errors[] = t('admin_user_password_min');
            if ($password !== $password2)  $errors[] = t('admin_user_password_mismatch');
        }
    }

    // Username uniqueness
    if (empty($errors)) {
        try {
            $sql = "SELECT id FROM admin_users WHERE username = ?";
            $params = [$user['username']];
            if (!$is_new) { $sql .= " AND id != ?"; $params[] = $id; }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) $errors[] = t('admin_username_taken');
        } catch (PDOException $e) {}
    }

    // Email uniqueness
    if (empty($errors)) {
        try {
            $sql = "SELECT id FROM admin_users WHERE email = ?";
            $params = [$user['email']];
            if (!$is_new) { $sql .= " AND id != ?"; $params[] = $id; }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) $errors[] = t('admin_email_taken');
        } catch (PDOException $e) {}
    }

    if (empty($errors)) {
        try {
            if ($is_new) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO admin_users (username, password_hash, full_name, email, role, branch_id, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user['username'], $hash, $user['full_name'], $user['email'],
                    $user['role'], $user['branch_id'], $user['is_active']
                ]);
                header('Location: ' . SITE_URL . 'admin_users.php');
                exit;
            } else {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE admin_users SET
                            username = ?, password_hash = ?, full_name = ?, email = ?,
                            role = ?, branch_id = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $user['username'], $hash, $user['full_name'], $user['email'],
                        $user['role'], $user['branch_id'], $user['is_active'], $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE admin_users SET
                            username = ?, full_name = ?, email = ?,
                            role = ?, branch_id = ?, is_active = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $user['username'], $user['full_name'], $user['email'],
                        $user['role'], $user['branch_id'], $user['is_active'], $id
                    ]);
                }
                $flash = t('admin_user_saved');
            }
        } catch (PDOException $e) {
            error_log('admin_user_edit: ' . $e->getMessage());
            $errors[] = t('admin_generic_fail');
        }
    }
}

$page_title = $is_new ? t('admin_new_user') : t('admin_edit_user');
require_once __DIR__ . '/includes/admin_header.php';
?>



        <div class="admin-hero">
            <div>
                <h1><?= $is_new ? e(t('admin_new_user')) : e(t('admin_edit_user')) ?></h1>
                <p><?= e($is_new ? t('admin_users_sub') : $user['username']) ?></p>
            </div>
            <a href="admin_users.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-arrow-left"></i> <?= e(t('admin_back_to_list')) ?>
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= e($flash) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="flash flash-error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" class="admin-panel">

            <div class="form-row">
                <div class="form-field">
                    <label for="username"><?= e(t('admin_user_field_username')) ?> *</label>
                    <input type="text" id="username" name="username" required value="<?= e($user['username']) ?>">
                </div>
                <div class="form-field">
                    <label for="full_name"><?= e(t('admin_user_field_fullname')) ?> *</label>
                    <input type="text" id="full_name" name="full_name" required value="<?= e($user['full_name']) ?>">
                </div>
            </div>

            <div class="form-field">
                <label for="email"><?= e(t('admin_user_field_email')) ?> *</label>
                <input type="email" id="email" name="email" required value="<?= e($user['email']) ?>">
            </div>

            <div class="form-row">
                <div class="form-field">
                    <label for="role"><?= e(t('admin_user_field_role')) ?></label>
                    <select id="role" name="role">
                        <option value="staff"   <?= $user['role'] === 'staff'   ? 'selected' : '' ?>><?= e(t('admin_role_staff')) ?></option>
                        <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>><?= e(t('admin_role_manager')) ?></option>
                        <option value="admin"   <?= $user['role'] === 'admin'   ? 'selected' : '' ?>><?= e(t('admin_role_admin')) ?></option>
                    </select>
                </div>
                <div class="form-field">
                    <label for="branch_id"><?= e(t('admin_user_field_branch')) ?></label>
                    <select id="branch_id" name="branch_id">
                        <option value=""><?= e(t('admin_user_select_branch')) ?></option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= $b['id'] == $user['branch_id'] ? 'selected' : '' ?>>
                                <?= e(pick_branch_name($b, $lang)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:var(--text-muted);font-size:0.78rem;display:block;margin-top:6px;">
                        <?= e(t('admin_role_hint')) ?>
                    </small>
                </div>
            </div>

            <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border);">
                <h4 style="font-size:0.9rem;margin-bottom:12px;color:var(--text-muted);">
                    <?= e(t('admin_user_field_password')) ?>
                </h4>

                <div class="form-row">
                    <div class="form-field">
                        <label for="password"><?= e($is_new ? t('admin_user_field_password') : t('admin_user_password_optional')) ?></label>
                        <input type="password" id="password" name="password" <?= $is_new ? 'required' : '' ?> minlength="6">
                    </div>
                    <div class="form-field">
                        <label for="password2"><?= e(t('admin_user_confirm_password')) ?></label>
                        <input type="password" id="password2" name="password2" <?= $is_new ? 'required' : '' ?> minlength="6">
                    </div>
                </div>
            </div>

            <div class="form-field" style="margin-top:20px;">
                <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= $user['is_active'] ? 'checked' : '' ?> style="width:auto;">
                    <?= e(t('admin_user_field_active')) ?>
                </label>
            </div>

            <div style="display:flex;gap:10px;margin-top:24px;">
                <button type="submit" class="btn btn-accent btn-lg">
                    <i class="fas fa-save"></i> <?= e(t('admin_user_save')) ?>
                </button>
                <a href="admin_users.php" class="btn btn-outline btn-lg"><?= e(t('admin_cancel')) ?></a>
            </div>

        </form>

    

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
