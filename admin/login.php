<?php
// --- 1. CONFIGURATION DE SÉCURITÉ ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
    ini_set('session.cookie_secure', '1');
}

session_start();
require_once __DIR__ . "/../connection.php";

// --- 2. SÉCURITÉ : VÉRIFICATION DU BADGE INVISIBLE ---
$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: "";
if (empty($admin_secret_key) || !isset($_COOKIE['psyspace_boss_key']) || $_COOKIE['psyspace_boss_key'] !== $admin_secret_key) {
    // Si pas de badge, on le renvoie à l'accueil comme s'il n'avait rien vu.
    header("Location: ../index.php");
    exit();
}

// --- 3. GÉNÉRATION DU JETON CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PsySpace - Admin Panel</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } }
        };
        // Auto Dark Mode
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="flex items-center justify-center min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-white transition-colors duration-300">

    <div class="w-full max-w-md p-8 bg-white dark:bg-slate-900 rounded-2xl shadow-xl dark:shadow-rose-900/10 border border-slate-100 dark:border-slate-800 transition-colors">
        
        <div class="text-center mb-8">
            <div class="w-12 h-12 mx-auto bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 rounded-xl flex items-center justify-center mb-4 shadow-inner">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                Psy<span class="text-rose-600 dark:text-rose-500">Space</span> Admin
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 font-medium">Accès sécurisé réservé à la direction</p>
        </div>
        
        <!-- Affichage des erreurs -->
        <?php if (isset($_GET['error'])): ?>
            <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 text-red-700 dark:text-red-400 rounded-r-lg text-sm font-medium flex items-center gap-3 shadow-sm">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?php 
                    if ($_GET['error'] == 'wrongpw') echo "Mot de passe incorrect.";
                    elseif ($_GET['error'] == 'noaccount') echo "Aucun compte trouvé avec cet email.";
                    elseif ($_GET['error'] == 'mailfail') echo "Erreur d'envoi du code de sécurité.";
                    elseif ($_GET['error'] == 'csrf') echo "Erreur de sécurité (CSRF). Réessayez.";
                    else echo "Une erreur est survenue.";
                ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire -->
        <form action="login_action.php" method="POST" class="space-y-5">
            <!-- SÉCURITÉ : JETON CSRF -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5 uppercase tracking-wide text-[10px]">Adresse Email</label>
                <input type="email" name="email" required placeholder="admin@psyspace.fr"
                       class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none transition-all dark:text-white">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-1.5 uppercase tracking-wide text-[10px]">Mot de passe</label>
                <input type="password" name="password" required placeholder="••••••••"
                       class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none transition-all dark:text-white">
            </div>
            
            <button type="submit" 
                    class="w-full py-3.5 px-4 bg-slate-900 dark:bg-rose-600 hover:bg-slate-800 dark:hover:bg-rose-700 text-white font-bold rounded-xl transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                Déverrouiller le panel
            </button>
        </form>
        
        <div class="mt-8 text-center border-t border-slate-100 dark:border-slate-800 pt-6">
            <a href="../index.php" class="text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 transition-colors flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Retour à l'accueil public
            </a>
        </div>
    </div>

</body>
</html>