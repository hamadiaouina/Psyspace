<?php
$host = "db";
$user = "root"; // Par défaut sur XAMPP
$pass = "";     // Par défaut sur XAMPP (vide)
$db   = "edoc"; // Le nom de ta base de données

$con = new mysqli($host, $user, $pass, $db);

// Vérifier la connexion
if ($con->connect_error) {
    die("La connexion a échoué : " . $con->connect_error);
}
?>