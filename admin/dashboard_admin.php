<?php
declare(strict_types=1);
ob_start(); // Empêche l'envoi de texte avant les headers (fix Nginx)
session_start();

// 1. Connexion et Configuration
include "../connection.php";
if (!isset($con) && isset($conn)) { $con = $conn; }

// 2. Protection de session
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); 
    exit();
}

/**
 * FONCTION LOG GLOBAL
 */
function logAction($con, $admin_id, $action, $details) {
    if (!$con) return;
    if ($con->query("SHOW TABLES LIKE 'admin_logs'")->num_rows === 0) {
        $con->query("CREATE TABLE admin_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT,
            action VARCHAR(100),
            details TEXT,
            ip VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $con->prepare("INSERT INTO admin_logs (admin_id, action, details, ip) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $admin_id, $action, $details, $ip);
    $stmt->execute(); 
    $stmt->close();
}

$admin_name    = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$admin_initial = strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1));
$current_file  = basename($_SERVER['PHP_SELF']);

/* ══════════════════════════════════════════════════
   3. TRAITEMENT DES ACTIONS (POST)
══════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']  ?? '';
    $section  = $_POST['section'] ?? 'overview';
    $admin_id = $_SESSION['admin_id'];

    switch ($action) {
        case 'toggle_doctor':
            $id  = (int)$_POST['docid'];
            $new = $_POST['new_status'];
            if (in_array($new, ['active','pending','suspended'])) {
                $stmt = $con->prepare("UPDATE doctor SET status=? WHERE docid=?");
                $stmt->bind_param("si", $new, $id);
                $stmt->execute(); $stmt->close();
                logAction($con, $admin_id, 'toggle_doctor', "Doctor ID $id -> $new");
            }
            break;

        case 'delete_doctor':
            $id = (int)$_POST['rid'];
            $r  = $con->query("SELECT docname FROM doctor WHERE docid=$id")->fetch_assoc();
            $stmt = $con->prepare("DELETE FROM doctor WHERE docid=?");
            $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
            logAction($con, $admin_id, 'delete_doctor', "Deleted: ".($r['docname']??$id));
            break;

        case 'delete_appointment':
            $id = (int)$_POST['rid'];
            $stmt = $con->prepare("DELETE FROM appointments WHERE id=?");
            $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
            logAction($con, $admin_id, 'delete_appointment', "Appt $id deleted");
            break;

        case 'delete_consultation':
            $id = (int)$_POST['rid'];
            $stmt = $con->prepare("DELETE FROM consultations WHERE id=?");
            $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
            logAction($con, $admin_id, 'delete_consultation', "Consultation $id deleted");
            break;

        case 'delete_patient':
            $id = (int)$_POST['rid'];
            $r  = $con->query("SELECT pname FROM patients WHERE id=$id")->fetch_assoc();
            $stmt = $con->prepare("DELETE FROM patients WHERE id=?");
            $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
            logAction($con, $admin_id, 'delete_patient', "Deleted: ".($r['pname']??$id));
            break;

        case 'edit_doctor':
            $id       = (int)$_POST['docid'];
            $docname  = trim($_POST['docname']  ?? '');
            $docemail = trim($_POST['docemail'] ?? '');
            $specialty= trim($_POST['specialty']?? '');
            if ($docname && $docemail) {
                $stmt = $con->prepare("UPDATE doctor SET docname=?, docemail=?, specialty=? WHERE docid=?");
                $stmt->bind_param("sssi", $docname, $docemail, $specialty, $id);
                $stmt->execute(); $stmt->close();
                logAction($con, $admin_id, 'edit_doctor', "Edited doctor ID $id -> $docname");
            }
            break;

        case 'reset_password':
            $id      = (int)$_POST['docid'];
            $newpass = trim($_POST['new_password'] ?? '');
            if (strlen($newpass) >= 8) {
                $hash = password_hash($newpass, PASSWORD_ARGON2ID);
                $stmt = $con->prepare("UPDATE doctor SET docpassword=? WHERE docid=?");
                $stmt->bind_param("si", $hash, $id);
                $stmt->execute(); $stmt->close();
                logAction($con, $admin_id, 'reset_password', "Password reset for doctor ID $id");
            }
            break;

        case 'clean_tokens':
            $con->query("UPDATE doctor SET reset_token=NULL, token_expiry=NULL WHERE reset_token IS NOT NULL");
            logAction($con, $admin_id, 'clean_tokens', "All reset tokens cleaned");
            break;

        case 'export_csv':
            if (ob_get_level()) ob_end_clean();
            $type = $_POST['export_type'] ?? 'doctors';
            $queries = [
                'doctors'       => "SELECT docid as ID, docname as Nom, docemail as Email, specialty as Specialite, status as Statut, dob as Naissance FROM doctor ORDER BY docid DESC",
                'patients'      => "SELECT id as ID, pname as Nom, pphone as Telephone, pdob as Naissance, created_at as Inscription FROM patients ORDER BY id DESC",
                'appointments'  => "SELECT a.id, a.patient_name as Patient, a.patient_phone as Telephone, d.docname as Medecin, a.app_date as Date, a.app_type as Type FROM appointments a LEFT JOIN doctor d ON a.doctor_id=d.docid ORDER BY a.app_date DESC",
                'consultations' => "SELECT c.id, a.patient_name as Patient, d.docname as Medecin, c.date_consultation as Date, c.duree_minutes as Duree FROM consultations c LEFT JOIN doctor d ON c.doctor_id=d.docid LEFT JOIN appointments a ON c.appointment_id=a.id ORDER BY c.date_consultation DESC",
            ];
            $q = $queries[$type] ?? $queries['doctors'];
            $res = $con->query($q);
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

    header("Location: " . $current_file . "?section=" . urlencode($section)); 
    exit();
}

/* ══════════════════════════════════════════════════
   4. RÉCUPÉRATION DES DONNÉES (GET)
══════════════════════════════════════════════════ */
$section = $_GET['section'] ?? 'overview';
$search  = trim($_GET['q'] ?? '');
$s = $con->real_escape_string($search);

$stat_doctors       = (int)$con->query("SELECT COUNT(*) c FROM doctor")->fetch_assoc()['c'];
$stat_active        = (int)$con->query("SELECT COUNT(*) c FROM doctor WHERE status='active'")->fetch_assoc()['c'];
$stat_suspended     = (int)$con->query("SELECT COUNT(*) c FROM doctor WHERE status='suspended'")->fetch_assoc()['c'];
$stat_pending       = $stat_doctors - $stat_active - $stat_suspended;
$stat_consultations = (int)$con->query("SELECT COUNT(*) c FROM consultations")->fetch_assoc()['c'];
$stat_appointments  = (int)$con->query("SELECT COUNT(*) c FROM appointments")->fetch_assoc()['c'];
$stat_patients      = (int)$con->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];

$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date  = date('Y-m-d', strtotime("-$i days"));
    $label = date('d/m', strtotime("-$i days"));
    $appts = (int)$con->query("SELECT COUNT(*) c FROM appointments WHERE DATE(app_date)='$date'")->fetch_assoc()['c'];
    $chart_data[] = ['label' => $label, 'appointments' => $appts, 'doctors' => 0];
}

$doctors = $patients = $appointments = $consultations = $logs = null;
if ($section === 'doctors') {
    $where = $s ? "WHERE docname LIKE '%$s%' OR docemail LIKE '%$s%' OR specialty LIKE '%$s%'" : '';
    $doctors = $con->query("SELECT * FROM doctor $where ORDER BY docid DESC");
} elseif ($section === 'patients') {
    $where = $s ? "WHERE pname LIKE '%$s%' OR pphone LIKE '%$s%'" : '';
    $patients = $con->query("SELECT * FROM patients $where ORDER BY created_at DESC");
} elseif ($section === 'appointments') {
    $where = $s ? "WHERE a.patient_name LIKE '%$s%' OR d.docname LIKE '%$s%'" : '';
    $appointments = $con->query("SELECT a.*, d.docname FROM appointments a LEFT JOIN doctor d ON a.doctor_id=d.docid $where ORDER BY a.app_date DESC LIMIT 100");
} elseif ($section === 'consultations') {
    $where = $s ? "WHERE a.patient_name LIKE '%$s%' OR d.docname LIKE '%$s%'" : '';
    $consultations = $con->query("SELECT c.*, d.docname, a.patient_name FROM consultations c LEFT JOIN doctor d ON c.doctor_id=d.docid LEFT JOIN appointments a ON c.appointment_id=a.id $where ORDER BY c.date_consultation DESC LIMIT 100");
} elseif ($section === 'logs') {
    $where = $s ? "WHERE action LIKE '%$s%' OR details LIKE '%$s%'" : '';
    if ($con->query("SHOW TABLES LIKE 'admin_logs'")->num_rows > 0) {
        $logs = $con->query("SELECT * FROM admin_logs $where ORDER BY created_at DESC LIMIT 150");
    }
}

// Détail consultation
$consultation_detail = null;
if (isset($_GET['view_consultation'])) {
    $cid = (int)$_GET['view_consultation'];
    $consultation_detail = $con->query("SELECT c.*, d.docname, d.docemail, a.patient_name, a.patient_phone FROM consultations c LEFT JOIN doctor d ON c.doctor_id=d.docid LEFT JOIN appointments a ON c.appointment_id=a.id WHERE c.id=$cid")->fetch_assoc();
}

// Édition médecin
$edit_doctor = null;
if (isset($_GET['edit_doctor'])) {
    $did = (int)$_GET['edit_doctor'];
    $edit_doctor = $con->query("SELECT * FROM doctor WHERE docid=$did")->fetch_assoc();
}

// Security Center
$sec = [];
if ($section === 'security') {
    $sec['weak_hash']        = $con->query("SELECT docid, docname, docemail FROM doctor WHERE docpassword LIKE '\$2y\$%'");
    $sec['weak_hash_count']  = $sec['weak_hash'] ? $sec['weak_hash']->num_rows : 0;
    $sec['stale_tokens']     = $con->query("SELECT docid, docname, docemail, token_expiry FROM doctor WHERE reset_token IS NOT NULL");
    $sec['stale_token_count']= $sec['stale_tokens'] ? $sec['stale_tokens']->num_rows : 0;
    $sec['pending_count']    = $stat_pending;
    $sec['brute_ips_count']  = 0;
    $sec['brute_force']      = null;
    $sec['total_failed']     = 0;
    if ($con->query("SHOW TABLES LIKE 'admin_logs'")->num_rows > 0) {
        $sec['brute_force']    = $con->query("SELECT ip, COUNT(*) as attempts, MAX(created_at) as last_attempt FROM admin_logs WHERE action='login_failed' GROUP BY ip HAVING attempts >= 3 ORDER BY attempts DESC LIMIT 50");
        $sec['brute_ips_count']= $sec['brute_force'] ? $sec['brute_force']->num_rows : 0;
        $sec['total_failed']   = (int)$con->query("SELECT COUNT(*) c FROM admin_logs WHERE action='login_failed'")->fetch_assoc()['c'];
    }
    $score = 100;
    if ($sec['weak_hash_count'] > 0)   $score -= min(30, $sec['weak_hash_count'] * 10);
    if ($sec['stale_token_count'] > 0) $score -= min(20, $sec['stale_token_count'] * 5);
    if ($sec['brute_ips_count'] > 0)   $score -= min(25, $sec['brute_ips_count'] * 8);
    if ($sec['pending_count'] > 5)     $score -= 10;
    $score = max(0, $score);
    $sec['score']       = $score;
    $sec['score_label'] = $score >= 90 ? 'Excellent' : ($score >= 70 ? 'Bon' : ($score >= 50 ? 'Moyen' : 'Critique'));
    $sec['score_color'] = $score >= 90 ? 'var(--ok)' : ($score >= 70 ? 'var(--in)' : ($score >= 50 ? 'var(--wa)' : 'var(--er)'));
}

$section_labels = [
    'overview'      => "Vue d'ensemble",
    'doctors'       => 'Médecins',
    'patients'      => 'Patients',
    'appointments'  => 'Rendez-vous',
    'consultations' => 'Consultations',
    'logs'          => "Logs d'activité",
    'security'      => 'Security Center',
];

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<link rel="icon" type="image/png" href="/assets/images/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin · PsySpace</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#f4f5f7;--surface:#fff;--border:#e5e7eb;--border2:#f0f1f3;
  --tx:#111827;--tx2:#6b7280;--tx3:#9ca3af;
  --ok:#059669;--ok-l:#ecfdf5;--wa:#d97706;--wa-l:#fffbeb;
  --er:#dc2626;--er-l:#fef2f2;--in:#2563eb;--in-l:#eff6ff;
  --pu:#7c3aed;--pu-l:#f5f3ff;
  --sb:240px;--r:10px;
  --sh:0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.04);
  --sh2:0 4px 12px rgba(0,0,0,.08),0 2px 4px rgba(0,0,0,.04);
}
html,body{height:100%;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--tx);font-size:13.5px;-webkit-font-smoothing:antialiased;}
a{text-decoration:none;color:inherit;}
button,input,select,textarea{font-family:inherit;}
::-webkit-scrollbar{width:5px;height:5px;}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}
.layout{display:flex;min-height:100vh;}
.main{margin-left:var(--sb);flex:1;display:flex;flex-direction:column;min-width:0;}
.sidebar{width:var(--sb);flex-shrink:0;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;}
.sb-brand{padding:18px 16px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.sb-logo{font-size:15px;font-weight:700;letter-spacing:-.02em;}
.sb-logo span{color:var(--in);}
.sb-tag{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--er);background:var(--er-l);padding:2px 7px;border-radius:4px;border:1px solid rgba(220,38,38,.15);}
.sb-nav{flex:1;padding:10px 8px;overflow-y:auto;display:flex;flex-direction:column;gap:1px;}
.sb-grp{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.15em;color:var(--tx3);padding:10px 10px 4px;}
.sb-item{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:var(--r);font-size:12.5px;font-weight:500;color:var(--tx2);transition:all .12s;border:1px solid transparent;}
.sb-item:hover{background:var(--bg);color:var(--tx);}
.sb-item.on{background:var(--in-l);color:var(--in);border-color:rgba(37,99,235,.12);font-weight:600;}
.sb-item svg{width:15px;height:15px;flex-shrink:0;opacity:.8;}
.sb-cnt{margin-left:auto;font-size:10px;font-weight:700;background:var(--bg);color:var(--tx3);padding:1px 7px;border-radius:99px;border:1px solid var(--border);}
.sb-item.on .sb-cnt{background:rgba(37,99,235,.1);color:var(--in);border-color:rgba(37,99,235,.2);}
.sb-foot{padding:10px 8px;border-top:1px solid var(--border);}
.sb-user{display:flex;align-items:center;gap:8px;padding:10px;background:var(--bg);border-radius:var(--r);margin-bottom:6px;border:1px solid var(--border);}
.sb-av{width:30px;height:30px;border-radius:8px;background:var(--in);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
.sb-uname{font-size:12px;font-weight:600;}
.sb-urole{font-size:10px;color:var(--tx3);}
.sb-out{display:flex;align-items:center;gap:7px;padding:8px 10px;border-radius:var(--r);font-size:12px;font-weight:500;color:var(--tx2);border:1px solid var(--border);transition:all .12s;width:100%;background:none;cursor:pointer;}
.sb-out:hover{background:var(--er-l);color:var(--er);border-color:rgba(220,38,38,.2);}
.topbar{height:54px;display:flex;align-items:center;justify-content:space-between;padding:0 22px;background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50;}
.tb-title{font-size:14px;font-weight:700;}
.tb-meta{font-size:10.5px;color:var(--tx3);margin-top:1px;}
.tb-right{display:flex;align-items:center;gap:10px;}
.tb-clock{font-family:'DM Mono',monospace;font-size:12px;font-weight:500;color:var(--tx2);background:var(--bg);padding:5px 12px;border-radius:8px;border:1px solid var(--border);}
.tb-alert{font-size:10px;font-weight:700;padding:4px 10px;border-radius:6px;background:var(--er-l);color:var(--er);border:1px solid rgba(220,38,38,.2);}
.content{padding:20px 22px;flex:1;}
.stats{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:20px;}
.sc{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:14px 16px;box-shadow:var(--sh);transition:box-shadow .15s,transform .15s;}
.sc:hover{box-shadow:var(--sh2);transform:translateY(-1px);}
.sc-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.sc-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;}
.sc-badge{font-size:9.5px;font-weight:700;padding:2px 7px;border-radius:5px;}
.sc-val{font-size:22px;font-weight:700;letter-spacing:-.02em;line-height:1;}
.sc-lbl{font-size:11px;color:var(--tx3);font-weight:500;margin-top:4px;}
.toolbar{display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.search-box{display:flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:6px 12px;flex:1;min-width:200px;max-width:340px;}
.search-box input{border:none;outline:none;font-size:13px;color:var(--tx);background:transparent;width:100%;}
.search-box input::placeholder{color:var(--tx3);}
.search-box svg{color:var(--tx3);flex-shrink:0;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;margin-bottom:16px;}
.card:last-child{margin-bottom:0;}
.card-head{display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-bottom:1px solid var(--border);}
.ch-left{display:flex;align-items:center;gap:9px;}
.ch-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}
.ch-title{font-size:13px;font-weight:700;}
.ch-cnt{font-size:10px;font-weight:700;background:var(--bg);color:var(--tx3);padding:2px 8px;border-radius:99px;border:1px solid var(--border);}
.ch-link{font-size:11px;font-weight:600;color:var(--in);}
.ch-link:hover{text-decoration:underline;}
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
thead th{padding:9px 14px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--tx3);background:var(--bg);white-space:nowrap;border-bottom:1px solid var(--border);}
tbody tr{border-bottom:1px solid var(--border2);transition:background .1s;}
tbody tr:last-child{border-bottom:none;}
tbody tr:hover{background:#fafbfc;}
td{padding:10px 14px;font-size:12.5px;color:var(--tx2);vertical-align:middle;}
td.name{color:var(--tx);font-weight:600;}
td.mono{font-family:'DM Mono',monospace;font-size:11.5px;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:10px;font-weight:700;white-space:nowrap;}
.b-ok{background:var(--ok-l);color:var(--ok);border:1px solid rgba(5,150,105,.2);}
.b-wa{background:var(--wa-l);color:var(--wa);border:1px solid rgba(217,119,6,.2);}
.b-er{background:var(--er-l);color:var(--er);border:1px solid rgba(220,38,38,.2);}
.b-in{background:var(--in-l);color:var(--in);border:1px solid rgba(37,99,235,.2);}
.b-pu{background:var(--pu-l);color:var(--pu);border:1px solid rgba(124,58,237,.2);}
.b-n{background:var(--bg);color:var(--tx3);border:1px solid var(--border);}
.btn{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:7px;border:1px solid;font-size:11px;font-weight:600;cursor:pointer;transition:all .12s;background:none;}
.btn-ok{color:var(--ok);border-color:rgba(5,150,105,.25);background:var(--ok-l);}
.btn-ok:hover{background:#d1fae5;}
.btn-wa{color:var(--wa);border-color:rgba(217,119,6,.25);background:var(--wa-l);}
.btn-wa:hover{background:#fde68a50;}
.btn-er{color:var(--er);border-color:rgba(220,38,38,.25);background:var(--er-l);}
.btn-er:hover{background:#fee2e2;}
.btn-in{color:var(--in);border-color:rgba(37,99,235,.25);background:var(--in-l);}
.btn-in:hover{background:#dbeafe;}
.btn-pu{color:var(--pu);border-color:rgba(124,58,237,.25);background:var(--pu-l);}
.btn-pu:hover{background:#ede9fe;}
.btn-ghost{color:var(--tx2);border-color:var(--border);background:var(--surface);}
.btn-ghost:hover{background:var(--bg);color:var(--tx);}
.btn-primary{color:#fff;border-color:var(--in);background:var(--in);}
.btn-primary:hover{background:#1d4ed8;}
.ov-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.ov-row{display:flex;align-items:center;justify-content:space-between;padding:9px 14px;border-bottom:1px solid var(--border2);transition:background .1s;}
.ov-row:last-child{border-bottom:none;}
.ov-row:hover{background:var(--bg);}
.ov-av{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;}
.chart-wrap{padding:16px 20px 8px;}
.chart-bars{display:flex;align-items:flex-end;gap:8px;height:100px;}
.chart-bar-grp{display:flex;flex-direction:column;align-items:center;gap:3px;flex:1;}
.chart-bar{width:100%;border-radius:4px 4px 0 0;transition:opacity .2s;min-height:2px;}
.chart-bar:hover{opacity:.75;}
.chart-lbl{font-size:9px;color:var(--tx3);font-weight:600;}
.chart-legend{display:flex;gap:14px;margin-top:10px;padding:0 4px;}
.chart-legend-item{display:flex;align-items:center;gap:5px;font-size:10px;color:var(--tx3);}
.chart-legend-dot{width:8px;height:8px;border-radius:2px;}
.empty{padding:48px 20px;text-align:center;color:var(--tx3);}
.empty-icon{font-size:24px;margin-bottom:8px;opacity:.4;}
.empty p{font-size:12px;}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;z-index:999;opacity:0;pointer-events:none;transition:opacity .2s;}
.overlay.show{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.15);transform:translateY(10px) scale(.97);transition:transform .2s;}
.overlay.show .modal{transform:translateY(0) scale(1);}
.modal-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:12px;}
.modal h3{font-size:15px;font-weight:700;margin-bottom:5px;}
.modal p{font-size:12.5px;color:var(--tx2);line-height:1.6;margin-bottom:16px;}
.modal-btns{display:flex;gap:8px;justify-content:flex-end;margin-top:16px;}
.form-group{margin-bottom:14px;}
.form-label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx2);margin-bottom:5px;}
.form-input{width:100%;border:1px solid var(--border);border-radius:8px;padding:9px 12px;font-size:13px;color:var(--tx);outline:none;transition:border-color .15s;}
.form-input:focus{border-color:var(--in);box-shadow:0 0 0 3px rgba(37,99,235,.08);}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.detail-section{margin-bottom:16px;}
.detail-title{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--tx3);margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--border);}
.detail-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border2);font-size:12.5px;}
.detail-row:last-child{border-bottom:none;}
.detail-key{color:var(--tx2);}
.detail-val{font-weight:600;color:var(--tx);text-align:right;max-width:60%;}
.detail-text{background:var(--bg);border-radius:8px;padding:12px;font-size:12px;line-height:1.7;color:var(--tx2);max-height:200px;overflow-y:auto;white-space:pre-wrap;}
.log-row{display:flex;align-items:flex-start;gap:10px;padding:8px 14px;border-bottom:1px solid var(--border2);}
.log-row:last-child{border-bottom:none;}
.log-dot{width:7px;height:7px;border-radius:50%;margin-top:4px;flex-shrink:0;}
.log-action{font-size:12px;font-weight:600;color:var(--tx);}
.log-detail{font-size:11px;color:var(--tx3);}
.log-time{font-size:10px;font-family:'DM Mono',monospace;color:var(--tx3);margin-left:auto;white-space:nowrap;}
/* Security Center */
.sec-risk-row{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border2);}
.sec-risk-row:last-child{border-bottom:none;}
.sec-risk-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.sec-risk-title{font-size:12.5px;font-weight:600;color:var(--tx);}
.sec-risk-desc{font-size:11px;color:var(--tx3);margin-top:2px;}
@media(max-width:1100px){.stats{grid-template-columns:repeat(3,1fr);}}
/* DARK MODE */
.dark{--bg:#0f172a;--surface:#1e293b;--border:#334155;--border2:#1e293b;--tx:#f1f5f9;--tx2:#94a3b8;--tx3:#64748b;}
.dark .sidebar,.dark .topbar{background:var(--surface) !important;}
.dark tbody tr:hover{background:#273548;}
.dark .tb-clock,.dark .search-box input{background:var(--surface);}
.dark .modal{background:var(--surface);border-color:var(--border);}
@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr);}.ov-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="layout">

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">Psy<span>Space</span></div>
    <div class="sb-tag">Admin</div>
  </div>
  <nav class="sb-nav">
    <div class="sb-grp">Dashboard</div>
    <a href="?section=overview" class="sb-item <?= $section==='overview'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Vue d'ensemble
    </a>
    <div class="sb-grp">Gestion</div>
    <a href="?section=doctors" class="sb-item <?= $section==='doctors'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Médecins <span class="sb-cnt"><?= $stat_doctors ?></span>
    </a>
    <a href="?section=patients" class="sb-item <?= $section==='patients'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Patients <span class="sb-cnt"><?= $stat_patients ?></span>
    </a>
    <a href="?section=appointments" class="sb-item <?= $section==='appointments'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Rendez-vous <span class="sb-cnt"><?= $stat_appointments ?></span>
    </a>
    <a href="?section=consultations" class="sb-item <?= $section==='consultations'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Consultations <span class="sb-cnt"><?= $stat_consultations ?></span>
    </a>
    <div class="sb-grp">Sécurité</div>
    <a href="?section=logs" class="sb-item <?= $section==='logs'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Logs d'activité
    </a>
    <a href="?section=security" class="sb-item <?= $section==='security'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      Security Center
    </a>
  </nav>
  <div class="sb-foot">
    <div class="sb-user">
      <div class="sb-av"><?= $admin_initial ?></div>
      <div><div class="sb-uname"><?= $admin_name ?></div><div class="sb-urole">Administrateur</div></div>
    </div>
    <a href="logout.php" class="sb-out">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Déconnexion
    </a>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div>
      <div class="tb-title"><?= $section_labels[$section] ?? 'Dashboard' ?></div>
      <div class="tb-meta">PsySpace · Panneau d'administration</div>
    </div>
    <div class="tb-right">
      <?php if($stat_pending>0): ?><div class="tb-alert">⚠ <?= $stat_pending ?> en attente</div><?php endif; ?>
      <?php if($stat_suspended>0): ?><div class="tb-alert" style="background:var(--pu-l);color:var(--pu);border-color:rgba(124,58,237,.2);">⏸ <?= $stat_suspended ?> suspendu<?= $stat_suspended>1?'s':'' ?></div><?php endif; ?>
      <button id="darkBtn" title="Mode sombre" style="width:34px;height:34px;border-radius:8px;border:1px solid var(--border);background:var(--bg);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;transition:all .15s;" aria-label="Toggle dark mode">🌙</button>
      <div class="tb-clock" id="clock">--:--:--</div>
    </div>
  </div>

  <div class="content">

    <!-- STATS -->
    <div class="stats">
      <div class="sc">
        <div class="sc-top"><div class="sc-icon" style="background:var(--in-l);">👨‍⚕️</div><span class="sc-badge" style="background:var(--in-l);color:var(--in);"><?= $stat_active ?> actifs</span></div>
        <div class="sc-val"><?= $stat_doctors ?></div><div class="sc-lbl">Médecins</div>
      </div>
      <div class="sc">
        <div class="sc-top"><div class="sc-icon" style="background:var(--ok-l);">🧑</div></div>
        <div class="sc-val"><?= $stat_patients ?></div><div class="sc-lbl">Patients</div>
      </div>
      <div class="sc">
        <div class="sc-top"><div class="sc-icon" style="background:var(--wa-l);">📅</div></div>
        <div class="sc-val"><?= $stat_appointments ?></div><div class="sc-lbl">Rendez-vous</div>
      </div>
      <div class="sc">
        <div class="sc-top"><div class="sc-icon" style="background:var(--in-l);">📋</div></div>
        <div class="sc-val"><?= $stat_consultations ?></div><div class="sc-lbl">Consultations</div>
      </div>
      <div class="sc">
        <div class="sc-top"><div class="sc-icon" style="background:var(--er-l);">⏳</div><?php if($stat_pending>0): ?><span class="sc-badge" style="background:var(--er-l);color:var(--er);">À activer</span><?php endif; ?></div>
        <div class="sc-val" style="<?= $stat_pending>0?'color:var(--er)':'' ?>"><?= $stat_pending ?></div><div class="sc-lbl">En attente</div>
      </div>
      <div class="sc">
        <div class="sc-top"><div class="sc-icon" style="background:var(--pu-l);">⏸</div></div>
        <div class="sc-val" style="<?= $stat_suspended>0?'color:var(--pu)':'' ?>"><?= $stat_suspended ?></div><div class="sc-lbl">Suspendus</div>
      </div>
    </div>

<?php if($section==='overview'): ?>

    <div class="card" style="margin-bottom:16px;">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:var(--in);"></div><div class="ch-title">Activité — 7 derniers jours</div></div>
      </div>
      <div class="chart-wrap">
        <?php $max_val=1; foreach($chart_data as $d) $max_val=max($max_val,$d['appointments']); ?>
        <div class="chart-bars">
          <?php foreach($chart_data as $d): ?>
          <div class="chart-bar-grp">
            <div style="display:flex;gap:2px;align-items:flex-end;width:100%;height:90px;">
              <div class="chart-bar" style="flex:1;height:<?= max(2,round($d['appointments']/$max_val*90)) ?>px;background:var(--in);opacity:.8;" title="<?= $d['appointments'] ?> RDV"></div>
            </div>
            <div class="chart-lbl"><?= $d['label'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="chart-legend"><div class="chart-legend-item"><div class="chart-legend-dot" style="background:var(--in);"></div>Rendez-vous</div></div>
      </div>
    </div>

    <div class="ov-grid">
      <div class="card">
        <div class="card-head">
          <div class="ch-left"><div class="ch-dot" style="background:var(--in);"></div><div class="ch-title">Médecins récents</div></div>
          <a href="?section=doctors" class="ch-link">Voir tout →</a>
        </div>
        <?php $ld=$con->query("SELECT * FROM doctor ORDER BY docid DESC LIMIT 6");
        if($ld && $ld->num_rows): while($d=$ld->fetch_assoc()):
          $bclass=$d['status']==='active'?'b-ok':($d['status']==='suspended'?'b-pu':'b-wa');
          $blabel=$d['status']==='active'?'● Actif':($d['status']==='suspended'?'⏸ Suspendu':'○ Attente');
        ?>
        <div class="ov-row">
          <div style="display:flex;align-items:center;gap:8px;">
            <div class="ov-av" style="background:var(--in-l);color:var(--in);"><?= strtoupper(substr($d['docname'],0,1)) ?></div>
            <div>
              <div style="font-size:12.5px;font-weight:600;color:var(--tx);"><?= htmlspecialchars($d['docname']) ?></div>
              <div style="font-size:10.5px;color:var(--tx3);"><?= htmlspecialchars($d['specialty']??$d['docemail']) ?></div>
            </div>
          </div>
          <span class="badge <?= $bclass ?>"><?= $blabel ?></span>
        </div>
        <?php endwhile; else: ?><div class="empty"><div class="empty-icon">👨‍⚕️</div><p>Aucun médecin</p></div><?php endif; ?>
      </div>

      <div class="card">
        <div class="card-head">
          <div class="ch-left"><div class="ch-dot" style="background:var(--ok);"></div><div class="ch-title">Dernières consultations</div></div>
          <a href="?section=consultations" class="ch-link">Voir tout →</a>
        </div>
        <?php $lc=$con->query("SELECT c.*,d.docname,a.patient_name FROM consultations c LEFT JOIN doctor d ON c.doctor_id=d.docid LEFT JOIN appointments a ON c.appointment_id=a.id ORDER BY c.date_consultation DESC LIMIT 6");
        if($lc && $lc->num_rows): while($c=$lc->fetch_assoc()): ?>
        <div class="ov-row">
          <div>
            <div style="font-size:12.5px;font-weight:600;color:var(--tx);"><?= htmlspecialchars($c['patient_name']??'Patient') ?></div>
            <div style="font-size:10.5px;color:var(--tx3);">Dr. <?= htmlspecialchars($c['docname']??'—') ?> · <?= date('d/m/Y',strtotime($c['date_consultation'])) ?></div>
          </div>
          <a href="?section=consultations&view_consultation=<?= $c['id'] ?>" class="badge b-in" style="cursor:pointer;">Voir</a>
        </div>
        <?php endwhile; else: ?><div class="empty"><div class="empty-icon">📋</div><p>Aucune consultation</p></div><?php endif; ?>
      </div>

      <div class="card" style="grid-column:1/-1;">
        <div class="card-head">
          <div class="ch-left"><div class="ch-dot" style="background:var(--wa);"></div><div class="ch-title">Rendez-vous récents</div></div>
          <a href="?section=appointments" class="ch-link">Voir tout →</a>
        </div>
        <?php $la=$con->query("SELECT a.*,d.docname FROM appointments a LEFT JOIN doctor d ON a.doctor_id=d.docid ORDER BY a.app_date DESC LIMIT 5");
        if($la && $la->num_rows): ?>
        <div class="tbl-wrap"><table>
          <thead><tr><th>Patient</th><th>Médecin</th><th>Date</th><th>Type</th></tr></thead>
          <tbody>
          <?php while($a=$la->fetch_assoc()): ?>
          <tr>
            <td class="name"><?= htmlspecialchars($a['patient_name']) ?></td>
            <td>Dr. <?= htmlspecialchars($a['docname']??'—') ?></td>
            <td class="mono"><?= date('d/m/Y H:i',strtotime($a['app_date'])) ?></td>
            <td><span class="badge b-n"><?= htmlspecialchars($a['app_type']??'Consultation') ?></span></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table></div>
        <?php else: ?><div class="empty"><div class="empty-icon">📅</div><p>Aucun rendez-vous</p></div><?php endif; ?>
      </div>
    </div>

<?php elseif($section==='doctors'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="doctors">
        <div class="search-box">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher médecin, email, spécialité...">
        </div>
        <button type="submit" class="btn btn-primary">Rechercher</button>
        <?php if($search): ?><a href="?section=doctors" class="btn btn-ghost">✕ Effacer</a><?php endif; ?>
      </form>
      <form method="POST" style="margin-left:auto;">
        <input type="hidden" name="action" value="export_csv">
        <input type="hidden" name="export_type" value="doctors">
        <input type="hidden" name="section" value="doctors">
        <button type="submit" class="btn btn-ghost">⬇ Export CSV</button>
      </form>
    </div>

    <div class="card">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:var(--in);"></div><div class="ch-title">Médecins inscrits</div><span class="ch-cnt"><?= $doctors?->num_rows ?? 0 ?></span></div>
      </div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Médecin</th><th>Email</th><th>Spécialité</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if($doctors && $doctors->num_rows): while($d=$doctors->fetch_assoc()):
          $bclass=$d['status']==='active'?'b-ok':($d['status']==='suspended'?'b-pu':'b-wa');
          $blabel=$d['status']==='active'?'● Actif':($d['status']==='suspended'?'⏸ Suspendu':'○ Attente');
        ?>
        <tr>
          <td class="mono" style="color:var(--tx3);"><?= $d['docid'] ?></td>
          <td class="name">
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="ov-av" style="background:var(--in-l);color:var(--in);"><?= strtoupper(substr($d['docname'],0,1)) ?></div>
              <?= htmlspecialchars($d['docname']) ?>
            </div>
          </td>
          <td><?= htmlspecialchars($d['docemail']) ?></td>
          <td><?= htmlspecialchars($d['specialty']??'—') ?></td>
          <td><span class="badge <?= $bclass ?>"><?= $blabel ?></span></td>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap;">
              <a href="?section=doctors&edit_doctor=<?= $d['docid'] ?>" class="btn btn-in">✏ Modifier</a>
              <button class="btn btn-pu" data-modal="resetpw" data-docid="<?= $d['docid'] ?>" data-docname="<?= htmlspecialchars($d['docname'],ENT_QUOTES) ?>">🔑 MDP</button>
              <?php if($d['status']==='active'): ?>
              <button class="btn btn-wa" data-modal="toggle" data-action="toggle_doctor" data-docid="<?= $d['docid'] ?>" data-newstatus="suspended" data-title="Suspendre ?" data-msg="Dr. <?= htmlspecialchars($d['docname'],ENT_QUOTES) ?> ne pourra plus se connecter.">⏸ Suspendre</button>
              <?php elseif($d['status']==='suspended'): ?>
              <button class="btn btn-ok" data-modal="toggle" data-action="toggle_doctor" data-docid="<?= $d['docid'] ?>" data-newstatus="active" data-title="Réactiver ?" data-msg="Dr. <?= htmlspecialchars($d['docname'],ENT_QUOTES) ?> pourra se reconnecter.">▶ Réactiver</button>
              <?php else: ?>
              <button class="btn btn-ok" data-modal="toggle" data-action="toggle_doctor" data-docid="<?= $d['docid'] ?>" data-newstatus="active" data-title="Activer ?" data-msg="Dr. <?= htmlspecialchars($d['docname'],ENT_QUOTES) ?> pourra se connecter.">✓ Activer</button>
              <?php endif; ?>
              <button class="btn btn-er" data-modal="delete" data-action="delete_doctor" data-rid="<?= $d['docid'] ?>" data-title="Supprimer ce médecin ?" data-msg="Action irréversible. Toutes les données seront perdues.">🗑</button>
            </div>
          </td>
        </tr>
        <?php endwhile; else: ?><tr><td colspan="6"><div class="empty"><div class="empty-icon">👨‍⚕️</div><p>Aucun médecin<?= $search?' pour "'.$search.'"':'' ?></p></div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>

    <?php if($edit_doctor): ?>
    <div class="card" style="border:2px solid var(--in);">
      <div class="card-head" style="background:var(--in-l);">
        <div class="ch-left"><div class="ch-dot" style="background:var(--in);"></div><div class="ch-title" style="color:var(--in);">Modifier : <?= htmlspecialchars($edit_doctor['docname']) ?></div></div>
        <a href="?section=doctors" class="btn btn-ghost">✕ Fermer</a>
      </div>
      <div style="padding:20px;">
        <form method="POST">
          <input type="hidden" name="action" value="edit_doctor">
          <input type="hidden" name="docid" value="<?= $edit_doctor['docid'] ?>">
          <input type="hidden" name="section" value="doctors">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Nom complet</label>
              <input type="text" name="docname" class="form-input" value="<?= htmlspecialchars($edit_doctor['docname']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" name="docemail" class="form-input" value="<?= htmlspecialchars($edit_doctor['docemail']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Spécialité</label>
              <input type="text" name="specialty" class="form-input" value="<?= htmlspecialchars($edit_doctor['specialty']??'') ?>" placeholder="Ex: Psychologue clinicien">
            </div>
          </div>
          <div class="modal-btns" style="justify-content:flex-start;">
            <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
            <a href="?section=doctors" class="btn btn-ghost">Annuler</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

<?php elseif($section==='patients'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="patients">
        <div class="search-box">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher patient, téléphone...">
        </div>
        <button type="submit" class="btn btn-primary">Rechercher</button>
        <?php if($search): ?><a href="?section=patients" class="btn btn-ghost">✕ Effacer</a><?php endif; ?>
      </form>
      <form method="POST" style="margin-left:auto;">
        <input type="hidden" name="action" value="export_csv">
        <input type="hidden" name="export_type" value="patients">
        <input type="hidden" name="section" value="patients">
        <button type="submit" class="btn btn-ghost">⬇ Export CSV</button>
      </form>
    </div>

    <div class="card">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:var(--ok);"></div><div class="ch-title">Patients</div><span class="ch-cnt"><?= $patients?->num_rows ?? 0 ?></span></div>
      </div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Nom</th><th>Téléphone</th><th>Naissance</th><th>Inscrit le</th><th>Action</th></tr></thead>
        <tbody>
        <?php if($patients && $patients->num_rows): while($p=$patients->fetch_assoc()): ?>
        <tr>
          <td class="mono" style="color:var(--tx3);"><?= $p['id'] ?></td>
          <td class="name"><?= htmlspecialchars($p['pname']) ?></td>
          <td><?= htmlspecialchars($p['pphone']??'—') ?></td>
          <td class="mono"><?= $p['pdob']?date('d/m/Y',strtotime($p['pdob'])):'—' ?></td>
          <td class="mono" style="color:var(--tx3);"><?= date('d/m/Y',strtotime($p['created_at'])) ?></td>
          <td><button class="btn btn-er" data-modal="delete" data-action="delete_patient" data-rid="<?= $p['id'] ?>" data-title="Supprimer ce patient ?" data-msg="<?= htmlspecialchars($p['pname'],ENT_QUOTES) ?> sera supprimé définitivement.">🗑 Supprimer</button></td>
        </tr>
        <?php endwhile; else: ?><tr><td colspan="6"><div class="empty"><div class="empty-icon">🧑</div><p>Aucun patient<?= $search?' pour "'.$search.'"':'' ?></p></div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>

<?php elseif($section==='appointments'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="appointments">
        <div class="search-box">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher patient, médecin...">
        </div>
        <button type="submit" class="btn btn-primary">Rechercher</button>
        <?php if($search): ?><a href="?section=appointments" class="btn btn-ghost">✕ Effacer</a><?php endif; ?>
      </form>
      <form method="POST" style="margin-left:auto;">
        <input type="hidden" name="action" value="export_csv">
        <input type="hidden" name="export_type" value="appointments">
        <input type="hidden" name="section" value="appointments">
        <button type="submit" class="btn btn-ghost">⬇ Export CSV</button>
      </form>
    </div>

    <div class="card">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:var(--wa);"></div><div class="ch-title">Rendez-vous</div><span class="ch-cnt"><?= $appointments?->num_rows ?? 0 ?></span></div>
      </div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Patient</th><th>Téléphone</th><th>Médecin</th><th>Date</th><th>Type</th><th>Action</th></tr></thead>
        <tbody>
        <?php if($appointments && $appointments->num_rows): while($a=$appointments->fetch_assoc()): ?>
        <tr>
          <td class="mono" style="color:var(--tx3);"><?= $a['id'] ?></td>
          <td class="name"><?= htmlspecialchars($a['patient_name']) ?></td>
          <td><?= htmlspecialchars($a['patient_phone']??'—') ?></td>
          <td>Dr. <?= htmlspecialchars($a['docname']??'—') ?></td>
          <td class="mono"><?= date('d/m/Y H:i',strtotime($a['app_date'])) ?></td>
          <td><span class="badge b-n"><?= htmlspecialchars($a['app_type']??'Consultation') ?></span></td>
          <td><button class="btn btn-er" data-modal="delete" data-action="delete_appointment" data-rid="<?= $a['id'] ?>" data-title="Supprimer ce RDV ?" data-msg="Le rendez-vous du <?= date('d/m/Y',strtotime($a['app_date'])) ?> sera supprimé.">🗑</button></td>
        </tr>
        <?php endwhile; else: ?><tr><td colspan="7"><div class="empty"><div class="empty-icon">📅</div><p>Aucun rendez-vous<?= $search?' pour "'.$search.'"':'' ?></p></div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>

<?php elseif($section==='consultations'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="consultations">
        <div class="search-box">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher patient, médecin...">
        </div>
        <button type="submit" class="btn btn-primary">Rechercher</button>
        <?php if($search): ?><a href="?section=consultations" class="btn btn-ghost">✕ Effacer</a><?php endif; ?>
      </form>
      <form method="POST" style="margin-left:auto;">
        <input type="hidden" name="action" value="export_csv">
        <input type="hidden" name="export_type" value="consultations">
        <input type="hidden" name="section" value="consultations">
        <button type="submit" class="btn btn-ghost">⬇ Export CSV</button>
      </form>
    </div>

    <?php if($consultation_detail): ?>
    <div class="card" style="border:2px solid var(--pu);margin-bottom:16px;">
      <div class="card-head" style="background:var(--pu-l);">
        <div class="ch-left"><div class="ch-dot" style="background:var(--pu);"></div><div class="ch-title" style="color:var(--pu);">Détail — Consultation #<?= $consultation_detail['id'] ?></div></div>
        <a href="?section=consultations" class="btn btn-ghost">✕ Fermer</a>
      </div>
      <div style="padding:20px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
          <div>
            <div class="detail-section">
              <div class="detail-title">Informations</div>
              <div class="detail-row"><span class="detail-key">Patient</span><span class="detail-val"><?= htmlspecialchars($consultation_detail['patient_name']??'—') ?></span></div>
              <div class="detail-row"><span class="detail-key">Téléphone</span><span class="detail-val"><?= htmlspecialchars($consultation_detail['patient_phone']??'—') ?></span></div>
              <div class="detail-row"><span class="detail-key">Médecin</span><span class="detail-val">Dr. <?= htmlspecialchars($consultation_detail['docname']??'—') ?></span></div>
              <div class="detail-row"><span class="detail-key">Date</span><span class="detail-val"><?= date('d/m/Y H:i',strtotime($consultation_detail['date_consultation'])) ?></span></div>
              <div class="detail-row"><span class="detail-key">Durée</span><span class="detail-val"><?= $consultation_detail['duree_minutes']>0?$consultation_detail['duree_minutes'].' min':'—' ?></span></div>
              <div class="detail-row"><span class="detail-key">Résumé IA</span><span class="detail-val"><?= !empty($consultation_detail['resume_ia'])?'<span class="badge b-ok">✓ Oui</span>':'<span class="badge b-n">Non</span>' ?></span></div>
            </div>
          </div>
          <div>
            <?php if(!empty($consultation_detail['resume_ia'])): ?>
            <div class="detail-section">
              <div class="detail-title">Résumé IA</div>
              <div class="detail-text"><?= htmlspecialchars($consultation_detail['resume_ia']) ?></div>
            </div>
            <?php endif; ?>
            <?php if(!empty($consultation_detail['transcription_brute'])): ?>
            <div class="detail-section">
              <div class="detail-title">Transcription brute</div>
              <div class="detail-text"><?= htmlspecialchars($consultation_detail['transcription_brute']) ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:var(--pu);"></div><div class="ch-title">Consultations archivées</div><span class="ch-cnt"><?= $consultations?->num_rows ?? 0 ?></span></div>
      </div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Patient</th><th>Médecin</th><th>Date</th><th>Durée</th><th>Résumé IA</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if($consultations && $consultations->num_rows): while($c=$consultations->fetch_assoc()): ?>
        <tr>
          <td class="mono" style="color:var(--tx3);"><?= $c['id'] ?></td>
          <td class="name"><?= htmlspecialchars($c['patient_name']??'Non lié') ?></td>
          <td>Dr. <?= htmlspecialchars($c['docname']??'—') ?></td>
          <td class="mono"><?= date('d/m/Y H:i',strtotime($c['date_consultation'])) ?></td>
          <td><span class="badge b-n"><?= $c['duree_minutes']>0?$c['duree_minutes'].' min':'—' ?></span></td>
          <td><?= !empty($c['resume_ia'])?'<span class="badge b-ok">✓ Généré</span>':'<span class="badge b-n">—</span>' ?></td>
          <td>
            <div style="display:flex;gap:5px;">
              <a href="?section=consultations&view_consultation=<?= $c['id'] ?>" class="btn btn-pu">👁 Voir</a>
              <button class="btn btn-er" data-modal="delete" data-action="delete_consultation" data-rid="<?= $c['id'] ?>" data-title="Supprimer cette consultation ?" data-msg="Transcription et résumé IA perdus définitivement.">🗑</button>
            </div>
          </td>
        </tr>
        <?php endwhile; else: ?><tr><td colspan="7"><div class="empty"><div class="empty-icon">📋</div><p>Aucune consultation<?= $search?' pour "'.$search.'"':'' ?></p></div></td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>

<?php elseif($section==='logs'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="logs">
        <div class="search-box">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Filtrer les logs...">
        </div>
        <button type="submit" class="btn btn-primary">Filtrer</button>
        <?php if($search): ?><a href="?section=logs" class="btn btn-ghost">✕ Effacer</a><?php endif; ?>
      </form>
    </div>

    <div class="card">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:var(--er);"></div><div class="ch-title">Journal d'activité admin</div></div>
        <span style="font-size:11px;color:var(--tx3);">150 entrées max</span>
      </div>
      <?php
      $log_colors=['delete_doctor'=>'var(--er)','delete_patient'=>'var(--er)','delete_appointment'=>'var(--er)','delete_consultation'=>'var(--er)','edit_doctor'=>'var(--in)','reset_password'=>'var(--pu)','toggle_doctor'=>'var(--wa)','export_csv'=>'var(--ok)','login_failed'=>'var(--er)','admin_login'=>'var(--ok)','doctor_login'=>'var(--in)','clean_tokens'=>'var(--wa)'];
      if($logs && $logs->num_rows): while($l=$logs->fetch_assoc()):
        $col=$log_colors[$l['action']]??'var(--tx3)';
      ?>
      <div class="log-row">
        <div class="log-dot" style="background:<?= $col ?>;"></div>
        <div style="flex:1;min-width:0;">
          <div class="log-action"><?= htmlspecialchars($l['action']) ?></div>
          <div class="log-detail"><?= htmlspecialchars($l['details']) ?> · IP: <?= htmlspecialchars($l['ip']) ?></div>
        </div>
        <div class="log-time"><?= date('d/m/Y H:i:s',strtotime($l['created_at'])) ?></div>
      </div>
      <?php endwhile; else: ?>
      <div class="empty"><div class="empty-icon">📋</div><p>Aucun log<?= $search?' pour "'.$search.'"':'. Les actions apparaîtront ici automatiquement.' ?></p></div>
      <?php endif; ?>
    </div>

<?php elseif($section==='security'): ?>

    <!-- SCORE + RÉSUMÉ -->
    <div style="display:grid;grid-template-columns:260px 1fr;gap:16px;margin-bottom:16px;">

      <div class="card" style="border:2px solid <?= $sec['score_color'] ?>;">
        <div style="padding:24px;text-align:center;">
          <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--tx3);margin-bottom:14px;">Score de sécurité</div>
          <div style="position:relative;width:110px;height:110px;margin:0 auto 14px;">
            <svg viewBox="0 0 36 36" style="width:110px;height:110px;transform:rotate(-90deg);">
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--border)" stroke-width="3"/>
              <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?= $sec['score_color'] ?>" stroke-width="3"
                stroke-dasharray="<?= $sec['score'] ?> 100" stroke-linecap="round"/>
            </svg>
            <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
              <span style="font-size:24px;font-weight:800;color:<?= $sec['score_color'] ?>;"><?= $sec['score'] ?></span>
              <span style="font-size:10px;color:var(--tx3);">/100</span>
            </div>
          </div>
          <div style="font-size:15px;font-weight:700;color:<?= $sec['score_color'] ?>;"><?= $sec['score_label'] ?></div>
          <div style="font-size:11px;color:var(--tx3);margin-top:4px;">Calculé en temps réel</div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><div class="ch-left"><div class="ch-dot" style="background:var(--er);"></div><div class="ch-title">Risques détectés</div></div></div>

        <div class="sec-risk-row">
          <div class="sec-risk-icon" style="background:<?= $sec['brute_ips_count']>0?'var(--er-l)':'var(--ok-l)' ?>;"><?= $sec['brute_ips_count']>0?'🚨':'✅' ?></div>
          <div style="flex:1;">
            <div class="sec-risk-title">Brute force — IPs suspectes</div>
            <div class="sec-risk-desc"><?= $sec['brute_ips_count']>0?"$sec[brute_ips_count] IP(s) avec 3+ tentatives échouées · $sec[total_failed] échec(s) total":'Aucune IP suspecte détectée' ?></div>
          </div>
          <span class="badge <?= $sec['brute_ips_count']>0?'b-er':'b-ok' ?>"><?= $sec['brute_ips_count']>0?"$sec[brute_ips_count] IP":'OK' ?></span>
        </div>

        <div class="sec-risk-row">
          <div class="sec-risk-icon" style="background:<?= $sec['weak_hash_count']>0?'var(--wa-l)':'var(--ok-l)' ?>;"><?= $sec['weak_hash_count']>0?'⚠️':'✅' ?></div>
          <div style="flex:1;">
            <div class="sec-risk-title">Mots de passe non-Argon2 (bcrypt)</div>
            <div class="sec-risk-desc"><?= $sec['weak_hash_count']>0?"$sec[weak_hash_count] compte(s) avec hash bcrypt — recommandé : Argon2id":'Tous les comptes utilisent Argon2id' ?></div>
          </div>
          <span class="badge <?= $sec['weak_hash_count']>0?'b-wa':'b-ok' ?>"><?= $sec['weak_hash_count']>0?"$sec[weak_hash_count]":'OK' ?></span>
        </div>

        <div class="sec-risk-row">
          <div class="sec-risk-icon" style="background:<?= $sec['stale_token_count']>0?'var(--wa-l)':'var(--ok-l)' ?>;"><?= $sec['stale_token_count']>0?'🔑':'✅' ?></div>
          <div style="flex:1;">
            <div class="sec-risk-title">Tokens de réinitialisation actifs</div>
            <div class="sec-risk-desc"><?= $sec['stale_token_count']>0?"$sec[stale_token_count] token(s) non nettoyé(s) en base":'Aucun token traînant en base' ?></div>
          </div>
          <?php if($sec['stale_token_count']>0): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="clean_tokens">
            <input type="hidden" name="section" value="security">
            <button type="submit" class="btn btn-wa" style="font-size:10px;padding:3px 8px;">🧹 Nettoyer</button>
          </form>
          <?php else: ?>
          <span class="badge b-ok">OK</span>
          <?php endif; ?>
        </div>

        <div class="sec-risk-row">
          <div class="sec-risk-icon" style="background:<?= $sec['pending_count']>5?'var(--wa-l)':'var(--ok-l)' ?>;"><?= $sec['pending_count']>5?'⏳':'✅' ?></div>
          <div style="flex:1;">
            <div class="sec-risk-title">Comptes en attente d'activation</div>
            <div class="sec-risk-desc"><?= $sec['pending_count']>0?"$sec[pending_count] compte(s) non activé(s)":'Tous les comptes sont activés' ?></div>
          </div>
          <span class="badge <?= $sec['pending_count']>5?'b-wa':'b-n' ?>"><?= $sec['pending_count'] ?></span>
        </div>
      </div>
    </div>

    <!-- BRUTE FORCE DÉTAIL -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:var(--er);"></div><div class="ch-title">IPs suspectes — Brute force</div><span class="ch-cnt"><?= $sec['brute_ips_count'] ?> IP</span></div>
        <span style="font-size:11px;color:var(--tx3);">Seuil : 3+ tentatives échouées</span>
      </div>
      <?php if($sec['brute_force'] && $sec['brute_force']->num_rows>0): ?>
      <div class="tbl-wrap"><table>
        <thead><tr><th>Adresse IP</th><th>Tentatives</th><th>Dernière tentative</th><th>Niveau de risque</th></tr></thead>
        <tbody>
        <?php while($bf=$sec['brute_force']->fetch_assoc()): ?>
        <tr>
          <td class="mono" style="color:var(--er);font-weight:600;"><?= htmlspecialchars($bf['ip']) ?></td>
          <td><span class="badge <?= $bf['attempts']>=10?'b-er':($bf['attempts']>=5?'b-wa':'b-n') ?>"><?= $bf['attempts'] ?> tentatives</span></td>
          <td class="mono"><?= date('d/m/Y H:i',strtotime($bf['last_attempt'])) ?></td>
          <td><span class="badge <?= $bf['attempts']>=10?'b-er':($bf['attempts']>=5?'b-wa':'b-in') ?>"><?= $bf['attempts']>=10?'🔴 Critique':($bf['attempts']>=5?'🟠 Élevé':'🟡 Modéré') ?></span></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table></div>
      <?php else: ?>
      <div class="empty"><div class="empty-icon">🛡️</div><p>Aucune IP suspecte. La plateforme est protégée.</p></div>
      <?php endif; ?>
    </div>

    <!-- COMPTES HASH FAIBLES -->
    <?php if($sec['weak_hash_count']>0): ?>
    <div class="card">
      <div class="card-head">
        <div class="ch-left"><div class="ch-dot" style="background:var(--wa);"></div><div class="ch-title">Comptes avec hash bcrypt</div><span class="ch-cnt"><?= $sec['weak_hash_count'] ?></span></div>
        <span style="font-size:11px;color:var(--tx3);">Réinitialiser leur MDP pour migrer vers Argon2id</span>
      </div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Médecin</th><th>Email</th><th>Action</th></tr></thead>
        <tbody>
        <?php while($wh=$sec['weak_hash']->fetch_assoc()): ?>
        <tr>
          <td class="mono" style="color:var(--tx3);"><?= $wh['docid'] ?></td>
          <td class="name"><?= htmlspecialchars($wh['docname']) ?></td>
          <td><?= htmlspecialchars($wh['docemail']) ?></td>
          <td><button class="btn btn-pu" data-modal="resetpw" data-docid="<?= $wh['docid'] ?>" data-docname="<?= htmlspecialchars($wh['docname'],ENT_QUOTES) ?>">🔑 Réinitialiser MDP</button></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table></div>
    </div>
    <?php endif; ?>

<?php endif; ?>

  </div>
</div>
</div>

<!-- MODAL SUPPRESSION / TOGGLE -->
<div class="overlay" id="ov-delete">
  <div class="modal">
    <div class="modal-icon" id="md-icon" style="background:var(--er-l);">🗑️</div>
    <h3 id="md-title"></h3>
    <p id="md-msg"></p>
    <div class="modal-btns">
      <button class="btn btn-ghost" id="md-cancel">Annuler</button>
      <form id="md-form" method="POST" style="display:inline;">
        <input type="hidden" name="action"     id="md-action">
        <input type="hidden" name="rid"        id="md-rid">
        <input type="hidden" name="docid"      id="md-docid">
        <input type="hidden" name="new_status" id="md-ns">
        <input type="hidden" name="section"    value="<?= $section ?>">
        <button type="submit" class="btn btn-er" id="md-confirm">Confirmer</button>
      </form>
    </div>
  </div>
</div>

<!-- MODAL RESET MDP -->
<div class="overlay" id="ov-resetpw">
  <div class="modal">
    <div class="modal-icon" style="background:var(--pu-l);">🔑</div>
    <h3>Réinitialiser le mot de passe</h3>
    <p id="rp-msg">Définissez un nouveau mot de passe pour ce médecin.</p>
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="section" value="<?= $section ?>">
      <input type="hidden" name="docid" id="rp-docid">
      <div class="form-group">
        <label class="form-label">Nouveau mot de passe</label>
        <input type="password" name="new_password" id="rp-password" class="form-input" placeholder="Minimum 8 caractères" minlength="8" required>
      </div>
      <div class="modal-btns">
        <button type="button" class="btn btn-ghost" id="rp-cancel">Annuler</button>
        <button type="submit" class="btn btn-pu">🔑 Réinitialiser</button>
      </div>
    </form>
  </div>
</div>

<script>
(function tick(){
    var n=new Date(),el=document.getElementById('clock');
    if(el)el.textContent=[n.getHours(),n.getMinutes(),n.getSeconds()].map(function(x){return(''+x).padStart(2,'0')}).join(':');
    setTimeout(tick,1000);
})();

var ovDelete  = document.getElementById('ov-delete');
var ovResetpw = document.getElementById('ov-resetpw');

function closeAll(){
    ovDelete.classList.remove('show');
    ovResetpw.classList.remove('show');
}

document.getElementById('md-cancel').addEventListener('click', closeAll);
document.getElementById('rp-cancel').addEventListener('click', closeAll);
ovDelete.addEventListener('click',  function(e){ if(e.target===this) closeAll(); });
ovResetpw.addEventListener('click', function(e){ if(e.target===this) closeAll(); });

document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-modal]');
    if (!btn) return;

    var modal = btn.getAttribute('data-modal');

    if (modal === 'delete' || modal === 'toggle') {
        var action    = btn.getAttribute('data-action')    || '';
        var rid       = btn.getAttribute('data-rid')       || '';
        var docid     = btn.getAttribute('data-docid')     || '';
        var newstatus = btn.getAttribute('data-newstatus') || '';
        var title     = btn.getAttribute('data-title')     || 'Confirmer ?';
        var msg       = btn.getAttribute('data-msg')       || '';

        document.getElementById('md-title').textContent = title;
        document.getElementById('md-msg').textContent   = msg;
        document.getElementById('md-action').value      = action;
        document.getElementById('md-rid').value         = rid;
        document.getElementById('md-docid').value       = docid;
        document.getElementById('md-ns').value          = newstatus;

        var confirmBtn = document.getElementById('md-confirm');
        var icon       = document.getElementById('md-icon');

        if (action === 'toggle_doctor' && newstatus === 'active') {
            confirmBtn.className = 'btn btn-ok'; confirmBtn.textContent = 'Activer';
            icon.textContent = '✓'; icon.style.background = 'var(--ok-l)';
        } else if (action === 'toggle_doctor' && newstatus === 'suspended') {
            confirmBtn.className = 'btn btn-wa'; confirmBtn.textContent = 'Suspendre';
            icon.textContent = '⏸'; icon.style.background = 'var(--wa-l)';
        } else {
            confirmBtn.className = 'btn btn-er'; confirmBtn.textContent = 'Supprimer';
            icon.textContent = '🗑️'; icon.style.background = 'var(--er-l)';
        }
        ovDelete.classList.add('show');
    }

    if (modal === 'resetpw') {
        var docid   = btn.getAttribute('data-docid')  || '';
        var docname = btn.getAttribute('data-docname') || '';
        document.getElementById('rp-docid').value = docid;
        document.getElementById('rp-msg').textContent = 'Nouveau mot de passe pour Dr. ' + docname;
        document.getElementById('rp-password').value = '';
        ovResetpw.classList.add('show');
    }
/* ── Dark Mode ── */
/* ── Dark Mode ── */
(function() {
    // Application immédiate au chargement pour éviter le flash blanc
    if (localStorage.getItem('psyadmin_dark') === '1') {
        document.documentElement.classList.add('dark');
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    // On récupère le bon ID : darkModeToggle
    var btn = document.getElementById('darkModeToggle');
    
    if (btn) {
        // Initialiser l'icône au chargement
        btn.textContent = document.documentElement.classList.contains('dark') ? '☀️' : '🌙';

        btn.addEventListener('click', function() {
            // Toggle de la classe dark sur le <html>
            var isDark = document.documentElement.classList.toggle('dark');
            
            // Mise à jour de l'icône
            btn.textContent = isDark ? '☀️' : '🌙';
            
            // Sauvegarde du choix (1 pour activé, 0 pour désactivé)
            localStorage.setItem('psyadmin_dark', isDark ? '1' : '0');
            
            console.log("Dark mode : " + (isDark ? "ON" : "OFF"));
        });
    }
});
</script>
</body>
</html>