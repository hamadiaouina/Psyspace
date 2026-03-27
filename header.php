<?php
// 1. Protection Anti-Clickjacking
header("X-Frame-Options: DENY");

// 2. Anti-Sniffing
header("X-Content-Type-Options: nosniff");

// 3. Confidentialité
header("Referrer-Policy: strict-origin-when-cross-origin");

// 4. HSTS (HTTPS forcé pendant 1 an)
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

/**
 * 5. CONTENT SECURITY POLICY (FUSIONNÉE)
 * On ajoute 'upgrade-insecure-requests' au début pour régler ton problème de cadenas rouge.
 */
header("Content-Security-Policy: upgrade-insecure-requests; default-src 'self'; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; frame-src https://challenges.cloudflare.com; img-src 'self' data:;");

// Inclusion de ton rate limit
require_once __DIR__ . "/Security/rate_limit.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <title>PsySpace | Espace Thérapeutique</title>
    
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #0f172a; }
    </style>
</head>
<body class="font-sans text-white antialiased selection:bg-indigo-500/30">

    <!-- HEADER PROPRE & MINIMALISTE -->
    <header class="sticky top-0 z-50 border-b border-white/5 bg-slate-900/80 backdrop-blur-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <!-- LOGO (Gauche) -->
                <div class="flex items-center gap-3">
                    <a href="index.php" class="flex items-center gap-2.5 group">
                        <img src="assets/images/logo.png" alt="Logo PsySpace" class="h-8 w-auto">
                        <span class="text-lg font-bold text-white tracking-tight">
                            Psy<span class="text-indigo-400">Space</span>
                        </span>
                    </a>
                </div>

                <!-- NAVIGATION CENTRALE (Caché sur mobile) -->
                <nav class="hidden md:flex items-center gap-1">
                    <a href="guide.php" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors rounded-md hover:bg-white/5">
                        Guide Pratique
                    </a>
                    <a href="securite.php" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors rounded-md hover:bg-white/5">
                        Sécurité
                    </a>
                    <a href="contact.php" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors rounded-md hover:bg-white/5">
                        Contact
                    </a>
                    <a href="chatbot.php" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white transition-colors rounded-md hover:bg-white/5">
                    Assistant 
                    </a>
                </nav>

                <!-- BOUTONS DROITE -->
                <div class="flex items-center gap-3">
                    <!-- Lien Connexion simple -->
                    <a href="login.php" class="hidden sm:block text-sm font-semibold text-slate-300 hover:text-white transition-colors">
                        Connexion
                    </a>
                    
                    <!-- Bouton Inscription propre (Flat design) -->
                    <a href="register.php" class="inline-flex items-center justify-center px-5 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-500 transition-colors shadow-sm">
                        Inscription
                    </a>

                    <!-- Menu Burger (Mobile uniquement) -->
                    <button id="menu-btn" class="md:hidden p-2 -mr-2 text-slate-400 hover:text-white focus:outline-none" aria-label="Menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- MENU MOBILE (Deroule en dessous) -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-white/5 bg-slate-900/95">
            <div class="px-4 pt-3 pb-4 space-y-1">
                <a href="guide.php" class="block px-3 py-2 text-base font-medium text-slate-200 rounded-lg hover:bg-white/5">Guide Pratique</a>
                <a href="securite.php" class="block px-3 py-2 text-base font-medium text-slate-200 rounded-lg hover:bg-white/5">Sécurité & Éthique</a>
                <a href="contact.php" class="block px-3 py-2 text-base font-medium text-slate-200 rounded-lg hover:bg-white/5">Contact</a>
                <a href="login.php" class="block mt-2 px-3 py-2 text-base font-medium text-indigo-400 rounded-lg hover:bg-white/5">Connexion</a>
            </div>
        </div>
    </header>

    <!-- Script simple pour le menu mobile -->
    <script>
        const btn = document.getElementById('menu-btn');
        const menu = document.getElementById('mobile-menu');
        btn.addEventListener('click', () => menu.classList.toggle('hidden'));
    </script>

    <!-- ... Suite de votre page ... -->

</body>
</html>