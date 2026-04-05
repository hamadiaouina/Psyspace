<?php
/**
 * PsySpace Web Application Firewall (WAF)
 * Protège contre les attaques SQLi, XSS, RCE, Path Traversal
 */

// 1. PROTECTION ACCÈS DIRECT
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header("HTTP/1.1 403 Forbidden");
    exit("🛡️ Accès direct refusé.");
}

function psySpaceFirewall() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Inconnue';
    $uri = $_SERVER['REQUEST_URI'];
    
    // 2. Signatures d'attaques affinées (Moins de faux positifs)
    $forbidden_patterns = [
        '/(?:union\s+select|drop\s+table|--\s*$|#\s*$)/i', // SQL Injection (Grave)
        '/(?:<script.*?>|<\/script>|javascript:|onerror=)/i', // XSS (Vol de session)
        '/(?:\.\.\/|\.\.\\\\|\/etc\/passwd|\/windows\/win.ini)/i', // Path Traversal (Lecture de fichiers serveur)
        '/(?:base64_decode\s*\(|shell_exec\s*\(|system\s*\(|phpinfo\s*\()/i' // RCE (Exécution de code)
    ];

    // 3. Fonction récursive pour scanner tous les tableaux (GET, POST, COOKIE) proprement
    $scan_data = function($data) use (&$scan_data, $forbidden_patterns, $ip, $uri) {
        if (is_array($data)) {
            foreach ($data as $value) {
                $scan_data($value);
            }
        } elseif (is_string($data)) {
            foreach ($forbidden_patterns as $pattern) {
                if (preg_match($pattern, $data)) {
                    
                    // 4. LOG SÉCURISÉ (Nom caché avec .ht pour empêcher la lecture web)
                    $log_file = __DIR__ . '/.ht_waf_alerts.log';
                    $log_entry = "[" . date('Y-m-d H:i:s') . "] IP: $ip | URI: $uri | Payload suspect détecté.\n";
                    @file_put_contents($log_file, $log_entry, FILE_APPEND);

                    // 5. BLOCAGE VISUEL
                    http_response_code(403);
                    echo "<!DOCTYPE html>
                    <html lang='fr'>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <title>Accès Refusé - PsySpace Security</title>
                        <style>
                            body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                            .container { background: #1e293b; padding: 40px; border-radius: 16px; text-align: center; max-width: 500px; border: 1px solid #334155; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
                            h1 { color: #ef4444; margin-bottom: 16px; font-size: 24px; display: flex; align-items: center; justify-content: center; gap: 10px; }
                            p { color: #94a3b8; line-height: 1.6; margin-bottom: 20px; }
                            .ip-box { background: #0f172a; padding: 10px; border-radius: 8px; border: 1px dashed #ef4444; font-family: monospace; color: #f87171; letter-spacing: 1px; }
                            .footer { margin-top: 24px; font-size: 12px; color: #475569; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <h1>
                                <svg width='28' height='28' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'></path></svg>
                                Blocage de Sécurité
                            </h1>
                            <p>Notre système de protection AI a détecté une requête anormale. Par mesure de précaution, votre accès a été temporairement suspendu.</p>
                            <div class='ip-box'>IP Enregistrée : $ip</div>
                            <div class='footer'>PsySpace Web Application Firewall v2.0</div>
                        </div>
                    </body>
                    </html>";
                    exit();
                }
            }
        }
    };

    // On scanne les 3 portes d'entrée principales du site
    if (!empty($_GET)) $scan_data($_GET);
    if (!empty($_POST)) $scan_data($_POST);
    if (!empty($_COOKIE)) $scan_data($_COOKIE);
}

// Lancement de la protection
psySpaceFirewall();