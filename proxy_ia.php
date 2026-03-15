<?php
declare(strict_types=1);

// ── Sécurité / headers ────────────────────────────────────────────────────────
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

// ── Helpers ──────────────────��────────────────────────────────────────────────
function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Lecture JSON robuste:
 * - Ne dépend pas de CONTENT_LENGTH (parfois absent)
 * - Limite la taille du body
 */
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

/**
 * Charge un .env très simple (KEY=VALUE) sans dépendances.
 * - Ignore les lignes vides et les commentaires "#"
 * - Ne gère pas les cas complexes (multi-lignes etc.)
 */
function loadDotEnv(string $path): void {
    if (!is_readable($path)) return;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;

        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $v = trim($v, "\"'"); // enlève quotes simples/doubles

        // Ne pas écraser une variable déjà définie côté serveur
        if ($k !== '' && getenv($k) === false) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

// ── 1) Authentification ───────────────────────────────────────────────────────
if (!isset($_SESSION['id'])) {
    respond(401, ['error' => 'unauthorized']);
}

// ── 2) Méthode ────────────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['error' => 'method_not_allowed']);
}

// ── 3) (Optionnel mais recommandé) Vérifier Content-Type JSON ─────────────────
// Si jamais ton front n'envoie pas application/json, tu peux commenter ce bloc.
$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
if ($contentType !== '' && !str_contains($contentType, 'application/json')) {
    respond(415, ['error' => 'unsupported_media_type']);
}

// ── 4) Body JSON + CSRF ───────────────────────────────────────────────────────
$body = readJsonBody();

$csrfClient = (string)($body['csrf_token'] ?? '');
$csrfServer = (string)($_SESSION['csrf_token'] ?? '');
if ($csrfClient === '' || $csrfServer === '' || !hash_equals($csrfServer, $csrfClient)) {
    respond(403, ['error' => 'invalid_csrf']);
}

// ── 5) Rate limiting (session) ────────────────────────────────────────────────
$doctorId    = (int)$_SESSION['id'];
$rateKey     = 'rate_ia_' . $doctorId;
$rateLimit   = 20;
$rateWindow  = 3600;

$now = time();
if (!isset($_SESSION[$rateKey]) || !is_array($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = ['count' => 0, 'reset_at' => $now + $rateWindow];
}
if ($now > (int)($_SESSION[$rateKey]['reset_at'] ?? 0)) {
    $_SESSION[$rateKey] = ['count' => 0, 'reset_at' => $now + $rateWindow];
}
if ((int)($_SESSION[$rateKey]['count'] ?? 0) >= $rateLimit) {
    $retry = max(1, (int)$_SESSION[$rateKey]['reset_at'] - $now);
    header('Retry-After: ' . $retry);
    respond(429, ['error' => 'rate_limit', 'detail' => "Limite de {$rateLimit} appels/heure atteinte."]);
}
$_SESSION[$rateKey]['count'] = (int)($_SESSION[$rateKey]['count'] ?? 0) + 1;

// ── 6) Validation prompt ──────────────────────────────────────────────────────
$prompt = trim((string)($body['prompt'] ?? ''));
if ($prompt === '') {
    respond(400, ['error' => 'invalid_prompt']);
}
if (mb_strlen($prompt, 'UTF-8') > 20_000) {
    respond(400, ['error' => 'invalid_prompt', 'detail' => 'prompt_too_long']);
}

// ── 7) Récupérer la clé API (ordre: secrets.php > .env > env serveur) ─────────
$apiKey = '';

// A) secrets.php hors public (recommandé)
$secretsPath = dirname(__DIR__) . '/config/secrets.php';
if (is_readable($secretsPath)) {
    $secrets = require $secretsPath;
    if (is_array($secrets)) {
        $apiKey = (string)($secrets['groq_key'] ?? '');
    }
}

// B) .env (sans composer) : .env au même niveau que ce fichier
if ($apiKey === '') {
    loadDotEnv(__DIR__ . '/.env');
    $apiKey = (string)(getenv('GROQ_API_KEY') ?: '');
}

// C) variable d’environnement serveur (dernier recours)
if ($apiKey === '') {
    $apiKey = (string)(getenv('GROQ_API_KEY') ?: '');
}

if ($apiKey === '') {
    error_log('[PsySpace proxy] GROQ_API_KEY introuvable (secrets.php/.env/env).');
    respond(500, ['error' => 'server_config_error']);
}

// ── 8) Paramètres modèle ─────────────────────────────────────────────────────
$maxTokensClient = (int)($body['max_tokens'] ?? 1200);
if ($maxTokensClient < 1) $maxTokensClient = 1;
if ($maxTokensClient > 3000) $maxTokensClient = 3000;

$payloadArr = [
    'model'       => 'llama-3.3-70b-versatile',
    'temperature' => 0.1,
    'max_tokens'  => $maxTokensClient,
    'messages'    => [
        [
            'role'    => 'system',
            'content' => 'Tu es PsySpace AI, un assistant clinique spécialisé en psychologie. Réponds UNIQUEMENT en JSON valide (pas de markdown, pas de backticks).'
        ],
        [
            'role'    => 'user',
            'content' => $prompt
        ],
    ],
];

$payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);
if ($payload === false) {
    respond(500, ['error' => 'server_error']);
}

// ── 9) Appel Groq (cURL) ─────────────────────────────────────────────────────
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
    error_log('[PsySpace proxy] cURL error: ' . $curlErr);
    respond(502, ['error' => 'upstream_error']);
}

// ── 10) Réponse ───────────────────────────────────────────────────────────────
http_response_code($httpCode);
echo $response;
exit();