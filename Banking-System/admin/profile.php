<?php
require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();

if ($user_data['role'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Update Info
    if ($_POST['action'] === 'update_profile') {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $dob = $_POST['date_of_birth'] ?: null;
        $national_id = trim($_POST['national_id']);
        $photo_name = $user_data['photo'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        }

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $new_filename = 'admin_' . $user_data['id'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../uploads/' . $new_filename)) {
                    $photo_name = $new_filename;
                }
            } else {
                $error = 'Invalid image format.';
            }
        }

        if (empty($error)) {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $user_data['id']]);
            if ($check->fetch()) {
                $error = 'Email is already in use.';
            } else {
                $update = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, date_of_birth = ?, national_id = ?, photo = ? WHERE id = ?");
                $update->execute([$first_name, $last_name, $email, $phone, $address, $dob, $national_id, $photo_name, $user_data['id']]);
                $flash = 'Profile updated successfully!';
                
                // Refresh
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user_data = $stmt->fetch();
            }
        }
    }

    // Update Password
    if ($_POST['action'] === 'update_password') {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        if (!password_verify($current, $user_data['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user_data['id']]);
            $flash = 'Password updated successfully!';
        }
    }
}

$page_title = 'Admin Profile';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 1.8rem; color: var(--text);">My Profile</h1>
    <p style="color: var(--text-muted);">Manage your personal information and security.</p>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="grid-2" style="align-items: start; gap: 32px;">
    
    <!-- Left Column: Preview Card -->
    <div class="card" style="background: var(--bg-alt); text-align: center; padding: 40px 24px;">
        <div style="display: flex; justify-content: center; margin-bottom: 24px;">
            <?php if (!empty($user_data['photo']) && file_exists(__DIR__ . '/../uploads/' . $user_data['photo'])): ?>
                <img src="<?= UPLOADS_URL . e($user_data['photo']) ?>?v=<?= time() ?>" style="width: 160px; height: 160px; border-radius: 50%; object-fit: cover; border: 4px solid var(--border); box-shadow: 0 10px 20px -5px var(--primary-glow);">
            <?php else: ?>
                <div style="width: 160px; height: 160px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; display: grid; place-items: center; font-size: 3.5rem; font-weight: bold; border: 4px solid var(--border); box-shadow: 0 10px 20px -5px var(--primary-glow);">
                    <?= strtoupper(substr($user_data['email'], 0, 2)) ?>
                </div>
            <?php endif; ?>
        </div>
        <h3 style="margin-bottom: 8px; font-size: 1.5rem;"><?= e($user_data['first_name'] . ' ' . $user_data['last_name']) ?></h3>
        <p style="color: var(--primary); font-weight: 700; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">System Administrator</p>
        
        <div style="margin-top: 32px; background: var(--card-bg); padding: 20px; border-radius: 16px; border: 1px solid var(--border); text-align: left;">
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                <span style="color: var(--text-muted);">Email</span>
                <span style="font-weight: 600;"><?= e($user_data['email']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                <span style="color: var(--text-muted);">Phone</span>
                <span style="font-weight: 600;"><?= e($user_data['phone']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border);">
                <span style="color: var(--text-muted);">National ID</span>
                <span style="font-weight: 600;"><?= e($user_data['national_id'] ?: 'Not set') ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0;">
                <span style="color: var(--text-muted);">Joined</span>
                <span style="font-weight: 600;"><?= date('M j, Y', strtotime($user_data['created_at'])) ?></span>
            </div>
        </div>
    </div>

    <!-- Right Column: Forms -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        
        <!-- Edit Info -->
        <div class="card">
            <h3><i class="fas fa-user-edit"></i> Personal Information</h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label>Profile Photo</label>
                    <input type="file" name="photo" id="photoUpload" accept="image/*" style="display: none;">
                    <label for="photoUpload" class="btn-outline" style="display: flex; width: 100%; padding: 12px; cursor: pointer;">
                        <i class="fas fa-camera"></i> <span id="fileName">Choose new image...</span>
                    </label>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?= e($user_data['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?= e($user_data['last_name']) ?>" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?= e($user_data['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?= e($user_data['phone']) ?>" required>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="<?= e($user_data['date_of_birth']) ?>">
                    </div>
                    <div class="form-group">
                        <label>National ID / SSN</label>
                        <input type="text" name="national_id" value="<?= e($user_data['national_id']) ?>" placeholder="123-45-6789">
                    </div>
                </div>
                <div class="form-group">
                    <label>Home Address</label>
                    <input type="text" name="address" value="<?= e($user_data['address']) ?>" placeholder="123 Main St, Helsinki">
                </div>
                
                <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; margin-left: auto; display: block;">Save Info</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="card">
            <h3><i class="fas fa-lock"></i> Change Password</h3>
            <form method="post">
                <input type="hidden" name="action" value="update_password">
                
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>
                </div>
                
                <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; margin-left: auto; display: block; background: linear-gradient(135deg, var(--danger), #b91c1c);">
                    <i class="fas fa-key"></i> Update Password
                </button>
            </form>
        </div>

    </div>
</div>

<script>
    document.getElementById('photoUpload').addEventListener('change', function(e) {
        var fileName = e.target.files[0] ? e.target.files[0].name : 'Choose new image...';
        document.getElementById('fileName').textContent = fileName;
    });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
