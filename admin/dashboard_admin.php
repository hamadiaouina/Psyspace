<?php
declare(strict_types=1);
ob_start();
session_start();

include "../connection.php";
if (!isset($con) && isset($conn)) { $con = $conn; }

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); 
    exit();
}

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
    $chart_data[] = ['label' => $label, 'appointments' => $appts];
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

$consultation_detail = null;
if (isset($_GET['view_consultation'])) {
    $cid = (int)$_GET['view_consultation'];
    $consultation_detail = $con->query("SELECT c.*, d.docname, d.docemail, a.patient_name, a.patient_phone FROM consultations c LEFT JOIN doctor d ON c.doctor_id=d.docid LEFT JOIN appointments a ON c.appointment_id=a.id WHERE c.id=$cid")->fetch_assoc();
}

$edit_doctor = null;
if (isset($_GET['edit_doctor'])) {
    $did = (int)$_GET['edit_doctor'];
    $edit_doctor = $con->query("SELECT * FROM doctor WHERE docid=$did")->fetch_assoc();
}

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
    $sec['score_color'] = $score >= 90 ? '#10b981' : ($score >= 70 ? '#3b82f6' : ($score >= 50 ? '#f59e0b' : '#ef4444'));
}

$section_labels = [
    'overview'      => "Vue d'ensemble",
    'doctors'       => 'Médecins',
    'patients'      => 'Patients',
    'appointments'  => 'Rendez-vous',
    'consultations' => 'Consultations',
    'logs'          => "Journal d'activité",
    'security'      => 'Security Center',
];

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
<link rel="icon" type="image/png" href="/assets/images/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>PsySpace · Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════
   RESET & BASE
═══════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{font-size:14px;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;}
a{text-decoration:none;color:inherit;}
button,input,select,textarea{font-family:inherit;font-size:inherit;}
img{max-width:100%;display:block;}
::-webkit-scrollbar{width:4px;height:4px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:99px;}

/* ═══════════════════════════════════════
   DESIGN TOKENS — LIGHT
═══════════════════════════════════════ */
:root {
  /* Base */
  --bg:         #f0f2f7;
  --surface:    #ffffff;
  --surface2:   #f8f9fc;
  --border:     #e4e7ef;
  --border2:    #eef0f6;

  /* Text */
  --tx:         #0d1117;
  --tx2:        #5a6480;
  --tx3:        #9ba3bf;

  /* Brand */
  --brand:      #4f6ef7;
  --brand-d:    #3a57e0;
  --brand-l:    #eef1fe;

  /* Semantic */
  --ok:         #10b981;
  --ok-l:       #ecfdf5;
  --ok-d:       #059669;
  --wa:         #f59e0b;
  --wa-l:       #fffbeb;
  --er:         #ef4444;
  --er-l:       #fef2f2;
  --er-d:       #dc2626;
  --pu:         #8b5cf6;
  --pu-l:       #f5f3ff;

  /* Layout */
  --sb: 230px;
  --r:  10px;
  --r2: 14px;

  /* Shadows */
  --sh:  0 1px 3px rgba(15,23,42,.06), 0 1px 2px rgba(15,23,42,.04);
  --sh2: 0 4px 16px rgba(15,23,42,.08), 0 2px 6px rgba(15,23,42,.04);
  --sh3: 0 12px 40px rgba(15,23,42,.12), 0 4px 12px rgba(15,23,42,.06);
}

/* ═══════════════════════════════════════
   DESIGN TOKENS — DARK
═══════════════════════════════════════ */
[data-theme="dark"] {
  --bg:         #0b0e18;
  --surface:    #131722;
  --surface2:   #1a1f2e;
  --border:     #252b3d;
  --border2:    #1e2333;

  --tx:         #e8ecf7;
  --tx2:        #7d89b0;
  --tx3:        #4a5370;

  --brand:      #6b87ff;
  --brand-d:    #5570f0;
  --brand-l:    #1a2040;

  --ok-l:       #0a2e1f;
  --wa-l:       #2e1f05;
  --er-l:       #2e0a0a;
  --pu-l:       #1e1030;

  --sh:  0 1px 3px rgba(0,0,0,.3), 0 1px 2px rgba(0,0,0,.2);
  --sh2: 0 4px 16px rgba(0,0,0,.35), 0 2px 6px rgba(0,0,0,.2);
  --sh3: 0 12px 40px rgba(0,0,0,.5), 0 4px 12px rgba(0,0,0,.3);
}

/* ═══════════════════════════════════════
   LAYOUT
═══════════════════════════════════════ */
body {
  font-family: 'Sora', sans-serif;
  background: var(--bg);
  color: var(--tx);
  min-height: 100vh;
  display: flex;
  transition: background .25s, color .25s;
}

/* ═══════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════ */
.sidebar {
  width: var(--sb);
  flex-shrink: 0;
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0;
  height: 100vh;
  z-index: 200;
  transition: background .25s, border-color .25s;
}

.sb-brand {
  padding: 20px 16px 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.sb-logo {
  display: flex;
  align-items: center;
  gap: 8px;
}

.sb-logo-icon {
  width: 32px;
  height: 32px;
  background: linear-gradient(135deg, var(--brand), var(--brand-d));
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  box-shadow: 0 4px 12px rgba(79,110,247,.35);
}

.sb-logo-text {
  font-size: 15px;
  font-weight: 700;
  letter-spacing: -.03em;
  color: var(--tx);
}

.sb-logo-text span { color: var(--brand); }

.sb-tag {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .12em;
  color: var(--brand);
  background: var(--brand-l);
  padding: 3px 8px;
  border-radius: 5px;
  border: 1px solid rgba(79,110,247,.2);
}

.sb-nav {
  flex: 1;
  padding: 12px 10px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.sb-grp {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .14em;
  color: var(--tx3);
  padding: 12px 8px 5px;
}

.sb-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 10px;
  border-radius: var(--r);
  font-size: 12.5px;
  font-weight: 500;
  color: var(--tx2);
  transition: all .15s;
  border: 1px solid transparent;
  position: relative;
}

.sb-item:hover {
  background: var(--surface2);
  color: var(--tx);
}

.sb-item.on {
  background: var(--brand-l);
  color: var(--brand);
  border-color: rgba(79,110,247,.15);
  font-weight: 600;
}

.sb-item svg {
  width: 15px;
  height: 15px;
  flex-shrink: 0;
}

.sb-cnt {
  margin-left: auto;
  font-size: 10px;
  font-weight: 600;
  font-family: 'JetBrains Mono', monospace;
  background: var(--surface2);
  color: var(--tx3);
  padding: 2px 7px;
  border-radius: 99px;
  border: 1px solid var(--border);
}

.sb-item.on .sb-cnt {
  background: rgba(79,110,247,.12);
  color: var(--brand);
  border-color: rgba(79,110,247,.2);
}

.sb-foot {
  padding: 10px;
  border-top: 1px solid var(--border);
}

.sb-user {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 10px;
  background: var(--surface2);
  border-radius: var(--r);
  margin-bottom: 6px;
  border: 1px solid var(--border);
}

.sb-av {
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: linear-gradient(135deg, var(--brand), var(--brand-d));
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
  box-shadow: 0 3px 8px rgba(79,110,247,.3);
}

.sb-uname { font-size: 12px; font-weight: 600; }
.sb-urole { font-size: 10px; color: var(--tx3); margin-top: 1px; }

.sb-out {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 12px;
  border-radius: var(--r);
  font-size: 12px;
  font-weight: 500;
  color: var(--tx2);
  border: 1px solid var(--border);
  background: none;
  cursor: pointer;
  width: 100%;
  transition: all .15s;
}

.sb-out:hover {
  background: var(--er-l);
  color: var(--er);
  border-color: rgba(239,68,68,.25);
}

/* ═══════════════════════════════════════
   MAIN & TOPBAR
═══════════════════════════════════════ */
.main {
  margin-left: var(--sb);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.topbar {
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 100;
  transition: background .25s, border-color .25s;
}

.tb-left { display: flex; align-items: center; gap: 12px; }

.tb-breadcrumb {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--tx3);
}

.tb-breadcrumb .sep { opacity: .5; }
.tb-breadcrumb .cur { color: var(--tx); font-weight: 600; }

.tb-right { display: flex; align-items: center; gap: 8px; }

.tb-alert {
  font-size: 10.5px;
  font-weight: 600;
  padding: 4px 11px;
  border-radius: 7px;
  background: var(--er-l);
  color: var(--er-d);
  border: 1px solid rgba(239,68,68,.2);
  display: flex;
  align-items: center;
  gap: 5px;
}

.tb-alert.purple {
  background: var(--pu-l);
  color: var(--pu);
  border-color: rgba(139,92,246,.2);
}

.tb-clock {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11.5px;
  font-weight: 500;
  color: var(--tx2);
  background: var(--surface2);
  padding: 6px 13px;
  border-radius: 8px;
  border: 1px solid var(--border);
  min-width: 80px;
  text-align: center;
}

/* Dark Mode Button */
.dark-btn {
  width: 36px;
  height: 36px;
  border-radius: 9px;
  border: 1px solid var(--border);
  background: var(--surface2);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  transition: all .2s;
  flex-shrink: 0;
}

.dark-btn:hover {
  background: var(--brand-l);
  border-color: rgba(79,110,247,.25);
  transform: rotate(15deg);
}

/* ═══════════════════════════════════════
   CONTENT
═══════════════════════════════════════ */
.content { padding: 22px 24px; flex: 1; }

/* Page header */
.page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 20px;
}
.page-title { font-size: 20px; font-weight: 800; letter-spacing: -.03em; }
.page-sub { font-size: 12px; color: var(--tx3); margin-top: 3px; font-weight: 400; }

/* ═══════════════════════════════════════
   STAT CARDS
═══════════════════════════════════════ */
.stats {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 12px;
  margin-bottom: 22px;
}

.sc {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r2);
  padding: 16px;
  box-shadow: var(--sh);
  transition: all .2s;
  position: relative;
  overflow: hidden;
}

.sc::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: var(--sc-color, var(--brand));
  opacity: 0;
  transition: opacity .2s;
}

.sc:hover {
  box-shadow: var(--sh2);
  transform: translateY(-2px);
}

.sc:hover::before { opacity: 1; }

.sc-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.sc-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.sc-badge {
  font-size: 9px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 99px;
  letter-spacing: .04em;
}

.sc-val {
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -.04em;
  line-height: 1;
  color: var(--tx);
  font-variant-numeric: tabular-nums;
}

.sc-lbl {
  font-size: 11px;
  color: var(--tx3);
  font-weight: 500;
  margin-top: 5px;
}

/* ═══════════════════════════════════════
   TOOLBAR
═══════════════════════════════════════ */
.toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
  flex-wrap: wrap;
}

.search-box {
  display: flex;
  align-items: center;
  gap: 9px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 9px;
  padding: 8px 14px;
  flex: 1;
  min-width: 220px;
  max-width: 380px;
  transition: border-color .15s, box-shadow .15s;
}

.search-box:focus-within {
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(79,110,247,.1);
}

.search-box input {
  border: none;
  outline: none;
  font-size: 13px;
  color: var(--tx);
  background: transparent;
  width: 100%;
}

.search-box input::placeholder { color: var(--tx3); }
.search-box svg { color: var(--tx3); flex-shrink: 0; }

/* ═══════════════════════════════════════
   CARD
═══════════════════════════════════════ */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r2);
  box-shadow: var(--sh);
  overflow: hidden;
  margin-bottom: 16px;
  transition: background .25s, border-color .25s;
}

.card:last-child { margin-bottom: 0; }

.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  background: var(--surface);
}

.ch-left { display: flex; align-items: center; gap: 10px; }

.ch-pill {
  width: 6px; height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}

.ch-title { font-size: 13px; font-weight: 700; }

.ch-cnt {
  font-size: 10px;
  font-weight: 700;
  font-family: 'JetBrains Mono', monospace;
  background: var(--surface2);
  color: var(--tx3);
  padding: 2px 9px;
  border-radius: 99px;
  border: 1px solid var(--border);
}

.ch-link {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--brand);
  transition: opacity .15s;
}

.ch-link:hover { opacity: .7; }

/* ═══════════════════════════════════════
   TABLE
═══════════════════════════════════════ */
.tbl-wrap { overflow-x: auto; }

table { width: 100%; border-collapse: collapse; }

thead th {
  padding: 10px 16px;
  text-align: left;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--tx3);
  background: var(--surface2);
  white-space: nowrap;
  border-bottom: 1px solid var(--border);
}

tbody tr {
  border-bottom: 1px solid var(--border2);
  transition: background .1s;
}

tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: var(--surface2); }

td {
  padding: 11px 16px;
  font-size: 13px;
  color: var(--tx2);
  vertical-align: middle;
}

td.name { color: var(--tx); font-weight: 600; }

td.mono {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11.5px;
}

/* ═══════════════════════════════════════
   BADGES
═══════════════════════════════════════ */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 99px;
  font-size: 10px;
  font-weight: 700;
  white-space: nowrap;
  letter-spacing: .02em;
}

.b-ok  { background: var(--ok-l); color: var(--ok-d); border: 1px solid rgba(16,185,129,.2); }
.b-wa  { background: var(--wa-l); color: #b45309; border: 1px solid rgba(245,158,11,.2); }
.b-er  { background: var(--er-l); color: var(--er-d); border: 1px solid rgba(239,68,68,.2); }
.b-in  { background: var(--brand-l); color: var(--brand-d); border: 1px solid rgba(79,110,247,.2); }
.b-pu  { background: var(--pu-l); color: var(--pu); border: 1px solid rgba(139,92,246,.2); }
.b-n   { background: var(--surface2); color: var(--tx3); border: 1px solid var(--border); }

/* ═══════════════════════════════════════
   BUTTONS
═══════════════════════════════════════ */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid;
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  transition: all .15s;
  background: none;
  white-space: nowrap;
}

.btn-primary { color: #fff; border-color: var(--brand); background: var(--brand); }
.btn-primary:hover { background: var(--brand-d); border-color: var(--brand-d); }

.btn-ok  { color: var(--ok-d); border-color: rgba(16,185,129,.3); background: var(--ok-l); }
.btn-ok:hover  { background: #d1fae5; }

.btn-wa  { color: #b45309; border-color: rgba(245,158,11,.3); background: var(--wa-l); }
.btn-wa:hover  { background: #fde68a; }

.btn-er  { color: var(--er-d); border-color: rgba(239,68,68,.3); background: var(--er-l); }
.btn-er:hover  { background: #fee2e2; }

.btn-in  { color: var(--brand-d); border-color: rgba(79,110,247,.3); background: var(--brand-l); }
.btn-in:hover  { background: #dbeafe; }

.btn-pu  { color: var(--pu); border-color: rgba(139,92,246,.3); background: var(--pu-l); }
.btn-pu:hover  { background: #ede9fe; }

.btn-ghost { color: var(--tx2); border-color: var(--border); background: var(--surface); }
.btn-ghost:hover { background: var(--surface2); color: var(--tx); }

/* ═══════════════════════════════════════
   OVERVIEW LAYOUT
═══════════════════════════════════════ */
.ov-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

.ov-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 18px;
  border-bottom: 1px solid var(--border2);
  transition: background .1s;
}

.ov-row:last-child { border-bottom: none; }
.ov-row:hover { background: var(--surface2); }

.ov-av {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  flex-shrink: 0;
}

/* ═══════════════════════════════════════
   CHART
═══════════════════════════════════════ */
.chart-wrap { padding: 18px 20px 12px; }

.chart-container {
  position: relative;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  gap: 8px;
  height: 110px;
}

.chart-bar-grp {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
  flex: 1;
}

.chart-bar {
  width: 100%;
  border-radius: 5px 5px 0 0;
  transition: opacity .2s, transform .2s;
  min-height: 3px;
  cursor: pointer;
}

.chart-bar:hover { opacity: .75; transform: scaleY(1.03); transform-origin: bottom; }

.chart-lbl { font-size: 9.5px; color: var(--tx3); font-weight: 600; }

.chart-tooltip {
  position: absolute;
  top: -32px; left: 50%; transform: translateX(-50%);
  background: var(--tx);
  color: var(--surface);
  font-size: 10px;
  font-weight: 700;
  padding: 4px 9px;
  border-radius: 6px;
  white-space: nowrap;
  display: none;
}

.chart-bar-grp:hover .chart-tooltip { display: block; }

/* ═══════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════ */
.empty {
  padding: 52px 20px;
  text-align: center;
  color: var(--tx3);
}

.empty-icon { font-size: 28px; margin-bottom: 10px; opacity: .4; }
.empty p { font-size: 12.5px; }

/* ═══════════════════════════════════════
   MODALS
═══════════════════════════════════════ */
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(11,14,24,.65);
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  opacity: 0;
  pointer-events: none;
  transition: opacity .2s;
}

.overlay.show { opacity: 1; pointer-events: all; }

.modal {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 26px;
  max-width: 440px;
  width: 92%;
  box-shadow: var(--sh3);
  transform: translateY(12px) scale(.96);
  transition: transform .25s cubic-bezier(.34,1.56,.64,1);
}

.overlay.show .modal { transform: translateY(0) scale(1); }

.modal-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  margin-bottom: 14px;
}

.modal h3 { font-size: 15px; font-weight: 700; margin-bottom: 6px; }
.modal p { font-size: 13px; color: var(--tx2); line-height: 1.65; }
.modal-btns { display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px; }

/* ═══════════════════════════════════════
   FORMS
═══════════════════════════════════════ */
.form-group { margin-bottom: 15px; }

.form-label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--tx3);
  margin-bottom: 6px;
}

.form-input {
  width: 100%;
  border: 1px solid var(--border);
  border-radius: 9px;
  padding: 10px 13px;
  font-size: 13px;
  color: var(--tx);
  background: var(--surface);
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}

.form-input:focus {
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(79,110,247,.1);
}

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* ═══════════════════════════════════════
   DETAIL PANEL
═══════════════════════════════════════ */
.detail-section { margin-bottom: 18px; }

.detail-title {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .1em;
  color: var(--tx3);
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 7px 0;
  border-bottom: 1px solid var(--border2);
  font-size: 13px;
  gap: 12px;
}

.detail-row:last-child { border-bottom: none; }
.detail-key { color: var(--tx2); flex-shrink: 0; }
.detail-val { font-weight: 600; color: var(--tx); text-align: right; }

.detail-text {
  background: var(--surface2);
  border-radius: 9px;
  padding: 13px;
  font-size: 12.5px;
  line-height: 1.75;
  color: var(--tx2);
  max-height: 200px;
  overflow-y: auto;
  white-space: pre-wrap;
  border: 1px solid var(--border);
}

/* ═══════════════════════════════════════
   LOGS
═══════════════════════════════════════ */
.log-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 10px 18px;
  border-bottom: 1px solid var(--border2);
  transition: background .1s;
}

.log-row:hover { background: var(--surface2); }
.log-row:last-child { border-bottom: none; }

.log-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  margin-top: 5px;
  flex-shrink: 0;
}

.log-action { font-size: 12.5px; font-weight: 600; color: var(--tx); font-family: 'JetBrains Mono', monospace; }
.log-detail { font-size: 11.5px; color: var(--tx3); margin-top: 2px; }
.log-time { font-size: 10px; font-family: 'JetBrains Mono', monospace; color: var(--tx3); margin-left: auto; white-space: nowrap; flex-shrink: 0; }

/* ═══════════════════════════════════════
   SECURITY CENTER
═══════════════════════════════════════ */
.sec-grid { display: grid; grid-template-columns: 240px 1fr; gap: 16px; margin-bottom: 16px; }

.sec-score-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--r2);
  box-shadow: var(--sh);
  padding: 28px 20px;
  text-align: center;
}

.score-ring {
  position: relative;
  width: 120px;
  height: 120px;
  margin: 0 auto 16px;
}

.score-ring svg {
  width: 120px;
  height: 120px;
  transform: rotate(-90deg);
}

.score-inner {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.score-val { font-size: 28px; font-weight: 800; letter-spacing: -.04em; }
.score-max { font-size: 10px; color: var(--tx3); margin-top: 2px; }
.score-lbl { font-size: 15px; font-weight: 700; margin-top: 4px; }
.score-sub { font-size: 11px; color: var(--tx3); margin-top: 3px; }

.sec-risk-row {
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--border2);
  transition: background .1s;
}

.sec-risk-row:hover { background: var(--surface2); }
.sec-risk-row:last-child { border-bottom: none; }

.sec-risk-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}

.sec-risk-title { font-size: 13px; font-weight: 600; color: var(--tx); }
.sec-risk-desc { font-size: 11.5px; color: var(--tx3); margin-top: 2px; }

/* ═══════════════════════════════════════
   HIGHLIGHT CARD (edit/detail)
═══════════════════════════════════════ */
.card-highlight {
  border: 2px solid var(--brand) !important;
}

.card-highlight .card-head {
  background: var(--brand-l);
}

/* ═══════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════ */
@media(max-width:1200px) { .stats { grid-template-columns: repeat(3,1fr); } }
@media(max-width:960px)  { .stats { grid-template-columns: repeat(2,1fr); } .ov-grid { grid-template-columns: 1fr; } .sec-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<!-- ══════════════════════ DARK MODE SCRIPT (avant rendu) ══════════════════════ -->
<script>
// Application immédiate pour éviter le flash blanc
(function() {
    if (localStorage.getItem('psyadmin_dark') === '1') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
})();
</script>

<div class="layout" style="display:flex;min-height:100vh;">

<!-- ████████████████ SIDEBAR ████████████████ -->
<aside class="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">
      <div class="sb-logo-icon">🧠</div>
      <div class="sb-logo-text">Psy<span>Space</span></div>
    </div>
    <div class="sb-tag">Admin</div>
  </div>

  <nav class="sb-nav">
    <div class="sb-grp">Dashboard</div>
    <a href="?section=overview" class="sb-item <?= $section==='overview'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
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
      Journal d'activité
    </a>
    <a href="?section=security" class="sb-item <?= $section==='security'?'on':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      Security Center
    </a>
  </nav>

  <div class="sb-foot">
    <div class="sb-user">
      <div class="sb-av"><?= $admin_initial ?></div>
      <div>
        <div class="sb-uname"><?= $admin_name ?></div>
        <div class="sb-urole">Administrateur</div>
      </div>
    </div>
    <a href="logout.php" class="sb-out">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Déconnexion
    </a>
  </div>
</aside>

<!-- ████████████████ MAIN ████████████████ -->
<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="tb-left">
      <div class="tb-breadcrumb">
        <span>PsySpace</span>
        <span class="sep">›</span>
        <span class="cur"><?= $section_labels[$section] ?? 'Dashboard' ?></span>
      </div>
    </div>
    <div class="tb-right">
      <?php if($stat_pending>0): ?>
      <div class="tb-alert">⚠ <?= $stat_pending ?> en attente</div>
      <?php endif; ?>
      <?php if($stat_suspended>0): ?>
      <div class="tb-alert purple">⏸ <?= $stat_suspended ?> suspendu<?= $stat_suspended>1?'s':'' ?></div>
      <?php endif; ?>
      <button class="dark-btn" id="darkModeBtn" title="Basculer le thème" aria-label="Toggle dark mode">🌙</button>
      <div class="tb-clock" id="clock">--:--:--</div>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="content">

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div>
        <div class="page-title"><?= $section_labels[$section] ?? 'Dashboard' ?></div>
        <div class="page-sub">PsySpace · Panneau d'administration</div>
      </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stats">
      <div class="sc" style="--sc-color:var(--brand);">
        <div class="sc-top">
          <div class="sc-icon" style="background:var(--brand-l);">👨‍⚕️</div>
          <span class="sc-badge" style="background:var(--ok-l);color:var(--ok-d);"><?= $stat_active ?> actifs</span>
        </div>
        <div class="sc-val"><?= $stat_doctors ?></div>
        <div class="sc-lbl">Médecins</div>
      </div>
      <div class="sc" style="--sc-color:var(--ok);">
        <div class="sc-top"><div class="sc-icon" style="background:var(--ok-l);">🧑</div></div>
        <div class="sc-val"><?= $stat_patients ?></div>
        <div class="sc-lbl">Patients</div>
      </div>
      <div class="sc" style="--sc-color:var(--wa);">
        <div class="sc-top"><div class="sc-icon" style="background:var(--wa-l);">📅</div></div>
        <div class="sc-val"><?= $stat_appointments ?></div>
        <div class="sc-lbl">Rendez-vous</div>
      </div>
      <div class="sc" style="--sc-color:var(--pu);">
        <div class="sc-top"><div class="sc-icon" style="background:var(--pu-l);">📋</div></div>
        <div class="sc-val"><?= $stat_consultations ?></div>
        <div class="sc-lbl">Consultations</div>
      </div>
      <div class="sc" style="--sc-color:var(--er);">
        <div class="sc-top">
          <div class="sc-icon" style="background:var(--er-l);">⏳</div>
          <?php if($stat_pending>0): ?><span class="sc-badge" style="background:var(--er-l);color:var(--er-d);">À activer</span><?php endif; ?>
        </div>
        <div class="sc-val" style="<?= $stat_pending>0?'color:var(--er)':'' ?>"><?= $stat_pending ?></div>
        <div class="sc-lbl">En attente</div>
      </div>
      <div class="sc" style="--sc-color:var(--pu);">
        <div class="sc-top"><div class="sc-icon" style="background:var(--pu-l);">⏸</div></div>
        <div class="sc-val" style="<?= $stat_suspended>0?'color:var(--pu)':'' ?>"><?= $stat_suspended ?></div>
        <div class="sc-lbl">Suspendus</div>
      </div>
    </div>

<!-- ══════════════════════════════════════════
     OVERVIEW
══════════════════════════════════════════ -->
<?php if($section==='overview'): ?>

    <div class="card" style="margin-bottom:16px;">
      <div class="card-head">
        <div class="ch-left">
          <div class="ch-pill" style="background:var(--brand);"></div>
          <div class="ch-title">Activité — 7 derniers jours</div>
        </div>
        <span style="font-size:11px;color:var(--tx3);">Rendez-vous / jour</span>
      </div>
      <div class="chart-wrap">
        <?php $max_val=1; foreach($chart_data as $d) $max_val=max($max_val,$d['appointments']); ?>
        <div class="chart-bars">
          <?php foreach($chart_data as $d):
            $h = max(4, round($d['appointments']/$max_val*100));
          ?>
          <div class="chart-bar-grp" style="position:relative;">
            <div class="chart-tooltip"><?= $d['appointments'] ?> RDV</div>
            <div class="chart-bar" style="height:<?= $h ?>px;background:linear-gradient(180deg,var(--brand),var(--brand-d));opacity:.85;"></div>
            <div class="chart-lbl"><?= $d['label'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="ov-grid">
      <div class="card">
        <div class="card-head">
          <div class="ch-left"><div class="ch-pill" style="background:var(--brand);"></div><div class="ch-title">Médecins récents</div></div>
          <a href="?section=doctors" class="ch-link">Voir tout →</a>
        </div>
        <?php $ld=$con->query("SELECT * FROM doctor ORDER BY docid DESC LIMIT 6");
        if($ld && $ld->num_rows): while($d=$ld->fetch_assoc()):
          $bclass=$d['status']==='active'?'b-ok':($d['status']==='suspended'?'b-pu':'b-wa');
          $blabel=$d['status']==='active'?'Actif':($d['status']==='suspended'?'Suspendu':'En attente');
        ?>
        <div class="ov-row">
          <div style="display:flex;align-items:center;gap:9px;">
            <div class="ov-av" style="background:var(--brand-l);color:var(--brand);"><?= strtoupper(substr($d['docname'],0,1)) ?></div>
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
          <div class="ch-left"><div class="ch-pill" style="background:var(--pu);"></div><div class="ch-title">Dernières consultations</div></div>
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
          <div class="ch-left"><div class="ch-pill" style="background:var(--wa);"></div><div class="ch-title">Rendez-vous récents</div></div>
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

<!-- ══════════════════════════════════════════
     DOCTORS
══════════════════════════════════════════ -->
<?php elseif($section==='doctors'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="doctors">
        <div class="search-box">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, email, spécialité...">
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
        <div class="ch-left">
          <div class="ch-pill" style="background:var(--brand);"></div>
          <div class="ch-title">Médecins inscrits</div>
          <span class="ch-cnt"><?= $doctors?->num_rows ?? 0 ?></span>
        </div>
      </div>
      <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Médecin</th><th>Email</th><th>Spécialité</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if($doctors && $doctors->num_rows): while($d=$doctors->fetch_assoc()):
          $bclass=$d['status']==='active'?'b-ok':($d['status']==='suspended'?'b-pu':'b-wa');
          $blabel=$d['status']==='active'?'Actif':($d['status']==='suspended'?'Suspendu':'En attente');
        ?>
        <tr>
          <td class="mono" style="color:var(--tx3);"><?= $d['docid'] ?></td>
          <td class="name">
            <div style="display:flex;align-items:center;gap:9px;">
              <div class="ov-av" style="background:var(--brand-l);color:var(--brand);"><?= strtoupper(substr($d['docname'],0,1)) ?></div>
              <?= htmlspecialchars($d['docname']) ?>
            </div>
          </td>
          <td style="color:var(--tx2);"><?= htmlspecialchars($d['docemail']) ?></td>
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
        <?php endwhile; else: ?>
        <tr><td colspan="6"><div class="empty"><div class="empty-icon">👨‍⚕️</div><p>Aucun médecin<?= $search?' pour "'.$search.'"':'' ?></p></div></td></tr>
        <?php endif; ?>
        </tbody>
      </table></div>
    </div>

    <?php if($edit_doctor): ?>
    <div class="card card-highlight">
      <div class="card-head">
        <div class="ch-left">
          <div class="ch-pill" style="background:var(--brand);"></div>
          <div class="ch-title" style="color:var(--brand);">Modifier : <?= htmlspecialchars($edit_doctor['docname']) ?></div>
        </div>
        <a href="?section=doctors" class="btn btn-ghost">✕ Fermer</a>
      </div>
      <div style="padding:22px;">
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
            <div class="form-group" style="grid-column:1/-1;">
              <label class="form-label">Spécialité</label>
              <input type="text" name="specialty" class="form-input" value="<?= htmlspecialchars($edit_doctor['specialty']??'') ?>" placeholder="Ex: Psychologue clinicien">
            </div>
          </div>
          <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
            <a href="?section=doctors" class="btn btn-ghost">Annuler</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

<!-- ══════════════════════════════════════════
     PATIENTS
══════════════════════════════════════════ -->
<?php elseif($section==='patients'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="patients">
        <div class="search-box">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, téléphone...">
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
        <div class="ch-left"><div class="ch-pill" style="background:var(--ok);"></div><div class="ch-title">Patients</div><span class="ch-cnt"><?= $patients?->num_rows ?? 0 ?></span></div>
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
        <?php endwhile; else: ?>
        <tr><td colspan="6"><div class="empty"><div class="empty-icon">🧑</div><p>Aucun patient<?= $search?' pour "'.$search.'"':'' ?></p></div></td></tr>
        <?php endif; ?>
        </tbody>
      </table></div>
    </div>

<!-- ══════════════════════════════════════════
     APPOINTMENTS
══════════════════════════════════════════ -->
<?php elseif($section==='appointments'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="appointments">
        <div class="search-box">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Patient, médecin...">
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
        <div class="ch-left"><div class="ch-pill" style="background:var(--wa);"></div><div class="ch-title">Rendez-vous</div><span class="ch-cnt"><?= $appointments?->num_rows ?? 0 ?></span></div>
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
        <?php endwhile; else: ?>
        <tr><td colspan="7"><div class="empty"><div class="empty-icon">📅</div><p>Aucun rendez-vous<?= $search?' pour "'.$search.'"':'' ?></p></div></td></tr>
        <?php endif; ?>
        </tbody>
      </table></div>
    </div>

<!-- ══════════════════════════════════════════
     CONSULTATIONS
══════════════════════════════════════════ -->
<?php elseif($section==='consultations'): ?>

    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <input type="hidden" name="section" value="consultations">
        <div class="search-box">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Patient, médecin...">
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
        <div class="ch-left"><div class="ch-pill" style="background:var(--pu);"></div><div class="ch-title" style="color:var(--pu);">Consultation #<?= $consultation_detail['id'] ?></div></div>
        <a href="?section=consultations" class="btn btn-ghost">✕ Fermer</a>
      </div>
      <div style="padding:22px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
          <div>
            <div class="detail-section">
              <div class="detail-title">Informations</div>
              <div class="detail-row"><span class="detail-key">Patient</span><span class="detail-val"><?= htmlspecialchars($consultation_detail['patient_name']??'—') ?></span></div>
              <div class="detail-row"><span class="detail-key">Téléphone</span><span class="detail-val"><?= htmlspecialchars($consultation_detail['patient_phone']??'—') ?></span></div>
              <div class="detail-row"><span class="detail-key">Médecin</span><span class="detail-val">Dr. <?= htmlspecialchars($consultation_detail['docname']??'—') ?></span></div>
              <div class="detail-row"><span class="detail-key">Date</span><span class="detail-val"><?= date('d/m/Y H:i',strtotime($consultation_detail['date_consultation'])) ?></span></div>
              <div class="detail-row"><span class="detail-key">Durée</span><span class="detail-val"><?= $consultation_detail['duree_minutes']>0?$consultation_detail['duree_minutes'].' min':'—' ?></span></div>
              <div class="detail-row"><span class="detail-key">Résumé IA</span><span class="detail-val"><?= !empty($consultation_detail['resume_ia'])?'<span class="badge b-ok">✓ Généré</span>':'<span class="badge b-n">—</span>' ?></span></div>
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
        <div class="ch-left"><div class="ch-pill" style="background:var(--pu);"></div><div class="ch-title">Consultations archivées</div><span class="ch-cnt"><?= $consultations?->num_rows ?? 0 ?></span></div>
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
        <?php endwhile; else: ?>
        <tr><td colspan="7"><div class="empty"><div class="empty-icon">📋</div><p>Aucune consultation<?= $search?' pour "'.$search.'"':'' ?></p></div></td></tr>
        <?php endif; ?>
        </tbody>
      </table></div>
    </div>

<!-- ══════════════════════════════════════════
     LOGS
══════════════════════════════════════════ -->
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
        <div class="ch-left"><div class="ch-pill" style="background:var(--er);"></div><div class="ch-title">Journal d'activité admin</div></div>
        <span style="font-size:11px;color:var(--tx3);">150 entrées max</span>
      </div>
      <?php
      $log_colors=[
        'delete_doctor'=>'var(--er)','delete_patient'=>'var(--er)','delete_appointment'=>'var(--er)','delete_consultation'=>'var(--er)',
        'edit_doctor'=>'var(--brand)','reset_password'=>'var(--pu)','toggle_doctor'=>'var(--wa)',
        'export_csv'=>'var(--ok)','login_failed'=>'var(--er)','admin_login'=>'var(--ok)',
        'doctor_login'=>'var(--brand)','clean_tokens'=>'var(--wa)'
      ];
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

<!-- ══════════════════════════════════════════
     SECURITY
══════════════════════════════════════════ -->
<?php elseif($section==='security'): ?>

    <div class="sec-grid">
      <div class="sec-score-card">
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--tx3);margin-bottom:18px;">Score de sécurité</div>
        <div class="score-ring">
          <svg viewBox="0 0 36 36">
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="var(--border)" stroke-width="2.5"/>
            <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?= $sec['score_color'] ?>" stroke-width="2.5"
              stroke-dasharray="<?= $sec['score'] ?> 100" stroke-linecap="round"/>
          </svg>
          <div class="score-inner">
            <span class="score-val" style="color:<?= $sec['score_color'] ?>;"><?= $sec['score'] ?></span>
            <span class="score-max">/100</span>
          </div>
        </div>
        <div class="score-lbl" style="color:<?= $sec['score_color'] ?>;"><?= $sec['score_label'] ?></div>
        <div class="score-sub">Calculé en temps réel</div>
      </div>

      <div class="card" style="margin-bottom:0;">
        <div class="card-head"><div class="ch-left"><div class="ch-pill" style="background:var(--er);"></div><div class="ch-title">Risques détectés</div></div></div>

        <div class="sec-risk-row">
          <div class="sec-risk-icon" style="background:<?= $sec['brute_ips_count']>0?'var(--er-l)':'var(--ok-l)' ?>;"><?= $sec['brute_ips_count']>0?'🚨':'✅' ?></div>
          <div style="flex:1;">
            <div class="sec-risk-title">Brute force — IPs suspectes</div>
            <div class="sec-risk-desc"><?= $sec['brute_ips_count']>0?"$sec[brute_ips_count] IP(s) avec 3+ tentatives · $sec[total_failed] échec(s) total":'Aucune IP suspecte détectée' ?></div>
          </div>
          <span class="badge <?= $sec['brute_ips_count']>0?'b-er':'b-ok' ?>"><?= $sec['brute_ips_count']>0?"$sec[brute_ips_count] IP":'OK' ?></span>
        </div>

        <div class="sec-risk-row">
          <div class="sec-risk-icon" style="background:<?= $sec['weak_hash_count']>0?'var(--wa-l)':'var(--ok-l)' ?>;"><?= $sec['weak_hash_count']>0?'⚠️':'✅' ?></div>
          <div style="flex:1;">
            <div class="sec-risk-title">Mots de passe non-Argon2 (bcrypt)</div>
            <div class="sec-risk-desc"><?= $sec['weak_hash_count']>0?"$sec[weak_hash_count] compte(s) avec hash bcrypt":'Tous les comptes utilisent Argon2id' ?></div>
          </div>
          <span class="badge <?= $sec['weak_hash_count']>0?'b-wa':'b-ok' ?>"><?= $sec['weak_hash_count']>0?"$sec[weak_hash_count]":'OK' ?></span>
        </div>

        <div class="sec-risk-row">
          <div class="sec-risk-icon" style="background:<?= $sec['stale_token_count']>0?'var(--wa-l)':'var(--ok-l)' ?>;"><?= $sec['stale_token_count']>0?'🔑':'✅' ?></div>
          <div style="flex:1;">
            <div class="sec-risk-title">Tokens de réinitialisation actifs</div>
            <div class="sec-risk-desc"><?= $sec['stale_token_count']>0?"$sec[stale_token_count] token(s) non nettoyé(s)":'Aucun token traînant en base' ?></div>
          </div>
          <?php if($sec['stale_token_count']>0): ?>
          <form method="POST">
            <input type="hidden" name="action" value="clean_tokens">
            <input type="hidden" name="section" value="security">
            <button type="submit" class="btn btn-wa">🧹 Nettoyer</button>
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

    <div class="card" style="margin-bottom:16px;">
      <div class="card-head">
        <div class="ch-left"><div class="ch-pill" style="background:var(--er);"></div><div class="ch-title">IPs suspectes</div><span class="ch-cnt"><?= $sec['brute_ips_count'] ?> IP</span></div>
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

    <?php if($sec['weak_hash_count']>0): ?>
    <div class="card">
      <div class="card-head">
        <div class="ch-left"><div class="ch-pill" style="background:var(--wa);"></div><div class="ch-title">Comptes avec hash bcrypt</div><span class="ch-cnt"><?= $sec['weak_hash_count'] ?></span></div>
        <span style="font-size:11px;color:var(--tx3);">Réinitialiser leur MDP → migration Argon2id</span>
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

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /layout -->

<!-- ████████████████ MODAL DELETE/TOGGLE ████████████████ -->
<div class="overlay" id="ov-delete">
  <div class="modal">
    <div class="modal-icon" id="md-icon" style="background:var(--er-l);">🗑️</div>
    <h3 id="md-title">Confirmer ?</h3>
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

<!-- ████████████████ MODAL RESET MDP ████████████████ -->
<div class="overlay" id="ov-resetpw">
  <div class="modal">
    <div class="modal-icon" style="background:var(--pu-l);">🔑</div>
    <h3>Réinitialiser le mot de passe</h3>
    <p id="rp-msg">Définissez un nouveau mot de passe pour ce médecin.</p>
    <form method="POST" style="margin-top:16px;">
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

<!-- ████████████████ SCRIPTS ████████████████ -->
<script>
// ──────────────────────────────────────────
// HORLOGE
// ──────────────────────────────────────────
(function tick() {
    var n = new Date(), el = document.getElementById('clock');
    if (el) el.textContent = [n.getHours(), n.getMinutes(), n.getSeconds()]
        .map(function(x) { return (''+x).padStart(2,'0'); }).join(':');
    setTimeout(tick, 1000);
})();

// ──────────────────────────────────────────
// DARK MODE — logique propre et isolée
// ──────────────────────────────────────────
(function() {
    var html = document.documentElement;
    var btn  = document.getElementById('darkModeBtn');
    
    function isDark() {
        return html.getAttribute('data-theme') === 'dark';
    }

    function updateBtn() {
        if (btn) btn.textContent = isDark() ? '☀️' : '🌙';
    }

    // Mettre à jour l'icône au chargement
    updateBtn();

    // Gestion du clic
    if (btn) {
        btn.addEventListener('click', function() {
            var dark = !isDark();
            html.setAttribute('data-theme', dark ? 'dark' : 'light');
            localStorage.setItem('psyadmin_dark', dark ? '1' : '0');
            updateBtn();
        });
    }
})();

// ──────────────────────────────────────────
// MODALS
// ──────────────────────────────────────────
(function() {
    var ovDelete  = document.getElementById('ov-delete');
    var ovResetpw = document.getElementById('ov-resetpw');

    function closeAll() {
        ovDelete.classList.remove('show');
        ovResetpw.classList.remove('show');
    }

    document.getElementById('md-cancel').addEventListener('click', closeAll);
    document.getElementById('rp-cancel').addEventListener('click', closeAll);

    ovDelete.addEventListener('click',  function(e) { if (e.target === this) closeAll(); });
    ovResetpw.addEventListener('click', function(e) { if (e.target === this) closeAll(); });

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

            document.getElementById('md-title').textContent  = title;
            document.getElementById('md-msg').textContent    = msg;
            document.getElementById('md-action').value       = action;
            document.getElementById('md-rid').value          = rid;
            document.getElementById('md-docid').value        = docid;
            document.getElementById('md-ns').value           = newstatus;

            var confirmBtn = document.getElementById('md-confirm');
            var icon       = document.getElementById('md-icon');

            if (action === 'toggle_doctor' && newstatus === 'active') {
                confirmBtn.className = 'btn btn-ok';
                confirmBtn.textContent = 'Activer';
                icon.textContent = '✓';
                icon.style.background = 'var(--ok-l)';
            } else if (action === 'toggle_doctor' && newstatus === 'suspended') {
                confirmBtn.className = 'btn btn-wa';
                confirmBtn.textContent = 'Suspendre';
                icon.textContent = '⏸';
                icon.style.background = 'var(--wa-l)';
            } else {
                confirmBtn.className = 'btn btn-er';
                confirmBtn.textContent = 'Supprimer';
                icon.textContent = '🗑️';
                icon.style.background = 'var(--er-l)';
            }

            ovDelete.classList.add('show');
        }

        if (modal === 'resetpw') {
            var docid   = btn.getAttribute('data-docid')  || '';
            var docname = btn.getAttribute('data-docname') || '';
            document.getElementById('rp-docid').value         = docid;
            document.getElementById('rp-msg').textContent     = 'Nouveau mot de passe pour Dr. ' + docname;
            document.getElementById('rp-password').value      = '';
            ovResetpw.classList.add('show');
        }
    });
})();
</script>
</body>
</html>