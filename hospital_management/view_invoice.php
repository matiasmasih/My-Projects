<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('invoices', 'login.php');
$role   = current_role();

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoice_id <= 0) die("Invalid invoice ID.");

try {
    $stmt = $pdo->prepare("
        SELECT i.*,
               p.first_name AS patient_first,
               p.last_name AS patient_last,
               p.medical_record_number AS patient_mrn,
               p.email AS patient_email,
               p.phone AS patient_phone,
               p.address AS patient_address,
               CONCAT(u.first_name, ' ', u.last_name) AS issued_by_name
        FROM invoices i
        LEFT JOIN patients p ON i.patient_id = p.id
        LEFT JOIN users u ON i.issued_by = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    if (!$invoice) die("Invoice not found.");

    // Patient can only see their own invoice
    if ($role === 'patient') {
        $my_patient_id = 0;
        try {
            $s2 = $pdo->prepare("SELECT id FROM patients WHERE email = ?");
            $s2->execute([$_SESSION['email'] ?? '']);
            $my_patient_id = (int)($s2->fetchColumn() ?: 0);
        } catch (Exception $e) {}
        if ((int)$invoice['patient_id'] !== $my_patient_id) {
            header("Location: invoices.php");
            exit;
        }
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Invoice items
try {
    $stmt = $pdo->prepare("
        SELECT description, item_type, quantity, unit_price, total_price
        FROM invoice_items
        WHERE invoice_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    $items = [];
}

// Payments
try {
    $stmt = $pdo->prepare("
        SELECT amount, method, paid_at, status, transaction_reference
        FROM payments
        WHERE invoice_id = ?
        ORDER BY paid_at ASC
    ");
    $stmt->execute([$invoice_id]);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    $payments = [];
}

// Totals
$subtotal = 0;
foreach ($items as $it) {
    $subtotal += (float)($it['total_price'] ?? 0);
}
if (count($items) === 0) {
    $subtotal = (float)($invoice['total_amount'] ?? 0);
}

$total_paid = 0;
foreach ($payments as $p) {
    if (($p['status'] ?? '') === 'paid') $total_paid += (float)$p['amount'];
}

$vat_rate    = 0;
$vat_amount  = 0;
$grand_total = $subtotal + $vat_amount;
$balance     = $grand_total - $total_paid;
$is_fully_paid = ($total_paid >= $grand_total && $grand_total > 0);

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$page_title = 'Invoice #' . ($invoice['invoice_number'] ?? '');
include 'invoices_header.php';
?>

<style>
    .vi-wrap { max-width: 900px; margin: 0 auto; }

    .vi-band {
        background: #14b8a6;
        color: white;
        padding: 22px 34px;
        border-radius: 12px 12px 0 0;
    }
    .vi-band table { width: 100%; }
    .vi-band td { vertical-align: middle; }
    .vi-band .hospital {
        font-size: 20px;
        font-weight: bold;
        letter-spacing: 0.4px;
    }
    .vi-band .hospital-sub {
        font-size: 12px;
        opacity: 0.95;
        margin-top: 2px;
        line-height: 1.45;
    }
    .vi-band .doc-label {
        text-align: right;
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 1.5px;
    }
    .vi-band .doc-sub {
        text-align: right;
        font-size: 12px;
        opacity: 0.95;
        margin-top: 2px;
    }

    .vi-body {
        background: var(--card-bg);
        padding: 22px 34px 28px 34px;
        border: 1px solid var(--border);
        border-top: none;
        border-radius: 0 0 12px 12px;
        box-shadow: var(--shadow);
    }

    .vi-title-row {
        margin-bottom: 14px;
        overflow: hidden;
    }
    .vi-title {
        font-size: 24px;
        font-weight: bold;
        color: var(--text);
        letter-spacing: 0.5px;
        line-height: 1.1;
    }
    .vi-amount-box {
        background: #f0fdfa;
        border: 2px solid #14b8a6;
        border-radius: 10px;
        padding: 10px 18px;
        text-align: right;
        display: inline-block;
    }
    .vi-amount-box .label {
        font-size: 11px;
        color: #0d9488;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: bold;
    }
    .vi-amount-box .value {
        font-size: 24px;
        font-weight: bold;
        color: var(--text);
        margin-top: 2px;
        line-height: 1;
    }

    table.vi-cards {
        width: 100%;
        border-collapse: separate;
        border-spacing: 10px 0;
        margin-bottom: 10px;
    }
    table.vi-cards > tr > td { vertical-align: top; width: 50%; }

    .vi-card {
        background: #f9fafb;
        border-radius: 10px;
        padding: 12px 16px;
        height: 100%;
    }
    .vi-card .card-title {
        font-size: 13px;
        color: #14b8a6;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: bold;
        margin-bottom: 8px;
        padding-bottom: 5px;
        border-bottom: 1px solid #e5e7eb;
    }
    .vi-card .row { margin-bottom: 5px; }
    .vi-card .row:last-child { margin-bottom: 0; }
    .vi-card .k {
        font-size: 10.5px;
        color: #6b7280;
        margin-bottom: 1px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .vi-card .v {
        font-size: 13px;
        color: var(--text);
        font-weight: bold;
        line-height: 1.35;
    }
    .vi-card .v-normal {
        font-weight: normal;
        color: var(--text);
        font-size: 12.5px;
    }

    .vi-section-label {
        font-size: 13px;
        color: #14b8a6;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: bold;
        margin: 14px 0 6px 0;
    }

    table.vi-services {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4px;
    }
    table.vi-services th {
        background: #f0fdfa;
        color: #0d9488;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        text-align: left;
        padding: 7px 10px;
        border-bottom: 2px solid #14b8a6;
        font-weight: bold;
    }
    table.vi-services th.num { text-align: right; }
    table.vi-services td {
        padding: 7px 10px;
        border-bottom: 1px solid #e5e7eb;
        font-size: 12.5px;
        vertical-align: top;
        color: var(--text);
    }
    table.vi-services td.num { text-align: right; }
    table.vi-services .service-name {
        font-weight: bold;
        color: var(--text);
    }
    table.vi-services .service-type {
        color: #9ca3af;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 1px;
    }

    table.vi-totals { width: 100%; margin-top: 2px; }
    table.vi-totals td { padding: 3px 0; color: var(--text); }
    table.vi-totals td.k {
        text-align: right;
        color: #6b7280;
        font-size: 12px;
        padding-right: 20px;
        width: 80%;
    }
    table.vi-totals td.v {
        text-align: right;
        font-size: 13px;
        width: 20%;
    }
    table.vi-totals tr.grand td {
        border-top: 2px solid #14b8a6;
        padding-top: 8px;
        font-size: 16px;
        font-weight: bold;
    }
    table.vi-totals tr.grand td.k {
        font-size: 13px;
        color: #0d9488;
        letter-spacing: 0.6px;
        text-transform: uppercase;
    }

    table.vi-payments {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
    }
    table.vi-payments th {
        background: #f9fafb;
        color: #6b7280;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        text-align: left;
        padding: 6px 10px;
        border-bottom: 1px solid #e5e7eb;
    }
    table.vi-payments th.num { text-align: right; }
    table.vi-payments td {
        padding: 6px 10px;
        border-bottom: 1px solid #e5e7eb;
        font-size: 12px;
        color: var(--text);
    }
    table.vi-payments td.num { text-align: right; font-weight: bold; }
    table.vi-payments .status-paid { color: #059669; font-weight: bold; }
    table.vi-payments .status-pending { color: #d97706; font-weight: bold; }

    .vi-balance {
        margin-top: 12px;
        background: #f0fdfa;
        padding: 12px 18px;
        border-radius: 10px;
    }
    .vi-balance table { width: 100%; }
    .vi-balance td { vertical-align: middle; }
    .vi-balance .k {
        font-size: 13px;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #0d9488;
        font-weight: bold;
    }
    .vi-balance .v {
        text-align: right;
        font-size: 20px;
        font-weight: bold;
        color: var(--text);
    }

    .vi-pay-header {
        font-size: 13px;
        color: #14b8a6;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: bold;
        margin: 16px 0 8px 0;
    }
    table.vi-pay-cols {
        width: 100%;
        border-collapse: separate;
        border-spacing: 10px 0;
    }
    table.vi-pay-cols > tr > td { vertical-align: top; width: 50%; }
    .vi-pay-card {
        background: #f9fafb;
        border-radius: 10px;
        padding: 12px 16px;
    }
    .vi-pay-card .row { margin-bottom: 6px; }
    .vi-pay-card .row:last-child { margin-bottom: 0; }
    .vi-pay-card .k {
        font-size: 10.5px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 1px;
    }
    .vi-pay-card .v {
        font-size: 13px;
        color: var(--text);
        font-weight: bold;
    }

    .vi-ref-strip {
        margin-top: 14px;
        background: #f0fdfa;
        border-left: 4px solid #14b8a6;
        padding: 12px 18px;
        border-radius: 8px;
    }
    .vi-ref-strip table { width: 100%; }
    .vi-ref-strip td { vertical-align: top; padding-right: 24px; }
    .vi-ref-strip .k {
        color: #0d9488;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        font-weight: bold;
        margin-bottom: 3px;
    }
    .vi-ref-strip .v {
        color: var(--text);
        font-weight: bold;
        font-size: 14px;
    }

    .vi-action-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        gap: 10px;
        flex-wrap: wrap;
    }
    .vi-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .vi-btn-back { background: #6b7280; color: white; }
    .vi-btn-back:hover { background: #4b5563; color: white; }
    .vi-btn-pdf { background: #14b8a6; color: white; }
    .vi-btn-pdf:hover { background: #0d9488; color: white; }
    .vi-btn-pay { background: #22c55e; color: white; }
    .vi-btn-pay:hover { background: #16a34a; color: white; }

    @media print {
        .navbar, .sidebar, .vi-action-bar { display: none !important; }
        .main-content { padding: 0 !important; }
        body { background: white !important; }
        .vi-body { box-shadow: none !important; border: none !important; }
    }
</style>

<div class="vi-wrap">

    <!-- Action bar -->
    <div class="vi-action-bar">
        <a href="invoices.php" class="vi-btn vi-btn-back">
            <i class="bi bi-arrow-left"></i> Back to Invoices
        </a>
        <div style="display:flex; gap:8px;">
            <a href="generate_receipt.php?id=<?= (int)$invoice['id'] ?>" target="_blank" class="vi-btn vi-btn-pdf">
                <i class="bi bi-file-pdf"></i> Download PDF
            </a>
            <?php if ($access === 'full' && ($invoice['status'] ?? '') !== 'paid'): ?>
                <a href="payments.php?invoice_id=<?= (int)$invoice['id'] ?>" class="vi-btn vi-btn-pay">
                    <i class="bi bi-cash-stack"></i> Record Payment
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Header band -->
    <div class="vi-band">
        <table>
            <tr>
                <td>
                    <div class="hospital"><?= e($hospital['name']) ?></div>
                    <div class="hospital-sub"><?= e($hospital['address']) ?></div>
                    <div class="hospital-sub"><?= e($hospital['phone']) ?></div>
                    <div class="hospital-sub"><?= e($hospital['email']) ?></div>
                </td>
                <td>
                    <div class="doc-label">INVOICE</div>
                    <div class="doc-sub"><?= $invoice['issued_at'] ? date('d.m.Y', strtotime($invoice['issued_at'])) : '-' ?></div>
                    <div class="doc-sub"><?= e($invoice['invoice_number'] ?? '-') ?></div>
                </td>
            </tr>
        </table>
    </div>

    <div class="vi-body">

        <!-- Title + Amount -->
        <div class="vi-title-row">
            <div style="float:left; width:50%;">
                <div class="vi-title"><?= $is_fully_paid ? 'Payment Receipt' : 'Invoice' ?></div>
            </div>
            <div style="float:right; width:45%; text-align:right;">
                <div class="vi-amount-box" style="display:inline-block;">
                    <div class="label"><?= $is_fully_paid ? 'Amount Paid' : 'Amount Due' ?></div>
                    <div class="value">&euro;<?= number_format($is_fully_paid ? $grand_total : $balance, 2) ?></div>
                </div>
            </div>
            <div style="clear:both;"></div>
        </div>

        <!-- Patient + Invoice cards -->
        <table class="vi-cards">
            <tr>
                <td>
                    <div class="vi-card">
                        <div class="card-title">Patient Details</div>
                        <div class="row">
                            <div class="k">Name</div>
                            <div class="v"><?= e(($invoice['patient_first'] ?? '') . ' ' . ($invoice['patient_last'] ?? '')) ?></div>
                        </div>
                        <?php if (!empty($invoice['patient_mrn'])): ?>
                        <div class="row">
                            <div class="k">MRN</div>
                            <div class="v"><?= e($invoice['patient_mrn']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($invoice['patient_address'])): ?>
                        <div class="row">
                            <div class="k">Address</div>
                            <div class="v v-normal"><?= e($invoice['patient_address']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($invoice['patient_email'])): ?>
                        <div class="row">
                            <div class="k">Email</div>
                            <div class="v v-normal"><?= e($invoice['patient_email']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <div class="vi-card">
                        <div class="card-title">Invoice Details</div>
                        <div class="row">
                            <div class="k">Invoice Number</div>
                            <div class="v"><?= e($invoice['invoice_number'] ?? '-') ?></div>
                        </div>
                        <div class="row">
                            <div class="k">Issued At</div>
                            <div class="v v-normal"><?= $invoice['issued_at'] ? date('d.m.Y', strtotime($invoice['issued_at'])) : '-' ?></div>
                        </div>
                        <div class="row">
                            <div class="k">Due Date</div>
                            <div class="v"><?= $invoice['due_date'] ? date('d.m.Y', strtotime($invoice['due_date'])) : '-' ?></div>
                        </div>
                        <div class="row">
                            <div class="k">Status</div>
                            <div class="v"><?= ucfirst(str_replace('_', ' ', $invoice['status'] ?? '-')) ?></div>
                        </div>
                        <div class="row">
                            <div class="k">Issued By</div>
                            <div class="v v-normal"><?= e($invoice['issued_by_name'] ?? '-') ?></div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Services -->
        <div class="vi-section-label">Services</div>
        <table class="vi-services">
            <thead>
                <tr>
                    <th style="width:55%;">Description</th>
                    <th class="num" style="width:10%;">Qty</th>
                    <th class="num" style="width:15%;">Unit Price</th>
                    <th class="num" style="width:20%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <div class="service-name"><?= e($it['description']) ?></div>
                            <?php if (!empty($it['item_type'])): ?>
                                <div class="service-type"><?= e($it['item_type']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= (int)($it['quantity'] ?? 1) ?></td>
                        <td class="num">&euro;<?= number_format((float)($it['unit_price'] ?? 0), 2) ?></td>
                        <td class="num" style="font-weight:bold;">&euro;<?= number_format((float)($it['total_price'] ?? 0), 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td>
                            <div class="service-name">Medical Services</div>
                            <div class="service-type"><?= e($invoice['notes'] ?: 'Consultation & treatment') ?></div>
                        </td>
                        <td class="num">1</td>
                        <td class="num">&euro;<?= number_format($subtotal, 2) ?></td>
                        <td class="num" style="font-weight:bold;">&euro;<?= number_format($subtotal, 2) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <table class="vi-totals">
            <tr>
                <td class="k">Subtotal</td>
                <td class="v">&euro;<?= number_format($subtotal, 2) ?></td>
            </tr>
            <tr>
                <td class="k">VAT (Medical services - 0%)</td>
                <td class="v">&euro;<?= number_format($vat_amount, 2) ?></td>
            </tr>
            <tr class="grand">
                <td class="k">Total</td>
                <td class="v">&euro;<?= number_format($grand_total, 2) ?></td>
            </tr>
        </table>

        <!-- Payments -->
        <?php if (count($payments) > 0): ?>
        <div class="vi-section-label" style="margin-top:16px;">Payments</div>
        <table class="vi-payments">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                    <?php $st = strtolower($p['status'] ?? 'pending'); ?>
                    <tr>
                        <td><?= !empty($p['paid_at']) ? date('d.m.Y', strtotime($p['paid_at'])) : '-' ?></td>
                        <td><?= e(ucfirst(str_replace('_', ' ', $p['method'] ?? '-'))) ?></td>
                        <td><?= e($p['transaction_reference'] ?? '-') ?></td>
                        <td class="<?= $st === 'paid' ? 'status-paid' : 'status-pending' ?>"><?= e(ucfirst($st)) ?></td>
                        <td class="num" style="color:#059669;">&euro;<?= number_format((float)$p['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Balance -->
        <div class="vi-balance">
            <table>
                <tr>
                    <td class="k"><?= $is_fully_paid ? 'Total Paid' : 'Balance Due' ?></td>
                    <td class="v">&euro;<?= number_format($is_fully_paid ? $grand_total : $balance, 2) ?></td>
                </tr>
            </table>
        </div>

        <!-- Payment Details -->
        <div class="vi-pay-header">Payment Details</div>
        <table class="vi-pay-cols">
            <tr>
                <td>
                    <div class="vi-pay-card">
                        <div class="row">
                            <div class="k">Recipient</div>
                            <div class="v"><?= e($hospital['name']) ?></div>
                        </div>
                        <div class="row">
                            <div class="k">IBAN</div>
                            <div class="v"><?= e($hospital['iban']) ?></div>
                        </div>
                        <div class="row">
                            <div class="k">BIC / SWIFT</div>
                            <div class="v"><?= e($hospital['bic']) ?></div>
                        </div>
                        <div class="row">
                            <div class="k">Bank</div>
                            <div class="v"><?= e($hospital['bank_name']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="vi-pay-card">
                        <div class="row">
                            <div class="k">Payer</div>
                            <div class="v"><?= e(($invoice['patient_first'] ?? '') . ' ' . ($invoice['patient_last'] ?? '')) ?></div>
                        </div>
                        <div class="row">
                            <div class="k">Reference</div>
                            <div class="v"><?= e($invoice['invoice_number'] ?? '-') ?></div>
                        </div>
                        <div class="row">
                            <div class="k">Due Date</div>
                            <div class="v"><?= $invoice['due_date'] ? date('d.m.Y', strtotime($invoice['due_date'])) : '-' ?></div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Reference strip -->
        <div class="vi-ref-strip">
            <table>
                <tr>
                    <td style="width:40%;">
                        <div class="k">Reference</div>
                        <div class="v"><?= e($invoice['invoice_number'] ?? '-') ?></div>
                    </td>
                    <td style="width:30%;">
                        <div class="k">Due Date</div>
                        <div class="v"><?= $invoice['due_date'] ? date('d.m.Y', strtotime($invoice['due_date'])) : '-' ?></div>
                    </td>
                    <td style="width:30%;">
                        <div class="k">Amount</div>
                        <div class="v">&euro;<?= number_format($is_fully_paid ? $grand_total : $balance, 2) ?></div>
                    </td>
                </tr>
            </table>
        </div>

    </div><!-- /vi-body -->

</div><!-- /vi-wrap -->

<?php include 'invoices_footer.php'; ?>
