<?php include "header.php"; ?>

<style>
    /* Rappel du design global PsySpace */
    .bg-soft-mesh {
        background-color: #ffffff;
        background-image: radial-gradient(at 0% 0%, rgba(37, 99, 235, 0.05) 0px, transparent 50%),
                          radial-gradient(at 100% 100%, rgba(37, 99, 235, 0.05) 0px, transparent 50%);
    }

    .glass-card {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(226, 232, 240, 0.8);
    }

    .input-otp {
        transition: all 0.3s ease;
        border: 1.5px solid #e2e8f0;
        background: rgba(248, 250, 252, 0.5);
    }

    .input-otp:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        background: white;
        outline: none;
    }

    .fade-in {
        animation: fadeIn 0.8s ease-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<main class="min-h-screen bg-soft-mesh flex items-center justify-center py-20 px-6">
    <div class="max-w-md w-full glass-card rounded-[2.5rem] shadow-2xl p-10 md:p-14 text-center fade-in">
        
        <div class="mb-10">
            <div class="w-20 h-20 bg-blue-50 text-blue-600 rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-inner">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Vérification</h2>
            <p class="text-slate-500 mt-3 text-sm font-medium leading-relaxed">
                Un code a été envoyé à :<br>
                <span class="text-blue-600 font-bold"><?php echo htmlspecialchars($_GET['email']); ?></span>
            </p>
        </div>

        <?php if(isset($_GET['error']) && $_GET['error'] == "wrongotp"): ?>
            <div class="mb-8 p-4 bg-red-50 border-l-2 border-red-500 text-red-700 text-[11px] rounded-r-lg font-bold flex items-center gap-3">
                <span>⚠️</span>
                Code incorrect. Veuillez réessayer.
            </div>
        <?php endif; ?>

        <form action="verify_action.php" method="POST" class="space-y-8">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email']); ?>">
            
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Saisir le code à 6 chiffres</label>
                <input type="text" name="otp" maxlength="6" required
                    class="input-otp w-full text-center text-4xl tracking-[0.8rem] font-black py-5 rounded-2xl text-slate-900"
                    placeholder="000000">
            </div>

            <button type="submit" 
                class="w-full bg-blue-600 text-white text-sm font-bold py-4 rounded-xl hover:bg-slate-900 transition-all duration-300 shadow-xl shadow-blue-100 transform active:scale-[0.98]">
                Vérifier mon identité
            </button>
        </form>

        <div class="mt-12 pt-8 border-t border-slate-100">
            <p class="text-sm text-slate-500 font-medium">
                Vous n'avez rien reçu ?<br>
                <a href="#" class="text-blue-600 font-bold hover:text-slate-900 transition-colors inline-block mt-2">Renvoyer un nouveau code</a>
            </p>
            
            <a href="login.php" class="mt-6 text-[10px] text-slate-400 hover:text-blue-600 font-bold uppercase tracking-widest transition-colors block">
                ← Retour à la connexion
            </a>
        </div>
    </div>
</main>

<?php include "footer.php"; ?>