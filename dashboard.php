<?php
// --- 1. SÉCURITÉ DES SESSIONS & HEADERS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}
session_start();

if (!isset($_SESSION['id'])) { 
    header("Location: login.php"); 
    exit(); 
}

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
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; media-src 'self' https://assets.mixkit.co; object-src 'none'; base-uri 'self'; frame-ancestors 'none';");

include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

$nom_docteur = mb_strtoupper($_SESSION['nom'] ?? 'Docteur', 'UTF-8');
$doc_id      = (int)$_SESSION['id'];

// ── Données du médecin ───────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM doctor WHERE docid=? LIMIT 1");
$stmt->bind_param("i", $doc_id); $stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();
$doc_photo     = $doc['photo'] ?? '';
$doc_specialty = htmlspecialchars($doc['specialty'] ?? '');
$doc_initial   = mb_substr($nom_docteur, 0, 1, 'UTF-8');

// ── Stats ────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT COUNT(DISTINCT patient_name) as total FROM appointments WHERE doctor_id = ?");
$stmt->bind_param("i", $doc_id); $stmt->execute();
$total_patients = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND DATE(app_date) = CURDATE()");
$stmt->bind_param("i", $doc_id); $stmt->execute();
$rdv_du_jour = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM consultations WHERE doctor_id = ? AND DATE(date_consultation) = CURDATE()");
$stmt->bind_param("i", $doc_id); $stmt->execute();
$rdv_termines = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM consultations WHERE doctor_id = ?");
$stmt->bind_param("i", $doc_id); $stmt->execute();
$total_archives = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$progression = ($rdv_du_jour > 0) ? round(($rdv_termines / $rdv_du_jour) * 100) : 0;

// ── Prochains RDV ─────────────────────────────────────────────────
$stmt = $conn->prepare(
   "SELECT a.*,
           (SELECT COUNT(*) FROM consultations c WHERE c.patient_id = a.patient_id AND c.doctor_id = a.doctor_id) AS archive_count
    FROM appointments a
    WHERE a.doctor_id = ?
      AND a.app_date >= NOW()
    ORDER BY a.app_date ASC
    LIMIT 6"
);
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$patients_query = $stmt->get_result();
$stmt->close();

// ── Prochain RDV (countdown) ──────────────────────────────────────
$stmt = $conn->prepare("SELECT app_date, patient_name FROM appointments WHERE doctor_id=? AND app_date >= NOW() ORDER BY app_date ASC LIMIT 1");
$stmt->bind_param("i", $doc_id); $stmt->execute();
$next_rdv = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Dernière consultation ─────────────────────────────────────────
$stmt = $conn->prepare("SELECT date_consultation FROM consultations WHERE doctor_id = ? ORDER BY date_consultation DESC LIMIT 1");
$stmt->bind_param("i", $doc_id); $stmt->execute();
$last_consult = $stmt->get_result()->fetch_assoc()['date_consultation'] ?? null;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | PsySpace</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    
    <script src="https://cdn.tailwindcss.com" nonce="<?= $nonce ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script nonce="<?= $nonce ?>">
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], serif: ['Merriweather', 'serif'] } } }
        };
        // Gestion locale du Dark Mode
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style nonce="<?= $nonce ?>">
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link.active { 
            background: rgba(79,70,229,0.25); 
            color: #a5b4fc; 
            font-weight: 600;
            border-left: 2px solid #6366f1;
            padding-left: calc(1rem - 2px);
        }
    </style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css"/>
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.js.iife.js"></script>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-700 dark:text-slate-300 transition-colors duration-300">

<div class="flex min-h-screen relative">

    <!-- SIDEBAR MOBILE OVERLAY -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity"></div>

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
            <a href="dashboard.php" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Dashboard
            </a>
            <a href="patients_search.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Patients
            </a>
            <a href="agenda.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Agenda
            </a>
            <a href="consultations.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                Archives
            </a>
        </nav>

        <div class="p-4 border-t border-white/5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold border-2 border-indigo-200 overflow-hidden">
                    <?php if(!empty($doc_photo) && file_exists($doc_photo)): ?>
                        <img src="<?= htmlspecialchars($doc_photo) ?>?v=<?= time() ?>" class="w-full h-full rounded-full object-cover">
                    <?php else: ?>
                        <?= $doc_initial ?>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <a href="profile.php" class="text-sm font-bold text-white truncate hover:text-indigo-300 transition-colors block">
                        Dr. <?= htmlspecialchars(ucwords(strtolower($nom_docteur))) ?>
                    </a>
                </div>
                <a href="logout.php" class="text-slate-500 hover:text-red-400 p-2" title="Déconnexion">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 lg:ml-64 p-4 md:p-8 w-full">
        
<!-- HEADER -->
<div class="flex flex-wrap justify-between items-center mb-8 gap-4">
    <div class="flex items-center gap-4">
        <button id="open-sidebar" class="lg:hidden p-2 text-slate-500 bg-white dark:bg-slate-800 rounded-md border border-slate-200 dark:border-slate-700 shadow-sm">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Bonjour, Dr. <?= htmlspecialchars($nom_docteur) ?></h1>
            
            <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                <p class="text-slate-500 dark:text-slate-400 text-sm">Voici le résumé de votre activité.</p>
                
                <!-- BADGE CODE SECRÉTARIAT (Subtil et Pro) -->
                <?php $cabinet_code = strtoupper(substr(md5($_SESSION['id'] . "PsySpaceCabinet2026"), 0, 10)); ?>
                <span class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md text-xs font-mono font-bold text-slate-600 dark:text-slate-300 flex items-center gap-1.5 cursor-pointer hover:bg-slate-200 dark:hover:bg-slate-700 transition" 
                      onclick="navigator.clipboard.writeText('<?= $cabinet_code ?>'); alert('Code cabinet copié !');" 
                      title="Copier le code pour l'assistante">
                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    Code Cabinet : <?= $cabinet_code ?>
                </span>
            </div>

        </div>
    </div>
            
            <div class="flex items-center gap-4">
                <button id="btn-demo" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition-colors flex items-center gap-2">
                    Visite Guidée
                </button>
                <button id="theme-toggle" class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-all border border-transparent dark:border-slate-700">
                    <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                    <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 011.414-1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"></path></svg>
                </button>
                <div class="hidden sm:block text-right">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Aujourd'hui</p>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= date('d M, Y') ?></p>
                </div>
            </div>
        </div>

        <!-- COUNTDOWN PROCHAIN RDV -->
        <?php if($next_rdv): ?>
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 mb-8 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Prochain rendez-vous</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($next_rdv['patient_name']) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800 px-4 py-2 rounded-lg border border-slate-100 dark:border-slate-700">
                <div class="text-center px-2">
                    <span class="countdown-val text-lg font-bold text-slate-900 dark:text-white" id="cd-h">--</span>
                    <span class="text-xs text-slate-400 block">H</span>
                </div>
                <span class="font-bold text-slate-300 dark:text-slate-600">:</span>
                <div class="text-center px-2">
                    <span class="countdown-val text-lg font-bold text-slate-900 dark:text-white" id="cd-m">--</span>
                    <span class="text-xs text-slate-400 block">Min</span>
                </div>
                <span class="font-bold text-slate-300 dark:text-slate-600">:</span>
                <div class="text-center px-2">
                    <span class="countdown-val text-lg font-bold text-slate-900 dark:text-white" id="cd-s">--</span>
                    <span class="text-xs text-slate-400 block">Sec</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- STATS GRID -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 hover:shadow-md transition-shadow">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Patients actifs</p>
                <p class="font-serif text-4xl font-bold text-indigo-600 dark:text-indigo-400"><?= $total_patients ?></p>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 hover:shadow-md transition-shadow">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Séances du jour</p>
                <p class="font-serif text-4xl font-bold text-slate-900 dark:text-white"><?= $rdv_du_jour ?></p>
                <div class="mt-4 w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5">
                    <div class="bg-indigo-600 dark:bg-indigo-500 h-1.5 rounded-full" style="width: <?= $progression ?>%"></div>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 hover:shadow-md transition-shadow">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Terminées</p>
                <p class="font-serif text-4xl font-bold text-slate-900 dark:text-white"><?= $rdv_termines ?></p>
                <div class="mt-4 text-xs text-emerald-600 dark:text-emerald-400 font-medium"><?= $progression ?>% complété</div>
            </div>
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 hover:shadow-md transition-shadow">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Archives</p>
                <p class="font-serif text-4xl font-bold text-slate-900 dark:text-white"><?= $total_archives ?></p>
                <a href="consultations.php" class="mt-4 text-xs text-indigo-600 dark:text-indigo-400 font-medium hover:underline inline-block">Voir tout →</a>
            </div>
        </div>

        <!-- TABLEAUX & LISTES -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                    <h3 class="font-bold text-slate-900 dark:text-white">Prochains rendez-vous</h3>
                    <a href="agenda.php" class="text-xs text-indigo-600 dark:text-indigo-400 font-medium hover:underline">Voir l'agenda</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php if ($patients_query->num_rows > 0):
                                while ($row = $patients_query->fetch_assoc()):
                                    $ts       = strtotime($row['app_date']);
                                    $archived = (int)$row['archive_count'] > 0;
                                    $patient_enc = urlencode($row['patient_name']);
                            ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 flex flex-col items-center justify-center text-slate-600 dark:text-slate-300">
                                            <span class="text-[9px] font-bold uppercase"><?= date('M', $ts) ?></span>
                                            <span class="text-sm font-bold leading-none"><?= date('d', $ts) ?></span>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-800 dark:text-slate-200 text-sm"><?= htmlspecialchars($row['patient_name']) ?></p>
                                            <p class="text-xs text-slate-400 dark:text-slate-500"><?= date('H:i', $ts) ?> · <?= htmlspecialchars($row['app_type'] ?? 'Consultation') ?></p>
                                        </div>
                                    </div>
                                </td>
                                    <td class="px-6 py-4 text-right">
    <div class="flex items-center justify-end gap-2">
        <!-- NOUVEAU BOUTON CALENDRIER -->
        <a href="download_ics.php?id=<?= $row['id'] ?>" 
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-slate-700 text-indigo-600 dark:text-indigo-400 border border-slate-200 dark:border-slate-700 rounded-md text-xs font-bold transition-colors shadow-sm"
           title="Ajouter au calendrier (Outlook, Apple, Google)">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            ICS
        </a>

        <!-- BOUTON DÉMARRER OU ARCHIVÉ -->
        <?php if ($archived): ?>
            <span class="text-xs font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 rounded-md">Archivé</span>
        <?php else: ?>
            <a href="analyse_ia.php?patient_name=<?= $patient_enc ?>&id=<?= $row['id'] ?>"
               class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-md text-xs font-bold transition-colors shadow-sm">
                Démarrer <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        <?php endif; ?>
    </div>
</td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td class="p-10 text-center text-slate-400 text-sm">Aucun rendez-vous à venir.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-indigo-600 dark:bg-indigo-700 rounded-xl p-6 text-white shadow-md">
                    <h3 class="font-bold text-lg mb-2">Analyse IA</h3>
                    <p class="text-indigo-100 text-sm mb-4">Recherchez un dossier patient pour générer une analyse sémantique.</p>
                    <a href="patients_search.php" class="block w-full bg-white text-indigo-600 text-center font-bold py-2.5 rounded-lg text-sm hover:bg-indigo-50 transition-colors">
                        Rechercher
                    </a>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm">
                    <h3 class="font-bold text-slate-900 dark:text-white mb-4 text-sm">Activité</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 dark:text-slate-400">Dernière séance</span>
                            <span class="font-medium text-slate-700 dark:text-slate-300">
                                <?= $last_consult ? date('d/m/y', strtotime($last_consult)) : '-' ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 dark:text-slate-400">Sécurité</span>
                            <span class="font-medium text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg> Active
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script nonce="<?= $nonce ?>">
// Gestion Sidebar Mobile
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebar-overlay');
const openBtn = document.getElementById('open-sidebar');
const closeBtn = document.getElementById('close-sidebar');

function toggleSidebar() {
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

openBtn.addEventListener('click', toggleSidebar);
closeBtn.addEventListener('click', toggleSidebar);
overlay.addEventListener('click', toggleSidebar);

// Gestion Dark Mode local
const themeToggleBtn = document.getElementById('theme-toggle');
const darkIcon = document.getElementById('theme-toggle-dark-icon');
const lightIcon = document.getElementById('theme-toggle-light-icon');

if (document.documentElement.classList.contains('dark')) {
    lightIcon.classList.remove('hidden');
} else {
    darkIcon.classList.remove('hidden');
}

themeToggleBtn.addEventListener('click', function() {
    darkIcon.classList.toggle('hidden');
    lightIcon.classList.toggle('hidden');
    if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('color-theme', 'light');
    } else {
        document.documentElement.classList.add('dark');
        localStorage.setItem('color-theme', 'dark');
    }
});

// Notifications
if (Notification.permission !== "granted") {
    Notification.requestPermission();
}
function alertPro(patientName, time) {
    const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3');
    audio.play().catch(e => console.log('Audio bloqué:', e));
    if (Notification.permission === "granted") {
        new Notification("PsySpace : Consultation Imminente", { body: "RDV avec " + patientName + " à " + time });
    }
}
setInterval(() => {
    fetch('api_check_now.php')
    .then(r => r.json())
    .then(data => { if(data.alert === true) alertPro(data.patient, data.time); })
    .catch(e => console.error('Erreur Polling:', e));
}, 60000); 

// Compte à rebours
<?php if($next_rdv): ?>
var targetTs = <?= strtotime($next_rdv['app_date']) ?> * 1000;
function updateCountdown() {
  var now = Date.now();
  var diff = Math.floor((targetTs - now) / 1000);
  if (diff <= 0) {
    ['cd-h', 'cd-m', 'cd-s'].forEach(id => document.getElementById(id).textContent = '00');
    return;
  }
  document.getElementById('cd-h').textContent = String(Math.floor(diff / 3600)).padStart(2,'0');
  document.getElementById('cd-m').textContent = String(Math.floor((diff % 3600) / 60)).padStart(2,'0');
  document.getElementById('cd-s').textContent = String(diff % 60).padStart(2,'0');
}
updateCountdown();
setInterval(updateCountdown, 1000);
<?php endif; ?>
</script>
<script>
// On attend que la page soit bien chargée avant de faire quoi que ce soit
document.addEventListener('DOMContentLoaded', function() {
    
    // On cherche le bouton que tu viens d'ajouter à l'étape 2
    const btnDemo = document.getElementById('btn-demo');
    
    if (btnDemo) {
        // Quand on clique sur le bouton...
        btnDemo.addEventListener('click', function() {
            
            // On initialise le moteur Driver.js
            const driver = window.driver.js.driver;
            
            const tour = driver({
                showProgress: true,       // Affiche "1 sur 3"
                nextBtnText: 'Suivant →', // Traduction des boutons en français
                prevBtnText: '← Retour',
                doneBtnText: 'Terminer',
                
                // Voici les étapes de ta visite guidée :
                steps: [
                    { 
                        // Étape 1 : Pop-up central (pas lié à un élément précis)
                        popover: { 
                            title: 'Bienvenue sur PsySpace ! 👋', 
                            description: 'Faisons un petit tour rapide de votre espace médecin pour vous familiariser avec les outils.' 
                        } 
                    },
                    { 
                        // Étape 2 : On éclaire la barre de navigation à gauche
                        element: '#sidebar', 
                        popover: { 
                            title: 'Votre Menu Principal', 
                            description: 'C\'est ici que vous gérez vos patients, vos rendez-vous et vos paramètres.', 
                            side: "right", 
                            align: 'start' 
                        } 
                    },
                    { 
                        // Étape 3 : On éclaire le bouton Dark Mode
                        element: '#theme-toggle', 
                        popover: { 
                            title: 'Travailler de nuit ? 🌙', 
                            description: 'Un simple clic ici permet de basculer l\'interface en mode sombre pour reposer vos yeux.', 
                            side: "bottom", 
                            align: 'end' 
                        } 
                    }
                ]
            });
            
            // On démarre l'animation !
            tour.drive();
        });
    }
});
</script>
<!-- ========================================================================= -->
<!-- 💬 TIROIR DE TCHAT & FLUX D'ACTIVITÉ (À coller juste avant </body>) -->
<!-- ========================================================================= -->
<div id="chat-button" class="fixed bottom-6 right-6 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full p-4 shadow-xl cursor-pointer transition-transform hover:scale-105 z-50 flex items-center justify-center">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
</div>

<div id="chat-drawer" class="fixed top-0 right-0 h-full w-80 md:w-96 bg-white dark:bg-slate-900 shadow-2xl z-50 transform translate-x-full transition-transform duration-300 flex flex-col border-l border-slate-200 dark:border-slate-800">
    <!-- Header du Tchat -->
    <div class="p-4 bg-indigo-600 text-white flex justify-between items-center shadow-md">
        <div class="flex items-center gap-2">
            <span class="text-xl">💬</span>
            <h3 class="font-bold">Liaison Cabinet</h3>
        </div>
        <button id="close-chat" class="text-indigo-200 hover:text-white text-2xl leading-none">&times;</button>
    </div>

    <!-- Zone des messages -->
    <div id="chat-messages" class="flex-1 p-4 overflow-y-auto bg-slate-50 dark:bg-slate-950 flex flex-col gap-3 custom-scroll">
        <!-- Les messages apparaîtront ici -->
    </div>

    <!-- Zone de saisie -->
    <div class="p-4 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
        <form id="chat-form" class="flex gap-2">
            <input type="text" id="chat-input" placeholder="Votre message..." required autocomplete="off" class="flex-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-full px-4 py-2 text-sm outline-none focus:border-indigo-500 dark:text-white">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-full w-10 h-10 flex items-center justify-center shrink-0 shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
            </button>
        </form>
    </div>
</div>

<script nonce="<?= $nonce ?>">
    const chatBtn = document.getElementById('chat-button');
    const chatDrawer = document.getElementById('chat-drawer');
    const closeChat = document.getElementById('close-chat');
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    
    // Déterminer qui je suis pour l'affichage (Docteur ou Assistant)
    // On regarde l'URL pour savoir si on est sur la page assistante ou non
    const amI_Assistant = window.location.pathname.includes('assistante.php');

    // Ouvrir / Fermer le tiroir
    chatBtn.addEventListener('click', () => chatDrawer.classList.remove('translate-x-full'));
    closeChat.addEventListener('click', () => chatDrawer.classList.add('translate-x-full'));

    // Charger les messages
    function loadMessages() {
        fetch('api_chat.php?action=fetch')
            .then(res => res.json())
            .then(data => {
                chatMessages.innerHTML = '';
                data.forEach(msg => {
                    let html = '';
                    
                    // 🤖 Message Système (Gris au centre)
                    if (msg.sender_type === 'system') {
                        html = `<div class="text-center my-2"><span class="bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-[10px] font-bold uppercase px-3 py-1 rounded-full">🤖 Système : ${msg.message} (${msg.time})</span></div>`;
                    } 
                    // 👤 Message de moi-même (À droite, en couleur principale)
                    else if ((msg.sender_type === 'assistant' && amI_Assistant) || (msg.sender_type === 'doctor' && !amI_Assistant)) {
                        html = `
                        <div class="self-end max-w-[80%] flex flex-col items-end">
                            <div class="bg-indigo-600 text-white text-sm py-2 px-3 rounded-2xl rounded-tr-sm shadow-sm">${msg.message}</div>
                            <span class="text-[10px] text-slate-400 mt-1">${msg.time}</span>
                        </div>`;
                    } 
                    // 👥 Message de l'autre (À gauche, en gris)
                    else {
                        const senderName = msg.sender_type === 'doctor' ? '👨‍⚕️ Docteur' : '👩‍💼 Secrétariat';
                        html = `
                        <div class="self-start max-w-[80%] flex flex-col items-start">
                            <span class="text-[10px] font-bold text-slate-500 mb-1">${senderName}</span>
                            <div class="bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 text-sm py-2 px-3 rounded-2xl rounded-tl-sm shadow-sm">${msg.message}</div>
                            <span class="text-[10px] text-slate-400 mt-1">${msg.time}</span>
                        </div>`;
                    }
                    chatMessages.innerHTML += html;
                });
                // Scroller tout en bas
                chatMessages.scrollTop = chatMessages.scrollHeight;
            });
    }

    // Envoyer un message
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const text = chatInput.value.trim();
        if (!text) return;

        const formData = new FormData();
        formData.append('message', text);

        fetch('api_chat.php?action=send', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    chatInput.value = '';
                    loadMessages(); // Recharger immédiatement
                }
            });
    });

    // Charger les messages au démarrage et toutes les 5 secondes
    loadMessages();
    setInterval(loadMessages, 5000);
</script>
<!-- ========================================================================= -->
 <!-- ========================================================================= -->
<!-- 💬 TIROIR DE TCHAT & FLUX D'ACTIVITÉ (VERSION DOCTEUR) -->
<!-- ========================================================================= -->
<div id="chat-button" class="fixed bottom-6 right-6 w-14 h-14 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full shadow-2xl cursor-pointer transition-transform hover:scale-105 z-50 flex items-center justify-center print:hidden">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
    <!-- Le badge rouge de notification -->
    <span id="chat-badge" class="absolute -top-1 -right-1 bg-red-500 border-2 border-white dark:border-slate-900 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center hidden animate-bounce">0</span>
</div>

<div id="chat-drawer" class="fixed top-0 right-0 h-full w-80 md:w-96 bg-white dark:bg-slate-900 shadow-2xl z-50 transform translate-x-full transition-transform duration-300 flex flex-col border-l border-slate-200 dark:border-slate-800 print:hidden">
    <!-- Header -->
    <div class="p-4 bg-indigo-600 text-white flex justify-between items-center shadow-md">
        <div class="flex items-center gap-2">
            <span class="text-xl">💬</span>
            <h3 class="font-bold">Liaison Secrétariat</h3>
        </div>
        <button id="close-chat" class="text-indigo-200 hover:text-white text-2xl leading-none">&times;</button>
    </div>

    <!-- Messages -->
    <div id="chat-messages" class="flex-1 p-4 overflow-y-auto bg-slate-50 dark:bg-slate-950 flex flex-col gap-3 custom-scroll"></div>

    <!-- Input -->
    <div class="p-4 bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800">
        <form id="chat-form" class="flex gap-2">
            <input type="text" id="chat-input" placeholder="Message à l'assistante..." required autocomplete="off" class="flex-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-full px-4 py-2 text-sm outline-none focus:border-indigo-500 dark:text-white transition-colors">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-full w-10 h-10 flex items-center justify-center shrink-0 shadow-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
            </button>
        </form>
    </div>
</div>

<script>
    const chatBtn = document.getElementById('chat-button');
    const chatDrawer = document.getElementById('chat-drawer');
    const closeChat = document.getElementById('close-chat');
    const chatMessages = document.getElementById('chat-messages');
    const chatForm = document.getElementById('chat-form');
    const chatInput = document.getElementById('chat-input');
    const chatBadge = document.getElementById('chat-badge');

    let lastMsgCount = 0; 
    let isDrawerOpen = false;
    const notifSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');

    // Ouvrir / Fermer
    chatBtn.addEventListener('click', () => {
        chatDrawer.classList.remove('translate-x-full');
        isDrawerOpen = true;
        chatBadge.classList.add('hidden'); 
        chatBadge.textContent = '0';
        chatMessages.scrollTop = chatMessages.scrollHeight;
    });
    
    closeChat.addEventListener('click', () => {
        chatDrawer.classList.add('translate-x-full');
        isDrawerOpen = false;
    });

    // Charger les messages (Version Docteur)
    function loadMessages() {
        fetch('api_chat.php?action=fetch')
            .then(res => res.json())
            .then(data => {
                let html = '';
                data.forEach(msg => {
                    // 🤖 Alertes Système
                    if (msg.sender_type === 'system') {
                        html += `<div class="text-center my-2"><span class="bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-[10px] font-bold px-3 py-1 rounded-full">🤖 ${msg.message}</span><div class="text-[9px] text-slate-400 mt-1">${msg.time}</div></div>`;
                    } 
                    // 👨‍⚕️ C'est MOI (le Docteur)
                    else if (msg.sender_type === 'doctor') {
                        html += `<div class="self-end max-w-[80%] flex flex-col items-end"><div class="bg-indigo-600 text-white text-sm py-2 px-3 rounded-2xl rounded-tr-sm shadow-sm">${msg.message}</div><span class="text-[10px] text-slate-400 mt-1">${msg.time}</span></div>`;
                    } 
                    // 👩‍💼 C'est l'autre (l'Assistante)
                    else {
                        html += `<div class="self-start max-w-[80%] flex flex-col items-start"><span class="text-[10px] font-bold text-slate-500 mb-1">👩‍💼 Assistante</span><div class="bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 text-sm py-2 px-3 rounded-2xl rounded-tl-sm shadow-sm">${msg.message}</div><span class="text-[10px] text-slate-400 mt-1">${msg.time}</span></div>`;
                    }
                });

                chatMessages.innerHTML = html;

                // NOTIFICATIONS 🔔
                if (data.length > lastMsgCount) {
                    if (lastMsgCount !== 0 && !isDrawerOpen) {
                        let unread = parseInt(chatBadge.textContent) + (data.length - lastMsgCount);
                        chatBadge.textContent = unread;
                        chatBadge.classList.remove('hidden');
                        notifSound.play().catch(e => console.log('Son bloqué')); 
                    }
                    if (isDrawerOpen) chatMessages.scrollTop = chatMessages.scrollHeight;
                    lastMsgCount = data.length;
                }
            });
    }

    // Envoyer un message
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const text = chatInput.value.trim();
        if (!text) return;

        const formData = new FormData(); 
        formData.append('message', text);

        fetch('api_chat.php?action=send', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    chatInput.value = '';
                    loadMessages();
                }
            });
    });

    // Chargement initial et rafraîchissement toutes les 3 secondes
    loadMessages();
    setInterval(loadMessages, 3000);
</script>
<!-- ========================================================================= -->
</body>
</html>