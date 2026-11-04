<?php
// user/receipt.php — Modern Payment Receipt

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT role, first_name, last_name, email, membership_number, branch_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || ($user['role'] !== 'member' && $user['role'] !== 'manager')) {
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$receipt_id = (int)($_GET['id'] ?? 0);
$branch_name = $user['branch_id'] ? get_branch_name($user['branch_id']) : 'Main Library';

$stmt = $pdo->prepare("
    SELECT r.*, f.reason as fine_reason, f.id as fine_id
    FROM receipts r
    LEFT JOIN fines f ON f.id = r.fine_id
    WHERE r.id = ? AND r.user_id = ?
");
$stmt->execute([$receipt_id, $user_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    header('Location: ' . SITE_URL . 'user/fines.php');
    exit;
}

$page_title = 'Receipt #' . $receipt['id'];
require_once __DIR__ . '/../includes/user_header.php';
?>

<style>
    /* Perfect Clean Print */
    @media print {
        body, html { background: white !important; margin: 0; padding: 0; }
        .admin-sidebar, .admin-topbar, .no-print, footer { display: none !important; }
        .admin-main, .admin-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        #receipt-card { box-shadow: none !important; border: none !important; max-width: 100% !important; margin: 0 !important; }
        .print-bg { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div style="max-width: 800px; margin: 0 auto; padding-top: 20px;">

    <!-- Action Bar (Hidden on Print) -->
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <a href="<?= e(SITE_URL) ?>user/fines.php" style="display: inline-flex; align-items: center; gap: 8px; color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 600;">
            <i class="fas fa-arrow-left"></i> Back to My Fines
        </a>
        <button onclick="window.print()" class="btn-primary" style="padding: 10px 24px; border-radius: 8px; background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; font-weight: 700; cursor: pointer; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-print"></i> Print Receipt
        </button>
    </div>

    <!-- MODERN RECEIPT CARD -->
    <div id="receipt-card" class="card" style="padding: 0; overflow: hidden; border-radius: 20px; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.2); background: var(--bg-alt);">
        
        <!-- Header -->
        <div style="padding: 40px 48px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), rgba(5, 150, 105, 0.02)); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
            <div>
                <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #10b981, #059669); color: white; display: grid; place-items: center; margin-bottom: 20px; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4);">
                    <i class="fas fa-file-invoice" style="font-size: 1.5rem;"></i>
                </div>
                <h1 style="font-size: 2rem; font-weight: 800; color: var(--text); margin: 0 0 4px 0; letter-spacing: -0.02em;">Payment Receipt</h1>
                <div style="font-size: 1rem; color: var(--text-muted); font-weight: 600;">#KT26-<?= str_pad($receipt['id'], 5, '0', STR_PAD_LEFT) ?></div>
            </div>

            <div style="text-align: right;">
                <div class="print-bg" style="display: inline-flex; align-items: center; gap: 6px; background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 6px 16px; border-radius: 50px; font-weight: 800; font-size: 0.8rem; text-transform: uppercase; border: 1px solid rgba(16, 185, 129, 0.3);">
                    <i class="fas fa-check-circle"></i> Paid
                </div>
                <div style="margin-top: 12px; font-size: 0.85rem; color: var(--text-muted);">
                    <strong style="color: var(--text);">Date Paid:</strong><br>
                    <?= date('d M Y, H:i', strtotime($receipt['paid_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Info Grid -->
        <div style="padding: 40px 48px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; border-bottom: 1px solid var(--border);">
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin-bottom: 8px;">Issued To</div>
                <div style="font-weight: 800; color: var(--text); font-size: 1.1rem; margin-bottom: 4px;"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></div>
                <div style="color: var(--text-muted); font-size: 0.9rem;"><?= e($user['email']) ?></div>
                <div style="color: var(--text-muted); font-size: 0.9rem;">Member #<?= e($user['membership_number'] ?: 'N/A') ?></div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin-bottom: 8px;">Issued By</div>
                <div style="font-weight: 800; color: var(--text); font-size: 1.1rem; margin-bottom: 4px;">Kirjasto 2026</div>
                <div style="color: var(--text-muted); font-size: 0.9rem;"><?= e($branch_name) ?></div>
                <div style="color: var(--text-muted); font-size: 0.9rem;">Helsinki, Finland</div>
            </div>
        </div>

        <!-- Line Items -->
        <div style="padding: 40px 48px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 0 0 16px 0; border-bottom: 1px solid var(--border); font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 1px;">Description</th>
                        <th style="text-align: right; padding: 0 0 16px 0; border-bottom: 1px solid var(--border); font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 1px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 24px 0; border-bottom: 1px solid var(--border);">
                            <div style="font-weight: 700; color: var(--text); font-size: 1rem;"><?= e($receipt['description']) ?></div>
                            <?php if (!empty($receipt['fine_reason'])): ?>
                                <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px;">Reason: <?= e($receipt['fine_reason']) ?></div>
                            <?php endif; ?>
                            <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 4px; font-family: monospace;">Fine Ref: #<?= (int)$receipt['fine_id'] ?></div>
                        </td>
                        <td style="padding: 24px 0; border-bottom: 1px solid var(--border); text-align: right; font-weight: 800; color: var(--text); font-size: 1.1rem;">
                            €<?= number_format($receipt['amount'], 2) ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Totals -->
            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <div style="min-width: 300px;">
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; color: var(--text-muted); font-size: 0.9rem;">
                        <span>Subtotal</span>
                        <span>€<?= number_format($receipt['amount'], 2) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 8px 0; color: var(--text-muted); font-size: 0.9rem;">
                        <span>Tax (0%)</span>
                        <span>€0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 16px 0; border-top: 2px dashed var(--border); margin-top: 8px;">
                        <span style="font-weight: 800; font-size: 1.1rem; color: var(--text);">Total Paid</span>
                        <span style="font-weight: 800; font-size: 1.4rem; color: #10b981;">€<?= number_format($receipt['amount'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="padding: 24px 48px; background: var(--bg); border-top: 1px solid var(--border); text-align: center; color: var(--text-muted); font-size: 0.8rem;">
            <p style="margin: 0 0 4px 0;">This is a computer-generated receipt. No signature is required.</p>
            <p style="margin: 0;">If you have any questions, contact us at <strong>info@kirjasto.local</strong></p>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/user_footer.php'; ?>
