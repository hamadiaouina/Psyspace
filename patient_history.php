<?php
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }
include "connection.php";

$doc_id = $_SESSION['id'];
$patient_name = mysqli_real_escape_string($con, $_GET['name']);

// On récupère TOUTES les séances de ce patient spécifique pour ce docteur
$sql = "SELECT * FROM appointments 
        WHERE doctor_id = '$doc_id' 
        AND patient_name = '$patient_name' 
        ORDER BY app_date DESC";
$history = $con->query($sql);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique | <?php echo $patient_name; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-[#F8FAFC] p-8">

    <div class="max-w-4xl mx-auto">
        <div class="mb-12">
            <a href="patients_search.php" class="text-blue-600 font-bold text-sm uppercase tracking-widest hover:underline">← Retour à la recherche</a>
            <h1 class="text-4xl font-black uppercase italic tracking-tighter mt-4 text-slate-900">
                Parcours Clinique : <span class="text-blue-600"><?php echo $patient_name; ?></span>
            </h1>
            <p class="text-slate-400 font-medium italic mt-2">Retrouvez l'intégralité des échanges et diagnostics passés.</p>
        </div>

        <div class="relative border-l-4 border-slate-200 ml-4 pl-8 space-y-12">
            <?php if ($history->num_rows > 0): ?>
                <?php while($session = $history->fetch_assoc()): 
                    $is_future = strtotime($session['app_date']) > time();
                ?>
                    <div class="relative">
                        <div class="absolute -left-[42px] top-0 w-6 h-6 rounded-full border-4 border-[#F8FAFC] <?php echo $is_future ? 'bg-blue-500' : 'bg-slate-300'; ?>"></div>
                        
                        <div class="bg-white p-8 rounded-[2rem] shadow-sm border border-slate-100 hover:shadow-xl transition-all group">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <span class="text-[10px] font-black <?php echo $is_future ? 'text-blue-600' : 'text-slate-400'; ?> uppercase tracking-[0.2em]">
                                        <?php echo $is_future ? 'Prochaine Séance' : 'Séance Terminée'; ?>
                                    </span>
                                    <h3 class="text-2xl font-extrabold text-slate-800">
                                        <?php echo date('d F Y', strtotime($session['app_date'])); ?> 
                                        <span class="text-slate-300 font-light px-2">|</span> 
                                        <span class="text-blue-600"><?php echo date('H:i', strtotime($session['app_date'])); ?></span>
                                    </h3>
                                </div>
                                <div class="bg-slate-50 px-4 py-2 rounded-xl text-[10px] font-black text-slate-500 uppercase">
                                    ID #<?php echo $session['id']; ?>
                                </div>
                            </div>

                            <p class="text-slate-500 leading-relaxed mb-6 italic">
                                <?php 
                                    // Simulation d'un aperçu de note (On ajoutera le champ 'notes' plus tard)
                                    echo "Consultation standard de suivi thérapeutique. Analyse des progrès comportementaux et gestion du stress environnemental.";
                                ?>
                            </p>

                            <div class="flex gap-4">
                                <a href="patient_details.php?id=<?php echo $session['id']; ?>" class="flex-1 bg-slate-900 text-white text-center py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-blue-600 transition">
                                    Consulter le compte-rendu complet
                                </a>
                                <button class="px-6 py-4 bg-blue-50 text-blue-600 rounded-2xl font-black text-[10px] uppercase hover:bg-blue-100 transition">
                                    Imprimer 🖨️
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-slate-400 font-bold italic">Aucune séance enregistrée pour ce patient.</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>