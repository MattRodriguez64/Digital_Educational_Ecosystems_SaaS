<?php
// import
require_once 'config.php';

// On vérifie si les informations de connexions sont rentrées
if (isset($_POST['identifiant']) && isset($_POST['password'])) {
    // On traite les potentielles espaces en trop
    $name = trim($_POST['identifiant']);
    $password = trim($_POST['password']);

    // Requete SQL classique pour voir si les informations correspondent à un utilisateur
    $request = $mysqli->prepare('SELECT * FROM auth_users WHERE username = ? AND password_hash = ? LIMIT 1');
    // On passe les paramètres de la requête 'ss' pour 'string string'
    $request->bind_param("ss", $name, $password);
    $request->execute();

    $result = $request->get_result();
    $row = $result->fetch_assoc();

    // Si on a un retour dans la requête
    if($result->num_rows > 0) {
        // Création des var de session + Redirection
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];

        header("Location: dashboard.php");
    } else {
        header("Location: index.php");
    }

    // Important d'exit() après une redirection
    exit();
}