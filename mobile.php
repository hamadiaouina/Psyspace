<?php include "header.php"; ?>

<main class="max-w-4xl mx-auto px-4 py-12 min-h-[80vh] flex flex-col justify-center">
    <div class="text-center space-y-8">
        <div class="space-y-2">
            <h1 class="text-5xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                PsySpace <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-violet-500">Mobile</span>
            </h1>
            <p class="text-lg text-slate-600 dark:text-slate-400 max-w-md mx-auto">
                Transformez ce site en application mobile en un instant.
            </p>
        </div>

        <div class="relative inline-block group">
            <div class="absolute -inset-1 bg-gradient-to-r from-indigo-600 to-violet-500 rounded-2xl blur opacity-25 group-hover:opacity-50 transition duration-1000"></div>
            <div class="relative bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-2xl border border-slate-200 dark:border-white/10">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=https://psyspace.me" 
                     alt="QR Code PsySpace" 
                     class="mx-auto mb-4 rounded-lg">
                <div class="flex items-center justify-center space-x-2 text-indigo-600 dark:text-indigo-400 font-bold">
                    <span class="relative flex h-3 w-3">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-indigo-500"></span>
                    </span>
                    <span>Scannez pour installer</span>
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mt-12 text-left">
            <div id="android-guide" class="p-6 bg-white dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
                <div class="flex items-center mb-4">
                    <span class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg mr-3">🤖</span>
                    <h3 class="font-bold text-slate-900 dark:text-white text-lg">Sur Android</h3>
                </div>
                <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                    <li class="flex items-start">
                        <span class="font-bold text-indigo-600 mr-2">1.</span> Scannez le code et ouvrez le site.
                    </li>
                    <li class="flex items-start">
                        <span class="font-bold text-indigo-600 mr-2">2.</span> Une bannière <strong>"Ajouter à l'écran d'accueil"</strong> devrait apparaître.
                    </li>
                    <li class="flex items-start">
                        <span class="font-bold text-indigo-600 mr-2">3.</span> Si non, cliquez sur les <span class="bg-slate-200 dark:bg-slate-700 px-1 rounded">⋮</span> en haut à droite.
                    </li>
                </ul>
            </div>

            <div id="ios-guide" class="p-6 bg-white dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm">
                <div class="flex items-center mb-4">
                    <span class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg mr-3">🍎</span>
                    <h3 class="font-bold text-slate-900 dark:text-white text-lg">Sur iPhone</h3>
                </div>
                <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                    <li class="flex items-start">
                        <span class="font-bold text-indigo-600 mr-2">1.</span> Ouvrez le lien dans <strong>Safari</strong>.
                    </li>
                    <li class="flex items-start">
                        <span class="font-bold text-indigo-600 mr-2">2.</span> Appuyez sur le bouton <strong>Partager</strong> <span class="text-lg">⎋</span>.
                    </li>
                    <li class="flex items-start">
                        <span class="font-bold text-indigo-600 mr-2">3.</span> Sélectionnez <strong>"Sur l'écran d'accueil"</strong>.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</main>

<div id="iphone-arrow" class="fixed bottom-6 left-50 transform -translate-x-1/2 text-center hidden md:hidden z-50">
    <p class="bg-indigo-600 text-white px-4 py-2 rounded-full text-sm font-bold shadow-lg animate-bounce">
        Cliquez ici pour installer ⬇️
    </p>
</div>

<script>
// --- LOGIQUE D'INSTALLATION AUTOMATIQUE (ANDROID) ---
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // On attend 2 secondes après le scan pour déclencher la popup
    setTimeout(() => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the PWA install');
                }
                deferredPrompt = null;
            });
        }
    }, 2000);
});

// --- DÉTECTION iOS POUR AFFICHER LA FLÈCHE ---
const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
const isStandalone = window.matchMedia('(display-mode: standalone)').matches;

if (isIOS && !isStandalone) {
    document.getElementById('iphone-arrow').classList.remove('hidden');
    document.getElementById('iphone-arrow').style.left = "50%";
}
</script>

<?php include "footer.php"; ?>