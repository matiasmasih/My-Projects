<?php
 $serverName = 'localhost';
 $userName   = 'naziz';
 $dataBaseName = 'libnaziz';
 $password = 'tietomekaanikko';

 $conn = new mysqli($serverName, $userName, $password, $dataBaseName);

 if ($conn->connect_error) {
    die("can not connect");
 } else {
   echo "";
 }

?>
