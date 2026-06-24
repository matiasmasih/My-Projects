<?php 
 session_start();
 include("connection.php"); 

 if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $delete_device = "DELETE FROM laite WHERE id=$id";
    $result_device = mysqli_query($conn, $delete_device);

    if ($result_device ) {
        $_SESSION['delete'] = "<div class='delete'>Laite poistaminen onnistui</div>";
        header("Location: devices.php");
    } else {
        echo "Error";
    }
 }

 ?>

