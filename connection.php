<?php
declare(strict_types=1);

/**
 * --- 1) CONFIGURATION SÉCURISÉE DES SESSIONS ---
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1'); 
    ini_set('session.use_only_cookies', '1'); 
    ini_set('session.cookie_samesite', 'Lax');

    if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
        ini_set('session.cookie_secure', '1');
    }
    
    session_start();
}

/**
 * --- 2) CHARGEUR DE FICHIER .ENV (POUR LE LOCAL) ---
 */
if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                if (strpos($value, '#') !== false) { 
                    $value = trim(explode('#', $value)[0]); 
                }
                $value = trim($value, '"\'');
                // On n'écrase pas une variable déjà définie par Azure
                if (getenv($key) === false) {
                    putenv("$key=$value");
                }
            }
        }
    }
}

// Charge le .env s'il existe (utile en local, ignoré sur Azure car .env n'est pas sur Git)
loadEnv(__DIR__ . '/.env'); 

/**
 * --- 3) CONNEXION À LA BASE DE DONNÉES ---
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Récupération des variables (Azure en priorité, sinon Docker local)
$DB_HOST = getenv('DB_HOST') ?: 'db'; 
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_NAME') ?: 'edoc';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

try {
    // Connexion MySQLi
    $con = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);
    $con->set_charset('utf8mb4');
    
    // On garde les deux noms pour éviter de casser le reste de ton code
    $database = $con; 
    $conn = $con; 

} catch (mysqli_sql_exception $e) {
    // Message d'erreur propre pour le jury
    echo "<div style='font-family:sans-serif; padding:20px; border:1px solid #cc0000; background:#fff5f5;'>";
    echo "<h3 style='color:#cc0000;'>Erreur de connexion à la base de données</h3>";
    echo "<p>Vérifiez les variables d'environnement sur le portail Azure.</p>";
    if (getenv('DB_HOST')) {
        echo "<strong>Hôte détecté :</strong> " . htmlspecialchars($DB_HOST);
    }
    echo "</div>";
    exit();
}