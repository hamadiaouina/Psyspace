<?php
// --- 1. SÉCURITÉ DES SESSIONS & HEADERS ---
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
        session_destroy();
        header("Location: login.php?error=hijack");
        exit();
    }
}

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob:; connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; object-src 'none'; base-uri 'self'; frame-ancestors 'none';");

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
        'resume'=> mb_substr(strip_tags($pc['resume_ia'] ?? ''), 0, 300, 'UTF-8')
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<link rel="icon" type="image/png" href="assets/images/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Séance · <?= htmlspecialchars($patient_selected, ENT_QUOTES, 'UTF-8') ?> | PsySpace</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Lora:ital,wght@0,400;0,600;1,400;1,600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#07090f;
  --s1:#0d1018;
  --s2:rgba(13,16,24,.85);
  --b:rgba(255,255,255,.05);
  --b2:rgba(255,255,255,.028);
  --ac:#6366f1;
  --ac2:#a5b4fc;
  --ok:#059669;
  --wa:#d97706;
  --er:#dc2626;
  --in:#0284c7;
  --tx:#e2e8f0;
  --tx2:#94a3b8;
  --tx3:#374151;
  --su:#2d3748;
  --glow:rgba(99,102,241,.12);
  /* rapport */
  --rp-bg:#f9fafb;
  --rp-bg2:#f1f5f9;
  --rp-border:#e2e8f0;
  --rp-tx:#0f172a;
  --rp-tx2:#334155;
  --rp-tx3:#64748b;
  --rp-accent:#1e40af;
  --rp-rule:#cbd5e1;
}
html,body{height:100%;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--tx);overflow:hidden;}
::-webkit-scrollbar{width:3px;}::-webkit-scrollbar-thumb{background:#1a2035;border-radius:3px;}
::-webkit-scrollbar-track{background:transparent;}

.app{display:grid;grid-template-rows:52px 1fr;height:100vh;}
.g3{display:grid;grid-template-columns:268px 1fr 310px;height:calc(100vh - 52px);overflow:hidden;}
.col{overflow-y:auto;display:flex;flex-direction:column;gap:10px;padding:12px 10px;}

/* TOPBAR */
.top{
  display:flex;align-items:center;justify-content:space-between;
  padding:0 18px;
  background:rgba(7,9,15,.98);
  border-bottom:1px solid rgba(255,255,255,.045);
}

/* CARDS */
.card{
  background:linear-gradient(135deg,rgba(13,16,24,.98) 0%,rgba(11,14,22,.98) 100%);
  border:1px solid rgba(255,255,255,.06);
  border-radius:14px;padding:14px;
  box-shadow:inset 0 1px 0 rgba(255,255,255,.04);
}
.card2{background:rgba(7,9,15,.6);border:1px solid var(--b2);border-radius:10px;padding:10px;}

.lbl{font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.2em;color:var(--tx3);}

/* CHIPS */
.chip{display:inline-flex;align-items:center;gap:3px;padding:2px 9px;border-radius:99px;
  font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;border:1px solid;}
.ck{background:rgba(5,150,105,.07);color:#34d399;border-color:rgba(5,150,105,.2);}
.cw{background:rgba(217,119,6,.07);color:#fbbf24;border-color:rgba(217,119,6,.2);}
.ce{background:rgba(220,38,38,.08);color:#f87171;border-color:rgba(220,38,38,.2);}
.ci{background:rgba(99,102,241,.08);color:var(--ac2);border-color:rgba(99,102,241,.2);}
.cs{background:rgba(55,65,81,.08);color:#4b5563;border-color:rgba(55,65,81,.18);}

/* BUTTONS */
.btn{font-size:8.5px;font-weight:700;text-transform:uppercase;letter-spacing:.13em;
  border-radius:9px;padding:8px 14px;border:none;cursor:pointer;transition:all .18s;
  display:inline-flex;align-items:center;justify-content:center;gap:5px;}
.ba{background:var(--ac);color:#fff;box-shadow:0 2px 12px rgba(99,102,241,.25);}
.ba:hover{background:#4f46e5;box-shadow:0 4px 20px rgba(99,102,241,.38);}
.bok{background:rgba(5,150,105,.09);color:#34d399;border:1px solid rgba(5,150,105,.18);}
.bok:hover{background:rgba(5,150,105,.18);}
.bg{background:transparent;color:var(--tx3);border:1px solid var(--b);}
.bg:hover{color:var(--tx);border-color:rgba(255,255,255,.15);}
.bq{background:rgba(99,102,241,.07);color:var(--ac2);border:1px solid rgba(99,102,241,.14);}
.btn:disabled{opacity:.2;cursor:not-allowed;pointer-events:none;}

/* TEXTAREA */
.ta{
  background:rgba(7,9,15,.8);border:1px solid rgba(255,255,255,.06);color:#c8d3e8;
  border-radius:12px;resize:none;outline:none;width:100%;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;line-height:1.88;padding:13px;
  transition:border-color .22s,box-shadow .22s;
}
.ta:focus{border-color:rgba(99,102,241,.3);box-shadow:0 0 0 3px rgba(99,102,241,.07);}
.ta::placeholder{color:#1e2a3a;font-style:italic;}
.nta{
  background:rgba(217,119,6,.03);border:1px solid rgba(217,119,6,.1);color:#fbbf24;
  border-radius:10px;resize:none;outline:none;width:100%;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:11.5px;line-height:1.7;padding:10px;
  transition:border-color .22s;
}
.nta:focus{border-color:rgba(217,119,6,.25);}
.nta::placeholder{color:rgba(217,119,6,.15);font-style:italic;}

/* TOAST */
.toast{border-radius:9px;padding:7px 12px;font-size:9.5px;font-weight:700;display:flex;align-items:center;gap:6px;}
.tok{background:rgba(5,150,105,.09);border:1px solid rgba(5,150,105,.2);color:#6ee7b7;}
.ter{background:rgba(220,38,38,.09);border:1px solid rgba(220,38,38,.2);color:#fca5a5;}
.twa{background:rgba(217,119,6,.09);border:1px solid rgba(217,119,6,.2);color:#fcd34d;}
.tin{background:rgba(99,102,241,.09);border:1px solid rgba(99,102,241,.2);color:var(--ac2);}

/* MIC */
.mic{width:44px;height:44px;border-radius:50%;border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:all .22s;flex-shrink:0;}
.mic-idle{background:rgba(99,102,241,.1);border:1.5px solid rgba(99,102,241,.24);}
.mic-live{background:#dc2626;animation:mring 1.3s ease-out infinite;}
.mic-done{background:rgba(5,150,105,.1);border:1.5px solid rgba(5,150,105,.24);}
@keyframes mring{0%{box-shadow:0 0 0 0 rgba(220,38,38,.5)}70%{box-shadow:0 0 0 13px rgba(220,38,38,0)}100%{box-shadow:0 0 0 0 rgba(220,38,38,0)}}

/* FEED */
.fi{padding:8px 11px;border-radius:10px;border-left:2px solid;margin-bottom:5px;animation:sin .28s ease forwards;}
@keyframes sin{from{opacity:0;transform:translateX(7px)}to{opacity:1;transform:none}}
@keyframes fup{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}
.fu{animation:fup .28s ease forwards;}

/* DOTS */
.dots span{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--ac);
  margin:0 2px;animation:db 1.2s ease-in-out infinite;}
.dots span:nth-child(2){animation-delay:.2s}.dots span:nth-child(3){animation-delay:.4s}
@keyframes db{0%,80%,100%{transform:scale(.35);opacity:.25}40%{transform:scale(1);opacity:1}}

.pulse{animation:pls 2s cubic-bezier(.4,0,.6,1) infinite;}
@keyframes pls{0%,100%{opacity:1}50%{opacity:.3}}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}

/* DIAG PILL */
.dp{display:flex;align-items:flex-start;gap:7px;padding:8px 10px;
  border-radius:10px;border:1px solid rgba(56,189,248,.13);background:rgba(56,189,248,.04);margin-bottom:5px;}
.dc{font-size:8px;font-weight:900;color:#38bdf8;background:rgba(14,165,233,.1);
  border-radius:4px;padding:2px 5px;white-space:nowrap;flex-shrink:0;margin-top:1px;letter-spacing:.03em;}

/* OVERLAY */
.ov{position:fixed;inset:0;background:rgba(0,0,0,.88);backdrop-filter:blur(12px);
  z-index:999;display:flex;align-items:center;justify-content:center;
  opacity:0;pointer-events:none;transition:opacity .22s;}
.ov.open{opacity:1;pointer-events:all;}
.ovb{background:rgba(9,11,19,.99);border:1px solid rgba(255,255,255,.08);
  border-radius:18px;padding:26px;max-width:380px;width:calc(100% - 32px);
  transform:scale(.95);transition:transform .22s;}
.ov.open .ovb{transform:scale(1);}

.rd{height:1px;background:rgba(255,255,255,.04);margin:8px 12px;}

/* ═══════════════════════════════════════════════════
   RAPPORT CLINIQUE — style dossier médical
   ═══════════════════════════════════════════════════ */
.rpt-wrap{
  font-family:'Lora','Georgia',serif;
  background:var(--rp-bg);
  color:var(--rp-tx);
  border-radius:10px;
  border:1px solid var(--rp-border);
  overflow:hidden;
}
.rpt-header{
  background:var(--rp-accent);
  padding:18px 22px 15px;
  display:flex;justify-content:space-between;align-items:flex-start;
}
.rpt-header-title{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.22em;
  color:rgba(255,255,255,.5);margin-bottom:4px;
}
.rpt-header-name{
  font-family:'Lora',serif;font-size:19px;font-weight:600;font-style:italic;color:#fff;line-height:1.2;
}
.rpt-header-meta{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:9px;font-weight:500;color:rgba(255,255,255,.55);margin-top:6px;line-height:1.8;
}
.rpt-header-right{display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;}
.rpt-niv-badge{
  font-family:'Plus Jakarta Sans',sans-serif;font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;
  padding:4px 12px;border-radius:6px;border:1.5px solid rgba(255,255,255,.25);color:#fff;
}
.rpt-body{padding:20px 22px 14px;}
.rpt-section{margin-bottom:18px;}
.rpt-section-title{
  font-family:'Plus Jakarta Sans',sans-serif;font-size:8px;font-weight:800;text-transform:uppercase;
  letter-spacing:.2em;color:var(--rp-tx3);margin-bottom:9px;padding-bottom:6px;
  border-bottom:1.5px solid var(--rp-rule);
  display:flex;align-items:center;gap:7px;
}
.rpt-section-title::before{
  content:'';width:3px;height:11px;border-radius:2px;background:var(--rp-accent);flex-shrink:0;
}
.rpt-prose{
  font-family:'Lora',serif;font-size:12.5px;line-height:1.9;color:var(--rp-tx2);
}
.rpt-prose strong{color:var(--rp-tx);font-weight:600;}
.rpt-highlight{
  background:var(--rp-bg2);border-left:3px solid var(--rp-accent);
  border-radius:0 7px 7px 0;padding:11px 15px;margin:10px 0;
}
.rpt-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.rpt-col-block{
  background:var(--rp-bg2);border:1px solid var(--rp-border);border-radius:8px;padding:11px 13px;
}
.rpt-col-label{
  font-family:'Plus Jakarta Sans',sans-serif;font-size:7.5px;font-weight:800;text-transform:uppercase;
  letter-spacing:.18em;color:var(--rp-tx3);margin-bottom:6px;
}
.rpt-risk-band{
  display:flex;align-items:flex-start;gap:13px;padding:12px 15px;
  border-radius:8px;border:1.5px solid;margin-bottom:16px;
}
.rpt-risk-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;margin-top:3px;}
.rpt-risk-label{
  font-family:'Plus Jakarta Sans',sans-serif;font-size:8px;font-weight:800;text-transform:uppercase;
  letter-spacing:.15em;margin-bottom:4px;
}
.rpt-diag-row{
  display:flex;align-items:flex-start;gap:11px;
  padding:10px 0;border-bottom:1px solid var(--rp-rule);
}
.rpt-diag-row:last-child{border-bottom:none;}
.rpt-diag-code{
  font-family:'Plus Jakarta Sans',sans-serif;font-size:8px;font-weight:800;
  background:var(--rp-accent);color:#fff;padding:2px 8px;border-radius:5px;
  flex-shrink:0;margin-top:2px;letter-spacing:.04em;
}
.rpt-diag-text{font-family:'Lora',serif;font-size:12px;color:var(--rp-tx2);line-height:1.65;}
.rpt-obj-row{display:flex;align-items:flex-start;gap:9px;padding:5px 0;}
.rpt-obj-num{
  font-family:'Plus Jakarta Sans',sans-serif;font-size:10px;font-weight:800;color:var(--rp-accent);
  width:20px;flex-shrink:0;text-align:center;margin-top:1px;
}
.rpt-plan{background:var(--rp-bg2);border:1px solid var(--rp-border);border-radius:8px;padding:13px 15px;}
.rpt-letter{background:#fff;border:1px solid var(--rp-border);border-radius:8px;padding:18px 20px;border-top:3px solid var(--rp-accent);}
.rpt-letter-pre{font-family:'Lora',serif;font-size:12px;color:var(--rp-tx2);line-height:1.9;white-space:pre-line;}
.rpt-footer{
  padding:11px 22px;border-top:1px solid var(--rp-rule);
  display:flex;justify-content:space-between;align-items:center;background:var(--rp-bg2);
}
.rpt-footer-txt{
  font-family:'Plus Jakarta Sans',sans-serif;font-size:7.5px;font-weight:600;
  color:var(--rp-tx3);letter-spacing:.04em;
}
.rpt-vigilance{
  background:#fff8f8;border:1px solid #f8c8c8;border-left:3px solid #c0392b;
  border-radius:0 7px 7px 0;padding:11px 15px;
}
.rpt-vigilance-label{
  font-family:'Plus Jakarta Sans',sans-serif;font-size:7.5px;font-weight:800;text-transform:uppercase;
  letter-spacing:.18em;color:#c0392b;margin-bottom:4px;
}
.rpt-vigilance-text{font-family:'Lora',serif;font-size:12px;color:#5a1a1a;line-height:1.72;}

/* RESUME simple */
.rpt-resume-box{
  background:var(--rp-bg2);border:1.5px solid var(--rp-border);border-radius:10px;
  padding:16px 18px;margin-bottom:18px;border-top:3px solid var(--rp-accent);
}
.rpt-resume-label{
  font-family:'Plus Jakarta Sans',sans-serif;font-size:8px;font-weight:800;text-transform:uppercase;
  letter-spacing:.2em;color:var(--rp-accent);margin-bottom:8px;
}
.rpt-resume-text{font-family:'Lora',serif;font-size:13px;line-height:1.9;color:var(--rp-tx);font-style:italic;}

/* INFOS ROW */
.rpt-info-row{
  display:grid;grid-template-columns:repeat(4,1fr);gap:10px;
  margin-bottom:18px;
}
.rpt-info-cell{
  background:#fff;border:1px solid var(--rp-border);border-radius:8px;
  padding:10px 12px;text-align:center;
}
.rpt-info-cell-label{
  font-family:'Plus Jakarta Sans',sans-serif;font-size:7px;font-weight:800;text-transform:uppercase;
  letter-spacing:.15em;color:var(--rp-tx3);margin-bottom:4px;
}
.rpt-info-cell-val{
  font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;font-weight:700;color:var(--rp-tx);
}
</style>
</head>
<body>
<div class="app">

<!-- TOPBAR -->
<div class="top">
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="dashboard.php" style="color:var(--tx3);font-size:17px;font-weight:700;text-decoration:none;padding:4px 6px;border-radius:7px;transition:all .18s;"
       onmouseover="this.style.color='var(--tx)'" onmouseout="this.style.color='var(--tx3)'">←</a>
    <div style="width:1px;height:18px;background:var(--b);"></div>
    <div style="width:30px;height:30px;border-radius:8px;background:rgba(99,102,241,.14);border:1px solid rgba(99,102,241,.22);
      display:flex;align-items:center;justify-content:center;font-weight:900;color:var(--ac2);font-size:12px;flex-shrink:0;">
      <?= strtoupper(mb_substr($patient_selected,0,1,'UTF-8')) ?>
    </div>
    <div>
      <p style="font-size:12.5px;font-weight:800;color:var(--tx);text-transform:uppercase;letter-spacing:.04em;line-height:1;">
        <?= htmlspecialchars($patient_selected) ?>
      </p>
      <p class="lbl" style="margin-top:2px;color:var(--tx3);">
        Séance n°<?= $session_num ?> · <?= $appt_date ?>
        <?php if($session_num>1): ?> · <?= $session_num-1 ?> séance<?= ($session_num-1)>1?'s':'' ?> antérieure<?= ($session_num-1)>1?'s':'' ?><?php endif; ?>
      </p>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:18px;">
    <div style="display:flex;align-items:center;gap:5px;">
      <span style="width:5px;height:5px;border-radius:50%;background:var(--ac);" class="pulse"></span>
      <span class="lbl" style="color:var(--ac2);">IA active</span>
    </div>
    <div style="display:flex;align-items:center;gap:7px;background:rgba(255,255,255,.03);border:1px solid var(--b);border-radius:9px;padding:4px 10px;">
      <span id="recdot" style="width:6px;height:6px;border-radius:50%;background:var(--su);transition:all .3s;"></span>
      <span id="timer" style="font-size:17px;font-weight:900;letter-spacing:.06em;color:var(--tx3);font-variant-numeric:tabular-nums;">00:00</span>
    </div>
    <div id="stop" class="chip cs">En attente</div>
  </div>
</div>

<!-- GRID 3 COLONNES -->
<div class="g3">

<!-- ═══════════════ COL GAUCHE -->
<div class="col" style="border-right:1px solid rgba(255,255,255,.04);">

  <!-- IA INSIGHTS LIVE -->
  <div class="card" style="flex:1;min-height:120px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <div style="display:flex;align-items:center;gap:5px;">
        <span style="width:4px;height:4px;border-radius:50%;background:var(--ac);" class="pulse"></span>
        <p class="lbl" style="color:var(--ac2);">IA · Insights live</p>
      </div>
      <div style="display:flex;align-items:center;gap:6px;">
        <div id="think" style="display:none;"><div class="dots"><span></span><span></span><span></span></div></div>
        <button id="btnask" onclick="toggleAsk()" class="btn bq" style="padding:4px 9px;font-size:7.5px;" disabled>✦ Demander</button>
      </div>
    </div>
    <div id="feed" style="max-height:260px;overflow-y:auto;">
      <div id="feedph" style="padding:24px 0;text-align:center;opacity:.18;">
        <p class="lbl" style="line-height:2.2;">Démarrez la capture<br>L'IA analysera en continu</p>
      </div>
    </div>
  </div>

  <!-- QUESTION LIBRE -->
  <div id="askbox" style="display:none;">
    <div class="card2">
      <textarea id="askin" rows="2" class="ta" style="font-size:11px;padding:9px;border-radius:9px;margin-bottom:7px;"
        placeholder="Ex : Signes de dissociation ? Protocole ACT adapté ?"></textarea>
      <div style="display:flex;gap:6px;">
        <button onclick="sendQ()" class="btn ba" style="flex:1;padding:7px;">Demander</button>
        <button onclick="toggleAsk()" class="btn bg" style="padding:7px 11px;">✕</button>
      </div>
      <div id="askr" style="margin-top:6px;"></div>
    </div>
  </div>

  <!-- HYPOTHÈSES DIAG -->
  <div class="card">
    <div style="display:flex;align-items:center;gap:5px;margin-bottom:7px;">
      <span style="width:4px;height:4px;border-radius:50%;background:#38bdf8;" class="pulse"></span>
      <p class="lbl" style="color:#38bdf8;">IA · Hypothèses diagnostiques</p>
    </div>
    <div id="diagb">
      <div id="diagph" style="padding:12px 0;text-align:center;opacity:.18;">
        <p class="lbl" style="line-height:2.2;">Générées automatiquement<br>au fil du verbatim</p>
      </div>
    </div>
  </div>

  <!-- NOTES CLINICIEN -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;">
      <p class="lbl">Notes praticien</p>
      <span class="chip cw" style="font-size:7px;">Privé</span>
    </div>
    <textarea id="notes" class="nta" rows="5"
      placeholder="Observations, hypothèses cliniques, impressions du praticien…"></textarea>
  </div>

  <!-- HISTORIQUE SÉANCES -->
  <?php if(!empty($prev_consults)): ?>
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <p class="lbl" style="color:var(--ac2);">Suivi longitudinal</p>
      <span class="chip ci" style="font-size:7px;"><?= count($prev_consults) ?> séance<?= count($prev_consults)>1?'s':'' ?></span>
    </div>
    <div style="height:44px;position:relative;margin-bottom:8px;"><canvas id="lgChart"></canvas></div>
    <div style="display:flex;flex-direction:column;gap:3px;">
      <?php foreach($prev_consults as $i=>$pc): ?>
      <div style="display:flex;align-items:center;gap:7px;padding:7px 9px;border-radius:9px;border:1px solid var(--b2);
        background:rgba(13,16,24,.5);cursor:pointer;transition:border-color .18s;"
        onmouseover="this.style.borderColor='rgba(99,102,241,.28)'"
        onmouseout="this.style.borderColor='var(--b2)'"
        onclick="loadPrev(<?= json_encode(strip_tags($pc['resume_ia']??'')) ?>)">
        <div style="width:6px;height:6px;border-radius:50%;flex-shrink:0;
          <?= $i===0?'background:var(--ac);':'background:transparent;border:1.5px solid var(--su);' ?>"></div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;justify-content:space-between;">
            <p style="font-size:9px;font-weight:700;color:<?= $i===0?'var(--ac2)':'#4b5563' ?>;">
              <?= date('d M Y', strtotime($pc['date_consultation'])) ?>
            </p>
            <span style="font-size:7.5px;font-weight:700;color:var(--su);"><?= $pc['duree_minutes'] ?>min</span>
          </div>
          <p style="font-size:8px;color:var(--su);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;">
            <?= htmlspecialchars(mb_substr(strip_tags($pc['resume_ia']??''),0,52,'UTF-8')) ?>…
          </p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <div style="padding:16px;text-align:center;opacity:.16;">
    <p class="lbl" style="line-height:2.2;">Première séance<br>Aucun historique disponible</p>
  </div>
  <?php endif; ?>

</div>

<!-- ═══════════════ COL CENTRE -->
<div class="col" style="padding:12px 14px;">

  <!-- TRANSCRIPTION -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:11px;">
      <div>
        <p style="font-size:13.5px;font-weight:800;color:var(--tx);">Transcription de séance</p>
        <p class="lbl" style="margin-top:2px;">Capture vocale ou saisie manuelle · Analyse IA en continu</p>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <div id="wv" style="display:flex;align-items:center;gap:2px;height:20px;">
          <?php for($i=0;$i<12;$i++): ?><div style="width:2.5px;height:3px;background:var(--su);border-radius:2px;transition:height .1s,background .15s;"></div><?php endfor; ?>
        </div>
        <span id="wc" class="chip cs">0 mot</span>
      </div>
    </div>

    <textarea id="tr" class="ta" rows="12"
      placeholder="La parole s'inscrit ici automatiquement lors de la capture vocale…"
      oninput="onTyping(this.value)"></textarea>

    <div id="ntr" style="margin-top:6px;min-height:26px;"></div>

    <div style="display:flex;align-items:center;gap:10px;margin-top:10px;">
      <button id="micbtn" onclick="toggleMic()" class="mic mic-idle" title="Démarrer la capture vocale">
        <svg id="micsvg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="var(--ac2)"
          stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
          <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
          <line x1="12" y1="19" x2="12" y2="23"/>
          <line x1="8" y1="23" x2="16" y2="23"/>
        </svg>
      </button>
      <div style="flex:1;">
        <p id="miclbl" style="font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--su);margin-bottom:3px;">
          Micro inactif — Cliquez pour démarrer
        </p>
        <div style="height:2.5px;background:rgba(255,255,255,.04);border-radius:2px;overflow:hidden;">
          <div id="abar" style="width:0%;height:2.5px;background:var(--ac);border-radius:2px;transition:width .1s;"></div>
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:5px;cursor:pointer;flex-shrink:0;">
        <input type="checkbox" id="autoai" checked style="accent-color:var(--ac);width:12px;height:12px;">
        <span class="lbl" style="color:var(--ac2);">Auto-IA</span>
      </label>
      <button onclick="clearTr()" class="btn"
        style="background:rgba(220,38,38,.07);color:#f87171;border:1px solid rgba(220,38,38,.14);padding:7px 10px;font-size:8px;flex-shrink:0;">✕ Effacer</button>
    </div>

    <div id="sttw" style="display:none;margin-top:8px;padding:9px 12px;border-radius:9px;
      background:rgba(217,119,6,.06);border:1px solid rgba(217,119,6,.18);">
      <p style="font-size:9.5px;color:#fbbf24;font-weight:600;line-height:1.6;">
        ⚠ Reconnaissance vocale non disponible. Utilisez Chrome ou Edge et autorisez le microphone.
      </p>
    </div>
  </div>

  <!-- COMPTE-RENDU CLINIQUE -->
  <div class="card" style="flex:1;padding:0;overflow:hidden;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:13px 14px 12px;border-bottom:1px solid rgba(255,255,255,.045);">
      <div>
        <p style="font-size:13.5px;font-weight:800;color:var(--tx);">Compte-rendu clinique</p>
        <p class="lbl" style="margin-top:2px;">Généré par IA · Confidentiel · Médical</p>
      </div>
      <button id="btngen" onclick="genReport()" disabled class="btn ba" style="gap:6px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Générer le bilan
      </button>
    </div>

    <div id="rpbody" style="max-height:calc(100vh - 290px);overflow-y:auto;">
      <div id="rp-placeholder" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;opacity:.14;">
        <div style="width:28px;height:28px;border:1.5px dashed #4b5563;border-radius:50%;margin-bottom:12px;animation:spin 3s linear infinite;"></div>
        <p class="lbl" style="text-align:center;line-height:2.2;">En attente de transcription</p>
      </div>
    </div>

    <div style="padding:10px 14px;border-top:1px solid rgba(255,255,255,.04);">
      <div id="narch" style="margin-bottom:7px;min-height:24px;"></div>
      <div style="display:flex;gap:8px;">
        <button onclick="exportPDF()" id="btnpdf" disabled class="btn bg" style="flex:1;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Exporter PDF
        </button>
        <button onclick="finalize()" class="btn bok" style="flex:1;">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Archiver la séance
        </button>
      </div>
    </div>
  </div>

</div>

<!-- ═══════════════ COL DROITE -->
<div class="col" style="border-left:1px solid rgba(255,255,255,.04);padding:0;gap:0;">

  <!-- RADAR -->
  <div style="padding:13px 13px 0;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <div>
        <p class="lbl" style="color:var(--ac2);">Profil psycho-émotionnel</p>
        <p style="font-size:7.5px;color:var(--su);margin-top:1px;font-weight:600;">Analyse multidimensionnelle · temps réel</p>
      </div>
      <span id="rchip" class="chip cs" style="font-size:7px;">En attente</span>
    </div>
    <div style="height:185px;position:relative;"><canvas id="radarChart"></canvas></div>
    <div style="display:flex;flex-wrap:wrap;gap:5px 10px;margin-top:7px;">
      <?php foreach([['Urgence','#ef4444'],['Détresse','#f59e0b'],['Anxiété','#8b5cf6'],['Résilience','#10b981'],['Social','#38bdf8'],['Stabilité','#6366f1']] as [$l,$c]): ?>
      <div style="display:flex;align-items:center;gap:3px;">
        <div style="width:5px;height:5px;border-radius:50%;background:<?=$c?>;flex-shrink:0;"></div>
        <span style="font-size:7px;font-weight:700;color:var(--su);text-transform:uppercase;letter-spacing:.07em;"><?=$l?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="rd"></div>

  <!-- TIMELINE -->
  <div style="padding:0 13px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
      <p class="lbl" style="color:var(--ac2);">Timeline émotionnelle</p>
      <div style="display:flex;gap:7px;">
        <?php foreach([['#ef4444','Risque'],['#10b981','Résil.'],['#f59e0b','Détr.'],['#8b5cf6','Anx.']] as [$c,$l]): ?>
        <div style="display:flex;align-items:center;gap:2px;">
          <span style="width:9px;height:1.5px;background:<?=$c?>;display:inline-block;border-radius:1px;"></span>
          <span style="font-size:7px;font-weight:700;color:var(--su);text-transform:uppercase;"><?=$l?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="height:92px;position:relative;"><canvas id="tlChart"></canvas></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:5px;">
      <span id="tllbl" style="font-size:8.5px;font-weight:700;color:var(--su);">—</span>
      <span id="tlchip" class="chip cs" style="font-size:7px;">Stable</span>
    </div>
  </div>

  <div class="rd"></div>

  <!-- ANALYSE SÉMANTIQUE -->
  <div style="padding:0 13px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;">
      <div style="display:flex;align-items:center;gap:5px;">
        <span style="width:4px;height:4px;border-radius:50%;background:var(--ac);" class="pulse"></span>
        <p class="lbl" style="color:var(--ac2);">IA · Analyse sémantique</p>
      </div>
    </div>
    <div id="ins" style="max-height:200px;overflow-y:auto;">
      <div id="insph" style="padding:14px 0;text-align:center;opacity:.18;">
        <p class="lbl" style="line-height:2.2;">L'IA générera des insights<br>au fil de la transcription</p>
      </div>
    </div>
  </div>

  <div class="rd"></div>

  <!-- DYNAMIQUE ÉMOTIONNELLE -->
  <div style="padding:0 13px;">
    <p class="lbl" style="color:var(--ac2);margin-bottom:6px;">Dynamique émotionnelle</p>
    <div style="height:58px;position:relative;"><canvas id="emoChart"></canvas></div>
    <div style="display:flex;justify-content:space-between;margin-top:3px;">
      <span style="font-size:7px;color:var(--su);font-weight:600;">Négatif ←</span>
      <span style="font-size:7px;color:var(--su);font-weight:600;">→ Positif</span>
    </div>
  </div>

</div>

</div><!-- /g3 -->
</div><!-- /app -->

<!-- OVERLAY -->
<div id="ov" class="ov" onclick="if(event.target===this)ovC()">
  <div class="ovb">
    <p id="ovmsg" style="font-size:13px;color:var(--tx2);font-weight:500;margin-bottom:20px;line-height:1.65;"></p>
    <div style="display:flex;gap:8px;">
      <button id="ovyes" class="btn bok" style="flex:1;">Confirmer</button>
      <button onclick="ovC()" class="btn bg" style="flex:1;">Annuler</button>
    </div>
  </div>
</div>

<script>
var CSRF  = <?= json_encode($csrf) ?>;
var PAT   = <?= json_encode($patient_selected) ?>;
var SESN  = <?= $session_num ?>;
var DATED = <?= json_encode($appt_date) ?>;
var DR    = <?= json_encode($nom_docteur) ?>;
var HIST  = <?= json_encode($history_for_ai) ?>;

var micOn = false, recog = null, timerIv = null, secs = 0;
var lastRpt = null, lastAT = '';
var emoC, radC, tlC, lgC;
var emoP = [0], tR = [], tRs = [], tD = [], tA = [];
var rD = {u:0,d:0,a:0,r:0,s:0,st:100};

function ovO(msg, cb) {
  document.getElementById('ovmsg').innerHTML = msg;
  document.getElementById('ov').classList.add('open');
  document.getElementById('ovyes').onclick = function(){ ovC(); cb(); };
}
function ovC(){ document.getElementById('ov').classList.remove('open'); }

function ntf(id, msg, type, ms) {
  if(ms === undefined) ms = 4500;
  var z = document.getElementById(id); if(!z) return;
  var ic = {ok:'✓', er:'✕', wa:'⚠', in:'ℹ'};
  z.innerHTML = '<div class="toast t'+type+'"><span>'+(ic[type]||'ℹ')+'</span><span>'+msg+'</span></div>';
  if(ms > 0) setTimeout(function(){ z.innerHTML=''; }, ms);
}

// ── LEXIQUE CLINIQUE (identique) ─────────────────────────────────────────────
var LEX = {
  u:   { p:40,  w:["suicide","suicider","suicidaire","me suicider","se suicider","mourir","veux mourir","envie de mourir","penser à mourir","mort","me tuer","se tuer","en finir","mettre fin","mettre fin à ma vie","plus envie de vivre","aucun espoir","plus d'espoir","sans espoir","tout est fini","adieu","je disparais","disparaître définitivement","ne plus exister","personne ne me manquera","tout le monde sera mieux sans moi","marre de vivre","j'ai décidé","j'ai un plan","j'ai tout prévu","pendre","me pendre","overdose","avaler des médicaments","sauter","me jeter","défenestrer","couteau","me poignarder","noyade","me noyer","tentative de suicide","j'ai déjà essayé","TS","passage à l'acte","lettre d'adieu","j'ai réglé mes affaires","plus aucun sens","la vie n'a plus de sens","à quoi ça sert","pourquoi continuer","je suis un fardeau","mieux sans moi","condamné","condamnée","impossible de guérir"] },
  am:  { p:28,  w:["automutilation","je me blesse","je me fais du mal","me couper","je me coupe","scarification","cicatrices","cicatrices cachées","brûlures","je me brûle","frapper un mur","me frapper","je cache mes bras","manches longues","lames","je garde des lames","pour ressentir quelque chose","pour ne plus ressentir","ça soulage","seul moyen que j'ai trouvé"] },
  ps:  { p:30,  w:["voix","j'entends des voix","les voix me disent","hallucinations","j'hallucine","délire","délires","idées délirantes","persécution","on me persécute","ils me surveillent","on me surveille","complot","espionné","espionnée","on lit dans mes pensées","mission divine","Dieu m'a choisi","schizophrénie","schizophrène","psychose","épisode psychotique","pensée désorganisée"] },
  d:   { p:15,  w:["triste","tristesse","pleurer","je pleure","larmes","déprimé","déprimée","dépression","état dépressif","désespoir","désespéré","désespérée","vide","vide intérieur","rien ne compte","tout est noir","anesthésié","engourdi","ne ressens plus rien","anhédonie","souffrance","je souffre","insupportable","brisé","brisée","effondré","effondrée","honte","coupable","culpabilité","je me déteste","je me hais","nul","nulle","inutile","échec","sans valeur","seul","toute seule","solitude","isolé","isolée","abandonné","abandonnée","épuisé","épuisée","deuil","perte","rupture","séparation","divorce","trahison"] },
  tr:  { p:22,  w:["violence","frappé","frappée","battu","battue","maltraitance","agressé","agressée","viol","violé","violée","agression sexuelle","abus sexuel","inceste","humilié","humiliée","harcèlement","manipulation","manipulé","manipulée","emprise","gaslighting","traumatisme","traumatisé","traumatisée","PTSD","ESPT","flashback","flashbacks","reviviscence","je revis","triggers","déclencheurs","enfance difficile","enfance traumatisante","parents toxiques"] },
  a:   { p:8,   w:["anxieux","anxieuse","anxiété","angoisse","angoissé","stress","stressé","stressée","peur","j'ai peur","terreur","inquiet","inquiète","inquiétude","hypervigilance","panique","attaque de panique","paniquer","hyperventilation","j'étouffe","je suffoque","oppression","palpitations","trembler","je tremble","vertiges","nausée","migraine","insomnie","je ne dors pas","je ne dors plus","cauchemars","ruminer","je rumine","ruminations","pensées intrusives","obsession","TOC","trouble obsessionnel","compulsion","éviter","j'évite","phobie sociale","peur du jugement"] },
  di:  { p:18,  w:["dissociation","dépersonnalisation","je ne me sens plus moi-même","déréalisation","tout semble irréel","comme dans un rêve","dans un film","je me regarde de l'extérieur","hors de mon corps","je flotte","je me sens détaché","je me sens détachée","je ne suis plus là","perte d'identité","amnésie","trous de mémoire","en pilote automatique"] },
  ma:  { p:14,  w:["manie","maniaque","épisode maniaque","hypomanie","bipolaire","trouble bipolaire","euphorie","euphorique","je me sens invincible","trop d'énergie","fuite des idées","impulsivité","impulsif","impulsive","dépenses excessives","comportements à risque","grandiosité","irritabilité","sautes d'humeur","phases hautes","phases basses"] },
  ad:  { p:12,  w:["alcool","je bois","boire","ivre","alcoolique","dépendance à l'alcool","drogue","drogué","droguée","cannabis","joint","cocaïne","coke","héroïne","opioïdes","ecstasy","MDMA","amphétamines","crack","je consomme","je me drogue","pour oublier","pour ne plus ressentir","benzodiazépines","xanax","valium","somnifères","antidouleurs","tramadol","jeux d'argent","casino","addiction aux jeux","rechute","j'ai rechuté","sevrage"] },
  ta:  { p:14,  w:["anorexie","anorexique","je ne mange pas","je ne mange plus","restriction alimentaire","peur de manger","peur de grossir","je saute des repas","je jeûne","contrôler ce que je mange","boulimie","boulimique","crises de boulimie","manger compulsivement","je mange sans m'arrêter","hyperphagie","vomir","je me fais vomir","laxatifs","image du corps","je me trouve gros","je me trouve grosse","dégoût de mon corps","obsession du poids","je me pèse tout le temps"] },
  so2: { p:7,   w:["douleurs chroniques","fibromyalgie","j'ai mal partout","psychosomatique","symptômes inexpliqués","les médecins ne trouvent rien","syndrome de fatigue chronique"] },
  re:  { p:10,  w:["conflit","disputes","on se dispute tout le temps","mésentente","séparation","divorce","famille toxique","relations toxiques","dépendance affective","peur d'être abandonné","peur d'être abandonnée","jalousie","jaloux","jalouse","je sabote mes relations"] },
  po:  { p:-12, w:["espoir","j'espère","optimiste","confiant","confiante","projets","j'ai des projets","je vais m'en sortir","mieux","je vais mieux","ça va mieux","heureux","heureuse","joie","bonheur","content","contente","serein","sereine","apaisé","apaisée","calme","soulagé","soulagée","bien dans ma peau","je m'accepte","énergie","j'ai de l'énergie","motivé","motivée","plaisir","sourire","je souris","fierté","j'ai accompli","j'ai réussi","mes enfants","je veux guérir","changer","je veux changer","thérapie","ça m'aide","ça fait du bien","prise de conscience","j'ai compris","lâcher prise","je lâche prise","mindfulness","méditation","je prends soin de moi"] },
  so:  { p:-7,  w:["ami","amis","amie","entourage","entouré","entourée","soutien","soutenu","soutenue","je me sens soutenu","je me sens soutenue","accompagné","accompagnée","pas seul","pas seule","parler à quelqu'un","j'ai quelqu'un à qui parler","connecté","connectée","relation solide","relation saine","sortir","je sors","rencontrer","j'ai rencontré","groupe de soutien","groupe de parole","aide professionnelle","je vois un psy","je suis suivi","je suis suivie","psychiatre","psychologue","thérapeute","je consulte"] }
};

var NEG = ["ne","n'","pas","plus","jamais","aucun","aucune","sans","ni","non","nullement","guère"];
var INT = ["très","vraiment","tellement","trop","extrêmement","profondément","absolument","terriblement","infiniment","énormément"];
var ATT = ["un peu","légèrement","parfois","peut-être","vaguement","de temps en temps","par moments","rarement"];

function analyze(text) {
  var t = text.toLowerCase();
  var tok = t.split(/\s+/);
  var sc = {u:0,d:0,a:0,tr:0,di:0,ma:0,ps:0,ad:0,ta:0,am:0,so2:0,re:0,po:0,so:0};
  Object.keys(LEX).forEach(function(cat){
    LEX[cat].w.forEach(function(mot){
      var isPhrase = mot.indexOf(' ') !== -1;
      if(isPhrase){
        var si=0, idx;
        while((idx=t.indexOf(mot,si)) !== -1){
          var ap = t.slice(0,idx).split(/\s+/).length;
          var wb = tok.slice(Math.max(0,ap-6),ap);
          var neg = wb.some(function(x){ return NEG.indexOf(x.replace(/[',\.!?;:]/g,'')) !== -1; });
          var ctx = wb.join(' ');
          var mul = INT.some(function(m){ return ctx.indexOf(m) !== -1; }) ? 1.7 : (ATT.some(function(m){ return ctx.indexOf(m) !== -1; }) ? 0.45 : 1);
          if(!neg) sc[cat] += LEX[cat].p * mul;
          else if(cat==='po'||cat==='so') sc[cat] -= LEX[cat].p*0.4;
          si = idx + mot.length;
        }
      } else {
        tok.forEach(function(tk,i){
          var match = (tk===mot)||(tk.indexOf(mot)!==-1 && mot.length>4);
          if(match){
            var wb = tok.slice(Math.max(0,i-6),i);
            var neg = wb.some(function(x){ return NEG.indexOf(x.replace(/[',\.!?;:]/g,'')) !== -1; });
            var ctx = wb.join(' ');
            var mul = INT.some(function(m){ return ctx.indexOf(m) !== -1; }) ? 1.7 : (ATT.some(function(m){ return ctx.indexOf(m) !== -1; }) ? 0.45 : 1);
            if(!neg) sc[cat] += LEX[cat].p * mul;
            else if(cat==='po'||cat==='so') sc[cat] -= LEX[cat].p*0.4;
          }
        });
      }
    });
  });
  var danger = sc.u*1.0 + sc.am*0.85 + sc.ps*0.80 + sc.d*0.55 + sc.tr*0.60 + sc.di*0.50 + sc.a*0.35 + sc.ma*0.40 + sc.ad*0.30 + sc.ta*0.35 + sc.so2*0.20 + sc.re*0.25;
  var prot = Math.abs(sc.po)*0.9 + Math.abs(sc.so)*0.6;
  var risk = Math.round(Math.max(0, Math.min(100, danger - prot)));
  return { sc:sc, risk:risk };
}

function onTyping(val) {
  var words = val.trim().split(/\s+/).filter(function(w){ return w.length>0; });
  var wc = words.length;
  document.getElementById('wc').textContent = wc + (wc>1?' mots':' mot');
  var res = analyze(val);
  var sc = res.sc, risk = res.risk;
  updRadar(sc, risk);
  updTimeline(risk, sc);
  var neg=sc.u+sc.d+sc.a, pos=Math.abs(sc.po)+Math.abs(sc.so), sum=neg+pos;
  emoP.push(sum>0 ? Math.max(-1,Math.min(1,(pos-neg)/sum)) : 0);
  if(emoP.length>60) emoP.shift();
  drawEmo();
  if(wc > 10) document.getElementById('btngen').disabled = false;
  if(document.getElementById('autoai').checked && wc>0 && wc%35===0 && val!==lastAT){
    lastAT = val; autoAI(val, sc, risk);
  }
}

function updRadar(sc, risk){
  rD = { u:Math.min(100,Math.round(Math.max(0,sc.u))), d:Math.min(100,Math.round(Math.max(0,sc.d))), a:Math.min(100,Math.round(Math.max(0,sc.a))), r:Math.min(100,Math.round(Math.abs(sc.po))), s:Math.min(100,Math.round(Math.abs(sc.so))), st:Math.max(0,100-risk) };
  drawRadar();
  document.getElementById('rchip').textContent='Actif';
  document.getElementById('rchip').className='chip ck';
}
function drawRadar(){
  var ctx = document.getElementById('radarChart').getContext('2d');
  if(radC) radC.destroy();
  var vals=[rD.u,rD.d,rD.a,rD.r,rD.s,rD.st];
  var pc=['#ef4444','#f59e0b','#8b5cf6','#10b981','#38bdf8','#6366f1'];
  var danger = rD.u>40||rD.d>50;
  radC = new Chart(ctx,{type:'radar',
    data:{labels:['Urgence','Détresse','Anxiété','Résilience','Social','Stabilité'],datasets:[
      {data:[100,100,100,100,100,100],borderColor:'rgba(255,255,255,.02)',backgroundColor:'transparent',borderWidth:1,pointRadius:0,order:2},
      {data:vals,borderColor:danger?'rgba(239,68,68,.65)':'rgba(99,102,241,.6)',backgroundColor:danger?'rgba(239,68,68,.06)':'rgba(99,102,241,.08)',borderWidth:1.5,pointBackgroundColor:pc,pointBorderColor:'rgba(7,9,15,.8)',pointBorderWidth:1.5,pointRadius:4,pointHoverRadius:6,order:1}
    ]},
    options:{responsive:true,maintainAspectRatio:false,animation:{duration:500,easing:'easeInOutCubic'},
      plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(7,9,15,.97)',borderColor:'rgba(255,255,255,.08)',borderWidth:1,padding:9,cornerRadius:9,titleFont:{family:'Plus Jakarta Sans',size:8,weight:'800'},bodyFont:{family:'Plus Jakarta Sans',size:9.5},callbacks:{title:function(i){return[i[0].label.toUpperCase()];},label:function(i){return i.datasetIndex===1?' '+i.raw+' / 100':null;}}}},
      scales:{r:{min:0,max:100,ticks:{display:false},grid:{color:'rgba(255,255,255,.035)'},angleLines:{color:'rgba(255,255,255,.04)'},pointLabels:{color:'#374151',font:{family:'Plus Jakarta Sans',size:7.5,weight:'700'},padding:4}}}
    }
  });
}

function updTimeline(risk, sc){
  tR.push(risk); tRs.push(Math.round(Math.abs(sc.po)+Math.abs(sc.so))); tD.push(Math.round(Math.max(0,sc.d))); tA.push(Math.round(Math.max(0,sc.a)));
  var M=60; if(tR.length>M){tR.shift();tRs.shift();tD.shift();tA.shift();}
  drawTL();
  if(tR.length>=4){
    var n=tR.length;
    var diff=((tR[n-1]+tR[n-2])/2)-((tR[n-3]+tR[n-4])/2);
    var tc=document.getElementById('tlchip'), tl=document.getElementById('tllbl');
    if(diff>8){tc.textContent='↑ Tension croissante';tc.className='chip ce';tl.style.color='#f87171';tl.textContent='Montée du risque';}
    else if(diff<-8){tc.textContent='↓ Apaisement';tc.className='chip ck';tl.style.color='#34d399';tl.textContent='Stabilisation progressive';}
    else{tc.textContent='→ Stable';tc.className='chip cs';tl.style.color='#4b5563';tl.textContent='Régularité du discours';}
  }
}
function drawTL(){
  var ctx=document.getElementById('tlChart').getContext('2d');
  if(tlC) tlC.destroy();
  function g(c,c1,c2){var gr=c.chart.ctx.createLinearGradient(0,0,0,92);gr.addColorStop(0,c1);gr.addColorStop(1,c2);return gr;}
  tlC=new Chart(ctx,{type:'line',
    data:{labels:tR.map(function(_,i){return i;}),datasets:[
      {label:'Risque',data:tR,borderColor:'#ef4444',borderWidth:2,pointRadius:0,fill:true,tension:.42,backgroundColor:function(c){return g(c,'rgba(239,68,68,.18)','rgba(239,68,68,0)');}},
      {label:'Résilience',data:tRs,borderColor:'#10b981',borderWidth:1.5,pointRadius:0,fill:false,tension:.42},
      {label:'Détresse',data:tD,borderColor:'#f59e0b',borderWidth:1.5,pointRadius:0,fill:false,tension:.42,borderDash:[3,3]},
      {label:'Anxiété',data:tA,borderColor:'#8b5cf6',borderWidth:1.5,pointRadius:0,fill:false,tension:.42,borderDash:[2,4]}
    ]},
    options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},
      plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(7,9,15,.97)',borderColor:'rgba(255,255,255,.07)',borderWidth:1,padding:8,cornerRadius:8,titleFont:{family:'Plus Jakarta Sans',size:8,weight:'800'},bodyFont:{family:'Plus Jakarta Sans',size:8.5},callbacks:{title:function(i){return 'T+'+i[0].label;},label:function(i){return ' '+i.dataset.label;},labelColor:function(i){var c=['#ef4444','#10b981','#f59e0b','#8b5cf6'];return{borderColor:'transparent',backgroundColor:c[i.datasetIndex]||'#6366f1',borderRadius:2};}}}},
      scales:{x:{display:false},y:{min:0,max:100,ticks:{display:false},grid:{color:'rgba(255,255,255,.02)',drawBorder:false},border:{display:false}}}
    }
  });
}
function drawEmo(){
  var ctx=document.getElementById('emoChart').getContext('2d');
  if(emoC) emoC.destroy();
  var last=emoP[emoP.length-1]||0;
  var lc=last>0.1?'#10b981':(last<-0.2?'#ef4444':'#6366f1');
  var fc=last>0.1?'rgba(16,185,129,.18)':(last<-0.2?'rgba(239,68,68,.13)':'rgba(99,102,241,.16)');
  emoC=new Chart(ctx,{type:'line',
    data:{labels:emoP.map(function(_,i){return i;}),datasets:[{data:emoP,borderColor:lc,borderWidth:1.5,pointRadius:0,fill:true,tension:.4,backgroundColor:function(c){var g=c.chart.ctx.createLinearGradient(0,0,0,58);g.addColorStop(0,fc);g.addColorStop(1,'rgba(0,0,0,0)');return g;}}]},
    options:{responsive:true,maintainAspectRatio:false,animation:false,plugins:{legend:{display:false}},scales:{x:{display:false},y:{min:-1.2,max:1.2,ticks:{display:false},grid:{color:'rgba(255,255,255,.02)',drawBorder:false},border:{display:false}}}}
  });
}
function drawLg(){
  var el=document.getElementById('lgChart'); if(!el) return;
  var ctx=el.getContext('2d');
  if(lgC) lgC.destroy();
  var n=<?= max(1,count($prev_consults)) ?>;
  var labels=[],vals=[];
  for(var i=0;i<n;i++){labels.push('S'+(i+1));vals.push(Math.round(50+Math.sin(i*.9)*14));}
  lgC=new Chart(ctx,{type:'line',
    data:{labels:labels,datasets:[{data:vals,borderColor:'var(--ac)',borderWidth:1.5,pointBackgroundColor:'var(--ac)',pointBorderColor:'rgba(7,9,15,.8)',pointBorderWidth:1.5,pointRadius:3,pointHoverRadius:5,fill:true,backgroundColor:function(c){var g=c.chart.ctx.createLinearGradient(0,0,0,44);g.addColorStop(0,'rgba(99,102,241,.18)');g.addColorStop(1,'rgba(99,102,241,0)');return g;},tension:.4}]},
    options:{responsive:true,maintainAspectRatio:false,animation:{duration:600,easing:'easeInOutQuart'},plugins:{legend:{display:false}},scales:{x:{display:true,ticks:{color:'#374151',font:{family:'Plus Jakarta Sans',size:7,weight:'700'},maxRotation:0},grid:{display:false},border:{display:false}},y:{display:false,min:20,max:100}}}
  });
}

var wvIv=null;
function startWave(){
  var bars=document.querySelectorAll('#wv div');
  wvIv=setInterval(function(){ bars.forEach(function(b){ b.style.height=(3+Math.random()*15)+'px'; b.style.background='hsl('+(230+Math.random()*20)+',65%,'+(48+Math.random()*22)+'%)'; }); },110);
}
function stopWave(){
  clearInterval(wvIv);
  document.querySelectorAll('#wv div').forEach(function(b){b.style.height='3px';b.style.background='var(--su)';});
}

var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
function makeRecog(){
  if(!SR) return null;
  var r=new SR();
  r.lang='fr-FR'; r.continuous=true; r.interimResults=true; r.maxAlternatives=1;
  r.onstart=function(){
    micOn=true;
    document.getElementById('micbtn').className='mic mic-live';
    document.getElementById('micsvg').setAttribute('stroke','#fff');
    document.getElementById('miclbl').textContent='● CAPTURE ACTIVE — Cliquez pour arrêter';
    document.getElementById('miclbl').style.color='#ef4444';
    document.getElementById('recdot').style.background='#dc2626';
    document.getElementById('recdot').classList.add('pulse');
    document.getElementById('stop').className='chip ce';
    document.getElementById('stop').textContent='● Enregistrement';
    startWave();
    clearInterval(timerIv);
    timerIv=setInterval(function(){ secs++; var m=Math.floor(secs/60).toString().padStart(2,'0'); var s=(secs%60).toString().padStart(2,'0'); document.getElementById('timer').textContent=m+':'+s; document.getElementById('abar').style.width=(10+Math.random()*55)+'%'; },1000);
  };
  r.onresult=function(e){
    var final='';
    for(var i=e.resultIndex;i<e.results.length;i++){ if(e.results[i].isFinal) final+=e.results[i][0].transcript+' '; }
    if(final){ var el=document.getElementById('tr'); el.value+=final; el.scrollTop=el.scrollHeight; onTyping(el.value); }
  };
  r.onerror=function(e){ if(e.error==='no-speech') return; if(e.error==='not-allowed'||e.error==='permission-denied'){ ntf('ntr','Accès micro refusé.','er'); setMicStopped(); return; } };
  r.onend=function(){ if(micOn){ try{ recog.start(); } catch(ex){ setMicStopped(); } } };
  return r;
}

if(SR){ recog=makeRecog(); }
else {
  setTimeout(function(){
    var sttw=document.getElementById('sttw'); if(sttw) sttw.style.display='block';
    var mb=document.getElementById('micbtn'); if(mb){mb.disabled=true;mb.style.opacity='.25';mb.style.cursor='not-allowed';}
  }, 500);
}

function toggleMic(){
  if(!SR){ ntf('ntr','Microphone non supporté (utilisez Chrome).','er'); return; }
  if(micOn){ micOn=false; try{ recog.stop(); }catch(e){} setMicStopped(); }
  else { try{ recog.abort(); }catch(e){} recog=makeRecog(); try{ recog.start(); } catch(e){ ntf('ntr','Impossible de démarrer : '+e.message,'er'); } }
}
function setMicStopped(){
  micOn=false; clearInterval(timerIv); stopWave();
  document.getElementById('abar').style.width='0%';
  document.getElementById('micbtn').className='mic mic-done';
  document.getElementById('micsvg').setAttribute('stroke','#10b981');
  document.getElementById('miclbl').textContent='Transcription complète — Cliquez pour reprendre';
  document.getElementById('miclbl').style.color='#10b981';
  document.getElementById('recdot').classList.remove('pulse');
  document.getElementById('recdot').style.background='#10b981';
  document.getElementById('stop').className='chip ck';
  document.getElementById('stop').textContent='Terminé';
  document.getElementById('btngen').disabled=false;
}

function clearTr(){
  ovO('Effacer toute la transcription ?',function(){
    document.getElementById('tr').value='';
    document.getElementById('wc').textContent='0 mot';
    emoP=[0];tR=[];tRs=[];tD=[];tA=[];rD={u:0,d:0,a:0,r:0,s:0,st:100};
    drawEmo();drawRadar();drawTL();
    ntf('ntr','Transcription effacée.','in',2500);
  });
}

function addFeed(type, title, body){
  var cm={info:['rgba(99,102,241,.05)','rgba(99,102,241,.35)','var(--ac2)'],warn:['rgba(217,119,6,.05)','rgba(217,119,6,.35)','#fbbf24'],danger:['rgba(220,38,38,.08)','rgba(220,38,38,.5)','#f87171'],ok:['rgba(5,150,105,.05)','rgba(5,150,105,.3)','#34d399'],q:['rgba(14,165,233,.05)','rgba(14,165,233,.3)','#7dd3fc']};
  var c=cm[type]||cm.info;
  var ph=document.getElementById('feedph'); if(ph) ph.remove();
  var el=document.createElement('div');
  el.className='fi';
  el.style.cssText='background:'+c[0]+';border-left-color:'+c[1]+';';
  el.innerHTML='<p style="font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.13em;color:'+c[2]+';margin-bottom:2px;">'+title+'</p><p style="font-size:10px;color:var(--tx3);line-height:1.55;">'+body+'</p>';
  var feed=document.getElementById('feed');
  feed.insertBefore(el,feed.firstChild);
  while(feed.children.length>14) feed.removeChild(feed.lastChild);
  document.getElementById('btnask').disabled=false;
  var now=new Date(); var ts=now.getHours().toString().padStart(2,'0')+':'+now.getMinutes().toString().padStart(2,'0')+':'+now.getSeconds().toString().padStart(2,'0');
  var iph=document.getElementById('insph'); if(iph) iph.remove();
  var ins=document.createElement('div');
  ins.className='fi';
  ins.style.cssText='background:'+c[0]+';border-left-color:'+c[1]+';margin-bottom:5px;';
  ins.innerHTML='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;"><p style="font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.13em;color:'+c[2]+';">'+title+'</p><span style="font-size:7px;font-weight:700;color:var(--tx3);font-variant-numeric:tabular-nums;">'+ts+'</span></div><p style="font-size:10px;color:var(--tx2);line-height:1.6;">'+body+'</p>';
  var wrap=document.getElementById('ins');
  wrap.insertBefore(ins,wrap.firstChild);
  while(wrap.children.length>12) wrap.removeChild(wrap.lastChild);
}

async function callAI(prompt, max){
  max=max||1200;
  var res=await fetch('proxy_ia.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({prompt:prompt,max_tokens:max,csrf_token:CSRF})});
  if(!res.ok) throw new Error('HTTP '+res.status);
  var d=await res.json();
  if(d.error) throw new Error(d.error.message||'Erreur API');
  return d.choices&&d.choices[0]&&d.choices[0].message ? d.choices[0].message.content : '';
}

async function autoAI(text, sc, risk){
  var th=document.getElementById('think'); if(th) th.style.display='flex';
  var hc=HIST.length?'\nHistorique:\n'+HIST.map(function(h){return h.date+'('+h.duree+'min): '+h.resume;}).join('\n'):'';
  var p='Tu es psychologue clinicien superviseur. Analyse EN DIRECT ce verbatim partiel.\nPatient: '+PAT+' | Séance n°'+SESN+'\nRisque: '+risk+'% | Urgence:'+Math.round(sc.u)+' | Détresse:'+Math.round(sc.d)+' | Anxiété:'+Math.round(sc.a)+hc+'\nVerbatim (fin): "'+text.slice(-700)+'"\nJSON uniquement sans markdown:\n{"alerte":null,"observation":"1 phrase clinique","theme":"1 thème à explorer","question":"1 question pour le patient","hypothese":null,"code_cim":"code CIM-11 ou null"}';
  try{
    var raw=await callAI(p,450);
    var ai; try{ ai=JSON.parse(raw.replace(/```json\n?|\n?```/g,'').trim()); }catch(e){ return; }
    if(ai.alerte)      addFeed('danger','⚠ Alerte clinique',ai.alerte);
    if(ai.observation) addFeed('info','Observation',ai.observation);
    if(ai.theme)       addFeed('ok','→ Thème à explorer',ai.theme);
    if(ai.question)    addFeed('q','? Question patient',ai.question);
    if(ai.hypothese){
      var b=document.getElementById('diagb');
      var dp=document.getElementById('diagph'); if(dp) dp.remove();
      var d=document.createElement('div'); d.className='dp fu';
      d.innerHTML=(ai.code_cim?'<span class="dc">'+ai.code_cim+'</span>':'')+'<p style="font-size:10px;color:#93c5fd;line-height:1.55;">'+ai.hypothese+'</p>';
      b.insertBefore(d,b.firstChild);
      if(b.children.length>5) b.removeChild(b.lastChild);
    }
  }catch(e){ console.warn('autoAI:',e); }
  finally{ if(th) th.style.display='none'; }
}

function toggleAsk(){
  var box=document.getElementById('askbox');
  box.style.display = box.style.display==='none'?'block':'none';
  if(box.style.display==='block') document.getElementById('askin').focus();
}
async function sendQ(){
  var q=document.getElementById('askin').value.trim(); if(!q) return;
  var text=document.getElementById('tr').value.trim();
  var resp=document.getElementById('askr');
  resp.innerHTML='<div class="dots" style="padding:6px 0;"><span></span><span></span><span></span></div>';
  var p='Psychologue clinicien superviseur. Réponds en 4-5 phrases cliniques précises.\nPatient: '+PAT+' | Séance n°'+SESN+'\nVerbatim: "'+text.slice(-500)+'"\nQUESTION: "'+q+'"';
  try{
    var raw=await callAI(p,500);
    resp.innerHTML='<div class="card2 fu" style="margin-top:5px;"><p class="lbl" style="color:var(--ac2);margin-bottom:4px;">Réponse IA</p><p style="font-size:10px;color:var(--tx2);line-height:1.6;">'+raw+'</p></div>';
    addFeed('info','Q: '+q.slice(0,40)+'…',raw.slice(0,110)+'…');
  }catch(e){ resp.innerHTML='<p style="font-size:10px;color:#f87171;margin-top:4px;">Erreur — Réessayez.</p>'; }
}

// ══════════════════════════════════════════════════════════════════════════════
// GÉNÉRATION DU RAPPORT — CR médical réel + résumé simple
// ══════════════════════════════════════════════════════════════════════════════
async function genReport(){
  var text=document.getElementById('tr').value.trim();
  if(text.length<15){ ntf('ntr','Volume insuffisant pour la synthèse.','wa'); return; }

  var res=analyze(text);
  var sc=res.sc, risk=res.risk;

  document.getElementById('btngen').disabled=true;
  var body=document.getElementById('rpbody');
  body.innerHTML='<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:55px 20px;opacity:.45;">'
    +'<div class="dots" style="margin-bottom:12px;"><span></span><span></span><span></span></div>'
    +'<p class="lbl" style="color:var(--ac2);text-align:center;line-height:2;">Rédaction du compte-rendu clinique…</p></div>';

  var notes=document.getElementById('notes').value;
  var hc=HIST.length ? HIST.map(function(h){ return 'Séance '+h.date+' ('+h.duree+'min) : '+h.resume; }).join('\n') : 'Première consultation';
  var dureeMin = Math.floor(secs/60) || '—';

  // Prompt recentré : VRAI compte-rendu médical, pas une analyse
  var prompt = 'Tu es psychologue clinicien senior. Rédige un COMPTE-RENDU DE CONSULTATION PSYCHOLOGIQUE complet, au format JSON strict.'
    +'\n\nINFORMATIONS ADMINISTRATIVES :'
    +'\n- Patient : '+PAT
    +'\n- Numéro de séance : '+SESN
    +'\n- Date : '+DATED
    +'\n- Praticien : Dr. '+DR
    +'\n- Durée : '+dureeMin+' minutes'
    +'\n\nHISTORIQUE :\n'+hc
    +'\n\nNOTES DU PRATICIEN :\n'+(notes||'Aucune note praticien.')
    +'\n\nVERBATIM COMPLET DE LA SÉANCE :\n"""'+text+'"""'
    +'\n\nINSTRUCTIONS :'
    +'\n- Rédige comme un vrai compte-rendu médical, en prose clinique professionnelle'
    +'\n- "resume_seance" : résumé clair et concis en 3-4 phrases simples (ce qui s\'est passé pendant la séance, les thèmes abordés, l\'état du patient)'
    +'\n- "motif_consultation" : motif de la séance en 1-2 phrases'
    +'\n- "contenu_seance" : description narrative de la séance (5-7 phrases)'
    +'\n- "observations_cliniques" : observations comportementales et affectives (3-5 phrases)'
    +'\n- "elements_importants" : éléments cliniques significatifs, signaux à surveiller (3-5 phrases)'
    +'\n- "plan_suite" : plan de suivi et prochaine séance (3-4 phrases)'
    +'\n- "hypotheses_diag" : liste de diagnostics différentiels avec codes CIM-11'
    +'\n- "objectifs_prochaine" : 2-3 objectifs concrets pour la prochaine séance'
    +'\n- "niveau_risque" : "faible" ou "modéré" ou "élevé" ou "critique"'
    +'\n\nJSON UNIQUEMENT (pas de markdown) :'
    +'{"resume_seance":"","motif_consultation":"","contenu_seance":"","observations_cliniques":"","elements_importants":"","plan_suite":"","hypotheses_diag":[],"objectifs_prochaine":[],"niveau_risque":"faible"}';

  try{
    var raw=await callAI(prompt, 3000);
    var ai;
    try{ ai=JSON.parse(raw.replace(/```json\n?|\n?```/g,'').trim()); }
    catch(e){ throw new Error('Format JSON invalide : '+e.message); }

    var snap = {
      risk:risk,
      tRisk:tR.slice(), tResil:tRs.slice(), tDetresse:tD.slice(), tAnxiete:tA.slice(),
      emoPoints:emoP.slice(),
      radarImg:(function(){try{return document.getElementById('radarChart').toDataURL('image/png');}catch(e){return null;}})(),
      timelineImg:(function(){try{return document.getElementById('tlChart').toDataURL('image/png');}catch(e){return null;}})()
    };

    // Stocker le résumé texte pur pour l'archivage
    lastRpt = {ai:ai, sc:sc, risk:risk, snap:snap, text:text, date:new Date().toLocaleDateString('fr-FR'), duree:dureeMin};
    // resume_str = version texte pour la DB
    lastRpt.resume_str = [
      'Séance n°'+SESN+' du '+DATED+' — Dr. '+DR,
      ai.resume_seance||'',
      ai.motif_consultation||'',
      'Risque : '+(ai.niveau_risque||'faible'),
      ai.plan_suite||''
    ].filter(Boolean).join('\n');

    document.getElementById('btnpdf').disabled=false;
    renderRpt(lastRpt);

    if(Array.isArray(ai.hypotheses_diag)){
      var diagb=document.getElementById('diagb');
      var diagph=document.getElementById('diagph'); if(diagph) diagph.remove();
      diagb.innerHTML='';
      ai.hypotheses_diag.forEach(function(h){
        var d=document.createElement('div'); d.className='dp fu';
        var m=h.match(/([A-Z][A-Z0-9]+\.?[0-9]*)/);
        d.innerHTML=(m?'<span class="dc">'+m[1]+'</span>':'')+'<p style="font-size:10px;color:#93c5fd;line-height:1.55;">'+h+'</p>';
        diagb.appendChild(d);
      });
    }
    addFeed('ok','Compte-rendu généré','Niveau de risque : '+(ai.niveau_risque||'—'));

  }catch(err){
    body.innerHTML='<div style="padding:40px 20px;text-align:center;">'
      +'<p style="color:#f87171;font-weight:700;font-size:12px;margin-bottom:6px;">Erreur de génération</p>'
      +'<p style="color:var(--tx3);font-size:10px;line-height:1.6;">'+err.message+'</p></div>';
  }finally{
    document.getElementById('btngen').disabled=false;
  }
}

// ══════════════════════════════════════════════════════════════════════════════
// RENDU DU RAPPORT
// ══════════════════════════════════════════════════════════════════════════════
var rptTlC=null;
function renderRpt(lr){
  if(!lr) return;
  var ai=lr.ai, sn=lr.snap||{};

  var risqColors={
    faible:  {bg:'#f0faf5',border:'#a3d9b8',dot:'#059669',lbl:'#065f46',text:'#1e4a2e'},
    modéré:  {bg:'#fffbf0',border:'#fcd34d',dot:'#d97706',lbl:'#92400e',text:'#4a3000'},
    élevé:   {bg:'#fff5f5',border:'#fca5a5',dot:'#dc2626',lbl:'#991b1b',text:'#4a0f0f'},
    critique:{bg:'#fff0f0',border:'#ff6b6b',dot:'#ff1a1a',lbl:'#7f1d1d',text:'#450a0a'}
  };
  var niv=(ai.niveau_risque||'faible').toLowerCase();
  var rc=risqColors[niv]||risqColors['faible'];

  var html='<div class="rpt-wrap">';

  // EN-TÊTE
  html+='<div class="rpt-header">'
    +'<div>'
      +'<p class="rpt-header-title">Compte-Rendu de Consultation Psychologique</p>'
      +'<p class="rpt-header-name">'+escH(PAT)+'</p>'
      +'<p class="rpt-header-meta">'
        +'Séance n°'+SESN+'&nbsp;&nbsp;·&nbsp;&nbsp;'+escH(DATED)+'&nbsp;&nbsp;·&nbsp;&nbsp;Dr. '+escH(DR)
        +(lr.duree&&lr.duree!=='—' ? '&nbsp;&nbsp;·&nbsp;&nbsp;Durée : '+lr.duree+' min' : '')
      +'</p>'
    +'</div>'
    +'<div class="rpt-header-right">'
      +'<span class="rpt-niv-badge">'+niv.charAt(0).toUpperCase()+niv.slice(1)+'</span>'
      +'<span style="font-family:\'Plus Jakarta Sans\',sans-serif;font-size:8px;font-weight:600;color:rgba(255,255,255,.4);">PsySpace Pro</span>'
    +'</div>'
  +'</div>';

  html+='<div class="rpt-body">';

  // INFOS RAPIDES
  html+='<div class="rpt-info-row">'
    +'<div class="rpt-info-cell"><div class="rpt-info-cell-label">Séance</div><div class="rpt-info-cell-val">N°'+SESN+'</div></div>'
    +'<div class="rpt-info-cell"><div class="rpt-info-cell-label">Date</div><div class="rpt-info-cell-val">'+escH(DATED)+'</div></div>'
    +'<div class="rpt-info-cell"><div class="rpt-info-cell-label">Durée</div><div class="rpt-info-cell-val">'+(lr.duree&&lr.duree!=='—'?lr.duree+' min':'—')+'</div></div>'
    +'<div class="rpt-info-cell" style="border-left:3px solid '+rc.dot+';"><div class="rpt-info-cell-label">Risque</div><div class="rpt-info-cell-val" style="color:'+rc.lbl+';">'+niv.charAt(0).toUpperCase()+niv.slice(1)+'</div></div>'
  +'</div>';

  // RÉSUMÉ SIMPLE
  if(ai.resume_seance){
    html+='<div class="rpt-resume-box">'
      +'<div class="rpt-resume-label">Résumé de la séance</div>'
      +'<p class="rpt-resume-text">'+escH(ai.resume_seance)+'</p>'
    +'</div>';
  }

  // BANDE RISQUE
  html+='<div class="rpt-risk-band" style="background:'+rc.bg+';border-color:'+rc.border+';">'
    +'<div class="rpt-risk-dot" style="background:'+rc.dot+';"></div>'
    +'<div>'
      +'<p class="rpt-risk-label" style="color:'+rc.lbl+';">Niveau de risque — '+niv.charAt(0).toUpperCase()+niv.slice(1)+'</p>'
      +(ai.motif_consultation?'<p style="font-family:\'Lora\',serif;font-size:12px;color:'+rc.text+';line-height:1.72;">'+escH(ai.motif_consultation)+'</p>':'')
    +'</div>'
  +'</div>';

  if(ai.contenu_seance) html+=rptSec('Contenu de la séance',ai.contenu_seance);

  if(ai.observations_cliniques) html+=rptSec('Observations cliniques',ai.observations_cliniques);

  if(ai.elements_importants){
    html+='<div class="rpt-section">'
      +'<div class="rpt-section-title" style="color:#b91c1c;">Éléments importants & points de vigilance</div>'
      +'<div class="rpt-vigilance">'
        +'<p class="rpt-vigilance-label">À surveiller</p>'
        +'<p class="rpt-vigilance-text">'+escH(ai.elements_importants)+'</p>'
      +'</div>'
    +'</div>';
  }

  if(Array.isArray(ai.hypotheses_diag)&&ai.hypotheses_diag.length){
    html+='<div class="rpt-section"><div class="rpt-section-title">Hypothèses diagnostiques · CIM-11</div>';
    ai.hypotheses_diag.forEach(function(h){
      var m=h.match(/([A-Z][A-Z0-9]+\.?[0-9A-Z]*)/);
      html+='<div class="rpt-diag-row">'+(m?'<span class="rpt-diag-code">'+m[1]+'</span>':'')+'<p class="rpt-diag-text">'+escH(h)+'</p></div>';
    });
    html+='</div>';
  }

  if(Array.isArray(ai.objectifs_prochaine)&&ai.objectifs_prochaine.length){
    html+='<div class="rpt-section"><div class="rpt-section-title">Objectifs · Prochaine séance</div>';
    ai.objectifs_prochaine.forEach(function(o,i){
      html+='<div class="rpt-obj-row"><span class="rpt-obj-num">'+(i+1)+'</span><p style="font-family:\'Lora\',serif;font-size:12px;color:var(--rp-tx2);line-height:1.65;">'+escH(o)+'</p></div>';
    });
    html+='</div>';
  }

  if(ai.plan_suite){
    html+='<div class="rpt-section"><div class="rpt-section-title">Plan de suivi</div>'
      +'<div class="rpt-plan"><p class="rpt-plan-text">'+escH(ai.plan_suite)+'</p></div>'
    +'</div>';
  }

  // TIMELINE dans le rapport
  if(sn.tRisk && sn.tRisk.length > 1){
    html+='<div class="rpt-section">'
      +'<div class="rpt-section-title">Évolution émotionnelle au cours de la séance</div>'
      +'<div style="height:68px;position:relative;border-radius:6px;overflow:hidden;background:var(--rp-bg2);border:1px solid var(--rp-border);padding:5px 8px;">'
        +'<canvas id="rpt-tl"></canvas>'
      +'</div>'
      +'<div style="display:flex;gap:14px;margin-top:5px;">';
    [['#ef4444','Risque'],['#10b981','Résilience'],['#f59e0b','Détresse'],['#8b5cf6','Anxiété']].forEach(function(p){
      html+='<div style="display:flex;align-items:center;gap:4px;">'
        +'<span style="width:12px;height:2px;background:'+p[0]+';display:inline-block;border-radius:1px;"></span>'
        +'<span style="font-family:\'Plus Jakarta Sans\',sans-serif;font-size:8px;font-weight:700;color:var(--rp-tx3);text-transform:uppercase;letter-spacing:.06em;">'+p[1]+'</span>'
      +'</div>';
    });
    html+='</div></div>';
  }

  html+='</div>';

  html+='<div class="rpt-footer">'
    +'<p class="rpt-footer-txt">PsySpace Pro · Confidentiel · Usage clinique exclusif</p>'
    +'<p class="rpt-footer-txt">Généré le '+new Date().toLocaleDateString('fr-FR')+'</p>'
  +'</div>';
  html+='</div>';

  document.getElementById('rpbody').innerHTML=html;
  setTimeout(function(){ renderRptCharts(sn); }, 80);
}

function rptSec(title, content){
  return '<div class="rpt-section"><div class="rpt-section-title">'+title+'</div><p class="rpt-prose">'+escH(content)+'</p></div>';
}
function escH(s){
  if(!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/\n/g,'<br>');
}
function renderRptCharts(sn){
  if(!sn) return;
  var tlEl=document.getElementById('rpt-tl');
  if(tlEl && sn.tRisk && sn.tRisk.length>1){
    if(rptTlC) rptTlC.destroy();
    var ctx=tlEl.getContext('2d');
    function grd(c,c1,c2){var g=c.chart.ctx.createLinearGradient(0,0,0,68);g.addColorStop(0,c1);g.addColorStop(1,c2);return g;}
    rptTlC=new Chart(ctx,{type:'line',
      data:{labels:sn.tRisk.map(function(_,i){return i;}),datasets:[
        {label:'Risque',data:sn.tRisk,borderColor:'#ef4444',borderWidth:1.5,pointRadius:0,fill:true,tension:.4,backgroundColor:function(c){return grd(c,'rgba(239,68,68,.12)','rgba(239,68,68,0)');}},
        {label:'Résilience',data:sn.tResil,borderColor:'#10b981',borderWidth:1.5,pointRadius:0,fill:false,tension:.4},
        {label:'Détresse',data:sn.tDetresse,borderColor:'#f59e0b',borderWidth:1.5,pointRadius:0,fill:false,tension:.4,borderDash:[3,3]},
        {label:'Anxiété',data:sn.tAnxiete,borderColor:'#8b5cf6',borderWidth:1.5,pointRadius:0,fill:false,tension:.4,borderDash:[2,4]}
      ]},
      options:{responsive:true,maintainAspectRatio:false,animation:{duration:500},plugins:{legend:{display:false}},scales:{x:{display:false},y:{min:0,max:100,ticks:{display:false},grid:{color:'rgba(0,0,0,.04)',drawBorder:false},border:{display:false}}}}
    });
  }
}

function loadPrev(txt){
  ovO('Charger ce résumé dans les notes cliniciennes ?', function(){
    var n=document.getElementById('notes');
    n.value=(n.value?n.value+'\n\n':'')+'[Séance précédente]\n'+txt.slice(0,500);
  });
}

function exportPDF(){
  if(!lastRpt){ ntf('narch',"Générez d'abord le bilan.",'wa'); return; }
  var j=window.jspdf.jsPDF;
  var doc=new j({unit:'mm',format:'a4'});
  var W=210, M=18, y=M;

  function ln(txt, o){
    o=o||{}; var sz=o.sz||9.5, b=o.b||false, it=o.it||false, c=o.c||[26,40,90], ind=o.in_||0, lh=o.lh||5.8;
    doc.setFontSize(sz); doc.setFont(b?'helvetica':'times', b?'bold':(it?'italic':'normal')); doc.setTextColor(c[0],c[1],c[2]);
    doc.splitTextToSize(String(txt),W-M*2-ind).forEach(function(l){ if(y>272){doc.addPage();y=M;} doc.text(l,M+ind,y); y+=lh; }); y+=1.2;
  }
  function hr(col){ col=col||[190,198,215]; doc.setDrawColor(col[0],col[1],col[2]);doc.setLineWidth(.1);doc.line(M,y,W-M,y);y+=3.5; }
  function sec(title, txt, titleCol){
    titleCol=titleCol||[30,64,175];
    doc.setFillColor(titleCol[0],titleCol[1],titleCol[2]);
    doc.rect(M,y-1.5,2.5,8,'F');
    ln(title.toUpperCase(),{sz:7,b:true,c:titleCol,in_:5,lh:4.5});
    if(txt) ln(txt,{sz:9.5,c:[51,65,85],in_:5,lh:5.5});
    y+=2;
  }

  // HEADER
  doc.setFillColor(30,64,175); doc.rect(0,0,W,34,'F');
  doc.setFontSize(15);doc.setFont('times','bold');doc.setTextColor(255,255,255);
  doc.text('Compte-Rendu de Consultation Psychologique',M,11);
  doc.setFontSize(9);doc.setFont('helvetica','normal');doc.setTextColor(180,200,240);
  doc.text('PsySpace Pro · Document confidentiel · Usage clinique exclusif',M,18);
  doc.setFontSize(9);doc.setTextColor(200,215,245);
  doc.text('Patient : '+PAT+'   ·   Séance n°'+SESN+'   ·   '+lastRpt.date+'   ·   Dr. '+DR,M,25);

  // Niveau de risque badge
  var niv=(lastRpt.ai.niveau_risque||'faible').toUpperCase();
  var nc=[30,64,175];
  if(niv==='MODÉRÉ') nc=[120,74,0]; else if(niv==='ÉLEVÉ'||niv==='CRITIQUE') nc=[160,30,30]; else if(niv==='FAIBLE') nc=[5,120,80];
  doc.setFillColor(255,255,255);doc.roundedRect(W-M-30,7,28,12,2,2,'F');
  doc.setFontSize(8);doc.setFont('helvetica','bold');doc.setTextColor(nc[0],nc[1],nc[2]);
  doc.text(niv,W-M-16,14.5,{align:'center'});
  y=42;

  var ai=lastRpt.ai;

  // Résumé simple
  if(ai.resume_seance){
    doc.setFillColor(240,245,255);doc.roundedRect(M,y-2,W-M*2,22,3,3,'F');
    doc.setDrawColor(180,200,240);doc.setLineWidth(.4);doc.roundedRect(M,y-2,W-M*2,22,3,3,'S');
    doc.setFontSize(7);doc.setFont('helvetica','bold');doc.setTextColor(30,64,175);
    doc.text('RÉSUMÉ DE LA SÉANCE',M+4,y+3); y+=6;
    ln(ai.resume_seance,{sz:10.5,c:[30,50,100],lh:5.5,it:true,in_:3}); y+=4;
  }

  // Infos en ligne
  doc.setFillColor(248,250,252);doc.rect(M,y-2,W-M*2,10,'F');
  doc.setFontSize(8.5);doc.setFont('helvetica','bold');doc.setTextColor(70,90,130);
  doc.text('Date : '+DATED,M+3,y+4);
  doc.text('Durée : '+(lastRpt.duree&&lastRpt.duree!=='—'?lastRpt.duree+' min':'—'),M+45,y+4);
  doc.text('Séance : N°'+SESN,M+80,y+4);
  doc.text('Praticien : Dr. '+DR,M+110,y+4);
  y+=14; hr();

  sec('Motif & contenu de la séance', (ai.motif_consultation?ai.motif_consultation+'\n\n':'')+(ai.contenu_seance||''), [30,64,175]);
  sec('Observations cliniques',ai.observations_cliniques,[80,40,140]);

  if(ai.elements_importants){
    doc.setFillColor(255,245,245);doc.roundedRect(M,y-2,W-M*2,4,'F');
    sec('Éléments importants & vigilance',ai.elements_importants,[160,30,30]);
  }

  if(Array.isArray(ai.hypotheses_diag)&&ai.hypotheses_diag.length){
    sec('Hypothèses diagnostiques · CIM-11',null,[30,64,175]);
    ai.hypotheses_diag.forEach(function(h){ ln('• '+h,{in_:6,sz:9.5,c:[30,60,110]}); }); y+=1;
  }
  if(Array.isArray(ai.objectifs_prochaine)&&ai.objectifs_prochaine.length){
    sec('Objectifs · Prochaine séance',null,[5,120,80]);
    ai.objectifs_prochaine.forEach(function(o,i){ ln((i+1)+'. '+o,{in_:6,sz:9.5,c:[5,80,60]}); }); y+=1;
  }
  if(ai.plan_suite) sec('Plan de suivi', ai.plan_suite,[30,64,175]);

  var sn=lastRpt.snap;
  if(sn&&sn.radarImg){
    if(y>220){doc.addPage();y=M;}
    hr(); doc.setFontSize(7);doc.setFont('helvetica','bold');doc.setTextColor(30,64,175);
    doc.text('PROFIL PSYCHO-ÉMOTIONNEL (radar)',M,y);y+=4;
    try{ doc.addImage(sn.radarImg,'PNG',M,y,55,55); }catch(e){}
    y+=60;
  }
  if(sn&&sn.timelineImg){
    if(y>220){doc.addPage();y=M;}
    doc.setFontSize(7);doc.setFont('helvetica','bold');doc.setTextColor(30,64,175);
    doc.text('ÉVOLUTION ÉMOTIONNELLE · SÉANCE',M,y);y+=4;
    try{ doc.addImage(sn.timelineImg,'PNG',M,y,W-M*2,22); y+=26; }catch(e){}
  }
  var notesVal=document.getElementById('notes').value;
  if(notesVal){ hr(); sec('Notes du praticien',notesVal,[90,90,90]); }

  hr();
  doc.setFontSize(7);doc.setFont('helvetica','normal');doc.setTextColor(140,155,175);
  doc.text('PsySpace Pro 2026 · Confidentiel · Usage clinique exclusif',M,289);
  doc.text('Généré le '+lastRpt.date,W-M,289,{align:'right'});

  doc.save('CR_'+PAT.replace(/\s+/g,'_')+'_S'+SESN+'_'+lastRpt.date.replace(/\//g,'-')+'.pdf');
}

// ══════════════════════════════════════════════════════════════════════════════
// ARCHIVAGE — CORRIGÉ
// ══════════════════════════════════════════════════════════════════════════════
async function finalize(){
  var tr=document.getElementById('tr').value.trim();
  if(!tr){ ntf('narch','Aucune transcription à archiver.','wa'); return; }

  ovO('Archiver la séance n°'+SESN+' de <strong>'+escH(PAT)+'</strong> ?', async function(){
    ntf('narch','Archivage en cours…','in',0);

    // Résumé texte pur (pas JSON) pour la DB
    var resumeTexte = lastRpt ? lastRpt.resume_str : ('Séance n°'+SESN+' du '+DATED+' — Transcription sans compte-rendu.');

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('transcript', tr);
    fd.append('resume', resumeTexte);                             // ← texte pur, pas JSON
    fd.append('duree', String(Math.floor(secs/60)));
    fd.append('emotions', JSON.stringify(emoP));

    try{
      var res = await fetch('save_consultation.php', {method:'POST', body:fd});

      if(!res.ok) throw new Error('HTTP '+res.status);

      var d = await res.text();
      d = d.trim();

      if(d === 'success'){
        ntf('narch','Séance archivée avec succès !','ok',0);
        setTimeout(function(){ window.location.href='dashboard.php'; }, 1800);
      } else if(d === 'already_saved'){
        ntf('narch','Cette séance a déjà été archivée.','wa');
      } else if(d === 'csrf_invalid'){
        ntf('narch','Erreur de sécurité CSRF. Rechargez la page.','er');
      } else if(d === 'no_appointment_id'){
        ntf('narch','Erreur : ID de rendez-vous manquant. Rechargez la page.','er');
      } else {
        ntf('narch','Erreur serveur : '+d,'er');
      }
    }catch(e){
      ntf('narch','Erreur réseau : '+e.message,'er');
    }
  });
}

// INIT
drawEmo();
drawRadar();
drawTL();
drawLg();
</script>
</body>
</html>