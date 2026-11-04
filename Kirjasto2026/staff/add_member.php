<?php
// staff/add_member.php — Dedicated Add Member Page (Branch Scoped)

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'staff' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

$flash = '';
$error = '';

// ---- ADD MEMBER ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_member') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $password = $_POST['password'];
    $profile_image = null;

    if (!$branch_id) {
        $error = 'Your account is not assigned to a branch.';
    } elseif (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Name, email, and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'This email is already registered.';
        } else {
            $membership_number = 'MEM' . date('Y') . rand(1000, 9999);

            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $profile_image = 'member_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], __DIR__ . '/../uploads/' . $profile_image)) {
                        $profile_image = null;
                    }
                }
            }

            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (first_name, last_name, email, phone, address, password_hash, role, membership_number, profile_image, branch_id)
                    VALUES (?, ?, ?, ?, ?, ?, 'member', ?, ?, ?)
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone, $address, $hash, $membership_number, $profile_image, $branch_id]);
                $flash = 'Member created successfully! Membership #: <strong>' . $membership_number . '</strong>';
            } catch (PDOException $e) {
                $error = 'Failed to create member: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Add New Member';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Add New Member</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Register a new member for your branch.
    </p>
</div>

<?php if ($flash): ?><div class="flash flash-success" style="margin-bottom: 24px;"><i class="fas fa-check-circle"></i> <?= $flash ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error" style="margin-bottom: 24px;"><i class="fas fa-exclamation-triangle"></i> <?= e($error) ?></div><?php endif; ?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_member">

        <div style="display: flex; align-items: center; gap: 24px; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--border);">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; display: grid; place-items: center; font-size: 1.5rem; font-weight: 800; flex-shrink: 0;">
                <i class="fas fa-user-plus"></i>
            </div>
            <div style="flex: 1;">
                <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Profile Photo</label>
                <input type="file" name="profile_image" accept="image/*" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>First Name *</label>
                <input type="text" name="first_name" required placeholder="John">
            </div>
            <div class="form-group">
                <label>Last Name *</label>
                <input type="text" name="last_name" required placeholder="Doe">
            </div>
        </div>

        <div class="form-group">
            <label>Email Address *</label>
            <input type="email" name="email" required placeholder="john@example.com">
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="+358 40 123 4567">
            </div>
            <div class="form-group">
                <label>Initial Password *</label>
                <input type="password" name="password" required minlength="6" placeholder="••••••••">
            </div>
        </div>

        <div class="form-group">
            <label>Home Address</label>
            <input type="text" name="address" placeholder="123 Main St, Helsinki">
        </div>

        <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 32px;">
            <a href="<?= e(SITE_URL) ?>staff/members.php" class="btn-outline" style="padding: 12px 24px;">Cancel</a>
            <button type="submit" class="btn-primary" style="padding: 12px 24px; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-check"></i> Create Member
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
