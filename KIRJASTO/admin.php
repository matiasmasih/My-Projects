<?php
 session_start();
 include("connection.php"); 


if (!isset($_SESSION['email'])) {
 header("location: login1.php");
}


 if (isset($_POST['submit'])) {
    $search = $_POST['search'];

    $sql_search = "SELECT * FROM ´customers´ WHERE id LIKE '%$search%' or name LIKE '%$search%'";
    $result_search = mysqli_query($conn, $sql_search);
    if ($result_search) {
        $num_search = mysqli_num_rows($result_search);
        if ($num_search > 0) {
        while ($row = mysqli_fetch_assoc($result_search)) {
            $_SESSION['search'] = "<table class='table'><tbody>
                <tr>
                <th>$row[id]</th>
                <td>$row[name]</td>
                <td>$row[email]</td>
                <td>$row[phone]</td>
                <td>$row[password]</td>
                <td style='text-align: center; align-items: center;'>
                <button class='btn btn-dark border border-ligh'> 
                    <a href='update.php?id=$row[id]'>Päivitä</a>
                    <svg xmlns='http://www.w3.org/2000/svg' width='1.2em' height='1.2em' viewBox='0 0 20 20'><path fill='currentColor' d='M5.7 9c.4-2 2.2-3.5 4.3-3.5c1.5 0 2.7.7 3.5 1.8l1.7-2C14 3.9 12.1 3 10 3C6.5 3 3.6 5.6 3.1 9H1l3.5 4L8 9zm9.8-2L12 11h2.3c-.5 2-2.2 3.5-4.3 3.5c-1.5 0-2.7-.7-3.5-1.8l-1.7 1.9C6 16.1 7.9 17 10 17c3.5 0 6.4-2.6 6.9-6H19z'/></svg>
                </button>
                <button class='btn btn-danger border border-ligh'>
                    <a href='delete.php?id=$row[id]'>Poista</a>
                    <svg xmlns='http://www.w3.org/2000/svg' width='1em' height='1em' viewBox='0 0 2048 2048'><path fill='currentColor' d='M1792 384h-128v1472q0 40-15 75t-41 61t-61 41t-75 15H448q-40 0-75-15t-61-41t-41-61t-15-75V384H128V256h512V128q0-27 10-50t27-40t41-28t50-10h384q27 0 50 10t40 27t28 41t10 50v128h512zM768 256h384V128H768zm768 128H384v1472q0 26 19 45t45 19h1024q26 0 45-19t19-45zM768 1664H640V640h128zm256 0H896V640h128zm256 0h-128V640h128z'/></svg>
                    </button>
                    </td>
                    </tr></tbody></table>
                    ";
        }


        } else {
            $_SESSION['nofound'] =  "<div class='nofound'>tietoja ei löydy</div>";
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
    <!-- bootstrap links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <title>PHP HOME PAGE</title>
    <!-- JQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

</head>
<style>
    .container-display {
    width: 90%;
    margin: 50px auto;
    z-index: 9999;
    }
    table {
        background-color: Aqua;
        color: black;
        border: 1px solid Aqua;
        z-index: 99;
        box-shadow: 4px 4px 10px rgb(84, 84, 84), -4px -4px 10px rgb(84, 84, 84);
    }
    table tr {
        color: darkblue;
        border: 1px solid Aqua;
    }
    a {
        text-decoration: none;
        color: white;
    }
    .user-added, .user-update, .update {
        background-color: rgb(33, 193, 33);
        color: white;
        padding: 10px 20px;
        width: 50%;
        margin: auto;
        text-align: center;
        border-radius: 4px;
        align-items: center;
    }
    .delete {
        background-color: tomato;
        color: white;
        padding: 10px 20px;
        width: 50%;
        margin: auto;
        text-align: center;
        border-radius: 4px;
        align-items: center;
    }
    .input-search {
        width: 100%;
        display: flex;
        justify-content: space-between;
    }
    .input-search input {
        border: 1px solid white;
        background-color: transparent;
        width: 100%;
        height: 40px;
        padding: 10px 10px;
        border-radius: 6px;
        outline: none;
        color: white;
    }
 
    .input-search form {
        width: 75%;
        display: flex;
    }
    .input-search form button {
        padding: 10px 10px;
        width: 80px;
        height: 40px;
        border: 1px solid white;
        background-color: tomato;
        color: white;
        border-radius: 6px;
        margin-left: 10px;
    }
    .nofound {
        border: 1px solid red;
        padding: 10px 10px;
        border-radius: 4px;
        width: 50%;
        margin: auto;
        text-align: center;
        align-items: center;
        color: white;
    }
    .logout {
        width: 35%;
        color: white;
        display: flex;
        margin-left: 100px;
        cursor: pointer;
    }
    .logout a {
        margin-left: 10px;
    }
    a {
        text-decoration: none;
    }
</style>
<body>

<?php
  include "headerForAdmin.php";
?>
    <div class="container-display">

        <?php
          if (isset($_SESSION['user-added'])) {
            echo $_SESSION['user-added'];
            unset($_SESSION['user-added']);
          }
          if (isset($_SESSION['delete'])) {
            echo $_SESSION['delete'];
            unset($_SESSION['delete']);
          }
          if (isset($_SESSION['user-update'])) {
            echo $_SESSION['user-update'];
            unset($_SESSION['user-update']);
          }
          if (isset($_SESSION['update'])) {
            echo $_SESSION['update'];
            unset($_SESSION['update']);
          }
          if (isset($_SESSION['search'])) {
            echo $_SESSION['search'];
            unset($_SESSION['search']);
          }
          if (isset($_SESSION['nofound'])) {
            echo $_SESSION['nofound'];
            unset($_SESSION['nofound']);
          }
        ?>
        <table class="table mt-5">
            <thead>
                <tr>
                <th scope="col">Sl No</th>
                <th scope="col">name</th>
                <th scope="col">phone</th>
                <th scope="col">Email</th>
                <th scope="col">Password</th>
                <th scope="col" style="text-align: center; align-items: center;">Toiminnot</th>
                </tr>
            </thead>
            <tbody>
            <?php

               $sql = "SELECT * FROM `customers`";
               $result = mysqli_query($conn, $sql);
               if (!$result) {
                echo "Error". mysqli_error($conn);
               }
               $number = 1;
               while($row = mysqli_fetch_assoc($result)) {
                  echo "
                        <tr>
                        <th>$row[id]</th>
                        <td>$row[name]</td>
                        <td>$row[email]</td>
                        <td>$row[phone]</td>
                        <td>$row[password]</td>
                        <td style='text-align: center; align-items: center;'>
                            <button class='btn btn-dark border border-ligh'> 
                                <a href='update.php?id=$row[id]'>Päivitä</a>
                                <svg xmlns='http://www.w3.org/2000/svg' width='1.2em' height='1.2em' viewBox='0 0 20 20'><path fill='currentColor' d='M5.7 9c.4-2 2.2-3.5 4.3-3.5c1.5 0 2.7.7 3.5 1.8l1.7-2C14 3.9 12.1 3 10 3C6.5 3 3.6 5.6 3.1 9H1l3.5 4L8 9zm9.8-2L12 11h2.3c-.5 2-2.2 3.5-4.3 3.5c-1.5 0-2.7-.7-3.5-1.8l-1.7 1.9C6 16.1 7.9 17 10 17c3.5 0 6.4-2.6 6.9-6H19z'/></svg>
                            </button>
                            <button class='btn btn-danger border border-ligh'>
                                <a href='delete.php?id=$row[id]'>Poista</a>
                                <svg xmlns='http://www.w3.org/2000/svg' width='1em' height='1em' viewBox='0 0 2048 2048'><path fill='currentColor' d='M1792 384h-128v1472q0 40-15 75t-41 61t-61 41t-75 15H448q-40 0-75-15t-61-41t-41-61t-15-75V384H128V256h512V128q0-27 10-50t27-40t41-28t50-10h384q27 0 50 10t40 27t28 41t10 50v128h512zM768 256h384V128H768zm768 128H384v1472q0 26 19 45t45 19h1024q26 0 45-19t19-45zM768 1664H640V640h128zm256 0H896V640h128zm256 0h-128V640h128z'/></svg>
                            </button>
                        </td>
                        </tr>
                    ";
                    $number++;
               }
            ?>
            </tbody>
        </table>
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



