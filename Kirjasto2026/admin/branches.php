<?php
// admin/branches.php — Manage Library Branches
// Kirjasto 2026 — Phase 9

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin' && $user['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$flash = '';
$error = '';

// ---- ADD BRANCH ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if ($name === '') {
        $error = 'Branch name is required.';
    } else {
        $pdo->prepare("INSERT INTO branches (name, address, phone, is_active) VALUES (?, ?, ?, 1)")
            ->execute([$name, $address, $phone]);
        $flash = 'Branch added successfully.';
    }
}

// ---- TOGGLE ACTIVE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $id = (int)$_POST['id'];
    $pdo->prepare("UPDATE branches SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    $flash = 'Branch status updated.';
}

// ---- FETCH BRANCHES WITH COUNTS ----
$branches = $pdo->query("
    SELECT b.*, 
           (SELECT COUNT(*) FROM book_copies WHERE branch_id = b.id) as book_count,
           (SELECT COUNT(*) FROM devices WHERE branch_id = b.id) as device_count,
           (SELECT COUNT(*) FROM users WHERE branch_id = b.id) as user_count
    FROM branches b
    ORDER BY b.name ASC
")->fetchAll();

$page_title = 'Manage Branches';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="margin-bottom: 32px; display: flex; justify-content: space-between; align-items: flex-end;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Manage Branches</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">Add and manage physical library locations.</p>
    </div>
</div>

<?php if ($flash): ?>
    <div class="flash flash-success" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; color: #10b981; font-weight: 600;">
        <i class="fas fa-check-circle"></i> <?= e($flash) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="flash flash-error" style="margin-bottom: 24px; padding: 16px; border-radius: 8px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; color: #ef4444; font-weight: 600;">
        <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Changed grid layout to give more width to the table -->
<div style="display: grid; grid-template-columns: 1fr 2.2fr; gap: 32px; align-items: start;">
    
    <!-- Add Branch Form -->
    <div class="card">
        <h3><i class="fas fa-plus-circle" style="color: #3b82f6;"></i> Add New Branch</h3>
        <form method="POST" style="margin-top: 24px;">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label>Branch Name</label>
                <input type="text" name="name" required placeholder="e.g. Central Library" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" placeholder="e.g. 123 Main St, Helsinki" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="+358 40 123 4567" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text);">
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 32px;">
                <button type="submit" style="padding: 12px 32px; border-radius: 8px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; font-weight: 700; cursor: pointer; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
                    <i class="fas fa-save"></i> Add Branch
                </button>
            </div>
        </form>
    </div>

    <!-- Branch List -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); background: var(--bg-alt);">
            <h3 style="margin: 0;"><i class="fas fa-map-marker-alt" style="color: #3b82f6;"></i> Active Branches</h3>
        </div>
        
        <div style="overflow-x: auto;">
            <table class="table" style="width: 100%; border-collapse: collapse; text-align: left; white-space: nowrap;">
                <thead style="background: var(--card-bg);">
                    <tr>
                        <th style="padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Branch</th>
                        <th style="padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Inventory</th>
                        <th style="padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted);">Status</th>
                        <th style="padding: 16px 24px; border-bottom: 1px solid var(--border); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $b): ?>
                        <tr>
                            <td style="padding: 20px 24px; border-bottom: 1px solid var(--border);">
                                <strong style="display: block; color: var(--text); font-size: 1rem; margin-bottom: 4px;"><?= e($b['name']) ?></strong>
                                <small style="color: var(--text-muted);"><i class="fas fa-map-pin"></i> <?= e($b['address'] ?: 'No address') ?></small>
                            </td>
                            <td style="padding: 20px 24px; border-bottom: 1px solid var(--border);">
                                <div style="display: flex; gap: 16px; font-size: 0.85rem; color: var(--text-muted);">
                                    <span title="Book Copies"><i class="fas fa-book" style="color: #10b981;"></i> <?= $b['book_count'] ?></span>
                                    <span title="Devices"><i class="fas fa-laptop" style="color: #0ea5e9;"></i> <?= $b['device_count'] ?></span>
                                    <span title="Users"><i class="fas fa-users" style="color: #8b5cf6;"></i> <?= $b['user_count'] ?></span>
                                </div>
                            </td>
                            <td style="padding: 20px 24px; border-bottom: 1px solid var(--border);">
                                <?php if ($b['is_active']): ?>
                                    <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Active</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 20px 24px; border-bottom: 1px solid var(--border); text-align: right;">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                    <?php if ($b['is_active']): ?>
                                        <button type="submit" style="padding: 6px 16px; font-size: 0.75rem; border-radius: 50px; border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.1); color: #ef4444; cursor: pointer; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.2s;" onmouseover="this.style.background='#ef4444'; this.style.color='white';" onmouseout="this.style.background='rgba(239, 68, 68, 0.1)'; this.style.color='#ef4444';">
                                            <i class="fas fa-ban"></i> Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" style="padding: 6px 16px; font-size: 0.75rem; border-radius: 50px; border: 1px solid rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.1); color: #10b981; cursor: pointer; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.2s;" onmouseover="this.style.background='#10b981'; this.style.color='white';" onmouseout="this.style.background='rgba(16, 185, 129, 0.1)'; this.style.color='#10b981';">
                                            <i class="fas fa-check"></i> Activate
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
