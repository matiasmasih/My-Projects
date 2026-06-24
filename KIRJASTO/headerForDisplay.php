<?php

 include("connection.php");
?>

<!-- HTML CODE -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!--  <link rel="stylesheet" href="style.css?=">  -->
    <!-- bootstrap links -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
    <title>PHP HOME PAGE</title>
    <!-- JQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

</head>
<style>
    header {
        top: 0;
        margin-bottom: 80px;
        width: 100%;
        height: 80px;
        display: flex;
        justify-content: space-between;
        padding: 20px 20px;
        align-items: center;
        text-align: center;
        background-color: black;
    }
    nav {

        color: white;
        display: flex;
        text-align: center;
        justify-content: center;
    }

    nav ul {
        display: flex;
        align-items: center;
        text-align: center;
    }
    nav ul li {
        list-style-type: none;
        margin: 10px;
        font-weight: 500;
        cursor: pointer;
    }
    nav ul li a {
        text-decoration: none;
        color: Aqua;
    }
    .search-input {
        position: relative;
    }
    .search-input input {
        padding: 5px 10px;
        width: 300px;
        border: 0.5px solid Aqua;
        background: transparent;
        outline: none;
        border-radius: 4px;
        color: Aqua;
        background-color: transparent;
    }
    .search-input .icon {
        position: absolute;
        right: 60px;
        top: 8px;
        cursor: pointer;
    }
    .hae {
        padding: 5px 10px;
        border: 1px solid Aqua;
        border-radius: 4px;
        background: transparent;
        color: Aqua;

    }
    #close {
        cursor: pointer;
    }
    #menu {
        cursor: pointer;
    }

.dropdown-menu {
    display: none;
    position: absolute;
    background-color: #0a0a0a;
    min-width: 160px;
    box-shadow: 0 8px 16px 0 rgba(0,0,0,0.2);
    z-index: 1;
    border-redius: 5px;
}

.dropdown:hover .dropdown-menu {
    display: block;
}

.dropdown-menu li {
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    color: #333;
}

.dropdown-menu li:hover {
    background-color: black;
}

    /* admin */
    .admin {
        color: white;
        width: 300xp;
        height: 100vh;
        position: absolute;
        right: 0;
        top:0;
        background-color: rgba(0, 0, 0, 0.9);
        padding: 20px 20px;
        display: none;
        transition: 0.5s ease-in-out;
    }
    .admin-a {
        margin: 20px;
        margin-top: 100px;
    }
    .admin-a svg {
        margin-right: 10px;
    }
    .admin-a a {
        color: Aqua;
        text-decoration: none;
    }
   .svg-a {
        margin-bottom: 10px;
    }

</style>
<body>
   <header>
    <nav>
        <div style="margin-top: 5px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" viewBox="0 0 100 100"><path fill="Aqua" d="M50 0C27.923 0 10 17.745 10 39.604c0 21.092 16.69 38.351 37.697 39.535V85H40a2.5 2.5 0 0 0-2.5 2.5V90H0v5h37.5v2.5A2.5 2.5 0 0 0 40 100h20a2.5 2.5 0 0 0 2.5-2.5V95H100v-5H62.5v-2.5A2.5 2.5 0 0 0 60 85h-7.303v-5.893c20.78-1.374 37.235-18.48 37.297-39.386a1.2 1.188 0 0 0 .006-.117a1.2 1.188 0 0 0-.004-.106C89.938 17.688 72.041 0 50 0m0 2.377c1.753 0 3.475.128 5.164.357c-.071.095.125.31.813.7l.818.275c1.023-.126.588-.473-.088-.73a37.631 37.631 0 0 1 7.225 2.044c-.647.145-1.631.224-2.604.186c.023 1.91.88 7.012 3.817 6.086c-.522.114 1.658 2.25.53 2.607c-.598 3.992-3.868-2.663-5.527-2.576c-2.479.414.47 1.628-.423 2.223c-2.347 1.995 2.03 4.088 1.677.734c.288 2.704 1.212 2.621 3.426 2.48c-5.057.846 2.816 2.787.26 4.407c-2.534-.231-3.954.704-2.522 3.16c-.672 2.274 1.597 2.373 2.375 3.158l.852-.076l.549-.072c-.021-.003-.044.003-.065 0c3.159-.962 1.525-7.22 4.891-6.81c-.705 2.42 3.184 4.724 1.098 1.214c-1.423-1.78 4.614 1.28 1.955 2.154c2.585 3.38.763-3.157 2.69-1.38c1.251 2.159 2.717.55 3.058.47c.828-.229 2.08.132 2.408.325c3.172 2.999-3.005 1.755-3.096 4.048c.336 1.468-3.39-.393-3.949-.793c-1.635-4.361-5.358-.011-8.096.69c-.093.072-2.342 1.81-2.607 2.04c-.642 3.822-3.745 6.146-4.897 10.013c-.23 2.234.517 5.322.204 7.732c.73 3.091 3.683 4.862 6.257 6.375c1.938 1.235 3.958-1.133 6.043-1.16c1.825-2.656 4.744-.708 6.393-.904c-1.9 2.962 1.414 5.253.476 8.457c-1.47 1.78-1.884 4.146-2.671 6.271c-6.79 6.646-16.122 10.75-26.434 10.75a37.846 37.846 0 0 1-14.555-2.89c.85-.764 1.345-1.903 2.487-2.23c5.185 1.242 2.926-5.11 6.447-6.546c2.359-4.28-5.172-4.963-7.86-6.521c-4.545-.68-4.118-6.158-8.246-7.342c-3.38-.534-2.154-5.358-4.632-5.1c-2.14.394-2.988-2.52-4.18-2.312c-.288.3-1.493-1.956-2.79.53c-3.66-.801-1.098-4.963-2.644-7.292c-.64-1.067 1.828-6.538-.818-3.36c-.104-.181-.174-.43-.217-.72a36.677 36.677 0 0 1 1.45-5.557c.39-.429.838-.641 1.331-.48c1.956-.44 1.306 2.602 1.467 4.486c1.475.626.785-5.525 3.85-5.668c2.085-2.325 4.504-4.303 7.039-5.904c.99.698 3.116.613 4.785-.938c-1.859-.37-1.993-1.773.492-2.326c3.954-1.682-1.784 1.842 1.444 2.352c3.503 1.022.532-3.017 2.193-4.596c-1.739-1.172 1.053-5.73-1.988-3.705c1.052-.745-.585-2.142.355-2.688c.389 1.077 2.344 2.56 3.04.075c1.954.33 1.678-2.473 2.734-3.98c4.956-1.73.854 3.025.668 5.386c1.594 4.602 4.285-1.774 7.12-1.932c3.446.116 3.052-3.146 2.999-4.892a22.587 22.587 0 0 0-2.25-.3c.093 0 .185-.005.279-.005M36.703 6.311c.634-.077-.366 1.286-1.734 1.511c-1.824 1.506-.27-.53.508-.861c.621-.44 1.015-.625 1.226-.65m27.977.996a.774.774 0 0 1 .271.054c.587.964-.652-.024-.271-.054m-32.203.453c1.403.1-.092 3.342-1.65 3.502c-.836-.077-1.406 1.226-2.052 1.652c-.063.286.052-4.463 2.233-4.646c.667-.388 1.145-.531 1.469-.508m20.191.086c-.715.03-1.247.33-.955 1.1c.418.398 1.026.495 1.58.497c3.416-.42.948-1.662-.625-1.597m12.1.619c.151.013.507.147 1.166.476c1.331 1.663-1.822-.535-1.166-.476m-6.579 1.22c-.043.02-.079.1-.091.274c.626.696.28-.355.091-.273m2.418.577c-.05-.014-.055.095.047.443c.673.758.106-.402-.047-.443m6.85.008c.55-.003 2.09.946 1.78 1.98c-1.487 1.19-.8.553-1.09-.766c-.996-.872-1.02-1.213-.69-1.214m-43.36 4.81c.11.013.265.141.477.46c-.531 1.613-.947-.515-.476-.46m7.428.936c.1 0 .26.08.498.302c-.519.388-.794-.306-.498-.302m45.512.285c.141-.016.42.182.885.713c.555-.904 3.42 1.192.933 1.57c-.468 1.852-2.43-2.213-1.818-2.283m-52.883.744c.052-.012.107.02.162.115c-.746 2.176-.526-.035-.162-.115m48.389 2.236a.191.191 0 0 1 .078.002c.448.074 2.212 1.068 2.916 1.76c.431.955-1.413-.747-1.787-.637c-1.044-.771-1.332-1.087-1.207-1.125m-49.309.018c.092.018.164.112.184.326c-1.35.929-.579-.405-.184-.326m54.702 1.77c.069-.03.268.08.677.462c.073.968-.885-.375-.677-.463m-8.22 1.833c-.165.026-.42.312-.767 1.07c.968 1.12 1.265-1.147.768-1.07m10.19.8c-.18.022-.37.223-.496.741c1.505.71 1.038-.812.496-.742m-29.23 2.2c-.195-.017-.468.065-.799.336c1.275.653 1.384-.284.799-.336m1.763.596c-.079-.03-.238.024-.507.248c.277.688.746-.159.507-.248m6.352 4.022c-.077.018-.179.102-.309.294c.492.805.641-.374.309-.294m-40.133.34c-.073 0-.118.18-.074.695c.78.92.293-.698.074-.696m-.435 1.306c-.074-.012-.183.202-.328.842c.44 1.268.547-.805.328-.842m-2.235.668c-1.553-.159 1.306 2.557 1.418 3.754c2.876.768-.402 1.637 2.03 2.607c3.662 1.305 1.399-1.61-.155-2.224c.61-.969-1.544-1.682-2.336-3.88c-.423-.152-.735-.234-.957-.257m45.364.607c-.166 0-.348.3-.424 1.198c1.073.255.787-1.198.424-1.198m-42.34.647a.079.079 0 0 0-.063.008c-.084.053-.088.31.145.873l.181.119c.15-.526-.104-.96-.263-1m39.603.066c-.47.027-.774.238-.44.79l.141.064h.8c2.373-.078.533-.913-.5-.854m-41.83 2.887c-.21-.044-.24.197.264 1.04c1.539.529.2-.944-.264-1.04m-4.297.12c.534 2.287.875 4.947 1.866 6.958c.654 2.462 1.135-.637 1.298 1.303c.555 3.664-1.7 7.44 1.077 10.953c1.32 3.543 4.219 6.026 6.248 9.111c-6.548-6.694-10.58-15.816-10.58-25.88c0-.823.035-1.635.088-2.444m9.573 2.112c-.193.024-.362.226-.409.75c1.763.714.985-.821.409-.75m2.808 2.887c-.078-.03-.158.315-.21 1.358l.03.574c.625 1.65.414-1.841.18-1.932m28.06 2.324c-.199-.021-.2.132.352.653c1.478.29.087-.606-.351-.653m2.15.489c-.093.001-.213.067-.364.238c.613.954.76-.245.363-.238m-.356 1.043c-.141.022-.34.113-.608.314c.735.785 1.217-.412.608-.314m-1.034.152c-.075-.008-.188.101-.343.432c.44.67.568-.408.343-.432m23.377 7.377c-.075-.008-.188.101-.343.432c.44.67.568-.409.343-.432M42.5 90h15v5h-15z" color="currentColor"/></svg>
        </div>
            <ul>
                <li><a href="display.php">Etusivu</a></li>
                <li><a href="user.php">Lisää Käyttäjä</a></li>
                <li><a href="kirja.php">Lisää Kirjaa</a></li>
                <li><a href="devices.php">Laite</a></li>
                <li><a href="chat.php">Chat</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown">Lainaukset</a>
                    <ul class="dropdown-menu">
                        <li><a href="borrow.php">Laina</a></li>
                        <li><a href="return.php">Palautus</a></li>
                    </ul>
                </li>
            </ul>
        </nav>
    <div>
        <svg id="menu" xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24"><path fill="none" stroke="Aqua" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h16M4 6h16M4 18h16"/></svg>
    </div>
   </header>

   <section>
    <div>
        <div class="admin">
            <div>
            <svg id="close" xmlns="http://www.w3.org/2000/svg" width="30px" height="30px" viewBox="0 0 24 24"><path fill="none" stroke="Aqua" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m7 7l10 10M7 17L17 7"/></svg>
            </div>
            <div class="admin-a">
                <div class="svg-a">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2"><path stroke-dasharray="32" stroke-dashoffset="32" d="M13 4L20 4C20.5523 4 21 4.44772 21 5V19C21 19.5523 20.5523 20 20 20H13"><animate fill="freeze" attributeName="stroke-dashoffset" dur="0.4s" values="32;0"/></path><path stroke-dasharray="12" stroke-dashoffset="12" d="M3 12h11.5" opacity="0"><set attributeName="opacity" begin="0.5s" to="1"/><animate fill="freeze" attributeName="stroke-dashoffset" begin="0.5s" dur="0.2s" values="12;0"/></path><path stroke-dasharray="6" stroke-dashoffset="6" d="M14.5 12l-3.5 -3.5M14.5 12l-3.5 3.5" opacity="0"><set attributeName="opacity" begin="0.7s" to="1"/><animate fill="freeze" attributeName="stroke-dashoffset" begin="0.7s" dur="0.2s" values="6;0"/></path></g></svg>
                    <a href="login1.php">Kirjaudu sisään</a>
                </div>
                <div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24"><path fill="currentColor" d="M15 14c-2.67 0-8 1.33-8 4v2h16v-2c0-2.67-5.33-4-8-4m-9-4V7H4v3H1v2h3v3h2v-3h3v-2m6 2a4 4 0 0 0 4-4a4 4 0 0 0-4-4a4 4 0 0 0-4 4a4 4 0 0 0 4 4"/></svg>
                    <a href="register.php">Rekisteröidy</a>
                </div>
            </div>
        </div>
    </div>
   </section>


   <!-- javascript file -->
    <script>
        const menu = document.getElementById("menu");
        const close = document.getElementById("close");
        const admin = document.querySelector(".admin");

        menu.addEventListener("click", () => {
            admin.style.display = "block";
            admin.style.transition = "0.5s ease-in-out";
        });
        close.addEventListener("click", () => {
            admin.style.display = "none";
        })

    </script>

<!-- bootstrap links -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js" integrity="sha384-7+zCNj/IqJ95wo16oMtfsKbZ9ccEh31eOz1HGyDuCQ6wgnyJNSYdrPa03rtR1zdB" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js" integrity="sha384-QJHtvGhmr9XOIpI6YVutG+2QOK9T+ZnN4kzFN1RtK3zEFEIsxhlmWl5/YESvpZ13" crossorigin="anonymous"></script>
<!-- JQuery Links -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

</body>
</html>


