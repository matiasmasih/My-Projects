<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Debug: Log that we reached this page
error_log("save_ward.php accessed by user: " . $_SESSION['user_id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received");
    
    // Get and validate form data
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $capacity = (int)$_POST['capacity'];
    $charge_per_day = (float)$_POST['charge_per_day'];
    $location = trim($_POST['location']);
    $phone_extension = trim($_POST['phone_extension'] ?? '');
    $status = $_POST['status'];
    $in_charge_id = !empty($_POST['in_charge_id']) ? (int)$_POST['in_charge_id'] : null;
    $notes = trim($_POST['notes'] ?? '');

    error_log("Form data - Name: $name, Type: $type, Capacity: $capacity");

    // Basic validation
    $errors = [];

    if (empty($name)) {
        $errors[] = "Ward name is required";
    }

    if (empty($location)) {
        $errors[] = "Location is required";
    }

    if ($capacity <= 0) {
        $errors[] = "Capacity must be greater than 0";
    }

    if ($charge_per_day < 0) {
        $errors[] = "Charge per day cannot be negative";
    }

    // Check if ward name already exists
    try {
        $stmt = $pdo->prepare("SELECT id FROM wards WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            $errors[] = "A ward with this name already exists";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error while checking ward name: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
    }

    error_log("Validation errors: " . count($errors));

    // If no errors, insert the new ward
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO wards 
                (name, type, capacity, charge_per_day, location, phone_extension, status, in_charge_id, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $name, $type, $capacity, $charge_per_day,
                $location, $phone_extension, $status,
                $in_charge_id, $notes
            ]);
            
            if ($result) {
                // Get the ID of the newly created ward
                $new_ward_id = $pdo->lastInsertId();
                error_log("Ward created successfully with ID: " . $new_ward_id);
                
                // Redirect to wards page with success message
                header("Location: wards.php?success=created&id=" . $new_ward_id);
                exit;
            } else {
                error_log("Insert failed - no rows affected");
                $errors[] = "Failed to create ward";
            }
            
        } catch (PDOException $e) {
            $error_msg = "Database Error: " . $e->getMessage();
            $errors[] = $error_msg;
            error_log($error_msg);
        }
    }

    // If there are errors, store them in session and redirect back to form
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = [
            'name' => $name,
            'type' => $type,
            'capacity' => $capacity,
            'charge_per_day' => $charge_per_day,
            'location' => $location,
            'phone_extension' => $phone_extension,
            'status' => $status,
            'in_charge_id' => $in_charge_id,
            'notes' => $notes
        ];
        error_log("Redirecting with errors: " . implode(", ", $errors));
        header("Location: wards.php?error=1");
        exit;
    }
} else {
    error_log("Not a POST request - redirecting to wards.php");
    // If not POST request, redirect to wards page
    header("Location: wards.php");
    exit;
}
?>
