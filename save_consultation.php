<?php
ob_start(); // Buffer toute sortie pour éviter que les warnings polluent la réponse JSON/texte

// --- 1. CONFIGURATION SÉCURISÉE DES SESSIONS ---
// Suppression des warnings si la session est déjà démarrée par connection.php
@ini_set('session.cookie_httponly', '1');
@ini_set('session.use_only_cookies', '1');
@ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    @ini_set('session.cookie_secure', '1');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 2. VÉRIFICATION MÉTHODE + CSRF ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean(); echo "method_not_allowed";
    exit();
}

$post_csrf = $_POST['csrf_token'] ?? '';
$sess_csrf = $_SESSION['csrf_token'] ?? '';

if (empty($post_csrf) || empty($sess_csrf) || !hash_equals($sess_csrf, $post_csrf)) {
    error_log("[PsySpace Security] Échec CSRF - save_consultation");
    ob_clean(); echo "csrf_invalid";
    exit();
}

// --- 3. CONNEXION DB ---
// connection.php ne doit PAS appeler session_start() lui-même
// Si c'est le cas, les warnings ini_set viennent de là — rien à faire ici
require_once "connection.php";

$db = null;
if (isset($conn))      { $db = $conn; }
elseif (isset($con))   { $db = $con; }

if (!$db) {
    error_log("[PsySpace Error] Connexion DB échouée");
    ob_clean(); echo "db_error";
    exit();
}

$doctor_id = (int)($_SESSION['id'] ?? 0);
$action    = trim($_POST['action'] ?? '');

// ══════════════════════════════════════════════
// ACTION : save_plan — sauvegarde le plan en session
// ══════════════════════════════════════════════
if ($action === 'save_plan') {
    $plan           = strip_tags(trim($_POST['plan'] ?? ''));
    $appointment_id = (int)($_SESSION['pending_appointment_id'] ?? 0);

    if (!$doctor_id || !$appointment_id || !$plan) {
        ob_clean(); echo "missing_data";
        exit();
    }

    // Vérification IDOR
    $s = $db->prepare("SELECT patient_id FROM appointments WHERE id=? AND doctor_id=? LIMIT 1");
    $s->bind_param("ii", $appointment_id, $doctor_id);
    $s->execute();
    $r = $s->get_result();
    $s->close();

    if ($r->num_rows === 0) {
        ob_clean(); echo "unauthorized";
        exit();
    }

    $_SESSION['pending_plan'] = $plan;
    ob_clean(); echo "success";
    exit();
}

// ══════════════════════════════════════════════
// ACTION : goal_achieved — marquer un objectif comme atteint
// ══════════════════════════════════════════════
if ($action === 'goal_achieved') {
    $goal_id = (int)($_POST['goal_id'] ?? 0);

    if (!$doctor_id || !$goal_id) {
        ob_clean(); echo "missing_data";
        exit();
    }

    $s = $db->prepare(
        "UPDATE consultation_goals
         SET status='achieved', resolved_at=NOW()
         WHERE id=? AND doctor_id=? LIMIT 1"
    );
    $s->bind_param("ii", $goal_id, $doctor_id);
    $s->execute();
    $s->close();
    ob_clean(); echo "success";
    exit();
}

// ══════════════════════════════════════════════
// ARCHIVAGE PRINCIPAL
// ══════════════════════════════════════════════

// Récupération et nettoyage des champs
$transcript      = strip_tags(trim($_POST['transcript']     ?? ''));
$resume          = strip_tags(trim($_POST['resume']         ?? ''));
$duree           = max(0, min(480, (int)($_POST['duree']    ?? 0)));
$emotions        = $_POST['emotions']      ?? '{}';   // JSON objet {tristesse:x, joie:y, ...}
$emo_timeline    = $_POST['emo_timeline']  ?? '[]';   // JSON tableau
$plan            = strip_tags(trim($_POST['plan'] ?? $_SESSION['pending_plan'] ?? ''));
$niveau_risque   = strip_tags(trim($_POST['niveau_risque']  ?? 'faible'));
$motif_seance    = mb_substr(strip_tags(trim($_POST['motif_seance']   ?? '')), 0, 500, 'UTF-8');
$evolution_inter = strip_tags(trim($_POST['evolution_inter'] ?? ''));
$notes_praticien = strip_tags(trim($_POST['notes']          ?? ''));

// Nouveaux champs : âge et ville extraits/sauvegardés
$age_patient     = !empty($_POST['age_patient'])   ? (int)$_POST['age_patient']            : null;
$ville_patient   = !empty($_POST['ville_patient'])  ? strip_tags(trim($_POST['ville_patient'])) : null;

$appointment_id  = (int)($_SESSION['pending_appointment_id'] ?? 0);

// Validations de base
if (!$doctor_id)      { ob_clean(); echo "not_logged_in";    exit(); }
if (!$appointment_id) { ob_clean(); echo "no_appointment_id"; exit(); }
if (!$resume && !$transcript) { ob_clean(); echo "empty_content"; exit(); }

// Validation JSON
foreach (['emotions', 'emo_timeline'] as $jsonField) {
    json_decode($$jsonField);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $$jsonField = ($jsonField === 'emotions') ? '{}' : '[]';
    }
}

// Niveau de risque : valeurs autorisées uniquement
$allowed_risques = ['faible', 'modéré', 'élevé', 'critique'];
if (!in_array($niveau_risque, $allowed_risques, true)) {
    $niveau_risque = 'faible';
}

// MySQLi bind_param ne gère pas null nativement pour les ints
// On passe 0 si null, la colonne accepte NULL via le type SQL
$age_patient_bind = $age_patient ?? 0;
if ($ville_patient && mb_strlen($ville_patient) > 150) {
    $ville_patient = mb_substr($ville_patient, 0, 150);
}

// --- Vérification IDOR ---
$stmt_find = $db->prepare(
    "SELECT patient_id FROM appointments WHERE id=? AND doctor_id=? LIMIT 1"
);
$stmt_find->bind_param("ii", $appointment_id, $doctor_id);
$stmt_find->execute();
$res_find = $stmt_find->get_result();

if ($res_find->num_rows === 0) {
    ob_clean(); echo "appointment_not_found";
    $stmt_find->close();
    exit();
}

$patient_id = (int)$res_find->fetch_assoc()['patient_id'];
$stmt_find->close();

if (!$patient_id) {
    error_log("[PsySpace Error] patient_id=0 pour appointment_id=$appointment_id");
    ob_clean(); echo "invalid_patient_id";
    exit();
}

// --- Protection anti-doublon ---
$stmt_dup = $db->prepare(
    "SELECT id FROM consultations WHERE appointment_id=? LIMIT 1"
);
$stmt_dup->bind_param("i", $appointment_id);
$stmt_dup->execute();
if ($stmt_dup->get_result()->num_rows > 0) {
    ob_clean(); echo "already_saved";
    $stmt_dup->close();
    exit();
}
$stmt_dup->close();

// --- Insertion ---
// IMPORTANT : motif_seance doit être TEXT (pas VARCHAR) car il contient maintenant le résumé complet
// ALTER TABLE consultations MODIFY COLUMN motif_seance TEXT;
// ALTER TABLE consultations ADD COLUMN age_patient INT DEFAULT NULL;
// ALTER TABLE consultations ADD COLUMN ville_patient VARCHAR(150) DEFAULT NULL;
// (motif_seance, evolution_inter, notes_praticien, emotions_plutchik, plan_therapeutique,
//  niveau_risque, emo_timeline déjà existants)

$sql = "INSERT INTO consultations
        (patient_id, appointment_id, doctor_id, date_consultation,
         transcription_brute, resume_ia, duree_minutes,
         emotion_data, emotions_plutchik, emo_timeline,
         plan_therapeutique, niveau_risque,
         motif_seance, evolution_inter, notes_praticien,
         age_patient, ville_patient)
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $db->prepare($sql);

if (!$stmt) {
    error_log("[PsySpace Error] Prepare: " . $db->error);
    ob_clean(); echo "db_prepare_error: " . $db->error;
    exit();
}

// 17 paramètres exacts :
// i patient_id, i appointment_id, i doctor_id
// s transcript,  s resume,        i duree
// s emotions(emotion_data), s emotions(emotions_plutchik), s emo_timeline
// s plan,        s niveau_risque
// s motif_seance, s evolution_inter, s notes_praticien
// i age_patient, s ville_patient
// = "iiissiSssssssssis" → comptons : i i i s s i s s s s s s s s s i s = 17
$stmt->bind_param(
    "iiississssssssis",
    $patient_id,       // i
    $appointment_id,   // i
    $doctor_id,        // i
    $transcript,       // s
    $resume,           // s
    $duree,            // i
    $emotions,         // s — emotion_data
    $emotions,         // s — emotions_plutchik
    $emo_timeline,     // s — emo_timeline
    $plan,             // s
    $niveau_risque,    // s
    $motif_seance,     // s
    $evolution_inter,  // s
    $notes_praticien,  // s
    $age_patient_bind, // i
    $ville_patient     // s
);

if ($stmt->execute()) {
    unset(
        $_SESSION['pending_appointment_id'],
        $_SESSION['pending_patient_name'],
        $_SESSION['pending_plan']
    );
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    ob_clean(); echo "success";
} else {
    error_log("[PsySpace Error] Insert: " . $stmt->error);
    // Retourner le détail de l'erreur pour debug (à retirer en prod)
    ob_clean(); echo "insert_error: " . $stmt->error;
}
$stmt->close();
?>