
<?php
 require_once 'connection.php';
if ($conn->connect_error) die("Fatal Error");

// Check if form is submitted
if (isset($_POST['author'])) {
    $author = get_post($conn, 'author');
    $title = get_post($conn, 'title');
    $year = get_post($conn, 'year');
    $make = get_post($conn, 'make');
    $isbn = get_post($conn, 'isbn');
    $query = "INSERT INTO wishvolume (author, title, year, make, isbn) VALUES ('$author','$title','$year','$make','$isbn')";
    $result = $conn->query($query);
    if (!$result) echo "INSERT failed<br><br>";
}

// Delete item if delete button is clicked
if(isset($_POST['delete'])) {
    $id = $_POST['delete'];
    $query = "DELETE FROM wishvolume WHERE id=$id";
    $result = $conn->query($query);
    if (!$result) echo "DELETE failed<br><br>";

// Reset auto-increment value of ID column to start from one again
    $reset_query = "ALTER TABLE wishvolume AUTO_INCREMENT = 1";
    $conn->query($reset_query);
}

// Fetch wishlist items from the database
$query = "SELECT * FROM wishvolume";
$result = $conn->query($query);
if (!$result) die("Database access failed");

// Close database connection
$conn->close();

// Function to sanitize input
function get_post($conn, $var) {
    return $conn->real_escape_string($_POST[$var]);
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist Form</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Custom CSS -->
    <style>

 body {
  background-image: url('https://img.freepik.com/premium-photo/full-moon-dark-fantasy-landscape_776674-197583.jpg');
  background-size: cover;
  background-repeat: no-repeat;
  background-attachment: fixed;
}

.form-container {
  margin-top: 50px;
}


/* Style for form and result sections */
.form-section{
  padding: 20px;
  border-radius: 10px;
  margin-bottom: 20px;
  color: #fff;
  border: 1px solid black;
  box-shadow: 4px 4px 4px 8px rgba(0, 0, 0, 0.5);
 }

 .result-section {
  padding: 20px;
  border-radius: 10px;
  margin-bottom: 20px;
  color: #fff;
  font-weight: bold;
  border: 1px solid black;
  box-shadow: 4px 4px 4px 8px rgba(0, 0, 0, 0.5);
 }

 .transparent-input {
  background-color: #d6d9dd;
  border: 1px solid #ced4da;
  color: #495057;
}

.wishlist-item {
    margin-bottom: 20px; /* Adjust as needed for the desired spacing */

}

 </style>
</head>

<body>
<div class="container form-container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="form-section">
                    <h3 class="text-center">Add to Wishlist</h3>
                    <form action="wishlist.php" method="post">
                        <label for="author">Author*</label>
                        <input type="text" class="form-control transparent-input" id="author" name="author" placeholder="Author"><br>
                        <label for="title">Title*</label>
                        <input type="text" class="form-control transparent-input" id="title" name="title" placeholder="Title"><br>
                        <label for="year">Year*</label>
                        <input type="text" class="form-control transparent-input" id="year" name="year" placeholder="Year"><br>
                        <label for="make">Make*</label>
                        <input type="text" class="form-control transparent-input" id="make" name="make" placeholder="Make"><br>
                        <label for="isbn">ISBN*</label>
                        <input type="text" class="form-control transparent-input" id="isbn" name="isbn" placeholder="ISBN"><br>
                          <!-- Other input fields -->
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </form>
                </div>
            </div>
        </div>

<div class="container form-container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <?php foreach($result as $row): ?>
            <div class="result-section">
                <h3 class="text-center">Wishlist Item</h3>
                <div class="result-item">
                    <p>Author: <?php echo htmlspecialchars($row['author']); ?></p>
                    <p>Title: <?php echo htmlspecialchars($row['title']); ?></p>
                    <p>Year: <?php echo htmlspecialchars($row['year']); ?></p>
                    <p>Make: <?php echo htmlspecialchars($row['make']); ?></p>
                    <p>ISBN: <?php echo htmlspecialchars($row['isbn']); ?></p>
                    <!-- Delete button -->
                    <form action="wishlist.php" method="post">
                        <input type="hidden" name="delete" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="btn btn-danger delete-btn">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

     <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
  </body>
 </html>
