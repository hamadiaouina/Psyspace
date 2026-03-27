<?php
// On récupère les variables d'Azure (ou du .env en local)
$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Erreur SQL = Exception PHP
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Récupère les données en tableau
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Sécurité max (vraies requêtes préparées)
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // En prod, on ne montre pas l'erreur brute pour la sécurité
     die("Erreur de connexion à la base de données.");
}