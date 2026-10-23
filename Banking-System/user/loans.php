<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user['role'] !== 'user') {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$flash = '';
$error = '';

// Handle Loan Application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_loan') {
    $type = $_POST['loan_type'];
    $amount = (float)$_POST['amount'];
    $term = (int)$_POST['term'];

    if ($amount <= 0 || $term <= 0) {
        $error = 'Please enter a valid amount and term.';
    } else {
        // Get interest rate from settings
        $rate = (float)get_setting('loan_interest_rate', '5.5');

        $stmt = $pdo->prepare("INSERT INTO loans (user_id, loan_type, principal_amount, interest_rate, term_months, status) VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $type, $amount, $rate, $term]);
        $flash = 'Loan application submitted! A manager will review it shortly.';
    }
}

// Fetch User's Loans
$stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$loans = $stmt->fetchAll();

$page_title = 'My Loans';
require_once __DIR__ . '/../includes/user_header.php';
?>

<div class="dashboard-header" style="margin-bottom: 32px;">
    <h1 style="font-size: 2rem; color: var(--text); font-weight: 800;">My Loans</h1>
    <p style="color: var(--text-muted);">Apply for financing and track your applications.</p>
</div>

<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($flash): ?><div class="flash flash-success"><?= e($flash) ?></div><?php endif; ?>

<div class="grid-2" style="align-items: start;">
    
    <!-- Left: Apply for Loan -->
    <div class="card">
        <h3><i class="fas fa-file-signature" style="color: #10b981;"></i> Apply for a Loan</h3>
        <p style="color: var(--text-muted); margin-bottom: 24px; font-size: 0.9rem;">Submit an application for financing.</p>
        
        <form method="post">
            <input type="hidden" name="action" value="apply_loan">
            
            <div class="form-group">
                <label>Loan Type</label>
                <select name="loan_type" required>
                    <option value="personal">Personal Loan</option>
                    <option value="mortgage">Mortgage</option>
                    <option value="auto">Auto Loan</option>
                </select>
            </div>
            <div class="form-group">
                <label>Amount (€)</label>
                <input type="number" step="100" min="100" name="amount" required placeholder="10000">
            </div>
            <div class="form-group">
                <label>Term (Months)</label>
                <input type="number" min="6" name="term" value="12" required>
            </div>
            
            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="submit" class="btn-primary" style="width: auto; padding: 12px 32px; background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </form>
    </div>

    <!-- Right: Loan History -->
    <div class="card">
        <h3><i class="fas fa-list-alt" style="color: #10b981;"></i> Application History</h3>
        
        <?php if (empty($loans)): ?>
            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                <i class="fas fa-inbox" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 12px; display: block;"></i>
                <p>You haven't applied for any loans yet.</p>
            </div>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($loans as $l): ?>
                    <li style="padding: 16px 0; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="display: block; color: var(--text); text-transform: capitalize;"><?= e($l['loan_type']) ?> Loan</strong>
                            <small style="color: var(--text-muted);">€<?= number_format($l['principal_amount'], 2) ?> · <?= $l['term_months'] ?> months @ <?= $l['interest_rate'] ?>%</small>
                        </div>
                        <div>
                            <?php 
                                $status_color = 'var(--warning)';
                                if ($l['status'] === 'approved' || $l['status'] === 'active') $status_color = 'var(--success)';
                                if ($l['status'] === 'rejected') $status_color = 'var(--danger)';
                                if ($l['status'] === 'paid_off') $status_color = 'var(--primary)';
                            ?>
                            <span style="background: <?= $status_color ?>20; color: <?= $status_color ?>; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                <?= e($l['status']) ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
