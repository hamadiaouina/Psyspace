<?php include "header.php"; ?>

<main class="max-w-6xl mx-auto px-6 py-20 min-h-[85vh] flex flex-col justify-center">
    
    <div class="max-w-3xl mb-20">
        <div class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 text-[11px] font-semibold uppercase tracking-widest mb-4">
            Module de Déploiement PWA v1.0
        </div>
        <h1 class="text-5xl md:text-6xl font-extrabold text-slate-950 dark:text-white tracking-tighter leading-tight mb-5">
            Accès optimisé <span class="text-indigo-600 dark:text-indigo-400">PsySpace Mobile</span>
        </div>
        <p class="text-xl text-slate-600 dark:text-slate-400 max-w-2xl font-normal leading-relaxed">
            Plateforme accessible via Progressive Web App. Cette technologie permet une utilisation fluide sur terminaux mobiles sans installation via les stores applicatifs traditionnels.
        </p>
    </div>

    <div class="grid lg:grid-cols-3 gap-12 items-start w-full">
        
        <div class="lg:col-span-1 p-8 bg-white dark:bg-slate-950 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 flex flex-col items-center">
            <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800 mb-6">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=https://psyspace.me" 
                     alt="Code QR de déploiement PsySpace" 
                     class="w-56 h-56 md:w-60 md:h-60 grayscale hover:grayscale-0 transition-all duration-300">
            </div>
            
            <div class="flex items-center gap-3 w-full justify-center">
                <div class="h-2 w-2 rounded-full bg-indigo-500 animate-pulse"></div>
                <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                    Scanner pour initialiser
                </span>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-10">
            
            <div class="p-8 bg-transparent border border-slate-100 dark:border-slate-800 rounded-2xl hover:border-indigo-100 dark:hover:border-indigo-900/50 transition-colors">
                <h3 class="text-sm font-semibold text-slate-950 dark:text-white uppercase tracking-wider mb-6 flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                    Protocole d'Installation : Environnement Android
                </h3>
                <ol class="space-y-4 text-sm text-slate-600 dark:text-slate-400 list-decimal list-inside font-medium">
                    <li>Activer le scanner optique du terminal mobile.</li>
                    <li>Scanner le code QR ci-contre pour charger l'URL sécurisée.</li>
                    <li>Accepter l'invite de déploiement automatique ("Ajouter à l'écran d'accueil").</li>
                    <li>Alternative : Menu Navigateur > "Installer l'application".</li>
                </ol>
            </div>

            <div class="p-8 bg-transparent border border-slate-100 dark:border-slate-800 rounded-2xl hover:border-indigo-100 dark:hover:border-indigo-900/50 transition-colors">
                <h3 class="text-sm font-semibold text-slate-950 dark:text-white uppercase tracking-wider mb-6 flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                    Protocole d'Installation : Environnement iOS
                </h3>
                <ol class="space-y-4 text-sm text-slate-600 dark:text-slate-400 list-decimal list-inside font-medium">
                    <li>Ouvrir l'URL cible exclusivement via le navigateur Safari.</li>
                    <li>Utiliser la fonction native "Partager" dans la barre d'outils.</li>
                    <li>Sélectionner l'option "Sur l'écran d'accueil" pour finaliser le déploiement.</li>
                </ol>
            </div>
            
        </div>

    </div>
</main>

<div id="iphone-helper" class="fixed bottom-6 left-6 right-6 p-5 md:hidden hidden z-50 bg-slate-950 dark:bg-white rounded-xl shadow-2xl border border-white/10 dark:border-slate-200 flex items-center justify-between">
    <span class="text-xs font-bold uppercase tracking-wider text-white dark:text-slate-950">Action requise : Menu Partager > Écran d'accueil</span>
    <svg class="w-5 h-5 text-indigo-400 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
    </svg>
</div>

<script nonce="<?= $nonce ?>">
    let deferredPrompt;

    // Interception de l'événement d'installation native (Android/Chrome)
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault(); // Empêche l'affichage immédiat
        deferredPrompt = e;
        
        // Déclenchement contrôlé après temporisation
        setTimeout(() => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    deferredPrompt = null; // Reset après choix
                });
            }
        }, 2500); // 2.5s pour laisser l'utilisateur lire
    });

    // Détection spécifique pour l'aide iOS
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches;

    if (isIOS && !isStandalone) {
        document.getElementById('iphone-helper').classList.remove('hidden');
    }
</script>

<?php include "footer.php"; ?>