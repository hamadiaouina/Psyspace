<?php
session_start();
include "../connection.php";

$email    = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

$sql    = "SELECT * FROM admin WHERE admemail = '$email' LIMIT 1";
$result = $con->query($sql);

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    if (password_verify($password, $admin['admpassword'])) {
        // ON NE CONNECTE PAS ENCORE TOUT DE SUITE
        // On enregistre juste qu'il a passé la première étape
        $_SESSION['temp_admin_id'] = $admin['admid'];
        
        // On redirige vers la saisie du code PIN de sécurité
        header("Location: security_check.php");
        exit();
    } else {
        header("Location: ../login.php?error=wrongpw");
        exit();
    }
} else {
    header("Location: ../login.php?error=noaccount");
    exit();
}