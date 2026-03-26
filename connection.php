<?php
declare(strict_types=1);

// --- CHARGEUR DE .ENV SÉCURISÉ ---
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                if (strpos($value, '#') !== false) { $value = trim(explode('#', $value)[0]); }
                $value = trim($value, '"\'');
                putenv("$key=$value");
            }
        }
    }
}

loadEnv(__DIR__ . '/.env'); 

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Récupération des paramètres
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'edoc';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

try {
    // On crée la connexion
    $con = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);
    $con->set_charset('utf8mb4');
    
    // Alias pour éviter l'erreur "Undefined variable $conn" ou "$con"
    $conn = $con; 
} catch (mysqli_sql_exception $e) {
    echo "<h3>Erreur de connexion détaillée :</h3>";
    echo "Message : " . $e->getMessage() . "<br>";
    echo "Hôte : " . $DB_HOST . "<br>";
    exit();
}