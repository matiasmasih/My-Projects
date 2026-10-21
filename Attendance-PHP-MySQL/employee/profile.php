<?php
// employee/profile.php — Edit own profile & upload photo

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch employee details securely
$stmt = $pdo->prepare("SELECT e.*, b.name AS branch_name, d.name AS dept_name
                       FROM employees e
                       LEFT JOIN branches b ON b.id = e.branch_id
                       LEFT JOIN departments d ON d.id = e.department_id
                       WHERE e.id = (SELECT employee_id FROM users WHERE id = ?) LIMIT 1");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee record not found. Please contact admin.");
}

$flash = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $photo_name = $employee['photo']; // Keep existing photo by default

    // Basic validation with fallbacks
    if (empty($first_name)) $first_name = $employee['first_name'];
    if (empty($last_name)) $last_name = $employee['last_name'];
    if (empty($email)) $email = $employee['email'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    }

    // Handle file upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = 'Invalid file type. Only JPG, PNG, and WEBP are allowed.';
        } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            $error = 'File is too large. Max size is 5MB.';
        } else {
            $new_filename = 'emp_' . $employee['id'] . '_' . time() . '.' . $ext;
            $upload_path = __DIR__ . '/../uploads/' . $new_filename;

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                $photo_name = $new_filename;
            } else {
                $error = 'Failed to upload image. Check folder permissions.';
            }
        }
    }

    if (empty($error)) {
        try {
            // Update editable fields in the employees table
            $update = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ?, photo = ? WHERE id = ?");
            $update->execute([$first_name, $last_name, $email, $phone, $photo_name, $employee['id']]);

            // Also update the email in the users table so login works with the new email
            $updateUser = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $updateUser->execute([$email, $user_id]);

            $flash = 'Profile updated successfully!';

            // Refresh data locally to show immediately
            $employee['first_name'] = $first_name;
            $employee['last_name']  = $last_name;
            $employee['email']      = $email;
            $employee['phone']      = $phone;
            $employee['photo']      = $photo_name;
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$page_title = 'My Profile';
require_once __DIR__ . '/../includes/employee_header.php';
?>

<div class="dashboard-container">

    <!-- HERO BANNER -->
    <div class="hero-banner">
        <div class="hero-content">
            <h1>My Profile</h1>
            <p><i class="fas fa-user-circle"></i> Manage your personal information and photo.</p>
        </div>
        <div class="hero-datetime">
            <div class="time" id="bannerTime"><?= date('h:i:s A') ?></div>
            <div class="date" id="bannerDate"><?= date('l, M j, Y') ?></div>
        </div>
    </div>

    <!-- FLASH MESSAGES -->
    <?php if ($flash): ?>
        <div class="flash flash-success" style="margin-bottom:20px; border-radius: 12px;">
            <i class="fas fa-check-circle"></i> <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="flash flash-error" style="margin-bottom:20px; display:block; border-radius: 12px;">
            <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="leave-layout">

        <!-- Left Column: Read-Only Info -->
        <div class="leave-form-card" style="background: var(--bg-alt); display: flex; flex-direction: column;">

            <!-- Profile Image -->
            <div class="profile-avatar-container">
                <?php if (!empty($employee['photo']) && file_exists(__DIR__ . '/../uploads/' . $employee['photo'])): ?>
                    <img src="<?= SITE_URL ?>uploads/<?= e($employee['photo']) ?>?v=<?= time() ?>" alt="Profile" class="profile-avatar-img">
                <?php else: ?>
                    <div class="profile-avatar-placeholder">
                        <?= strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <h3 style="text-align: center; margin-bottom: 4px; font-size: 1.4rem;"><?= e($employee['first_name'] . ' ' . $employee['last_name']) ?></h3>
            <p style="text-align: center; color: var(--text-muted); font-size: 0.95rem; margin-bottom: 24px;">
                <?= e($employee['position'] ?: 'Employee') ?>
            </p>

            <div class="readonly-info">
                <h4>Employment Details</h4>
                <div class="info-row">
                    <span class="info-label">Employee ID</span>
                    <span class="info-value"><?= e($employee['employee_code']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Branch</span>
                    <span class="info-value"><?= e($employee['branch_name'] ?: 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Department</span>
                    <span class="info-value"><?= e($employee['dept_name'] ?: 'N/A') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Hire Date</span>
                    <span class="info-value"><?= $employee['hire_date'] ? date('M j, Y', strtotime($employee['hire_date'])) : 'N/A' ?></span>
                </div>
            </div>
        </div>

        <!-- Right Column: Edit Form -->
        <div class="leave-form-card">
            <h3><i class="fas fa-edit" style="color:var(--primary);"></i> Edit Profile Details</h3>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">

                <!-- File Upload -->
                <div class="form-group">
                    <label>Profile Photo</label>
                    <input type="file" name="photo" id="photoUpload" accept="image/*" style="display: none !important;">
                    <label for="photoUpload" class="custom-file-upload" style="display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 14px; border: 2px dashed var(--border); border-radius: 12px; background: var(--bg-alt); color: var(--text); font-weight: 600; cursor: pointer; transition: all 0.2s ease; margin-top: 10px;">
                        <i class="fas fa-camera"></i>
                        <span id="fileName">Choose an image...</span>
                    </label>
                    <small style="color: var(--text-muted); margin-top: 8px; display: block;">Max size: 5MB (JPG, PNG, WEBP)</small>
                </div>

                <!-- Grid for the details -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 24px;">

                    <!-- Editable Name -->
                    <div class="form-group">
                        <label style="color: var(--primary);">First Name</label>
                        <input type="text" name="first_name" value="<?= e($employee['first_name']) ?>" required style="border-color: var(--primary);">
                    </div>

                    <!-- Editable Name -->
                    <div class="form-group">
                        <label style="color: var(--primary);">Last Name</label>
                        <input type="text" name="last_name" value="<?= e($employee['last_name']) ?>" required style="border-color: var(--primary);">
                    </div>

                    <!-- Locked Admin Fields -->
                    <div class="form-group">
                        <label>Employee ID (Locked)</label>
                        <input type="text" value="<?= e($employee['employee_code']) ?>" disabled style="opacity: 0.6; cursor: not-allowed; background: var(--bg-alt);">
                    </div>

                    <div class="form-group">
                        <label>Position (Locked)</label>
                        <input type="text" value="<?= e($employee['position']) ?>" disabled style="opacity: 0.6; cursor: not-allowed; background: var(--bg-alt);">
                    </div>

                    <div class="form-group">
                        <label>Branch (Locked)</label>
                        <input type="text" value="<?= e($employee['branch_name'] ?: 'N/A') ?>" disabled style="opacity: 0.6; cursor: not-allowed; background: var(--bg-alt);">
                    </div>

                    <div class="form-group">
                        <label>Department (Locked)</label>
                        <input type="text" value="<?= e($employee['dept_name'] ?: 'N/A') ?>" disabled style="opacity: 0.6; cursor: not-allowed; background: var(--bg-alt);">
                    </div>

                    <div class="form-group">
                        <label>Hire Date (Locked)</label>
                        <input type="text" value="<?= $employee['hire_date'] ? date('M j, Y', strtotime($employee['hire_date'])) : 'N/A' ?>" disabled style="opacity: 0.6; cursor: not-allowed; background: var(--bg-alt);">
                    </div>

                    <!-- Editable Email -->
                    <div class="form-group">
                        <label style="color: var(--primary);">Email Address</label>
                        <input type="email" name="email" value="<?= e($employee['email']) ?>" required style="border-color: var(--primary);">
                    </div>
                 </div>

                <!-- Editable Phone -->
                <div style="margin-top: 24px; border-top: 1px dashed var(--border); padding-top: 24px;">
                    <div class="form-group">
                        <label style="color: var(--primary);">Phone Number</label>
                        <input type="text" name="phone" value="<?= e($employee['phone']) ?>" placeholder="+358 40 123 4567" style="border-color: var(--primary);">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 30px;">
                    <button type="submit" class="btn-submit" style="width: 30%; margin-top: 0;">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    // Show selected file name when user picks an image
    document.getElementById('photoUpload').addEventListener('change', function(e) {
        var fileName = e.target.files[0] ? e.target.files[0].name : 'Choose an image...';
        document.getElementById('fileName').textContent = fileName;
    });
</script>

<?php require_once __DIR__ . '/../includes/employee_footer.php'; ?>
