<?php
// manage_users.php — Admin: full CRUD for all user accounts
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('manage_users');
if (my_access('manage_users') !== 'full') {
    header('Location: index.php'); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Manage Users';
$action     = $_GET['action'] ?? 'list';
$error      = '';
$flash      = '';

$my_id = (int)($_SESSION['user_id'] ?? 0);

// ============================================================
// ADD (POST)
// ============================================================
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $username    = trim($_POST['username'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $country     = trim($_POST['country'] ?? 'Finland');
    $role        = $_POST['role'] ?? 'member';
    $branch_id   = (int)($_POST['branch_id'] ?? 0) ?: null;
    $status      = $_POST['status'] ?? 'active';
    $language    = $_POST['language'] ?? 'en';
    $card_number = trim($_POST['card_number'] ?? '');

    if ($username === '' || !preg_match('/^[a-zA-Z0-9_.\-]{3,50}$/', $username)) {
        $error = 'Username must be 3–50 chars (letters, numbers, dot, dash, underscore).';
    } elseif ($first_name === '' || $last_name === '') {
        $error = 'First and last name are required.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email is required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!in_array($role, ['guest','member','librarian','admin'], true)) {
        $error = 'Invalid role.';
    } elseif (!in_array($status, ['active','suspended','expired'], true)) {
        $error = 'Invalid status.';
    } elseif (!in_array($language, ['en','fi','fa'], true)) {
        $error = 'Invalid language.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $chk->execute([$username, $email]);
        if ($chk->fetchColumn()) $error = 'Username or email is already taken.';
        if (!$error && $card_number !== '') {
            $chk2 = $pdo->prepare("SELECT id FROM users WHERE card_number = ?");
            $chk2->execute([$card_number]);
            if ($chk2->fetchColumn()) $error = 'That card number is already used.';
        }
    }

    if (!$error) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                "INSERT INTO users (card_number, username, email, password, first_name, last_name,
                                    phone, address, city, postal_code, country, role, branch_id, status, language)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $card_number !== '' ? $card_number : null,
                $username, $email, $hash, $first_name, $last_name,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                $city !== '' ? $city : null,
                $postal_code !== '' ? $postal_code : null,
                $country !== '' ? $country : null,
                $role, $branch_id, $status, $language
            ]);
            $uid = (int)$pdo->lastInsertId();

            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'user_created', ?, 'user', ?)")
                ->execute([$my_id, "Created user #{$uid} \"{$username}\" with role {$role}", $uid]);

            header('Location: manage_users.php?saved=added');
            exit;
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// ============================================================
// EDIT (POST)
// ============================================================
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $user_id     = (int)($_POST['user_id'] ?? 0);
    $email       = trim($_POST['email'] ?? '');
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $country     = trim($_POST['country'] ?? 'Finland');
    $role        = $_POST['role'] ?? 'member';
    $branch_id   = (int)($_POST['branch_id'] ?? 0) ?: null;
    $status      = $_POST['status'] ?? 'active';
    $language    = $_POST['language'] ?? 'en';
    $card_number = trim($_POST['card_number'] ?? '');

    if ($user_id <= 0) {
        $error = 'Invalid user.';
    } elseif ($first_name === '' || $last_name === '') {
        $error = 'First and last name are required.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email is required.';
    } elseif (!in_array($role, ['guest','member','librarian','admin'], true)) {
        $error = 'Invalid role.';
    } elseif (!in_array($status, ['active','suspended','expired'], true)) {
        $error = 'Invalid status.';
    } elseif (!in_array($language, ['en','fi','fa'], true)) {
        $error = 'Invalid language.';
    } else {
        // Prevent self-demotion / self-suspension
        if ($user_id === $my_id) {
            if ($role !== 'admin') {
                $error = 'You cannot change your own role away from admin.';
            } elseif ($status !== 'active') {
                $error = 'You cannot suspend your own account.';
            }
        }
        if (!$error) {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $chk->execute([$email, $user_id]);
            if ($chk->fetchColumn()) $error = 'That email is used by another user.';
        }
    }

    if (!$error) {
        try {
            $pdo->prepare(
                "UPDATE users SET card_number=?, email=?, first_name=?, last_name=?, phone=?,
                                  address=?, city=?, postal_code=?, country=?, role=?, branch_id=?,
                                  status=?, language=?
                 WHERE id=?"
            )->execute([
                $card_number !== '' ? $card_number : null,
                $email, $first_name, $last_name,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                $city !== '' ? $city : null,
                $postal_code !== '' ? $postal_code : null,
                $country !== '' ? $country : null,
                $role, $branch_id, $status, $language, $user_id
            ]);

            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'user_updated', ?, 'user', ?)")
                ->execute([$my_id, "Updated user #{$user_id} ({$email})", $user_id]);

            header('Location: manage_users.php?saved=updated');
            exit;
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// ============================================================
// RESET PASSWORD (POST)
// ============================================================
if ($action === 'reset_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    $user_id  = (int)($_POST['user_id'] ?? 0);
    $password = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($user_id <= 0) {
        $error = 'Invalid user.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $user_id]);
        $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                       VALUES (?, 'password_reset', ?, 'user', ?)")
            ->execute([$my_id, "Reset password for user #{$user_id}", $user_id]);
        header('Location: manage_users.php?saved=password');
        exit;
    }
}

// ============================================================
// TOGGLE STATUS (POST)
// ============================================================
if ($action === 'toggle_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id > 0 && $user_id !== $my_id) {
        $st = $pdo->prepare("SELECT username, status FROM users WHERE id=?");
        $st->execute([$user_id]);
        $u = $st->fetch();
        if ($u) {
            $new = $u['status'] === 'active' ? 'suspended' : 'active';
            $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$new, $user_id]);
            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'user_status', ?, 'user', ?)")
                ->execute([$my_id, "Set user #{$user_id} ({$u['username']}) status to {$new}", $user_id]);
        }
    }
    header('Location: manage_users.php?saved=status');
    exit;
}

// ============================================================
// DELETE (POST) — admin only, with safety checks
// ============================================================
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        header('Location: manage_users.php');
        exit;
    }
    if ($user_id === $my_id) {
        header('Location: manage_users.php?error=self_delete');
        exit;
    }

    // Block if user has history (real-library rule: preserve records)
    $chk = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM borrowings WHERE user_id=?) AS b,
            (SELECT COUNT(*) FROM fines WHERE user_id=?) AS f,
            (SELECT COUNT(*) FROM holds WHERE user_id=?) AS h"
    );
    $chk->execute([$user_id, $user_id, $user_id]);
    $hist = $chk->fetch();
    if ((int)$hist['b'] > 0 || (int)$hist['f'] > 0 || (int)$hist['h'] > 0) {
        header('Location: manage_users.php?error=has_history');
        exit;
    }

    $st = $pdo->prepare("SELECT username FROM users WHERE id=?");
    $st->execute([$user_id]);
    $uname = $st->fetchColumn();

    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$user_id]);
    $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                   VALUES (?, 'user_deleted', ?, 'user', ?)")
        ->execute([$my_id, "Deleted user #{$user_id} \"{$uname}\" (no history)", $user_id]);

    header('Location: manage_users.php?saved=deleted');
    exit;
}

// ============================================================
// LOAD FORM DATA
// ============================================================
$all_branches = $pdo->query("SELECT id, name FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();

$edit_user = null;
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $st->execute([$id]);
    $edit_user = $st->fetch();
    if (!$edit_user) { header('Location: manage_users.php'); exit; }
}

$reset_user = null;
if ($action === 'reset_password') {
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare("SELECT id, username, first_name, last_name FROM users WHERE id=?");
    $st->execute([$id]);
    $reset_user = $st->fetch();
    if (!$reset_user) { header('Location: manage_users.php'); exit; }
}

// ============================================================
// LIST DATA
// ============================================================
$q      = trim($_GET['q'] ?? '');
$role   = $_GET['role'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$branch = (int)($_GET['branch'] ?? 0);

$where = []; $args = [];
if ($q !== '') {
    $where[] = "(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.card_number LIKE ?)";
    $like = "%$q%";
    array_push($args, $like, $like, $like, $like, $like);
}
if ($role !== 'all')   { $where[] = "u.role = ?";     $args[] = $role; }
if ($status !== 'all') { $where[] = "u.status = ?";   $args[] = $status; }
if ($branch > 0)       { $where[] = "u.branch_id = ?"; $args[] = $branch; }
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$st = $pdo->prepare(
    "SELECT u.*, b.name AS branch_name,
            (SELECT COUNT(*) FROM borrowings WHERE user_id=u.id AND status IN ('borrowed','overdue')) AS active_loans,
            (SELECT COUNT(*) FROM holds WHERE user_id=u.id AND status IN ('pending','ready')) AS active_holds,
            (SELECT COALESCE(SUM(amount),0) FROM fines WHERE user_id=u.id AND paid=0 AND waived=0) AS unpaid_fines
     FROM users u
     LEFT JOIN branches b ON b.id = u.branch_id
     $where_sql
     ORDER BY u.role, u.last_name, u.first_name
     LIMIT 500"
);
$st->execute($args);
$users = $st->fetchAll();

include 'header.php';
?>

<style>
    .main-content { min-width: 0; max-width: 100%; overflow-x: hidden; }
    .main-container { min-width: 0; }

    .toolbar {
        display:flex; gap:10px; flex-wrap:nowrap; align-items:center;
        background:var(--card-bg); padding:14px; border-radius:12px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        margin-bottom:18px; overflow-x:auto;
    }
    .toolbar input, .toolbar select {
        padding:9px 12px; border:1px solid var(--border); border-radius:8px;
        background:var(--card-bg); color:var(--text); font-size:.9rem;
        white-space:nowrap;
    }
    .toolbar input[type=text] { flex:1; min-width:220px; }

    .u-card {
        background:var(--card-bg); border-radius:12px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        overflow:hidden; max-width:100%;
    }
    .u-scroll {
        width:100%; max-width:100%;
        overflow-x:auto; overflow-y:visible;
        -webkit-overflow-scrolling:touch; padding-bottom:10px;
    }
    table.u-table { width:100%; border-collapse:collapse; min-width:1350px; }
    table.u-table th, table.u-table td {
        padding:12px 14px; vertical-align:middle;
        white-space:nowrap; border-bottom:1px solid rgba(0,0,0,0.05);
        text-align:left; font-size:.9rem; color:var(--text); font-weight:500;
    }

    /* Center the last column (Actions) */
    table.u-table th:last-child,
    table.u-table td:last-child {
        text-align: center;
    }

    table.u-table thead th {
        background: var(--primary);
        color: #ffffff;
        font-weight: 600;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        border-bottom: none;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    table.u-table tbody tr:hover { background:#f8fffe; }

    .avatar-mini {
        width:34px; height:34px; border-radius:50%;
        display:grid; place-items:center; color:#fff; font-weight:700;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        overflow:hidden; font-size:.78rem; flex-shrink:0;
    }
    .avatar-mini img { width:100%; height:100%; object-fit:cover; }

    .badge { display:inline-block; padding:3px 9px; border-radius:8px; font-size:.72rem; font-weight:600; white-space:nowrap; }
    .b-active    { background:#d1fae5; color:#065f46; }
    .b-suspended { background:#fee2e2; color:#991b1b; }
    .b-expired   { background:#e2e8f0; color:#475569; }
    .b-admin     { background:#ede9fe; color:#5b21b6; }
    .b-librarian { background:#dbeafe; color:#1e40af; }
    .b-member    { background:#ccfbf1; color:#115e59; }
    .b-guest     { background:#e2e8f0; color:#475569; }

    .row-actions { display:flex; gap:5px; white-space:nowrap; flex-wrap:nowrap; }
    .row-actions form { display:inline-flex; margin:0; }
    .row-actions a, .row-actions button {
        padding:5px 10px; border-radius:6px; font-size:.72rem; font-weight:600;
        text-decoration:none; display:inline-flex; align-items:center; gap:3px;
        border:none; cursor:pointer; height:28px; box-sizing:border-box;
        white-space:nowrap;
    }
    .btn-edit-sm  { background:#0d9488; color:#fff; }
    .btn-edit-sm:hover  { background:#0f766e; }
    .btn-pass-sm  { background:#657aa8; color:#fff; }
    .btn-pass-sm:hover  { background:#4f6088; }
    .btn-warn-sm  { background:#d97706; color:#fff; }
    .btn-warn-sm:hover  { background:#b45309; }
    .btn-ok-sm    { background:#10b981; color:#fff; }
    .btn-ok-sm:hover    { background:#059669; }
    .btn-del-sm   { background:#dc2626; color:#fff; }
    .btn-del-sm:hover   { background:#b91c1c; }

    .form-panel {
        background:var(--card-bg); border-radius:var(--radius); padding:22px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        max-width:900px; margin:0 auto;
    }
    .form-panel h3 { margin:0 0 18px; display:flex; align-items:center; gap:8px; }
    .form-panel h3 i { color:var(--primary); }
    .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    @media (max-width:700px){ .grid2 { grid-template-columns:1fr; } }
    .grid2 label { display:block; font-weight:600; font-size:.85rem; margin-bottom:6px; }
    .grid2 input, .grid2 select {
        width:100%; padding:10px 12px; border:1px solid var(--border);
        border-radius:10px; font-size:.92rem; background:var(--card-bg); color:var(--text);
        font-family:inherit;
    }
    .span2 { grid-column:span 2; }
    @media (max-width:700px){ .span2 { grid-column:span 1; } }
    .readonly { background:#f1f5f9 !important; color:var(--text-muted) !important; cursor:not-allowed; }
</style>

<?php if (isset($_GET['saved'])): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i>
        <?php
            $msg = [
                'added'    => 'User created.',
                'updated'  => 'User updated.',
                'password' => 'Password reset.',
                'status'   => 'Status changed.',
                'deleted'  => 'User deleted.'
            ][$_GET['saved']] ?? 'Saved.';
        ?>
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?php
            $msg = [
                'self_delete' => 'You cannot delete your own account.',
                'has_history' => 'Cannot delete — this user has borrowings, fines, or holds. Suspend instead.'
            ][$_GET['error']] ?? 'Error.';
        ?>
        <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($action === 'add'): ?>

    <!-- ========== ADD USER ========== -->
    <div class="form-panel">
        <h3><i class="bi bi-person-plus-fill"></i> Add User</h3>

        <form method="POST" action="manage_users.php?action=add">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="grid2">
                <div>
                    <label>Username *</label>
                    <input type="text" name="username" required pattern="[a-zA-Z0-9_.\-]{3,50}" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div>
                    <label>Card Number</label>
                    <input type="text" name="card_number" value="<?= htmlspecialchars($_POST['card_number'] ?? '') ?>" placeholder="leave blank to auto-assign later">
                </div>

                <div>
                    <label>First Name *</label>
                    <input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>
                <div>
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>

                <div class="span2">
                    <label>Email *</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div>
                    <label>Password *</label>
                    <input type="password" name="password" required minlength="6">
                </div>
                <div>
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>

                <div class="span2">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>
                <div>
                    <label>City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                </div>
                <div>
                    <label>Postal Code</label>
                    <input type="text" name="postal_code" value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
                </div>
                <div class="span2">
                    <label>Country</label>
                    <input type="text" name="country" value="<?= htmlspecialchars($_POST['country'] ?? 'Finland') ?>">
                </div>

                <div>
                    <label>Role *</label>
                    <select name="role" required>
                        <?php foreach (['member'=>'Member','librarian'=>'Librarian','admin'=>'Admin','guest'=>'Guest'] as $c=>$l): ?>
                            <option value="<?= $c ?>" <?= ($_POST['role'] ?? 'member')===$c?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['active'=>'Active','suspended'=>'Suspended','expired'=>'Expired'] as $c=>$l): ?>
                            <option value="<?= $c ?>" <?= ($_POST['status'] ?? 'active')===$c?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="0">— None —</option>
                        <?php foreach ($all_branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= ((int)($_POST['branch_id'] ?? 0) === (int)$b['id'])?'selected':'' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Language</label>
                    <select name="language">
                        <?php foreach (['en'=>'English','fi'=>'Finnish','fa'=>'Persian'] as $c=>$l): ?>
                            <option value="<?= $c ?>" <?= ($_POST['language'] ?? 'en')===$c?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                <a href="manage_users.php" class="btn-secondary-custom">← Back</a>
                <button type="submit" class="btn-add"><i class="bi bi-check-circle"></i> Create User</button>
            </div>
        </form>
    </div>

<?php elseif ($action === 'edit' && $edit_user): ?>

    <!-- ========== EDIT USER ========== -->
    <div class="form-panel">
        <h3><i class="bi bi-pencil-square"></i> Edit User — <?= htmlspecialchars($edit_user['username']) ?></h3>

        <form method="POST" action="manage_users.php?action=edit">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="user_id" value="<?= (int)$edit_user['id'] ?>">

            <div class="grid2">
                <div>
                    <label>Username (read-only)</label>
                    <input type="text" class="readonly" readonly value="<?= htmlspecialchars($edit_user['username']) ?>">
                </div>
                <div>
                    <label>Card Number</label>
                    <input type="text" name="card_number" value="<?= htmlspecialchars($edit_user['card_number'] ?? '') ?>">
                </div>

                <div>
                    <label>First Name *</label>
                    <input type="text" name="first_name" required value="<?= htmlspecialchars($edit_user['first_name']) ?>">
                </div>
                <div>
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required value="<?= htmlspecialchars($edit_user['last_name']) ?>">
                </div>

                <div class="span2">
                    <label>Email *</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($edit_user['email']) ?>">
                </div>

                <div>
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($edit_user['phone'] ?? '') ?>">
                </div>
                <div>
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="0">— None —</option>
                        <?php foreach ($all_branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= ((int)($edit_user['branch_id'] ?? 0) === (int)$b['id'])?'selected':'' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="span2">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($edit_user['address'] ?? '') ?>">
                </div>
                <div>
                    <label>City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($edit_user['city'] ?? '') ?>">
                </div>
                <div>
                    <label>Postal Code</label>
                    <input type="text" name="postal_code" value="<?= htmlspecialchars($edit_user['postal_code'] ?? '') ?>">
                </div>
                <div class="span2">
                    <label>Country</label>
                    <input type="text" name="country" value="<?= htmlspecialchars($edit_user['country'] ?? '') ?>">
                </div>

                <div>
                    <label>Role *</label>
                    <select name="role" required <?= (int)$edit_user['id'] === $my_id ? 'disabled' : '' ?>>
                        <?php foreach (['member'=>'Member','librarian'=>'Librarian','admin'=>'Admin','guest'=>'Guest'] as $c=>$l): ?>
                            <option value="<?= $c ?>" <?= $edit_user['role']===$c?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ((int)$edit_user['id'] === $my_id): ?>
                        <input type="hidden" name="role" value="<?= htmlspecialchars($edit_user['role']) ?>">
                        <small style="color:var(--text-muted);font-size:.78rem;">You cannot change your own role.</small>
                    <?php endif; ?>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status" <?= (int)$edit_user['id'] === $my_id ? 'disabled' : '' ?>>
                        <?php foreach (['active'=>'Active','suspended'=>'Suspended','expired'=>'Expired'] as $c=>$l): ?>
                            <option value="<?= $c ?>" <?= $edit_user['status']===$c?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ((int)$edit_user['id'] === $my_id): ?>
                        <input type="hidden" name="status" value="active">
                    <?php endif; ?>
                </div>

                <div>
                    <label>Language</label>
                    <select name="language">
                        <?php foreach (['en'=>'English','fi'=>'Finnish','fa'=>'Persian'] as $c=>$l): ?>
                            <option value="<?= $c ?>" <?= $edit_user['language']===$c?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                <a href="manage_users.php" class="btn-secondary-custom">← Back</a>
                <button type="submit" class="btn-add"><i class="bi bi-check-circle"></i> Save Changes</button>
            </div>
        </form>
    </div>

<?php elseif ($action === 'reset_password' && $reset_user): ?>

    <!-- ========== RESET PASSWORD ========== -->
    <div class="form-panel">
        <h3><i class="bi bi-shield-lock"></i> Reset Password — @<?= htmlspecialchars($reset_user['username']) ?></h3>
        <p style="color:var(--text-muted);margin-bottom:18px;">
            Setting a new password for <strong><?= htmlspecialchars($reset_user['first_name'] . ' ' . $reset_user['last_name']) ?></strong>.
            They will use this to log in next time.
        </p>

        <form method="POST" action="manage_users.php?action=reset_password">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="user_id" value="<?= (int)$reset_user['id'] ?>">

            <div class="grid2">
                <div class="span2">
                    <label>New Password *</label>
                    <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
                </div>
                <div class="span2">
                    <label>Confirm New Password *</label>
                    <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password">
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                <a href="manage_users.php" class="btn-secondary-custom">← Back</a>
                <button type="submit" class="btn-add"><i class="bi bi-check-circle"></i> Set New Password</button>
            </div>
        </form>
    </div>

<?php else: ?>

    <!-- ========== LIST ========== -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;"><i class="bi bi-people-fill" style="color:var(--primary);"></i> Users (<?= count($users) ?>)</h2>
        <a href="manage_users.php?action=add" class="btn-add" style="text-decoration:none;">
            <i class="bi bi-person-plus-fill"></i> Add User
        </a>
    </div>

    <form class="toolbar" method="get" action="manage_users.php">
        <input type="text" name="q" placeholder="Search username, email, name, card…" value="<?= htmlspecialchars($q) ?>">
        <select name="role">
            <?php foreach (['all'=>'All roles','admin'=>'Admins','librarian'=>'Librarians','member'=>'Members','guest'=>'Guests'] as $c=>$l): ?>
                <option value="<?= $c ?>" <?= $role===$c?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <?php foreach (['all'=>'All statuses','active'=>'Active','suspended'=>'Suspended','expired'=>'Expired'] as $c=>$l): ?>
                <option value="<?= $c ?>" <?= $status===$c?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <select name="branch">
            <option value="0">All branches</option>
            <?php foreach ($all_branches as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= $branch===(int)$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-add" style="white-space:nowrap;"><i class="bi bi-search"></i> Filter</button>
        <a href="manage_users.php" class="btn-secondary-custom" style="white-space:nowrap;">Reset</a>
    </form>

    <?php if (!$users): ?>
        <div class="u-card" style="text-align:center;padding:50px;">
            <i class="bi bi-people" style="font-size:3rem;color:var(--text-muted);"></i>
            <p style="color:var(--text-muted);margin-top:12px;">No users match.</p>
        </div>
    <?php else: ?>
    <div class="u-card">
        <div class="u-scroll">
            <table class="u-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Card #</th>
                        <th>Email</th>
                        <th>Branch</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Loans</th>
                        <th>Holds</th>
                        <th>Fines</th>
                        <th>Last Login</th>
                        <th> Actions </th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $initials = strtoupper(substr($u['first_name'],0,1) . substr($u['last_name'],0,1));
                    $is_me = (int)$u['id'] === $my_id;
                ?>
                    <tr>
                        <td>
                            <div class="avatar-mini">
                                <?php if (!empty($u['avatar']) && file_exists(__DIR__ . '/uploads/avatars/' . $u['avatar'])): ?>
                                    <img src="uploads/avatars/<?= htmlspecialchars($u['avatar']) ?>" alt="">
                                <?php else: ?>
                                    <?= htmlspecialchars($initials) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="font-weight:600;">
                            <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                            <?php if ($is_me): ?><span class="badge b-member" style="margin-left:6px;">you</span><?php endif; ?>
                        </td>
                        <td>@<?= htmlspecialchars($u['username']) ?></td>
                        <td><code style="font-size:.78rem;"><?= htmlspecialchars($u['card_number'] ?? '—') ?></code></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['branch_name'] ?? '—') ?></td>
                        <td><span class="badge b-<?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                        <td><span class="badge b-<?= htmlspecialchars($u['status']) ?>"><?= htmlspecialchars($u['status']) ?></span></td>
                        <td><?= (int)$u['active_loans'] ?></td>
                        <td><?= (int)$u['active_holds'] ?></td>
                        <td>
                            <?php if ((float)$u['unpaid_fines'] > 0): ?>
                                <span class="badge b-suspended">€<?= number_format((float)$u['unpaid_fines'],2) ?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td style="font-size:.82rem;color:var(--text-muted);">
                            <?= $u['last_login'] ? htmlspecialchars(substr($u['last_login'],0,16)) : '—' ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="manage_users.php?action=edit&id=<?= (int)$u['id'] ?>" class="btn-edit-sm" title="Edit">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <a href="manage_users.php?action=reset_password&id=<?= (int)$u['id'] ?>" class="btn-pass-sm" title="Reset password">
                                    <i class="bi bi-key"></i> Reset
                                </a>
                                <?php if (!$is_me): ?>
                                    <form method="POST" action="manage_users.php?action=toggle_status"
                                          onsubmit="return confirm('<?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?> this user?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <?php if ($u['status'] === 'active'): ?>
                                            <button type="submit" class="btn-warn-sm" title="Suspend"><i class="bi bi-pause-circle"></i> Suspend</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-ok-sm" title="Activate"><i class="bi bi-play-circle"></i> Activate</button>
                                        <?php endif; ?>
                                    </form>
                                    <form method="POST" action="manage_users.php?action=delete"
                                          onsubmit="return confirm('Delete this user? Must have no history.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn-del-sm" title="Delete"><i class="bi bi-trash"></i> Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p style="color:var(--text-muted);font-size:.82rem;margin-top:10px;">
        Showing <?= count($users) ?> user(s).
    </p>
    <?php endif; ?>

<?php endif; ?>

<?php include 'footer.php'; ?>
