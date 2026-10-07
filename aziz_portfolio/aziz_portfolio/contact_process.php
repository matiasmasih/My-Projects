<?php
// contact_process.php - Handle contact form submissions
require_once 'config.php';

// Set timezone
date_default_timezone_set('Europe/Helsinki');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate inputs
    if (empty($name)) {
        $response['message'] = 'Nimi on pakollinen';
    } elseif (empty($email)) {
        $response['message'] = 'Sähköposti on pakollinen';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Syötä kelvollinen sähköpostiosoite';
    } elseif (empty($subject)) {
        $response['message'] = 'Aihe on pakollinen';
    } elseif (empty($message)) {
        $response['message'] = 'Viesti on pakollinen';
    } elseif (strlen($message) < 10) {
        $response['message'] = 'Viestin on oltava vähintään 10 merkkiä';
    } else {
        // Save to database
        $sql = "INSERT INTO contact_messages (name, email, subject, message, status, created_at) 
                VALUES (?, ?, ?, ?, 'unread', NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $name, $email, $subject, $message);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Kiitos viestistäsi! Otan sinuun pian yhteyttä.';
        } else {
            $response['message'] = 'Virhe: ' . $conn->error;
        }
        $stmt->close();
    }
}

// Return JSON response for AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    // Redirect back to contact page with message
    session_start();
    $_SESSION['contact_message'] = $response;
    header('Location: index.php#contact');
    exit;
}
?>
