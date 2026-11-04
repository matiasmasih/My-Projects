<?php
// staff/profile.php — Staff Personal Profile

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch();

if (!$staff || $staff['role'] !== 'staff' && $staff_user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';
$error = '';

// ---- UPDATE PROFILE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $dob = $_POST['date_of_birth'] ?: null;
    $national_id = trim($_POST['national_id'] ?? '');
    $profile_image = $staff['profile_image'];

    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $staff['id']]);
        if ($check->fetch()) {
            $error = 'This email is already in use.';
        } else {
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $new_photo = 'staff_' . $staff['id'] . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], __DIR__ . '/../uploads/' . $new_photo)) {
                        if (!empty($staff['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $staff['profile_image'])) {
                            @unlink(__DIR__ . '/../uploads/' . $staff['profile_image']);
                        }
                        $profile_image = $new_photo;
                    }
                }
            }

            try {
                $update = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, date_of_birth = ?, national_id = ?, profile_image = ? WHERE id = ?");
                $update->execute([$first_name, $last_name, $email, $phone, $address, $dob, $national_id, $profile_image, $staff['id']]);
                $flash = 'Profile updated successfully.';

                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $staff = $stmt->fetch();
            } catch (PDOException $e) {
                $error = 'Update failed: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'My Profile';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">My Profile</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Manage your personal information and photo.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2" style="align-items: start; gap: 32px;">
    <!-- Left: Preview Card -->
    <div class="card" style="background: var(--bg-alt); text-align: center; padding: 40px 24px;">
        <div style="display: flex; justify-content: center; margin-bottom: 24px;">
            <?php if (!empty($staff['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $staff['profile_image'])): ?>
                <img src="<?= UPLOADS_URL . e($staff['profile_image']) ?>?v=<?= time() ?>" style="width: 160px; height: 160px; border-radius: 50%; object-fit: cover; border: 4px solid var(--border); box-shadow: 0 10px 20px -5px rgba(14, 165, 233, 0.5);">
            <?php else: ?>
                <div style="width: 160px; height: 160px; border-radius: 50%; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; display: grid; place-items: center; font-size: 3.5rem; font-weight: bold; border: 4px solid var(--border); box-shadow: 0 10px 20px -5px rgba(14, 165, 233, 0.5);">
                    <?= strtoupper(substr($staff['email'], 0, 2)) ?>
                </div>
            <?php endif; ?>
        </div>
        <h3 style="margin-bottom: 8px; font-size: 1.5rem;"><?= e($staff['first_name'] . ' ' . $staff['last_name']) ?></h3>
        <p style="color: #0ea5e9; font-weight: 700; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">Staff Member</p>

        <div style="margin-top: 32px; background: var(--card-bg); padding: 20px; border-radius: 16px; border: 1px solid var(--border); text-align: left;">
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                <span style="color: var(--text-muted);">Email</span>
                <span style="font-weight: 600; font-size: 0.85rem;"><?= e($staff['email']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                <span style="color: var(--text-muted);">Phone</span>
                <span style="font-weight: 600;"><?= e($staff['phone'] ?: 'Not set') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                <span style="color: var(--text-muted);">Date of Birth</span>
                <span style="font-weight: 600;"><?= !empty($staff['date_of_birth']) ? date('M j, Y', strtotime($staff['date_of_birth'])) : 'Not set' ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                <span style="color: var(--text-muted);">National ID</span>
                <span style="font-weight: 600;"><?= e($staff['national_id'] ?: 'Not set') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                <span style="color: var(--text-muted);">Address</span>
                <span style="font-weight: 600; text-align: right; max-width: 60%;"><?= e($staff['address'] ?: 'Not set') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0;">
                <span style="color: var(--text-muted);">Joined</span>
                <span style="font-weight: 600;"><?= date('M j, Y', strtotime($staff['created_at'])) ?></span>
            </div>
        </div>
    </div>

    <!-- Right: Edit Form -->
    <div class="card">
        <h3><i class="fas fa-user-edit"></i> Edit Profile</h3>

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
                    <input type="text" name="first_name" value="<?= e($staff['first_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?= e($staff['last_name']) ?>" required>
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= e($staff['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" value="<?= e($staff['phone']) ?>" placeholder="+358 40 123 4567">
                </div>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?= e($staff['date_of_birth']) ?>">
                </div>
                <div class="form-group">
                    <label>National ID / SSN</label>
                    <input type="text" name="national_id" value="<?= e($staff['national_id'] ?? '') ?>" placeholder="123-45-6789">
                </div>
            </div>
            <div class="form-group">
                <label>Home Address</label>
                <input type="text" name="address" value="<?= e($staff['address']) ?>" placeholder="123 Main St, Helsinki">
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; background: linear-gradient(135deg, #0ea5e9, #14b8a6);">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
