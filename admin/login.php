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
    <title>Accès Sécurisé | PsySpace Admin</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { 
                extend: { 
                    fontFamily: { 
                        sans: ['IBM Plex Sans', 'sans-serif'],
                        mono: ['IBM Plex Mono', 'monospace']
                    },
                    colors: {
                        brand: '#3d52a0',
                        brand_hover: '#2d3d80',
                        dark_bg: '#0c0c12',
                        dark_surface: '#14141e',
                        dark_border: '#26263a'
                    }
                } 
            }
        };
        // Auto Dark Mode
        if (localStorage.getItem('psyadmin_dark') === '1' || (!('psyadmin_dark' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style>
        body { font-family: 'IBM Plex Sans', sans-serif; }
        .font-mono { font-family: 'IBM Plex Mono', monospace; }
        .glow { box-shadow: 0 0 40px rgba(61, 82, 160, 0.15); }
        .dark .glow { box-shadow: 0 0 40px rgba(61, 82, 160, 0.08); }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen bg-slate-50 dark:bg-dark_bg text-slate-900 dark:text-slate-200 transition-colors duration-300 p-4">

    <div class="w-full max-w-[400px]">
        <!-- Header Gateway -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-white dark:bg-dark_surface border border-slate-200 dark:border-dark_border rounded-xl shadow-sm mb-5 relative">
                <div class="absolute inset-0 border border-brand/30 rounded-xl animate-pulse"></div>
                <svg class="w-6 h-6 text-brand dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </div>
            <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">Passerelle d'Administration</h1>
            <p class="text-[11px] uppercase tracking-[0.15em] font-mono text-slate-500 dark:text-slate-400 mt-2">Authentification Requise</p>
        </div>

        <!-- Box Login -->
        <div class="bg-white dark:bg-dark_surface rounded-xl shadow-2xl dark:shadow-none border border-slate-200 dark:border-dark_border p-8 glow relative overflow-hidden">
            <!-- Ligne supérieure de décoration -->
            <div class="absolute top-0 left-0 w-full h-1 bg-brand"></div>

            <!-- Affichage des erreurs -->
            <?php if (isset($_GET['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-lg text-[13px] text-red-600 dark:text-red-400 flex items-start gap-3">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span class="font-medium leading-tight">
                    <?php 
                        if ($_GET['error'] == 'wrongpw') echo "Identifiants incorrects.";
                        elseif ($_GET['error'] == 'noaccount') echo "Accès refusé. Compte inexistant.";
                        elseif ($_GET['error'] == 'mailfail') echo "Erreur système : Échec de l'envoi du code.";
                        elseif ($_GET['error'] == 'csrf') echo "Jeton de sécurité expiré. Veuillez réessayer.";
                        else echo "Une erreur de sécurité est survenue.";
                    ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Formulaire -->
            <form action="login_action.php" method="POST" class="space-y-5">
                <!-- SÉCURITÉ : JETON CSRF -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-wider font-mono">Identifiant</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <input type="email" name="email" required placeholder="admin@psyspace.fr"
                               class="w-full pl-10 pr-4 py-2.5 text-[13px] bg-slate-50 dark:bg-dark_bg border border-slate-200 dark:border-dark_border rounded-lg focus:ring-1 focus:ring-brand focus:border-brand dark:focus:ring-indigo-500 dark:focus:border-indigo-500 outline-none transition-colors dark:text-white placeholder-slate-400 dark:placeholder-slate-600">
                    </div>
                </div>
                
                <div>
                    <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 mb-2 uppercase tracking-wider font-mono">Mot de passe</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        </div>
                        <input type="password" name="password" required placeholder="••••••••"
                               class="w-full pl-10 pr-4 py-2.5 text-[13px] bg-slate-50 dark:bg-dark_bg border border-slate-200 dark:border-dark_border rounded-lg focus:ring-1 focus:ring-brand focus:border-brand dark:focus:ring-indigo-500 dark:focus:border-indigo-500 outline-none transition-colors dark:text-white placeholder-slate-400 dark:placeholder-slate-600 font-mono">
                    </div>
                </div>
                
                <div class="pt-2">
                    <button type="submit" 
                            class="w-full py-2.5 px-4 bg-brand hover:bg-brand_hover text-white text-[13px] font-medium rounded-lg transition-colors flex items-center justify-center gap-2">
                        Connexion
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Footer -->
        <div class="mt-6 text-center">
            <a href="../index.php" class="text-[11px] font-mono text-slate-400 dark:text-slate-500 hover:text-brand dark:hover:text-indigo-400 transition-colors inline-flex items-center gap-1.5 uppercase tracking-wide">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Quitter l'espace sécurisé
            </a>
        </div>
    </div>

</body>
</html>