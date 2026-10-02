<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('payments', 'login.php');

if ($access !== 'full') {
    $_SESSION['flash_error'] = "You do not have permission to record payments.";
    header("Location: payments.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: payments.php");
    exit;
}

$invoice_id            = (int)($_POST['invoice_id'] ?? 0);
$amount                = (float)($_POST['amount'] ?? 0);
$paid_at               = trim($_POST['paid_at'] ?? '');
$method                = $_POST['method'] ?? 'cash';
$status                = $_POST['status'] ?? 'paid';
$paid_by               = trim($_POST['paid_by'] ?? '');
$transaction_reference = trim($_POST['transaction_reference'] ?? '');
$notes                 = trim($_POST['notes'] ?? '');

// Normalize datetime-local → MySQL
if ($paid_at !== '') {
    $paid_at = str_replace('T', ' ', $paid_at);
    if (strlen($paid_at) === 16) $paid_at .= ':00';
}

if ($invoice_id <= 0 || $amount <= 0 || $paid_at === '') {
    $_SESSION['flash_error'] = "Missing required fields.";
    header("Location: payments.php");
    exit;
}

// Verify invoice exists
try {
    $stmt = $pdo->prepare("SELECT id, total_amount, status FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        $_SESSION['flash_error'] = "Invoice not found.";
        header("Location: payments.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Database Error: " . $e->getMessage();
    header("Location: payments.php");
    exit;
}

// Insert the payment
try {
    $stmt = $pdo->prepare("
        INSERT INTO payments
        (invoice_id, amount, method, transaction_reference, paid_by, paid_at, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $invoice_id, $amount, $method, $transaction_reference,
        $paid_by ?: null, $paid_at, $status, $notes ?: null
    ]);
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Failed to save payment: " . $e->getMessage();
    header("Location: payments.php");
    exit;
}

// ============================================
// AUTO-UPDATE INVOICE STATUS
// ============================================
try {
    // Sum of successful payments on this invoice
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total_paid
        FROM payments
        WHERE invoice_id = ? AND status = 'paid'
    ");
    $stmt->execute([$invoice_id]);
    $total_paid = (float)$stmt->fetchColumn();

    $invoice_total = (float)$invoice['total_amount'];

    if ($total_paid >= $invoice_total && $invoice_total > 0) {
        $new_status = 'paid';
    } elseif ($total_paid > 0) {
        $new_status = 'partially_paid';
    } else {
        // nothing paid — leave existing (draft/issued)
        $new_status = $invoice['status'];
    }

    $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $invoice_id]);
} catch (PDOException $e) {
    // Payment saved, but status update failed — still proceed
    $_SESSION['flash_error'] = "Payment saved but invoice status update failed: " . $e->getMessage();
    header("Location: payments.php");
    exit;
}

$_SESSION['flash_success'] = "Payment recorded successfully. Invoice status updated to: " . str_replace('_', ' ', $new_status) . ".";
header("Location: payments.php");
exit;
