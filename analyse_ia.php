<?php
// ════════════════════════════════════════════════════════════════
//  PsySpace · analyse_ia.php  — Séance IA v3
// ════════════════════════════════════════════════════════════════

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

$appointment_id = (int)($_GET['id'] ?? 0);
if (!$appointment_id) { header("Location: dashboard.php"); exit(); }
$doctor_id  = (int)$_SESSION['id'];
$nom_docteur = $_SESSION['nom'] ?? 'Docteur';

$stmt = $conn->prepare("SELECT patient_id, patient_name, app_date FROM appointments WHERE id=? AND doctor_id=? LIMIT 1");
$stmt->bind_param("ii", $appointment_id, $doctor_id);
$stmt->execute();
$rAppt = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$rAppt) { header("Location: dashboard.php?error=unauthorized"); exit(); }

$patient_id       = (int)$rAppt['patient_id'];
$patient_selected = trim($rAppt['patient_name']);
$appt_date        = $rAppt['app_date'] ? date('d/m/Y', strtotime($rAppt['app_date'])) : date('d/m/Y');

$stmt = $conn->prepare("SELECT * FROM doctor WHERE docid=? LIMIT 1");
$stmt->bind_param("i", $doctor_id); $stmt->execute();
$doc = $stmt->get_result()->fetch_assoc(); $stmt->close();
$doc_prenom    = $doc['docprenom'] ?? '';
$doc_nom_db    = $doc['docname']   ?? $nom_docteur;
$doc_specialty = $doc['specialty'] ?? 'Psychologue clinicien';
$doc_adresse   = $doc['adresse']   ?? '';
$doc_tel       = $doc['tel']       ?? '';
$doc_rpps      = $doc['rpps']      ?? '';
$doc_fullname  = trim($doc_prenom . ' ' . $doc_nom_db);

$pat = [];
if ($patient_id) {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $patient_id); $stmt->execute();
    $pat = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
}
$pat_dob        = $pat['pdob']          ?? null;
$pat_age        = $pat_dob ? (int)date_diff(date_create($pat_dob), date_create('today'))->y : null;
$pat_gender     = $pat['pgender']       ?? null;
$pat_profession = $pat['pprofession']   ?? null;
$pat_situation  = $pat['psituation']    ?? null;
$pat_motif_init = $pat['pmotif_initial'] ?? null;
$pat_antecedents= $pat['pantecedents']  ?? null;
$pat_traitement = $pat['ptraitement']   ?? null;
$pat_notes_admin= $pat['pnotes_admin']  ?? null;
$pat_ville      = $pat['pville']        ?? ($pat['pcity'] ?? ($pat['ville'] ?? ($pat['city'] ?? null)));

// Historique consultations (6 dernières)
$prev_consults = [];
$stmt2 = $conn->prepare(
    "SELECT id, date_consultation, resume_ia, duree_minutes,
            plan_therapeutique, objectifs_suivants, niveau_risque,
            emotions_plutchik, motif_seance, evolution_inter
     FROM consultations
     WHERE patient_id=? AND doctor_id=?
     ORDER BY date_consultation DESC LIMIT 6"
);
$stmt2->bind_param("ii", $patient_id, $doctor_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row2 = $res2->fetch_assoc()) $prev_consults[] = $row2;
$stmt2->close();

$session_num = count($prev_consults) + 1;
$is_followup = $session_num > 1;

// Pour les séances de suivi, on utilise l'âge/ville de la 1ère séance si disponible
// (l'âge peut avoir changé, mais la référence reste celle de la 1ère séance enregistrée)
$pat_age_display   = $pat_age;
$pat_ville_display = $pat_ville;
if ($is_followup) {
    $stmt_first = $conn->prepare(
        "SELECT age_patient, ville_patient FROM consultations
         WHERE patient_id=? AND doctor_id=?
         ORDER BY date_consultation ASC LIMIT 1"
    );
    if ($stmt_first) {
        $stmt_first->bind_param("ii", $patient_id, $doctor_id);
        $stmt_first->execute();
        $first_consult = $stmt_first->get_result()->fetch_assoc() ?: [];
        $stmt_first->close();
        // Utilise la valeur de la 1ère séance seulement si elle existe
        if (!empty($first_consult['age_patient'])) $pat_age_display = (int)$first_consult['age_patient'];
        if (!empty($first_consult['ville_patient'])) $pat_ville_display = $first_consult['ville_patient'];
    }
}

// Objectifs en cours (non atteints)
$goals_open = [];
$stmtG = $conn->prepare(
    "SELECT id, goal_text, created_at FROM consultation_goals
     WHERE patient_id=? AND doctor_id=? AND status='pending'
     ORDER BY created_at DESC LIMIT 10"
);
if ($stmtG) {
    $stmtG->bind_param("ii", $patient_id, $doctor_id);
    $stmtG->execute();
    $resG = $stmtG->get_result();
    while ($g = $resG->fetch_assoc()) $goals_open[] = $g;
    $stmtG->close();
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
$_SESSION['pending_appointment_id'] = $appointment_id;
$_SESSION['pending_patient_name']   = $patient_selected;
$_SESSION['pending_doctor_id']      = $doctor_id;

// Historique pour l'IA (3 dernières séances)
$history_for_ai = [];
foreach (array_slice($prev_consults, 0, 3) as $pc) {
    $emotions_str = '';
    if (!empty($pc['emotions_plutchik'])) {
        $emo = json_decode($pc['emotions_plutchik'], true);
        if (is_array($emo)) {
            $top = array_slice(array_filter($emo, fn($v)=>$v>20), 0, 3, true);
            $emotions_str = implode(', ', array_map(fn($k,$v)=>"$k:$v%", array_keys($top), $top));
        }
    }
    $history_for_ai[] = [
        'date'       => date('d/m/Y', strtotime($pc['date_consultation'])),
        'duree'      => $pc['duree_minutes'],
        'resume'     => mb_substr(strip_tags($pc['resume_ia'] ?? ''), 0, 350, 'UTF-8'),
        'motif'      => $pc['motif_seance'] ?? '',
        'risque'     => $pc['niveau_risque'] ?? 'faible',
        'emotions'   => $emotions_str,
        'plan'       => mb_substr($pc['plan_therapeutique'] ?? '', 0, 200, 'UTF-8'),
        'objectifs'  => $pc['objectifs_suivants'] ?? '',
        'evolution'  => $pc['evolution_inter'] ?? '',
    ];
}

$risque_tendance = 'stable';
if (count($prev_consults) >= 2) {
    $map = ['faible'=>1,'modéré'=>2,'élevé'=>3,'critique'=>4];
    $r1 = $map[$prev_consults[0]['niveau_risque'] ?? 'faible'] ?? 1;
    $r2 = $map[$prev_consults[1]['niveau_risque'] ?? 'faible'] ?? 1;
    if ($r1 > $r2) $risque_tendance = 'hausse';
    elseif ($r1 < $r2) $risque_tendance = 'baisse';
}

$last_plan = $prev_consults[0]['plan_therapeutique'] ?? null;
$last_obj  = $prev_consults[0]['objectifs_suivants'] ?? null;
$last_emo  = $prev_consults[0]['emotions_plutchik']  ?? null;
$last_emo_arr = $last_emo ? json_decode($last_emo, true) : null;

$pat_initials = mb_strtoupper(mb_substr($patient_selected, 0, 1, 'UTF-8'), 'UTF-8');
if (strpos($patient_selected, ' ') !== false) {
    $parts = explode(' ', $patient_selected);
    $pat_initials = mb_strtoupper(mb_substr($parts[0],0,1,'UTF-8').mb_substr(end($parts),0,1,'UTF-8'), 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
<link rel="icon" type="image/png" href="assets/images/logo.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Séance · <?= htmlspecialchars($patient_selected) ?> | PsySpace</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,700;1,9..144,400;1,9..144,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F2EFE9;--bg2:#FFFFFF;--bg3:#EAE7E1;--bg4:#F7F5F2;
  --border:#D9D4CC;--border2:#E8E4DE;
  --tx:#1C1916;--tx2:#4A4540;--tx3:#8C867E;--tx4:#B8B2AA;
  --ink:#0F3460;--ink2:#1A237E;--ink-bg:#EEF2FF;
  --teal:#00796B;--teal-bg:#E0F2F1;
  --amber:#C77700;--amber-bg:#FFF8E1;
  --rose:#B71C1C;--rose-bg:#FFF3F3;
  --violet:#6A1B9A;--violet-bg:#F3E5F5;
  --ok:#1B6B3A;--ok-bg:#EDFAF3;
  --sh1:0 1px 4px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.06);
  --sh2:0 2px 8px rgba(0,0,0,.08),0 8px 32px rgba(0,0,0,.08);
  --r:14px;--r2:9px;--r3:6px;
}
[data-theme="dark"]{
  --bg:#12100E;--bg2:#1C1916;--bg3:#252220;--bg4:#1C1916;
  --border:#2C2926;--border2:#252220;
  --tx:#EDE9E3;--tx2:#9A948C;--tx3:#5A5550;--tx4:#3A3530;
  --ink:#7B9FFF;--ink2:#A8C0FF;--ink-bg:rgba(123,159,255,.09);
  --teal:#4DB6AC;--teal-bg:rgba(77,182,172,.09);
  --amber:#FFB300;--amber-bg:rgba(255,179,0,.08);
  --rose:#EF5350;--rose-bg:rgba(239,83,80,.08);
  --violet:#CE93D8;--violet-bg:rgba(206,147,216,.08);
  --ok:#4CAF82;--ok-bg:rgba(76,175,130,.09);
  --sh1:0 1px 4px rgba(0,0,0,.25);
  --sh2:0 4px 24px rgba(0,0,0,.35);
}
html,body{height:100%;font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);transition:background .3s,color .3s;}
body{overflow:hidden;}
.app{display:grid;grid-template-rows:56px 1fr;height:100vh;}
.main{display:grid;grid-template-columns:290px 1fr 320px;height:calc(100vh - 56px);overflow:hidden;}
.col{overflow-y:auto;padding:18px 14px;display:flex;flex-direction:column;gap:12px;}
.col::-webkit-scrollbar{width:3px;}
.col::-webkit-scrollbar-track{background:transparent;}
.col::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px;}

/* TOPBAR */
.topbar{display:flex;align-items:center;justify-content:space-between;padding:0 20px;background:var(--bg2);border-bottom:1px solid var(--border);box-shadow:var(--sh1);position:relative;z-index:100;}
.topbar::after{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,var(--ink) 0%,transparent 50%);}
.topbar-left{display:flex;align-items:center;gap:12px;}
.back-btn{display:flex;align-items:center;gap:5px;color:var(--tx3);font-size:12px;font-weight:600;text-decoration:none;padding:5px 11px;border-radius:var(--r3);border:1px solid var(--border);background:var(--bg3);transition:all .18s;letter-spacing:.01em;}
.back-btn:hover{color:var(--tx);border-color:var(--ink);background:var(--ink-bg);}
.sep{width:1px;height:22px;background:var(--border);}
.pat-badge{display:flex;align-items:center;gap:10px;}
.pat-ava{width:36px;height:36px;border-radius:10px;background:var(--ink-bg);border:2px solid var(--ink);display:flex;align-items:center;justify-content:center;font-family:'Fraunces',serif;font-size:14px;font-weight:700;color:var(--ink);}
.pat-name{font-size:14px;font-weight:700;color:var(--tx);}
.pat-meta{font-size:10.5px;color:var(--tx3);margin-top:1px;}
.topbar-right{display:flex;align-items:center;gap:8px;}
.timer-pill{display:flex;align-items:center;gap:7px;padding:5px 13px;border-radius:99px;border:1px solid var(--border);background:var(--bg3);}
.t-dot{width:7px;height:7px;border-radius:50%;background:var(--tx4);transition:background .3s;}
.t-dot.live{background:#E53935;animation:pulse-dot 1.4s ease-in-out infinite;}
.t-txt{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:500;color:var(--tx);letter-spacing:.08em;}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.3;transform:scale(.7)}}
.ctrl-btn{width:36px;height:36px;border-radius:var(--r3);border:1px solid var(--border);background:var(--bg3);color:var(--tx3);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:15px;transition:all .18s;}
.ctrl-btn:hover{color:var(--tx);border-color:var(--ink);background:var(--ink-bg);}
.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;border:1px solid;}
.c-idle{background:var(--bg3);color:var(--tx3);border-color:var(--border);}
.c-live{background:var(--rose-bg);color:var(--rose);border-color:rgba(183,28,28,.25);}
.c-ok{background:var(--ok-bg);color:var(--ok);border-color:rgba(27,107,58,.25);}
.c-info{background:var(--ink-bg);color:var(--ink);border-color:rgba(15,52,96,.2);}
.c-warn{background:var(--amber-bg);color:var(--amber);border-color:rgba(199,119,0,.25);}
.c-crit{background:var(--rose-bg);color:var(--rose);border-color:rgba(183,28,28,.35);animation:blink-crit 1.2s ease-in-out infinite;}
@keyframes blink-crit{0%,100%{opacity:1}50%{opacity:.5}}

/* CARDS */
.card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:15px;box-shadow:var(--sh1);}
.card-hd{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:var(--tx3);margin-bottom:11px;display:flex;align-items:center;gap:7px;}
.hd-dot{width:5px;height:5px;border-radius:50%;background:var(--ink);flex-shrink:0;}

/* PROFIL */
.prof-ava{width:52px;height:52px;border-radius:14px;background:var(--ink-bg);border:2px solid var(--ink);display:flex;align-items:center;justify-content:center;font-family:'Fraunces',serif;font-size:20px;font-weight:700;color:var(--ink);flex-shrink:0;}
.prof-name{font-family:'Fraunces',serif;font-size:17px;font-weight:700;color:var(--tx);letter-spacing:-.02em;line-height:1.2;}
.prof-sub{font-size:11px;color:var(--tx3);margin-top:3px;line-height:1.6;}
.prof-tags{display:flex;flex-wrap:wrap;gap:4px;margin-top:8px;}
.ptag{font-size:10px;font-weight:600;padding:2px 8px;border-radius:99px;border:1px solid var(--border);background:var(--bg3);color:var(--tx2);}
.inter-strip{display:flex;align-items:stretch;gap:0;border:1px solid var(--border);border-radius:var(--r2);overflow:hidden;background:var(--bg3);margin-top:4px;}
.inter-cell{flex:1;padding:10px 12px;text-align:center;border-right:1px solid var(--border);}
.inter-cell:last-child{border-right:none;}
.inter-lbl{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--tx3);margin-bottom:4px;}
.inter-val{font-family:'Fraunces',serif;font-size:20px;font-weight:700;color:var(--tx);line-height:1;}
.inter-sub{font-size:9px;color:var(--tx3);margin-top:2px;}

/* INPUTS */
.ta{width:100%;background:var(--bg3);border:1.5px solid var(--border);color:var(--tx);border-radius:var(--r2);resize:none;outline:none;padding:12px;font-family:'DM Sans',sans-serif;font-size:13.5px;line-height:1.8;transition:border-color .2s,box-shadow .2s;}
.ta:focus{border-color:var(--ink);box-shadow:0 0 0 3px var(--ink-bg);}
.ta::placeholder{color:var(--tx4);font-style:italic;font-size:13px;}
.ta-plan{width:100%;background:var(--teal-bg);border:1.5px solid rgba(0,121,107,.25);color:var(--tx);border-radius:var(--r2);resize:none;outline:none;padding:12px;font-family:'DM Sans',sans-serif;font-size:13px;line-height:1.75;transition:border-color .2s;}
.ta-plan:focus{border-color:var(--teal);box-shadow:0 0 0 3px var(--teal-bg);}
.ta-plan::placeholder{color:var(--tx4);font-style:italic;}

/* NOTES PRATICIEN */
.ta-notes-big{
  width:100%;
  background:var(--amber-bg);
  border:2px solid rgba(199,119,0,.3);
  color:var(--tx);
  border-radius:var(--r2);
  resize:vertical;
  outline:none;
  padding:16px;
  font-family:'DM Sans',sans-serif;
  font-size:14px;
  line-height:1.85;
  transition:border-color .2s,box-shadow .2s;
  min-height:160px;
}
.ta-notes-big:focus{border-color:var(--amber);box-shadow:0 0 0 4px var(--amber-bg);}
.ta-notes-big::placeholder{color:var(--tx4);font-style:italic;}

/* BUTTONS */
.btn{font-size:11.5px;font-weight:700;border-radius:var(--r2);padding:8px 16px;border:none;cursor:pointer;transition:all .18s;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-family:'DM Sans',sans-serif;letter-spacing:.01em;}
.btn-ink{background:var(--ink);color:#fff;box-shadow:0 2px 8px rgba(15,52,96,.3);}
.btn-ink:hover{background:var(--ink2);}
.btn-ink:disabled{background:var(--border);color:var(--tx3);box-shadow:none;cursor:not-allowed;}
.btn-teal{background:var(--teal);color:#fff;}
.btn-teal:hover{filter:brightness(1.1);}
.btn-ghost{background:var(--bg3);color:var(--tx2);border:1.5px solid var(--border);}
.btn-ghost:hover{border-color:var(--ink);color:var(--ink);}
.btn-danger{background:var(--rose-bg);color:var(--rose);border:1.5px solid rgba(183,28,28,.2);}
.btn-archive{background:var(--ok);color:#fff;font-size:13px;font-weight:700;padding:13px 24px;border-radius:var(--r);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s;box-shadow:0 3px 14px rgba(27,107,58,.35);width:100%;font-family:'DM Sans',sans-serif;}
.btn-archive:hover{filter:brightness(1.08);box-shadow:0 5px 22px rgba(27,107,58,.5);transform:translateY(-1px);}

/* MIC */
.mic-btn{width:50px;height:50px;border-radius:50%;border:2px solid var(--border);background:var(--bg3);color:var(--tx3);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .22s;flex-shrink:0;}
.mic-btn.live{background:#E53935;border-color:#E53935;color:#fff;animation:mic-ring 1.4s ease-out infinite;}
.mic-btn.done{background:var(--ok-bg);border-color:var(--ok);color:var(--ok);}
@keyframes mic-ring{0%{box-shadow:0 0 0 0 rgba(229,57,53,.45)}70%{box-shadow:0 0 0 16px rgba(229,57,53,0)}100%{box-shadow:0 0 0 0 rgba(229,57,53,0)}}

/* TOAST */
.toast{padding:8px 13px;border-radius:var(--r2);font-size:11.5px;font-weight:600;display:flex;align-items:center;gap:7px;border:1px solid;}
.t-ok{background:var(--ok-bg);color:var(--ok);border-color:rgba(27,107,58,.25);}
.t-er{background:var(--rose-bg);color:var(--rose);border-color:rgba(183,28,28,.25);}
.t-wa{background:var(--amber-bg);color:var(--amber);border-color:rgba(199,119,0,.25);}
.t-in{background:var(--ink-bg);color:var(--ink);border-color:rgba(15,52,96,.2);}

/* WORD COUNT BAR */
.wc-bar{height:2px;background:var(--border);border-radius:2px;overflow:hidden;margin-top:5px;}
.wc-fill{height:2px;background:var(--ink);border-radius:2px;transition:width .4s;}

/* OVERLAY */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(10px);z-index:9999;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s;}
.overlay.open{opacity:1;pointer-events:all;}
.ov-box{background:var(--bg2);border:1px solid var(--border);border-radius:18px;padding:28px;max-width:420px;width:calc(100% - 32px);box-shadow:var(--sh2);transform:scale(.95);transition:transform .22s;}
.overlay.open .ov-box{transform:scale(1);}
.ov-title{font-family:'Fraunces',serif;font-size:18px;font-weight:700;color:var(--tx);margin-bottom:6px;}
.ov-msg{font-size:13px;color:var(--tx2);line-height:1.7;margin-bottom:22px;}

/* HISTORY */
.evo-bar{display:flex;align-items:center;gap:9px;padding:9px 0;border-bottom:1px solid var(--border2);}
.evo-bar:last-child{border-bottom:none;}
.evo-date{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--tx3);width:65px;flex-shrink:0;}
.evo-risk-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
.evo-txt{font-size:11px;color:var(--tx2);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.evo-dur{font-size:10px;color:var(--tx4);}

/* DOTS LOADER */
.dots span{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--ink);margin:0 2px;animation:db 1.2s ease-in-out infinite;}
.dots span:nth-child(2){animation-delay:.2s}
.dots span:nth-child(3){animation-delay:.4s}
@keyframes db{0%,80%,100%{transform:scale(.35);opacity:.25}40%{transform:scale(1);opacity:1}}

/* WAVEFORM */
.wv-bar{width:3px;height:3px;border-radius:2px;background:var(--border);transition:height .1s,background .15s;}

/* GOALS */
.goal-item{display:flex;align-items:flex-start;gap:8px;padding:8px 10px;border-radius:var(--r3);border:1px solid var(--border2);background:var(--bg4);margin-bottom:5px;}
.goal-cb{width:16px;height:16px;border-radius:4px;border:1.5px solid var(--border);background:var(--bg);cursor:pointer;flex-shrink:0;margin-top:1px;display:flex;align-items:center;justify-content:center;transition:all .18s;}
.goal-cb.done{background:var(--ok);border-color:var(--ok);}
.goal-txt{font-size:11.5px;line-height:1.6;color:var(--tx2);}
.goal-txt.done{text-decoration:line-through;opacity:.5;}

/* RAPPORT */
.rpt-wrap{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;}
.rpt-head{background:linear-gradient(135deg,#0F3460 0%,#1A237E 60%,#283593 100%);padding:26px 26px 22px;}
.rpt-head-label{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.22em;color:rgba(255,255,255,.4);margin-bottom:8px;}
.rpt-head-name{font-family:'Fraunces',serif;font-size:26px;color:#fff;letter-spacing:-.02em;line-height:1.15;}
.rpt-meta-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:18px;}
.rpt-mc{background:rgba(255,255,255,.1);border-radius:8px;padding:9px 12px;}
.rpt-mc-lbl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:rgba(255,255,255,.4);margin-bottom:3px;}
.rpt-mc-val{font-size:12px;font-weight:700;color:#fff;}
.rpt-doc-band{background:rgba(0,0,0,.2);padding:11px 26px;display:flex;justify-content:space-between;align-items:center;border-top:1px solid rgba(255,255,255,.08);}
.rpt-doc-name{font-size:13px;font-weight:700;color:#fff;}
.rpt-doc-sub{font-size:10px;color:rgba(255,255,255,.4);margin-top:1px;}
.rpt-risk-badge{display:inline-flex;align-items:center;gap:5px;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;padding:5px 12px;border-radius:99px;border:1.5px solid rgba(255,255,255,.3);color:#fff;}
.rpt-body{padding:22px 26px;}
.rpt-section{margin-bottom:20px;}
.rpt-sec-lbl{font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.18em;color:var(--ink);padding-bottom:8px;margin-bottom:12px;border-bottom:1.5px solid var(--ink-bg);display:flex;align-items:center;gap:8px;}
.rpt-sec-lbl::before{content:'';width:3px;height:12px;background:var(--ink);border-radius:2px;flex-shrink:0;}
.rpt-prose{font-size:13px;line-height:1.9;color:var(--tx2);}
.rpt-summary-box{background:var(--ink-bg);border:1px solid rgba(15,52,96,.15);border-left:4px solid var(--ink);border-radius:0 var(--r2) var(--r2) 0;padding:15px 17px;margin-bottom:18px;}
.rpt-summary-lbl{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.18em;color:var(--ink);margin-bottom:7px;}
.rpt-summary-txt{font-family:'Fraunces',serif;font-size:14px;line-height:1.9;color:var(--ink2);font-style:italic;}
.rpt-evolution-box{background:var(--teal-bg);border:1px solid rgba(0,121,107,.18);border-left:4px solid var(--teal);border-radius:0 var(--r2) var(--r2) 0;padding:14px 16px;margin-bottom:16px;}
.rpt-evolution-lbl{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.18em;color:var(--teal);margin-bottom:6px;}
.rpt-evolution-txt{font-size:13px;line-height:1.75;color:var(--tx2);}
.rpt-vigil-box{background:var(--rose-bg);border:1px solid rgba(183,28,28,.18);border-left:4px solid var(--rose);border-radius:0 var(--r2) var(--r2) 0;padding:14px 16px;}
.rpt-vigil-lbl{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.18em;color:var(--rose);margin-bottom:6px;}
.rpt-vigil-txt{font-size:13px;line-height:1.75;color:var(--tx2);}
.rpt-diag-row{display:flex;align-items:flex-start;gap:11px;padding:9px 0;border-bottom:1px solid var(--border2);}
.rpt-diag-row:last-child{border-bottom:none;}
.rpt-diag-code{font-size:9px;font-weight:800;background:var(--ink);color:#fff;padding:3px 8px;border-radius:4px;flex-shrink:0;margin-top:2px;}
.rpt-diag-txt{font-size:13px;color:var(--tx2);line-height:1.6;}
.rpt-grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.rpt-ib{background:var(--bg3);border:1px solid var(--border);border-radius:var(--r2);padding:12px 14px;}
.rpt-ib-lbl{font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.16em;color:var(--tx3);margin-bottom:5px;}
.rpt-ib-txt{font-size:12.5px;line-height:1.7;color:var(--tx2);}
.rpt-obj-row{display:flex;align-items:flex-start;gap:9px;padding:5px 0;}
.rpt-obj-n{font-size:11px;font-weight:800;color:var(--teal);width:20px;flex-shrink:0;text-align:center;margin-top:2px;}
.rpt-obj-t{font-size:12.5px;line-height:1.65;color:var(--tx2);}
.rpt-sig{border-top:1px solid var(--border);padding:16px 26px;display:flex;justify-content:space-between;align-items:center;background:var(--bg3);}
.rpt-sig-sub{font-size:11px;color:var(--tx3);}
.rpt-sig-name{font-size:13px;font-weight:700;color:var(--tx);margin-top:2px;}

/* EMOTIONS 6 */
.emo-6-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:4px;}
.emo-6-cell{
  border-radius:var(--r2);padding:14px 10px;text-align:center;
  border:1.5px solid transparent;cursor:pointer;
  transition:all .25s;position:relative;overflow:hidden;
}
.emo-6-cell:hover{transform:translateY(-3px);box-shadow:var(--sh2);}
.emo-6-name{
  font-size:10px;font-weight:800;text-transform:uppercase;
  letter-spacing:.08em;margin-bottom:8px;
}
.emo-6-pct{
  font-family:'Fraunces',serif;font-size:20px;font-weight:700;
  line-height:1;margin-bottom:6px;
}
.emo-6-bar-track{height:4px;border-radius:3px;background:rgba(0,0,0,.1);overflow:hidden;}
.emo-6-bar-fill{height:100%;border-radius:3px;transition:width .6s ease;}
.emo-6-chip{
  display:inline-block;margin-top:6px;padding:2px 8px;
  border-radius:99px;font-size:9px;font-weight:700;
  text-transform:uppercase;letter-spacing:.07em;
}

/* PLAN SECTION */
.plan-section{background:var(--teal-bg);border:1px solid rgba(0,121,107,.2);border-radius:var(--r);padding:15px;}
.plan-hd{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:var(--teal);margin-bottom:10px;display:flex;align-items:center;gap:7px;}

/* AUDIO */
.audio-vis{height:3px;background:var(--border);border-radius:2px;overflow:hidden;flex:1;}
.audio-fill{height:3px;background:var(--ink);border-radius:2px;transition:width .1s;}
.divider{height:1px;background:var(--border);margin:2px 0;}

/* LEXIQUE MODAL */
.lex-modal{position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(12px);z-index:9998;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s;}
.lex-modal.open{opacity:1;pointer-events:all;}
.lex-box{background:var(--bg2);border:1px solid var(--border);border-radius:18px;padding:26px;max-width:500px;width:calc(100% - 32px);box-shadow:var(--sh2);transform:scale(.95);transition:transform .22s;max-height:80vh;overflow-y:auto;}
.lex-modal.open .lex-box{transform:scale(1);}
.lex-title{font-family:'Fraunces',serif;font-size:20px;font-weight:700;margin-bottom:4px;}
.lex-sub{font-size:12px;color:var(--tx3);margin-bottom:16px;}
.lex-word{display:inline-block;margin:3px;padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:600;border:1px solid;cursor:default;}
</style>
</head>
<body>
<div class="app">

<!-- TOPBAR -->
<div class="topbar">
  <div class="topbar-left">
    <a href="dashboard.php" class="back-btn">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Tableau de bord
    </a>
    <div class="sep"></div>
    <div class="pat-badge">
      <div class="pat-ava"><?= htmlspecialchars($pat_initials) ?></div>
      <div>
        <div class="pat-name"><?= htmlspecialchars($patient_selected) ?></div>
        <div class="pat-meta">
          Séance n°<?= $session_num ?> · <?= $appt_date ?>
          <?php if($pat_age): ?> · <?= $pat_age ?> ans<?php endif; ?>
          <?php if($session_num>1): ?> · <?= $session_num-1 ?> séance<?= ($session_num-1)>1?'s':'' ?> antérieure<?= ($session_num-1)>1?'s':'' ?><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="topbar-right">
    <div class="timer-pill">
      <span class="t-dot" id="t-dot"></span>
      <span class="t-txt" id="timer">00:00</span>
    </div>
    <div id="rec-status" class="chip c-idle">En attente</div>
    <button class="ctrl-btn" id="theme-btn" title="Thème">
      <span id="theme-icon">L</span>
    </button>
  </div>
</div>

<!-- MAIN -->
<div class="main">

<!-- COLONNE GAUCHE -->
<div class="col" style="border-right:1px solid var(--border);">

  <!-- Profil Patient -->
  <div class="card">
    <div class="card-hd"><span class="hd-dot" style="background:var(--ink)"></span>Profil patient</div>
    <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px;">
      <div class="prof-ava"><?= htmlspecialchars($pat_initials) ?></div>
      <div style="flex:1;min-width:0;">
        <div class="prof-name"><?= htmlspecialchars($patient_selected) ?></div>
        <div class="prof-sub">
          <?php
            $meta_parts = [];
            if($pat_age) $meta_parts[] = $pat_age.' ans';
            if($pat_gender) $meta_parts[] = htmlspecialchars($pat_gender);
            if($pat_situation) $meta_parts[] = htmlspecialchars($pat_situation);
            echo implode(' · ', $meta_parts) ?: '<em style="color:var(--tx4);font-size:11px;">Profil incomplet</em>';
          ?>
        </div>
        <div class="prof-tags">
          <?php if($pat_profession): ?>
          <span class="ptag"><?= htmlspecialchars($pat_profession) ?></span>
          <?php endif; ?>
          <?php if($pat_dob): ?>
          <span class="ptag"><?= date('d/m/Y', strtotime($pat_dob)) ?></span>
          <?php endif; ?>
          <?php if($doc['specialty'] ?? null): ?>
          <span class="ptag" style="background:var(--ink-bg);color:var(--ink);border-color:rgba(15,52,96,.2);"><?= htmlspecialchars($doc['specialty']) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="inter-strip">
      <div class="inter-cell">
        <div class="inter-lbl">Séance</div>
        <div class="inter-val" style="color:var(--ink);"><?= $session_num ?></div>
        <div class="inter-sub">en cours</div>
      </div>
      <div class="inter-cell">
        <div class="inter-lbl">Suivi</div>
        <div class="inter-val"><?= count($prev_consults) ?></div>
        <div class="inter-sub">séances</div>
      </div>
      <div class="inter-cell" style="border-right:none;">
        <div class="inter-lbl">Tendance</div>
        <div class="inter-val" style="font-size:18px;<?= $risque_tendance==='hausse'?'color:var(--rose)':($risque_tendance==='baisse'?'color:var(--ok)':'color:var(--tx3)') ?>">
          <?= $risque_tendance==='hausse'?'haut':($risque_tendance==='baisse'?'bas':'stable') ?>
        </div>
        <div class="inter-sub"><?= $risque_tendance ?></div>
      </div>
    </div>

    <?php if($pat_motif_init): ?>
    <div style="margin-top:10px;padding:10px 12px;border-radius:var(--r2);background:var(--bg3);border:1px solid var(--border);">
      <div style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--tx3);margin-bottom:4px;">Motif initial</div>
      <p style="font-size:12px;color:var(--tx2);line-height:1.65;"><?= htmlspecialchars(mb_substr($pat_motif_init,0,200,'UTF-8')) ?>…</p>
    </div>
    <?php endif; ?>

    <?php if($pat_antecedents): ?>
    <div style="margin-top:8px;padding:10px 12px;border-radius:var(--r2);background:var(--rose-bg);border:1px solid rgba(183,28,28,.15);">
      <div style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--rose);margin-bottom:4px;">Antécédents</div>
      <p style="font-size:11.5px;color:var(--tx2);line-height:1.65;"><?= htmlspecialchars(mb_substr($pat_antecedents,0,150,'UTF-8')) ?></p>
    </div>
    <?php endif; ?>

    <?php if($pat_traitement): ?>
    <div style="margin-top:8px;padding:10px 12px;border-radius:var(--r2);background:var(--violet-bg);border:1px solid rgba(106,27,154,.15);">
      <div style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--violet);margin-bottom:4px;">Traitement en cours</div>
      <p style="font-size:11.5px;color:var(--tx2);line-height:1.65;"><?= htmlspecialchars(mb_substr($pat_traitement,0,150,'UTF-8')) ?></p>
    </div>
    <?php endif; ?>
  </div>

  <!-- NOTES PRATICIEN -->
  <div class="card">
    <div class="card-hd">
      <span class="hd-dot" style="background:var(--amber)"></span>
      Notes du praticien
      <span class="chip c-warn" style="font-size:9px;margin-left:auto;">Confidentiel · Archivé</span>
    </div>
    <textarea id="notes" class="ta-notes-big" rows="7"
      placeholder="Observations cliniques, impressions, hypothèses, contre-transfert, éléments non verbaux…&#10;&#10;Ces notes sont archivées avec la séance, accessibles uniquement par vous."></textarea>
    <div id="notes-save-status" style="margin-top:6px;min-height:18px;font-size:10.5px;color:var(--tx3);"></div>
  </div>

  <!-- Historique séances -->
  <?php if(!empty($prev_consults)): ?>
  <div class="card">
    <div class="card-hd">
      <span class="hd-dot" style="background:var(--ok)"></span>
      Fil de continuité
      <span class="chip c-ok" style="font-size:9px;margin-left:auto;"><?= count($prev_consults) ?> séances</span>
    </div>
    <?php
    $risk_colors = ['faible'=>'#1B6B3A','modéré'=>'#C77700','élevé'=>'#B71C1C','critique'=>'#7B1FA2'];
    foreach($prev_consults as $pc):
      $rc = $risk_colors[$pc['niveau_risque'] ?? 'faible'] ?? '#8C867E';
    ?>
    <div class="evo-bar">
      <span class="evo-date"><?= date('d/m/Y', strtotime($pc['date_consultation'])) ?></span>
      <span class="evo-risk-dot" style="background:<?= $rc ?>;" title="Risque : <?= $pc['niveau_risque'] ?? 'faible' ?>"></span>
      <span class="evo-txt"><?= htmlspecialchars(mb_substr(strip_tags($pc['resume_ia']??''),0,60,'UTF-8')) ?>…</span>
      <span class="evo-dur"><?= $pc['duree_minutes'] ?>min</span>
      <button onclick="loadPrev(<?= json_encode(strip_tags($pc['resume_ia']??'')) ?>,<?= json_encode($pc['plan_therapeutique']??'') ?>)"
        class="btn btn-ghost" style="padding:3px 7px;font-size:10px;flex-shrink:0;">voir</button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Objectifs en cours -->
  <?php if(!empty($goals_open)): ?>
  <div class="card">
    <div class="card-hd">
      <span class="hd-dot" style="background:var(--teal)"></span>
      Objectifs en cours
      <span class="chip c-info" style="font-size:9px;margin-left:auto;"><?= count($goals_open) ?></span>
    </div>
    <?php foreach($goals_open as $g): ?>
    <div class="goal-item">
      <div class="goal-cb" id="gcb_<?= $g['id'] ?>" onclick="toggleGoal(<?= $g['id'] ?>, this)">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" class="check-svg" style="display:none"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <span class="goal-txt" id="gt_<?= $g['id'] ?>"><?= htmlspecialchars($g['goal_text']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div><!-- /col gauche -->

<!-- COLONNE CENTRE -->
<div class="col" style="padding:18px 18px;">

  <!-- TRANSCRIPTION -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <div>
        <h2 style="font-family:'Fraunces',serif;font-size:16px;font-weight:700;color:var(--tx);letter-spacing:-.02em;">Transcription de séance</h2>
        <p style="font-size:11px;color:var(--tx3);margin-top:2px;">Capture vocale ou saisie manuelle</p>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <div id="waveform" style="display:flex;align-items:center;gap:2px;height:22px;">
          <?php for($i=0;$i<14;$i++): ?><div class="wv-bar"></div><?php endfor; ?>
        </div>
        <span id="word-count" class="chip c-idle" style="font-size:10px;">0 mot</span>
      </div>
    </div>
    <textarea id="transcript" class="ta" rows="10"
      placeholder="La transcription apparaît ici automatiquement lors de la capture vocale, ou saisissez directement…"
      oninput="onTyping(this.value)"></textarea>
    <div class="wc-bar"><div class="wc-fill" id="wc-fill" style="width:0%"></div></div>
    <div id="tr-notif" style="margin-top:7px;min-height:26px;"></div>
    <div style="display:flex;align-items:center;gap:10px;margin-top:10px;">
      <button id="mic-btn" onclick="toggleMic()" class="mic-btn idle" title="Démarrer">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
      </button>
      <div style="flex:1;">
        <p id="mic-label" style="font-size:11px;font-weight:600;color:var(--tx3);margin-bottom:4px;">Microphone inactif — Cliquez pour démarrer</p>
        <div class="audio-vis"><div class="audio-fill" id="audio-fill" style="width:0%;"></div></div>
      </div>
      <button onclick="clearTranscript()" class="btn btn-danger" style="padding:6px 11px;font-size:11px;flex-shrink:0;">Effacer</button>
    </div>
    <div id="stt-warning" style="display:none;margin-top:9px;padding:9px 13px;border-radius:var(--r2);background:var(--amber-bg);border:1px solid rgba(199,119,0,.25);">
      <p style="font-size:11px;color:var(--amber);font-weight:600;">Reconnaissance vocale non disponible — Utilisez Chrome ou Edge et autorisez le microphone.</p>
    </div>
  </div>

  <!-- PLAN DE SUIVI — Espace praticien, toujours visible -->
  <div class="plan-section" id="plan-section-wrap">
    <div class="plan-hd">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      Plan de suivi · Séance n°<?= $session_num ?>
      <?php if($last_plan): ?>
      <span class="chip" style="background:var(--teal-bg);color:var(--teal);border-color:rgba(0,121,107,.25);font-size:9px;margin-left:4px;">Séance précédente disponible</span>
      <?php else: ?>
      <span class="chip c-info" style="font-size:9px;margin-left:4px;">1ère séance</span>
      <?php endif; ?>
    </div>
    <p style="font-size:11px;color:var(--teal);margin-bottom:10px;line-height:1.55;">
      Rédigez ici votre plan de suivi pour la prochaine séance — dictez ou écrivez directement. Ce plan sera accessible lors de la prochaine consultation.
    </p>
    <?php if($last_plan): ?>
    <div style="padding:10px 12px;border-radius:var(--r2);background:rgba(0,121,107,.06);border:1px dashed rgba(0,121,107,.3);margin-bottom:10px;">
      <div style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:var(--teal);margin-bottom:5px;">Plan de la séance précédente (lecture seule)</div>
      <p style="font-size:12px;color:var(--tx2);line-height:1.65;font-style:italic;"><?= htmlspecialchars(mb_substr($last_plan,0,300,'UTF-8')) ?><?= strlen($last_plan)>300?'…':'' ?></p>
    </div>
    <?php endif; ?>
    <textarea id="plan-field" class="ta-plan" rows="4"
      placeholder="Décrivez ici le plan pour la prochaine séance : axes thérapeutiques, techniques envisagées, points à explorer, fréquence…&#10;&#10;Vous pouvez aussi dicter en cliquant sur le bouton micro ci-dessous."><?= htmlspecialchars($last_plan ?? '') ?></textarea>
    <div id="plan-notif" style="margin-top:6px;min-height:22px;"></div>
    <div style="display:flex;gap:8px;margin-top:8px;align-items:center;">
      <button onclick="dictPlan()" id="plan-mic-btn" class="mic-btn" style="width:38px;height:38px;flex-shrink:0;" title="Dicter le plan">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
      </button>
      <button onclick="savePlan()" class="btn btn-teal" style="flex:1;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Sauvegarder le plan
      </button>
      <button onclick="aiPlan()" class="btn btn-ghost" style="flex:1;font-size:11px;">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Suggestion IA
      </button>
    </div>
  </div>

  <!-- COMPTE-RENDU -->
  <div class="card" style="flex-shrink:0;padding:0;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;">
      <div>
        <h2 style="font-family:'Fraunces',serif;font-size:16px;font-weight:700;color:var(--tx);letter-spacing:-.02em;">Compte-rendu clinique</h2>
        <p style="font-size:11px;color:var(--tx3);margin-top:2px;">Généré par IA · Confidentiel</p>
      </div>
      <button id="btn-generate" onclick="genReport()" disabled class="btn btn-ink">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Générer le bilan
      </button>
    </div>
  </div>

  <div id="report-body" style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh1);">
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:50px 20px;color:var(--tx4);text-align:center;gap:10px;min-height:160px;">
      <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <p style="font-size:12px;font-weight:500;">En attente de transcription</p>
    </div>
  </div>

  <!-- Actions finales -->
  <div style="position:sticky;bottom:0;z-index:20;background:var(--bg);padding:12px 0 4px;margin-top:2px;">
    <div id="arch-notif" style="margin-bottom:7px;min-height:0;"></div>
    <div style="display:flex;gap:9px;">
      <button onclick="exportPDF()" id="btn-pdf" disabled class="btn btn-ghost" style="flex:0 0 auto;padding:10px 16px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        PDF
      </button>
      <button onclick="finalize()" class="btn-archive">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        Archiver la séance
      </button>
    </div>
  </div>

</div><!-- /col centre -->

<!-- COLONNE DROITE -->
<div class="col" style="border-left:1px solid var(--border);">

  <!-- EMOTIONS 6 -->
  <div class="card">
    <div class="card-hd">
      <span class="hd-dot" style="background:var(--amber)"></span>
      Émotions détectées
      <span id="emo-chip" class="chip c-idle" style="font-size:9px;margin-left:auto;">En attente</span>
    </div>
    <div class="emo-6-grid" id="emo-grid"></div>
    <div id="emo-donut-wrap" style="height:130px;position:relative;margin-top:12px;">
      <canvas id="emoDonut"></canvas>
    </div>
    <p style="font-size:10px;color:var(--tx4);text-align:center;margin-top:5px;">Cliquez sur une émotion pour voir les mots détectés</p>
  </div>

  <!-- TIMELINE ÉMOTIONNELLE -->
  <div class="card">
    <div class="card-hd">
      <span class="hd-dot" style="background:var(--teal)"></span>
      Évolution de séance
      <span id="tl-chip" class="chip c-idle" style="font-size:9px;margin-left:auto;">Stable</span>
    </div>
    <div style="height:75px;position:relative;"><canvas id="tlChart"></canvas></div>
    <p id="tl-label" style="font-size:11px;font-weight:600;color:var(--tx3);margin-top:5px;">—</p>
  </div>

  <!-- POLARITÉ ÉMOTIONNELLE -->
  <div class="card">
    <div class="card-hd">
      <span class="hd-dot" style="background:var(--violet)"></span>
      Polarité émotionnelle
    </div>
    <div style="height:50px;position:relative;"><canvas id="polChart"></canvas></div>
    <div style="display:flex;justify-content:space-between;margin-top:3px;">
      <span style="font-size:10px;color:var(--tx3);">Négatif</span>
      <span style="font-size:10px;color:var(--tx3);">Positif</span>
    </div>
  </div>

  <!-- ANALYSE SÉMANTIQUE -->
  <div class="card" style="flex:1;min-height:0;">
    <div class="card-hd">
      <span class="hd-dot" style="background:var(--violet)"></span>
      Analyse sémantique
    </div>
    <div id="semantic" style="max-height:200px;overflow-y:auto;">
      <div id="sem-ph" style="padding:16px 0;text-align:center;color:var(--tx4);">
        <p style="font-size:11px;line-height:2.2;">Insights générés<br>au fil de la transcription</p>
      </div>
    </div>
  </div>

</div><!-- /col droite -->
</div><!-- /main -->
</div><!-- /app -->

<!-- OVERLAY CONFIRMATION -->
<div id="overlay" class="overlay" onclick="if(event.target===this)closeOv()">
  <div class="ov-box">
    <div class="ov-title" id="ov-title"></div>
    <div class="ov-msg" id="ov-msg"></div>
    <div style="display:flex;gap:10px;">
      <button id="ov-yes" class="btn btn-teal" style="flex:1;">Confirmer</button>
      <button onclick="closeOv()" class="btn btn-ghost" style="flex:1;">Annuler</button>
    </div>
  </div>
</div>

<!-- LEXIQUE MODAL -->
<div id="lex-modal" class="lex-modal" onclick="if(event.target===this)closeLex()">
  <div class="lex-box">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
      <div>
        <div class="lex-title" id="lex-title"></div>
        <div class="lex-sub" id="lex-sub"></div>
      </div>
      <button onclick="closeLex()" class="btn btn-ghost" style="padding:5px 9px;font-size:11px;">Fermer</button>
    </div>
    <div id="lex-words"></div>
    <div id="lex-extract" style="margin-top:14px;"></div>
  </div>
</div>

<script>
/* ═══════════════════════════════════════════════════════════
   DONNÉES PHP → JS
═══════════════════════════════════════════════════════════ */
var CSRF    = <?= json_encode($csrf) ?>;
var PAT     = <?= json_encode($patient_selected) ?>;
var SESN    = <?= $session_num ?>;
var DATED   = <?= json_encode($appt_date) ?>;
var DR      = <?= json_encode($doc_fullname ?: $nom_docteur) ?>;
var DR_SPEC = <?= json_encode($doc_specialty) ?>;
var DR_ADR  = <?= json_encode($doc_adresse) ?>;
var DR_TEL  = <?= json_encode($doc_tel) ?>;
var DR_RPPS = <?= json_encode($doc_rpps) ?>;
var HIST    = <?= json_encode($history_for_ai) ?>;
var PAT_AGE = <?= json_encode($pat_age) ?>;
var PAT_AGE_DISPLAY = <?= json_encode($pat_age_display) ?>;
var PAT_VILLE = <?= json_encode($pat_ville_display) ?>;
var PAT_GENDER = <?= json_encode($pat_gender) ?>;
var PAT_PROFESSION = <?= json_encode($pat_profession) ?>;
var PAT_SITUATION = <?= json_encode($pat_situation) ?>;
var PAT_MOTIF_INIT = <?= json_encode($pat_motif_init) ?>;
var PAT_ANTECEDENTS = <?= json_encode($pat_antecedents) ?>;
var PAT_TRAITEMENT = <?= json_encode($pat_traitement) ?>;
var LAST_EMO = <?= json_encode($last_emo_arr) ?>;
var RISQUE_TENDANCE = <?= json_encode($risque_tendance) ?>;
var GOALS_OPEN = <?= json_encode(array_map(fn($g)=>['id'=>$g['id'],'text'=>$g['goal_text']], $goals_open)) ?>;
var IS_FOLLOWUP = <?= json_encode($is_followup) ?>;

/* ÉTAT GLOBAL */
var micOn = false, recog = null, timerIv = null, secs = 0;
var lastReport = null, lastAutoText = '';
var tlC, polC, donutC;
var emoPoints = [0], tlRisk = [], tlResil = [], tlAnx = [];

/* ═══════════════════════════════════════════════════════════
   6 ÉMOTIONS PLUTCHIK
═══════════════════════════════════════════════════════════ */
var PLUT6 = {
  tristesse: {
    label:'Tristesse',
    color:'#1565C0', colorLight:'#E3F2FD', border:'#0D47A1', textOnBg:'#1565C0',
    lex:['triste','tristesse','déprimé','déprimée','dépression','pleur','larme','désespoir','vide','souffrance',
         'effondré','brisé','perdu','abandonné','seul','solitude','deuil','rupture','chagrin','mélancolie',
         'abattu','cafard','épuisé','vide','mort','noir','sombre']
  },
  joie: {
    label:'Joie',
    color:'#E65100', colorLight:'#FFF3E0', border:'#BF360C', textOnBg:'#E65100',
    lex:['heureux','heureuse','joie','bonheur','content','bien','sourire','rire','fantastique','génial',
         'super','top','merveilleux','enchanté','ravi','épanoui','fier','gratitude','légèreté',
         'rigole','plaisir','amusant','drôle','bien-être']
  },
  surprise: {
    label:'Surprise',
    color:'#00796B', colorLight:'#E0F2F1', border:'#004D40', textOnBg:'#00796B',
    lex:['surpris','surprise','choc','inattendu','bizarre','étrange','incroyable','soudain','brutal',
         'stupéfait','stupéfaction','imprévu','déconcerté','abasourdi','sidéré','ahuri','insolite',
         'wow','tellement','pas prévu']
  },
  degout: {
    label:'Dégoût',
    color:'#558B2F', colorLight:'#F1F8E9', border:'#33691E', textOnBg:'#558B2F',
    lex:['dégoût','dégoûté','nauséeux','écœurant','répugnant','insupportable','horrible','abominable',
         'honte','honteux','rejet','mépris','nausée','répulsion','dépréciation','abject','immonde',
         'horreur','écœuré']
  },
  colere: {
    label:'Colère',
    color:'#B71C1C', colorLight:'#FFEBEE', border:'#7F0000', textOnBg:'#B71C1C',
    lex:['colère','furieux','furie','rage','énervé','énervée','irrité','frustré','frustration','agressif',
         'agressivité','haine','crier','violence','injustice','révolté','exaspéré','hostile','conflit',
         'dispute','rancœur','marre','ras-le-bol','excédé','fâché']
  },
  peur: {
    label:'Peur',
    color:'#6A1B9A', colorLight:'#F3E5F5', border:'#4A148C', textOnBg:'#6A1B9A',
    lex:['peur','anxieux','anxieuse','anxiété','angoisse','stress','panique','terreur','crainte','phob',
         'cauchemar','insomnie','tremble','terrif','effroi','appréhension','inquiet','inquiète',
         'hypervigilance','menace','danger','effrayé','affolé','paralysé','redoute']
  }
};

var EMO6_KEYS = Object.keys(PLUT6);
var emoValues6 = {};
EMO6_KEYS.forEach(function(k){ emoValues6[k] = 0; });
var detectedWords6 = {};

/* ═══════════════════════════════════════════════════════════
   THÈME
═══════════════════════════════════════════════════════════ */
(function(){
  var t = localStorage.getItem('psyspace-theme') || 'light';
  document.documentElement.setAttribute('data-theme', t);
  document.getElementById('theme-icon').textContent = t==='dark'?'C':'L';
})();
document.getElementById('theme-btn').addEventListener('click', function(){
  var c = document.documentElement.getAttribute('data-theme');
  var n = c==='dark'?'light':'dark';
  document.documentElement.setAttribute('data-theme', n);
  localStorage.setItem('psyspace-theme', n);
  document.getElementById('theme-icon').textContent = n==='dark'?'C':'L';
  setTimeout(function(){ drawAll(); }, 50);
});

/* ═══════════════════════════════════════════════════════════
   OVERLAY / NOTIF
═══════════════════════════════════════════════════════════ */
function openOv(title, msg, cb) {
  document.getElementById('ov-title').innerHTML = title;
  document.getElementById('ov-msg').innerHTML = msg;
  document.getElementById('overlay').classList.add('open');
  document.getElementById('ov-yes').onclick = function(){ closeOv(); cb(); };
}
function closeOv(){ document.getElementById('overlay').classList.remove('open'); }
function ntf(id, msg, type, ms) {
  if(ms===undefined) ms=4000;
  var z=document.getElementById(id); if(!z) return;
  var ic={ok:'ok',er:'err',wa:'avert',in:'info'};
  z.innerHTML='<div class="toast t-'+type+'"><span>'+msg+'</span></div>';
  if(ms>0) setTimeout(function(){ if(z) z.innerHTML=''; }, ms);
}

/* ═══════════════════════════════════════════════════════════
   ANALYSE TEXTE
═══════════════════════════════════════════════════════════ */
var LEX_CLI = {
  detresse: ["triste","tristesse","déprimé","déprimée","dépression","désespoir","vide","souffrance","honte","coupable","inutile","seul","abandonné","deuil","rupture","pleurer","larmes","effondré","brisé","épuisé"],
  anxiete:  ["anxieux","anxieuse","anxiété","angoisse","stress","peur","panique","inquiet","inquiète","ruminer","insomnie","cauchemars","obsession","phobie","hypervigilance","tremble"],
  resilience:["espoir","optimiste","mieux","heureux","heureuse","joie","bonheur","calme","apaisé","soulagé","énergie","motivé","plaisir","confiant","projet","guérir","lâcher prise"],
  social:   ["ami","amis","entourage","soutien","famille","couple","relation","confiance","connecté","accompagné","groupe","parler","thérapeute"],
  lien:     ["attachement","lien","relation","proche","intimité","confiance","sécurité"]
};
var LEX_URGENCE = ["suicide","mourir","en finir","me tuer","plus vivre","automutilation","me blesser","me couper","overdose","sauter","pendre","ne veux plus vivre"];

function analyzeText(text) {
  var t=text.toLowerCase(); var scores={detresse:0,anxiete:0,resilience:0,social:0,lien:0};
  Object.keys(LEX_CLI).forEach(function(cat){
    LEX_CLI[cat].forEach(function(w){
      var re=new RegExp('\\b'+w.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'gi');
      var m=t.match(re); if(m) scores[cat]+=m.length;
    });
  });
  return scores;
}

function analyzePlutchik6(text) {
  // Algorithme intelligent avec détection de négation et pondération contextuelle
  var t = text.toLowerCase();

  // Fenêtres de négation : si un mot négatif précède dans les 5 mots, on inverse
  var NEG_PATTERNS = [
    /\b(pas|plus|jamais|ni|sans|aucun|aucune|nullement|non|ne)\s+(\w+\s+){0,4}/g
  ];

  // Construire une version annotée pour la négation
  // On tokenise par phrases pour éviter les faux-positifs inter-phrases
  var sentences = t.split(/[.!?;]+/).filter(function(s){ return s.trim().length > 2; });

  var scores = {}, found = {};
  EMO6_KEYS.forEach(function(emo){ scores[emo] = 0; found[emo] = []; });

  sentences.forEach(function(sentence) {
    EMO6_KEYS.forEach(function(emo) {
      PLUT6[emo].lex.forEach(function(w) {
        var re = new RegExp('\\b' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
        var m = sentence.match(re);
        if (!m) return;

        // Chercher si le mot est précédé d'une négation dans la même phrase
        var wordRe = new RegExp('\\b(ne\\s+|n\'\\s*)?(pas|plus|jamais|sans|nullement|aucun[e]?)\\s+(?:\\w+\\s+){0,4}' + w.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'), 'i');
        var isNegated = wordRe.test(sentence);

        if (!isNegated) {
          scores[emo] += m.length;
          found[emo].push({w: w, n: m.length});
        } else {
          // Mot nié : on note comme détecté mais avec un signal négatif
          // On l'enregistre pour l'affichage mais sans incrémenter le score
          found[emo].push({w: w + ' (nié)', n: 0, negated: true});
        }
      });
    });
  });

  detectedWords6 = found;
  return scores;
}

function normalizeEmo6Scores(rawScores) {
  // Normalisation intelligente : relatif au max, avec seuil minimal de signification
  var vals = EMO6_KEYS.map(function(k){ return rawScores[k]; });
  var maxRaw = Math.max.apply(null, vals);
  var totalRaw = vals.reduce(function(a,b){ return a+b; }, 0);

  var result = {};
  if (maxRaw === 0) {
    EMO6_KEYS.forEach(function(k){ result[k] = 0; });
    return result;
  }

  // Score relatif : l'émotion dominante prend au max 85%, les autres proportionnellement
  // Cela rend impossible d'avoir deux 100%
  EMO6_KEYS.forEach(function(k) {
    var raw = rawScores[k];
    if (raw === 0) { result[k] = 0; return; }
    // Normalisation relative : (raw / maxRaw) * 85, arrondi
    var pct = Math.round((raw / maxRaw) * 85);
    // Seuil minimal de détection : si raw >= 1 mais pct < 8, on affiche au moins 8
    result[k] = Math.max(8, pct);
    // L'émotion dominante garde son score exact (plafonné à 85)
    if (raw === maxRaw) result[k] = 85;
  });

  return result;
}

function checkUrgence(text) {
  var t=text.toLowerCase();
  return LEX_URGENCE.some(function(p){ return t.includes(p); });
}

/* ═══════════════════════════════════════════════════════════
   ON TYPING
═══════════════════════════════════════════════════════════ */
function onTyping(val) {
  var words=val.trim().split(/\s+/).filter(function(w){ return w.length>0; });
  var wc=words.length;
  document.getElementById('word-count').textContent=wc+(wc>1?' mots':' mot');
  document.getElementById('wc-fill').style.width=Math.min(100,wc/5)+'%';

  if(checkUrgence(val)){
    addInsight('er','ALERTE URGENCE','Propos pouvant indiquer un risque vital détectés. Évaluation immédiate requise.');
    document.getElementById('rec-status').textContent='ALERTE';
    document.getElementById('rec-status').className='chip c-crit';
  }

  var sc=analyzeText(val);
  var plut=analyzePlutchik6(val);
  var normalized=normalizeEmo6Scores(plut);

  EMO6_KEYS.forEach(function(k){
    emoValues6[k] = normalized[k];
  });

  updateEmo6Grid();
  drawDonut6();
  updateTimeline(sc);

  var neg=sc.detresse+sc.anxiete, pos=sc.resilience+sc.social+sc.lien;
  var total=neg+pos;
  emoPoints.push(total>0?Math.max(-1,Math.min(1,(pos-neg)/total)):0);
  if(emoPoints.length>60) emoPoints.shift();
  drawPol();

  if(wc>8) document.getElementById('btn-generate').disabled=false;
}

/* ═══════════════════════════════════════════════════════════
   GRILLE 6 ÉMOTIONS
═══════════════════════════════════════════════════════════ */
function updateEmo6Grid() {
  var g=document.getElementById('emo-grid');
  g.innerHTML='';
  var sorted=[...EMO6_KEYS].sort(function(a,b){ return emoValues6[b]-emoValues6[a]; });
  sorted.forEach(function(emo){
    var p=PLUT6[emo];
    var v=emoValues6[emo];
    var isDominant=v>=50;
    var isActive=v>0;
    var cell=document.createElement('div');
    cell.className='emo-6-cell';
    cell.style.background=isActive?p.colorLight:'var(--bg3)';
    cell.style.borderColor=isDominant?p.border:(isActive?p.border+'88':'var(--border)');
    cell.style.borderWidth=isDominant?'2.5px':'1.5px';
    cell.style.opacity=isActive?'1':'0.45';
    cell.style.transform=isDominant?'scale(1.02)':'scale(1)';
    var badgeLabel='', badgeBg='', badgeColor='';
    if(v>=75){ badgeLabel='Dominant'; badgeBg=p.color; badgeColor='#fff'; }
    else if(v>=40){ badgeLabel='Présent'; badgeBg=p.colorLight; badgeColor=p.color; }
    else if(v>0){ badgeLabel='Trace'; badgeBg='var(--bg3)'; badgeColor='var(--tx3)'; }
    cell.innerHTML=
      '<div class="emo-6-name" style="color:'+(isActive?p.textOnBg:'var(--tx3)')+';">'+p.label+'</div>'+
      '<div class="emo-6-pct" style="color:'+(isActive?p.color:'var(--tx4)')+';">'+(isActive?v+'%':'—')+'</div>'+
      '<div class="emo-6-bar-track"><div class="emo-6-bar-fill" style="width:'+v+'%;background:'+p.color+';"></div></div>'+
      (badgeLabel?'<div class="emo-6-chip" style="background:'+badgeBg+';color:'+badgeColor+';">'+badgeLabel+'</div>':'');
    cell.onclick=function(){ if(isActive) openLex6(emo); };
    cell.title=isActive?(p.label+' : '+v+' %'):p.label+' : non détecté';
    g.appendChild(cell);
  });
  document.getElementById('emo-chip').textContent='Actif';
  document.getElementById('emo-chip').className='chip c-ok';
}

/* ═══════════════════════════════════════════════════════════
   LEXIQUE MODAL
═══════════════════════════════════════════════════════════ */
function openLex6(emo) {
  var p=PLUT6[emo];
  var found=detectedWords6[emo]||[];
  document.getElementById('lex-title').innerHTML=p.label;
  document.getElementById('lex-title').style.color=p.color;
  var totalOcc=found.reduce(function(a,b){return a+b.n;},0);
  document.getElementById('lex-sub').textContent=totalOcc>0
    ?totalOcc+' occurrence(s) détectée(s) dans le verbatim'
    :'Aucune occurrence détectée';
  var wordsHtml='';
  p.lex.forEach(function(w){
    var hit=found.find(function(f){ return f.w===w; });
    wordsHtml+='<span class="lex-word" style="background:'+(hit?p.colorLight:'var(--bg3)')+';\
      border-color:'+(hit?p.border:'var(--border)')+';\
      color:'+(hit?p.color:'var(--tx3)')+';\
      font-weight:'+(hit?'800':'500')+';">'
      +(hit?'+ ':'')+w+(hit?' x'+hit.n:'')+'</span>';
  });
  document.getElementById('lex-words').innerHTML=wordsHtml;
  var tr=document.getElementById('transcript').value;
  var extraits='';
  if(found.length>0&&tr){
    var sentences=tr.split(/[.!?]+/).filter(function(s){ return s.trim().length>10; });
    var matchSentences=sentences.filter(function(s){
      return found.some(function(f){ return s.toLowerCase().includes(f.w); });
    }).slice(0,3);
    if(matchSentences.length>0){
      extraits='<div style="margin-top:12px;"><div style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:var(--tx3);margin-bottom:8px;">Extraits du verbatim</div>';
      matchSentences.forEach(function(s){
        extraits+='<div style="padding:9px 12px;border-radius:var(--r2);background:var(--bg3);border-left:3px solid '+p.color+';margin-bottom:6px;font-size:12px;line-height:1.65;color:var(--tx2);font-style:italic;">« '+escH(s.trim())+' »</div>';
      });
      extraits+='</div>';
    }
  }
  document.getElementById('lex-extract').innerHTML=extraits;
  document.getElementById('lex-modal').classList.add('open');
}
function closeLex(){ document.getElementById('lex-modal').classList.remove('open'); }

/* ═══════════════════════════════════════════════════════════
   CHARTS
═══════════════════════════════════════════════════════════ */
function isDark(){ return document.documentElement.getAttribute('data-theme')==='dark'; }
function gc(a){ return isDark()?'rgba(237,233,227,'+a+')':'rgba(28,25,22,'+a+')'; }

function drawDonut6() {
  var ctx=document.getElementById('emoDonut').getContext('2d');
  if(donutC) donutC.destroy();
  var vals=EMO6_KEYS.map(function(k){ return emoValues6[k]; });
  var total=vals.reduce(function(a,b){return a+b;},0);
  if(total===0){ donutC=null; return; }
  donutC=new Chart(ctx,{
    type:'doughnut',
    data:{
      labels:EMO6_KEYS.map(function(k){ return PLUT6[k].label; }),
      datasets:[{
        data:vals,
        backgroundColor:EMO6_KEYS.map(function(k){ return PLUT6[k].color; }),
        borderColor:EMO6_KEYS.map(function(k){ return PLUT6[k].colorLight; }),
        borderWidth:3,hoverOffset:8
      }]
    },
    options:{
      responsive:true,maintainAspectRatio:false,cutout:'62%',
      plugins:{
        legend:{display:false},
        tooltip:{
          backgroundColor:'rgba(28,25,22,.95)',padding:10,cornerRadius:8,
          titleFont:{family:'DM Sans',size:10,weight:'700'},
          bodyFont:{family:'DM Sans',size:11},
          callbacks:{label:function(ctx){ return ' '+ctx.label+' : '+ctx.raw+'%'; }}
        }
      }
    }
  });
}

function drawTL() {
  var ctx=document.getElementById('tlChart').getContext('2d');
  if(tlC) tlC.destroy();
  function grd(c,c1,c2){ var g=c.chart.ctx.createLinearGradient(0,0,0,75); g.addColorStop(0,c1); g.addColorStop(1,c2); return g; }
  tlC=new Chart(ctx,{
    type:'line',
    data:{
      labels:tlRisk.map(function(_,i){return i;}),
      datasets:[
        {label:'Tension',data:tlRisk,borderColor:'#C77700',borderWidth:2,pointRadius:0,fill:true,tension:.42,
          backgroundColor:function(c){return grd(c,'rgba(199,119,0,.18)','rgba(199,119,0,0)');}},
        {label:'Résilience',data:tlResil,borderColor:'#1B6B3A',borderWidth:1.5,pointRadius:0,fill:false,tension:.42},
        {label:'Anxiété',data:tlAnx,borderColor:'#6A1B9A',borderWidth:1.5,pointRadius:0,fill:false,tension:.42,borderDash:[3,3]}
      ]
    },
    options:{responsive:true,maintainAspectRatio:false,animation:false,
      plugins:{legend:{display:false}},
      scales:{x:{display:false},y:{min:0,max:100,ticks:{display:false},grid:{color:gc(.05)},border:{display:false}}}}
  });
}

function drawPol() {
  var ctx=document.getElementById('polChart').getContext('2d');
  if(polC) polC.destroy();
  var last=emoPoints[emoPoints.length-1]||0;
  var lc=last>0.1?'#1B6B3A':(last<-0.2?'#C77700':'#0F3460');
  polC=new Chart(ctx,{
    type:'line',
    data:{labels:emoPoints.map(function(_,i){return i;}),
      datasets:[{data:emoPoints,borderColor:lc,borderWidth:1.5,pointRadius:0,fill:true,tension:.4,
        backgroundColor:function(c){
          var g=c.chart.ctx.createLinearGradient(0,0,0,50);
          var r=parseInt(lc.slice(1,3),16),gr=parseInt(lc.slice(3,5),16),b=parseInt(lc.slice(5,7),16);
          g.addColorStop(0,'rgba('+r+','+gr+','+b+',.15)'); g.addColorStop(1,'transparent'); return g;
        }
      }]
    },
    options:{responsive:true,maintainAspectRatio:false,animation:false,
      plugins:{legend:{display:false}},
      scales:{x:{display:false},y:{min:-1.2,max:1.2,ticks:{display:false},grid:{color:gc(.04)},border:{display:false}}}}
  });
}

function updateTimeline(sc) {
  tlRisk.push(Math.min(100,(sc.detresse+sc.anxiete)*11));
  tlResil.push(Math.min(100,(sc.resilience+sc.social)*11));
  tlAnx.push(Math.min(100,sc.anxiete*11));
  if(tlRisk.length>80){ tlRisk.shift(); tlResil.shift(); tlAnx.shift(); }
  drawTL();
  if(tlRisk.length>=4){
    var n=tlRisk.length;
    var diff=((tlRisk[n-1]+tlRisk[n-2])/2)-((tlRisk[n-3]+tlRisk[n-4])/2);
    var chip=document.getElementById('tl-chip'), lbl=document.getElementById('tl-label');
    if(diff>8){chip.textContent='Tension en hausse';chip.className='chip c-live';lbl.textContent='Montée du niveau de détresse';}
    else if(diff<-8){chip.textContent='Apaisement';chip.className='chip c-ok';lbl.textContent='Stabilisation progressive';}
    else{chip.textContent='Stable';chip.className='chip c-idle';lbl.textContent='Régularité du discours';}
  }
}

function drawAll() { drawTL(); drawPol(); drawDonut6(); }

/* ═══════════════════════════════════════════════════════════
   INSIGHTS SÉMANTIQUES
═══════════════════════════════════════════════════════════ */
function addInsight(type, title, body) {
  var sem=document.getElementById('sem-ph'); if(sem) sem.remove();
  var col={info:'var(--ink)',warn:'var(--amber)',ok:'var(--ok)',er:'var(--rose)'};
  var wrap=document.getElementById('semantic'); if(!wrap) return;
  var el=document.createElement('div');
  el.style.cssText='padding:10px 12px;border-radius:var(--r2);margin-bottom:6px;border-left:3px solid;animation:slideIn .25s ease forwards;';
  var colorMap={info:'var(--ink-bg)',warn:'var(--amber-bg)',ok:'var(--ok-bg)',er:'var(--rose-bg)'};
  var bColorMap={info:'var(--ink)',warn:'var(--amber)',ok:'var(--ok)',er:'var(--rose)'};
  el.style.background=colorMap[type]||'var(--ink-bg)';
  el.style.borderLeftColor=bColorMap[type]||'var(--ink)';
  var tstr=new Date().toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});
  el.innerHTML='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">'
    +'<span style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.12em;color:'+(col[type]||'var(--ink)')+';">'+title+'</span>'
    +'<span style="font-size:9px;color:var(--tx3);">'+tstr+'</span></div>'
    +'<p style="font-size:11.5px;line-height:1.55;color:var(--tx2);">'+escH(body)+'</p>';
  wrap.insertBefore(el,wrap.firstChild);
  while(wrap.children.length>12) wrap.removeChild(wrap.lastChild);
}

/* ═══════════════════════════════════════════════════════════
   CALL IA
═══════════════════════════════════════════════════════════ */
async function callAI(prompt, max) {
  max=max||1500;
  var res=await fetch('proxy_ia.php',{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({prompt:prompt,max_tokens:max,csrf_token:CSRF})
  });
  if(!res.ok) throw new Error('HTTP '+res.status);
  var d=await res.json();
  if(d.error) throw new Error(d.error.message||'Erreur API');
  return d.choices&&d.choices[0]&&d.choices[0].message?d.choices[0].message.content:'';
}

/* ═══════════════════════════════════════════════════════════
   CONTEXTE PATIENT
═══════════════════════════════════════════════════════════ */
function buildPatientContext() {
  var lines=['Nom : '+PAT];
  if(PAT_AGE) lines.push('Âge : '+PAT_AGE+' ans');
  if(PAT_GENDER) lines.push('Genre : '+PAT_GENDER);
  if(PAT_PROFESSION) lines.push('Profession : '+PAT_PROFESSION);
  if(PAT_SITUATION) lines.push('Situation familiale : '+PAT_SITUATION);
  if(PAT_MOTIF_INIT) lines.push('Motif initial : '+(PAT_MOTIF_INIT||'').slice(0,200));
  if(PAT_ANTECEDENTS) lines.push('Antécédents : '+(PAT_ANTECEDENTS||'').slice(0,200));
  if(PAT_TRAITEMENT) lines.push('Traitement en cours : '+(PAT_TRAITEMENT||'').slice(0,150));
  return lines.join('\n');
}

/* ═══════════════════════════════════════════════════════════
   PLAN THÉRAPEUTIQUE
═══════════════════════════════════════════════════════════ */
var planMicOn=false;
function dictPlan(){
  var SR2=window.SpeechRecognition||window.webkitSpeechRecognition;
  if(!SR2){ ntf('plan-notif','Microphone non supporté sur ce navigateur.','er'); return; }
  if(planMicOn) return;
  planMicOn=true;
  var pmb=document.getElementById('plan-mic-btn');
  if(pmb){ pmb.className='mic-btn live'; }
  ntf('plan-notif','Dictée en cours — parlez…','in',0);
  var pr=new SR2(); pr.lang='fr-FR'; pr.continuous=false; pr.interimResults=false;
  pr.onresult=function(e){
    var t=e.results[0][0].transcript;
    var f=document.getElementById('plan-field');
    if(f){ f.value=(f.value?f.value+'\n':'')+t; ntf('plan-notif','Dictée capturée — vérifiez et sauvegardez.','ok'); }
  };
  pr.onerror=function(e){
    ntf('plan-notif','Erreur dictée : '+e.error,'er');
    if(pmb){ pmb.className='mic-btn'; }
    planMicOn=false;
  };
  pr.onend=function(){
    planMicOn=false;
    if(pmb){ pmb.className='mic-btn done'; }
    setTimeout(function(){ if(pmb) pmb.className='mic-btn'; },2500);
  };
  try{ pr.start(); }catch(e){ ntf('plan-notif','Erreur démarrage : '+e.message,'er'); planMicOn=false; if(pmb) pmb.className='mic-btn'; }
}

async function savePlan(){
  var pf=document.getElementById('plan-field'); if(!pf) return;
  var val=pf.value.trim(); if(!val){ ntf('plan-notif','Plan vide.','wa'); return; }
  ntf('plan-notif','Sauvegarde…','in',0);
  var fd=new FormData(); fd.append('csrf_token',CSRF); fd.append('action','save_plan'); fd.append('plan',val);
  try {
    var res=await fetch('save_consultation.php',{method:'POST',body:fd});
    var d=await res.text();
    ntf('plan-notif',d.trim()==='success'?'Plan sauvegardé.':'Erreur : '+d.trim(),d.trim()==='success'?'ok':'er');
  } catch(e){ ntf('plan-notif','Erreur réseau.','er'); }
}

async function aiPlan(){
  var pf=document.getElementById('plan-field'); if(!pf) return;
  var text=document.getElementById('transcript').value.trim();
  if(text.length<20){ ntf('plan-notif','Transcription insuffisante.','wa'); return; }
  ntf('plan-notif','Génération via IA…','in',0);
  var patCtx=buildPatientContext();
  var histCtx=HIST.length?'Historique :\n'+HIST.map(function(h){ return '- '+h.date+' : '+h.resume+(h.plan?' | Plan: '+h.plan:''); }).join('\n'):'Première consultation.';
  var prompt='## Rôle\nTu es psychologue clinicien. Propose un plan thérapeutique structuré en 3-5 lignes, en français professionnel, sans numérotation, en prose.\n\n## Patient\n'+patCtx+'\n\n## '+histCtx+'\n\n## Verbatim\n"'+text.slice(-800)+'"\n\nRéponds UNIQUEMENT avec le texte du plan, sans titre ni introduction.';
  try {
    var raw=await callAI(prompt,600);
    pf.value=raw.trim();
    ntf('plan-notif','Plan généré. Vérifiez et sauvegardez.','ok');
  } catch(e){ ntf('plan-notif','Erreur IA.','er'); }
}

/* ═══════════════════════════════════════════════════════════
   GÉNÉRATION COMPTE-RENDU
═══════════════════════════════════════════════════════════ */
async function genReport() {
  var text=document.getElementById('transcript').value.trim();
  if(text.length<20){ ntf('tr-notif','Volume insuffisant.','wa'); return; }
  document.getElementById('btn-generate').disabled=true;
  var body=document.getElementById('report-body');
  body.innerHTML='<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:50px 20px;color:var(--tx3);gap:14px;"><div class="dots"><span></span><span></span><span></span></div><p style="font-size:12px;font-weight:500;">Rédaction du compte-rendu en cours…</p></div>';

  var notes=document.getElementById('notes').value;
  var planEl=document.getElementById('plan-field');
  var planVal=planEl?planEl.value:'';
  var dureeMin=Math.floor(secs/60)||null;
  var topEmoPct=EMO6_KEYS.filter(function(k){ return emoValues6[k]>10; }).map(function(k){ return PLUT6[k].label+':'+emoValues6[k]+'%'; }).join(', ')||'non déterminées';

  var patCtx=buildPatientContext();
  var histCtx=HIST.length
    ?'HISTORIQUE DES CONSULTATIONS :\n'+HIST.map(function(h,i){
        return (i+1)+'. Séance du '+h.date+' ('+h.duree+'min) — Risque:'+h.risque+'\n   Résumé: '+h.resume+'\n'
          +(h.evolution?'   Évolution: '+h.evolution+'\n':'')
          +(h.plan?'   Plan précédent: '+h.plan+'\n':'')
          +(h.objectifs?'   Objectifs fixés: '+h.objectifs+'\n':'');
      }).join('\n')
    :'HISTORIQUE : Première consultation.';

  var prompt='# RÔLE\nTu es psychologue clinicien senior avec 20 ans d\'expérience. Tu rédiges un compte-rendu clinique confidentiel en français professionnel.\n\n'
    +'# CONTEXTE\n- Praticien : Dr. '+DR+', '+DR_SPEC+(DR_RPPS?' (RPPS : '+DR_RPPS+')':'')+'\n'
    +'- Date : '+DATED+' · Séance n°'+SESN+(dureeMin?' · Durée approx. : '+dureeMin+'min':'')+'\n\n'
    +'# PROFIL PATIENT\n'+patCtx+'\n\n'
    +'# '+histCtx+'\n\n'
    +(notes?'# NOTES CLINIQUES DU PRATICIEN (observations directes, non verbal, contre-transfert)\n'+notes+'\n\n':'')
    +(planVal?'# PLAN DE SUIVI EN COURS (défini par le praticien)\n'+planVal+'\n\n':'')
    +'# ÉMOTIONS DÉTECTÉES AUTOMATIQUEMENT\n'+topEmoPct+'\nTendance risque globale : '+RISQUE_TENDANCE+'\n\n'
    +'# VERBATIM DE LA SÉANCE\n'
    +'IMPORTANT : Le verbatim ci-dessous contient les propos mélangés du psychologue et du patient. '
    +'Identifie les tours de parole par contexte (questions = psy, réponses émotionnelles = patient). '
    +'Focalise ton analyse sur le discours du PATIENT uniquement pour le compte-rendu clinique.\n'
    +'"""\n'+text+'\n"""\n\n'
    +'# INSTRUCTIONS\n'
    +'Réponds en JSON strict UNIQUEMENT, sans texte avant ni après, sans balises markdown :\n'
    +'{\n'
    +'"resume_psychologue": "Synthèse narrative intégrant : motif principal exprimé par le patient, déroulement de la séance (thèmes abordés, dynamique), et synthèse du discours patient. Rédige en prose continue, 4-6 phrases, style clinique sobre.",\n'
    +'"evolution_depuis_derniere_seance": "Uniquement si séance de suivi : comparer avec la séance précédente, évolution observée. Null si 1ère séance.",\n'
    +'"observations": "Observations cliniques du praticien : attitude, affect, cohérence du discours, éléments non verbaux notables, fonctionnement psychique observé.",\n'
    +'"points_vigilance": "Points à surveiller : signaux de risque, éléments préoccupants, thèmes récurrents à explorer. Vide si rien de notable.",\n'
    +'"plan_therapeutique": "Suggestion de plan de suivi pour la prochaine séance basée sur cette séance. Le praticien décidera du plan final.",\n'
    +'"niveau_risque": "faible | modéré | élevé | critique"\n'
    +'}';

  try {
    var raw=await callAI(prompt,4000);
    var ai; try{ ai=JSON.parse(raw.replace(/```json\n?|\n?```/g,'').trim()); }catch(e){ throw new Error('JSON invalide: '+e.message); }
    lastReport={ai:ai,text:text,duree:dureeMin,date:new Date().toLocaleDateString('fr-FR'),emo:Object.assign({},emoValues6)};
    lastReport.resume_str=[
      'CR · '+PAT+' · S'+SESN+' · '+DATED,
      ai.resume_psychologue||'',
      ai.evolution_depuis_derniere_seance?'Évolution : '+ai.evolution_depuis_derniere_seance:'',
      'Plan : '+(ai.plan_therapeutique||''),
      'Risque : '+(ai.niveau_risque||'faible')
    ].filter(Boolean).join('\n\n');
    document.getElementById('btn-pdf').disabled=false;
    renderReport(lastReport);
    addInsight('ok','Compte-rendu généré','Risque : '+(ai.niveau_risque||'faible'));
  } catch(err) {
    body.innerHTML='<div style="padding:40px 20px;text-align:center;"><p style="color:var(--rose);font-weight:700;font-size:13px;margin-bottom:8px;">Erreur</p><p style="color:var(--tx3);font-size:11px;line-height:1.65;">'+err.message+'</p></div>';
  } finally {
    document.getElementById('btn-generate').disabled=false;
  }
}

/* ═══════════════════════════════════════════════════════════
   RENDU RAPPORT
═══════════════════════════════════════════════════════════ */
function renderReport(lr) {
  var ai=lr.ai;
  var niv=(ai.niveau_risque||'faible').toLowerCase();
  var riskCfg={
    faible:{bdg:'rgba(27,94,32,.9)'},
    modéré:{bdg:'rgba(230,81,0,.9)'},
    élevé:{bdg:'rgba(183,28,28,.9)'},
    critique:{bdg:'rgba(136,14,79,.95)'}
  };
  var rc=riskCfg[niv]||riskCfg['faible'];

  var html='<div class="rpt-wrap">';
  html+='<div class="rpt-head">';
  html+='<div style="display:flex;justify-content:space-between;align-items:flex-start;">'
    +'<div><p class="rpt-head-label">Compte-Rendu de Consultation Psychologique — Confidentiel</p>'
    +'<p class="rpt-head-name">'+escH(PAT)+'</p></div>'
    +'<div style="text-align:right;">'
    +'<div class="rpt-risk-badge" style="background:'+rc.bdg+';">'+niv.charAt(0).toUpperCase()+niv.slice(1)+'</div>'
    +'<p style="font-size:9px;color:rgba(255,255,255,.35);margin-top:5px;font-weight:600;">PsySpace Pro</p>'
    +'</div></div>';
  html+='<div class="rpt-meta-grid">'
    +'<div class="rpt-mc"><div class="rpt-mc-lbl">Date</div><div class="rpt-mc-val">'+escH(DATED)+'</div></div>'
    +'<div class="rpt-mc"><div class="rpt-mc-lbl">Séance</div><div class="rpt-mc-val">N°'+SESN+'</div></div>'
    +(PAT_AGE_DISPLAY?'<div class="rpt-mc"><div class="rpt-mc-lbl">Âge</div><div class="rpt-mc-val">'+PAT_AGE_DISPLAY+' ans</div></div>':'<div class="rpt-mc"><div class="rpt-mc-lbl">Durée</div><div class="rpt-mc-val">'+(lr.duree?lr.duree+' min':'—')+'</div></div>')
    +(PAT_VILLE?'<div class="rpt-mc"><div class="rpt-mc-lbl">Ville</div><div class="rpt-mc-val">'+escH(PAT_VILLE)+'</div></div>':'<div class="rpt-mc"><div class="rpt-mc-lbl">Praticien</div><div class="rpt-mc-val">Dr. '+escH(DR)+'</div></div>')
    +'</div></div>';
  html+='<div class="rpt-doc-band">'
    +'<div><p class="rpt-doc-name">Dr. '+escH(DR)+'</p><p class="rpt-doc-sub">'+escH(DR_SPEC)+(DR_RPPS?' · RPPS '+escH(DR_RPPS):'')+'</p></div>'
    +'<div style="text-align:right;"><p class="rpt-doc-sub">'+escH(DATED)+'</p>'+(DR_TEL?'<p class="rpt-doc-sub">'+escH(DR_TEL)+'</p>':'')+'</div>'
    +'</div>';
  html+='<div class="rpt-body">';

  var topEmo=EMO6_KEYS.filter(function(k){ return emoValues6[k]>10; }).slice(0,4);
  if(topEmo.length>0){
    html+='<div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;">';
    topEmo.forEach(function(k){
      html+='<div style="display:flex;align-items:center;gap:5px;padding:5px 11px;border-radius:99px;background:'+PLUT6[k].colorLight+';border:1px solid '+PLUT6[k].border+';font-size:11px;font-weight:600;color:'+PLUT6[k].color+';">'
        +PLUT6[k].label+' '+emoValues6[k]+'%</div>';
    });
    html+='</div>';
  }

  if(ai.resume_psychologue){
    html+='<div class="rpt-summary-box"><div class="rpt-summary-lbl">Synthèse · Motif & Déroulement de séance</div>'
      +'<p class="rpt-summary-txt">'+escH(ai.resume_psychologue)+'</p></div>';
  }
  if(ai.evolution_depuis_derniere_seance){
    html+='<div class="rpt-evolution-box"><div class="rpt-evolution-lbl">Évolution depuis la dernière séance</div>'
      +'<p class="rpt-evolution-txt">'+escH(ai.evolution_depuis_derniere_seance)+'</p></div>';
  }
  if(ai.observations) html+=rptSec('Observations cliniques','<p class="rpt-prose">'+escH(ai.observations)+'</p>');
  if(ai.points_vigilance){
    html+='<div class="rpt-section"><div class="rpt-sec-lbl" style="color:var(--rose);">Points de vigilance</div>'
      +'<div class="rpt-vigil-box"><div class="rpt-vigil-lbl">À surveiller</div><p class="rpt-vigil-txt">'+escH(ai.points_vigilance)+'</p></div></div>';
  }
  if(ai.plan_therapeutique){
    html+='<div class="rpt-section"><div class="rpt-sec-lbl">Plan thérapeutique (suggestion IA)</div>'
      +'<div class="rpt-ib"><p class="rpt-ib-txt">'+escH(ai.plan_therapeutique)+'</p></div></div>';
  }
  html+='</div>';
  html+='<div class="rpt-sig"><div><p class="rpt-sig-sub">Confidentiel · PsySpace Pro</p></div>'
    +'<div style="text-align:right;"><p class="rpt-sig-sub">Dr. '+escH(DR)+'</p><p class="rpt-sig-name">'+escH(DR_SPEC)+'</p>'+(DR_RPPS?'<p class="rpt-sig-sub">RPPS : '+escH(DR_RPPS)+'</p>':'')+'</div>'
    +'</div></div>';
  document.getElementById('report-body').innerHTML=html;
}

function rptSec(title,content){
  return '<div class="rpt-section"><div class="rpt-sec-lbl">'+title+'</div>'+content+'</div>';
}

/* ═══════════════════════════════════════════════════════════
   ARCHIVAGE — notes incluses
═══════════════════════════════════════════════════════════ */
async function finalize() {
  var tr=document.getElementById('transcript').value.trim();
  var notesVal=document.getElementById('notes').value.trim();

  openOv('Archiver la séance',
    'Vous allez archiver la séance n°'+SESN+' de <strong>'+escH(PAT)+'</strong>.<br><br>'
    +(tr?'La transcription et le compte-rendu seront sauvegardés.':'<em>Aucune transcription — seul le compte-rendu sera archivé.</em>')
    +(notesVal?'<br>Vos notes cliniques seront archivées de manière confidentielle.':''),
    async function(){
      ntf('arch-notif','Archivage en cours…','in',0);
      var resumeTexte=lastReport?lastReport.resume_str:'Séance n°'+SESN+' du '+DATED+(tr?' — avec transcription.':' — sans transcription.');
      var planEl=document.getElementById('plan-field');
      var planVal=planEl?planEl.value:'';
      var fd=new FormData();
      fd.append('csrf_token',CSRF);
      fd.append('transcript',tr||'');
      fd.append('resume',resumeTexte);
      fd.append('duree',String(Math.floor(secs/60)));
      fd.append('emotions',JSON.stringify(emoValues6));
      fd.append('emo_timeline',JSON.stringify(emoPoints));
      fd.append('plan',planVal);
      // Notes du praticien — transmises séparément
      fd.append('notes',notesVal);
      if(lastReport){
        fd.append('niveau_risque',lastReport.ai.niveau_risque||'faible');
        fd.append('motif_seance',lastReport.ai.resume_psychologue||'');
        fd.append('evolution_inter',lastReport.ai.evolution_depuis_derniere_seance||'');
      }
      // Sauvegarder âge et ville pour les séances de suivi futures
      if(PAT_AGE_DISPLAY) fd.append('age_patient',String(PAT_AGE_DISPLAY));
      if(PAT_VILLE) fd.append('ville_patient',PAT_VILLE);
      try {
        var res=await fetch('save_consultation.php',{method:'POST',body:fd});
        if(!res.ok) throw new Error('HTTP '+res.status);
        var d=await res.text(); d=d.trim();
        if(d==='success'){
          ntf('arch-notif','Séance archivée avec succès.','ok',0);
          setTimeout(function(){ window.location.href='dashboard.php'; },2000);
        } else if(d==='already_saved'){ ntf('arch-notif','Déjà archivée.','wa');
        } else if(d==='csrf_invalid'){  ntf('arch-notif','Erreur sécurité. Rechargez.','er');
        } else { ntf('arch-notif','Erreur : '+d,'er'); }
      } catch(e){ ntf('arch-notif','Erreur réseau : '+e.message,'er'); }
    }
  );
}

/* ═══════════════════════════════════════════════════════════
   EXPORT PDF
═══════════════════════════════════════════════════════════ */
function exportPDF() {
  if(!lastReport){ ntf('arch-notif',"Générez d'abord le bilan.",'wa'); return; }
  var j=window.jspdf.jsPDF, doc=new j({unit:'mm',format:'a4'});
  var W=210, M=18, y=M;
  var ai=lastReport.ai, niv=(ai.niveau_risque||'faible').toUpperCase();

  function ln(txt,o){
    o=o||{}; var sz=o.sz||10,b=o.b||false,it=o.it||false,c=o.c||[30,28,24];
    doc.setFontSize(sz); doc.setFont(b?'helvetica':'times',b?'bold':(it?'italic':'normal'));
    doc.setTextColor(c[0],c[1],c[2]);
    doc.splitTextToSize(String(txt),W-M*2-(o.ind||0)).forEach(function(l){
      if(y>274){doc.addPage();y=M;} doc.text(l,M+(o.ind||0),y); y+=o.lh||6;
    }); y+=1;
  }
  function hr(){doc.setDrawColor(210,206,200);doc.setLineWidth(.15);doc.line(M,y,W-M,y);y+=4;}
  function sec(title,txt){
    if(y>250){doc.addPage();y=M;}
    doc.setFillColor(15,52,96); doc.rect(M,y-2,3,9,'F');
    doc.setFontSize(8);doc.setFont('helvetica','bold');doc.setTextColor(15,52,96);
    doc.text(title.toUpperCase(),M+6,y+4); y+=10;
    if(txt) ln(txt,{sz:10.5,c:[42,37,32],lh:5.5,ind:4});
    y+=2;
  }

  doc.setFillColor(15,52,96); doc.rect(0,0,W,40,'F');
  doc.setFontSize(9);doc.setFont('helvetica','bold');doc.setTextColor(180,200,240);
  doc.text('COMPTE-RENDU DE CONSULTATION PSYCHOLOGIQUE — CONFIDENTIEL',M,10);
  doc.setFontSize(21);doc.setFont('times','bold');doc.setTextColor(255,255,255);
  doc.text(PAT,M,23);
  var subLine='Séance n°'+SESN+' · '+DATED+' · Dr. '+DR;
  if(PAT_AGE_DISPLAY) subLine+=' · '+PAT_AGE_DISPLAY+' ans';
  if(PAT_VILLE) subLine+=' · '+PAT_VILLE;
  doc.setFontSize(9.5);doc.setFont('helvetica','normal');doc.setTextColor(160,185,230);
  doc.text(subLine,M,31);
  var nc=[27,94,32]; if(niv==='MODÉRÉ') nc=[230,81,0]; if(niv==='ÉLEVÉ'||niv==='CRITIQUE') nc=[183,28,28];
  doc.setFillColor(255,255,255); doc.roundedRect(W-M-34,9,32,14,2,2,'F');
  doc.setFontSize(8);doc.setFont('helvetica','bold');doc.setTextColor(nc[0],nc[1],nc[2]);
  doc.text(niv,W-M-18,17.5,{align:'center'});
  y=46;

  var topEmo=EMO6_KEYS.filter(function(k){ return emoValues6[k]>10; });
  if(topEmo.length>0){
    doc.setFontSize(8);doc.setFont('helvetica','bold');doc.setTextColor(15,52,96);
    doc.text('ÉMOTIONS DÉTECTÉES :',M,y);
    var ex=M+45;
    topEmo.slice(0,5).forEach(function(k){
      doc.setFontSize(8);doc.setFont('helvetica','normal');doc.setTextColor(60,55,50);
      doc.text(PLUT6[k].label+' '+emoValues6[k]+'%',ex,y); ex+=34;
    });
    y+=8;
  }

  doc.setFillColor(248,247,244); doc.rect(M,y,W-M*2,14,'F');
  doc.setFontSize(9);doc.setFont('helvetica','bold');doc.setTextColor(15,52,96);
  doc.text('Dr. '+DR,M+4,y+5);
  doc.setFontSize(8.5);doc.setFont('helvetica','normal');doc.setTextColor(90,86,82);
  doc.text(DR_SPEC+(DR_RPPS?' · RPPS '+DR_RPPS:''),M+4,y+10);
  if(DR_TEL) doc.text(DR_TEL,W-M-4,y+5,{align:'right'});
  y+=18; hr();

  if(ai.resume_psychologue){
    doc.setFillColor(240,245,255); doc.roundedRect(M,y-2,W-M*2,28,2,2,'F');
    doc.setFillColor(15,52,96);doc.rect(M,y-2,3,28,'F');
    doc.setFontSize(8);doc.setFont('helvetica','bold');doc.setTextColor(15,52,96);
    doc.text('SYNTHÈSE · MOTIF & DÉROULEMENT DE SÉANCE',M+6,y+3);
    y+=7; ln(ai.resume_psychologue,{sz:11,c:[20,35,100],lh:5.5,ind:4,it:true}); y+=3;
  }
  if(ai.evolution_depuis_derniere_seance){
    hr();
    doc.setFillColor(224,242,241); doc.roundedRect(M,y-2,W-M*2,20,2,2,'F');
    doc.setFillColor(0,121,107);doc.rect(M,y-2,3,20,'F');
    doc.setFontSize(8);doc.setFont('helvetica','bold');doc.setTextColor(0,77,64);
    doc.text('ÉVOLUTION DEPUIS LA DERNIÈRE SÉANCE',M+6,y+3);
    y+=7; ln(ai.evolution_depuis_derniere_seance,{sz:10.5,c:[0,60,50],lh:5.5,ind:4}); y+=2;
  }
  hr();
  sec('Observations cliniques',ai.observations||'');
  if(ai.points_vigilance) sec('Points de vigilance',ai.points_vigilance);
  if(ai.plan_therapeutique) sec('Suggestion plan thérapeutique (IA)',ai.plan_therapeutique);

  var planEl=document.getElementById('plan-field');
  if(planEl&&planEl.value.trim()){
    hr();
    sec('Plan de suivi (praticien · prochaine séance)',planEl.value.trim());
  }

  var trVal=document.getElementById('transcript').value.trim();
  if(trVal){
    hr();
    if(y>240){doc.addPage();y=M;}
    doc.setFillColor(240,240,248); doc.rect(M,y-2,W-M*2,10,'F');
    doc.setFillColor(15,52,96);doc.rect(M,y-2,3,10,'F');
    doc.setFontSize(8);doc.setFont('helvetica','bold');doc.setTextColor(15,52,96);
    doc.text('TRANSCRIPTION COMPLÈTE DE LA SÉANCE',M+6,y+4); y+=12;
    ln(trVal,{sz:9,c:[50,46,42],lh:5,ind:2});
    y+=2;
  }

  hr();
  doc.setFontSize(8);doc.setFont('helvetica','normal');doc.setTextColor(150,146,142);
  doc.text('PsySpace Pro · Confidentiel · Usage clinique exclusif',M,288);
  doc.text('Dr. '+DR+' · '+lastReport.date,W-M,288,{align:'right'});

  doc.save('CR_'+PAT.replace(/\s+/g,'_')+'_S'+SESN+'_'+DATED.replace(/\//g,'-')+'.pdf');
}

/* ═══════════════════════════════════════════════════════════
   OBJECTIFS
═══════════════════════════════════════════════════════════ */
async function toggleGoal(goalId, el){
  var txt=document.getElementById('gt_'+goalId);
  if(!el.classList.contains('done')){
    el.classList.add('done');
    el.querySelector('.check-svg').style.display='block';
    if(txt) txt.classList.add('done');
    var fd=new FormData(); fd.append('csrf_token',CSRF); fd.append('action','goal_achieved'); fd.append('goal_id',goalId);
    try{ await fetch('save_consultation.php',{method:'POST',body:fd}); }catch(e){}
  }
}

function loadPrev(resume,plan){
  openOv('Charger la séance précédente','Ajouter le résumé et le plan dans vos notes ?',function(){
    var n=document.getElementById('notes');
    var txt='[Séance précédente]\n'+resume.slice(0,400);
    if(plan) txt+='\n\n[Plan précédent]\n'+plan.slice(0,300);
    n.value=(n.value?n.value+'\n\n':'')+txt;
    document.getElementById('notes-save-status').textContent='Notes mises à jour depuis l\'historique.';
    setTimeout(function(){ document.getElementById('notes-save-status').textContent=''; }, 3000);
  });
}

/* ═══════════════════════════════════════════════════════════
   MICROPHONE
═══════════════════════════════════════════════════════════ */
var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
function makeRecog(){
  if(!SR) return null;
  var r=new SR(); r.lang='fr-FR'; r.continuous=true; r.interimResults=true; r.maxAlternatives=1;
  r.onstart=function(){
    micOn=true;
    document.getElementById('mic-btn').className='mic-btn live';
    document.getElementById('mic-label').textContent='CAPTURE ACTIVE — Cliquez pour arrêter';
    document.getElementById('mic-label').style.color='var(--rose)';
    document.getElementById('t-dot').className='t-dot live';
    document.getElementById('rec-status').textContent='Enregistrement';
    document.getElementById('rec-status').className='chip c-live';
    startWaveform(); clearInterval(timerIv);
    timerIv=setInterval(function(){
      secs++; var m=Math.floor(secs/60).toString().padStart(2,'0'),s=(secs%60).toString().padStart(2,'0');
      document.getElementById('timer').textContent=m+':'+s;
      document.getElementById('audio-fill').style.width=(10+Math.random()*65)+'%';
    },1000);
  };
  r.onresult=function(e){
    var final='';
    for(var i=e.resultIndex;i<e.results.length;i++){
      if(e.results[i].isFinal) final+=e.results[i][0].transcript+' ';
    }
    if(final){
      var el=document.getElementById('transcript');
      el.value+=final; el.scrollTop=el.scrollHeight; onTyping(el.value);
    }
  };
  r.onerror=function(e){
    if(e.error==='no-speech') return;
    if(e.error==='not-allowed'||e.error==='permission-denied'){ ntf('tr-notif','Accès microphone refusé.','er'); setMicStopped(); }
  };
  r.onend=function(){ if(micOn){ try{ recog.start(); }catch(ex){ setMicStopped(); } } };
  return r;
}
if(SR){ recog=makeRecog(); }
else{
  setTimeout(function(){
    document.getElementById('stt-warning').style.display='block';
    var mb=document.getElementById('mic-btn');
    if(mb){mb.disabled=true;mb.style.opacity='.3';mb.style.cursor='not-allowed';}
  },500);
}
function toggleMic(){
  if(!SR){ ntf('tr-notif','Non supporté — utilisez Chrome/Edge.','er'); return; }
  if(micOn){ micOn=false; try{ recog.stop(); }catch(e){} setMicStopped(); }
  else{ try{ recog.abort(); }catch(e){} recog=makeRecog(); try{ recog.start(); }catch(e){ ntf('tr-notif','Erreur : '+e.message,'er'); } }
}
function setMicStopped(){
  micOn=false; clearInterval(timerIv); stopWaveform();
  document.getElementById('audio-fill').style.width='0%';
  document.getElementById('mic-btn').className='mic-btn done';
  document.getElementById('mic-label').textContent='Transcription terminée — Cliquez pour reprendre';
  document.getElementById('mic-label').style.color='var(--ok)';
  document.getElementById('t-dot').className='t-dot'; document.getElementById('t-dot').style.background='var(--ok)';
  document.getElementById('rec-status').textContent='Terminé'; document.getElementById('rec-status').className='chip c-ok';
  document.getElementById('btn-generate').disabled=false;
}
var wvIv=null;
function startWaveform(){
  var bars=document.querySelectorAll('.wv-bar');
  wvIv=setInterval(function(){ bars.forEach(function(b){ b.style.height=(3+Math.random()*16)+'px'; b.style.background='var(--ink)'; }); },100);
}
function stopWaveform(){
  clearInterval(wvIv);
  document.querySelectorAll('.wv-bar').forEach(function(b){ b.style.height='3px'; b.style.background='var(--border)'; });
}
function clearTranscript(){
  openOv('Effacer la transcription','Effacer tout le contenu ? Action irréversible.',function(){
    document.getElementById('transcript').value='';
    document.getElementById('word-count').textContent='0 mot';
    document.getElementById('wc-fill').style.width='0%';
    emoPoints=[0]; tlRisk=[]; tlResil=[]; tlAnx=[];
    EMO6_KEYS.forEach(function(k){ emoValues6[k]=0; });
    drawAll(); updateEmo6Grid();
    ntf('tr-notif','Transcription effacée.','in',2500);
  });
}

/* ═══════════════════════════════════════════════════════════
   UTILS
═══════════════════════════════════════════════════════════ */
function escH(s){
  if(!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/\n/g,'<br>');
}

/* INIT */
updateEmo6Grid();
drawAll();
</script>
</body>
</html>