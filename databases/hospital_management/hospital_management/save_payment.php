<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: login.php");
    exit;
}

// Get POST data safely
$invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$method = isset($_POST['method']) ? trim($_POST['method']) : 'cash';
$status = isset($_POST['status']) ? trim(strtolower($_POST['status'])) : 'pending';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
$payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
$paid_by = $_SESSION['user_id'];

// Validate amount
if ($amount <= 0) {
    die("Invalid payment amount.");
}

// Validate required fields for manual payments
if ($invoice_id <= 0 && $patient_id <= 0) {
    die("Please select a patient for manual payment.");
}

// ✅ Match exact ENUM values
$allowed_status = ['pending', 'paid', 'failed'];
$allowed_methods = ['cash', 'card', 'insurance', 'bank_transfer', 'other'];

if (!in_array($status, $allowed_status)) {
    $status = 'pending';
}
if (!in_array($method, $allowed_methods)) {
    $method = 'cash';
}

try {
    // If no invoice ID but we have patient ID, create a direct payment
    if ($invoice_id <= 0 && $patient_id > 0) {
        // Generate a unique invoice number for direct payments
        $invoice_number = 'DIRECT-' . date('Ymd-His') . '-' . rand(100, 999);
        $issued_at = date('Y-m-d H:i:s');
        $due_date = date('Y-m-d'); // Same as issued date for direct payments
        $issued_by = $_SESSION['user_id']; // Current user issuing the invoice

        // Create a simple invoice record for this direct payment
        $stmt = $pdo->prepare("
            INSERT INTO invoices (
                patient_id,
                invoice_number,
                issued_by,
                issued_at,
                due_date,
                total_amount,
                status,
                notes
            )
            VALUES (?, ?, ?, ?, ?, ?, 'paid', ?)
        ");
        $stmt->execute([
            $patient_id,
            $invoice_number,
            $issued_by,
            $issued_at,
            $due_date,
            $amount,
            $notes
        ]);
        $invoice_id = $pdo->lastInsertId();

    } elseif ($invoice_id > 0) {
        // For existing invoices, get the patient_id to ensure consistency
        $getInvoice = $pdo->prepare("SELECT patient_id, total_amount FROM invoices WHERE id = ?");
        $getInvoice->execute([$invoice_id]);
        $invoice = $getInvoice->fetch();

        if ($invoice) {
            $patient_id = $invoice['patient_id']; // Use the invoice's patient_id
        }
    }

    // Validate invoice exists (if provided)
    if ($invoice_id > 0) {
        $checkInvoice = $pdo->prepare("SELECT id FROM invoices WHERE id = ?");
        $checkInvoice->execute([$invoice_id]);
        if (!$checkInvoice->fetch()) {
            die("Invalid invoice ID: Invoice does not exist.");
        }
    }

    // Insert payment
    $stmt = $pdo->prepare("
        INSERT INTO payments (invoice_id, amount, method, status, notes, paid_at, paid_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$invoice_id, $amount, $method, $status, $notes, $payment_date, $paid_by]);

    // ✅ If status is 'paid', update the invoice status as well
    if ($status === 'paid' && $invoice_id > 0) {
        $update = $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?");
        $update->execute([$invoice_id]);
    }

    // Redirect back to payments page
    header("Location: payments.php");
    exit;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
