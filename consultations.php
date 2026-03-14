<?php
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }
include "connection.php";
if (!isset($conn) && isset($con)) { $conn = $con; }

$doc_id = (int)$_SESSION['id'];

// Filtrage par nom de patient
$filter_name = trim($_GET['patient_name'] ?? '');

// Requête adaptée : on récupère l'ID de la consultation pour le rapport
$query = "SELECT c.id as consultation_id, c.date_consultation, c.duree_minutes, 
                 p.pname as patient_name, p.pphone as patient_phone, p.id as patient_real_id
          FROM consultations c
          LEFT JOIN patients p ON c.patient_id = p.id
          WHERE c.doctor_id = ?";

if ($filter_name !== '') {
    $query .= " AND p.pname LIKE ?";
}
$query .= " ORDER BY c.date_consultation DESC";

$stmt = $conn->prepare($query);
if ($filter_name !== '') {
    $s = "%$filter_name%";
    $stmt->bind_param("is", $doc_id, $s);
} else {
    $stmt->bind_param("i", $doc_id);
}

$stmt->execute();
$result = $stmt->get_result();
$total  = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archives | PsySpace</title>
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
        .archive-card { transition: all 0.2s ease; border-left: 4px solid transparent; }
        .archive-card:hover { border-left-color: #4f46e5; background-color: #ffffff; box-shadow: 0 4px 12px -2px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="bg-slate-50 text-slate-700">

<div class="flex min-h-screen">

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
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.123-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Patients
            </a>
            <a href="agenda.php" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Agenda
            </a>
            <a href="consultations.php" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-white bg-slate-800/50">
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

    <main class="flex-1 ml-64 p-8">
        
        <div class="flex justify-between items-end mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Archives des Séances</h1>
                <p class="text-slate-500 text-sm mt-1">Historique des comptes-rendus générés.</p>
            </div>
            <div class="bg-white px-4 py-2 rounded-lg border border-slate-200 shadow-sm hidden md:block">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-widest block">Total</span>
                <span class="text-xl font-serif font-bold text-indigo-600"><?= $total ?> Séances</span>
            </div>
        </div>

        <form action="" method="GET" class="mb-8">
            <div class="flex gap-3">
                <div class="relative flex-1">
                    <input type="text" name="patient_name" value="<?= htmlspecialchars($filter_name) ?>"
                           placeholder="Rechercher par nom de patient..."
                           class="w-full px-4 py-2.5 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition shadow-sm">
                </div>
                <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-2.5 rounded-lg text-sm font-medium transition shadow-sm">
                    Rechercher
                </button>
            </div>
        </form>

        <div class="grid grid-cols-1 gap-3">
            <?php if ($total > 0): ?>
                <?php while ($row = $result->fetch_assoc()): 
                    $nom = !empty($row['patient_name']) ? $row['patient_name'] : 'Patient #' . $row['patient_real_id'];
                    $initiale = strtoupper(mb_substr($nom, 0, 1, 'UTF-8'));
                    $date_ts = strtotime($row['date_consultation']);
                ?>
                
                <div class="archive-card bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
                            <?= $initiale ?>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($nom) ?></h3>
                            <p class="text-[11px] text-slate-400 font-medium">
                                Séance du <?= date('d/m/Y', $date_ts) ?> à <?= date('H:i', $date_ts) ?>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="hidden sm:block text-right">
                            <span class="text-[10px] font-bold text-slate-400 uppercase block tracking-tighter">Durée</span>
                            <span class="text-xs font-semibold text-slate-600"><?= $row['duree_minutes'] ?> minutes</span>
                        </div>
                        <a href="patient_details.php?id=<?= $row['consultation_id'] ?>" 
                           class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-2 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            Afficher le compte-rendu
                        </a>
                    </div>
                </div>

                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-20 bg-white border border-dashed border-slate-300 rounded-xl">
                    <p class="text-slate-400 text-sm">Aucun historique trouvé.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>