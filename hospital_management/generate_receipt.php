<?php
session_start();
include 'config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Only allow logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get invoice ID
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
if ($invoice_id <= 0) die("Invalid invoice ID.");

// Fetch invoice info along with patient and staff who issued it
try {
    $stmt = $pdo->prepare("
        SELECT inv.*,
               p.first_name AS patient_first, p.last_name AS patient_last,
               u.first_name AS staff_first, u.last_name AS staff_last
        FROM invoices inv
        JOIN patients p ON inv.patient_id = p.id
        JOIN users u ON inv.issued_by = u.id
        WHERE inv.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();

    if (!$invoice) die("Invoice not found.");

    // Combine staff first and last name
    $invoice['staff_name'] = $invoice['staff_first'].' '.$invoice['staff_last'];

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// ✅ Fetch payments related to this invoice (JOIN users to get name)
try {
    $stmt = $pdo->prepare("
        SELECT 
            pay.amount,
            pay.method,
            CONCAT(u.first_name, ' ', u.last_name) AS paid_by,
            pay.paid_at,
            pay.status,
            pay.notes
        FROM payments pay
        LEFT JOIN users u ON pay.paid_by = u.id
        WHERE pay.invoice_id = ?
        ORDER BY pay.paid_at ASC
    ");
    $stmt->execute([$invoice_id]);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Include mPDF
require_once __DIR__ . '/vendor/autoload.php';
$mpdf = new \Mpdf\Mpdf();

// Build HTML for PDF
$html = '
<h1 style="text-align:center;">Receipt</h1>
<hr>
<table width="100%" style="margin-bottom:20px;">
    <tr>
        <td><strong>Invoice #:</strong> '.$invoice['invoice_number'].'</td>
        <td><strong>Issued by:</strong> '.$invoice['staff_name'].'</td>
    </tr>
    <tr>
        <td><strong>Patient:</strong> '.$invoice['patient_first'].' '.$invoice['patient_last'].'</td>
        <td><strong>Issued at:</strong> '.$invoice['issued_at'].'</td>
    </tr>
    <tr>
        <td><strong>Due date:</strong> '.($invoice['due_date'] ?? 'N/A').'</td>
        <td><strong>Status:</strong> '.ucfirst($invoice['status']).'</td>
    </tr>
</table>

<h3>Payments</h3>
<table width="100%" border="1" style="border-collapse: collapse; text-align:center;">
    <thead>
        <tr style="background-color:#f0f0f0;">
            <th>#</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Paid By</th>
            <th>Payment Date</th>
            <th>Status</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
';

foreach ($payments as $i => $pay) {
    $html .= '
    <tr>
        <td>'.($i+1).'</td>
        <td>$'.number_format($pay['amount'], 2).'</td>
        <td>'.htmlspecialchars($pay['method']).'</td>
        <td>'.htmlspecialchars($pay['paid_by']).'</td>
        <td>'.date('Y-m-d H:i', strtotime($pay['paid_at'])).'</td>
        <td>'.ucfirst($pay['status']).'</td>
        <td>'.htmlspecialchars($pay['notes'] ?? '').'</td>
    </tr>
    ';
}

$html .= '
    </tbody>
</table>

<p style="margin-top:20px;"><strong>Invoice Notes:</strong> '.htmlspecialchars($invoice['notes'] ?? '').'</p>
<hr>
<p style="text-align:center;">Thank you for your payment!</p>
';

// Output PDF inline in browser
$mpdf->WriteHTML($html);
$mpdf->Output('Receipt_'.$invoice['invoice_number'].'.pdf', 'I');
exit;
?>
