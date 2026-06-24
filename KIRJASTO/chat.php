<?php 
 session_start();
 include("connection.php"); 

if (isset($_POST['send'])) {
    $chat = $_POST['chat'];

    if (empty($chat)) {
        echo "";
    } else {
        $sql_chat = "INSERT INTO chat(chat) VALUES ('$chat')";
        $result_chat = mysqli_query($conn, $sql_chat);

        if ($result_chat) {
            header("location: chat.php");
        }
    }
}



function formatDate($date)
{
    return date('D g:i a', strtotime($date));
}




?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- bootstrap links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <title>CHAT - PAGE</title>
    <!-- JQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

</head>
<style>
    body {
        background: url("https://www.moviehdwallpapers.com/wp-content/uploads/2014/10/abstract-wallpapers-abstract-dark-background-wallpaper-37300.jpg");
        background-repeat: no-repeat;
        background-position: center;
        background-size: cover;
    }

    .chat-container {
        width: 50%;
        height: 80vh;
        background: url("https://cdn.photoroom.com/v1/assets-cached.jpg?path=backgrounds_v3/black/Photoroom_black_background_extremely_fine_texture_only_black_co_c756a0c0-4895-4275-845b-7a20f085e432.jpg");
        margin: 10px auto;
        background-repeat: no-repeat;
        background-position: center;
        background-size: cover;
        color: white;
        position: relative !important;
        border-radius: 5px;
        overflow: hidden;
        border: 1px solid Aqua;

    }

    .chat-container p {
        background-color: rgba(0, 0, 0, 0.7);
        padding: 20px 10px;
        font-weight: 500;
        text-align: center;
        color: Aqua;
    }

    .chat {
        width: 100%;
        height: 68vh;
        padding: 0px 10px;
        overflow: scroll;

    }

    .chat::-webkit-scrollbar {
        width: 0;
    }

    .chat-container form {
        width: 100%;
        display: flex;
        position: absolute;
        bottom: 0;
        padding: 10px 10px;
    }

    .chat-container form textarea {
        resize: none;
        width: 90%;
        background-color: rgba(0, 0, 0, 0.7);
        height: 35px;
        border-top-left-radius: 20px;
        border-bottom-left-radius: 20px;
        padding: 4px 20px;
        color: Aqua ;
        border: 1px solid;
        overflow: hidden;
        outline: none;
        position: relative;

    }

    .icon-send {
        position: absolute;
        right: 20%;
        top: 18px;
    }

    @media screen and (min-width: 1300px) {
        .icon-send {
            position: absolute;
            right: 14%;
            top: 18px;
        }
    }

    @media screen and (max-width: 1400px) {
        .icon-send {
            position: absolute;
            right: 20%;
            top: 18px;
        }

    }

    @media screen and (max-width: 900px) {
        .icon-send {
            position: absolute;
            right: 26%;
            top: 18px;
        }

    }

    .chat-container form input {
        padding: 4px 15px;
        background-color: rgba(0, 0, 0, 0.7);
        color: Aqua;
        border: 1px solid;
        border-top-right-radius: 20px;
        border-bottom-right-radius: 20px;
    }

    /* table */
    .chat-table {
        padding: 0px 10px;
        overflow: scroll;

    }

    .chat-table::-webkit-scrollbar {
        width: 0;
    }

    .message-text {
        float: right;
        background-color: #006880;
        color: Aqua;
        padding: 8px;
        padding-right: 15px;
        width: fit-content;
        font-size: 18px;
        border-top-right-radius: 25px;
        border-bottom-right-radius: 25px;
    }

    small {
        margin-bottom: 10px;
        color: #8000ff;
    }

    .tab {
        display: flex;
        flex-direction: column;

    }

    .tab p {
        float: right;
        text-align: right;
        width: fit-content;
    }
</style>

<body>
    <?php
    include "headerForDisplay.php";
    ?>

    <div class="chat-container">
        <p>Yksinkertainen chat-sovellus</p>
        <div class="chat">
            <div class="chat-table">
                <?php
                include("phpConfig/connection.php");
                $chat_show = "SELECT * FROM chat";
                $result_show = mysqli_query($conn, $chat_show);
                while ($info = mysqli_fetch_array($result_show)) {
                ?>
                    <div class="tab">
                        <span class="message-text"><?php echo $info['chat']; ?></span>
                        <small><?php echo formatDate($info['time']); ?></small>
                    </div>
                <?php
                }

                ?>
            </div>
        </div>

        <form action="chat.php" method="post">
            <textarea name="chat" id="myTextarea" placeholder="Viestiä tänne..." require></textarea>
            <svg class="icon-send" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 1792 1792">
                <path fill="Aqua" d="M1764 11q33 24 27 64l-256 1536q-5 29-32 45q-14 8-31 8q-11 0-24-5l-453-185l-242 295q-18 23-49 23q-13 0-22-4q-19-7-30.5-23.5T640 1728v-349l864-1059l-1069 925l-395-162q-37-14-40-55q-2-40 32-59L1696 9q15-9 32-9q20 0 36 11" />
            </svg>
            <input id="sendbutton" type="submit" name="send" value="Lähettä">
        </form>
    </div>




    <!-- javascript file -->

    <script>
        const textarea = document.getElementById("myTextarea");
        const chat = document.querySelector(".chat");

        textarea.addEventListener("input", () => {
            var maxLength = 44;
            if (textarea.value.length > maxLength) {
                textarea.value = textarea.value.slice(0, maxLength)
            }
        });

        chat.scrollTop = chat.scrollHeight - chat.clientHeight;
    </script>


    <!-- bootstrap links -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js" integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
    <!-- JQuery Links -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

</body>
</html>
