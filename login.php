<?php
/**
 * PSYSPACE - LOGIN AVEC FIX REDIRECT
 */
session_start();

// 1. RÉCUPÉRATION DE LA CLÉ DEPUIS AZURE
$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: ""; 

// 2. GESTION DU BADGE (Si on arrive avec ?psypass=...)
if (isset($_GET['psypass'])) {
    if (!empty($admin_secret_key) && $_GET['psypass'] === $admin_secret_key) {
        // Création du cookie sécurisé
        setcookie("psyspace_boss_key", $admin_secret_key, [
            'expires' => time() + (365 * 24 * 60 * 60), // 1 an
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        // Redirection propre pour nettoyer l'URL
        header("Location: login.php?badge=success");
        exit();
    }
}

include "header.php"; 
?>

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
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-0.5">Connexion sécurisée</p>
                    <p class="text-sm text-white/80">Chiffrement AES-256 · HDS</p>
                </div>
            </div>
        </div>

        <div class="w-full md:w-7/12 bg-white p-10 md:p-14">

            <div class="mb-10">
                <h2 class="font-serif text-3xl font-bold text-slate-900 mb-2">Connexion</h2>
                <p class="text-sm text-slate-400">Accédez à votre espace praticien.</p>
            </div>

            <?php if(isset($_GET['badge']) && $_GET['badge'] == 'success'): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
                    ✅ Badge Admin activé. Les options d'administration sont débloquées.
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl flex items-center gap-3">
                    <svg class="shrink-0" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>
                        <?php
                            switch($_GET['error']) {
                                case "wrongpw": echo "Identifiants incorrects."; break;
                                case "noaccount": echo "Aucun compte trouvé."; break;
                                case "mailfail": echo "Erreur lors de l'envoi du mail de sécurité."; break;
                                case "captcha": echo "Vérification Turnstile échouée."; break;
                                default: echo "Une erreur est survenue.";
                            }
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <form id="loginForm" action="login_action.php" method="POST" class="space-y-5">
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Adresse email</label>
                    <input type="email" id="loginEmail" name="email" required
                           placeholder="votre@cabinet.fr"
                           value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email'], ENT_QUOTES, 'UTF-8') : ''; ?>"
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
                    <p id="pwError" class="text-xs text-red-500 hidden">8 caractères minimum requis.</p>
                </div>

                <div class="pt-2 flex justify-center">
                    <div class="cf-turnstile" data-sitekey="0x4AAAAAACwwGfr14_N69NoP"></div> 
                </div>

                <div class="pt-2">
                    <button type="submit" id="loginBtn"
                             class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold py-3.5 rounded-xl transition-all shadow-sm">
                        Se connecter
                    </button>
                </div>
            </form>

            <div class="mt-10 pt-8 border-t border-slate-100 text-center space-y-3">
                <p class="text-sm text-slate-500">
                    Pas encore de compte ? <a href="register.php" class="text-blue-600 font-semibold">Créer un compte</a>
                </p>
                <a href="index.php" class="text-xs text-slate-400 flex items-center justify-center gap-1">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
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

        loginBtn.disabled = !(emailOk && passOk);
    }

    emailInput.addEventListener('input', validate);
    passInput.addEventListener('input', validate);
});
</script>

<?php include "footer.php"; ?>