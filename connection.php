<?php
declare(strict_types=1);

/**
 * --- 1) CONFIGURATION SÉCURISÉE DES SESSIONS ---
 * On empêche le vol de session par JavaScript (HttpOnly) et on force le HTTPS
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1'); 
    ini_set('session.use_only_cookies', '1'); 
    ini_set('session.cookie_samesite', 'Lax');

    // Détection HTTPS (marche aussi derrière le proxy de Clever Cloud)
    $is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if ($is_https) {
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
                if (getenv($key) === false) {
                    putenv("$key=$value");
                }
            }
        }
    }
}

loadEnv(__DIR__ . '/.env'); 

/**
 * --- 3) CONNEXION À LA BASE DE DONNÉES (AUTO-DÉTECTION) ---
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Priorité 1 : Clever Cloud | Priorité 2 : Azure/Docker | Priorité 3 : Valeur par défaut
$DB_HOST = getenv('MYSQL_ADDON_HOST') ?: (getenv('DB_HOST') ?: 'localhost'); 
$DB_PORT = getenv('MYSQL_ADDON_PORT') ?: (getenv('DB_PORT') ?: '3306');
$DB_NAME = getenv('MYSQL_ADDON_DB') ?: (getenv('DB_NAME') ?: 'psyspace');
$DB_USER = getenv('MYSQL_ADDON_USER') ?: (getenv('DB_USER') ?: 'root');
$DB_PASS = getenv('MYSQL_ADDON_PASSWORD') ?: (getenv('DB_PASS') ?: '');

try {
    // Connexion MySQLi avec port
    $con = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);
    $con->set_charset('utf8mb4');
    
    // Compatibilité avec tes anciens scripts
    $database = $con; 
    $conn = $con; 

} catch (mysqli_sql_exception $e) {
    // Message d'erreur pro mais discret (Protection contre l'Information Leakage)
    error_log("Erreur DB : " . $e->getMessage()); // Écrit l'erreur dans les logs Clever Cloud
    
    echo "<div style='font-family:sans-serif; padding:20px; border:1px solid #cc0000; background:#fff5f5; border-radius:10px;'>";
    echo "<h3 style='color:#cc0000;'>🛡️ Service Temporairement Indisponible</h3>";
    echo "<p>Une erreur de connexion sécurisée a été détectée. L'équipe technique a été alertée.</p>";
    
    // On affiche l'hôte uniquement si on est en local pour le débug
    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1') {
        echo "<small>Debug Hôte : " . htmlspecialchars($DB_HOST) . "</small>";
    }
    echo "</div>";
    exit();
}