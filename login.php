<?php 
include "header.php"; 

// --- SÉCURITÉ : GÉNÉRATION DU TOKEN CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<style nonce="<?= $nonce ?? '' ?>">
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
        border-color: #4f46e5;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(79,70,229,0.08);
    }
    .input-field::placeholder { color: #94a3b8; }
    .input-field.error { border-color: #ef4444; }

    .fade-in { animation: fadeIn 0.5s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
</style>

<main class="min-h-screen bg-slate-50 flex items-center justify-center py-16 px-6">
    <div class="w-full max-w-5xl flex rounded-2xl shadow-xl overflow-hidden border border-slate-200 fade-in">

        <!-- PARTIE GAUCHE (DESIGN) -->
        <div class="hidden md:flex w-5/12 bg-gradient-to-b from-slate-900 to-indigo-950 flex-col justify-between p-12 text-white">
            <div>
                <a href="index.php" class="flex items-center gap-3 mb-16">
                    <img src="assets/images/logo.png" alt="PsySpace" class="h-8 w-auto">
                    <span class="text-lg font-bold">PsySpace</span>
                </a>
                <h2 class="font-serif text-3xl font-bold leading-snug mb-4">
                    L'intelligence<br>
                    <em class="text-indigo-300">au service du soin.</em>
                </h2>
                <p class="text-indigo-200/70 text-sm leading-relaxed">
                    Accédez à votre interface sécurisée pour piloter vos analyses cliniques.
                </p>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-5 flex items-center gap-4">
                <div class="w-10 h-10 bg-indigo-500/20 rounded-xl flex items-center justify-center text-indigo-300 shrink-0">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-0.5">Connexion sécurisée</p>
                    <p class="text-sm text-white/80">Chiffrement AES-256 · HDS</p>
                </div>
            </div>
        </div>

        <!-- PARTIE DROITE (FORMULAIRE) -->
        <div class="w-full md:w-7/12 bg-white p-10 md:p-14">

            <div class="mb-10">
                <h2 class="font-serif text-3xl font-bold text-slate-900 mb-2">Connexion</h2>
                <p class="text-sm text-slate-400">Accédez à votre espace praticien.</p>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl flex items-center gap-3">
                    <svg class="shrink-0" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>
                        <?php
                            switch($_GET['error']) {
                                case "wrongpw": echo "Identifiants incorrects."; break;
                                case "noaccount": echo "Aucun compte trouvé."; break;
                                case "notactive": echo "Compte en attente d'activation."; break;
                                case "captcha": echo "Vérification de sécurité échouée."; break;
                                case "bruteforce": echo "Trop de tentatives. Veuillez patienter 5 minutes."; break;
                                case "suspended": echo "Ce compte a été suspendu par l'administration."; break;
                                case "pending": echo "Votre compte est en attente d'activation."; break;
                                case "hijack": echo "Session expirée par mesure de sécurité."; break;
                                case "csrf": echo "Jeton de sécurité invalide. Veuillez réessayer."; break;
                                default: echo "Une erreur est survenue.";
                            }
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <form id="loginForm" action="login_action.php" method="POST" class="space-y-5" novalidate>
                
                <!-- SÉCURITÉ : Jeton CSRF -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <!-- SÉCURITÉ : Honeypot (Piège à robots, invisible pour les humains) -->
                <div style="display:none;" aria-hidden="true">
                    <label for="hp_website">Ne pas remplir ce champ</label>
                    <input type="text" name="hp_website" id="hp_website" tabindex="-1" autocomplete="off">
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Adresse email</label>
                    <input type="email" id="loginEmail" name="email" required autocomplete="email"
                           placeholder="votre@cabinet.fr"
                           value="<?php echo isset($_SESSION['login_email_attempt']) ? htmlspecialchars($_SESSION['login_email_attempt'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                           class="input-field">
                    <p id="emailError" class="text-xs text-red-500 hidden">Adresse email invalide.</p>
                </div>

                <!-- CHAMP MOT DE PASSE AVEC L'ŒIL -->
                <div class="space-y-1.5">
                    <div class="flex justify-between items-center">
                        <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Mot de passe</label>
                        <a href="forgot.php" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium transition-colors">Mot de passe oublié ?</a>
                    </div>
                    <div class="relative">
                        <input type="password" id="loginPassword" name="password" required autocomplete="current-password"
                               placeholder="••••••••"
                               class="input-field pr-12"> <!-- pr-12 laisse de la place pour l'œil -->
                        
                        <!-- Bouton Œil -->
                        <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-blue-600 transition-colors focus:outline-none">
                            <!-- Icône Œil Ouvert -->
                            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <!-- Icône Œil Fermé (caché par défaut) -->
                            <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 hidden">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    <p id="pwError" class="text-xs text-red-500 hidden">Le mot de passe doit faire 8 caractères minimum.</p>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" id="remember" name="remember" value="1"
                           class="w-4 h-4 rounded border-slate-300 text-indigo-600 accent-indigo-600 cursor-pointer">
                    <label for="remember" class="text-sm text-slate-500 cursor-pointer select-none">Rester connecté</label>
                </div>

                <div class="pt-2 flex justify-center">
                    <div class="cf-turnstile" data-sitekey="0x4AAAAAACwwGfr14_N69NoP"></div> 
                </div>

                <div class="pt-2">
                    <button type="submit" id="loginBtn"
                            class="w-full flex justify-center items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold py-3.5 rounded-xl transition-all hover:-translate-y-0.5 shadow-sm">
                        <span id="btnText">Se connecter</span>
                        
                        <svg id="btnSpinner" class="hidden animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </div>
            </form>

            <div class="mt-10 pt-8 border-t border-slate-100 text-center space-y-3">
                <p class="text-sm text-slate-500">
                    Pas encore de compte ? <a href="register.php" class="text-indigo-600 font-semibold">Créer un compte</a>
                </p>
                <a href="index.php" class="text-xs text-slate-400 flex items-center justify-center gap-1">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</main>

<script nonce="<?= $nonce ?? '' ?>">
document.addEventListener('DOMContentLoaded', () => {
    const emailInput = document.getElementById('loginEmail');
    const passInput  = document.getElementById('loginPassword');
    const loginBtn   = document.getElementById('loginBtn');
    const emailError = document.getElementById('emailError');
    const pwError    = document.getElementById('pwError');
    
    const loginForm  = document.getElementById('loginForm');
    const btnText    = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');

    // --- ANIMATION DE L'ŒIL POUR LE MOT DE PASSE ---
    const togglePassword = document.getElementById('togglePassword');
    const eyeOpen = document.getElementById('eyeOpen');
    const eyeClosed = document.getElementById('eyeClosed');

    togglePassword.addEventListener('click', function () {
        // Alterne entre 'password' et 'text'
        const type = passInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passInput.setAttribute('type', type);
        
        // Alterne l'affichage des deux icônes SVG
        eyeOpen.classList.toggle('hidden');
        eyeClosed.classList.toggle('hidden');
    });

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

        loginBtn.disabled = !(emailOk && passOk);
    }

    // --- ANIMATION DU BOUTON AU CLIC ---
    loginForm.addEventListener('submit', function() {
        btnText.textContent = "Connexion...";
        btnSpinner.classList.remove('hidden');
        loginBtn.classList.remove('hover:-translate-y-0.5');
        loginBtn.classList.add('cursor-wait', 'opacity-80');
    });

    emailInput.addEventListener('input', validate);
    passInput.addEventListener('input', validate);
    validate();
});
</script>

<?php include "footer.php"; ?>