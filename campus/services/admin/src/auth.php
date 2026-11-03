<?php

require_once 'config.php';

if (isset($_POST['identifiant']) && isset($_POST['password'])) {
    $name = trim($_POST['identifiant']);
    $password = trim($_POST['password']);


    $request = $mysqli->prepare('SELECT * FROM auth_users WHERE username = ? AND password_hash = ? LIMIT 1');
    $request->bind_param("ss", $name, $password);
    $request->execute();

    $result = $request->get_result();
    $row = $result->fetch_assoc();

    if($result->num_rows > 0) {
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];

        header("Location: dashboard.php");
    } else {
        header("Location: index.php");
    }

    exit();
}