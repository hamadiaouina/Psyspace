<?php
/**
 * PsySpace Web Application Firewall (WAF)
 * Protège contre les injections SQL, XSS et scans de vulnérabilités
 */

function psySpaceFirewall() {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // 1. Liste des motifs d'attaque classiques
    $forbidden_patterns = [
        '/UNION\s+SELECT/i',      // SQL Injection
        '/DROP\s+TABLE/i',        // SQL Injection
        '/<script/i',             // XSS
        '/alert\(/i',             // XSS
        '/ORD\s*\(/i',            // Blind SQLi
        '/\.\.\//',               // Path Traversal (accès aux dossiers parents)
        '/eval\(/i',              // Exécution de code
        '/base64_/i'              // Obfuscation de code malveillant
    ];

    // 2. Analyse de l'URL (GET) et des formulaires (POST)
    $input_to_check = $_SERVER['QUERY_STRING'] . encodePostData();

    foreach ($forbidden_patterns as $pattern) {
        if (preg_match($pattern, $input_to_check)) {
            // Log de l'incident dans un fichier local pour l'admin
            $log_entry = date('Y-m-d H:i:s') . " | Bloqué: $ip | Motif: $pattern | URL: " . $_SERVER['REQUEST_URI'] . "\n";
            file_put_contents(__DIR__ . '/waf_alerts.log', $log_entry, FILE_APPEND);

            // Bloquer l'accès
            http_response_code(403);
            exit("<h1 style='color:red;font-family:sans-serif;'>Accès Refusé par le Firewall PsySpace</h1>
                  <p>Une activité suspecte a été détectée depuis votre adresse IP ($ip).</p>");
        }
    }
}

// Fonction utilitaire pour lire le contenu des POST sans les perturber
function encodePostData() {
    return isset($_POST) ? json_encode($_POST) : '';
}

// Lancement automatique
psySpaceFirewall();