<?php 
 session_start();
 include("connection.php"); 

 if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $delete_book = "DELETE FROM kirja WHERE id=$id";
    $result_delete = mysqli_query($conn, $delete_book);

    if ($result_delete) {
        $_SESSION['delete'] = "<div class='delete'>Kirja poistaminen onnistui</div>";
        header("Location: kirja.php");
    }

 }


 ?>

