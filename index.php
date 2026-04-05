<?php
include "security/firewall.php";
include "header.php"; 
?>

<style nonce="<?= $nonce ?? '' ?>">
    @import url('https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&display=swap');
    body { font-family: 'Inter', sans-serif; scroll-behavior: smooth; }
    .font-serif { font-family: 'Merriweather', serif; }
    .img-natural { object-fit: cover; object-position: center; }

    /* Animations d'apparition au scroll */
    .fade-up {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.8s ease-out, transform 0.8s ease-out;
    }
    .fade-up.visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* Animation de l'assistant */
    @keyframes assistant-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0), 0 8px 32px rgba(79, 70, 229, 0.4); }
        50%      { box-shadow: 0 0 0 10px rgba(79, 70, 229, 0.1), 0 8px 32px rgba(79, 70, 229, 0.6); }
    }
    .assistant-btn { animation: assistant-pulse 3s ease-in-out infinite; }
</style>

<main class="text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-950 transition-colors duration-300">

    <!-- HERO -->
    <section class="bg-slate-900 pt-20 pb-24 md:pt-28 md:pb-32 relative overflow-hidden">
        <!-- Grille de fond subtile -->
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 32px 32px;"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col lg:flex-row items-center gap-16 lg:gap-20 relative z-10">
            <div class="flex-1 space-y-8 text-center lg:text-left fade-up">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 mx-auto lg:mx-0 backdrop-blur-sm">
                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-pulse"></span>
                    <span class="text-xs font-semibold text-indigo-300 uppercase tracking-wider">Logiciel de cabinet psychologique</span>
                </div>
                
                <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-transparent bg-clip-text bg-gradient-to-br from-white to-slate-300 leading-tight tracking-tight">
                    Concentrez-vous sur<br>
                    <span class="text-indigo-400 italic">vos patients.</span><br>
                    Nous gérons le reste.
                </h1>
                
                <p class="text-lg text-slate-400 leading-relaxed max-w-lg mx-auto lg:mx-0">
                    PsySpace centralise le suivi de vos patients, la génération de comptes-rendus et la gestion des séances — dans un espace sécurisé, conforme au RGPD.
                </p>
                
                <div class="flex flex-col sm:flex-row items-center gap-4 pt-2 justify-center lg:justify-start">
                    <a href="register.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-indigo-600 text-white font-semibold px-8 py-4 rounded-xl shadow-[0_0_20px_rgba(79,70,229,0.3)] hover:bg-indigo-500 hover:shadow-[0_0_25px_rgba(79,70,229,0.5)] transition-all hover:-translate-y-0.5">
                        Créer mon espace
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                    <a href="#fonctionnalites" class="text-sm font-medium text-slate-300 hover:text-white transition-colors">
                        Découvrir les fonctionnalités &rarr;
                    </a>
                </div>
            </div>

            <div class="flex-1 relative hidden lg:block fade-up" style="transition-delay: 0.2s;">
                <div class="rounded-2xl overflow-hidden shadow-2xl bg-slate-800 border border-slate-700/50 relative group">
                    <div class="absolute inset-0 bg-indigo-500/10 group-hover:bg-transparent transition-colors duration-500 z-10"></div>
                    <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?auto=format&fit=crop&q=80&w=1200"
                         class="w-full h-auto opacity-80 img-natural transform group-hover:scale-105 transition-transform duration-700" alt="Interface PsySpace">
                </div>
                <!-- Badge Glassmorphism -->
                <div class="absolute -bottom-6 -left-6 bg-white/90 dark:bg-slate-800/90 backdrop-blur-md rounded-xl shadow-2xl px-5 py-4 flex items-center gap-4 border border-slate-200/50 dark:border-slate-700/50 z-20">
                    <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/50 rounded-lg flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white">Données protégées</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Hébergement HDS · RGPD</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- TRUST BAR -->
    <section class="bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 py-6 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-400">
                    <svg class="text-indigo-500 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Certifié HDS
                </div>
                <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-400">
                    <svg class="text-indigo-500 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Chiffrement AES-256
                </div>
                <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-400">
                    <svg class="text-indigo-500 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Disponibilité 24/7
                </div>
                <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-600 dark:text-slate-400">
                    <svg class="text-indigo-500 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Secret médical
                </div>
            </div>
        </div>
    </section>

    <!-- STATS -->
    <section class="bg-slate-50 dark:bg-slate-950 py-20 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 fade-up">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-slate-200 dark:bg-slate-800 rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="p-8 lg:p-10 text-center bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <p class="font-serif text-4xl font-bold text-indigo-600 dark:text-indigo-400">-70%</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-2">Temps de documentation</p>
                </div>
                <div class="p-8 lg:p-10 text-center bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <p class="font-serif text-4xl font-bold text-indigo-600 dark:text-indigo-400">100%</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-2">RGPD conforme</p>
                </div>
                <div class="p-8 lg:p-10 text-center bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <p class="font-serif text-4xl font-bold text-indigo-600 dark:text-indigo-400">AES-256</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-2">Niveau de chiffrement</p>
                </div>
                <div class="p-8 lg:p-10 text-center bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <p class="font-serif text-4xl font-bold text-indigo-600 dark:text-indigo-400">24/7</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mt-2">Disponibilité</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FONCTIONNALITÉS -->
    <section id="fonctionnalites" class="bg-white dark:bg-slate-900 py-24 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16 fade-up">
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-4">Fonctionnalités</p>
                <h2 class="font-serif text-3xl md:text-4xl font-bold text-slate-900 dark:text-white mb-4">Tout ce dont vous avez besoin.</h2>
                <p class="text-slate-500 dark:text-slate-400 leading-relaxed">Un outil pensé pour les professionnels de la santé mentale — sobre, précis et fiable.</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-6 lg:gap-8">
                <!-- Carte 1 -->
                <div class="fade-up bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/50 rounded-2xl p-8 group hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-xl transition-all duration-300">
                    <div class="w-14 h-14 bg-indigo-600 rounded-xl flex items-center justify-center text-white mb-6 transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3 shadow-lg shadow-indigo-200 dark:shadow-none">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-3">Comptes-rendus IA</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">À partir de vos notes ou d'un audio, PsySpace génère un compte-rendu clinique structuré en quelques secondes.</p>
                </div>
                
                <!-- Carte 2 -->
                <div class="fade-up bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/50 rounded-2xl p-8 group hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-xl transition-all duration-300" style="transition-delay: 0.1s;">
                    <div class="w-14 h-14 bg-indigo-600 rounded-xl flex items-center justify-center text-white mb-6 transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-3 shadow-lg shadow-indigo-200 dark:shadow-none">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-3">Dossiers centralisés</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Accédez à l'historique complet de chaque patient : séances, évolutions, documents — classés et sécurisés.</p>
                </div>
                
                <!-- Carte 3 -->
                <div class="fade-up bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/50 rounded-2xl p-8 group hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-xl transition-all duration-300" style="transition-delay: 0.2s;">
                    <div class="w-14 h-14 bg-indigo-600 rounded-xl flex items-center justify-center text-white mb-6 transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3 shadow-lg shadow-indigo-200 dark:shadow-none">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white mb-3">Gestion de l'agenda</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Planifiez et retrouvez facilement chaque rendez-vous. PsySpace s'adapte à votre rythme de travail.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PROCESSUS -->
    <section class="bg-slate-50 dark:bg-slate-950 py-24 border-y border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-xl mx-auto mb-16 fade-up">
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-4">Processus</p>
                <h2 class="font-serif text-3xl md:text-4xl font-bold text-slate-900 dark:text-white mb-4">Opérationnel en 3 étapes.</h2>
                <p class="text-slate-500 dark:text-slate-400 leading-relaxed">Pas de formation, pas de configuration. Prêt en moins de cinq minutes.</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-12 md:gap-8">
                <div class="text-center fade-up">
                    <div class="w-16 h-16 rounded-full border-2 border-indigo-100 dark:border-indigo-900 bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-bold text-xl flex items-center justify-center mx-auto mb-6 shadow-md shadow-indigo-100 dark:shadow-none">1</div>
                    <h3 class="font-bold text-slate-900 dark:text-white mb-2">Créez votre compte</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Renseignez vos informations professionnelles. Votre espace est configuré instantanément.</p>
                </div>
                <div class="text-center fade-up" style="transition-delay: 0.1s;">
                    <div class="w-16 h-16 rounded-full border-2 border-indigo-100 dark:border-indigo-900 bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-bold text-xl flex items-center justify-center mx-auto mb-6 shadow-md shadow-indigo-100 dark:shadow-none">2</div>
                    <h3 class="font-bold text-slate-900 dark:text-white mb-2">Ajoutez vos patients</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Créez des dossiers patients. Toutes les données sont chiffrées dès la saisie.</p>
                </div>
                <div class="text-center fade-up" style="transition-delay: 0.2s;">
                    <div class="w-16 h-16 rounded-full border-2 border-indigo-100 dark:border-indigo-900 bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 font-bold text-xl flex items-center justify-center mx-auto mb-6 shadow-md shadow-indigo-100 dark:shadow-none">3</div>
                    <h3 class="font-bold text-slate-900 dark:text-white mb-2">Gagnez du temps</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">Dictez vos notes après chaque séance. Le compte-rendu est prêt avant le prochain rendez-vous.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA FINAL -->
    <section class="bg-slate-900 py-24 relative overflow-hidden">
        <div class="absolute inset-0 bg-indigo-600/10 blur-3xl rounded-full translate-y-1/2 scale-150"></div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10 fade-up">
            <h2 class="font-serif text-3xl md:text-4xl font-bold text-white mb-4">Prêt à simplifier votre pratique ?</h2>
            <p class="text-slate-300 max-w-md mx-auto leading-relaxed mb-10">Rejoignez les professionnels qui passent moins de temps à documenter et plus de temps à soigner.</p>
            <a href="register.php" class="inline-flex items-center gap-3 bg-white text-indigo-600 font-bold px-8 py-4 rounded-xl hover:bg-indigo-50 hover:-translate-y-1 hover:shadow-[0_0_30px_rgba(255,255,255,0.3)] transition-all">
                Commencer gratuitement
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>
    </section>

</main>

<!-- BOUTON ASSISTANT VOCAL -->
<a href="chatbot.php"
   class="assistant-btn fixed bottom-6 right-6 z-50 flex items-center gap-3
          bg-indigo-600 hover:bg-indigo-500 text-white font-semibold
          px-5 py-3.5 rounded-full shadow-2xl transition-all hover:-translate-y-1 hover:pr-6">
    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
            <line x1="12" y1="19" x2="12" y2="23"/>
            <line x1="8" y1="23" x2="16" y2="23"/>
        </svg>
    </div>
    <span class="text-sm hidden sm:inline-block">Parler à l'assistant</span>
    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 flex-shrink-0 shadow-[0_0_8px_#4ade80] border-2 border-indigo-600"></span>
</a>

<!-- BADGE DÉVELOPPEUR -->
<a href="developer.php" class="fixed bottom-6 left-6 z-50 hidden sm:flex items-center gap-3 bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm border border-slate-200 dark:border-slate-700 rounded-full py-2 pl-2 pr-5 shadow-lg hover:border-indigo-300 dark:hover:border-indigo-500 hover:-translate-y-1 transition-all">
    <img src="assets/images/moi.jpg" class="w-9 h-9 rounded-full object-cover shadow-sm" alt="Hamadi">
    <div>
        <p class="text-sm font-bold text-slate-900 dark:text-white leading-none">Hamadi Aouina</p>
        <p class="text-xs text-slate-500 dark:text-slate-400">Développeur</p>
    </div>
</a>

<!-- Script d'animation d'apparition -->
<script nonce="<?= $nonce ?? '' ?>">
    document.addEventListener("DOMContentLoaded", () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(e => { 
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    // Optionnel : arrêter d'observer une fois affiché
                    observer.unobserve(e.target);
                }
            });
        }, { threshold: 0.15, rootMargin: "0px 0px -50px 0px" });
        
        document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
    });
</script>

<?php include "footer.php"; ?>