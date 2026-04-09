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
        <!-- Ligne de gradient indigo en haut -->
        <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500 to-transparent"></div>
        <!-- Grille de fond subtile -->
        <div class="absolute inset-0 opacity-[0.04]" style="background-image: linear-gradient(#6366f1 1px, transparent 1px), linear-gradient(to right, #6366f1 1px, transparent 1px); background-size: 40px 40px;"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col lg:flex-row items-center gap-16 lg:gap-20 relative z-10">
            <div class="flex-1 space-y-8 text-center lg:text-left fade-up">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 mx-auto lg:mx-0 backdrop-blur-sm">
                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-pulse"></span>
                    <span class="text-xs font-semibold text-indigo-300 uppercase tracking-wider">PsySpace · Cabinet Psychologique IA</span>
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
                    <!-- Mockup UI PsySpace — remplace la photo stock générique -->
                    <svg viewBox="0 0 680 420" xmlns="http://www.w3.org/2000/svg" class="w-full h-auto" role="img" aria-label="Aperçu de l'interface PsySpace">
                        <!-- Fond général -->
                        <rect width="680" height="420" fill="#0f172a"/>
                        <!-- Sidebar -->
                        <rect width="160" height="420" fill="#1e293b"/>
                        <rect x="0" y="0" width="3" height="420" fill="#4f46e5"/>
                        <!-- Logo sidebar -->
                        <rect x="18" y="22" width="28" height="28" rx="7" fill="#4f46e5"/>
                        <text x="24" y="41" fill="white" font-size="12" font-weight="700" font-family="sans-serif">P</text>
                        <text x="52" y="41" fill="white" font-size="13" font-weight="700" font-family="sans-serif">PsySpace</text>
                        <!-- Nav items sidebar -->
                        <rect x="12" y="72" width="136" height="34" rx="8" fill="#4f46e5" opacity="0.2"/>
                        <rect x="18" y="82" width="14" height="14" rx="3" fill="#6366f1"/>
                        <text x="40" y="93" fill="#a5b4fc" font-size="11" font-weight="600" font-family="sans-serif">Dashboard</text>
                        <rect x="18" y="116" width="14" height="14" rx="3" fill="#475569"/>
                        <text x="40" y="127" fill="#64748b" font-size="11" font-family="sans-serif">Patients</text>
                        <rect x="18" y="144" width="14" height="14" rx="3" fill="#475569"/>
                        <text x="40" y="155" fill="#64748b" font-size="11" font-family="sans-serif">Agenda</text>
                        <rect x="18" y="172" width="14" height="14" rx="3" fill="#475569"/>
                        <text x="40" y="183" fill="#64748b" font-size="11" font-family="sans-serif">Archives</text>
                        <!-- Avatar bas de sidebar -->
                        <circle cx="28" cy="396" r="14" fill="#4f46e5" opacity="0.4"/>
                        <text x="21" y="401" fill="#a5b4fc" font-size="12" font-weight="700" font-family="sans-serif">HA</text>
                        <text x="50" y="394" fill="#94a3b8" font-size="9" font-family="sans-serif">Dr. Aouina</text>
                        <text x="50" y="405" fill="#4f46e5" font-size="8" font-family="sans-serif">Psychiatre</text>
                        <!-- Zone principale -->
                        <!-- Titre dashboard -->
                        <text x="184" y="42" fill="white" font-size="16" font-weight="700" font-family="sans-serif">Bonjour, Dr. Aouina 👋</text>
                        <text x="184" y="60" fill="#64748b" font-size="10" font-family="sans-serif">Voici le résumé de votre activité</text>
                        <!-- Stat cards -->
                        <rect x="184" y="78" width="116" height="72" rx="10" fill="#1e293b"/>
                        <text x="196" y="96" fill="#64748b" font-size="8" font-weight="600" font-family="sans-serif">PATIENTS ACTIFS</text>
                        <text x="196" y="130" fill="#6366f1" font-size="26" font-weight="700" font-family="sans-serif">24</text>
                        <rect x="310" y="78" width="116" height="72" rx="10" fill="#1e293b"/>
                        <text x="322" y="96" fill="#64748b" font-size="8" font-weight="600" font-family="sans-serif">SÉANCES DU JOUR</text>
                        <text x="322" y="130" fill="white" font-size="26" font-weight="700" font-family="sans-serif">5</text>
                        <rect x="310" y="140" width="116" height="4" rx="2" fill="#1e2d3d"/>
                        <rect x="310" y="140" width="58" height="4" rx="2" fill="#4f46e5"/>
                        <rect x="436" y="78" width="116" height="72" rx="10" fill="#1e293b"/>
                        <text x="448" y="96" fill="#64748b" font-size="8" font-weight="600" font-family="sans-serif">TERMINÉES</text>
                        <text x="448" y="130" fill="white" font-size="26" font-weight="700" font-family="sans-serif">3</text>
                        <text x="448" y="148" fill="#10b981" font-size="9" font-family="sans-serif">60% complété</text>
                        <!-- Table RDV -->
                        <rect x="184" y="170" width="368" height="200" rx="12" fill="#1e293b"/>
                        <text x="200" y="192" fill="white" font-size="11" font-weight="700" font-family="sans-serif">Prochains rendez-vous</text>
                        <text x="498" y="192" fill="#6366f1" font-size="9" font-family="sans-serif">Voir l'agenda</text>
                        <line x1="184" y1="200" x2="552" y2="200" stroke="#0f172a" stroke-width="1"/>
                        <!-- RDV rows -->
                        <rect x="196" y="212" width="40" height="38" rx="6" fill="#0f172a"/>
                        <text x="205" y="226" fill="#94a3b8" font-size="7" font-weight="700" font-family="sans-serif">AVR</text>
                        <text x="208" y="240" fill="white" font-size="11" font-weight="700" font-family="sans-serif">09</text>
                        <text x="246" y="226" fill="white" font-size="10" font-weight="600" font-family="sans-serif">Sophie Martin</text>
                        <text x="246" y="240" fill="#64748b" font-size="8" font-family="sans-serif">09:00 · Consultation</text>
                        <rect x="476" y="214" width="60" height="22" rx="5" fill="#4f46e5"/>
                        <text x="490" y="229" fill="white" font-size="9" font-weight="600" font-family="sans-serif">Démarrer</text>
                        <line x1="196" y1="260" x2="544" y2="260" stroke="#0f172a" stroke-width="1"/>
                        <rect x="196" y="270" width="40" height="38" rx="6" fill="#0f172a"/>
                        <text x="205" y="284" fill="#94a3b8" font-size="7" font-weight="700" font-family="sans-serif">AVR</text>
                        <text x="208" y="298" fill="white" font-size="11" font-weight="700" font-family="sans-serif">10</text>
                        <text x="246" y="284" fill="white" font-size="10" font-weight="600" font-family="sans-serif">Karim Benali</text>
                        <text x="246" y="298" fill="#64748b" font-size="8" font-family="sans-serif">14:30 · Suivi</text>
                        <rect x="476" y="272" width="60" height="22" rx="5" fill="#0f172a" stroke="#4f46e5" stroke-width="1"/>
                        <text x="492" y="287" fill="#6366f1" font-size="9" font-weight="600" font-family="sans-serif">ICS</text>
                        <line x1="196" y1="318" x2="544" y2="318" stroke="#0f172a" stroke-width="1"/>
                        <rect x="196" y="328" width="40" height="38" rx="6" fill="#0f172a"/>
                        <text x="205" y="342" fill="#94a3b8" font-size="7" font-weight="700" font-family="sans-serif">AVR</text>
                        <text x="208" y="356" fill="white" font-size="11" font-weight="700" font-family="sans-serif">11</text>
                        <text x="246" y="342" fill="white" font-size="10" font-weight="600" font-family="sans-serif">Leila Hamdi</text>
                        <text x="246" y="356" fill="#64748b" font-size="8" font-family="sans-serif">10:00 · Bilan</text>
                        <rect x="476" y="330" width="60" height="22" rx="5" fill="#4f46e5"/>
                        <text x="490" y="345" fill="white" font-size="9" font-weight="600" font-family="sans-serif">Démarrer</text>
                        <!-- Overlay shimmer subtil -->
                        <rect width="680" height="420" fill="url(#shimmer)" opacity="0.03"/>
                        <defs>
                            <linearGradient id="shimmer" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#6366f1"/>
                                <stop offset="100%" stop-color="#0f172a"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="absolute inset-0 bg-indigo-500/5 group-hover:bg-transparent transition-colors duration-500 z-10"></div>
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
    <section class="bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 py-5 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-center gap-x-10 gap-y-3">
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <svg class="text-indigo-500 shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Hébergement HDS
                </div>
                <span class="hidden sm:block text-slate-200 dark:text-slate-700">·</span>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <svg class="text-indigo-500 shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Chiffrement AES-256
                </div>
                <span class="hidden sm:block text-slate-200 dark:text-slate-700">·</span>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <svg class="text-indigo-500 shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                    RGPD conforme
                </div>
                <span class="hidden sm:block text-slate-200 dark:text-slate-700">·</span>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <svg class="text-indigo-500 shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Disponible 24/7
                </div>
                <span class="hidden sm:block text-slate-200 dark:text-slate-700">·</span>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <svg class="text-indigo-500 shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
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
    <img src="assets/images/moi.JPG" class="w-9 h-9 rounded-full object-cover shadow-sm" alt="Hamadi">
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