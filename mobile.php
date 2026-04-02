<?php include "header.php"; ?>

<main class="max-w-4xl mx-auto px-4 py-12 min-h-[80vh] flex flex-col justify-center">
    <div class="text-center space-y-10">
        <div class="space-y-3">
            <h1 class="text-5xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                PsySpace <span class="text-indigo-600 dark:text-indigo-400">Mobile</span>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 max-w-sm mx-auto font-medium leading-relaxed">
                Accès optimisé via Progressive Web App (PWA). 
                Aucune installation via store requise.
            </p>
        </div>

        <div class="relative inline-block group">
            <div class="absolute -inset-0.5 bg-slate-200 dark:bg-slate-800 rounded-3xl opacity-50"></div>
            <div class="relative bg-white dark:bg-slate-950 p-8 rounded-3xl shadow-sm border border-slate-200 dark:border-white/5">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=https://psyspace.me" 
                     alt="Deployment QR Code" 
                     class="mx-auto mb-6 grayscale hover:grayscale-0 transition-all duration-500">
                
                <div class="flex items-center justify-center gap-3">
                    <div class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-pulse"></div>
                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400 dark:text-slate-500">
                        Scan to Deploy
                    </span>
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-8 mt-12 text-left">
            <div class="group p-6 bg-transparent border-l border-slate-200 dark:border-slate-800 hover:border-indigo-500 dark:hover:border-indigo-500 transition-colors">
                <h3 class="font-bold text-slate-900 dark:text-white mb-4 flex items-center text-sm uppercase tracking-wider">
                    <span class="w-8 h-[1px] bg-indigo-500 mr-3"></span>
                    Environnement Android
                </h3>
                <ul class="space-y-4 text-sm text-slate-500 dark:text-slate-400">
                    <li class="flex items-baseline gap-3">
                        <span class="text-indigo-500 font-mono">01.</span>
                        <span>Lancer le scan via l'appareil photo du terminal.</span>
                    </li>
                    <li class="flex items-baseline gap-3">
                        <span class="text-indigo-500 font-mono">02.</span>
                        <span>Accepter l'invite de déploiement automatique en bas d'écran.</span>
                    </li>
                    <li class="flex items-baseline gap-3">
                        <span class="text-indigo-500 font-mono">03.</span>
                        <span>Optionnel : Menu Chrome > "Installer l'application".</span>
                    </li>
                </ul>
            </div>

            <div class="group p-6 bg-transparent border-l border-slate-200 dark:border-slate-800 hover:border-indigo-500 dark:hover:border-indigo-500 transition-colors">
                <h3 class="font-bold text-slate-900 dark:text-white mb-4 flex items-center text-sm uppercase tracking-wider">
                    <span class="w-8 h-[1px] bg-indigo-500 mr-3"></span>
                    Environnement iOS
                </h3>
                <ul class="space-y-4 text-sm text-slate-500 dark:text-slate-400">
                    <li class="flex items-baseline gap-3">
                        <span class="text-indigo-500 font-mono">01.</span>
                        <span>Ouvrir l'URL cible exclusivement sous le navigateur Safari.</span>
                    </li>
                    <li class="flex items-baseline gap-3">
                        <span class="text-indigo-500 font-mono">02.</span>
                        <span>Activer l'option "Partager" dans la barre d'outils native.</span>
                    </li>
                    <li class="flex items-baseline gap-3">
                        <span class="text-indigo-500 font-mono">03.</span>
                        <span>Sélectionner "Sur l'écran d'accueil" pour finaliser.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</main>

<div id="iphone-arrow" class="fixed bottom-8 left-1/2 -translate-x-1/2 hidden md:hidden z-50">
    <div class="bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-[0.2em] shadow-2xl border border-white/10">
        Action requise en bas d'écran
    </div>
</div>

<script>
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    setTimeout(() => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                deferredPrompt = null;
            });
        }
    }, 2000);
});

const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
const isStandalone = window.matchMedia('(display-mode: standalone)').matches;

if (isIOS && !isStandalone) {
    document.getElementById('iphone-arrow').classList.remove('hidden');
}
</script>

<?php include "footer.php"; ?>