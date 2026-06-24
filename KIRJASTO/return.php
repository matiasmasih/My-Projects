<?php
session_start();
include("connection.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = "";
$message_color = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_email = $_POST['userEmail'];
    $item_name = $_POST['itemName'];
    $user_name = $_POST['userName'];
    $item_type = $_POST['itemType'];
    $return_date = $_POST['returnDate'];
    $action_type = 'return';

    try {
        // Check if the item is borrowed
        $stmt = $pdo->prepare("SELECT action_date, item_type FROM borrow_return WHERE user_email = ? AND item_name = ? AND action_type = 'borrow'");
        $stmt->execute([$user_email, $item_name]);
        $borrow_data = $stmt->fetch();

        if ($borrow_data) {
            $borrow_date = $borrow_data['action_date'];
            $borrow_type = $borrow_data['item_type'];
            $borrow_timestamp = strtotime($borrow_date);
            $return_timestamp = strtotime($return_date);
            $borrow_period = 30 * 24 * 60 * 60;

            if ($return_timestamp > ($borrow_timestamp + $borrow_period)) {
                $message = "Error: Return date is past the borrowing period.";
                $message_color = "red";
            } elseif ($item_type !== $borrow_type) {
                $message = "Error: You borrowed a $borrow_type, not a $item_type. Please return the correct item.";
                $message_color = "red";
            } else {
                // Delete borrowed record
                $delete_stmt = $pdo->prepare("DELETE FROM borrow_return WHERE user_email = ? AND item_name = ? AND action_type = 'borrow'");
                $delete_stmt->execute([$user_email, $item_name]);

                if ($delete_stmt->rowCount() > 0) {
                    $message = "Item returned successfully and removed from the borrowed list.";
                    $message_color = "green";
                } else {
                    $message = "Error: No record found for deletion.";
                    $message_color = "red";
                }
            }
        } else {
            $message = "Error: Item not borrowed by user.";
            $message_color = "red";
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $message_color = "red";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Books and Devices</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>

<style>

body {
        color: white;
        font-family: Arial, Helvetica, sans-serif;
        background: url('https://cdn.pixabay.com/photo/2015/04/23/22/00/tree-736885_1280.jpg');
        background-repeat: no-repeat;
        background-position: center;
        background-size: cover;
        background-attachment: fixed;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 100vh;
        margin: 0;
    }

    .container {
        width: 50%;
        max-width: 600px;
        padding: 20px;
        background: rgba(0, 0, 0, 0.5); /* Darker transparent background */
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        margin-top: 30px;
        border: 1px solid black;
    }

    input[type="text"],
    input[type="email"],
    input[type="date"],
    input[type="submit"] {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border: 1px solid rgba(255, 255, 255, 0.5); /* Light border for visibility */
        background: rgba(0, 0, 0, 0.5); /* Transparent background */
        color: white; /* Text color */
        border-radius: 4px;
        box-sizing: border-box; /* Ensures padding doesn't affect width */
    }

    label {
        display: block;
        margin-top: 10px;
        margin-bottom: 5px;
    }

    h3 {
        text-align: center;
        color: Aqua;
        margin-bottom: 20px;
    }

    form {
        margin-top: 20px;
    }

    .form-group label {
        font-weight: bold;
        color: Aqua;
    }

    .form-control {
        border-radius: 4px;
    }

    button[type="submit"] {
        width: 20%;
        padding: 10px;
        border-radius: 5px;
        background-color: red;
        font-size: 17px;
        border: none;
        color: white;
    }

    button[type="submit"]:hover {
        background-color: green;
        color: black;
        font-weight: bold;
    }

    .message {
        font-weight: bold;
        margin: 20px 0;
        padding: 10px;
        border-radius: 5px;
        color: white;
    }

    .message.green {
        background-color: green;
    }

    .message.red {
        background-color: red;
    }

    @media (max-width: 576px) {
        .container {
            padding: 15px;
        }

        h3 {
            font-size: 1.5rem;
        }
    }


</style>

<body>
<div class="container">
    <h1 class="mt-5">Return Books and Devices</h1>
    <?php if ($message != ""): ?>
        <p class="message <?php echo $message_color; ?>"><?php echo $message; ?></p>
    <?php endif; ?>
    <form id="returnForm" class="mt-4" method="post" action="return.php">
        <div class="form-group">
            <label for="userName">Name</label>
            <input type="text" class="form-control" id="userName" name="userName" placeholder="Enter your name" required>
        </div>
        <div class="form-group">
            <label for="userEmail">Email</label>
            <input type="email" class="form-control" id="userEmail" name="userEmail" placeholder="Enter your email" required>
        </div>
        <div class="form-group">
            <label for="itemType">Item Type</label>
            <select class="form-control" id="itemType" name="itemType" required>
                <option value="">Select an item type</option>
                <option value="book">Book</option>
                <option value="device">Device</option>
            </select>
        </div>
        <div class="form-group">
            <label for="itemName">Item Name</label>
            <input type="text" class="form-control" id="itemName" name="itemName" placeholder="Enter the name of the item" required>
        </div>
        <div class="form-group">
            <label for="returnDate">Return Date</label>
            <input type="date" class="form-control" id="returnDate" name="returnDate" required>
        </div>
        <button type="submit" class="btn btn-danger m-30">Submit</button>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>


