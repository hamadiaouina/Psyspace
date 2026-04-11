<?php
// --- SÉCURITÉ ---
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');
if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

if (isset($_SESSION['user_ip'], $_SESSION['user_agent'])) {
    if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR'] ||
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy(); header("Location: login.php?error=hijack"); exit();
    }
}

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none';");

include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

$patient_raw = $_GET['patient_name'] ?? '';
if (!preg_match('/^[\p{L}\s\'\-\.]{1,100}$/u', $patient_raw)) { header("Location: dashboard.php"); exit(); }
$patient_selected = trim($patient_raw);
$doctor_id        = (int)$_SESSION['id'];
$nom_docteur      = $_SESSION['nom'] ?? 'Docteur';
$appointment_id   = (int)($_GET['id'] ?? 0);
if (!$appointment_id) { header("Location: dashboard.php"); exit(); }

$stmt = $conn->prepare("SELECT patient_id FROM appointments WHERE id=? AND doctor_id=? LIMIT 1");
$stmt->bind_param("ii", $appointment_id, $doctor_id);
$stmt->execute();
$r = $stmt->get_result();
if ($r->num_rows === 0) { header("Location: dashboard.php?error=unauthorized"); exit(); }
$patient_id = (int)$r->fetch_assoc()['patient_id'];
$stmt->close();
if (!$patient_id) { header("Location: dashboard.php"); exit(); }

// Données du médecin
$stmt = $conn->prepare("SELECT * FROM doctor WHERE docid=? LIMIT 1");
$stmt->bind_param("i", $doctor_id); $stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();
$doc_prenom = $doc['docprenom'] ?? '';
$doc_nom_db = $doc['docname'] ?? $nom_docteur;
$doc_specialty = $doc['specialty'] ?? 'Psychologue clinicien';
$doc_adresse = $doc['adresse'] ?? '';
$doc_tel = $doc['tel'] ?? '';
$doc_rpps = $doc['rpps'] ?? '';
$doc_fullname = trim($doc_prenom . ' ' . $doc_nom_db);

$prev_consults = [];
$stmt2 = $conn->prepare("SELECT date_consultation, resume_ia, duree_minutes FROM consultations WHERE patient_id=? AND doctor_id=? ORDER BY date_consultation DESC LIMIT 6");
$stmt2->bind_param("ii", $patient_id, $doctor_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row2 = $res2->fetch_assoc()) $prev_consults[] = $row2;
$stmt2->close();

$session_num = count($prev_consults) + 1;
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
$_SESSION['pending_appointment_id'] = $appointment_id;
$_SESSION['pending_patient_name']   = $patient_selected;
$_SESSION['pending_doctor_id']      = $doctor_id;

$appt_date = date('d/m/Y');
$stmt3 = $conn->prepare("SELECT app_date FROM appointments WHERE id=? LIMIT 1");
$stmt3->bind_param("i", $appointment_id);
$stmt3->execute();
$r3 = $stmt3->get_result()->fetch_assoc();
$stmt3->close();
if ($r3) $appt_date = date('d/m/Y', strtotime($r3['app_date']));

$history_for_ai = [];
foreach (array_slice($prev_consults, 0, 3) as $pc) {
    $history_for_ai[] = [
        'date'  => date('d/m/Y', strtotime($pc['date_consultation'])),
        'duree' => $pc['duree_minutes'],
        'resume'=> mb_substr(strip_tags($pc['resume_ia'] ?? ''), 0, 400, 'UTF-8')
    ];
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
<link rel="icon" type="image/png" href="assets/images/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Séance · <?= htmlspecialchars($patient_selected) ?> | PsySpace</title>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0;}

/* ── DESIGN TOKENS ──────────────────────────────────── */
:root {
  /* Light */
  --bg:       #f4f2ee;
  --bg2:      #ffffff;
  --bg3:      #ede9e2;
  --border:   #d8d3c9;
  --border2:  #e8e4dc;
  --tx:       #1a1714;
  --tx2:      #4a4540;
  --tx3:      #8a837a;
  --accent:   #2d5be3;
  --accent2:  #1a3fa8;
  --accent-bg:#eef2fd;
  --ok:       #1a7a4e;
  --ok-bg:    #edfaf3;
  --wa:       #9a6500;
  --wa-bg:    #fef9ec;
  --er:       #c0392b;
  --er-bg:    #fef2f2;
  --sidebar:  #1a1714;
  --sidebar-tx:#f4f2ee;
  --shadow:   0 2px 12px rgba(0,0,0,.07);
  --shadow2:  0 4px 24px rgba(0,0,0,.10);
  --radius:   12px;
  --radius2:  8px;
}
[data-theme="dark"] {
  --bg:       #111318;
  --bg2:      #1a1e26;
  --bg3:      #232830;
  --border:   #2a3040;
  --border2:  #232830;
  --tx:       #edeae4;
  --tx2:      #a8a4a0;
  --tx3:      #4a4f60;
  --accent:   #6b8cff;
  --accent2:  #4a6dff;
  --accent-bg:rgba(107,140,255,.08);
  --ok:       #34d399;
  --ok-bg:    rgba(52,211,153,.08);
  --wa:       #fbbf24;
  --wa-bg:    rgba(251,191,36,.07);
  --er:       #f87171;
  --er-bg:    rgba(248,113,113,.08);
  --sidebar:  #0d1018;
  --sidebar-tx:#e8e4dc;
  --shadow:   0 2px 12px rgba(0,0,0,.3);
  --shadow2:  0 4px 24px rgba(0,0,0,.4);
}

html,body{height:100%;font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);overflow:hidden;transition:background .25s,color .25s;}

/* ── LAYOUT ─────────────────────────────────────────── */
.app{display:grid;grid-template-rows:56px 1fr;height:100vh;}
.main{display:grid;grid-template-columns:300px 1fr 340px;height:calc(100vh - 56px);overflow:hidden;}
.col{overflow-y:auto;padding:20px 16px;display:flex;flex-direction:column;gap:14px;}
.col::-webkit-scrollbar{width:3px;}
.col::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px;}

/* ── TOPBAR ──────────────────────────────────────────── */
.topbar{
  display:flex;align-items:center;justify-content:space-between;
  padding:0 24px;
  background:var(--bg2);
  border-bottom:1px solid var(--border);
  box-shadow:var(--shadow);
}
.topbar-left{display:flex;align-items:center;gap:14px;}
.back-btn{
  display:flex;align-items:center;gap:6px;color:var(--tx3);font-size:13px;font-weight:500;
  text-decoration:none;padding:6px 12px;border-radius:var(--radius2);border:1px solid var(--border);
  background:var(--bg);transition:all .18s;
}
.back-btn:hover{color:var(--tx);border-color:var(--accent);}
.patient-badge{
  display:flex;align-items:center;gap:10px;
}
.patient-avatar{
  width:34px;height:34px;border-radius:10px;background:var(--accent-bg);
  border:1.5px solid var(--accent);display:flex;align-items:center;justify-content:center;
  font-size:13px;font-weight:700;color:var(--accent);
}
.patient-name{font-size:14px;font-weight:700;color:var(--tx);}
.patient-meta{font-size:11px;color:var(--tx3);margin-top:1px;}

.topbar-right{display:flex;align-items:center;gap:10px;}

/* ── THEME TOGGLE ─────────────────────────────────── */
.theme-btn{
  width:38px;height:38px;border-radius:10px;border:1px solid var(--border);
  background:var(--bg);color:var(--tx3);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:all .18s;font-size:16px;
}
.theme-btn:hover{color:var(--tx);border-color:var(--accent);}

/* ── TIMER ─────────────────────────────────────────── */
.timer-wrap{
  display:flex;align-items:center;gap:8px;padding:6px 14px;
  border-radius:var(--radius2);border:1px solid var(--border);background:var(--bg);
}
.timer-dot{width:7px;height:7px;border-radius:50%;background:var(--tx3);transition:background .3s;}
.timer-dot.live{background:#dc2626;animation:pulse 1.3s ease-in-out infinite;}
.timer-txt{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:500;color:var(--tx);letter-spacing:.05em;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

/* ── CARDS ───────────────────────────────────────── */
.card{
  background:var(--bg2);border:1px solid var(--border);
  border-radius:var(--radius);padding:16px;
  box-shadow:var(--shadow);
}
.card-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--tx3);margin-bottom:12px;display:flex;align-items:center;gap:7px;}
.card-title-dot{width:5px;height:5px;border-radius:50%;background:var(--accent);}

/* ── TEXTAREA ─────────────────────────────────────── */
.ta{
  width:100%;background:var(--bg);border:1.5px solid var(--border);color:var(--tx);
  border-radius:var(--radius2);resize:none;outline:none;padding:13px;
  font-family:'DM Sans',sans-serif;font-size:14px;line-height:1.75;
  transition:border-color .2s,box-shadow .2s;
}
.ta:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-bg);}
.ta::placeholder{color:var(--tx3);font-style:italic;}

.ta-notes{
  width:100%;background:var(--bg3);border:1.5px solid var(--border);color:var(--tx);
  border-radius:var(--radius2);resize:none;outline:none;padding:12px;
  font-family:'DM Sans',sans-serif;font-size:13px;line-height:1.7;
  transition:border-color .2s;
}
.ta-notes:focus{border-color:var(--wa);box-shadow:0 0 0 3px var(--wa-bg);}
.ta-notes::placeholder{color:var(--tx3);font-style:italic;}

/* ── BUTTONS ─────────────────────────────────────── */
.btn{
  font-size:12px;font-weight:600;border-radius:var(--radius2);padding:9px 18px;
  border:none;cursor:pointer;transition:all .18s;
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  font-family:'DM Sans',sans-serif;letter-spacing:.01em;
}
.btn-primary{background:var(--accent);color:#fff;box-shadow:0 2px 8px rgba(45,91,227,.25);}
.btn-primary:hover{background:var(--accent2);box-shadow:0 4px 16px rgba(45,91,227,.35);}
.btn-primary:disabled{background:var(--border);color:var(--tx3);box-shadow:none;cursor:not-allowed;}
.btn-ok{background:var(--ok-bg);color:var(--ok);border:1.5px solid rgba(26,122,78,.2);}
.btn-ok:hover{background:var(--ok);color:#fff;}
.btn-ghost{background:var(--bg);color:var(--tx2);border:1.5px solid var(--border);}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent);}
.btn-danger{background:var(--er-bg);color:var(--er);border:1.5px solid rgba(192,57,43,.2);}
.btn-archive{
  background:var(--ok);color:#fff;font-size:13px;font-weight:700;
  padding:12px 24px;border-radius:var(--radius);border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:all .2s;box-shadow:0 3px 12px rgba(26,122,78,.3);width:100%;
  font-family:'DM Sans',sans-serif;
}
.btn-archive:hover{background:#147a3e;box-shadow:0 5px 20px rgba(26,122,78,.45);transform:translateY(-1px);}

/* ── MIC BUTTON ───────────────────────────────────── */
.mic-btn{
  width:48px;height:48px;border-radius:50%;border:2px solid var(--border);
  background:var(--bg);color:var(--tx3);cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:all .22s;flex-shrink:0;
}
.mic-btn.idle{border-color:var(--border);}
.mic-btn.live{background:#dc2626;border-color:#dc2626;color:#fff;animation:mic-ring 1.4s ease-out infinite;}
.mic-btn.done{background:var(--ok-bg);border-color:var(--ok);color:var(--ok);}
@keyframes mic-ring{0%{box-shadow:0 0 0 0 rgba(220,38,38,.4)}70%{box-shadow:0 0 0 14px rgba(220,38,38,0)}100%{box-shadow:0 0 0 0 rgba(220,38,38,0)}}

/* ── STATUS CHIPS ─────────────────────────────────── */
.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;border:1px solid;}
.chip-idle{background:var(--bg3);color:var(--tx3);border-color:var(--border);}
.chip-live{background:var(--er-bg);color:var(--er);border-color:rgba(192,57,43,.3);}
.chip-ok{background:var(--ok-bg);color:var(--ok);border-color:rgba(26,122,78,.3);}
.chip-info{background:var(--accent-bg);color:var(--accent);border-color:rgba(45,91,227,.25);}

/* ── TOAST ───────────────────────────────────────── */
.toast{
  padding:9px 14px;border-radius:var(--radius2);font-size:12px;font-weight:600;
  display:flex;align-items:center;gap:7px;border:1px solid;
}
.toast-ok{background:var(--ok-bg);color:var(--ok);border-color:rgba(26,122,78,.3);}
.toast-er{background:var(--er-bg);color:var(--er);border-color:rgba(192,57,43,.3);}
.toast-wa{background:var(--wa-bg);color:var(--wa);border-color:rgba(154,101,0,.3);}
.toast-in{background:var(--accent-bg);color:var(--accent);border-color:rgba(45,91,227,.25);}

/* ── LIVE INSIGHTS ─────────────────────────────────── */
.insight-item{
  padding:10px 12px;border-radius:var(--radius2);margin-bottom:6px;
  border-left:3px solid;animation:slideIn .25s ease forwards;
}
.ins-info{background:var(--accent-bg);border-left-color:var(--accent);}
.ins-warn{background:var(--wa-bg);border-left-color:var(--wa);}
.ins-ok{background:var(--ok-bg);border-left-color:var(--ok);}
.ins-er{background:var(--er-bg);border-left-color:var(--er);}
.ins-title{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;margin-bottom:3px;}
.ins-body{font-size:11.5px;line-height:1.55;color:var(--tx2);}
@keyframes slideIn{from{opacity:0;transform:translateX(8px)}to{opacity:1;transform:none}}

/* ── WORD COUNT BAR ───────────────────────────────── */
.wc-bar{height:2px;background:var(--border);border-radius:2px;overflow:hidden;margin-top:6px;}
.wc-fill{height:2px;background:var(--accent);border-radius:2px;transition:width .3s;}

/* ── OVERLAY ─────────────────────────────────────── */
.overlay{
  position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(8px);
  z-index:999;display:flex;align-items:center;justify-content:center;
  opacity:0;pointer-events:none;transition:opacity .22s;
}
.overlay.open{opacity:1;pointer-events:all;}
.overlay-box{
  background:var(--bg2);border:1px solid var(--border);border-radius:16px;
  padding:28px;max-width:400px;width:calc(100% - 32px);
  box-shadow:var(--shadow2);transform:scale(.95);transition:transform .22s;
}
.overlay.open .overlay-box{transform:scale(1);}
.overlay-title{font-size:15px;font-weight:700;color:var(--tx);margin-bottom:8px;}
.overlay-msg{font-size:13px;color:var(--tx2);line-height:1.65;margin-bottom:24px;}

/* ── REPORT STYLES ────────────────────────────────── */
.rpt-wrap{
  background:#fff;color:#1a1714;
  border:1px solid #d8d3c9;border-radius:var(--radius);
  overflow:hidden;font-family:'DM Sans',sans-serif;
}
[data-theme="dark"] .rpt-wrap{background:#1e2230;color:#edeae4;border-color:#2a3040;}

.rpt-top-band{
  background:#1a3fa8;color:#fff;
  padding:24px 28px 20px;
}
.rpt-top-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.22em;color:rgba(255,255,255,.5);margin-bottom:6px;}
.rpt-patient-name{font-family:'Instrument Serif',serif;font-size:26px;color:#fff;margin-bottom:8px;}
.rpt-meta-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px;}
.rpt-meta-cell{background:rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;}
.rpt-meta-cell-label{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:rgba(255,255,255,.5);margin-bottom:3px;}
.rpt-meta-cell-val{font-size:12px;font-weight:600;color:#fff;}

.rpt-doc-strip{
  background:rgba(255,255,255,.07);border-top:1px solid rgba(255,255,255,.1);
  padding:12px 28px;display:flex;justify-content:space-between;align-items:center;
}
.rpt-doc-name{font-size:13px;font-weight:700;color:#fff;}
.rpt-doc-sub{font-size:10px;color:rgba(255,255,255,.5);margin-top:1px;}

.rpt-body{padding:24px 28px;}
.rpt-section{margin-bottom:22px;}
.rpt-section-label{
  font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.18em;
  color:#2d5be3;padding-bottom:8px;margin-bottom:12px;
  border-bottom:1.5px solid #e0e8ff;
  display:flex;align-items:center;gap:8px;
}
[data-theme="dark"] .rpt-section-label{color:#6b8cff;border-bottom-color:#2a3040;}
.rpt-section-label::before{content:'';width:3px;height:12px;background:#2d5be3;border-radius:2px;flex-shrink:0;}
[data-theme="dark"] .rpt-section-label::before{background:#6b8cff;}

.rpt-prose{font-size:13.5px;line-height:1.85;color:#2a2520;}
[data-theme="dark"] .rpt-prose{color:#c8c4be;}
.rpt-prose strong{color:#1a1714;font-weight:600;}
[data-theme="dark"] .rpt-prose strong{color:#edeae4;}

.rpt-summary-box{
  background:#f0f5ff;border:1px solid #c8d8f8;border-left:4px solid #2d5be3;
  border-radius:0 var(--radius2) var(--radius2) 0;padding:16px 18px;margin-bottom:20px;
}
[data-theme="dark"] .rpt-summary-box{background:rgba(107,140,255,.06);border-color:rgba(107,140,255,.2);border-left-color:#6b8cff;}
.rpt-summary-label{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.18em;color:#2d5be3;margin-bottom:8px;}
[data-theme="dark"] .rpt-summary-label{color:#6b8cff;}
.rpt-summary-text{font-family:'Instrument Serif',serif;font-size:14.5px;line-height:1.85;color:#1a3fa8;font-style:italic;}
[data-theme="dark"] .rpt-summary-text{color:#a8bfff;}

.rpt-diag-row{display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid #e8e4dc;}
[data-theme="dark"] .rpt-diag-row{border-bottom-color:#2a3040;}
.rpt-diag-row:last-child{border-bottom:none;}
.rpt-diag-code{font-size:9px;font-weight:800;background:#2d5be3;color:#fff;padding:3px 9px;border-radius:5px;flex-shrink:0;margin-top:2px;letter-spacing:.04em;}
.rpt-diag-text{font-size:13px;color:#2a2520;line-height:1.6;}
[data-theme="dark"] .rpt-diag-text{color:#c8c4be;}

.rpt-obj-row{display:flex;align-items:flex-start;gap:10px;padding:6px 0;}
.rpt-obj-num{font-size:11px;font-weight:800;color:#2d5be3;width:22px;flex-shrink:0;text-align:center;margin-top:2px;}
.rpt-obj-text{font-size:13px;line-height:1.65;color:#2a2520;}
[data-theme="dark"] .rpt-obj-text{color:#c8c4be;}

.rpt-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.rpt-info-block{background:#f8f7f4;border:1px solid #e8e4dc;border-radius:var(--radius2);padding:12px 14px;}
[data-theme="dark"] .rpt-info-block{background:#1a1e26;border-color:#2a3040;}
.rpt-info-block-label{font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.16em;color:#8a837a;margin-bottom:5px;}
[data-theme="dark"] .rpt-info-block-label{color:#4a4f60;}
.rpt-info-block-text{font-size:12.5px;line-height:1.65;color:#1a1714;}
[data-theme="dark"] .rpt-info-block-text{color:#c8c4be;}

.rpt-sig-block{
  border-top:1px solid #e8e4dc;padding:18px 28px;display:flex;justify-content:space-between;align-items:center;
  background:#f8f7f4;
}
[data-theme="dark"] .rpt-sig-block{background:#1a1e26;border-top-color:#2a3040;}
.rpt-sig-text{font-size:11px;color:#8a837a;}
.rpt-sig-name{font-size:13px;font-weight:700;color:#1a1714;margin-top:2px;}
[data-theme="dark"] .rpt-sig-name{color:#edeae4;}

/* ── HISTORY LIST ─────────────────────────────────── */
.hist-item{
  display:flex;align-items:center;gap:10px;padding:9px 11px;
  border-radius:var(--radius2);border:1px solid var(--border2);
  background:var(--bg3);cursor:pointer;transition:border-color .18s;
}
.hist-item:hover{border-color:var(--accent);}
.hist-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;}

/* ── DOTS LOADER ──────────────────────────────────── */
.dots span{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--accent);margin:0 2px;animation:db 1.2s ease-in-out infinite;}
.dots span:nth-child(2){animation-delay:.2s}.dots span:nth-child(3){animation-delay:.4s}
@keyframes db{0%,80%,100%{transform:scale(.35);opacity:.25}40%{transform:scale(1);opacity:1}}

/* ── DIVIDER ────────────────────────────────────── */
.divider{height:1px;background:var(--border);margin:4px 0;}

/* ── WAVEFORM ────────────────────────────────────── */
.wv-bar{width:3px;height:3px;border-radius:2px;background:var(--border);transition:height .1s,background .15s;}
</style>
</head>
<body>
<div class="app">

<!-- ══════════════════ TOPBAR ═══════════════════════ -->
<div class="topbar">
  <div class="topbar-left">
    <a href="dashboard.php" class="back-btn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Tableau de bord
    </a>
    <div style="width:1px;height:20px;background:var(--border);"></div>
    <div class="patient-badge">
      <div class="patient-avatar"><?= strtoupper(mb_substr($patient_selected,0,1,'UTF-8')) ?></div>
      <div>
        <div class="patient-name"><?= htmlspecialchars($patient_selected) ?></div>
        <div class="patient-meta">Séance n°<?= $session_num ?> · <?= $appt_date ?><?php if($session_num>1): ?> · <?= $session_num-1 ?> séance<?= ($session_num-1)>1?'s':'' ?> antérieure<?= ($session_num-1)>1?'s':'' ?><?php endif; ?></div>
      </div>
    </div>
  </div>

  <div class="topbar-right">
    <div class="timer-wrap">
      <span class="timer-dot" id="timer-dot"></span>
      <span class="timer-txt" id="timer">00:00</span>
    </div>
    <div id="rec-status" class="chip chip-idle">En attente</div>
    <button class="theme-btn" id="theme-btn" title="Changer de thème">
      <span id="theme-icon">🌙</span>
    </button>
  </div>
</div>

<!-- ══════════════════ MAIN GRID ══════════════════════ -->
<div class="main">

<!-- ═══ COL GAUCHE ═══════════════════════════════════ -->
<div class="col" style="border-right:1px solid var(--border);">

  <!-- IA INSIGHTS LIVE -->
  <div class="card" style="flex:1;min-height:0;">
    <div class="card-title">
      <span class="card-title-dot" style="background:var(--accent);"></span>
      Insights IA · Live
      <div id="ai-loader" style="display:none;margin-left:auto;"><div class="dots"><span></span><span></span><span></span></div></div>
      <button id="btn-ask" onclick="toggleAsk()" class="btn btn-ghost" style="margin-left:auto;padding:5px 10px;font-size:10px;" disabled>✦ Question</button>
    </div>
    <div id="feed" style="max-height:220px;overflow-y:auto;">
      <div id="feed-ph" style="padding:28px 0;text-align:center;color:var(--tx3);">
        <p style="font-size:11px;line-height:2;">Démarrez la capture vocale<br>L'IA analysera en continu</p>
      </div>
    </div>
  </div>

  <!-- QUESTION LIBRE -->
  <div id="ask-box" style="display:none;">
    <div class="card" style="padding:12px;">
      <textarea id="ask-in" rows="2" class="ta" style="font-size:12px;padding:9px;margin-bottom:8px;" placeholder="Ex : Signes de dissociation ? Approche ACT ?"></textarea>
      <div style="display:flex;gap:6px;">
        <button onclick="sendQ()" class="btn btn-primary" style="flex:1;font-size:11px;">Demander</button>
        <button onclick="toggleAsk()" class="btn btn-ghost" style="padding:7px 11px;font-size:11px;">✕</button>
      </div>
      <div id="ask-resp" style="margin-top:8px;"></div>
    </div>
  </div>

  <!-- NOTES CLINICIEN -->
  <div class="card">
    <div class="card-title">
      <span class="card-title-dot" style="background:var(--wa);"></span>
      Notes du praticien
      <span class="chip chip-idle" style="font-size:9px;margin-left:auto;">Privé</span>
    </div>
    <textarea id="notes" class="ta-notes" rows="5" placeholder="Observations cliniques, impressions, hypothèses propres au praticien…"></textarea>
  </div>

  <!-- HISTORIQUE -->
  <?php if(!empty($prev_consults)): ?>
  <div class="card">
    <div class="card-title">
      <span class="card-title-dot" style="background:var(--ok);"></span>
      Séances précédentes
      <span class="chip chip-ok" style="font-size:9px;margin-left:auto;"><?= count($prev_consults) ?></span>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;">
      <?php foreach($prev_consults as $i=>$pc): ?>
      <div class="hist-item" onclick="loadPrev(<?= json_encode(strip_tags($pc['resume_ia']??'')) ?>)">
        <div class="hist-dot" style="<?= $i===0?'background:var(--accent)':'background:var(--border);border:2px solid var(--border)' ?>"></div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <p style="font-size:11px;font-weight:700;color:var(--tx);"><?= date('d MMM Y', strtotime($pc['date_consultation'])) ?></p>
            <span style="font-size:10px;color:var(--tx3);"><?= $pc['duree_minutes'] ?>min</span>
          </div>
          <p style="font-size:10px;color:var(--tx3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;">
            <?= htmlspecialchars(mb_substr(strip_tags($pc['resume_ia']??''),0,55,'UTF-8')) ?>…
          </p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- ═══ COL CENTRE ════════════════════════════════════ -->
<div class="col" style="padding:20px 20px;">

  <!-- TRANSCRIPTION -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <div>
        <h2 style="font-size:15px;font-weight:700;color:var(--tx);">Transcription de séance</h2>
        <p style="font-size:11px;color:var(--tx3);margin-top:2px;">Capture vocale ou saisie manuelle</p>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <div id="waveform" style="display:flex;align-items:center;gap:2px;height:22px;">
          <?php for($i=0;$i<14;$i++): ?><div class="wv-bar"></div><?php endfor; ?>
        </div>
        <span id="word-count" class="chip chip-idle" style="font-size:10px;">0 mot</span>
      </div>
    </div>

    <textarea id="transcript" class="ta" rows="11"
      placeholder="La transcription apparaît ici automatiquement lors de la capture vocale, ou saisissez directement le contenu de la séance…"
      oninput="onTyping(this.value)"></textarea>
    
    <div class="wc-bar"><div class="wc-fill" id="wc-fill" style="width:0%"></div></div>
    <div id="tr-notif" style="margin-top:8px;min-height:28px;"></div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:12px;">
      <button id="mic-btn" onclick="toggleMic()" class="mic-btn idle" title="Démarrer la capture vocale">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
          <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
          <line x1="12" y1="19" x2="12" y2="23"/>
          <line x1="8" y1="23" x2="16" y2="23"/>
        </svg>
      </button>
      <div style="flex:1;">
        <p id="mic-label" style="font-size:11px;font-weight:600;color:var(--tx3);margin-bottom:4px;">Microphone inactif — Cliquez pour démarrer</p>
        <div style="height:3px;background:var(--border);border-radius:2px;overflow:hidden;">
          <div id="audio-bar" style="width:0%;height:3px;background:var(--accent);border-radius:2px;transition:width .1s;"></div>
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:6px;cursor:pointer;flex-shrink:0;">
        <input type="checkbox" id="auto-ai" checked style="accent-color:var(--accent);width:13px;height:13px;">
        <span style="font-size:11px;font-weight:600;color:var(--tx3);">Auto-IA</span>
      </label>
      <button onclick="clearTranscript()" class="btn btn-danger" style="padding:7px 12px;font-size:11px;flex-shrink:0;">✕ Effacer</button>
    </div>

    <div id="stt-warning" style="display:none;margin-top:10px;padding:10px 14px;border-radius:var(--radius2);background:var(--wa-bg);border:1px solid rgba(154,101,0,.25);">
      <p style="font-size:11px;color:var(--wa);font-weight:600;">⚠ Reconnaissance vocale non disponible — Utilisez Chrome ou Edge et autorisez le microphone.</p>
    </div>
  </div>

  <!-- COMPTE-RENDU -->
  <div class="card" style="flex:1;padding:0;overflow:hidden;display:flex;flex-direction:column;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 18px;border-bottom:1px solid var(--border);">
      <div>
        <h2 style="font-size:15px;font-weight:700;color:var(--tx);">Compte-rendu clinique</h2>
        <p style="font-size:11px;color:var(--tx3);margin-top:2px;">Généré par IA · Confidentiel</p>
      </div>
      <button id="btn-generate" onclick="genReport()" disabled class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Générer le bilan
      </button>
    </div>

    <div id="report-body" style="flex:1;overflow-y:auto;max-height:calc(100vh - 320px);">
      <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:60px 20px;color:var(--tx3);text-align:center;gap:10px;min-height:200px;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        <p style="font-size:12px;font-weight:500;">En attente de transcription</p>
        <p style="font-size:11px;opacity:.6;">Commencez à transcrire, puis cliquez sur « Générer le bilan »</p>
      </div>
    </div>

    <!-- BOUTON ARCHIVER - TRÈS VISIBLE -->
    <div style="padding:16px 18px;border-top:1px solid var(--border);background:var(--bg);">
      <div id="arch-notif" style="margin-bottom:10px;min-height:0;"></div>
      <div style="display:flex;gap:10px;">
        <button onclick="exportPDF()" id="btn-pdf" disabled class="btn btn-ghost" style="flex:1;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          PDF
        </button>
        <button onclick="finalize()" class="btn-archive">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          Archiver la séance
        </button>
      </div>
    </div>
  </div>

</div>

<!-- ═══ COL DROITE ════════════════════════════════════ -->
<div class="col" style="border-left:1px solid var(--border);">

  <!-- RADAR -->
  <div class="card">
    <div class="card-title">
      <span class="card-title-dot"></span>
      Profil émotionnel
      <span id="radar-chip" class="chip chip-idle" style="font-size:9px;margin-left:auto;">En attente</span>
    </div>
    <div style="height:180px;position:relative;"><canvas id="radarChart"></canvas></div>
    <div style="display:flex;flex-wrap:wrap;gap:5px 12px;margin-top:8px;">
      <?php foreach([['Détresse','#f59e0b'],['Anxiété','#8b5cf6'],['Résilience','#10b981'],['Social','#38bdf8'],['Lien','#6366f1']] as [$l,$c]): ?>
      <div style="display:flex;align-items:center;gap:4px;">
        <div style="width:6px;height:6px;border-radius:50%;background:<?=$c?>;"></div>
        <span style="font-size:10px;font-weight:600;color:var(--tx3);text-transform:uppercase;letter-spacing:.06em;"><?=$l?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- TIMELINE -->
  <div class="card">
    <div class="card-title">
      <span class="card-title-dot" style="background:#10b981;"></span>
      Évolution de séance
      <span id="tl-chip" class="chip chip-idle" style="font-size:9px;margin-left:auto;">Stable</span>
    </div>
    <div style="height:80px;position:relative;"><canvas id="tlChart"></canvas></div>
    <p id="tl-label" style="font-size:11px;font-weight:600;color:var(--tx3);margin-top:6px;">—</p>
  </div>

  <!-- ANALYSE SÉMANTIQUE -->
  <div class="card" style="flex:1;min-height:0;">
    <div class="card-title">
      <span class="card-title-dot" style="background:#8b5cf6;"></span>
      Analyse sémantique
    </div>
    <div id="semantic" style="max-height:220px;overflow-y:auto;">
      <div id="sem-ph" style="padding:18px 0;text-align:center;color:var(--tx3);">
        <p style="font-size:11px;line-height:2;">Insights générés<br>au fil de la transcription</p>
      </div>
    </div>
  </div>

  <!-- DYNAMIQUE EMO -->
  <div class="card">
    <div class="card-title">
      <span class="card-title-dot" style="background:#f59e0b;"></span>
      Polarité émotionnelle
    </div>
    <div style="height:55px;position:relative;"><canvas id="emoChart"></canvas></div>
    <div style="display:flex;justify-content:space-between;margin-top:4px;">
      <span style="font-size:10px;color:var(--tx3);">← Négatif</span>
      <span style="font-size:10px;color:var(--tx3);">Positif →</span>
    </div>
  </div>

</div>

</div><!-- /main -->
</div><!-- /app -->

<!-- OVERLAY -->
<div id="overlay" class="overlay" onclick="if(event.target===this)closeOverlay()">
  <div class="overlay-box">
    <div class="overlay-title" id="ov-title"></div>
    <div class="overlay-msg" id="ov-msg"></div>
    <div style="display:flex;gap:10px;">
      <button id="ov-yes" class="btn btn-ok" style="flex:1;">Confirmer</button>
      <button onclick="closeOverlay()" class="btn btn-ghost" style="flex:1;">Annuler</button>
    </div>
  </div>
</div>

<script>
/* ═══════════════════════════════════════════════
   VARIABLES GLOBALES
═══════════════════════════════════════════════ */
var CSRF   = <?= json_encode($csrf) ?>;
var PAT    = <?= json_encode($patient_selected) ?>;
var SESN   = <?= $session_num ?>;
var DATED  = <?= json_encode($appt_date) ?>;
var DR     = <?= json_encode($doc_fullname ?: $nom_docteur) ?>;
var DR_SPEC= <?= json_encode($doc_specialty) ?>;
var DR_ADR = <?= json_encode($doc_adresse) ?>;
var DR_TEL = <?= json_encode($doc_tel) ?>;
var DR_RPPS= <?= json_encode($doc_rpps) ?>;
var HIST   = <?= json_encode($history_for_ai) ?>;

var micOn = false, recog = null, timerIv = null, secs = 0;
var lastReport = null, lastAutoText = '';
var emoC, radC, tlC;
var emoPoints = [0], tlRisk = [], tlResil = [], tlDetresse = [], tlAnx = [];
var radarData = {d:0, a:0, r:0, s:0, lien:0};

/* ═══════════════════════════════════════════════
   THEME TOGGLE
═══════════════════════════════════════════════ */
(function(){
  var saved = localStorage.getItem('psyspace-theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
  document.getElementById('theme-icon').textContent = saved === 'dark' ? '☀️' : '🌙';
})();

document.getElementById('theme-btn').addEventListener('click', function(){
  var curr = document.documentElement.getAttribute('data-theme');
  var next = curr === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('psyspace-theme', next);
  document.getElementById('theme-icon').textContent = next === 'dark' ? '☀️' : '🌙';
  // Redraw charts
  setTimeout(function(){ drawRadar(); drawTL(); drawEmo(); }, 50);
});

/* ═══════════════════════════════════════════════
   OVERLAY
═══════════════════════════════════════════════ */
function openOverlay(title, msg, cb) {
  document.getElementById('ov-title').innerHTML = title;
  document.getElementById('ov-msg').innerHTML = msg;
  document.getElementById('overlay').classList.add('open');
  document.getElementById('ov-yes').onclick = function(){ closeOverlay(); cb(); };
}
function closeOverlay(){ document.getElementById('overlay').classList.remove('open'); }

/* ═══════════════════════════════════════════════
   NOTIFICATIONS
═══════════════════════════════════════════════ */
function ntf(id, msg, type, ms) {
  if(ms === undefined) ms = 4000;
  var z = document.getElementById(id); if(!z) return;
  var icons = {ok:'✓', er:'✕', wa:'⚠', in:'ℹ'};
  z.innerHTML = '<div class="toast toast-'+type+'"><span>'+(icons[type]||'ℹ')+'</span><span>'+msg+'</span></div>';
  if(ms > 0) setTimeout(function(){ if(z) z.innerHTML=''; }, ms);
}

/* ═══════════════════════════════════════════════
   LEXIQUE CLINIQUE (simplifié, sans scores visibles)
═══════════════════════════════════════════════ */
var LEX = {
  d: ["triste","tristesse","déprimé","déprimée","dépression","désespoir","vide","souffrance","honte","coupable","inutile","seul","abandonné","deuil","rupture","pleurer","larmes","effondré","brisé","épuisé"],
  a: ["anxieux","anxieuse","anxiété","angoisse","stress","peur","panique","inquiet","inquiète","ruminer","insomnie","cauchemars","obsession","phobie","hypervigilance","tremble"],
  r: ["espoir","optimiste","mieux","heureux","heureuse","joie","bonheur","calme","apaisé","soulagé","énergie","motivé","plaisir","confiant","projet","guérir","lâcher prise"],
  s: ["ami","amis","entourage","soutien","famille","couple","relation","confiance","connecté","accompagné","groupe","parler","thérapeute"],
  u: ["suicide","mourir","en finir","me tuer","plus vivre","automutilation","me blesser","me couper","overdose","sauter","pendre"],
  po: ["content","content","serein","bien dans ma peau","fier","réussi","accompli","progression","avancé","mieux gérer","accepte"]
};

function analyzeText(text) {
  var t = text.toLowerCase();
  var scores = {d:0, a:0, r:0, s:0, u:0, po:0};
  Object.keys(LEX).forEach(function(cat){
    LEX[cat].forEach(function(w){
      var re = new RegExp('\\b'+w.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'gi');
      var m = t.match(re);
      if(m) scores[cat] += m.length;
    });
  });
  return scores;
}

/* ═══════════════════════════════════════════════
   ON TYPING
═══════════════════════════════════════════════ */
function onTyping(val) {
  var words = val.trim().split(/\s+/).filter(function(w){ return w.length>0; });
  var wc = words.length;
  document.getElementById('word-count').textContent = wc + (wc>1?' mots':' mot');
  document.getElementById('wc-fill').style.width = Math.min(100, wc/5) + '%';

  var sc = analyzeText(val);
  updateRadar(sc);
  updateTimeline(sc);

  var neg = sc.d + sc.a, pos = sc.r + sc.s + sc.po;
  var total = neg + pos;
  emoPoints.push(total>0 ? Math.max(-1,Math.min(1,(pos-neg)/total)) : 0);
  if(emoPoints.length > 60) emoPoints.shift();
  drawEmo();

  if(wc > 8) document.getElementById('btn-generate').disabled = false;

  if(document.getElementById('auto-ai').checked && wc > 0 && wc % 30 === 0 && val !== lastAutoText){
    lastAutoText = val; runAutoAI(val, sc);
  }
}

/* ═══════════════════════════════════════════════
   CHARTS
═══════════════════════════════════════════════ */
function getColor(opacity) {
  var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  return isDark ? 'rgba(232,228,220,'+opacity+')' : 'rgba(26,23,20,'+opacity+')';
}

function updateRadar(sc) {
  radarData = {
    d: Math.min(100, sc.d * 15),
    a: Math.min(100, sc.a * 15),
    r: Math.min(100, sc.r * 15),
    s: Math.min(100, sc.s * 15),
    lien: Math.min(100, sc.po * 12)
  };
  drawRadar();
  document.getElementById('radar-chip').textContent = 'Actif';
  document.getElementById('radar-chip').className = 'chip chip-ok';
}

function drawRadar() {
  var ctx = document.getElementById('radarChart').getContext('2d');
  if(radC) radC.destroy();
  radC = new Chart(ctx, {
    type: 'radar',
    data: {
      labels: ['Détresse','Anxiété','Résilience','Social','Lien'],
      datasets: [{
        data: [radarData.d, radarData.a, radarData.r, radarData.s, radarData.lien],
        borderColor: 'rgba(45,91,227,.7)',
        backgroundColor: 'rgba(45,91,227,.08)',
        borderWidth: 1.5,
        pointBackgroundColor: ['#f59e0b','#8b5cf6','#10b981','#38bdf8','#6366f1'],
        pointBorderColor: 'transparent',
        pointRadius: 4
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: {duration: 400},
      plugins: {legend:{display:false}, tooltip:{
        backgroundColor: 'rgba(26,23,20,.95)', padding:9, cornerRadius:8,
        titleFont:{family:'DM Sans',size:9,weight:'700'},
        bodyFont:{family:'DM Sans',size:10}
      }},
      scales: {r:{
        min:0, max:100,
        ticks:{display:false},
        grid:{color:getColor(.07)},
        angleLines:{color:getColor(.06)},
        pointLabels:{color:getColor(.5),font:{family:'DM Sans',size:9,weight:'600'}}
      }}
    }
  });
}

function updateTimeline(sc) {
  var stress = Math.min(100, (sc.d + sc.a) * 12);
  var resil  = Math.min(100, (sc.r + sc.s) * 12);
  tlRisk.push(stress); tlResil.push(resil); tlDetresse.push(Math.min(100,sc.d*12)); tlAnx.push(Math.min(100,sc.a*12));
  var M = 80;
  if(tlRisk.length > M){tlRisk.shift();tlResil.shift();tlDetresse.shift();tlAnx.shift();}
  drawTL();
  if(tlRisk.length >= 4) {
    var n = tlRisk.length;
    var diff = ((tlRisk[n-1]+tlRisk[n-2])/2) - ((tlRisk[n-3]+tlRisk[n-4])/2);
    var chip = document.getElementById('tl-chip'), lbl = document.getElementById('tl-label');
    if(diff > 8){ chip.textContent='↑ Tension'; chip.className='chip chip-live'; lbl.textContent='Montée du niveau de détresse'; }
    else if(diff < -8){ chip.textContent='↓ Apaisement'; chip.className='chip chip-ok'; lbl.textContent='Stabilisation progressive'; }
    else{ chip.textContent='→ Stable'; chip.className='chip chip-idle'; lbl.textContent='Régularité du discours'; }
  }
}

function drawTL() {
  var ctx = document.getElementById('tlChart').getContext('2d');
  if(tlC) tlC.destroy();
  function grd(c, c1, c2){ var g = c.chart.ctx.createLinearGradient(0,0,0,80); g.addColorStop(0,c1); g.addColorStop(1,c2); return g; }
  tlC = new Chart(ctx, {
    type:'line',
    data:{
      labels: tlRisk.map(function(_,i){return i;}),
      datasets:[
        {label:'Tension',data:tlRisk,borderColor:'#f59e0b',borderWidth:2,pointRadius:0,fill:true,tension:.42,backgroundColor:function(c){return grd(c,'rgba(245,158,11,.15)','rgba(245,158,11,0)');}},
        {label:'Résilience',data:tlResil,borderColor:'#10b981',borderWidth:1.5,pointRadius:0,fill:false,tension:.42},
        {label:'Anxiété',data:tlAnx,borderColor:'#8b5cf6',borderWidth:1.5,pointRadius:0,fill:false,tension:.42,borderDash:[3,3]}
      ]
    },
    options:{
      responsive:true,maintainAspectRatio:false,animation:false,
      plugins:{legend:{display:false},tooltip:{
        backgroundColor:'rgba(26,23,20,.95)',padding:8,cornerRadius:7,
        titleFont:{family:'DM Sans',size:8,weight:'700'},bodyFont:{family:'DM Sans',size:9}
      }},
      scales:{
        x:{display:false},
        y:{min:0,max:100,ticks:{display:false},grid:{color:getColor(.05)},border:{display:false}}
      }
    }
  });
}

function drawEmo() {
  var ctx = document.getElementById('emoChart').getContext('2d');
  if(emoC) emoC.destroy();
  var last = emoPoints[emoPoints.length-1] || 0;
  var lc = last > 0.1 ? '#10b981' : (last < -0.2 ? '#f59e0b' : '#2d5be3');
  emoC = new Chart(ctx, {
    type:'line',
    data:{
      labels:emoPoints.map(function(_,i){return i;}),
      datasets:[{data:emoPoints,borderColor:lc,borderWidth:1.5,pointRadius:0,fill:true,tension:.4,
        backgroundColor:function(c){var g=c.chart.ctx.createLinearGradient(0,0,0,55);g.addColorStop(0,lc.replace('#','rgba(').replace(/^rgba\(([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})\)/,function(_,r,g,b){return 'rgba('+parseInt(r,16)+','+parseInt(g,16)+','+parseInt(b,16)+','})+'0.15)');g.addColorStop(1,'transparent');return g;}
      }]
    },
    options:{responsive:true,maintainAspectRatio:false,animation:false,plugins:{legend:{display:false}},
      scales:{x:{display:false},y:{min:-1.2,max:1.2,ticks:{display:false},grid:{color:getColor(.04)},border:{display:false}}}
    }
  });
}

/* ═══════════════════════════════════════════════
   API IA
═══════════════════════════════════════════════ */
async function callAI(prompt, max) {
  max = max || 1500;
  var res = await fetch('proxy_ia.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify({prompt:prompt, max_tokens:max, csrf_token:CSRF})
  });
  if(!res.ok) throw new Error('HTTP '+res.status);
  var d = await res.json();
  if(d.error) throw new Error(d.error.message || 'Erreur API');
  return d.choices && d.choices[0] && d.choices[0].message ? d.choices[0].message.content : '';
}

/* ═══════════════════════════════════════════════
   AUTO-IA (live insights)
═══════════════════════════════════════════════ */
async function runAutoAI(text, sc) {
  var loader = document.getElementById('ai-loader');
  if(loader) loader.style.display = 'flex';

  var hist = HIST.length
    ? '\nHistorique des séances :\n' + HIST.map(function(h){ return '- Séance du '+h.date+' ('+h.duree+' min) : '+h.resume; }).join('\n')
    : '\nPremière consultation.';

  var prompt = 'Tu es psychologue clinicien superviseur expérimenté.\n'
    + 'Patient : '+PAT+' | Séance n°'+SESN+' | Date : '+DATED+'\n'
    + hist
    + '\nExtrait du verbatim en cours (derniers échanges) :\n"'+text.slice(-600)+'"\n\n'
    + 'Réponds UNIQUEMENT en JSON strict, sans markdown :\n'
    + '{\n'
    + '  "observation": "1 observation clinique factuelle et précise sur le discours du patient (12-15 mots)",\n'
    + '  "theme": "1 thème clinique central qui émerge (5-8 mots)",\n'
    + '  "question_therapeutique": "1 question ouverte pertinente pour approfondir avec le patient (15-20 mots)",\n'
    + '  "alerte": null\n'
    + '}\n'
    + 'Si tu détectes un risque suicidaire ou une urgence, mets alerte à une phrase courte.';

  try {
    var raw = await callAI(prompt, 350);
    var ai; try{ ai = JSON.parse(raw.replace(/```json\n?|\n?```/g,'').trim()); } catch(e){ return; }

    if(ai.alerte)               addInsight('er', '⚠ Alerte clinique', ai.alerte);
    if(ai.observation)          addInsight('info', 'Observation', ai.observation);
    if(ai.theme)                addInsight('ok', 'Thème central', ai.theme);
    if(ai.question_therapeutique) addInsight('warn', 'Question à explorer', ai.question_therapeutique);

  } catch(e){ console.warn('AutoAI:', e); }
  finally { if(loader) loader.style.display = 'none'; }
}

function addInsight(type, title, body) {
  var ph = document.getElementById('feed-ph'); if(ph) ph.remove();
  var sph = document.getElementById('sem-ph'); if(sph) sph.remove();
  var classes = {info:'ins-info', warn:'ins-warn', ok:'ins-ok', er:'ins-er'};
  var colors = {info:'var(--accent)', warn:'var(--wa)', ok:'var(--ok)', er:'var(--er)'};

  ['feed','semantic'].forEach(function(id){
    var wrap = document.getElementById(id); if(!wrap) return;
    var el = document.createElement('div');
    el.className = 'insight-item ' + (classes[type]||'ins-info');
    var ts = new Date(); var tstr = ts.getHours().toString().padStart(2,'0')+':'+ts.getMinutes().toString().padStart(2,'0');
    el.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">'
      +'<span class="ins-title" style="color:'+colors[type]+';">'+title+'</span>'
      +'<span style="font-size:9px;color:var(--tx3);">'+tstr+'</span>'
    +'</div>'
    +'<p class="ins-body">'+escH(body)+'</p>';
    wrap.insertBefore(el, wrap.firstChild);
    while(wrap.children.length > 10) wrap.removeChild(wrap.lastChild);
  });
  document.getElementById('btn-ask').disabled = false;
}

/* ═══════════════════════════════════════════════
   QUESTION LIBRE
═══════════════════════════════════════════════ */
function toggleAsk() {
  var box = document.getElementById('ask-box');
  box.style.display = box.style.display === 'none' ? 'block' : 'none';
  if(box.style.display === 'block') document.getElementById('ask-in').focus();
}
async function sendQ() {
  var q = document.getElementById('ask-in').value.trim(); if(!q) return;
  var text = document.getElementById('transcript').value.trim();
  var resp = document.getElementById('ask-resp');
  resp.innerHTML = '<div class="dots" style="padding:8px;"><span></span><span></span><span></span></div>';

  var prompt = 'Tu es psychologue clinicien superviseur. Réponds de façon clinique et précise en 4-5 phrases.\n'
    + 'Patient : '+PAT+' | Séance n°'+SESN+'\n'
    + 'Verbatim : "'+text.slice(-500)+'"\n'
    + 'QUESTION : "'+q+'"';
  try {
    var raw = await callAI(prompt, 500);
    resp.innerHTML = '<div class="card" style="margin-top:8px;padding:12px;">'
      +'<p style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--accent);margin-bottom:6px;">Réponse IA</p>'
      +'<p style="font-size:12px;color:var(--tx2);line-height:1.65;">'+escH(raw)+'</p></div>';
  } catch(e){ resp.innerHTML = '<p style="font-size:11px;color:var(--er);margin-top:6px;">Erreur — Réessayez.</p>'; }
}

/* ═══════════════════════════════════════════════
   GÉNÉRATION DU COMPTE-RENDU
   ─────────────────────────────────────────────
   Prompt engineering : vrai CR médical complet
   + résumé psychologue du discours patient
═══════════════════════════════════════════════ */
async function genReport() {
  var text = document.getElementById('transcript').value.trim();
  if(text.length < 20){ ntf('tr-notif','Volume insuffisant pour générer un bilan.','wa'); return; }

  document.getElementById('btn-generate').disabled = true;
  var body = document.getElementById('report-body');
  body.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;color:var(--tx3);gap:14px;">'
    +'<div class="dots"><span></span><span></span><span></span></div>'
    +'<p style="font-size:12px;font-weight:500;">Rédaction du compte-rendu en cours…</p></div>';

  var notes  = document.getElementById('notes').value;
  var dureeMin = Math.floor(secs/60) || null;
  var hist = HIST.length
    ? 'HISTORIQUE DES CONSULTATIONS :\n' + HIST.map(function(h,i){ return (i+1)+'. Séance du '+h.date+' ('+h.duree+' min) : '+h.resume; }).join('\n')
    : 'HISTORIQUE : Première consultation, aucun antécédent de suivi.';

  /* ─────── PROMPT PRINCIPAL ─────────────────── */
  var prompt = '# CONTEXTE\n'
    + 'Tu es un psychologue clinicien senior avec 20 ans d\'expérience. Tu dois rédiger un compte-rendu de consultation psychologique complet, professionnel et confidentiel, tel qu\'il serait rédigé dans un dossier médical officiel.\n\n'

    + '# INFORMATIONS ADMINISTRATIVES\n'
    + '- Nom du patient : ' + PAT + '\n'
    + '- Numéro de séance : ' + SESN + '\n'
    + '- Date de consultation : ' + DATED + '\n'
    + '- Praticien : ' + DR + ', ' + DR_SPEC + '\n'
    + (dureeMin ? '- Durée de la séance : ' + dureeMin + ' minutes\n' : '')
    + '\n'

    + '# ' + hist + '\n\n'

    + (notes ? '# NOTES DU PRATICIEN (observations internes, ne pas citer mot pour mot)\n' + notes + '\n\n' : '')

    + '# VERBATIM COMPLET DE LA SÉANCE\n"""\n' + text + '\n"""\n\n'

    + '# INSTRUCTIONS DE RÉDACTION\n'
    + 'Rédige un compte-rendu médical COMPLET avec les sections suivantes. Chaque section doit être rédigée en prose clinique, en français professionnel, comme le ferait un vrai psychologue clinicien :\n\n'

    + '**resume_psychologue** : Résumé du discours et de l\'état du patient rédigé du point de vue du psychologue. '
    + 'Ce résumé doit capturer : les thèmes centraux abordés par le patient, son état émotionnel et psychologique tel qu\'il s\'est exprimé, les éléments marquants du discours, les préoccupations principales. '
    + 'Rédige comme si tu décrivais à un confrère ce que le patient a dit et comment il était. 4-6 phrases, style clinique mais accessible. '
    + 'Exemple de ton voulu : "M. X s\'est présenté dans un état de fatigue émotionnelle notable. Il a abordé longuement sa relation conflictuelle avec ses parents..."\n\n'

    + '**motif** : Motif de consultation ou thème central de cette séance (1-2 phrases)\n\n'

    + '**deroulement** : Déroulement de la séance — comment s\'est passé l\'entretien, posture du patient, engagement, résistances éventuelles, moments clés (4-6 phrases)\n\n'

    + '**observations** : Observations cliniques — état psychique, comportement, affect observé, cognitions exprimées, fonctionnement global (4-5 phrases)\n\n'

    + '**points_vigilance** : Points de vigilance ou éléments significatifs à surveiller pour la suite (2-4 phrases, cliniquement précis)\n\n'

    + '**hypotheses_diagnostiques** : Tableau diagnostic différentiel. Liste d\'hypothèses avec codes CIM-11. Format : ["6A70 - Trouble dépressif caractérisé", "MB24 - Trouble anxieux généralisé"]\n\n'

    + '**plan_therapeutique** : Plan thérapeutique et orientation pour les prochaines séances (3-4 phrases)\n\n'

    + '**objectifs_prochaine_seance** : 2-3 objectifs concrets et mesurables pour la prochaine séance. Format liste.\n\n'

    + '**niveau_risque** : Évaluation clinique du niveau de risque : "faible", "modéré", "élevé" ou "critique"\n\n'

    + '# FORMAT DE SORTIE\n'
    + 'JSON strict uniquement, sans markdown, sans commentaires.\n'
    + '{"resume_psychologue":"","motif":"","deroulement":"","observations":"","points_vigilance":"","hypotheses_diagnostiques":[],"plan_therapeutique":"","objectifs_prochaine_seance":[],"niveau_risque":"faible"}';

  try {
    var raw = await callAI(prompt, 3500);
    var ai;
    try{ ai = JSON.parse(raw.replace(/```json\n?|\n?```/g,'').trim()); }
    catch(e){ throw new Error('Format JSON invalide : '+ e.message); }

    lastReport = {ai:ai, text:text, duree:dureeMin, date:new Date().toLocaleDateString('fr-FR')};

    // Résumé pour DB (texte pur)
    lastReport.resume_str = [
      'Compte-rendu · '+PAT+' · Séance n°'+SESN+' du '+DATED,
      ai.resume_psychologue || '',
      'Motif : '+(ai.motif||''),
      'Plan : '+(ai.plan_therapeutique||''),
      'Niveau de risque : '+(ai.niveau_risque||'faible')
    ].filter(Boolean).join('\n\n');

    document.getElementById('btn-pdf').disabled = false;
    renderReport(lastReport);
    addInsight('ok', 'Compte-rendu généré', 'Niveau de risque : '+(ai.niveau_risque||'faible'));

  } catch(err) {
    body.innerHTML = '<div style="padding:40px 20px;text-align:center;">'
      +'<p style="color:var(--er);font-weight:700;font-size:13px;margin-bottom:8px;">Erreur de génération</p>'
      +'<p style="color:var(--tx3);font-size:11px;line-height:1.6;">'+err.message+'</p></div>';
  } finally {
    document.getElementById('btn-generate').disabled = false;
  }
}

/* ═══════════════════════════════════════════════
   RENDU DU RAPPORT
═══════════════════════════════════════════════ */
function renderReport(lr) {
  if(!lr) return;
  var ai = lr.ai;
  var niv = (ai.niveau_risque || 'faible').toLowerCase();
  var nivColors = {
    faible:  {bg:'#f0faf5', border:'#a3d9b8', label:'#065f46', badgeBg:'#d1fae5'},
    modéré:  {bg:'#fffbf0', border:'#fcd34d', label:'#92400e', badgeBg:'#fef3c7'},
    élevé:   {bg:'#fff5f5', border:'#fca5a5', label:'#991b1b', badgeBg:'#fee2e2'},
    critique:{bg:'#fff0f0', border:'#ff6b6b', label:'#7f1d1d', badgeBg:'#ffd7d7'}
  };
  var nc = nivColors[niv] || nivColors['faible'];

  var html = '<div class="rpt-wrap">';

  /* En-tête bleu */
  html += '<div class="rpt-top-band">'
    +'<div style="display:flex;justify-content:space-between;align-items:flex-start;">'
      +'<div>'
        +'<p class="rpt-top-label">Compte-Rendu de Consultation Psychologique — Confidentiel</p>'
        +'<p class="rpt-patient-name">'+escH(PAT)+'</p>'
      +'</div>'
      +'<div style="text-align:right;">'
        +'<div style="background:'+nc.badgeBg+';color:'+nc.label+';font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;padding:6px 14px;border-radius:8px;display:inline-block;margin-bottom:6px;">'
          +niv.charAt(0).toUpperCase()+niv.slice(1)
        +'</div>'
        +'<p style="font-size:10px;color:rgba(255,255,255,.4);font-weight:600;">PsySpace Pro</p>'
      +'</div>'
    +'</div>'
    +'<div class="rpt-meta-row">'
      +'<div class="rpt-meta-cell"><div class="rpt-meta-cell-label">Date</div><div class="rpt-meta-cell-val">'+escH(DATED)+'</div></div>'
      +'<div class="rpt-meta-cell"><div class="rpt-meta-cell-label">Séance</div><div class="rpt-meta-cell-val">N°'+SESN+'</div></div>'
      +'<div class="rpt-meta-cell"><div class="rpt-meta-cell-label">Durée</div><div class="rpt-meta-cell-val">'+(lr.duree?lr.duree+' min':'—')+'</div></div>'
      +'<div class="rpt-meta-cell"><div class="rpt-meta-cell-label">Praticien</div><div class="rpt-meta-cell-val">Dr. '+escH(DR)+'</div></div>'
    +'</div>'
  +'</div>'
  +'<div class="rpt-doc-strip">'
    +'<div><div class="rpt-doc-name">Dr. '+escH(DR)+'</div><div class="rpt-doc-sub">'+escH(DR_SPEC)+(DR_RPPS?' · RPPS '+escH(DR_RPPS):'')+'</div></div>'
    +'<div style="text-align:right;"><div class="rpt-doc-sub">'+escH(DATED)+'</div>'+(DR_TEL?'<div class="rpt-doc-sub">'+escH(DR_TEL)+'</div>':'')+'</div>'
  +'</div>';

  html += '<div class="rpt-body">';

  /* Résumé psychologue */
  if(ai.resume_psychologue) {
    html += '<div class="rpt-summary-box">'
      +'<div class="rpt-summary-label">Résumé de la séance — Synthèse du discours patient</div>'
      +'<p class="rpt-summary-text">'+escH(ai.resume_psychologue)+'</p>'
    +'</div>';
  }

  /* Motif */
  if(ai.motif) html += rptSection('Motif de consultation', '<p class="rpt-prose">'+escH(ai.motif)+'</p>');

  /* Déroulement */
  if(ai.deroulement) html += rptSection('Déroulement de la séance', '<p class="rpt-prose">'+escH(ai.deroulement)+'</p>');

  /* Observations */
  if(ai.observations) html += rptSection('Observations cliniques', '<p class="rpt-prose">'+escH(ai.observations)+'</p>');

  /* Points de vigilance */
  if(ai.points_vigilance) {
    html += '<div class="rpt-section">'
      +'<div class="rpt-section-label" style="color:#c0392b;">Points de vigilance</div>'
      +'<div style="background:#fff8f8;border:1px solid #f8c8c8;border-left:4px solid #c0392b;border-radius:0 var(--radius2) var(--radius2) 0;padding:14px 16px;">'
        +'<p class="rpt-prose" style="color:#5a1a1a;">'+escH(ai.points_vigilance)+'</p>'
      +'</div>'
    +'</div>';
  }

  /* Diagnostics */
  if(Array.isArray(ai.hypotheses_diagnostiques) && ai.hypotheses_diagnostiques.length) {
    html += '<div class="rpt-section"><div class="rpt-section-label">Hypothèses diagnostiques · CIM-11</div>';
    ai.hypotheses_diagnostiques.forEach(function(h){
      var m = h.match(/([A-Z][A-Z0-9]+\.?[0-9A-Z]*)/);
      html += '<div class="rpt-diag-row">'+(m?'<span class="rpt-diag-code">'+m[1]+'</span>':'')+'<p class="rpt-diag-text">'+escH(h)+'</p></div>';
    });
    html += '</div>';
  }

  /* Grille plan/objectifs */
  if(ai.plan_therapeutique || (Array.isArray(ai.objectifs_prochaine_seance) && ai.objectifs_prochaine_seance.length)) {
    html += '<div class="rpt-section"><div class="rpt-section-label">Plan thérapeutique & Prochaine séance</div><div class="rpt-grid2">';
    if(ai.plan_therapeutique) {
      html += '<div class="rpt-info-block"><div class="rpt-info-block-label">Plan de suivi</div><p class="rpt-info-block-text">'+escH(ai.plan_therapeutique)+'</p></div>';
    }
    if(Array.isArray(ai.objectifs_prochaine_seance) && ai.objectifs_prochaine_seance.length) {
      html += '<div class="rpt-info-block"><div class="rpt-info-block-label">Objectifs séance suivante</div>';
      ai.objectifs_prochaine_seance.forEach(function(o,i){
        html += '<div class="rpt-obj-row"><span class="rpt-obj-num">'+(i+1)+'</span><p class="rpt-obj-text">'+escH(o)+'</p></div>';
      });
      html += '</div>';
    }
    html += '</div></div>';
  }

  html += '</div>'; /* /rpt-body */

  /* Signature */
  html += '<div class="rpt-sig-block">'
    +'<div><p class="rpt-sig-text">Document confidentiel · Usage clinique exclusif · PsySpace Pro</p></div>'
    +'<div style="text-align:right;">'
      +'<p class="rpt-sig-text">Dr. '+escH(DR)+'</p>'
      +'<p class="rpt-sig-name">'+escH(DR_SPEC)+'</p>'
      +(DR_RPPS?'<p class="rpt-sig-text" style="margin-top:2px;">RPPS : '+escH(DR_RPPS)+'</p>':'')
    +'</div>'
  +'</div>';

  html += '</div>'; /* /rpt-wrap */

  document.getElementById('report-body').innerHTML = html;
}

function rptSection(title, content) {
  return '<div class="rpt-section"><div class="rpt-section-label">'+title+'</div>'+content+'</div>';
}

function escH(s) {
  if(!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/\n/g,'<br>');
}

/* ═══════════════════════════════════════════════
   EXPORT PDF
═══════════════════════════════════════════════ */
function exportPDF() {
  if(!lastReport){ ntf('arch-notif',"Générez d'abord le compte-rendu.",'wa'); return; }
  var j = window.jspdf.jsPDF;
  var doc = new j({unit:'mm', format:'a4'});
  var W = 210, M = 18, y = M;
  var ai = lastReport.ai;
  var niv = (ai.niveau_risque || 'faible').toUpperCase();

  function ln(txt, o) {
    o = o||{}; var sz=o.sz||10, b=o.b||false, it=o.it||false, c=o.c||[30,28,24];
    doc.setFontSize(sz); doc.setFont(b?'helvetica':'times', b?'bold':(it?'italic':'normal'));
    doc.setTextColor(c[0],c[1],c[2]);
    doc.splitTextToSize(String(txt), W-M*2-(o.ind||0)).forEach(function(l){
      if(y>274){doc.addPage();y=M;} doc.text(l, M+(o.ind||0), y); y+=o.lh||6;
    }); y+=1;
  }
  function hr(){doc.setDrawColor(210,206,200);doc.setLineWidth(.15);doc.line(M,y,W-M,y);y+=4;}
  function sec(title, txt){
    if(y>250){doc.addPage();y=M;}
    doc.setFillColor(26,63,168); doc.rect(M,y-2,3,9,'F');
    doc.setFontSize(8);doc.setFont('helvetica','bold');doc.setTextColor(26,63,168);
    doc.text(title.toUpperCase(),M+6,y+4); y+=9;
    if(txt) ln(txt,{sz:10.5,c:[42,37,32],lh:5.5,ind:4});
    y+=3;
  }

  // Header
  doc.setFillColor(26,63,168); doc.rect(0,0,W,38,'F');
  doc.setFontSize(9);doc.setFont('helvetica','bold');doc.setTextColor(200,215,245);
  doc.text('COMPTE-RENDU DE CONSULTATION PSYCHOLOGIQUE — CONFIDENTIEL',M,10);
  doc.setFontSize(20);doc.setFont('times','bold');doc.setTextColor(255,255,255);
  doc.text(PAT,M,22);
  doc.setFontSize(9.5);doc.setFont('helvetica','normal');doc.setTextColor(180,200,240);
  doc.text('Séance n°'+SESN+'  ·  '+DATED+'  ·  Dr. '+DR+(lastReport.duree?'  ·  Durée : '+lastReport.duree+' min':''),M,30);

  // Niveau risque badge
  var nc=[5,120,80]; if(niv==='MODÉRÉ') nc=[120,74,0]; else if(niv==='ÉLEVÉ'||niv==='CRITIQUE') nc=[160,30,30];
  doc.setFillColor(255,255,255); doc.roundedRect(W-M-32,8,30,14,2,2,'F');
  doc.setFontSize(8);doc.setFont('helvetica','bold');doc.setTextColor(nc[0],nc[1],nc[2]);
  doc.text(niv,W-M-17,16.5,{align:'center'});
  y=46;

  // Praticien
  doc.setFillColor(248,247,244); doc.rect(M,y-2,W-M*2,14,'F');
  doc.setFontSize(9);doc.setFont('helvetica','bold');doc.setTextColor(26,63,168);
  doc.text('Dr. '+DR,M+4,y+4);
  doc.setFontSize(8.5);doc.setFont('helvetica','normal');doc.setTextColor(90,86,82);
  doc.text(DR_SPEC+(DR_RPPS?' · RPPS '+DR_RPPS:''),M+4,y+9);
  if(DR_TEL) doc.text(DR_TEL,W-M-4,y+4,{align:'right'});
  y+=18; hr();

  // Résumé
  if(ai.resume_psychologue){
    doc.setFillColor(240,245,255); doc.roundedRect(M,y-2,W-M*2,26,2,2,'F');
    doc.setDrawColor(200,215,245);doc.setLineWidth(.3);doc.roundedRect(M,y-2,W-M*2,26,2,2,'S');
    doc.setFillColor(26,63,168);doc.rect(M,y-2,3,26,'F');
    doc.setFontSize(8);doc.setFont('helvetica','bold');doc.setTextColor(26,63,168);
    doc.text('RÉSUMÉ DE LA SÉANCE — SYNTHÈSE DU DISCOURS PATIENT',M+6,y+3);
    y+=7;
    ln(ai.resume_psychologue,{sz:11,c:[26,40,100],lh:5.5,ind:4,it:true}); y+=4;
  }

  hr();
  sec('Motif de consultation', ai.motif||'');
  sec('Déroulement de la séance', ai.deroulement||'');
  sec('Observations cliniques', ai.observations||'');
  if(ai.points_vigilance){
    if(y>240){doc.addPage();y=M;}
    doc.setFillColor(255,248,248);doc.roundedRect(M,y-2,W-M*2,4,'F');
    sec('Points de vigilance', ai.points_vigilance);
  }
  if(Array.isArray(ai.hypotheses_diagnostiques)&&ai.hypotheses_diagnostiques.length){
    sec('Hypothèses diagnostiques · CIM-11', null);
    ai.hypotheses_diagnostiques.forEach(function(h){ ln('• '+h,{sz:10,c:[26,40,100],ind:6,lh:5.5}); }); y+=2;
  }
  sec('Plan thérapeutique', ai.plan_therapeutique||'');
  if(Array.isArray(ai.objectifs_prochaine_seance)&&ai.objectifs_prochaine_seance.length){
    sec('Objectifs · Prochaine séance', null);
    ai.objectifs_prochaine_seance.forEach(function(o,i){ ln((i+1)+'. '+o,{sz:10,c:[5,90,60],ind:6,lh:5.5}); }); y+=2;
  }

  var notesVal = document.getElementById('notes').value;
  if(notesVal){ hr(); sec('Notes du praticien (usage interne)', notesVal); }

  hr();
  doc.setFontSize(8);doc.setFont('helvetica','normal');doc.setTextColor(150,146,142);
  doc.text('PsySpace Pro · Document confidentiel · Usage clinique exclusif',M,288);
  doc.text('Dr. '+DR+' · '+lastReport.date,W-M,288,{align:'right'});

  doc.save('CR_'+PAT.replace(/\s+/g,'_')+'_S'+SESN+'_'+DATED.replace(/\//g,'-')+'.pdf');
}

/* ═══════════════════════════════════════════════
   ARCHIVAGE
═══════════════════════════════════════════════ */
async function finalize() {
  var tr = document.getElementById('transcript').value.trim();
  if(!tr){ ntf('arch-notif','Aucune transcription à archiver.','wa'); return; }

  openOverlay(
    'Archiver la séance',
    'Vous allez archiver la séance n°'+SESN+' de <strong>'+escH(PAT)+'</strong>.<br><br>Cette action est définitive. Le compte-rendu sera sauvegardé dans le dossier patient.',
    async function(){
      ntf('arch-notif','Archivage en cours…','in', 0);
      var resumeTexte = lastReport ? lastReport.resume_str : ('Séance n°'+SESN+' du '+DATED+' — Transcription sans compte-rendu généré.');
      var fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('transcript', tr);
      fd.append('resume', resumeTexte);
      fd.append('duree', String(Math.floor(secs/60)));
      fd.append('emotions', JSON.stringify(emoPoints));
      try {
        var res = await fetch('save_consultation.php', {method:'POST', body:fd});
        if(!res.ok) throw new Error('HTTP '+res.status);
        var d = await res.text();
        d = d.trim();
        if(d === 'success'){
          ntf('arch-notif','✓ Séance archivée avec succès !','ok', 0);
          setTimeout(function(){ window.location.href='dashboard.php'; }, 2000);
        } else if(d === 'already_saved'){
          ntf('arch-notif','Cette séance a déjà été archivée.','wa');
        } else if(d === 'csrf_invalid'){
          ntf('arch-notif','Erreur de sécurité. Rechargez la page.','er');
        } else {
          ntf('arch-notif','Erreur : '+d,'er');
        }
      } catch(e){ ntf('arch-notif','Erreur réseau : '+e.message,'er'); }
    }
  );
}

/* ═══════════════════════════════════════════════
   MICROPHONE
═══════════════════════════════════════════════ */
var SR = window.SpeechRecognition || window.webkitSpeechRecognition;

function makeRecog(){
  if(!SR) return null;
  var r = new SR();
  r.lang='fr-FR'; r.continuous=true; r.interimResults=true; r.maxAlternatives=1;
  r.onstart = function(){
    micOn = true;
    document.getElementById('mic-btn').className = 'mic-btn live';
    document.getElementById('mic-label').textContent = '● CAPTURE ACTIVE — Cliquez pour arrêter';
    document.getElementById('mic-label').style.color = 'var(--er)';
    document.getElementById('timer-dot').className = 'timer-dot live';
    document.getElementById('rec-status').textContent = '● Enregistrement';
    document.getElementById('rec-status').className = 'chip chip-live';
    startWaveform();
    clearInterval(timerIv);
    timerIv = setInterval(function(){
      secs++;
      var m = Math.floor(secs/60).toString().padStart(2,'0');
      var s = (secs%60).toString().padStart(2,'0');
      document.getElementById('timer').textContent = m+':'+s;
      document.getElementById('audio-bar').style.width = (15+Math.random()*60)+'%';
    }, 1000);
  };
  r.onresult = function(e){
    var final = '';
    for(var i=e.resultIndex;i<e.results.length;i++){
      if(e.results[i].isFinal) final += e.results[i][0].transcript+' ';
    }
    if(final){
      var el = document.getElementById('transcript');
      el.value += final; el.scrollTop = el.scrollHeight;
      onTyping(el.value);
    }
  };
  r.onerror = function(e){
    if(e.error==='no-speech') return;
    if(e.error==='not-allowed'||e.error==='permission-denied'){ ntf('tr-notif','Accès microphone refusé.','er'); setMicStopped(); }
  };
  r.onend = function(){ if(micOn){ try{ recog.start(); }catch(ex){ setMicStopped(); } } };
  return r;
}

if(SR){ recog = makeRecog(); }
else {
  setTimeout(function(){
    document.getElementById('stt-warning').style.display = 'block';
    var mb = document.getElementById('mic-btn');
    if(mb){ mb.disabled=true; mb.style.opacity='.3'; mb.style.cursor='not-allowed'; }
  }, 500);
}

function toggleMic(){
  if(!SR){ ntf('tr-notif','Microphone non supporté (utilisez Chrome ou Edge).','er'); return; }
  if(micOn){ micOn=false; try{ recog.stop(); }catch(e){} setMicStopped(); }
  else { try{ recog.abort(); }catch(e){} recog=makeRecog(); try{ recog.start(); }catch(e){ ntf('tr-notif','Impossible de démarrer : '+e.message,'er'); } }
}

function setMicStopped(){
  micOn=false; clearInterval(timerIv); stopWaveform();
  document.getElementById('audio-bar').style.width='0%';
  document.getElementById('mic-btn').className='mic-btn done';
  document.getElementById('mic-label').textContent='Transcription terminée — Cliquez pour reprendre';
  document.getElementById('mic-label').style.color='var(--ok)';
  document.getElementById('timer-dot').className='timer-dot';
  document.getElementById('timer-dot').style.background='var(--ok)';
  document.getElementById('rec-status').textContent='Terminé';
  document.getElementById('rec-status').className='chip chip-ok';
  document.getElementById('btn-generate').disabled=false;
}

var wvIv = null;
function startWaveform(){
  var bars = document.querySelectorAll('.wv-bar');
  wvIv = setInterval(function(){
    bars.forEach(function(b){
      b.style.height = (3+Math.random()*16)+'px';
      b.style.background = 'var(--accent)';
    });
  }, 100);
}
function stopWaveform(){
  clearInterval(wvIv);
  document.querySelectorAll('.wv-bar').forEach(function(b){ b.style.height='3px'; b.style.background='var(--border)'; });
}

function clearTranscript(){
  openOverlay('Effacer la transcription','Voulez-vous effacer tout le contenu de la transcription ? Cette action est irréversible.', function(){
    document.getElementById('transcript').value = '';
    document.getElementById('word-count').textContent = '0 mot';
    document.getElementById('wc-fill').style.width = '0%';
    emoPoints=[0]; tlRisk=[]; tlResil=[]; tlDetresse=[]; tlAnx=[]; radarData={d:0,a:0,r:0,s:0,lien:0};
    drawEmo(); drawRadar(); drawTL();
    ntf('tr-notif','Transcription effacée.','in', 2500);
  });
}

function loadPrev(txt){
  openOverlay('Charger les notes précédentes', 'Ajouter le résumé de la séance précédente dans vos notes praticien ?', function(){
    var n = document.getElementById('notes');
    n.value = (n.value ? n.value+'\n\n' : '') + '[Séance précédente]\n'+txt.slice(0,500);
  });
}

/* ═══════════════════════════════════════════════
   INIT
═══════════════════════════════════════════════ */
drawEmo();
drawRadar();
drawTL();
</script>
</body>
</html>