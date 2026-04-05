<?php
include "security/firewall.php";
include "header.php"; 
?>

<style nonce="<?= $nonce ?? '' ?>">
    @import url('https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&family=Inter:wght@400;500;600;700&display=swap');
    
    body { font-family: 'Inter', sans-serif; scroll-behavior: smooth; }
    .font-serif { font-family: 'Merriweather', serif; }
    
    /* Animation d'apparition fluide au scroll */
    .fade-up {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.8s ease-out, transform 0.8s ease-out;
    }
    .fade-up.visible {
        opacity: 1;
        transform: translateY(0);
    }
</style>

<main class="text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-950 transition-colors duration-300">

    <!-- HERO — Sombre & Feutré -->
    <section class="bg-slate-900 pt-24 pb-20 md:pt-32 md:pb-28 relative overflow-hidden">
        <!-- Effet de grille subtil en fond -->
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 32px 32px;"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="fade-up max-w-3xl">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 mb-6 backdrop-blur-sm">
                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-pulse"></span>
                    <span class="text-xs font-semibold text-indigo-300 uppercase tracking-wider">Assistant de pratique clinique</span>
                </div>
                
                <h1 class="font-serif text-4xl md:text-5xl lg:text-6xl font-bold text-transparent bg-clip-text bg-gradient-to-br from-white to-slate-300 leading-tight tracking-tight mb-6">
                    Libérez votre <span class="text-indigo-400 italic">écoute.</span>
                </h1>
                
                <p class="text-lg md:text-xl text-slate-400 leading-relaxed">
                    PsySpace ne remplace pas votre analyse — elle élimine la charge administrative pour vous redonner l'essentiel : <span class="text-white font-medium">la présence thérapeutique.</span>
                </p>
            </div>
        </div>
    </section>

    <!-- ÉTAPES — Aéré & Clean -->
    <section class="py-20 md:py-28 bg-white dark:bg-slate-950 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

            <!-- Étape 01 -->
            <div class="fade-up group bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-700/50 rounded-2xl p-8 md:p-10 flex flex-col lg:flex-row gap-8 items-center hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-xl transition-all duration-300">
                <div class="shrink-0 w-16 h-16 lg:w-20 lg:h-20 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-2xl font-bold shadow-lg shadow-indigo-200 dark:shadow-none group-hover:scale-105 group-hover:rotate-3 transition-transform duration-300">
                    01
                </div>
                <div class="text-center lg:text-left">
                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-3 tracking-tight">Immersion totale</h3>
                    <p class="text-slate-500 dark:text-slate-400 leading-relaxed max-w-3xl text-lg">
                        Activez le mode écoute. Le système capte les nuances du discours sans que vous ayez à baisser les yeux vers votre carnet. Restez en <span class="font-semibold text-slate-700 dark:text-slate-200">contact visuel avec votre patient.</span>
                    </p>
                </div>
            </div>

            <!-- Étape 02 -->
            <div class="fade-up group bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-700/50 rounded-2xl p-8 md:p-10 flex flex-col lg:flex-row gap-8 items-center hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-xl transition-all duration-300" style="transition-delay: 0.1s;">
                <div class="shrink-0 w-16 h-16 lg:w-20 lg:h-20 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-2xl font-bold shadow-lg shadow-indigo-200 dark:shadow-none group-hover:scale-105 group-hover:-rotate-3 transition-transform duration-300">
                    02
                </div>
                <div class="text-center lg:text-left">
                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-3 tracking-tight">Cartographie mentale</h3>
                    <p class="text-slate-500 dark:text-slate-400 leading-relaxed max-w-3xl text-lg">
                        L'IA structure les thématiques abordées — transfert, mécanismes de défense, affects. Elle transforme un flux verbal complexe en une <span class="font-semibold text-slate-700 dark:text-slate-200">anamnèse cohérente et exploitable.</span>
                    </p>
                </div>
            </div>

            <!-- Étape 03 -->
            <div class="fade-up group bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-700/50 rounded-2xl p-8 md:p-10 flex flex-col lg:flex-row gap-8 items-center hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-xl transition-all duration-300" style="transition-delay: 0.2s;">
                <div class="shrink-0 w-16 h-16 lg:w-20 lg:h-20 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-2xl font-bold shadow-lg shadow-indigo-200 dark:shadow-none group-hover:scale-105 group-hover:rotate-3 transition-transform duration-300">
                    03
                </div>
                <div class="text-center lg:text-left">
                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-3 tracking-tight">Soutien au diagnostic</h3>
                    <p class="text-slate-500 dark:text-slate-400 leading-relaxed max-w-3xl text-lg">
                        Recevez des suggestions basées sur les critères du <span class="font-semibold text-indigo-600 dark:text-indigo-400">DSM-5</span> ou de la <span class="font-semibold text-indigo-600 dark:text-indigo-400">CIM-11</span>. Un deuxième regard objectif pour valider vos intuitions cliniques.
                    </p>
                </div>
            </div>

        </div>
    </section>

    <!-- CONFIDENTIALITÉ — Bloc de confiance -->
    <section class="bg-slate-50 dark:bg-slate-900 py-20 md:py-28 border-y border-slate-100 dark:border-slate-800 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="fade-up bg-white dark:bg-slate-800/80 backdrop-blur-sm border border-slate-200 dark:border-slate-700 rounded-3xl p-8 md:p-16 text-center shadow-xl max-w-4xl mx-auto">
                
                <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-50 dark:bg-indigo-900/50 rounded-2xl text-indigo-600 dark:text-indigo-400 mb-8 shadow-inner">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                
                <h2 class="font-serif text-3xl md:text-4xl font-bold text-slate-900 dark:text-white mb-6 tracking-tight">
                    Le serment de confidentialité
                </h2>
                
                <p class="text-slate-500 dark:text-slate-300 text-lg max-w-2xl mx-auto leading-relaxed mb-10">
                    <span class="italic text-slate-600 dark:text-slate-200 font-serif">"Ce qui est dit dans le cabinet reste dans le cabinet."</span><br><br>
                    Nos algorithmes sont conçus pour oublier. Une fois le rapport généré, les données brutes sont 
                    <span class="font-bold text-slate-900 dark:text-white">définitivement effacées</span>, 
                    garantissant un secret professionnel inviolable.
                </p>
                
                <div class="flex flex-wrap items-center justify-center gap-x-8 gap-y-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
                    <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-900 px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700">
                        <svg class="text-emerald-500 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Chiffrement AES-256
                    </div>
                    <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-900 px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700">
                        <svg class="text-emerald-500 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Hébergement HDS
                    </div>
                    <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-900 px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700">
                        <svg class="text-emerald-500 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Conforme RGPD
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA — Sobre & Efficace -->
    <section class="bg-slate-900 py-24 relative overflow-hidden">
        <!-- Halo lumineux arrière-plan -->
        <div class="absolute inset-0 bg-indigo-600/10 blur-3xl rounded-full translate-y-1/2 scale-150"></div>
        
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10 fade-up">
            <h2 class="font-serif text-3xl md:text-4xl font-bold text-white mb-4 tracking-tight">Prêt à commencer ?</h2>
            <p class="text-slate-400 mb-10 text-lg">Ouvrez votre cabinet numérique en moins de 5 minutes.</p>
            
            <a href="register.php" class="inline-flex items-center gap-3 bg-white text-indigo-600 font-bold px-8 py-4 rounded-xl hover:bg-indigo-50 hover:-translate-y-1 hover:shadow-[0_0_30px_rgba(255,255,255,0.3)] transition-all">
                Ouvrir mon cabinet
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>
    </section>

</main>

<!-- Script pour l'animation au scroll -->
<script nonce="<?= $nonce ?? '' ?>">
    document.addEventListener("DOMContentLoaded", () => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(e => { 
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    observer.unobserve(e.target);
                }
            });
        }, { threshold: 0.15, rootMargin: "0px 0px -50px 0px" });

        document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
    });
</script>

<?php include "footer.php"; ?>