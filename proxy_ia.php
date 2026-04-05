<?php
declare(strict_types=1);

// --- 1. SÉCURITÉ DES SESSIONS & HEADERS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

// --- 2. FONCTIONS UTILITAIRES ---
function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function readJsonBody(int $maxBytes = 200_000): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        respond(400, ['error' => 'empty_body']);
    }
    if (strlen($raw) > $maxBytes) {
        respond(413, ['error' => 'payload_too_large']);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        respond(400, ['error' => 'invalid_json']);
    }
    return $data;
}

function loadDotEnv(string $path): void {
    if (!is_readable($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;

        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim(trim($v), "\"'");

        if ($k !== '' && getenv($k) === false) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

// --- 3. AUTHENTIFICATION & ANTI-HIJACKING ---
if (!isset($_SESSION['id'])) {
    respond(401, ['error' => 'unauthorized']);
}

if (isset($_SESSION['user_ip']) && isset($_SESSION['user_agent'])) {
    if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR'] || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        respond(401, ['error' => 'session_hijacked']);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'method_not_allowed']);
}

// --- 4. VÉRIFICATION CSRF ---
$body = readJsonBody();
$csrfClient = (string)($body['csrf_token'] ?? '');
$csrfServer = (string)($_SESSION['csrf_token'] ?? '');

if ($csrfClient === '' || $csrfServer === '' || !hash_equals($csrfServer, $csrfClient)) {
    respond(403, ['error' => 'invalid_csrf']);
}

// --- 5. RATE LIMITING (Ajusté pour l'Auto-IA) ---
$doctorId    = (int)$_SESSION['id'];
$rateKey     = 'rate_ia_' . $doctorId;
// 300 appels par heure permet de faire l'Auto-IA en direct sans bloquer le médecin
$rateLimit   = 300; 
$rateWindow  = 3600;
$now         = time();

if (!isset($_SESSION[$rateKey]) || !is_array($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = ['count' => 0, 'reset_at' => $now + $rateWindow];
}
if ($now > (int)($_SESSION[$rateKey]['reset_at'] ?? 0)) {
    $_SESSION[$rateKey] = ['count' => 0, 'reset_at' => $now + $rateWindow];
}
if ((int)($_SESSION[$rateKey]['count'] ?? 0) >= $rateLimit) {
    $retry = max(1, (int)$_SESSION[$rateKey]['reset_at'] - $now);
    header('Retry-After: ' . $retry);
    respond(429, ['error' => 'rate_limit', 'detail' => "Limite de requêtes IA atteinte pour cette heure."]);
}
$_SESSION[$rateKey]['count'] = (int)($_SESSION[$rateKey]['count'] ?? 0) + 1;

// --- 6. VALIDATION DU PROMPT ---
$prompt = trim((string)($body['prompt'] ?? ''));
if ($prompt === '') {
    respond(400, ['error' => 'invalid_prompt']);
}
if (mb_strlen($prompt, 'UTF-8') > 30_000) {
    respond(400, ['error' => 'prompt_too_long']);
}

// --- 7. CHARGEMENT DE LA CLÉ API (GROQ) ---
$apiKey = '';
$secretsPath = dirname(__DIR__) . '/config/secrets.php';
if (is_readable($secretsPath)) {
    $secrets = require $secretsPath;
    if (is_array($secrets)) $apiKey = (string)($secrets['groq_key'] ?? '');
}
if ($apiKey === '') {
    loadDotEnv(__DIR__ . '/.env');
    $apiKey = (string)(getenv('GROQ_API_KEY') ?: '');
}
if ($apiKey === '') {
    error_log('[PsySpace Security] GROQ_API_KEY introuvable.');
    respond(500, ['error' => 'server_config_error']);
}

// --- 8. CONFIGURATION DU MODÈLE (JSON MODE FORCÉ) ---
$maxTokensClient = max(1, min(3000, (int)($body['max_tokens'] ?? 1200)));

$payloadArr = [
    'model'       => 'llama-3.3-70b-versatile',
    'temperature' => 0.1,
    'max_tokens'  => $maxTokensClient,
    'response_format' => ['type' => 'json_object'], // 🔴 Force l'API à renvoyer un JSON strict
    'messages'    => [
        [
            'role'    => 'system',
            'content' => 'Tu es PsySpace AI, un assistant clinique spécialisé en psychologie. Tu dois IMPÉRATIVEMENT répondre au format JSON.'
        ],
        [
            'role'    => 'user',
            'content' => $prompt
        ],
    ],
];

$payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

// --- 9. APPEL API GROQ VIA CURL ---
$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_log('[PsySpace cURL Error] ' . $curlErr);
    respond(502, ['error' => 'upstream_timeout']);
}

// --- 10. RETOUR AU FRONTEND ---
http_response_code($httpCode);
echo $response;
exit();