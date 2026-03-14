<?php
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }
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
    $action = $_POST['action'] ?? '';

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
            if ($st->execute()) { $success_msg = "Profil mis à jour avec succès."; $_SESSION['nom'] = $docname; }
            else { $error_msg = "Erreur lors de la mise à jour."; }
            $st->close();
            $s2 = $conn->prepare("SELECT * FROM doctor WHERE docid=? LIMIT 1");
            $s2->bind_param("i", $doctor_id); $s2->execute(); $doc = $s2->get_result()->fetch_assoc(); $s2->close();
        }
    }

    if ($action === 'change_password') {
        $active_tab = 'security';
        $old  = $_POST['old_password']     ?? '';
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';

        $st = $conn->prepare("SELECT docpassword FROM doctor WHERE docid=? LIMIT 1");
        $st->bind_param("i", $doctor_id); $st->execute(); $hr = $st->get_result()->fetch_assoc(); $st->close();

        if (empty($old) || empty($new) || empty($conf))  { $error_msg = "Tous les champs sont requis."; }
        elseif ($new !== $conf)                           { $error_msg = "Les mots de passe ne correspondent pas."; }
        elseif (strlen($new) < 8)                         { $error_msg = "Minimum 8 caractères requis."; }
        elseif (!password_verify($old, $hr['docpassword'])){ $error_msg = "Mot de passe actuel incorrect."; }
        else {
            $h  = password_hash($new, PASSWORD_BCRYPT);
            $st = $conn->prepare("UPDATE doctor SET docpassword=? WHERE docid=?");
            $st->bind_param("si", $h, $doctor_id);
            if ($st->execute()) { $success_msg = "Mot de passe modifié avec succès."; }
            else { $error_msg = "Erreur lors du changement."; }
            $st->close();
        }
    }

    if ($action === 'upload_photo' && isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $file    = $_FILES['photo'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!in_array($file['type'], $allowed))        { $error_msg = "Format non supporté (JPG, PNG, WebP)."; }
        elseif ($file['size'] > 3*1024*1024)           { $error_msg = "Image trop lourde (max 3 Mo)."; }
        else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fn  = 'doc_'.$doctor_id.'_'.time().'.'.$ext;
            $dir = 'uploads/avatars/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $dir.$fn)) {
                if (!empty($doc['photo']) && file_exists($doc['photo'])) @unlink($doc['photo']);
                $p  = $dir.$fn;
                $st = $conn->prepare("UPDATE doctor SET photo=? WHERE docid=?");
                $st->bind_param("si", $p, $doctor_id); $st->execute(); $st->close();
                $success_msg   = "Photo mise à jour.";
                $doc['photo']  = $p;
            } else { $error_msg = "Erreur lors de l'upload."; }
        }
    }
}

$doc_name      = htmlspecialchars($doc['docname']   ?? '');
$doc_email     = htmlspecialchars($doc['docemail']  ?? '');
$doc_phone     = htmlspecialchars($doc['docphone']  ?? '');
$doc_specialty = htmlspecialchars($doc['specialty'] ?? '');
$doc_order     = htmlspecialchars($doc['order_num'] ?? '');
$doc_bio       = htmlspecialchars($doc['bio']       ?? '');
$doc_photo     = $doc['photo']  ?? '';
$doc_status    = $doc['status'] ?? 'pending';
$doc_initial   = strtoupper(mb_substr($doc['docname'] ?? 'D', 0, 1, 'UTF-8'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil | PsySpace</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','sans-serif'] } } } }</script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .sidebar-link { transition: all 0.2s ease; }
    </style>
</head>
<body class="bg-slate-50 text-slate-700">
<div class="flex min-h-screen">

    <!-- SIDEBAR -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col fixed h-full z-50">
        <div class="p-6 border-b border-slate-800">
            <a href="dashboard.php" class="flex items-center gap-2">
                        <img src="assets/images/logo.png" alt="PsySpace Logo" class="h-8 w-8 rounded-lg object-cover">

                <span class="text-lg font-bold text-white">PsySpace</span>
            </a>
        </div>
        <nav class="flex-1 p-4 space-y-1">
            <a href="dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
            <a href="patients_search.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
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
    <main class="flex-1 ml-64 p-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Mon Profil</h1>
            <p class="text-slate-500 text-sm mt-1">Gérez vos informations personnelles et paramètres de sécurité.</p>
        </div>

        <?php if($success_msg): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-6 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?= $success_msg ?>
        </div>
        <?php elseif($error_msg): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-6 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?= $error_msg ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- COLONNE GAUCHE -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6 flex flex-col items-center text-center">
                        <div class="relative mb-4">
                            <div class="w-28 h-28 rounded-full overflow-hidden border-4 border-slate-100 shadow-sm bg-slate-50 flex items-center justify-center">
                                <?php if(!empty($doc_photo) && file_exists($doc_photo)): ?>
                                    <img src="<?= htmlspecialchars($doc_photo) ?>?v=<?= time() ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="text-4xl font-bold text-slate-400"><?= $doc_initial ?></span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data" id="photoForm">
                                <input type="hidden" name="action" value="upload_photo">
                                <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="document.getElementById('photoForm').submit()">
                            </form>
                            <button type="button" onclick="document.getElementById('photoInput').click()" class="absolute bottom-0 right-0 w-9 h-9 bg-indigo-600 rounded-full flex items-center justify-center text-white hover:bg-indigo-700 shadow-md border-2 border-white">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </button>
                        </div>
                        <h2 class="text-xl font-bold text-slate-900">Dr. <?= $doc_name ?></h2>
                        <span class="mt-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold <?= $doc_status === 'active' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $doc_status === 'active' ? 'bg-emerald-500' : 'bg-amber-500' ?>"></span>
                            <?= $doc_status === 'active' ? 'Compte Actif' : 'En attente' ?>
                        </span>
                    </div>
                    <div class="border-t border-slate-100 grid grid-cols-2 divide-x divide-slate-100">
                        <div class="p-4 text-center">
                            <p class="text-2xl font-bold text-indigo-600"><?= $stat_patients ?></p>
                            <p class="text-xs text-slate-500 font-medium">Patients</p>
                        </div>
                        <div class="p-4 text-center">
                            <p class="text-2xl font-bold text-indigo-600"><?= $stat_consultations ?></p>
                            <p class="text-xs text-slate-500 font-medium">Séances</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-3">Coordonnées</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center gap-2 text-slate-600">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span class="truncate"><?= $doc_email ?></span>
                        </div>
                        <?php if($doc_phone): ?>
                        <div class="flex items-center gap-2 text-slate-600">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span><?= $doc_phone ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- COLONNE DROITE -->
            <div class="lg:col-span-2">

                <!-- Tabs -->
                <div class="bg-white border border-slate-200 rounded-t-xl border-b-0">
                    <nav class="flex gap-0 px-4">
                        <button onclick="showTab('infos')" id="btn-infos"
                            class="tab-btn px-6 py-4 text-sm font-medium border-b-2 -mb-px transition-colors"
                            data-tab="infos">
                            Informations
                        </button>
                        <button onclick="showTab('security')" id="btn-security"
                            class="tab-btn px-6 py-4 text-sm font-medium border-b-2 -mb-px transition-colors"
                            data-tab="security">
                            Mot de passe
                        </button>
                    </nav>
                </div>

                <!-- Tab Infos -->
                <div id="tab-infos" class="bg-white border border-slate-200 rounded-b-xl p-6 shadow-sm">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nom complet</label>
                                <input type="text" name="docname" value="<?= $doc_name ?>" required class="w-full border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email</label>
                                <input type="email" name="docemail" value="<?= $doc_email ?>" required class="w-full border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Téléphone</label>
                                <input type="tel" name="docphone" value="<?= $doc_phone ?>" class="w-full border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Numéro d'ordre (ADELI)</label>
                                <input type="text" name="order_num" value="<?= $doc_order ?>" class="w-full border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Biographie</label>
                                <textarea name="bio" rows="4" class="w-full border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition" placeholder="Présentation brève..."><?= $doc_bio ?></textarea>
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-slate-100 pt-4">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-lg text-sm font-bold transition-colors shadow-sm">Sauvegarder</button>
                        </div>
                    </form>
                </div>

                <!-- Tab Mot de passe -->
                <div id="tab-security" class="bg-white border border-slate-200 rounded-b-xl p-6 shadow-sm" style="display:none;">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="max-w-md space-y-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Mot de passe actuel</label>
                                <input type="password" name="old_password" required class="w-full border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nouveau mot de passe</label>
                                <input type="password" name="new_password" required class="w-full border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Confirmer le mot de passe</label>
                                <input type="password" name="confirm_password" required class="w-full border border-slate-200 rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none transition">
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-slate-100 pt-4 mt-6">
                            <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white px-6 py-2.5 rounded-lg text-sm font-bold transition-colors shadow-sm">Mettre à jour le mot de passe</button>
                        </div>
                    </form>
                </div>

            </div><!-- fin col droite -->
        </div>
    </main>
</div>

<script>
function showTab(id) {
    // Cacher tous les contenus
    document.getElementById('tab-infos').style.display    = 'none';
    document.getElementById('tab-security').style.display = 'none';

    // Réinitialiser tous les boutons
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.style.borderBottomColor = 'transparent';
        btn.style.color = '#64748b';
    });

    // Afficher le bon contenu
    document.getElementById('tab-' + id).style.display = 'block';

    // Activer le bon bouton
    var activeBtn = document.getElementById('btn-' + id);
    if (activeBtn) {
        activeBtn.style.borderBottomColor = '#4f46e5';
        activeBtn.style.color = '#4f46e5';
    }
}

// Au chargement, afficher le bon onglet
showTab('<?= $active_tab ?>');
</script>
</body>
</html>