<?php include "header.php"; ?>

<!-- Cloudflare Turnstile (Captcha) -->
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;1,400&family=Inter:wght@400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; }
    .font-serif { font-family: 'Merriweather', serif; }

    .input-field {
        width: 100%;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        color: #0f172a;
        background: #f8fafc;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .input-field:focus {
        border-color: #2563eb;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
    }
    .input-field::placeholder { color: #94a3b8; }
    .input-field.error { border-color: #ef4444; }

    .fade-in { animation: fadeIn 0.5s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
</style>

<main class="min-h-screen bg-slate-50 flex items-center justify-center py-16 px-6">
    <div class="w-full max-w-5xl flex rounded-2xl shadow-xl overflow-hidden border border-slate-200 fade-in">

        <!-- PANNEAU GAUCHE — bleu foncé -->
        <div class="hidden md:flex w-5/12 bg-blue-950 flex-col justify-between p-12 text-white">
            <div>
                <a href="index.php" class="flex items-center gap-3 mb-16">
                    <img src="assets/images/logo.png" alt="PsySpace" class="h-8 w-auto">
                    <span class="text-lg font-bold">PsySpace</span>
                </a>
                <h2 class="font-serif text-3xl font-bold leading-snug mb-4">
                    L'intelligence<br>
                    <em class="text-blue-300">au service du soin.</em>
                </h2>
                <p class="text-blue-200/70 text-sm leading-relaxed">
                    Accédez à votre interface sécurisée pour piloter vos analyses cliniques.
                </p>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-5 flex items-center gap-4">
                <div class="w-10 h-10 bg-blue-500/20 rounded-xl flex items-center justify-center text-blue-300 shrink-0">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-0.5">Connexion sécurisée</p>
                    <p class="text-sm text-white/80">Chiffrement AES-256 · HDS</p>
                </div>
            </div>
        </div>

        <!-- PANNEAU DROIT — blanc -->
        <div class="w-full md:w-7/12 bg-white p-10 md:p-14">

            <div class="mb-10">
                <h2 class="font-serif text-3xl font-bold text-slate-900 mb-2">Connexion</h2>
                <p class="text-sm text-slate-400">Accédez à votre espace praticien.</p>
            </div>

            <!-- Erreurs PHP -->
            <?php if(isset($_GET['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl flex items-center gap-3">
                    <svg class="shrink-0" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php
                        if($_GET['error'] == "wrongpw")   echo "Identifiants incorrects. Vérifiez votre email et mot de passe.";
                        if($_GET['error'] == "noaccount")  echo "Aucun compte trouvé avec cet email.";
                        if($_GET['error'] == "notactive")  echo "Votre compte est en attente d'activation.";
                        if($_GET['error'] == "captcha")    echo "Captcha invalide. Réessayez.";
                        if($_GET['error'] == "captchaconfig") echo "Captcha non configuré côté serveur. Contactez l'admin.";
                    ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" action="login_action.php" method="POST" class="space-y-5" novalidate>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Adresse email</label>
                    <input type="email" id="loginEmail" name="email" required
                           placeholder="votre@cabinet.fr"
                           value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>"
                           class="input-field">
                    <p id="emailError" class="text-xs text-red-500 hidden">Adresse email invalide.</p>
                </div>

                <div class="space-y-1.5">
                    <div class="flex justify-between items-center">
                        <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Mot de passe</label>
                        <a href="forgot.php" class="text-xs text-blue-600 hover:text-blue-700 font-medium transition-colors">Mot de passe oublié ?</a>
                    </div>
                    <input type="password" id="loginPassword" name="password" required
                           placeholder="••••••••"
                           class="input-field">
                    <p id="pwError" class="text-xs text-red-500 hidden">Le mot de passe doit contenir au moins 8 caractères.</p>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" id="remember" name="remember" value="1"
                           class="w-4 h-4 rounded border-slate-300 text-blue-600 accent-blue-600 cursor-pointer">
                    <label for="remember" class="text-sm text-slate-500 cursor-pointer select-none">
                        Rester connecté
                    </label>
                </div>

                <!-- Turnstile widget -->
                <div class="pt-2 flex justify-center">
                    <!-- Remplace TA_SITE_KEY_ICI par ta *Site key* Turnstile (publique) -->
                    <div class="cf-turnstile" data-sitekey="1x00000000000000000000AA"></div> 
                </div>

                <div class="pt-2">
                    <button type="submit" id="loginBtn"
                            class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold py-3.5 rounded-xl transition-all hover:-translate-y-0.5 shadow-sm shadow-blue-100">
                        Se connecter
                    </button>
                </div>

            </form>

            <div class="mt-10 pt-8 border-t border-slate-100 text-center space-y-3">
                <p class="text-sm text-slate-500">
                    Pas encore de compte ?
                    <a href="register.php" class="text-blue-600 font-semibold hover:text-blue-700 transition-colors ml-1">Créer un compte</a>
                </p>
                <a href="index.php" class="text-xs text-slate-400 hover:text-slate-600 transition-colors flex items-center justify-center gap-1">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    Retour à l'accueil
                </a>
            </div>

        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const emailInput = document.getElementById('loginEmail');
    const passInput  = document.getElementById('loginPassword');
    const loginBtn   = document.getElementById('loginBtn');
    const emailError = document.getElementById('emailError');
    const pwError    = document.getElementById('pwError');

    function validate() {
        const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value);
        const passOk  = passInput.value.length >= 8;

        if (emailInput.value && !emailOk) {
            emailInput.classList.add('error');
            emailError.classList.remove('hidden');
        } else {
            emailInput.classList.remove('error');
            emailError.classList.add('hidden');
        }

        if (passInput.value && !passOk) {
            passInput.classList.add('error');
            pwError.classList.remove('hidden');
        } else {
            passInput.classList.remove('error');
            pwError.classList.add('hidden');
        }

        // Note: on ne peut pas vérifier côté JS si le captcha est résolu.
        // La vérification du captcha se fait côté serveur dans login_action.php.
        loginBtn.disabled = !(emailOk && passOk);
    }

    emailInput.addEventListener('input', validate);
    passInput.addEventListener('input', validate);
    validate();
});
</script>

<?php include "footer.php"; ?>