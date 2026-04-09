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

// --- Vérification de sécurité (Rate limiting anti brute-force) ---
$ip_visiteur = $_SERVER['REMOTE_ADDR'];
$stmt_check_ip = $conn->prepare("SELECT attempts FROM login_attempts WHERE ip_address = ? AND last_attempt > NOW() - INTERVAL 15 MINUTE");
$stmt_check_ip->bind_param("s", $ip_visiteur);
$stmt_check_ip->execute();
$res_ip = $stmt_check_ip->get_result();

if ($row_ip = $res_ip->fetch_assoc()) {
    if ($row_ip['attempts'] >= 5) {
        http_response_code(429);
        die("
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Accès Restreint | Sécurité PsySpace</title>
            <script src='https://cdn.tailwindcss.com' nonce='$nonce'></script>
            <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap' rel='stylesheet'>
            <style nonce='$nonce'>
                body { font-family: 'Inter', sans-serif; background-color: #0f172a; }
                .font-mono { font-family: 'JetBrains Mono', monospace; }
                .glow-red { box-shadow: 0 0 20px rgba(220, 38, 38, 0.4); }
            </style>
        </head>
        <body class='flex items-center justify-center min-h-screen p-4'>
            <div class='bg-slate-900 max-w-md w-full rounded-2xl glow-red border border-red-500/30 p-8 text-center relative overflow-hidden'>
                <div class='absolute top-0 left-0 w-full h-1 bg-red-600'></div>
                
                <div class='w-20 h-20 bg-red-500/10 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6 border border-red-500/20'>
                    <svg class='w-10 h-10' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/></svg>
                </div>
                
                <h2 class='text-2xl font-bold text-white mb-2 tracking-wide uppercase'>Accès Restreint</h2>
                
                <div class='bg-red-500/10 border border-red-500/20 rounded-lg p-5 mb-6 mt-4'>
                    <p class='text-red-400 text-sm font-medium leading-relaxed'>
                        Par mesure de sécurité, suite à de multiples tentatives de connexion échouées, l'accès depuis ce réseau a été temporairement suspendu.
                    </p>
                </div>
                
                <div class='bg-slate-950 border border-slate-800 rounded-lg p-4 mb-6 text-left flex flex-col gap-2'>
                    <div class='flex justify-between items-center border-b border-slate-800 pb-2'>
                        <span class='text-slate-500 text-xs uppercase font-bold tracking-wider'>Adresse IP</span>
                        <span class='text-red-400 text-xs font-mono'>" . htmlspecialchars($ip_visiteur) . "</span>
                    </div>
                    <div class='flex justify-between items-center pt-1'>
                        <span class='text-slate-500 text-xs uppercase font-bold tracking-wider'>Statut</span>
                        <span class='text-red-500 text-xs font-bold flex items-center gap-1.5'><span class='w-2 h-2 rounded-full bg-red-500 animate-pulse'></span> BLOQUÉ</span>
                    </div>
                </div>
                
                <p class='text-slate-400 text-xs'>Veuillez patienter 15 minutes avant toute nouvelle tentative.</p>
            </div>
        </body>
        </html>
        ");
    }
}
$stmt_check_ip->close();

// --- Déconnexion ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: assistante.php");
    exit();
}

// --- Authentification Assistante ---
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
        sleep(2); // Délai anti brute-force supplémentaire
        $stmt_fail = $conn->prepare("INSERT INTO login_attempts (ip_address, attempts) VALUES (?, 1) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()");
        $stmt_fail->bind_param("s", $ip_visiteur);
        $stmt_fail->execute();
        $login_error = "Code d'accès invalide.";
    }
}

// ── VUE : PORTAIL DE CONNEXION ───────────────────────────────────────────────
if (!isset($_SESSION['sec_doc_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Accès Secrétariat | PsySpace</title>
        <link rel="icon" type="image/png" href="assets/images/logo.png">
        <script src="https://cdn.tailwindcss.com" nonce="<?= $nonce ?>"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style nonce="<?= $nonce ?>">body { font-family: 'Inter', sans-serif; }</style>
    </head>
    <body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
        <div class="bg-white max-w-md w-full rounded-3xl shadow-xl border border-slate-200 p-8 text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-2 bg-indigo-600"></div>
            
            <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </div>
            
            <h2 class="text-xl font-bold text-slate-800 mb-2">Espace Accueil</h2>
            <p class="text-slate-500 mb-8 text-sm">Veuillez entrer le code d'accès du cabinet.</p>
            
            <?php if (isset($login_error)): ?>
                <div class="text-red-600 text-sm font-medium bg-red-50 border border-red-100 p-3 rounded-lg mb-6 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?= htmlspecialchars($login_error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="assistante.php">
                <input type="text" name="cabinet_code" required placeholder="CODE À 10 CARACTÈRES" maxlength="15"
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

// ── VUE : ESPACE ASSISTANTE CONNECTÉE ────────────────────────────────────────
$doc_id = (int)$_SESSION['sec_doc_id'];
$doc_name = $_SESSION['sec_doc_name'];

// Protection CSRF Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_sec_token'], $_POST['csrf_token'])) {
        die("Action non autorisée.");
    }
}

// --- Actions CRUD (Rendez-vous) ---
if (isset($_POST['save_appointment'])) {
    $edit_id = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    $p_name  = trim($_POST['p_name']);
    $p_phone = trim($_POST['p_phone']);
    $p_date  = $_POST['p_date']; 
    $app_datetime = date('Y-m-d H:i:s', strtotime($p_date));
    $date_format_chat = date('d/m à H:i', strtotime($p_date));
    
    // Vérification de l'existence du patient
    $stmt_p = $conn->prepare("SELECT id FROM patients WHERE pphone = ? LIMIT 1");
    $stmt_p->bind_param("s", $p_phone);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    
    if ($res_p->num_rows > 0) { 
        $final_patient_id = $res_p->fetch_assoc()['id']; 
    } else {
        $stmt_ins_p = $conn->prepare("INSERT INTO patients (pname, pphone) VALUES (?, ?)");
        $stmt_ins_p->bind_param("ss", $p_name, $p_phone);
        $stmt_ins_p->execute();
        $final_patient_id = $stmt_ins_p->insert_id;
    }
    
    if ($edit_id > 0) {
        // Notification système
        $sys_msg = "RDV modifié : " . $p_name . " (" . $date_format_chat . ")";
        $stmt_chat = $conn->prepare("INSERT INTO cabinet_chat (doctor_id, sender_type, message) VALUES (?, 'system', ?)");
        $stmt_chat->bind_param("is", $doc_id, $sys_msg);
        $stmt_chat->execute();
        
        // Mise à jour
        $stmt_upd = $conn->prepare("UPDATE appointments SET patient_id=?, patient_name=?, patient_phone=?, app_date=? WHERE id=? AND doctor_id=?");
        $stmt_upd->bind_param("isssii", $final_patient_id, $p_name, $p_phone, $app_datetime, $edit_id, $doc_id);
        $stmt_upd->execute();
        
        header("Location: assistante.php?success=edit"); exit();
    } else {
        // Notification système
        $sys_msg = "Nouveau RDV : " . $p_name . " (" . $date_format_chat . ")";
        $stmt_chat = $conn->prepare("INSERT INTO cabinet_chat (doctor_id, sender_type, message) VALUES (?, 'system', ?)");
        $stmt_chat->bind_param("is", $doc_id, $sys_msg);
        $stmt_chat->execute();
        
        // Insertion
        $app_type = 'Consultation';
        $stmt_ins = $conn->prepare("INSERT INTO appointments (doctor_id, patient_id, patient_name, patient_phone, app_date, app_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("iissss", $doc_id, $final_patient_id, $p_name, $p_phone, $app_datetime, $app_type);
        $stmt_ins->execute();
        
        header("Location: assistante.php?success=add"); exit();
    }
}

if (isset($_POST['delete_app'])) {
    $app_id = (int)$_POST['app_id'];
    
    // Récupération du nom pour notification
    $stmt_nm = $conn->prepare("SELECT patient_name FROM appointments WHERE id=? AND doctor_id=?");
    $stmt_nm->bind_param("ii", $app_id, $doc_id);
    $stmt_nm->execute();
    $res_name = $stmt_nm->get_result();
    $del_name = ($res_name->num_rows > 0) ? $res_name->fetch_assoc()['patient_name'] : 'Inconnu';
    
    // Notification système
    $sys_msg = "RDV annulé : " . $del_name;
    $stmt_chat = $conn->prepare("INSERT INTO cabinet_chat (doctor_id, sender_type, message) VALUES (?, 'system', ?)");
    $stmt_chat->bind_param("is", $doc_id, $sys_msg);
    $stmt_chat->execute();
    
    // Suppression
    $stmt_del = $conn->prepare("DELETE FROM appointments WHERE id=? AND doctor_id=?");
    $stmt_del->bind_param("ii", $app_id, $doc_id);
    $stmt_del->execute();
    
    header("Location: assistante.php?success=delete"); exit();
}

// --- Récupération des données pour l'affichage ---
$stmt_agenda = $conn->prepare("SELECT * FROM appointments WHERE doctor_id = ? AND app_date >= CURDATE() ORDER BY app_date ASC");
$stmt_agenda->bind_param("i", $doc_id);
$stmt_agenda->execute();
$query = $stmt_agenda->get_result();
$appointments = [];
while ($row = $query->fetch_assoc()) {
    $appointments[] = $row;
}

$booked = [];
foreach ($appointments as $app) {
    $dk = date('Y-m-d', strtotime($app['app_date']));
    $tk = date('H:i',   strtotime($app['app_date']));
    if (!isset($booked[$dk])) $booked[$dk] = [];
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
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com" nonce="<?= $nonce ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script nonce="<?= $nonce ?>">
        tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } };
    </script>
    <style nonce="<?= $nonce ?>">
        body { font-family: 'Inter', sans-serif; }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>
<body class="bg-slate-50 text-slate-700">

<div class="flex min-h-screen relative">
    
    <!-- Menu Latéral -->
    <aside id="sidebar" class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col fixed h-full z-50 transition-transform transform -translate-x-full lg:translate-x-0">
        <div class="p-6 border-b border-slate-800 flex items-center gap-3">
            <img src="assets/images/logo.png" alt="PsySpace Logo" class="h-8 w-8 rounded-lg object-cover">            
            <span class="text-lg font-bold text-white">PsySpace</span>
        </div>
        <div class="p-6 border-b border-slate-800">
            <p class="text-xs text-slate-500 uppercase font-bold tracking-wider mb-2">Secrétariat</p>
            <p class="text-white font-medium">Dr. <?= htmlspecialchars($doc_name, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <nav class="flex-1 p-4">
            <a href="#" class="bg-indigo-600/20 text-indigo-400 flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-colors">
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

    <!-- Contenu Principal -->
    <main class="flex-1 lg:ml-64 p-4 md:p-8 w-full pb-24">
        
        <div class="flex flex-wrap justify-between items-center mb-8 gap-4">
            <div class="flex items-center gap-4">
                <button id="open-sidebar" class="lg:hidden p-2 text-slate-500 bg-white rounded-md border border-slate-200 shadow-sm">
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

        <!-- Alertes de succès -->
        <?php if(isset($_GET['success'])): ?>
            <?php 
                $msg = "Opération effectuée.";
                if($_GET['success'] == 'add') $msg = "Rendez-vous ajouté avec succès.";
                if($_GET['success'] == 'edit') $msg = "Rendez-vous modifié avec succès.";
                if($_GET['success'] == 'delete') $msg = "Rendez-vous annulé avec succès.";
            ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex items-center justify-between text-sm font-medium shadow-sm">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span><?= $msg ?></span>
                </div>
                <a href="assistante.php" class="text-emerald-500 hover:text-emerald-700 transition">&times;</a>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Colonne Calendrier -->
            <div class="lg:col-span-4 space-y-6">
                
                <!-- Composant Calendrier -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <button onclick="changeMonth(-1)" class="p-1.5 rounded hover:bg-slate-100 text-slate-500 transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
                        <h3 id="cal-title" class="font-bold text-slate-800 text-sm uppercase tracking-wide"></h3>
                        <button onclick="changeMonth(1)" class="p-1.5 rounded hover:bg-slate-100 text-slate-500 transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center mb-2">
                        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?>
                        <div class="text-[10px] font-bold text-slate-400 uppercase"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="cal-grid" class="grid grid-cols-7 gap-1"></div>
                </div>

                <!-- Aperçu du jour sélectionné -->
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
                        <h4 id="dpanel-title" class="font-bold text-slate-800 text-sm">Aperçu du jour</h4>
                    </div>
                    <div id="dpanel-body" class="p-5">
                        <div class="text-center py-6 text-slate-400 text-xs font-medium">Sélectionnez une date</div>
                    </div>
                </div>
            </div>

            <!-- Colonne Liste des RDV -->
            <div class="lg:col-span-8">
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="font-bold text-slate-800 text-sm">Liste des réservations à venir</h3>
                        <span class="text-xs font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 px-2 py-1 rounded-md"><?= $total ?> prévu(s)</span>
                    </div>
                    
                    <?php if(!empty($appointments)): ?>
                    <div class="divide-y divide-slate-100 max-h-[600px] overflow-y-auto custom-scroll">
                        <?php foreach($appointments as $row):
                            $ts = strtotime($row['app_date']);
                            $id = $row['id'];
                            $pname = htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8');
                            $pphone = htmlspecialchars($row['patient_phone'], ENT_QUOTES, 'UTF-8');
                        ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between px-5 py-4 hover:bg-slate-50 gap-4 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-lg border border-slate-200 flex flex-col items-center justify-center bg-white shadow-sm shrink-0">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase"><?= date('M', $ts) ?></span>
                                    <span class="text-lg font-bold text-slate-700 leading-tight"><?= date('d', $ts) ?></span>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800 text-sm"><?= $pname ?></p>
                                    <p class="text-xs font-medium text-slate-500 mt-0.5 flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <?= date('H:i', $ts) ?> 
                                        <span class="text-slate-300">|</span> 
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        <?= $pphone ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <button onclick="openEditModal(<?= $id ?>, '<?= addslashes($pname) ?>', '<?= addslashes($pphone) ?>', '<?= date('Y-m-d', $ts) ?>', '<?= date('H:i', $ts) ?>')" 
                                        class="px-3 py-1.5 text-slate-600 bg-white rounded border border-slate-200 text-xs font-medium hover:bg-slate-50 hover:text-indigo-600 transition shadow-sm">
                                    Modifier
                                </button>
                                
                                <form method="POST" class="inline-block" onsubmit="return confirm('Confirmer l\'annulation définitive de ce rendez-vous ?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_sec_token'] ?>">
                                    <input type="hidden" name="delete_app" value="1">
                                    <input type="hidden" name="app_id" value="<?= $id ?>">
                                    <button type="submit" class="px-3 py-1.5 text-red-600 bg-white rounded border border-slate-200 text-xs font-medium hover:bg-red-50 hover:border-red-200 transition shadow-sm">
                                        Annuler
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="p-16 flex flex-col items-center justify-center text-center">
                        <svg class="w-12 h-12 text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <p class="text-slate-500 font-medium text-sm">Aucun rendez-vous de prévu pour le moment.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal Formulaire d'ajout / édition RDV -->
<div id="modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden transition-opacity" onclick="if(event.target===this)closeModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden border border-slate-200" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center px-6 py-5 border-b border-slate-100 bg-slate-50/50">
            <h3 id="modal-title-text" class="font-bold text-slate-800 text-base">Planifier un rendez-vous</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-700 bg-white border border-slate-200 w-8 h-8 rounded-lg flex items-center justify-center transition shadow-sm">&times;</button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2">
            <div class="p-6 border-b md:border-b-0 md:border-r border-slate-100 bg-white">
                <form action="assistante.php" method="POST" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_sec_token'] ?>">
                    <input type="hidden" name="save_appointment" value="1">
                    <input type="hidden" name="edit_id" id="edit_id_input" value="0">
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1.5">Patient</label>
                        <input type="text" name="p_name" id="input_pname" required placeholder="Nom et Prénom" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1.5">Téléphone</label>
                        <input type="tel" name="p_phone" id="input_pphone" required placeholder="06 XX XX XX XX" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition-all">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1.5">Date</label>
                            <div id="sel-date-txt" class="border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-400 bg-slate-50 font-medium">À sélectionner</div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-1.5">Heure</label>
                            <div id="sel-time-txt" class="border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-400 bg-slate-50 font-medium">À sélectionner</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="p_date" id="p_date_hidden">
                    
                    <div class="pt-4">
                        <button type="submit" id="modal-submit" disabled class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white py-3 rounded-xl text-sm font-bold transition shadow-sm">
                            Enregistrer le rendez-vous
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="p-6 bg-slate-50/50 space-y-6">
                <!-- Mini Calendrier -->
                <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <button type="button" onclick="changeModalMonth(-1)" class="text-slate-400 hover:text-slate-600 p-1 bg-slate-50 rounded">&lsaquo;</button>
                        <span id="modal-cal-title" class="text-xs font-bold text-slate-800 uppercase tracking-wide"></span>
                        <button type="button" onclick="changeModalMonth(1)" class="text-slate-400 hover:text-slate-600 p-1 bg-slate-50 rounded">&rsaquo;</button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center mb-1">
                        <?php foreach(['L','M','M','J','V','S','D'] as $d): ?>
                        <div class="text-[9px] font-bold text-slate-400"><?= $d ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div id="modal-cal-grid" class="grid grid-cols-7 gap-1"></div>
                </div>
                
                <!-- Grille Horaires -->
                <div>
                    <p class="text-xs font-bold text-slate-600 uppercase tracking-wide mb-2">Créneaux horaires</p>
                    <div id="slots-grid" class="grid grid-cols-4 gap-2 h-36 overflow-y-auto custom-scroll pr-1">
                        <div class="col-span-4 text-center text-xs font-medium text-slate-400 py-6 border border-dashed border-slate-200 rounded-xl bg-white">Veuillez d'abord choisir une date</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bouton Tchat Flottant -->
<div id="chat-button" class="fixed bottom-6 right-6 w-14 h-14 bg-slate-900 hover:bg-slate-800 text-white rounded-full shadow-xl cursor-pointer transition-transform hover:scale-105 flex items-center justify-center border-2 border-slate-800 z-40">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
    <span id="chat-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center shadow-sm hidden border border-white">0</span>
</div>

<!-- Tiroir Tchat -->
<div id="chat-drawer" class="fixed top-0 right-0 h-full w-80 sm:w-96 bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col border-l border-slate-200">
    <div class="p-5 bg-slate-900 text-white flex justify-between items-center shadow-md z-10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center border border-slate-700 text-indigo-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            </div>
            <div>
                <h3 class="font-bold text-sm">Liaison Cabinet</h3>
                <p class="text-[10px] text-emerald-400 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Connecté</p>
            </div>
        </div>
        <button id="close-chat" class="text-slate-400 hover:text-white bg-slate-800 p-1.5 rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
    
    <div id="chat-messages" class="flex-1 p-5 overflow-y-auto bg-slate-50 flex flex-col gap-4 custom-scroll">
        <!-- Messages chargés via AJAX -->
    </div>
    
    <div class="p-4 bg-white border-t border-slate-200">
        <form id="chat-form" class="flex gap-2">
            <input type="hidden" id="chat-csrf" value="<?= $_SESSION['csrf_sec_token'] ?>">
            <input type="text" id="chat-input" placeholder="Écrire un message..." required autocomplete="off" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 transition">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl px-4 flex items-center justify-center transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
            </button>
        </form>
    </div>
</div>

<script nonce="<?= $nonce ?>">
// --- Gestion du Calendrier ---
const BOOKED = <?php echo json_encode($booked); ?>;
const TODAY_STR = '<?php echo $today; ?>';
const MFR = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
const DFR = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];

let mainM = {y: new Date().getFullYear(), m: new Date().getMonth()};
let modalM = {y: new Date().getFullYear(), m: new Date().getMonth()};
let selDay = null, selDate = null, selTime = null, currentEditId = 0;

function pz(n) { return String(n).padStart(2, '0'); }
function ymd(y, m, d) { return `${y}-${pz(m + 1)}-${pz(d)}`; }
function bookedFor(ds) { return BOOKED[ds] || []; }
function isPast(ds, t) { return new Date(`${ds}T${t}:00`) < new Date(); }
function fmtDate(ds) { 
    const d = new Date(ds + 'T12:00:00'); 
    return `${DFR[(d.getDay() + 6) % 7]} ${d.getDate()} ${MFR[d.getMonth()]}`; 
}

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
        
        let classes = 'w-full aspect-square rounded-lg flex flex-col items-center justify-center cursor-pointer text-xs font-bold transition-all duration-200 ';
        
        if (past) classes += 'text-slate-300';
        else if (sel) classes += 'bg-indigo-600 text-white shadow-md transform scale-105';
        else if (today) classes += 'bg-indigo-50 text-indigo-700 border border-indigo-200';
        else classes += 'hover:bg-slate-100 text-slate-700';
        
        let indicator = bk.length ? `<span class="mt-1 w-1.5 h-1.5 rounded-full ${sel ? 'bg-white' : 'bg-amber-400'}"></span>` : '';
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
        body.innerHTML = `<div class="text-center py-6"><button onclick="openModal('${ds}')" class="text-indigo-600 font-medium text-sm hover:text-indigo-800 transition">Planifier un RDV pour ce jour</button></div>`;
        return;
    }
    
    let html = '<div class="space-y-3">';
    bk.forEach(b => {
        html += `
        <div class="flex justify-between items-center p-3 rounded-xl border border-slate-200 bg-white shadow-sm">
            <div>
                <p class="font-bold text-slate-800 text-sm">${b.patient}</p>
                <p class="text-xs font-medium text-slate-500 mt-0.5 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>${b.time}</p>
            </div>
        </div>`;
    });
    html += `<button onclick="openModal('${ds}')" class="w-full mt-3 py-2.5 rounded-xl border border-dashed border-indigo-300 text-indigo-600 font-medium text-sm hover:bg-indigo-50 transition">Ajouter un créneau</button></div>`;
    body.innerHTML = html;
}

function openModal(prefill) {
    currentEditId = 0; 
    document.getElementById('edit_id_input').value = '0';
    document.getElementById('modal-title-text').innerHTML = 'Planifier un rendez-vous';
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
    document.getElementById('modal-title-text').innerHTML = 'Modifier le rendez-vous';
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
        
        let classes = 'w-full aspect-square rounded-lg flex items-center justify-center cursor-pointer text-[11px] font-bold transition-all ';
        if (past) classes += 'text-slate-300';
        else if (sel) classes += 'bg-indigo-600 text-white shadow';
        else classes += 'hover:bg-slate-100 text-slate-700 border border-transparent hover:border-slate-200';
        
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
        
        let classes = 'px-1 py-2 rounded-lg text-center text-[11px] font-bold border transition-all cursor-pointer ';
        if (isBooked) classes += 'bg-slate-100 text-slate-400 border-slate-100 cursor-not-allowed';
        else if (past && ds === TODAY_STR) classes += 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed';
        else if (sel) classes += 'bg-indigo-600 text-white border-indigo-600 shadow-md transform scale-105';
        else classes += 'bg-white text-slate-700 border-slate-200 hover:border-indigo-400 hover:text-indigo-600';
        
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
        dEl.classList.add('text-slate-800', 'border-indigo-200', 'bg-indigo-50');
        dEl.classList.remove('text-slate-400', 'bg-slate-50');
    } else {
        dEl.textContent = 'À sélectionner';
        dEl.classList.remove('text-slate-800', 'border-indigo-200', 'bg-indigo-50');
        dEl.classList.add('text-slate-400', 'bg-slate-50');
    }
    
    if (selTime) {
        tEl.textContent = selTime;
        tEl.classList.add('text-slate-800', 'border-indigo-200', 'bg-indigo-50');
        tEl.classList.remove('text-slate-400', 'bg-slate-50');
    } else {
        tEl.textContent = 'À sélectionner';
        tEl.classList.remove('text-slate-800', 'border-indigo-200', 'bg-indigo-50');
        tEl.classList.add('text-slate-400', 'bg-slate-50');
    }
    
    if (selDate && selTime) {
        hid.value = `${selDate}T${selTime}`;
        btn.disabled = false;
    } else {
        btn.disabled = true;
    }
}

// Initialisation
renderMainCal();
if (BOOKED[TODAY_STR]) clickDay(TODAY_STR);

document.getElementById('open-sidebar').addEventListener('click', () => {
    document.getElementById('sidebar').classList.remove('-translate-x-full');
});

// --- Logique du Chat Flottant ---
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
const notifSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');

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
                    html += `<div class="text-center my-3"><span class="bg-slate-200 text-slate-600 text-[10px] font-bold px-3 py-1.5 rounded-full inline-flex items-center justify-center gap-1.5 shadow-sm border border-slate-300"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>${msg.message}</span></div>`;
                } else if (msg.sender_type === 'assistant') { 
                    html += `<div class="self-end max-w-[85%] flex flex-col items-end mb-2"><div class="bg-indigo-600 text-white text-sm py-2.5 px-4 rounded-2xl rounded-tr-sm shadow-sm">${msg.message}</div><span class="text-[10px] text-slate-400 mt-1">${msg.time}</span></div>`;
                } else { 
                    html += `<div class="self-start max-w-[85%] flex flex-col items-start mb-2"><span class="text-[10px] font-bold text-slate-500 mb-1 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Docteur</span><div class="bg-white border border-slate-200 text-slate-800 text-sm py-2.5 px-4 rounded-2xl rounded-tl-sm shadow-sm">${msg.message}</div><span class="text-[10px] text-slate-400 mt-1">${msg.time}</span></div>`;
                }
            });

            chatMessages.innerHTML = html;

            if (data.length > lastMsgCount) {
                if (lastMsgCount !== 0 && !isDrawerOpen) {
                    let unread = parseInt(chatBadge.textContent) + (data.length - lastMsgCount);
                    chatBadge.textContent = unread;
                    chatBadge.classList.remove('hidden');
                    notifSound.play().catch(e => {}); 
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