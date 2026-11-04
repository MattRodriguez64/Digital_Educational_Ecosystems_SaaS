<?php
// Page de connexion classique
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Auth - SaaS</title>
        <meta name="author" content="">
        <meta name="description" content="A short description...">
        <meta name="keywords" content="HTML, CSS, JavaScript">
        <link rel="stylesheet" href="./styles/styles.css">
    </head>
    <body>
        <div class="main-container">

            <div class="headbar">
                <div class="title-container">
                    <svg  xmlns="http://www.w3.org/2000/svg" width="48" height="48"
                    fill="#74D4FF" viewBox="0 0 24 24" >
                    <!--Boxicons v3.0.8 https://boxicons.com | License  https://docs.boxicons.com/free-->
                    <path d="m21.45 8.61-9-4.5a1 1 0 0 0-.89 0l-6 3-3 1.5-1 .5a1 1 0 0 0-.55.89v6h2v-5.38l2 1v3.83c0 2.06 3.12 4.56 7 4.56s7-2.49 7-4.56v-3.83l2.45-1.22c.34-.17.55-.52.55-.89s-.21-.72-.55-.89Zm-15 .29L12 6.12l6.76 3.38L12 12.88 5.24 9.5l1.21-.61ZM17 15.45c0 .76-2.11 2.56-5 2.56s-5-1.79-5-2.56v-2.83l4.55 2.28c.14.07.29.11.45.11s.31-.04.45-.11L17 12.62z"></path>
                    </svg>
                    <h1>Digital Educational System <span style="color:#74D4FF;">SaaS</span></h1>
                </div>
            </div>
            <div class="card-container">
                <div>
                    <h2>Panel de Connexion Admin</h2>
                    <p class="description">Accédez à plateforme d'administration du SaaS.</p>
                </div>
                <form action="./auth.php" method="POST">
                    <label for="identifiant">Identifiant</label>
                    <br>
                    <input type="text" id="identifiant" name="identifiant" required placeholder="Votre identifiant...">
                    <br>
                    <label for="password">Mot de Passe</label>
                    <br>
                    <input type="text" id="password" name="password" required placeholder="Votre mot de passe...">
                    <br>
                    <button type="submit">Envoyer</button>
                </form>
            </div>

        </div>


    </body>
</html>
