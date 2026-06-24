<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get POST data safely
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$generic_name = isset($_POST['generic_name']) ? trim($_POST['generic_name']) : '';
$brand = isset($_POST['brand']) ? trim($_POST['brand']) : '';
$form = isset($_POST['form']) ? trim($_POST['form']) : '';
$strength = isset($_POST['strength']) ? trim($_POST['strength']) : '';
$unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$initial_quantity = isset($_POST['initial_quantity']) ? (int)$_POST['initial_quantity'] : 0;

// Validate required fields
if (empty($name) || empty($generic_name) || empty($form) || empty($unit)) {
    die("Error: Please fill all required fields.");
}

// Validate form values
$allowed_forms = ['Tablet', 'Capsule', 'Syrup', 'Injection', 'Ointment', 'Cream', 'Drops', 'Inhaler', 'Spray', 'Other'];
if (!in_array($form, $allowed_forms)) {
    die("Error: Invalid form selected.");
}

$allowed_units = ['tablet', 'capsule', 'bottle', 'tube', 'inhaler', 'ampoule', 'vial', 'pack'];
if (!in_array($unit, $allowed_units)) {
    die("Error: Invalid unit selected.");
}

try {
    // Insert medicine into database
    $stmt = $pdo->prepare("
        INSERT INTO medicines (name, generic_name, brand, form, strength, unit, description) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $name,
        $generic_name,
        $brand,
        $form,
        $strength,
        $unit,
        $description
    ]);
    
    $medicine_id = $pdo->lastInsertId();
    
    // Create a default batch for the new medicine
    $batch_number = 'BATCH-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 4)) . '-001';
    $expiry_date = date('Y-m-d', strtotime('+2 years'));
    
    $stmt = $pdo->prepare("
        INSERT INTO medicine_batches (medicine_id, batch_number, expiry_date, cost_price, selling_price) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $medicine_id,
        $batch_number,
        $expiry_date,
        0.00,  // Default cost price
        0.00   // Default selling price
    ]);
    
    $batch_id = $pdo->lastInsertId();
    
    // Create initial stock entry with the quantity from the form
    $stmt = $pdo->prepare("
        INSERT INTO pharmacy_stock (medicine_batch_id, quantity, min_threshold, location) 
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $batch_id,
        $initial_quantity,  // Use the quantity from the form
        10,     // Default minimum threshold
        'main_pharmacy'
    ]);
    
    // Redirect back to medicines page with success message
    header("Location: medicines.php?success=1");
    exit;
    
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
