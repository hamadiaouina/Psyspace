<?php
// Valeurs par défaut (local Docker)
$host = getenv('DB_HOST') ?: "db";
$user = getenv('DB_USER') ?: "root";
$pass = getenv('DB_PASS') ?: "";
$db   = getenv('DB_NAME') ?: "edoc";

$con = new mysqli($host, $user, $pass, $db);

if ($con->connect_error) {
    die("La connexion a échoué : " . $con->connect_error);
}
?>