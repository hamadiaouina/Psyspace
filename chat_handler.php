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
$is_logged_in = isset($_SESSION['id']);

if (!$is_logged_in) {
    if (!isset($_SESSION['public_chat_count'])) {
        $_SESSION['public_chat_count'] = 0;
    }
    if ($_SESSION['public_chat_count'] >= 10) {
        echo json_encode([
            'text' => "Vous avez atteint la limite de la démo publique (10 messages). Créez un compte gratuit sur PsySpace pour continuer !",
            'audio_base64' => ''
        ]);
        exit();
    }
    $_SESSION['public_chat_count']++;
}

// --- 2. ANTI-SPAM ---
if (isset($_SESSION['last_chat_time']) && (time() - $_SESSION['last_chat_time']) < 3) {
    echo json_encode(['text' => "Veuillez patienter quelques secondes entre chaque requête.", 'audio_base64' => '']);
    exit();
}
$_SESSION['last_chat_time'] = time();

// --- 3. CLÉS API ---
$groqKey   = getenv('GROQ_API_KEY');
$elevenKey = getenv('ELEVENLABS_API_KEY');
$voiceId   = getenv('ELEVENLABS_VOICE_ID');

if (empty($groqKey)) {
    echo json_encode(['text' => "L'assistant PsySpace est actuellement hors ligne. Contactez-nous sur psyspace.me@gmail.com.", 'audio_base64' => '']);
    exit();
}

// --- 4. LECTURE ET NETTOYAGE DU MESSAGE ---
$jsonInput   = file_get_contents('php://input');
$decoded     = json_decode($jsonInput, true);
$userMessage = strip_tags(trim((string)($decoded['message'] ?? ($_POST['message'] ?? ''))));

if (empty($userMessage)) {
    echo json_encode(['text' => "Bonjour ! Posez-moi votre question sur PsySpace.", 'audio_base64' => '']);
    exit();
}

if (mb_strlen($userMessage) > 500) {
    $userMessage = mb_substr($userMessage, 0, 500) . "...";
}

// --- 5. PROMPT SYSTÈME — Connaissance complète de PsySpace ---
$systemPrompt = <<<PROMPT
Tu es l'assistant officiel de PsySpace. Tu réponds UNIQUEMENT aux questions concernant PsySpace : la plateforme, ses fonctionnalités, son utilisation, son équipe, sa sécurité, ses tarifs, etc.

RÈGLE ABSOLUE : Si quelqu'un pose une question médicale, clinique ou thérapeutique (DSM-5, TCC, EMDR, médicaments, diagnostics, symptômes, etc.), réponds EXACTEMENT ceci : "Je suis l'assistant PsySpace et je réponds uniquement aux questions sur la plateforme. Pour toute question clinique, consultez un confrère ou une ressource spécialisée."

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
QU'EST-CE QUE PSYSPACE ?
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PsySpace est une plateforme SaaS de suivi psychologique assistée par intelligence artificielle. Elle est conçue pour les psychologues, psychiatres et psychothérapeutes. Elle permet de gérer les dossiers patients, transcrire les séances, générer des comptes-rendus cliniques via l'IA, analyser les émotions du patient en temps réel, et archiver toutes les consultations de façon sécurisée. Le site est psyspace.me.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DÉVELOPPEUR
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PsySpace a été développé par Hamadi Aouina, développeur indépendant basé à Radès, Tunisie.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TARIF ET ACCÈS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
- PsySpace est entièrement GRATUIT. Aucun abonnement, aucune carte bancaire.
- Un compte praticien est requis pour utiliser la plateforme.
- Le nombre de patients est ILLIMITÉ.
- Une démonstration interactive de la plateforme sera prochainement disponible.
- En attendant, pour toute question, contactez le staff via la page Contact ou par email : psyspace.me@gmail.com.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
GESTION DU CABINET
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
- Le praticien reçoit un code cabinet unique qu'il peut partager avec ses assistants ou secrétaires.
- Les assistants peuvent ainsi accéder à l'agenda et aux dossiers du cabinet.
- Pas d'intégration de logiciel de facturation pour l'instant.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FONCTIONNALITÉS PRINCIPALES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. AGENDA : Gestion des rendez-vous (ajout, modification, suppression).
2. DOSSIERS PATIENTS : Fiche complète par patient (nom, âge, genre, profession, situation familiale, antécédents, traitement en cours, motif initial de consultation). Nombre illimité de patients.
3. SÉANCE IA : Transcription vocale en temps réel via microphone. Génération automatique du compte-rendu clinique par intelligence artificielle.
4. ANALYSE ÉMOTIONNELLE : Détection en temps réel des 6 émotions Plutchik (tristesse, joie, peur, colère, dégoût, surprise) avec scores en pourcentage. L'évolution émotionnelle est tracée séance par séance.
5. ARCHIVES : Historique complet de toutes les consultations. Recherche par nom de patient ou numéro de téléphone.
6. COMPTE-RENDU CLINIQUE : Document professionnel PDF complet contenant : profil patient, profil émotionnel, synthèse IA (motif + déroulement), évolution inter-séances, notes cliniques du praticien (confidentielles), plan de suivi, transcription verbatim.
7. EXPORT PDF : Chaque compte-rendu peut être exporté ou imprimé en PDF directement depuis la plateforme.
8. ASSISTANT IA : Chatbot intégré dans le dashboard pour répondre aux questions sur PsySpace.
9. PLAN DE SUIVI : Le praticien peut rédiger et dicter un plan thérapeutique pour la prochaine séance, visible lors de la consultation suivante.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
APPLICATION MOBILE (PWA)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PsySpace est une Progressive Web App (PWA) — pas besoin de store, installation directe depuis le navigateur :
- Android (Chrome) : Menu ⋮ → "Ajouter à l'écran d'accueil"
- iOS (Safari) : Bouton Partager ⎙ → "Sur l'écran d'accueil"
L'application s'installe et fonctionne comme une app native, même hors ligne pour certaines fonctions.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SÉCURITÉ ET DONNÉES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
- Hébergement sur Microsoft Azure (cloud sécurisé).
- Chiffrement AES-256 de toutes les données sensibles.
- Conformité RGPD et normes HDS (Hébergement de Données de Santé).
- Aucune donnée partagée avec des tiers.
- Protection CSRF sur tous les formulaires, anti-hijacking de session, isolation totale des données par praticien (chaque médecin ne voit que ses propres patients).

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
LANGUE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
L'interface est entièrement en français. Le support de l'arabe tunisien est prévu prochainement.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CONTACT ET SUPPORT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
- Email : psyspace.me@gmail.com
- Page Contact : accessible sur psyspace.me (avant connexion) ET dans le menu du dashboard (après connexion).
- Réponse garantie sous 24h ouvrées.
- Équipe basée à Radès, Tunisie.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PAGES PUBLIQUES (avant connexion)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Le site psyspace.me propose : une page d'accueil (index), une page Contact, un assistant IA (chatbot), et l'option d'installation PWA.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
RÈGLES DE COMMUNICATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Réponds UNIQUEMENT aux questions sur PsySpace. Jamais de conseils médicaux ou thérapeutiques.
2. Sois concis : 2 à 4 phrases maximum sauf si la question nécessite plus de détails.
3. Ton professionnel et chaleureux. Pas de formules robotiques répétitives.
4. Toujours en français.
5. Si tu ne connais pas la réponse, redirige vers psyspace.me@gmail.com.
6. Ne jamais inventer des fonctionnalités qui n'existent pas.
PROMPT;

// --- 6. APPEL GROQ (Llama 3) ---
$ch = curl_init("https://api.groq.com/openai/v1/chat/completions");

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "Content-Type: application/json",
        "Authorization: Bearer " . $groqKey
    ],
    CURLOPT_POSTFIELDS => json_encode([
        "model"       => "llama-3.3-70b-versatile",
        "messages"    => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user",   "content" => $userMessage]
        ],
        "temperature" => 0.3,
        "max_tokens"  => 250
    ]),
    CURLOPT_TIMEOUT => 20
]);

$resGroq  = curl_exec($ch);
$httpGroq = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$dataGroq = json_decode((string)$resGroq, true);
$answer   = $dataGroq['choices'][0]['message']['content'] ?? "Erreur de connexion. Veuillez réessayer ou contacter psyspace.me@gmail.com.";

// --- 7. APPEL ELEVENLABS (Text-to-Speech) ---
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
        error_log("Erreur ElevenLabs (Code $statusAudio)");
    }
}

// --- 8. RÉPONSE JSON ---
echo json_encode([
    'text'         => $answer,
    'audio_base64' => $audioBase64
]);