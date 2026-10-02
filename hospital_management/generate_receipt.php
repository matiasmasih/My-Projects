<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('payments', 'login.php');

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoice_id <= 0) die("Invalid invoice ID.");

// Fetch invoice + patient + issuer
try {
    $stmt = $pdo->prepare("
        SELECT inv.*,
               p.first_name AS patient_first, p.last_name AS patient_last,
               p.medical_record_number AS patient_mrn,
               p.email AS patient_email, p.phone AS patient_phone,
               p.address AS patient_address,
               u.first_name AS staff_first, u.last_name AS staff_last
        FROM invoices inv
        LEFT JOIN patients p ON inv.patient_id = p.id
        LEFT JOIN users u ON inv.issued_by = u.id
        WHERE inv.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    if (!$invoice) die("Invoice not found.");
    $invoice['staff_name'] = trim(($invoice['staff_first'] ?? '') . ' ' . ($invoice['staff_last'] ?? ''));
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch invoice items (real line items)
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

// Fetch payments
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

// Totals from invoice_items
$subtotal = 0;
foreach ($items as $it) {
    $subtotal += (float)($it['total_price'] ?? 0);
}

// Fallback: if no items, use invoice total
if (count($items) === 0) {
    $subtotal = (float)($invoice['total_amount'] ?? 0);
}

// Payments
$total_paid = 0;
foreach ($payments as $p) {
    if (($p['status'] ?? '') === 'paid') $total_paid += (float)$p['amount'];
}

// VAT (medical services = 0% in Finland)
$vat_rate   = 0;
$vat_amount = 0;
$grand_total = $subtotal + $vat_amount;

$balance       = $grand_total - $total_paid;
$is_fully_paid = ($total_paid >= $grand_total && $grand_total > 0);

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Barcode
require_once __DIR__ . '/vendor/autoload.php';

$reference = preg_replace('/[^0-9]/', '', $invoice['invoice_number'] ?? '');
if ($reference === '') $reference = (string)($invoice['id'] ?? 0);
$reference = str_pad($reference, 15, '0', STR_PAD_LEFT);

$barcode_img = '';
try {
    $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
    $png = $generator->getBarcode($reference, $generator::TYPE_CODE_128, 1, 50);
    $barcode_img = '<img src="data:image/png;base64,' . base64_encode($png) . '" style="height:55px; width:50%;">';
} catch (Exception $e) {}

$html = '

<style>
    @page { margin: 0; }
    * { box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, sans-serif;
        color: #1f2937;
        font-size: 12px;
        line-height: 1.5;
        margin: 0;
        padding: 0;
    }

    .page {
        padding: 0;
        position: relative;
        min-height: 280mm;
    }

    /* HEADER BAND */
    .header-band {
        background: #14b8a6;
        color: white;
        padding: 18px 45px;
    }
    .header-band table { width: 100%; }
    .header-band td { vertical-align: middle; }
    .header-band .hospital {
        font-size: 20px;
        font-weight: bold;
        letter-spacing: 0.4px;
    }
    .header-band .hospital-sub {
        font-size: 12px;
        opacity: 0.95;
        margin-top: 2px;
        line-height: 1.45;
    }
    .header-band .doc-label {
        text-align: right;
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 1.5px;
    }
    .header-band .doc-sub {
        text-align: right;
        font-size: 12px;
        opacity: 0.95;
        margin-top: 2px;
    }

    .body-area {
        padding: 20px 45px 20px 45px;
    }

    /* TITLE + AMOUNT */
    .title-row {
        margin-bottom: 12px;
        overflow: hidden;
    }
    .title-main {
        font-size: 26px;
        font-weight: bold;
        color: #0f172a;
        letter-spacing: 0.5px;
        line-height: 1.1;
    }
    .amount-box {
        background: #f0fdfa;
        border: 2px solid #14b8a6;
        border-radius: 10px;
        padding: 10px 18px;
        text-align: right;
        display: inline-block;
    }
    .amount-box .label {
        font-size: 11px;
        color: #0d9488;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: bold;
    }
    .amount-box .value {
        font-size: 24px;
        font-weight: bold;
        color: #0f172a;
        margin-top: 2px;
        line-height: 1;
    }

    /* CARDS */
    table.cards {
        width: 100%;
        border-collapse: separate;
        border-spacing: 10px 0;
        margin-bottom: 10px;
    }
    table.cards > tr > td {
        vertical-align: top;
        width: 50%;
    }
    .card {
        background: #f9fafb;
        border-radius: 10px;
        padding: 12px 16px;
    }
    .card .card-title {
        font-size: 13px;
        color: #14b8a6;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: bold;
        margin-bottom: 8px;
        padding-bottom: 5px;
        border-bottom: 1px solid #e5e7eb;
    }
    .card .row { margin-bottom: 5px; }
    .card .row:last-child { margin-bottom: 0; }
    .card .k {
        font-size: 10.5px;
        color: #6b7280;
        margin-bottom: 1px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .card .v {
        font-size: 13px;
        color: #0f172a;
        font-weight: bold;
        line-height: 1.35;
    }
    .card .v-normal {
        font-weight: normal;
        color: #1f2937;
        font-size: 12.5px;
    }

    /* SECTION LABEL */
    .section-label {
        font-size: 13px;
        color: #14b8a6;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: bold;
        margin: 12px 0 6px 0;
    }

    /* SERVICES TABLE */
    table.services {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 4px;
    }
    table.services th {
        background: #f0fdfa;
        color: #0d9488;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        text-align: left;
        padding: 6px 10px;
        border-bottom: 2px solid #14b8a6;
        font-weight: bold;
    }
    table.services th.num { text-align: right; }
    table.services td {
        padding: 6px 10px;
        border-bottom: 1px solid #e5e7eb;
        font-size: 12.5px;
        vertical-align: top;
    }
    table.services td.num { text-align: right; }
    table.services .service-name {
        font-weight: bold;
        color: #0f172a;
    }
    table.services .service-type {
        color: #9ca3af;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 1px;
    }

    /* TOTALS TABLE */
    table.totals {
        width: 100%;
        margin-top: 2px;
    }
    table.totals td { padding: 3px 0; }
    table.totals td.k {
        text-align: right;
        color: #6b7280;
        font-size: 12px;
        padding-right: 20px;
        width: 80%;
    }
    table.totals td.v {
        text-align: right;
        font-size: 13px;
        width: 20%;
    }
    table.totals tr.grand td {
        border-top: 2px solid #14b8a6;
        padding-top: 8px;
        font-size: 16px;
        font-weight: bold;
        color: #0f172a;
    }
    table.totals tr.grand td.k {
        font-size: 13px;
        color: #0d9488;
        letter-spacing: 0.6px;
        text-transform: uppercase;
    }

    /* PAYMENTS TABLE */
    table.payments {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
    }
    table.payments th {
        background: #f9fafb;
        color: #6b7280;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        text-align: left;
        padding: 6px 10px;
        border-bottom: 1px solid #e5e7eb;
    }
    table.payments th.num { text-align: right; }
    table.payments td {
        padding: 6px 10px;
        border-bottom: 1px solid #e5e7eb;
        font-size: 12px;
    }
    table.payments td.num { text-align: right; font-weight: bold; }
    table.payments .status-paid { color: #059669; font-weight: bold; }
    table.payments .status-pending { color: #d97706; font-weight: bold; }

    /* BALANCE STRIP */
    .balance-strip {
        margin-top: 10px;
        background: #f0fdfa;
        padding: 12px 18px;
        border-radius: 10px;
    }
    .balance-strip table { width: 100%; }
    .balance-strip td { vertical-align: middle; }
    .balance-strip .k {
        font-size: 13px;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #0d9488;
        font-weight: bold;
    }
    .balance-strip .v {
        text-align: right;
        font-size: 20px;
        font-weight: bold;
        color: #0f172a;
    }

    /* PAYMENT DETAILS */
    .pay-header {
        font-size: 13px;
        color: #14b8a6;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: bold;
        margin: 14px 0 8px 0;
    }
    table.pay-cols {
        width: 100%;
        border-collapse: separate;
        border-spacing: 10px 0;
    }
    table.pay-cols > tr > td { vertical-align: top; width: 50%; }
    .pay-card {
        background: #f9fafb;
        border-radius: 10px;
        padding: 12px 16px;
    }
    .pay-card .row { margin-bottom: 6px; }
    .pay-card .row:last-child { margin-bottom: 0; }
    .pay-card .k {
        font-size: 10.5px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 1px;
    }
    .pay-card .v {
        font-size: 13px;
        color: #0f172a;
        font-weight: bold;
    }

    /* REFERENCE STRIP */
    .ref-strip {
        margin-top: 12px;
        background: #f0fdfa;
        border-left: 4px solid #14b8a6;
        padding: 12px 18px;
        border-radius: 8px;
    }
    .ref-strip table { width: 100%; }
    .ref-strip td { vertical-align: top; padding-right: 24px; }
    .ref-strip .k {
        color: #0d9488;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        font-weight: bold;
        margin-bottom: 3px;
    }
    .ref-strip .v {
        color: #0f172a;
        font-weight: bold;
        font-size: 14px;
    }

    /* BARCODE */
    .barcode-area {
        margin-top: 14px;
        text-align: left;
    }
    .barcode-area img { height: 50px; }
</style>

<div class="page">

    <!-- HEADER BAND -->
    <div class="header-band">
        <table>
            <tr>
                <td>
                    <div class="hospital">' . e($hospital['name']) . '</div>
                    <div class="hospital-sub">' . e($hospital['address']) . '</div>
                    <div class="hospital-sub">' . e($hospital['phone']) . '</div>
                    <div class="hospital-sub">' . e($hospital['email']) . '</div>
                </td>
                <td>
                    <div class="doc-label">RECEIPT</div>
                    <div class="doc-sub">' . date('d.m.Y') . '</div>
                    <div class="doc-sub">' . e($invoice['invoice_number'] ?? '-') . '</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="body-area">

        <!-- TITLE + AMOUNT -->
        <div class="title-row">
            <div style="float:left; width:50%;">
                <div class="title-main">' . ($is_fully_paid ? 'Payment Receipt' : 'Invoice Receipt') . '</div>
            </div>
            <div style="float:right; width:45%; text-align:right;">
                <div class="amount-box" style="display:inline-block;">
                    <div class="label">' . ($is_fully_paid ? 'Amount Paid' : 'Amount Due') . '</div>
                    <div class="value">' . number_format($is_fully_paid ? $grand_total : $balance, 2) . ' &euro;</div>
                </div>
            </div>
            <div style="clear:both;"></div>
        </div>

        <!-- PATIENT + INVOICE CARDS -->
        <table class="cards">
            <tr>
                <td>
                    <div class="card">
                        <div class="card-title">Patient Details</div>
                        <div class="row">
                            <div class="k">Name</div>
                            <div class="v">' . e(($invoice['patient_first'] ?? '') . ' ' . ($invoice['patient_last'] ?? '')) . '</div>
                        </div>
                        ' . (!empty($invoice['patient_mrn']) ? '
                        <div class="row">
                            <div class="k">MRN</div>
                            <div class="v">' . e($invoice['patient_mrn']) . '</div>
                        </div>
                        ' : '') . '
                        ' . (!empty($invoice['patient_address']) ? '
                        <div class="row">
                            <div class="k">Address</div>
                            <div class="v v-normal">' . e($invoice['patient_address']) . '</div>
                        </div>
                        ' : '') . '
                        ' . (!empty($invoice['patient_email']) ? '
                        <div class="row">
                            <div class="k">Email</div>
                            <div class="v v-normal">' . e($invoice['patient_email']) . '</div>
                        </div>
                        ' : '') . '
                    </div>
                </td>
                <td>
                    <div class="card">
                        <div class="card-title">Invoice Details</div>
                        <div class="row">
                            <div class="k">Invoice Number</div>
                            <div class="v">' . e($invoice['invoice_number'] ?? '-') . '</div>
                        </div>
                        <div class="row">
                            <div class="k">Issued At</div>
                            <div class="v v-normal">' . (!empty($invoice['issued_at']) ? date('d.m.Y', strtotime($invoice['issued_at'])) : '-') . '</div>
                        </div>
                        <div class="row">
                            <div class="k">Due Date</div>
                            <div class="v">' . (!empty($invoice['due_date']) ? date('d.m.Y', strtotime($invoice['due_date'])) : '-') . '</div>
                        </div>
                        <div class="row">
                            <div class="k">Issued By</div>
                            <div class="v v-normal">' . e($invoice['staff_name'] ?: '-') . '</div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- SERVICES -->
        <div class="section-label">Services</div>
        <table class="services">
            <thead>
                <tr>
                    <th style="width:55%;">Description</th>
                    <th class="num" style="width:10%;">Qty</th>
                    <th class="num" style="width:15%;">Unit Price</th>
                    <th class="num" style="width:20%;">Total</th>
                </tr>
            </thead>
            <tbody>
';

if (count($items) > 0) {
    foreach ($items as $it) {
        $html .= '
                <tr>
                    <td>
                        <div class="service-name">' . e($it['description']) . '</div>
                        ' . (!empty($it['item_type']) ? '<div class="service-type">' . e($it['item_type']) . '</div>' : '') . '
                    </td>
                    <td class="num">' . (int)($it['quantity'] ?? 1) . '</td>
                    <td class="num">' . number_format((float)($it['unit_price'] ?? 0), 2) . ' &euro;</td>
                    <td class="num" style="font-weight:bold;">' . number_format((float)($it['total_price'] ?? 0), 2) . ' &euro;</td>
                </tr>
        ';
    }
} else {
    $html .= '
                <tr>
                    <td>
                        <div class="service-name">Medical Services</div>
                        <div class="service-type">' . e($invoice['notes'] ?: 'Consultation & treatment') . '</div>
                    </td>
                    <td class="num">1</td>
                    <td class="num">' . number_format($subtotal, 2) . ' &euro;</td>
                    <td class="num" style="font-weight:bold;">' . number_format($subtotal, 2) . ' &euro;</td>
                </tr>
    ';
}

$html .= '
            </tbody>
        </table>

        <!-- TOTALS -->
        <table class="totals">
            <tr>
                <td class="k">Subtotal</td>
                <td class="v">' . number_format($subtotal, 2) . ' &euro;</td>
            </tr>
            <tr>
                <td class="k">VAT (Medical services - 0%)</td>
                <td class="v">' . number_format($vat_amount, 2) . ' &euro;</td>
            </tr>
            <tr class="grand">
                <td class="k">Total</td>
                <td class="v">' . number_format($grand_total, 2) . ' &euro;</td>
            </tr>
        </table>
';

// PAYMENTS
if (count($payments) > 0) {
    $html .= '
        <div class="section-label" style="margin-top:26px;">Payments</div>
        <table class="payments">
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
    ';
    foreach ($payments as $p) {
        $status = strtolower($p['status'] ?? 'pending');
        $status_class = ($status === 'paid') ? 'status-paid' : 'status-pending';
        $html .= '
                <tr>
                    <td>' . (!empty($p['paid_at']) ? date('d.m.Y', strtotime($p['paid_at'])) : '-') . '</td>
                    <td>' . e(ucfirst(str_replace('_', ' ', $p['method'] ?? '-'))) . '</td>
                    <td>' . e($p['transaction_reference'] ?? '-') . '</td>
                    <td class="' . $status_class . '">' . e(ucfirst($status)) . '</td>
                    <td class="num" style="color:#059669;">' . number_format((float)$p['amount'], 2) . ' &euro;</td>
                </tr>
        ';
    }
    $html .= '
            </tbody>
        </table>
    ';
}

$html .= '
        <!-- BALANCE STRIP -->
        <div class="balance-strip">
            <table>
                <tr>
                    <td class="k">' . ($is_fully_paid ? 'Total Paid' : 'Balance Due') . '</td>
                    <td class="v">' . number_format($is_fully_paid ? $grand_total : $balance, 2) . ' &euro;</td>
                </tr>
            </table>
        </div>

        <!-- PAYMENT DETAILS -->
        <div class="pay-header">Payment Details</div>
        <table class="pay-cols">
            <tr>
                <td>
                    <div class="pay-card">
                        <div class="row">
                            <div class="k">Recipient</div>
                            <div class="v">' . e($hospital['name']) . '</div>
                        </div>
                        <div class="row">
                            <div class="k">IBAN</div>
                            <div class="v">' . e($hospital['iban']) . '</div>
                        </div>
                        <div class="row">
                            <div class="k">BIC / SWIFT</div>
                            <div class="v">' . e($hospital['bic']) . '</div>
                        </div>
                        <div class="row">
                            <div class="k">Bank</div>
                            <div class="v">' . e($hospital['bank_name']) . '</div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="pay-card">
                        <div class="row">
                            <div class="k">Payer</div>
                            <div class="v">' . e(($invoice['patient_first'] ?? '') . ' ' . ($invoice['patient_last'] ?? '')) . '</div>
                        </div>
                        <div class="row">
                            <div class="k">Reference</div>
                            <div class="v">' . e($invoice['invoice_number'] ?? '-') . '</div>
                        </div>
                        <div class="row">
                            <div class="k">Due Date</div>
                            <div class="v">' . (!empty($invoice['due_date']) ? date('d.m.Y', strtotime($invoice['due_date'])) : '-') . '</div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- REFERENCE STRIP -->
        <div class="ref-strip">
            <table>
                <tr>
                    <td style="width:40%;">
                        <div class="k">Reference</div>
                        <div class="v">' . e($invoice['invoice_number'] ?? '-') . '</div>
                    </td>
                    <td style="width:30%;">
                        <div class="k">Due Date</div>
                        <div class="v">' . (!empty($invoice['due_date']) ? date('d.m.Y', strtotime($invoice['due_date'])) : '-') . '</div>
                    </td>
                    <td style="width:30%;">
                        <div class="k">Amount</div>
                        <div class="v">' . number_format($is_fully_paid ? $grand_total : $balance, 2) . ' &euro;</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- BARCODE -->
        <div class="barcode-area">
            ' . $barcode_img . '
        </div>

    </div><!-- /body-area -->

</div>
';

$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4',
    'margin_top' => 0,
    'margin_bottom' => 0,
    'margin_left' => 0,
    'margin_right' => 0,
]);
$mpdf->WriteHTML($html);
$mpdf->Output('Receipt_' . ($invoice['invoice_number'] ?? 'invoice') . '.pdf', 'I');
exit;
