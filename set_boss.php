<?php
require_once 'vendor/autoload.php';
$badge_secret = $_ENV['ADMIN_BADGE_TOKEN'];

// On installe le badge pour 10 ans sur ce navigateur
setcookie("psyspace_boss_key", $badge_secret, [
    'expires' => time() + (10 * 365 * 24 * 60 * 60),
    'path' => '/',
    'httponly' => true, // Personne ne peut le voler via script
    'secure' => true,   // Uniquement via HTTPS (Azure)
    'samesite' => 'Strict'
]);

echo "✅ Ton PC est maintenant reconnu. Supprime ce fichier du serveur !";