<?php
session_start();
if (!isset($_SESSION['id'])) { http_response_code(401); echo json_encode(['error'=>'unauthorized']); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(); }

$body   = json_decode(file_get_contents('php://input'), true);
$prompt = trim($body['prompt'] ?? '');
if (empty($prompt) || mb_strlen($prompt, 'UTF-8') > 20000) {
    http_response_code(400); echo json_encode(['error'=>'invalid_prompt']); exit();
}

// ── Clé Groq (gratuite) ───────────────────────────────────────────────────────
$api_key = "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";

// ── Payload au format OpenAI/Groq ─────────────────────────────────────────────
$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'temperature' => 0.1,
    'max_tokens'  => 2048,
    'messages'    => [
        [
            'role'    => 'system',
            'content' => 'Tu es PsySpace AI, un assistant clinique spécialisé en psychologie. Réponds UNIQUEMENT en JSON valide sans markdown ni backticks.'
        ],
        [
            'role'    => 'user',
            'content' => $prompt
        ]
    ]
]);

// ── Appel API Groq via cURL ───────────────────────────────────────────────────
$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    header('Content-Type: application/json');
    error_log("[PsySpace proxy] cURL error: " . $curlErr);
    echo json_encode(['error' => 'curl_error', 'detail' => $curlErr]);
    exit();
}

// ── Retransmettre la réponse au JS ────────────────────────────────────────────
http_response_code($httpCode);
header('Content-Type: application/json');
echo $response;
?>