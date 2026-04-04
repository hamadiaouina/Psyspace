<?php
session_start();
// Pas besoin d'inclure le header public ici, juste la connexion à la base de données
require_once __DIR__ . "/../connection.php";

// --- SÉCURITÉ : VÉRIFICATION DU BADGE ---
$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: "";
if (!isset($_COOKIE['psyspace_boss_key']) || $_COOKIE['psyspace_boss_key'] !== $admin_secret_key) {
    header("Location: ../index.php?error=unauthorized_action");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr" class="bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PsySpace - Admin Panel</title>
    <!-- On charge Tailwind directement via CDN pour l'admin -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md p-8 bg-white rounded-2xl shadow-xl border border-slate-100">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-slate-900">
                Psy<span class="text-indigo-600">Space</span> Admin
            </h1>
            <p class="text-sm text-slate-500 mt-2">Accès sécurisé réservé à la direction</p>
        </div>
        
        <!-- Affichage des erreurs -->
        <?php if (isset($_GET['error'])): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-lg text-sm font-medium">
                <?php 
                    if ($_GET['error'] == 'wrongpw') echo "Mot de passe incorrect.";
                    elseif ($_GET['error'] == 'noaccount') echo "Aucun compte trouvé avec cet email.";
                    elseif ($_GET['error'] == 'mailfail') echo "Erreur d'envoi du code de sécurité.";
                ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire -->
        <form action="login_action.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Adresse Email</label>
                <input type="email" name="email" required 
                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 outline-none transition-all">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Mot de passe</label>
                <input type="password" name="password" required 
                       class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600 outline-none transition-all">
            </div>
            
            <button type="submit" 
                    class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg transition-colors shadow-lg shadow-indigo-200">
                Déverrouiller le panel
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="../index.php" class="text-sm text-slate-500 hover:text-indigo-600 transition-colors">&larr; Retour à l'accueil public</a>
        </div>
    </div>

</body>
</html>