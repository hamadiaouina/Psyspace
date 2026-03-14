<?php include "header.php"; ?>

<style>
    /* Import des fonts */
    @import url('https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&family=Inter:wght@400;500;600;700&display=swap');
    
    body { font-family: 'Inter', sans-serif; }
    .font-serif { font-family: 'Merriweather', serif; }
    
    /* Animation fluide */
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

<main class="text-slate-700 bg-white">

    <!-- HERO — Institutionnel & Tech -->
    <section class="bg-slate-900 pt-24 pb-20 md:pt-32 md:pb-28 relative overflow-hidden">
        <!-- Effet de grille subtil en fond -->
        <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 24px 24px;"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="flex flex-col lg:flex-row items-center gap-16">
                
                <!-- Contenu Texte -->
                <div class="flex-1 fade-up space-y-6 text-center lg:text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 mx-auto lg:mx-0">
                        <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full"></span>
                        <span class="text-xs font-semibold text-indigo-300 uppercase tracking-wider">Sécurité & Éthique</span>
                    </div>
                    
                    <h1 class="font-serif text-4xl md:text-5xl font-bold text-white leading-tight tracking-tight">
                        Le secret médical,<br>
                        <span class="text-indigo-400">inviolable par conception.</span>
                    </h1>
                    
                    <p class="text-lg text-slate-400 leading-relaxed max-w-lg mx-auto lg:mx-0">
                        La neutralité bienveillante du thérapeute nécessite un environnement technique sans faille. Voici comment nous le garantissons.
                    </p>
                </div>

                <!-- Badge Statut (Style "Tech Spec") -->
                <div class="fade-up shrink-0 bg-slate-800/50 border border-slate-700 rounded-xl p-6 flex items-center gap-5 shadow-2xl backdrop-blur-sm w-full lg:w-auto">
                    <div class="w-14 h-14 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-center text-emerald-400 shrink-0">
                        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-emerald-400 uppercase tracking-wider mb-1 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse inline-block"></span>
                            Statut : Protégé
                        </p>
                        <p class="text-white font-bold text-lg">Chiffrement AES-256</p>
                        <p class="text-slate-400 text-sm">Hébergement HDS certifié</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PRINCIPES — Aéré & Lisible -->
    <section class="py-20 md:py-28">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16 fade-up">
                <p class="text-xs font-semibold uppercase tracking-widest text-indigo-600 mb-4">
                    Nos engagements
                </p>
                <h2 class="font-serif text-3xl md:text-4xl font-bold text-slate-900 mb-4 tracking-tight">Trois piliers de protection.</h2>
                <p class="text-slate-500 leading-relaxed">Chaque décision technique est guidée par un principe : vos données cliniques ne nous appartiennent pas.</p>
            </div>

            <div class="space-y-6">

                <!-- Pilier 01 -->
                <div class="fade-up group bg-slate-50 border border-slate-100 rounded-xl p-6 md:p-10 flex flex-col md:flex-row gap-8 items-start hover:border-indigo-200 hover:shadow-md transition-all duration-300">
                    <div class="shrink-0 w-14 h-14 bg-indigo-600 group-hover:bg-indigo-700 text-white rounded-lg flex items-center justify-center font-bold text-lg transition-colors shadow-sm">
                        01
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 mb-3 tracking-tight">Anonymisation chirurgicale</h3>
                        <p class="text-slate-500 leading-relaxed">
                            Chaque session génère un jeton éphémère. Les données patient sont isolées dans un silo chiffré, totalement séparé de l'intelligence artificielle. 
                            <span class="font-semibold text-slate-700">L'IA traite des concepts, jamais des individus.</span>
                        </p>
                    </div>
                </div>

                <!-- Pilier 02 -->
                <div class="fade-up group bg-slate-50 border border-slate-100 rounded-xl p-6 md:p-10 flex flex-col md:flex-row gap-8 items-start hover:border-indigo-200 hover:shadow-md transition-all duration-300">
                    <div class="shrink-0 w-14 h-14 bg-indigo-600 group-hover:bg-indigo-700 text-white rounded-lg flex items-center justify-center font-bold text-lg transition-colors shadow-sm">
                        02
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 mb-3 tracking-tight">Zéro stockage audio</h3>
                        <p class="text-slate-500 leading-relaxed">
                            Aucun enregistrement vocal ne survit à la fin de la séance. Le flux audio est transformé en texte via une mémoire volatile puis immédiatement purgé. 
                            <span class="font-semibold text-slate-700">Rien n'est stocké sur disque.</span>
                        </p>
                    </div>
                </div>

                <!-- Pilier 03 -->
                <div class="fade-up group bg-slate-50 border border-slate-100 rounded-xl p-6 md:p-10 flex flex-col md:flex-row gap-8 items-start hover:border-indigo-200 hover:shadow-md transition-all duration-300">
                    <div class="shrink-0 w-14 h-14 bg-indigo-600 group-hover:bg-indigo-700 text-white rounded-lg flex items-center justify-center font-bold text-lg transition-colors shadow-sm">
                        03
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 mb-3 tracking-tight">Souveraineté des données</h3>
                        <p class="text-slate-500 leading-relaxed">
                            Vos analyses sont hébergées sur des serveurs certifiés HDS. Vous restez le propriétaire unique et 
                            <span class="font-semibold text-slate-700">l'unique détenteur de la clé de déchiffrement.</span>
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- BADGES CONFORMITÉ — Gris & Blanc -->
    <section class="bg-slate-50 py-20 md:py-28 border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="fade-up bg-white border border-slate-200 rounded-xl p-8 md:p-16 text-center shadow-sm max-w-4xl mx-auto">
                
                <h2 class="font-serif text-2xl md:text-3xl font-bold text-slate-900 mb-4 tracking-tight">Conformité & certifications</h2>
                <p class="text-slate-500 max-w-xl mx-auto mb-12 leading-relaxed">
                    PsySpace est conçu dès sa fondation pour respecter les standards les plus stricts du secteur médical.
                </p>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <!-- Item 1 -->
                    <div class="text-center p-4">
                        <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-slate-600 mx-auto mb-4 transition-colors hover:bg-indigo-100 hover:text-indigo-600">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <p class="text-sm font-bold text-slate-900">RGPD</p>
                        <p class="text-xs text-slate-400 mt-1 uppercase">Conforme</p>
                    </div>
                    
                    <!-- Item 2 -->
                    <div class="text-center p-4">
                        <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-slate-600 mx-auto mb-4 transition-colors hover:bg-indigo-100 hover:text-indigo-600">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <p class="text-sm font-bold text-slate-900">HDS</p>
                        <p class="text-xs text-slate-400 mt-1 uppercase">Certifié</p>
                    </div>
                    
                    <!-- Item 3 -->
                    <div class="text-center p-4">
                        <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-slate-600 mx-auto mb-4 transition-colors hover:bg-indigo-100 hover:text-indigo-600">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <p class="text-sm font-bold text-slate-900">AES-256</p>
                        <p class="text-xs text-slate-400 mt-1 uppercase">Chiffrement</p>
                    </div>
                    
                    <!-- Item 4 -->
                    <div class="text-center p-4">
                        <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-slate-600 mx-auto mb-4 transition-colors hover:bg-indigo-100 hover:text-indigo-600">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <p class="text-sm font-bold text-slate-900">24/7</p>
                        <p class="text-xs text-slate-400 mt-1 uppercase">Monitoring</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA — Sobre -->
    <section class="bg-slate-900 py-24">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center fade-up">
            <h2 class="font-serif text-3xl md:text-4xl font-bold text-white mb-4 tracking-tight">Prêt à travailler en toute sérénité ?</h2>
            <p class="text-slate-400 mb-10 text-lg max-w-md mx-auto">Votre cabinet, vos patients, vos données. PsySpace vous garantit une confidentialité totale.</p>
            
            <a href="register.php" class="inline-flex items-center gap-2 bg-white text-slate-900 font-bold px-8 py-4 rounded-lg hover:bg-indigo-50 hover:-translate-y-0.5 transition-all shadow-xl">
                Activer la protection
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>
    </section>

</main>

<!-- Script Animation -->
<script>
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.1 });
    document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
</script>

<?php include "footer.php"; ?>