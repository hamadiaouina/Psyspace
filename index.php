<?php
include "security/firewall.php";
include "header.php"; 
// Ici, on ne rajoute PAS de header CSP, le header.php s'en occupe déjà !
?>

<style nonce="<?= $nonce ?>">
    /* Ajoute le nonce ici pour que tes styles perso soient acceptés */
    @import url('https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&display=swap');
    body { font-family: 'Inter', sans-serif; }
    .font-serif { font-family: 'Merriweather', serif; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .spin { animation: spin .7s linear infinite; }
    .img-natural { object-fit: cover; object-position: center; }

    @keyframes assistant-pulse {
        0%,100% { box-shadow: 0 0 0 0 rgba(99,102,241,0), 0 8px 32px rgba(99,102,241,.4); }
        50%      { box-shadow: 0 0 0 10px rgba(99,102,241,.08), 0 8px 32px rgba(99,102,241,.5); }
    }
    .assistant-btn { animation: assistant-pulse 3s ease-in-out infinite; }
</style>

<main class="text-slate-700 bg-white">

    <!-- HERO -->
    <section class="bg-slate-900 pt-20 pb-24 md:pt-28 md:pb-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col lg:flex-row items-center gap-16 lg:gap-20">
            <div class="flex-1 space-y-8 text-center lg:text-left">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 mx-auto lg:mx-0">
                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full"></span>
                    <span class="text-xs font-semibold text-indigo-300 uppercase tracking-wider">Logiciel de cabinet psychologique</span>
                </div>
                <h1 class="font-serif text-4xl md:text-5xl font-bold text-white leading-tight tracking-tight">
                    Concentrez-vous sur<br>
                    <span class="text-indigo-400">vos patients.</span><br>
                    Nous gérons le reste.
                </h1>
                <p class="text-lg text-slate-400 leading-relaxed max-w-lg mx-auto lg:mx-0">
                    PsySpace centralise le suivi de vos patients, la génération de comptes-rendus et la gestion des séances — dans un espace sécurisé, conforme au RGPD.
                </p>
                <div class="flex flex-col sm:flex-row items-center gap-4 pt-2 justify-center lg:justify-start">
                    <a href="register.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-indigo-600 text-white font-semibold px-8 py-3.5 rounded-lg shadow-lg hover:bg-indigo-500 transition-all hover:-translate-y-0.5">
                        Créer mon espace
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                    <a href="#fonctionnalites" class="text-sm font-medium text-slate-300 hover:text-white transition-colors">
                        Découvrir les fonctionnalités →
                    </a>
                </div>
            </div>

            <div class="flex-1 relative hidden lg:block">
                <div class="rounded-2xl overflow-hidden shadow-2xl bg-slate-800 border border-slate-700">
                    <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?auto=format&fit=crop&q=80&w=1200"
                         class="w-full h-auto opacity-80 img-natural" alt="Interface PsySpace">
                </div>
                <div class="absolute -bottom-6 -left-6 bg-white rounded-xl shadow-xl px-5 py-4 flex items-center gap-4 border border-slate-100">
                    <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center text-indigo-600 shrink-0">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-900">Données protégées</p>
                        <p class="text-xs text-slate-500">Hébergement HDS · RGPD</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- TRUST BAR -->
    <section class="bg-white border-b border-slate-100 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-600">
                    <svg class="text-indigo-500 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Certifié HDS
                </div>
                <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-600">
                    <svg class="text-indigo-500 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Chiffrement AES-256
                </div>
                <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-600">
                    <svg class="text-indigo-500 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Disponibilité 24/7
                </div>
                <div class="flex items-center justify-center gap-2 text-sm font-medium text-slate-600">
                    <svg class="text-indigo-500 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Secret médical
                </div>
            </div>
        </div>
    </section>

    <!-- STATS -->
    <section class="bg-slate-50 py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-slate-200 rounded-xl overflow-hidden border border-slate-200">
                <div class="p-8 lg:p-10 text-center bg-white hover:bg-slate-50 transition-colors">
                    <p class="font-serif text-4xl font-bold text-indigo-600">-70%</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mt-2">Temps de documentation</p>
                </div>
                <div class="p-8 lg:p-10 text-center bg-white hover:bg-slate-50 transition-colors">
                    <p class="font-serif text-4xl font-bold text-indigo-600">100%</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mt-2">RGPD conforme</p>
                </div>
                <div class="p-8 lg:p-10 text-center bg-white hover:bg-slate-50 transition-colors">
                    <p class="font-serif text-4xl font-bold text-indigo-600">AES-256</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mt-2">Niveau de chiffrement</p>
                </div>
                <div class="p-8 lg:p-10 text-center bg-white hover:bg-slate-50 transition-colors">
                    <p class="font-serif text-4xl font-bold text-indigo-600">24/7</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mt-2">Disponibilité</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FONCTIONNALITÉS -->
    <section id="fonctionnalites" class="bg-white py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600 mb-4">Fonctionnalités</p>
                <h2 class="font-serif text-3xl md:text-4xl font-bold text-slate-900 mb-4">Tout ce dont vous avez besoin.</h2>
                <p class="text-slate-500 leading-relaxed">Un outil pensé pour les professionnels de la santé mentale — sobre, précis et fiable.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-6 lg:gap-8">
                <div class="bg-slate-50 border border-slate-100 rounded-xl p-8 group hover:border-indigo-200 hover:shadow-md transition-all duration-300">
                    <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white mb-6 transition-transform group-hover:scale-105">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-3">Comptes-rendus automatiques</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">À partir de vos notes ou d'un audio, PsySpace génère un compte-rendu clinique structuré en quelques secondes.</p>
                </div>
                <div class="bg-slate-50 border border-slate-100 rounded-xl p-8 group hover:border-indigo-200 hover:shadow-md transition-all duration-300">
                    <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white mb-6 transition-transform group-hover:scale-105">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-3">Dossiers patients centralisés</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">Accédez à l'historique complet de chaque patient : séances, évolutions, documents — classés et sécurisés.</p>
                </div>
                <div class="bg-slate-50 border border-slate-100 rounded-xl p-8 group hover:border-indigo-200 hover:shadow-md transition-all duration-300">
                    <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white mb-6 transition-transform group-hover:scale-105">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-3">Gestion des séances</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">Planifiez et retrouvez facilement chaque rendez-vous. PsySpace s'adapte à votre rythme de travail.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- COMMENT ÇA MARCHE -->
    <section class="bg-slate-50 py-24 border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-xl mx-auto mb-16">
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600 mb-4">Processus</p>
                <h2 class="font-serif text-3xl md:text-4xl font-bold text-slate-900 mb-4">Opérationnel en 3 étapes.</h2>
                <p class="text-slate-500 leading-relaxed">Pas de formation, pas de configuration. Prêt en moins de cinq minutes.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-12 md:gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full border border-slate-200 bg-white text-slate-400 font-bold text-xl flex items-center justify-center mx-auto mb-6 shadow-sm">01</div>
                    <h3 class="font-bold text-slate-900 mb-2">Créez votre compte</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">Renseignez vos informations professionnelles. Votre espace est configuré instantanément.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full border border-slate-200 bg-white text-slate-400 font-bold text-xl flex items-center justify-center mx-auto mb-6 shadow-sm">02</div>
                    <h3 class="font-bold text-slate-900 mb-2">Ajoutez vos patients</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">Créez des dossiers patients. Toutes les données sont chiffrées dès la saisie.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full border border-slate-200 bg-white text-slate-400 font-bold text-xl flex items-center justify-center mx-auto mb-6 shadow-sm">03</div>
                    <h3 class="font-bold text-slate-900 mb-2">Gagnez du temps</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">Dictez vos notes après chaque séance. Le compte-rendu est prêt avant le prochain rendez-vous.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA FINAL -->
    <section class="bg-slate-900 py-24">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-serif text-3xl md:text-4xl font-bold text-white mb-4">Prêt à simplifier votre pratique ?</h2>
            <p class="text-slate-400 max-w-md mx-auto leading-relaxed mb-8">Rejoignez les professionnels qui passent moins de temps à documenter et plus de temps à soigner.</p>
            <a href="register.php" class="inline-flex items-center gap-2 bg-white text-slate-900 font-bold px-8 py-4 rounded-lg hover:bg-indigo-50 hover:-translate-y-0.5 transition-all shadow-xl">
                Commencer gratuitement
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>
    </section>

</main>

<!-- ══════════════════════════════════════════
     BOUTONS FLOTTANTS
══════════════════════════════════════════ -->

<!-- ★ BOUTON ASSISTANT VOCAL — remplace l'ancien chatbot ★ -->
<a href="chatbot.php"
   class="assistant-btn fixed bottom-6 right-6 z-50 flex items-center gap-3
          bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
          px-5 py-3.5 rounded-full shadow-2xl transition-all hover:-translate-y-1 hover:gap-4">
    <!-- Avatar mini -->
    <div class="w-8 h-8 rounded-full bg-white/15 flex items-center justify-center flex-shrink-0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
            <line x1="12" y1="19" x2="12" y2="23"/>
            <line x1="8" y1="23" x2="16" y2="23"/>
        </svg>
    </div>
    <span class="text-sm">Parler à l'assistant</span>
    <!-- Point vert "en ligne" -->
    <span class="w-2 h-2 rounded-full bg-green-400 flex-shrink-0"
          style="box-shadow:0 0 6px #4ade80;"></span>
</a>

<!-- Badge Développeur — inchangé -->
<a href="developer.php" class="fixed bottom-6 left-6 z-50 flex items-center gap-3 bg-white border border-slate-200 rounded-full py-2 pl-2 pr-5 shadow-lg hover:border-indigo-200 hover:-translate-y-0.5 transition-all">
    <img src="assets/images/moi.jpg" class="w-9 h-9 rounded-full object-cover" alt="Hamadi">
    <div>
        <p class="text-sm font-bold text-slate-900 leading-none">Hamadi Aouina</p>
        <p class="text-xs text-slate-400">Développeur</p>
    </div>
</a>

<?php include "footer.php"; ?>