<?php
// --- 1. CONFIGURATION SÉCURISÉE DES SESSIONS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}

session_start();

// --- 2. VÉRIFICATION DE LA MÉTHODE ET DU JETON CSRF (CRITIQUE) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    echo "method_not_allowed"; 
    exit(); 
}

$post_csrf = $_POST['csrf_token'] ?? '';
$sess_csrf = $_SESSION['csrf_token'] ?? '';

if (empty($post_csrf) || empty($sess_csrf) || !hash_equals($sess_csrf, $post_csrf)) {
    error_log("[PsySpace Security] Échec CSRF lors de l'enregistrement de la consultation.");
    echo "csrf_invalid";
    exit();
}

// --- 3. CONNEXION À LA BASE DE DONNÉES ---
require_once "connection.php";

$db = null;
if (isset($conn)) { $db = $conn; }
elseif (isset($con)) { $db = $con; }

if (!$db) {
    error_log("[PsySpace Error] Erreur de connexion DB");
    echo "db_error";
    exit();
}

// --- 4. RÉCUPÉRATION ET NETTOYAGE (ANTI-XSS) ---
$doctor_id = (int)($_SESSION['id'] ?? 0);

$action = trim($_POST['action'] ?? '');

// --- ACTIONS SECONDAIRES (save_plan, goal_achieved) ---
if ($action === 'save_plan') {
    $plan = strip_tags(trim($_POST['plan'] ?? ''));
    $appointment_id = (int)($_SESSION['pending_appointment_id'] ?? 0);
    if (!$doctor_id || !$appointment_id || !$plan) { echo "missing_data"; exit(); }

    // Vérification IDOR
    $s = $db->prepare("SELECT patient_id FROM appointments WHERE id=? AND doctor_id=? LIMIT 1");
    $s->bind_param("ii", $appointment_id, $doctor_id); $s->execute();
    $r = $s->get_result(); $s->close();
    if ($r->num_rows === 0) { echo "unauthorized"; exit(); }

    // Upsert plan dans la consultation existante ou dans une table dédiée (session en cours)
    // On stocke en session pour récupération lors de l'archivage final
    $_SESSION['pending_plan'] = $plan;
    echo "success";
    exit();
}

if ($action === 'goal_achieved') {
    $goal_id = (int)($_POST['goal_id'] ?? 0);
    if (!$doctor_id || !$goal_id) { echo "missing_data"; exit(); }
    $s = $db->prepare("UPDATE consultation_goals SET status='achieved', achieved_at=NOW() WHERE id=? AND doctor_id=? LIMIT 1");
    $s->bind_param("ii", $goal_id, $doctor_id); $s->execute(); $s->close();
    echo "success";
    exit();
}

// --- ARCHIVAGE PRINCIPAL ---
$transcript       = strip_tags(trim($_POST['transcript'] ?? ''));
$resume           = strip_tags(trim($_POST['resume'] ?? ''));
$duree            = max(0, min(480, (int)($_POST['duree'] ?? 0)));
$emotions         = $_POST['emotions'] ?? '[]';
$emo_timeline     = $_POST['emo_timeline'] ?? '[]';
$plan             = strip_tags(trim($_POST['plan'] ?? $_SESSION['pending_plan'] ?? ''));
$niveau_risque    = strip_tags(trim($_POST['niveau_risque'] ?? 'faible'));
$hypotheses       = $_POST['hypotheses'] ?? '[]';
$objectifs        = $_POST['objectifs'] ?? '[]';
$motif_seance     = strip_tags(trim($_POST['motif_seance'] ?? ''));
$evolution_inter  = strip_tags(trim($_POST['evolution_inter'] ?? ''));

// Notes du praticien — stockées séparément, confidentielles
$notes_praticien  = strip_tags(trim($_POST['notes'] ?? ''));

$appointment_id = (int)($_SESSION['pending_appointment_id'] ?? 0);

if (!$doctor_id)      { echo "not_logged_in"; exit(); }
if (!$appointment_id) { echo "no_appointment_id"; exit(); }

// Transcription optionnelle : on peut archiver sans transcription si un compte-rendu a été généré
// (mais on exige au minimum le résumé)
if (!$resume && !$transcript) { echo "empty_content"; exit(); }

// Validation JSON stricte
foreach (['emotions', 'emo_timeline', 'hypotheses', 'objectifs'] as $jsonField) {
    json_decode($$jsonField);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $$jsonField = '[]';
    }
}

// Niveau de risque : valeurs autorisées uniquement
$allowed_risques = ['faible', 'modéré', 'élevé', 'critique'];
if (!in_array($niveau_risque, $allowed_risques, true)) {
    $niveau_risque = 'faible';
}

// --- 5. VÉRIFICATION ANTI-IDOR ---
$stmt_find = $db->prepare("SELECT patient_id FROM appointments WHERE id = ? AND doctor_id = ? LIMIT 1");
$stmt_find->bind_param("ii", $appointment_id, $doctor_id);
$stmt_find->execute();
$res_find = $stmt_find->get_result();

if ($res_find->num_rows === 0) {
    echo "appointment_not_found";
    $stmt_find->close();
    exit();
}

$patient_id = (int)$res_find->fetch_assoc()['patient_id'];
$stmt_find->close();

if (!$patient_id) {
    error_log("[PsySpace Error] patient_id=0 pour appointment_id=$appointment_id");
    echo "invalid_patient_id";
    exit();
}

// --- 6. PROTECTION ANTI-DOUBLON ---
$stmt_dup = $db->prepare("SELECT id FROM consultations WHERE appointment_id = ? LIMIT 1");
$stmt_dup->bind_param("i", $appointment_id);
$stmt_dup->execute();
if ($stmt_dup->get_result()->num_rows > 0) {
    echo "already_saved";
    $stmt_dup->close();
    exit();
}
$stmt_dup->close();

// --- 7. INSERTION SÉCURISÉE ---
// NOTE : Assurez-vous que la colonne notes_praticien existe :
// ALTER TABLE consultations ADD COLUMN notes_praticien TEXT NULL;
// ALTER TABLE consultations ADD COLUMN plan_therapeutique TEXT NULL;
// ALTER TABLE consultations ADD COLUMN niveau_risque VARCHAR(20) NULL DEFAULT 'faible';
// ALTER TABLE consultations ADD COLUMN hypotheses_diagnostiques JSON NULL;
// ALTER TABLE consultations ADD COLUMN objectifs_suivants JSON NULL;
// ALTER TABLE consultations ADD COLUMN motif_seance TEXT NULL;
// ALTER TABLE consultations ADD COLUMN evolution_inter TEXT NULL;
// ALTER TABLE consultations ADD COLUMN emotions_plutchik JSON NULL;

$sql = "INSERT INTO consultations
        (patient_id, appointment_id, doctor_id, date_consultation,
         transcription_brute, resume_ia, duree_minutes, emotion_data,
         emotions_plutchik, plan_therapeutique, niveau_risque,
         hypotheses_diagnostiques, objectifs_suivants, motif_seance,
         evolution_inter, notes_praticien)
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $db->prepare($sql);

if (!$stmt) {
    error_log("[PsySpace Error] Prepare Error: " . $db->error);
    echo "db_prepare_error";
    exit();
}

$stmt->bind_param("iiissiissssssss",
    $patient_id,
    $appointment_id,
    $doctor_id,
    $transcript,
    $resume,
    $duree,
    $emotions,       // emotion_data (ancien champ)
    $emo_timeline,   // emotions_plutchik (nouveau champ JSON détaillé)
    $plan,
    $niveau_risque,
    $hypotheses,
    $objectifs,
    $motif_seance,
    $evolution_inter,
    $notes_praticien
);

if ($stmt->execute()) {
    unset($_SESSION['pending_appointment_id'], $_SESSION['pending_patient_name'], $_SESSION['pending_plan']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
    echo "success";
} else {
    error_log("[PsySpace Error] Insert Error: " . $stmt->error);
    echo "insert_error";
}

$stmt->close();
?>