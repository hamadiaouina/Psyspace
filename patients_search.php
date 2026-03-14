<?php
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }
include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

$doc_id = (int)$_SESSION['id'];
$nom_docteur = mb_strtoupper($_SESSION['nom'] ?? 'Docteur', 'UTF-8');

$msg = '';

// Supprimer un rendez-vous
if(isset($_POST['delete_appt'])) {
    $appt_id = (int)$_POST['appt_id'];
    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("ii", $appt_id, $doc_id);
    $stmt->execute(); $stmt->close();
    header("Location: patients_search.php?search=".urlencode($_POST['back_search'])."&msg=deleted"); exit();
}

// Modifier un rendez-vous
if(isset($_POST['edit_appt'])) {
    $appt_id  = (int)$_POST['appt_id'];
    $new_date = $_POST['new_date'];
    $stmt = $conn->prepare("UPDATE appointments SET app_date = ? WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("sii", $new_date, $appt_id, $doc_id);
    $stmt->execute(); $stmt->close();
    header("Location: patients_search.php?search=".urlencode($_POST['back_search'])."&msg=updated"); exit();
}

// Supprimer un patient (tous ses RDV + consultations)
if(isset($_POST['delete_patient'])) {
    $patient_name = $_POST['patient_name'];
    
    // Récupérer les IDs des RDV de ce patient
    $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND patient_name = ?");
    $stmt->bind_param("is", $doc_id, $patient_name);
    $stmt->execute();
    $res = $stmt->get_result();
    $appt_ids = [];
    while($r = $res->fetch_assoc()) $appt_ids[] = $r['id'];
    $stmt->close();

    // Supprimer les consultations liées
    if(!empty($appt_ids)) {
        $in = implode(',', array_map('intval', $appt_ids));
        $conn->query("DELETE FROM consultations WHERE appointment_id IN ($in)");
    }

    // Supprimer tous les RDV du patient
    $stmt = $conn->prepare("DELETE FROM appointments WHERE doctor_id = ? AND patient_name = ?");
    $stmt->bind_param("is", $doc_id, $patient_name);
    $stmt->execute(); $stmt->close();

    header("Location: patients_search.php?msg=patient_deleted"); exit();
}

// Recherche
$search_query = trim($_GET['search'] ?? '');
$patients = [];

if($search_query !== '') {
    $s = "%$search_query%";
    $stmt = $conn->prepare(
        "SELECT patient_name, MAX(patient_phone) as patient_phone,
                COUNT(*) as total_rdv,
                SUM(CASE WHEN app_date < NOW() THEN 1 ELSE 0 END) as rdv_passes,
                MAX(CASE WHEN app_date < NOW() THEN app_date END) as derniere_rdv,
                MIN(CASE WHEN app_date >= NOW() THEN app_date END) as prochain_rdv
         FROM appointments
         WHERE doctor_id = ? AND (patient_name LIKE ? OR patient_phone LIKE ?)
         GROUP BY patient_name ORDER BY patient_name ASC"
    );
    $stmt->bind_param("iss", $doc_id, $s, $s);
    $stmt->execute();
    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $stmt = $conn->prepare(
        "SELECT patient_name, MAX(patient_phone) as patient_phone,
                COUNT(*) as total_rdv,
                SUM(CASE WHEN app_date < NOW() THEN 1 ELSE 0 END) as rdv_passes,
                MAX(CASE WHEN app_date < NOW() THEN app_date END) as derniere_rdv,
                MIN(CASE WHEN app_date >= NOW() THEN app_date END) as prochain_rdv
         FROM appointments WHERE doctor_id = ?
         GROUP BY patient_name ORDER BY patient_name ASC"
    );
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$appts_by_patient = [];
if(!empty($patients)) {
    $names = array_map(fn($p) => "'".$conn->real_escape_string($p['patient_name'])."'", $patients);
    $in = implode(',', $names);
    $res = $conn->query(
        "SELECT a.id, a.patient_name, a.app_date, c.id as archive_id
         FROM appointments a
         LEFT JOIN consultations c ON a.id = c.appointment_id
         WHERE a.doctor_id = $doc_id AND a.patient_name IN ($in)
         ORDER BY a.app_date ASC"
    );
    while($r = $res->fetch_assoc()) {
        $appts_by_patient[$r['patient_name']][] = $r;
    }
}

$msg = $_GET['msg'] ?? '';
$total_patients = count($patients);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients | PsySpace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'], serif: ['Merriweather','serif'] } } }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .sidebar-link { transition: all 0.2s ease; }
        .patient-details { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .patient-details.open { max-height: 1000px; }
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
            <a href="dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
            <a href="patients_search.php" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-white bg-slate-800/50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Patients
            </a>
            <a href="agenda.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Agenda
            </a>
            <a href="consultations.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                Archives
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
            <a href="logout.php" class="flex items-center gap-2 text-slate-500 hover:text-red-400 text-sm font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Déconnexion
            </a>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="flex-1 ml-64 p-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Gestion des Patients</h1>
                <p class="text-slate-500 text-sm mt-1">Recherchez et gérez les dossiers patients.</p>
            </div>
            <div class="text-right hidden sm:block">
                <p class="text-sm font-semibold text-slate-700"><?= date(' d M,Y') ?></p>
            </div>
        </div>

        <!-- Flash messages -->
        <?php if($msg === 'deleted'): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            Rendez-vous supprimé avec succès.
        </div>
        <?php elseif($msg === 'updated'): ?>
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Rendez-vous mis à jour.
        </div>
        <?php elseif($msg === 'patient_deleted'): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm font-medium">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            Patient supprimé avec succès (rendez-vous et archives inclus).
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white border border-slate-200 rounded-lg p-5">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Patients</p>
                <p class="font-serif text-3xl font-bold text-slate-900"><?= $total_patients ?></p>
            </div>
            <div class="bg-white border border-slate-200 rounded-lg p-5">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">RDV Totaux</p>
                <p class="font-serif text-3xl font-bold text-slate-900"><?php $t=0; foreach($patients as $p) $t+=$p['total_rdv']; echo $t; ?></p>
            </div>
            <div class="bg-white border border-slate-200 rounded-lg p-5">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">À venir</p>
                <p class="font-serif text-3xl font-bold text-indigo-600"><?php $f=0; foreach($patients as $p) $f+=($p['total_rdv']-$p['rdv_passes']); echo $f; ?></p>
            </div>
        </div>

        <!-- Search -->
        <form action="" method="GET" class="mb-8">
            <div class="flex gap-3">
                <div class="relative flex-1">
                    <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>"
                           placeholder="Nom du patient ou téléphone..."
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                </div>
                <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition shadow-sm">Rechercher</button>
                <?php if($search_query): ?>
                <a href="patients_search.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2.5 rounded-lg text-sm font-medium transition border border-slate-200">Effacer</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Résultats -->
        <?php if(empty($patients)): ?>
        <div class="text-center py-16 bg-white border border-slate-200 rounded-lg">
            <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <p class="text-slate-500 font-medium text-sm">Aucun patient trouvé.</p>
        </div>
        <?php else: ?>

        <div class="space-y-3">
            <?php foreach($patients as $p):
                $name  = $p['patient_name'];
                $phone = $p['patient_phone'] ?: '—';
                $init  = strtoupper(mb_substr($name, 0, 1, 'UTF-8'));
                $appts = $appts_by_patient[$name] ?? [];
                $pid   = 'p_'.md5($name);
            ?>
            <div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4">
                    <!-- Partie cliquable (nom/info) -->
                    <div class="flex items-center gap-4 flex-1 cursor-pointer hover:opacity-80 transition" onclick="togglePatient('<?= $pid ?>')">
                        <div class="w-10 h-10 rounded-full bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-sm flex-shrink-0">
                            <?= $init ?>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm"><?= htmlspecialchars($name) ?></p>
                            <p class="text-xs text-slate-400">📞 <?= htmlspecialchars($phone) ?></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Prochain RDV -->
                        <div class="hidden md:block text-right mr-2">
                            <p class="text-xs text-slate-500">
                                Prochain RDV : 
                                <span class="font-semibold <?= $p['prochain_rdv']?'text-indigo-600':'text-slate-400' ?>">
                                    <?= $p['prochain_rdv'] ? date('d M Y', strtotime($p['prochain_rdv'])) : 'Aucun' ?>
                                </span>
                            </p>
                        </div>

                        <!-- Bouton supprimer patient -->
                        <button onclick="openDeletePatient('<?= htmlspecialchars(addslashes($name)) ?>', <?= count($appts) ?>)"
                            class="p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition"
                            title="Supprimer ce patient">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9l4 4m0-4l-4 4" class="text-red-500"/>
                            </svg>
                        </button>

                        <!-- Chevron -->
                        <div class="w-6 h-6 flex items-center justify-center text-slate-400 cursor-pointer" id="chev_<?= $pid ?>" onclick="togglePatient('<?= $pid ?>')">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l4 4 4-4"/></svg>
                        </div>
                    </div>
                </div>

                <!-- Détails dépliables -->
                <div id="<?= $pid ?>" class="patient-details border-t border-slate-100">
                    <div class="p-5 bg-slate-50">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="text-xs font-bold uppercase text-slate-500 tracking-wider">Historique (<?= count($appts) ?>)</h4>
                            <a href="consultations.php?patient_name=<?= urlencode($name) ?>" class="text-xs text-indigo-600 font-medium hover:underline">Voir les archives →</a>
                        </div>

                        <div class="bg-white rounded-lg border border-slate-100 divide-y divide-slate-100">
                            <?php foreach($appts as $appt):
                                $ts     = strtotime($appt['app_date']);
                                $isPast = $ts < time();
                                $isArch = !empty($appt['archive_id']);
                            ?>
                            <div class="flex items-center justify-between px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="text-center px-2 py-1 rounded bg-slate-100 text-xs">
                                        <span class="block text-slate-500 text-[10px] uppercase font-bold"><?= date('M', $ts) ?></span>
                                        <span class="block text-slate-800 font-bold leading-tight"><?= date('d', $ts) ?></span>
                                    </div>
                                    <div>
                                        <p class="text-sm text-slate-700 font-medium">
                                            <?= date('H:i', $ts) ?>
                                            <?php if($isArch): ?>
                                                <span class="ml-2 text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded uppercase font-bold">Archivé</span>
                                            <?php elseif(!$isPast): ?>
                                                <span class="ml-2 text-[10px] bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded uppercase font-bold">À venir</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if(!$isArch && !$isPast): ?>
                                        <a href="analyse_ia.php?patient_name=<?= urlencode($name) ?>&id=<?= $appt['id'] ?>"
                                           class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded text-xs font-bold transition">
                                            Démarrer
                                        </a>
                                    <?php endif; ?>
                                    <?php if(!$isArch): ?>
                                        <button onclick="openEdit(<?= $appt['id'] ?>, '<?= date('Y-m-d\TH:i', $ts) ?>', '<?= htmlspecialchars(addslashes($name)) ?>')" class="p-1.5 hover:bg-slate-100 rounded text-slate-400 hover:text-slate-700 transition" title="Modifier">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <button onclick="openConfirm(<?= $appt['id'] ?>, '<?= htmlspecialchars(addslashes($name)) ?>')" class="p-1.5 hover:bg-red-50 rounded text-slate-400 hover:text-red-600 transition" title="Supprimer RDV">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- MODAL EDIT RDV -->
<div id="edit-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden" onclick="if(event.target===this)closeEdit()">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden" onclick="event.stopPropagation()">
        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
            <h3 class="font-bold text-slate-900">Modifier le rendez-vous</h3>
            <button onclick="closeEdit()" class="text-slate-400 hover:text-slate-700 text-xl">&times;</button>
        </div>
        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="edit_appt" value="1">
            <input type="hidden" name="appt_id" id="edit-appt-id">
            <input type="hidden" name="back_search" value="<?= htmlspecialchars($search_query) ?>">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Patient</label>
                <p id="edit-patient-name" class="font-medium text-slate-800 text-sm"></p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nouvelle date</label>
                <input type="datetime-local" name="new_date" id="edit-new-date" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-lg font-bold text-sm transition">Sauvegarder</button>
        </form>
    </div>
</div>

<!-- MODAL CONFIRM SUPPR RDV -->
<div id="confirm-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden" onclick="if(event.target===this)closeConfirm()">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden" onclick="event.stopPropagation()">
        <div class="p-6 text-center">
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h3 class="font-bold text-slate-900 text-lg mb-2">Supprimer ce rendez-vous ?</h3>
            <p id="confirm-info" class="text-sm text-slate-500 mb-6"></p>
            <form action="" method="POST" class="flex gap-3">
                <input type="hidden" name="delete_appt" value="1">
                <input type="hidden" name="appt_id" id="confirm-appt-id">
                <input type="hidden" name="back_search" value="<?= htmlspecialchars($search_query) ?>">
                <button type="button" onclick="closeConfirm()" class="flex-1 py-2 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition text-sm">Annuler</button>
                <button type="submit" class="flex-1 py-2 rounded-lg bg-red-600 text-white font-medium hover:bg-red-700 transition text-sm">Supprimer</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL CONFIRM SUPPR PATIENT -->
<div id="delete-patient-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden" onclick="if(event.target===this)closeDeletePatient()">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden" onclick="event.stopPropagation()">
        <div class="p-6 text-center">
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4 text-red-600">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <h3 class="font-bold text-slate-900 text-lg mb-1">Supprimer ce patient ?</h3>
            <p class="text-sm font-semibold text-red-600 mb-1" id="delete-patient-name"></p>
            <p class="text-xs text-slate-500 mb-1" id="delete-patient-info"></p>
            <p class="text-xs text-red-500 font-medium mb-6">⚠️ Cette action est irréversible. Tous les rendez-vous et archives seront supprimés.</p>
            <form action="" method="POST" class="flex gap-3">
                <input type="hidden" name="delete_patient" value="1">
                <input type="hidden" name="patient_name" id="delete-patient-input">
                <button type="button" onclick="closeDeletePatient()" class="flex-1 py-2.5 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition text-sm">Annuler</button>
                <button type="submit" class="flex-1 py-2.5 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition text-sm">Supprimer</button>
            </form>
        </div>
    </div>
</div>

<script>
function togglePatient(id) {
    const el = document.getElementById(id);
    const chev = document.getElementById('chev_' + id);
    el.classList.toggle('open');
    chev.innerHTML = el.classList.contains('open')
        ? '<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 8l4-4 4 4"/></svg>'
        : '<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4l4 4 4-4"/></svg>';
}

function openEdit(id, date, name) {
    document.getElementById('edit-appt-id').value = id;
    document.getElementById('edit-new-date').value = date;
    document.getElementById('edit-patient-name').textContent = name;
    document.getElementById('edit-modal').classList.remove('hidden');
}
function closeEdit() { document.getElementById('edit-modal').classList.add('hidden'); }

function openConfirm(id, name) {
    document.getElementById('confirm-appt-id').value = id;
    document.getElementById('confirm-info').textContent = "Supprimer le RDV de " + name + " ?";
    document.getElementById('confirm-modal').classList.remove('hidden');
}
function closeConfirm() { document.getElementById('confirm-modal').classList.add('hidden'); }

function openDeletePatient(name, nbAppts) {
    document.getElementById('delete-patient-input').value = name;
    document.getElementById('delete-patient-name').textContent = name;
    document.getElementById('delete-patient-info').textContent = nbAppts + " rendez-vous seront supprimés.";
    document.getElementById('delete-patient-modal').classList.remove('hidden');
}
function closeDeletePatient() { document.getElementById('delete-patient-modal').classList.add('hidden'); }

<?php if(count($patients)===1): ?>
togglePatient('<?= 'p_'.md5($patients[0]['patient_name']) ?>');
<?php endif; ?>
</script>
</body>
</html>