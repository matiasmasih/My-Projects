<?php
require_once 'connection.php';
require_once 'receipt_helper.php';

// Test creating a receipt
$result = createLoanReceipt(1, 9, 'book', 'To Kill a Mockingbird', date('Y-m-d H:i:s'));

if ($result) {
    echo "Receipt created successfully! ID: " . $result;
} else {
    echo "Failed to create receipt";
    global $conn;
    echo "<br>Error: " . $conn->error;
}
?>
