<?php
// --- 1. SÉCURITÉ DES SESSIONS & HEADERS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}

session_start();

$nonce = base64_encode(random_bytes(16));
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' 'nonce-{$nonce}' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none';");

include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

// --- 2. FONCTION DE GÉNÉRATION DU CODE CABINET ---
function getCabinetCode($docid) {
    return strtoupper(substr(md5($docid . "PsySpaceCabinet2026"), 0, 10));
}

// --- 3. DÉCONNEXION DE L'ASSISTANTE ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: assistante.php");
    exit();
}

// --- 4. TRAITEMENT DU LOGIN ASSISTANTE ---
if (!isset($_SESSION['sec_login_attempts'])) { $_SESSION['sec_login_attempts'] = 0; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cabinet_code'])) {
    if ($_SESSION['sec_login_attempts'] >= 5) {
        $login_error = "Trop de tentatives. Veuillez patienter.";
    } else {
        $input_code = strtoupper(trim($_POST['cabinet_code']));
        $res_docs = $conn->query("SELECT docid, docname FROM doctor WHERE status = 'active'");
        $found = false;
        
        while ($doc = $res_docs->fetch_assoc()) {
            if (getCabinetCode($doc['docid']) === $input_code) {
                session_regenerate_id(true); 
                $_SESSION['sec_doc_id'] = $doc['docid'];
                $_SESSION['sec_doc_name'] = $doc['docname'];
                $_SESSION['csrf_sec_token'] = bin2hex(random_bytes(32)); 
                $_SESSION['sec_login_attempts'] = 0;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $_SESSION['sec_login_attempts']++;
            $login_error = "Code d'accès invalide.";
        }
    }
}

// =========================================================================================
// 🔒 ÉCRAN DE VERROUILLAGE (SI NON CONNECTÉE)
// =========================================================================================
if (!isset($_SESSION['sec_doc_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr" class="dark:bg-slate-950">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Accès Secrétariat | PsySpace</title>
        <script src="https://cdn.tailwindcss.com" nonce="<?= $nonce ?>"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style nonce="<?= $nonce ?>">body { font-family: 'Inter', sans-serif; }</style>
    </head>
    <body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
        <div class="bg-white max-w-md w-full rounded-3xl shadow-xl border border-slate-200 p-8 text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-2 bg-indigo-600"></div>
            <div class="w-20 h-20 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 shadow-inner">👩‍💼</div>
            <h2 class="text-2xl font-bold text-slate-800 mb-2">Espace Accueil</h2>
            <p class="text-slate-500 mb-8 text-sm">Entrez le code sécurisé à 10 caractères du cabinet.</p>
            
            <?php if (isset($login_error)): ?>
                <div class="text-red-600 text-sm font-bold bg-red-50 p-3 rounded-xl mb-6 animate-pulse"><?= $login_error ?></div>
            <?php endif; ?>

            <form method="POST" action="assistante.php">
                <input type="text" name="cabinet_code" required placeholder="XXXXXXXXXX" maxlength="10"
                       class="w-full border-2 border-slate-200 rounded-xl p-4 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-50 outline-none text-center tracking-[0.4em] font-bold uppercase mb-6 text-xl transition-all" autocomplete="off">
                <button type="submit" class="w-full bg-slate-900 hover:bg-indigo-600 text-white font-bold py-4 rounded-xl transition-colors shadow-md text-lg">
                    Déverrouiller l'Agenda
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// =========================================================================================
// 📅 ESPACE ASSISTANTE (CONNECTÉE)
// =========================================================================================
$doc_id = (int)$_SESSION['sec_doc_id'];
$doc_name = $_SESSION['sec_doc_name'];

// --- TRAITEMENT CRUD : AJOUTER / MODIFIER ---
if(isset($_POST['save_appointment'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_sec_token'], $_POST['csrf_token'])) {
        die("Erreur CSRF.");
    }

    $edit_id = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $p_name  = mysqli_real_escape_string($conn, trim($_POST['p_name']));
    $p_phone = mysqli_real_escape_string($conn, trim($_POST['p_phone']));
    $p_date  = $_POST['p_date']; 
    $app_datetime = date('Y-m-d H:i:s', strtotime($p_date));
    
    // Patient
    $check_p = $conn->query("SELECT id FROM patients WHERE pphone = '$p_phone' LIMIT 1");
    if($check_p->num_rows > 0) { $final_patient_id = $check_p->fetch_assoc()['id']; } 
    else {
        $conn->query("INSERT INTO patients (pname, pphone) VALUES ('$p_name', '$p_phone')");
        $final_patient_id = $conn->insert_id;
    }
    
    if ($edit_id > 0) {
        $conn->query("UPDATE appointments SET patient_id='$final_patient_id', patient_name='$p_name', patient_phone='$p_phone', app_date='$app_datetime' WHERE id='$edit_id' AND doctor_id='$doc_id'");
        header("Location: assistante.php?success=edit"); exit();
    } else {
        $conn->query("INSERT INTO appointments (doctor_id, patient_id, patient_name, patient_phone, app_date, app_type) VALUES ('$doc_id','$final_patient_id','$p_name','$p_phone','$app_datetime', 'Consultation')");
        header("Location: assistante.php?success=add"); exit();
    }
}

// --- TRAITEMENT CRUD : SUPPRIMER ---
if (isset($_POST['delete_app'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_sec_token'], $_POST['csrf_token'])) { die("Erreur CSRF."); }
    $app_id = (int)$_POST['app_id'];
    $conn->query("DELETE FROM appointments WHERE id='$app_id' AND doctor_id='$doc_id'");
    header("Location: assistante.php?success=delete"); exit();
}

// --- RÉCUPÉRATION DES DONNÉES DE L'AGENDA ---
$sql = "SELECT * FROM appointments WHERE doctor_id = '$doc_id' AND app_date >= CURDATE() ORDER BY app_date ASC";
$query = $conn->query($sql);
$appointments = [];
while($row = $query->fetch_assoc()) $appointments[] = $row;

// Construction du tableau pour le JS
$booked = [];
foreach($appointments as $app) {
    $dk = date('Y-m-d', strtotime($app['app_date']));
    $tk = date('H:i',   strtotime($app['app_date']));
    if(!isset($booked[$dk])) $booked[$dk] = [];
    $booked[$dk][] = [
        'id' => $app['id'],
        'time' => $tk,
        'patient' => $app['patient_name'],
        'phone' => $app['patient_phone'],
        'archived' => false // L'assistante ne gère pas les archives
    ];
}

$today = date('Y-m-d');
$total = count($appointments);
$today_c = count(array_filter($appointments, fn($a)=>date('Y-m-d',strtotime($a['app_date']))===$today));
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secrétariat | PsySpace</title>
    
    <script src="https://cdn.tailwindcss.com" nonce="<?= $nonce ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <script nonce="<?= $nonce ?>">
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } };
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style nonce="<?= $nonce ?>">
        body { font-family: 'Inter', sans-serif; }
        .fadein { animation: fadeUp 0.3s ease forwards; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scroll::-webkit-scrollbar-thumb { background: #475569; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-700 dark:text-slate-300 transition-colors duration-300">

<div class="flex min-h-screen relative">

    <!-- SIDEBAR SIMPLIFIÉE ASSISTANTE -->
    <aside id="sidebar" class="w-64 bg-slate-900 dark:bg-slate-900 border-r border-slate-800 flex flex-col fixed h-full z-50 transition-transform transform -translate-x-full lg:translate-x-0">
        <div class="p-6 border-b border-slate-800 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-indigo-500 text-white rounded-lg flex items-center justify-center font-bold">PS</div>
                <span class="text-lg font-bold text-white">PsySpace</span>
            </div>
            <button id="close-sidebar" class="lg:hidden text-slate-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6 border-b border-slate-800">
            <p class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-2">Espace Secrétariat</p>
            <p class="text-white font-semibold">Cabinet du Dr. <?= htmlspecialchars($doc_name) ?></p>
        </div>
        <nav class="flex-1 p-4 space-y-1">
            <a href="#" class="bg-indigo-600/20 text-indigo-400 flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Agenda du Cabinet
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
             <a href="assistante.php?logout=1" class="flex items-center gap-2 text-slate-400 hover:text-red-400 text-sm font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Verrouiller le poste
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 lg:ml-64 p-4 md:p-8 w-full">
        
        <!-- Header -->
        <div class="flex flex-wrap justify-between items-center mb-8 gap-4">
            <div class="flex items-center gap-4">
                <button id="open-sidebar" class="lg:hidden p-2 text-slate-500 bg-white dark:bg-slate-800 rounded-md border border-slate-200 dark:border-slate-700 shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Agenda</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Gestion des appels et réservations.</p>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <button id="theme-toggle" class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-all border border-transparent dark:border-slate-700">
                    <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                    <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 011.414-1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1z"></path></svg>
                </button>
                <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg font-medium text-sm transition shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    <span class="hidden sm:inline">Nouveau RDV</span>
                </button>
            </div>
        </div>

        <!-- Alertes de Succès -->
        <?php if(isset($_GET['success'])): ?>
            <?php 
                $msg = "Opération réussie.";
                if($_GET['success'] == 'add') $msg = "Nouveau rendez-vous ajouté à l'agenda.";
                if($_GET['success'] == 'edit') $msg = "Rendez-vous modifié avec succès.";
                if($_GET['success'] == 'delete') $msg = "Rendez-vous annulé et supprimé.";
            ?>
            <div class="mb-6 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 px-4 py-3 rounded-lg flex items-center justify-between text-sm font-medium fadein">
                <div class="flex items-center gap-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg><?= $msg ?></div>
                <a href="assistante.php" class="text-emerald-500 hover:text-emerald-700">&times;</a>
            </div>
        <?php endif; ?>

        <!-- Layout principal -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- GAUCHE : Calendrier -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Mini Calendrier JS -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm transition-colors">
                    <div class="flex items-center justify-between mb-4">
                        <button onclick="changeMonth(-1)" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
                        <h3 id="cal-title" class="font-bold text-slate-800 dark:text-slate-200 text-sm uppercase tracking-wide"></h3>
                        <button onclick="changeMonth(1)" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center mb-2">
                        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?>
                        <div class="text-[10px] font-bold text-slate-400 uppercase"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="cal-grid" class="grid grid-cols-7 gap-1"></div>
                </div>

                <!-- Panneau Jour Sélectionné -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm transition-colors">
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                        <h4 id="dpanel-title" class="font-bold text-slate-800 dark:text-slate-200 text-sm">Sélectionnez un jour</h4>
                    </div>
                    <div id="dpanel-body" class="p-5">
                        <div class="text-center py-6 text-slate-400"><p class="text-xs font-medium">Aucun jour sélectionné</p></div>
                    </div>
                </div>
            </div>

            <!-- DROITE : Liste RDV globaux -->
            <div class="lg:col-span-8">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm transition-colors">
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                        <h3 class="font-bold text-slate-800 dark:text-slate-200 text-sm">Prochains rendez-vous (Cabinet)</h3>
                        <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 px-2.5 py-1 rounded-full"><?= $total ?> prévus</span>
                    </div>
                    
                    <?php if(!empty($appointments)): ?>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800 max-h-[600px] overflow-y-auto custom-scroll">
                        <?php foreach($appointments as $row):
                            $ts = strtotime($row['app_date']);
                            $id = $row['id'];
                            $pname = htmlspecialchars($row['patient_name'], ENT_QUOTES);
                            $pphone = htmlspecialchars($row['patient_phone'], ENT_QUOTES);
                            $date_raw = date('Y-m-d', $ts);
                            $time_raw = date('H:i', $ts);
                        ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors gap-4">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col items-center justify-center bg-slate-50 dark:bg-slate-800 shrink-0 shadow-sm">
                                    <span class="text-[10px] font-bold uppercase text-slate-400"><?= date('M', $ts) ?></span>
                                    <span class="text-lg font-bold leading-tight text-slate-700 dark:text-slate-200"><?= date('d', $ts) ?></span>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800 dark:text-slate-200 text-sm"><?= $pname ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> <?= $time_raw ?>
                                        <span class="text-slate-300 dark:text-slate-600">|</span> 📞 <?= $pphone ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- BOUTONS ACTIONS (MODIFIER / ANNULER) -->
                            <div class="flex items-center gap-2">
                                <button onclick="openEditModal(<?= $id ?>, '<?= $pname ?>', '<?= $pphone ?>', '<?= $date_raw ?>', '<?= $time_raw ?>')" class="px-3 py-1.5 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg font-semibold text-xs hover:bg-blue-100 dark:hover:bg-blue-900/50 transition flex items-center gap-1 border border-blue-100 dark:border-blue-800">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    Modifier
                                </button>
                                
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Confirmer l\'annulation du rendez-vous de <?= $pname ?> ?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_sec_token'] ?>">
                                    <input type="hidden" name="delete_app" value="1">
                                    <input type="hidden" name="app_id" value="<?= $id ?>">
                                    <button type="submit" class="px-3 py-1.5 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg font-semibold text-xs hover:bg-red-100 dark:hover:bg-red-900/50 transition flex items-center gap-1 border border-red-100 dark:border-red-800">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        Annuler
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="p-12 text-center text-slate-500 dark:text-slate-400 text-sm">Agenda vide.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- MODAL AJOUT / MODIFICATION RDV -->
<div id="modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden" onclick="if(event.target===this)closeModal()">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden border border-transparent dark:border-slate-700 transition-colors" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
            <h3 id="modal-title-text" class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Planifier un rendez-vous
            </h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition-colors text-xl leading-none">&times;</button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2">
            <!-- Formulaire -->
            <div class="p-6 border-b md:border-b-0 md:border-r border-slate-100 dark:border-slate-800">
                <form action="assistante.php" method="POST" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_sec_token'] ?>">
                    <input type="hidden" name="save_appointment" value="1">
                    <input type="hidden" name="edit_id" id="edit_id_input" value="0">
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1.5">Patient</label>
                        <input type="text" name="p_name" id="input_pname" required placeholder="Nom complet" class="w-full bg-transparent border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1.5">Téléphone</label>
                        <input type="tel" name="p_phone" id="input_pphone" required placeholder="Numéro (ex: 06 12 34 56 78)" class="w-full bg-transparent border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none dark:text-white transition-colors">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1.5">Date</label>
                            <div id="sel-date-txt" class="w-full border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2.5 text-xs text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-slate-800/50 truncate">Sélectionner →</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-1.5">Heure</label>
                            <div id="sel-time-txt" class="w-full border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2.5 text-xs text-slate-400 dark:text-slate-500 bg-slate-50 dark:bg-slate-800/50 truncate">Sélectionner →</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="p_date" id="p_date_hidden">
                    
                    <div class="pt-4">
                        <button type="submit" id="modal-submit" disabled
                            class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 dark:disabled:bg-slate-800 disabled:text-slate-400 dark:disabled:text-slate-500 disabled:cursor-not-allowed text-white py-3 rounded-lg font-bold text-sm transition-all shadow-sm">
                            Confirmer
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Sélection Date/Heure -->
            <div class="p-6 bg-slate-50 dark:bg-slate-800/20 space-y-6">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <button type="button" onclick="changeModalMonth(-1)" class="p-1 rounded-md hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
                        <span id="modal-cal-title" class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide"></span>
                        <button type="button" onclick="changeModalMonth(1)" class="p-1 rounded-md hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center mb-1.5">
                        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?>
                        <div class="text-[9px] font-bold text-slate-400"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="modal-cal-grid" class="grid grid-cols-7 gap-1"></div>
                </div>
                
                <div>
                    <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase mb-3 flex items-center gap-2">Créneaux</p>
                    <div id="slots-grid" class="grid grid-cols-4 gap-2 max-h-32 overflow-y-auto custom-scroll pr-1">
                        <div class="col-span-4 text-center text-[10px] text-slate-400 py-6 italic border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">Choisir une date</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $nonce ?>">
// Dark mode toggle
const themeToggleBtn = document.getElementById('theme-toggle');
const darkIcon = document.getElementById('theme-toggle-dark-icon');
const lightIcon = document.getElementById('theme-toggle-light-icon');
if (document.documentElement.classList.contains('dark')) { lightIcon.classList.remove('hidden'); } 
else { darkIcon.classList.remove('hidden'); }
themeToggleBtn.addEventListener('click', function() {
    darkIcon.classList.toggle('hidden'); lightIcon.classList.toggle('hidden');
    if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark'); localStorage.setItem('color-theme', 'light');
    } else {
        document.documentElement.classList.add('dark'); localStorage.setItem('color-theme', 'dark');
    }
});

// Sidebar Mobile
document.getElementById('open-sidebar').addEventListener('click', ()=>{document.getElementById('sidebar').classList.remove('-translate-x-full');});
document.getElementById('close-sidebar').addEventListener('click', ()=>{document.getElementById('sidebar').classList.add('-translate-x-full');});

// LOGIQUE CALENDRIER
const BOOKED = <?php echo json_encode($booked); ?>;
const TODAY_STR = '<?php echo $today; ?>';
const MFR = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const DFR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
let mainM={y:new Date().getFullYear(),m:new Date().getMonth()};
let modalM={y:new Date().getFullYear(),m:new Date().getMonth()};
let selDay=null, selDate=null, selTime=null;
let currentEditId = 0; // Si != 0, on modifie

function pz(n){return String(n).padStart(2,'0');}
function ymd(y,m,d){return `${y}-${pz(m+1)}-${pz(d)}`;}
function bookedFor(ds){return BOOKED[ds]||[];}
function isPast(ds,t){return new Date(`${ds}T${t}:00`)<new Date();}
function fmtDate(ds){const d=new Date(ds+'T12:00:00');return `${DFR[(d.getDay()+6)%7]} ${d.getDate()} ${MFR[d.getMonth()]}`;}

// Mini Cal Principal
function renderMainCal(){
    const {y,m}=mainM; document.getElementById('cal-title').textContent=`${MFR[m]} ${y}`;
    const g=document.getElementById('cal-grid'); g.innerHTML='';
    const fd=(new Date(y,m,1).getDay()+6)%7, dim=new Date(y,m+1,0).getDate();
    for(let i=0;i<fd;i++) g.innerHTML+=`<div></div>`;
    for(let d=1;d<=dim;d++){
        const ds=ymd(y,m,d), bk=bookedFor(ds), past=ds<TODAY_STR, tod=ds===TODAY_STR, sel=ds===selDay;
        let cls=`w-full aspect-square rounded-lg flex flex-col items-center justify-center cursor-pointer text-xs font-medium relative transition-all `;
        if(past) cls+=' text-slate-300 dark:text-slate-600 ';
        else if(sel) cls+=' bg-indigo-600 text-white shadow-md z-10 scale-105';
        else if(tod) cls+=' bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 font-bold border border-indigo-200 dark:border-indigo-800/50';
        else cls+=' hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300';
        let dots = bk.length ? `<span class="absolute bottom-1 flex gap-0.5">${bk.slice(0,3).map(b=>`<span class="w-1 h-1 rounded-full bg-amber-500"></span>`).join('')}</span>` : '';
        g.innerHTML+=`<div class="${cls}" onclick="clickDay('${ds}')">${d}${dots}</div>`;
    }
}
function clickDay(ds){selDay=ds;renderMainCal();renderDayPanel(ds);}
function changeMonth(dir){mainM.m+=dir;if(mainM.m<0){mainM.m=11;mainM.y--;}if(mainM.m>11){mainM.m=0;mainM.y++;}renderMainCal();}

// Panneau Jour
function renderDayPanel(ds){
    const bk=bookedFor(ds); document.getElementById('dpanel-title').textContent=fmtDate(ds);
    if(bk.length===0){
        document.getElementById('dpanel-body').innerHTML=`<div class="text-center py-6 text-sm text-slate-500"><button onclick="openModal('${ds}')" class="text-indigo-600 font-bold hover:underline">+ Planifier</button></div>`;
        return;
    }
    let html='<div class="space-y-2">';
    bk.forEach(b=>{
        html+=`<div class="flex justify-between items-center p-3 rounded-lg border bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700">
            <div><p class="text-sm font-bold text-slate-800 dark:text-slate-200">${b.patient}</p><p class="text-xs text-slate-500 flex gap-1 mt-0.5">⏱️ ${b.time}</p></div>
        </div>`;
    });
    html+=`<button onclick="openModal('${ds}')" class="w-full mt-3 py-2 rounded-lg border border-dashed border-indigo-300 text-indigo-600 text-xs font-bold hover:bg-indigo-50">+ Ajouter un créneau</button></div>`;
    document.getElementById('dpanel-body').innerHTML=html;
}

// Modal Logique (Ajout / Modif)
function openModal(prefill){
    currentEditId = 0; document.getElementById('edit_id_input').value = '0';
    document.getElementById('modal-title-text').innerHTML = 'Planifier un rendez-vous';
    document.getElementById('input_pname').value = ''; document.getElementById('input_pphone').value = '';
    document.getElementById('modal').classList.remove('hidden');
    if(prefill){selDate=prefill; const d=new Date(prefill+'T12:00:00'); modalM.y=d.getFullYear(); modalM.m=d.getMonth(); renderModalCal(); renderSlots(prefill); updateDisplay();}
    else {selDate=null;selTime=null;renderModalCal(); updateDisplay();}
}
function openEditModal(id, name, phone, date, time) {
    currentEditId = id; document.getElementById('edit_id_input').value = id;
    document.getElementById('modal-title-text').innerHTML = '✏️ Modifier le rendez-vous';
    document.getElementById('input_pname').value = name; document.getElementById('input_pphone').value = phone;
    document.getElementById('modal').classList.remove('hidden');
    selDate = date; selTime = time;
    const d=new Date(date+'T12:00:00'); modalM.y=d.getFullYear(); modalM.m=d.getMonth();
    renderModalCal(); renderSlots(date); updateDisplay();
}
function closeModal(){document.getElementById('modal').classList.add('hidden');}

// Calendrier Modal
function renderModalCal(){
    const {y,m}=modalM; document.getElementById('modal-cal-title').textContent=`${MFR[m]} ${y}`;
    const g=document.getElementById('modal-cal-grid'); g.innerHTML='';
    const fd=(new Date(y,m,1).getDay()+6)%7, dim=new Date(y,m+1,0).getDate();
    for(let i=0;i<fd;i++) g.innerHTML+=`<div></div>`;
    for(let d=1;d<=dim;d++){
        const ds=ymd(y,m,d), past=ds<TODAY_STR, sel=ds===selDate;
        let cls=`w-full aspect-square rounded-md flex items-center justify-center cursor-pointer text-[11px] font-medium transition-colors `;
        if(past) cls+=' text-slate-300 dark:text-slate-600 ';
        else if(sel) cls+=' bg-indigo-600 text-white ';
        else cls+=' hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 ';
        if(!past) g.innerHTML+=`<div class="${cls}" onclick="selectModalDate('${ds}')">${d}</div>`;
        else g.innerHTML+=`<div class="${cls}">${d}</div>`;
    }
}
function selectModalDate(ds){selDate=ds;selTime=null;renderModalCal();renderSlots(ds);updateDisplay();}
function changeModalMonth(dir){modalM.m+=dir;if(modalM.m<0){modalM.m=11;modalM.y--;}if(modalM.m>11){modalM.m=0;modalM.y++;}renderModalCal();}

// Créneaux Modal
function renderSlots(ds){
    const g=document.getElementById('slots-grid'), s=[];
    for(let h=8;h<19;h++)for(let mn of[0,30]){if(h===18&&mn===30)break;s.push(`${pz(h)}:${pz(mn)}`);}
    const bk = bookedFor(ds).filter(b => b.id != currentEditId).map(b=>b.time); // On ignore l'heure actuelle si on modifie
    let html='';
    s.forEach(t=>{
        const isB=bk.includes(t), past=isPast(ds,t), sel=t===selTime;
        let cls='px-1 py-2 rounded-md text-center text-[11px] font-bold cursor-pointer border transition-colors ';
        if(isB) cls+='bg-slate-100 text-slate-400 line-through';
        else if(past&&ds===TODAY_STR) cls+='bg-slate-50 text-slate-300';
        else if(sel) cls+=' bg-indigo-600 text-white border-indigo-600';
        else cls+=' bg-white text-slate-700 border-slate-200 hover:border-indigo-400';
        if(!isB && !(past&&ds===TODAY_STR)) html+=`<div class="${cls}" onclick="pickTime('${t}')">${t}</div>`;
        else html+=`<div class="${cls}">${t}</div>`;
    });
    g.innerHTML=html;
}
function pickTime(t){selTime=t;renderSlots(selDate);updateDisplay();}
function updateDisplay(){
    const dEl=document.getElementById('sel-date-txt'),tEl=document.getElementById('sel-time-txt'),btn=document.getElementById('modal-submit'),hid=document.getElementById('p_date_hidden');
    if(selDate){dEl.textContent=fmtDate(selDate);dEl.classList.add('text-slate-900','font-bold');}else{dEl.textContent='Sélectionner →';}
    if(selTime){tEl.textContent=selTime;tEl.classList.add('text-slate-900','font-bold');}else{tEl.textContent='Sélectionner →';}
    if(selDate&&selTime){hid.value=`${selDate}T${selTime}`;btn.disabled=false;}else btn.disabled=true;
}

renderMainCal();
if(BOOKED[TODAY_STR])clickDay(TODAY_STR);
</script>
</body>
</html>