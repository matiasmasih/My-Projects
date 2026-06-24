
<?php
 session_start();
 include("connection.php");

if ($conn->connect_error) {
 die("Database connection Error:" . $conn->connect_error);
}

 if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!isset($_GET['id'])) {
        header('location: display.php');

    }
    $id = $_GET['id'];
    $sql = "SELECT * FROM `customers` WHERE id=$id";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);

    if (!$row) {
        header('location: display.php');

    }
    $name = $row['name'];
    $email = $row['email'];
    $phone = $row['phone'];
    $password = $row['password'];

 } else {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];


    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $_SESSION['empty'] = "<div class='empty'>Ole hyvä ja täytä syöte</div>";
    }  else {
        $sql = "UPDATE `customers` SET name='$name', email='$email', phone='$phone', password='$password' WHERE id=$id";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            echo "wrong";
        }
        $_SESSION["update"] = "<div class='update'>Päivitys onnistui</div>";
        header("location: display.php");
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
    <!-- bootstrap links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <title>PHP HOME PAGE</title>
    <!-- JQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

</head>
<style>
    a {
        text-decoration: none;
        color: white;
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
    form {
    width: 100%;
   height: 100%;
    margin: auto;
    color: rgb(255, 255, 255);
    padding: 40px 20px;
    background: url("https://cdn.wallpapersafari.com/64/40/HG04iv.jpg");
    background-repeat: no-repeat;
    background-position: center;
    background-size: cover;
    background-attachment: fixed;
    z-index: 99;

}
.update-btn {
    background-color: tomato;
    color: white;
    border: 1px solid white;
}
</style>
<body>
   <div class="user">
    <div style="display: flex;">
        <div style="width: 50%;">
            <img width="100%" height="100%" src="https://images.all-free-download.com/images/graphiclarge/abstract_background_black_272662.jpg" alt="">
        </div>
        <div style="width: 50%;">
        <form action="update.php" method="post">
         <div>
            <h2 style="font-style: italic;" class="text-center mb-5">PÄIVITÄ KÄYTTÄJÄ</h2>
         </div>
         <?php
           if (isset($_SESSION['empty'])) {
                echo $_SESSION['empty'];
                unset($_SESSION['empty']);
           }
         ?>
             <input type="hidden" name="id" value="<?php echo $id ?>">
         <div class="mb-3">
            <label for="name" class="form-label">Päivitä Nimi</label>
            <input type="text" name="name" class="form-control" value="<?php echo $name ?>"  placeholder="Kirjoita nimesi" require>
         </div>
         <div class="mb-3">
            <label for="email" class="form-label">Päivitä Sähköposti</label>
            <input type="email" name="email" value="<?php echo $email ?>"  placeholder="Kirjoita sähköpostiosoitteesi" class="form-control" require>
         </div>
         <div class="mb-3">
            <label for="phone" class="form-label">Päivitä Puhelin Numero</label>
            <input type="text" name="phone" value="<?php echo $phone ?>" placeholder="Kirjoita puhelin numerosi" class="form-control" require>
         </div>
         <div class="mb-5">
            <label for="password" class="form-label">Päivitä Salasana</label>
            <input type="password" name="password" value="<?php echo $password ?>" placeholder="***************" class="form-control" require>
         </div>

         <div class="mb-3">
            <button type="submit" name="submit" class="btn update-btn w-30">Päivitä</button>
         </div>
         <div class="mb-3">
             <span style="margin-left: 3px; color: green;">Tässä vain lisäät tietoa henkilö</span> <a href="display.php">Näytä käyttäjä</a>
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
 <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

</body>
</html>
