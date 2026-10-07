<?php
// my_account.php — Member/staff: view + edit own profile, change password
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('my_account');
if (my_access('my_account') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'My Account';
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) { header('Location: login.php'); exit; }

$success = '';
$error   = '';

// ---------- HANDLE POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    // ============ PROFILE UPDATE ============
    if ($form === 'profile') {
        $first_name  = trim($_POST['first_name'] ?? '');
        $last_name   = trim($_POST['last_name'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $phone       = trim($_POST['phone'] ?? '');
        $address     = trim($_POST['address'] ?? '');
        $city        = trim($_POST['city'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $country     = trim($_POST['country'] ?? '');
        $language    = $_POST['language'] ?? 'en';

        if ($first_name === '' || $last_name === '') {
            $error = 'First and last name are required.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'A valid email is required.';
        } elseif (!in_array($language, ['en','fi','fa'], true)) {
            $error = 'Invalid language.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
            $chk->execute([$email, $user_id]);
            if ($chk->fetchColumn()) {
                $error = 'That email is already used by another account.';
            }
        }

        // Avatar upload
        $avatar_name = null;
        if (!$error && !empty($_FILES['avatar']['name'])) {
            $allowed = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                $error = 'Avatar must be JPG, PNG, or WEBP.';
            } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                $error = 'Avatar must be under 2 MB.';
            } else {
                $avatar_name = 'u' . $user_id . '_' . time() . '.' . $ext;
                $dest = __DIR__ . '/uploads/avatars/' . $avatar_name;
                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                    $error = 'Could not save avatar.';
                    $avatar_name = null;
                }
            }
        }

        if (!$error) {
            if ($avatar_name) {
                $sql = "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, address=?,
                                        city=?, postal_code=?, country=?, language=?, avatar=?
                        WHERE id=?";
                $pdo->prepare($sql)->execute([
                    $first_name, $last_name, $email, $phone, $address,
                    $city, $postal_code, $country, $language, $avatar_name, $user_id
                ]);
                $_SESSION['avatar'] = $avatar_name;
            } else {
                $sql = "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, address=?,
                                        city=?, postal_code=?, country=?, language=?
                        WHERE id=?";
                $pdo->prepare($sql)->execute([
                    $first_name, $last_name, $email, $phone, $address,
                    $city, $postal_code, $country, $language, $user_id
                ]);
            }

            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name']  = $last_name;
            $_SESSION['language']   = $language;

            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'profile_update', 'Updated own profile', 'user', ?)")
                ->execute([$user_id, $user_id]);

            $success = 'Profile updated.';
        }
    }

    // ============ PASSWORD CHANGE ============
    if ($form === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $error = 'All password fields are required.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $st = $pdo->prepare("SELECT password FROM users WHERE id=?");
            $st->execute([$user_id]);
            $hash = $st->fetchColumn();

            if (!$hash || !password_verify($current, $hash)) {
                $error = 'Current password is incorrect.';
            } else {
                $newhash = password_hash($new, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$newhash, $user_id]);
                $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                               VALUES (?, 'password_change', 'Changed own password', 'user', ?)")
                    ->execute([$user_id, $user_id]);
                $success = 'Password changed.';
            }
        }
    }
}

// ---------- LOAD USER ----------
$u = $pdo->prepare(
    "SELECT u.*, b.name AS branch_name
     FROM users u LEFT JOIN branches b ON b.id = u.branch_id
     WHERE u.id = ?"
);
$u->execute([$user_id]);
$user = $u->fetch();
if (!$user) { header('Location: login.php'); exit; }

// ---------- STATS ----------
$s = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM borrowings WHERE user_id=? AND status IN ('borrowed','overdue')) AS loans,
        (SELECT COUNT(*) FROM holds WHERE user_id=? AND status IN ('pending','ready')) AS holds,
        (SELECT COALESCE(SUM(amount),0) FROM fines WHERE user_id=? AND paid=0 AND waived=0) AS fines
    "
);
$s->execute([$user_id, $user_id, $user_id]);
$stats = $s->fetch();

$role_labels = ['admin'=>'Admin','librarian'=>'Librarian','member'=>'Member','guest'=>'Guest'];
$status_labels = ['active'=>'Active','suspended'=>'Suspended','expired'=>'Expired'];

include 'header.php';
?>

<style>
    .acct-grid { display:grid; grid-template-columns:320px 1fr; gap:22px; }
    @media (max-width:900px){ .acct-grid { grid-template-columns:1fr; } }

    .side-card {
        background:var(--card-bg); border-radius:var(--radius);
        padding:24px 20px; text-align:center;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        position:sticky; top:90px;
    }
    .side-avatar {
        width:110px; height:110px; border-radius:50%;
        margin:0 auto 14px;
        display:grid; place-items:center;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        color:#fff; font-size:2.6rem; font-weight:700;
        overflow:hidden; border:4px solid #fff; box-shadow:0 4px 16px rgba(13,148,136,0.25);
    }
    .side-avatar img { width:100%; height:100%; object-fit:cover; }
    .side-name { font-size:1.15rem; font-weight:700; color:var(--text); }
    .side-username { color:var(--text-muted); font-size:.85rem; margin-top:2px; }
    .side-role {
        display:inline-block; margin-top:10px; padding:3px 10px;
        background:#ccfbf1; color:#115e59; border-radius:8px;
        font-size:.78rem; font-weight:600;
    }
    .side-meta { margin-top:16px; font-size:.83rem; color:var(--text-muted); text-align:left; padding-top:14px; border-top:1px solid rgba(0,0,0,0.06); }
    .side-meta div { margin:6px 0; }
    .side-meta i { color:var(--primary); margin-right:6px; }

    .side-stats { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-top:16px; padding-top:14px; border-top:1px solid rgba(0,0,0,0.06); }
    .side-stats div { text-align:center; }
    .side-stats .v { font-weight:700; color:var(--primary-dark); font-size:1.05rem; }
    .side-stats .l { font-size:.7rem; color:var(--text-muted); }

    .panel {
        background:var(--card-bg); border-radius:var(--radius);
        padding:22px; box-shadow:var(--shadow); margin-bottom:20px;
        border:1px solid rgba(13,148,136,0.08);
    }
    .panel h3 { font-size:1.05rem; margin:0 0 18px; display:flex; align-items:center; gap:8px; }
    .panel h3 i { color:var(--primary); }

    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
    @media (max-width:600px){ .form-row { grid-template-columns:1fr; } }

    .form-row label { display:block; font-weight:600; font-size:.85rem; margin-bottom:6px; color:var(--text); }
    .form-row input, .form-row select {
        width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:10px;
        font-size:.92rem; background:var(--card-bg); color:var(--text);
    }
    .form-row input:focus, .form-row select:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(13,148,136,0.12); }
    .span2 { grid-column:span 2; }
    @media (max-width:600px){ .span2 { grid-column:span 1; } }
    .readonly { background:#f8fafc !important; color:var(--text-muted); cursor:not-allowed; }
</style>

<h2 style="margin-bottom:18px;"><i class="bi bi-person-badge" style="color:var(--primary);"></i> My Account</h2>

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

<div class="acct-grid">

    <!-- ===== LEFT: summary card ===== -->
    <div>
        <div class="side-card">
            <div class="side-avatar">
                <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/uploads/avatars/' . $user['avatar'])): ?>
                    <img src="uploads/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars(strtoupper(substr($user['first_name'],0,1) . substr($user['last_name'],0,1))) ?>
                <?php endif; ?>
            </div>
            <div class="side-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
            <div class="side-username">@<?= htmlspecialchars($user['username']) ?></div>
            <div class="side-role"><?= htmlspecialchars($role_labels[$user['role']] ?? $user['role']) ?></div>

            <div class="side-meta">
                <div><i class="bi bi-upc-scan"></i> <?= htmlspecialchars($user['card_number'] ?? '—') ?></div>
                <div><i class="bi bi-envelope"></i> <?= htmlspecialchars($user['email']) ?></div>
                <?php if (!empty($user['phone'])): ?>
                    <div><i class="bi bi-telephone"></i> <?= htmlspecialchars($user['phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($user['branch_name'])): ?>
                    <div><i class="bi bi-building"></i> <?= htmlspecialchars($user['branch_name']) ?></div>
                <?php endif; ?>
                <div><i class="bi bi-info-circle"></i> <?= htmlspecialchars($status_labels[$user['status']] ?? $user['status']) ?></div>
                <div><i class="bi bi-calendar"></i> Member since <?= htmlspecialchars(substr($user['created_at'],0,10)) ?></div>
            </div>

            <div class="side-stats">
                <div><div class="v"><?= (int)$stats['loans'] ?></div><div class="l">Loans</div></div>
                <div><div class="v"><?= (int)$stats['holds'] ?></div><div class="l">Holds</div></div>
                <div><div class="v">€<?= number_format((float)$stats['fines'],2) ?></div><div class="l">Fines</div></div>
            </div>
        </div>
    </div>

    <!-- ===== RIGHT: forms ===== -->
    <div>

        <!-- PROFILE FORM -->
        <div class="panel">
            <h3><i class="bi bi-pencil-square"></i> Profile Information</h3>

            <form method="POST" action="my_account.php" enctype="multipart/form-data">
                <input type="hidden" name="form" value="profile">

                <div class="form-row">
                    <div>
                        <label>First Name *</label>
                        <input type="text" name="first_name" required value="<?= htmlspecialchars($user['first_name']) ?>">
                    </div>
                    <div>
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required value="<?= htmlspecialchars($user['last_name']) ?>">
                    </div>

                    <div>
                        <label>Username (read-only)</label>
                        <input type="text" class="readonly" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                    </div>
                    <div>
                        <label>Card Number (read-only)</label>
                        <input type="text" class="readonly" value="<?= htmlspecialchars($user['card_number'] ?? '—') ?>" readonly>
                    </div>

                    <div class="span2">
                        <label>Email *</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">
                    </div>

                    <div>
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Language</label>
                        <select name="language">
                            <?php foreach (['en'=>'English','fi'=>'Suomi','fa'=>'فارسی'] as $code=>$label): ?>
                                <option value="<?= $code ?>" <?= $user['language']===$code?'selected':'' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="span2">
                        <label>Address</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                    </div>

                    <div>
                        <label>City</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Postal Code</label>
                        <input type="text" name="postal_code" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>">
                    </div>

                    <div class="span2">
                        <label>Country</label>
                        <input type="text" name="country" value="<?= htmlspecialchars($user['country'] ?? '') ?>">
                    </div>

                    <div class="span2">
                        <label>Avatar (JPG/PNG/WEBP, max 2 MB)</label>
                        <input type="file" name="avatar" accept="image/*">
                    </div>
                </div>

                <div style="text-align:right;margin-top:6px;">
                    <button type="submit" class="btn-add">
                        <i class="bi bi-check-circle"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- PASSWORD FORM -->
        <div class="panel">
            <h3><i class="bi bi-shield-lock"></i> Change Password</h3>

            <form method="POST" action="my_account.php">
                <input type="hidden" name="form" value="password">

                <div class="form-row">
                    <div class="span2">
                        <label>Current Password *</label>
                        <input type="password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div>
                        <label>New Password *</label>
                        <input type="password" name="new_password" required minlength="6" autocomplete="new-password">
                    </div>
                    <div>
                        <label>Confirm New Password *</label>
                        <input type="password" name="confirm_password" required minlength="6" autocomplete="new-password">
                    </div>
                </div>

                <div style="text-align:right;margin-top:6px;">
                    <button type="submit" class="btn-add">
                        <i class="bi bi-shield-check"></i> Update Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>
