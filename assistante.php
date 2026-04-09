<?php
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

include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

// Vérification de sécurité (Rate limiting)
$ip_visiteur = $_SERVER['REMOTE_ADDR'];
$stmt_check_ip = $conn->prepare("SELECT attempts FROM login_attempts WHERE ip_address = ? AND last_attempt > NOW() - INTERVAL 15 MINUTE");
$stmt_check_ip->bind_param("s", $ip_visiteur);
$stmt_check_ip->execute();
$res_ip = $stmt_check_ip->get_result();

if ($row_ip = $res_ip->fetch_assoc()) {
    if ($row_ip['attempts'] >= 5) {
        http_response_code(429);
        die("<div style='font-family:sans-serif; text-align:center; padding:50px;'>Accès temporairement bloqué suite à de nombreuses tentatives. Réessayez dans 15 minutes.</div>");
    }
}
$stmt_check_ip->close();

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: assistante.php");
    exit();
}

// Authentification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cabinet_code'])) {
    $input_code = strtoupper(trim($_POST['cabinet_code']));
    
    $stmt = $conn->prepare("
        SELECT d.docid, d.docname 
        FROM doctor d 
        JOIN assistant_access a ON d.docid = a.doctor_id 
        WHERE d.status = 'active' AND a.access_code = ?
    ");
    $stmt->bind_param("s", $input_code);
    $stmt->execute();
    $res_docs = $stmt->get_result();
    
    if ($doc = $res_docs->fetch_assoc()) {
        session_regenerate_id(true);
        $_SESSION['sec_doc_id'] = $doc['docid'];
        $_SESSION['sec_doc_name'] = $doc['docname'];
        $_SESSION['csrf_sec_token'] = bin2hex(random_bytes(32)); 
        
        $stmt_del = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmt_del->bind_param("s", $ip_visiteur);
        $stmt_del->execute();
        
        header("Location: assistante.php");
        exit();
    } else {
        sleep(2);
        $stmt_fail = $conn->prepare("INSERT INTO login_attempts (ip_address, attempts) VALUES (?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()");
        $stmt_fail->bind_param("s", $ip_visiteur);
        $stmt_fail->execute();
        $login_error = "Code d'accès invalide.";
    }
}

// Vue: Verrouillage
if (!isset($_SESSION['sec_doc_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
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
            <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-6">👩‍💼</div>
            <h2 class="text-xl font-bold text-slate-800 mb-2">Espace Accueil</h2>
            <p class="text-slate-500 mb-8 text-sm">Veuillez entrer le code d'accès du cabinet.</p>
            
            <?php if (isset($login_error)): ?>
                <div class="text-red-600 text-sm font-medium bg-red-50 p-3 rounded-lg mb-6"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>

            <form method="POST" action="assistante.php">
                <input type="text" name="cabinet_code" required placeholder="Code à 10 caractères" maxlength="15"
                       class="w-full border border-slate-300 rounded-xl p-4 focus:border-indigo-600 focus:ring-2 focus:ring-indigo-100 outline-none text-center tracking-widest font-mono uppercase mb-6 text-lg transition-all" autocomplete="off">
                <button type="submit" class="w-full bg-slate-900 hover:bg-indigo-600 text-white font-medium py-3.5 rounded-xl transition-colors shadow-sm text-sm">
                    Déverrouiller l'Agenda
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Espace Assistante Connectée
$doc_id = (int)$_SESSION['sec_doc_id'];
$doc_name = $_SESSION['sec_doc_name'];

// Protection CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_sec_token'], $_POST['csrf_token'])) {
        die("Action non autorisée.");
    }
}

// Actions CRUD (RDV)
if(isset($_POST['save_appointment'])) {
    $edit_id = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $p_name  = trim($_POST['p_name']);
    $p_phone = trim($_POST['p_phone']);
    $p_date  = $_POST['p_date']; 
    $app_datetime = date('Y-m-d H:i:s', strtotime($p_date));
    $date_format_chat = date('d/m à H:i', strtotime($p_date));
    
    $stmt_p = $conn->prepare("SELECT id FROM patients WHERE pphone = ? LIMIT 1");
    $stmt_p->bind_param("s", $p_phone);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    
    if($res_p->num_rows > 0) { 
        $final_patient_id = $res_p->fetch_assoc()['id']; 
    } else {
        $stmt_ins_p = $conn->prepare("INSERT INTO patients (pname, pphone) VALUES (?, ?)");
        $stmt_ins_p->bind_param("ss", $p_name, $p_phone);
        $stmt_ins_p->execute();
        $final_patient_id = $stmt_ins_p->insert_id;
    }
    
    if ($edit_id > 0) {
        $sys_msg = "RDV modifié : " . $p_name . " (" . $date_format_chat . ")";
        $stmt_chat = $conn->prepare("INSERT INTO cabinet_chat (doctor_id, sender_type, message) VALUES (?, 'system', ?)");
        $stmt_chat->bind_param("is", $doc_id, $sys_msg);
        $stmt_chat->execute();
        
        $stmt_upd = $conn->prepare("UPDATE appointments SET patient_id=?, patient_name=?, patient_phone=?, app_date=? WHERE id=? AND doctor_id=?");
        $stmt_upd->bind_param("isssii", $final_patient_id, $p_name, $p_phone, $app_datetime, $edit_id, $doc_id);
        $stmt_upd->execute();
        
        header("Location: assistante.php?success=edit"); exit();
    } else {
        $sys_msg = "Nouveau RDV : " . $p_name . " (" . $date_format_chat . ")";
        $stmt_chat = $conn->prepare("INSERT INTO cabinet_chat (doctor_id, sender_type, message) VALUES (?, 'system', ?)");
        $stmt_chat->bind_param("is", $doc_id, $sys_msg);
        $stmt_chat->execute();
        
        $app_type = 'Consultation';
        $stmt_ins = $conn->prepare("INSERT INTO appointments (doctor_id, patient_id, patient_name, patient_phone, app_date, app_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("iissss", $doc_id, $final_patient_id, $p_name, $p_phone, $app_datetime, $app_type);
        $stmt_ins->execute();
        
        header("Location: assistante.php?success=add"); exit();
    }
}

if (isset($_POST['delete_app'])) {
    $app_id = (int)$_POST['app_id'];
    
    $stmt_nm = $conn->prepare("SELECT patient_name FROM appointments WHERE id=? AND doctor_id=?");
    $stmt_nm->bind_param("ii", $app_id, $doc_id);
    $stmt_nm->execute();
    $res_name = $stmt_nm->get_result();
    $del_name = ($res_name->num_rows > 0) ? $res_name->fetch_assoc()['patient_name'] : 'Inconnu';
    
    $sys_msg = "RDV annulé : " . $del_name;
    $stmt_chat = $conn->prepare("INSERT INTO cabinet_chat (doctor_id, sender_type, message) VALUES (?, 'system', ?)");
    $stmt_chat->bind_param("is", $doc_id, $sys_msg);
    $stmt_chat->execute();
    
    $stmt_del = $conn->prepare("DELETE FROM appointments WHERE id=? AND doctor_id=?");
    $stmt_del->bind_param("ii", $app_id, $doc_id);
    $stmt_del->execute();
    
    header("Location: assistante.php?success=delete"); exit();
}

// Récupération des rendez-vous
$stmt_agenda = $conn->prepare("SELECT * FROM appointments WHERE doctor_id = ? AND app_date >= CURDATE() ORDER BY app_date ASC");
$stmt_agenda->bind_param("i", $doc_id);
$stmt_agenda->execute();
$query = $stmt_agenda->get_result();
$appointments = [];
while($row = $query->fetch_assoc()) {
    $appointments[] = $row;
}

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
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-50 text-slate-700">

<div class="flex min-h-screen relative">
    <!-- Navigation Latérale -->
    <aside id="sidebar" class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col fixed h-full z-50 transition-transform transform -translate-x-full lg:translate-x-0">
        <div class="p-6 border-b border-slate-800 flex items-center gap-3">
            <div class="w-8 h-8 bg-indigo-500 text-white rounded-lg flex items-center justify-center font-bold">PS</div>
            <span class="text-lg font-bold text-white">PsySpace</span>
        </div>
        <div class="p-6 border-b border-slate-800">
            <p class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-2">Secrétariat</p>
            <p class="text-white font-medium">Dr. <?= htmlspecialchars($doc_name, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <nav class="flex-1 p-4">
            <a href="#" class="bg-indigo-600/20 text-indigo-400 flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Agenda du Cabinet
            </a>
        </nav>
        <div class="p-4 border-t border-slate-800">
             <a href="assistante.php?logout=1" class="flex items-center gap-2 text-slate-400 hover:text-red-400 text-sm font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Déconnexion
            </a>
        </div>
    </aside>

    <main class="flex-1 lg:ml-64 p-4 md:p-8 w-full pb-24">
        
        <div class="flex flex-wrap justify-between items-center mb-8 gap-4">
            <div class="flex items-center gap-4">
                <button id="open-sidebar" class="lg:hidden p-2 text-slate-500 bg-white rounded-md border border-slate-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Agenda</h1>
                    <p class="text-slate-500 text-sm mt-1">Gestion des consultations</p>
                </div>
            </div>
            
            <div>
                <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg font-medium text-sm transition shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Nouveau RDV
                </button>
            </div>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <?php 
                $msg = "Opération effectuée.";
                if($_GET['success'] == 'add') $msg = "Rendez-vous ajouté.";
                if($_GET['success'] == 'edit') $msg = "Rendez-vous modifié.";
                if($_GET['success'] == 'delete') $msg = "Rendez-vous annulé.";
            ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex justify-between text-sm font-medium">
                <span><?= $msg ?></span>
                <a href="assistante.php" class="text-emerald-500">&times;</a>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Calendrier -->
            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <button onclick="changeMonth(-1)" class="p-1.5 rounded hover:bg-slate-100 text-slate-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
                        <h3 id="cal-title" class="font-bold text-slate-800 text-sm uppercase tracking-wide"></h3>
                        <button onclick="changeMonth(1)" class="p-1.5 rounded hover:bg-slate-100 text-slate-500"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center mb-2">
                        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?>
                        <div class="text-[10px] font-bold text-slate-400 uppercase"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="cal-grid" class="grid grid-cols-7 gap-1"></div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
                        <h4 id="dpanel-title" class="font-bold text-slate-800 text-sm">Aperçu du jour</h4>
                    </div>
                    <div id="dpanel-body" class="p-5">
                        <div class="text-center py-6 text-slate-400 text-xs font-medium">Sélectionnez une date</div>
                    </div>
                </div>
            </div>

            <!-- Liste des RDV -->
            <div class="lg:col-span-8">
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
                        <h3 class="font-bold text-slate-800 text-sm">Liste des réservations</h3>
                        <span class="text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-1 rounded-md"><?= $total ?> prévus</span>
                    </div>
                    
                    <?php if(!empty($appointments)): ?>
                    <div class="divide-y divide-slate-100 max-h-[600px] overflow-y-auto custom-scroll">
                        <?php foreach($appointments as $row):
                            $ts = strtotime($row['app_date']);
                            $id = $row['id'];
                            $pname = htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8');
                            $pphone = htmlspecialchars($row['patient_phone'], ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between px-5 py-4 hover:bg-slate-50 gap-4">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-lg border border-slate-200 flex flex-col items-center justify-center bg-slate-50 shrink-0">
                                    <span class="text-[10px] font-bold text-slate-400"><?= date('M', $ts) ?></span>
                                    <span class="text-lg font-bold text-slate-700"><?= date('d', $ts) ?></span>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-800 text-sm"><?= $pname ?></p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        <?= date('H:i', $ts) ?> &mdash; <?= $pphone ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <button onclick="openEditModal(<?= $id ?>, '<?= addslashes($pname) ?>', '<?= addslashes($pphone) ?>', '<?= date('Y-m-d', $ts) ?>', '<?= date('H:i', $ts) ?>')" 
                                        class="px-3 py-1.5 text-blue-600 rounded border border-blue-100 text-xs hover:bg-blue-50 transition">
                                    Modifier
                                </button>
                                
                                <form method="POST" class="inline-block" onsubmit="return confirm('Confirmer l\'annulation ?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_sec_token'] ?>">
                                    <input type="hidden" name="delete_app" value="1">
                                    <input type="hidden" name="app_id" value="<?= $id ?>">
                                    <button type="submit" class="px-3 py-1.5 text-red-600 rounded border border-red-100 text-xs hover:bg-red-50 transition">
                                        Annuler
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="p-12 text-center text-slate-500 text-sm">Aucun rendez-vous prévu.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Formulaire RDV -->
<div id="modal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden" onclick="if(event.target===this)closeModal()">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl overflow-hidden" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-100 bg-slate-50">
            <h3 id="modal-title-text" class="font-bold text-slate-800 text-sm">Planifier</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600">&times;</button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2">
            <div class="p-6 border-b md:border-b-0 md:border-r border-slate-100">
                <form action="assistante.php" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_sec_token'] ?>">
                    <input type="hidden" name="save_appointment" value="1">
                    <input type="hidden" name="edit_id" id="edit_id_input" value="0">
                    
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Nom du patient</label>
                        <input type="text" name="p_name" id="input_pname" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Téléphone</label>
                        <input type="tel" name="p_phone" id="input_pphone" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
                            <div id="sel-date-txt" class="border border-slate-300 rounded-lg px-3 py-2 text-xs text-slate-500 bg-slate-50">Sélect.</div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Heure</label>
                            <div id="sel-time-txt" class="border border-slate-300 rounded-lg px-3 py-2 text-xs text-slate-500 bg-slate-50">Sélect.</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="p_date" id="p_date_hidden">
                    
                    <div class="pt-2">
                        <button type="submit" id="modal-submit" disabled class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white py-2 rounded-lg text-sm font-medium transition">
                            Valider
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="p-6 bg-slate-50 space-y-6">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <button type="button" onclick="changeModalMonth(-1)" class="text-slate-400 hover:text-slate-600">&lsaquo;</button>
                        <span id="modal-cal-title" class="text-xs font-bold text-slate-700"></span>
                        <button type="button" onclick="changeModalMonth(1)" class="text-slate-400 hover:text-slate-600">&rsaquo;</button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center mb-1">
                        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?>
                        <div class="text-[9px] font-medium text-slate-400"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="modal-cal-grid" class="grid grid-cols-7 gap-1"></div>
                </div>
                
                <div>
                    <p class="text-xs font-medium text-slate-600 mb-2">Créneaux horaires</p>
                    <div id="slots-grid" class="grid grid-cols-4 gap-2 h-28 overflow-y-auto custom-scroll pr-1">
                        <div class="col-span-4 text-center text-xs text-slate-400 py-4">Choisissez une date</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chat Flottant -->
<div id="chat-button" class="fixed bottom-6 right-6 w-12 h-12 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full shadow-lg cursor-pointer transition flex items-center justify-center">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
    <span id="chat-badge" class="absolute 0 top-0 right-0 bg-red-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center hidden">0</span>
</div>

<div id="chat-drawer" class="fixed top-0 right-0 h-full w-80 bg-white shadow-xl z-50 transform translate-x-full transition-transform flex flex-col border-l border-slate-200">
    <div class="p-4 bg-slate-900 text-white flex justify-between items-center">
        <h3 class="font-medium text-sm">Liaison Cabinet</h3>
        <button id="close-chat" class="text-slate-400 hover:text-white">&times;</button>
    </div>
    <div id="chat-messages" class="flex-1 p-4 overflow-y-auto bg-slate-50 flex flex-col gap-3 custom-scroll"></div>
    <div class="p-3 bg-white border-t border-slate-100">
        <form id="chat-form" class="flex gap-2">
            <input type="hidden" id="chat-csrf" value="<?= $_SESSION['csrf_sec_token'] ?>">
            <input type="text" id="chat-input" placeholder="Message..." required autocomplete="off" class="flex-1 bg-slate-100 border-none rounded px-3 py-2 text-sm outline-none">
            <button type="submit" class="bg-indigo-600 text-white rounded px-3 text-sm font-medium">Envoyer</button>
        </form>
    </div>
</div>

<script>
// État global du calendrier
const BOOKED = <?php echo json_encode($booked); ?>;
const TODAY_STR = '<?php echo $today; ?>';
const MFR = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const DFR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];

let mainM = {y: new Date().getFullYear(), m: new Date().getMonth()};
let modalM = {y: new Date().getFullYear(), m: new Date().getMonth()};
let selDay = null, selDate = null, selTime = null, currentEditId = 0;

// Utilitaires
function pz(n) { return String(n).padStart(2, '0'); }
function ymd(y, m, d) { return `${y}-${pz(m + 1)}-${pz(d)}`; }
function bookedFor(ds) { return BOOKED[ds] || []; }
function isPast(ds, t) { return new Date(`${ds}T${t}:00`) < new Date(); }
function fmtDate(ds) { 
    const d = new Date(ds + 'T12:00:00'); 
    return `${DFR[(d.getDay() + 6) % 7]} ${d.getDate()} ${MFR[d.getMonth()]}`; 
}

// Rendu Calendrier Principal
function renderMainCal() {
    const {y, m} = mainM; 
    document.getElementById('cal-title').textContent = `${MFR[m]} ${y}`;
    const grid = document.getElementById('cal-grid'); 
    grid.innerHTML = '';
    
    const firstDay = (new Date(y, m, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    
    for (let i = 0; i < firstDay; i++) {
        grid.innerHTML += `<div></div>`;
    }
    
    for (let d = 1; d <= daysInMonth; d++) {
        const ds = ymd(y, m, d);
        const bk = bookedFor(ds);
        const past = ds < TODAY_STR;
        const today = ds === TODAY_STR;
        const sel = ds === selDay;
        
        let classes = 'w-full aspect-square rounded-md flex flex-col items-center justify-center cursor-pointer text-xs font-medium transition ';
        
        if (past) classes += 'text-slate-300';
        else if (sel) classes += 'bg-indigo-600 text-white shadow';
        else if (today) classes += 'bg-indigo-50 text-indigo-700 border border-indigo-200';
        else classes += 'hover:bg-slate-100 text-slate-600';
        
        let indicator = bk.length ? `<span class="mt-0.5 w-1 h-1 rounded-full bg-amber-400"></span>` : '';
        grid.innerHTML += `<div class="${classes}" onclick="clickDay('${ds}')">${d}${indicator}</div>`;
    }
}

function clickDay(ds) { 
    selDay = ds; 
    renderMainCal(); 
    renderDayPanel(ds); 
}

function changeMonth(dir) { 
    mainM.m += dir; 
    if (mainM.m < 0) { mainM.m = 11; mainM.y--; } 
    if (mainM.m > 11) { mainM.m = 0; mainM.y++; } 
    renderMainCal(); 
}

function renderDayPanel(ds) {
    const bk = bookedFor(ds); 
    document.getElementById('dpanel-title').textContent = fmtDate(ds);
    const body = document.getElementById('dpanel-body');
    
    if (bk.length === 0) {
        body.innerHTML = `<div class="text-center py-4"><button onclick="openModal('${ds}')" class="text-indigo-600 text-sm hover:underline">Planifier un RDV</button></div>`;
        return;
    }
    
    let html = '<div class="space-y-2">';
    bk.forEach(b => {
        html += `
        <div class="flex justify-between items-center p-2 rounded border border-slate-100 bg-slate-50">
            <div class="text-sm">
                <p class="font-medium text-slate-800">${b.patient}</p>
                <p class="text-xs text-slate-500">${b.time}</p>
            </div>
        </div>`;
    });
    html += `<button onclick="openModal('${ds}')" class="w-full mt-2 py-1.5 rounded border border-dashed border-indigo-200 text-indigo-600 text-xs hover:bg-indigo-50">Ajouter</button></div>`;
    body.innerHTML = html;
}

// Logique de la Modal
function openModal(prefill) {
    currentEditId = 0; 
    document.getElementById('edit_id_input').value = '0';
    document.getElementById('modal-title-text').innerHTML = 'Planifier';
    document.getElementById('input_pname').value = ''; 
    document.getElementById('input_pphone').value = '';
    document.getElementById('modal').classList.remove('hidden');
    
    if (prefill) { 
        selDate = prefill; 
        const d = new Date(prefill + 'T12:00:00'); 
        modalM.y = d.getFullYear(); 
        modalM.m = d.getMonth(); 
        renderModalCal(); 
        renderSlots(prefill); 
        updateDisplay();
    } else { 
        selDate = null; 
        selTime = null; 
        renderModalCal(); 
        updateDisplay(); 
    }
}

function openEditModal(id, name, phone, date, time) {
    currentEditId = id; 
    document.getElementById('edit_id_input').value = id;
    document.getElementById('modal-title-text').innerHTML = 'Modifier';
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

function closeModal() { 
    document.getElementById('modal').classList.add('hidden'); 
}

function renderModalCal() {
    const {y, m} = modalM; 
    document.getElementById('modal-cal-title').textContent = `${MFR[m]} ${y}`;
    const grid = document.getElementById('modal-cal-grid'); 
    grid.innerHTML = '';
    
    const firstDay = (new Date(y, m, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    
    for (let i = 0; i < firstDay; i++) grid.innerHTML += `<div></div>`;
    
    for (let d = 1; d <= daysInMonth; d++) {
        const ds = ymd(y, m, d);
        const past = ds < TODAY_STR;
        const sel = ds === selDate;
        
        let classes = 'w-full aspect-square rounded flex items-center justify-center cursor-pointer text-[10px] transition ';
        if (past) classes += 'text-slate-300';
        else if (sel) classes += 'bg-indigo-600 text-white';
        else classes += 'hover:bg-slate-100 text-slate-600';
        
        grid.innerHTML += `<div class="${classes}" ${!past ? `onclick="selectModalDate('${ds}')"` : ''}>${d}</div>`;
    }
}

function selectModalDate(ds) { 
    selDate = ds; 
    selTime = null; 
    renderModalCal(); 
    renderSlots(ds); 
    updateDisplay(); 
}

function changeModalMonth(dir) { 
    modalM.m += dir; 
    if (modalM.m < 0) { modalM.m = 11; modalM.y--; } 
    if (modalM.m > 11) { modalM.m = 0; modalM.y++; } 
    renderModalCal(); 
}

function renderSlots(ds) {
    const grid = document.getElementById('slots-grid');
    const times = [];
    for (let h = 8; h < 19; h++) {
        times.push(`${pz(h)}:00`);
        if (h !== 18) times.push(`${pz(h)}:30`);
    }
    
    const bookedTimes = bookedFor(ds).filter(b => b.id != currentEditId).map(b => b.time); 
    let html = '';
    
    times.forEach(t => {
        const isBooked = bookedTimes.includes(t);
        const past = isPast(ds, t);
        const sel = t === selTime;
        
        let classes = 'px-1 py-1.5 rounded text-center text-[10px] font-medium border transition cursor-pointer ';
        if (isBooked) classes += 'bg-slate-50 text-slate-300 border-slate-100';
        else if (past && ds === TODAY_STR) classes += 'text-slate-300 border-slate-100';
        else if (sel) classes += 'bg-indigo-600 text-white border-indigo-600';
        else classes += 'text-slate-600 border-slate-200 hover:border-indigo-300';
        
        html += `<div class="${classes}" ${!isBooked && !(past && ds === TODAY_STR) ? `onclick="pickTime('${t}')"` : ''}>${t}</div>`;
    });
    grid.innerHTML = html;
}

function pickTime(t) { 
    selTime = t; 
    renderSlots(selDate); 
    updateDisplay(); 
}

function updateDisplay() {
    const dEl = document.getElementById('sel-date-txt');
    const tEl = document.getElementById('sel-time-txt');
    const btn = document.getElementById('modal-submit');
    const hid = document.getElementById('p_date_hidden');
    
    if (selDate) {
        dEl.textContent = fmtDate(selDate);
        dEl.classList.add('text-slate-800');
    } else {
        dEl.textContent = 'Date';
        dEl.classList.remove('text-slate-800');
    }
    
    if (selTime) {
        tEl.textContent = selTime;
        tEl.classList.add('text-slate-800');
    } else {
        tEl.textContent = 'Heure';
        tEl.classList.remove('text-slate-800');
    }
    
    if (selDate && selTime) {
        hid.value = `${selDate}T${selTime}`;
        btn.disabled = false;
    } else {
        btn.disabled = true;
    }
}

// Initialisation Calendrier
renderMainCal();
if (BOOKED[TODAY_STR]) clickDay(TODAY_STR);

// Ouverture Menu Mobile
document.getElementById('open-sidebar').addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('-translate-x-full');
});

// --- Logique du Chat ---
const chatBtn = document.getElementById('chat-button');
const chatDrawer = document.getElementById('chat-drawer');
const closeChat = document.getElementById('close-chat');
const chatMessages = document.getElementById('chat-messages');
const chatForm = document.getElementById('chat-form');
const chatInput = document.getElementById('chat-input');
const chatBadge = document.getElementById('chat-badge');
const chatCsrf = document.getElementById('chat-csrf');

let lastMsgCount = 0; 
let isDrawerOpen = false;

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

function loadMessages() {
    fetch('api_chat.php?action=fetch')
        .then(res => res.json())
        .then(data => {
            let html = '';
            data.forEach(msg => {
                if (msg.sender_type === 'system') {
                    html += `<div class="text-center my-2"><span class="bg-slate-200 text-slate-500 text-[9px] px-2 py-1 rounded">${msg.message}</span></div>`;
                } else if (msg.sender_type === 'assistant') { 
                    html += `<div class="self-end max-w-[80%] flex flex-col items-end"><div class="bg-indigo-600 text-white text-xs py-1.5 px-3 rounded-lg rounded-tr-none">${msg.message}</div><span class="text-[8px] text-slate-400 mt-1">${msg.time}</span></div>`;
                } else { 
                    html += `<div class="self-start max-w-[80%] flex flex-col items-start"><span class="text-[9px] text-slate-400 mb-0.5">Docteur</span><div class="bg-white border border-slate-200 text-slate-700 text-xs py-1.5 px-3 rounded-lg rounded-tl-none">${msg.message}</div><span class="text-[8px] text-slate-400 mt-1">${msg.time}</span></div>`;
                }
            });

            chatMessages.innerHTML = html;

            if (data.length > lastMsgCount) {
                if (lastMsgCount !== 0 && !isDrawerOpen) {
                    let unread = parseInt(chatBadge.textContent) + (data.length - lastMsgCount);
                    chatBadge.textContent = unread;
                    chatBadge.classList.remove('hidden');
                }
                if (isDrawerOpen) chatMessages.scrollTop = chatMessages.scrollHeight;
                lastMsgCount = data.length;
            }
        });
}

chatForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const text = chatInput.value.trim();
    if (!text) return;
    
    const formData = new FormData(); 
    formData.append('message', text);
    if (chatCsrf) formData.append('csrf_token', chatCsrf.value);
    
    fetch('api_chat.php?action=send', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => { 
            if (data.success) { 
                chatInput.value = ''; 
                loadMessages(); 
            } 
        });
});

loadMessages();
setInterval(loadMessages, 3000); 
</script>
</body>
</html>