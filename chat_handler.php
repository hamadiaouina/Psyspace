<?php
header('Content-Type: application/json; charset=utf-8');

$userMessage = '';
if (!empty($_POST['message'])) {
    $userMessage = trim($_POST['message']);
} else {
    $body = json_decode(file_get_contents('php://input'), true);
    $userMessage = trim($body['message'] ?? '');
}

if (empty($userMessage)) {
    echo json_encode(['text' => 'Bonjour ! Comment puis-je vous aider avec PsySpace ?', 'audio_base64' => '']);
    exit();
}

$groqKey   = "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";
$elevenKey = "sk_f9b3b1b93295d592e69ab32a0e4eb5512e876e73c6acd60f";
$voiceId   = "GBv7mTt0atIp3Br8iCZE"; // Antoni — gratuit

/* ══ GROQ ══ */
$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        "model"       => "llama-3.3-70b-versatile",
        "temperature" => 0.5,
        "max_tokens"  => 180,
        "messages"    => [
            ["role" => "system", "content" => "Tu es l'assistant officiel de PsySpace, une plateforme de gestion de cabinet psychologique développée par Hamadi Aouina, étudiant en 3ème année à l'ISI, dans le cadre de son PFE. Tu aides sur : inscription/connexion, fonctionnalités, sécurité, développeur. Réponds en 2-3 phrases max, naturelles, sans listes. Tu es une voix."],
            ["role" => "user", "content" => $userMessage]
        ]
    ]),
    CURLOPT_HTTPHEADER     => ["Content-Type: application/json", "Authorization: Bearer $groqKey"],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 15,
]);
$groqData   = json_decode(curl_exec($ch), true);
curl_close($ch);
$answerText = trim($groqData['choices'][0]['message']['content'] ?? 'Désolé, difficulté technique.');

/* ══ ELEVENLABS ══ */
$ch2 = curl_init("https://api.elevenlabs.io/v1/text-to-speech/$voiceId");
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        "text"           => $answerText,
        "model_id"       => "eleven_multilingual_v2",
        "voice_settings" => ["stability" => 0.3, "similarity_boost" => 0.9, "style" => 0.3, "use_speaker_boost" => true]
    ]),
    CURLOPT_HTTPHEADER     => ["Content-Type: application/json", "xi-api-key: $elevenKey", "Accept: audio/mpeg"],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
]);
$audioRaw    = curl_exec($ch2);
$audioStatus = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

$audioBase64 = ($audioStatus === 200 && !empty($audioRaw)) ? base64_encode($audioRaw) : '';

echo json_encode(['text' => $answerText, 'audio_base64' => $audioBase64]);