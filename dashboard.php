<?php
// --- 1. SÉCURITÉ DES SESSIONS & HEADERS (Le bouclier invisible) ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

// Détection HTTPS pour sécuriser le cookie
if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}

session_start();

if (!isset($_SESSION['id'])) { 
    header("Location: login.php"); 
    exit(); 
}

// --- 2. ANTI VOL DE SESSION (Session Hijacking) ---
if (isset($_SESSION['user_ip']) && isset($_SESSION['user_agent'])) {
    if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR'] || $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        header("Location: login.php?error=hijack");
        exit();
    }
}

// --- 3. GÉNÉRATION DU PARE-FEU CSP SPÉCIFIQUE AU DASHBOARD ---
$nonce = base64_encode(random_bytes(16));
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; media-src 'self' https://assets.mixkit.co; object-src 'none'; base-uri 'self'; frame-ancestors 'none';");

// --- 4. CONNEXION DB ---
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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | PsySpace</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    
    <!-- Ajout du nonce pour la sécurité CSP -->
    <script src="https://cdn.tailwindcss.com" nonce="<?= $nonce ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script nonce="<?= $nonce ?>">
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
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link.active { background-color: #eef2ff; color: #4f46e5; font-weight: 600; }
    </style>
</head>
<body class="bg-slate-50 text-slate-700">

<div class="flex min-h-screen">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col fixed h-full z-50 print:hidden">
        <div class="p-6 border-b border-slate-800">
            <a href="dashboard.php" class="flex items-center gap-3">
                <img src="assets/images/logo.png" alt="PsySpace Logo" class="h-8 w-8 rounded-lg object-cover">
                <span class="text-lg font-bold text-white">PsySpace</span>
            </a>
        </div>

        <nav class="flex-1 p-4 space-y-1">
            <a href="dashboard.php" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-white bg-slate-800/50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Dashboard
            </a>
            <a href="patients_search.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Patients
            </a>
            <a href="agenda.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Agenda
            </a>
            <a href="consultations.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                Archives
            </a>
        </nav>

        <!-- Profil Sidebar -->
        <div class="p-4 border-t border-slate-800">
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
    <main class="flex-1 ml-64 p-8">
        
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Bonjour, Dr. <?= htmlspecialchars($nom_docteur) ?></h1>
                <p class="text-slate-500 text-sm mt-1">Voici le résumé de votre activité.</p>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="hidden sm:block text-right">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Aujourd'hui</p>
                    <p class="text-sm font-semibold text-slate-700"><?= date('d M, Y') ?></p>
                </div>

                <a href="profile.php" class="flex items-center gap-3 bg-white border border-slate-200 rounded-full pl-4 pr-1.5 py-1.5 hover:border-indigo-400 hover:shadow-md transition-all group cursor-pointer">
                    <div class="text-right hidden sm:block">
                        <p class="text-xs text-slate-500">Connecté en tant que</p>
                        <p class="text-sm font-bold text-slate-800 group-hover:text-indigo-600 leading-tight">Dr. <?= htmlspecialchars(ucwords(strtolower($nom_docteur))) ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm border-2 border-white shadow overflow-hidden">
                        <?php if(!empty($doc_photo) && file_exists($doc_photo)): ?>
                            <img src="<?= htmlspecialchars($doc_photo) ?>?v=<?= time() ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <?= $doc_initial ?>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        </div>

        <!-- COUNTDOWN PROCHAIN RDV -->
        <?php if($next_rdv): ?>
        <div class="bg-white border border-slate-200 rounded-xl p-5 mb-8 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600 border border-indigo-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Prochain rendez-vous</p>
                    <p class="text-lg font-bold text-slate-900"><?= htmlspecialchars($next_rdv['patient_name']) ?></p>
                </div>
            </div>
            <div id="countdown-wrap" class="flex items-center gap-2 bg-slate-50 px-4 py-2 rounded-lg border border-slate-100">
                <div class="text-center px-2">
                    <span class="countdown-val text-lg font-bold text-slate-900" id="cd-h">--</span>
                    <span class="text-xs text-slate-400 block">H</span>
                </div>
                <span class="font-bold text-slate-300">:</span>
                <div class="text-center px-2">
                    <span class="countdown-val text-lg font-bold text-slate-900" id="cd-m">--</span>
                    <span class="text-xs text-slate-400 block">Min</span>
                </div>
                <span class="font-bold text-slate-300">:</span>
                <div class="text-center px-2">
                    <span class="countdown-val text-lg font-bold text-slate-900" id="cd-s">--</span>
                    <span class="text-xs text-slate-400 block">Sec</span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- STATS GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="bg-white border border-slate-200 rounded-xl p-6 hover:shadow-md transition-shadow">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Patients actifs</p>
                <p class="font-serif text-4xl font-bold text-indigo-600"><?= $total_patients ?></p>
                <div class="mt-4 text-xs text-slate-500">Total unique</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-6 hover:shadow-md transition-shadow">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Séances du jour</p>
                <p class="font-serif text-4xl font-bold text-slate-900"><?= $rdv_du_jour ?></p>
                <div class="mt-4 w-full bg-slate-100 rounded-full h-1.5">
                    <div class="bg-indigo-600 h-1.5 rounded-full" style="width: <?= $progression ?>%"></div>
                </div>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-6 hover:shadow-md transition-shadow">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Terminées</p>
                <p class="font-serif text-4xl font-bold text-slate-900"><?= $rdv_termines ?></p>
                <div class="mt-4 text-xs text-emerald-600 font-medium"><?= $progression ?>% complété</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl p-6 hover:shadow-md transition-shadow">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Archives</p>
                <p class="font-serif text-4xl font-bold text-slate-900"><?= $total_archives ?></p>
                <a href="consultations.php" class="mt-4 text-xs text-indigo-600 font-medium hover:underline">Voir tout →</a>
            </div>
        </div>

        <!-- TABLEAUX & LISTES -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Liste RDV -->
            <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-bold text-slate-900">Prochains rendez-vous</h3>
                    <a href="agenda.php" class="text-xs text-indigo-600 font-medium hover:underline">Voir l'agenda</a>
                </div>
                <table class="w-full text-left">
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($patients_query->num_rows > 0):
                            while ($row = $patients_query->fetch_assoc()):
                                $ts       = strtotime($row['app_date']);
                                $is_today = date('Y-m-d', $ts) === date('Y-m-d');
                                $archived = (int)$row['archive_count'] > 0;
                                $patient_enc = urlencode($row['patient_name']);
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex flex-col items-center justify-center text-slate-600">
                                        <span class="text-[9px] font-bold uppercase"><?= date('M', $ts) ?></span>
                                        <span class="text-sm font-bold leading-none"><?= date('d', $ts) ?></span>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-slate-800 text-sm"><?= htmlspecialchars($row['patient_name']) ?></p>
                                        <p class="text-xs text-slate-400"><?= date('H:i', $ts) ?> · <?= htmlspecialchars($row['app_type'] ?? 'Consultation') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($archived): ?>
                                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">Archivé</span>
                                <?php else: ?>
                                    <a href="analyse_ia.php?patient_name=<?= $patient_enc ?>&id=<?= $row['id'] ?>"
                                       class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded text-xs font-bold transition-colors">
                                        Démarrer
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td class="p-10 text-center text-slate-400 text-sm">Aucun rendez-vous à venir.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sidebar Droite -->
            <div class="space-y-6">
                <div class="bg-indigo-600 rounded-xl p-6 text-white">
                    <h3 class="font-bold text-lg mb-2">Analyse IA</h3>
                    <p class="text-indigo-100 text-sm mb-4">Recherchez un dossier patient pour générer une analyse sémantique.</p>
                    <a href="patients_search.php" class="block w-full bg-white text-indigo-600 text-center font-bold py-2.5 rounded-lg text-sm hover:bg-indigo-50 transition-colors">
                        Rechercher
                    </a>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-6">
                    <h3 class="font-bold text-slate-900 mb-4 text-sm">Activité</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Dernière séance</span>
                            <span class="font-medium text-slate-700">
                                <?= $last_consult ? date('d/m/y', strtotime($last_consult)) : '-' ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Séances archivées</span>
                            <span class="font-medium text-slate-700"><?= $total_archives ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">Sécurité</span>
                            <span class="font-medium text-emerald-600">Active</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Ajout du nonce pour la sécurité JS -->
<script nonce="<?= $nonce ?>">
// 1. Demander la permission dès le chargement
if (Notification.permission !== "granted") {
    Notification.requestPermission();
}

// 2. Fonction pour jouer le son et afficher la notif
function alertPro(patientName, time) {
    const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3');
    audio.play().catch(e => console.log('Audio bloqué par le navigateur:', e));

    if (Notification.permission === "granted") {
        new Notification("PsySpace : Consultation Imminente", {
            body: "Rendez-vous avec " + patientName + " à " + time,
            icon: "icon-192.png" 
        });
    }
}

// 3. Système de "Check" automatique (Polling)
setInterval(() => {
    fetch('api_check_now.php')
    .then(response => response.json())
    .then(data => {
        if(data.alert === true) {
            alertPro(data.patient, data.time);
        }
    }).catch(e => console.error('Erreur Polling:', e));
}, 60000); 

<?php if($next_rdv): ?>
var targetTs = <?= strtotime($next_rdv['app_date']) ?> * 1000;
function updateCountdown() {
  var now  = Date.now();
  var diff = Math.floor((targetTs - now) / 1000);
  if (diff <= 0) {
    document.getElementById('cd-h').textContent = '00';
    document.getElementById('cd-m').textContent = '00';
    document.getElementById('cd-s').textContent = '00';
    return;
  }
  var h = Math.floor(diff / 3600);
  var m = Math.floor((diff % 3600) / 60);
  var s = diff % 60;
  document.getElementById('cd-h').textContent = String(h).padStart(2,'0');
  document.getElementById('cd-m').textContent = String(m).padStart(2,'0');
  document.getElementById('cd-s').textContent = String(s).padStart(2,'0');
}
updateCountdown();
setInterval(updateCountdown, 1000);
<?php endif; ?>
</script>

</body>
</html>