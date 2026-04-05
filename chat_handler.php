<?php
/**
 * PSYSPACE - CHAT HANDLER (PUBLIC MAIS SÉCURISÉ)
 */

declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// --- 1. SÉCURITÉ : QUOTA PUBLIC ---
// Si l'utilisateur n'est pas connecté, on limite à 10 messages par session.
$is_logged_in = isset($_SESSION['id']);

if (!$is_logged_in) {
    if (!isset($_SESSION['public_chat_count'])) {
        $_SESSION['public_chat_count'] = 0;
    }
    
    // S'il dépasse 10 messages, on bloque poliment.
    if ($_SESSION['public_chat_count'] >= 10) {
        echo json_encode([
            'text' => "Vous avez atteint la limite de la démo publique (10 messages). Veuillez créer un compte gratuit pour continuer à utiliser PsySpace !", 
            'audio_base64' => ''
        ]);
        exit();
    }
    $_SESSION['public_chat_count']++; // On incrémente le compteur
}

// --- 2. ANTI-SPAM (Rate Limiting) ---
if (isset($_SESSION['last_chat_time']) && (time() - $_SESSION['last_chat_time']) < 3) {
    echo json_encode(['text' => "Veuillez patienter quelques secondes entre chaque message...", 'audio_base64' => '']);
    exit();
}
$_SESSION['last_chat_time'] = time();

// --- 3. CLÉS API ---
$groqKey   = getenv('GROQ_API_KEY');
$elevenKey = getenv('ELEVENLABS_API_KEY');
$voiceId   = getenv('ELEVENLABS_VOICE_ID');

if (empty($groqKey)) {
    echo json_encode(['text' => "L'intelligence artificielle est en maintenance (API manquante).", 'audio_base64' => '']);
    exit();
}

// --- 4. LECTURE ET NETTOYAGE DU MESSAGE ---
$jsonInput   = file_get_contents('php://input');
$decoded     = json_decode($jsonInput, true);

// On enlève les balises HTML potentielles (Protection XSS)
$userMessage = strip_tags(trim((string)($decoded['message'] ?? ($_POST['message'] ?? ''))));

if (empty($userMessage)) {
    echo json_encode(['text' => "Bonjour ! Comment puis-je t'aider aujourd'hui ?", 'audio_base64' => '']);
    exit();
}

// PROTECTION DU QUOTA ELEVENLABS : Limite à 500 caractères maximum
if (mb_strlen($userMessage) > 500) {
    $userMessage = mb_substr($userMessage, 0, 500) . "...";
}

// --- 5. APPEL GROQ (Llama 3) ---
$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");

$systemPrompt = "Tu es l'assistant virtuel PsySpace, conçu pour accompagner les professionnels de santé mentale et leurs patients. 
Réponds de manière concise, empathique et professionnelle, uniquement en français. 
Règle absolue : Tu n'es pas médecin. Tu ne dois jamais poser de diagnostic médical ni prescrire de médicaments.";

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
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user",   "content" => $userMessage]
        ]
    ]),
    CURLOPT_TIMEOUT => 20
]);

$resGroq  = curl_exec($ch);
$httpGroq = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$dataGroq = json_decode((string)$resGroq, true);
$answer   = $dataGroq['choices'][0]['message']['content'] ?? "Désolé, j'ai un souci de réflexion actuellement.";

// --- 6. APPEL ELEVENLABS (Text-to-Speech) ---
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
        $audioBase64 = base64_encode($resAudio);
    } else {
        error_log("Erreur ElevenLabs (Code $statusAudio) : " . $resAudio);
    }
}

// --- 7. RÉPONSE JSON ---
echo json_encode([
    'text'         => $answer,
    'audio_base64' => $audioBase64 
]);