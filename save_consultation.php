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

// Nettoyage strict : on supprime toutes les balises HTML/PHP potentielles
$transcript = strip_tags(trim($_POST['transcript'] ?? ''));
$resume     = strip_tags(trim($_POST['resume'] ?? ''));
$duree      = max(0, min(480, (int)($_POST['duree'] ?? 0)));
$emotions   = $_POST['emotions'] ?? '[]';

$appointment_id = (int)($_SESSION['pending_appointment_id'] ?? 0);

if (!$doctor_id)      { echo "not_logged_in"; exit(); }
if (!$transcript)     { echo "empty_transcript"; exit(); }
if (!$appointment_id) { echo "no_appointment_id"; exit(); }

// Validation JSON stricte des émotions
json_decode($emotions);
if (json_last_error() !== JSON_ERROR_NONE) {
    $emotions = '[]';
}

// --- 5. VÉRIFICATION ANTI-IDOR (Autorisation) ---
// On s'assure que le docteur a bien le droit de sauvegarder CE rendez-vous
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

// --- 7. INSERTION SÉCURISÉE (PREPARED STATEMENT) ---
$sql = "INSERT INTO consultations
        (patient_id, appointment_id, doctor_id, date_consultation, transcription_brute, resume_ia, duree_minutes, emotion_data)
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)";

$stmt = $db->prepare($sql);

if (!$stmt) {
    error_log("[PsySpace Error] Prepare Error: " . $db->error);
    echo "db_prepare_error";
    exit();
}

$stmt->bind_param("iiissis",
    $patient_id,
    $appointment_id,
    $doctor_id,
    $transcript,
    $resume,
    $duree,
    $emotions
);

if ($stmt->execute()) {
    // Nettoyage de la session et renouvellement du jeton CSRF pour la prochaine action
    unset($_SESSION['pending_appointment_id'], $_SESSION['pending_patient_name']);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
    
    echo "success";
} else {
    error_log("[PsySpace Error] Insert Error: " . $stmt->error);
    echo "insert_error";
}

$stmt->close();
?>