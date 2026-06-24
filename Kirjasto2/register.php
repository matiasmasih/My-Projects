<?php
session_start();
require_once 'connection.php';
if ($conn->connect_error) die ("Login connection Error");

/// register
if ($_SERVER['REQUEST_METHOD']=='POST') {

  $firstname = $_POST["firstname"];
  $lastname = $_POST["lastname"];
  $email = $_POST["email"];
  $password = $_POST["password"];
  $cpassword = $_POST["cpassword"];

  if (empty($_POST['firstname'])) {
      $_SESSION['firstname'] = "<div class='firstname'>Etunimi vaaditaan!</div>";
  } else if (empty($_POST["lastname"])) {
      $_SESSION["lastname"] = "<div class='lastname'>Sukunimi vaaditaan!</div>";
  } else if (empty($_POST["email"])) {
      $_SESSION["email"] = "<div class='email'>Sähköposti vaaditaan!</div>";
  } else if (empty($_POST["password"])) {
      $_SESSION["password"] = "<div class='password'>Salasana vaaditaan!</div>";
  } else {
      $sql_register = "SELECT * FROM register WHERE email='$email'";
      $result_register = mysqli_query($conn, $sql_register);
      if ($result_register) {
          $num = mysqli_num_rows($result_register);
          if ($num > 0) {
              $_SESSION['email-exit'] = "<div class='email-exit'>Sähköpostiosoite on jo olemassa, kokeile toista!</div>";
          } else if ($password !== $cpassword) {
                     $SESSION['password-not-mach'] = "<div class='password-not-match'>Salasana ja vahvista salasana eivät täsmää</div>";
          } else {
              $sql_register = "INSERT INTO register(firstname, lastname, email, password, cpassword) VALUES ('$firstname', '$lastname', '$email', '$password', '$cpassword')";
              $result_register = mysqli_query($conn, $sql_register);
          if ($result_register) {
              $_SESSION['register'] = "<div class='register'>Rekisteröityminen onnistui nyt voit kirjautua sisään</div>";
              header("location: login1.php");
          } else {
              die(mysqli_error($conn));
          }

        }
       }
      }
    }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registeröidy</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</head>
<style>
    /* CSS styles can go here */
    body {
        font-family: Arial, sans-serif;
        overflow: scroll;
        background: url("https://i.pinimg.com/originals/1a/7e/d3/1a7ed3b4775376a0d283e6e0cf206384.jpg");
        background-repeat: no-repeat;
        background-position: center;
        background-size: cover;
        margin: 25px;
        color: aqua;
        width: 95%;
        height: 90vh;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        /* Ensure content is pushed to the bottom */
    }

    body::-webkit-scrollbar {
        width: 0;
    }

    .container {
        width: 100%;
        max-height: 95vh;
        overflow: hidden;
        padding: 0px;
        margin: 10 auto 10px auto;
        border: 1px solid Aqua;
        background-image: url("https://wallpaper.dog/large/10928880.jpg");
        background-repeat: no-repeat;
        background-position: center;
        background-size: cover;
        border-radius: 5px;
    }

    .container img {
        width: 100%;
        max-height: 800px;
        /* Set a max height for the image */
        object-fit: cover;
    }

    legend {
        text-align: center;
        margin-top: 20px;
        margin-bottom: 20px;
        /* Reduced margin */
    }

    form {
        width: 100%;
        margin-bottom: 20px;
        /* Reduced margin */
    }

    .row {
        display: flex;
    }

    h4 {
        text-align: center;
        margin: 20px;
    }

    .error-login {
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: red;
        color: white;
        padding: 7px 10px;
        margin: 10px auto;
        border-radius: 4px;
        text-align: center;
    }

    a {
        text-decoration: none;
    }

    img {
        max-height: 300px;
        /* Reduced max height */
        object-fit: cover;
    }

    .firstname,
    .lastname,
    .email,
    .password,
    .password-not-match {
        color: red;
        margin-left: 5px;
    }

    .email-exit {
        background-color: red;
        color: white;
        text-align: center;
        padding: 5px 10px;
        border-radius: 4px;
        margin-bottom: 12px;
    }

    .register {
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: red;
        color: white;
        padding: 7px 10px;
        margin: 10px auto;
        border-radius: 4px;
        text-align: center;
    }

    .container form input {
        background-color: rgba(0, 0, 0, 0.9);
        color: #fff;
    }

    .container form input:focus {
        background-color: rgba(0, 0, 0, 0.1);
        color: white;
    }

    .container form input::placeholder {
        color: rgb(106, 102, 96);
    }
</style>

<body>
    <div class="container">
        <div style="display: flex;">
            <div style="width: 45%;">
                <img src="https://i.pinimg.com/originals/ea/79/4e/ea794e378f9760047f4d3783dc663a20.jpg" alt="" style="height: 100%;">
            </div>
            <div style="width: 55%; padding: 20px;">
                <form action="register.php" method="post">
                    <fieldset>
                        <legend>Rekisteröidy</legend>
                        <?php
                            if (isset($_SESSION['email-exit'])) {
                                echo $_SESSION['email-exit'];
                                unset($_SESSION['email-exit']);
                            }
                        ?>
                        <div class="mb-3">
                            <label for="firstname" class="form-label">EtuNimi *</label>
                            <input type="text" name="firstname" class="form-control" placeholder="Etunimi">
                            <?php
                                if (isset($_SESSION['firstname'])) {
                                    echo $_SESSION['firstname'];
                                    unset($_SESSION['firstname']);
                                }
                            ?>
                        </div>
                        <div class="mb-3">
                            <label for="lastname" class="form-label">SukuNimi *</label>
                            <input type="text" name="lastname" class="form-control" placeholder="Sukunimi">
                            <?php
                                if (isset($_SESSION['lastname'])) {
                                    echo $_SESSION['lastname'];
                                    unset($_SESSION['lastname']);
                                }
                            ?>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Sähköposti *</label>
                            <input type="email" name="email" class="form-control" placeholder="esimerkki@edu.hel.fi">
                            <?php
                                if (isset($_SESSION['email'])) {
                                    echo $_SESSION['email'];
                                    unset($_SESSION['email']);
                                }
                            ?>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Salasana *</label>
                            <input type="password" name="password" class="form-control" placeholder="***************">
                            <?php
                                if (isset($_SESSION['password'])) {
                                    echo $_SESSION['password'];
                                    unset($_SESSION['password']);
                                }
                            ?>
                        </div>
                        <div class="mb-5">
                            <label for="cpassword" class="form-label">Vahvista salasana *</label>
                            <input type="password" name="cpassword" class="form-control" placeholder="***************">
                            <?php
                                if (isset($_SESSION['password-not-mach'])) {
                                    echo $_SESSION['password-not-mach'];
                                    unset($_SESSION['password-not-mach']);
                                }
                            ?>
                        </div>
                        <div class="mb-3">
                            <input type="submit" class="btn btn-danger w-30" name="login" value="Kirjaudu sisään">
                        </div>
                        <div class="mb-3">
                            <span>Minulla on tili! </span><a href="login1.php" style="color: red;">Kirjaudu sisään</a>
                        </div>
                        <hr>
                        <span>Pala takaisin etusivulle! </span><a href='display.php' style="color: red;">Takasin</a>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
    <div class="bottom"></div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
</body>

</html>


