<?php
declare(strict_types=1);

/**
 * --- 0) PROTECTION CONTRE L'ACCÈS DIRECT ---
 * Empêche un visiteur de taper /connection.php dans l'URL
 */
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header("HTTP/1.1 403 Forbidden");
    exit("🛡️ Accès direct refusé.");
}

/**
 * --- 1) CONFIGURATION SÉCURISÉE DES SESSIONS (Avant tout session_start) ---
 * On configure la sécurité des cookies AVANT que n'importe quel fichier ne démarre la session
 */
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1'); 
ini_set('session.cookie_samesite', 'Lax');

// Détection HTTPS (marche aussi derrière le proxy de Clever Cloud / Azure)
$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if ($is_https) {
    ini_set('session.cookie_secure', '1');
}

/**
 * --- 2) SYNCHRONISATION DU FUSEAU HORAIRE ---
 * Crucial pour l'horodatage des logs de sécurité et des rendez-vous psy
 */
date_default_timezone_set('Europe/Paris');

/**
 * --- 3) CHARGEUR DE FICHIER .ENV (POUR LE LOCAL) ---
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
                // Enlève les guillemets éventuels autour de la valeur
                $value = trim($parts[1], '"\' ');
                if (getenv($key) === false) {
                    putenv("$key=$value");
                }
            }
        }
    }
}
loadEnv(__DIR__ . '/.env'); 

/**
 * --- 4) CONNEXION À LA BASE DE DONNÉES ---
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = getenv('MYSQL_ADDON_HOST') ?: (getenv('DB_HOST') ?: 'localhost'); 
$DB_PORT = getenv('MYSQL_ADDON_PORT') ?: (getenv('DB_PORT') ?: '3306');
$DB_NAME = getenv('MYSQL_ADDON_DB') ?: (getenv('DB_NAME') ?: 'psyspace');
$DB_USER = getenv('MYSQL_ADDON_USER') ?: (getenv('DB_USER') ?: 'root');
$DB_PASS = getenv('MYSQL_ADDON_PASSWORD') ?: (getenv('DB_PASS') ?: '');

try {
    $con = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, (int)$DB_PORT);
    
    // Sécurité : Forcer l'encodage (prévient les bypass d'injection SQL)
    $con->set_charset('utf8mb4');
    
    // Aligner l'heure de la DB avec l'heure de PHP
    $con->query("SET time_zone = '+01:00'"); // +01:00 ou +02:00 selon heure d'hiver/été pour Paris
    
    // Compatibilité avec tes anciens scripts
    $database = $con; 
    $conn = $con; 

    // 🧹 SÉCURITÉ : Nettoyage de la mémoire
    // On détruit les variables contenant les mots de passe pour qu'elles ne fuitent jamais
    unset($DB_HOST, $DB_PORT, $DB_NAME, $DB_USER, $DB_PASS);

} catch (mysqli_sql_exception $e) {
    // Écrit la vraie erreur (avec le mot de passe/hôte) DANS LES LOGS SERVEUR, jamais à l'écran
    error_log("Erreur critique DB PsySpace : " . $e->getMessage()); 
    
    // Ce que le visiteur voit
    echo "<div style='font-family:sans-serif; max-width: 600px; margin: 50px auto; padding:20px; border:1px solid #fca5a5; background:#fef2f2; border-radius:10px; text-align: center; color: #991b1b;'>";
    echo "<h2>🛡️ Maintenance de Sécurité</h2>";
    echo "<p>La connexion sécurisée à la base de données est temporairement interrompue. Nos équipes sont sur le coup.</p>";
    
    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
        echo "<hr style='border-color: #fca5a5; margin: 15px 0;'>";
        echo "<small><i>Mode Local Actif - Regardez vos logs PHP pour voir l'erreur exacte.</i></small>";
    }
    echo "</div>";
    exit();
}
?>