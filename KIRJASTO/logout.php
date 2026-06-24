<?php
 require_once 'connection.php';
 if ($conn->connect_error) die ("logout error");

  session_start();
  session_destroy();

  header("location: display.php ");

?>



