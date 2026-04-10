<?php
// --- 1. SÉCURITÉ DES SESSIONS & HEADERS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}

session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

// --- 2. ANTI VOL DE SESSION ---
if (isset($_SESSION['user_ip']) && isset($_SESSION['user_agent'])) {
    if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR'] || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        header("Location: login.php?error=hijack");
        exit();
    }
}

// --- 3. GÉNÉRATION DU PARE-FEU CSP ---
$nonce = base64_encode(random_bytes(16));
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' 'strict-dynamic' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none';");
include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

// --- 4. VÉRIFICATIONS ANTI-IDOR & VALIDATION ---
$patient_raw = $_GET['patient_name'] ?? '';
if (!preg_match('/^[\p{L}\s\'\-\.]{1,100}$/u', $patient_raw)) { header("Location: dashboard.php"); exit(); }
$patient_selected = trim($patient_raw);
$doctor_id = (int)$_SESSION['id'];
$nom_docteur = $_SESSION['nom'] ?? 'Docteur';
$appointment_id = (int)($_GET['id'] ?? 0);

if (!$appointment_id) { header("Location: dashboard.php"); exit(); }

// Vérification stricte : Ce rendez-vous appartient-il bien à ce docteur ?
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
$stmt2->bind_param("ii", $patient_id, $doctor_id); $stmt2->execute();
$res2 = $stmt2->get_result();
while ($row2 = $res2->fetch_assoc()) $prev_consults[] = $row2;
$stmt2->close();

$session_num = count($prev_consults) + 1;
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
$_SESSION['pending_appointment_id'] = $appointment_id;   // lu par save_consultation.php
$_SESSION['pending_patient_name']   = $patient_selected;
$_SESSION['pending_doctor_id']      = $doctor_id;

$appt_date = date('d/m/Y');
$stmt3 = $conn->prepare("SELECT app_date FROM appointments WHERE id=? LIMIT 1");
$stmt3->bind_param("i", $appointment_id); $stmt3->execute();
$r3 = $stmt3->get_result()->fetch_assoc(); $stmt3->close();
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

<!-- Scripts sécurisés avec le Nonce -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" nonce="<?= $nonce ?>"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" nonce="<?= $nonce ?>"></script>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- CSS sécurisé avec le Nonce -->
<style nonce="<?= $nonce ?>">
*{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#0a0e1a;--s1:#111827;--s2:rgba(17,24,39,.75);
/* ... LA SUITE DE TON CSS ICI ... */
  --b:rgba(255,255,255,.06);--b2:rgba(255,255,255,.04);
  --ac:#6366f1;--ok:#10b981;--wa:#f59e0b;--er:#ef4444;--in:#38bdf8;
  --tx:#f1f5f9;--su:#4b5563;
}
html,body{height:100%;font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--tx);overflow:hidden;}
::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-thumb{background:#1f2937;border-radius:4px;}

.app{display:grid;grid-template-rows:52px 1fr;height:100vh;}
.g3{display:grid;grid-template-columns:260px 1fr 330px;height:calc(100vh - 52px);overflow:hidden;}
.col{overflow-y:auto;display:flex;flex-direction:column;gap:10px;padding:12px 11px;}

/* Topbar */
.top{display:flex;align-items:center;justify-content:space-between;padding:0 18px;
  background:rgba(10,14,26,.97);backdrop-filter:blur(20px);border-bottom:1px solid var(--b);}

/* Cards */
.card{background:var(--s1);border:1px solid var(--b);border-radius:16px;padding:13px;}
.card2{background:var(--s2);border:1px solid var(--b2);border-radius:11px;padding:10px;}

/* Labels */
.lbl{font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.2em;color:var(--su);}

/* Chips */
.chip{display:inline-flex;align-items:center;gap:3px;padding:2px 9px;border-radius:99px;
  font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;border:1px solid;}
.ck{background:rgba(16,185,129,.08);color:#34d399;border-color:rgba(16,185,129,.2);}
.cw{background:rgba(245,158,11,.08);color:#fcd34d;border-color:rgba(245,158,11,.2);}
.ce{background:rgba(239,68,68,.1);color:#f87171;border-color:rgba(239,68,68,.22);}
.ci{background:rgba(99,102,241,.1);color:#a5b4fc;border-color:rgba(99,102,241,.22);}
.cs{background:rgba(75,85,99,.1);color:#6b7280;border-color:rgba(75,85,99,.2);}
.ccrit{background:rgba(239,68,68,.15);color:#ff6060;border-color:rgba(239,68,68,.4);animation:gcrit 1.4s infinite;}
@keyframes gcrit{50%{box-shadow:0 0 10px rgba(239,68,68,.4);}}

/* Buttons */
.btn{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.15em;
  border-radius:10px;padding:9px 15px;border:none;cursor:pointer;transition:all .18s;
  display:inline-flex;align-items:center;justify-content:center;gap:5px;}
.ba{background:var(--ac);color:#fff;}.ba:hover{background:#4f46e5;box-shadow:0 4px 18px rgba(99,102,241,.35);}
.bok{background:rgba(16,185,129,.1);color:#34d399;border:1px solid rgba(16,185,129,.2);}.bok:hover{background:rgba(16,185,129,.2);}
.bg{background:transparent;color:var(--su);border:1px solid var(--b);}.bg:hover{color:#fff;border-color:rgba(255,255,255,.2);}
.bq{background:rgba(99,102,241,.08);color:#a5b4fc;border:1px solid rgba(99,102,241,.15);}
.btn:disabled{opacity:.25;cursor:not-allowed;pointer-events:none;}

/* Textarea */
.ta{background:rgba(10,14,26,.6);border:1px solid rgba(255,255,255,.07);color:#cbd5e1;
  border-radius:13px;resize:none;outline:none;width:100%;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;line-height:1.9;padding:13px;
  transition:border-color .25s;}
.ta:focus{border-color:rgba(99,102,241,.35);}
.ta::placeholder{color:#374151;font-style:italic;}
.nta{background:rgba(245,158,11,.03);border:1px solid rgba(245,158,11,.1);color:#fcd34d;
  border-radius:10px;resize:none;outline:none;width:100%;
  font-family:'Plus Jakarta Sans',sans-serif;font-size:11.5px;line-height:1.7;padding:10px;}
.nta:focus{border-color:rgba(245,158,11,.28);}
.nta::placeholder{color:rgba(245,158,11,.2);font-style:italic;}

/* Toast */
.toast{border-radius:10px;padding:8px 12px;font-size:10px;font-weight:700;
  display:flex;align-items:center;gap:6px;}
.tok{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.22);color:#6ee7b7;}
.ter{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.22);color:#fca5a5;}
.twa{background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.22);color:#fcd34d;}
.tin{background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.22);color:#a5b4fc;}

/* Mic */
.mic{width:46px;height:46px;border-radius:50%;border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:all .25s;flex-shrink:0;}
.mic-idle{background:rgba(99,102,241,.15);border:2px solid rgba(99,102,241,.3);}
.mic-live{background:#ef4444;animation:mring 1.3s ease-out infinite;}
.mic-done{background:rgba(16,185,129,.15);border:2px solid rgba(16,185,129,.3);}
@keyframes mring{0%{box-shadow:0 0 0 0 rgba(239,68,68,.5)}70%{box-shadow:0 0 0 14px rgba(239,68,68,0)}100%{box-shadow:0 0 0 0 rgba(239,68,68,0)}}

/* Feed */
.fi{padding:9px 11px;border-radius:11px;border-left:2.5px solid;margin-bottom:6px;
  animation:sin .3s ease forwards;}
@keyframes sin{from{opacity:0;transform:translateX(8px)}to{opacity:1;transform:none}}
@keyframes fup{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.fu{animation:fup .3s ease forwards;}

/* Risk bar */
.rb{height:5px;border-radius:3px;transition:width 1.2s cubic-bezier(.4,0,.2,1),background .6s;}

/* Score bars */
.sb{display:flex;align-items:center;gap:8px;margin-bottom:7px;}
.sbl{font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.09em;width:54px;flex-shrink:0;}
.sbt{flex:1;height:4px;background:rgba(255,255,255,.04);border-radius:2px;overflow:hidden;}
.sbf{height:4px;border-radius:2px;transition:width 1.2s cubic-bezier(.4,0,.2,1);}
.sbn{font-size:9px;font-weight:900;width:20px;text-align:right;flex-shrink:0;}

/* Right bars */
.ar{display:flex;align-items:center;gap:8px;padding:4px 0;}
.arl{font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;width:56px;flex-shrink:0;}
.art{flex:1;height:5px;background:rgba(255,255,255,.04);border-radius:3px;overflow:hidden;}
.arf{height:5px;border-radius:3px;transition:width 1.3s cubic-bezier(.4,0,.2,1);}
.arn{font-size:9px;font-weight:900;width:22px;text-align:right;flex-shrink:0;}

/* Heatmap */
.hms{display:flex;gap:2px;height:18px;}
.hmc{flex:1;border-radius:3px;background:#1f2937;transition:background .5s;}

/* Diag pill */
.dp{display:flex;align-items:flex-start;gap:7px;padding:8px 10px;
  border-radius:10px;border:1px solid rgba(56,189,248,.14);background:rgba(56,189,248,.04);margin-bottom:5px;}
.dc{font-size:8.5px;font-weight:900;color:#38bdf8;background:rgba(56,189,248,.12);
  border-radius:4px;padding:2px 5px;white-space:nowrap;flex-shrink:0;margin-top:1px;}

/* Dots loader */
.dots span{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--ac);
  margin:0 2px;animation:db 1.2s ease-in-out infinite;}
.dots span:nth-child(2){animation-delay:.2s}.dots span:nth-child(3){animation-delay:.4s}
@keyframes db{0%,80%,100%{transform:scale(.4);opacity:.3}40%{transform:scale(1);opacity:1}}

.pulse{animation:pls 2s cubic-bezier(.4,0,.6,1) infinite;}
@keyframes pls{0%,100%{opacity:1}50%{opacity:.25}}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}

/* Tabs */
.tab{font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.13em;
  padding:5px 11px;border-radius:8px;cursor:pointer;border:none;background:transparent;color:var(--su);}
.tab.on{background:rgba(99,102,241,.12);color:#a5b4fc;border:1px solid rgba(99,102,241,.22);}

.rs{border-radius:12px;padding:12px 14px;border:1px solid;margin-bottom:8px;}

/* Overlay */
.ov{position:fixed;inset:0;background:rgba(0,0,0,.88);backdrop-filter:blur(12px);
  z-index:999;display:flex;align-items:center;justify-content:center;
  opacity:0;pointer-events:none;transition:opacity .22s;}
.ov.open{opacity:1;pointer-events:all;}
.ovb{background:rgba(10,14,26,.97);border:1px solid rgba(255,255,255,.08);
  border-radius:20px;padding:24px;max-width:400px;width:calc(100%-32px);
  transform:scale(.96);transition:transform .22s;}
.ov.open .ovb{transform:scale(1);}

/* Right col divider */
.rd{height:1px;background:var(--b);margin:10px 12px;}
</style>
</head>
<body>
<div class="app">

<!-- TOPBAR -->
<div class="top">
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="dashboard.php" style="color:#4b5563;font-size:18px;font-weight:700;text-decoration:none;padding:4px 6px;border-radius:8px;transition:all .2s;" onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#4b5563'">←</a>
    <div style="width:1px;height:20px;background:var(--b);"></div>
    <div style="width:32px;height:32px;border-radius:9px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.25);display:flex;align-items:center;justify-content:center;font-weight:900;color:#818cf8;font-size:13px;flex-shrink:0;"><?= strtoupper(mb_substr($patient_selected,0,1,'UTF-8')) ?></div>
    <div>
      <p style="font-size:13px;font-weight:900;color:#f1f5f9;text-transform:uppercase;font-style:italic;line-height:1;"><?= htmlspecialchars($patient_selected) ?></p>
      <p class="lbl" style="margin-top:2px;">Séance n°<?= $session_num ?> · <?= $appt_date ?><?php if($session_num>1):?> · <?= $session_num-1 ?> séance<?= ($session_num-1)>1?'s':'' ?> antérieure<?= ($session_num-1)>1?'s':'' ?><?php endif;?></p>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:14px;">
    <div style="display:flex;align-items:center;gap:5px;">
      <span style="width:5px;height:5px;border-radius:50%;background:#6366f1;" class="pulse"></span>
      <span class="lbl" style="color:#6366f1;">IA active</span>
    </div>
    <div style="display:flex;align-items:center;gap:6px;">
      <span id="recdot" style="width:7px;height:7px;border-radius:50%;background:#374151;transition:all .3s;"></span>
      <span id="timer" style="font-size:18px;font-weight:900;letter-spacing:.04em;color:#4b5563;font-variant-numeric:tabular-nums;">00:00</span>
    </div>
    <div id="rtop" class="chip cs">Risque · 0%</div>
    <div id="stop" class="chip cs">En attente</div>
  </div>
</div>

<!-- GRID -->
<div class="g3">

<!-- ═══ COL GAUCHE ═══ -->
<div class="col" style="border-right:1px solid var(--b);">

  <div class="card" id="rcard" style="border-left:3px solid #10b981;transition:border-color .7s,box-shadow .7s;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:7px;">
      <div>
        <p class="lbl">Vigilance clinique</p>
        <p id="rlbl" style="font-size:14px;font-weight:900;color:#10b981;margin-top:3px;transition:color .5s;">Stabilité</p>
      </div>
      <span id="rpct" style="font-size:26px;font-weight:900;color:#f1f5f9;line-height:1;">0%</span>
    </div>
    <div style="height:5px;background:#1f2937;border-radius:3px;overflow:hidden;margin-bottom:7px;">
      <div id="rbar" class="rb" style="width:0%;background:#10b981;"></div>
    </div>
    <p id="rdesc" style="font-size:10px;color:#4b5563;line-height:1.6;"></p>
  </div>

  <div class="card">
    <p class="lbl" style="margin-bottom:9px;">Indicateurs temps réel</p>
    <div class="sb"><span class="sbl" style="color:#f87171;">Urgence</span><div class="sbt"><div id="sbu" class="sbf" style="width:0%;background:#ef4444;"></div></div><span class="sbn" style="color:#f87171;" id="snu">0</span></div>
    <div class="sb"><span class="sbl" style="color:#fcd34d;">Détresse</span><div class="sbt"><div id="sbd" class="sbf" style="width:0%;background:#f59e0b;"></div></div><span class="sbn" style="color:#fcd34d;" id="snd">0</span></div>
    <div class="sb"><span class="sbl" style="color:#c4b5fd;">Anxiété</span><div class="sbt"><div id="sba" class="sbf" style="width:0%;background:#8b5cf6;"></div></div><span class="sbn" style="color:#c4b5fd;" id="sna">0</span></div>
    <div class="sb" style="margin-bottom:0;"><span class="sbl" style="color:#6ee7b7;">Résilience</span><div class="sbt"><div id="sbr" class="sbf" style="width:0%;background:#10b981;"></div></div><span class="sbn" style="color:#6ee7b7;" id="snr">0</span></div>
  </div>

  <div class="card">
    <p class="lbl" style="margin-bottom:7px;">Dynamique émotionnelle</p>
    <div style="height:68px;position:relative;"><canvas id="emoChart"></canvas></div>
  </div>

  <div class="card" style="flex:1;min-height:140px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <div style="display:flex;align-items:center;gap:5px;">
        <span style="width:5px;height:5px;border-radius:50%;background:#6366f1;" class="pulse"></span>
        <p class="lbl" style="color:#818cf8;">IA · Insights live</p>
      </div>
      <button id="btnask" onclick="toggleAsk()" class="btn bq" style="padding:5px 9px;font-size:7.5px;" disabled>✦ Demander</button>
    </div>
    <div id="feed" style="max-height:230px;overflow-y:auto;">
      <div id="feedph" style="padding:16px 0;text-align:center;opacity:.25;">
        <p class="lbl" style="line-height:1.9;">Démarrez la capture<br>L'IA analysera en continu</p>
      </div>
    </div>
  </div>

  <div id="askbox" style="display:none;">
    <div class="card2">
      <textarea id="askin" rows="2" class="ta" style="font-size:11px;padding:9px;border-radius:9px;margin-bottom:7px;" placeholder="Ex: Signes de dissociation ? Protocole ACT adapté ?"></textarea>
      <div style="display:flex;gap:6px;">
        <button onclick="sendQ()" class="btn ba" style="flex:1;padding:7px;">Demander</button>
        <button onclick="toggleAsk()" class="btn bg" style="padding:7px 11px;">✕</button>
      </div>
      <div id="askr" style="margin-top:6px;"></div>
    </div>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;">
      <p class="lbl">Notes praticien</p><span class="chip cw" style="font-size:7px;">Privé</span>
    </div>
    <textarea id="notes" class="nta" rows="4" placeholder="Observations, hypothèses, impressions cliniques..."></textarea>
  </div>

</div>

<!-- ═══ COL CENTRE ═══ -->
<div class="col" style="padding:12px 14px;">

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <div>
        <p style="font-size:13px;font-weight:800;color:#f1f5f9;">Transcription de séance</p>
        <p class="lbl" style="margin-top:2px;">Capture vocale ou saisie manuelle · Analyse IA en continu</p>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <div id="wv" style="display:flex;align-items:center;gap:2px;height:22px;">
          <?php for($i=0;$i<12;$i++):?><div style="width:3px;height:4px;background:#1f2937;border-radius:2px;transition:height .1s,background .2s;"></div><?php endfor;?>
        </div>
        <span id="wc" class="chip cs">0 mot</span>
      </div>
    </div>

    <textarea id="tr" class="ta" rows="11"
      placeholder="La parole s'inscrit ici automatiquement lors de la capture vocale. L'IA lit en continu et génère des insights dans la colonne gauche..."
      oninput="onTyping(this.value)"></textarea>

    <div id="ntr" style="margin-top:6px;min-height:28px;"></div>

    <div style="display:flex;align-items:center;gap:10px;margin-top:9px;">

      <!-- BOUTON MICRO -->
      <button id="micbtn" onclick="toggleMic()" class="mic mic-idle" title="Démarrer la capture vocale">
        <svg id="micsvg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
          <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
          <line x1="12" y1="19" x2="12" y2="23"/>
          <line x1="8" y1="23" x2="16" y2="23"/>
        </svg>
      </button>

      <div style="flex:1;">
        <p id="miclbl" style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:#374151;margin-bottom:4px;">Micro inactif — Cliquez pour démarrer</p>
        <div style="height:3px;background:#1f2937;border-radius:2px;overflow:hidden;">
          <div id="abar" style="width:0%;height:3px;background:var(--ac);border-radius:2px;transition:width .1s;"></div>
        </div>
      </div>

      <label style="display:flex;align-items:center;gap:5px;cursor:pointer;flex-shrink:0;">
        <input type="checkbox" id="autoai" checked style="accent-color:#6366f1;width:13px;height:13px;">
        <span class="lbl" style="color:#6366f1;">Auto-IA</span>
      </label>

      <button onclick="clearTr()" class="btn" style="background:rgba(239,68,68,.08);color:#f87171;border:1px solid rgba(239,68,68,.16);padding:8px 11px;font-size:8.5px;flex-shrink:0;">✕</button>
    </div>

    <div id="sttw" style="display:none;margin-top:8px;padding:8px 12px;border-radius:10px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);">
      <p style="font-size:10px;color:#fcd34d;font-weight:600;">⚠ Reconnaissance vocale non disponible. Utilisez Chrome/Edge et autorisez le microphone. Vous pouvez saisir le texte manuellement.</p>
    </div>
  </div>

  <div class="card" style="flex:1;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:11px;">
      <div>
        <p style="font-size:13px;font-weight:800;color:#f1f5f9;">Compte-rendu clinique IA</p>
        <p class="lbl" style="margin-top:2px;">Généré par intelligence artificielle</p>
      </div>
      <button id="btngen" onclick="genReport()" disabled class="btn ba">✦ Générer le bilan</button>
    </div>

    <!-- Pas d'onglets — rendu complet en une seule vue scrollable -->

    <div id="rpbody" style="min-height:160px;max-height:460px;overflow-y:auto;padding-right:3px;">
      <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:50px 20px;opacity:.18;">
        <div style="width:30px;height:30px;border:2px dashed #64748b;border-radius:50%;margin-bottom:10px;animation:spin 3s linear infinite;"></div>
        <p class="lbl" style="text-align:center;line-height:1.9;">En attente de données</p>
      </div>
    </div>

    <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--b);">
      <div id="narch" style="margin-bottom:6px;min-height:24px;"></div>
      <div style="display:flex;gap:8px;">
        <button onclick="exportPDF()" id="btnpdf" disabled class="btn bg" style="flex:1;">↓ PDF</button>
        <button onclick="finalize()" class="btn bok" style="flex:1;">✓ Archiver</button>
      </div>
    </div>
  </div>

</div>

<!-- ═══ COL DROITE : COCKPIT IA ═══ -->
<div class="col" style="border-left:1px solid var(--b);padding:0;gap:0;">

  <!-- RADAR -->
  <div style="padding:13px 13px 0;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <div>
        <p class="lbl" style="color:#818cf8;">Profil psycho-émotionnel</p>
        <p style="font-size:8px;color:#374151;margin-top:1px;font-weight:600;">Analyse multidimensionnelle · temps réel</p>
      </div>
      <span id="rchip" class="chip cs" style="font-size:7px;">En attente</span>
    </div>
    <div style="height:188px;position:relative;"><canvas id="radarChart"></canvas></div>
    <div style="display:flex;flex-wrap:wrap;gap:5px 10px;margin-top:7px;">
      <?php foreach([['Urgence','#ef4444'],['Détresse','#f59e0b'],['Anxiété','#8b5cf6'],['Résilience','#10b981'],['Social','#38bdf8'],['Stabilité','#6366f1']] as [$l,$c]):?>
      <div style="display:flex;align-items:center;gap:3px;">
        <div style="width:6px;height:6px;border-radius:50%;background:<?=$c?>;flex-shrink:0;"></div>
        <span style="font-size:7.5px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.07em;"><?=$l?></span>
      </div>
      <?php endforeach;?>
    </div>
  </div>

  <div class="rd"></div>

  <!-- TIMELINE 4 COURBES -->
  <div style="padding:0 13px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
      <p class="lbl" style="color:#818cf8;">Timeline émotionnelle</p>
      <div style="display:flex;gap:7px;">
        <?php foreach([['#ef4444','Risque'],['#10b981','Résil.'],['#f59e0b','Détr.'],['#8b5cf6','Anx.']] as [$c,$l]):?>
        <div style="display:flex;align-items:center;gap:2px;">
          <span style="width:10px;height:2px;background:<?=$c?>;border-radius:1px;display:inline-block;"></span>
          <span style="font-size:7px;font-weight:700;color:#374151;text-transform:uppercase;"><?=$l?></span>
        </div>
        <?php endforeach;?>
      </div>
    </div>
    <div style="height:100px;position:relative;"><canvas id="tlChart"></canvas></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:5px;">
      <span id="tllbl" style="font-size:9px;font-weight:700;color:#374151;">—</span>
      <span id="tlchip" class="chip cs" style="font-size:7px;">Stable</span>
    </div>
  </div>

  <div class="rd"></div>

  <!-- ARC GAUGE -->
  <div style="padding:0 13px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <p class="lbl" style="color:#818cf8;">Intensité émotionnelle</p>
      <span id="ichip" class="chip cs" style="font-size:7px;">0 / 100</span>
    </div>
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="position:relative;width:84px;height:52px;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
        <svg width="84" height="52" viewBox="0 0 84 52">
          <path d="M6,48 A36,36 0 0,1 78,48" fill="none" stroke="rgba(255,255,255,.05)" stroke-width="6" stroke-linecap="round"/>
          <path id="arcf" d="M6,48 A36,36 0 0,1 78,48" fill="none" stroke="#6366f1" stroke-width="6" stroke-linecap="round"
            stroke-dasharray="113" stroke-dashoffset="113" style="transition:stroke-dashoffset 1.4s cubic-bezier(.4,0,.2,1),stroke .6s;"/>
          <defs><filter id="gf"><feGaussianBlur stdDeviation="2.5"/></filter></defs>
          <path id="arcg" d="M6,48 A36,36 0 0,1 78,48" fill="none" stroke="#6366f1" stroke-width="3" stroke-linecap="round"
            stroke-dasharray="113" stroke-dashoffset="113" opacity=".35" filter="url(#gf)"
            style="transition:stroke-dashoffset 1.4s cubic-bezier(.4,0,.2,1),stroke .6s;"/>
        </svg>
        <div style="position:absolute;bottom:3px;text-align:center;">
          <p id="gval" style="font-size:16px;font-weight:900;color:#6366f1;line-height:1;transition:color .6s;">0</p>
          <p style="font-size:6.5px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#374151;margin-top:1px;">Intensité</p>
        </div>
      </div>
      <div style="flex:1;">
        <div class="ar"><span class="arl" style="color:#ef4444;">Urgence</span><div class="art"><div id="arcu" class="arf" style="width:0%;background:linear-gradient(90deg,#b91c1c,#ef4444);"></div></div><span class="arn" style="color:#ef4444;" id="avu">0</span></div>
        <div class="ar"><span class="arl" style="color:#fcd34d;">Détresse</span><div class="art"><div id="arcd" class="arf" style="width:0%;background:linear-gradient(90deg,#b45309,#f59e0b);"></div></div><span class="arn" style="color:#fcd34d;" id="avd">0</span></div>
        <div class="ar"><span class="arl" style="color:#c4b5fd;">Anxiété</span><div class="art"><div id="arca" class="arf" style="width:0%;background:linear-gradient(90deg,#6d28d9,#8b5cf6);"></div></div><span class="arn" style="color:#c4b5fd;" id="ava">0</span></div>
        <div class="ar"><span class="arl" style="color:#6ee7b7;">Résilience</span><div class="art"><div id="arcr" class="arf" style="width:0%;background:linear-gradient(90deg,#065f46,#10b981);"></div></div><span class="arn" style="color:#6ee7b7;" id="avr">0</span></div>
      </div>
    </div>
  </div>

  <div class="rd"></div>

  <!-- IA INSIGHTS DROITE -->
  <div style="padding:0 13px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:7px;">
      <div style="display:flex;align-items:center;gap:5px;">
        <span style="width:5px;height:5px;border-radius:50%;background:#6366f1;" class="pulse"></span>
        <p class="lbl" style="color:#818cf8;">IA · Analyse sémantique</p>
      </div>
      <div id="think" style="display:none;"><div class="dots"><span></span><span></span><span></span></div></div>
    </div>
    <p style="font-size:7.5px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#374151;margin-bottom:4px;">Carte thermique émotionnelle</p>
    <div class="hms" id="hm">
      <?php for($i=0;$i<20;$i++):?><div class="hmc"></div><?php endfor;?>
    </div>
    <div style="display:flex;justify-content:space-between;margin-top:2px;margin-bottom:8px;">
      <span style="font-size:7px;color:#374151;font-weight:600;">Début</span>
      <span style="font-size:7px;color:#374151;font-weight:600;">Maintenant</span>
    </div>
    <div id="ins" style="max-height:175px;overflow-y:auto;">
      <div id="insph" style="padding:12px 0;text-align:center;opacity:.22;">
        <p class="lbl" style="line-height:1.9;">L'IA générera des insights<br>au fil de la transcription</p>
      </div>
    </div>
  </div>

  <div class="rd"></div>

  <!-- HYPOTHÈSES DIAG -->
  <div style="padding:0 13px;">
    <div style="display:flex;align-items:center;gap:5px;margin-bottom:7px;">
      <span style="width:5px;height:5px;border-radius:50%;background:#38bdf8;" class="pulse"></span>
      <p class="lbl" style="color:#38bdf8;">IA · Hypothèses diagnostiques</p>
    </div>
    <div id="diagb">
      <div id="diagph" style="padding:10px 0;text-align:center;opacity:.22;">
        <p class="lbl" style="line-height:1.9;">Générées automatiquement<br>par analyse du verbatim</p>
      </div>
    </div>
  </div>

  <!-- HISTORIQUE -->
  <?php if(!empty($prev_consults)):?>
  <div class="rd"></div>
  <div style="padding:0 13px 13px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <p class="lbl" style="color:#818cf8;">Suivi longitudinal</p>
      <span class="chip ci" style="font-size:7px;"><?= count($prev_consults) ?> séance<?= count($prev_consults)>1?'s':'' ?></span>
    </div>
    <div style="height:48px;position:relative;margin-bottom:8px;"><canvas id="lgChart"></canvas></div>
    <div style="display:flex;flex-direction:column;gap:4px;">
      <?php foreach($prev_consults as $i=>$pc):?>
      <div style="display:flex;align-items:center;gap:7px;padding:7px 9px;border-radius:10px;border:1px solid var(--b2);background:rgba(17,24,39,.4);cursor:pointer;transition:border-color .2s;"
        onmouseover="this.style.borderColor='rgba(99,102,241,.3)'" onmouseout="this.style.borderColor='var(--b2)'"
        onclick="loadPrev(<?= json_encode(strip_tags($pc['resume_ia']??'')) ?>)">
        <div style="width:7px;height:7px;border-radius:50%;flex-shrink:0;<?= $i===0?'background:#6366f1;':'background:transparent;border:1.5px solid #374151;' ?>"></div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;justify-content:space-between;">
            <p style="font-size:9.5px;font-weight:700;color:<?= $i===0?'#a5b4fc':'#6b7280' ?>;"><?= date('d M Y',strtotime($pc['date_consultation'])) ?></p>
            <span style="font-size:7.5px;font-weight:700;color:#374151;"><?= $pc['duree_minutes'] ?>min</span>
          </div>
          <p style="font-size:8.5px;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:1px;"><?= htmlspecialchars(mb_substr(strip_tags($pc['resume_ia']??''),0,55,'UTF-8')) ?>…</p>
        </div>
      </div>
      <?php endforeach;?>
    </div>
  </div>
  <?php else:?>
  <div style="padding:16px;text-align:center;opacity:.2;">
    <p class="lbl" style="line-height:1.9;">Première séance<br>Aucun historique</p>
  </div>
  <?php endif;?>

</div>
</div>
</div>

<!-- OVERLAY -->
<div id="ov" class="ov" onclick="if(event.target===this)ovC()">
  <div class="ovb">
    <p id="ovmsg" style="font-size:13px;color:#cbd5e1;font-weight:500;margin-bottom:18px;line-height:1.6;"></p>
    <div style="display:flex;gap:8px;">
      <button id="ovyes" class="btn bok" style="flex:1;">Confirmer</button>
      <button onclick="ovC()" class="btn bg" style="flex:1;">Annuler</button>
    </div>
  </div>
</div>

<script nonce="<?= $nonce ?>">
// ── PHP → JS ──────────────────────────────────────────────────────────────────
var CSRF    = <?= json_encode($csrf) ?>;
var PAT     = <?= json_encode($patient_selected) ?>;
var SESN    = <?= $session_num ?>;
var DATED   = <?= json_encode($appt_date) ?>;
var DR      = <?= json_encode($nom_docteur) ?>;
var HIST    = <?= json_encode($history_for_ai) ?>;

// ── État ──────────────────────────────────────────────────────────────────────
var micOn   = false;
var recog   = null;
var timerIv = null;
var secs    = 0;
var lastRpt = null;
var curTab  = 's';
var lastAT  = '';

// Charts
var emoC, radC, tlC, lgC;

// Séries
var emoP  = [0];
var tR=[], tRs=[], tD=[], tA=[];
var heatD = [];

// Radar data
var rD = {u:0,d:0,a:0,r:0,s:0,st:100};

// ── Overlay ───────────────────────────────────────────────────────────────────
function ovO(msg, cb) {
  document.getElementById('ovmsg').innerHTML = msg;
  document.getElementById('ov').classList.add('open');
  document.getElementById('ovyes').onclick = function(){ ovC(); cb(); };
}
function ovC() { document.getElementById('ov').classList.remove('open'); }

// ── Notify ────────────────────────────────────────────────────────────────────
function ntf(id, msg, type, ms) {
  ms = (ms === undefined) ? 4500 : ms;
  var z = document.getElementById(id); if (!z) return;
  var ic = {ok:'✓',er:'✕',wa:'⚠',in:'ℹ'};
  z.innerHTML = '<div class="toast t'+type+'"><span>'+(ic[type]||'ℹ')+'</span><span>'+msg+'</span></div>';
  if (ms > 0) setTimeout(function(){ z.innerHTML=''; }, ms);
}

// ══════════════════════════════════════════════════════════════════════════════
// LEXIQUE CLINIQUE COMPLET — PsySpace Pro
// Conçu pour consultation psychologique réelle
// Catégories : urgence, détresse, anxiété, trauma, addiction, alimentaire,
//              dissociation, manie, psychose, deuil, relationnel, somatique,
//              protecteurs, social, thérapeutique
// ══════════════════════════════════════════════════════════════════════════════

var LEX = {

  // ════════════════════════════════════════════════════════════════════════════
  // URGENCE — Idéation suicidaire, passage à l'acte, crise aiguë
  // Poids élevé : détection prioritaire
  // ════════════════════════════════════════════════════════════════════════════
  u: { p: 40, w: [
    // Idéation directe
    "suicide","suicider","suicidaire","me suicider","se suicider","suicidal",
    "mourir","veux mourir","envie de mourir","penser à mourir","pensées de mort",
    "mort","me tuer","se tuer","tuer","finir ma vie","finir mes jours",
    "en finir","mettre fin","mettre fin à ma vie","mettre fin à tout","mettre fin à mes souffrances",
    "plus envie de vivre","plus la peine de vivre","pas envie de vivre","ne veux plus vivre",
    "aucun espoir","plus d'espoir","sans espoir","tout est fini","c'est sans issue",
    "adieu","au revoir pour toujours","c'est la dernière fois","dernière fois",
    "je disparais","disparaître définitivement","ne plus exister","je ne veux plus exister",
    "personne ne me manquera","tout le monde sera mieux sans moi","je ne sers à rien",
    "marre de vivre","j'en ai assez de vivre","je ne supporte plus de vivre",
    "bientôt ce sera fini","j'ai décidé","c'est décidé","ma décision est prise",
    "je sais comment faire","j'ai un plan","j'ai tout prévu",
    // Méthodes explicites
    "pendre","me pendre","se pendre","corde","nœud coulant",
    "overdose","avaler des médicaments","avaler tous mes médicaments","prendre une overdose",
    "sauter","me jeter","se jeter dans le vide","défenestrer","sauter d'un pont",
    "couteau","me poignarder","me lacérer les veines","me trancher",
    "arme","pistolet","fusil","me tirer une balle",
    "noyade","me noyer","se noyer",
    "se jeter sous","sous un train","sous une voiture",
    // Antécédents et planification
    "tentative de suicide","tentative","j'ai déjà essayé","j'ai essayé une fois",
    "TS","passage à l'acte","crise suicidaire",
    "j'ai stocké des médicaments","j'ai préparé","tout est prêt",
    "lettre d'adieu","j'ai écrit une lettre","j'ai réglé mes affaires",
    "donné mes affaires","distribué mes affaires",
    // Désespoir terminal
    "plus aucun sens","la vie n'a plus de sens","rien ne vaut la peine",
    "à quoi ça sert","pourquoi continuer","inutile de continuer",
    "je ne changerai jamais","impossible de guérir","condamné","condamnée",
    "je suis un fardeau","je fais du mal à tout le monde","mieux sans moi"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // DÉTRESSE ÉMOTIONNELLE — Dépression, souffrance, effondrements
  // ════════════════════════════════════════════════════════════════════════════
  d: { p: 15, w: [
    // Tristesse profonde
    "triste","tristesse","profondément triste","tellement triste","pleurer",
    "je pleure","je pleure tout le temps","larmes","sanglots","sangloter",
    "déprimé","déprimée","dépression","état dépressif","épisode dépressif",
    "syndrome dépressif","dépression majeure","grosse déprime","cafard","mélancolie",
    "dysthymie","humeur dépressive","humeur basse","humeur sombre",
    "désespoir","désespéré","désespérée","sans issue","impasse","mur",
    // Vide / anesthésie émotionnelle
    "vide","vide intérieur","rien ne compte","rien ne m'intéresse","plus rien",
    "tout est gris","tout est noir","tout est terne","grisaille","monotone",
    "anesthésié","anesthésiée","engourdi","engourdie","ne ressens plus rien",
    "flat","affect plat","émotions éteintes","plus de joie","anhédonie",
    "sombre","obscurité","ténèbres","noirceur","tunnel",
    "je ne ressens plus","j'ai perdu le goût","goût de rien",
    // Souffrance
    "souffrance","souffrir","je souffre","je souffre trop","mal","j'ai mal",
    "douleur","douleur morale","douleur psychique","douloureux","insupportable","intolérable",
    "brisé","brisée","cassé","cassée","abîmé","abîmée","démoli","demolie",
    "meurtri","meurtrie","blessé","blessée","déchiré","déchirée","en lambeaux",
    "détruit","détruite","ravagé","ravagée","effondré","effondrée",
    // Honte / culpabilité
    "honte","honteux","honteuse","j'ai honte","tellement honte","tellement de honte",
    "honte de moi","honte de ce que je suis","honte de ce que j'ai fait",
    "coupable","culpabilité","je me sens coupable","c'est ma faute","tout est ma faute",
    "je me déteste","je me hais","j'ai du dégoût pour moi","dégoût de moi-même",
    "nul","nulle","inutile","bon à rien","bonne à rien","raté","ratée",
    "incapable","incompétent","incompétente","je n'y arrive pas","je n'arrive jamais",
    "échec","je suis un échec","ma vie est un échec","tout rate","tout échoue",
    "sans valeur","je ne vaux rien","je ne vaux pas grand chose",
    // Isolement
    "seul","toute seule","tout seul","solitude","profonde solitude",
    "isolé","isolée","je m'isole","je m'enferme","coupé du monde",
    "personne","personne ne comprend","personne ne m'aime","personne ne se soucie",
    "personne ne m'écoute","personne n'est là","je suis invisible",
    "abandonné","abandonnée","rejeté","rejetée","exclu","exclue",
    "mis à l'écart","écarté","écartée","ignoré","ignorée",
    // Épuisement / effondrement
    "épuisé","épuisée","épuisement","épuisement total","à bout","au bout du rouleau",
    "plus d'énergie","vidé","vidée","drainé","drainée","exténué","exténuée",
    "fatigue chronique","fatigue profonde","je ne peux plus","plus la force",
    "plus l'envie","plus goût à rien","je traîne","je n'avance plus",
    // Deuil / perte
    "deuil","perte","perdu","perdue","je l'ai perdu","je l'ai perdue",
    "il est parti","elle est partie","il est mort","elle est morte","décédé","décédée",
    "il me manque","elle me manque","ça me manque","le manque",
    "rupture","séparation","divorce","il m'a quitté","elle m'a quitté",
    "trahison","trahi","trahie","trompé","trompée","infidélité"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // ANXIÉTÉ — Panique, rumination, évitement, phobies
  // ════════════════════════════════════════════════════════════════════════════
  a: { p: 8, w: [
    // Anxiété généralisée
    "anxieux","anxieuse","anxiété","anxiété généralisée","TAG",
    "angoisse","angoissé","angoissée","crises d'angoisse","bouffées d'angoisse",
    "stress","stressé","stressée","stresser","surmenage","burn-out","burnout",
    "peur","j'ai peur","terreur","effroi","effroyable","horrifié","horrifiée",
    "inquiet","inquiète","inquiétude","préoccupé","préoccupée","préoccupation",
    "appréhension","redouter","je redoute","craindre","je crains","j'appréhende",
    "hypervigilance","sur le qui-vive","toujours sur le qui-vive","aux aguets",
    // Attaques de panique
    "panique","attaque de panique","crise de panique","crise d'angoisse aiguë",
    "paniquer","paniqué","paniquée","ça arrive sans prévenir",
    "hyperventiler","hyperventilation","souffle coupé","j'étouffe","je suffoque",
    "oppression","oppressé","oppressée","poitrine serrée","pression dans la poitrine",
    "sensation de mort imminente","je vais mourir","je pensais que j'allais mourir",
    "sentiment de danger","sentiment de catastrophe imminente",
    // Symptômes somatiques anxieux
    "coeur qui bat","coeur qui s'emballe","palpitations","tachycardie",
    "trembler","je tremble","tremblements","frissons","mains qui tremblent",
    "sueurs","sueurs froides","transpirer","je transpire de peur",
    "vertiges","tête qui tourne","j'ai la tête qui tourne","étourdissements",
    "nausée","nausées","mal au ventre","ventre noué","estomac noué","crampes",
    "migraine","maux de tête","céphalées","tension dans la tête",
    "gorge serrée","boule dans la gorge","nœud dans la gorge","avaler difficile",
    "jambes en coton","jambes qui flanchent","je ne sens plus mes jambes",
    // Rumination / pensées intrusives
    "ruminer","je rumine","ruminations","pensées envahissantes","pensées intrusives",
    "je n'arrête pas de penser","tournent en boucle","en boucle","obsessionnel",
    "obsession","obsédé","obsédée","idées fixes","idée fixe",
    "je ne contrôle pas mes pensées","mes pensées s'emballent","trop de pensées",
    "pensées catastrophiques","scénarios catastrophes","je m'imagine le pire",
    "et si","et si ça arrive","et si je fais une erreur",
    // Insomnie / troubles du sommeil
    "insomnie","je ne dors pas","je ne dors plus","je ne dors pas bien",
    "nuits blanches","nuit sans sommeil","je reste éveillé","je reste éveillée",
    "cauchemars","mauvais rêves","nuit agitée","réveils nocturnes","je me réveille la nuit",
    "endormissement difficile","j'ai du mal à m'endormir","sommeil perturbé",
    // Évitement / phobies
    "éviter","j'évite","évitement","comportements d'évitement",
    "ne sors plus","je ne sors plus","je n'ose pas sortir","renfermé sur moi",
    "agoraphobie","claustrophobie","phobie sociale","phobie","phobies",
    "peur du jugement","peur du regard des autres","regard des autres","qu'est-ce qu'ils pensent",
    "peur de perdre le contrôle","peur de devenir fou","peur de devenir folle",
    "TOC","trouble obsessionnel","compulsion","rituel","rituels","vérifier","je vérifie tout",
    "compter","je compte","ordre","symétrie","contamination","peur des microbes"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // TRAUMA — PTSD, abus, violence, enfance difficile
  // ════════════════════════════════════════════════════════════════════════════
  tr: { p: 22, w: [
    // Violence physique
    "violence","violent","violente","violences physiques","frappé","frappée",
    "battu","battue","coups","brutalité","maltraitance","maltraité","maltraitée",
    "agressé","agressée","agression","agression physique",
    // Violence sexuelle
    "viol","violé","violée","agression sexuelle","abus sexuel","abusé sexuellement",
    "attouchements","inceste","pédophilie","exploitation sexuelle",
    "harcèlement sexuel","harcelé sexuellement",
    // Violence psychologique
    "violence psychologique","violence verbale","humilié","humiliée","humiliation",
    "rabaissé","rabaissée","dévalorisé","dévalorisée","insulté","insultée",
    "harcèlement","harcelé","harcelée","harceleur","harcèlement moral",
    "manipulation","manipulé","manipulée","emprise","sous emprise","emprise émotionnelle",
    "gaslighting","on me faisait douter","on me disait que j'étais fou","on me disait que j'étais folle",
    "contrôle","tout contrôlait","il contrôlait tout","elle contrôlait tout",
    "isolé de ma famille","coupé de mes amis","il m'a isolé","elle m'a isolée",
    // PTSD / reviviscences
    "traumatisme","traumatisé","traumatisée","traumatisme psychologique",
    "PTSD","ESPT","état de stress post-traumatique","stress post-traumatique",
    "flashback","flashbacks","reviviscence","je revis","je revois","ça revient",
    "images qui reviennent","odeurs qui rappellent","sons qui déclenchent",
    "triggers","déclencheurs","j'ai été déclenché","ça m'a déclenché",
    "hyperréactivité","réactions excessives","je sursaute","je sursaute tout le temps",
    "éviter les rappels","ne plus pouvoir aller","certains endroits me terrorisent",
    // Enfance difficile
    "enfance difficile","enfance douloureuse","enfance traumatisante",
    "parents toxiques","père violent","père absent","père alcoolique",
    "mère absente","mère froide","mère narcissique","mère toxique",
    "pas aimé enfant","pas aimée enfant","carences affectives","carence",
    "négligé","négligée","pas pris en charge","livré à moi-même",
    "placé en famille d'accueil","foyer","protection de l'enfance",
    "témoin de violences","j'ai vu mon père battre","violence conjugale vue",
    // Accidents / catastrophes
    "accident","accident de voiture","accident grave","j'ai failli mourir",
    "catastrophe","attentat","guerre","j'ai vécu la guerre","réfugié",
    "incendie","naufrage","catastrophe naturelle"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // DISSOCIATION — Dépersonnalisation, déréalisation, dissociation identitaire
  // ════════════════════════════════════════════════════════════════════════════
  di: { p: 18, w: [
    "dissociation","dissociatif","dissociative","état dissociatif",
    "dépersonnalisation","je ne me sens plus moi-même","je ne me reconnais plus",
    "déréalisation","tout semble irréel","comme dans un rêve","dans un film",
    "je me regarde de l'extérieur","j'observe mon corps","hors de mon corps",
    "je flotte","je plane","je me sens détaché","je me sens détachée",
    "je ne suis plus là","absent","absente de moi-même","vide de moi",
    "je ne sais plus qui je suis","perte d'identité","identité floue",
    "amnésie","trous de mémoire","je ne me souviens pas","pertes de mémoire",
    "plusieurs personnalités","TDI","trouble dissociatif de l'identité",
    "une voix en moi","une autre partie de moi","une partie de moi voulait",
    "je me suis coupé de mes émotions","engourdi","engourdie émotionnellement",
    "automatique","j'agissais automatiquement","en pilote automatique"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // MANIE / HYPOMANIE — Bipolarité, excitation, impulsivité
  // ════════════════════════════════════════════════════════════════════════════
  ma: { p: 14, w: [
    "manie","maniaque","épisode maniaque","hypomanie","hypomaniaque",
    "bipolarité","bipolaire","trouble bipolaire","je suis bipolaire",
    "euphorie","euphorique","je suis au top","je me sens invincible",
    "trop d'énergie","je déborde d'énergie","je ne dors pas mais je n'ai pas besoin",
    "idées qui s'enchaînent","pensées qui fusent","je pense trop vite",
    "je parle trop vite","fuite des idées","logorrhée",
    "impulsivité","impulsif","impulsive","j'agis sans réfléchir",
    "dépenses excessives","j'ai tout dépensé","j'ai fait des achats compulsifs",
    "comportements à risque","prise de risque excessive","je n'ai peur de rien",
    "grandiosité","je suis le meilleur","je suis la meilleure","mission divine",
    "hypersexualité","comportements sexuels excessifs","je me suis lâché",
    "irritabilité","très irritable","je m'emporte","sautes d'humeur",
    "cycles","phases hautes","phases basses","montagnes russes","je passe du coq à l'âne"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // PSYCHOSE — Hallucinations, délires, pensée désorganisée
  // ════════════════════════════════════════════════════════════════════════════
  ps: { p: 30, w: [
    "voix","j'entends des voix","les voix me disent","voix dans ma tête",
    "hallucinations","hallucination","j'hallucine","j'ai vu","j'ai entendu",
    "hallucinations auditives","hallucinations visuelles",
    "délire","délirant","délires","idées délirantes","pensées délirantes",
    "persécution","on me persécute","ils me surveillent","on me surveille",
    "complot","il y a un complot contre moi","ils complotent contre moi",
    "espionné","espionnée","micros cachés","caméras cachées","on m'écoute",
    "on lit dans mes pensées","télépathie","mes pensées sont volées",
    "mission","j'ai une mission divine","Dieu m'a choisi","je suis élu","je suis élue",
    "messages cachés","messages pour moi","les signes","tout est un signe",
    "pensée magique","rituel magique","je peux influencer","par la pensée",
    "schizophrénie","schizophrène","psychose","épisode psychotique",
    "pensée désorganisée","je n'arrive plus à penser","mes pensées sont décousues",
    "référence","idée de référence","tout me concerne","tout parle de moi"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // ADDICTIONS — Alcool, drogues, comportementales
  // ════════════════════════════════════════════════════════════════════════════
  ad: { p: 12, w: [
    // Alcool
    "alcool","je bois","boire","boire pour oublier","boire seul","boire seule",
    "ivre","ivresse","alcoolique","dépendance à l'alcool","alcoolodépendance",
    "verres de trop","trop bu","soirées arrosées","cacher que je bois",
    "je bois en cachette","gueule de bois","ressac","lendemain difficile",
    // Drogues
    "drogue","drogué","droguée","consommation de drogue","usage de drogue",
    "cannabis","joint","fumer du cannabis","herbe","shit","beuh",
    "cocaïne","coke","snifer","ligne de coke","poudre blanche",
    "héroïne","smack","opioïdes","morphine","codéine",
    "ecstasy","MDMA","amphétamines","speed","pilule","pilules",
    "kétamine","LSD","acide","champignons hallucinogènes","psychédéliques",
    "crack","crystal meth","méthamphétamine",
    "je consomme","je me drogue","je prends des trucs","pour tenir",
    "pour oublier","pour ne plus ressentir","pour me sentir bien",
    // Médicaments détournés
    "médicaments","cachets","pilules","je prends trop","surdosage","j'en prends trop",
    "benzodiazépines","xanax","valium","lexomil","temesta","rivotril",
    "somnifères","hypnotiques","je ne peux pas m'endormir sans",
    "antidouleurs","tramadol","codéine","opiacés",
    "addiction aux médicaments","dépendance aux médicaments",
    // Comportementales
    "jeux d'argent","casino","paris sportifs","je joue trop","je perds tout",
    "addiction aux jeux","jeux vidéo","je joue des heures","je joue toute la nuit",
    "écrans","réseaux sociaux","je ne peux pas m'en passer","FOMO",
    "achat compulsif","shopping compulsif","je dépense tout","je claque tout",
    "pornographie","addiction à la pornographie","je regarde tout le temps",
    "je n'arrive pas à m'arrêter","je recommence toujours","rechute","j'ai rechuté",
    "abstinence","sevrage","arrêter","j'essaie d'arrêter","je veux arrêter"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // TROUBLES ALIMENTAIRES — Anorexie, boulimie, hyperphagie
  // ════════════════════════════════════════════════════════════════════════════
  ta: { p: 14, w: [
    // Restriction / anorexie
    "anorexie","anorexique","je ne mange pas","je ne mange plus","je mange peu",
    "je saute des repas","refus de manger","peur de manger","peur de grossir",
    "restriction","restriction alimentaire","régime strict","régime drastique",
    "jeûner","jeûne","je jeûne","je n'ai pas mangé depuis","je ne mérite pas de manger",
    "contrôle alimentaire","contrôler ce que je mange","peser les aliments",
    "calories","je compte les calories","obsession des calories",
    // Boulimie / compulsions
    "boulimie","boulimique","crises de boulimie","crise alimentaire",
    "manger compulsivement","je mange trop","je mange sans m'arrêter",
    "hyperphagie","compulsion alimentaire","je me jette sur la nourriture",
    "manger pour combler","manger pour oublier","manger mes émotions",
    "crise de nourriture","j'ai tout mangé","j'ai tout avalé",
    // Comportements purgatifs
    "vomir","je me fais vomir","vomissements provoqués","purge","se purger",
    "laxatifs","je prends des laxatifs","diurétiques pour maigrir",
    "sport excessif pour compenser","compenser","je dois compenser",
    // Image corporelle
    "image du corps","image corporelle","je me trouve gros","je me trouve grosse",
    "je me dégoûte","dégoût de mon corps","je ne supporte pas mon corps",
    "poids","obsession du poids","je me pèse tout le temps","balance",
    "je veux maigrir","trop grosse","trop gros","mon ventre","mes cuisses",
    "dysmorphophobie","je vois des défauts qui n'existent pas"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // AUTO-MUTILATION — Scarification, automutilation sans intention suicidaire
  // ════════════════════════════════════════════════════════════════════════════
  am: { p: 28, w: [
    "automutilation","s'automutiler","je me blesse","je me fais du mal",
    "me couper","je me coupe","scarification","cicatrices","cicatrices cachées",
    "brûlures","je me brûle","frapper un mur","me frapper","me cogner la tête",
    "s'arracher les cheveux","s'arracher la peau","se gratter jusqu'au sang",
    "se mordre","se pincer fort","comportements d'automutilation",
    "pour ressentir quelque chose","pour ne plus ressentir","pour évacuer",
    "ça soulage","ça fait du bien sur le moment","seul moyen que j'ai trouvé",
    "je cache mes bras","manches longues","pour cacher","personne ne voit",
    "lames","je garde des lames","je cache des lames"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // SOMATISATION — Douleurs physiques d'origine psychologique
  // ════════════════════════════════════════════════════════════════════════════
  so2: { p: 7, w: [
    "douleurs chroniques","douleur partout","fibromyalgie","j'ai mal partout",
    "maladie psychosomatique","psychosomatique","c'est dans la tête dit-on",
    "symptômes inexpliqués","les médecins ne trouvent rien","rien à l'examen",
    "syndrome de fatigue chronique","SFC","épuisement médical inexpliqué",
    "côlon irritable","intestin irritable","problèmes digestifs chroniques",
    "eczéma","psoriasis","urticaire chronique","peau qui réagit au stress",
    "douleurs musculaires","tension musculaire","contractures","dos bloqué",
    "maux de tête chroniques","migraines à répétition","céphalées tensionnelles",
    "boule dans la gorge","difficultés à avaler","globus hystericus",
    "palpitations inexpliquées","tachycardie fonctionnelle",
    "vertiges fonctionnels","vertiges sans cause organique"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // RELATIONNEL — Conflits, dépendance affective, relations toxiques
  // ════════════════════════════════════════════════════════════════════════════
  re: { p: 10, w: [
    // Conflits
    "conflit","disputes","on se dispute tout le temps","crise dans le couple",
    "mésentente","communication rompue","on ne se parle plus","silence",
    "séparation","divorce","rupture","je vais divorcer","on va se séparer",
    "famille dysfonctionnelle","famille toxique","relations toxiques",
    // Dépendance affective
    "dépendance affective","je ne peux pas vivre sans lui","sans elle",
    "j'ai besoin qu'on m'aime","besoin d'amour","peur d'être abandonné",
    "peur d'être abandonnée","peur de la solitude","je fais tout pour plaire",
    "je m'oublie pour l'autre","je m'efface","je n'existe que pour lui","que pour elle",
    "jalousie","jaloux","jalouse","je surveille son téléphone","je vérifie tout",
    // Attachement insécure
    "attachement","style d'attachement","peur de l'intimité","je fuis quand ça devient sérieux",
    "je m'attache trop vite","je tombe amoureux trop vite","je tombe amoureuse trop vite",
    "peur d'être blessé","peur d'être blessée","je me protège","je me ferme",
    "relations qui durent pas","je sabote","je sabote mes relations",
    // Limites personnelles
    "je n'arrive pas à dire non","je dis toujours oui","je ne sais pas dire non",
    "limites","pas de limites","on piétine mes limites","je me laisse faire",
    "passif","passive","trop gentil","trop gentille","on profite de moi"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // FACTEURS PROTECTEURS — Ressources, espoir, résilience
  // ════════════════════════════════════════════════════════════════════════════
  po: { p: -12, w: [
    // Espoir / futur positif
    "espoir","j'espère","j'ai de l'espoir","optimiste","optimisme",
    "confiant","confiante","je me sens confiant","je me sens confiante",
    "futur","avenir","l'avenir","je vois un avenir","projets","j'ai des projets",
    "envie de","j'ai envie","je veux","je veux vraiment","je suis motivé","je suis motivée",
    "demain","la semaine prochaine","bientôt","dans quelques mois","un jour",
    "ça va changer","les choses vont changer","je vais m'en sortir",
    // Bien-être
    "mieux","je vais mieux","ça va mieux","amélioration","en progrès","je progresse",
    "heureux","heureuse","joie","bonheur","content","contente","je suis content","je suis contente",
    "satisfait","satisfaite","épanoui","épanouie","je m'épanouis",
    "serein","sereine","tranquille","apaisé","apaisée","calme","je me sens calme",
    "soulagé","soulagée","soulagement","légèreté","je me sens léger","je me sens légère",
    "bien dans ma peau","je m'accepte","j'apprends à m'accepter",
    // Énergie / motivation
    "énergie","j'ai de l'énergie","dynamique","actif","active","vitalité",
    "motivé","motivée","motivation","envie de faire","envie d'agir",
    "plaisir","j'ai du plaisir","je prends du plaisir","ça me fait plaisir",
    "sourire","je souris","rire","on a ri","j'ai ri","je ris",
    "profiter","je profite","apprécier","j'apprécie",
    // Sens / valeurs
    "sens","ça a du sens","je retrouve du sens","sens de la vie","pourquoi",
    "valeurs","mes valeurs","ce qui compte","ce qui est important pour moi",
    "identité","je sais qui je suis","je me retrouve","je me reconnais",
    "fierté","je suis fier","je suis fière","j'ai accompli","j'ai réussi",
    // Famille / enfants
    "mes enfants","mon fils","ma fille","mon bébé","mes enfants me donnent de la force",
    "famille","ma famille","mes proches","ma mère","mon père","mes parents",
    "mon mari","ma femme","mon compagnon","ma compagne","mon partenaire",
    "ils comptent sur moi","je veux être là pour eux",
    // Guérison / thérapie
    "guérir","je veux guérir","reprendre","avancer","je veux avancer","aller de l'avant",
    "changer","je veux changer","travailler sur moi","me reconstruire",
    "thérapie","ça m'aide","ça aide","ça fait du bien","la thérapie m'aide",
    "je réalise","prise de conscience","j'ai compris","j'ai réalisé",
    "accepter","j'apprends à accepter","acceptation","lâcher prise","je lâche prise",
    "mindfulness","pleine conscience","méditation","je médite","cohérence cardiaque",
    "je prends soin de moi","self-care","prendre soin"
  ]},

  // ════════════════════════════════════════════════════════════════════════════
  // SOUTIEN SOCIAL — Liens, entourage, présence
  // ════════════════════════════════════════════════════════════════════════════
  so: { p: -7, w: [
    // Amis / cercle social
    "ami","amis","amie","amies","copain","copine","copains","copines",
    "entourage","entouré","entourée","bien entouré","bien entourée",
    "soutien","soutenu","soutenue","je me sens soutenu","je me sens soutenue",
    "accompagné","accompagnée","pas seul","pas seule","je ne suis pas seul","je ne suis pas seule",
    "parler à quelqu'un","j'ai quelqu'un à qui parler","j'ai pu en parler",
    "aidé","aidée","on m'aide","ils m'aident","elle m'aide","il m'aide",
    "connecté","connectée","lien","liens fort","relation solide","relation saine",
    // Activités sociales
    "sortir","je sors","on sort","je suis sorti","je suis sortie",
    "rencontrer","j'ai rencontré","voir des gens","passer du temps avec",
    "ensemble","on est ensemble","réunion de famille","dîner entre amis",
    // Communauté / entraide
    "groupe de soutien","groupe de parole","j'ai rejoint un groupe",
    "association","bénévole","solidarité","communauté","je m'engage",
    "aide professionnelle","je vois un psy","je suis suivi","je suis suivie",
    "psychiatre","psychologue","thérapeute","je consulte","j'ai un rendez-vous"
  ]}

};

// ══════════════════════════════════════════════════════════════════════════════
// MODIFICATEURS LINGUISTIQUES — Négation, intensité, atténuation
// ══════════════════════════════════════════════════════════════════════════════
var NEG = [
  "ne","n'","pas","plus","jamais","aucun","aucune","sans","ni","non",
  "nullement","guère","point","absolument pas","certainement pas",
  "je n'ai pas","je ne suis pas","je ne me sens pas","je ne pense pas",
  "contrairement","à l'opposé","au contraire"
];

var INT = [
  "très","vraiment","tellement","trop","extrêmement","profondément",
  "absolument","terriblement","infiniment","énormément","complètement",
  "totalement","intensément","fortement","vraiment très","beaucoup trop",
  "particulièrement","incroyablement","horriblement","affreusement",
  "à en mourir","à en crever","au plus haut point","au maximum",
  "comme jamais","plus que jamais","encore plus","de plus en plus"
];

var ATT = [
  "un peu","légèrement","parfois","peut-être","vaguement","de temps en temps",
  "par moments","à certains moments","rarement","pas toujours","un peu parfois",
  "il m'arrive","ça m'arrive","de temps à autre","certains jours",
  "pas systématiquement","pas tout le temps","dans certaines situations",
  "quand je suis fatigué","quand je suis fatiguée","quand je suis stressé"
];


// ══════════════════════════════════════════════════════════════════════════════
// FONCTION ANALYZE — Version complète avec toutes les catégories
// Remplace l'ancienne fonction analyze(text) dans analyse_ia.php
// ══════════════════════════════════════════════════════════════════════════════
function analyze(text) {
  var t = text.toLowerCase();
  var tok = t.split(/\s+/);

  // Initialiser tous les scores
  var sc = { u:0, d:0, a:0, tr:0, di:0, ma:0, ps:0, ad:0, ta:0, am:0, so2:0, re:0, po:0, so:0 };

  Object.keys(LEX).forEach(function(cat) {
    LEX[cat].w.forEach(function(mot) {
      var isPhrase = mot.indexOf(' ') !== -1;

      if (isPhrase) {
        var si = 0, idx;
        while ((idx = t.indexOf(mot, si)) !== -1) {
          var ap = t.slice(0, idx).split(/\s+/).length;
          var window_before = tok.slice(Math.max(0, ap - 6), ap);

          // Vérifier négation
          var neg = window_before.some(function(x) {
            return NEG.indexOf(x.replace(/[',\.!?;:]/g, '')) !== -1;
          });

          // Vérifier intensificateur
          var ctx = window_before.join(' ');
          var mul = 1;
          if (INT.some(function(m) { return ctx.indexOf(m) !== -1; })) mul = 1.7;
          else if (ATT.some(function(m) { return ctx.indexOf(m) !== -1; })) mul = 0.45;

          if (!neg) {
            sc[cat] += LEX[cat].p * mul;
          } else {
            // Négation d'un terme positif = aggrave légèrement
            if (cat === 'po' || cat === 'so') sc[cat] -= LEX[cat].p * 0.4;
          }
          si = idx + mot.length;
        }
      } else {
        tok.forEach(function(tk, i) {
          // Correspondance stricte ou incluse dans le token
          var match = (tk === mot) || (tk.indexOf(mot) !== -1 && mot.length > 4);
          if (match) {
            var window_before = tok.slice(Math.max(0, i - 6), i);
            var neg = window_before.some(function(x) {
              return NEG.indexOf(x.replace(/[',\.!?;:]/g, '')) !== -1;
            });
            var ctx = window_before.join(' ');
            var mul = 1;
            if (INT.some(function(m) { return ctx.indexOf(m) !== -1; })) mul = 1.7;
            else if (ATT.some(function(m) { return ctx.indexOf(m) !== -1; })) mul = 0.45;

            if (!neg) {
              sc[cat] += LEX[cat].p * mul;
            } else {
              if (cat === 'po' || cat === 'so') sc[cat] -= LEX[cat].p * 0.4;
            }
          }
        });
      }
    });
  });

  // ── Calcul du risque global ────────────────────────────────────────────────
  // Catégories à risque pondérées
  var danger  = sc.u * 1.0          // Urgence suicidaire — poids maximum
              + sc.am * 0.85        // Automutilation — très sérieux
              + sc.ps * 0.80        // Psychose — urgent
              + sc.d  * 0.55        // Détresse profonde
              + sc.tr * 0.60        // Trauma actif
              + sc.di * 0.50        // Dissociation
              + sc.a  * 0.35        // Anxiété
              + sc.ma * 0.40        // Manie / impulsivité
              + sc.ad * 0.30        // Addiction
              + sc.ta * 0.35        // Troubles alimentaires
              + sc.so2* 0.20        // Somatisation
              + sc.re * 0.25;       // Relationnel

  // Facteurs protecteurs — réduisent le risque
  var protecteurs = Math.abs(sc.po) * 0.9 + Math.abs(sc.so) * 0.6;

  var raw = danger - protecteurs;

  // Normalisation 0-100
  var risk = Math.round(Math.max(0, Math.min(100, raw)));

  // ── Indicateurs pour l'UI ──────────────────────────────────────────────────
  return {
    sc: sc,
    risk: risk,

    // Indicateurs synthétiques pour les barres existantes
    urgence:    Math.round(Math.max(0, sc.u)),
    detresse:   Math.round(Math.max(0, sc.d)),
    anxiete:    Math.round(Math.max(0, sc.a)),
    resilience: Math.round(Math.abs(sc.po) + Math.abs(sc.so)),

    // Nouvelles dimensions
    trauma:        Math.round(Math.max(0, sc.tr)),
    dissociation:  Math.round(Math.max(0, sc.di)),
    manie:         Math.round(Math.max(0, sc.ma)),
    psychose:      Math.round(Math.max(0, sc.ps)),
    addiction:     Math.round(Math.max(0, sc.ad)),
    alimentaire:   Math.round(Math.max(0, sc.ta)),
    automutilation:Math.round(Math.max(0, sc.am)),
    somatique:     Math.round(Math.max(0, sc.so2)),
    relationnel:   Math.round(Math.max(0, sc.re)),

    // Alertes critiques booléennes (pour déclenchement immédiat)
    alerte_suicidaire:    sc.u > 30,
    alerte_automutilation:sc.am > 20,
    alerte_psychose:      sc.ps > 25,
    alerte_manie:         sc.ma > 20,
    alerte_dissociation:  sc.di > 15
  };
}

// ── LEXIQUE ───────────────────────────────────────────────────────────────────
// ── LEXIQUE ENRICHI ───────────────────────────────────────────────────────────
// Remplace l'ancien var LEX = {...} dans analyse_ia.php


// ── INPUT ─────────────────────────────────────────────────────────────────────
function onTyping(val) {
  var words = val.trim().split(/\s+/).filter(function(w){return w.length>0;});
  var wc = words.length;
  document.getElementById('wc').textContent = wc + (wc>1?' mots':' mot');
  var res = analyze(val);
  var sc = res.sc, risk = res.risk;
  updRisk(risk, sc);
  updRadar(sc, risk);
  updTimeline(risk, sc);
  updArc(risk, sc);
  updHeat(risk);
  if (wc > 10) document.getElementById('btngen').disabled = false;
  if (document.getElementById('autoai').checked && wc>0 && wc%35===0 && val!==lastAT) {
    lastAT = val; autoAI(val, sc, risk);
  }
}

// ── RISK LEFT ─────────────────────────────────────────────────────────────────
function updRisk(risk, sc) {
  document.getElementById('rpct').textContent = risk+'%';
  document.getElementById('rtop').textContent = 'Risque · '+risk+'%';
  var bar = document.getElementById('rbar');
  var lbl = document.getElementById('rlbl');
  var desc = document.getElementById('rdesc');
  var card = document.getElementById('rcard');
  bar.style.width = risk+'%';
  var parts = [];
  if (sc.u>30) parts.push('Idéation à risque');
  if (sc.d>20) parts.push('Détresse émotionnelle');
  if (sc.a>15) parts.push('Tension anxieuse');
  if ((Math.abs(sc.po)+Math.abs(sc.so))>5) parts.push('Facteurs protecteurs');
  if (risk>=80) {
    card.style.borderLeftColor='#ef4444'; card.style.boxShadow='0 0 20px rgba(239,68,68,.1)';
    bar.style.background='#ef4444'; lbl.textContent='⚠ Vigilance Critique'; lbl.style.color='#ef4444';
    document.getElementById('rtop').className='chip ccrit';
    desc.textContent = parts.join(' · ') || 'Risque élevé — intervention recommandée.';
  } else if (risk>=40) {
    card.style.borderLeftColor='#f59e0b'; card.style.boxShadow='none';
    bar.style.background='#f59e0b'; lbl.textContent='Vigilance Modérée'; lbl.style.color='#f59e0b';
    document.getElementById('rtop').className='chip cw';
    desc.textContent = parts.join(' · ') || 'Tension émotionnelle détectée.';
  } else {
    card.style.borderLeftColor='#10b981'; card.style.boxShadow='none';
    bar.style.background='#10b981'; lbl.textContent='Stabilité Clinique'; lbl.style.color='#10b981';
    document.getElementById('rtop').className='chip ck';
    desc.textContent = parts.join(' · ') || 'Aucun marqueur de risque immédiat.';
  }
  // Score bars
  var bmap = [['sbu','snu',sc.u],['sbd','snd',sc.d],['sba','sna',sc.a],['sbr','snr',Math.abs(sc.po)+Math.abs(sc.so)]];
  bmap.forEach(function(b){
    var v = Math.round(Math.max(0,Math.abs(b[2])));
    var el=document.getElementById(b[0]); if(el) el.style.width=Math.min(100,v)+'%';
    var en=document.getElementById(b[1]); if(en) en.textContent=v;
  });
  // Emo chart
  var neg=sc.u+sc.d+sc.a, pos=Math.abs(sc.po)+Math.abs(sc.so), sum=neg+pos;
  emoP.push(sum>0 ? Math.max(-1,Math.min(1,(pos-neg)/sum)) : 0);
  if (emoP.length>60) emoP.shift();
  drawEmo();
}

// ── RADAR ─────────────────────────────────────────────────────────────────────
function updRadar(sc, risk) {
  rD = {
    u:  Math.min(100,Math.round(Math.max(0,sc.u))),
    d:  Math.min(100,Math.round(Math.max(0,sc.d))),
    a:  Math.min(100,Math.round(Math.max(0,sc.a))),
    r:  Math.min(100,Math.round(Math.abs(sc.po))),
    s:  Math.min(100,Math.round(Math.abs(sc.so))),
    st: Math.max(0,100-risk)
  };
  drawRadar();
  document.getElementById('rchip').textContent='Live';
  document.getElementById('rchip').className='chip ck';
}
function drawRadar() {
  var ctx = document.getElementById('radarChart').getContext('2d');
  if (radC) radC.destroy();
  var vals=[rD.u,rD.d,rD.a,rD.r,rD.s,rD.st];
  var pc=['#ef4444','#f59e0b','#8b5cf6','#10b981','#38bdf8','#6366f1'];
  var danger = rD.u>40||rD.d>50;
  radC = new Chart(ctx,{
    type:'radar',
    data:{
      labels:['Urgence','Détresse','Anxiété','Résilience','Social','Stabilité'],
      datasets:[
        {data:[100,100,100,100,100,100],borderColor:'rgba(255,255,255,.025)',backgroundColor:'transparent',borderWidth:1,pointRadius:0,order:2},
        {data:vals,borderColor:danger?'rgba(239,68,68,.7)':'rgba(99,102,241,.65)',
          backgroundColor:danger?'rgba(239,68,68,.07)':'rgba(99,102,241,.09)',
          borderWidth:2,pointBackgroundColor:pc,pointBorderColor:'rgba(10,14,26,.8)',
          pointBorderWidth:1.5,pointRadius:4,pointHoverRadius:7,order:1}
      ]
    },
    options:{
      responsive:true,maintainAspectRatio:false,
      animation:{duration:600,easing:'easeInOutCubic'},
      plugins:{legend:{display:false},tooltip:{
        backgroundColor:'rgba(10,14,26,.97)',borderColor:'rgba(255,255,255,.1)',borderWidth:1,
        padding:10,cornerRadius:10,
        titleFont:{family:'Plus Jakarta Sans',size:9,weight:'800'},
        bodyFont:{family:'Plus Jakarta Sans',size:10},
        callbacks:{
          title:function(i){return [i[0].label.toUpperCase()];},
          label:function(i){return i.datasetIndex===1?' '+i.raw+' / 100':null;}
        }
      }},
      scales:{r:{min:0,max:100,ticks:{display:false},
        grid:{color:'rgba(255,255,255,.04)'},
        angleLines:{color:'rgba(255,255,255,.05)'},
        pointLabels:{color:'#374151',font:{family:'Plus Jakarta Sans',size:8,weight:'700'},padding:5}
      }}
    }
  });
}

// ── TIMELINE ──────────────────────────────────────────────────────────────────
function updTimeline(risk, sc) {
  tR.push(risk);
  tRs.push(Math.round(Math.abs(sc.po)+Math.abs(sc.so)));
  tD.push(Math.round(Math.max(0,sc.d)));
  tA.push(Math.round(Math.max(0,sc.a)));
  var M=60; if(tR.length>M){tR.shift();tRs.shift();tD.shift();tA.shift();}
  drawTL();
  if (tR.length>=4) {
    var n=tR.length;
    var diff=((tR[n-1]+tR[n-2])/2)-((tR[n-3]+tR[n-4])/2);
    var tc=document.getElementById('tlchip');
    var tl=document.getElementById('tllbl');
    if (diff>8)       {tc.textContent='↑ Risque en hausse';tc.className='chip ce';tl.style.color='#f87171';tl.textContent='Tension croissante';}
    else if (diff<-8) {tc.textContent='↓ Stabilisation';tc.className='chip ck';tl.style.color='#34d399';tl.textContent='Apaisement progressif';}
    else              {tc.textContent='→ Stable';tc.className='chip cs';tl.style.color='#4b5563';tl.textContent='Régularité du discours';}
  }
}
function drawTL() {
  var ctx = document.getElementById('tlChart').getContext('2d');
  if (tlC) tlC.destroy();
  var labels = tR.map(function(_,i){return i;});
  function g(c,c1,c2){var gr=c.chart.ctx.createLinearGradient(0,0,0,100);gr.addColorStop(0,c1);gr.addColorStop(1,c2);return gr;}
  tlC = new Chart(ctx,{
    type:'line',
    data:{labels:labels,datasets:[
      {label:'Risque',    data:tR, borderColor:'#ef4444',borderWidth:2,  pointRadius:0,fill:true,backgroundColor:function(c){return g(c,'rgba(239,68,68,.2)','rgba(239,68,68,0)');},tension:.42},
      {label:'Résilience',data:tRs,borderColor:'#10b981',borderWidth:1.5,pointRadius:0,fill:true,backgroundColor:function(c){return g(c,'rgba(16,185,129,.12)','rgba(16,185,129,0)');},tension:.42},
      {label:'Détresse',  data:tD, borderColor:'#f59e0b',borderWidth:1.5,pointRadius:0,fill:false,tension:.42,borderDash:[3,3]},
      {label:'Anxiété',   data:tA, borderColor:'#8b5cf6',borderWidth:1.5,pointRadius:0,fill:false,tension:.42,borderDash:[2,4]}
    ]},
    options:{
      responsive:true,maintainAspectRatio:false,animation:false,
      interaction:{mode:'index',intersect:false},
      plugins:{legend:{display:false},tooltip:{
        backgroundColor:'rgba(10,14,26,.97)',borderColor:'rgba(255,255,255,.09)',borderWidth:1,
        padding:9,cornerRadius:9,
        titleFont:{family:'Plus Jakarta Sans',size:8,weight:'800'},
        bodyFont:{family:'Plus Jakarta Sans',size:9},
        callbacks:{
          title:function(i){return 'T+'+i[0].label;},
          label:function(i){return ' '+i.dataset.label+': '+i.raw+'%';},
          labelColor:function(i){var c=['#ef4444','#10b981','#f59e0b','#8b5cf6'];return{borderColor:'transparent',backgroundColor:c[i.datasetIndex]||'#6366f1',borderRadius:2};}
        }
      }},
      scales:{
        x:{display:false},
        y:{min:0,max:100,ticks:{display:false},grid:{color:'rgba(255,255,255,.025)',drawBorder:false},border:{display:false}}
      }
    }
  });
}

// ── EMO CHART ─────────────────────────────────────────────────────────────────
function drawEmo() {
  var ctx = document.getElementById('emoChart').getContext('2d');
  if (emoC) emoC.destroy();
  var last = emoP[emoP.length-1]||0;
  var lc = last>0.1?'#10b981':(last<-0.2?'#ef4444':'#6366f1');
  var fc = last>0.1?'rgba(16,185,129,.2)':(last<-0.2?'rgba(239,68,68,.15)':'rgba(99,102,241,.2)');
  emoC = new Chart(ctx,{
    type:'line',
    data:{labels:emoP.map(function(_,i){return i;}),datasets:[{
      data:emoP,borderColor:lc,borderWidth:1.5,pointRadius:0,fill:true,
      backgroundColor:function(c){var g=c.chart.ctx.createLinearGradient(0,0,0,68);g.addColorStop(0,fc);g.addColorStop(1,'rgba(0,0,0,0)');return g;},
      tension:.4
    }]},
    options:{responsive:true,maintainAspectRatio:false,animation:false,
      plugins:{legend:{display:false}},
      scales:{x:{display:false},y:{min:-1.2,max:1.2,ticks:{display:false},grid:{color:'rgba(255,255,255,.025)',drawBorder:false},border:{display:false}}}
    }
  });
}

// ── ARC GAUGE ─────────────────────────────────────────────────────────────────
function updArc(risk, sc) {
  var intensity = Math.min(100,risk);
  var offset = 113*(1-intensity/100);
  var color = risk>=80?'#ef4444':(risk>=50?'#f59e0b':(risk>=25?'#6366f1':'#10b981'));
  var af=document.getElementById('arcf'),ag=document.getElementById('arcg');
  if(af){af.setAttribute('stroke-dashoffset',offset);af.setAttribute('stroke',color);}
  if(ag){ag.setAttribute('stroke-dashoffset',offset);ag.setAttribute('stroke',color);}
  var gv=document.getElementById('gval');
  if(gv){gv.textContent=intensity;gv.style.color=color;}
  var ic=document.getElementById('ichip');
  if(ic) ic.textContent=intensity+' / 100';
  var bm=[['arcu','avu',sc.u],['arcd','avd',sc.d],['arca','ava',sc.a],['arcr','avr',Math.abs(sc.po)+Math.abs(sc.so)]];
  bm.forEach(function(b){
    var v=Math.round(Math.max(0,Math.abs(b[2])));
    var el=document.getElementById(b[0]);if(el)el.style.width=Math.min(100,v)+'%';
    var ev=document.getElementById(b[1]);if(ev)ev.textContent=v;
  });
}

// ── HEATMAP ───────────────────────────────────────────────────────────────────
function updHeat(risk) {
  heatD.push(risk);
  if(heatD.length>20) heatD.shift();
  var cells=document.querySelectorAll('.hmc');
  cells.forEach(function(c,i){
    var idx=Math.floor(i*(heatD.length/cells.length));
    var v=heatD[idx]||0;
    var color;
    if      (v>=80) color='rgba(239,68,68,'  +(.3+v/200)+')';
    else if (v>=50) color='rgba(245,158,11,' +(.25+v/220)+')';
    else if (v>=25) color='rgba(99,102,241,' +(.2+v/250)+')';
    else            color='rgba(16,185,129,'  +(.15+v/300)+')';
    c.style.background=color;
  });
}

// ── LONGITUDINAL CHART ────────────────────────────────────────────────────────
function drawLg() {
  var el=document.getElementById('lgChart');
  if(!el) return;
  var ctx=el.getContext('2d');
  if(lgC) lgC.destroy();
  var n=<?= max(1,count($prev_consults)) ?>;
  var labels=[],vals=[];
  for(var i=0;i<n;i++){labels.push('S'+(i+1));vals.push(Math.round(50+Math.sin(i*.9)*14+Math.random()*8));}
  lgC=new Chart(ctx,{
    type:'line',
    data:{labels:labels,datasets:[{data:vals,borderColor:'#6366f1',borderWidth:1.5,
      pointBackgroundColor:'#6366f1',pointBorderColor:'rgba(10,14,26,.8)',pointBorderWidth:1.5,pointRadius:3,pointHoverRadius:5,
      fill:true,backgroundColor:function(c){var g=c.chart.ctx.createLinearGradient(0,0,0,48);g.addColorStop(0,'rgba(99,102,241,.2)');g.addColorStop(1,'rgba(99,102,241,0)');return g;},tension:.4
    }]},
    options:{responsive:true,maintainAspectRatio:false,animation:{duration:700,easing:'easeInOutQuart'},
      plugins:{legend:{display:false},tooltip:{
        backgroundColor:'rgba(10,14,26,.97)',borderColor:'rgba(255,255,255,.09)',borderWidth:1,padding:8,cornerRadius:8,
        titleFont:{family:'Plus Jakarta Sans',size:8,weight:'800'},bodyFont:{family:'Plus Jakarta Sans',size:9},
        callbacks:{title:function(i){return [i[0].label];},label:function(i){return ' Score: '+i.raw;}}
      }},
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
      b.style.height=(4+Math.random()*16)+'px';
      b.style.background='hsl('+(225+Math.random()*20)+',70%,'+(50+Math.random()*25)+'%)';
    });
  },110);
}
function stopWave(){
  clearInterval(wvIv);
  document.querySelectorAll('#wv div').forEach(function(b){b.style.height='4px';b.style.background='#1f2937';});
}

// ── MICRO — VERSION ROBUSTE ───────────────────────────────────────────────────
(function(){
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) {
    document.getElementById('sttw').style.display='block';
    var mb=document.getElementById('micbtn');
    mb.disabled=true; mb.style.opacity='.3'; mb.style.cursor='not-allowed';
    return;
  }

  function makeRecog() {
    var r = new SR();
    r.lang='fr-FR';
    r.continuous=true;
    r.interimResults=true;
    r.maxAlternatives=1;

    r.onstart=function(){
      // Déclenché quand le micro est vraiment actif
      micOn=true;
      document.getElementById('micbtn').className='mic mic-live';
      document.getElementById('micsvg').setAttribute('stroke','#fff');
      document.getElementById('miclbl').textContent='● CAPTURE ACTIVE — Cliquez pour arrêter';
      document.getElementById('miclbl').style.color='#ef4444';
      document.getElementById('recdot').style.background='#ef4444';
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
        document.getElementById('abar').style.width=(12+Math.random()*55)+'%';
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
      if(e.error==='no-speech') return; // normal, on ignore
      if(e.error==='not-allowed'||e.error==='permission-denied'){
        ntf('ntr','Accès micro refusé. Autorisez le microphone dans votre navigateur.','er');
        setMicStopped();
        return;
      }
      if(e.error==='network'){
        ntf('ntr','Erreur réseau STT. Vérifiez votre connexion.','wa');
      }
      // Pour les autres erreurs, on laisse onend gérer le redémarrage
    };

    r.onend=function(){
      // Si on voulait rester actif → redémarrer automatiquement
      if(micOn){
        try{ recog.start(); }
        catch(ex){ setMicStopped(); }
      }
    };

    return r;
  }

  recog = makeRecog();

  window.toggleMic = function(){
    if(micOn){
      // Arrêter
      micOn=false;
      try{ recog.stop(); }catch(e){}
      setMicStopped();
    } else {
      // Démarrer — recréer l'instance pour éviter les états bloqués
      try{ recog.abort(); }catch(e){}
      recog = makeRecog();
      try{
        recog.start();
        // onstart va confirmer
      } catch(e){
        ntf('ntr','Impossible de démarrer le micro: '+e.message,'er');
      }
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
  document.getElementById('stop').textContent='Transcription complète';
  document.getElementById('btngen').disabled=false;
}

function clearTr(){
  ovO('Effacer toute la transcription ?',function(){
    document.getElementById('tr').value='';
    document.getElementById('wc').textContent='0 mot';
    emoP=[0];tR=[];tRs=[];tD=[];tA=[];heatD=[];
    rD={u:0,d:0,a:0,r:0,s:0,st:100};
    updRisk(0,{u:0,d:0,a:0,po:0,so:0});
    drawEmo();drawRadar();drawTL();
    updArc(0,{u:0,d:0,a:0,po:0,so:0});
    document.querySelectorAll('.hmc').forEach(function(c){c.style.background='#1f2937';});
    ntf('ntr','Transcription effacée.','in',2500);
  });
}

// ── FEED ──────────────────────────────────────────────────────────────────────
function addFeed(type, title, body) {
  var cm={
    info: ['rgba(99,102,241,.06)','rgba(99,102,241,.4)','#a5b4fc'],
    warn: ['rgba(245,158,11,.06)','rgba(245,158,11,.4)','#fcd34d'],
    danger:['rgba(239,68,68,.1)','rgba(239,68,68,.6)','#f87171'],
    ok:  ['rgba(16,185,129,.06)','rgba(16,185,129,.35)','#34d399'],
    q:   ['rgba(56,189,248,.06)','rgba(56,189,248,.35)','#7dd3fc']
  };
  var c=cm[type]||cm.info;
  var bg=c[0],bc=c[1],tc=c[2];

  // Feed gauche
  var ph=document.getElementById('feedph'); if(ph) ph.remove();
  var el=document.createElement('div');
  el.className='fi';
  el.style.cssText='background:'+bg+';border-left-color:'+bc+';';
  el.innerHTML='<p style="font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.13em;color:'+tc+';margin-bottom:2px;">'+title+'</p><p style="font-size:10px;color:#6b7280;line-height:1.55;">'+body+'</p>';
  var feed=document.getElementById('feed');
  feed.insertBefore(el,feed.firstChild);
  while(feed.children.length>14) feed.removeChild(feed.lastChild);
  document.getElementById('btnask').disabled=false;

  // Insight droite
  var now=new Date();
  var ts=now.getHours().toString().padStart(2,'0')+':'+now.getMinutes().toString().padStart(2,'0')+':'+now.getSeconds().toString().padStart(2,'0');
  var iph=document.getElementById('insph'); if(iph) iph.remove();
  var ins=document.createElement('div');
  ins.className='fi';
  ins.style.cssText='background:'+bg+';border-left-color:'+bc+';margin-bottom:5px;';
  ins.innerHTML='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;"><p style="font-size:8px;font-weight:900;text-transform:uppercase;letter-spacing:.13em;color:'+tc+';">'+title+'</p><span style="font-size:7px;font-weight:700;color:#374151;font-variant-numeric:tabular-nums;">'+ts+'</span></div><p style="font-size:10.5px;color:#9ca3af;line-height:1.6;">'+body+'</p>';
  var wrap=document.getElementById('ins');
  wrap.insertBefore(ins,wrap.firstChild);
  while(wrap.children.length>12) wrap.removeChild(wrap.lastChild);
}

// ── API ───────────────────────────────────────────────────────────────────────
async function callAI(prompt, max) {
  max=max||1200;
  var res=await fetch('proxy_ia.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({prompt:prompt,max_tokens:max,csrf_token:CSRF})});
  if(!res.ok) throw new Error('HTTP '+res.status);
  var d=await res.json();
  if(d.error) throw new Error(d.error.message||'API error');
  return d.choices&&d.choices[0]&&d.choices[0].message ? d.choices[0].message.content : '';
}

// ── AUTO-IA ───────────────────────────────────────────────────────────────────
async function autoAI(text, sc, risk) {
  var th=document.getElementById('think'); if(th) th.style.display='flex';
  var hc=HIST.length?'\nHistorique:\n'+HIST.map(function(h){return h.date+'('+h.duree+'min): '+h.resume;}).join('\n'):'';
  var p='Tu es psychologue clinicien superviseur. Analyse EN DIRECT ce verbatim partiel.\nPatient: '+PAT+' | Séance n°'+SESN+'\nRisque: '+risk+'% | Urgence:'+Math.round(sc.u)+' | Détresse:'+Math.round(sc.d)+' | Anxiété:'+Math.round(sc.a)+hc+'\nVerbatim (fin): "'+text.slice(-700)+'"\nJSON uniquement sans markdown:\n{"alerte":null,"observation":"1 phrase","theme":"1 thème","question":"1 question pour le patient","hypothese":null,"code_cim":"code CIM-11 ou null"}';
  try{
    var raw=await callAI(p,400);
    var ai;try{ai=JSON.parse(raw.replace(/```json\n?|\n?```/g,'').trim());}catch(e){return;}
    if(ai.alerte)      addFeed('danger','⚠ ALERTE',ai.alerte);
    if(ai.observation) addFeed('info','Observation',ai.observation);
    if(ai.theme)       addFeed('ok','→ Explorer',ai.theme);
    if(ai.question)    addFeed('q','? Patient',ai.question);
    if(ai.hypothese){
      var b=document.getElementById('diagb');
      var dp=document.getElementById('diagph'); if(dp) dp.remove();
      var d=document.createElement('div');
      d.className='dp fu';
      d.innerHTML=(ai.code_cim?'<span class="dc">'+ai.code_cim+'</span>':'')+'<p style="font-size:10px;color:#93c5fd;line-height:1.55;">'+ai.hypothese+'</p>';
      b.insertBefore(d,b.firstChild);
      if(b.children.length>5) b.removeChild(b.lastChild);
    }
  }catch(e){console.warn('autoAI:',e);}
  finally{ if(th) th.style.display='none'; }
}

// ── QUESTION LIBRE ────────────────────────────────────────────────────────────
function toggleAsk(){
  var box=document.getElementById('askbox');
  box.style.display=box.style.display==='none'?'block':'none';
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
    resp.innerHTML='<div class="card2 fu" style="margin-top:5px;"><p class="lbl" style="color:#818cf8;margin-bottom:4px;">Réponse IA</p><p style="font-size:10px;color:#9ca3af;line-height:1.6;">'+raw+'</p></div>';
    addFeed('info','Q: '+q.slice(0,40)+'…',raw.slice(0,110)+'…');
  }catch(e){resp.innerHTML='<p style="font-size:10px;color:#f87171;margin-top:4px;">Erreur. Réessayez.</p>';}
}

// ── RAPPORT ───────────────────────────────────────────────────────────────────

async function genReport(){
  var text=document.getElementById('tr').value.trim();
  if(text.length<15){ntf('ntr','Volume insuffisant pour la synthèse.','wa');return;}
  var res=analyze(text); var sc=res.sc,risk=res.risk;
  document.getElementById('btngen').disabled=true;
  // no tabs
  var body=document.getElementById('rpbody');
  body.innerHTML='<div style="padding:40px 0;text-align:center;"><div class="dots" style="margin-bottom:10px;"><span></span><span></span><span></span></div><p class="lbl" style="color:#818cf8;">Analyse clinique approfondie en cours...</p></div>';
  var notes=document.getElementById('notes').value;
  var hc=HIST.length?HIST.map(function(h){return 'Séance '+h.date+'('+h.duree+'min): '+h.resume;}).join('\n'):'Première séance';
  var p='Tu es psychologue clinicien senior (20 ans), expert TCC/ACT/EMDR. Rédige un compte-rendu clinique confidentiel de haute qualité.\nPatient: '+PAT+' | Séance n°'+SESN+' | Date: '+DATED+' | Dr. '+DR+'\nRisque: '+risk+'/100 | Urgence: '+Math.round(sc.u)+' | Détresse: '+Math.round(sc.d)+' | Anxiété: '+Math.round(sc.a)+' | Résilience: '+Math.round(Math.abs(sc.po)+Math.abs(sc.so))+'\nNotes: '+(notes||'Aucune')+'\nHistorique:\n'+hc+'\nVerbatim:\n"""'+text+'"""\nJSON uniquement sans markdown:\n{"synthese_courte":"2 phrases","observation":"4-5 phrases","humeur":"3-4 phrases","alliance":"3 phrases","vigilance":"3-4 phrases","axes":"4-5 phrases","hypotheses_diag":["Diag + code CIM-11"],"objectifs_next":["Obj 1","Obj 2","Obj 3"],"plan_therapeutique":"Plan 4 séances","recommandations":"Orientations","lettre_confrere":"Courrier ou null","niveau_risque":"faible|modéré|élevé|critique"}';
  try{
    var raw=await callAI(p,3000);
    var ai;
    try{ai=JSON.parse(raw.replace(/```json\n?|\n?```/g,'').trim());}
    catch(e){throw new Error('Format JSON invalide. Réessayez.');}
    // Snapshot des données émotionnelles au moment du bilan
    var emoSnap = {
      risk:      risk,
      urgence:   Math.round(Math.max(0,sc.u)),
      detresse:  Math.round(Math.max(0,sc.d)),
      anxiete:   Math.round(Math.max(0,sc.a)),
      resilience:Math.round(Math.abs(sc.po)+Math.abs(sc.so)),
      stabilite: Math.max(0,100-risk),
      trend:     document.getElementById('tlchip') ? document.getElementById('tlchip').textContent : '—',
      trendLbl:  document.getElementById('tllbl')  ? document.getElementById('tllbl').textContent  : '—',
      heatData:  heatD.slice(),
      tRisk:     tR.slice(),
      tResil:    tRs.slice(),
      tDetresse: tD.slice(),
      tAnxiete:  tA.slice(),
      emoPoints: emoP.slice(),
      // Canvas snapshots (base64)
      radarImg:    (function(){ try{ return document.getElementById('radarChart').toDataURL('image/png'); } catch(e){ return null; } })(),
      timelineImg: (function(){ try{ return document.getElementById('tlChart').toDataURL('image/png');    } catch(e){ return null; } })(),
      emoImg:      (function(){ try{ return document.getElementById('emoChart').toDataURL('image/png');   } catch(e){ return null; } })()
    };
    lastRpt={ai:ai,sc:sc,risk:risk,snap:emoSnap,text:text,date:new Date().toLocaleDateString('fr-FR')};
    document.getElementById('btnpdf').disabled=false;
    renderRpt(lastRpt);
    // Hypothèses
    if(Array.isArray(ai.hypotheses_diag)){
      var b=document.getElementById('diagb');
      var dp=document.getElementById('diagph');if(dp)dp.remove();
      b.innerHTML='';
      ai.hypotheses_diag.forEach(function(h){
        var d=document.createElement('div');
        d.className='dp fu';
        var m=h.match(/([A-Z][0-9]+\.?[0-9]*)/);
        d.innerHTML=(m?'<span class="dc">'+m[1]+'</span>':'')+'<p style="font-size:10px;color:#93c5fd;line-height:1.55;">'+h+'</p>';
        b.appendChild(d);
      });
    }
    addFeed('ok','Bilan généré','Niveau de risque · '+ai.niveau_risque);
  }catch(err){
    body.innerHTML='<div style="padding:40px 0;text-align:center;"><p style="color:#f87171;font-weight:700;margin-bottom:6px;">Erreur IA</p><p style="color:#4b5563;font-size:11px;">'+err.message+'</p></div>';
  }finally{
    document.getElementById('btngen').disabled=false;
  }
}



// ── CHARTS DANS L'ONGLET ÉMOTIONS DU RAPPORT ──────────────────────────────────
var rptTlChart=null, rptEmoChart=null;

// ── CHARTS RAPPORT ────────────────────────────────────────────────────────────
var rptTlC=null, rptEmoC=null;
function renderRptCharts(sn){
  if(!sn) return;
  var tlEl=document.getElementById('rpt-tl');
  if(tlEl && sn.tRisk && sn.tRisk.length>1){
    if(rptTlC) rptTlC.destroy();
    var ctx=tlEl.getContext('2d');
    function grd(c,c1,c2){var g=c.chart.ctx.createLinearGradient(0,0,0,80);g.addColorStop(0,c1);g.addColorStop(1,c2);return g;}
    rptTlC=new Chart(ctx,{type:'line',
      data:{labels:sn.tRisk.map(function(_,i){return i;}),datasets:[
        {label:'Risque',    data:sn.tRisk,    borderColor:'#ef4444',borderWidth:2,  pointRadius:0,fill:true,tension:.4,backgroundColor:function(c){return grd(c,'rgba(239,68,68,.2)','rgba(239,68,68,0)');}},
        {label:'Résilience',data:sn.tResil,   borderColor:'#10b981',borderWidth:1.5,pointRadius:0,fill:false,tension:.4},
        {label:'Détresse',  data:sn.tDetresse,borderColor:'#f59e0b',borderWidth:1.5,pointRadius:0,fill:false,tension:.4,borderDash:[3,3]},
        {label:'Anxiété',   data:sn.tAnxiete, borderColor:'#8b5cf6',borderWidth:1.5,pointRadius:0,fill:false,tension:.4,borderDash:[2,4]}
      ]},
      options:{responsive:true,maintainAspectRatio:false,animation:{duration:500},
        interaction:{mode:'index',intersect:false},
        plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(10,14,26,.97)',borderColor:'rgba(255,255,255,.09)',borderWidth:1,padding:8,cornerRadius:8,
          titleFont:{family:'Plus Jakarta Sans',size:8,weight:'800'},bodyFont:{family:'Plus Jakarta Sans',size:9},
          callbacks:{title:function(i){return 'T+'+i[0].label;},label:function(i){return ' '+i.dataset.label+': '+i.raw+'%';},
            labelColor:function(i){var c=['#ef4444','#10b981','#f59e0b','#8b5cf6'];return{borderColor:'transparent',backgroundColor:c[i.datasetIndex]||'#6366f1',borderRadius:2};}}}},
        scales:{x:{display:false},y:{min:0,max:100,ticks:{display:false},grid:{color:'rgba(255,255,255,.03)',drawBorder:false},border:{display:false}}}}
    });
  }
  var emoEl=document.getElementById('rpt-emo');
  if(emoEl && sn.emoPoints && sn.emoPoints.length>1){
    if(rptEmoC) rptEmoC.destroy();
    var ctx2=emoEl.getContext('2d');
    var last=sn.emoPoints[sn.emoPoints.length-1]||0;
    var lc=last>0.1?'#10b981':(last<-0.2?'#ef4444':'#6366f1');
    var fc=last>0.1?'rgba(16,185,129,.2)':(last<-0.2?'rgba(239,68,68,.15)':'rgba(99,102,241,.18)');
    rptEmoC=new Chart(ctx2,{type:'line',
      data:{labels:sn.emoPoints.map(function(_,i){return i;}),datasets:[{
        data:sn.emoPoints,borderColor:lc,borderWidth:2,pointRadius:0,fill:true,tension:.4,
        backgroundColor:function(c){var g=c.chart.ctx.createLinearGradient(0,0,0,50);g.addColorStop(0,fc);g.addColorStop(1,'rgba(0,0,0,0)');return g;}
      }]},
      options:{responsive:true,maintainAspectRatio:false,animation:{duration:500},
        plugins:{legend:{display:false}},
        scales:{x:{display:false},y:{min:-1.2,max:1.2,ticks:{display:false},grid:{color:'rgba(255,255,255,.03)',drawBorder:false},border:{display:false}}}}
    });
  }
}

// ── RENDU RAPPORT — vue unique, pas d'onglets ─────────────────────────────────
function renderRpt(lr) {
  if(!lr) return;
  var ai=lr.ai, sc=lr.sc, risk=lr.risk, sn=lr.snap||{};
  var rc={faible:'#10b981',modéré:'#f59e0b',élevé:'#ef4444',critique:'#ff2040'}[ai.niveau_risque]||'#64748b';
  var body=document.getElementById('rpbody');

  // ── barre score helper ──
  function bar(lbl,val,color){
    var v=Math.min(100,Math.round(Math.max(0,val)));
    return '<div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">'
      +'<span style="font-size:8px;font-weight:800;text-transform:uppercase;color:'+color+';width:56px;flex-shrink:0;">'+lbl+'</span>'
      +'<div style="flex:1;height:4px;background:rgba(255,255,255,.05);border-radius:2px;overflow:hidden;">'
        +'<div style="width:'+v+'%;height:4px;background:'+color+';border-radius:2px;"></div>'
      +'</div>'
      +'<span style="font-size:9.5px;font-weight:900;color:'+color+';width:22px;text-align:right;">'+v+'</span>'
    +'</div>';
  }

  // ── Heatmap ──
  var hmHtml='';
  if(sn.heatData && sn.heatData.length){
    hmHtml='<div style="display:flex;gap:2px;height:14px;border-radius:3px;overflow:hidden;">';
    sn.heatData.forEach(function(hv){
      var hc;
      if(hv>=80)      hc='rgba(239,68,68,'+Math.min(1,(.3+hv/200))+')';
      else if(hv>=50) hc='rgba(245,158,11,'+Math.min(1,(.25+hv/220))+')';
      else if(hv>=25) hc='rgba(99,102,241,'+Math.min(1,(.2+hv/250))+')';
      else            hc='rgba(16,185,129,'+Math.min(1,(.15+hv/300))+')';
      hmHtml+='<div style="flex:1;background:'+hc+';"></div>';
    });
    hmHtml+='</div>';
  }

  // ── Construit le HTML ──
  var html='<div class="fu">';

  // SECTION 1 — RISQUE + SYNTHÈSE
  html+='<div class="rs" style="background:'+rc+'0d;border-color:'+rc+'30;margin-bottom:8px;">'
    +'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">'
      +'<span class="lbl" style="color:'+rc+';">Risque évalué</span>'
      +'<span class="chip" style="background:'+rc+'22;color:'+rc+';border-color:'+rc+'44;">'+((ai.niveau_risque||'')).toUpperCase()+'</span>'
    +'</div>'
    +'<p style="font-size:13px;color:#e2e8f0;line-height:1.7;font-weight:500;">'+( ai.synthese_courte||'—')+'</p>'
  +'</div>';

  // SECTION 2 — DONNÉES ÉMOTIONNELLES
  if(sn.urgence||sn.detresse||sn.anxiete||sn.resilience||sn.tRisk){
    html+='<div class="rs" style="background:rgba(10,14,26,.45);border-color:rgba(255,255,255,.07);margin-bottom:8px;">'
      +'<p class="lbl" style="color:#818cf8;margin-bottom:10px;">Profil émotionnel · séance</p>';

    // Barres scores
    html+=bar('Urgence',  sn.urgence||0,   '#ef4444')
         +bar('Détresse', sn.detresse||0,  '#f59e0b')
         +bar('Anxiété',  sn.anxiete||0,   '#8b5cf6')
         +bar('Résilience',sn.resilience||0,'#10b981')
         +bar('Stabilité',sn.stabilite||0, '#6366f1');

    // Heatmap
    if(hmHtml){
      html+='<div style="margin-top:10px;">'
        +'<p style="font-size:7.5px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px;">Carte thermique émotionnelle</p>'
        +hmHtml
        +'<div style="display:flex;justify-content:space-between;margin-top:2px;">'
          +'<span style="font-size:7px;color:#374151;font-weight:600;">Début</span>'
          +'<span style="font-size:7px;color:#374151;font-weight:600;">Fin</span>'
        +'</div>'
      +'</div>';
    }

    // Timeline chart
    if(sn.tRisk && sn.tRisk.length>1){
      html+='<div style="margin-top:10px;">'
        +'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">'
          +'<p style="font-size:7.5px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.1em;">Timeline émotionnelle</p>'
          +'<div style="display:flex;gap:8px;">';
      [['#ef4444','Risque'],['#10b981','Résil.'],['#f59e0b','Détr.'],['#8b5cf6','Anx.']].forEach(function(pair){
        html+='<div style="display:flex;align-items:center;gap:2px;"><span style="width:10px;height:2px;background:'+pair[0]+';display:inline-block;border-radius:1px;"></span><span style="font-size:7px;font-weight:700;color:#374151;">'+pair[1]+'</span></div>';
      });
      html+='</div></div><div style="height:72px;position:relative;"><canvas id="rpt-tl"></canvas></div>';
      html+='</div>';
    }

    // Valence
    if(sn.emoPoints && sn.emoPoints.length>1){
      html+='<div style="margin-top:8px;">'
        +'<p style="font-size:7.5px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px;">Valence émotionnelle · positif / négatif</p>'
        +'<div style="height:44px;position:relative;"><canvas id="rpt-emo"></canvas></div>'
      +'</div>';
    }

    html+='</div>';
  }

  // SECTION 3 — COMPTE-RENDU CLINIQUE
  html+='<div class="rs" style="background:rgba(99,102,241,.04);border-color:rgba(99,102,241,.14);margin-bottom:8px;">'
    +'<p class="lbl" style="color:#818cf8;margin-bottom:8px;">Observation clinique</p>'
    +'<p style="font-size:11px;color:#9ca3af;line-height:1.75;">'+( ai.observation||'—')+'</p>'
  +'</div>';

  // Humeur + Alliance côte à côte
  html+='<div style="display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-bottom:8px;">'
    +'<div class="rs" style="background:rgba(245,158,11,.04);border-color:rgba(245,158,11,.13);">'
      +'<p class="lbl" style="color:#fbbf24;margin-bottom:4px;">Humeur</p>'
      +'<p style="font-size:11px;color:#9ca3af;line-height:1.65;">'+( ai.humeur||'—')+'</p>'
    +'</div>'
    +'<div class="rs" style="background:rgba(139,92,246,.04);border-color:rgba(139,92,246,.13);">'
      +'<p class="lbl" style="color:#a78bfa;margin-bottom:4px;">Alliance</p>'
      +'<p style="font-size:11px;color:#9ca3af;line-height:1.65;">'+( ai.alliance||'—')+'</p>'
    +'</div>'
  +'</div>';

  // Vigilance
  if(ai.vigilance){
    html+='<div class="rs" style="background:rgba(239,68,68,.04);border-color:rgba(239,68,68,.15);margin-bottom:8px;">'
      +'<p class="lbl" style="color:#f87171;margin-bottom:4px;">⚠ Vigilance</p>'
      +'<p style="font-size:11px;color:#9ca3af;line-height:1.7;">'+ai.vigilance+'</p>'
    +'</div>';
  }

  // Axes + Plan
  if(ai.axes){
    html+='<div class="rs" style="background:rgba(16,185,129,.04);border-color:rgba(16,185,129,.13);margin-bottom:8px;">'
      +'<p class="lbl" style="color:#34d399;margin-bottom:4px;">Axes thérapeutiques</p>'
      +'<p style="font-size:11px;color:#9ca3af;line-height:1.75;">'+ai.axes+'</p>'
    +'</div>';
  }
  if(ai.plan_therapeutique){
    html+='<div class="rs" style="background:rgba(99,102,241,.04);border-color:rgba(99,102,241,.13);margin-bottom:8px;">'
      +'<p class="lbl" style="color:#818cf8;margin-bottom:4px;">Plan · prochaines séances</p>'
      +'<p style="font-size:11px;color:#9ca3af;line-height:1.75;white-space:pre-line;">'+ai.plan_therapeutique+'</p>'
    +'</div>';
  }

  // Hypothèses diag
  if(Array.isArray(ai.hypotheses_diag) && ai.hypotheses_diag.length){
    html+='<div class="rs" style="background:rgba(56,189,248,.04);border-color:rgba(56,189,248,.14);margin-bottom:8px;">'
      +'<p class="lbl" style="color:#38bdf8;margin-bottom:6px;">Hypothèses · CIM-11</p>';
    ai.hypotheses_diag.forEach(function(h){
      var m=h.match(/([A-Z][A-Z0-9]+\.?[0-9]*)/);
      html+='<div style="display:flex;align-items:flex-start;gap:7px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04);">'
        +(m?'<span style="font-size:8px;font-weight:900;color:#38bdf8;background:rgba(56,189,248,.12);border-radius:4px;padding:2px 5px;white-space:nowrap;flex-shrink:0;margin-top:1px;">'+m[1]+'</span>':'')+
        '<p style="font-size:10.5px;color:#93c5fd;line-height:1.55;">'+h+'</p></div>';
    });
    html+='</div>';
  }

  // Courrier confrère
  if(ai.lettre_confrere){
    html+='<div class="rs" style="background:rgba(30,41,59,.5);border-color:rgba(255,255,255,.08);margin-bottom:8px;">'
      +'<p class="lbl" style="color:#4b5563;margin-bottom:6px;">Courrier de liaison</p>'
      +'<p style="font-size:10.5px;color:#6b7280;line-height:1.75;white-space:pre-line;">'+ai.lettre_confrere+'</p>'
    +'</div>';
  }

  html+='</div>';
  body.innerHTML = html;

  // Dessiner les charts après rendu DOM
  setTimeout(function(){ renderRptCharts(sn); }, 60);
}

// ── LOAD PREV ─────────────────────────────────────────────────────────────────
function loadPrev(txt){
  ovO('Charger ce résumé dans les notes ?',function(){
    var n=document.getElementById('notes');
    n.value=(n.value?n.value+'\n\n':'')+'[Séance précédente]\n'+txt.slice(0,500);
  });
}

// ── EXPORT PDF ────────────────────────────────────────────────────────────────
function exportPDF(){
  if(!lastRpt){ntf('narch',"Générez d'abord le bilan.",'wa');return;}
  var j=window.jspdf.jsPDF;
  var doc=new j({unit:'mm',format:'a4'});
  var W=210,M=18;var y=M;
  function w(txt,o){
    o=o||{};var sz=o.sz||9,b=o.b||false,c=o.c||[65,75,100],ind=o.in_||0,lh=o.lh||5.5;
    doc.setFontSize(sz);doc.setFont('helvetica',b?'bold':'normal');doc.setTextColor(c[0],c[1],c[2]);
    doc.splitTextToSize(String(txt),W-M*2-ind).forEach(function(l){if(y>272){doc.addPage();y=M;}doc.text(l,M+ind,y);y+=lh;});y+=1.5;
  }
  function hr(){doc.setDrawColor(210,215,225);doc.setLineWidth(.12);doc.line(M,y,W-M,y);y+=4;}
  function sec(t,txt,c){w(t,{sz:8,b:true,c:c||[40,50,130]});w(txt,{in_:3,sz:9,lh:5,c:[58,68,95]});y+=1;}
  doc.setFillColor(10,14,26);doc.rect(0,0,W,30,'F');
  doc.setFillColor(99,102,241);doc.rect(0,0,4,30,'F');
  doc.setFontSize(15);doc.setFont('helvetica','bold');doc.setTextColor(255,255,255);doc.text('PsySpace Pro',M+2,12);
  doc.setFontSize(7);doc.setFont('helvetica','normal');doc.setTextColor(148,163,184);
  doc.text('COMPTE-RENDU DE CONSULTATION — CONFIDENTIEL',M+2,19);
  doc.text('Patient: '+PAT+'  ·  Séance n°'+SESN+'  ·  '+lastRpt.date+'  ·  '+Math.floor(secs/60)+' min  ·  Dr. '+DR,M+2,26);
  y=38;
  var ai=lastRpt.ai,sc=lastRpt.sc;
  w('SYNTHÈSE CLINIQUE',{sz:9,b:true,c:[30,40,60]});hr();
  w('Niveau de risque: '+(ai.niveau_risque||'').toUpperCase(),{in_:3,sz:8,b:true,c:[170,40,60]});
  w(ai.synthese_courte||'',{in_:3,sz:9,lh:5.5,c:[45,55,90]});y+=2;
  sec('OBSERVATION',ai.observation,[40,50,120]);
  sec('HUMEUR',ai.humeur,[130,90,20]);
  sec('ALLIANCE',ai.alliance,[100,50,150]);
  sec('VIGILANCE',ai.vigilance,[160,40,40]);
  sec('AXES THÉRAPEUTIQUES',ai.axes,[20,120,70]);
  if(ai.recommandations) sec('RECOMMANDATIONS',ai.recommandations,[60,80,120]);
  if(Array.isArray(ai.hypotheses_diag)&&ai.hypotheses_diag.length){w('HYPOTHÈSES (CIM-11)',{sz:8,b:true,c:[30,40,60]});ai.hypotheses_diag.forEach(function(h){w('• '+h,{in_:3,sz:9,c:[40,80,110]});});y+=1;}
  if(Array.isArray(ai.objectifs_next)&&ai.objectifs_next.length){w('OBJECTIFS',{sz:8,b:true,c:[20,150,150]});ai.objectifs_next.forEach(function(o,i){w((i+1)+'. '+o,{in_:3,sz:9,c:[35,100,100]});});y+=1;}
  if(ai.plan_therapeutique) sec('PLAN',ai.plan_therapeutique,[50,60,130]);
  if(ai.lettre_confrere){hr();w('COURRIER',{sz:8,b:true,c:[30,40,60]});w(ai.lettre_confrere,{in_:3,sz:9,lh:5,c:[50,60,90]});}
  hr();
  w('INDICATEURS',{sz:8,b:true,c:[30,40,60]});
  w('Risque: '+lastRpt.risk+'%  Urgence: '+Math.round(sc.u)+'  Détresse: '+Math.round(sc.d)+'  Anxiété: '+Math.round(sc.a)+'  Résilience: '+Math.round(Math.abs(sc.po)+Math.abs(sc.so)),{in_:3,sz:8.5});
  // Snapshot visuel émotionnel dans le PDF
  var sn=lastRpt.snap;
  if(sn){
    hr();
    w('PROFIL PSYCHO-ÉMOTIONNEL · SÉANCE',{sz:8,b:true,c:[30,40,60]});
    // Radar image
    if(sn.radarImg){
      try{
        if(y>220){doc.addPage();y=18;}
        doc.addImage(sn.radarImg,'PNG',M,y,60,60);
        // Barres à droite du radar
        var bx=M+65, by=y+5;
        var bars=[['Urgence',sn.urgence,[239,68,68]],['Détresse',sn.detresse,[245,158,11]],['Anxiété',sn.anxiete,[139,92,246]],['Résilience',sn.resilience,[16,185,129]],['Stabilité',sn.stabilite,[99,102,241]]];
        bars.forEach(function(b){
          doc.setFontSize(7);doc.setFont('helvetica','bold');doc.setTextColor(b[2][0],b[2][1],b[2][2]);
          doc.text(b[0].toUpperCase(),bx,by);
          var barW=Math.max(1,Math.round((Math.min(100,b[1])/100)*80));
          doc.setFillColor(30,40,60);doc.rect(bx+28,by-3,80,3,'F');
          doc.setFillColor(b[2][0],b[2][1],b[2][2]);doc.rect(bx+28,by-3,barW,3,'F');
          doc.setFontSize(7);doc.setFont('helvetica','bold');doc.setTextColor(b[2][0],b[2][1],b[2][2]);
          doc.text(String(Math.round(b[1])),bx+112,by);
          by+=9;
        });
        y+=65;
      }catch(e){}
    }
    // Timeline image
    if(sn.timelineImg){
      try{
        if(y>220){doc.addPage();y=18;}
        doc.setFontSize(7);doc.setFont('helvetica','bold');doc.setTextColor(90,100,150);
        doc.text('TIMELINE ÉMOTIONNELLE · ÉVOLUTION SÉANCE',M,y);y+=3;
        if(sn.trendLbl){doc.setFontSize(7);doc.setFont('helvetica','normal');doc.setTextColor(100,110,130);doc.text('Tendance: '+sn.trendLbl,M,y);y+=3;}
        doc.addImage(sn.timelineImg,'PNG',M,y,W-M*2,28);
        y+=32;
      }catch(e){}
    }
    // Heatmap textuelle si pas d'image
    if(!sn.timelineImg && sn.heatData && sn.heatData.length){
      doc.setFontSize(7);doc.setFont('helvetica','normal');doc.setTextColor(80,90,110);
      doc.text('Heatmap émotionnelle: ['+sn.heatData.map(function(v){return Math.round(v);}).join(', ')+']',M,y);y+=6;
    }
  }
  var notes=document.getElementById('notes').value;
  if(notes){y+=2;w('NOTES CLINICIENNES',{sz:8,b:true,c:[30,40,60]});w(notes,{in_:3,sz:8,lh:4.5,c:[85,95,115]});}
  hr();w('VERBATIM (extrait)',{sz:8,b:true,c:[30,40,60]});
  w(lastRpt.text.slice(0,600)+(lastRpt.text.length>600?'…':''),{in_:3,sz:8,lh:4.5,c:[80,90,110]});
  doc.setFontSize(6.5);doc.setTextColor(130,140,155);doc.text('PsySpace Pro 2026 · Confidentiel · Usage clinique exclusif',M,289);
  doc.save('CR_'+PAT.replace(/\s+/g,'_')+'_S'+SESN+'_'+lastRpt.date.replace(/\//g,'-')+'.pdf');
}

// ── ARCHIVER ──────────────────────────────────────────────────────────────────
async function finalize(){
  var tr=document.getElementById('tr').value.trim();
  if(!tr){ntf('narch','Aucune transcription à archiver.','wa');return;}
  ovO('Archiver la séance n°'+SESN+' de <strong>'+PAT+'</strong> ?',async function(){
    ntf('narch','Archivage en cours...','in',0);
    var fd=new FormData();
    fd.append('csrf_token',CSRF);
    fd.append('transcript',tr);
    fd.append('resume',lastRpt?JSON.stringify(lastRpt.ai):'');
    fd.append('duree',Math.floor(secs/60));
    fd.append('emotions',JSON.stringify(emoP));
    try{
      var res=await fetch('save_consultation.php',{method:'POST',body:fd});
      if(!res.ok) throw new Error('HTTP '+res.status);
      var d=await res.text();
      if(d.trim()==='success'){
        ntf('narch','Séance archivée avec succès !','ok',0);
        setTimeout(function(){window.location.href='dashboard.php';},1600);
      }else{ntf('narch','Erreur: '+d.trim(),'er');}
    }catch(e){ntf('narch','Erreur réseau.','er');}
  });
}

// ── INIT ──────────────────────────────────────────────────────────────────────
drawEmo();
drawRadar();
drawTL();
drawLg();
</script>
</body>
</html>