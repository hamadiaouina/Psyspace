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

$stmt = $conn->prepare("SELECT docid,docemail,docname,status,photo,specialty,order_num,bio,docphone FROM doctor WHERE docid=? LIMIT 1");
$stmt->bind_param("i", $doctor_id); $stmt->execute();
$doc = $stmt->get_result()->fetch_assoc(); $stmt->close();

$stat_patients      = (int)$conn->query("SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE doctor_id=$doctor_id")->fetch_assoc()['c'];
$stat_consultations = (int)$conn->query("SELECT COUNT(*) c FROM consultations WHERE doctor_id=$doctor_id")->fetch_assoc()['c'];

$active_tab = 'infos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VÉRIFICATION CSRF CRITIQUE POUR TOUS LES FORMULAIRES
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "Jeton de sécurité invalide. Veuillez réessayer.";
    } else {
        $action = $_POST['action'] ?? '';

        // --- MISE À JOUR DES INFOS ---
        if ($action === 'update_profile') {
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
                    // Renouvellement du jeton CSRF après succès
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
                else { $error_msg = "Erreur lors de la mise à jour."; }
                $st->close();
                
                $s2 = $conn->prepare("SELECT * FROM doctor WHERE docid=? LIMIT 1");
                $s2->bind_param("i", $doctor_id); $s2->execute(); $doc = $s2->get_result()->fetch_assoc(); $s2->close();
            }
        }

        // --- CHANGEMENT DE MOT DE PASSE SÉCURISÉ ---
        if ($action === 'change_password') {
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

        // --- UPLOAD SÉCURISÉ DE PHOTO (ANTI-SHELL) ---
        if ($action === 'upload_photo' && isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $file = $_FILES['photo'];

            if ($file['size'] > 3 * 1024 * 1024) {
                $error_msg = "Image trop lourde (max 3 Mo).";
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $real_mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $allowed_mimes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                if (!array_key_exists($real_mime, $allowed_mimes)) {
                    $error_msg = "Format non supporté ou fichier falsifié. (JPG, PNG, WebP uniquement).";
                } else {
                    $ext = $allowed_mimes[$real_mime];
                    $fn  = 'doc_' . $doctor_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dir = 'uploads/avatars/';
                    
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    
                    if (move_uploaded_file($file['tmp_name'], $dir.$fn)) {
                        if (!empty($doc['photo']) && file_exists($doc['photo'])) {
                            @unlink($doc['photo']);
                        }
                        $p  = $dir.$fn;
                        $st = $conn->prepare("UPDATE doctor SET photo=? WHERE docid=?");
                        $st->bind_param("si", $p, $doctor_id); 
                        $st->execute(); 
                        $st->close();
                        $success_msg   = "Photo de profil mise à jour avec succès.";
                        $doc['photo']  = $p;
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    } else { 
                        $error_msg = "Erreur lors de l'enregistrement sur le serveur."; 
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
        .sidebar-link.active { background: rgba(79,70,229,0.25); color: #a5b4fc; font-weight: 600; border-left: 2px solid #6366f1; padding-left: calc(1rem - 2px); }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-950 text-slate-700 dark:text-slate-300 transition-colors duration-300">
<div class="flex min-h-screen relative">

    <!-- SIDEBAR MOBILE OVERLAY -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-gradient-to-b from-slate-900 to-indigo-950 border-r border-white/5 flex flex-col fixed h-full z-50 transition-transform transform -translate-x-full lg:translate-x-0">
        <div class="p-6 border-b border-white/5 flex justify-between items-center">
            <a href="dashboard.php" class="flex items-center gap-2">
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
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Patients
            </a>
            <a href="agenda.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Agenda
            </a>
            <a href="consultations.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-white/5 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                Archives
            </a>
        </nav>
        <div class="p-4 border-t border-white/5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold border-2 border-indigo-200 overflow-hidden">
                    <?php if(!empty($doc_photo) && file_exists($doc_photo)): ?>
                        <img src="<?= htmlspecialchars($doc_photo) ?>?v=<?= time() ?>" class="w-full h-full object-cover">
                    <?php else: ?><?= $doc_initial ?><?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <a href="profile.php" class="text-sm font-bold text-white truncate hover:text-indigo-300 transition-colors block">Dr. <?= htmlspecialchars(ucwords(strtolower($doc_name))) ?></a>
                </div>
                <a href="logout.php" class="text-slate-500 hover:text-red-400 p-2" title="Déconnexion">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="flex-1 lg:ml-64 p-4 md:p-8 w-full">
        <div class="flex flex-wrap justify-between items-center mb-8 gap-4">
            <div class="flex items-center gap-4">
                <button id="open-sidebar" class="lg:hidden p-2 text-slate-500 bg-white dark:bg-slate-800 rounded-md border border-slate-200 dark:border-slate-700 shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Mon Profil</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Gérez vos informations personnelles et paramètres de sécurité.</p>
                </div>
            </div>
            
            <!-- Toggle Dark Mode -->
            <button id="theme-toggle" class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-all border border-transparent dark:border-slate-700">
                <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 011.414-1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"></path></svg>
            </button>
        </div>

        <?php if($success_msg): ?>
        <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 px-4 py-3 rounded-lg mb-6 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?= $success_msg ?>
        </div>
        <?php elseif($error_msg): ?>
        <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 px-4 py-3 rounded-lg mb-6 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?= $error_msg ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- COLONNE GAUCHE -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm overflow-hidden transition-colors">
                    <div class="p-6 flex flex-col items-center text-center">
                        <div class="relative mb-4">
                            <div class="w-28 h-28 rounded-full overflow-hidden border-4 border-slate-100 dark:border-slate-800 shadow-sm bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                                <?php if(!empty($doc_photo) && file_exists($doc_photo)): ?>
                                    <img src="<?= htmlspecialchars($doc_photo) ?>?v=<?= time() ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="text-4xl font-bold text-slate-400 dark:text-slate-500"><?= $doc_initial ?></span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data" id="photoForm">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="upload_photo">
                                <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="document.getElementById('photoForm').submit()">
                            </form>
                            <button type="button" onclick="document.getElementById('photoInput').click()" class="absolute bottom-0 right-0 w-9 h-9 bg-indigo-600 rounded-full flex items-center justify-center text-white hover:bg-indigo-700 shadow-md border-2 border-white dark:border-slate-900">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </button>
                        </div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Dr. <?= $doc_name ?></h2>
                        <span class="mt-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?= $doc_status === 'active' ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' : 'bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $doc_status === 'active' ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
                            <?= $doc_status === 'active' ? 'Compte Actif' : 'En attente' ?>
                        </span>
                    </div>
                    <div class="border-t border-slate-100 dark:border-slate-800 grid grid-cols-2 divide-x divide-slate-100 dark:divide-slate-800">
                        <div class="p-4 text-center">
                            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400"><?= $stat_patients ?></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Patients</p>
                        </div>
                        <div class="p-4 text-center">
                            <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400"><?= $stat_consultations ?></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Séances</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 shadow-sm transition-colors">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-3">Coordonnées</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span class="truncate"><?= $doc_email ?></span>
                        </div>
                        <?php if($doc_phone): ?>
                        <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                            <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span><?= $doc_phone ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- COLONNE DROITE -->
            <div class="lg:col-span-2">

                <!-- Tabs -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-t-xl border-b-0 transition-colors">
                    <nav class="flex gap-0 px-4">
                        <button onclick="showTab('infos')" id="btn-infos"
                            class="tab-btn px-6 py-4 text-sm font-medium border-b-2 -mb-px transition-colors focus:outline-none"
                            data-tab="infos">
                            Informations
                        </button>
                        <button onclick="showTab('security')" id="btn-security"
                            class="tab-btn px-6 py-4 text-sm font-medium border-b-2 -mb-px transition-colors focus:outline-none"
                            data-tab="security">
                            Mot de passe
                        </button>
                    </nav>
                </div>

                <!-- Tab Infos -->
                <div id="tab-infos" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-b-xl p-6 shadow-sm transition-colors">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Nom complet</label>
                                <input type="text" name="docname" value="<?= $doc_name ?>" required class="w-full bg-transparent border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Email</label>
                                <input type="email" name="docemail" value="<?= $doc_email ?>" required class="w-full bg-transparent border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Téléphone</label>
                                <input type="tel" name="docphone" value="<?= $doc_phone ?>" class="w-full bg-transparent border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Numéro d'ordre (ADELI)</label>
                                <input type="text" name="order_num" value="<?= $doc_order ?>" class="w-full bg-transparent border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:text-white">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Biographie</label>
                                <textarea name="bio" rows="4" class="w-full bg-transparent border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:text-white" placeholder="Présentation brève..."><?= $doc_bio ?></textarea>
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-slate-100 dark:border-slate-800 pt-4 mt-2">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-bold transition-colors shadow-sm">Sauvegarder</button>
                        </div>
                    </form>
                </div>

                <!-- Tab Mot de passe -->
                <div id="tab-security" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-b-xl p-6 shadow-sm transition-colors" style="display:none;">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="max-w-md space-y-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Mot de passe actuel</label>
                                <input type="password" name="old_password" required class="w-full bg-transparent border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Nouveau mot de passe</label>
                                <input type="password" name="new_password" minlength="8" required class="w-full bg-transparent border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2">Confirmer le mot de passe</label>
                                <input type="password" name="confirm_password" minlength="8" required class="w-full bg-transparent border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition dark:text-white">
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-slate-100 dark:border-slate-800 pt-4 mt-6">
                            <button type="submit" class="bg-slate-800 hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600 text-white px-6 py-2.5 rounded-lg text-sm font-bold transition-colors shadow-sm">Mettre à jour le mot de passe</button>
                        </div>
                    </form>
                </div>

            </div><!-- fin col droite -->
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

if(openBtn) openBtn.addEventListener('click', toggleSidebar);
if(closeBtn) closeBtn.addEventListener('click', toggleSidebar);
if(overlay) overlay.addEventListener('click', toggleSidebar);

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

// Gestion des Tabs
function showTab(id) {
    document.getElementById('tab-infos').style.display    = 'none';
    document.getElementById('tab-security').style.display = 'none';

    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = '#64748b'; // text-slate-500
    });

    document.getElementById('tab-' + id).style.display = 'block';

    var activeBtn = document.getElementById('btn-' + id);
    if (activeBtn) {
        activeBtn.style.borderBottomColor = '#4f46e5'; // text-indigo-600
        activeBtn.style.color = '#4f46e5';
    }
}
showTab('<?= htmlspecialchars($active_tab, ENT_QUOTES, 'UTF-8') ?>');
</script>
</body>
</html>