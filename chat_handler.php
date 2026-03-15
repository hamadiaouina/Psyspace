<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function loadDotEnv(string $path): void {
    if (!is_readable($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;

        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $v = trim($v, "\"'");

        if ($k !== '' && getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

loadDotEnv(__DIR__ . '/.env');

$debugTts = (string)(getenv('DEBUG_TTS') ?: '') === '1';

$userMessage = '';
if (!empty($_POST['message'])) {
    $userMessage = trim((string)$_POST['message']);
} else {
    $body = json_decode((string)file_get_contents('php://input'), true);
    if (is_array($body)) {
        $userMessage = trim((string)($body['message'] ?? ''));
    }
}

if ($userMessage === '') {
    respond(200, [
        'text' => "Bonjour ! Comment puis-je t'aider sur PsySpace ?",
        'audio_base64' => '',
        'audio_url' => '',
    ]);
}

if (mb_strlen($userMessage, 'UTF-8') > 2000) {
    respond(400, ['error' => 'message_too_long']);
}

$groqKey   = (string)(getenv('GROQ_API_KEY') ?: '');
$elevenKey = (string)(getenv('ELEVENLABS_API_KEY') ?: '');
$voiceId   = (string)(getenv('ELEVENLABS_VOICE_ID') ?: '');

if ($groqKey === '') {
    respond(500, ['error' => 'missing_groq_key']);
}
if ($elevenKey === '') {
    respond(500, ['error' => 'missing_elevenlabs_key']);
}
if ($voiceId === '') {
    respond(500, ['error' => 'missing_voice_id']);
}

/* ══ GROQ ══ */
$system = <<<SYS
Tu es l'assistant officiel de PsySpace (plateforme de gestion de cabinet psychologique).
Objectif: aider l'utilisateur avec l'app (connexion, rendez-vous, dossiers, sécurité, bugs, utilisation).
Contraintes:
- Réponds en français naturel, ton chaleureux et pro.
- Fais 2 à 6 phrases maximum.
- Si la question est vague, pose 1 question de clarification.
- Ne donne jamais de données personnelles ni de diagnostic médical.
- Si l'utilisateur demande une action technique, donne des étapes simples (pas de listes longues).
SYS;

$payload = [
    "model"       => "llama-3.3-70b-versatile",
    "temperature" => 0.3,
    "max_tokens"  => 250,
    "messages"    => [
        ["role" => "system", "content" => $system],
        ["role" => "user", "content" => $userMessage],
    ],
];

$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
        "Content-Type: application/json",
        "Authorization: Bearer $groqKey",
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 20,
]);

$groqRaw  = curl_exec($ch);
$groqHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$groqErr  = curl_error($ch);
curl_close($ch);

if ($groqRaw === false) {
    respond(502, ['error' => 'groq_unreachable', 'detail' => $debugTts ? $groqErr : null]);
}

$groqData   = json_decode((string)$groqRaw, true);
$answerText = trim((string)($groqData['choices'][0]['message']['content'] ?? ''));

if ($groqHttp < 200 || $groqHttp >= 300 || $answerText === '') {
    $answerText = "Désolé, j’ai eu un souci technique. Tu peux reformuler ta demande en une phrase ?";
}

/* ══ ELEVENLABS (TTS) ══ */
$ch2 = curl_init("https://api.elevenlabs.io/v1/text-to-speech/$voiceId");
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        "text"     => $answerText,
        "model_id" => "eleven_multilingual_v2",
        "voice_settings" => [
            "stability" => 0.35,
            "similarity_boost" => 0.85,
            "style" => 0.45,
            "use_speaker_boost" => true,
        ],
    ], JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
        "Content-Type: application/json",
        "Accept: audio/mpeg",
        "xi-api-key: $elevenKey",
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 25,
]);

$audioRaw    = curl_exec($ch2);
$audioStatus = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$audioErr    = curl_error($ch2);
curl_close($ch2);

if ($audioStatus !== 200 || !is_string($audioRaw) || $audioRaw === '') {
    $out = [
        'text' => $answerText,
        'audio_base64' => '',
        'audio_url' => '',
    ];

    if ($debugTts) {
        $out['tts_debug'] = [
            'status' => $audioStatus,
            'curl_error' => $audioErr,
            'body_preview' => substr((string)$audioRaw, 0, 600),
            'voice_id' => $voiceId,
            'has_eleven_key' => $elevenKey !== '' ? 'yes' : 'no',
        ];
    }

    respond(200, $out);
}

$audioBase64 = base64_encode($audioRaw);
$audioUrl = 'data:audio/mpeg;base64,' . $audioBase64;

respond(200, [
    'text' => $answerText,
    'audio_base64' => $audioBase64,
    'audio_url' => $audioUrl, // ✅ prêt à jouer côté navigateur
]);