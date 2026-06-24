<?php
 require_once 'connection.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
header ('locaition: test.php');
}

// Check if message data is received
$message = $_POST['message'] ?? '';
if (!empty($message)) {
    // Prepare and bind the statement
    $stmt = $conn->prepare("INSERT INTO messages (message) VALUES (?)");
    if (!$stmt) {
        die("Error: " . $conn->error); // Print any errors that occur during preparation
    }

    $stmt->bind_param("s", $message);

    // Execute statement
    if ($stmt->execute() === TRUE) {
        echo "<div class='message'>" . htmlspecialchars($message) . "</div>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Error: Message data not received.";
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat</title>
    <link rel="stylesheet" href="styles.css">
</head>
<style>

/* Apply basic styling to the chat container */
.chat-container {
    height: 300px;
    overflow-y: scroll;
    border: 1px solid #ccc;
    padding: 10px;
}

/* Style the message input field */
input[type="text"] {
    width: 70%;
    padding: 5px;
    margin-right: 5px; /* Add some space between input and button */
}

/* Style the send button */
button[type="submit"] {
    padding: 5px 10px;
    background-color: #007bff; /* Blue color for the button */
    color: #fff; /* White text color */
    border: none;
    cursor: pointer;
}

/* Style the messages */
.message {
    margin-bottom: 10px;
    background-color: #f2f2f2; /* Light gray background */
    padding: 10px;
    border-radius: 5px;
}

/* Style the messages sent by the user */
/* You can add additional styling for different types of messages */
.message.sent {
    background-color: #007bff; /* Blue background for sent messages */
    color: #fff; /* White text color */
}

/* You can add more styles as needed */

</style>

<body>
    <div class="chat-container" id="chat-container">
        <!-- Messages will be displayed here -->
    </div>
    <form action="test.php" method="post">
    <input type="text" id="message-input" placeholder="Type your message...">
    <button type="submit">Send</button>
</body>
</html>
