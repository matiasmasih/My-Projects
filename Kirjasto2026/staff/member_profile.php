<?php
// staff/member_profile.php — Modern Member Detail & Action Page

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch();

if ($staff['role'] !== 'staff' && $staff['role'] !== 'manager') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$member_id = (int)($_GET['id'] ?? 0);
$branch_id = $staff['branch_id'] ?? null;
$branch_name = $branch_id ? get_branch_name($branch_id) : null;

// Handle Quick Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'return_book') {
        $loan_id = (int)$_POST['loan_id'];
        $now = date('Y-m-d H:i:s');
        
        $pdo->beginTransaction();
        // Fetch loan to get copy_id
        $stmt = $pdo->prepare("SELECT book_copy_id FROM loans WHERE id = ? AND user_id = ?");
        $stmt->execute([$loan_id, $member_id]);
        $loan = $stmt->fetch();
        
        if ($loan) {
            $pdo->prepare("UPDATE loans SET returned_at = ?, status = 'returned' WHERE id = ?")->execute([$now, $loan_id]);
            $pdo->prepare("UPDATE book_copies SET status = 'available' WHERE id = ?")->execute([$loan['book_copy_id']]);
        }
        $pdo->commit();
        header("Location: ?id=$member_id");
        exit;
    }
    
    if (($_POST['action'] ?? '') === 'pay_fine') {
        $fine_id = (int)$_POST['fine_id'];
        $pdo->prepare("UPDATE fines SET status = 'paid', paid_amount = amount WHERE id = ?")->execute([$fine_id]);
        header("Location: ?id=$member_id");
        exit;
    }
}

// Fetch member details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'member' AND branch_id = ?");
$stmt->execute([$member_id, $branch_id]);
$member = $stmt->fetch();

if (!$member) {
    header('Location: ' . SITE_URL . 'staff/members.php');
    exit;
}

// Fetch Stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ?");
$stmt->execute([$member_id]);
$total_loans = $stmt->fetchColumn();

// Fetch Active Loans (with book cover)
$stmt = $pdo->prepare("
    SELECT l.*, b.title, b.author, b.cover_image 
    FROM loans l 
    JOIN book_copies bc ON bc.id = l.book_copy_id 
    JOIN books b ON b.id = bc.book_id 
    WHERE l.user_id = ? AND l.status = 'active'
    ORDER BY l.due_at ASC
");
$stmt->execute([$member_id]);
$active_loans = $stmt->fetchAll();

// Fetch Active Device Loans
$stmt = $pdo->prepare("
    SELECT dl.*, d.brand, d.model, d.image 
    FROM device_loans dl
    JOIN devices d ON d.id = dl.device_id
    WHERE dl.user_id = ? AND dl.status = 'active'
    ORDER BY dl.due_at ASC
");
$stmt->execute([$member_id]);
$active_device_loans = $stmt->fetchAll();

// Fetch Fines (with book title)
$stmt = $pdo->prepare("
    SELECT f.*, b.title 
    FROM fines f 
    LEFT JOIN loans l ON l.id = f.loan_id 
    LEFT JOIN book_copies bc ON bc.id = l.book_copy_id 
    LEFT JOIN books b ON b.id = bc.book_id
    WHERE f.user_id = ? AND f.status != 'paid'
");
$stmt->execute([$member_id]);
$fines = $stmt->fetchAll();
$total_fines = array_sum(array_column($fines, 'amount'));

$page_title = 'Member Profile';
require_once __DIR__ . '/../includes/staff_header.php';
?>

<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
    <div>
        <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">Member Profile</h1>
        <p style="color: var(--text-muted); font-size: 1rem; margin-top: 4px;">
            <?= $branch_name ? e($branch_name) . ' · ' : '' ?>Manage loans, returns, and fines for this member.
        </p>
    </div>
    <a href="<?= e(SITE_URL) ?>staff/members.php" class="btn-outline" style="padding: 10px 20px;">
        <i class="fas fa-arrow-left"></i> Back to Members
    </a>
</div>

<!-- Modern Profile Card -->
<div class="card" style="padding: 0; overflow: hidden; margin-bottom: 32px;">
    <!-- Banner -->
    <div style="height: 100px; background: linear-gradient(135deg, #0ea5e9, #14b8a6);"></div>
    
    <!-- Profile Info -->
    <div style="padding: 0 32px 32px 32px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: -50px; flex-wrap: wrap; gap: 20px;">
            
            <!-- Left side: Avatar + Name -->
            <div style="display: flex; align-items: flex-end; gap: 20px;">
                <?php if (!empty($member['profile_image']) && file_exists(__DIR__ . '/../uploads/' . $member['profile_image'])): ?>
                    <img src="<?= UPLOADS_URL . e($member['profile_image']) ?>" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 6px solid var(--card-solid); background: var(--card-solid); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <?php else: ?>
                    <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #0ea5e9, #14b8a6); color: white; display: grid; place-items: center; font-weight: 800; font-size: 2.5rem; border: 6px solid var(--card-solid); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        <?= strtoupper(substr($member['email'], 0, 2)) ?>
                    </div>
                <?php endif; ?>
                <div style="padding-bottom: 8px;">
                    <h2 style="font-size: 1.8rem; color: var(--text); margin-bottom: 4px; line-height: 1.2;"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></h2>
                    <div style="display: flex; gap: 16px; color: var(--text-muted); font-size: 0.9rem; font-weight: 500;">
                        <span><i class="fas fa-envelope"></i> <?= e($member['email']) ?></span>
                        <span><i class="fas fa-phone"></i> <?= e($member['phone'] ?: 'No phone') ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Right side: Action Buttons -->
            <div style="display: flex; gap: 12px; padding-bottom: 8px;">
                <a href="<?= e(SITE_URL) ?>staff/loans.php?user_id=<?= $member_id ?>" class="btn-primary" style="padding: 10px 20px; background: #0ea5e9; text-decoration: none; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-book"></i> Loan Book
                </a>
                <a href="<?= e(SITE_URL) ?>staff/device_loans.php?user_id=<?= $member_id ?>" class="btn-outline" style="padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; background: var(--bg-alt);">
                    <i class="fas fa-laptop"></i> Loan Device
                </a>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="grid-3" style="margin-top: 32px; gap: 16px;">
            <div style="padding: 20px; background: var(--bg-alt); border-radius: 12px; border: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Total Lifetime Loans</div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $total_loans ?></div>
            </div>
            <div style="padding: 20px; background: var(--bg-alt); border-radius: 12px; border: 1px solid var(--border);">
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Membership ID</div>
                <div style="font-size: 1.5rem; font-family: monospace; font-weight: 800; color: var(--text); margin-top: 8px;"><?= e($member['membership_number'] ?: '—') ?></div>
            </div>
            <div style="padding: 20px; background: rgba(239, 68, 68, 0.05); border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.2);">
                <div style="font-size: 0.8rem; color: var(--danger); font-weight: 700; text-transform: uppercase;">Unpaid Fines</div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--danger); margin-top: 4px;">€<?= number_format($total_fines, 2) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Lower Grid: Loans & Fines -->
<div class="grid-2" style="align-items: start; gap: 32px;">
    
    <!-- Left Column: Loans -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Book Loans -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid var(--border); background: var(--bg-alt);">
                <h3 style="margin: 0; font-size: 1.1rem;"><i class="fas fa-book-open" style="color: #0ea5e9;"></i> Active Book Loans (<?= count($active_loans) ?>)</h3>
            </div>
            <?php if (empty($active_loans)): ?>
                <p style="color: var(--text-muted); padding: 30px; text-align: center;">No active book loans.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column;">
                    <?php foreach ($active_loans as $loan): ?>
                        <div style="display: flex; align-items: center; gap: 16px; padding: 16px 20px; border-bottom: 1px solid var(--border);">
                            <?php if (!empty($loan['cover_image']) && file_exists(__DIR__ . '/../uploads/' . $loan['cover_image'])): ?>
                                <img src="<?= UPLOADS_URL . e($loan['cover_image']) ?>" style="width: 40px; height: 60px; border-radius: 4px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 40px; height: 60px; border-radius: 4px; background: var(--bg-alt); display: grid; place-items: center; color: var(--text-muted);"><i class="fas fa-book"></i></div>
                            <?php endif; ?>
                            <div style="flex: 1;">
                                <strong style="display: block; color: var(--text);"><?= e($loan['title']) ?></strong>
                                <small style="color: var(--text-muted);">by <?= e($loan['author']) ?></small>
                                <div style="font-size: 0.8rem; margin-top: 4px; color: <?= strtotime($loan['due_at']) < time() ? 'var(--danger)' : 'var(--text-muted)' ?>;">
                                    <i class="fas fa-clock"></i> Due: <?= date('M j, Y', strtotime($loan['due_at'])) ?>
                                </div>
                            </div>
                            <form method="post" style="margin: 0;">
                                <input type="hidden" name="action" value="return_book">
                                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.75rem; background: var(--success); border: none; border-radius: 6px; cursor: pointer;">
                                    <i class="fas fa-undo"></i> Return
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Device Loans -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid var(--border); background: var(--bg-alt);">
                <h3 style="margin: 0; font-size: 1.1rem;"><i class="fas fa-laptop" style="color: #0ea5e9;"></i> Active Device Loans (<?= count($active_device_loans) ?>)</h3>
            </div>
            <?php if (empty($active_device_loans)): ?>
                <p style="color: var(--text-muted); padding: 30px; text-align: center;">No active device loans.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column;">
                    <?php foreach ($active_device_loans as $dloan): ?>
                        <div style="display: flex; align-items: center; gap: 16px; padding: 16px 20px; border-bottom: 1px solid var(--border);">
                            <?php if (!empty($dloan['image']) && file_exists(__DIR__ . '/../uploads/' . $dloan['image'])): ?>
                                <img src="<?= UPLOADS_URL . e($dloan['image']) ?>" style="width: 60px; height: 40px; border-radius: 4px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 60px; height: 40px; border-radius: 4px; background: var(--bg-alt); display: grid; place-items: center; color: var(--text-muted);"><i class="fas fa-laptop"></i></div>
                            <?php endif; ?>
                            <div style="flex: 1;">
                                <strong style="display: block; color: var(--text);"><?= e($dloan['brand'] . ' ' . $dloan['model']) ?></strong>
                                <div style="font-size: 0.8rem; margin-top: 4px; color: <?= strtotime($dloan['due_at']) < time() ? 'var(--danger)' : 'var(--text-muted)' ?>;">
                                    <i class="fas fa-clock"></i> Due: <?= date('M j, Y', strtotime($dloan['due_at'])) ?>
                                </div>
                            </div>
                            <a href="<?= e(SITE_URL) ?>staff/device_loans.php" class="btn-outline" style="padding: 6px 12px; font-size: 0.75rem; border-radius: 6px; text-decoration: none;">
                                Manage
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Fines & Details -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        
        <!-- Fines -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid var(--border); background: rgba(239, 68, 68, 0.05);">
                <h3 style="margin: 0; font-size: 1.1rem; color: var(--danger);"><i class="fas fa-money-bill-wave"></i> Unpaid Fines (<?= count($fines) ?>)</h3>
            </div>
            <?php if (empty($fines)): ?>
                <p style="color: var(--text-muted); padding: 30px; text-align: center;">No unpaid fines. Account in good standing.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column;">
                    <?php foreach ($fines as $fine): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border);">
                            <div>
                                <strong style="display: block; color: var(--text);">€<?= number_format($fine['amount'], 2) ?></strong>
                                <small style="color: var(--text-muted);"><?= e($fine['reason'] ?: 'Library Fine') ?></small>
                                <?php if (!empty($fine['title'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; font-style: italic;">Book: <?= e($fine['title']) ?></div>
                                <?php endif; ?>
                            </div>
                            <form method="post" style="margin: 0;">
                                <input type="hidden" name="action" value="pay_fine">
                                <input type="hidden" name="fine_id" value="<?= $fine['id'] ?>">
                                <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 0.75rem; background: var(--danger); border: none; border-radius: 6px; cursor: pointer;">
                                    <i class="fas fa-check"></i> Collect
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Contact & Address Info -->
        <div class="card">
            <h3><i class="fas fa-address-card" style="color: #0ea5e9;"></i> Contact Details</h3>
            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 16px;">
                <div style="padding: 12px; background: var(--bg-alt); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Home Address</div>
                    <div style="color: var(--text); font-weight: 600; margin-top: 4px;"><?= e($member['address'] ?: 'Not provided') ?></div>
                </div>
                <div style="padding: 12px; background: var(--bg-alt); border-radius: 8px; border: 1px solid var(--border);">
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Member Since</div>
                    <div style="color: var(--text); font-weight: 600; margin-top: 4px;"><?= date('F j, Y', strtotime($member['created_at'])) ?></div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_footer.php'; ?>
