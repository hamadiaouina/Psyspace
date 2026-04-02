<?php include "header.php"; ?>

<main class="max-w-5xl mx-auto px-4 py-16 min-h-[85vh] flex flex-col items-center justify-center">
    
    <div class="w-full max-w-2xl text-center mb-16">
        <div class="inline-flex items-center px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-100 dark:border-indigo-500/20 text-indigo-600 dark:text-indigo-400 text-[10px] font-bold uppercase tracking-widest mb-6">
            Protocol PWA v1.0
        </div>
        <h1 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white mb-4">
            Déploiement <span class="text-indigo-600">Mobile</span>
        </h1>
        <div class="h-1 w-20 bg-indigo-600 mx-auto rounded-full"></div>
    </div>

    <div class="grid lg:grid-cols-2 gap-16 items-start w-full">
        
        <div class="flex flex-col items-center lg:items-end">
            <div class="p-4 bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200 dark:border-white/5">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=https://psyspace.me" 
                     alt="PWA QR" 
                     class="w-64 h-64 md:w-80 md:h-80">
            </div>
            <p class="mt-6 text-sm text-slate-400 font-mono uppercase tracking-tighter">
                ID: PSY-MOBILE-INIT
            </p>
        </div>

        <div class="space-y-12 relative">
            <div class="absolute left-[11px] top-2 bottom-2 w-[2px] bg-slate-200 dark:bg-slate-800"></div>

            <div class="relative pl-10">
                <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-indigo-600 border-4 border-slate-50 dark:border-slate-950 flex items-center justify-center"></div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">01. Initialisation</h4>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Scanner le module via un terminal Android ou iOS.</p>
            </div>

            <div class="relative pl-10">
                <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-slate-300 dark:bg-slate-700 border-4 border-slate-50 dark:border-slate-950"></div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">02. Authentification</h4>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Valider l'invite système : <span class="font-mono text-indigo-500">"Ajouter à l'écran d'accueil"</span>.</p>
            </div>

            <div class="relative pl-10">
                <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-slate-300 dark:bg-slate-700 border-4 border-slate-50 dark:border-slate-950"></div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wide">03. Finalisation</h4>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">L'application est désormais isolée et prête pour un usage hors-ligne (Offline Mode).</p>
            </div>

            <div class="mt-8 p-4 bg-slate-100 dark:bg-white/5 rounded-lg border border-slate-200 dark:border-white/10">
                <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed italic">
                    Note : Pour iOS, l'installation requiert l'activation manuelle via l'option "Sur l'écran d'accueil" dans le menu de partage Safari.
                </p>
            </div>
        </div>

    </div>
</main>

<div id="iphone-overlay" class="fixed inset-x-0 bottom-0 p-6 md:hidden hidden z-50">
    <div class="bg-indigo-600 text-white p-4 rounded-2xl shadow-2xl flex items-center justify-between">
        <span class="text-xs font-bold uppercase tracking-wider">Installation Safari Requise</span>
        <svg class="w-5 h-5 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
    </div>
</div>

<script nonce="<?= $nonce ?>">
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches;

    if (isIOS && !isStandalone) {
        document.getElementById('iphone-overlay').classList.remove('hidden');
    }

    // Trigger automatique pour Android
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        setTimeout(() => { if (deferredPrompt) deferredPrompt.prompt(); }, 3000);
    });
</script>

<?php include "footer.php"; ?>