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

// --- 3. GÉNÉRATION DU PARE-FEU CSP ET JETON CSRF ---
$nonce = base64_encode(random_bytes(16));
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none';");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

$doctor_id   = (int)$_SESSION['id'];
$success_msg = '';
$error_msg   = '';
$active_tab  = 'infos';

// --- RÉCUPÉRATION DU CODE SECRÉTARIAT ACTUEL ---
$stmt_code = $conn->prepare("SELECT access_code FROM assistant_access WHERE doctor_id = ?");
$stmt_code->bind_param("i", $doctor_id);
$stmt_code->execute();
$res_code = $stmt_code->get_result()->fetch_assoc();
$current_cabinet_code = $res_code ? $res_code['access_code'] : 'AUCUN CODE';
$stmt_code->close();

$stmt = $conn->prepare("SELECT docid,docemail,docname,status,photo,specialty,order_num,bio,docphone FROM doctor WHERE docid=? LIMIT 1");
$stmt->bind_param("i", $doctor_id); $stmt->execute();
$doc = $stmt->get_result()->fetch_assoc(); $stmt->close();

$stat_patients      = (int)$conn->query("SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE doctor_id=$doctor_id")->fetch_assoc()['c'];
$stat_consultations = (int)$conn->query("SELECT COUNT(*) c FROM consultations WHERE doctor_id=$doctor_id")->fetch_assoc()['c'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        $action = $_POST['action'] ?? '';

        // --- GÉNÉRER UN NOUVEAU CODE CABINET ---
        if ($action === 'generate_cabinet_code') {
            $active_tab = 'assistant';
            $new_code = '';
            $is_unique = false;
            
            // Boucle pour s'assurer que le code est 100% unique dans la base de données
            while (!$is_unique) {
                // Génère un code alphanumérique de 10 caractères
                $new_code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)); 
                
                $check = $conn->prepare("SELECT doctor_id FROM assistant_access WHERE access_code = ?");
                $check->bind_param("s", $new_code);
                $check->execute();
                if ($check->get_result()->num_rows === 0) {
                    $is_unique = true;
                }
                $check->close();
            }

            // Mise à jour ou Insertion
            $up = $conn->prepare("INSERT INTO assistant_access (doctor_id, access_code) VALUES (?, ?) ON DUPLICATE KEY UPDATE access_code = VALUES(access_code)");
            $up->bind_param("is", $doctor_id, $new_code);
            if ($up->execute()) {
                $success_msg = "Nouveau code d'accès généré avec succès !";
                $current_cabinet_code = $new_code;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $error_msg = "Erreur lors de la génération du code.";
            }
            $up->close();
        }

        // --- MISE À JOUR DES INFOS ---
        elseif ($action === 'update_profile') {
            $docname  = trim($_POST['docname']  ?? '');
            $docemail = trim($_POST['docemail'] ?? '');
            $docphone = trim($_POST['docphone'] ?? '');
            $specialty= trim($_POST['specialty']?? '');
            $order_num= trim($_POST['order_num']?? '');
            $bio      = trim($_POST['bio']      ?? '');

            if (empty($docname) || empty($docemail)) {
                $error_msg = "Le nom et l'email sont obligatoires.";
            } elseif (!filter_var($docemail, FILTER_VALIDATE_EMAIL)) {
                $error_msg = "Adresse email invalide.";
            } else {
                $st = $conn->prepare("UPDATE doctor SET docname=?,docemail=?,docphone=?,specialty=?,order_num=?,bio=? WHERE docid=?");
                $st->bind_param("ssssssi", $docname, $docemail, $docphone, $specialty, $order_num, $bio, $doctor_id);
                if ($st->execute()) { 
                    $success_msg = "Profil mis à jour avec succès."; 
                    $_SESSION['nom'] = $docname; 
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
                else { $error_msg = "Erreur lors de la mise à jour."; }
                $st->close();
                
                $s2 = $conn->prepare("SELECT * FROM doctor WHERE docid=? LIMIT 1");
                $s2->bind_param("i", $doctor_id); $s2->execute(); $doc = $s2->get_result()->fetch_assoc(); $s2->close();
            }
        }

        // --- CHANGEMENT DE MOT DE PASSE SÉCURISÉ ---
        elseif ($action === 'change_password') {
            $active_tab = 'security';
            $old  = $_POST['old_password']     ?? '';
            $new  = $_POST['new_password']     ?? '';
            $conf = $_POST['confirm_password'] ?? '';

            $st = $conn->prepare("SELECT docpassword FROM doctor WHERE docid=? LIMIT 1");
            $st->bind_param("i", $doctor_id); $st->execute(); $hr = $st->get_result()->fetch_assoc(); $st->close();

            if (empty($old) || empty($new) || empty($conf))  { $error_msg = "Tous les champs sont requis."; }
            elseif ($new !== $conf)                           { $error_msg = "Les mots de passe ne correspondent pas."; }
            elseif (strlen($new) < 8)                         { $error_msg = "Le nouveau mot de passe doit faire 8 caractères minimum."; }
            elseif (!password_verify($old, $hr['docpassword'])){ $error_msg = "Mot de passe actuel incorrect."; }
            else {
                $h  = password_hash($new, PASSWORD_ARGON2ID);
                $st = $conn->prepare("UPDATE doctor SET docpassword=? WHERE docid=?");
                $st->bind_param("si", $h, $doctor_id);
                if ($st->execute()) { 
                    $success_msg = "Mot de passe modifié avec succès."; 
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
                else { $error_msg = "Erreur lors du changement."; }
                $st->close();
            }
        }

        // --- UPLOAD DE PHOTO ---
        elseif ($action === 'upload_photo' && isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            // ... (ton code upload habituel, je l'ai gardé identique à ton précédent fichier pour ne pas l'alourdir ici, il est inchangé)
            $file = $_FILES['photo'];

            if ($file['size'] > 3 * 1024 * 1024) {
                $error_msg = "Image trop lourde (max 3 Mo).";
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $real_mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png'  => 'png', 'image/webp' => 'webp'];

                if (!array_key_exists($real_mime, $allowed_mimes)) {
                    $error_msg = "Format non supporté ou fichier falsifié.";
                } else {
                    $ext = $allowed_mimes[$real_mime];
                    $fn  = 'doc_' . $doctor_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dir = 'uploads/avatars/';
                    
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    
                    if (move_uploaded_file($file['tmp_name'], $dir.$fn)) {
                        if (!empty($doc['photo']) && file_exists($doc['photo'])) { @unlink($doc['photo']); }
                        $p  = $dir.$fn;
                        $st = $conn->prepare("UPDATE doctor SET photo=? WHERE docid=?");
                        $st->bind_param("si", $p, $doctor_id); 
                        $st->execute(); 
                        $st->close();
                        $success_msg   = "Photo de profil mise à jour.";
                        $doc['photo']  = $p;
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    } else { 
                        $error_msg = "Erreur d'enregistrement."; 
                    }
                }
            }
        }
    }
}

$doc_name      = htmlspecialchars($doc['docname']   ?? '', ENT_QUOTES, 'UTF-8');
$doc_email     = htmlspecialchars($doc['docemail']  ?? '', ENT_QUOTES, 'UTF-8');
$doc_phone     = htmlspecialchars($doc['docphone']  ?? '', ENT_QUOTES, 'UTF-8');
$doc_specialty = htmlspecialchars($doc['specialty'] ?? '', ENT_QUOTES, 'UTF-8');
$doc_order     = htmlspecialchars($doc['order_num'] ?? '', ENT_QUOTES, 'UTF-8');
$doc_bio       = htmlspecialchars($doc['bio']       ?? '', ENT_QUOTES, 'UTF-8');
$doc_photo     = $doc['photo']  ?? '';
$doc_status    = $doc['status'] ?? 'pending';
$doc_initial   = strtoupper(mb_substr($doc['docname'] ?? 'D', 0, 1, 'UTF-8'));
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil | PsySpace</title>
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
        .sidebar-link { transition: all 0.2s ease; }
        .sidebar-link.active { background-color: #eef2ff; color: #4f46e5; font-weight: 600; }
        .dark .sidebar-link.active { background-color: rgba(79,70,229,0.2); color: #818cf8; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-700 dark:text-slate-300 transition-colors duration-300">
<div class="flex min-h-screen relative">

    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity"></div>

    <aside id="sidebar" class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col fixed h-full z-50 transition-transform transform -translate-x-full lg:translate-x-0">
        <!-- ... (Ton menu sidebar normal) ... -->
        <div class="p-6 border-b border-slate-800 flex justify-between items-center">
            <a href="dashboard.php" class="flex items-center gap-2">
                <img src="assets/images/logo.png" alt="PsySpace Logo" class="h-8 w-8 rounded-lg object-cover">
                <span class="text-lg font-bold text-white">PsySpace</span>
            </a>
            <button id="close-sidebar" class="lg:hidden text-slate-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <nav class="flex-1 p-4 space-y-1">
            <a href="dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg> Dashboard
            </a>
            <a href="chat_cabinet.php" class="sidebar-link flex items-center justify-between px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg> Contact Assistant
                </div>
                <span id="chat-badge-sidebar" class="hidden bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm transition-all duration-300">0</span>
            </a>
        </nav>
    </aside>

    <main class="flex-1 lg:ml-64 p-4 md:p-8 w-full">
        <!-- ... (Ton Header normal avec Theme Toggle) ... -->
        
        <?php if($success_msg): ?>
        <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 px-4 py-3 rounded-lg mb-6 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> <?= $success_msg ?>
        </div>
        <?php elseif($error_msg): ?>
        <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 px-4 py-3 rounded-lg mb-6 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> <?= $error_msg ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- COLONNE GAUCHE (Photo & Stats) -->
            <div class="lg:col-span-1 space-y-6">
                <!-- ... (Bloc Photo inchangé) ... -->
            </div>

            <!-- COLONNE DROITE -->
            <div class="lg:col-span-2">

                <!-- TABS DE NAVIGATION -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-t-xl border-b-0">
                    <nav class="flex flex-wrap gap-0 px-4">
                        <button onclick="showTab('infos')" id="btn-infos" class="tab-btn px-4 sm:px-6 py-4 text-sm font-medium border-b-2 -mb-px transition-colors focus:outline-none" data-tab="infos">
                            Informations
                        </button>
                        <button onclick="showTab('security')" id="btn-security" class="tab-btn px-4 sm:px-6 py-4 text-sm font-medium border-b-2 -mb-px transition-colors focus:outline-none" data-tab="security">
                            Mot de passe
                        </button>
                        <!-- NOUVEL ONGLET SECRÉTARIAT -->
                        <button onclick="showTab('assistant')" id="btn-assistant" class="tab-btn px-4 sm:px-6 py-4 text-sm font-medium border-b-2 -mb-px transition-colors focus:outline-none" data-tab="assistant">
                            Secrétariat
                        </button>
                    </nav>
                </div>

                <!-- Tab Infos -->
                <div id="tab-infos" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-b-xl p-6 shadow-sm transition-colors">
                    <!-- ... (Ton formulaire Infos inchangé) ... -->
                </div>

                <!-- Tab Mot de passe -->
                <div id="tab-security" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-b-xl p-6 shadow-sm transition-colors" style="display:none;">
                    <!-- ... (Ton formulaire Mot de passe inchangé) ... -->
                </div>

                <!-- NOUVEAU TAB SECRÉTARIAT -->
                <div id="tab-assistant" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-b-xl p-6 shadow-sm transition-colors" style="display:none;">
                    <div class="max-w-xl">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Code d'accès Secrétariat</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">
                            Transmettez ce code à votre assistante. Elle en aura besoin pour se connecter à son portail dédié, gérer votre agenda et communiquer avec vous.
                        </p>
                        
                        <div class="flex items-center gap-4 mb-8">
                            <div class="bg-slate-50 dark:bg-slate-800 px-6 py-4 rounded-xl border border-slate-200 dark:border-slate-700 w-full sm:w-auto text-center">
                                <span class="text-2xl sm:text-3xl font-mono font-bold tracking-[0.2em] text-indigo-600 dark:text-indigo-400 select-all">
                                    <?= htmlspecialchars($current_cabinet_code) ?>
                                </span>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 dark:border-slate-800 pt-6">
                            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-2">Sécurité : Révoquer l'accès</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                                Si votre assistante quitte le cabinet ou si le code a été compromis, générez un nouveau code. L'ancien code sera immédiatement désactivé.
                            </p>
                            <form method="POST" onsubmit="return confirm('⚠️ Êtes-vous sûr ? L\'ancien code ne fonctionnera plus et l\'assistante actuelle sera déconnectée.');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="generate_cabinet_code">
                                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-5 py-2.5 rounded-lg text-sm font-bold transition-colors shadow-sm flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                    Générer un nouveau code
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<script nonce="<?= $nonce ?>">
// --- SCRIPT POUR LES TABS ---
function showTab(id) {
    document.getElementById('tab-infos').style.display    = 'none';
    document.getElementById('tab-security').style.display = 'none';
    document.getElementById('tab-assistant').style.display = 'none';

    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = '#64748b'; 
    });

    document.getElementById('tab-' + id).style.display = 'block';

    var activeBtn = document.getElementById('btn-' + id);
    if (activeBtn) {
        activeBtn.style.borderBottomColor = '#4f46e5'; 
        activeBtn.style.color = '#4f46e5';
    }
}
showTab('<?= htmlspecialchars($active_tab, ENT_QUOTES, 'UTF-8') ?>');

// --- POLLING NOTIFICATION CHAT ---
function checkUnreadMessages() {
    fetch('api_chat_unread.php')
    .then(r => r.json())
    .then(data => {
        const badge = document.getElementById('chat-badge-sidebar');
        if (badge) {
            if (data.unread > 0) {
                badge.textContent = '+' + data.unread;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }
    })
    .catch(e => console.error('Erreur Badge Chat:', e));
}
checkUnreadMessages();
setInterval(checkUnreadMessages, 5000);
</script>
</body>
</html>