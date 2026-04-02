<?php include "header.php"; ?>

<main class="max-w-4xl mx-auto px-4 py-12">
    <div class="text-center space-y-6">
        <h1 class="text-4xl font-extrabold text-slate-900 dark:text-white">
            PsySpace <span class="text-indigo-600">Mobile</span>
        </h1>
        <p class="text-lg text-slate-600 dark:text-slate-400">
            Emportez votre espace thérapeutique partout avec vous.
        </p>

        <div class="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-xl inline-block border border-slate-200 dark:border-white/10">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=https://psyspace.me" 
                 alt="QR Code PsySpace" 
                 class="mx-auto mb-4">
            <p class="text-sm font-semibold text-slate-500">Scannez pour installer</p>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mt-12 text-left">
            <div class="p-6 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl border border-indigo-100 dark:border-indigo-500/20">
                <h3 class="font-bold text-indigo-900 dark:text-indigo-300 mb-2">Sur Android (Chrome)</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    1. Scannez le code.<br>
                    2. Cliquez sur les <strong>3 points</strong> en haut à droite.<br>
                    3. Choisissez <strong>"Installer l'application"</strong>.
                </p>
            </div>
            <div class="p-6 bg-slate-100 dark:bg-white/5 rounded-xl border border-slate-200 dark:border-white/10">
                <h3 class="font-bold text-slate-900 dark:text-white mb-2">Sur iPhone (Safari)</h3>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    1. Scannez le code.<br>
                    2. Cliquez sur le bouton <strong>Partager</strong> (carré avec flèche).<br>
                    3. Choisissez <strong>"Sur l'écran d'accueil"</strong>.
                </p>
            </div>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>
