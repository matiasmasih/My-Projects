<?php
session_start(); // Start session

if (!isset($_SESSION['added_book'])) {
    // Redirect to form page if no book details are stored in session
    header("Location: add_book.php");
    exit();
}

// Get the added book details from session
$addedBook = $_SESSION['added_book'];
// Unset session variable after displaying the details
unset($_SESSION['added_book']);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Management System - Result</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0MhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<style>
<!-- Your CSS styles -->
</style>

<div class="container">
    <div class="mb-5 text-center">
    </div>
    <ul>
        <!-- Display added book details -->
        <li>Title: <?php echo $addedBook['title']; ?></li>
        <li>Author: <?php echo $addedBook['author']; ?></li>
        <li>Year: <?php echo $addedBook['year']; ?></li>
        <li>Publisher: <?php echo $addedBook['publisher']; ?></li>
        <li>ISBN: <?php echo $addedBook['isbn']; ?></li>
        <li>Available: <?php echo $addedBook['available'] ? 'Yes' : 'No'; ?></li>
        <!-- Display other book details -->
    </ul>
</div>
</body>
</html>
