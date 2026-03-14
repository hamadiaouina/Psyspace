<?php
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }
include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    header("Location: consultations.php?error=invalid_id"); exit();
}

 $consultation_id = (int)$_GET['id'];
 $doc_id          = (int)$_SESSION['id'];

// ── Requête conservée ───────────────────────────────────────────
 $stmt = $conn->prepare("
    SELECT c.*,
           a.patient_name,
           a.patient_phone,
           a.app_date
    FROM consultations c
    LEFT JOIN appointments a ON c.appointment_id = a.id
    WHERE c.id = ? AND c.doctor_id = ?
    LIMIT 1
");
 $stmt->bind_param("ii", $consultation_id, $doc_id);
 $stmt->execute();
 $data = $stmt->get_result()->fetch_assoc();
 $stmt->close();

if (!$data) { header("Location: consultations.php?error=not_found"); exit(); }

if (empty($data['patient_name'])) {
    $data['patient_name']  = 'Patient non lié';
    $data['patient_phone'] = '—';
}

// ── Décode resume_ia ────────────────────────────────────────────
 $resume_json = null;
 $resume_text = '';
if (!empty($data['resume_ia'])) {
    $raw = $data['resume_ia'];
    $cleaned = preg_replace('/^```json\s*/i', '', trim($raw));
    $cleaned = preg_replace('/```\s*$/', '', $cleaned);
    $decoded = json_decode(trim($cleaned), true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $resume_json = $decoded;
    } else {
        $resume_text = $raw;
    }
}

// ── Date / heure ─────────────────────────────────────────────────
 $mois    = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
 $ts      = strtotime($data['date_consultation']);
 $date_fr = date('d', $ts) . ' ' . $mois[(int)date('n',$ts)-1] . ' ' . date('Y',$ts);
 $heure   = date('H:i', $ts);

// ── Couleur niveau de risque ─────────────────────────────────────
 $niveau = $resume_json['niveau_risque'] ?? '';
 $rc_map = [
    'faible'   => ['text'=>'text-emerald-700', 'bg'=>'bg-emerald-50', 'border'=>'border-emerald-200'],
    'modéré'   => ['text'=>'text-amber-700',   'bg'=>'bg-amber-50',   'border'=>'border-amber-200'],
    'élevé'    => ['text'=>'text-red-700',     'bg'=>'bg-red-50',     'border'=>'border-red-200'],
    'critique' => ['text'=>'text-rose-800',    'bg'=>'bg-rose-50',    'border'=>'border-rose-300'],
];
 $rc = $rc_map[$niveau] ?? ['text'=>'text-indigo-700', 'bg'=>'bg-indigo-50', 'border'=>'border-indigo-200'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>PsySpace · Rapport de séance</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                    serif: ['Merriweather', 'serif'],
                }
            }
        }
    }
  </script>
  
  <style>
    /* Impression propre */
    @media print {
      body { background: white !important; }
      .no-print { display: none !important; }
      .card-print { break-inside: avoid; box-shadow: none !important; border: 1px solid #e5e7eb !important; }
    }
    .verbatim::-webkit-scrollbar { width: 4px; }
    .verbatim::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
  </style>
</head>
<body class="bg-slate-100 font-sans text-slate-700 min-h-screen">

<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

  <!-- NAVIGATION -->
  <nav class="flex justify-between items-center mb-6 no-print">
    <a href="consultations.php" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-indigo-600 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
      Retour aux archives
    </a>
    <button onclick="window.print()" class="bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider transition shadow-sm">
      Imprimer / PDF
    </button>
  </nav>

  <!-- HEADER -->
  <header class="mb-8 bg-white border-l-4 border-indigo-500 rounded-xl p-6 shadow-sm card-print">
    <h1 class="font-serif text-3xl font-bold text-slate-900 tracking-tight">Rapport de séance</h1>
    <p class="mt-2 text-sm text-slate-500">
      <span class="font-semibold text-indigo-600 text-base"><?= htmlspecialchars($data['patient_name']) ?></span>
      &nbsp;·&nbsp; <?= $date_fr ?> à <?= $heure ?>
      <?php if($data['duree_minutes']>0): ?> &nbsp;·&nbsp; <?= (int)$data['duree_minutes'] ?> min <?php endif; ?>
    </p>
  </header>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- COLONNE PRINCIPALE -->
    <div class="lg:col-span-2 space-y-6">

      <!-- CARTE : COMPTE-RENDU -->
      <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm card-print border-l-4 border-indigo-400">
        <h2 class="text-xs font-bold uppercase tracking-widest text-indigo-600 mb-6 pb-2 border-b border-slate-100">
          Compte-rendu clinique
        </h2>

        <?php if ($resume_json): ?>

          <!-- RISQUE -->
          <?php if ($niveau): ?>
          <div class="flex justify-between items-center p-4 rounded-lg mb-6 border <?= $rc['bg'] ?> <?= $rc['border'] ?>">
            <div>
              <p class="text-[10px] font-bold uppercase tracking-widest <?= $rc['text'] ?> mb-1">Niveau de risque</p>
              <p class="text-2xl font-bold <?= $rc['text'] ?>"><?= ucfirst(htmlspecialchars($niveau)) ?></p>
            </div>
            <span class="px-3 py-1 text-xs font-bold rounded-full border <?= $rc['text'] ?> <?= $rc['bg'] ?> <?= $rc['border'] ?>">
              <?= ucfirst(htmlspecialchars($niveau)) ?>
            </span>
          </div>
          <?php endif; ?>

          <!-- SYNTHÈSE -->
          <?php if (!empty($resume_json['synthese_courte'])): ?>
          <div class="p-5 bg-indigo-50 border-l-4 border-indigo-400 text-sm text-slate-700 mb-6 leading-relaxed rounded-r-lg shadow-sm">
            <p class="font-bold uppercase text-indigo-600 text-[10px] tracking-widest mb-2">Synthèse</p>
            <?= nl2br(htmlspecialchars($resume_json['synthese_courte'])) ?>
          </div>
          <?php endif; ?>

          <!-- SECTIONS STRUCTURÉES -->
          <?php
          // Définition des sections pour la boucle (correction de l'erreur)
          $sections = [
            ['observation',    'Observation clinique',   'border-slate-300'],
            ['humeur',         'État thymique',          'border-amber-300'],
            ['alliance',       'Alliance thérapeutique', 'border-purple-300'],
            ['vigilance',      'Points de vigilance',    'border-red-300'],
            ['axes',           'Axes thérapeutiques',    'border-emerald-300'],
            ['recommandations','Recommandations',        'border-blue-300'],
          ];
          foreach ($sections as $s):
            if (empty($resume_json[$s[0]])) continue;
          ?>
          <div class="mb-5 group">
            <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-slate-300 group-hover:bg-indigo-400 transition"></span>
                <?= $s[1] ?>
            </h3>
            <div class="p-4 bg-white border border-slate-100 border-l-2 <?= $s[2] ?> rounded-r-lg text-sm text-slate-600 leading-relaxed shadow-sm hover:shadow transition">
              <?= nl2br(htmlspecialchars($resume_json[$s[0]])) ?>
            </div>
          </div>
          <?php endforeach; ?>

          <!-- HYPOTHÈSES -->
          <?php if (!empty($resume_json['hypotheses_diag']) && is_array($resume_json['hypotheses_diag'])): ?>
          <div class="mb-5 bg-sky-50 p-4 rounded-lg border border-sky-100">
             <h3 class="text-[10px] font-bold uppercase tracking-widest text-sky-600 mb-3">Hypothèses diagnostiques (CIM-11)</h3>
             <div class="space-y-2">
                <?php foreach ($resume_json['hypotheses_diag'] as $h): ?>
                <div class="flex items-start gap-3 p-2 bg-white border border-sky-100 rounded text-xs text-slate-600 shadow-xs">
                   <span class="bg-sky-100 text-sky-700 font-bold px-2 py-0.5 rounded uppercase shrink-0"><?= htmlspecialchars($h) ?></span>
                </div>
                <?php endforeach; ?>
             </div>
          </div>
          <?php endif; ?>
          
          <!-- OBJECTIFS -->
          <?php if (!empty($resume_json['objectifs_next']) && is_array($resume_json['objectifs_next'])): ?>
          <div class="mb-5 bg-teal-50 p-4 rounded-lg border border-teal-100">
            <h3 class="text-[10px] font-bold uppercase tracking-widest text-teal-600 mb-3">Objectifs prochaine séance</h3>
            <ul class="space-y-2">
              <?php foreach ($resume_json['objectifs_next'] as $i => $o): ?>
              <li class="flex items-start gap-3">
                <span class="w-5 h-5 rounded bg-teal-100 text-teal-700 text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5"><?= $i+1 ?></span>
                <span class="text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($o) ?></span>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

        <?php elseif ($resume_text): ?>
          <div class="text-sm text-slate-600 whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($resume_text) ?></div>
        <?php else: ?>
          <p class="text-sm text-slate-400 italic">Aucun compte-rendu généré.</p>
        <?php endif; ?>
      </div>

      <!-- CARTE : ÉMOTIONS -->
      <?php if (!empty($data['emotion_data'])): ?>
      <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm card-print border-l-4 border-emerald-300">
        <h2 class="text-xs font-bold uppercase tracking-widest text-emerald-600 mb-4 flex items-center gap-2">
           <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
           Dynamique émotionnelle
        </h2>
        <div style="height: 120px; position: relative;">
          <canvas id="emoChart"></canvas>
        </div>
        <div class="flex justify-between text-[9px] font-bold uppercase tracking-widest text-slate-400 mt-2">
          <span>Début</span>
          <span>Fin</span>
        </div>
      </div>
      <?php endif; ?>

      <!-- CARTE : TRANSCRIPTION -->
      <?php if (!empty($data['transcription_brute'])): ?>
      <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm card-print border-l-4 border-slate-300">
        <h2 class="text-xs font-bold uppercase tracking-widest text-slate-500 mb-4 flex items-center gap-2">
          <span class="w-1.5 h-1.5 bg-slate-400 rounded-full"></span>
          Transcription verbatim
        </h2>
        <div class="verbatim max-h-64 overflow-y-auto bg-slate-50 border border-slate-100 rounded-lg p-4 text-xs text-slate-500 font-mono leading-relaxed">
          <?= nl2br(htmlspecialchars($data['transcription_brute'])) ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- SIDEBAR (Infos) -->
    <div class="space-y-6">
      
      <!-- FICHE PATIENT -->
      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm card-print border-l-4 border-indigo-500">
          <div class="bg-slate-50 px-5 py-3 border-b border-slate-100">
             <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Patient</h3>
          </div>
          <div class="p-5 space-y-4">
            <div>
              <p class="text-[10px] uppercase tracking-widest text-slate-400 font-bold">Nom complet</p>
              <p class="font-bold text-slate-800 text-base"><?= htmlspecialchars($data['patient_name']) ?></p>
            </div>
            <div>
              <p class="text-[10px] uppercase tracking-widest text-slate-400 font-bold">Téléphone</p>
              <p class="font-medium text-slate-700 text-sm"><?= htmlspecialchars($data['patient_phone'] ?? '—') ?></p>
            </div>
          </div>
      </div>

      <!-- DÉTAILS SÉANCE -->
      <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm card-print">
          <div class="bg-slate-50 px-5 py-3 border-b border-slate-100">
             <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Détails</h3>
          </div>
          <div class="p-5 space-y-3 text-sm">
           <div class="flex justify-between border-b border-slate-100 pb-2">
             <span class="text-slate-500">Date</span>
             <span class="font-semibold text-slate-800"><?= $date_fr ?></span>
           </div>
           <div class="flex justify-between border-b border-slate-100 pb-2">
             <span class="text-slate-500">Heure</span>
             <span class="font-semibold text-slate-800"><?= $heure ?></span>
           </div>
           <div class="flex justify-between">
             <span class="text-slate-500">Durée</span>
             <span class="font-semibold text-slate-800"><?= ($data['duree_minutes']>0) ? (int)$data['duree_minutes'].' min' : '< 1 min' ?></span>
           </div>
        </div>
      </div>

      <!-- ADMINISTRATIF -->
      <div class="bg-slate-800 text-slate-300 border border-slate-700 rounded-xl p-5 text-xs space-y-2 card-print shadow-inner">
        <div class="flex justify-between">
          <span class="opacity-70">Réf. Séance</span>
          <span class="font-bold text-white">#<?= $data['id'] ?></span>
        </div>
        <div class="flex justify-between">
          <span class="opacity-70">Réf. RDV</span>
          <span class="font-bold text-white">#<?= $data['appointment_id'] ?? '—' ?></span>
        </div>
        <div class="pt-2 mt-2 border-t border-slate-700 text-center">
             <span class="text-[9px] tracking-widest uppercase opacity-50">PsySpace · Sécurisé</span>
        </div>
      </div>

    </div>
  </div>
</div>

<?php if (!empty($data['emotion_data'])): ?>
<script>
var ep = <?php
  $ep_raw = $data['emotion_data'];
  $ep_dec = json_decode($ep_raw, true);
  if (is_array($ep_dec) && !empty($ep_dec)) {
     if(is_array($ep_dec[0])) echo json_encode(array_map(fn($e)=> $e['v'] ?? $e['valence'] ?? 0, $ep_dec));
     else echo json_encode($ep_dec);
  } else { echo '[]'; }
?>;
if (ep.length > 1) {
  var ctx = document.getElementById('emoChart').getContext('2d');
  var grad = ctx.createLinearGradient(0, 0, 0, 120);
  grad.addColorStop(0, 'rgba(16, 185, 129, 0.25)'); // Emerald light
  grad.addColorStop(1, 'rgba(255, 255, 255, 0.0)');
  
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: ep.map((_,i)=>i),
      datasets: [{
        data: ep,
        borderColor: '#10b981', // Emerald
        borderWidth: 2,
        pointRadius: 0,
        fill: true,
        backgroundColor: grad,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { display: false },
        y: { min: -1.2, max: 1.2, display: false }
      }
    }
  });
}
</script>
<?php endif; ?>
</body>
</html>