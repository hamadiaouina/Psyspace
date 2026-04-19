<?php
// ════════════════════════════════════════════════════════════════
//  PsySpace · rapport_seance.php — Compte-Rendu Clinique Complet
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

if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    header("Location: consultations.php?error=invalid_id"); exit();
}

$consultation_id = (int)$_GET['id'];
$doc_id = (int)$_SESSION['id'];

// ── Consultation + patient + docteur
$stmt = $conn->prepare("
    SELECT c.*,
           a.patient_name, a.patient_phone, a.app_date, a.app_type,
           p.pdob, p.pgender, p.pprofession, p.psituation,
           p.pmotif_initial, p.pantecedents, p.ptraitement, p.padresse, p.pemail,
           d.docname, d.docprenom, d.specialty, d.rpps, d.tel AS doc_tel, d.adresse AS doc_adresse
    FROM consultations c
    LEFT JOIN appointments a ON c.appointment_id = a.id
    LEFT JOIN patients p ON c.patient_id = p.id
    LEFT JOIN doctor d ON c.doctor_id = d.docid
    WHERE c.id = ? AND c.doctor_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $consultation_id, $doc_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) { header("Location: consultations.php?error=not_found"); exit(); }

// ── Numéro de séance (combien de consultations avant celle-ci pour ce patient)
$stmt_sn = $conn->prepare("SELECT COUNT(*) as n FROM consultations WHERE patient_id=? AND doctor_id=? AND date_consultation <= ? AND id <= ?");
$stmt_sn->bind_param("iisi", $data['patient_id'], $doc_id, $data['date_consultation'], $consultation_id);
$stmt_sn->execute();
$sn_row = $stmt_sn->get_result()->fetch_assoc();
$stmt_sn->close();
$session_num = (int)($sn_row['n'] ?? 1);

// ── Données patient
$pat_name     = $data['patient_name']  ?: 'Patient non lié';
$pat_phone    = $data['patient_phone'] ?: null;
$pat_email    = $data['pemail']        ?: null;
$pat_adresse  = $data['padresse']      ?: null;
$pat_dob      = $data['pdob']          ?: null;
$pat_age      = $data['age_patient']   ?: ($pat_dob ? (int)date_diff(date_create($pat_dob), date_create('today'))->y : null);
$pat_ville    = $data['ville_patient'] ?: null;
$pat_gender   = $data['pgender']       ?: null;
$pat_job      = $data['pprofession']   ?: null;
$pat_sit      = $data['psituation']    ?: null;
$pat_motif    = $data['pmotif_initial'] ?: null;
$pat_ant      = $data['pantecedents']  ?: null;
$pat_trt      = $data['ptraitement']   ?: null;

// ── Données docteur
$doc_fullname = trim(($data['docprenom'] ?? '') . ' ' . ($data['docname'] ?? '')) ?: ($_SESSION['nom'] ?? 'Dr.');
$doc_spec     = $data['specialty']    ?: 'Psychologue clinicien';
$doc_rpps     = $data['rpps']         ?: null;
$doc_tel      = $data['doc_tel']      ?: null;
$doc_adr      = $data['doc_adresse']  ?: null;

// ── Dates
$mois_fr = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$ts = strtotime($data['date_consultation']);
$date_fr = date('d', $ts) . ' ' . $mois_fr[(int)date('n',$ts)] . ' ' . date('Y', $ts);
$heure   = date('H:i', $ts);

// ── Résumé IA
$resume_ia = trim($data['resume_ia'] ?? '');

// ── Émotions Plutchik (nouveau format JSON objet)
$emotions_pu = [];
if (!empty($data['emotions_plutchik'])) {
    $dec = json_decode($data['emotions_plutchik'], true);
    if (is_array($dec)) $emotions_pu = $dec;
}

// ── Niveau de risque
$niveau_risque = strtolower(trim($data['niveau_risque'] ?? 'faible'));
$risque_cfg = [
    'faible'   => ['label'=>'Faible',   'color'=>'#1B6B3A', 'bg'=>'#EDFAF3', 'border'=>'#A7F3D0'],
    'modéré'   => ['label'=>'Modéré',   'color'=>'#C77700', 'bg'=>'#FFF8E1', 'border'=>'#FDE68A'],
    'élevé'    => ['label'=>'Élevé',    'color'=>'#B71C1C', 'bg'=>'#FFF3F3', 'border'=>'#FECACA'],
    'critique' => ['label'=>'Critique', 'color'=>'#7B1FA2', 'bg'=>'#F3E5F5', 'border'=>'#DDD6FE'],
];
$rc = $risque_cfg[$niveau_risque] ?? $risque_cfg['faible'];

// ── Émotions mapping
$emo_cfg = [
    'tristesse' => ['label'=>'Tristesse', 'color'=>'#1565C0', 'bg'=>'#E3F2FD'],
    'joie'      => ['label'=>'Joie',      'color'=>'#E65100', 'bg'=>'#FFF3E0'],
    'peur'      => ['label'=>'Peur',      'color'=>'#6A1B9A', 'bg'=>'#F3E5F5'],
    'colere'    => ['label'=>'Colère',    'color'=>'#B71C1C', 'bg'=>'#FFEBEE'],
    'degout'    => ['label'=>'Dégoût',    'color'=>'#558B2F', 'bg'=>'#F1F8E9'],
    'surprise'  => ['label'=>'Surprise',  'color'=>'#00796B', 'bg'=>'#E0F2F1'],
];

// Trier les émotions par score décroissant
arsort($emotions_pu);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CR · <?= htmlspecialchars($pat_name) ?> · <?= $date_fr ?> | PsySpace</title>
<link rel="icon" type="image/png" href="assets/images/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;
  --paper:#FFFFFF;
  --border:#DDD8CF;
  --border2:#EAE6DF;
  --tx:#1A1714;
  --tx2:#4A4540;
  --tx3:#8C867E;
  --tx4:#B8B2AA;
  --ink:#0D2D6B;
  --ink-lt:#EEF3FF;
  --teal:#005F52;
  --teal-lt:#E6F4F1;
  --rose:#8B1A1A;
  --rose-lt:#FDF3F3;
  --amber:#7A4800;
  --amber-lt:#FFF8ED;
  --violet:#4A1272;
  --violet-lt:#F5EEFF;
  --ok:#1B5E20;
  --ok-lt:#F0FAF0;
  --sh:0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.05);
  --sh2:0 2px 8px rgba(0,0,0,.08), 0 8px 32px rgba(0,0,0,.08);
}

html,body{min-height:100%;font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--tx);font-size:14px;line-height:1.6;}

/* ── LAYOUT ── */
.page{max-width:900px;margin:0 auto;padding:32px 20px 80px;}

/* ── TOPBAR ── */
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;}
.back-btn{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--tx3);text-decoration:none;padding:6px 12px;border:1px solid var(--border);border-radius:6px;background:var(--paper);transition:all .15s;}
.back-btn:hover{color:var(--ink);border-color:var(--ink);}
.topbar-actions{display:flex;gap:8px;}
.btn-print{display:inline-flex;align-items:center;gap:6px;font-size:11.5px;font-weight:700;padding:7px 16px;border-radius:6px;border:none;cursor:pointer;background:var(--ink);color:#fff;transition:all .15s;letter-spacing:.02em;}
.btn-print:hover{background:#1a3c7a;}

/* ── DOCUMENT HEADER ── */
.doc-header{background:var(--ink);border-radius:12px 12px 0 0;padding:28px 32px 24px;color:#fff;position:relative;overflow:hidden;}
.doc-header::before{content:'';position:absolute;top:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.04);}
.doc-header::after{content:'';position:absolute;bottom:-30px;right:60px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.03);}
.doc-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.22em;color:rgba(255,255,255,.4);margin-bottom:6px;}
.doc-patient-name{font-family:'Lora',serif;font-size:28px;font-weight:700;color:#fff;letter-spacing:-.02em;line-height:1.2;margin-bottom:4px;}
.doc-subtitle{font-size:12px;color:rgba(255,255,255,.55);margin-top:2px;}
.risk-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:99px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;border:1.5px solid rgba(255,255,255,.25);color:#fff;position:relative;z-index:1;}
.doc-meta-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;margin-top:20px;background:rgba(255,255,255,.08);border-radius:8px;overflow:hidden;}
.doc-meta-cell{background:rgba(0,0,0,.15);padding:10px 14px;}
.doc-meta-lbl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.16em;color:rgba(255,255,255,.35);margin-bottom:3px;}
.doc-meta-val{font-size:13px;font-weight:700;color:#fff;}
.doc-praticien-band{background:rgba(0,0,0,.2);margin:0 -32px -24px;padding:11px 32px;display:flex;justify-content:space-between;align-items:center;margin-top:18px;border-top:1px solid rgba(255,255,255,.08);}
.doc-prat-name{font-size:13px;font-weight:700;color:#fff;}
.doc-prat-sub{font-size:10px;color:rgba(255,255,255,.4);margin-top:1px;}

/* ── BODY WRAPPER ── */
.doc-body{background:var(--paper);border:1px solid var(--border);border-top:none;border-radius:0 0 12px 12px;box-shadow:var(--sh);}

/* ── SECTIONS ── */
.section{padding:24px 32px;border-bottom:1px solid var(--border2);}
.section:last-child{border-bottom:none;}
.section-title{font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.2em;color:var(--tx3);margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.section-title::before{content:'';width:3px;height:12px;border-radius:2px;background:var(--ink);flex-shrink:0;}
.section-title.rose::before{background:var(--rose);}
.section-title.teal::before{background:var(--teal);}
.section-title.amber::before{background:var(--amber);}
.section-title.violet::before{background:var(--violet);}
.section-title.ok::before{background:var(--ok);}

/* ── PATIENT INFO GRID ── */
.pat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
.pat-field{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:11px 14px;}
.pat-field-lbl{font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.14em;color:var(--tx3);margin-bottom:4px;}
.pat-field-val{font-size:13px;font-weight:600;color:var(--tx);}
.pat-field-val.missing{color:var(--tx4);font-weight:400;font-style:italic;}
.pat-field.full{grid-column:1/-1;}
.pat-field.half{grid-column:span 2;}

/* ── RESUME BOX ── */
.resume-box{background:var(--ink-lt);border:1px solid rgba(13,45,107,.12);border-left:4px solid var(--ink);border-radius:0 8px 8px 0;padding:16px 18px;margin-bottom:0;}
.resume-label{font-size:8.5px;font-weight:800;text-transform:uppercase;letter-spacing:.18em;color:var(--ink);margin-bottom:8px;}
.resume-txt{font-family:'Lora',serif;font-size:14px;line-height:1.9;color:#1A2D5A;font-style:italic;}

/* ── OBSERVATIONS / VIGILANCE ── */
.prose-block{font-size:13.5px;line-height:1.85;color:var(--tx2);}
.vigil-box{background:var(--rose-lt);border:1px solid rgba(139,26,26,.15);border-left:4px solid var(--rose);border-radius:0 8px 8px 0;padding:14px 16px;}
.vigil-lbl{font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:var(--rose);margin-bottom:6px;}
.vigil-txt{font-size:13px;line-height:1.75;color:var(--tx2);}

/* ── ÉVOLUTION ── */
.evo-box{background:var(--teal-lt);border:1px solid rgba(0,95,82,.15);border-left:4px solid var(--teal);border-radius:0 8px 8px 0;padding:14px 16px;}
.evo-lbl{font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:var(--teal);margin-bottom:6px;}
.evo-txt{font-size:13px;line-height:1.75;color:var(--tx2);}

/* ── ÉMOTIONS ── */
.emo-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
.emo-cell{border-radius:8px;padding:12px 10px;text-align:center;border:1.5px solid;}
.emo-name{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px;}
.emo-pct{font-family:'Lora',serif;font-size:22px;font-weight:700;line-height:1;margin-bottom:6px;}
.emo-bar-track{height:3px;border-radius:2px;background:rgba(0,0,0,.1);overflow:hidden;}
.emo-bar-fill{height:3px;border-radius:2px;}
.emo-badge{display:inline-block;margin-top:6px;padding:2px 7px;border-radius:99px;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;}

/* ── PLAN ── */
.plan-box{background:var(--teal-lt);border:1px solid rgba(0,95,82,.2);border-left:4px solid var(--teal);border-radius:0 8px 8px 0;padding:16px 18px;}
.plan-txt{font-size:13px;line-height:1.85;color:var(--tx2);white-space:pre-wrap;}

/* ── NOTES PRATICIEN ── */
.notes-box{background:var(--amber-lt);border:1px solid rgba(122,72,0,.15);border-left:4px solid var(--amber);border-radius:0 8px 8px 0;padding:14px 16px;}
.notes-txt{font-size:13px;line-height:1.75;color:var(--tx2);white-space:pre-wrap;}

/* ── ANTÉCÉDENTS / TRAITEMENT ── */
.ant-box{background:var(--rose-lt);border:1px solid rgba(139,26,26,.12);border-left:3px solid var(--rose);border-radius:0 6px 6px 0;padding:11px 14px;}
.trt-box{background:var(--violet-lt);border:1px solid rgba(74,18,114,.12);border-left:3px solid var(--violet);border-radius:0 6px 6px 0;padding:11px 14px;}
.ant-lbl, .trt-lbl{font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.14em;margin-bottom:5px;}
.ant-lbl{color:var(--rose);}
.trt-lbl{color:var(--violet);}
.ant-txt, .trt-txt{font-size:12.5px;line-height:1.7;color:var(--tx2);}

/* ── TRANSCRIPTION ── */
.verbatim{background:#FAFAF8;border:1px solid var(--border);border-radius:8px;padding:16px;font-family:'JetBrains Mono',monospace;font-size:11.5px;line-height:1.8;color:var(--tx3);max-height:300px;overflow-y:auto;}
.verbatim::-webkit-scrollbar{width:3px;}
.verbatim::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px;}

/* ── SIGNATURE ── */
.doc-sig{padding:20px 32px;display:flex;justify-content:space-between;align-items:center;background:#FAFAF8;border-top:1px solid var(--border2);border-radius:0 0 12px 12px;}
.sig-left{font-size:11px;color:var(--tx4);}
.sig-right{text-align:right;}
.sig-name{font-size:13px;font-weight:700;color:var(--tx);}
.sig-sub{font-size:10.5px;color:var(--tx3);margin-top:1px;}

/* ── RISQUE INLINE ── */
.risk-inline{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;font-size:9.5px;font-weight:800;text-transform:uppercase;letter-spacing:.1em;border:1.5px solid;}

/* ── PRINT ── */
@media print{
  .topbar,.btn-print,.back-btn{display:none!important;}
  body{background:#fff;}
  .page{padding:0;max-width:100%;}
  .doc-header{border-radius:0;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  .doc-body{border-radius:0;box-shadow:none;border:none;}
  .section{break-inside:avoid;}
  .verbatim{max-height:none;}
}
</style>
</head>
<body>
<div class="page">

  <!-- TOPBAR -->
  <div class="topbar">
    <a href="consultations.php" class="back-btn">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Archives
    </a>
    <div class="topbar-actions">
      <button class="btn-print" onclick="window.print()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Imprimer / PDF
      </button>
    </div>
  </div>

  <!-- DOCUMENT -->
  <div>

    <!-- EN-TÊTE -->
    <div class="doc-header">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;">
        <div style="flex:1;">
          <div class="doc-label">Compte-Rendu de Consultation Psychologique — Confidentiel</div>
          <div class="doc-patient-name"><?= htmlspecialchars($pat_name) ?></div>
          <div class="doc-subtitle">
            Séance n°<?= $session_num ?> · <?= $date_fr ?> à <?= $heure ?>
            <?php if($data['duree_minutes']>0): ?> · <?= (int)$data['duree_minutes'] ?> min<?php endif; ?>
            <?php if($pat_age): ?> · <?= $pat_age ?> ans<?php endif; ?>
            <?php if($pat_ville): ?> · <?= htmlspecialchars($pat_ville) ?><?php endif; ?>
          </div>
        </div>
        <div style="text-align:right;flex-shrink:0;margin-left:16px;">
          <div class="risk-badge" style="background:<?= $rc['color'] ?>33;border-color:rgba(255,255,255,.25);">
            <span style="width:6px;height:6px;border-radius:50%;background:<?= $rc['color'] ?>;display:inline-block;"></span>
            Risque <?= $rc['label'] ?>
          </div>
          <div style="font-size:9px;color:rgba(255,255,255,.3);margin-top:6px;font-weight:600;">PsySpace Pro</div>
        </div>
      </div>

      <div class="doc-meta-row">
        <div class="doc-meta-cell">
          <div class="doc-meta-lbl">Date</div>
          <div class="doc-meta-val"><?= $date_fr ?></div>
        </div>
        <div class="doc-meta-cell">
          <div class="doc-meta-lbl">Séance</div>
          <div class="doc-meta-val">N°<?= $session_num ?></div>
        </div>
        <div class="doc-meta-cell">
          <div class="doc-meta-lbl">Âge</div>
          <div class="doc-meta-val"><?= $pat_age ? $pat_age.' ans' : '—' ?></div>
        </div>
        <div class="doc-meta-cell">
          <div class="doc-meta-lbl">Ville</div>
          <div class="doc-meta-val"><?= $pat_ville ? htmlspecialchars($pat_ville) : '—' ?></div>
        </div>
      </div>

      <div class="doc-praticien-band">
        <div>
          <div class="doc-prat-name">Dr. <?= htmlspecialchars($doc_fullname) ?></div>
          <div class="doc-prat-sub"><?= htmlspecialchars($doc_spec) ?><?= $doc_rpps ? ' · RPPS '.$doc_rpps : '' ?></div>
        </div>
        <div style="text-align:right;">
          <?php if($doc_tel): ?><div class="doc-prat-sub"><?= htmlspecialchars($doc_tel) ?></div><?php endif; ?>
          <?php if($doc_adr): ?><div class="doc-prat-sub"><?= htmlspecialchars($doc_adr) ?></div><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- CORPS DU DOCUMENT -->
    <div class="doc-body">

      <!-- 1. PROFIL PATIENT -->
      <div class="section">
        <div class="section-title">Profil patient</div>
        <div class="pat-grid">
          <div class="pat-field">
            <div class="pat-field-lbl">Nom complet</div>
            <div class="pat-field-val"><?= htmlspecialchars($pat_name) ?></div>
          </div>
          <div class="pat-field">
            <div class="pat-field-lbl">Date de naissance</div>
            <div class="pat-field-val <?= !$pat_dob?'missing':'' ?>">
              <?= $pat_dob ? date('d/m/Y', strtotime($pat_dob)).($pat_age?' ('.$pat_age.' ans)':'') : 'Non renseigné' ?>
            </div>
          </div>
          <div class="pat-field">
            <div class="pat-field-lbl">Genre</div>
            <div class="pat-field-val <?= !$pat_gender?'missing':'' ?>"><?= $pat_gender ? htmlspecialchars($pat_gender) : 'Non renseigné' ?></div>
          </div>
          <div class="pat-field">
            <div class="pat-field-lbl">Téléphone</div>
            <div class="pat-field-val <?= !$pat_phone?'missing':'' ?>"><?= $pat_phone ? htmlspecialchars($pat_phone) : 'Non renseigné' ?></div>
          </div>
          <div class="pat-field">
            <div class="pat-field-lbl">Email</div>
            <div class="pat-field-val <?= !$pat_email?'missing':'' ?>"><?= $pat_email ? htmlspecialchars($pat_email) : 'Non renseigné' ?></div>
          </div>
          <div class="pat-field">
            <div class="pat-field-lbl">Situation</div>
            <div class="pat-field-val <?= !$pat_sit?'missing':'' ?>"><?= $pat_sit ? htmlspecialchars($pat_sit) : 'Non renseigné' ?></div>
          </div>
          <div class="pat-field">
            <div class="pat-field-lbl">Profession</div>
            <div class="pat-field-val <?= !$pat_job?'missing':'' ?>"><?= $pat_job ? htmlspecialchars($pat_job) : 'Non renseigné' ?></div>
          </div>
          <div class="pat-field">
            <div class="pat-field-lbl">Ville</div>
            <div class="pat-field-val <?= !$pat_ville?'missing':'' ?>"><?= $pat_ville ? htmlspecialchars($pat_ville) : 'Non renseigné' ?></div>
          </div>
          <div class="pat-field">
            <div class="pat-field-lbl">Adresse</div>
            <div class="pat-field-val <?= !$pat_adresse?'missing':'' ?>"><?= $pat_adresse ? htmlspecialchars($pat_adresse) : 'Non renseigné' ?></div>
          </div>
          <?php if($pat_motif): ?>
          <div class="pat-field full">
            <div class="pat-field-lbl">Motif initial de consultation</div>
            <div class="pat-field-val"><?= htmlspecialchars($pat_motif) ?></div>
          </div>
          <?php endif; ?>
        </div>

        <?php if($pat_ant || $pat_trt): ?>
        <div style="display:grid;grid-template-columns:<?= ($pat_ant&&$pat_trt)?'1fr 1fr':'1fr' ?>;gap:12px;margin-top:12px;">
          <?php if($pat_ant): ?>
          <div class="ant-box">
            <div class="ant-lbl">Antécédents</div>
            <div class="ant-txt"><?= nl2br(htmlspecialchars($pat_ant)) ?></div>
          </div>
          <?php endif; ?>
          <?php if($pat_trt): ?>
          <div class="trt-box">
            <div class="trt-lbl">Traitement en cours</div>
            <div class="trt-txt"><?= nl2br(htmlspecialchars($pat_trt)) ?></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- 2. PROFIL ÉMOTIONNEL -->
      <?php if(!empty($emotions_pu)): ?>
      <div class="section">
        <div class="section-title teal">Profil émotionnel de séance</div>
        <div class="emo-grid">
          <?php foreach($emotions_pu as $emo_key => $emo_val):
            $emo_val = max(0, min(100, (int)$emo_val));
            if($emo_val === 0) continue;
            $ecfg = $emo_cfg[$emo_key] ?? ['label'=>ucfirst($emo_key),'color'=>'#666','bg'=>'#f5f5f5'];
            $badge = $emo_val >= 75 ? 'Dominant' : ($emo_val >= 40 ? 'Présent' : 'Trace');
          ?>
          <div class="emo-cell" style="background:<?= $ecfg['bg'] ?>;border-color:<?= $ecfg['color'] ?>44;">
            <div class="emo-name" style="color:<?= $ecfg['color'] ?>;"><?= $ecfg['label'] ?></div>
            <div class="emo-pct" style="color:<?= $ecfg['color'] ?>;"><?= $emo_val ?>%</div>
            <div class="emo-bar-track">
              <div class="emo-bar-fill" style="width:<?= $emo_val ?>%;background:<?= $ecfg['color'] ?>;"></div>
            </div>
            <div class="emo-badge" style="background:<?= $ecfg['color'] ?>22;color:<?= $ecfg['color'] ?>;"><?= $badge ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- 3. SYNTHÈSE CLINIQUE (résumé IA) -->
      <?php if($resume_ia): ?>
      <div class="section">
        <div class="section-title">Synthèse · Motif & Déroulement de séance</div>
        <div class="resume-box">
          <div class="resume-label">Compte-rendu clinique</div>
          <div class="resume-txt"><?= nl2br(htmlspecialchars($resume_ia)) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- 4. ÉVOLUTION -->
      <?php if(!empty($data['evolution_inter']) && trim($data['evolution_inter'])): ?>
      <div class="section">
        <div class="section-title teal">Évolution depuis la dernière séance</div>
        <div class="evo-box">
          <div class="evo-lbl">Comparaison inter-séances</div>
          <div class="evo-txt"><?= nl2br(htmlspecialchars($data['evolution_inter'])) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- 5. POINTS DE VIGILANCE -->
      <?php 
      // Chercher les points de vigilance dans le résumé (champ motif_seance peut contenir la synthèse complète)
      // On n'a pas de champ dédié, mais on peut les afficher si présents
      $notes_pr = trim($data['notes_praticien'] ?? '');
      if($notes_pr): ?>
      <div class="section">
        <div class="section-title amber">Notes cliniques du praticien <span style="font-size:9px;font-weight:500;text-transform:none;letter-spacing:0;color:var(--tx4);margin-left:8px;">Confidentiel · Usage exclusif</span></div>
        <div class="notes-box">
          <div class="notes-txt"><?= nl2br(htmlspecialchars($notes_pr)) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- 6. PLAN DE SUIVI -->
      <?php if(!empty($data['plan_therapeutique']) && trim($data['plan_therapeutique'])): ?>
      <div class="section">
        <div class="section-title teal">Plan de suivi · Prochaine séance</div>
        <div class="plan-box">
          <div class="plan-txt"><?= nl2br(htmlspecialchars($data['plan_therapeutique'])) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- 7. TRANSCRIPTION -->
      <?php $transcript = trim($data['transcription_brute'] ?? $data['transcription'] ?? '');
      if($transcript): ?>
      <div class="section">
        <div class="section-title" style="">Transcription verbatim de séance</div>
        <div class="verbatim"><?= nl2br(htmlspecialchars($transcript)) ?></div>
      </div>
      <?php endif; ?>

      <!-- SIGNATURE -->
      <div class="doc-sig">
        <div class="sig-left">Confidentiel · PsySpace Pro · Usage clinique exclusif · Réf. #<?= $data['id'] ?></div>
        <div class="sig-right">
          <div class="sig-name">Dr. <?= htmlspecialchars($doc_fullname) ?></div>
          <div class="sig-sub"><?= htmlspecialchars($doc_spec) ?><?= $doc_rpps ? ' · RPPS '.$doc_rpps : '' ?></div>
        </div>
      </div>

    </div><!-- /doc-body -->
  </div><!-- /document -->

</div><!-- /page -->
</body>
</html>