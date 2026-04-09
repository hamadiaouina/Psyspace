<?php
declare(strict_types=1);
ob_start();

// --- 1. SÉCURITÉ DES SESSIONS & HEADERS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

session_start();

include "../connection.php";
if (!isset($con) && isset($conn)) { $con = $conn; }

// --- 2. SÉCURITÉ : VÉRIFICATION DU BADGE INVISIBLE (God Mode) ---
$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: "";
if (empty($admin_secret_key) || !isset($_COOKIE['psyspace_boss_key']) || $_COOKIE['psyspace_boss_key'] !== $admin_secret_key) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// --- 3. VÉRIFICATION DE LA SESSION ADMIN ---
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); 
    exit();
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token CSRF invalide.');
    }
}

// ── LOG ───────────────────────────────────────────────────────────────────────
function logAction($con, $admin_id, $action, $details): void {
    if (!$con) return;
    if ($con->query("SHOW TABLES LIKE 'admin_logs'")->num_rows === 0) {
        $con->query("CREATE TABLE admin_logs (id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT, action VARCHAR(100), details TEXT, ip VARCHAR(45), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    }
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $con->prepare("INSERT INTO admin_logs (admin_id, action, details, ip) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $admin_id, $action, $details, $ip);
    $stmt->execute(); $stmt->close();
}

$admin_name    = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$admin_initial = strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1));
$current_file  = basename($_SERVER['PHP_SELF']);

// ── ACTIONS POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action   = $_POST['action']  ?? '';
    $section  = $_POST['section'] ?? 'overview';
    $admin_id = (int)$_SESSION['admin_id'];

    switch ($action) {
        case 'toggle_doctor':
            $id  = (int)$_POST['docid'];
            $new = $_POST['new_status'] ?? '';
            if (in_array($new, ['active','pending','suspended'], true)) {
                $s = $con->prepare("UPDATE doctor SET status=? WHERE docid=?");
                $s->bind_param("si", $new, $id); $s->execute(); $s->close();
                logAction($con, $admin_id, 'toggle_doctor', "Doctor ID $id → $new");
            } break;

        case 'delete_doctor':
            $id = (int)$_POST['rid'];
            $r  = $con->query("SELECT docname FROM doctor WHERE docid=$id")->fetch_assoc();
            $s  = $con->prepare("DELETE FROM doctor WHERE docid=?");
            $s->bind_param("i",$id); $s->execute(); $s->close();
            logAction($con, $admin_id, 'delete_doctor', "Deleted: ".($r['docname']??$id)); break;

        case 'delete_appointment':
            $id = (int)$_POST['rid'];
            $s  = $con->prepare("DELETE FROM appointments WHERE id=?");
            $s->bind_param("i",$id); $s->execute(); $s->close();
            logAction($con, $admin_id, 'delete_appointment', "Appt $id deleted"); break;

        case 'delete_consultation':
            $id = (int)$_POST['rid'];
            $s  = $con->prepare("DELETE FROM consultations WHERE id=?");
            $s->bind_param("i",$id); $s->execute(); $s->close();
            logAction($con, $admin_id, 'delete_consultation', "Consultation $id deleted"); break;

        case 'delete_patient':
            $id = (int)$_POST['rid'];
            $r  = $con->query("SELECT pname FROM patients WHERE id=$id")->fetch_assoc();
            $s  = $con->prepare("DELETE FROM patients WHERE id=?");
            $s->bind_param("i",$id); $s->execute(); $s->close();
            logAction($con, $admin_id, 'delete_patient', "Deleted: ".($r['pname']??$id)); break;

        case 'edit_doctor':
            $id        = (int)$_POST['docid'];
            $docname   = trim($_POST['docname']   ?? '');
            $docemail  = trim($_POST['docemail']  ?? '');
            $specialty = trim($_POST['specialty'] ?? '');
            if ($docname && $docemail) {
                $s = $con->prepare("UPDATE doctor SET docname=?, docemail=?, specialty=? WHERE docid=?");
                $s->bind_param("sssi", $docname, $docemail, $specialty, $id); $s->execute(); $s->close();
                logAction($con, $admin_id, 'edit_doctor', "Edited doctor ID $id → $docname");
            } break;

        case 'reset_password':
            $id      = (int)$_POST['docid'];
            $newpass = trim($_POST['new_password'] ?? '');
            if (strlen($newpass) >= 8) {
                $hash = password_hash($newpass, PASSWORD_ARGON2ID);
                $s    = $con->prepare("UPDATE doctor SET docpassword=? WHERE docid=?");
                $s->bind_param("si", $hash, $id); $s->execute(); $s->close();
                logAction($con, $admin_id, 'reset_password', "Password reset for doctor ID $id");
            } break;

        case 'clean_tokens':
            $con->query("UPDATE doctor SET reset_token=NULL, token_expiry=NULL WHERE reset_token IS NOT NULL");
            logAction($con, $admin_id, 'clean_tokens', "All reset tokens cleaned"); break;

        case 'unblock_ip':
            $ip_to_unblock = trim($_POST['target_ip'] ?? '');
            if ($ip_to_unblock) {
                $s = $con->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
                $s->bind_param("s", $ip_to_unblock); $s->execute(); $s->close();
                logAction($con, $admin_id, 'unblock_ip', "IP Assistante débloquée: $ip_to_unblock");
            } break;

        case 'export_csv':
            ob_end_clean();
            $type    = $_POST['export_type'] ?? 'doctors';
            $queries = [
                'doctors'       => "SELECT docid as ID, docname as Nom, docemail as Email, specialty as Specialite, status as Statut FROM doctor ORDER BY docid DESC",
                'patients'      => "SELECT id as ID, pname as Nom, pphone as Telephone, pdob as Naissance, created_at as Inscription FROM patients ORDER BY id DESC",
                'appointments'  => "SELECT a.id, a.patient_name as Patient, a.patient_phone as Telephone, d.docname as Medecin, a.app_date as Date, a.app_type as Type FROM appointments a LEFT JOIN doctor d ON a.doctor_id=d.docid ORDER BY a.app_date DESC",
                'consultations' => "SELECT c.id, a.patient_name as Patient, d.docname as Medecin, c.date_consultation as Date, c.duree_minutes as Duree FROM consultations c LEFT JOIN doctor d ON c.doctor_id=d.docid LEFT JOIN appointments a ON c.appointment_id=a.id ORDER BY c.date_consultation DESC",
            ];
            $res = $con->query($queries[$type] ?? $queries['doctors']);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="psyspace_'.$type.'_'.date('Y-m-d').'.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            if ($res && $res->num_rows > 0) {
                $first = $res->fetch_assoc();
                fputcsv($out, array_keys($first));
                fputcsv($out, $first);
                while ($row = $res->fetch_assoc()) fputcsv($out, $row);
            }
            fclose($out);
            logAction($con, $admin_id, 'export_csv', "Export $type");
            exit();
    }
    header("Location: ".$current_file."?section=".urlencode($section));
    exit();
}

// ── PAGINATION ────────────────────────────────────────────────────────────────
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

// ── SECTION & SEARCH ──────────────────────────────────────────────────────────
$section = $_GET['section'] ?? 'overview';
$search  = trim($_GET['q'] ?? '');
$s       = $con->real_escape_string($search);

// ── STATS GLOBALES ────────────────────────────────────────────────────────────
$stat_doctors       = (int)$con->query("SELECT COUNT(*) c FROM doctor")->fetch_assoc()['c'];
$stat_active        = (int)$con->query("SELECT COUNT(*) c FROM doctor WHERE status='active'")->fetch_assoc()['c'];
$stat_suspended     = (int)$con->query("SELECT COUNT(*) c FROM doctor WHERE status='suspended'")->fetch_assoc()['c'];
$stat_pending       = $stat_doctors - $stat_active - $stat_suspended;
$stat_consultations = (int)$con->query("SELECT COUNT(*) c FROM consultations")->fetch_assoc()['c'];
$stat_appointments  = (int)$con->query("SELECT COUNT(*) c FROM appointments")->fetch_assoc()['c'];
$stat_patients      = (int)$con->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];

// ── ALERTES CRITIQUES ─────────────────────────────────────────────────────────
$critical_count = 0;
$critical_rows  = [];
$res_crit = $con->query("SELECT c.id, c.date_consultation, c.resume_ia, d.docname, a.patient_name
    FROM consultations c
    LEFT JOIN doctor d ON c.doctor_id = d.docid
    LEFT JOIN appointments a ON c.appointment_id = a.id
    WHERE c.resume_ia LIKE '%\"niveau_risque\":\"critique\"%'
    ORDER BY c.date_consultation DESC LIMIT 50");
if ($res_crit) {
    $critical_count = $res_crit->num_rows;
    while ($r = $res_crit->fetch_assoc()) $critical_rows[] = $r;
}

// ── CHART 7 JOURS ─────────────────────────────────────────────────────────────
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date  = date('Y-m-d', strtotime("-$i days"));
    $label = date('d/m', strtotime("-$i days"));
    $appts = (int)$con->query("SELECT COUNT(*) c FROM appointments WHERE DATE(app_date)='$date'")->fetch_assoc()['c'];
    $chart_data[] = ['label' => $label, 'appointments' => $appts];
}

// ── DONNÉES PAR SECTION ───────────────────────────────────────────────────────
$doctors = $patients = $appointments = $consultations = $logs = null;
$total_rows = 0;

if ($section === 'doctors') {
    $where      = $s ? "WHERE docname LIKE '%$s%' OR docemail LIKE '%$s%' OR specialty LIKE '%$s%'" : '';
    $total_rows = (int)$con->query("SELECT COUNT(*) c FROM doctor $where")->fetch_assoc()['c'];
    $doctors    = $con->query("SELECT * FROM doctor $where ORDER BY docid DESC LIMIT $per_page OFFSET $offset");

} elseif ($section === 'patients') {
    $where      = $s ? "WHERE pname LIKE '%$s%' OR pphone LIKE '%$s%'" : '';
    $total_rows = (int)$con->query("SELECT COUNT(*) c FROM patients $where")->fetch_assoc()['c'];
    $patients   = $con->query("SELECT * FROM patients $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");

} elseif ($section === 'appointments') {
    $where        = $s ? "WHERE a.patient_name LIKE '%$s%' OR d.docname LIKE '%$s%'" : '';
    $total_rows   = (int)$con->query("SELECT COUNT(*) c FROM appointments a LEFT JOIN doctor d ON a.doctor_id=d.docid $where")->fetch_assoc()['c'];
    $appointments = $con->query("SELECT a.*, d.docname FROM appointments a LEFT JOIN doctor d ON a.doctor_id=d.docid $where ORDER BY a.app_date DESC LIMIT $per_page OFFSET $offset");

} elseif ($section === 'consultations') {
    $where         = $s ? "WHERE a.patient_name LIKE '%$s%' OR d.docname LIKE '%$s%'" : '';
    $total_rows    = (int)$con->query("SELECT COUNT(*) c FROM consultations c LEFT JOIN doctor d ON c.doctor_id=d.docid LEFT JOIN appointments a ON c.appointment_id=a.id $where")->fetch_assoc()['c'];
    $consultations = $con->query("SELECT c.*, d.docname, a.patient_name FROM consultations c LEFT JOIN doctor d ON c.doctor_id=d.docid LEFT JOIN appointments a ON c.appointment_id=a.id $where ORDER BY c.date_consultation DESC LIMIT $per_page OFFSET $offset");

} elseif ($section === 'logs') {
    $where = $s ? "WHERE action LIKE '%$s%' OR details LIKE '%$s%'" : '';
    if ($con->query("SHOW TABLES LIKE 'admin_logs'")->num_rows > 0) {
        $total_rows = (int)$con->query("SELECT COUNT(*) c FROM admin_logs $where")->fetch_assoc()['c'];
        $logs       = $con->query("SELECT * FROM admin_logs $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
    }
}

$total_pages = $total_rows > 0 ? (int)ceil($total_rows / $per_page) : 1;

// ── STATS PAR MÉDECIN ─────────────────────────────────────────────────────────
$doctor_stats = [];
if ($section === 'doctors' || $section === 'overview') {
    $ds = $con->query("
        SELECT d.docid, d.docname, d.docemail, d.specialty, d.status,
               COUNT(DISTINCT a.id)  AS total_appts,
               COUNT(DISTINCT c.id)  AS total_consults,
               COUNT(DISTINCT a.patient_id) AS total_patients,
               MAX(c.date_consultation) AS last_consult,
               AVG(c.duree_minutes) AS avg_duree
        FROM doctor d
        LEFT JOIN appointments a  ON a.doctor_id  = d.docid
        LEFT JOIN consultations c ON c.doctor_id  = d.docid
        GROUP BY d.docid
        ORDER BY total_consults DESC
    ");
    if ($ds) while ($r = $ds->fetch_assoc()) $doctor_stats[$r['docid']] = $r;
}

// ── DÉTAIL CONSULTATION ───────────────────────────────────────────────────────
$consultation_detail = null;
if (isset($_GET['view_consultation'])) {
    $cid = (int)$_GET['view_consultation'];
    $consultation_detail = $con->query("
        SELECT c.*, d.docname, d.docemail, a.patient_name, a.patient_phone
        FROM consultations c
        LEFT JOIN doctor d ON c.doctor_id = d.docid
        LEFT JOIN appointments a ON c.appointment_id = a.id
        WHERE c.id=$cid
    ")->fetch_assoc();
}

// ── DÉTAIL PATIENT ────────────────────────────────────────────────────────────
$patient_detail       = null;
$patient_consultations = [];
if (isset($_GET['view_patient'])) {
    $pid = (int)$_GET['view_patient'];
    $patient_detail = $con->query("SELECT * FROM patients WHERE id=$pid")->fetch_assoc();
    $res_pc = $con->query("
        SELECT c.*, d.docname, a.patient_name
        FROM consultations c
        LEFT JOIN doctor d ON c.doctor_id = d.docid
        LEFT JOIN appointments a ON c.appointment_id = a.id
        WHERE c.patient_id = $pid
        ORDER BY c.date_consultation DESC
    ");
    if ($res_pc) while ($r = $res_pc->fetch_assoc()) $patient_consultations[] = $r;
}

// ── EDIT DOCTOR ───────────────────────────────────────────────────────────────
$edit_doctor = null;
if (isset($_GET['edit_doctor'])) {
    $did         = (int)$_GET['edit_doctor'];
    $edit_doctor = $con->query("SELECT * FROM doctor WHERE docid=$did")->fetch_assoc();
}

// ── SÉCURITÉ ──────────────────────────────────────────────────────────────────
$sec = [];
if ($section === 'security') {
    $sec['weak_hash']        = $con->query("SELECT docid, docname, docemail FROM doctor WHERE docpassword LIKE '\$2y\$%'");
    $sec['weak_hash_count']  = $sec['weak_hash'] ? $sec['weak_hash']->num_rows : 0;
    
    $sec['stale_tokens']     = $con->query("SELECT docid, docname, docemail, token_expiry FROM doctor WHERE reset_token IS NOT NULL");
    $sec['stale_token_count']= $sec['stale_tokens'] ? $sec['stale_tokens']->num_rows : 0;
    
    $sec['pending_count']    = $stat_pending;
    
    // Admin Brute Force
    $sec['brute_ips_count']  = 0; $sec['brute_force'] = null; $sec['total_failed'] = 0;
    if ($con->query("SHOW TABLES LIKE 'admin_logs'")->num_rows > 0) {
        $sec['brute_force']     = $con->query("SELECT ip, COUNT(*) as attempts, MAX(created_at) as last_attempt FROM admin_logs WHERE action='login_failed' GROUP BY ip HAVING attempts >= 3 ORDER BY attempts DESC LIMIT 50");
        $sec['brute_ips_count'] = $sec['brute_force'] ? $sec['brute_force']->num_rows : 0;
        $sec['total_failed']    = (int)$con->query("SELECT COUNT(*) c FROM admin_logs WHERE action='login_failed'")->fetch_assoc()['c'];
    }

    // Assistante Brute Force (login_attempts)
    $sec['ast_blocked_count'] = 0;
    $sec['ast_tracked']       = null;
    if ($con->query("SHOW TABLES LIKE 'login_attempts'")->num_rows > 0) {
        $sec['ast_blocked_count'] = (int)$con->query("SELECT COUNT(*) c FROM login_attempts WHERE attempts >= 5 AND last_attempt > NOW() - INTERVAL 15 MINUTE")->fetch_assoc()['c'];
        $sec['ast_tracked']       = $con->query("SELECT * FROM login_attempts ORDER BY last_attempt DESC LIMIT 50");
    }

    // Calcul du score
    $score = 100;
    if ($sec['weak_hash_count']   > 0) $score -= min(30, $sec['weak_hash_count']   * 10);
    if ($sec['stale_token_count'] > 0) $score -= min(20, $sec['stale_token_count'] * 5);
    if ($sec['brute_ips_count']   > 0) $score -= min(25, $sec['brute_ips_count']   * 8);
    if ($sec['ast_blocked_count'] > 0) $score -= min(20, $sec['ast_blocked_count'] * 5);
    if ($sec['pending_count']     > 5) $score -= 10;
    
    $score              = max(0, $score);
    $sec['score']       = $score;
    $sec['score_label'] = $score >= 90 ? 'Excellent' : ($score >= 70 ? 'Bon' : ($score >= 50 ? 'Moyen' : 'Critique'));
    $sec['score_color'] = $score >= 90 ? '#22c55e' : ($score >= 70 ? '#3d52a0' : ($score >= 50 ? '#ca8a04' : '#dc2626'));
}

// ── NAV ───────────────────────────────────────────────────────────────────────
$nav_items = [
    'overview'      => ['label' => "Vue d'ensemble", 'icon' => 'M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h7v7h-7z'],
    'doctors'       => ['label' => 'Médecins',        'icon' => 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75M9 11a4 4 0 100-8 4 4 0 000 8z'],
    'patients'      => ['label' => 'Patients',         'icon' => 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z'],
    'appointments'  => ['label' => 'Rendez-vous',      'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    'consultations' => ['label' => 'Consultations',    'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    'alerts'        => ['label' => 'Alertes',          'icon' => 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],
    'logs'          => ['label' => 'Journal',          'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16'],
    'security'      => ['label' => 'Sécurité',         'icon' => 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
];
$counts = [
    'doctors'       => $stat_doctors,
    'patients'      => $stat_patients,
    'appointments'  => $stat_appointments,
    'consultations' => $stat_consultations,
    'alerts'        => $critical_count,
];
$section_label = $nav_items[$section]['label'] ?? 'Dashboard';

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
<link rel="icon" type="image/png" href="/assets/images/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PsySpace Admin</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{font-size:13px;-webkit-font-smoothing:antialiased;}
a{text-decoration:none;color:inherit;}
button,input,select,textarea{font-family:inherit;font-size:inherit;}
::-webkit-scrollbar{width:3px;height:3px;}
::-webkit-scrollbar-thumb{background:var(--line);border-radius:2px;}

:root {
  --bg:#f6f6f8;--surface:#fff;--raised:#f1f1f4;--line:#e3e3e8;--line2:#ebebef;
  --tx:#111118;--tx2:#52525c;--tx3:#9898a8;
  --ac:#3d52a0;--ac-l:#eef0f8;--ac-d:#2d3d80;
  --ok:#15803d;--ok-l:#f0fdf4;--ok-b:#bbf7d0;
  --wa:#b45309;--wa-l:#fefce8;--wa-b:#fde68a;
  --er:#b91c1c;--er-l:#fef2f2;--er-b:#fecaca;
  --pu:#6d28d9;--pu-l:#f5f3ff;--pu-b:#ddd6fe;
  --sb:218px;--r:5px;--r2:8px;
  --sh:0 1px 3px rgba(0,0,0,.06),0 0 0 1px rgba(0,0,0,.05);
  --sh2:0 4px 14px rgba(0,0,0,.09),0 0 0 1px rgba(0,0,0,.04);
  --sh3:0 24px 64px rgba(0,0,0,.16),0 0 0 1px rgba(0,0,0,.08);
}
[data-theme="dark"]{
  --bg:#0c0c12;--surface:#14141e;--raised:#1c1c28;--line:#26263a;--line2:#1e1e2c;
  --tx:#e6e6f0;--tx2:#70708a;--tx3:#40405a;
  --ac:#6b82d4;--ac-l:#181c38;--ac-d:#8098e0;
  --ok:#4ade80;--ok-l:#042010;--ok-b:#14532d;
  --wa:#fbbf24;--wa-l:#1a1200;--wa-b:#78350f;
  --er:#f87171;--er-l:#200000;--er-b:#7f1d1d;
  --pu:#a78bfa;--pu-l:#160e30;--pu-b:#4c1d95;
  --sh:0 1px 3px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.04);
  --sh2:0 4px 14px rgba(0,0,0,.6),0 0 0 1px rgba(255,255,255,.04);
  --sh3:0 24px 64px rgba(0,0,0,.8),0 0 0 1px rgba(255,255,255,.06);
}

body{font-family:'IBM Plex Sans',sans-serif;background:var(--bg);color:var(--tx);display:flex;min-height:100vh;}

.sb{width:var(--sb);flex-shrink:0;background:var(--surface);border-right:1px solid var(--line);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:200;}
.sb-top{padding:16px 14px 12px;border-bottom:1px solid var(--line);}
.sb-wordmark{font-size:11.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--tx);display:flex;align-items:center;gap:8px;}
.sb-wordmark-dot{width:6px;height:6px;border-radius:50%;background:var(--ac);flex-shrink:0;}
.sb-sub{font-size:9.5px;color:var(--tx3);margin-top:4px;padding-left:14px;}
.sb-nav{flex:1;padding:8px 6px;overflow-y:auto;}
.sb-sec{font-size:9px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--tx3);padding:10px 8px 3px;}
.sb-lnk{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:var(--r);font-size:12px;font-weight:400;color:var(--tx2);transition:background .1s,color .1s;}
.sb-lnk:hover{background:var(--raised);color:var(--tx);}
.sb-lnk.on{background:var(--ac-l);color:var(--ac);font-weight:500;}
.sb-lnk svg{width:13px;height:13px;flex-shrink:0;stroke:currentColor;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round;}
.sb-cnt{margin-left:auto;font-size:10px;font-family:'IBM Plex Mono',monospace;color:var(--tx3);}
.sb-lnk.on .sb-cnt{color:var(--ac);}
.sb-cnt.alert{background:var(--er-l);color:var(--er);border:1px solid var(--er-b);padding:0 5px;border-radius:3px;}
.sb-foot{padding:8px 6px;border-top:1px solid var(--line);}
.sb-usr{display:flex;align-items:center;gap:8px;padding:8px;border-radius:var(--r);}
.sb-av{width:26px;height:26px;border-radius:4px;background:var(--ac-l);border:1px solid var(--line);color:var(--ac);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;flex-shrink:0;font-family:'IBM Plex Mono',monospace;}
.sb-un{font-size:11.5px;font-weight:500;}
.sb-ur{font-size:10px;color:var(--tx3);}
.sb-out{display:flex;align-items:center;gap:7px;padding:6px 8px;border-radius:var(--r);font-size:11.5px;color:var(--tx3);background:none;border:none;cursor:pointer;width:100%;transition:background .1s,color .1s;}
.sb-out:hover{background:var(--er-l);color:var(--er);}
.sb-out svg{width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round;}

.main{margin-left:var(--sb);flex:1;display:flex;flex-direction:column;min-width:0;}
.topbar{height:46px;background:var(--surface);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 20px;position:sticky;top:0;z-index:100;}
.tb-path{display:flex;align-items:center;gap:5px;font-size:11.5px;color:var(--tx3);}
.tb-path span{color:var(--tx);font-weight:500;}
.tb-sep{opacity:.35;font-size:10px;}
.tb-r{display:flex;align-items:center;gap:5px;}
.tb-pill{font-size:10px;font-weight:500;padding:2px 9px;border-radius:3px;}
.tb-pill-er{background:var(--er-l);border:1px solid var(--er-b);color:var(--er);}
.tb-pill-wa{background:var(--wa-l);border:1px solid var(--wa-b);color:var(--wa);}
.tb-clock{font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:var(--tx3);background:var(--raised);border:1px solid var(--line);padding:3px 9px;border-radius:var(--r);}
.tb-theme{width:28px;height:28px;border-radius:var(--r);border:1px solid var(--line);background:var(--raised);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .12s;}
.tb-theme:hover{background:var(--ac-l);}
.tb-theme svg{width:12px;height:12px;stroke:var(--tx2);fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round;}

.content{padding:20px;flex:1;}

.stat-strip{display:grid;grid-template-columns:repeat(6,1fr);gap:1px;background:var(--line);border:1px solid var(--line);border-radius:var(--r2);overflow:hidden;margin-bottom:18px;box-shadow:var(--sh);}
.stat-cell{background:var(--surface);padding:13px 14px;transition:background .1s;}
.stat-cell:hover{background:var(--raised);}
.stat-lbl{font-size:9.5px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--tx3);margin-bottom:7px;}
.stat-val{font-size:22px;font-weight:300;letter-spacing:-.04em;color:var(--tx);font-family:'IBM Plex Mono',monospace;line-height:1;}
.stat-val.c-ac{color:var(--ac);}
.stat-val.c-er{color:var(--er);}
.stat-val.c-wa{color:var(--wa);}
.stat-hint{font-size:9.5px;color:var(--tx3);margin-top:4px;}

.toolbar{display:flex;align-items:center;gap:6px;margin-bottom:11px;flex-wrap:wrap;}
.srch{display:flex;align-items:center;gap:7px;background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:6px 11px;flex:1;max-width:340px;transition:border-color .12s;}
.srch:focus-within{border-color:var(--ac);}
.srch svg{width:12px;height:12px;stroke:var(--tx3);flex-shrink:0;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round;}
.srch input{border:none;outline:none;font-size:12px;color:var(--tx);background:transparent;width:100%;}
.srch input::placeholder{color:var(--tx3);}

.btn{display:inline-flex;align-items:center;gap:4px;padding:5px 11px;border-radius:var(--r);font-size:11.5px;font-weight:500;cursor:pointer;transition:all .1s;border:1px solid var(--line);background:var(--surface);color:var(--tx2);white-space:nowrap;}
.btn:hover{background:var(--raised);color:var(--tx);}
.btn-p{background:var(--ac);border-color:var(--ac);color:#fff;}
.btn-p:hover{background:var(--ac-d);}
.btn-er{background:var(--er-l);border-color:var(--er-b);color:var(--er);}
.btn-er:hover{background:var(--er-b);}
.btn-wa{background:var(--wa-l);border-color:var(--wa-b);color:var(--wa);}
.btn-wa:hover{background:var(--wa-b);}
.btn-ok{background:var(--ok-l);border-color:var(--ok-b);color:var(--ok);}
.btn-ok:hover{background:var(--ok-b);}
.btn-ac{background:var(--ac-l);border-color:transparent;color:var(--ac);}
.btn-ac:hover{border-color:var(--ac);}
.btn-pu{background:var(--pu-l);border-color:var(--pu-b);color:var(--pu);}
.btn-pu:hover{background:var(--pu-b);}

.panel{background:var(--surface);border:1px solid var(--line);border-radius:var(--r2);box-shadow:var(--sh);margin-bottom:12px;overflow:hidden;}
.panel:last-child{margin-bottom:0;}
.panel-head{display:flex;align-items:center;justify-content:space-between;padding:10px 15px;border-bottom:1px solid var(--line);background:var(--raised);}
.ph-l{display:flex;align-items:center;gap:9px;}
.ph-t{font-size:12px;font-weight:600;color:var(--tx);}
.ph-c{font-size:9.5px;font-family:'IBM Plex Mono',monospace;color:var(--tx3);background:var(--bg);border:1px solid var(--line);padding:1px 7px;border-radius:3px;}
.ph-a{font-size:11px;color:var(--ac);font-weight:500;}
.ph-a:hover{text-decoration:underline;}

.tw{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead th{padding:7px 14px;text-align:left;font-size:9.5px;font-weight:600;letter-spacing:.09em;text-transform:uppercase;color:var(--tx3);background:var(--raised);border-bottom:1px solid var(--line);white-space:nowrap;}
tbody tr{border-bottom:1px solid var(--line2);transition:background .08s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:var(--raised);}
td{padding:9px 14px;font-size:12.5px;color:var(--tx2);vertical-align:middle;}
td.pr{color:var(--tx);font-weight:500;}
td.mn{font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--tx3);}

.tag{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:500;padding:2px 8px;border-radius:3px;white-space:nowrap;border:1px solid transparent;}
.tag::before{content:'';width:4px;height:4px;border-radius:50%;flex-shrink:0;}
.tag-ok{background:var(--ok-l);color:var(--ok);border-color:var(--ok-b);}
.tag-ok::before{background:var(--ok);}
.tag-wa{background:var(--wa-l);color:var(--wa);border-color:var(--wa-b);}
.tag-wa::before{background:var(--wa);}
.tag-er{background:var(--er-l);color:var(--er);border-color:var(--er-b);}
.tag-er::before{background:var(--er);}
.tag-ac{background:var(--ac-l);color:var(--ac);border-color:rgba(61,82,160,.18);}
.tag-ac::before{background:var(--ac);}
.tag-pu{background:var(--pu-l);color:var(--pu);border-color:var(--pu-b);}
.tag-pu::before{background:var(--pu);}
.tag-n{background:var(--raised);color:var(--tx3);border-color:var(--line);}
.tag-n::before{background:var(--tx3);}
.tag-crit{background:rgba(185,28,28,.12);color:var(--er);border-color:var(--er-b);animation:pulse-crit 1.5s infinite;}
@keyframes pulse-crit{50%{box-shadow:0 0 8px rgba(185,28,28,.3);}}

.ov-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.ov-row{display:flex;align-items:center;justify-content:space-between;padding:8px 15px;border-bottom:1px solid var(--line2);gap:10px;transition:background .08s;}
.ov-row:hover{background:var(--raised);}
.ov-row:last-child{border-bottom:none;}
.ov-name{font-size:12px;font-weight:500;color:var(--tx);}
.ov-sub{font-size:11px;color:var(--tx3);margin-top:1px;}
.ov-mn{font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:var(--tx3);white-space:nowrap;}

.chart-area{padding:14px 16px 10px;}
.chart-bars{display:flex;align-items:flex-end;gap:5px;height:80px;border-bottom:1px solid var(--line);}
.chart-col{display:flex;flex-direction:column;align-items:center;gap:3px;flex:1;}
.chart-bar{width:100%;border-radius:2px 2px 0 0;background:var(--ac);opacity:.65;min-height:2px;transition:opacity .12s;cursor:default;}
.chart-bar:hover{opacity:1;}
.chart-lbl{font-size:9px;color:var(--tx3);font-family:'IBM Plex Mono',monospace;}

.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:18px;}
.dt{font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--tx3);margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--line);}
.kv{display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--line2);font-size:12px;gap:10px;}
.kv:last-child{border-bottom:none;}
.kk{color:var(--tx3);}
.kv2{font-weight:500;color:var(--tx);text-align:right;}
.txt-block{background:var(--raised);border:1px solid var(--line);border-radius:var(--r);padding:10px;font-size:11.5px;line-height:1.7;color:var(--tx2);max-height:160px;overflow-y:auto;white-space:pre-wrap;}

.log-e{display:flex;align-items:flex-start;gap:9px;padding:8px 15px;border-bottom:1px solid var(--line2);transition:background .08s;}
.log-e:hover{background:var(--raised);}
.log-e:last-child{border-bottom:none;}
.log-dot{width:5px;height:5px;border-radius:50%;margin-top:5px;flex-shrink:0;}
.log-act{font-size:11.5px;font-weight:500;color:var(--tx);font-family:'IBM Plex Mono',monospace;}
.log-det{font-size:10.5px;color:var(--tx3);margin-top:2px;}
.log-ts{font-size:9.5px;font-family:'IBM Plex Mono',monospace;color:var(--tx3);margin-left:auto;white-space:nowrap;flex-shrink:0;}

/* PAGINATION */
.pag{display:flex;align-items:center;gap:4px;padding:10px 15px;border-top:1px solid var(--line);justify-content:center;}
.pag a,.pag span{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:var(--r);font-size:11.5px;border:1px solid var(--line);background:var(--surface);color:var(--tx2);transition:all .1s;}
.pag a:hover{background:var(--raised);color:var(--tx);}
.pag span.cur{background:var(--ac);border-color:var(--ac);color:#fff;font-weight:600;}
.pag span.dots{border:none;background:transparent;color:var(--tx3);}

/* STATS MEDECIN */
.doc-stat-bar{height:3px;background:var(--line);border-radius:2px;margin-top:4px;overflow:hidden;}
.doc-stat-fill{height:3px;background:var(--ac);border-radius:2px;}

/* ALERT BANNER */
.alert-banner{display:flex;align-items:center;gap:10px;padding:10px 15px;background:var(--er-l);border:1px solid var(--er-b);border-radius:var(--r2);margin-bottom:14px;font-size:12px;color:var(--er);}
.alert-banner svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0;}

/* SECURITY */
.sec-grid{display:grid;grid-template-columns:188px 1fr;gap:12px;margin-bottom:12px;}
.score-card{background:var(--surface);border:1px solid var(--line);border-radius:var(--r2);padding:22px 14px;text-align:center;box-shadow:var(--sh);}
.score-lbl{font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--tx3);margin-bottom:14px;}
.score-ring{position:relative;width:90px;height:90px;margin:0 auto 10px;}
.score-ring svg{width:90px;height:90px;transform:rotate(-90deg);}
.score-inner{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.score-num{font-size:22px;font-weight:300;letter-spacing:-.04em;font-family:'IBM Plex Mono',monospace;}
.score-den{font-size:8.5px;color:var(--tx3);}
.score-name{font-size:11.5px;font-weight:500;margin-top:4px;}
.risk-row{display:flex;align-items:center;gap:10px;padding:10px 15px;border-bottom:1px solid var(--line2);transition:background .08s;}
.risk-row:hover{background:var(--raised);}
.risk-row:last-child{border-bottom:none;}
.risk-ico{width:30px;height:30px;border-radius:var(--r);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.risk-ico svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round;}
.risk-t{font-size:12px;font-weight:500;color:var(--tx);}
.risk-d{font-size:10.5px;color:var(--tx3);margin-top:1px;}

.empty{padding:44px 14px;text-align:center;}
.empty-t{font-size:12px;font-weight:500;color:var(--tx3);}
.empty-s{font-size:10.5px;color:var(--tx3);margin-top:3px;opacity:.6;}

.fw{padding:18px;}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}
.fc{display:flex;flex-direction:column;gap:4px;}
.fc.full{grid-column:1/-1;}
.fl{font-size:9.5px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--tx3);}
.fi{border:1px solid var(--line);border-radius:var(--r);padding:7px 10px;font-size:12.5px;color:var(--tx);background:var(--surface);outline:none;transition:border-color .12s;}
.fi:focus{border-color:var(--ac);}

.overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:999;opacity:0;pointer-events:none;transition:opacity .16s;}
.overlay.open{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border:1px solid var(--line);border-radius:var(--r2);padding:22px;max-width:380px;width:92%;box-shadow:var(--sh3);transform:translateY(6px);transition:transform .18s cubic-bezier(.33,1,.68,1);}
.overlay.open .modal{transform:translateY(0);}
.modal-tag{font-size:9px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--tx3);margin-bottom:7px;}
.modal h3{font-size:14px;font-weight:600;margin-bottom:5px;}
.modal p{font-size:12px;color:var(--tx2);line-height:1.65;}
.modal-acts{display:flex;gap:5px;justify-content:flex-end;margin-top:16px;}

.panel-hl{border-color:var(--ac)!important;}
.panel-hl .panel-head{background:var(--ac-l);}

@media(max-width:1200px){.stat-strip{grid-template-columns:repeat(3,1fr);}}
@media(max-width:900px){.stat-strip{grid-template-columns:repeat(2,1fr);}.ov-grid{grid-template-columns:1fr;}.sec-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<script>(function(){if(localStorage.getItem('psyadmin_dark')==='1')document.documentElement.setAttribute('data-theme','dark');})();</script>

<!-- SIDEBAR -->
<aside class="sb">
  <div class="sb-top">
    <div class="sb-wordmark"><div class="sb-wordmark-dot"></div>PsySpace</div>
    <div class="sb-sub">Administration</div>
  </div>
  <nav class="sb-nav">
    <div class="sb-sec">Navigation</div>
    <?php foreach($nav_items as $key => $item):
      $cnt = $counts[$key] ?? null;
      $is_alert = ($key === 'alerts' && $critical_count > 0);
    ?>
    <a href="?section=<?= $key ?>" class="sb-lnk <?= $section===$key?'on':'' ?>">
      <svg viewBox="0 0 24 24"><path d="<?= $item['icon'] ?>"/></svg>
      <?= $item['label'] ?>
      <?php if($cnt !== null): ?>
        <span class="sb-cnt <?= $is_alert?'alert':'' ?>"><?= $cnt ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sb-foot">
    <div class="sb-usr">
      <div class="sb-av"><?= $admin_initial ?></div>
      <div><div class="sb-un"><?= $admin_name ?></div><div class="sb-ur">Administrateur</div></div>
    </div>
    <a href="logout.php" class="sb-out">
      <svg viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Déconnexion
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="tb-path">PsySpace <span class="tb-sep">/</span> <span><?= $section_label ?></span></div>
    <div class="tb-r">
      <?php if($critical_count>0): ?>
        <a href="?section=alerts" class="tb-pill tb-pill-er">
          <svg viewBox="0 0 24 24" style="width:10px;height:10px;display:inline-block;stroke:currentColor;fill:none;vertical-align:middle;margin-right:2px;stroke-width:2;"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <?= $critical_count ?> critique<?= $critical_count>1?'s':'' ?>
        </a>
      <?php endif; ?>
      <?php if($stat_pending>0): ?><div class="tb-pill tb-pill-er"><?= $stat_pending ?> en attente</div><?php endif; ?>
      <?php if($stat_suspended>0): ?><div class="tb-pill tb-pill-wa"><?= $stat_suspended ?> suspendu<?= $stat_suspended>1?'s':'' ?></div><?php endif; ?>
      <button class="tb-theme" id="themeBtn">
        <svg id="ico-sun" viewBox="0 0 24 24" style="display:none"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        <svg id="ico-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
      </button>
      <div class="tb-clock" id="clock">--:--:--</div>
    </div>
  </div>

  <div class="content">

    <!-- STAT STRIP -->
    <div class="stat-strip">
      <div class="stat-cell">
        <div class="stat-lbl">Médecins</div>
        <div class="stat-val c-ac"><?= $stat_doctors ?></div>
        <div class="stat-hint"><?= $stat_active ?> actifs</div>
      </div>
      <div class="stat-cell">
        <div class="stat-lbl">Patients</div>
        <div class="stat-val"><?= $stat_patients ?></div>
      </div>
      <div class="stat-cell">
        <div class="stat-lbl">Rendez-vous</div>
        <div class="stat-val"><?= $stat_appointments ?></div>
      </div>
      <div class="stat-cell">
        <div class="stat-lbl">Consultations</div>
        <div class="stat-val"><?= $stat_consultations ?></div>
      </div>
      <div class="stat-cell">
        <div class="stat-lbl">En attente</div>
        <div class="stat-val <?= $stat_pending>0?'c-er':'' ?>"><?= $stat_pending ?></div>
        <?php if($stat_pending>0): ?><div class="stat-hint" style="color:var(--er);">Activation requise</div><?php endif; ?>
      </div>
      <div class="stat-cell">
        <div class="stat-lbl">Alertes critiques</div>
        <div class="stat-val <?= $critical_count>0?'c-er':'' ?>"><?= $critical_count ?></div>
        <?php if($critical_count>0): ?><div class="stat-hint" style="color:var(--er);">Risque critique</div><?php endif; ?>
      </div>
    </div>

    <!-- BANNER ALERTES -->
    <?php if($critical_count>0 && $section!=='alerts'): ?>
    <div class="alert-banner">
      <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <strong><?= $critical_count ?> séance<?= $critical_count>1?'s':'' ?> à risque critique</strong> détectée<?= $critical_count>1?'s':'' ?> —
      <a href="?section=alerts" style="font-weight:600;text-decoration:underline;color:var(--er);">Voir les alertes →</a>
    </div>
    <?php endif; ?>

<?php /* ═══════════════════════ OVERVIEW ═══════════════════════════ */ ?>
<?php if($section==='overview'): ?>

    <div class="panel" style="margin-bottom:12px;">
      <div class="panel-head">
        <div class="ph-l"><div class="ph-t">Rendez-vous · 7 derniers jours</div></div>
        <span style="font-size:10.5px;color:var(--tx3);">par jour</span>
      </div>
      <div class="chart-area">
        <?php $mx=max(1,...array_column($chart_data,'appointments')); ?>
        <div class="chart-bars">
          <?php foreach($chart_data as $d): $h=max(2,round($d['appointments']/$mx*72)); ?>
          <div class="chart-col">
            <div class="chart-bar" style="height:<?= $h ?>px;" title="<?= $d['appointments'] ?> RDV"></div>
            <div class="chart-lbl"><?= $d['label'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="ov-grid">
      <div class="panel">
        <div class="panel-head"><div class="ph-l"><div class="ph-t">Médecins récents</div></div><a href="?section=doctors" class="ph-a">Voir tout</a></div>
        <?php $ld=$con->query("SELECT * FROM doctor ORDER BY docid DESC LIMIT 7");
        if($ld&&$ld->num_rows): while($d=$ld->fetch_assoc()):
          $tc=$d['status']==='active'?'tag-ok':($d['status']==='suspended'?'tag-wa':'tag-n');
          $tl=$d['status']==='active'?'Actif':($d['status']==='suspended'?'Suspendu':'Attente');
        ?>
        <div class="ov-row">
          <div>
            <div class="ov-name"><?= htmlspecialchars($d['docname']) ?></div>
            <div class="ov-sub"><?= htmlspecialchars($d['specialty']??$d['docemail']) ?></div>
            <?php if(isset($doctor_stats[$d['docid']])): $ds=$doctor_stats[$d['docid']]; ?>
            <div style="font-size:10px;color:var(--tx3);margin-top:3px;"><?= $ds['total_consults'] ?> consultation<?= $ds['total_consults']!=1?'s':'' ?> · <?= $ds['total_patients'] ?> patient<?= $ds['total_patients']!=1?'s':'' ?></div>
            <?php endif; ?>
          </div>
          <span class="tag <?= $tc ?>"><?= $tl ?></span>
        </div>
        <?php endwhile; else: ?><div class="empty"><div class="empty-t">Aucun médecin</div></div><?php endif; ?>
      </div>

      <div class="panel">
        <div class="panel-head"><div class="ph-l"><div class="ph-t">Consultations récentes</div></div><a href="?section=consultations" class="ph-a">Voir tout</a></div>
        <?php $lc=$con->query("SELECT c.*,d.docname,a.patient_name FROM consultations c LEFT JOIN doctor d ON c.doctor_id=d.docid LEFT JOIN appointments a ON c.appointment_id=a.id ORDER BY c.date_consultation DESC LIMIT 7");
        if($lc&&$lc->num_rows): while($c=$lc->fetch_assoc()):
          $is_crit = str_contains($c['resume_ia']??'','"niveau_risque":"critique"');
        ?>
        <div class="ov-row">
          <div>
            <div class="ov-name"><?= htmlspecialchars($c['patient_name']??'—') ?></div>
            <div class="ov-sub">Dr. <?= htmlspecialchars($c['docname']??'—') ?></div>
          </div>
          <div style="text-align:right;">
            <div class="ov-mn"><?= date('d/m/Y',strtotime($c['date_consultation'])) ?></div>
            <?php if($is_crit): ?><span class="tag tag-crit" style="font-size:9px;margin-top:3px;">Critique</span><?php endif; ?>
          </div>
        </div>
        <?php endwhile; else: ?><div class="empty"><div class="empty-t">Aucune consultation</div></div><?php endif; ?>
      </div>

      <div class="panel" style="grid-column:1/-1;">
        <div class="panel-head"><div class="ph-l"><div class="ph-t">Derniers rendez-vous</div></div><a href="?section=appointments" class="ph-a">Voir tout</a></div>
        <?php $la=$con->query("SELECT a.*,d.docname FROM appointments a LEFT JOIN doctor d ON a.doctor_id=d.docid ORDER BY a.app_date DESC LIMIT 5");
        if($la&&$la->num_rows): ?>
        <div class="tw"><table>
          <thead><tr><th>Patient</th><th>Médecin</th><th>Date</th><th>Type</th></tr></thead>
          <tbody>
          <?php while($a=$la->fetch_assoc()): ?>
          <tr>
            <td class="pr"><?= htmlspecialchars($a['patient_name']) ?></td>
            <td>Dr. <?= htmlspecialchars($a['docname']??'—') ?></td>
            <td class="mn"><?= date('d/m/Y H:i',strtotime($a['app_date'])) ?></td>
            <td><span class="tag tag-n"><?= htmlspecialchars($a['app_type']??'Consultation') ?></span></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table></div>
        <?php else: ?><div class="empty"><div class="empty-t">Aucun rendez-vous</div></div><?php endif; ?>
      </div>
    </div>

<?php /* ═══════════════════════ ALERTES CRITIQUES ═══════════════════════════ */ ?>
<?php elseif($section==='alerts'): ?>

    <div class="panel">
      <div class="panel-head">
        <div class="ph-l">
          <div class="ph-t" style="color:var(--er);">⚠ Séances à risque critique</div>
          <span class="ph-c"><?= $critical_count ?></span>
        </div>
        <span style="font-size:10.5px;color:var(--tx3);">Détection automatique par analyse IA</span>
      </div>
      <?php if($critical_count > 0): ?>
      <div class="tw"><table>
        <thead><tr><th>ID</th><th>Patient</th><th>Médecin</th><th>Date</th><th>Synthèse</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($critical_rows as $cr):
          $ai_data = json_decode($cr['resume_ia']??'{}', true);
          $synth   = $ai_data['synthese_courte'] ?? ($ai_data['synthese'] ?? '—');
        ?>
        <tr>
          <td class="mn"><?= $cr['id'] ?></td>
          <td class="pr"><?= htmlspecialchars($cr['patient_name']??'—') ?></td>
          <td>Dr. <?= htmlspecialchars($cr['docname']??'—') ?></td>
          <td class="mn"><?= date('d/m/Y H:i',strtotime($cr['date_consultation'])) ?></td>
          <td style="max-width:280px;">
            <span class="tag tag-crit" style="margin-bottom:4px;display:inline-flex;">CRITIQUE</span>
            <p style="font-size:11px;color:var(--tx2);line-height:1.5;margin-top:3px;"><?= htmlspecialchars(mb_substr($synth,0,100,'UTF-8')) ?>…</p>
          </td>
          <td>
            <a href="?section=consultations&view_consultation=<?= $cr['id'] ?>" class="btn btn-er">Voir dossier</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php else: ?>
      <div class="empty"><div class="empty-t">Aucune alerte critique</div><div class="empty-s">Toutes les consultations sont à risque faible ou modéré.</div></div>
      <?php endif; ?>
    </div>

<?php /* ═══════════════════════ DOCTORS ═══════════════════════════ */ ?>
<?php elseif($section==='doctors'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="doctors">
        <div class="srch">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, email, spécialité…">
        </div>
        <button type="submit" class="btn btn-p">Rechercher</button>
        <?php if($search): ?><a href="?section=doctors" class="btn">Effacer</a><?php endif; ?>
      </form>
      <form method="POST" style="margin-left:auto;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="export_csv">
        <input type="hidden" name="export_type" value="doctors">
        <input type="hidden" name="section" value="doctors">
        <button type="submit" class="btn">Export CSV</button>
      </form>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div class="ph-l"><div class="ph-t">Médecins</div><span class="ph-c"><?= $total_rows ?></span></div>
      </div>
      <div class="tw"><table>
        <thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Spécialité</th><th>Consultations</th><th>Patients</th><th>Dernière séance</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if($doctors&&$doctors->num_rows): while($d=$doctors->fetch_assoc()):
          $tc=$d['status']==='active'?'tag-ok':($d['status']==='suspended'?'tag-wa':'tag-n');
          $tl=$d['status']==='active'?'Actif':($d['status']==='suspended'?'Suspendu':'Attente');
          $ds=$doctor_stats[$d['docid']]??[];
          $max_c = max(1, ...array_column($doctor_stats,'total_consults'));
        ?>
        <tr>
          <td class="mn"><?= $d['docid'] ?></td>
          <td class="pr"><?= htmlspecialchars($d['docname']) ?></td>
          <td><?= htmlspecialchars($d['docemail']) ?></td>
          <td><?= htmlspecialchars($d['specialty']??'—') ?></td>
          <td>
            <div style="font-size:12px;font-weight:600;color:var(--tx);"><?= $ds['total_consults']??0 ?></div>
            <div class="doc-stat-bar"><div class="doc-stat-fill" style="width:<?= $max_c>0?round((($ds['total_consults']??0)/$max_c)*100):0 ?>%"></div></div>
          </td>
          <td class="mn"><?= $ds['total_patients']??0 ?></td>
          <td class="mn"><?= !empty($ds['last_consult'])?date('d/m/Y',strtotime($ds['last_consult'])):'—' ?></td>
          <td><span class="tag <?= $tc ?>"><?= $tl ?></span></td>
          <td>
            <div style="display:flex;gap:3px;flex-wrap:wrap;">
              <a href="?section=doctors&edit_doctor=<?= $d['docid'] ?>" class="btn btn-ac">Modifier</a>
              <button class="btn btn-pu" data-modal="resetpw" data-docid="<?= $d['docid'] ?>" data-docname="<?= htmlspecialchars($d['docname'],ENT_QUOTES) ?>">MDP</button>
              <?php if($d['status']==='active'): ?>
              <button class="btn btn-wa" data-modal="toggle" data-action="toggle_doctor" data-docid="<?= $d['docid'] ?>" data-newstatus="suspended" data-title="Suspendre ce médecin ?" data-msg="Dr. <?= htmlspecialchars($d['docname'],ENT_QUOTES) ?> ne pourra plus se connecter.">Suspendre</button>
              <?php elseif($d['status']==='suspended'): ?>
              <button class="btn btn-ok" data-modal="toggle" data-action="toggle_doctor" data-docid="<?= $d['docid'] ?>" data-newstatus="active" data-title="Réactiver ?" data-msg="Dr. <?= htmlspecialchars($d['docname'],ENT_QUOTES) ?> pourra se reconnecter.">Réactiver</button>
              <?php else: ?>
              <button class="btn btn-ok" data-modal="toggle" data-action="toggle_doctor" data-docid="<?= $d['docid'] ?>" data-newstatus="active" data-title="Activer ?" data-msg="Dr. <?= htmlspecialchars($d['docname'],ENT_QUOTES) ?> pourra se connecter.">Activer</button>
              <?php endif; ?>
              <button class="btn btn-er" data-modal="delete" data-action="delete_doctor" data-rid="<?= $d['docid'] ?>" data-title="Supprimer ce médecin ?" data-msg="Action irréversible.">Suppr.</button>
            </div>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="9"><div class="empty"><div class="empty-t">Aucun médecin<?= $search?' pour cette recherche':'' ?></div></div></td></tr>
        <?php endif; ?>
        </tbody>
      </table></div>
      <?php if($total_pages>1): ?>
      <div class="pag">
        <?php
        $base = "?section=doctors".($search?"&q=".urlencode($search):'');
        if($page>1) echo '<a href="'.$base.'&page='.($page-1).'">‹</a>';
        for($i=1;$i<=$total_pages;$i++){
          if($i===$page) echo '<span class="cur">'.$i.'</span>';
          elseif($i===1||$i===$total_pages||abs($i-$page)<=1) echo '<a href="'.$base.'&page='.$i.'">'.$i.'</a>';
          elseif(abs($i-$page)===2) echo '<span class="dots">…</span>';
        }
        if($page<$total_pages) echo '<a href="'.$base.'&page='.($page+1).'">›</a>';
        ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if($edit_doctor): ?>
    <div class="panel panel-hl">
      <div class="panel-head">
        <div class="ph-l"><div class="ph-t" style="color:var(--ac);">Modifier · <?= htmlspecialchars($edit_doctor['docname']) ?></div></div>
        <a href="?section=doctors" class="btn">Fermer</a>
      </div>
      <div class="fw">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="edit_doctor">
          <input type="hidden" name="docid" value="<?= $edit_doctor['docid'] ?>">
          <input type="hidden" name="section" value="doctors">
          <div class="fg">
            <div class="fc"><label class="fl">Nom complet</label><input type="text" name="docname" class="fi" value="<?= htmlspecialchars($edit_doctor['docname']) ?>" required></div>
            <div class="fc"><label class="fl">Email</label><input type="email" name="docemail" class="fi" value="<?= htmlspecialchars($edit_doctor['docemail']) ?>" required></div>
            <div class="fc full"><label class="fl">Spécialité</label><input type="text" name="specialty" class="fi" value="<?= htmlspecialchars($edit_doctor['specialty']??'') ?>"></div>
          </div>
          <div style="display:flex;gap:5px;"><button type="submit" class="btn btn-p">Enregistrer</button><a href="?section=doctors" class="btn">Annuler</a></div>
        </form>
      </div>
    </div>
    <?php endif; ?>

<?php /* ═══════════════════════ PATIENTS ═══════════════════════════ */ ?>
<?php elseif($section==='patients'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="patients">
        <div class="srch"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, téléphone…"></div>
        <button type="submit" class="btn btn-p">Rechercher</button>
        <?php if($search): ?><a href="?section=patients" class="btn">Effacer</a><?php endif; ?>
      </form>
      <form method="POST" style="margin-left:auto;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="export_csv">
        <input type="hidden" name="export_type" value="patients">
        <input type="hidden" name="section" value="patients">
        <button type="submit" class="btn">Export CSV</button>
      </form>
    </div>

    <!-- DÉTAIL PATIENT -->
    <?php if($patient_detail): ?>
    <div class="panel panel-hl" style="margin-bottom:12px;">
      <div class="panel-head">
        <div class="ph-l"><div class="ph-t" style="color:var(--ac);">Dossier · <?= htmlspecialchars($patient_detail['pname']) ?></div></div>
        <a href="?section=patients" class="btn">Fermer</a>
      </div>
      <div class="detail-grid">
        <div>
          <div class="dt">Informations patient</div>
          <div class="kv"><span class="kk">Nom</span><span class="kv2"><?= htmlspecialchars($patient_detail['pname']) ?></span></div>
          <div class="kv"><span class="kk">Téléphone</span><span class="kv2"><?= htmlspecialchars($patient_detail['pphone']??'—') ?></span></div>
          <div class="kv"><span class="kk">Naissance</span><span class="kv2"><?= $patient_detail['pdob']?date('d/m/Y',strtotime($patient_detail['pdob'])):'—' ?></span></div>
          <div class="kv"><span class="kk">Inscription</span><span class="kv2"><?= date('d/m/Y',strtotime($patient_detail['created_at'])) ?></span></div>
          <div class="kv"><span class="kk">Consultations</span><span class="kv2"><?= count($patient_consultations) ?></span></div>
        </div>
        <div>
          <div class="dt">Historique des consultations</div>
          <?php if($patient_consultations): foreach($patient_consultations as $pc):
            $is_crit = str_contains($pc['resume_ia']??'','"niveau_risque":"critique"');
          ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line2);gap:8px;">
            <div>
              <div style="font-size:12px;font-weight:500;color:var(--tx);"><?= date('d/m/Y',strtotime($pc['date_consultation'])) ?> · Dr. <?= htmlspecialchars($pc['docname']??'—') ?></div>
              <div style="font-size:10.5px;color:var(--tx3);"><?= $pc['duree_minutes']>0?$pc['duree_minutes'].' min':'—' ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:5px;">
              <?php if($is_crit): ?><span class="tag tag-crit">Critique</span><?php endif; ?>
              <a href="?section=consultations&view_consultation=<?= $pc['id'] ?>" class="btn btn-ac" style="padding:3px 8px;font-size:10px;">Voir</a>
            </div>
          </div>
          <?php endforeach; else: ?>
          <div style="padding:20px 0;text-align:center;color:var(--tx3);font-size:11.5px;">Aucune consultation archivée</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-head"><div class="ph-l"><div class="ph-t">Patients</div><span class="ph-c"><?= $total_rows ?></span></div></div>
      <div class="tw"><table>
        <thead><tr><th>ID</th><th>Nom</th><th>Téléphone</th><th>Naissance</th><th>Inscription</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if($patients&&$patients->num_rows): while($p=$patients->fetch_assoc()): ?>
        <tr>
          <td class="mn"><?= $p['id'] ?></td>
          <td class="pr"><?= htmlspecialchars($p['pname']) ?></td>
          <td><?= htmlspecialchars($p['pphone']??'—') ?></td>
          <td class="mn"><?= $p['pdob']?date('d/m/Y',strtotime($p['pdob'])):'—' ?></td>
          <td class="mn"><?= date('d/m/Y',strtotime($p['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:3px;">
              <a href="?section=patients&view_patient=<?= $p['id'] ?>" class="btn btn-ac">Dossier</a>
              <button class="btn btn-er" data-modal="delete" data-action="delete_patient" data-rid="<?= $p['id'] ?>" data-title="Supprimer ce patient ?" data-msg="<?= htmlspecialchars($p['pname'],ENT_QUOTES) ?> sera supprimé définitivement.">Suppr.</button>
            </div>
          </td>
        </tr>
        <?php endwhile; else: ?><tr><td colspan="6"><div class="empty"><div class="empty-t">Aucun patient<?= $search?' pour cette recherche':'' ?></div></div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
      <?php if($total_pages>1): ?>
      <div class="pag">
        <?php
        $base="?section=patients".($search?"&q=".urlencode($search):'');
        if($page>1) echo '<a href="'.$base.'&page='.($page-1).'">‹</a>';
        for($i=1;$i<=$total_pages;$i++){
          if($i===$page) echo '<span class="cur">'.$i.'</span>';
          elseif($i===1||$i===$total_pages||abs($i-$page)<=1) echo '<a href="'.$base.'&page='.$i.'">'.$i.'</a>';
          elseif(abs($i-$page)===2) echo '<span class="dots">…</span>';
        }
        if($page<$total_pages) echo '<a href="'.$base.'&page='.($page+1).'">›</a>';
        ?>
      </div>
      <?php endif; ?>
    </div>

<?php /* ═══════════════════════ APPOINTMENTS ═══════════════════════════ */ ?>
<?php elseif($section==='appointments'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="appointments">
        <div class="srch"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Patient, médecin…"></div>
        <button type="submit" class="btn btn-p">Rechercher</button>
        <?php if($search): ?><a href="?section=appointments" class="btn">Effacer</a><?php endif; ?>
      </form>
      <form method="POST" style="margin-left:auto;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="export_csv">
        <input type="hidden" name="export_type" value="appointments">
        <input type="hidden" name="section" value="appointments">
        <button type="submit" class="btn">Export CSV</button>
      </form>
    </div>
    <div class="panel">
      <div class="panel-head"><div class="ph-l"><div class="ph-t">Rendez-vous</div><span class="ph-c"><?= $total_rows ?></span></div></div>
      <div class="tw"><table>
        <thead><tr><th>ID</th><th>Patient</th><th>Téléphone</th><th>Médecin</th><th>Date</th><th>Type</th><th>Action</th></tr></thead>
        <tbody>
        <?php if($appointments&&$appointments->num_rows): while($a=$appointments->fetch_assoc()): ?>
        <tr>
          <td class="mn"><?= $a['id'] ?></td>
          <td class="pr"><?= htmlspecialchars($a['patient_name']) ?></td>
          <td><?= htmlspecialchars($a['patient_phone']??'—') ?></td>
          <td>Dr. <?= htmlspecialchars($a['docname']??'—') ?></td>
          <td class="mn"><?= date('d/m/Y H:i',strtotime($a['app_date'])) ?></td>
          <td><span class="tag tag-n"><?= htmlspecialchars($a['app_type']??'Consultation') ?></span></td>
          <td>
            <button class="btn btn-er" data-modal="delete" data-action="delete_appointment" data-rid="<?= $a['id'] ?>" data-title="Supprimer ce rendez-vous ?" data-msg="Le rendez-vous du <?= date('d/m/Y',strtotime($a['app_date'])) ?> sera supprimé.">Suppr.</button>
          </td>
        </tr>
        <?php endwhile; else: ?><tr><td colspan="7"><div class="empty"><div class="empty-t">Aucun rendez-vous<?= $search?' pour cette recherche':'' ?></div></div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
      <?php if($total_pages>1): ?>
      <div class="pag">
        <?php
        $base="?section=appointments".($search?"&q=".urlencode($search):'');
        if($page>1) echo '<a href="'.$base.'&page='.($page-1).'">‹</a>';
        for($i=1;$i<=$total_pages;$i++){
          if($i===$page) echo '<span class="cur">'.$i.'</span>';
          elseif($i===1||$i===$total_pages||abs($i-$page)<=1) echo '<a href="'.$base.'&page='.$i.'">'.$i.'</a>';
          elseif(abs($i-$page)===2) echo '<span class="dots">…</span>';
        }
        if($page<$total_pages) echo '<a href="'.$base.'&page='.($page+1).'">›</a>';
        ?>
      </div>
      <?php endif; ?>
    </div>

<?php /* ═══════════════════════ CONSULTATIONS ═══════════════════════════ */ ?>
<?php elseif($section==='consultations'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="consultations">
        <div class="srch"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Patient, médecin…"></div>
        <button type="submit" class="btn btn-p">Rechercher</button>
        <?php if($search): ?><a href="?section=consultations" class="btn">Effacer</a><?php endif; ?>
      </form>
      <form method="POST" style="margin-left:auto;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action" value="export_csv">
        <input type="hidden" name="export_type" value="consultations">
        <input type="hidden" name="section" value="consultations">
        <button type="submit" class="btn">Export CSV</button>
      </form>
    </div>

    <?php if($consultation_detail): ?>
    <div class="panel panel-hl" style="margin-bottom:12px;">
      <div class="panel-head">
        <div class="ph-l"><div class="ph-t" style="color:var(--ac);">Consultation #<?= $consultation_detail['id'] ?></div></div>
        <a href="?section=consultations" class="btn">Fermer</a>
      </div>
      <div class="detail-grid">
        <div>
          <div class="dt">Informations</div>
          <div class="kv"><span class="kk">Patient</span><span class="kv2"><?= htmlspecialchars($consultation_detail['patient_name']??'—') ?></span></div>
          <div class="kv"><span class="kk">Téléphone</span><span class="kv2"><?= htmlspecialchars($consultation_detail['patient_phone']??'—') ?></span></div>
          <div class="kv"><span class="kk">Médecin</span><span class="kv2">Dr. <?= htmlspecialchars($consultation_detail['docname']??'—') ?></span></div>
          <div class="kv"><span class="kk">Date</span><span class="kv2"><?= date('d/m/Y H:i',strtotime($consultation_detail['date_consultation'])) ?></span></div>
          <div class="kv"><span class="kk">Durée</span><span class="kv2"><?= $consultation_detail['duree_minutes']>0?$consultation_detail['duree_minutes'].' min':'—' ?></span></div>
          <?php
            $ai_detail = json_decode($consultation_detail['resume_ia']??'{}',true);
            $niv = $ai_detail['niveau_risque'] ?? null;
            $niv_class = ['critique'=>'tag-crit','élevé'=>'tag-er','modéré'=>'tag-wa','faible'=>'tag-ok'][$niv]??'tag-n';
          ?>
          <div class="kv"><span class="kk">Niveau risque</span><span class="kv2"><?= $niv?'<span class="tag '.$niv_class.'">'.strtoupper($niv).'</span>':'—' ?></span></div>
        </div>
        <div>
          <?php if(!empty($consultation_detail['resume_ia'])): ?>
          <div class="dt">Résumé IA</div>
          <div class="txt-block"><?= htmlspecialchars($consultation_detail['resume_ia']) ?></div>
          <?php endif; ?>
          <?php if(!empty($consultation_detail['transcription_brute'])): ?>
          <div class="dt" style="margin-top:12px;">Transcription</div>
          <div class="txt-block"><?= htmlspecialchars($consultation_detail['transcription_brute']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-head"><div class="ph-l"><div class="ph-t">Consultations archivées</div><span class="ph-c"><?= $total_rows ?></span></div></div>
      <div class="tw"><table>
        <thead><tr><th>ID</th><th>Patient</th><th>Médecin</th><th>Date</th><th>Durée</th><th>Risque</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if($consultations&&$consultations->num_rows): while($c=$consultations->fetch_assoc()):
          $ai_c  = json_decode($c['resume_ia']??'{}',true);
          $niv_c = $ai_c['niveau_risque']??null;
          $nc    = ['critique'=>'tag-crit','élevé'=>'tag-er','modéré'=>'tag-wa','faible'=>'tag-ok'][$niv_c]??'tag-n';
        ?>
        <tr>
          <td class="mn"><?= $c['id'] ?></td>
          <td class="pr"><?= htmlspecialchars($c['patient_name']??'—') ?></td>
          <td>Dr. <?= htmlspecialchars($c['docname']??'—') ?></td>
          <td class="mn"><?= date('d/m/Y H:i',strtotime($c['date_consultation'])) ?></td>
          <td class="mn"><?= $c['duree_minutes']>0?$c['duree_minutes'].' min':'—' ?></td>
          <td><?= $niv_c?'<span class="tag '.$nc.'">'.strtoupper($niv_c).'</span>':'<span class="tag tag-n">—</span>' ?></td>
          <td>
            <div style="display:flex;gap:3px;">
              <a href="?section=consultations&view_consultation=<?= $c['id'] ?>" class="btn btn-ac">Voir</a>
              <button class="btn btn-er" data-modal="delete" data-action="delete_consultation" data-rid="<?= $c['id'] ?>" data-title="Supprimer cette consultation ?" data-msg="La transcription et le résumé IA seront perdus définitivement.">Suppr.</button>
            </div>
          </td>
        </tr>
        <?php endwhile; else: ?><tr><td colspan="7"><div class="empty"><div class="empty-t">Aucune consultation<?= $search?' pour cette recherche':'' ?></div></div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
      <?php if($total_pages>1): ?>
      <div class="pag">
        <?php
        $base="?section=consultations".($search?"&q=".urlencode($search):'');
        if($page>1) echo '<a href="'.$base.'&page='.($page-1).'">‹</a>';
        for($i=1;$i<=$total_pages;$i++){
          if($i===$page) echo '<span class="cur">'.$i.'</span>';
          elseif($i===1||$i===$total_pages||abs($i-$page)<=1) echo '<a href="'.$base.'&page='.$i.'">'.$i.'</a>';
          elseif(abs($i-$page)===2) echo '<span class="dots">…</span>';
        }
        if($page<$total_pages) echo '<a href="'.$base.'&page='.($page+1).'">›</a>';
        ?>
      </div>
      <?php endif; ?>
    </div>

<?php /* ═════════��═════════════ LOGS ═══════════════════════════ */ ?>
<?php elseif($section==='logs'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="logs">
        <div class="srch"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Filtrer…"></div>
        <button type="submit" class="btn btn-p">Filtrer</button>
        <?php if($search): ?><a href="?section=logs" class="btn">Effacer</a><?php endif; ?>
      </form>
      <span style="margin-left:auto;font-size:10.5px;color:var(--tx3);"><?= $total_rows ?> entrées</span>
    </div>
    <div class="panel">
      <div class="panel-head"><div class="ph-l"><div class="ph-t">Journal d'activité admin</div></div></div>
      <?php
      $lc=['delete_doctor'=>'var(--er)','delete_patient'=>'var(--er)','delete_appointment'=>'var(--er)','delete_consultation'=>'var(--er)','edit_doctor'=>'var(--ac)','reset_password'=>'var(--pu)','toggle_doctor'=>'var(--wa)','export_csv'=>'var(--ok)','login_failed'=>'var(--er)','admin_login'=>'var(--ok)','doctor_login'=>'var(--ac)','clean_tokens'=>'var(--wa)','unblock_ip'=>'var(--ok)'];
      if($logs&&$logs->num_rows): while($l=$logs->fetch_assoc()):
        $col=$lc[$l['action']]??'var(--tx3)';
      ?>
      <div class="log-e">
        <div class="log-dot" style="background:<?= $col ?>;"></div>
        <div style="flex:1;min-width:0;"><div class="log-act"><?= htmlspecialchars($l['action']) ?></div><div class="log-det"><?= htmlspecialchars($l['details']) ?> · IP <?= htmlspecialchars($l['ip']) ?></div></div>
        <div class="log-ts"><?= date('d/m/Y H:i:s',strtotime($l['created_at'])) ?></div>
      </div>
      <?php endwhile; else: ?>
      <div class="empty"><div class="empty-t">Aucun log<?= $search?' pour cette recherche':'' ?></div></div>
      <?php endif; ?>
      <?php if($total_pages>1): ?>
      <div class="pag">
        <?php
        $base="?section=logs".($search?"&q=".urlencode($search):'');
        if($page>1) echo '<a href="'.$base.'&page='.($page-1).'">‹</a>';
        for($i=1;$i<=$total_pages;$i++){
          if($i===$page) echo '<span class="cur">'.$i.'</span>';
          elseif($i===1||$i===$total_pages||abs($i-$page)<=1) echo '<a href="'.$base.'&page='.$i.'">'.$i.'</a>';
          elseif(abs($i-$page)===2) echo '<span class="dots">…</span>';
        }
        if($page<$total_pages) echo '<a href="'.$base.'&page='.($page+1).'">›</a>';
        ?>
      </div>
      <?php endif; ?>
    </div>

<?php /* ═══════════════════════ SECURITY ═══════════════════════════ */ ?>
<?php elseif($section==='security'): ?>

    <div class="sec-grid">
      <div class="score-card">
        <div class="score-lbl">Score sécurité</div>
        <div class="score-ring">
          <svg viewBox="0 0 36 36">
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--line)" stroke-width="2"/>
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?= $sec['score_color'] ?>" stroke-width="2" stroke-dasharray="<?= $sec['score'] ?> 100" stroke-linecap="round"/>
          </svg>
          <div class="score-inner"><span class="score-num" style="color:<?= $sec['score_color'] ?>;"><?= $sec['score'] ?></span><span class="score-den">/100</span></div>
        </div>
        <div class="score-name" style="color:<?= $sec['score_color'] ?>;"><?= $sec['score_label'] ?></div>
      </div>
      <div class="panel" style="margin-bottom:0;">
        <div class="panel-head"><div class="ph-l"><div class="ph-t">Risques détectés</div></div></div>
        
        <!-- Brute Force Admin -->
        <div class="risk-row">
          <div class="risk-ico" style="background:<?= $sec['brute_ips_count']>0?'var(--er-l)':'var(--ok-l)' ?>;color:<?= $sec['brute_ips_count']>0?'var(--er)':'var(--ok)' ?>;">
            <svg viewBox="0 0 24 24"><?php echo $sec['brute_ips_count']>0?'<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>':'<polyline points="20 6 9 17 4 12"/>'; ?></svg>
          </div>
          <div style="flex:1;"><div class="risk-t">Portail Admin (Brute force)</div><div class="risk-d"><?= $sec['brute_ips_count']>0?"$sec[brute_ips_count] IP(s) suspectes · $sec[total_failed] échec(s)":'Aucune IP suspecte' ?></div></div>
          <span class="tag <?= $sec['brute_ips_count']>0?'tag-er':'tag-ok' ?>"><?= $sec['brute_ips_count']>0?"$sec[brute_ips_count] IP":'OK' ?></span>
        </div>

        <!-- Brute Force Assistante -->
        <div class="risk-row">
          <div class="risk-ico" style="background:<?= $sec['ast_blocked_count']>0?'var(--er-l)':'var(--ok-l)' ?>;color:<?= $sec['ast_blocked_count']>0?'var(--er)':'var(--ok)' ?>;">
            <svg viewBox="0 0 24 24"><?php echo $sec['ast_blocked_count']>0?'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>':'<polyline points="20 6 9 17 4 12"/>'; ?></svg>
          </div>
          <div style="flex:1;"><div class="risk-t">Portail Secrétariat (Brute force)</div><div class="risk-d"><?= $sec['ast_blocked_count']>0?"$sec[ast_blocked_count] IP(s) actuellement bloquée(s)":'Aucune adresse IP bloquée' ?></div></div>
          <span class="tag <?= $sec['ast_blocked_count']>0?'tag-er':'tag-ok' ?>"><?= $sec['ast_blocked_count']>0?"$sec[ast_blocked_count] Bloquée(s)":'OK' ?></span>
        </div>

        <!-- Comptes Faibles -->
        <div class="risk-row">
          <div class="risk-ico" style="background:<?= $sec['weak_hash_count']>0?'var(--wa-l)':'var(--ok-l)' ?>;color:<?= $sec['weak_hash_count']>0?'var(--wa)':'var(--ok)' ?>;">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          </div>
          <div style="flex:1;"><div class="risk-t">Hash bcrypt (non-Argon2)</div><div class="risk-d"><?= $sec['weak_hash_count']>0?"$sec[weak_hash_count] compte(s) à migrer":'Tous les comptes utilisent Argon2id' ?></div></div>
          <span class="tag <?= $sec['weak_hash_count']>0?'tag-wa':'tag-ok' ?>"><?= $sec['weak_hash_count']>0?"$sec[weak_hash_count]":'OK' ?></span>
        </div>
        
        <!-- Tokens -->
        <div class="risk-row">
          <div class="risk-ico" style="background:<?= $sec['stale_token_count']>0?'var(--wa-l)':'var(--ok-l)' ?>;color:<?= $sec['stale_token_count']>0?'var(--wa)':'var(--ok)' ?>;">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div style="flex:1;"><div class="risk-t">Tokens de reset</div><div class="risk-d"><?= $sec['stale_token_count']>0?"$sec[stale_token_count] token(s) résiduel(s)":'Aucun token résiduel' ?></div></div>
          <?php if($sec['stale_token_count']>0): ?>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="clean_tokens">
            <input type="hidden" name="section" value="security">
            <button type="submit" class="btn btn-wa">Nettoyer</button>
          </form>
          <?php else: ?><span class="tag tag-ok">OK</span><?php endif; ?>
        </div>

        <!-- En attente -->
        <div class="risk-row">
          <div class="risk-ico" style="background:<?= $sec['pending_count']>5?'var(--wa-l)':'var(--ok-l)' ?>;color:<?= $sec['pending_count']>5?'var(--wa)':'var(--ok)' ?>;">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
          </div>
          <div style="flex:1;"><div class="risk-t">Comptes en attente</div><div class="risk-d"><?= $sec['pending_count']>0?"$sec[pending_count] compte(s) non activé(s)":'Tous les comptes sont actifs' ?></div></div>
          <span class="tag <?= $sec['pending_count']>5?'tag-wa':'tag-n' ?>"><?= $sec['pending_count'] ?></span>
        </div>

        <?php if($critical_count>0): ?>
        <div class="risk-row">
          <div class="risk-ico" style="background:var(--er-l);color:var(--er);">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <div style="flex:1;"><div class="risk-t">Alertes cliniques critiques</div><div class="risk-d"><?= $critical_count ?> séance<?= $critical_count>1?'s':'' ?> à risque critique détectée<?= $critical_count>1?'s':'' ?></div></div>
          <a href="?section=alerts" class="btn btn-er">Voir</a>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- TABLEAU : IP SECRÉTARIAT TRACKÉES -->
    <?php if($sec['ast_tracked'] && $sec['ast_tracked']->num_rows > 0): ?>
    <div class="panel" style="margin-bottom:12px;">
      <div class="panel-head"><div class="ph-l"><div class="ph-t">Sécurité Secrétariat · Historique des IPs</div><span class="ph-c"><?= $sec['ast_tracked']->num_rows ?></span></div></div>
      <div class="tw"><table>
        <thead><tr><th>IP Assistante</th><th>Tentatives échouées</th><th>Dernier échec</th><th>Statut</th><th>Action</th></tr></thead>
        <tbody>
        <?php while($la = $sec['ast_tracked']->fetch_assoc()): 
          $is_blocked = ($la['attempts'] >= 5 && strtotime($la['last_attempt']) > time() - 900);
        ?>
        <tr>
          <td class="mn" style="<?= $is_blocked ? 'color:var(--er);font-weight:500;' : '' ?>"><?= htmlspecialchars($la['ip_address']) ?></td>
          <td><span class="tag <?= $is_blocked ? 'tag-er' : ($la['attempts']>=3?'tag-wa':'tag-n') ?>"><?= $la['attempts'] ?></span></td>
          <td class="mn"><?= date('d/m/Y H:i:s',strtotime($la['last_attempt'])) ?></td>
          <td><span class="tag <?= $is_blocked ? 'tag-er' : 'tag-ok' ?>"><?= $is_blocked ? 'Bloquée (15 min)' : 'Surveillée' ?></span></td>
          <td>
            <button class="btn <?= $is_blocked ? 'btn-ok' : 'btn' ?>" data-modal="unblock" data-action="unblock_ip" data-targetip="<?= htmlspecialchars($la['ip_address']) ?>" data-title="Débloquer cette IP ?" data-msg="L'IP <?= htmlspecialchars($la['ip_address']) ?> sera supprimée du registre et pourra à nouveau tenter de se connecter au portail assistante.">Débloquer</button>
          </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table></div>
    </div>
    <?php endif; ?>

    <!-- TABLEAU : IP ADMIN SUSPECTES -->
    <?php if($sec['brute_force']&&$sec['brute_force']->num_rows>0): ?>
    <div class="panel" style="margin-bottom:12px;">
      <div class="panel-head"><div class="ph-l"><div class="ph-t">Sécurité Admin · IPs suspectes</div><span class="ph-c"><?= $sec['brute_ips_count'] ?></span></div></div>
      <div class="tw"><table>
        <thead><tr><th>IP Admin</th><th>Tentatives</th><th>Dernière tentative</th><th>Niveau</th></tr></thead>
        <tbody>
        <?php while($bf=$sec['brute_force']->fetch_assoc()): ?>
        <tr>
          <td class="mn" style="color:var(--er);font-weight:500;"><?= htmlspecialchars($bf['ip']) ?></td>
          <td><span class="tag <?= $bf['attempts']>=10?'tag-er':($bf['attempts']>=5?'tag-wa':'tag-n') ?>"><?= $bf['attempts'] ?></span></td>
          <td class="mn"><?= date('d/m/Y H:i',strtotime($bf['last_attempt'])) ?></td>
          <td><span class="tag <?= $bf['attempts']>=10?'tag-er':($bf['attempts']>=5?'tag-wa':'tag-ac') ?>"><?= $bf['attempts']>=10?'Critique':($bf['attempts']>=5?'Élevé':'Modéré') ?></span></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table></div>
    </div>
    <?php endif; ?>

    <?php if($sec['weak_hash_count']>0): ?>
    <div class="panel">
      <div class="panel-head"><div class="ph-l"><div class="ph-t">Comptes bcrypt à migrer</div><span class="ph-c"><?= $sec['weak_hash_count'] ?></span></div></div>
      <div class="tw"><table>
        <thead><tr><th>ID</th><th>Médecin</th><th>Email</th><th>Action</th></tr></thead>
        <tbody>
        <?php while($wh=$sec['weak_hash']->fetch_assoc()): ?>
        <tr>
          <td class="mn"><?= $wh['docid'] ?></td>
          <td class="pr"><?= htmlspecialchars($wh['docname']) ?></td>
          <td><?= htmlspecialchars($wh['docemail']) ?></td>
          <td><button class="btn btn-pu" data-modal="resetpw" data-docid="<?= $wh['docid'] ?>" data-docname="<?= htmlspecialchars($wh['docname'],ENT_QUOTES) ?>">Réinitialiser MDP</button></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table></div>
    </div>
    <?php endif; ?>

<?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->

<!-- MODAL CONFIRM -->
<div class="overlay" id="ov-confirm">
  <div class="modal">
    <div class="modal-tag" id="mc-tag">Confirmer</div>
    <h3 id="mc-title"></h3>
    <p id="mc-msg"></p>
    <div class="modal-acts">
      <button class="btn" id="mc-cancel">Annuler</button>
      <form id="mc-form" method="POST" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action"     id="mc-action">
        <input type="hidden" name="rid"        id="mc-rid">
        <input type="hidden" name="docid"      id="mc-docid">
        <input type="hidden" name="target_ip"  id="mc-targetip">
        <input type="hidden" name="new_status" id="mc-ns">
        <input type="hidden" name="section"    value="<?= $section ?>">
        <button type="submit" class="btn btn-er" id="mc-confirm">Confirmer</button>
      </form>
    </div>
  </div>
</div>

<!-- MODAL RESET MDP -->
<div class="overlay" id="ov-reset">
  <div class="modal">
    <div class="modal-tag">Sécurité</div>
    <h3>Réinitialiser le mot de passe</h3>
    <p id="rp-desc" style="margin-bottom:14px;"></p>
    <form method="POST">
      <input type="hidden" name="csrf_token"    value="<?= $csrf ?>">
      <input type="hidden" name="action"        value="reset_password">
      <input type="hidden" name="section"       value="<?= $section ?>">
      <input type="hidden" name="docid"         id="rp-docid">
      <div class="fc" style="margin-bottom:13px;">
        <label class="fl">Nouveau mot de passe</label>
        <input type="password" name="new_password" id="rp-pw" class="fi" placeholder="Minimum 8 caractères" minlength="8" required>
      </div>
      <div class="modal-acts">
        <button type="button" class="btn" id="rp-cancel">Annuler</button>
        <button type="submit" class="btn btn-p">Réinitialiser</button>
      </div>
    </form>
  </div>
</div>

<script>
// Clock
(function tick(){
  var el=document.getElementById('clock'),n=new Date();
  if(el) el.textContent=[n.getHours(),n.getMinutes(),n.getSeconds()].map(function(x){return(''+x).padStart(2,'0');}).join(':');
  setTimeout(tick,1000);
})();

// Dark mode
(function(){
  var html=document.documentElement;
  var btn=document.getElementById('themeBtn');
  var sun=document.getElementById('ico-sun');
  var moon=document.getElementById('ico-moon');
  function isDark(){return html.getAttribute('data-theme')==='dark';}
  function sync(){if(sun)sun.style.display=isDark()?'':'none';if(moon)moon.style.display=isDark()?'none':'';}
  sync();
  if(btn) btn.addEventListener('click',function(){
    var d=!isDark();
    html.setAttribute('data-theme',d?'dark':'light');
    localStorage.setItem('psyadmin_dark',d?'1':'0');
    sync();
  });
})();

// Modals
(function(){
  var ovC=document.getElementById('ov-confirm');
  var ovR=document.getElementById('ov-reset');
  function close(){ovC.classList.remove('open');ovR.classList.remove('open');}
  document.getElementById('mc-cancel').addEventListener('click',close);
  document.getElementById('rp-cancel').addEventListener('click',close);
  ovC.addEventListener('click',function(e){if(e.target===this)close();});
  ovR.addEventListener('click',function(e){if(e.target===this)close();});
  
  document.addEventListener('click',function(e){
    var btn=e.target.closest('[data-modal]');
    if(!btn) return;
    var type=btn.getAttribute('data-modal');
    
    if(type==='delete'||type==='toggle'||type==='unblock'){
      var action=btn.getAttribute('data-action')||'';
      var ns=btn.getAttribute('data-newstatus')||'';
      
      document.getElementById('mc-title').textContent=btn.getAttribute('data-title')||'Confirmer ?';
      document.getElementById('mc-msg').textContent=btn.getAttribute('data-msg')||'';
      document.getElementById('mc-action').value=action;
      document.getElementById('mc-rid').value=btn.getAttribute('data-rid')||'';
      document.getElementById('mc-docid').value=btn.getAttribute('data-docid')||'';
      document.getElementById('mc-targetip').value=btn.getAttribute('data-targetip')||'';
      document.getElementById('mc-ns').value=ns;
      
      var cfm=document.getElementById('mc-confirm');
      var tag=document.getElementById('mc-tag');
      
      if(action==='toggle_doctor'&&ns==='active'){cfm.className='btn btn-ok';cfm.textContent='Activer';tag.textContent='Activation';}
      else if(action==='toggle_doctor'&&ns==='suspended'){cfm.className='btn btn-wa';cfm.textContent='Suspendre';tag.textContent='Suspension';}
      else if(action==='unblock_ip'){cfm.className='btn btn-ok';cfm.textContent='Confirmer';tag.textContent='Sécurité réseau';}
      else{cfm.className='btn btn-er';cfm.textContent='Supprimer';tag.textContent='Action irréversible';}
      
      ovC.classList.add('open');
    }
    
    if(type==='resetpw'){
      document.getElementById('rp-docid').value=btn.getAttribute('data-docid')||'';
      document.getElementById('rp-desc').textContent='Nouveau mot de passe pour Dr. '+(btn.getAttribute('data-docname')||'');
      document.getElementById('rp-pw').value='';
      ovR.classList.add('open');
    }
  });
})();
</script>
</body>
</html>