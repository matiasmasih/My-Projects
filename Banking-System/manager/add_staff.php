<?php
require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

// Verify Manager Role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

if ($current_user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $address = trim($_POST['address']);
    $dob = $_POST['date_of_birth'] ?: null;
    $national_id = trim($_POST['national_id']);
    $photo_name = null;

    // SECURITY: Prevent managers from creating admins
    if ($role === 'admin') {
        $error = 'Managers cannot create Admin accounts.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'This email address is already registered.';
        } else {
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
                    $new_filename = 'staff_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $upload_path = __DIR__ . '/../uploads/' . $new_filename;

                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                        $photo_name = $new_filename;
                    } else {
                        $error = 'Failed to upload image. Check folder permissions.';
                    }
                }
            }

            if (empty($error)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, role, address, date_of_birth, national_id, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$first_name, $last_name, $email, $phone, $hash, $role, $address, $dob, $national_id, $photo_name]);
                
                $success = 'User successfully created!';
            }
        }
    }
}

$page_title = 'Add New Staff / Customer';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Add New Staff / Customer</h1>
    <p style="color: var(--text-muted);">Create a new staff member or customer profile.</p>
</div>

<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="flash flash-success"><?= e($success) ?></div><?php endif; ?>

<div class="grid-2" style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 32px; align-items: start;">
    
    <!-- Left Column: Live Preview Card -->
    <div class="card" style="background: var(--bg-alt); display: flex; flex-direction: column; text-align: center; padding: 40px 24px;">
        <div style="display: flex; justify-content: center; margin-bottom: 24px;">
            <div id="previewContainer" style="width: 160px; height: 160px; border-radius: 50%; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; display: grid; place-items: center; font-size: 3.5rem; font-weight: bold; border: 4px solid var(--border); overflow: hidden; box-shadow: 0 10px 20px -5px rgba(245, 158, 11, 0.5);">
                <i class="fas fa-user"></i>
            </div>
        </div>
        <h3 style="margin-bottom: 8px; font-size: 1.5rem;" id="previewName">New User</h3>
        <p style="color: #d97706; font-weight: 700; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;" id="previewRole">Awaiting Creation</p>
        
        <div style="margin-top: 32px; background: var(--card-bg); padding: 20px; border-radius: 16px; border: 1px solid var(--border); text-align: left;">
            <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.6;">
                <i class="fas fa-info-circle" style="color: #f59e0b;"></i> 
                Fill out the form to create a new staff or customer profile. The preview will update in real-time.
            </p>
        </div>
    </div>

    <!-- Right Column: Form -->
    <div class="card" style="display: flex; flex-direction: column; padding: 40px;">
        <form method="post" enctype="multipart/form-data" style="flex: 1; display: flex; flex-direction: column;">
            
            <div class="form-group">
                <label>Profile Photo</label>
                <input type="file" name="photo" id="photoUpload" accept="image/*" style="display: none;">
                <label for="photoUpload" style="display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; padding: 18px; border: 2px dashed var(--border); border-radius: 16px; background: var(--bg-alt); color: var(--text); font-weight: 600; cursor: pointer; transition: var(--transition);">
                    <i class="fas fa-camera" style="font-size: 1.2rem; color: #f59e0b;"></i> 
                    <span id="fileName">Choose an image...</span>
                </label>
            </div>

            <div class="grid-2" style="margin-top: 24px;">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" id="fname" required placeholder="John">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" id="lname" required placeholder="Doe">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="john@example.com">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" required placeholder="+358 40 123 4567">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth">
                </div>
                <div class="form-group">
                    <label>National ID / SSN</label>
                    <input type="text" name="national_id" placeholder="123-45-6789">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required minlength="6" placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="roleSelect" required onchange="updateRolePreview()">
                        <option value="user">Customer</option>
                        <option value="staff">Staff (Teller)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Home Address</label>
                <input type="text" name="address" placeholder="123 Main St, Helsinki">
            </div>

            <div style="margin-top: auto; display: flex; gap: 12px; justify-content: flex-end; padding-top: 24px;">
                <a href="<?= e(SITE_URL) ?>manager/index.php" class="btn-outline" style="padding: 12px 32px;">Cancel</a>
                <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; background: linear-gradient(135deg, #f59e0b, #d97706);">Create User</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Live Name Preview
    const fnameInput = document.getElementById('fname');
    const lnameInput = document.getElementById('lname');
    const previewName = document.getElementById('previewName');

    function updateNamePreview() {
        const fname = fnameInput.value;
        const lname = lnameInput.value;
        previewName.textContent = (fname + ' ' + lname).trim() || 'New User';
    }

    fnameInput.addEventListener('input', updateNamePreview);
    lnameInput.addEventListener('input', updateNamePreview);

    // Live Role Preview
    function updateRolePreview() {
        const role = document.getElementById('roleSelect').value;
        document.getElementById('previewRole').textContent = role;
    }

    // Live Image Preview
    document.getElementById('photoUpload').addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            document.getElementById('fileName').textContent = file.name;
            var reader = new FileReader();
            reader.onload = function(e) {
                var preview = document.getElementById('previewContainer');
                preview.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
            }
            reader.readAsDataURL(file);
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
