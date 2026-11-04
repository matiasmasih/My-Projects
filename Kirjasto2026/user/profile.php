<?php
// user/profile.php — Member Personal Profile & Digital Library Card

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$member = $stmt->fetch();

if (!$member || ($member['role'] !== 'member' && $member['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $member['id'];
$branch_id = $member['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : 'Main Library';

$flash = '';
$error = '';

// ---- 1. UPDATE PASSWORD ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill in all password fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } else {
        if (password_verify($current_password, $member['password_hash'])) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $member['id']]);

            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->execute([$member['id'], 'profile.password_change', 'Member changed their password', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            $flash = 'Password updated successfully.';
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

// ---- 2. UPDATE PROFILE DETAILS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $dob = $_POST['date_of_birth'] ?: null;
    $national_id = trim($_POST['national_id'] ?? '');
    $profile_image = $member['profile_image'];

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $member['id']]);
        if ($check->fetch()) {
            $error = 'This email is already in use.';
        } else {
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $new_photo = 'member_' . $member['id'] . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], __DIR__ . '/../uploads/' . $new_photo)) {
                        if (!empty($member['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $member['profile_image'])) {
                            @unlink(__DIR__ . '/../uploads/' . $member['profile_image']);
                        }
                        $profile_image = $new_photo;
                    }
                }
            }

            try {
                $update = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, date_of_birth = ?, national_id = ?, profile_image = ? WHERE id = ?");
                $update->execute([$first_name, $last_name, $email, $phone, $address, $dob, $national_id, $profile_image, $member['id']]);
                $flash = 'Profile updated successfully.';

                // Refresh data to show new image and details immediately
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $member = $stmt->fetch();
            } catch (PDOException $e) {
                $error = 'Update failed: ' . $e->getMessage();
            }
        }
    }
}

// ---- 3. FETCH ACCOUNT STATS ----
$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$active_loans = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_loans = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM fines WHERE user_id = ? AND status != 'paid'");
$stmt->execute([$user_id]);
$total_fines = (float)$stmt->fetchColumn();

$page_title = 'My Profile';
require_once __DIR__ . '/../includes/user_header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
    .flatpickr-calendar { background: var(--card-solid) !important; border: 1px solid var(--border) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important; border-radius: 12px !important; }
    .flatpickr-day.selected { background: linear-gradient(135deg, #10b981, #059669) !important; border-color: #10b981 !important; color: white !important; }
    .flatpickr-months, .flatpickr-weekdays { background: var(--bg-alt) !important; border-radius: 12px 12px 0 0; }
    .flatpickr-month { color: var(--text) !important; fill: var(--text) !important; }
    .flatpickr-current-month .flatpickr-monthDropdown-months { color: var(--text) !important; }
    .flatpickr-weekday { color: var(--text-muted) !important; }
    .flatpickr-day { color: var(--text) !important; border-radius: 8px !important; }
    .flatpickr-day:hover { background: var(--bg-alt) !important; }
    .flatpickr-day.today { border-color: #10b981 !important; }
</style>

<!-- Hero Banner -->
<div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); padding: 40px; margin-bottom: 32px; position: relative; overflow: hidden;">
    <div style="position: relative; z-index: 2;">
        <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text); margin-bottom: 8px;">My Profile</h1>
        <p style="color: var(--text-muted); font-size: 1.1rem; margin: 0;">Manage your personal information, photo, and digital library card.</p>
    </div>
    <i class="fas fa-user-circle" style="position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: #10b981; opacity: 0.05; z-index: 1; transform: rotate(-15deg);"></i>
</div>

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

<div class="grid-2" style="align-items: start; gap: 32px;">

    <!-- LEFT COLUMN: Digital Library Card & Preview -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- DIGITAL LIBRARY CARD (Modern Green) -->
        <div style="perspective: 1000px;">
            <div style="background: linear-gradient(135deg, #10b981, #059669); border-radius: 20px; box-shadow: 0 20px 40px -10px rgba(16, 185, 129, 0.5); overflow: hidden; color: white; position: relative;">
                
                <i class="fas fa-book-reader" style="position: absolute; right: -20px; bottom: -20px; font-size: 10rem; opacity: 0.1; transform: rotate(-15deg);"></i>

                <div style="padding: 32px; position: relative; z-index: 2;">
                    <!-- Card Header -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
                        <div>
                            <div style="font-size: 0.75rem; opacity: 0.8; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Kirjasto 2026</div>
                            <div style="font-size: 1.2rem; font-weight: 800; margin-top: 4px;">Digital Library Card</div>
                        </div>
                        <div style="width: 48px; height: 48px; background: rgba(255,255,255,0.2); border-radius: 12px; display: grid; place-items: center; backdrop-filter: blur(10px);">
                            <i class="fas fa-id-card" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>

                    <!-- Member Info -->
                    <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 24px;">
                        <?php if (!empty($member['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $member['profile_image'])): ?>
                            <img src="<?= UPLOADS_URL . e($member['profile_image']) ?>?t=<?= time() ?>" style="width: 80px; height: 80px; border-radius: 16px; object-fit: cover; border: 3px solid rgba(255,255,255,0.5);">
                        <?php else: ?>
                            <div style="width: 80px; height: 80px; border-radius: 16px; background: rgba(255,255,255,0.2); color: white; display: grid; place-items: center; font-size: 1.8rem; font-weight: bold; border: 3px solid rgba(255,255,255,0.5);">
                                <?= strtoupper(substr($member['email'], 0, 2)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div style="font-size: 1.3rem; font-weight: 800; line-height: 1.1;"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></div>
                            <div style="font-size: 0.85rem; opacity: 0.9; margin-top: 6px;"><i class="fas fa-map-marker-alt"></i> <?= e($branch_name) ?></div>
                            <div style="font-size: 0.85rem; opacity: 0.9; margin-top: 2px;"><i class="fas fa-envelope"></i> <?= e($member['email']) ?></div>
                        </div>
                    </div>

                    <!-- Membership Number -->
                    <div style="background: rgba(0,0,0,0.15); padding: 16px; border-radius: 12px; backdrop-filter: blur(5px); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 0.7rem; opacity: 0.8; font-weight: 700; text-transform: uppercase;">Membership No.</div>
                            <div style="font-size: 1.4rem; font-family: monospace; font-weight: 800; letter-spacing: 2px; margin-top: 4px;"><?= e($member['membership_number'] ?: 'N/A') ?></div>
                        </div>
                        <i class="fas fa-qrcode" style="font-size: 2.5rem; opacity: 0.5;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACCOUNT STATS WIDGET -->
        <div class="card">
            <h3><i class="fas fa-chart-pie" style="color: #10b981;"></i> Account Overview</h3>
            <div class="grid-3" style="margin-top: 20px; gap: 16px;">
                <div style="text-align: center; padding: 12px; background: var(--bg-alt); border-radius: 12px;">
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--text);"><?= $active_loans ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Active Loans</div>
                </div>
                <div style="text-align: center; padding: 12px; background: var(--bg-alt); border-radius: 12px;">
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--text);"><?= $total_loans ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Lifetime Loans</div>
                </div>
                <div style="text-align: center; padding: 12px; background: <?= $total_fines > 0 ? 'rgba(239,68,68,0.1)' : 'var(--bg-alt)' ?>; border-radius: 12px;">
                    <div style="font-size: 1.5rem; font-weight: 800; color: <?= $total_fines > 0 ? '#ef4444' : 'var(--text)' ?>;">€<?= number_format($total_fines, 2) ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Unpaid Fines</div>
                </div>
            </div>
        </div>

        <!-- Notification Preferences -->
        <div class="card">
            <h3><i class="fas fa-bell" style="color: #10b981;"></i> Notification Preferences</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 16px;">Choose how you want to be notified about your account.</p>
            
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <label style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border); cursor: pointer;">
                    <span style="font-size: 0.9rem;"><i class="fas fa-clock" style="color: #f59e0b; margin-right: 8px;"></i> Email me when a book is due</span>
                    <input type="checkbox" checked style="width: 18px; height: 18px; accent-color: #10b981;">
                </label>
                <label style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--bg-alt); border-radius: 10px; border: 1px solid var(--border); cursor: pointer;">
                    <span style="font-size: 0.9rem;"><i class="fas fa-bookmark" style="color: #0ea5e9; margin-right: 8px;"></i> Alert me when a reservation is ready</span>
                    <input type="checkbox" checked style="width: 18px; height: 18px; accent-color: #10b981;">
                </label>
            </div>
        </div>

    </div>

    <!-- RIGHT COLUMN: Edit Forms -->
    <div style="display: flex; flex-direction: column; gap: 32px;">

        <!-- Personal Info Form -->
        <div class="card">
            <h3><i class="fas fa-user-edit" style="color: #10b981;"></i> Personal Information</h3>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label>Profile Photo</label>
                    <input type="file" name="profile_image" accept="image/*">
                    <small style="color: var(--text-muted); display: block; margin-top: 6px; font-size: 0.8rem;">Leave empty to keep current photo.</small>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?= e($member['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?= e($member['last_name']) ?>" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?= e($member['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?= e($member['phone']) ?>" placeholder="+358 40 123 4567">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="text" name="date_of_birth" id="dobPicker" value="<?= e($member['date_of_birth']) ?>" placeholder="Select date..." style="cursor: pointer; background: var(--bg);">
                    </div>
                    <div class="form-group">
                        <label>National ID / SSN</label>
                        <input type="text" name="national_id" value="<?= e($member['national_id'] ?? '') ?>" placeholder="123-45-6789">
                    </div>
                </div>
                <div class="form-group">
                    <label>Home Address</label>
                    <input type="text" name="address" value="<?= e($member['address']) ?>" placeholder="123 Main St, Helsinki">
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                    <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer;">
                        <i class="fas fa-save" style="margin-right: 8px;"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Password Form -->
        <div class="card">
            <h3><i class="fas fa-lock" style="color: #10b981;"></i> Change Password</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Ensure your account is using a long, random password to stay secure.</p>

            <form method="POST">
                <input type="hidden" name="action" value="update_password">

                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                    <small style="color: var(--text-muted); display: block; margin-top: 6px; font-size: 0.8rem;">Minimum 8 characters.</small>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                    <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer;">
                        <i class="fas fa-key" style="margin-right: 8px;"></i> Update Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<script>
    flatpickr("#dobPicker", {
        dateFormat: "Y-m-d",
        maxDate: "today",
        theme: "dark"
    });
</script>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
