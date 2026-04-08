<?php
// --- 1. SÉCURITÉ DES SESSIONS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();

$nonce = base64_encode(random_bytes(16));
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

// --- 2. FONCTION CODE CABINET ---
function getCabinetCode($docid) {
    return strtoupper(substr(md5($docid . "PsySpaceCabinet2026"), 0, 10));
}

// --- 3. DÉCONNEXION ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: assistante.php");
    exit();
}

// --- 4. TRAITEMENT LOGIN ASSISTANTE ---
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

// ==========================================
// 🔒 ÉCRAN DE VERROUILLAGE 
// ==========================================
if (!isset($_SESSION['sec_doc_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr" class="dark:bg-slate-950">
    <head>
        <link rel="icon" type="image/png" href="assets/images/logo.png">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Accès Secrétariat | PsySpace</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>body { font-family: 'Inter', sans-serif; }</style>
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

// ==========================================
// 📅 ESPACE ASSISTANTE CONNECTÉE
// ==========================================
$doc_id = (int)$_SESSION['sec_doc_id'];
$doc_name = $_SESSION['sec_doc_name'];

// --- CRUD : AJOUTER / MODIFIER ---
if(isset($_POST['save_appointment'])) {
    $edit_id = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $p_name  = mysqli_real_escape_string($conn, trim($_POST['p_name']));
    $p_phone = mysqli_real_escape_string($conn, trim($_POST['p_phone']));
    $p_date  = $_POST['p_date']; // Format YYYY-MM-DDTHH:MM
    $app_datetime = date('Y-m-d H:i:s', strtotime($p_date));
    
    // Check ou création patient
    $check_p = $conn->query("SELECT id FROM patients WHERE pphone = '$p_phone' LIMIT 1");
    if($check_p->num_rows > 0) { 
        $final_patient_id = $check_p->fetch_assoc()['id']; 
    } else {
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

// --- CRUD : SUPPRIMER ---
if (isset($_POST['delete_app'])) {
    $app_id = (int)$_POST['app_id'];
    $conn->query("DELETE FROM appointments WHERE id='$app_id' AND doctor_id='$doc_id'");
    header("Location: assistante.php?success=delete"); exit();
}

// --- RÉCUPÉRATION AGENDA ---
$sql = "SELECT * FROM appointments WHERE doctor_id = '$doc_id' AND app_date >= CURDATE() ORDER BY app_date ASC";
$query = $conn->query($sql);
$appointments = [];
while($row = $query->fetch_assoc()) $appointments[] = $row;

$booked = [];
foreach($appointments as $app) {
    $dk = date('Y-m-d', strtotime($app['app_date']));
    $tk = date('H:i',   strtotime($app['app_date']));
    if(!isset($booked[$dk])) $booked[$dk] = [];
    $booked[$dk][] = [
        'id' => $app['id'],
        'time' => $tk,
        'patient' => $app['patient_name'],
        'phone' => $app['patient_phone']
    ];
}

$today = date('Y-m-d');
$total = count($appointments);
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secrétariat | PsySpace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } };
    </script>
    <style>
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

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-slate-900 dark:bg-slate-900 border-r border-slate-800 flex flex-col fixed h-full z-50 transition-transform transform -translate-x-full lg:translate-x-0">
        <div class="p-6 border-b border-slate-800 flex items-center gap-3">
            <div class="w-8 h-8 bg-indigo-500 text-white rounded-lg flex items-center justify-center font-bold">PS</div>
            <span class="text-lg font-bold text-white">PsySpace</span>
        </div>
        <div class="p-6 border-b border-slate-800">
            <p class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-2">Espace Secrétariat</p>
            <p class="text-white font-semibold">Dr. <?= htmlspecialchars($doc_name) ?></p>
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
        
        <!-- HEADER -->
        <div class="flex flex-wrap justify-between items-center mb-8 gap-4">
            <div class="flex items-center gap-4">
                <button id="open-sidebar" class="lg:hidden p-2 text-slate-500 bg-white rounded-md border border-slate-200 shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Agenda</h1>
                    <p class="text-slate-500 text-sm mt-1">Gestion des réservations.</p>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg font-medium text-sm transition shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Nouveau RDV
                </button>
            </div>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if(isset($_GET['success'])): ?>
            <?php 
                $msg = "Opération réussie.";
                if($_GET['success'] == 'add') $msg = "Nouveau rendez-vous ajouté à l'agenda.";
                if($_GET['success'] == 'edit') $msg = "Rendez-vous modifié avec succès.";
                if($_GET['success'] == 'delete') $msg = "Rendez-vous annulé.";
            ?>
            <div class="mb-6 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex justify-between text-sm font-medium fadein">
                <div class="flex items-center gap-2">✅ <?= $msg ?></div>
                <a href="assistante.php" class="text-emerald-500 hover:text-emerald-700">&times;</a>
            </div>
        <?php endif; ?>

        <!-- LAYOUT DE LA PAGE -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- GAUCHE : Calendrier -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <button onclick="changeMonth(-1)" class="p-1.5 rounded hover:bg-slate-100 text-slate-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
                        <h3 id="cal-title" class="font-bold text-slate-800 dark:text-slate-200 text-sm uppercase tracking-wide"></h3>
                        <button onclick="changeMonth(1)" class="p-1.5 rounded hover:bg-slate-100 text-slate-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center mb-2">
                        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?>
                        <div class="text-[10px] font-bold text-slate-400 uppercase"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="cal-grid" class="grid grid-cols-7 gap-1"></div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                        <h4 id="dpanel-title" class="font-bold text-slate-800 dark:text-slate-200 text-sm">Sélectionnez un jour</h4>
                    </div>
                    <div id="dpanel-body" class="p-5">
                        <div class="text-center py-6 text-slate-400 text-xs font-medium">Aucun jour sélectionné</div>
                    </div>
                </div>
            </div>

            <!-- DROITE : Liste des RDV et Actions -->
            <div class="lg:col-span-8">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                        <h3 class="font-bold text-slate-800 dark:text-slate-200 text-sm">Prochains rendez-vous</h3>
                        <span class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full"><?= $total ?> prévus</span>
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
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between px-5 py-4 hover:bg-slate-50 transition-colors gap-4">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl border border-slate-200 flex flex-col items-center justify-center bg-slate-50 shrink-0 shadow-sm">
                                    <span class="text-[10px] font-bold uppercase text-slate-400"><?= date('M', $ts) ?></span>
                                    <span class="text-lg font-bold leading-tight text-slate-700"><?= date('d', $ts) ?></span>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800 dark:text-slate-200 text-sm"><?= $pname ?></p>
                                    <p class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                                        ⏱️ <?= $time_raw ?> <span class="text-slate-300">|</span> 📞 <?= $pphone ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- BOUTONS D'ACTION (MODIFIER / ANNULER) -->
                            <div class="flex items-center gap-2">
                                <button onclick="openEditModal(<?= $id ?>, '<?= addslashes($pname) ?>', '<?= addslashes($pphone) ?>', '<?= $date_raw ?>', '<?= $time_raw ?>')" 
                                        class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg font-semibold text-xs hover:bg-blue-100 transition flex items-center gap-1 border border-blue-100">
                                    ✏️ Modifier
                                </button>
                                
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Annuler le rendez-vous de <?= addslashes($pname) ?> ?');">
                                    <input type="hidden" name="delete_app" value="1">
                                    <input type="hidden" name="app_id" value="<?= $id ?>">
                                    <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg font-semibold text-xs hover:bg-red-100 transition flex items-center gap-1 border border-red-100">
                                        🗑️ Annuler
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="p-12 text-center text-slate-500 text-sm">Agenda vide.</div>
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
                📅 Planifier un rendez-vous
            </h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2">
            <!-- Formulaire -->
            <div class="p-6 border-b md:border-b-0 md:border-r border-slate-100 dark:border-slate-800">
                <form action="assistante.php" method="POST" class="space-y-5">
                    <input type="hidden" name="save_appointment" value="1">
                    <input type="hidden" name="edit_id" id="edit_id_input" value="0">
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Patient</label>
                        <input type="text" name="p_name" id="input_pname" required placeholder="Nom complet" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Téléphone</label>
                        <input type="tel" name="p_phone" id="input_pphone" required placeholder="Numéro (ex: 06 12 34 56 78)" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Date</label>
                            <div id="sel-date-txt" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-xs text-slate-400 bg-slate-50 truncate">Sélectionner →</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Heure</label>
                            <div id="sel-time-txt" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-xs text-slate-400 bg-slate-50 truncate">Sélectionner →</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="p_date" id="p_date_hidden">
                    
                    <div class="pt-4">
                        <button type="submit" id="modal-submit" disabled
                            class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white py-3 rounded-lg font-bold text-sm transition-all shadow-sm">
                            Confirmer
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Sélection Date/Heure pour le Modal -->
            <div class="p-6 bg-slate-50 space-y-6">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <button type="button" onclick="changeModalMonth(-1)" class="p-1 rounded-md hover:bg-slate-200 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
                        <span id="modal-cal-title" class="text-xs font-bold text-slate-700 uppercase tracking-wide"></span>
                        <button type="button" onclick="changeModalMonth(1)" class="p-1 rounded-md hover:bg-slate-200 text-slate-500"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center mb-1.5">
                        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?>
                        <div class="text-[9px] font-bold text-slate-400"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="modal-cal-grid" class="grid grid-cols-7 gap-1"></div>
                </div>
                
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase mb-3 flex items-center gap-2">Créneaux</p>
                    <div id="slots-grid" class="grid grid-cols-4 gap-2 max-h-32 overflow-y-auto custom-scroll pr-1">
                        <div class="col-span-4 text-center text-[10px] text-slate-400 py-6 italic border border-dashed border-slate-200 rounded-lg">Choisir une date au-dessus</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// --- LOGIQUE JAVASCRIPT AGENDA ---
const BOOKED = <?php echo json_encode($booked); ?>;
const TODAY_STR = '<?php echo $today; ?>';
const MFR = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const DFR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];

let mainM = {y: new Date().getFullYear(), m: new Date().getMonth()};
let modalM = {y: new Date().getFullYear(), m: new Date().getMonth()};
let selDay = null, selDate = null, selTime = null, currentEditId = 0;

function pz(n){return String(n).padStart(2,'0');}
function ymd(y,m,d){return `${y}-${pz(m+1)}-${pz(d)}`;}
function bookedFor(ds){return BOOKED[ds] || [];}
function isPast(ds,t){return new Date(`${ds}T${t}:00`) < new Date();}
function fmtDate(ds){const d=new Date(ds+'T12:00:00');return `${DFR[(d.getDay()+6)%7]} ${d.getDate()} ${MFR[d.getMonth()]}`;}

// -- MINI CALENDRIER GAUCHE --
function renderMainCal(){
    const {y,m} = mainM; document.getElementById('cal-title').textContent = `${MFR[m]} ${y}`;
    const g = document.getElementById('cal-grid'); g.innerHTML = '';
    const fd = (new Date(y,m,1).getDay()+6)%7, dim = new Date(y,m+1,0).getDate();
    
    for(let i=0;i<fd;i++) g.innerHTML += `<div></div>`;
    
    for(let d=1;d<=dim;d++){
        const ds=ymd(y,m,d), bk=bookedFor(ds), past=ds<TODAY_STR, tod=ds===TODAY_STR, sel=ds===selDay;
        let cls=`w-full aspect-square rounded-lg flex flex-col items-center justify-center cursor-pointer text-xs font-medium relative transition-all `;
        
        if(past) cls+=' text-slate-300 ';
        else if(sel) cls+=' bg-indigo-600 text-white shadow-md scale-105 z-10';
        else if(tod) cls+=' bg-indigo-50 text-indigo-700 font-bold border border-indigo-200';
        else cls+=' hover:bg-slate-100 text-slate-700';
        
        let dots = bk.length ? `<span class="absolute bottom-1 flex gap-0.5">${bk.slice(0,3).map(b=>`<span class="w-1 h-1 rounded-full bg-amber-500"></span>`).join('')}</span>` : '';
        g.innerHTML += `<div class="${cls}" onclick="clickDay('${ds}')">${d}${dots}</div>`;
    }
}
function clickDay(ds){ selDay=ds; renderMainCal(); renderDayPanel(ds); }
function changeMonth(dir){ mainM.m+=dir; if(mainM.m<0){mainM.m=11;mainM.y--;} if(mainM.m>11){mainM.m=0;mainM.y++;} renderMainCal(); }

function renderDayPanel(ds){
    const bk = bookedFor(ds); 
    document.getElementById('dpanel-title').textContent = fmtDate(ds);
    if(bk.length === 0){
        document.getElementById('dpanel-body').innerHTML = `<div class="text-center py-6"><button onclick="openModal('${ds}')" class="text-indigo-600 font-bold hover:underline text-sm">+ Planifier</button></div>`;
        return;
    }
    let html='<div class="space-y-2">';
    bk.forEach(b=>{
        html+=`<div class="flex justify-between items-center p-3 rounded-lg border bg-white border-slate-200">
            <div><p class="text-sm font-bold text-slate-800">${b.patient}</p><p class="text-xs text-slate-500 mt-0.5">⏱️ ${b.time}</p></div>
        </div>`;
    });
    html+=`<button onclick="openModal('${ds}')" class="w-full mt-3 py-2 rounded-lg border border-dashed border-indigo-300 text-indigo-600 text-xs font-bold hover:bg-indigo-50">+ Ajouter un créneau</button></div>`;
    document.getElementById('dpanel-body').innerHTML = html;
}

// -- FONCTIONS DU MODAL (AJOUT ET MODIF) --
function openModal(prefill){
    currentEditId = 0; document.getElementById('edit_id_input').value = '0';
    document.getElementById('modal-title-text').innerHTML = '📅 Planifier un rendez-vous';
    document.getElementById('input_pname').value = ''; document.getElementById('input_pphone').value = '';
    document.getElementById('modal').classList.remove('hidden');
    if(prefill){
        selDate=prefill; 
        const d=new Date(prefill+'T12:00:00'); modalM.y=d.getFullYear(); modalM.m=d.getMonth(); 
        renderModalCal(); renderSlots(prefill); updateDisplay();
    } else {
        selDate=null; selTime=null; renderModalCal(); updateDisplay();
    }
}

// 💡 C'EST ICI LA FONCTION QUI FAISAIT DÉFAUT POUR LA MODIFICATION :
function openEditModal(id, name, phone, date, time) {
    currentEditId = id; 
    document.getElementById('edit_id_input').value = id;
    document.getElementById('modal-title-text').innerHTML = '✏️ Modifier le rendez-vous';
    document.getElementById('input_pname').value = name; 
    document.getElementById('input_pphone').value = phone;
    document.getElementById('modal').classList.remove('hidden');
    
    selDate = date; 
    selTime = time;
    
    const parts = date.split('-');
    modalM.y = parseInt(parts[0], 10); 
    modalM.m = parseInt(parts[1], 10) - 1;
    
    renderModalCal(); 
    renderSlots(date); 
    updateDisplay();
}

function closeModal() { document.getElementById('modal').classList.add('hidden'); }

// -- CALENDRIER DU MODAL --
function renderModalCal(){
    const {y,m} = modalM; document.getElementById('modal-cal-title').textContent = `${MFR[m]} ${y}`;
    const g = document.getElementById('modal-cal-grid'); g.innerHTML = '';
    const fd = (new Date(y,m,1).getDay()+6)%7, dim = new Date(y,m+1,0).getDate();
    
    for(let i=0;i<fd;i++) g.innerHTML += `<div></div>`;
    
    for(let d=1;d<=dim;d++){
        const ds=ymd(y,m,d), past=ds<TODAY_STR, sel=ds===selDate;
        let cls=`w-full aspect-square rounded-md flex items-center justify-center cursor-pointer text-[11px] font-medium transition-colors `;
        if(past) cls+=' text-slate-300 ';
        else if(sel) cls+=' bg-indigo-600 text-white ';
        else cls+=' hover:bg-slate-200 text-slate-700 ';
        
        if(!past) g.innerHTML+=`<div class="${cls}" onclick="selectModalDate('${ds}')">${d}</div>`;
        else g.innerHTML+=`<div class="${cls}">${d}</div>`;
    }
}
function selectModalDate(ds){ selDate=ds; selTime=null; renderModalCal(); renderSlots(ds); updateDisplay(); }
function changeModalMonth(dir){ modalM.m+=dir; if(modalM.m<0){modalM.m=11;modalM.y--;} if(modalM.m>11){modalM.m=0;modalM.y++;} renderModalCal(); }

// -- CRÉNEAUX DU MODAL --
function renderSlots(ds){
    const g=document.getElementById('slots-grid'), s=[];
    for(let h=8;h<19;h++)for(let mn of[0,30]){if(h===18&&mn===30)break;s.push(`${pz(h)}:${pz(mn)}`);}
    
    // On récupère les créneaux occupés, mais on IGNORE celui qu'on est en train de modifier
    const bk = bookedFor(ds).filter(b => b.id != currentEditId).map(b=>b.time); 
    
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
function pickTime(t){ selTime=t; renderSlots(selDate); updateDisplay(); }

function updateDisplay(){
    const dEl=document.getElementById('sel-date-txt'), tEl=document.getElementById('sel-time-txt');
    const btn=document.getElementById('modal-submit'), hid=document.getElementById('p_date_hidden');
    
    if(selDate){ dEl.textContent=fmtDate(selDate); dEl.classList.add('text-slate-900','font-bold'); }
    else { dEl.textContent='Sélectionner →'; dEl.classList.remove('text-slate-900','font-bold'); }
    
    if(selTime){ tEl.textContent=selTime; tEl.classList.add('text-slate-900','font-bold'); }
    else { tEl.textContent='Sélectionner →'; tEl.classList.remove('text-slate-900','font-bold'); }
    
    if(selDate && selTime){ hid.value=`${selDate}T${selTime}`; btn.disabled=false; }
    else { btn.disabled=true; }
}

// Lancement
renderMainCal();
if(BOOKED[TODAY_STR]) clickDay(TODAY_STR);

// Gestion Sidebar Mobile
document.getElementById('open-sidebar').addEventListener('click', ()=>{document.getElementById('sidebar').classList.remove('-translate-x-full');});
document.getElementById('close-sidebar').addEventListener('click', ()=>{document.getElementById('sidebar').classList.add('-translate-x-full');});
</script>
</body>
</html>