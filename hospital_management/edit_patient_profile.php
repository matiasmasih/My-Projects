<?php
session_start();
include 'config.php';
include_once 'access_control.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Allow ONLY patient
if (current_role() !== 'patient') {
    header("Location: login.php");
    exit;
}

// Get patient by session email
$patient = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE email = ?");
    $stmt->execute([$_SESSION['email'] ?? '']);
    $patient = $stmt->fetch();
} catch (Exception $e) {}

if (!$patient) {
    header("Location: patient_profile.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone           = trim($_POST['phone'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $emergency_name  = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_phone = trim($_POST['emergency_contact_phone'] ?? '');

    $new_image_path = $patient['profile_image'] ?? null;

    // ---- Profile image upload ----
    if (!empty($_FILES['profile_image']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxSize = 8 * 1024 * 1024;

        $fileName  = $_FILES['profile_image']['name'];
        $fileTmp   = $_FILES['profile_image']['tmp_name'];
        $fileSize  = $_FILES['profile_image']['size'];
        $fileError = $_FILES['profile_image']['error'] ?? -1;
        $ext       = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        error_log("UPLOAD DEBUG: name=$fileName tmp=$fileTmp size=$fileSize error=$fileError ext=$ext");

        if ($fileError !== UPLOAD_ERR_OK) {
            $codes = [
                UPLOAD_ERR_INI_SIZE   => "File exceeds 2MB limit.",
                UPLOAD_ERR_FORM_SIZE  => "File exceeds form limit.",
                UPLOAD_ERR_PARTIAL    => "File only partially uploaded.",
                UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
                UPLOAD_ERR_NO_TMP_DIR => "No temp folder.",
                UPLOAD_ERR_CANT_WRITE => "Can't write to disk.",
                UPLOAD_ERR_EXTENSION  => "Blocked by PHP extension.",
            ];
            $error = "Upload failed (code $fileError). " . ($codes[$fileError] ?? "Unknown error.");
        } elseif (!in_array($ext, $allowed)) {
            $error = "Image must be JPG, PNG, GIF, or WEBP.";
        } elseif ($fileSize > $maxSize) {
            $error = "Image must be under 2MB.";
        } else {
            $uploadDir = __DIR__ . '/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            $newFileName = 'avatar_' . $patient['id'] . '_' . time() . '.' . $ext;
            $destPath    = $uploadDir . $newFileName;
            $relPath     = 'uploads/avatars/' . $newFileName;

            if (move_uploaded_file($fileTmp, $destPath)) {
                if (!empty($patient['profile_image']) && file_exists(__DIR__ . '/' . $patient['profile_image'])) {
                    @unlink(__DIR__ . '/' . $patient['profile_image']);
                }
                $new_image_path = $relPath;
            } else {
                $error = "Failed to move file. dest=$destPath | writable="
                       . (is_writable($uploadDir) ? 'YES' : 'NO')
                       . " | tmp_exists=" . (file_exists($fileTmp) ? 'YES' : 'NO');
            }
        }
    }

    // ---- Validate + Save ----
    if ($error === '') {
        if ($email === '') {
            $error = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address.";
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM patients WHERE email = ? AND id != ?");
                $check->execute([$email, $patient['id']]);
                if ($check->fetch()) {
                    $error = "Email is already used by another patient.";
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE patients SET
                            phone = ?, email = ?, address = ?,
                            emergency_contact_name = ?, emergency_contact_phone = ?,
                            profile_image = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$phone, $email, $address, $emergency_name, $emergency_phone, $new_image_path, $patient['id']]);

                    // Keep users table in sync if email changed
                    if ($email !== $patient['email']) {
                        try {
                            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE email = ?");
                            $stmt->execute([$email, $patient['email']]);
                            $_SESSION['email'] = $email;
                        } catch (Exception $e) {}
                    }

                    $_SESSION['flash_success'] = "Profile updated successfully.";
                    header("Location: patient_profile.php");
                    exit;
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'Edit Profile';
include 'patient_header.php';
?>

<?php if ($error): ?>
    <div class="alert" style="border-radius:12px; padding:12px 16px; margin-bottom:15px; background:#fee2e2; color:#991b1b;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="patient_profile.php" class="btn" style="background:#64748b; color:white; border-radius:8px; padding:8px 16px; text-decoration:none; font-weight:600;">
        <i class="bi bi-arrow-left"></i> Back to Profile
    </a>
    <span style="color: var(--text-muted); font-size:0.85rem;">
        <i class="bi bi-info-circle"></i> Medical details (MRN, DOB, gender, ID) are managed by the hospital.
    </span>
</div>

<div class="table-card">
    <h5><i class="bi bi-pencil-square"></i> Edit Profile</h5>

    <form method="POST" enctype="multipart/form-data">

        <div class="mb-4" style="display:flex; gap:20px; align-items:center; flex-wrap:wrap;">
            <div>
                <?php if (!empty($patient['profile_image']) && file_exists(__DIR__ . '/' . $patient['profile_image'])): ?>
                    <img src="<?= htmlspecialchars($patient['profile_image']) ?>" id="preview"
                         style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid var(--primary);">
                <?php else: ?>
                    <img src="" id="preview" style="display:none; width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid var(--primary);">
                    <div id="previewPlaceholder" style="width:80px; height:80px; border-radius:50%; background:rgba(14,165,233,0.15); color:var(--primary); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.8rem; border:3px solid var(--primary);">
                        <?= htmlspecialchars(strtoupper(substr($patient['first_name'] ?? 'P', 0, 1) . substr($patient['last_name'] ?? '', 0, 1))) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div style="flex:1; min-width:220px;">
                <label class="form-label" style="font-weight:700;">Profile Photo</label>
                <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/*" onchange="previewImage(event)">
                <small style="color: var(--text-muted); font-size:0.8rem;">JPG, PNG, GIF, or WEBP. Max 8MB.</small>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Email <span style="color:#ef4444;">*</span></label>
                <input type="email" name="email" class="form-control" required
                       value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control"
                       value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
            </div>

            <div class="col-md-12">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control"
                       value="<?= htmlspecialchars($patient['address'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" class="form-control"
                       value="<?= htmlspecialchars($patient['emergency_contact_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Emergency Contact Phone</label>
                <input type="text" name="emergency_contact_phone" class="form-control"
                       value="<?= htmlspecialchars($patient['emergency_contact_phone'] ?? '') ?>">
            </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add" style="background: var(--primary); color:white; border:none; border-radius:8px; padding:8px 16px; font-weight:600;">
                <i class="bi bi-save"></i> Save Changes
            </button>
            <a href="patient_profile.php" class="btn" style="background:#64748b; color:white; border-radius:8px; padding:8px 16px; text-decoration:none; font-weight:600;">
                <i class="bi bi-x-lg"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
function previewImage(event) {
    const file = event.target.files[0];
    if (!file) return;
    const preview = document.getElementById('preview');
    const placeholder = document.getElementById('previewPlaceholder');
    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
        if (placeholder) placeholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
}
</script>

<?php include 'patient_footer.php'; ?>
