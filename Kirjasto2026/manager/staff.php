<?php
// manager/staff.php — Manager HR Page (Add & View Staff)

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$branch_id = $user['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;
$all_branches = $pdo->query("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name")->fetchAll();

$flash = '';
$error = '';

// ---- ADD STAFF ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_staff') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $dob = $_POST['dob'] ?? null;
    $national_id = trim($_POST['national_id'] ?? '');
    $target_branch_id = (int)($_POST['branch_id'] ?? $branch_id);
    $password = $_POST['password'];
    $profile_image = null;

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = 'Name, email, and password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'This email is already registered.';
        } else {
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $profile_image = 'staff_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], __DIR__ . '/../uploads/' . $profile_image)) {
                        $profile_image = null;
                    }
                }
            }

            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (first_name, last_name, email, phone, address, date_of_birth, national_id, password_hash, role, branch_id, profile_image)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'staff', ?, ?)
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone, $address, $dob ?: null, $national_id ?: null, $hash, $target_branch_id, $profile_image]);
                $flash = 'Staff member created successfully.';
            } catch (PDOException $e) {
                $error = 'Failed to create staff: ' . $e->getMessage();
            }
        }
    }
}

// ---- FETCH STAFF ----
$staff_members = [];
if ($branch_id) {
    $stmt = $pdo->prepare("
        SELECT u.*, b.name AS branch_name
        FROM users u
        LEFT JOIN branches b ON b.id = u.branch_id
        WHERE u.role = 'staff' AND u.branch_id = ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$branch_id]);
    $staff_members = $stmt->fetchAll();
}

$page_title = 'Manage Staff';
require_once __DIR__ . '/../includes/manager_header.php';
?>

<!-- Load Flatpickr CSS (Dark Mode) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
    /* Force Flatpickr to use the site's dark colors */
    .flatpickr-calendar {
        background: var(--card-solid) !important;
        border: 1px solid var(--border) !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;
        border-radius: 12px !important;
    }
    .flatpickr-day.selected {
        background: linear-gradient(135deg, #f59e0b, #d97706) !important;
        border-color: #f59e0b !important;
        color: white !important;
    }
    .flatpickr-months, .flatpickr-weekdays {
        background: var(--bg-alt) !important;
        border-radius: 12px 12px 0 0;
    }
    .flatpickr-month {
        color: var(--text) !important;
        fill: var(--text) !important;
    }
    .flatpickr-current-month .flatpickr-monthDropdown-months {
        color: var(--text) !important;
    }
    .flatpickr-weekday {
        color: var(--text-muted) !important;
    }
    .flatpickr-day {
        color: var(--text) !important;
        border-radius: 8px !important;
    }
    .flatpickr-day:hover {
        background: var(--bg-alt) !important;
    }
    .flatpickr-day.today {
        border-color: #f59e0b !important;
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Manage Staff</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
            <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Add and view staff members for your branch.
        </p>
    </div>
    <button onclick="document.getElementById('addStaffModal').style.display='flex'" class="btn-primary" style="width: auto; padding: 12px 24px; background: linear-gradient(135deg, #f59e0b, #d97706);">
        <i class="fas fa-user-plus"></i> Add Staff
    </button>
</div>

<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<!-- Staff Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Branch</th>
                    <th>National ID</th>
                    <th>Contact</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staff_members as $s): ?>
                    <tr>
                        <td style="display: flex; align-items: center; gap: 16px;">
                            <?php if (!empty($s['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $s['profile_image'])): ?>
                                <img src="<?= UPLOADS_URL . e($s['profile_image']) ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border);">
                            <?php else: ?>
                                <div style="width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #f59e0b, #ea580c); color: white; display: grid; place-items: center; font-weight: bold;">
                                    <?= strtoupper(substr($s['email'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong style="display: block; color: var(--text);"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($s['email']) ?></small>
                            </div>
                        </td>
                        <td>
                            <span style="padding: 4px 10px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                <?= e($s['branch_name'] ?: 'Unassigned') ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted); font-family: monospace;"><?= e($s['national_id'] ?: '—') ?></td>
                        <td style="color: var(--text-muted); font-size: 0.9rem;">
                            <?= e($s['phone'] ?: '—') ?><br>
                            <small><?= e($s['address'] ?: '') ?></small>
                        </td>
                        <td style="color: var(--text-muted);"><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($staff_members)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">No staff members yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Staff Modal -->
<div id="addStaffModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto; background: var(--card-solid);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0;"><i class="fas fa-user-plus" style="color: #f59e0b;"></i> Add New Staff</h3>
            <button onclick="document.getElementById('addStaffModal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">&times;</button>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_staff">

            <div class="form-group">
                <label>Profile Photo</label>
                <input type="file" name="profile_image" accept="image/*">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required placeholder="Jane">
                </div>
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required placeholder="Smith">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required placeholder="jane@kirjasto.local">
                </div>
                <div class="form-group">
                    <label>Assign to Branch *</label>
                    <select name="branch_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
                        <?php foreach ($all_branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $b['id'] == $branch_id ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" placeholder="+358 40 123 4567">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="text" name="dob" id="dobPicker" placeholder="Select date..." style="cursor: pointer; background: var(--bg);">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>National ID / SSN</label>
                    <input type="text" name="national_id" placeholder="130595-124B">
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

            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" onclick="document.getElementById('addStaffModal').style.display='none'" class="btn-outline">Cancel</button>
                <button type="submit" class="btn-primary" style="width: auto; background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-check"></i> Create Staff
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Initialize Flatpickr with the dark theme
    flatpickr("#dobPicker", {
        dateFormat: "Y-m-d",
        maxDate: "today",
        theme: "dark"
    });
</script>

<?php require_once __DIR__ . '/../includes/manager_footer.php'; ?>
