<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: '';
if (
    empty($admin_secret_key) ||
    !isset($_COOKIE['psyspace_boss_key']) ||
    $_COOKIE['psyspace_boss_key'] !== $admin_secret_key ||
    !isset($_SESSION['admin_id']) ||
    $_SESSION['role'] !== 'admin'
) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit();
}

$csrf_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_header)) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf']);
    exit();
}

include '../connection.php';
if (!isset($con) && isset($conn)) { $con = $conn; }

// ✅ FIX #5 : Gestion connexion DB nulle
if (!isset($con) || !$con) {
    http_response_code(500);
    echo json_encode(['error' => 'db_unavailable']);
    exit();
}

$stat_doctors       = (int)$con->query("SELECT COUNT(*) c FROM doctor")->fetch_assoc()['c'];
$stat_active        = (int)$con->query("SELECT COUNT(*) c FROM doctor WHERE status='active'")->fetch_assoc()['c'];
$stat_suspended     = (int)$con->query("SELECT COUNT(*) c FROM doctor WHERE status='suspended'")->fetch_assoc()['c'];
$stat_pending       = $stat_doctors - $stat_active - $stat_suspended;
$stat_consultations = (int)$con->query("SELECT COUNT(*) c FROM consultations")->fetch_assoc()['c'];
$stat_appointments  = (int)$con->query("SELECT COUNT(*) c FROM appointments")->fetch_assoc()['c'];
$stat_patients      = (int)$con->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];

$critical_count = (int)$con->query(
    "SELECT COUNT(*) c FROM consultations WHERE resume_ia LIKE '%\"niveau_risque\":\"critique\"%'"
)->fetch_assoc()['c'];

$last_crit_row = $con->query(
    "SELECT id FROM consultations
     WHERE resume_ia LIKE '%\"niveau_risque\":\"critique\"%'
     ORDER BY id DESC LIMIT 1"  // ✅ ORDER BY id DESC plus fiable que date
)->fetch_assoc();
$last_crit_id = $last_crit_row ? (int)$last_crit_row['id'] : 0;

$last_consult_res = $con->query(
    "SELECT c.id, a.patient_name, d.docname, c.date_consultation
     FROM consultations c
     LEFT JOIN doctor d ON c.doctor_id = d.docid
     LEFT JOIN appointments a ON c.appointment_id = a.id
     ORDER BY c.id DESC LIMIT 1"  // ✅ ORDER BY id plus fiable
);
$last_consult = $last_consult_res ? $last_consult_res->fetch_assoc() : null;

$last_appt_res = $con->query(
    "SELECT a.id, a.patient_name, d.docname, a.app_date
     FROM appointments a
     LEFT JOIN doctor d ON a.doctor_id = d.docid
     ORDER BY a.id DESC LIMIT 1"  // ✅ ORDER BY id plus fiable
);
$last_appt = $last_appt_res ? $last_appt_res->fetch_assoc() : null;

$pending_doctors = [];
$res_pend = $con->query(
    "SELECT docid, docname, docemail FROM doctor WHERE status='pending' ORDER BY docid DESC LIMIT 5"
);
if ($res_pend) {
    while ($r = $res_pend->fetch_assoc()) $pending_doctors[] = $r;
}

echo json_encode([
    'ts'              => time(),
    'stats'           => [
        'doctors'       => $stat_doctors,
        'active'        => $stat_active,
        'pending'       => $stat_pending,
        'suspended'     => $stat_suspended,
        'consultations' => $stat_consultations,
        'appointments'  => $stat_appointments,
        'patients'      => $stat_patients,
        'critical'      => $critical_count,
    ],
    'last_crit_id'    => $last_crit_id,
    'last_consult'    => $last_consult,
    'last_appt'       => $last_appt,
    'pending_doctors' => $pending_doctors,
]);