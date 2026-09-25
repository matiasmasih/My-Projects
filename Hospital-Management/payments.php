<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('payments', 'login.php');
$role   = current_role();

$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// Pre-select invoice if coming from invoices.php
$selected_invoice_id  = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
$selected_amount      = 0;

if ($selected_invoice_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT total_amount FROM invoices WHERE id = ?");
        $stmt->execute([$selected_invoice_id]);
        $selected_amount = (float)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {}
}

// ============================================
// HANDLE VOID PAYMENT (POST, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['void_payment']) && $access === 'full') {
    $void_id     = (int)($_POST['payment_id'] ?? 0);
    $void_reason = trim($_POST['void_reason'] ?? '');

    if ($void_id > 0) {
        try {
            // Get the invoice_id first so we can recalc
            $stmt = $pdo->prepare("SELECT invoice_id FROM payments WHERE id = ?");
            $stmt->execute([$void_id]);
            $invoice_id = (int)($stmt->fetchColumn() ?: 0);

            // Void the payment (soft — set status to failed, add reason to notes)
            $stmt = $pdo->prepare("
                UPDATE payments
                SET status = 'failed',
                    notes = CONCAT(COALESCE(notes, ''), '\n[VOIDED] ', ?)
                WHERE id = ?
            ");
            $stmt->execute([$void_reason ?: 'No reason provided', $void_id]);

            // Recalc invoice status
            if ($invoice_id > 0) {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(amount), 0) FROM payments
                    WHERE invoice_id = ? AND status = 'paid'
                ");
                $stmt->execute([$invoice_id]);
                $total_paid = (float)$stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT total_amount, status FROM invoices WHERE id = ?");
                $stmt->execute([$invoice_id]);
                $inv = $stmt->fetch();

                $invoice_total = (float)($inv['total_amount'] ?? 0);

                if ($total_paid >= $invoice_total && $invoice_total > 0) {
                    $new_status = 'paid';
                } elseif ($total_paid > 0) {
                    $new_status = 'partially_paid';
                } else {
                    $new_status = ($inv['status'] === 'paid' || $inv['status'] === 'partially_paid') ? 'issued' : $inv['status'];
                }

                $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $invoice_id]);
            }

            $_SESSION['flash_success'] = "Payment voided. Invoice status updated.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Void failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Invalid payment ID.";
    }
    header("Location: payments.php");
    exit;
}

// ============================================
// FETCH PAYMENTS
// ============================================
try {
    $stmt = $pdo->query("
        SELECT
            pay.id AS payment_id,
            pay.amount,
            pay.method,
            pay.status,
            pay.notes,
            pay.paid_at,
            pay.paid_by,
            pay.transaction_reference,
            pay.invoice_id,
            inv.invoice_number,
            inv.status AS invoice_status,
            CONCAT(pt.first_name, ' ', pt.last_name) AS patient_name,
            pt.medical_record_number AS patient_mrn
        FROM payments pay
        LEFT JOIN invoices inv ON pay.invoice_id = inv.id
        LEFT JOIN patients pt ON inv.patient_id = pt.id
        ORDER BY pay.paid_at DESC
    ");
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ============================================
// UNPAID INVOICES (for modal)
// ============================================
$unpaid_invoices = [];
if ($access === 'full') {
    try {
        $unpaid_invoices = $pdo->query("
            SELECT inv.id, inv.invoice_number, inv.total_amount,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name
            FROM invoices inv
            LEFT JOIN patients p ON inv.patient_id = p.id
            WHERE inv.status IN ('draft','issued','partially_paid')
            ORDER BY inv.id DESC
        ")->fetchAll();
    } catch (PDOException $e) {}
}

$page_title = 'Payments';
include 'payments_header.php';
?>

<!-- Alerts -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search payments...">
        <button class="btn btn-add" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px;">
            Total: <?= count($payments); ?> payments
        </span>
        <?php if ($access === 'full'): ?>
        <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
            <i class="bi bi-plus-circle"></i> Add Payment
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Payments Table -->
<div class="table-card">
    <h5><i class="bi bi-cash-coin"></i> All Payments (<?= count($payments); ?>)</h5>

    <?php if (count($payments) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Patient</th>
                    <th>Invoice #</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Paid By</th>
                    <th>Payment Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="paymentTable">
                <?php foreach ($payments as $index => $p): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td>
                        <?php if (!empty($p['patient_mrn'])): ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;"><?= htmlspecialchars($p['patient_mrn']) ?></span><br>
                        <?php endif; ?>
                        <strong><?= htmlspecialchars($p['patient_name'] ?? '-') ?></strong>
                    </td>
                    <td><?= htmlspecialchars($p['invoice_number'] ?? '-') ?></td>
                    <td><strong>€<?= number_format((float)($p['amount'] ?? 0), 2) ?></strong></td>
                    <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $p['method'] ?? '-'))) ?></td>
                    <td><?= htmlspecialchars($p['paid_by'] ?? '-') ?></td>
                    <td><?= $p['paid_at'] ? date('M d, Y H:i', strtotime($p['paid_at'])) : '-' ?></td>
                    <td>
                        <span class="status-badge status-<?= htmlspecialchars($p['status'] ?? 'pending') ?>">
                            <?= ucfirst($p['status'] ?? '-') ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($p['invoice_id'])): ?>
                            <a href="generate_receipt.php?id=<?= (int)$p['invoice_id'] ?>" class="btn-receipt" title="Receipt" target="_blank">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                        <?php endif; ?>

                        <?php if ($access === 'full' && ($p['status'] ?? '') !== 'failed'): ?>
                            <button type="button" class="btn-delete" title="Void Payment"
                                    data-bs-toggle="modal" data-bs-target="#voidPaymentModal"
                                    data-payment-id="<?= (int)$p['payment_id'] ?>">
                                <i class="bi bi-slash-circle"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-cash-stack" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No payments recorded yet.
        </p>
    <?php endif; ?>
</div>

<!-- ADD PAYMENT MODAL -->
<?php if ($access === 'full'): ?>
<div class="modal fade" id="addPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white;">
                <h5 class="modal-title">Add Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="save_payment.php">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Invoice <span style="color: #ef4444;">*</span></label>
                            <select name="invoice_id" id="invoice_id" class="form-select" required>
                                <option value="">Select Invoice</option>
                                <?php foreach ($unpaid_invoices as $inv): ?>
                                    <option value="<?= (int)$inv['id'] ?>"
                                            data-amount="<?= (float)$inv['total_amount'] ?>"
                                            <?= (int)$inv['id'] === $selected_invoice_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(($inv['invoice_number'] ?? '') . ' — ' . ($inv['patient_name'] ?? '')) ?>
                                        (€<?= number_format((float)$inv['total_amount'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Amount (€) <span style="color: #ef4444;">*</span></label>
                            <input type="number" step="0.01" name="amount" id="amount" class="form-control"
                                   value="<?= $selected_amount ? number_format($selected_amount, 2, '.', '') : '' ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Date <span style="color: #ef4444;">*</span></label>
                            <input type="datetime-local" name="paid_at" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Method <span style="color: #ef4444;">*</span></label>
                            <select name="method" class="form-select" required>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="insurance">Insurance</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status <span style="color: #ef4444;">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Paid By</label>
                            <input type="text" name="paid_by" class="form-control"
                                   value="<?= htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transaction Reference</label>
                            <input type="text" name="transaction_reference" class="form-control" placeholder="Optional">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="submit" class="btn btn-add">Save Payment</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- VOID PAYMENT MODAL -->
<div class="modal fade" id="voidPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: #ef4444; color: white;">
                <h5 class="modal-title">Void Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="payments.php">
                <div class="modal-body">
                    <p style="color: var(--text-muted); font-size: 0.9rem;">
                        <i class="bi bi-info-circle"></i> The payment will not be deleted. It will be marked as <strong>voided</strong> and the invoice status will be recalculated.
                    </p>
                    <input type="hidden" name="payment_id" id="void_payment_id" value="">
                    <input type="hidden" name="void_payment" value="1">
                    <div class="mb-3">
                        <label class="form-label">Reason for voiding <span style="color: #ef4444;">*</span></label>
                        <textarea name="void_reason" class="form-control" rows="3" required placeholder="e.g., Wrong amount, duplicate payment, refunded"></textarea>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="submit" class="btn btn-delete">Void Payment</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- CLIENT-SIDE SEARCH + AUTO-FILL + VOID MODAL WIRING -->
<script>
// Search
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const paymentTable = document.getElementById('paymentTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = paymentTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);

// Auto-fill amount
const invoiceSel  = document.getElementById('invoice_id');
const amountField = document.getElementById('amount');
if (invoiceSel && amountField) {
    invoiceSel.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const amt = opt.getAttribute('data-amount');
        if (amt) amountField.value = parseFloat(amt).toFixed(2);
    });
}

// Void modal — pass payment id
const voidModal = document.getElementById('voidPaymentModal');
if (voidModal) {
    voidModal.addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        const pid = btn.getAttribute('data-payment-id');
        document.getElementById('void_payment_id').value = pid;
    });
}
</script>

<!-- Bootstrap JS (for modal) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($selected_invoice_id > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var m = new bootstrap.Modal(document.getElementById('addPaymentModal'));
    m.show();
});
</script>
<?php endif; ?>

<?php include 'payments_footer.php'; ?>
