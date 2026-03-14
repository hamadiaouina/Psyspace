<?php
// Fichier de test temporaire — supprimer après
$elevenKey = "sk_f9b3b1b93295d592e69ab32a0e4eb5512e876e73c6acd60f";
$voiceId   = "pNInz6obpgDQGcFmaJgB";

$ch = curl_init("https://api.elevenlabs.io/v1/text-to-speech/$voiceId");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        "text"     => "Bonjour, je suis l'assistant PsySpace.",
        "model_id" => "eleven_multilingual_v2",
    ]),
    CURLOPT_HTTPHEADER     => [
        "Content-Type: application/json",
        "xi-api-key: $elevenKey",
        "Accept: audio/mpeg"
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_VERBOSE        => true,
]);

$raw    = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);

echo "HTTP Status : $status\n";
echo "cURL Error  : " . ($err ?: 'aucune') . "\n";
echo "Taille audio: " . strlen($raw) . " bytes\n";

if ($status !== 200) {
    echo "Réponse ElevenLabs : " . substr($raw, 0, 500) . "\n";
} else {
    echo "✅ Audio reçu — ElevenLabs fonctionne !\n";
}