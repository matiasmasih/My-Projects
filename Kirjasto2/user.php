<?php
 session_start();
 include("connection.php");

 if (isset($_POST['submit'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $_SESSION['empty'] = "<div class='empty'>Ole hyvä ja täytä syöte</div>";
    } else {
        $sql = "INSERT INTO `customers` (name, email, phone, password) VALUES ('$name', '$email', '$phone', '$password')";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            $_SESSION['user-added'] = "<div class='user-added'>Käyttäjä lisätty</div>";
            header("location: display.php");
        } else {
            echo "error";
        }
    }
 }

?>
<!-- HTML CODE -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?=<?php echo time(); ?>">

    <!-- Bootstrap links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

    <title>PHP HOME PAGE</title>
    <!-- JQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: url("https://img.freepik.com/free-vector/realistic-neon-lights-background_23-2148907367.jpg") no-repeat center center fixed;
            background-size: cover;
            overflow: scroll;
        }

        body::-webkit-scrollbar {
            width: 0;
        }

        .user {
            width: 95%;
            overflow: hidden;
            margin: 5% auto;
            border-radius: 5px;
            background-color: white;
            color: rgb(120, 171, 120);
            background: url("https://img.freepik.com/free-vector/gradient-black-background-with-cubes_23-2149177092.jpg") 
            no-repeat center center fixed;
            background-size: cover;
            z-index: 99;
            box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.1), -4px -4px 10px rgba(0, 0, 0, 0.1);
        }

        .user::after {
            position: fixed;
            content: "";
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.2);
            z-index: -222;
        }

        .user form {
            width: 100%;
            margin: auto;
            color: Aqua;
            padding: 40px 20px;
        }

        .user form input {
            background-color: rgb(54, 46, 61);
            color: white;
            width: 100%;
            padding: 5px;
            margin-top: 5px;
             margin-bottom: 10px;
        }

        a {
            text-decoration: none;
            color: red;
        }

        .empty {
            background-color: tomato;
            color: white;
            padding: 5px 20px;
            width: 100%;
            margin: 10px auto;
            text-align: center;
            border-radius: 4px;
            align-items: center;
        }
    </style>
</head>

<body>

    <div class="user">
        <div style="display: flex; width: 100%; height: 100%;">
            <div style="width: 50%;">
                <img src="https://img.freepik.com/premium-photo/dark-background-with-rows-numbers-words-code-it_771335-51699.jpg"
                    alt="Code Background" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
            <div style="width: 50%;">
                <form action="user.php" method="post">
                    <div>
                        <h2 style="font-style: italic;" class="text-center mb-5">LISSÄ KÄYTTÄJÄ</h2>
                    </div>
                    <?php
                    if (isset($_SESSION['empty'])) {
                        echo $_SESSION['empty'];
                        unset($_SESSION['empty']);
                    }
                    ?>
                    <div class="mb-3">
                        <label for="name" class="form-label">Nimi</label>
                        <input type="text" name="name" class="form-control" placeholder="Kirjoita nimesi" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Sähköposti</label>
                        <input type="email" name="email" placeholder="Kirjoita sähköpostiosoitteesi" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Puhelin Numero</label>
                        <input type="text" name="phone" placeholder="Kirjoita puhelin numerosi" class="form-control" required>
                    </div>
                    <div class="mb-5">
                        <label for="password" class="form-label">Salasana</label>
                        <input type="password" name="password" placeholder="***************" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <button type="submit" name="submit" class="btn btn-danger w-30">Lähetä</button>
                    </div>
                    <div class="mb-3">
                        <span style="margin-left: 3px; color: Aqua;">Tässä vain lisäät tietoa henkilö</span> <a href="display.php">Näytön</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- javascript file -->
    <script src="javascript/main.js"></script>

    <!-- bootstrap links -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js" integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
    <!-- JQuery Links -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g== crossorigin="anonymous" referrerpolicy="no-referrer"></script>

</body>
</html>

