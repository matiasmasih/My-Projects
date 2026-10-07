<?php
// manage_members.php — Librarian: list / view / edit / suspend member accounts
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('manage_members');
if (my_access('manage_members') !== 'full') {
    header('Location: index.php'); exit;
}

// Generate CSRF token if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Manage Members';
$action     = $_GET['action'] ?? 'list';
$error      = '';

// ============================================================
// EDIT (POST)
// ============================================================
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die('Invalid CSRF token');
    }

    $user_id     = (int)($_POST['user_id'] ?? 0);
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $country     = trim($_POST['country'] ?? '');
    $branch_id   = (int)($_POST['branch_id'] ?? 0) ?: null;
    $status      = $_POST['status'] ?? 'active';
    
    // Non-admins cannot alter roles
    $role = (current_role() === 'admin') ? ($_POST['role'] ?? 'member') : 'member';

    if ($first_name === '' || $last_name === '') {
        $error = 'First and last name are required.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email is required.';
    } elseif (!in_array($status, ['active','suspended','expired'], true)) {
        $error = 'Invalid status.';
    } elseif (!in_array($role, ['guest','member','librarian','admin'], true)) {
        $error = 'Invalid role.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $chk->execute([$email, $user_id]);
        if ($chk->fetchColumn()) $error = 'That email is used by another account.';
    }

    if (!$error) {
        try {
            if (current_role() === 'admin') {
                $pdo->prepare(
                    "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, address=?,
                                     city=?, postal_code=?, country=?, branch_id=?, status=?, role=?
                     WHERE id=?"
                )->execute([
                    $first_name, $last_name, $email,
                    $phone !== '' ? $phone : null,
                    $address !== '' ? $address : null,
                    $city !== '' ? $city : null,
                    $postal_code !== '' ? $postal_code : null,
                    $country !== '' ? $country : null,
                    $branch_id, $status, $role, $user_id
                ]);
            } else {
                $pdo->prepare(
                    "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, address=?,
                                     city=?, postal_code=?, country=?, branch_id=?, status=?
                     WHERE id=?"
                )->execute([
                    $first_name, $last_name, $email,
                    $phone !== '' ? $phone : null,
                    $address !== '' ? $address : null,
                    $city !== '' ? $city : null,
                    $postal_code !== '' ? $postal_code : null,
                    $country !== '' ? $country : null,
                    $branch_id, $status, $user_id
                ]);
            }

            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'member_updated', ?, 'user', ?)")
                ->execute([$_SESSION['user_id'] ?? null, "Updated member #{$user_id} ($email)", $user_id]);

            header('Location: manage_members.php?saved=updated');
            exit;

        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// ============================================================
// QUICK ACTIONS (POST-protected suspend / activate)
// ============================================================
if (($action === 'suspend' || $action === 'activate') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die('Invalid CSRF token');
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && $id !== (int)($_SESSION['user_id'] ?? 0)) {
        $new_status = ($action === 'suspend') ? 'suspended' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $id]);
        $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                       VALUES (?, 'member_status', ?, 'user', ?)")
            ->execute([
                $_SESSION['user_id'] ?? null,
                "Set user #{$id} status to {$new_status}",
                $id
            ]);
    }
    header('Location: manage_members.php?saved=status');
    exit;
}

// ============================================================
// LOAD FORM DATA
// ============================================================
$all_branches = $pdo->query("SELECT id, name, city FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();

$edit_member = null;
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare(
        "SELECT u.*, b.name AS branch_name
         FROM users u LEFT JOIN branches b ON b.id = u.branch_id
         WHERE u.id = ?"
    );
    $st->execute([$id]);
    $edit_member = $st->fetch();
    if (!$edit_member) { header('Location: manage_members.php'); exit; }
}

// ============================================================
// LIST DATA
// ============================================================
if ($action === 'list') {
    $q      = trim($_GET['q'] ?? '');
    $role   = $_GET['role'] ?? 'member';
    $status = $_GET['status'] ?? 'all';
    $branch = (int)($_GET['branch'] ?? 0);

    $where = []; $args = [];
    if ($q !== '') {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?
                     OR u.email LIKE ? OR u.card_number LIKE ?)";
        $like = "%$q%";
        array_push($args, $like, $like, $like, $like, $like);
    }
    if ($role !== 'all') {
        $where[] = "u.role = ?";
        $args[]  = $role;
    }
    if ($status !== 'all') {
        $where[] = "u.status = ?";
        $args[]  = $status;
    }
    if ($branch > 0) {
        $where[] = "u.branch_id = ?";
        $args[]  = $branch;
    }
    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $st = $pdo->prepare(
        "SELECT u.id, u.card_number, u.username, u.email,
                u.first_name, u.last_name, u.phone, u.city,
                u.role, u.status, u.branch_id, u.avatar, u.created_at, u.last_login,
                b.name AS branch_name,
                (SELECT COUNT(*) FROM borrowings WHERE user_id=u.id AND status IN ('borrowed','overdue')) AS active_loans,
                (SELECT COUNT(*) FROM holds WHERE user_id=u.id AND status IN ('pending','ready')) AS active_holds,
                (SELECT COALESCE(SUM(amount),0) FROM fines WHERE user_id=u.id AND paid=0 AND waived=0) AS unpaid_fines
         FROM users u
         LEFT JOIN branches b ON b.id = u.branch_id
         $where_sql
         ORDER BY u.last_name, u.first_name
         LIMIT 500"
    );
    $st->execute($args);
    $members = $st->fetchAll();
}

include 'header.php';
?>

<style>
    /* Main Layout Framework */
    .main-container {
        display: flex;
        min-height: calc(100vh - 68px);
        width: 100%;
    }

    /* Fixed Sidebar Styling */
    .sidebar {
        background: var(--sidebar-bg, #1e293b);
        color: white;
        width: 250px;
        min-width: 250px;
        max-width: 250px;
        padding: 25px 0;
        position: sticky;
        top: 68px;
        height: calc(100vh - 68px);
        overflow-y: auto;
        flex-shrink: 0;
    }

    .main-content {
        flex: 1;
        min-width: 0; /* Prevents flexbox overflow bugs */
        padding: 24px;
    }

    /* Toolbar Layout & Single-Line Inputs */
    .toolbar {
        display: flex;
        gap: 10px;
        flex-wrap: nowrap;
        margin-bottom: 18px;
        align-items: center;
        background: var(--card-bg, #ffffff);
        padding: 14px;
        border-radius: 12px;
        box-shadow: var(--shadow, 0 4px 6px -1px rgba(0,0,0,0.1));
        border: 1px solid rgba(13,148,136,0.08);
        overflow-x: auto;
    }
    
    .toolbar input, .toolbar select {
        padding: 9px 12px;
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        background: var(--card-bg, #ffffff);
        color: var(--text, #0f172a);
        font-size: .9rem;
        white-space: nowrap;
    }
    
    .toolbar input[type=text] { 
        flex: 1; 
        min-width: 220px; 
    }

    /* Avatar Minimalist Design */
    .avatar-mini {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        color: #fff;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary, #0d9488), var(--accent, #14b8a6));
        overflow: hidden;
        font-size: .85rem;
        flex-shrink: 0;
    }
    .avatar-mini img { width: 100%; height: 100%; object-fit: cover; }

    /* Badges */
    .badge { display: inline-block; padding: 3px 9px; border-radius: 8px; font-size: .72rem; font-weight: 600; white-space: nowrap; }
    .b-active    { background: #d1fae5; color: #065f46; }
    .b-suspended { background: #fee2e2; color: #991b1b; }
    .b-expired   { background: #e2e8f0; color: #475569; }
    .b-admin     { background: #ede9fe; color: #5b21b6; }
    .b-librarian { background: #dbeafe; color: #1e40af; }
    .b-member    { background: #ccfbf1; color: #115e59; }
    .b-guest     { background: #e2e8f0; color: #475569; }

    /* Action Buttons */
    .row-actions { display: flex; gap: 4px; align-items: center; white-space: nowrap; }
    .row-actions form { display: inline; margin: 0; }
    .row-actions button, .row-actions a {
        padding: 6px 10px;
        border-radius: 6px;
        font-size: .75rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: none;
        cursor: pointer;
    }
    .btn-edit-sm { background: #0d9488; color: #fff; }
    .btn-warn-sm { background: #dc2626; color: #fff; }
    .btn-ok-sm   { background: #10b981; color: #fff; }

    /* Form Card Styles */
    .form-panel {
        background: var(--card-bg, #ffffff);
        border-radius: var(--radius, 12px);
        padding: 22px;
        box-shadow: var(--shadow, 0 4px 6px -1px rgba(0,0,0,0.1));
        border: 1px solid rgba(13,148,136,0.08);
        max-width: 880px;
        margin: 0 auto;
    }
    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 700px){ .grid2 { grid-template-columns: 1fr; } }
    .grid2 label { display: block; font-weight: 600; font-size: .85rem; margin-bottom: 6px; }
    .grid2 input, .grid2 select {
        width: 100%; padding: 10px 12px; border: 1px solid var(--border, #e2e8f0);
        border-radius: 10px; font-size: .92rem; background: var(--card-bg, #ffffff); color: var(--text, #0f172a);
    }
    .span2 { grid-column: span 2; }
    @media (max-width: 700px){ .span2 { grid-column: span 1; } }
    .readonly { background: #f1f5f9 !important; color: var(--text-muted, #64748b) !important; cursor: not-allowed; }

    /* Scrollable Table Component */
    .table-card {
        background: var(--card-bg, #ffffff);
        border-radius: 12px;
        box-shadow: var(--shadow, 0 4px 6px -1px rgba(0,0,0,0.1));
        border: 1px solid rgba(13,148,136,0.08);
        overflow: hidden;
    }
    .members-scroll {
        width: 100%;
        overflow-x: auto; /* Horizontal ScrollBar Enable */
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }
    table.members {
        width: 100%;
        border-collapse: collapse;
        min-width: 1100px; /* Forces Horizontal scroll when space is constrained */
    }
    table.members th,
    table.members td {
        padding: 12px 14px;
        vertical-align: middle;
        white-space: nowrap; /* Single line row lock */
        border-bottom: 1px solid rgba(0,0,0,0.05);
        text-align: left;
    }
    table.members thead th {
        background: var(--card-bg, #ffffff);
        font-weight: 600;
        font-size: .82rem;
        color: var(--text-muted, #64748b);
        text-transform: uppercase;
        letter-spacing: .03em;
        border-bottom: 2px solid var(--border, #e2e8f0);
    }
</style>

<div class="main-content">

<?php if (isset($_GET['saved'])): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i>
        <?= $_GET['saved'] === 'updated' ? 'Member updated.' : 'Status updated.' ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($action === 'edit' && $edit_member): ?>

    <!-- ========== EDIT FORM ========== -->
    <div class="form-panel">
        <h3 style="margin-bottom:18px;">
            <i class="bi bi-pencil-square"></i> Edit Member — <?= htmlspecialchars($edit_member['first_name'] . ' ' . $edit_member['last_name']) ?>
        </h3>

        <form method="POST" action="manage_members.php?action=edit">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="user_id" value="<?= (int)$edit_member['id'] ?>">

            <div class="grid2">
                <div>
                    <label>First Name *</label>
                    <input type="text" name="first_name" required value="<?= htmlspecialchars($edit_member['first_name']) ?>">
                </div>
                <div>
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required value="<?= htmlspecialchars($edit_member['last_name']) ?>">
                </div>

                <div>
                    <label>Username (read-only)</label>
                    <input type="text" class="readonly" readonly value="<?= htmlspecialchars($edit_member['username']) ?>">
                </div>
                <div>
                    <label>Card Number (read-only)</label>
                    <input type="text" class="readonly" readonly value="<?= htmlspecialchars($edit_member['card_number'] ?? '—') ?>">
                </div>

                <div class="span2">
                    <label>Email *</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($edit_member['email']) ?>">
                </div>

                <div>
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($edit_member['phone'] ?? '') ?>">
                </div>
                <div>
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="0">— None —</option>
                        <?php foreach ($all_branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= ((int)($edit_member['branch_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?><?= $b['city'] ? ' · ' . htmlspecialchars($b['city']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="span2">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($edit_member['address'] ?? '') ?>">
                </div>
                <div>
                    <label>City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($edit_member['city'] ?? '') ?>">
                </div>
                <div>
                    <label>Postal Code</label>
                    <input type="text" name="postal_code" value="<?= htmlspecialchars($edit_member['postal_code'] ?? '') ?>">
                </div>
                <div class="span2">
                    <label>Country</label>
                    <input type="text" name="country" value="<?= htmlspecialchars($edit_member['country'] ?? '') ?>">
                </div>

                <div>
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['active'=>'Active','suspended'=>'Suspended','expired'=>'Expired'] as $c=>$l): ?>
                            <option value="<?= $c ?>" <?= $edit_member['status']===$c?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Role <?= current_role() !== 'admin' ? '(admin only)' : '' ?></label>
                    <select name="role" <?= current_role() !== 'admin' ? 'disabled' : '' ?>>
                        <?php foreach (['guest'=>'Guest','member'=>'Member','librarian'=>'Librarian','admin'=>'Admin'] as $c=>$l): ?>
                            <option value="<?= $c ?>" <?= $edit_member['role']===$c?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                <a href="manage_members.php" class="btn-secondary-custom">← Back</a>
                <button type="submit" class="btn-add"><i class="bi bi-check-circle"></i> Save Changes</button>
            </div>
        </form>
    </div>

<?php else: ?>

    <!-- ========== LIST ========== -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:nowrap;gap:10px;">
        <h2 style="margin:0;white-space:nowrap;"><i class="bi bi-people" style="color:var(--primary);"></i> Members (<?= count($members) ?>)</h2>
    </div>

    <form class="toolbar" method="get" action="manage_members.php">
        <input type="text" name="q" placeholder="Search name, username, email, card…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">

        <select name="role">
            <?php foreach (['all'=>'All roles','member'=>'Members','librarian'=>'Librarians','admin'=>'Admins','guest'=>'Guests'] as $c=>$l): ?>
                <option value="<?= $c ?>" <?= (($_GET['role'] ?? 'member')===$c)?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status">
            <?php foreach (['all'=>'All statuses','active'=>'Active','suspended'=>'Suspended','expired'=>'Expired'] as $c=>$l): ?>
                <option value="<?= $c ?>" <?= (($_GET['status'] ?? 'all')===$c)?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>

        <select name="branch">
            <option value="0">All branches</option>
            <?php foreach ($all_branches as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= ((int)($_GET['branch'] ?? 0)===(int)$b['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn-add" style="white-space:nowrap;"><i class="bi bi-search"></i> Filter</button>
        <a href="manage_members.php" class="btn-secondary-custom" style="white-space:nowrap;">Reset</a>
    </form>

    <?php if (!$members): ?>
        <div class="table-card" style="text-align:center;padding:40px;">
            <i class="bi bi-people" style="font-size:3rem;color:var(--text-muted);"></i>
            <p style="color:var(--text-muted);margin-top:12px;">No members match.</p>
        </div>
    <?php else: ?>
    <div class="table-card">
        <div class="members-scroll">
            <table class="members">
                <thead>
                    <tr>
                        <th></th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Card #</th>
                        <th>Email</th>
                        <th>Branch</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Loans</th>
                        <th>Holds</th>
                        <th>Fines</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $m):
                    $initials = strtoupper(substr($m['first_name'],0,1) . substr($m['last_name'],0,1));
                ?>
                    <tr>
                        <td>
                            <div class="avatar-mini">
                                <?php if (!empty($m['avatar']) && file_exists(__DIR__ . '/uploads/avatars/' . $m['avatar'])): ?>
                                    <img src="uploads/avatars/<?= htmlspecialchars($m['avatar']) ?>" alt="">
                                <?php else: ?>
                                    <?= htmlspecialchars($initials) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <a href="manage_members.php?action=edit&id=<?= (int)$m['id'] ?>" style="color:var(--primary-dark);text-decoration:none;font-weight:600;">
                                <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                            </a>
                        </td>
                        <td>@<?= htmlspecialchars($m['username']) ?></td>
                        <td><code style="font-size:.78rem;"><?= htmlspecialchars($m['card_number'] ?? '—') ?></code></td>
                        <td><?= htmlspecialchars($m['email']) ?></td>
                        <td><?= htmlspecialchars($m['branch_name'] ?? '—') ?></td>
                        <td><span class="badge b-<?= htmlspecialchars($m['role']) ?>"><?= htmlspecialchars($m['role']) ?></span></td>
                        <td><span class="badge b-<?= htmlspecialchars($m['status']) ?>"><?= htmlspecialchars($m['status']) ?></span></td>
                        <td><?= (int)$m['active_loans'] ?></td>
                        <td><?= (int)$m['active_holds'] ?></td>
                        <td>
                            <?php if ((float)$m['unpaid_fines'] > 0): ?>
                                <span class="badge b-suspended">€<?= number_format((float)$m['unpaid_fines'],2) ?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="manage_members.php?action=edit&id=<?= (int)$m['id'] ?>" class="btn-edit-sm" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($m['status'] === 'active' && (int)$m['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                    <form method="POST" action="manage_members.php?action=suspend" onsubmit="return confirm('Suspend this member?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                        <button type="submit" class="btn-warn-sm" title="Suspend">
                                            <i class="bi bi-pause-circle"></i>
                                        </button>
                                    </form>
                                <?php elseif ($m['status'] === 'suspended'): ?>
                                    <form method="POST" action="manage_members.php?action=activate" onsubmit="return confirm('Reactivate this member?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                        <button type="submit" class="btn-ok-sm" title="Activate">
                                            <i class="bi bi-play-circle"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

</div> <!-- End .main-content -->

<?php include 'footer.php'; ?>
