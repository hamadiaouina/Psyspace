<?php include "header.php"; ?>

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<style>
    body { font-family: 'Inter', sans-serif; }

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

    /* Couleurs des alertes */
    .alert { display:flex; align-items:center; gap:12px; padding:14px 16px; border-radius:12px; font-size:13.5px; margin-bottom:20px; }
    .alert-red    { background:#fef2f2; border:1px solid #fecaca; color:#dc2626; }
    .alert-orange { background:#fffbeb; border:1px solid #fde68a; color:#d97706; }
    .alert-blue   { background:#eff6ff; border:1px solid #bfdbfe; color:#2563eb; }
    .alert-green  { background:#f0fdf4; border:1px solid #bbf7d0; color:#059669; }
</style>

<main class="min-h-screen bg-slate-50 flex items-center justify-center py-16 px-6">
    <div class="w-full max-w-5xl flex rounded-2xl shadow-xl overflow-hidden border border-slate-200 fade-in">

        <!-- Panneau gauche -->
        <div class="hidden md:flex w-5/12 bg-blue-950 flex-col justify-between p-12 text-white">
            <div>
                <a href="index.php" class="flex items-center gap-3 mb-16">
                    <img src="assets/images/logo.png" alt="PsySpace" class="h-8 w-auto">
                    <span class="text-lg font-bold">PsySpace</span>
                </a>
                <h2 class="text-3xl font-bold leading-snug mb-4">
                    L'intelligence<br>
                    <em class="text-blue-300 not-italic">au service du soin.</em>
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

        <!-- Panneau droit -->
        <div class="w-full md:w-7/12 bg-white p-10 md:p-14">

            <div class="mb-10">
                <h2 class="text-3xl font-bold text-slate-900 mb-2">Connexion</h2>
                <p class="text-sm text-slate-400">Accédez à votre espace praticien.</p>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <?php
                $error = $_GET['error'];
                $alertClass = 'alert-red';
                $alertIcon  = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
                $alertMsg   = 'Une erreur est survenue.';

                switch($error) {
                    case 'wrongpw':
                        $alertClass = 'alert-red';
                        $alertMsg   = 'Identifiants incorrects. Vérifiez votre email et mot de passe.';
                        break;
                    case 'noaccount':
                        $alertClass = 'alert-red';
                        $alertMsg   = 'Aucun compte trouvé avec cet email.';
                        break;
                    case 'suspended':
                        $alertClass = 'alert-orange';
                        $alertIcon  = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>';
                        $alertMsg   = 'Votre compte a été suspendu par l\'administrateur. Contactez le support.';
                        break;
                    case 'pending':
                        $alertClass = 'alert-blue';
                        $alertIcon  = '<circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3"/>';
                        $alertMsg   = 'Votre compte est en attente d\'activation par l\'administrateur.';
                        break;
                    case 'notactive':
                        $alertClass = 'alert-blue';
                        $alertIcon  = '<circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3"/>';
                        $alertMsg   = 'Votre compte n\'est pas encore activé.';
                        break;
                    case 'captcha':
                        $alertClass = 'alert-orange';
                        $alertIcon  = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>';
                        $alertMsg   = 'Vérification de sécurité échouée. Réessayez.';
                        break;
                    case 'empty':
                        $alertClass = 'alert-red';
                        $alertMsg   = 'Veuillez remplir tous les champs.';
                        break;
                    case 'server':
                        $alertClass = 'alert-red';
                        $alertMsg   = 'Erreur serveur temporaire. Réessayez dans quelques instants.';
                        break;
                }
                ?>
                <div class="alert <?= $alertClass ?>">
                    <svg class="shrink-0" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <?= $alertIcon ?>
                    </svg>
                    <span><?= $alertMsg ?></span>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-green">
                    <svg class="shrink-0" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>
                        <?php
                        switch($_GET['success']) {
                            case 'registered': echo 'Compte créé ! Vous pouvez vous connecter.'; break;
                            case 'verified':   echo 'Email vérifié avec succès. Vous pouvez vous connecter.'; break;
                            case 'reset':      echo 'Mot de passe réinitialisé avec succès.'; break;
                            default:           echo 'Opération réussie.';
                        }
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <form id="loginForm" action="login_action.php" method="POST" class="space-y-5" novalidate>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Adresse email</label>
                    <input type="email" id="loginEmail" name="email" required
                           placeholder="votre@cabinet.fr"
                           value="<?= isset($_GET['email']) ? htmlspecialchars($_GET['email'], ENT_QUOTES, 'UTF-8') : '' ?>"
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
                    <p id="pwError" class="text-xs text-red-500 hidden">Le mot de passe doit faire 8 caractères minimum.</p>
                </div>

                <div class="flex items-center gap-3">
                    <input type="checkbox" id="remember" name="remember" value="1"
                           class="w-4 h-4 rounded border-slate-300 text-blue-600 accent-blue-600 cursor-pointer">
                    <label for="remember" class="text-sm text-slate-500 cursor-pointer select-none">Rester connecté</label>
                </div>

                <!-- Cloudflare Turnstile -->
                <div class="pt-2 flex justify-center">
                    <div class="cf-turnstile" data-sitekey="0x4AAAAAACwwGfr14_N69NoP"></div>
                </div>

                <div class="pt-2">
                    <button type="submit" id="loginBtn"
                            class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold py-3.5 rounded-xl transition-all hover:-translate-y-0.5 shadow-sm">
                        Se connecter
                    </button>
                </div>
            </form>

            <div class="mt-10 pt-8 border-t border-slate-100 text-center space-y-3">
                <p class="text-sm text-slate-500">
                    Pas encore de compte ?
                    <a href="register.php" class="text-blue-600 font-semibold hover:text-blue-700 transition-colors ml-1">Créer un compte</a>
                </p>
                <a href="index.php" class="text-xs text-slate-400 flex items-center justify-center gap-1 hover:text-slate-600 transition-colors">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var emailInput = document.getElementById('loginEmail');
    var passInput  = document.getElementById('loginPassword');
    var loginBtn   = document.getElementById('loginBtn');
    var emailError = document.getElementById('emailError');
    var pwError    = document.getElementById('pwError');

    function validate() {
        var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value);
        var passOk  = passInput.value.length >= 8;

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

    emailInput.addEventListener('input', validate);
    passInput.addEventListener('input',  validate);
    validate();
});
</script>

<?php include "footer.php"; ?>