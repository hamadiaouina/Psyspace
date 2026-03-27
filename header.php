<?php
// 1. Génération du nonce unique
$nonce = base64_encode(random_bytes(16));

// 2. Détection d'environnement (plus robuste)
$is_localhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) 
               || $_SERVER['SERVER_NAME'] === 'localhost'
               || (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false));

// 3. Headers de sécurité
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Construction de la CSP pour régler tes erreurs Console
$csp = "default-src 'self'; ";
$csp .= "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' https://challenges.cloudflare.com; ";
$csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ";
$csp .= "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com; "; // Fix erreur index.php:12
$csp .= "font-src 'self' data: https://fonts.gstatic.com; ";
$csp .= "img-src 'self' data: https: blob:; "; // Fix erreur Unsplash
$csp .= "connect-src 'self' https:; ";
$csp .= "frame-src https://challenges.cloudflare.com; ";
$csp .= "upgrade-insecure-requests;";

if (!$is_localhost) {
    header("Content-Security-Policy: " . $csp);
} else {
    // En Localhost, on reste permissif pour le dev
    header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval' data: blob:; img-src * data: blob:; style-src * 'unsafe-inline'; font-src * data:; connect-src *;");
}

// 4. Session et Buffer pour injection automatique
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$GLOBALS['csp_nonce'] = $nonce;
ob_start(function($buffer) {
    return preg_replace('/<script(?![^>]*\bnonce\b)([^>]*)>/i', '<script nonce="' . $GLOBALS['csp_nonce'] . '"$1>', $buffer);
});
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <title>PsySpace | Espace Thérapeutique</title>

    <!-- Tailwind local (SRI compatible) -->
    <script src="assets/js/tailwind.min.js" nonce="<?= $nonce ?>"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">


    <!-- Tailwind config + dark mode anti-flash : nonce obligatoire -->
    <script nonce="<?= $nonce ?>">
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }
                }
            }
        }

        // ANTI-FLASH : applique le thème avant le rendu
        (function() {
            const theme = localStorage.getItem('color-theme');
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (theme === 'dark' || (!theme && systemDark)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>

    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .dark body { background-color: #0f172a !important; color: #f8fafc !important; }
        .dark .bg-white { background-color: #1e293b !important; color: #f8fafc !important; border-color: #334155 !important; }
        .dark .text-slate-900, .dark .text-gray-900 { color: #f1f5f9 !important; }
        .dark header { background-color: rgba(15, 23, 42, 0.9) !important; border-color: rgba(255, 255, 255, 0.1) !important; }
    </style>
</head>

<body class="font-sans antialiased selection:bg-indigo-500/30 bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-white">

    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/80 backdrop-blur-lg dark:border-white/5 dark:bg-slate-900/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                <div class="flex items-center gap-3">
                    <a href="index.php" class="flex items-center gap-2.5 group">
                        <img src="assets/images/logo.png" alt="Logo" class="h-8 w-auto">
                        <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                            Psy<span class="text-indigo-600 dark:text-indigo-400">Space</span>
                        </span>
                    </a>
                </div>

                <nav class="hidden md:flex items-center gap-1">
                    <a href="guide.php" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-white transition-colors rounded-md">Guide</a>
                    <a href="securite.php" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-white transition-colors rounded-md">Sécurité</a>
                    <a href="chatbot.php" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-white transition-colors rounded-md">Assistant</a>
                </nav>

                <div class="flex items-center gap-2">
                    <button id="theme-toggle" class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 rounded-lg transition-all">
                        <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                        <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 011.414-1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"></path></svg>
                    </button>

                    <a href="login.php" class="hidden sm:block px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-indigo-600 transition-colors">Connexion</a>
                    <a href="register.php" class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-500 transition-all shadow-md">Inscription</a>

                    <button id="menu-btn" class="md:hidden p-2 text-slate-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Menu mobile -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-100 bg-white dark:border-white/5 dark:bg-slate-900">
            <div class="px-4 py-4 space-y-2">
                <a href="guide.php" class="block py-2 text-slate-600 dark:text-slate-300">Guide Pratique</a>
                <a href="securite.php" class="block py-2 text-slate-600 dark:text-slate-300">Sécurité</a>
                <a href="chatbot.php" class="block py-2 text-slate-600 dark:text-slate-300">Assistant AI</a>
                <hr class="border-slate-100 dark:border-white/5">
                <a href="login.php" class="block py-2 font-bold text-indigo-600">Connexion</a>
            </div>
        </div>
    </header>

    <!-- Scripts header : nonce obligatoire -->
    <script nonce="<?= $nonce ?>">
        const themeToggleBtn = document.getElementById('theme-toggle');
        const darkIcon  = document.getElementById('theme-toggle-dark-icon');
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

        document.getElementById('menu-btn').addEventListener('click', () => {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });
    </script>