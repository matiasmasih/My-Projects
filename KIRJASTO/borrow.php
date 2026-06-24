<?php
session_start();
include("connection.php");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = ""; // Initialize an empty message
$message_color = ""; // Initialize an empty message color

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $user_name = $_POST['userName'];
    $user_email = $_POST['userEmail'];
    $item_type = $_POST['itemType'];
    $item_name = $_POST['itemName'];
    $action_date = $_POST['borrowDate'];
    $action_type = 'borrow';

    // Prepare and bind
    $stmt = $conn->prepare("INSERT INTO borrow_return (user_name, user_email, item_type, item_name, action_type, action_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $user_name, $user_email, $item_type, $item_name, $action_type, $action_date);

    // Execute and check for success
    if ($stmt->execute()) {
        $message = "Item borrowed successfully!";
        $message_color = "bg-success"; // Bootstrap class for success
    } else {
        $message = "Error: Item could not be borrowed.";
        $message_color = "bg-danger"; // Bootstrap class for error
    }

    $stmt->close();
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Books and Devices</title>
    <!--  <link rel="stylesheet" href="style.css?=">  -->
    <!-- bootstrap links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
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
        margin-top: 90px;
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

    .form-group label {
        font-weight: bold;
        color: Aqua;
    }

    .form-control {
        border-radius: 4px;
    }

    button[type="submit"] {
        width: auto;
        padding: 10px 20px;
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

    @media (max-width: 276px) {
        .container {
            width: 50%;
            padding: 15px;
        }

        h3 {
            font-size: 1.5rem;
        }
    }

    </style>
</head>
<body>

    <div class="container">
        <h3>Borrow Books and Devices</h3>
        <?php if ($message != ""): ?>
            <p class="message <?php echo $message_color; ?>"><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="post" action="borrow.php">
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
                <label for="borrowDate">Borrow Date</label>
                <input type="date" class="form-control" id="borrowDate" name="borrowDate" required>
            </div>
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
