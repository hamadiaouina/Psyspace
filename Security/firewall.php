<?php
/**
 * PsySpace Web Application Firewall (WAF)
 * Version optimisée pour PFE - Protection multicouche
 */

function psySpaceFirewall() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $uri = $_SERVER['REQUEST_URI'];
    
    // 1. Liste étendue des signatures d'attaques (Regex)
    $forbidden_patterns = [
        '/(union\s+select|insert\s+into|drop\s+table|delete\s+from|update\s+.*set)/i', // SQL Injection
        '/(<script|script>|alert\(|onerror|onclick|onload)/i',                         // XSS (Cross-Site Scripting)
        '/(\.\.\/|\.\.\\\\)/',                                                         // Path Traversal
        '/(eval\(|base64_decode|shell_exec|system\()|phpinfo/i',                       // RCE (Exécution de code)
        '/(ORD\s*\(|HEX\s*\(|SLEEP\s*\()/i',                                           // Blind SQLi / Time-based
        '/javascript:/i'                                                               // Protocoles suspects
    ];

    // 2. Récupération de toutes les entrées utilisateur
    $query_string = $_SERVER['QUERY_STRING'] ?? '';
    $post_data = file_get_contents('php://input'); // Capture POST brut (JSON, Form-data, etc.)
    $cookies = json_encode($_COOKIE);
    
    // On fusionne tout pour un scan complet
    $payload = $query_string . $post_data . $cookies;

    // 3. Analyse
    foreach ($forbidden_patterns as $pattern) {
        if (preg_match($pattern, $payload)) {
            
            // Log de l'incident (Assure-toi que le dossier a les droits d'écriture)
            $log_entry = "[" . date('Y-m-d H:i:s') . "] IP: $ip | Motif: $pattern | URI: $uri\n";
            file_put_contents(__DIR__ . '/waf_alerts.log', $log_entry, FILE_APPEND);

            // 4. Blocage avec une interface propre
            http_response_code(403);
            exit("
            <!DOCTYPE html>
            <html lang='fr'>
            <head>
                <meta charset='UTF-8'>
                <title>Accès Refusé - PsySpace Firewall</title>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                    .container { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); text-align: center; max-width: 500px; border: 1px solid #e2e8f0; }
                    h1 { color: #e11d48; margin-bottom: 16px; font-size: 24px; }
                    p { color: #64748b; line-height: 1.6; }
                    .ip { font-family: monospace; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; color: #0f172a; }
                    .footer { margin-top: 24px; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; pt: 16px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h1>🛡️ Blocage de Sécurité</h1>
                    <p>Une activité suspecte a été détectée. Par mesure de sécurité pour nos patients et praticiens, votre requête a été interrompue.</p>
                    <p>Votre adresse IP : <span class='ip'>$ip</span></p>
                    <div class='footer'>PsySpace AI Security System v1.0</div>
                </div>
            </body>
            </html>");
        }
    }
}

// Lancement de la protection
psySpaceFirewall();