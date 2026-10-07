<?php
// manage_branches.php — Admin: list / add / edit / deactivate library branches
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('manage_branches');
if (my_access('manage_branches') !== 'full') {
    header('Location: index.php'); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Manage Branches';
$action     = $_GET['action'] ?? 'list';
$error      = '';

// ============================================================
// ADD / EDIT (POST) — admin only
// ============================================================
if (in_array($action, ['add','edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (current_role() !== 'admin') {
        header('Location: manage_branches.php?error=forbidden');
        exit;
    }
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $branch_id     = (int)($_POST['branch_id'] ?? 0);
    $code          = strtoupper(trim($_POST['code'] ?? ''));
    $name          = trim($_POST['name'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $postal_code   = trim($_POST['postal_code'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $opening_hours = trim($_POST['opening_hours'] ?? '');
    $is_active     = isset($_POST['is_active']) ? 1 : 0;

    if ($code === '') {
        $error = 'Branch code is required.';
    } elseif (!preg_match('/^[A-Z0-9\-]{2,10}$/', $code)) {
        $error = 'Code must be 2–10 characters (A–Z, 0–9, dash).';
    } elseif ($name === '') {
        $error = 'Branch name is required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email format is invalid.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM branches WHERE code = ? AND id <> ?");
        $chk->execute([$code, $branch_id]);
        if ($chk->fetchColumn()) $error = 'That branch code is already used.';
    }

    if (!$error) {
        try {
            if ($action === 'add') {
                $pdo->prepare(
                    "INSERT INTO branches (code, name, address, city, postal_code, phone, email, opening_hours, is_active)
                     VALUES (?,?,?,?,?,?,?,?,?)"
                )->execute([
                    $code, $name,
                    $address !== '' ? $address : null,
                    $city !== '' ? $city : null,
                    $postal_code !== '' ? $postal_code : null,
                    $phone !== '' ? $phone : null,
                    $email !== '' ? $email : null,
                    $opening_hours !== '' ? $opening_hours : null,
                    $is_active
                ]);
                $branch_id = (int)$pdo->lastInsertId();
                $log_desc   = "Added branch #{$branch_id} \"$name\" ($code)";
                $log_action = 'branch_added';
            } else {
                $pdo->prepare(
                    "UPDATE branches SET code=?, name=?, address=?, city=?, postal_code=?,
                                         phone=?, email=?, opening_hours=?, is_active=?
                     WHERE id=?"
                )->execute([
                    $code, $name,
                    $address !== '' ? $address : null,
                    $city !== '' ? $city : null,
                    $postal_code !== '' ? $postal_code : null,
                    $phone !== '' ? $phone : null,
                    $email !== '' ? $email : null,
                    $opening_hours !== '' ? $opening_hours : null,
                    $is_active, $branch_id
                ]);
                $log_desc   = "Updated branch #{$branch_id} \"$name\" ($code)";
                $log_action = 'branch_updated';
            }

            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, ?, ?, 'branch', ?)")
                ->execute([$_SESSION['user_id'] ?? null, $log_action, $log_desc, $branch_id]);

            header('Location: manage_branches.php?saved=' . ($action === 'add' ? 'added' : 'updated'));
            exit;

        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// ============================================================
// TOGGLE ACTIVE (POST)
// ============================================================
if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (current_role() !== 'admin') {
        header('Location: manage_branches.php?error=forbidden');
        exit;
    }
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    $branch_id = (int)($_POST['branch_id'] ?? 0);
    if ($branch_id > 0) {
        $st = $pdo->prepare("SELECT name, is_active FROM branches WHERE id=?");
        $st->execute([$branch_id]);
        $b = $st->fetch();
        if ($b) {
            $new = $b['is_active'] ? 0 : 1;

            if ($new === 0) {
                $chk = $pdo->prepare(
                    "SELECT COUNT(*) FROM copies c JOIN shelves s ON s.id = c.shelf_id
                     WHERE s.branch_id = ? AND c.status IN ('available','borrowed')"
                );
                $chk->execute([$branch_id]);
                if ((int)$chk->fetchColumn() > 0) {
                    header('Location: manage_branches.php?error=has_copies');
                    exit;
                }
            }

            $pdo->prepare("UPDATE branches SET is_active = ? WHERE id = ?")->execute([$new, $branch_id]);
            $pdo->prepare("INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
                           VALUES (?, 'branch_status', ?, 'branch', ?)")
                ->execute([
                    $_SESSION['user_id'] ?? null,
                    ($new ? 'Activated' : 'Deactivated') . " branch #{$branch_id} \"{$b['name']}\"",
                    $branch_id
                ]);
        }
    }
    header('Location: manage_branches.php?saved=status');
    exit;
}

// ============================================================
// FORM DATA
// ============================================================
$edit_branch = null;
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
    $st->execute([$id]);
    $edit_branch = $st->fetch();
    if (!$edit_branch) { header('Location: manage_branches.php'); exit; }
}

// ============================================================
// LIST DATA
// ============================================================
$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';

$where = []; $args = [];
if ($q !== '') {
    $where[] = "(b.code LIKE ? OR b.name LIKE ? OR b.city LIKE ? OR b.email LIKE ?)";
    $like = "%$q%";
    array_push($args, $like, $like, $like, $like);
}
if ($status === 'active')   $where[] = "b.is_active = 1";
if ($status === 'inactive') $where[] = "b.is_active = 0";
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$st = $pdo->prepare(
    "SELECT b.*,
            (SELECT COUNT(*) FROM shelves s WHERE s.branch_id = b.id) AS shelf_count,
            (SELECT COUNT(*) FROM copies c JOIN shelves s ON s.id = c.shelf_id
             WHERE s.branch_id = b.id) AS copy_count,
            (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.id) AS user_count,
            (SELECT COUNT(*) FROM devices d WHERE d.branch_id = b.id) AS device_count
     FROM branches b
     $where_sql
     ORDER BY b.name"
);
$st->execute($args);
$branches = $st->fetchAll();

include 'header.php';
?>

<style>
    /* === Contain the layout so the sidebar isn't squeezed by wide tables === */
    .main-content {
        min-width: 0;
        max-width: 100%;
        overflow-x: hidden;
    }
    .main-container {
        min-width: 0;
    }

    .toolbar {
        display:flex; gap:10px; flex-wrap:nowrap; align-items:center;
        background:var(--card-bg); padding:14px; border-radius:12px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        margin-bottom:18px; overflow-x:auto;
    }
    .toolbar input, .toolbar select {
        padding:9px 12px; border:1px solid var(--border); border-radius:8px;
        background:var(--card-bg); color:var(--text); font-size:.9rem;
        white-space:nowrap;
    }
    .toolbar input[type=text] { flex:1; min-width:200px; }

    .br-card {
        background:var(--card-bg); border-radius:12px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        overflow:hidden;
        max-width:100%;
    }
    .br-scroll {
        width:100%;
        max-width:100%;
        overflow-x:auto;
        overflow-y:visible;
        -webkit-overflow-scrolling:touch;
        padding-bottom:10px;
    }
    table.br-table { width:100%; border-collapse:collapse; min-width:1200px; }

    table.br-table th, table.br-table td {
        padding: 12px 14px;
        vertical-align: middle;
        white-space: nowrap;
        border-bottom: 1px solid rgba(0,0,0,0.06);
        text-align: left;
        font-size: .9rem;
        color: var(--text);
        font-weight: 500;
    }

    table.br-table thead th {
        background: var(--primary);              /* teal header */
        color: #ffffff;                          /* white text — clearly visible */
        font-weight: 600;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        padding: 14px;                           /* a bit more vertical space */
        border-bottom: none;
        position: sticky;                        /* stays visible when scrolling */
        top: 0;
        z-index: 2;
    }

    table.br-table tbody tr:hover { background:#f8fffe; }

    .badge { display:inline-block; padding:3px 9px; border-radius:8px; font-size:.72rem; font-weight:600; white-space:nowrap; }
    .b-active   { background:#d1fae5; color:#065f46; }
    .b-inactive { background:#e2e8f0; color:#475569; }
    .b-code     { background:#f0fdfa; color:#115e59; font-family:monospace; }

    .row-actions { display:flex; gap:6px; white-space:nowrap; }
    .row-actions form { display:inline-flex; margin:0; }
    .row-actions a, .row-actions button {
        padding:5px 12px; border-radius:6px; font-size:.78rem; font-weight:600;
        text-decoration:none; display:inline-flex; align-items:center; gap:4px;
        border:none; cursor:pointer; height:30px; box-sizing:border-box;
        white-space:nowrap;
    }
    .btn-edit-sm { background:#0d9488; color:#fff; }
    .btn-edit-sm:hover { background:#0f766e; }
    .btn-warn-sm { background:#d90606; color:#fff; }
    .btn-warn-sm:hover { background:#b45309; }
    .btn-ok-sm { background:#10b981; color:#fff; }
    .btn-ok-sm:hover { background:#059669; }

    .form-panel {
        background:var(--card-bg); border-radius:var(--radius); padding:22px;
        box-shadow:var(--shadow); border:1px solid rgba(13,148,136,0.08);
        max-width:820px; margin:0 auto;
    }
    .form-panel h3 { margin:0 0 18px; display:flex; align-items:center; gap:8px; }
    .form-panel h3 i { color:var(--primary); }
    .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    @media (max-width:700px){ .grid2 { grid-template-columns:1fr; } }
    .grid2 label { display:block; font-weight:600; font-size:.85rem; margin-bottom:6px; }
    .grid2 input, .grid2 select, .grid2 textarea {
        width:100%; padding:10px 12px; border:1px solid var(--border);
        border-radius:10px; font-size:.92rem; background:var(--card-bg); color:var(--text);
        font-family:inherit;
    }
    .grid2 textarea { resize:vertical; }
    .span2 { grid-column:span 2; }
    @media (max-width:700px){ .span2 { grid-column:span 1; } }
    .checkbox-row { display:flex; align-items:center; gap:8px; padding:10px 0; }
    .checkbox-row input { width:auto; }
</style>

<?php if (isset($_GET['saved'])): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-check-circle-fill"></i>
        Branch <?= $_GET['saved'] === 'added' ? 'created' : ($_GET['saved'] === 'updated' ? 'updated' : 'status changed') ?>.
    </div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'has_copies'): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> Cannot deactivate — this branch still has copies on its shelves. Move or delete them first.
    </div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'forbidden'): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> Only administrators can modify branches.
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (in_array($action, ['add','edit'], true) && current_role() === 'admin'): ?>

    <div class="form-panel">
        <h3>
            <i class="bi bi-<?= $action === 'add' ? 'plus-circle' : 'pencil-square' ?>"></i>
            <?= $action === 'add' ? 'Add Branch' : 'Edit Branch' ?>
        </h3>

        <form method="POST" action="manage_branches.php?action=<?= $action ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="branch_id" value="<?= (int)($edit_branch['id'] ?? 0) ?>">

            <div class="grid2">
                <div>
                    <label>Branch Code *</label>
                    <input type="text" name="code" required maxlength="10" style="text-transform:uppercase;"
                           value="<?= htmlspecialchars($edit_branch['code'] ?? '') ?>"
                           placeholder="e.g. HEL01">
                    <small style="color:var(--text-muted);font-size:.78rem;">2–10 chars (A–Z, 0–9, dash)</small>
                </div>
                <div>
                    <label>Branch Name *</label>
                    <input type="text" name="name" required
                           value="<?= htmlspecialchars($edit_branch['name'] ?? '') ?>"
                           placeholder="e.g. Helsinki Central Library">
                </div>

                <div class="span2">
                    <label>Street Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($edit_branch['address'] ?? '') ?>">
                </div>

                <div>
                    <label>City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($edit_branch['city'] ?? '') ?>">
                </div>
                <div>
                    <label>Postal Code</label>
                    <input type="text" name="postal_code" value="<?= htmlspecialchars($edit_branch['postal_code'] ?? '') ?>">
                </div>

                <div>
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($edit_branch['phone'] ?? '') ?>">
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($edit_branch['email'] ?? '') ?>">
                </div>

                <div class="span2">
                    <label>Opening Hours</label>
                    <input type="text" name="opening_hours"
                           value="<?= htmlspecialchars($edit_branch['opening_hours'] ?? '') ?>"
                           placeholder="e.g. Mon–Fri 9:00–18:00, Sat 10:00–16:00">
                </div>

                <div class="span2">
                    <div class="checkbox-row">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               <?= ($edit_branch['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="is_active" style="margin:0;font-weight:500;">Active (accepting new users and copies)</label>
                    </div>
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                <a href="manage_branches.php" class="btn-secondary-custom">← Back</a>
                <button type="submit" class="btn-add">
                    <i class="bi bi-check-circle"></i> <?= $action === 'add' ? 'Create Branch' : 'Save Changes' ?>
                </button>
            </div>
        </form>
    </div>

<?php else: ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;"><i class="bi bi-building" style="color:var(--primary);"></i> Branches (<?= count($branches) ?>)</h2>
        <?php if (current_role() === 'admin'): ?>
            <a href="manage_branches.php?action=add" class="btn-add" style="text-decoration:none;">
                <i class="bi bi-plus-circle-fill"></i> Add Branch
            </a>
        <?php endif; ?>
    </div>

    <form class="toolbar" method="get" action="manage_branches.php">
        <input type="text" name="q" placeholder="Search code, name, city, email…" value="<?= htmlspecialchars($q) ?>">
        <select name="status">
            <?php foreach (['all'=>'All','active'=>'Active only','inactive'=>'Inactive only'] as $c=>$l): ?>
                <option value="<?= $c ?>" <?= $status===$c?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-add" style="white-space:nowrap;"><i class="bi bi-search"></i> Filter</button>
        <a href="manage_branches.php" class="btn-secondary-custom" style="white-space:nowrap;">Reset</a>
    </form>

    <?php if (!$branches): ?>
        <div class="br-card" style="text-align:center;padding:50px;">
            <i class="bi bi-building" style="font-size:3rem;color:var(--text-muted);"></i>
            <p style="color:var(--text-muted);margin-top:12px;">No branches match.</p>
        </div>
    <?php else: ?>
    <div class="br-card">
        <div class="br-scroll">
            <table class="br-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>City</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Opening Hours</th>
                        <th>Shelves</th>
                        <th>Copies</th>
                        <th>Users</th>
                        <th>Devices</th>
                        <th>Status</th>
                        <?php if (current_role() === 'admin'): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($branches as $b): ?>
                    <tr>
                        <td><span class="badge b-code"><?= htmlspecialchars($b['code']) ?></span></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($b['name']) ?></td>
                        <td><?= htmlspecialchars($b['city'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($b['phone'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($b['email'] ?? '—') ?></td>
                        <td style="font-size:.85rem;color:var(--text);"><?= htmlspecialchars($b['opening_hours'] ?? '—') ?></td>
                        <td><?= (int)$b['shelf_count'] ?></td>
                        <td><?= (int)$b['copy_count'] ?></td>
                        <td><?= (int)$b['user_count'] ?></td>
                        <td><?= (int)$b['device_count'] ?></td>
                        <td>
                            <?php if ($b['is_active']): ?>
                                <span class="badge b-active">active</span>
                            <?php else: ?>
                                <span class="badge b-inactive">inactive</span>
                            <?php endif; ?>
                        </td>
                        <?php if (current_role() === 'admin'): ?>
                            <td>
                                <div class="row-actions">
                                    <a href="manage_branches.php?action=edit&id=<?= (int)$b['id'] ?>" class="btn-edit-sm">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <form method="POST" action="manage_branches.php?action=toggle"
                                          onsubmit="return confirm('<?= $b['is_active'] ? 'Deactivate' : 'Activate' ?> this branch?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="branch_id" value="<?= (int)$b['id'] ?>">
                                        <?php if ($b['is_active']): ?>
                                            <button type="submit" class="btn-warn-sm"><i class="bi bi-pause-circle"></i> Deactivate</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-ok-sm"><i class="bi bi-play-circle"></i> Activate</button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p style="color:var(--text-muted);font-size:.82rem;margin-top:10px;">
        Showing <?= count($branches) ?> branch(es).
    </p>
    <?php endif; ?>

<?php endif; ?>

<?php include 'footer.php'; ?>
