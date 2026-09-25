<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Only full access can generate invoices
$access = require_access('invoices', 'login.php');
if ($access !== 'full') {
    header("Location: invoices.php");
    exit;
}

$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
if ($appointment_id <= 0) {
    die("Invalid appointment ID.");
}

try {
    // Fetch appointment + patient + doctor's consultation fee
    $stmt = $pdo->prepare("
        SELECT
            a.id,
            a.reason,
            a.duration_minutes,
            p.id AS patient_id,
            p.first_name AS patient_first,
            p.last_name AS patient_last,
            d.id AS doctor_id,
            d.consultation_fee
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        die("Appointment not found.");
    }

    // If invoice already exists for this appointment, redirect to it
    $check = $pdo->prepare("SELECT id FROM invoices WHERE appointment_id = ?");
    $check->execute([$appointment_id]);
    $existingInvoice = $check->fetch(PDO::FETCH_ASSOC);

    if ($existingInvoice) {
        header("Location: view_invoice.php?id=" . $existingInvoice['id']);
        exit;
    }

    // ============================================
    // SEQUENTIAL INVOICE NUMBER
    // Format: INV-YYYY-NNNN
    // ============================================
    $year = date('Y');
    $stmt = $pdo->prepare("
        SELECT invoice_number
        FROM invoices
        WHERE invoice_number LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute(["INV-$year-%"]);
    $lastNumber = $stmt->fetchColumn();

    $nextSeq = 1;
    if ($lastNumber) {
        // Extract the 4-digit sequence at the end
        if (preg_match('/INV-\d{4}-(\d+)/', $lastNumber, $m)) {
            $nextSeq = (int)$m[1] + 1;
        }
    }

    $invoice_number = sprintf("INV-%s-%04d", $year, $nextSeq);

    // Safety: if it somehow exists, bump until unique
    $tries = 0;
    while ($tries < 100) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = ?");
        $chk->execute([$invoice_number]);
        if ($chk->fetchColumn() == 0) break;
        $nextSeq++;
        $invoice_number = sprintf("INV-%s-%04d", $year, $nextSeq);
        $tries++;
    }

    // ============================================
    // CALCULATE AMOUNT
    // ============================================
    $fee = (float)($appointment['consultation_fee'] ?? 0);
    if ($fee <= 0) {
        // Fallback if doctor has no fee set
        $fee = 50.00;
    }

    $duration = (int)($appointment['duration_minutes'] ?? 30);
    if ($duration <= 0) $duration = 30;

    // Fee is per 30 minutes (round up to next 30)
    $units = ceil($duration / 30);
    $line_total = $fee * $units;

    // ============================================
    // DUE DATE (7 days from now)
    // ============================================
    $due_date = date('Y-m-d', strtotime('+7 days'));
    $notes = "Invoice for appointment: " . ($appointment['reason'] ?? 'consultation');

    // ============================================
    // INSERT INVOICE
    // ============================================
    $pdo->beginTransaction();

    try {
        $insert = $pdo->prepare("
            INSERT INTO invoices
                (appointment_id, invoice_number, patient_id, issued_by, total_amount, status, notes, due_date, created_at)
            VALUES
                (?, ?, ?, ?, ?, 'issued', ?, ?, NOW())
        ");
        $insert->execute([
            $appointment_id,
            $invoice_number,
            $appointment['patient_id'],
            $_SESSION['user_id'],
            $line_total,
            $notes,
            $due_date
        ]);

        $invoice_id = (int)$pdo->lastInsertId();

        // ============================================
        // INSERT INVOICE ITEM (line item)
        // ============================================
        $itemDesc = 'Medical Consultation';
        if (!empty($appointment['reason'])) {
            $itemDesc .= ' — ' . $appointment['reason'];
        }

        $insertItem = $pdo->prepare("
            INSERT INTO invoice_items
                (invoice_id, description, item_type, reference_id, quantity, unit_price, total_price)
            VALUES
                (?, ?, 'service', ?, ?, ?, ?)
        ");
        $insertItem->execute([
            $invoice_id,
            $itemDesc,
            $appointment['doctor_id'] ?? null,
            $units,
            $fee,
            $line_total
        ]);

        $pdo->commit();

        header("Location: view_invoice.php?id=" . $invoice_id);
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Database Error: " . $e->getMessage());
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
