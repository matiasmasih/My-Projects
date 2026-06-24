<?php
session_start();
include("connection.php");

 if (isset($_COOKIE['email']) && isset($_COOKIE['password'])) {
    $id = $_COOKIE['email'];
    $pass = $_COOKIE['password'];
 } else {
  $id = "";
  $pass = "";
 }

 if ($conn->connect_error) die ("Login connection Error");

 if (isset($_POST['email']) && isset($_POST['password'])) {

 $email = $_POST['email'];
 $password = $_POST['password'];
 $remember = $_POST['remember'];

 if (empty($_POST['email'])) {
  $_SESSION["error"] = "<p style='color: red;'>Sähköposti Vatii!</p>";
 }
 else if (empty($_POST['password'])) {
  $_SESSION['pass-error'] = "<p style='color: red;'>Salasana Vatii</p>";
 } else {
  $sql_login = "SELECT * FROM register WHERE email='$email' AND password='$password'";
  $result = $conn->query($sql_login);

   if ($result->num_rows > 0) {
   $row = $result->fetch_assoc();

   // for remember me check box input
   if (isset($_REQUEST['remember'])) {
      setcookie('email', $_REQUEST['email'], time() + (60 * 60 + 24));
      setcookie('password', $_REQUEST['password'], time() + (60 * 60 + 24));
   } else {
     setcookie('email', $_REQUEST['email'], time() - (60 * 60 + 24));
     setcookie('password', $_REQUEST['password'], time() - (60 * 60 + 24));
  }

   $_SESSION['email'] = $email;
   header("Location: admin.php");
   //exit();
  } else {
   $_SESSION['error-login'] = "<p class='error-login'>Sähköpostiosoite tai salasana väärä! yritä uudelleen!</p>";
  }
 }
}
?>

<!DOCTYPE html>
 <html lang="en">
 <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page</title>
   <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
 </head>

<!-- STYLE CSS HERE -->

<style>
    /* CSS styles can go here */
body {
 width: 100%;
 Height: 89vh;
 font-family: Arial, sans-serif;
 overflow: scroll;
 background: url("https://cdn.pixabay.com/photo/2017/02/08/17/24/fantasy-2049567_1280.jpg");
 background-repeat: no-repeat;
 background-position: center;
 background-size: cover;
 color: Aqua;
}

body::-webkit-scrollbar {
 width: 0;
}

h1 {
 color: #333;
}

.container {
 width: 84%;
 margin: 10% auto;
 background-image: url("https://th.bing.com/th/id/OIP.BclskZlpUuJPjxcWQrx4JAHaE7?pid=ImgDet&w=178&h=118&c=7&dpr=1,5");
 border-radius: 5px;
 border: 1px solid white;
 overflow: hidden;
 padding: 0px;

}

.container img {
 height: 100%;
 width: 100%;
}

form {
 width: 100%;
 padding: 60px 20px;
}

.row {
 display: flex;
}

h4 {
 text-align: center;
 margin-top: 70px;
}

.error-login, .register {
 display: flex;
 align-items: center;
 justify-content: center;
 background-color: red;
 color: Aqua;
 padding: 7px 10px;
 margin: 10px auto;
 border-radius: 4px;
 text-align: center;
}

a {
 text-decoration: none;
}

.remember {
 font-size: 14px;
 align-items: center;
 text-align: center;
 color: Aqua;
}


</style>
</head>

<body>
 <div class="container">
 <?php
  if (isset($_SESSION['error-login'])) {
   echo $_SESSION['error-login'];
   unset($_SESSION['error-login']);
  }
 ?>
 <div style="display: flex;">
  <div style="width: 50%;">
  <img src="https://images4.alphacoders.com/485/48578.jpg"/>
</div>
 <div style="width: 50%">
   <h4>Kirjaudu sisään</h4>

   <?php
    if (isset($_SESSION['register'])) {
      echo $_SESSION['register'];
      unset($_SESSION['register']);
     }
   ?>
  <form action="login1.php" method="post">
    <div class="mb-3">
     <label for="email" class="form-label">Sähköpost *</label>
     <input type="email" class="form-control" name="email" value="<?php echo $id; ?>" id="exampleInputEmail1" placeholder="esimerkki@edu.hel.fi" aria-describedby="emailHelp">
     <?php
      if (isset($_SESSION['error'])) {
       echo $_SESSION['error'];
       unset($_SESSION['error']);
      }
     ?>
    </div>
    <div class="mb-3">
     <label for="password" class="form-label">Password:</label>
     <input type="password" name="password" value="<?php echo $pass; ?>" class="form-control" placeholder="***************" id="exampleInputPassword1">
     <?php
      if (isset($_SESSION['pass-error'])) {
       echo $_SESSION['pass-error'];
       unset($_SESSION['pass-error']);
      }
     ?>
    </div>

     <div style="width: 50%">

     </div>
     <div class="mb-4">
        <input type="checkbox" name="remember" <?php if (isset($_COOKIE['remember_cookie'])) echo "checked"; ?> >
        <label class="remember">muista minut!</label>
     </div>
     <div class="mb-4">
      <input type="submit" class="btn btn-danger w-30" name="login" value="Kirjaudu sisään">
     </div>
     <div class="mb-3">
      <span>Minulla ei ole tiliä! </span><a href='register.php'>Rekisteröidy</a>
     </div>
    </form>
   </div>
  </div>
 </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
</body>
</html>





