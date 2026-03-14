<?php
// Fichier temporaire — SUPPRIME-LE après utilisation !
$password = $_GET['p'] ?? 'changeme';
echo password_hash($password, PASSWORD_BCRYPT);
?>