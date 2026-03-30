<footer class="bg-white border-t border-slate-200 pt-12 pb-8 mt-auto font-sans dark:bg-slate-900 dark:border-white/5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 mb-12">

            <div class="col-span-1">
                <div class="flex items-center gap-3 mb-5">
                    <img src="assets/images/logo.png" alt="PsySpace" class="h-8 w-auto">
                    <span class="text-xl font-bold text-slate-900 tracking-tight dark:text-white">PsySpace</span>
                </div>
                <p class="text-sm text-slate-500 leading-relaxed max-w-xs dark:text-slate-400">
                    Plateforme de suivi psychologique assisté par intelligence artificielle. 
                    Innovation au service de la santé mentale.
                </p>
            </div>

            <div>
                <h4 class="text-[11px] font-bold text-slate-900 uppercase tracking-widest mb-6 dark:text-slate-300">À propos</h4>
                <ul class="space-y-4 text-sm">
                    <li>
                        <a href="dashboard.php" class="text-slate-600 hover:text-indigo-600 transition-colors dark:text-slate-400 dark:hover:text-white">
                            Accueil Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="securite.php" class="text-slate-600 hover:text-indigo-600 transition-colors dark:text-slate-400 dark:hover:text-white">
                            Éthique & Sécurité
                        </a>
                    </li>
                    <li>
                        <a href="contact.php" class="text-slate-600 hover:text-indigo-600 transition-colors dark:text-slate-400 dark:hover:text-white">
                            Support technique
                        </a>
                    </li>
                </ul>
            </div>

            <div>
                <h4 class="text-[11px] font-bold text-slate-900 uppercase tracking-widest mb-6 dark:text-slate-300">Infrastructure</h4>
                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-5 dark:bg-slate-800/50 dark:border-white/5">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-2 h-2 bg-indigo-500 rounded-full animate-pulse"></span>
                        <span class="text-[11px] font-bold text-indigo-700 uppercase tracking-tight dark:text-indigo-400">Cloud Sécurisé</span>
                    </div>
                    <p class="text-[12px] text-slate-500 leading-relaxed dark:text-slate-400">
                        Architecture déployée sur Azure. Protection des données par chiffrement end-to-end.
                    </p>
                </div>
            </div>

        </div>

        <div class="border-t border-slate-100 pt-8 flex flex-col md:flex-row justify-between items-center gap-4 dark:border-white/5">
            <p class="text-[12px] text-slate-400">
                &copy; 2026 <span class="font-medium text-slate-600 dark:text-slate-300">PsySpace</span> &middot; 
                Projet PFE par <span class="font-semibold text-slate-900 dark:text-white">Hamadi Aouina</span>
            </p>
            <div class="flex items-center gap-6">
                <span class="text-[11px] text-slate-400">v1.0-stable</span>
            </div>
        </div>

    </div>
</footer>

<script nonce="<?= $nonce ?? '' ?>">
    // 1. Gestion du Menu Mobile
    const menuBtn = document.getElementById('menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    if (menuBtn && mobileMenu) {
        menuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // 2. Gestion du Toggle Dark Mode
    const themeToggleBtn = document.getElementById('theme-toggle');
    const darkIcon = document.getElementById('theme-toggle-dark-icon');
    const lightIcon = document.getElementById('theme-toggle-light-icon');

    // Fonction pour mettre à jour les icônes
    function updateIcons() {
        if (document.documentElement.classList.contains('dark')) {
            darkIcon?.classList.add('hidden');
            lightIcon?.classList.remove('hidden');
        } else {
            darkIcon?.classList.remove('hidden');
            lightIcon?.classList.add('hidden');
        }
    }

    // Initialisation icônes
    updateIcons();

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function() {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('color-theme', isDark ? 'dark' : 'light');
            updateIcons();
        });
    }
</script>

</body>
</html>