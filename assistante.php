<?php
// --- 1. SÉCURITÉ DES SESSIONS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
include "connection.php";

// Génération du code à 10 caractères
function getCabinetCode($docid) {
    return strtoupper(substr(md5($docid . "PsySpaceCabinet2026"), 0, 10));
}

// --- 2. REQUÊTE AJAX POUR LA SYNCHRONISATION EN TEMPS RÉEL ---
if (isset($_GET['action']) && $_GET['action'] === 'fetch_agenda' && isset($_SESSION['sec_doc_id'])) {
    $doc_id = $_SESSION['sec_doc_id'];
    $stmt_apps = $conn->prepare("SELECT app_date, patient_name, patient_phone FROM appointments WHERE doctor_id = ? AND app_date >= CURDATE() ORDER BY app_date ASC LIMIT 10");
    $stmt_apps->bind_param("i", $doc_id);
    $stmt_apps->execute();
    $res_apps = $stmt_apps->get_result();
    
    if ($res_apps->num_rows === 0) {
        echo '<tr><td colspan="3" class="p-8 text-center text-slate-400 italic font-medium">Aucun rendez-vous à venir.</td></tr>';
    } else {
        while ($app = $res_apps->fetch_assoc()) {
            $date = date('d/m/Y', strtotime($app['app_date']));
            $heure = date('H:i', strtotime($app['app_date']));
            $patient = htmlspecialchars($app['patient_name']);
            $phone = htmlspecialchars($app['patient_phone']);
            
            echo "<tr class='hover:bg-slate-50 transition border-b border-slate-100 last:border-0'>
                    <td class='p-4'>
                        <div class='font-bold text-slate-800'>{$date}</div>
                        <div class='text-indigo-600 font-bold mt-1 bg-indigo-50 inline-block px-2 py-0.5 rounded text-xs'>{$heure}</div>
                    </td>
                    <td class='p-4 font-bold text-slate-700'>{$patient}</td>
                    <td class='p-4 text-slate-500 font-medium'>📞 {$phone}</td>
                  </tr>";
        }
    }
    $stmt_apps->close();
    exit(); // On arrête le script ici pour la requête AJAX
}

// --- 3. DÉCONNEXION ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: assistante.php");
    exit();
}

$alert_msg = "";
$alert_type = "";

// --- 4. ANTI BRUTE-FORCE & CONNEXION ---
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
                session_regenerate_id(true); // Sécurité : on change l'ID de session
                $_SESSION['sec_doc_id'] = $doc['docid'];
                $_SESSION['sec_doc_name'] = $doc['docname'];
                $_SESSION['csrf_sec_token'] = bin2hex(random_bytes(32)); // Jeton anti-spam
                $_SESSION['sec_login_attempts'] = 0;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $_SESSION['sec_login_attempts']++;
            $login_error = "Code d'accès invalide. (" . (5 - $_SESSION['sec_login_attempts']) . " essais restants)";
        }
    }
}

// --- 5. ÉCRAN DE CONNEXION (VERROUILLÉ) ---
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
        <style>body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }</style>
    </head>
    <body class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white max-w-md w-full rounded-3xl shadow-xl border border-slate-200 p-8 text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-2 bg-indigo-600"></div>
            <div class="w-20 h-20 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 shadow-inner">👩‍💼</div>
            <h2 class="text-2xl font-bold text-slate-800 mb-2">Espace Accueil</h2>
            <p class="text-slate-500 mb-8 text-sm">Entrez le code sécurisé à 10 caractères de votre cabinet.</p>
            
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
            <div class="mt-8 flex justify-center gap-4 text-xs text-slate-400 font-medium">
                <span>🔒 Connexion chiffrée</span>
                <span>⚡ Temps réel</span>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// --- 6. TRAITEMENT DE L'AJOUT D'UN RDV (SÉCURISÉ) ---
$doc_id = $_SESSION['sec_doc_id'];
$doc_name = $_SESSION['sec_doc_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_app'])) {
    
    // Vérification du POT DE MIEL (Anti-Robot)
    if (!empty($_POST['hp_field'])) { die("Spam détecté."); }
    
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_sec_token']) {
        $alert_msg = "Erreur de sécurité. Veuillez réessayer.";
        $alert_type = "error";
    } else {
        $pname = trim(strip_tags($_POST['pname']));
        $pphone = trim(strip_tags($_POST['pphone']));
        $date = $_POST['date'];
        $time = $_POST['time'];
        $app_datetime = $date . ' ' . $time . ':00';

        // Éviter les doublons exacts (même patient, même heure)
        $check = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND app_date = ?");
        $check->bind_param("is", $doc_id, $app_datetime);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $alert_msg = "Ce créneau est déjà pris !";
            $alert_type = "error";
        } else {
            // Patient existant ?
            $stmt = $conn->prepare("SELECT id FROM patients WHERE pphone = ? LIMIT 1");
            $stmt->bind_param("s", $pphone);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) { 
                $patient_id = $res->fetch_assoc()['id']; 
            } else {
                $stmt2 = $conn->prepare("INSERT INTO patients (pname, pphone) VALUES (?, ?)");
                $stmt2->bind_param("ss", $pname, $pphone);
                $stmt2->execute();
                $patient_id = $stmt2->insert_id;
                $stmt2->close();
            }
            $stmt->close();

            // Insertion
            $stmt3 = $conn->prepare("INSERT INTO appointments (doctor_id, patient_id, patient_name, patient_phone, app_date, app_type) VALUES (?, ?, ?, ?, ?, 'Consultation')");
            $stmt3->bind_param("iisss", $doc_id, $patient_id, $pname, $pphone, $app_datetime);
            if ($stmt3->execute()) { 
                $alert_msg = "Rendez-vous ajouté avec succès !"; 
                $alert_type = "success";
            } else { 
                $alert_msg = "Erreur base de données."; 
                $alert_type = "error";
            }
            $stmt3->close();
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Secrétariat | PsySpace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Librairie pour des alertes professionnelles -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }</style>
</head>
<body class="min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        
        <!-- HEADER -->
        <div class="flex flex-col md:flex-row items-center justify-between mb-8 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-2xl font-bold shadow-inner">🗓️</div>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-slate-800">Gestion de l'Agenda</h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <p class="text-slate-500 text-sm font-medium">Cabinet du <span class="text-indigo-600 font-bold">Dr. <?= htmlspecialchars($doc_name) ?></span></p>
                    </div>
                </div>
            </div>
            <a href="assistante.php?logout=1" class="bg-slate-100 text-slate-600 px-5 py-2.5 rounded-xl font-bold hover:bg-slate-200 transition flex items-center gap-2 text-sm">
                🔒 Verrouiller le poste
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- FORMULAIRE (GAUCHE) -->
            <div class="lg:col-span-1 bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-slate-200 h-fit sticky top-8">
                <h2 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2 border-b border-slate-100 pb-4">
                    <span class="bg-blue-50 text-blue-600 p-2 rounded-lg">📞</span> Ajouter un RDV
                </h2>
                
                <form method="POST" action="assistante.php" class="space-y-4">
                    <input type="hidden" name="add_app" value="1">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_sec_token'] ?>">
                    
                    <!-- HONEYPOT (Invisible pour l'humain, piège à robot) -->
                    <div style="display:none;"><input type="text" name="hp_field" value=""></div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Nom et Prénom</label>
                        <input type="text" name="pname" required placeholder="Ex: Jean Dupont" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3.5 outline-none focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 transition">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Numéro de Téléphone</label>
                        <input type="tel" name="pphone" required placeholder="Ex: 54 859 582" pattern="[0-9\s\-\+]+" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3.5 outline-none focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 transition">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Date</label>
                            <input type="date" name="date" required min="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3.5 outline-none focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 transition cursor-pointer">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Heure</label>
                            <input type="time" name="time" required step="900" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3.5 outline-none focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 transition cursor-pointer">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-slate-900 hover:bg-indigo-600 text-white font-bold py-4 rounded-xl transition-all shadow-md mt-4 flex justify-center items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Enregistrer
                    </button>
                </form>
            </div>

            <!-- AGENDA SYNCHRONISÉ (DROITE) -->
            <div class="lg:col-span-2 bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center justify-between mb-6 border-b border-slate-100 pb-4">
                    <h2 class="text-lg font-bold text-slate-800">Rendez-vous à venir</h2>
                    <span class="bg-indigo-50 text-indigo-600 px-3 py-1 rounded-md text-xs font-bold flex items-center gap-1">
                        <svg class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        Synchronisé
                    </span>
                </div>
                
                <div class="overflow-x-auto rounded-xl border border-slate-100">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-400 text-xs uppercase tracking-wider">
                                <th class="p-4 font-bold">Date & Heure</th>
                                <th class="p-4 font-bold">Patient</th>
                                <th class="p-4 font-bold">Contact</th>
                            </tr>
                        </thead>
                        <tbody id="agenda-tbody" class="bg-white">
                            <!-- Les données sont chargées en AJAX ici par le JS en bas de page -->
                            <tr><td colspan="3" class="p-8 text-center text-slate-400 italic">Chargement de l'agenda...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS MAGIQUES (Alertes + Synchro) -->
    <script>
        // 1. Affichage des notifications Toastify (Design Pro)
        <?php if ($alert_msg !== ""): ?>
            Toastify({
                text: "<?= $alert_msg ?>",
                duration: 4000,
                gravity: "top", 
                position: "right",
                style: {
                    background: "<?= $alert_type === 'success' ? '#10b981' : '#ef4444' ?>",
                    borderRadius: "10px",
                    fontWeight: "bold",
                    boxShadow: "0 4px 6px -1px rgba(0, 0, 0, 0.1)"
                }
            }).showToast();
        <?php endif; ?>

        // 2. Synchronisation de l'agenda en temps réel (Toutes les 15 secondes)
        function fetchAgenda() {
            fetch('assistante.php?action=fetch_agenda')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('agenda-tbody').innerHTML = html;
                })
                .catch(error => console.error('Erreur de synchro:', error));
        }

        // Charger tout de suite au démarrage
        fetchAgenda();
        
        // Puis recharger toutes les 15 secondes silencieusement
        setInterval(fetchAgenda, 15000);
    </script>
</body>
</html>