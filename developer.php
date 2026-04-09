<?php include "header.php"; ?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&display=swap');
    body { font-family: 'Inter', sans-serif; }
    .font-serif { font-family: 'Merriweather', serif; }
    .tech-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .tech-card:hover { transform: translateY(-4px); }
    .arch-card { transition: all 0.25s ease; }
    .arch-card:hover { border-color: #c7d2fe; box-shadow: 0 4px 20px -8px rgba(99,102,241,0.2); }
</style>

<main class="text-slate-700 bg-white">

    <!-- HERO -->
    <section class="bg-slate-900 pt-20 pb-24 md:pt-28 md:pb-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row items-center gap-16 lg:gap-20">

                <!-- Photo -->
                <div class="relative flex-shrink-0">
                    <div class="absolute -inset-1 rounded-[2rem] bg-gradient-to-br from-indigo-500 via-purple-500 to-cyan-400 opacity-40 blur-lg"></div>
                    <img src="assets/images/moi.JPG" alt="Hamadi Aouina"
                         class="relative w-56 h-56 md:w-72 md:h-72 rounded-[2rem] object-cover border border-slate-700 shadow-2xl">
                </div>

                <!-- Texte -->
                <div class="flex-1 space-y-6 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 mx-auto lg:mx-0">
                        <span class="w-1.5 h-1.5 bg-green-400 rounded-full" style="box-shadow:0 0 6px #4ade80;"></span>
                        <span class="text-xs font-semibold text-indigo-300 uppercase tracking-wider">Projet de Fin d'Études · ISI Ariana · 2026</span>
                    </div>

                    <h1 class="font-serif text-4xl md:text-6xl font-bold text-white leading-tight tracking-tight">
                        Hamadi<br>
                        <span class="text-indigo-400">Aouina</span>
                    </h1>

                    <p class="text-lg text-slate-400 leading-relaxed max-w-xl mx-auto lg:mx-0">
                        Développeur Fullstack spécialisé en IA clinique. J'ai conçu PsySpace de A à Z — du backend PHP à l'avatar 3D avec lip sync, en passant par l'analyse IA temps réel et la conteneurisation Docker.
                    </p>

                    <div class="flex flex-wrap gap-2 justify-center lg:justify-start">
                        <span class="px-3 py-1 bg-slate-800 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300">PHP 8.2</span>
                        <span class="px-3 py-1 bg-slate-800 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300">Docker</span>
                        <span class="px-3 py-1 bg-slate-800 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300">Llama 3.3-70B</span>
                        <span class="px-3 py-1 bg-slate-800 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300">Three.js</span>
                        <span class="px-3 py-1 bg-slate-800 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300">ElevenLabs</span>
                        <span class="px-3 py-1 bg-slate-800 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300">MySQL 8.0</span>
                    </div>

                    <a href="index.php" class="inline-flex items-center gap-2 text-sm font-medium text-slate-400 hover:text-white transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Retour à l'application
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- STATS -->
    <section class="bg-white border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-px bg-slate-100 border border-slate-100">
                <div class="p-8 text-center bg-white hover:bg-slate-50 transition-colors">
                    <p class="font-serif text-3xl font-bold text-indigo-600">9</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mt-2">Technologies intégrées</p>
                </div>
                <div class="p-8 text-center bg-white hover:bg-slate-50 transition-colors">
                    <p class="font-serif text-3xl font-bold text-indigo-600">72</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mt-2">Morph targets lip sync</p>
                </div>
                <div class="p-8 text-center bg-white hover:bg-slate-50 transition-colors">
                    <p class="font-serif text-3xl font-bold text-indigo-600">70B</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mt-2">Paramètres LLM</p>
                </div>
                <div class="p-8 text-center bg-white hover:bg-slate-50 transition-colors">
                    <p class="font-serif text-3xl font-bold text-indigo-600">100%</p>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mt-2">RGPD conforme</p>
                </div>
            </div>
        </div>
    </section>

    <!-- STACK TECHNIQUE -->
    <section class="bg-slate-50 py-24 border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600 mb-4">Technologies</p>
                <h2 class="font-serif text-3xl md:text-4xl font-bold text-slate-900 mb-4">Stack technique réelle.</h2>
                <p class="text-slate-500 leading-relaxed">Chaque technologie a été choisie pour une raison précise — performance, réalisme ou conformité médicale.</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">

                <div class="tech-card bg-white border border-slate-200 rounded-xl p-6 flex flex-col items-center text-center gap-3 hover:border-indigo-200 hover:shadow-md">
                    <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center">
                        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/php/php-original.svg" class="w-8 h-8" alt="PHP">
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">PHP 8.2</p>
                        <p class="text-xs text-indigo-500 font-semibold uppercase tracking-wide mt-0.5">Backend</p>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">Logique serveur, API, sessions, auth bcrypt</p>
                </div>

                <div class="tech-card bg-white border border-slate-200 rounded-xl p-6 flex flex-col items-center text-center gap-3 hover:border-indigo-200 hover:shadow-md">
                    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
                        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/mysql/mysql-original.svg" class="w-8 h-8" alt="MySQL">
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">MySQL 8.0</p>
                        <p class="text-xs text-blue-500 font-semibold uppercase tracking-wide mt-0.5">Base de données</p>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">5 tables : patients, médecins, séances, RDV, admin</p>
                </div>

                <div class="tech-card bg-white border border-slate-200 rounded-xl p-6 flex flex-col items-center text-center gap-3 hover:border-indigo-200 hover:shadow-md">
                    <div class="w-12 h-12 bg-cyan-50 rounded-xl flex items-center justify-center">
                        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/docker/docker-original.svg" class="w-8 h-8" alt="Docker">
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Docker</p>
                        <p class="text-xs text-cyan-500 font-semibold uppercase tracking-wide mt-0.5">Infra</p>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">Conteneurisation PHP + MySQL, déploiement 1 commande</p>
                </div>

                <div class="tech-card bg-white border border-slate-200 rounded-xl p-6 flex flex-col items-center text-center gap-3 hover:border-indigo-200 hover:shadow-md">
                    <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center text-2xl">🧠</div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Groq + Llama 3.3</p>
                        <p class="text-xs text-purple-500 font-semibold uppercase tracking-wide mt-0.5">IA · LLM</p>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">Analyse clinique temps réel, 70B paramètres</p>
                </div>

                <div class="tech-card bg-white border border-slate-200 rounded-xl p-6 flex flex-col items-center text-center gap-3 hover:border-indigo-200 hover:shadow-md">
                    <div class="w-12 h-12 bg-pink-50 rounded-xl flex items-center justify-center text-2xl">🎙️</div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">ElevenLabs</p>
                        <p class="text-xs text-pink-500 font-semibold uppercase tracking-wide mt-0.5">TTS</p>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">Synthèse vocale réaliste, voix naturelle française</p>
                </div>

                <div class="tech-card bg-white border border-slate-200 rounded-xl p-6 flex flex-col items-center text-center gap-3 hover:border-indigo-200 hover:shadow-md">
                    <div class="w-12 h-12 bg-orange-50 rounded-xl flex items-center justify-center text-2xl">🧊</div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Three.js</p>
                        <p class="text-xs text-orange-500 font-semibold uppercase tracking-wide mt-0.5">3D · WebGL</p>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">Avatar GLB, 72 morph targets, lip sync visèmes</p>
                </div>

                <div class="tech-card bg-white border border-slate-200 rounded-xl p-6 flex flex-col items-center text-center gap-3 hover:border-indigo-200 hover:shadow-md">
                    <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-2xl">🎤</div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Web Speech API</p>
                        <p class="text-xs text-green-500 font-semibold uppercase tracking-wide mt-0.5">STT · Vocal</p>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">Reconnaissance vocale fr-FR, native navigateur</p>
                </div>

                <div class="tech-card bg-white border border-slate-200 rounded-xl p-6 flex flex-col items-center text-center gap-3 hover:border-indigo-200 hover:shadow-md">
                    <div class="w-12 h-12 bg-sky-50 rounded-xl flex items-center justify-center">
                        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/tailwindcss/tailwindcss-original.svg" class="w-8 h-8" alt="Tailwind">
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">Tailwind CSS</p>
                        <p class="text-xs text-sky-500 font-semibold uppercase tracking-wide mt-0.5">UI / UX</p>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">Design system cohérent, dark mode, responsive</p>
                </div>

                <div class="tech-card bg-white border border-slate-200 rounded-xl p-6 flex flex-col items-center text-center gap-3 hover:border-indigo-200 hover:shadow-md">
                    <div class="w-12 h-12 bg-yellow-50 rounded-xl flex items-center justify-center">
                        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/javascript/javascript-original.svg" class="w-8 h-8 rounded" alt="JS">
                    </div>
                    <div>
                        <p class="font-bold text-slate-900 text-sm">JavaScript</p>
                        <p class="text-xs text-yellow-500 font-semibold uppercase tracking-wide mt-0.5">Frontend</p>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">Audio, animations, fetch API, interactions</p>
                </div>

            </div>
        </div>
    </section>

    <!-- ARCHITECTURE -->
    <section class="bg-white py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16">
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600 mb-4">Architecture</p>
                <h2 class="font-serif text-3xl md:text-4xl font-bold text-slate-900 mb-4">Comment PsySpace fonctionne.</h2>
                <p class="text-slate-500 leading-relaxed">Chaque brique du système a été pensée pour fonctionner ensemble de façon fluide et sécurisée.</p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">

                <div class="arch-card bg-slate-50 border border-slate-100 rounded-xl p-8">
                    <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white mb-6">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z"/><path d="M12 8v4l3 3"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-3">Analyse IA temps réel</h3>
                    <p class="text-sm text-slate-500 leading-relaxed mb-4">Web Speech API transcrit le discours du patient. Llama 3.3-70B via Groq analyse détresse, anxiété, résilience et urgence suicidaire en direct.</p>
                    <span class="inline-block px-3 py-1 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-full">Groq · Llama 3.3</span>
                </div>

                <div class="arch-card bg-slate-50 border border-slate-100 rounded-xl p-8">
                    <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white mb-6">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-3">Assistant vocal 3D</h3>
                    <p class="text-sm text-slate-500 leading-relaxed mb-4">Avatar Avaturn rendu en Three.js avec 72 morph targets pour le lip sync. ElevenLabs génère une voix naturelle synchronisée avec les mouvements de bouche.</p>
                    <span class="inline-block px-3 py-1 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-full">Three.js · ElevenLabs</span>
                </div>

                <div class="arch-card bg-slate-50 border border-slate-100 rounded-xl p-8">
                    <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white mb-6">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-3">Comptes-rendus CIM-11</h3>
                    <p class="text-sm text-slate-500 leading-relaxed mb-4">Génération automatique de rapports cliniques structurés — hypothèses diagnostiques CIM-11, plan thérapeutique, niveau de risque. Export PDF inclus.</p>
                    <span class="inline-block px-3 py-1 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-full">PHP · MySQL · PDF</span>
                </div>

                <div class="arch-card bg-slate-50 border border-slate-100 rounded-xl p-8">
                    <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white mb-6">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-3">Sécurité & RGPD</h3>
                    <p class="text-sm text-slate-500 leading-relaxed mb-4">Mots de passe bcrypt, sessions isolées médecin/admin, tokens sécurisés, aucun stockage audio permanent. Conformité RGPD totale.</p>
                    <span class="inline-block px-3 py-1 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-full">bcrypt · Sessions PHP</span>
                </div>

                <div class="arch-card bg-slate-50 border border-slate-100 rounded-xl p-8">
                    <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white mb-6">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-3">Gestion multi-rôles</h3>
                    <p class="text-sm text-slate-500 leading-relaxed mb-4">Interface séparée pour les médecins et l'admin. Dossiers patients, historique des séances, tableau de bord, gestion des rendez-vous.</p>
                    <span class="inline-block px-3 py-1 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-full">PHP · MySQL</span>
                </div>

                <div class="arch-card bg-slate-50 border border-slate-100 rounded-xl p-8">
                    <div class="w-12 h-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white mb-6">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-3">Déploiement Docker</h3>
                    <p class="text-sm text-slate-500 leading-relaxed mb-4">Containers PHP/Apache + MySQL orchestrés via docker-compose. Démarrage complet en une commande, portable sur n'importe quel serveur Linux.</p>
                    <span class="inline-block px-3 py-1 bg-indigo-50 text-indigo-600 text-xs font-semibold rounded-full">Docker Compose</span>
                </div>

            </div>
        </div>
    </section>

    <!-- CITATION -->
    <section class="bg-slate-900 py-24">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <svg class="w-10 h-10 text-indigo-400 mx-auto mb-8 opacity-60" fill="currentColor" viewBox="0 0 24 24"><path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/></svg>
            <blockquote class="font-serif text-2xl md:text-3xl font-bold text-white leading-relaxed mb-8">
                L'architecture logicielle ne consiste pas seulement à faire fonctionner un programme, mais à construire un système capable d'évoluer.
            </blockquote>
            <div class="flex items-center justify-center gap-4">
                <img src="assets/images/moi.JPG" class="w-12 h-12 rounded-full object-cover border-2 border-slate-700" alt="Hamadi">
                <div class="text-left">
                    <p class="font-bold text-white">Hamadi Aouina</p>
                    <p class="text-sm text-slate-400">Étudiant · ISI Ariana · PFE 2026</p>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include "footer.php"; ?>