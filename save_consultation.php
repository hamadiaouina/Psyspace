<?php
session_start();
require_once "connection.php";

// 1. Initialisation de la connexion
$db = null;
if (isset($conn)) { $db = $conn; }
elseif (isset($con)) { $db = $con; }

if (!$db) {
    error_log("[PsySpace] Erreur de connexion DB");
    echo "db_error";
    exit();
}

// 2. Vérifications de base
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo "method_not_allowed"; exit(); }

$doctor_id  = (int)($_SESSION['id'] ?? 0);
$transcript = trim($_POST['transcript'] ?? '');
$resume     = trim($_POST['resume']     ?? '');
$duree      = max(0, min(480, (int)($_POST['duree'] ?? 0)));
$emotions   = $_POST['emotions'] ?? '[]';

// CORRECTION #1 : pending_patient_id → pending_appointment_id
$appointment_id = (int)($_SESSION['pending_appointment_id'] ?? 0);

if (!$doctor_id)      { echo "not_logged_in";    exit(); }
if (!$transcript)     { echo "empty_transcript"; exit(); }
if (!$appointment_id) { echo "no_appointment_id"; exit(); }

// 3. Récupération du patient_id
// CORRECTION #2 : ajout AND doctor_id = ? + blocage patient_id=0
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
    error_log("[PsySpace] patient_id=0 pour appointment_id=$appointment_id");
    echo "invalid_patient_id";
    exit();
}

// 4. Validation JSON des émotions
json_decode($emotions);
if (json_last_error() !== JSON_ERROR_NONE) {
    $emotions = '[]';
}

// CORRECTION #3 : guard anti-doublon
$stmt_dup = $db->prepare("SELECT id FROM consultations WHERE appointment_id = ? LIMIT 1");
$stmt_dup->bind_param("i", $appointment_id);
$stmt_dup->execute();
if ($stmt_dup->get_result()->num_rows > 0) {
    echo "already_saved";
    $stmt_dup->close();
    exit();
}
$stmt_dup->close();

// 5. Insertion
$sql = "INSERT INTO consultations
        (patient_id, appointment_id, doctor_id, date_consultation, transcription_brute, resume_ia, duree_minutes, emotion_data)
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)";

$stmt = $db->prepare($sql);

if (!$stmt) {
    error_log("[PsySpace] Prepare Error: " . $db->error);
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
    // CORRECTION #4 : nettoyage avec le bon nom de session
    unset($_SESSION['pending_appointment_id'], $_SESSION['pending_patient_name']);
    echo "success";
} else {
    error_log("[PsySpace] Insert Error: " . $stmt->error);
    echo "insert_error";
}

$stmt->close();
?>