<?php
/**
 * PSYSPACE - CHAT HANDLER
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// 1. Clés API
$groqKey   = getenv('GROQ_API_KEY');
$elevenKey = getenv('ELEVENLABS_API_KEY');
$voiceId   = getenv('ELEVENLABS_VOICE_ID');

// 2. Lecture du message
$jsonInput   = file_get_contents('php://input');
$decoded     = json_decode($jsonInput, true);
$userMessage = trim((string)($decoded['message'] ?? ($_POST['message'] ?? '')));

if (empty($userMessage)) {
    echo json_encode(['text' => "Bonjour ! Comment puis-je t'aider ?", 'audio_base64' => '']);
    exit;
}

// 3. Appel GROQ
$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "Content-Type: application/json",
        "Authorization: Bearer " . $groqKey
    ],
    CURLOPT_POSTFIELDS => json_encode([
        "model"    => "llama-3.3-70b-versatile",
        "messages" => [
            ["role" => "system", "content" => "Tu es l'assistant PsySpace. Réponds court et pro en français."],
            ["role" => "user",   "content" => $userMessage]
        ]
    ]),
    CURLOPT_TIMEOUT => 20
]);

$resGroq  = curl_exec($ch);
$httpGroq = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$dataGroq = json_decode((string)$resGroq, true);
$answer   = $dataGroq['choices'][0]['message']['content'] ?? "Désolé, j'ai un souci technique (Code $httpGroq).";

// 4. Appel ElevenLabs — renvoie le base64 BRUT (sans préfixe data:...)
$audioBase64 = "";
if ($httpGroq === 200 && !empty($elevenKey) && !empty($voiceId)) {
    $ch2 = curl_init("https://api.elevenlabs.io/v1/text-to-speech/$voiceId");
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "xi-api-key: " . $elevenKey
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "text"     => $answer,
            "model_id" => "eleven_multilingual_v2"
        ]),
        CURLOPT_TIMEOUT => 25
    ]);
    $resAudio    = curl_exec($ch2);
    $statusAudio = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($statusAudio === 200 && !empty($resAudio)) {
        // Base64 brut — PAS de préfixe "data:audio/mpeg;base64,"
        $audioBase64 = base64_encode($resAudio);
    }
}

// 5. Réponse JSON
echo json_encode([
    'text'         => $answer,
    'audio_base64' => $audioBase64   // champ attendu par chatbot.php
]);