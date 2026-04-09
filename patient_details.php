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

// --- 3. PARE-FEU CSP ---
$nonce = base64_encode(random_bytes(16));
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none';");

include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

if (!isset($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    header("Location: consultations.php?error=invalid_id"); exit();
}

$consultation_id = (int)$_GET['id'];
$doc_id          = (int)$_SESSION['id'];

// ── Requête sécurisée (Anti-IDOR) ───────────────────────────────
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

// ── Date / heure ───────────────────────────────────────────────��─
$mois    = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$ts      = strtotime($data['date_consultation']);
$date_fr = date('d', $ts) . ' ' . $mois[(int)date('n',$ts)-1] . ' ' . date('Y',$ts);
$heure   = date('H:i', $ts);

// ── Couleur niveau de risque (adapté Dark Mode) ──────────────────
$niveau = $resume_json['niveau_risque'] ?? '';
$rc_map = [
    'faible'   => ['text'=>'text-emerald-700 dark:text-emerald-400', 'bg'=>'bg-emerald-50 dark:bg-emerald-900/30', 'border'=>'border-emerald-200 dark:border-emerald-800'],
    'modéré'   => ['text'=>'text-amber-700 dark:text-amber-400',   'bg'=>'bg-amber-50 dark:bg-amber-900/30',   'border'=>'border-amber-200 dark:border-amber-800'],
    'élevé'    => ['text'=>'text-red-700 dark:text-red-400',     'bg'=>'bg-red-50 dark:bg-red-900/30',     'border'=>'border-red-200 dark:border-red-800'],
    'critique' => ['text'=>'text-rose-800 dark:text-rose-400',    'bg'=>'bg-rose-50 dark:bg-rose-900/30',    'border'=>'border-rose-300 dark:border-rose-800'],
];
$rc = $rc_map[$niveau] ?? ['text'=>'text-indigo-700 dark:text-indigo-400', 'bg'=>'bg-indigo-50 dark:bg-indigo-900/30', 'border'=>'border-indigo-200 dark:border-indigo-800'];
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
  <link rel="icon" type="image/png" href="assets/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>PsySpace · Rapport de séance</title>
  
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" nonce="<?= $nonce ?>"></script>
  <script src="https://cdn.tailwindcss.com" nonce="<?= $nonce ?>"></script>
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <script nonce="<?= $nonce ?>">
    tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], serif: ['Merriweather', 'serif'] } } } };
    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    }
  </script>
  
  <style nonce="<?= $nonce ?>">
    body { font-family: 'Inter', sans-serif; }
    .sidebar-link { transition: all 0.2s ease; }
    .sidebar-link.active { background: rgba(79,70,229,0.25); color: #a5b4fc; font-weight: 600; border-left: 2px solid #6366f1; padding-left: calc(1rem - 2px); }
    .verbatim::-webkit-scrollbar { width: 4px; }
    .verbatim::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .dark .verbatim::-webkit-scrollbar-thumb { background: #475569; }
    
    /* Impression propre (Force fond blanc, cache sidebar & dark mode) */
    @media print {
      body { background: white !important; color: black !important; }
      .dark body { background: white !important; color: black !important; }
      aside, #sidebar-overlay, #theme-toggle, .no-print { display: none !important; }
      main { margin-left: 0 !important; padding: 0 !important; }
      .card-print { break-inside: avoid; box-shadow: none !important; border: 1px solid #e5e7eb !important; background: white !important; color: black !important; }
      * { color: black !important; }
    }
  </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-700 dark:text-slate-300 transition-colors duration-300 min-h-screen">

<div class="flex min-h-screen relative">

    <!-- SIDEBAR MOBILE OVERLAY -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity print:hidden"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-gradient-to-b from-slate-900 to-indigo-950 border-r border-white/5 flex flex-col fixed h-full z-50 transition-transform transform -translate-x-full lg:translate-x-0 print:hidden">
        <div class="p-6 border-b border-white/5 flex justify-between items-center">
            <a href="dashboard.php" class="flex items-center gap-3">
                <img src="assets/images/logo.png" alt="PsySpace Logo" class="h-8 w-8 rounded-lg object-cover">
                <span class="text-lg font-bold text-white">PsySpace</span>
            </a>
            <button id="close-sidebar" class="lg:hidden text-slate-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <nav class="flex-1 p-4 space-y-1">
            <a href="dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
            <a href="patients_search.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Patients
            </a>
            <a href="agenda.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Agenda
            </a>
            <a href="consultations.php" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-white bg-slate-800/50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                Archives
            </a>
        </nav>
        <div class="p-4 border-t border-white/5">
             <a href="logout.php" class="flex items-center gap-2 text-slate-500 hover:text-red-400 text-sm font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Déconnexion
            </a>
        </div>
    </aside>

    <main class="flex-1 lg:ml-64 p-4 md:p-8 w-full max-w-6xl mx-auto">
        
        <!-- NAVIGATION HAUT -->
        <nav class="flex justify-between items-center mb-6 no-print">
            <div class="flex items-center gap-4">
                <button id="open-sidebar" class="lg:hidden p-2 text-slate-500 bg-white dark:bg-slate-800 rounded-md border border-slate-200 dark:border-slate-700 shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="consultations.php" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    Retour aux archives
                </a>
            </div>
            
            <div class="flex items-center gap-4">
                <button id="theme-toggle" class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-all border border-transparent dark:border-slate-700">
                    <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                    <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 011.414-1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"></path></svg>
                </button>
                <button onclick="window.print()" class="bg-indigo-600 dark:bg-indigo-500 hover:bg-indigo-700 dark:hover:bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider transition shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span class="hidden sm:inline">Imprimer / PDF</span>
                </button>
            </div>
        </nav>

        <!-- HEADER RAPPORT -->
        <header class="mb-8 bg-white dark:bg-slate-900 border-l-4 border-indigo-500 rounded-xl p-6 shadow-sm card-print transition-colors">
            <h1 class="font-serif text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Rapport de séance</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                <span class="font-semibold text-indigo-600 dark:text-indigo-400 text-base"><?= htmlspecialchars($data['patient_name']) ?></span>
                &nbsp;·&nbsp; <?= $date_fr ?> à <?= $heure ?>
                <?php if($data['duree_minutes']>0): ?> &nbsp;·&nbsp; <?= (int)$data['duree_minutes'] ?> min <?php endif; ?>
            </p>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- COLONNE PRINCIPALE -->
            <div class="lg:col-span-2 space-y-6">

                <!-- CARTE : COMPTE-RENDU -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm card-print border-l-4 border-indigo-400 transition-colors">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-6 pb-2 border-b border-slate-100 dark:border-slate-800">
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
                    <div class="p-5 bg-indigo-50 dark:bg-indigo-900/20 border-l-4 border-indigo-400 text-sm text-slate-700 dark:text-slate-300 mb-6 leading-relaxed rounded-r-lg shadow-sm">
                        <p class="font-bold uppercase text-indigo-600 dark:text-indigo-400 text-[10px] tracking-widest mb-2">Synthèse</p>
                        <?= nl2br(htmlspecialchars($resume_json['synthese_courte'])) ?>
                    </div>
                    <?php endif; ?>

                    <!-- SECTIONS STRUCTURÉES -->
                    <?php
                    $sections = [
                        ['observation',    'Observation clinique',   'border-slate-300 dark:border-slate-600'],
                        ['humeur',         'État thymique',          'border-amber-300 dark:border-amber-600'],
                        ['alliance',       'Alliance thérapeutique', 'border-purple-300 dark:border-purple-600'],
                        ['vigilance',      'Points de vigilance',    'border-red-300 dark:border-red-600'],
                        ['axes',           'Axes thérapeutiques',    'border-emerald-300 dark:border-emerald-600'],
                        ['recommandations','Recommandations',        'border-blue-300 dark:border-blue-600'],
                    ];
                    foreach ($sections as $s):
                        if (empty($resume_json[$s[0]])) continue;
                    ?>
                    <div class="mb-5 group">
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-2 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-600 group-hover:bg-indigo-400 transition"></span>
                            <?= $s[1] ?>
                        </h3>
                        <div class="p-4 bg-white dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 border-l-2 <?= $s[2] ?> rounded-r-lg text-sm text-slate-600 dark:text-slate-300 leading-relaxed shadow-sm hover:shadow dark:hover:shadow-slate-800 transition">
                            <?= nl2br(htmlspecialchars($resume_json[$s[0]])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- HYPOTHÈSES -->
                    <?php if (!empty($resume_json['hypotheses_diag']) && is_array($resume_json['hypotheses_diag'])): ?>
                    <div class="mb-5 bg-sky-50 dark:bg-sky-900/20 p-4 rounded-lg border border-sky-100 dark:border-sky-800/50">
                         <h3 class="text-[10px] font-bold uppercase tracking-widest text-sky-600 dark:text-sky-400 mb-3">Hypothèses diagnostiques (CIM-11)</h3>
                         <div class="space-y-2">
                            <?php foreach ($resume_json['hypotheses_diag'] as $h): ?>
                            <div class="flex items-start gap-3 p-2 bg-white dark:bg-slate-800/80 border border-sky-100 dark:border-sky-800 rounded text-xs text-slate-600 dark:text-slate-300 shadow-sm">
                               <span class="bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-400 font-bold px-2 py-0.5 rounded uppercase shrink-0"><?= htmlspecialchars($h) ?></span>
                            </div>
                            <?php endforeach; ?>
                         </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- OBJECTIFS -->
                    <?php if (!empty($resume_json['objectifs_next']) && is_array($resume_json['objectifs_next'])): ?>
                    <div class="mb-5 bg-teal-50 dark:bg-teal-900/20 p-4 rounded-lg border border-teal-100 dark:border-teal-800/50">
                        <h3 class="text-[10px] font-bold uppercase tracking-widest text-teal-600 dark:text-teal-400 mb-3">Objectifs prochaine séance</h3>
                        <ul class="space-y-2">
                            <?php foreach ($resume_json['objectifs_next'] as $i => $o): ?>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded bg-teal-100 dark:bg-teal-900/50 text-teal-700 dark:text-teal-400 text-[10px] font-bold flex items-center justify-center shrink-0 mt-0.5"><?= $i+1 ?></span>
                                <span class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed"><?= htmlspecialchars($o) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php elseif ($resume_text): ?>
                    <div class="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($resume_text) ?></div>
                    <?php else: ?>
                    <p class="text-sm text-slate-400 dark:text-slate-500 italic">Aucun compte-rendu généré.</p>
                    <?php endif; ?>
                </div>

                <!-- CARTE : ÉMOTIONS -->
                <?php if (!empty($data['emotion_data']) && $data['emotion_data'] !== '[]'): ?>
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm card-print border-l-4 border-emerald-300 transition-colors">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-emerald-600 dark:text-emerald-400 mb-4 flex items-center gap-2">
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
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm card-print border-l-4 border-slate-300 transition-colors">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400 mb-4 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-slate-400 dark:bg-slate-500 rounded-full"></span>
                        Transcription verbatim
                    </h2>
                    <div class="verbatim max-h-64 overflow-y-auto bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 rounded-lg p-4 text-xs text-slate-500 dark:text-slate-400 font-mono leading-relaxed transition-colors">
                        <?= nl2br(htmlspecialchars($data['transcription_brute'])) ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- SIDEBAR (Infos) -->
            <div class="space-y-6">
                
                <!-- FICHE PATIENT -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm card-print border-l-4 border-indigo-500 transition-colors">
                    <div class="bg-slate-50 dark:bg-slate-800/50 px-5 py-3 border-b border-slate-100 dark:border-slate-800 transition-colors">
                         <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Patient</h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold">Nom complet</p>
                            <p class="font-bold text-slate-800 dark:text-slate-200 text-base"><?= htmlspecialchars($data['patient_name']) ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-bold">Téléphone</p>
                            <p class="font-medium text-slate-700 dark:text-slate-300 text-sm"><?= htmlspecialchars($data['patient_phone'] ?? '—') ?></p>
                        </div>
                    </div>
                </div>

                <!-- DÉTAILS SÉANCE -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm card-print transition-colors">
                    <div class="bg-slate-50 dark:bg-slate-800/50 px-5 py-3 border-b border-slate-100 dark:border-slate-800 transition-colors">
                         <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-slate-400">Détails</h3>
                    </div>
                    <div class="p-5 space-y-3 text-sm">
                        <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                            <span class="text-slate-500 dark:text-slate-400">Date</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200"><?= $date_fr ?></span>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                            <span class="text-slate-500 dark:text-slate-400">Heure</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200"><?= $heure ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 dark:text-slate-400">Durée</span>
                            <span class="font-semibold text-slate-800 dark:text-slate-200"><?= ($data['duree_minutes']>0) ? (int)$data['duree_minutes'].' min' : '< 1 min' ?></span>
                        </div>
                    </div>
                </div>

                <!-- ADMINISTRATIF -->
                <div class="bg-slate-800 dark:bg-slate-950 text-slate-300 border border-slate-700 dark:border-slate-800 rounded-xl p-5 text-xs space-y-2 card-print shadow-inner transition-colors">
                    <div class="flex justify-between">
                        <span class="opacity-70">Réf. Séance</span>
                        <span class="font-bold text-white">#<?= $data['id'] ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="opacity-70">Réf. RDV</span>
                        <span class="font-bold text-white">#<?= $data['appointment_id'] ?? '—' ?></span>
                    </div>
                    <div class="pt-2 mt-2 border-t border-slate-700 dark:border-slate-800 text-center">
                         <span class="text-[9px] tracking-widest uppercase opacity-50">PsySpace · Sécurisé</span>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<script nonce="<?= $nonce ?>">
// Gestion Menu Mobile & Dark Mode
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebar-overlay');
const openBtn = document.getElementById('open-sidebar');
const closeBtn = document.getElementById('close-sidebar');

function toggleSidebar() { sidebar.classList.toggle('-translate-x-full'); overlay.classList.toggle('hidden'); }
if(openBtn) openBtn.addEventListener('click', toggleSidebar);
if(closeBtn) closeBtn.addEventListener('click', toggleSidebar);
if(overlay) overlay.addEventListener('click', toggleSidebar);

const themeToggleBtn = document.getElementById('theme-toggle');
const darkIcon = document.getElementById('theme-toggle-dark-icon');
const lightIcon = document.getElementById('theme-toggle-light-icon');

if (document.documentElement.classList.contains('dark')) { lightIcon.classList.remove('hidden'); } 
else { darkIcon.classList.remove('hidden'); }

themeToggleBtn.addEventListener('click', function() {
    darkIcon.classList.toggle('hidden');
    lightIcon.classList.toggle('hidden');
    if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark'); localStorage.setItem('color-theme', 'light');
    } else {
        document.documentElement.classList.add('dark'); localStorage.setItem('color-theme', 'dark');
    }
});

// Graphique des émotions
<?php if (!empty($data['emotion_data']) && $data['emotion_data'] !== '[]'): ?>
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
  grad.addColorStop(1, 'rgba(16, 185, 129, 0.0)');
  
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
<?php endif; ?>
</script>
</body>
</html>