<?php
session_start();
include("connection.php");

if (isset($_POST['submit'])) {
    $search = $_POST['search'];

    $sql_search = "SELECT * FROM `customers` WHERE id LIKE '%$search%' or name LIKE '%$search%'";
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
        margin: auto;
        z-index: 9999;
    }

    table {
        background-color: aqua;
        color: white;
        border: 1px solid white;
        z-index: 99;
        box-shadow: 4px 4px 10px rgb(84, 84, 84), -4px -4px 10px rgb(84, 84, 84);
    }

    table tr {
        color: darkblue;
        border: 1px solid aqua;
    }

    a {
        text-decoration: none;
        color: darkblue;
    }

    .user-added,
    .user-update,
    .update {
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
        width: 50%;
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

    .hae {
        font-weight: bold;
        padding-bottom: 2px;
    }

    form {
        position: relative;
    }

    form .icon {
        position: absolute;
        top: 10px;
        right: 20%;
        cursor: pointer;
    }

    .svg-a {
        margin-bottom: 10px;
    }
</style>

<body>
    <?php

    include "header.php";
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
                    <th scope="col">Nimi</th>
                    <th scope="col">Sähköposti</th>
                    <th scope="col">Puhelin Numero</th>
                    <th scope="col">Salasana</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT * FROM `customers`";
                $result = mysqli_query($conn, $sql);
                if (!$result) {
                    echo "Error" . mysqli_error($conn);
                }
                $number = 1;
                while ($row = mysqli_fetch_assoc($result)) {
                    echo "
                        <tr>
                        <th>$row[id]</th>
                        <td>$row[name]</td>
                        <td>$row[email]</td>
                        <td>$row[phone]</td>
                        <td>$row[password]</td>
                        </tr>
                    ";
                    $number++;
                }
                ?>
            </tbody>
        </table>
    </div>




    <!-- javascript file -->
    <script>
        const adminIcon = document.querySelector('.admin-icon');
        const adminDisplay = document.querySelector('.admin');

        adminIcon.addEventListener("click", () => {
            adminDisplay.classList.toggle("active");

        })
    </script>

    <!-- bootstrap links -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js" integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
    <!-- JQuery Links -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

</body>
</html>
