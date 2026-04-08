<?php
session_start();
include "connection.php"; // Ta connexion à la base de données

$msg = "";

// 1. L'ASSISTANTE AJOUTE UN RENDEZ-VOUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_app'])) {
    $doc_id = $_POST['doc_id'];
    $pname = trim($_POST['pname']);
    $pphone = trim($_POST['pphone']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $app_datetime = $date . ' ' . $time . ':00';

    // Chercher si le patient existe, sinon le créer
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

    // Ajouter le rendez-vous
    $stmt3 = $conn->prepare("INSERT INTO appointments (doctor_id, patient_id, patient_name, patient_phone, app_date, app_type) VALUES (?, ?, ?, ?, ?, 'Consultation')");
    $stmt3->bind_param("iisss", $doc_id, $patient_id, $pname, $pphone, $app_datetime);
    
    if ($stmt3->execute()) {
        $msg = "✅ Rendez-vous ajouté avec succès pour le Dr !";
    } else {
        $msg = "❌ Erreur lors de l'ajout.";
    }
    $stmt3->close();
}

// 2. RÉCUPÉRER LA LISTE DES DOCTEURS POUR L'ASSISTANTE
$doctors = [];
$res_docs = $conn->query("SELECT docid, docname FROM doctor WHERE status = 'active'");
while ($row = $res_docs->fetch_assoc()) {
    $doctors[] = $row;
}

// 3. RÉCUPÉRER LES RENDEZ-VOUS À VENIR (Pour l'affichage)
$appointments = [];
$res_apps = $conn->query("SELECT a.app_date, a.patient_name, a.patient_phone, d.docname 
                          FROM appointments a 
                          JOIN doctor d ON a.doctor_id = d.docid 
                          WHERE a.app_date >= CURDATE() 
                          ORDER BY a.app_date ASC LIMIT 10");
while ($row = $res_apps->fetch_assoc()) {
    $appointments[] = $row;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Secrétariat | PsySpace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen p-8">

    <div class="max-w-6xl mx-auto">
        <!-- Header Assistante -->
        <div class="flex items-center justify-between mb-8 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-pink-100 text-pink-600 rounded-full flex items-center justify-center text-xl font-bold">👩‍💼</div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Espace Secrétariat</h1>
                    <p class="text-slate-500 text-sm">Gestion des agendas du cabinet PsySpace</p>
                </div>
            </div>
            <a href="index.php" class="text-blue-600 font-semibold hover:underline">Retour au site</a>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-xl text-sm font-bold <?php echo strpos($msg, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- COLONNE GAUCHE : AJOUTER UN RDV -->
            <div class="md:col-span-1 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">📞 Nouvel Appel</h2>
                
                <form method="POST" action="assistante.php" class="space-y-4">
                    <input type="hidden" name="add_app" value="1">
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Pour quel Docteur ?</label>
                        <select name="doc_id" required class="w-full border border-slate-300 rounded-xl p-3 bg-slate-50 outline-none focus:border-pink-500 font-medium">
                            <option value="">-- Choisir le médecin --</option>
                            <?php foreach ($doctors as $doc): ?>
                                <option value="<?= $doc['docid'] ?>">Dr. <?= htmlspecialchars($doc['docname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Patient (Nom & Prénom)</label>
                        <input type="text" name="pname" required placeholder="Ex: Jean Dupont" class="w-full border border-slate-300 rounded-xl p-3 outline-none focus:border-pink-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Téléphone</label>
                        <input type="tel" name="pphone" required placeholder="Ex: 54 859 582" class="w-full border border-slate-300 rounded-xl p-3 outline-none focus:border-pink-500">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Date</label>
                            <input type="date" name="date" required min="<?= date('Y-m-d') ?>" class="w-full border border-slate-300 rounded-xl p-3 outline-none focus:border-pink-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Heure</label>
                            <input type="time" name="time" required class="w-full border border-slate-300 rounded-xl p-3 outline-none focus:border-pink-500">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-pink-600 hover:bg-pink-700 text-white font-bold py-3.5 rounded-xl transition shadow-sm mt-4">
                        + Enregistrer le RDV
                    </button>
                </form>
            </div>

            <!-- COLONNE DROITE : L'AGENDA GLOBAL -->
            <div class="md:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h2 class="text-lg font-bold text-slate-800 mb-4">🗓️ Prochains Rendez-vous (Tout le cabinet)</h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                                <th class="p-4 rounded-tl-xl">Date & Heure</th>
                                <th class="p-4">Patient</th>
                                <th class="p-4">Téléphone</th>
                                <th class="p-4 rounded-tr-xl">Docteur</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($appointments)): ?>
                                <tr><td colspan="4" class="p-6 text-center text-slate-400 italic">Aucun rendez-vous prévu.</td></tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $app): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="p-4 font-bold text-slate-800">
                                            <?= date('d/m/Y', strtotime($app['app_date'])) ?> <br>
                                            <span class="text-pink-600"><?= date('H:i', strtotime($app['app_date'])) ?></span>
                                        </td>
                                        <td class="p-4 font-medium text-slate-700"><?= htmlspecialchars($app['patient_name']) ?></td>
                                        <td class="p-4 text-slate-500"><?= htmlspecialchars($app['patient_phone']) ?></td>
                                        <td class="p-4"><span class="bg-blue-100 text-blue-700 py-1 px-3 rounded-lg text-xs font-bold">Dr. <?= htmlspecialchars($app['docname']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</body>
</html>