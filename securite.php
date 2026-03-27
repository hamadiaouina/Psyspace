<?php include "header.php"; ?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;0,900;1,400&family=Inter:wght@400;500;600;700&display=swap');
    
    body { font-family: 'Inter', sans-serif; scroll-behavior: smooth; }
    .font-serif { font-family: 'Merriweather', serif; }
    
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

    <section class="bg-slate-900 pt-24 pb-20 md:pt-32 md:pb-28 relative overflow-hidden">
        <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 24px 24px;"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="flex flex-col lg:flex-row items-center gap-16 text-center lg:text-left">
                <div class="flex-1 fade-up space-y-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 mx-auto lg:mx-0">
                        <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-pulse"></span>
                        <span class="text-xs font-semibold text-indigo-300 uppercase tracking-widest">Cyber-Sécurité active</span>
                    </div>
                    
                    <h1 class="font-serif text-4xl md:text-5xl font-bold text-white leading-tight">
                        Une architecture cloud<br>
                        <span class="text-indigo-400 italic">impénétrable.</span>
                    </h1>
                    
                    <p class="text-lg text-slate-400 leading-relaxed max-w-lg mx-auto lg:mx-0">
                        PsySpace repose sur une stack technologique moderne et sécurisée, garantissant l'intégrité de chaque donnée patient.
                    </p>
                </div>

                <div class="fade-up w-full lg:w-96 bg-slate-800/40 border border-white/10 rounded-2xl p-8 backdrop-blur-md shadow-2xl">
                    <div class="space-y-6">
                        <div class="flex justify-between items-center border-b border-white/5 pb-4">
                            <span class="text-slate-400 text-sm">Serveur</span>
                            <span class="text-white font-mono text-sm">Microsoft Azure</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-white/5 pb-4">
                            <span class="text-slate-400 text-sm">Protocole</span>
                            <span class="text-emerald-400 font-mono text-sm">HTTPS / TLS 1.3</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400 text-sm">Base de données</span>
                            <span class="text-white font-mono text-sm">MySQL (Encrypted)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 md:py-28 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center">
                
                <div class="fade-up space-y-8">
                    <div>
                        <h2 class="font-serif text-3xl font-bold text-slate-900 mb-6">Protection du site et du code</h2>
                        <p class="text-slate-500 leading-relaxed mb-6">
                            Le développement de PsySpace suit les directives de l'<strong>OWASP</strong> pour prévenir les vulnérabilités web courantes.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div class="flex gap-4">
                            <div class="mt-1 shrink-0 w-5 h-5 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900">Prévention des Injections SQL</h4>
                                <p class="text-sm text-slate-500">Utilisation systématique de requêtes préparées (PDO) pour isoler les données des commandes système.</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="mt-1 shrink-0 w-5 h-5 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900">Hachage de mots de passe</h4>
                                <p class="text-sm text-slate-500">Algorithme BCRYPT avec sel dynamique pour garantir que même nous ne connaissons pas vos accès.</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="mt-1 shrink-0 w-5 h-5 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-900">Protection XSS & CSRF</h4>
                                <p class="text-sm text-slate-500">Filtrage rigoureux des entrées utilisateurs et jetons de sécurité sur chaque formulaire.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fade-up relative">
                    <div class="aspect-square bg-slate-50 rounded-3xl flex items-center justify-center border border-slate-100 overflow-hidden">
                         <div class="text-center p-10">
                            <span class="text-6xl mb-4 block">🔒</span>
                            <div class="text-4xl font-black text-slate-900 mb-2">100%</div>
                            <p class="text-slate-400 font-semibold uppercase tracking-widest text-xs">Temps de disponibilité Azure</p>
                         </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-slate-50 py-20 border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h3 class="text-[11px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-12">Stack technologique de confiance</h3>
            <div class="flex flex-wrap justify-center gap-12 grayscale opacity-60">
                <span class="text-xl font-bold text-slate-800">Microsoft Azure</span>
                <span class="text-xl font-bold text-slate-800">PHP 8.2</span>
                <span class="text-xl font-bold text-slate-800">MySQL</span>
                <span class="text-xl font-bold text-slate-800">Cloudflare</span>
            </div>
        </div>
    </section>

</main>

<script>
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.1 });
    document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
</script>

<?php include "footer.php"; ?>