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

// --- 2. ANTI VOL DE SESSION ---
if (isset($_SESSION['user_ip'], $_SESSION['user_agent'])) {
    if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR'] ||
        $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        header("Location: login.php?error=hijack");
        exit();
    }
}

// --- 3. PARE-FEU CSP (CORRIGÉ POUR AUTORISER LE MICRO ET LES BOUTONS) ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
// On a ajouté 'unsafe-inline' et 'unsafe-eval' pour permettre à Chart.js, jsPDF et tes boutons 'onclick' de fonctionner
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none';");

include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

// --- 4. VALIDATION ---
$patient_raw = $_GET['patient_name'] ?? '';
if (!preg_match('/^[\p{L}\s\'\-\.]{1,100}$/u', $patient_raw)) { header("Location: dashboard.php"); exit(); }
$patient_selected = trim($patient_raw);
$doctor_id        = (int)$_SESSION['id'];
$nom_docteur      = $_SESSION['nom'] ?? 'Docteur';
$appointment_id   = (int)($_GET['id'] ?? 0);
if (!$appointment_id) { header("Location: dashboard.php"); exit(); }

// --- 5. VÉRIF IDOR ---
$stmt = $conn->prepare("SELECT patient_id FROM appointments WHERE id=? AND doctor_id=? LIMIT 1");
$stmt->bind_param("ii", $appointment_id, $doctor_id);
$stmt->execute();
$r = $stmt->get_result();
if ($r->num_rows === 0) { header("Location: dashboard.php?error=unauthorized"); exit(); }
$patient_id = (int)$r->fetch_assoc()['patient_id'];
$stmt->close();
if (!$patient_id) { header("Location: dashboard.php"); exit(); }

// --- 6. HISTORIQUE ---
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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Séance · <?= htmlspecialchars($patient_selected, ENT_QUOTES, 'UTF-8') ?> | PsySpace</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=Lora:ital,wght@0,400;0,600;1,400;1,600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
/* ─── RESET & VARIABLES ─────────────────────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#07090f;
  --s1:#0e1117;
  --s2:rgba(14,17,23,.8);
  --b:rgba(255,255,255,.055);
  --b2:rgba(255,255,255,.032);
  --ac:#5b5fef;
  --ac2:#818cf8;
  --ok:#0ea472;
  --wa:#d97706;
  --er:#dc2626;
  --in:#0284c7;
  --tx:#e8edf5;
  --tx2:#94a3b8;
  --tx3:#4b5563;
  --su:#374151;

  /* Rapport — palette papier */
  --rp-bg:#f8f9fb;
  --rp-bg2:#f1f3f7;
  --rp-border:#dde2ec;
  --rp-tx:#1a2035;
  --rp-tx2:#3d4a63;
  --rp-tx3:#697288;
  --rp-accent:#2c3a8c;
  --rp-ok:#145a38;
  --rp-wa:#7c4a00;
  --rp-er:#7c1a1a;
  --rp-rule:#c8d0e0;
}

html,body{height:100%;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--tx);overflow:hidden;}
::-webkit-scrollbar{width:3px;}::-webkit-scrollbar-thumb{background:#1e2433;border-radius:3px;}
::-webkit-scrollbar-track{background:transparent;}

/* ─── LAYOUT ──────────────────────────────────────────────────────────────── */
.app{display:grid;grid-template-rows:50px 1fr;height:100vh;}
.g3{display:grid;grid-template-columns:270px 1fr 320px;height:calc(100vh - 50px);overflow:hidden;}
.col{overflow-y:auto;display:flex;flex-direction:column;gap:10px;padding:12px 10px;}

/* ─── TOPBAR ──────────────────────────────────────────────────────────────── */
.top{
  display:flex;align-items:center;justify-content:space-between;
  padding:0 20px;
  background:rgba(7,9,15,.97);
  border-bottom:1px solid var(--b);
}

/* ─── CARDS ───────────────────────────────────────────────────────────────── */
.card{background:var(--s1);border:1px solid var(--b);border-radius:14px;padding:13px;}
.card2{background:var(--s2);border:1px solid var(--b2);border-radius:10px;padding:10px;}

/* ─── LABELS ──────────────────────────────────────────────────────────────── */
.lbl{font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.2em;color:var(--tx3);}

/* ─── CHIPS ───────────────────────────────────────────────────────────────── */
.chip{display:inline-flex;align-items:center;gap:3px;padding:2px 9px;border-radius:99px;
  font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;border:1px solid;}
.ck{background:rgba(14,164,114,.07);color:#34d399;border-color:rgba(14,164,114,.2);}
.cw{background:rgba(217,119,6,.07);color:#fbbf24;border-color:rgba(217,119,6,.2);}
.ce{background:rgba(220,38,38,.08);color:#f87171;border-color:rgba(220,38,38,.2);}
.ci{background:rgba(91,95,239,.08);color:var(--ac2);border-color:rgba(91,95,239,.2);}
.cs{background:rgba(55,65,81,.08);color:#6b7280;border-color:rgba(55,65,81,.18);}
.ccrit{background:rgba(220,38,38,.14);color:#ff5555;border-color:rgba(220,38,38,.38);animation:gcrit 1.4s infinite;}
@keyframes gcrit{50%{box-shadow:0 0 10px rgba(220,38,38,.35);}}

/* ─── BUTTONS ─────────────────────────────────────────────────────────────── */
.btn{font-size:8.5px;font-weight:700;text-transform:uppercase;letter-spacing:.13em;
  border-radius:9px;padding:8px 14px;border:none;cursor:pointer;transition:all .16s;
  display:inline-flex;align-items:center;justify-content:center;gap:5px;}
.ba{background:var(--ac);color:#fff;}
.ba:hover{background:#4f52d4;box-shadow:0 4px 18px rgba(91,95,239,.3);}
.bok{background:rgba(14,164,114,.09);color:#34d399;border:1px solid rgba(14,164,114,.18);}
.bok:hover{background:rgba(14,164,114,.18);}
.bg{background:transparent;color:var(--tx3);border:1px solid var(--b);}
.bg:hover{color:var(--tx);border-color:rgba(255,255,255,.18);}
.bq{background:rgba(91,95,239,.07);color:var(--ac2);border:1px solid rgba(91,95,239,.14);}
.btn:disabled{opacity:.2;cursor:not-allowed;pointer-events:none;}

/* ─── TEXTAREA ────────────────────────────────────────────────────────────── */
.ta{
  background:rgba(7,9,15,.7);border:1px solid rgba(255,255,255,.065);color:#c8d3e8;
  border-radius:12px;resize:none;outline:none;width:100%;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;line-height:1.85;padding:13px;
  transition:border-color .22s;
}
.ta:focus{border-color:rgba(91,95,239,.32);}
.ta::placeholder{color:#2a3142;font-style:italic;}
.nta{
  background:rgba(217,119,6,.03);border:1px solid rgba(217,119,6,.1);color:#fbbf24;
  border-radius:10px;resize:none;outline:none;width:100%;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:11.5px;line-height:1.7;padding:10px;
}
.nta:focus{border-color:rgba(217,119,6,.25);}
.nta::placeholder{color:rgba(217,119,6,.18);font-style:italic;}

/* ─── TOAST ───────────────────────────────────────────────────────────────── */
.toast{border-radius:9px;padding:7px 12px;font-size:9.5px;font-weight:700;display:flex;align-items:center;gap:6px;}
.tok{background:rgba(14,164,114,.09);border:1px solid rgba(14,164,114,.2);color:#6ee7b7;}
.ter{background:rgba(220,38,38,.09);border:1px solid rgba(220,38,38,.2);color:#fca5a5;}
.twa{background:rgba(217,119,6,.09);border:1px solid rgba(217,119,6,.2);color:#fcd34d;}
.tin{background:rgba(91,95,239,.09);border:1px solid rgba(91,95,239,.2);color:var(--ac2);}

/* ─── MIC ─────────────────────────────────────────────────────────────────── */
.mic{width:44px;height:44px;border-radius:50%;border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:all .22s;flex-shrink:0;}
.mic-idle{background:rgba(91,95,239,.12);border:1.5px solid rgba(91,95,239,.26);}
.mic-live{background:#dc2626;animation:mring 1.3s ease-out infinite;}
.mic-done{background:rgba(14,164,114,.12);border:1.5px solid rgba(14,164,114,.26);}
@keyframes mring{0%{box-shadow:0 0 0 0 rgba(220,38,38,.5)}70%{box-shadow:0 0 0 13px rgba(220,38,38,0)}100%{box-shadow:0 0 0 0 rgba(220,38,38,0)}}

/* ─── FEED ────────────────────────────────────────────────────────────────── */
.fi{padding:8px 11px;border-radius:10px;border-left:2px solid;margin-bottom:5px;animation:sin .28s ease forwards;}
@keyframes sin{from{opacity:0;transform:translateX(7px)}to{opacity:1;transform:none}}
@keyframes fup{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}
.fu{animation:fup .28s ease forwards;}

/* ─── DOTS LOADER ─────────────────────────────────────────────────────────── */
.dots span{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--ac);
  margin:0 2px;animation:db 1.2s ease-in-out infinite;}
.dots span:nth-child(2){animation-delay:.2s}.dots span:nth-child(3){animation-delay:.4s}
@keyframes db{0%,80%,100%{transform:scale(.35);opacity:.25}40%{transform:scale(1);opacity:1}}

.pulse{animation:pls 2s cubic-bezier(.4,0,.6,1) infinite;}
@keyframes pls{0%,100%{opacity:1}50%{opacity:.2}}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}

/* ─── DIAG PILL ───────────────────────────────────────────────────────────── */
.dp{display:flex;align-items:flex-start;gap:7px;padding:8px 10px;
  border-radius:10px;border:1px solid rgba(14,165,233,.13);background:rgba(14,165,233,.04);margin-bottom:5px;}
.dc{font-size:8px;font-weight:900;color:#38bdf8;background:rgba(14,165,233,.1);
  border-radius:4px;padding:2px 5px;white-space:nowrap;flex-shrink:0;margin-top:1px;letter-spacing:.03em;}

/* ─── OVERLAY ─────────────────────────────────────────────────────────────── */
.ov{position:fixed;inset:0;background:rgba(0,0,0,.85);backdrop-filter:blur(10px);
  z-index:999;display:flex;align-items:center;justify-content:center;
  opacity:0;pointer-events:none;transition:opacity .2s;}
.ov.open{opacity:1;pointer-events:all;}
.ovb{background:rgba(7,9,15,.98);border:1px solid rgba(255,255,255,.07);
  border-radius:18px;padding:24px;max-width:390px;width:calc(100% - 32px);
  transform:scale(.95);transition:transform .2s;}
.ov.open .ovb{transform:scale(1);}

/* ─── DIVIDER ─────────────────────────────────────────────────────────────── */
.rd{height:1px;background:var(--b);margin:9px 12px;}

/* ═══════════════════════════════════════════════════════════════════════════ */
/* RAPPORT CLINIQUE — style dossier médical imprimable                        */
/* ═══════════════════════════════════════════════════════════════════════════ */
.rpt-wrap{
  font-family:'Lora','Georgia',serif;
  background:var(--rp-bg);
  color:var(--rp-tx);
  border-radius:10px;
  border:1px solid var(--rp-border);
  padding:0;
  overflow:hidden;
}

/* En-tête du rapport */
.rpt-header{
  background:var(--rp-accent);
  padding:16px 20px 14px;
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
}
.rpt-header-title{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.2em;
  color:rgba(255,255,255,.55);margin-bottom:4px;
}
.rpt-header-name{
  font-family:'Lora',serif;
  font-size:18px;font-weight:600;font-style:italic;color:#fff;
  line-height:1.2;
}
.rpt-header-meta{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:8.5px;font-weight:500;color:rgba(255,255,255,.5);
  margin-top:5px;letter-spacing:.03em;line-height:1.7;
}
.rpt-header-badge{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;
  padding:4px 10px;border-radius:5px;border:1px solid rgba(255,255,255,.22);
  color:rgba(255,255,255,.8);
}

/* Corps du rapport */
.rpt-body{padding:18px 20px;}

/* Section */
.rpt-section{margin-bottom:18px;}
.rpt-section-title{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.22em;
  color:var(--rp-tx3);margin-bottom:8px;padding-bottom:6px;
  border-bottom:1px solid var(--rp-rule);
  display:flex;align-items:center;gap:6px;
}
.rpt-section-title::before{
  content:'';width:2.5px;height:10px;border-radius:2px;
  background:var(--rp-accent);flex-shrink:0;
}

/* Texte courant du rapport */
.rpt-prose{
  font-family:'Lora',serif;
  font-size:12px;line-height:1.85;color:var(--rp-tx2);
}
.rpt-prose em{color:var(--rp-tx);font-style:italic;}
.rpt-prose strong{color:var(--rp-tx);font-weight:600;}

/* Bloc mis en évidence */
.rpt-highlight{
  background:var(--rp-bg2);
  border-left:3px solid var(--rp-accent);
  border-radius:0 6px 6px 0;
  padding:10px 14px;
  margin:10px 0;
}
.rpt-highlight .rpt-prose{font-size:12.5px;color:var(--rp-tx);}

/* Grille 2 colonnes */
.rpt-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:0;}
.rpt-col-block{
  background:var(--rp-bg2);border:1px solid var(--rp-border);
  border-radius:6px;padding:10px 12px;
}
.rpt-col-label{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.18em;
  color:var(--rp-tx3);margin-bottom:5px;
}

/* Niveau de risque */
.rpt-risk-band{
  display:flex;align-items:center;gap:12px;
  padding:10px 14px;border-radius:7px;
  border:1px solid;margin-bottom:14px;
}
.rpt-risk-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.rpt-risk-label{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.15em;flex-shrink:0;
}
.rpt-risk-text{
  font-family:'Lora',serif;font-size:11.5px;color:var(--rp-tx2);line-height:1.7;
}

/* Hypothèses diagnostiques */
.rpt-diag-row{
  display:flex;align-items:flex-start;gap:10px;
  padding:9px 0;border-bottom:1px solid var(--rp-rule);
}
.rpt-diag-row:last-child{border-bottom:none;}
.rpt-diag-code{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:8px;font-weight:800;letter-spacing:.05em;
  background:var(--rp-accent);color:#fff;
  padding:2px 7px;border-radius:4px;flex-shrink:0;margin-top:2px;
}
.rpt-diag-text{
  font-family:'Lora',serif;font-size:11.5px;color:var(--rp-tx2);line-height:1.65;
}

/* Objectifs */
.rpt-obj-row{
  display:flex;align-items:flex-start;gap:9px;padding:5px 0;
}
.rpt-obj-num{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:9px;font-weight:800;color:var(--rp-accent);
  width:18px;flex-shrink:0;text-align:center;margin-top:1px;
}
.rpt-obj-text{
  font-family:'Lora',serif;font-size:11.5px;color:var(--rp-tx2);line-height:1.65;
}

/* Plan thérapeutique */
.rpt-plan{
  background:var(--rp-bg2);border:1px solid var(--rp-border);
  border-radius:7px;padding:12px 14px;
}
.rpt-plan-text{
  font-family:'Lora',serif;font-size:11.5px;color:var(--rp-tx2);line-height:1.8;
  white-space:pre-line;
}

/* Courrier confrère */
.rpt-letter{
  background:#fff;border:1px solid var(--rp-border);
  border-radius:7px;padding:16px 18px;
  border-top:3px solid var(--rp-accent);
}
.rpt-letter-pre{
  font-family:'Lora',serif;font-size:11.5px;color:var(--rp-tx2);
  line-height:1.9;white-space:pre-line;
}

/* Footer rapport */
.rpt-footer{
  padding:10px 20px;border-top:1px solid var(--rp-rule);
  display:flex;justify-content:space-between;align-items:center;
  background:var(--rp-bg2);
}
.rpt-footer-txt{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:7.5px;font-weight:600;color:var(--rp-tx3);letter-spacing:.05em;
}

/* Vigilance */
.rpt-vigilance{
  background:#fff8f8;border:1px solid #f0c0c0;border-left:3px solid #c0392b;
  border-radius:0 6px 6px 0;padding:10px 14px;margin:0;
}
.rpt-vigilance-label{
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.18em;
  color:#c0392b;margin-bottom:4px;
}
.rpt-vigilance-text{
  font-family:'Lora',serif;font-size:11.5px;color:#5a1a1a;line-height:1.7;
}
</style>
</head>
<body>
<div class="app">

<!-- ═══ TOPBAR ═══ -->
<div class="top">
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="dashboard.php" style="color:var(--tx3);font-size:17px;font-weight:700;text-decoration:none;padding:4px 6px;border-radius:7px;transition:all .18s;"
       onmouseover="this.style.color='var(--tx)'" onmouseout="this.style.color='var(--tx3)'">←</a>
    <div style="width:1px;height:18px;background:var(--b);"></div>
    <div style="width:30px;height:30px;border-radius:8px;background:rgba(91,95,239,.13);border:1px solid rgba(91,95,239,.22);
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
  <div style="display:flex;align-items:center;gap:16px;">
    <div style="display:flex;align-items:center;gap:5px;">
      <span style="width:5px;height:5px;border-radius:50%;background:var(--ac);" class="pulse"></span>
      <span class="lbl" style="color:var(--ac2);">IA active</span>
    </div>
    <div style="display:flex;align-items:center;gap:6px;">
      <span id="recdot" style="width:6px;height:6px;border-radius:50%;background:var(--su);transition:all .3s;"></span>
      <span id="timer" style="font-size:17px;font-weight:900;letter-spacing:.05em;color:var(--tx3);font-variant-numeric:tabular-nums;">00:00</span>
    </div>
    <div id="stop" class="chip cs">En attente</div>
  </div>
</div>

<!-- ═══ GRID 3 COLONNES ═══ -->
<div class="g3">

<!-- ══════════════════════════════════════════════════════════ COL GAUCHE -->
<div class="col" style="border-right:1px solid var(--b);">

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
      <div id="feedph" style="padding:20px 0;text-align:center;opacity:.2;">
        <p class="lbl" style="line-height:2;">Démarrez la capture<br>L'IA analysera en continu</p>
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
      <div id="diagph" style="padding:10px 0;text-align:center;opacity:.2;">
        <p class="lbl" style="line-height:2;">Générées automatiquement<br>au fil du verbatim</p>
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
      placeholder="Observations, hypothèses cliniques, impressions du praticien..."></textarea>
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
        background:rgba(14,17,23,.4);cursor:pointer;transition:border-color .18s;"
        onmouseover="this.style.borderColor='rgba(91,95,239,.28)'"
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
  <div style="padding:14px;text-align:center;opacity:.18;">
    <p class="lbl" style="line-height:2;">Première séance<br>Aucun historique disponible</p>
  </div>
  <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════════ COL CENTRE -->
<div class="col" style="padding:12px 14px;">

  <!-- TRANSCRIPTION -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <div>
        <p style="font-size:13px;font-weight:800;color:var(--tx);">Transcription de séance</p>
        <p class="lbl" style="margin-top:2px;">Capture vocale ou saisie manuelle · Analyse IA en continu</p>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <div id="wv" style="display:flex;align-items:center;gap:2px;height:20px;">
          <?php for($i=0;$i<12;$i++): ?><div style="width:2.5px;height:3px;background:var(--su);border-radius:2px;transition:height .1s,background .18s;"></div><?php endfor; ?>
        </div>
        <span id="wc" class="chip cs">0 mot</span>
      </div>
    </div>

    <textarea id="tr" class="ta" rows="12"
      placeholder="La parole s'inscrit ici automatiquement lors de la capture vocale..."
      oninput="onTyping(this.value)"></textarea>

    <div id="ntr" style="margin-top:6px;min-height:26px;"></div>

    <div style="display:flex;align-items:center;gap:10px;margin-top:9px;">
      <!-- MICRO -->
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
        <div style="height:2.5px;background:var(--s1);border-radius:2px;overflow:hidden;">
          <div id="abar" style="width:0%;height:2.5px;background:var(--ac);border-radius:2px;transition:width .1s;"></div>
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:5px;cursor:pointer;flex-shrink:0;">
        <input type="checkbox" id="autoai" checked style="accent-color:var(--ac);width:12px;height:12px;">
        <span class="lbl" style="color:var(--ac2);">Auto-IA</span>
      </label>
      <button onclick="clearTr()" class="btn"
        style="background:rgba(220,38,38,.07);color:#f87171;border:1px solid rgba(220,38,38,.14);padding:7px 10px;font-size:8px;flex-shrink:0;">✕</button>
    </div>

    <div id="sttw" style="display:none;margin-top:8px;padding:8px 12px;border-radius:9px;
      background:rgba(217,119,6,.07);border:1px solid rgba(217,119,6,.18);">
      <p style="font-size:9.5px;color:#fbbf24;font-weight:600;line-height:1.6;">
        ⚠ Reconnaissance vocale non disponible. Utilisez Chrome ou Edge et autorisez le microphone. Vous pouvez saisir le texte manuellement.
      </p>
    </div>
  </div>

  <!-- RAPPORT CLINIQUE -->
  <div class="card" style="flex:1;padding:0;overflow:hidden;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 13px 11px;border-bottom:1px solid var(--b);">
      <div>
        <p style="font-size:13px;font-weight:800;color:var(--tx);">Compte-rendu clinique</p>
        <p class="lbl" style="margin-top:2px;">Généré par intelligence artificielle · Confidentiel</p>
      </div>
      <button id="btngen" onclick="genReport()" disabled class="btn ba">✦ Générer le bilan</button>
    </div>

    <div id="rpbody" style="max-height:calc(100vh - 280px);overflow-y:auto;">
      <!-- état initial -->
      <div id="rp-placeholder" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:55px 20px;opacity:.16;">
        <div style="width:28px;height:28px;border:1.5px dashed #64748b;border-radius:50%;margin-bottom:10px;animation:spin 3s linear infinite;"></div>
        <p class="lbl" style="text-align:center;line-height:2;">En attente de transcription</p>
      </div>
    </div>

    <div style="padding:10px 13px;border-top:1px solid var(--b);">
      <div id="narch" style="margin-bottom:6px;min-height:22px;"></div>
      <div style="display:flex;gap:8px;">
        <button onclick="exportPDF()" id="btnpdf" disabled class="btn bg" style="flex:1;">↓ Exporter PDF</button>
        <button onclick="finalize()" class="btn bok" style="flex:1;">✓ Archiver la séance</button>
      </div>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════ COL DROITE -->
<div class="col" style="border-left:1px solid var(--b);padding:0;gap:0;">

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
      <?php foreach([['Urgence','#ef4444'],['Détresse','#f59e0b'],['Anxiété','#8b5cf6'],['Résilience','#10b981'],['Social','#38bdf8'],['Stabilité','var(--ac)']] as [$l,$c]): ?>
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
    <div id="ins" style="max-height:195px;overflow-y:auto;">
      <div id="insph" style="padding:12px 0;text-align:center;opacity:.2;">
        <p class="lbl" style="line-height:2;">L'IA générera des insights<br>au fil de la transcription</p>
      </div>
    </div>
  </div>

  <div class="rd"></div>

  <!-- DYNAMIQUE ÉMOTIONNELLE (mini chart valence) -->
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

<!-- ═══ OVERLAY ═══ -->
<div id="ov" class="ov" onclick="if(event.target===this)ovC()">
  <div class="ovb">
    <p id="ovmsg" style="font-size:13px;color:var(--tx2);font-weight:500;margin-bottom:18px;line-height:1.6;"></p>
    <div style="display:flex;gap:8px;">
      <button id="ovyes" class="btn bok" style="flex:1;">Confirmer</button>
      <button onclick="ovC()" class="btn bg" style="flex:1;">Annuler</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════════════════════════ -->
<script>
// ── PHP → JS ──────────────────────────────────────────────────────────────────
var CSRF  = <?= json_encode($csrf) ?>;
var PAT   = <?= json_encode($patient_selected) ?>;
var SESN  = <?= $session_num ?>;
var DATED = <?= json_encode($appt_date) ?>;
var DR    = <?= json_encode($nom_docteur) ?>;
var HIST  = <?= json_encode($history_for_ai) ?>;

// ── État global ───────────────────────────────────────────────────────────────
var micOn = false, recog = null, timerIv = null, secs = 0;
var lastRpt = null, lastAT = '';
var emoC, radC, tlC, lgC;
var emoP = [0], tR = [], tRs = [], tD = [], tA = [];
var rD = {u:0,d:0,a:0,r:0,s:0,st:100};

// ── Overlay ───────────────────────────────────────────────────────────────────
function ovO(msg, cb) {
  document.getElementById('ovmsg').innerHTML = msg;
  document.getElementById('ov').classList.add('open');
  document.getElementById('ovyes').onclick = function(){ ovC(); cb(); };
}
function ovC(){ document.getElementById('ov').classList.remove('open'); }

// ── Notifications ─────────────────────────────────────────────────────────────
function ntf(id, msg, type, ms) {
  if(ms === undefined) ms = 4500;
  var z = document.getElementById(id); if(!z) return;
  var ic = {ok:'✓', er:'✕', wa:'⚠', in:'ℹ'};
  z.innerHTML = '<div class="toast t'+type+'"><span>'+(ic[type]||'ℹ')+'</span><span>'+msg+'</span></div>';
  if(ms > 0) setTimeout(function(){ z.innerHTML=''; }, ms);
}

// ══════════════════════════════════════════════════════════════════════════════
// LEXIQUE CLINIQUE — détection silencieuse (n'alimente QUE le prompt IA)
// ════════════════════════════════════════════════════════════════���═════════════
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
  so2: { p:7,   w:["douleurs chroniques","fibromyalgie","j'ai mal partout","psychosomatique","symptômes inexpliqués","les médecins ne trouvent rien","syndrome de fatigue chronique","côlon irritable","eczéma","psoriasis","douleurs musculaires","contractures"] },
  re:  { p:10,  w:["conflit","disputes","on se dispute tout le temps","mésentente","communication rompue","séparation","divorce","famille toxique","relations toxiques","dépendance affective","peur d'être abandonné","peur d'être abandonnée","jalousie","jaloux","jalouse","je sabote mes relations","je n'arrive pas à dire non","on piétine mes limites","je me laisse faire"] },
  po:  { p:-12, w:["espoir","j'espère","optimiste","confiant","confiante","projets","j'ai des projets","je vais m'en sortir","mieux","je vais mieux","ça va mieux","heureux","heureuse","joie","bonheur","content","contente","serein","sereine","apaisé","apaisée","calme","soulagé","soulagée","bien dans ma peau","je m'accepte","énergie","j'ai de l'énergie","motivé","motivée","plaisir","sourire","je souris","fierté","j'ai accompli","j'ai réussi","mes enfants","je veux guérir","changer","je veux changer","thérapie","ça m'aide","ça fait du bien","prise de conscience","j'ai compris","lâcher prise","je lâche prise","mindfulness","méditation","je prends soin de moi"] },
  so:  { p:-7,  w:["ami","amis","amie","entourage","entouré","entourée","soutien","soutenu","soutenue","je me sens soutenu","je me sens soutenue","accompagné","accompagnée","pas seul","pas seule","parler à quelqu'un","j'ai quelqu'un à qui parler","connecté","connectée","relation solide","relation saine","sortir","je sors","rencontrer","j'ai rencontré","groupe de soutien","groupe de parole","aide professionnelle","je vois un psy","je suis suivi","je suis suivie","psychiatre","psychologue","thérapeute","je consulte"] }
};

var NEG = ["ne","n'","pas","plus","jamais","aucun","aucune","sans","ni","non","nullement","guère","absolument pas","certainement pas"];
var INT = ["très","vraiment","tellement","trop","extrêmement","profondément","absolument","terriblement","infiniment","énormément","complètement","totalement","intensément","fortement"];
var ATT = ["un peu","légèrement","parfois","peut-être","vaguement","de temps en temps","par moments","rarement","pas toujours","il m'arrive","ça m'arrive","de temps à autre"];

// ── analyze() — silencieux, alimente uniquement le prompt IA ─────────────────
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
          var mul = 1;
          if(INT.some(function(m){ return ctx.indexOf(m) !== -1; })) mul=1.7;
          else if(ATT.some(function(m){ return ctx.indexOf(m) !== -1; })) mul=0.45;
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
            var mul = 1;
            if(INT.some(function(m){ return ctx.indexOf(m) !== -1; })) mul=1.7;
            else if(ATT.some(function(m){ return ctx.indexOf(m) !== -1; })) mul=0.45;
            if(!neg) sc[cat] += LEX[cat].p * mul;
            else if(cat==='po'||cat==='so') sc[cat] -= LEX[cat].p*0.4;
          }
        });
      }
    });
  });

  var danger = sc.u*1.0 + sc.am*0.85 + sc.ps*0.80 + sc.d*0.55 + sc.tr*0.60
             + sc.di*0.50 + sc.a*0.35 + sc.ma*0.40 + sc.ad*0.30
             + sc.ta*0.35 + sc.so2*0.20 + sc.re*0.25;
  var prot = Math.abs(sc.po)*0.9 + Math.abs(sc.so)*0.6;
  var risk = Math.round(Math.max(0, Math.min(100, danger - prot)));

  return {
    sc:sc, risk:risk,
    alerte_suicidaire:    sc.u > 30,
    alerte_automutilation:sc.am > 20,
    alerte_psychose:      sc.ps > 25,
    alerte_manie:         sc.ma > 20,
    alerte_dissociation:  sc.di > 15
  };
}

// ── INPUT → analyse + mise à jour visuels (radar + timeline uniquement) ───────
function onTyping(val) {
  var words = val.trim().split(/\s+/).filter(function(w){ return w.length>0; });
  var wc = words.length;
  document.getElementById('wc').textContent = wc + (wc>1?' mots':' mot');

  var res = analyze(val);
  var sc = res.sc, risk = res.risk;

  // Met à jour les visuels (radar, timeline) — SANS afficher de scores numériques
  updRadar(sc, risk);
  updTimeline(risk, sc);

  // Met à jour la valence émotionnelle
  var neg=sc.u+sc.d+sc.a, pos=Math.abs(sc.po)+Math.abs(sc.so), sum=neg+pos;
  emoP.push(sum>0 ? Math.max(-1,Math.min(1,(pos-neg)/sum)) : 0);
  if(emoP.length>60) emoP.shift();
  drawEmo();

  if(wc > 10) document.getElementById('btngen').disabled = false;

  if(document.getElementById('autoai').checked && wc>0 && wc%35===0 && val!==lastAT){
    lastAT = val; autoAI(val, sc, risk);
  }
}

// ── RADAR ─────────────────────────────────────────────────────────────────────
function updRadar(sc, risk){
  rD = {
    u:  Math.min(100,Math.round(Math.max(0,sc.u))),
    d:  Math.min(100,Math.round(Math.max(0,sc.d))),
    a:  Math.min(100,Math.round(Math.max(0,sc.a))),
    r:  Math.min(100,Math.round(Math.abs(sc.po))),
    s:  Math.min(100,Math.round(Math.abs(sc.so))),
    st: Math.max(0,100-risk)
  };
  drawRadar();
  document.getElementById('rchip').textContent='Actif';
  document.getElementById('rchip').className='chip ck';
}
function drawRadar(){
  var ctx = document.getElementById('radarChart').getContext('2d');
  if(radC) radC.destroy();
  var vals=[rD.u,rD.d,rD.a,rD.r,rD.s,rD.st];
  var pc=['#ef4444','#f59e0b','#8b5cf6','#10b981','#38bdf8','#5b5fef'];
  var danger = rD.u>40||rD.d>50;
  radC = new Chart(ctx,{
    type:'radar',
    data:{
      labels:['Urgence','Détresse','Anxiété','Résilience','Social','Stabilité'],
      datasets:[
        {data:[100,100,100,100,100,100],borderColor:'rgba(255,255,255,.02)',backgroundColor:'transparent',borderWidth:1,pointRadius:0,order:2},
        {data:vals,
          borderColor:danger?'rgba(239,68,68,.65)':'rgba(91,95,239,.6)',
          backgroundColor:danger?'rgba(239,68,68,.06)':'rgba(91,95,239,.08)',
          borderWidth:1.5,pointBackgroundColor:pc,
          pointBorderColor:'rgba(7,9,15,.8)',pointBorderWidth:1.5,
          pointRadius:4,pointHoverRadius:6,order:1}
      ]
    },
    options:{
      responsive:true,maintainAspectRatio:false,
      animation:{duration:500,easing:'easeInOutCubic'},
      plugins:{legend:{display:false},tooltip:{
        backgroundColor:'rgba(7,9,15,.97)',borderColor:'rgba(255,255,255,.08)',borderWidth:1,
        padding:9,cornerRadius:9,
        titleFont:{family:'Plus Jakarta Sans',size:8,weight:'800'},
        bodyFont:{family:'Plus Jakarta Sans',size:9.5},
        callbacks:{
          title:function(i){ return [i[0].label.toUpperCase()]; },
          label:function(i){ return i.datasetIndex===1?' '+i.raw+' / 100':null; }
        }
      }},
      scales:{r:{min:0,max:100,ticks:{display:false},
        grid:{color:'rgba(255,255,255,.035)'},
        angleLines:{color:'rgba(255,255,255,.04)'},
        pointLabels:{color:'#374151',font:{family:'Plus Jakarta Sans',size:7.5,weight:'700'},padding:4}
      }}
    }
  });
}

// ── TIMELINE ──────────────────────────────────────────────────────────────────
function updTimeline(risk, sc){
  tR.push(risk);
  tRs.push(Math.round(Math.abs(sc.po)+Math.abs(sc.so)));
  tD.push(Math.round(Math.max(0,sc.d)));
  tA.push(Math.round(Math.max(0,sc.a)));
  var M=60; if(tR.length>M){tR.shift();tRs.shift();tD.shift();tA.shift();}
  drawTL();
  if(tR.length>=4){
    var n=tR.length;
    var diff=((tR[n-1]+tR[n-2])/2)-((tR[n-3]+tR[n-4])/2);
    var tc=document.getElementById('tlchip');
    var tl=document.getElementById('tllbl');
    if(diff>8)      {tc.textContent='↑ Tension croissante';tc.className='chip ce';tl.style.color='#f87171';tl.textContent='Montée du risque';}
    else if(diff<-8){tc.textContent='↓ Apaisement';tc.className='chip ck';tl.style.color='#34d399';tl.textContent='Stabilisation progressive';}
    else            {tc.textContent='→ Stable';tc.className='chip cs';tl.style.color='#4b5563';tl.textContent='Régularité du discours';}
  }
}
function drawTL(){
  var ctx=document.getElementById('tlChart').getContext('2d');
  if(tlC) tlC.destroy();
  function g(c,c1,c2){var gr=c.chart.ctx.createLinearGradient(0,0,0,92);gr.addColorStop(0,c1);gr.addColorStop(1,c2);return gr;}
  tlC=new Chart(ctx,{type:'line',
    data:{labels:tR.map(function(_,i){return i;}),datasets:[
      {label:'Risque',     data:tR, borderColor:'#ef4444',borderWidth:2,  pointRadius:0,fill:true,tension:.42,backgroundColor:function(c){return g(c,'rgba(239,68,68,.18)','rgba(239,68,68,0)');}},
      {label:'Résilience', data:tRs,borderColor:'#10b981',borderWidth:1.5,pointRadius:0,fill:false,tension:.42},
      {label:'Détresse',   data:tD, borderColor:'#f59e0b',borderWidth:1.5,pointRadius:0,fill:false,tension:.42,borderDash:[3,3]},
      {label:'Anxiété',    data:tA, borderColor:'#8b5cf6',borderWidth:1.5,pointRadius:0,fill:false,tension:.42,borderDash:[2,4]}
    ]},
    options:{responsive:true,maintainAspectRatio:false,animation:false,
      interaction:{mode:'index',intersect:false},
      plugins:{legend:{display:false},tooltip:{
        backgroundColor:'rgba(7,9,15,.97)',borderColor:'rgba(255,255,255,.07)',borderWidth:1,
        padding:8,cornerRadius:8,
        titleFont:{family:'Plus Jakarta Sans',size:8,weight:'800'},
        bodyFont:{family:'Plus Jakarta Sans',size:8.5},
        callbacks:{
          title:function(i){return 'T+'+i[0].label;},
          label:function(i){return ' '+i.dataset.label;},
          labelColor:function(i){var c=['#ef4444','#10b981','#f59e0b','#8b5cf6'];return{borderColor:'transparent',backgroundColor:c[i.datasetIndex]||'#6366f1',borderRadius:2};}
        }
      }},
      scales:{
        x:{display:false},
        y:{min:0,max:100,ticks:{display:false},grid:{color:'rgba(255,255,255,.02)',drawBorder:false},border:{display:false}}
      }
    }
  });
}

// ── VALENCE CHART ─────────────────────────────────────────────────────────────
function drawEmo(){
  var ctx=document.getElementById('emoChart').getContext('2d');
  if(emoC) emoC.destroy();
  var last=emoP[emoP.length-1]||0;
  var lc=last>0.1?'#10b981':(last<-0.2?'#ef4444':'#5b5fef');
  var fc=last>0.1?'rgba(16,185,129,.18)':(last<-0.2?'rgba(239,68,68,.13)':'rgba(91,95,239,.16)');
  emoC=new Chart(ctx,{type:'line',
    data:{labels:emoP.map(function(_,i){return i;}),datasets:[{
      data:emoP,borderColor:lc,borderWidth:1.5,pointRadius:0,fill:true,tension:.4,
      backgroundColor:function(c){var g=c.chart.ctx.createLinearGradient(0,0,0,58);g.addColorStop(0,fc);g.addColorStop(1,'rgba(0,0,0,0)');return g;}
    }]},
    options:{responsive:true,maintainAspectRatio:false,animation:false,
      plugins:{legend:{display:false}},
      scales:{x:{display:false},y:{min:-1.2,max:1.2,ticks:{display:false},grid:{color:'rgba(255,255,255,.02)',drawBorder:false},border:{display:false}}}
    }
  });
}

// ── LONGITUDINAL ──────────────────────────────────────────────────────────────
function drawLg(){
  var el=document.getElementById('lgChart'); if(!el) return;
  var ctx=el.getContext('2d');
  if(lgC) lgC.destroy();
  var n=<?= max(1,count($prev_consults)) ?>;
  var labels=[],vals=[];
  for(var i=0;i<n;i++){labels.push('S'+(i+1));vals.push(Math.round(50+Math.sin(i*.9)*14));}
  lgC=new Chart(ctx,{type:'line',
    data:{labels:labels,datasets:[{data:vals,borderColor:'var(--ac)',borderWidth:1.5,
      pointBackgroundColor:'var(--ac)',pointBorderColor:'rgba(7,9,15,.8)',pointBorderWidth:1.5,
      pointRadius:3,pointHoverRadius:5,
      fill:true,backgroundColor:function(c){var g=c.chart.ctx.createLinearGradient(0,0,0,44);g.addColorStop(0,'rgba(91,95,239,.18)');g.addColorStop(1,'rgba(91,95,239,0)');return g;},tension:.4
    }]},
    options:{responsive:true,maintainAspectRatio:false,animation:{duration:600,easing:'easeInOutQuart'},
      plugins:{legend:{display:false}},
      scales:{
        x:{display:true,ticks:{color:'#374151',font:{family:'Plus Jakarta Sans',size:7,weight:'700'},maxRotation:0},grid:{display:false},border:{display:false}},
        y:{display:false,min:20,max:100}
      }
    }
  });
}

// ── WAVEFORM ──────────────────────────────────────────────────────────────────
var wvIv=null;
function startWave(){
  var bars=document.querySelectorAll('#wv div');
  wvIv=setInterval(function(){
    bars.forEach(function(b){
      b.style.height=(3+Math.random()*15)+'px';
      b.style.background='hsl('+(225+Math.random()*20)+',65%,'+(48+Math.random()*22)+'%)';
    });
  },110);
}
function stopWave(){
  clearInterval(wvIv);
  document.querySelectorAll('#wv div').forEach(function(b){b.style.height='3px';b.style.background='var(--su)';});
}

// ── MICRO ─────────────────────────────────────────────────────────────────────
(function(){
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if(!SR){
    document.getElementById('sttw').style.display='block';
    var mb=document.getElementById('micbtn');
    mb.disabled=true; mb.style.opacity='.25'; mb.style.cursor='not-allowed';
    return;
  }

  function makeRecog(){
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
      timerIv=setInterval(function(){
        secs++;
        var m=Math.floor(secs/60).toString().padStart(2,'0');
        var s=(secs%60).toString().padStart(2,'0');
        document.getElementById('timer').textContent=m+':'+s;
        document.getElementById('abar').style.width=(10+Math.random()*55)+'%';
      },1000);
    };

    r.onresult=function(e){
      var final='';
      for(var i=e.resultIndex;i<e.results.length;i++){
        if(e.results[i].isFinal) final+=e.results[i][0].transcript+' ';
      }
      if(final){
        var el=document.getElementById('tr');
        el.value+=final;
        el.scrollTop=el.scrollHeight;
        onTyping(el.value);
      }
    };

    r.onerror=function(e){
      if(e.error==='no-speech') return;
      if(e.error==='not-allowed'||e.error==='permission-denied'){
        ntf('ntr','Accès micro refusé. Autorisez le microphone.','er');
        setMicStopped(); return;
      }
    };

    r.onend=function(){
      if(micOn){ try{ recog.start(); } catch(ex){ setMicStopped(); } }
    };
    return r;
  }

  recog=makeRecog();

  window.toggleMic=function(){
    if(micOn){
      micOn=false;
      try{ recog.stop(); }catch(e){}
      setMicStopped();
    } else {
      try{ recog.abort(); }catch(e){}
      recog=makeRecog();
      try{ recog.start(); }
      catch(e){ ntf('ntr','Impossible de démarrer le micro : '+e.message,'er'); }
    }
  };
})();

function setMicStopped(){
  micOn=false;
  clearInterval(timerIv);
  stopWave();
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
    emoP=[0];tR=[];tRs=[];tD=[];tA=[];
    rD={u:0,d:0,a:0,r:0,s:0,st:100};
    drawEmo();drawRadar();drawTL();
    ntf('ntr','Transcription effacée.','in',2500);
  });
}

// ── FEED ──────────────────────────────────────────────────────────────────────
function addFeed(type, title, body){
  var cm={
    info:  ['rgba(91,95,239,.05)','rgba(91,95,239,.35)','var(--ac2)'],
    warn:  ['rgba(217,119,6,.05)','rgba(217,119,6,.35)','#fbbf24'],
    danger:['rgba(220,38,38,.08)','rgba(220,38,38,.5)','#f87171'],
    ok:    ['rgba(14,164,114,.05)','rgba(14,164,114,.3)','#34d399'],
    q:     ['rgba(14,165,233,.05)','rgba(14,165,233,.3)','#7dd3fc']
  };
  var c=cm[type]||cm.info;

  // Col gauche feed
  var ph=document.getElementById('feedph'); if(ph) ph.remove();
  var el=document.createElement('div');
  el.className='fi';
  el.style.cssText='background:'+c[0]+';border-left-color:'+c[1]+';';
  el.innerHTML='<p style="font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.13em;color:'+c[2]+';margin-bottom:2px;">'+title+'</p>'
    +'<p style="font-size:10px;color:var(--tx3);line-height:1.55;">'+body+'</p>';
  var feed=document.getElementById('feed');
  feed.insertBefore(el,feed.firstChild);
  while(feed.children.length>14) feed.removeChild(feed.lastChild);
  document.getElementById('btnask').disabled=false;

  // Col droite insights
  var now=new Date();
  var ts=now.getHours().toString().padStart(2,'0')+':'+now.getMinutes().toString().padStart(2,'0')+':'+now.getSeconds().toString().padStart(2,'0');
  var iph=document.getElementById('insph'); if(iph) iph.remove();
  var ins=document.createElement('div');
  ins.className='fi';
  ins.style.cssText='background:'+c[0]+';border-left-color:'+c[1]+';margin-bottom:5px;';
  ins.innerHTML='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">'
    +'<p style="font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.13em;color:'+c[2]+';">'+title+'</p>'
    +'<span style="font-size:7px;font-weight:700;color:var(--tx3);font-variant-numeric:tabular-nums;">'+ts+'</span></div>'
    +'<p style="font-size:10px;color:var(--tx2);line-height:1.6;">'+body+'</p>';
  var wrap=document.getElementById('ins');
  wrap.insertBefore(ins,wrap.firstChild);
  while(wrap.children.length>12) wrap.removeChild(wrap.lastChild);
}

// ── API ───────────────────────────────────────────────────────────────────────
async function callAI(prompt, max){
  max=max||1200;
  var res=await fetch('proxy_ia.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({prompt:prompt,max_tokens:max,csrf_token:CSRF})});
  if(!res.ok) throw new Error('HTTP '+res.status);
  var d=await res.json();
  if(d.error) throw new Error(d.error.message||'Erreur API');
  return d.choices&&d.choices[0]&&d.choices[0].message ? d.choices[0].message.content : '';
}

// ── AUTO-IA (silencieuse) ─────────────────────────────────────────────────────
async function autoAI(text, sc, risk){
  var th=document.getElementById('think'); if(th) th.style.display='flex';
  var hc=HIST.length?'\nHistorique:\n'+HIST.map(function(h){return h.date+'('+h.duree+'min): '+h.resume;}).join('\n'):'';
  var p='Tu es psychologue clinicien superviseur. Analyse EN DIRECT ce verbatim partiel.\nPatient: '+PAT+' | Séance n°'+SESN
    +'\n[Données contextuelles silencieuses — ne pas afficher à l\'utilisateur] Risque calculé: '+risk+'% | Urgence:'+Math.round(sc.u)+' | Détresse:'+Math.round(sc.d)+' | Anxiété:'+Math.round(sc.a)+hc
    +'\nVerbatim (fin): "'+text.slice(-700)+'"'
    +'\nJSON uniquement sans markdown:\n{"alerte":null,"observation":"1 phrase clinique","theme":"1 thème à explorer","question":"1 question pour le patient","hypothese":null,"code_cim":"code CIM-11 ou null"}';
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
      var d=document.createElement('div');
      d.className='dp fu';
      d.innerHTML=(ai.code_cim?'<span class="dc">'+ai.code_cim+'</span>':'')
        +'<p style="font-size:10px;color:#93c5fd;line-height:1.55;">'+ai.hypothese+'</p>';
      b.insertBefore(d,b.firstChild);
      if(b.children.length>5) b.removeChild(b.lastChild);
    }
  }catch(e){ console.warn('autoAI:',e); }
  finally{ if(th) th.style.display='none'; }
}

// ── QUESTION LIBRE ────────────────────────────────────────────────────────────
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
    resp.innerHTML='<div class="card2 fu" style="margin-top:5px;">'
      +'<p class="lbl" style="color:var(--ac2);margin-bottom:4px;">Réponse IA</p>'
      +'<p style="font-size:10px;color:var(--tx2);line-height:1.6;">'+raw+'</p></div>';
    addFeed('info','Q: '+q.slice(0,40)+'…',raw.slice(0,110)+'…');
  }catch(e){
    resp.innerHTML='<p style="font-size:10px;color:#f87171;margin-top:4px;">Erreur — Réessayez.</p>';
  }
}

// ══════════════════════════════════════════════════════════════════════════════
// RAPPORT CLINIQUE — style dossier médical imprimable
// ══════════════════════════════════════════════════════════════════════════════
async function genReport(){
  var text=document.getElementById('tr').value.trim();
  if(text.length<15){ ntf('ntr','Volume insuffisant pour la synthèse.','wa'); return; }

  var res=analyze(text);
  var sc=res.sc, risk=res.risk;

  document.getElementById('btngen').disabled=true;
  var body=document.getElementById('rpbody');
  body.innerHTML='<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:50px 20px;opacity:.5;">'
    +'<div class="dots" style="margin-bottom:10px;"><span></span><span></span><span></span></div>'
    +'<p class="lbl" style="color:var(--ac2);text-align:center;">Rédaction du compte-rendu clinique en cours…</p></div>';

  var notes=document.getElementById('notes').value;
  var hc=HIST.length ? HIST.map(function(h){ return 'Séance '+h.date+' ('+h.duree+'min) : '+h.resume; }).join('\n') : 'Première séance';

  var prompt = 'Tu es psychologue clinicien senior (20 ans d\'expérience), expert TCC/ACT/EMDR/thérapies humanistes.'
    +'\nRédige un compte-rendu clinique confidentiel, professionnel, rédigé comme un vrai dossier médical.'
    +'\nPatient : '+PAT+' | Séance n°'+SESN+' | Date : '+DATED+' | Dr. '+DR
    +'\n[Données silencieuses pour contexte — ne pas mentionner les chiffres bruts dans le texte] Risque lexical : '+risk+'/100 | Urgence : '+Math.round(sc.u)+' | Détresse : '+Math.round(sc.d)+' | Anxiété : '+Math.round(sc.a)+' | Résilience : '+Math.round(Math.abs(sc.po)+Math.abs(sc.so))
    +'\nNotes du praticien : '+(notes||'Aucune')
    +'\nHistorique :\n'+hc
    +'\nVerbatim complet :\n"""'+text+'"""'
    +'\n\nJSON uniquement, sans markdown, sans balises, sans commentaires :'
    +'\n{'
    +'\n  "synthese_courte": "2-3 phrases résumant la séance de manière clinique et nuancée",'
    +'\n  "presentation_clinique": "5-7 phrases sur la présentation du patient aujourd\'hui : tenue, contact, psychomotricité, expression, attitude",'
    +'\n  "contenu_seance": "5-8 phrases sur les thèmes abordés, les matériaux cliniques apportés par le patient, les associations, les récits",'
    +'\n  "etat_affectif": "4-6 phrases sur l\'humeur observée, les affects exprimés, la régulation émotionnelle, les fluctuations au cours de la séance",'
    +'\n  "processus_therapeutique": "4-6 phrases sur la qualité de l\'alliance, la dynamique transférentielle, l\'engagement thérapeutique du patient, la résistance éventuelle",'
    +'\n  "elements_vigilance": "3-5 phrases sur les points de vigilance clinique, les risques identifiés ou absents, ce qui doit être surveillé",'
    +'\n  "axes_travail": "4-6 phrases sur les axes cliniques prioritaires issus de cette séance",'
    +'\n  "hypotheses_diag": ["Hypothèse 1 avec code CIM-11", "Hypothèse 2 avec code CIM-11"],'
    +'\n  "objectifs_prochaine": ["Objectif concret 1", "Objectif concret 2", "Objectif concret 3"],'
    +'\n  "plan_therapeutique": "Plan narratif pour les 3 à 5 prochaines séances : approche, techniques, jalons",'
    +'\n  "recommandations": "Orientations éventuelles : psychiatre, médecin, bilan, hospitalisation ou null",'
    +'\n  "lettre_confrere": "Courrier de liaison formel à un confrère si pertinent, ou null",'
    +'\n  "niveau_risque": "faible|modéré|élevé|critique"'
    +'\n}';

  try{
    var raw=await callAI(prompt, 3500);
    var ai;
    try{ ai=JSON.parse(raw.replace(/```json\n?|\n?```/g,'').trim()); }
    catch(e){ throw new Error('Le modèle n\'a pas renvoyé de JSON valide. Réessayez.'); }

    var snap = {
      risk:risk,
      tRisk:tR.slice(), tResil:tRs.slice(), tDetresse:tD.slice(), tAnxiete:tA.slice(),
      emoPoints:emoP.slice(),
      radarImg:   (function(){try{return document.getElementById('radarChart').toDataURL('image/png');}catch(e){return null;}})(),
      timelineImg:(function(){try{return document.getElementById('tlChart').toDataURL('image/png');}catch(e){return null;}})(),
    };
    lastRpt={ai:ai, sc:sc, risk:risk, snap:snap, text:text, date:new Date().toLocaleDateString('fr-FR')};
    document.getElementById('btnpdf').disabled=false;
    renderRpt(lastRpt);

    // Mise à jour hypothèses col gauche
    if(Array.isArray(ai.hypotheses_diag)){
      var diagb=document.getElementById('diagb');
      var diagph=document.getElementById('diagph'); if(diagph) diagph.remove();
      diagb.innerHTML='';
      ai.hypotheses_diag.forEach(function(h){
        var d=document.createElement('div');
        d.className='dp fu';
        var m=h.match(/([A-Z][A-Z0-9]+\.?[0-9]*)/);
        d.innerHTML=(m?'<span class="dc">'+m[1]+'</span>':'')
          +'<p style="font-size:10px;color:#93c5fd;line-height:1.55;">'+h+'</p>';
        diagb.appendChild(d);
      });
    }
    addFeed('ok','Bilan généré','Niveau de risque : '+ai.niveau_risque);

  }catch(err){
    body.innerHTML='<div style="padding:40px 20px;text-align:center;">'
      +'<p style="color:#f87171;font-weight:700;font-size:12px;margin-bottom:6px;">Erreur de génération</p>'
      +'<p style="color:var(--tx3);font-size:10px;line-height:1.6;">'+err.message+'</p></div>';
  }finally{
    document.getElementById('btngen').disabled=false;
  }
}

// ── renderRpt — style dossier médical ─────────────────────────────────────────
var rptTlC=null, rptEmoC=null;

function renderRpt(lr){
  if(!lr) return;
  var ai=lr.ai, sn=lr.snap||{};

  var risqColors={
    faible:  {bg:'#f0faf5', border:'#a3d9b8', dot:'#1a7a4a', lbl:'#145a38', text:'#1e4a2e'},
    modéré:  {bg:'#fffbf0', border:'#f5d58a', dot:'#b97a00', lbl:'#7c4a00', text:'#4a3000'},
    élevé:   {bg:'#fff5f5', border:'#f5a3a3', dot:'#c0392b', lbl:'#7c1a1a', text:'#4a0f0f'},
    critique:{bg:'#fff0f0', border:'#ff7070', dot:'#ff2040', lbl:'#900000', text:'#500000'}
  };
  var niv=ai.niveau_risque||'faible';
  var rc=risqColors[niv]||risqColors['faible'];

  var hasTimeline = sn.tRisk && sn.tRisk.length > 1;
  var hasEmo      = sn.emoPoints && sn.emoPoints.length > 1;

  var html='<div class="rpt-wrap">';

  // ── EN-TÊTE ──────────────────────────────────────────────────────────────
  html+='<div class="rpt-header">'
    +'<div>'
      +'<p class="rpt-header-title">Compte-Rendu de Consultation Psychologique</p>'
      +'<p class="rpt-header-name">'+escH(PAT)+'</p>'
      +'<p class="rpt-header-meta">'
        +'Séance n°'+SESN+' &nbsp;·&nbsp; '+escH(DATED)+'&nbsp;·&nbsp; Dr. '+escH(DR)
        +'<br>Durée estimée : '+Math.floor(lr.snap&&lr.snap.dur||secs/60||0)+' min'
        +' &nbsp;·&nbsp; Document confidentiel'
      +'</p>'
    +'</div>'
    +'<div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">'
      +'<span class="rpt-header-badge">'+niv.toUpperCase()+'</span>'
      +'<span style="font-family:\'Plus Jakarta Sans\',sans-serif;font-size:7.5px;font-weight:700;color:rgba(255,255,255,.4);">PsySpace Pro · IA assistée</span>'
    +'</div>'
  +'</div>';

  // ── CORPS ────────────────────────────────────────────────────────────────
  html+='<div class="rpt-body">';

  // NIVEAU DE RISQUE
  html+='<div class="rpt-risk-band" style="background:'+rc.bg+';border-color:'+rc.border+';">'
    +'<div class="rpt-risk-dot" style="background:'+rc.dot+';"></div>'
    +'<div>'
      +'<p class="rpt-risk-label" style="color:'+rc.lbl+';">Niveau de risque évalué · '+niv.charAt(0).toUpperCase()+niv.slice(1)+'</p>'
      +'<p class="rpt-risk-text" style="color:'+rc.text+';">'+escH(ai.synthese_courte||'—')+'</p>'
    +'</div>'
  +'</div>';

  // PRÉSENTATION CLINIQUE
  if(ai.presentation_clinique){
    html+=rptSec('Présentation clinique',ai.presentation_clinique);
  }

  // CONTENU DE LA SÉANCE
  if(ai.contenu_seance){
    html+=rptSec('Contenu de la séance',ai.contenu_seance);
  }

  // ÉTAT AFFECTIF + PROCESSUS (grille 2 colonnes)
  if(ai.etat_affectif||ai.processus_therapeutique){
    html+='<div class="rpt-section"><div class="rpt-section-title">État affectif & Processus thérapeutique</div>'
      +'<div class="rpt-grid2">';
    if(ai.etat_affectif){
      html+='<div class="rpt-col-block"><p class="rpt-col-label">État affectif & humeur</p>'
        +'<p class="rpt-prose">'+escH(ai.etat_affectif)+'</p></div>';
    }
    if(ai.processus_therapeutique){
      html+='<div class="rpt-col-block"><p class="rpt-col-label">Alliance & processus</p>'
        +'<p class="rpt-prose">'+escH(ai.processus_therapeutique)+'</p></div>';
    }
    html+='</div></div>';
  }

  // TIMELINE ÉMOTIONNELLE (intégrée dans le rapport)
  if(hasTimeline){
    html+='<div class="rpt-section">'
      +'<div class="rpt-section-title">Dynamique émotionnelle · séance</div>'
      +'<div style="height:70px;position:relative;border-radius:6px;overflow:hidden;background:var(--rp-bg2);border:1px solid var(--rp-border);padding:6px 8px;">'
        +'<canvas id="rpt-tl"></canvas>'
      +'</div>'
      +'<div style="display:flex;justify-content:space-between;margin-top:4px;">';
    [['#ef4444','Risque'],['#10b981','Résilience'],['#f59e0b','Détresse'],['#8b5cf6','Anxiété']].forEach(function(p){
      html+='<div style="display:flex;align-items:center;gap:3px;">'
        +'<span style="width:10px;height:2px;background:'+p[0]+';display:inline-block;border-radius:1px;"></span>'
        +'<span style="font-family:\'Plus Jakarta Sans\',sans-serif;font-size:7px;font-weight:700;color:var(--rp-tx3);text-transform:uppercase;">'+p[1]+'</span>'
      +'</div>';
    });
    html+='</div></div>';
  }

  // VIGILANCE
  if(ai.elements_vigilance){
    html+='<div class="rpt-section">'
      +'<div class="rpt-section-title" style="color:var(--rp-er);">Éléments de vigilance clinique</div>'
      +'<div class="rpt-vigilance">'
        +'<p class="rpt-vigilance-label">Points d\'attention</p>'
        +'<p class="rpt-vigilance-text">'+escH(ai.elements_vigilance)+'</p>'
      +'</div>'
    +'</div>';
  }

  // AXES DE TRAVAIL
  if(ai.axes_travail){
    html+='<div class="rpt-section">'
      +'<div class="rpt-section-title">Axes de travail thérapeutique</div>'
      +'<div class="rpt-highlight"><p class="rpt-prose">'+escH(ai.axes_travail)+'</p></div>'
    +'</div>';
  }

  // HYPOTHÈSES DIAGNOSTIQUES
  if(Array.isArray(ai.hypotheses_diag)&&ai.hypotheses_diag.length){
    html+='<div class="rpt-section">'
      +'<div class="rpt-section-title">Hypothèses diagnostiques · Classification CIM-11</div>';
    ai.hypotheses_diag.forEach(function(h){
      var m=h.match(/([A-Z][A-Z0-9]+\.?[0-9A-Z]*)/);
      html+='<div class="rpt-diag-row">'
        +(m?'<span class="rpt-diag-code">'+m[1]+'</span>':'')
        +'<p class="rpt-diag-text">'+escH(h)+'</p>'
      +'</div>';
    });
    html+='</div>';
  }

  // OBJECTIFS PROCHAINE SÉANCE
  if(Array.isArray(ai.objectifs_prochaine)&&ai.objectifs_prochaine.length){
    html+='<div class="rpt-section">'
      +'<div class="rpt-section-title">Objectifs · Prochaine séance</div>';
    ai.objectifs_prochaine.forEach(function(o,i){
      html+='<div class="rpt-obj-row">'
        +'<span class="rpt-obj-num">'+(i+1)+'.</span>'
        +'<p class="rpt-obj-text">'+escH(o)+'</p>'
      +'</div>';
    });
    html+='</div>';
  }

  // PLAN THÉRAPEUTIQUE
  if(ai.plan_therapeutique){
    html+='<div class="rpt-section">'
      +'<div class="rpt-section-title">Plan thérapeutique · Prochaines séances</div>'
      +'<div class="rpt-plan"><p class="rpt-plan-text">'+escH(ai.plan_therapeutique)+'</p></div>'
    +'</div>';
  }

  // RECOMMANDATIONS
  if(ai.recommandations && ai.recommandations!=='null' && ai.recommandations!==''){
    html+=rptSec('Recommandations & orientations',ai.recommandations);
  }

  // COURRIER CONFRÈRE
  if(ai.lettre_confrere && ai.lettre_confrere!=='null' && ai.lettre_confrere!==''){
    html+='<div class="rpt-section">'
      +'<div class="rpt-section-title">Courrier de liaison</div>'
      +'<div class="rpt-letter"><p class="rpt-letter-pre">'+escH(ai.lettre_confrere)+'</p></div>'
    +'</div>';
  }

  html+='</div>'; // /rpt-body

  // FOOTER
  html+='<div class="rpt-footer">'
    +'<p class="rpt-footer-txt">PsySpace Pro · Document confidentiel · Usage clinique exclusif</p>'
    +'<p class="rpt-footer-txt">Généré le '+new Date().toLocaleDateString('fr-FR')+' · IA assistée</p>'
  +'</div>';

  html+='</div>'; // /rpt-wrap

  document.getElementById('rpbody').innerHTML=html;
  setTimeout(function(){ renderRptCharts(sn); }, 80);
}

function rptSec(title, content){
  return '<div class="rpt-section">'
    +'<div class="rpt-section-title">'+title+'</div>'
    +'<p class="rpt-prose">'+escH(content)+'</p>'
  +'</div>';
}
function escH(s){
  if(!s) return '';
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;')
    .replace(/\n/g,'<br>');
}

// ── Charts dans le rapport ────────────────────────────────────────────────────
function renderRptCharts(sn){
  if(!sn) return;
  var tlEl=document.getElementById('rpt-tl');
  if(tlEl && sn.tRisk && sn.tRisk.length>1){
    if(rptTlC) rptTlC.destroy();
    var ctx=tlEl.getContext('2d');
    function grd(c,c1,c2){var g=c.chart.ctx.createLinearGradient(0,0,0,70);g.addColorStop(0,c1);g.addColorStop(1,c2);return g;}
    rptTlC=new Chart(ctx,{type:'line',
      data:{labels:sn.tRisk.map(function(_,i){return i;}),datasets:[
        {label:'Risque',     data:sn.tRisk,    borderColor:'#ef4444',borderWidth:1.5,pointRadius:0,fill:true,tension:.4,backgroundColor:function(c){return grd(c,'rgba(239,68,68,.12)','rgba(239,68,68,0)');}},
        {label:'Résilience', data:sn.tResil,   borderColor:'#10b981',borderWidth:1.5,pointRadius:0,fill:false,tension:.4},
        {label:'Détresse',   data:sn.tDetresse,borderColor:'#f59e0b',borderWidth:1.5,pointRadius:0,fill:false,tension:.4,borderDash:[3,3]},
        {label:'Anxiété',    data:sn.tAnxiete, borderColor:'#8b5cf6',borderWidth:1.5,pointRadius:0,fill:false,tension:.4,borderDash:[2,4]}
      ]},
      options:{responsive:true,maintainAspectRatio:false,animation:{duration:500},
        plugins:{legend:{display:false}},
        scales:{
          x:{display:false},
          y:{min:0,max:100,ticks:{display:false},grid:{color:'rgba(0,0,0,.04)',drawBorder:false},border:{display:false}}
        }
      }
    });
  }
}

// ── LOAD PREV ─────────────────────────────────────────────────────────────────
function loadPrev(txt){
  ovO('Charger ce résumé dans les notes cliniciennes ?', function(){
    var n=document.getElementById('notes');
    n.value=(n.value?n.value+'\n\n':'')+'[Séance précédente]\n'+txt.slice(0,500);
  });
}

// ── EXPORT PDF ────────────────────────────────────────────────────────────────
function exportPDF(){
  if(!lastRpt){ ntf('narch',"Générez d'abord le bilan.",'wa'); return; }
  var j=window.jspdf.jsPDF;
  var doc=new j({unit:'mm',format:'a4'});
  var W=210, M=18, y=M;

  function ln(txt, o){
    o=o||{};
    var sz=o.sz||9.5, b=o.b||false, it=o.it||false;
    var c=o.c||[26,40,90], ind=o.in_||0, lh=o.lh||5.8;
    var font=b?'bold':(it?'italic':'normal');
    doc.setFontSize(sz);
    doc.setFont(b?'helvetica':'times', font);
    doc.setTextColor(c[0],c[1],c[2]);
    doc.splitTextToSize(String(txt),W-M*2-ind).forEach(function(l){
      if(y>272){doc.addPage();y=M;}
      doc.text(l,M+ind,y); y+=lh;
    });
    y+=1.2;
  }
  function hr(col){ col=col||[190,198,215]; doc.setDrawColor(col[0],col[1],col[2]);doc.setLineWidth(.1);doc.line(M,y,W-M,y);y+=3.5; }
  function sec(title, txt, titleCol){
    titleCol=titleCol||[44,58,140];
    doc.setDrawColor(titleCol[0],titleCol[1],titleCol[2]);
    doc.setFillColor(titleCol[0],titleCol[1],titleCol[2]);
    doc.rect(M,y-1.5,2,8,'F');
    ln(title.toUpperCase(),{sz:7,b:true,c:titleCol,in_:4,lh:4.5});
    if(txt) ln(txt,{sz:9.5,c:[55,75,110],in_:4,lh:5.5,it:false});
    y+=1.5;
  }

  // Header
  doc.setFillColor(44,58,140); doc.rect(0,0,W,32,'F');
  doc.setFillColor(255,255,255); doc.setOpacity(.12); doc.rect(W-40,0,40,32,'F');
  doc.setOpacity(1);
  doc.setFontSize(14);doc.setFont('times','bold');doc.setTextColor(255,255,255);
  doc.text('Compte-Rendu de Consultation',M,12);
  doc.setFontSize(9);doc.setFont('helvetica','normal');doc.setTextColor(180,195,230);
  doc.text('PsySpace Pro · Document confidentiel · Usage clinique exclusif',M,18);
  doc.setFontSize(8.5);doc.setTextColor(200,210,240);
  doc.text('Patient : '+PAT+'   ·   Séance n°'+SESN+'   ·   '+lastRpt.date+'   ·   Dr. '+DR,M,24);
  doc.setFontSize(8);doc.setFont('helvetica','bold');
  var niv=(lastRpt.ai.niveau_risque||'').toUpperCase();
  var nc=[44,58,140]; // default bleu
  if(niv==='FAIBLE') nc=[20,90,56]; else if(niv==='MODÉRÉ') nc=[120,74,0]; else if(niv==='ÉLEVÉ'||niv==='CRITIQUE') nc=[160,30,30];
  doc.setTextColor(nc[0],nc[1],nc[2]);
  doc.setFillColor(255,255,255);
  doc.roundedRect(W-M-26,6,24,10,2,2,'F');
  doc.text(niv,W-M-14,12.5,{align:'center'});
  y=40;

  var ai=lastRpt.ai;

  if(ai.synthese_courte){
    doc.setFillColor(235,240,252);doc.roundedRect(M,y-1.5,W-M*2,14,2,2,'F');
    ln(ai.synthese_courte,{sz:10.5,c:[26,40,90],lh:5.5,it:true,in_:2});
    y+=2;
  }

  hr();
  sec('Présentation clinique',ai.presentation_clinique,[44,58,140]);
  sec('Contenu de la séance',ai.contenu_seance,[44,58,140]);
  sec('État affectif & humeur',ai.etat_affectif,[140,90,20]);
  sec('Alliance & processus thérapeutique',ai.processus_therapeutique,[80,44,140]);

  if(ai.elements_vigilance){
    doc.setFillColor(255,240,240);doc.roundedRect(M,y-1,W-M*2,4,'F');
    sec('Éléments de vigilance clinique',ai.elements_vigilance,[160,30,30]);
  }

  sec('Axes de travail thérapeutique',ai.axes_travail,[20,100,60]);

  if(Array.isArray(ai.hypotheses_diag)&&ai.hypotheses_diag.length){
    sec('Hypothèses diagnostiques · CIM-11',null,[44,58,140]);
    ai.hypotheses_diag.forEach(function(h){ ln('• '+h,{in_:6,sz:9.5,c:[30,60,110]}); });
    y+=1;
  }
  if(Array.isArray(ai.objectifs_prochaine)&&ai.objectifs_prochaine.length){
    sec('Objectifs · Prochaine séance',null,[20,100,60]);
    ai.objectifs_prochaine.forEach(function(o,i){ ln((i+1)+'. '+o,{in_:6,sz:9.5,c:[20,80,55]}); });
    y+=1;
  }
  if(ai.plan_therapeutique) sec('Plan thérapeutique',ai.plan_therapeutique,[44,58,140]);
  if(ai.recommandations&&ai.recommandations!=='null') sec('Recommandations',ai.recommandations,[80,44,140]);
  if(ai.lettre_confrere&&ai.lettre_confrere!=='null'){
    hr();
    sec('Courrier de liaison',ai.lettre_confrere,[44,58,140]);
  }

  // Snapshot radar
  var sn=lastRpt.snap;
  if(sn&&sn.radarImg){
    if(y>220){doc.addPage();y=M;}
    hr();
    doc.setFontSize(7);doc.setFont('helvetica','bold');doc.setTextColor(44,58,140);
    doc.text('PROFIL PSYCHO-ÉMOTIONNEL',M,y);y+=4;
    try{ doc.addImage(sn.radarImg,'PNG',M,y,55,55); }catch(e){}
    y+=60;
  }
  if(sn&&sn.timelineImg){
    if(y>220){doc.addPage();y=M;}
    doc.setFontSize(7);doc.setFont('helvetica','bold');doc.setTextColor(44,58,140);
    doc.text('TIMELINE ÉMOTIONNELLE · ÉVOLUTION SÉANCE',M,y);y+=4;
    try{ doc.addImage(sn.timelineImg,'PNG',M,y,W-M*2,22); y+=26; }catch(e){}
  }

  var notes=document.getElementById('notes').value;
  if(notes){ hr(); sec('Notes cliniciennes',notes,[80,80,80]); }
  hr();
  doc.setFontSize(7);doc.setFont('helvetica','normal');doc.setTextColor(140,150,170);
  doc.text('PsySpace Pro 2026 · Confidentiel · Usage clinique exclusif',M,289);
  doc.text('Généré le '+lastRpt.date,W-M,289,{align:'right'});

  doc.save('CR_'+PAT.replace(/\s+/g,'_')+'_S'+SESN+'_'+lastRpt.date.replace(/\//g,'-')+'.pdf');
}

// ── ARCHIVER ──────────────────────────────────────────────────────────────────
async function finalize(){
  var tr=document.getElementById('tr').value.trim();
  if(!tr){ ntf('narch','Aucune transcription à archiver.','wa'); return; }
  ovO('Archiver la séance n°'+SESN+' de <strong>'+escH(PAT)+'</strong> ?', async function(){
    ntf('narch','Archivage en cours…','in',0);
    var fd=new FormData();
    fd.append('csrf_token',CSRF);
    fd.append('transcript',tr);
    fd.append('resume',lastRpt?JSON.stringify(lastRpt.ai):'');
    fd.append('duree',Math.floor(secs/60));
    fd.append
