<?php
// staff/members.php — Create & Manage Members (Branch Scoped)

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fixed role check
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
                // Assign the member to the staff member's branch
                $stmt = $pdo->prepare("
                    INSERT INTO users (first_name, last_name, email, phone, address, password_hash, role, membership_number, profile_image, branch_id)
                    VALUES (?, ?, ?, ?, ?, ?, 'member', ?, ?, ?)
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone, $address, $hash, $membership_number, $profile_image, $branch_id]);
                $flash = 'Member created! Membership #: <strong>' . $membership_number . '</strong>';
            } catch (PDOException $e) {
                $error = 'Failed to create member: ' . $e->getMessage();
            }
        }
    }
}

// ---- FETCH MEMBERS (Branch Scoped) ----
$search = trim($_GET['search'] ?? '');
$members = [];

if ($branch_id) {
    $query = "SELECT * FROM users WHERE role = 'member' AND branch_id = ?";
    $params = [$branch_id];

    if ($search !== '') {
        $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR membership_number LIKE ?)";
        $term = '%' . $search . '%';
        $params = array_merge($params, [$term, $term, $term, $term]);
    }
    $query .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $members = $stmt->fetchAll();
}

$page_title = 'Manage Members';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<div style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Manage Members</h1>
    <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
        <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Register new members and view the member list.
    </p>
</div>

<?php if (!$branch_id): ?>
    <div class="flash flash-error" style="margin-bottom: 24px;"><i class="fas fa-exclamation-triangle"></i> Your account is not assigned to a branch.</div>
<?php endif; ?>
<?php if ($flash): ?><div class="flash flash-success"><?= $flash ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<!-- Search -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <form method="get" style="display: flex; gap: 12px;">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search by name, email, or membership #..." style="flex: 1; padding: 12px 16px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: var(--font); font-size: 0.95rem; outline: none;">
        <button type="submit" class="btn-primary" style="width: auto; padding: 12px 24px; background: linear-gradient(135deg, #0ea5e9, #14b8a6);">
            <i class="fas fa-search"></i> Search
        </button>
    </form>
</div>

<!-- Members Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Membership #</th>
                    <th>Phone</th>
                    <th>Joined</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td style="display: flex; align-items: center; gap: 16px;">
                            <?php if (!empty($m['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $m['profile_image'])): ?>
                                <img src="<?= UPLOADS_URL . e($m['profile_image']) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                            <?php else: ?>
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; display: grid; place-items: center; font-weight: bold;">
                                    <?= strtoupper(substr($m['email'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong style="display: block; color: var(--text);"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($m['email']) ?></small>
                            </div>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.85rem; font-family: monospace;"><?= e($m['membership_number'] ?: '—') ?></td>
                        <td style="color: var(--text-muted);"><?= e($m['phone']) ?></td>
                        <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($m['created_at'])) ?></td>
                        <td style="text-align: right;">
                            <a href="<?= e(SITE_URL) ?>staff/member_profile.php?id=<?= $m['id'] ?>" class="btn-primary" style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; border: none; border-radius: 6px; font-weight: 600;">
                                <i class="fas fa-eye"></i> View Profile
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">No members found in your branch.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Member Modal -->
<div id="addMemberModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto; background: var(--card-solid);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0;"><i class="fas fa-user-plus" style="color: #0ea5e9;"></i> Register New Member</h3>
            <button onclick="document.getElementById('addMemberModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_member">

            <div class="form-group">
                <label>Profile Photo</label>
                <input type="file" name="profile_image" accept="image/*">
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
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="+358 40 123 4567">
            </div>
            <div class="form-group">
                <label>Home Address</label>
                <input type="text" name="address" placeholder="123 Main St, Helsinki">
            </div>
            <div class="form-group">
                <label>Initial Password *</label>
                <input type="password" name="password" required minlength="6" placeholder="••••••••">
                <small style="color: var(--text-muted); display: block; margin-top: 6px; font-size: 0.8rem;">The member can change this later.</small>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" onclick="document.getElementById('addMemberModal').style.display='none'" class="btn-outline">Cancel</button>
                <button type="submit" class="btn-primary" style="width: auto; background: linear-gradient(135deg, #0ea5e9, #14b8a6);">
                    <i class="fas fa-check"></i> Create Member
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Auto-open the modal if the URL has ?add=1 (clicked from sidebar)
    if (window.location.search.includes('add=1')) {
        document.getElementById('addMemberModal').style.display = 'flex';
    }
</script>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
