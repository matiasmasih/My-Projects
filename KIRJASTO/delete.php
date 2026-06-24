<?php 
 session_start();
 include("connection.php"); 

 if (isset($_GET["id"])) {
    $id = $_GET['id'];

    $sql_delete = "DELETE FROM `crud` WHERE id=$id";
    $sql_result = mysqli_query($conn, $sql_delete);
    if ($sql_result) {
        $_SESSION['delete'] = "<div class='delete'>Kohteen poistaminen onnistui</div>";
        header("Location: admin.php");
    } else {
        echo "Error";
    }
 }


?>



