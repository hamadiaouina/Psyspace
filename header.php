<?php
/**
 * PSYSPACE - HEADER DE SÉCURITÉ FINAL & RECONNAISSANCE MATÉRIELLE
 */

// --- 0. DÉTECTION HTTPS ET LOCALHOST ---
$is_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$is_localhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || $_SERVER['SERVER_NAME'] === 'localhost';

// --- A. RECONNAISSANCE MATÉRIELLE (BADGE INVISIBLE) ---
$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: ""; 

// 1. Activation manuelle via URL
if (isset($_GET['psypass']) && !empty($admin_secret_key) && $_GET['psypass'] === $admin_secret_key) {
    setcookie("psyspace_boss_key", $admin_secret_key, [
        'expires' => time() + (10 * 365 * 24 * 60 * 60),
        'path' => '/',
        'httponly' => true,
        'secure' => $is_https, 
        'samesite' => 'Lax'
    ]);
    
    header("Location: index.php?badge=active");
    exit();
}

// 2. Vérification du badge
$is_admin_device = false;
if (!empty($admin_secret_key) && isset($_COOKIE['psyspace_boss_key'])) {
    if ($_COOKIE['psyspace_boss_key'] === $admin_secret_key) {
        $is_admin_device = true;
    }
}

// --- B. SÉCURITÉ & FIREWALL ---
if (file_exists(__DIR__ . '/security/firewall.php')) {
    require_once __DIR__ . '/security/firewall.php';
}

// --- C. GESTION DES SESSIONS SANS CONFLIT ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '', 
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

include_once "connection.php";

// --- D. NETTOYAGE & HEADERS DE SÉCURITÉ ---
header_remove("Content-Security-Policy");
header_remove("X-Content-Security-Policy");
header_remove("X-Frame-Options");

$nonce = base64_encode(random_bytes(16));
$GLOBALS['csp_nonce'] = $nonce;

if ($is_https) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

// --- E. CONTENT SECURITY POLICY (CSP) ---
if (!$is_localhost) {
    header("Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' https://cdn.tailwindcss.com https://challenges.cloudflare.com; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "frame-src 'self' https://challenges.cloudflare.com; " .
        "img-src 'self' data: https: blob: https://images.unsplash.com; " . 
        "connect-src 'self' https: wss: blob:; " .
        "media-src 'self' blob: data:; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "frame-ancestors 'none'; " .
        "upgrade-insecure-requests;"
    );
} else {
    header("Content-Security-Policy: " .
        "default-src 'self' 'unsafe-inline' 'unsafe-eval' https: http:; " .
        "script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' 'unsafe-eval' https: http: https://challenges.cloudflare.com; " .
        "frame-src 'self' https://challenges.cloudflare.com; " . 
        "img-src 'self' data: https: http: blob: https://images.unsplash.com; " .
        "connect-src 'self' https: http: blob: wss:; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https: http: data:; " .
        "media-src 'self' blob: data:; " .
        "object-src 'none';"
    );
}

// --- F. INJECTION AUTOMATIQUE DU NONCE ---
ob_start(function($buffer) {
    $n = $GLOBALS['csp_nonce'] ?? '';
    return preg_replace(
        '/<script(?![^>]*\bnonce\b)([^>]*)>/i',
        '<script nonce="' . $n . '"$1>',
        $buffer
    );
});
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta name="google-site-verification" content="google-site-verification=3648EysteCJqG8a_40SJDbKlNvM34j8PpGx3_wwR0bI">
    <script nonce="<?= $nonce ?>">
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js');
      }
    </script>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="{{ asset('assets/images/logo.png') }}">    <title>PsySpace | Espace Thérapeutique</title>
<link rel="shortcut icon" href="/assets/logo.png">
    <script src="assets/js/tailwind.min.js" nonce="<?= $nonce ?>"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" nonce="<?= $nonce ?>" async defer></script>

    <script nonce="<?= $nonce ?>">
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }
                }
            }
        };

        // ANTI-FLASH
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

    <style nonce="<?= $nonce ?>">
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .dark body { background-color: #0f172a !important; color: #f8fafc !important; }
    </style>
</head>

<body class="font-sans antialiased bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-white">

    <!-- FENÊTRE D'ALERTE PRO -->
    <?php if(isset($_GET['error']) && $_GET['error'] == 'security_lock'): ?>
        <div id="security-lock-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/80 backdrop-blur-sm transition-opacity">
            <div class="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-2xl max-w-md text-center border border-red-500/30 transform transition-all scale-100">
                
                <div class="w-16 h-16 mx-auto bg-red-100 dark:bg-red-900/30 text-red-600 rounded-full flex items-center justify-center mb-4 shadow-inner">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                
                <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Accès Verrouillé</h2>
                <p class="text-slate-600 dark:text-slate-300 mb-6 font-medium">
                    Par mesure de sécurité, suite à 3 tentatives infructueuses, l'accès admin a été bloqué.
                </p>
                
                <div class="flex items-center justify-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <svg class="animate-spin h-4 w-4 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Redirection en cours...
                </div>
            </div>
        </div>

        <script nonce="<?= $GLOBALS['csp_nonce'] ?? '' ?>">
            setTimeout(function() {
                window.location.href = 'index.php';
            }, 3500);
        </script>
    <?php endif; ?>

    <!-- LA FAMEUSE BANNIÈRE COMPLÈTE & FERMÉE PROPREMENT -->
    <div class="relative bg-indigo-600 dark:bg-indigo-500">
        <div class="max-w-7xl mx-auto py-2 px-3 sm:px-6 lg:px-8">
            <div class="text-center pr-16 sm:px-16">
                <p class="font-medium text-white text-xs sm:text-sm">
                    <span class="md:hidden">PsySpace est disponible sur mobile.</span>
                    <span class="hidden md:inline">L'expérience PsySpace évolue : Découvrez notre interface mobile PWA.</span>
                    <span class="block sm:inline-block ml-2">
                        <a href="mobile.php" class="text-white font-bold underline decoration-white/50 underline-offset-4 hover:decoration-white transition-all">
                            En savoir plus <span aria-hidden="true">&rarr;</span>
                        </a>
                    </span>
                </p>
            </div>
        </div>
    </div>

    <!-- NAVBAR -->
    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/80 backdrop-blur-lg dark:border-white/5 dark:bg-slate-900/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <div class="flex items-center gap-3">
                    <a href="index.php" class="flex items-center gap-2.5 group">
                        <img src="assets/images/logo.png" alt="Logo" class="h-8 w-auto">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:gap-2">
                            <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                                Psy<span class="text-indigo-600 dark:text-indigo-400">Space</span>
                            </span>
                            <span class="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-500/20 uppercase tracking-widest">
                                PWA
                            </span>
                        </div>
                    </a>
                </div>

                <nav class="hidden md:flex items-center gap-1">
                    <a href="guide.php" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-white transition-colors rounded-md">Guide</a>
                    <a href="securite.php" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-white transition-colors rounded-md">Sécurité</a>
                    <a href="chatbot.php" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-white transition-colors rounded-md">Assistant</a>
                    <a href="contact.php" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-white transition-colors rounded-md">Contact</a>

                    <?php if ($is_admin_device): ?>
                        <a href="admin/login.php" 
                           class="ml-2 px-4 py-2 text-sm font-bold text-red-600 border border-red-200 bg-red-50/50 hover:bg-red-100 dark:text-red-400 dark:border-red-900/30 dark:bg-red-900/20 transition-all rounded-md relative z-50">
                            Admin Panel
                        </a>
                    <?php endif; ?>
                </nav>

                <div class="flex items-center gap-2">
                    <button id="theme-toggle" class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/5 rounded-lg transition-all">
                        <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                        <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 011.414-1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"></path></svg>
                    </button>
                    <a href="login.php" class="hidden sm:block px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-indigo-600 transition-colors">Connexion</a>
                    <a href="register.php" class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-500 transition-all shadow-md">Inscription</a>
                                        <button id="menu-btn" class="md:hidden p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-white/5 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- MENU MOBILE CORRIGÉ -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-100 bg-white dark:border-white/5 dark:bg-slate-900">
            <div class="px-4 py-4 space-y-2">
                <a href="guide.php" class="block py-2 text-slate-600 dark:text-slate-300">Guide Pratique</a>
                <a href="securite.php" class="block py-2 text-slate-600 dark:text-slate-300">Sécurité</a>
                <a href="chatbot.php" class="block py-2 text-slate-600 dark:text-slate-300">Assistant AI</a>
                <a href="mobile.php" class="block py-2 font-bold text-indigo-600">Installer l'App</a>
                <hr class="border-slate-100 dark:border-white/5">
                <a href="login.php" class="block py-2 text-slate-600 dark:text-slate-300">Connexion</a>
                
                <?php if ($is_admin_device): ?>
                    <hr class="border-slate-100 dark:border-white/5">
                    <a href="admin/login.php" class="block py-2 font-bold text-red-600 dark:text-red-400">🚨 Admin Panel</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <script nonce="<?= $nonce ?>">
        // Logique Menu Mobile
        document.getElementById('menu-btn').addEventListener('click', () => {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });

        // Logique Dark Mode
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
    </script>