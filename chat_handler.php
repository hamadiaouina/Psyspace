<?php
/**
 * PSYSPACE - CHAT HANDLER SÉCURISÉ
 */

declare(strict_types=1);

// --- 1. SÉCURITÉ : VÉRIFICATION DE LA SESSION ---
// On empêche les requêtes externes (Postman, scripts pirates) de consommer tes API
ini_set('session.cookie_httponly', '1');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id'])) {
    http_response_code(403);
    echo json_encode(['text' => "Erreur : Action non autorisée. Veuillez vous connecter.", 'audio_base64' => '']);
    exit();
}

// --- 2. ANTI-SPAM (Rate Limiting) ---
// Empêche l'utilisateur d'envoyer 50 messages en 2 secondes
if (isset($_SESSION['last_chat_time']) && (time() - $_SESSION['last_chat_time']) < 3) {
    echo json_encode(['text' => "Veuillez patienter quelques secondes entre chaque message...", 'audio_base64' => '']);
    exit();
}
$_SESSION['last_chat_time'] = time();

// --- 3. CLÉS API ---
// Si tu testes en local sans .env, remplace temporairement les getenv() par tes clés, mais ne les pousse jamais sur GitHub !
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

// On enlève les balises HTML potentielles (Protection XSS / Prompt Injection)
$userMessage = strip_tags(trim((string)($decoded['message'] ?? ($_POST['message'] ?? ''))));

if (empty($userMessage)) {
    echo json_encode(['text' => "Bonjour ! Comment puis-je t'aider aujourd'hui ?", 'audio_base64' => '']);
    exit();
}

// PROTECTION DU QUOTA : Limite à 500 caractères maximum pour ne pas vider tes crédits ElevenLabs
if (mb_strlen($userMessage) > 500) {
    $userMessage = mb_substr($userMessage, 0, 500) . "...";
}

// --- 5. APPEL GROQ (Llama 3) ---
$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");

// Prompt Système spécial Santé : On protège ta responsabilité
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

// On ne génère la voix que si l'IA a bien répondu ET qu'on a les clés vocales
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
        // Base64 brut attendu par l'avatar Three.js
        $audioBase64 = base64_encode($resAudio);
    } else {
        error_log("Erreur ElevenLabs (Code $statusAudio) : " . $resAudio);
    }
}

// --- 7. RÉPONSE JSON AU FRONTEND ---
echo json_encode([
    'text'         => $answer,
    'audio_base64' => $audioBase64 
]);