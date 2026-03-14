<?php
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }
include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

$nom_docteur = mb_strtoupper($_SESSION['nom'] ?? 'Docteur', 'UTF-8');
$doc_id = (int)$_SESSION['id'];

if(isset($_POST['add_appointment'])) {
    $p_name  = mysqli_real_escape_string($conn, $_POST['p_name']);
    $p_phone = mysqli_real_escape_string($conn, $_POST['p_phone']);
    $p_date  = $_POST['p_date'];
    $check_p = $conn->query("SELECT id FROM patients WHERE pphone = '$p_phone' LIMIT 1");
    if($check_p->num_rows > 0) {
        $final_patient_id = $check_p->fetch_assoc()['id'];
    } else {
        $conn->query("INSERT INTO patients (pname, pphone) VALUES ('$p_name', '$p_phone')");
        $final_patient_id = $conn->insert_id;
    }
    $conn->query("INSERT INTO appointments (doctor_id, patient_id, patient_name, app_date, patient_phone) VALUES ('$doc_id','$final_patient_id','$p_name','$p_date','$p_phone')");
    header("Location: agenda.php?success=1"); exit();
}

$sql = "SELECT a.*, c.id AS archive_id FROM appointments a LEFT JOIN consultations c ON a.id = c.appointment_id WHERE a.doctor_id = '$doc_id' ORDER BY a.app_date ASC";
$query = $conn->query($sql);
$appointments = [];
while($row = $query->fetch_assoc()) $appointments[] = $row;

$booked = [];
foreach($appointments as $app) {
    $dk = date('Y-m-d', strtotime($app['app_date']));
    $tk = date('H:i',   strtotime($app['app_date']));
    if(!isset($booked[$dk])) $booked[$dk] = [];
    $booked[$dk][] = ['time'=>$tk,'patient'=>$app['patient_name'],'archived'=>!empty($app['archive_id']), 'id'=>$app['id']];
}

$today    = date('Y-m-d');
$total    = count($appointments);
$archived = count(array_filter($appointments, fn($a)=>!empty($a['archive_id'])));
$pending  = $total - $archived;
$today_c  = count(array_filter($appointments, fn($a)=>date('Y-m-d',strtotime($a['app_date']))===$today));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda | PsySpace</title>
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
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link.active { background-color: #eef2ff; color: #4f46e5; font-weight: 600; }
        
        /* Animation simple */
        .fadein { animation: fadeUp 0.3s ease forwards; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:none} }
    </style>
</head>
<body class="bg-slate-50 text-slate-700">

<div class="flex min-h-screen">

    <!-- SIDEBAR (Unifiée) -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col fixed h-full z-50 print:hidden">
<div class="p-6 border-b border-slate-800">
    <a href="dashboard.php" class="flex items-center gap-3">
        <!-- Votre logo remplace le "P" -->
        <img src="assets/images/logo.png" alt="PsySpace Logo" class="h-8 w-8 rounded-lg object-cover">
        <span class="text-lg font-bold text-white">PsySpace</span>
    </a>
</div>
        <nav class="flex-1 p-4 space-y-1">
            <a href="dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Dashboard
            </a>
            <a href="patients_search.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Patients
            </a>
            <a href="agenda.php" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-white bg-slate-800/50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Agenda
            </a>
            <a href="consultations.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
                Archives
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
             <a href="logout.php" class="flex items-center gap-2 text-slate-500 hover:text-red-400 text-sm font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Déconnexion
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 ml-64 p-8">
        
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Agenda</h1>
                <p class="text-slate-500 text-sm mt-1">Planification et gestion des rendez-vous.</p>
            </div>
            <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg font-medium text-sm transition shadow-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Nouveau RDV
            </button>
        </div>

        <!-- Flash message -->
        <?php if(isset($_GET['success'])): ?>
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm font-medium fadein">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            Rendez-vous enregistré avec succès.
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white border border-slate-200 rounded-lg p-5">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total</p>
                <p class="font-serif text-3xl font-bold text-slate-900"><?= $total ?></p>
            </div>
            <div class="bg-white border border-slate-200 rounded-lg p-5">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Aujourd'hui</p>
                <p class="font-serif text-3xl font-bold text-indigo-600"><?= $today_c ?></p>
            </div>
            <div class="bg-white border border-slate-200 rounded-lg p-5">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">En attente</p>
                <p class="font-serif text-3xl font-bold text-orange-500"><?= $pending ?></p>
            </div>
            <div class="bg-white border border-slate-200 rounded-lg p-5">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Terminés</p>
                <p class="font-serif text-3xl font-bold text-slate-900"><?= $archived ?></p>
            </div>
        </div>

        <!-- Layout principal -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- GAUCHE : Calendrier -->
            <div class="lg:col-span-4 space-y-6">
                
                <!-- Mini Calendrier -->
                <div class="bg-white border border-slate-200 rounded-lg p-5">
                    <div class="flex items-center justify-between mb-4">
                        <button onclick="changeMonth(-1)" class="p-1 rounded hover:bg-slate-100 text-slate-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <h3 id="cal-title" class="font-bold text-slate-800 text-sm"></h3>
                        <button onclick="changeMonth(1)" class="p-1 rounded hover:bg-slate-100 text-slate-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center mb-2">
                        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?>
                        <div class="text-[10px] font-bold text-slate-400 uppercase"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="cal-grid" class="grid grid-cols-7 gap-1"></div>
                </div>

                <!-- Panneau Jour Sélectionné -->
                <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
                        <h4 id="dpanel-title" class="font-bold text-slate-800 text-sm">Sélectionnez un jour</h4>
                        <p id="dpanel-sub" class="text-xs text-slate-500 mt-0.5">Cliquez sur une date</p>
                    </div>
                    <div id="dpanel-body" class="p-5">
                        <div class="text-center py-6 text-slate-400">
                             <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <p class="text-xs font-medium">Aucun jour sélectionné</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DROITE : Liste RDV -->
            <div class="lg:col-span-8">
                <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                        <h3 class="font-bold text-slate-800 text-sm">Prochains rendez-vous</h3>
                        <span class="text-xs text-slate-500"><?= $total ?> entrées</span>
                    </div>
                    
                    <?php if(!empty($appointments)): ?>
                    <div class="divide-y divide-slate-100 max-h-[600px] overflow-y-auto">
                        <?php foreach($appointments as $row):
                            $ts       = strtotime($row['app_date']);
                            $is_today_row = date('Y-m-d',$ts) === $today;
                            $arch     = !empty($row['archive_id']);
                            $penc     = urlencode($row['patient_name']);
                        ?>
                        <div class="flex items-center justify-between px-5 py-3 hover:bg-slate-50 transition <?= $arch?'opacity-50':''; ?>">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-lg border border-slate-200 flex flex-col items-center justify-center bg-white shrink-0">
                                    <span class="text-[10px] font-bold uppercase text-slate-400"><?= date('M', $ts) ?></span>
                                    <span class="text-lg font-bold leading-tight text-slate-700"><?= date('d', $ts) ?></span>
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-800 text-sm"><?= htmlspecialchars($row['patient_name']) ?></p>
                                    <p class="text-xs text-slate-500">
                                        <?= date('H:i', $ts) ?>
                                        <?php if($is_today_row): ?>
                                        <span class="ml-2 text-indigo-600 font-medium">Aujourd'hui</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div>
                                <?php if($arch): ?>
                                    <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">Archivé</span>
                                <?php else: ?>
                                    <a href="analyse_ia.php?patient_name=<?= $penc ?>&id=<?= $row['id'] ?>" class="text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded transition">
                                        Démarrer
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="p-12 text-center text-slate-500 text-sm">Aucun rendez-vous.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- MODAL NOUVEAU RDV -->
<div id="modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden" onclick="if(event.target===this)closeModal()">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl overflow-hidden" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-200">
            <h3 class="font-bold text-slate-900">Nouveau rendez-vous</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-700 text-xl">&times;</button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2">
            <!-- Formulaire -->
            <div class="p-6 border-r border-slate-100">
                <form action="agenda.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Patient</label>
                        <input type="text" name="p_name" required placeholder="Nom complet" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Téléphone</label>
                        <input type="tel" name="p_phone" required placeholder="Numéro" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Date</label>
                        <div id="sel-date-txt" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-500 bg-slate-50">Sélectionnez une date →</div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Heure</label>
                        <div id="sel-time-txt" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-500 bg-slate-50">Sélectionnez une heure →</div>
                    </div>
                    <input type="hidden" name="p_date" id="p_date_hidden">
                    <button type="submit" name="add_appointment" id="modal-submit" disabled
                        class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white py-2.5 rounded-lg font-bold text-sm transition mt-4">
                        Confirmer
                    </button>
                </form>
            </div>
            
            <!-- Sélection Date/Heure -->
            <div class="p-6 bg-slate-50 space-y-4">
                <!-- Calendrier Modal -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <button type="button" onclick="changeModalMonth(-1)" class="p-1 rounded hover:bg-slate-200 text-slate-500">‹</button>
                        <span id="modal-cal-title" class="text-xs font-bold text-slate-600 uppercase"></span>
                        <button type="button" onclick="changeModalMonth(1)" class="p-1 rounded hover:bg-slate-200 text-slate-500">›</button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center mb-1">
                        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?>
                        <div class="text-[9px] font-bold text-slate-400"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="modal-cal-grid" class="grid grid-cols-7 gap-1"></div>
                </div>
                
                <!-- Créneaux -->
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase mb-2">Créneaux disponibles</p>
                    <div id="slots-grid" class="grid grid-cols-4 gap-1.5 max-h-32 overflow-y-auto">
                        <div class="col-span-4 text-center text-[10px] text-slate-400 py-4 italic">Choisir une date</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BOOKED    = <?php echo json_encode($booked); ?>;
const TODAY_STR = '<?php echo $today; ?>';
const MFR = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const DFR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
let mainM={y:new Date().getFullYear(),m:new Date().getMonth()};
let modalM={y:new Date().getFullYear(),m:new Date().getMonth()};
let selDay=null,selDate=null,selTime=null;

function pz(n){return String(n).padStart(2,'0');}
function ymd(y,m,d){return `${y}-${pz(m+1)}-${pz(d)}`;}
function bookedFor(ds){return BOOKED[ds]||[];}
function isPast(ds,t){return new Date(`${ds}T${t}:00`)<new Date();}
function fmtDate(ds){const d=new Date(ds+'T12:00:00');return `${DFR[(d.getDay()+6)%7]} ${d.getDate()} ${MFR[d.getMonth()]}`;}

// --- Calendrier Principal ---
function renderMainCal(){
    const {y,m}=mainM;
    document.getElementById('cal-title').textContent=`${MFR[m]} ${y}`;
    const g=document.getElementById('cal-grid');g.innerHTML='';
    const fd=(new Date(y,m,1).getDay()+6)%7,dim=new Date(y,m+1,0).getDate();
    
    for(let i=0;i<fd;i++){
        g.innerHTML+=`<div class="w-full aspect-square"></div>`; // Empty
    }
    
    for(let d=1;d<=dim;d++){
        const ds=ymd(y,m,d),bk=bookedFor(ds);
        const past=ds<TODAY_STR,tod=ds===TODAY_STR, sel=ds===selDay;
        
        let cls=`w-full aspect-square rounded-lg flex flex-col items-center justify-center cursor-pointer text-xs font-medium relative `;
        if(past) cls+=' text-slate-300 ';
        else if(sel) cls+=' bg-indigo-600 text-white shadow-sm ';
        else if(tod) cls+=' bg-indigo-50 text-indigo-700 font-bold ';
        else cls+=' text-slate-700 hover:bg-slate-100 ';
        
        let dots = '';
        if(bk.length > 0){
            dots = `<span class="absolute bottom-1 flex gap-0.5">
                ${bk.slice(0,3).map(b=>`<span class="w-1 h-1 rounded-full ${b.archived?'bg-emerald-500':'bg-red-500'}"></span>`).join('')}
            </span>`;
        }
        
        g.innerHTML+=`<div class="${cls}" onclick="clickDay('${ds}')">${d}${dots}</div>`;
    }
}
function clickDay(ds){selDay=ds;renderMainCal();renderDayPanel(ds);}
function changeMonth(dir){mainM.m+=dir;if(mainM.m<0){mainM.m=11;mainM.y--;}if(mainM.m>11){mainM.m=0;mainM.y++;}renderMainCal();}

function renderDayPanel(ds){
    const bk=bookedFor(ds);
    document.getElementById('dpanel-title').textContent=fmtDate(ds);
    document.getElementById('dpanel-sub').textContent=bk.length>0?`${bk.length} rendez-vous`:'Journée libre';
    
    if(bk.length===0){
        document.getElementById('dpanel-body').innerHTML=`
            <div class="text-center py-4">
                <p class="text-sm text-slate-500 mb-3">Aucun rendez-vous.</p>
                <button onclick="openModal('${ds}')" class="text-indigo-600 font-bold text-xs hover:underline">+ Planifier</button>
            </div>`;
        return;
    }
    
    let html='<div class="space-y-2">';
    bk.forEach(b=>{
        html+=`<div class="flex items-center justify-between p-2 rounded border ${b.archived?'bg-emerald-50 border-emerald-200':'bg-white border-slate-200'}">
            <div>
                <p class="text-sm font-medium">${b.patient}</p>
                <p class="text-xs text-slate-500">${b.time}</p>
            </div>
            <span class="text-[10px] font-bold ${b.archived?'text-emerald-600':'text-slate-400'}">${b.archived?'Archivé':'Prévu'}</span>
        </div>`;
    });
    html+=`<button onclick="openModal('${ds}')" class="w-full mt-2 py-2 rounded border border-dashed border-indigo-300 text-indigo-600 font-bold text-xs hover:bg-indigo-50 transition">+ Ajouter</button></div>`;
    document.getElementById('dpanel-body').innerHTML=html;
}

// --- Modal Logique ---
function renderModalCal(){
    const {y,m}=modalM;
    document.getElementById('modal-cal-title').textContent=`${MFR[m]} ${y}`;
    const g=document.getElementById('modal-cal-grid');g.innerHTML='';
    const fd=(new Date(y,m,1).getDay()+6)%7,dim=new Date(y,m+1,0).getDate();
    
    for(let i=0;i<fd;i++){ g.innerHTML+=`<div class="w-full aspect-square"></div>`; }
    for(let d=1;d<=dim;d++){
        const ds=ymd(y,m,d);
        const past=ds<TODAY_STR, sel=ds===selDate;
        // Styling similaire au principal mais plus petit
        let cls=`w-full aspect-square rounded flex items-center justify-center cursor-pointer text-xs relative `;
        if(past) cls+=' text-slate-300 ';
        else if(sel) cls+=' bg-indigo-600 text-white ';
        else cls+=' hover:bg-slate-200 ';
        
        // Indicateur rouge si occupé
        const bk = bookedFor(ds).filter(b=>!b.archived);
        let dot = bk.length>0?`<span class="absolute bottom-0.5 w-1 h-1 rounded-full bg-red-500"></span>`:'';
        
        if(!past) g.innerHTML+=`<div class="${cls}" onclick="selectModalDate('${ds}')">${d}${dot}</div>`;
        else g.innerHTML+=`<div class="${cls}">${d}</div>`;
    }
}
function selectModalDate(ds){selDate=ds;selTime=null;renderModalCal();renderSlots(ds);updateDisplay();}
function changeModalMonth(dir){modalM.m+=dir;if(modalM.m<0){modalM.m=11;modalM.y--;}if(modalM.m>11){modalM.m=0;modalM.y++;}renderModalCal();}

function getSlots(){const s=[];for(let h=8;h<19;h++)for(let mn of[0,30]){if(h===18&&mn===30)break;s.push(`${pz(h)}:${pz(mn)}`);}return s;}
function renderSlots(ds){
    const g=document.getElementById('slots-grid'),slots=getSlots(),bk=bookedFor(ds);
    const bkT=bk.map(b=>b.time);
    
    let html='';
    slots.forEach(t=>{
        const isB=bkT.includes(t), past=isPast(ds,t);
        const sel=t===selTime;
        
        let cls='px-2 py-1 rounded text-center text-[10px] font-medium cursor-pointer border ';
        if(isB){ cls+='bg-slate-100 text-slate-400 border-slate-200 line-through'; } // Booked
        else if(past&&ds===TODAY_STR){ cls+='bg-slate-50 text-slate-300 border-slate-100'; } // Past
        else if(sel){ cls+=' bg-indigo-600 text-white border-indigo-600'; } // Selected
        else { cls+=' bg-white text-slate-700 border-slate-200 hover:border-indigo-400'; } // Free
        
        if(!isB && !(past&&ds===TODAY_STR)) html+=`<div class="${cls}" onclick="pickTime('${t}')">${t}</div>`;
        else html+=`<div class="${cls}">${t}</div>`;
    });
    g.innerHTML=html;
}
function pickTime(t){selTime=t;renderSlots(selDate);updateDisplay();}

function updateDisplay(){
    const dEl=document.getElementById('sel-date-txt'),tEl=document.getElementById('sel-time-txt');
    const btn=document.getElementById('modal-submit'),hid=document.getElementById('p_date_hidden');
    
    if(selDate){dEl.textContent=fmtDate(selDate);dEl.classList.add('text-slate-700');}
    else {dEl.textContent='Sélectionnez une date →';dEl.classList.remove('text-slate-700');}
    
    if(selTime){tEl.textContent=selTime;tEl.classList.add('text-slate-700');}
    else {tEl.textContent='Sélectionnez une heure →';tEl.classList.remove('text-slate-700');}
    
    if(selDate&&selTime){hid.value=`${selDate}T${selTime}`;btn.disabled=false;}
    else btn.disabled=true;
}

function openModal(prefill){
    document.getElementById('modal').classList.remove('hidden');
    if(prefill){selDate=prefill;const d=new Date(prefill+'T12:00:00');modalM.y=d.getFullYear();modalM.m=d.getMonth();renderModalCal();renderSlots(prefill);updateDisplay();}
    else renderModalCal();
}
function closeModal(){document.getElementById('modal').classList.add('hidden');}

// Init
renderMainCal();
if(BOOKED[TODAY_STR])clickDay(TODAY_STR);
</script>
</body>
</html>
```