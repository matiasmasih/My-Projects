<?php
session_start();
include 'config.php';

// ✅ Allow only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    header("Location: login.php");
    exit;
}

// ✅ Get appointment ID
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
if ($appointment_id <= 0) {
    die("Invalid appointment ID.");
}

try {
    // ✅ Fetch appointment info with patient details
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.reason,
            a.duration_minutes,
            p.id AS patient_id,
            p.first_name,
            p.last_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        die("Appointment not found.");
    }

    // ✅ Check if an invoice already exists for this appointment
    $check = $pdo->prepare("SELECT id FROM invoices WHERE appointment_id = ?");
    $check->execute([$appointment_id]);
    $existingInvoice = $check->fetch(PDO::FETCH_ASSOC);

    if ($existingInvoice) {
        // If already exists, redirect to existing invoice
        header("Location: view_invoice.php?id=" . $existingInvoice['id']);
        exit;
    }

    // ✅ Generate a unique invoice number (e.g., INV-2025-0015)
    $invoice_number = "INV-" . date('Y') . "-" . str_pad($appointment_id, 4, "0", STR_PAD_LEFT);

    // Check if this invoice number already exists in DB
    $checkNumber = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = ?");
    $checkNumber->execute([$invoice_number]);
    if ($checkNumber->fetchColumn() > 0) {
        // Add a random suffix to avoid duplicate number
        $invoice_number .= "-" . rand(100, 999);
    }

    // ✅ Calculate total amount (example: $50 per 30 minutes)
    $rate_per_30min = 50;
    $total_amount = ($appointment['duration_minutes'] / 30) * $rate_per_30min;

    // ✅ Set due date (7 days later) and notes
    $due_date = date('Y-m-d', strtotime('+7 days'));
    $notes = "Invoice for appointment: " . htmlspecialchars($appointment['reason']);

    // ✅ Insert new invoice record
    $insert = $pdo->prepare("
        INSERT INTO invoices 
        (appointment_id, invoice_number, patient_id, issued_by, total_amount, status, notes, due_date, created_at)
        VALUES 
        (?, ?, ?, ?, ?, 'draft', ?, ?, NOW())
    ");
    $insert->execute([
        $appointment_id,             // appointment_id
        $invoice_number,             // invoice_number
        $appointment['patient_id'],  // patient_id
        $_SESSION['user_id'],        // issued_by
        $total_amount,               // total_amount
        $notes,                      // notes
        $due_date                    // due_date
    ]);

    // ✅ Get the new invoice ID
    $invoice_id = $pdo->lastInsertId();

    // ✅ Redirect to view the invoice
    header("Location: view_invoice.php?id=" . $invoice_id);
    exit;

} catch (PDOException $e) {
    // ✅ Display a user-friendly error (for debugging)
    die("Database Error: " . $e->getMessage());
}
?>
