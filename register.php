<?php 
include "header.php"; 

// --- SÉCURITÉ : GÉNÉRATION DU TOKEN CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!-- SÉCURITÉ : Nonce ajouté pour autoriser Cloudflare Turnstile -->
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" nonce="<?= $nonce ?? '' ?>" async defer></script>

<style nonce="<?= $nonce ?? '' ?>">
    body { font-family: 'Inter', sans-serif; }
    .fade-in { animation: fadeIn 0.5s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

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

    input[type="date"]::-webkit-calendar-picker-indicator {
        filter: invert(0.5);
        cursor: pointer;
    }

    .password-strength {
        height: 3px;
        background: #e2e8f0;
        border-radius: 2px;
        margin-top: 8px;
        overflow: hidden;
    }
    .strength-meter {
        height: 100%;
        width: 0;
        transition: width 0.3s, background-color 0.3s;
        border-radius: 2px;
    }
</style>

<main class="min-h-screen bg-slate-50 flex items-center justify-center py-16 px-6">
    <div class="w-full max-w-5xl flex rounded-3xl shadow-xl overflow-hidden border border-slate-200 fade-in bg-white">

        <!-- Panneau gauche -->
        <div class="hidden md:flex w-5/12 bg-gradient-to-b from-slate-900 to-indigo-950 flex-col justify-between p-12 text-white">
            <div>
                <a href="index.php" class="flex items-center gap-3 mb-12">
                    <img src="assets/images/logo.png" alt="PsySpace" class="h-8 w-auto">
                    <span class="text-lg font-bold">PsySpace</span>
                </a>
                <h2 class="text-3xl font-bold leading-snug mb-4">
                    Rejoignez une pratique<br>
                    <em class="text-indigo-300 not-italic">plus fluide.</em>
                </h2>
                <p class="text-indigo-200/70 text-sm leading-relaxed">
                    Créez votre compte professionnel et accédez à un espace sécurisé pour gérer votre activité clinique.
                </p>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-5 flex items-center gap-4">
                <div class="w-10 h-10 bg-indigo-500/20 rounded-xl flex items-center justify-center text-indigo-300 shrink-0">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-0.5">Protection des données</p>
                    <p class="text-sm text-white/80">RGPD · HDS · chiffrement AES-256</p>
                </div>
            </div>
        </div>

        <!-- Panneau droit -->
        <div class="w-full md:w-7/12 p-10 md:p-14">
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-slate-900 mb-2">Créer un compte</h2>
                <p class="text-sm text-slate-400">Renseignez vos informations pour ouvrir votre espace praticien.</p>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl flex items-center gap-3">
                    <svg class="shrink-0" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php
                        switch($_GET['error']) {
                            case "emailexist": echo "Cette adresse email est déjà utilisée."; break;
                            case "empty": echo "Tous les champs sont obligatoires."; break;
                            case "csrf": echo "Session invalide, veuillez réessayer."; break;
                            case "captcha": echo "Échec de la vérification anti-robot."; break;
                            case "weakpass": echo "Le mot de passe ne respecte pas les critères de sécurité."; break;
                            default: echo "Une erreur est survenue.";
                        }
                    ?>
                </div>
            <?php endif; ?>

            <form id="registerForm" action="register_action.php" method="POST" class="space-y-5" novalidate>
                
                <!-- SÉCURITÉ : Jeton CSRF -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <!-- SÉCURITÉ : Honeypot (Piège à robots, invisible pour les humains) -->
                <div style="display:none;" aria-hidden="true">
                    <label for="hp_registration">Ne pas remplir ce champ</label>
                    <input type="text" name="hp_registration" id="hp_registration" tabindex="-1" autocomplete="off">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Nom</label>
                        <input type="text" id="nom" name="nom" required autocomplete="family-name" class="input-field" placeholder="Ex. Aouina">
                        <p id="nomError" class="text-xs text-red-500 hidden">Veuillez renseigner un nom valide.</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Prénom</label>
                        <input type="text" id="prenom" name="prenom" required autocomplete="given-name" class="input-field" placeholder="Ex. Hamadi">
                        <p id="prenomError" class="text-xs text-red-500 hidden">Veuillez renseigner un prénom valide.</p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Date de naissance</label>
                    <input type="date" id="dob" name="dob" required autocomplete="bday" class="input-field">
                    <p id="dobError" class="text-xs text-red-500 hidden">Vous devez avoir au moins 18 ans.</p>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Adresse email professionnelle</label>
                    <input type="email" id="email" name="email" required autocomplete="email" class="input-field" placeholder="votre@cabinet.fr">
                    <p id="emailError" class="text-xs text-red-500 hidden">Adresse email invalide.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Mot de passe</label>
                        <input type="password" id="password" name="password" required autocomplete="new-password" class="input-field" placeholder="••••••••">
                        <div class="password-strength">
                            <div class="strength-meter" id="strengthMeter"></div>
                        </div>
                        <p id="passwordHelp" class="text-xs text-slate-400 mt-1">Minimum 8 caractères avec majuscule et chiffre</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Confirmer le mot de passe</label>
                        <input type="password" id="confirm_password" required autocomplete="new-password" class="input-field" placeholder="••••••••">
                        <p id="confirmHelp" class="text-xs text-red-500 hidden">Les mots de passe ne correspondent pas.</p>
                    </div>
                </div>

                <!-- SÉCURITÉ : Widget Turnstile ajouté ici -->
                <div class="pt-2 flex justify-center">
                    <div class="cf-turnstile" data-sitekey="0x4AAAAAACwwGfr14_N69NoP"></div> 
                </div>

                <div class="pt-2">
                    <button type="submit" id="submitBtn" disabled
                        class="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold py-3.5 rounded-xl transition-all hover:-translate-y-0.5 shadow-sm shadow-indigo-100">
                        Créer mon compte
                    </button>
                </div>
            </form>

            <div class="mt-10 pt-8 border-t border-slate-100 text-center">
                <p class="text-sm text-slate-500">
                    Vous avez déjà un compte ?
                    <a href="login.php" class="text-indigo-600 font-semibold hover:text-indigo-700 transition-colors ml-1">Se connecter</a>
                </p>
            </div>
        </div>
    </div>
</main>

<script nonce="<?= $nonce ?? '' ?>">
/* ═══════════════════════════════════════════════════
   RÈGLES UNIFIÉES — une seule source de vérité
   strength >= 3 sur 4 critères pour activer le bouton
═══════════════════════════════════════════════════ */
function getPasswordStrength(pass) {
    var score = 0;
    if (pass.length >= 8)        score++;
    if (/[A-Z]/.test(pass))      score++;
    if (/[0-9]/.test(pass))      score++;
    if (/[^A-Za-z0-9]/.test(pass)) score++;
    return score; // 0 à 4
}

function checkPasswordStrength() {
    var pass    = document.getElementById('password').value;
    var meter   = document.getElementById('strengthMeter');
    var help    = document.getElementById('passwordHelp');
    var score   = getPasswordStrength(pass);

    if (pass.length === 0) {
        meter.style.width = '0%';
        help.textContent  = 'Minimum 8 caractères avec majuscule et chiffre';
        help.className    = 'text-xs text-slate-400 mt-1';
        validateForm(); return;
    }

    meter.style.width = (score / 4 * 100) + '%';

    if (score <= 1) {
        meter.style.backgroundColor = '#ef4444';
        help.textContent = 'Mot de passe trop faible';
        help.className   = 'text-xs text-red-500 mt-1';
    } else if (score === 2) {
        meter.style.backgroundColor = '#f59e0b';
        help.textContent = 'Mot de passe moyen';
        help.className   = 'text-xs text-amber-500 mt-1';
    } else if (score === 3) {
        meter.style.backgroundColor = '#3b82f6';
        help.textContent = 'Mot de passe correct ✓';
        help.className   = 'text-xs text-blue-500 mt-1';
    } else {
        meter.style.backgroundColor = '#10b981';
        help.textContent = 'Mot de passe sécurisé ✓';
        help.className   = 'text-xs text-emerald-500 mt-1';
    }
    validateForm();
}

function checkPasswordMatch() {
    var pass1       = document.getElementById('password').value;
    var pass2       = document.getElementById('confirm_password').value;
    var confirmHelp = document.getElementById('confirmHelp');

    if (pass2.length > 0 && pass1 !== pass2) {
        confirmHelp.classList.remove('hidden');
        document.getElementById('confirm_password').classList.add('error');
    } else {
        confirmHelp.classList.add('hidden');
        document.getElementById('confirm_password').classList.remove('error');
    }
    validateForm();
}

function validateField(name) {
    var field    = document.getElementById(name);
    var errorMsg = document.getElementById(name + 'Error');
    if (!field || !errorMsg) return;

    var isValid = false;
    switch(name) {
        case 'nom':
        case 'prenom':
            isValid = /^[a-zA-ZÀ-ÿ\s\-]{2,}$/.test(field.value);
            break;
        case 'dob':
            if (!field.value) return;
            var birth    = new Date(field.value);
            var today    = new Date();
            var age      = today.getFullYear() - birth.getFullYear();
            var m        = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            isValid = age >= 18;
            break;
        case 'email':
            isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value);
            break;
    }

    if (field.value && !isValid) {
        field.classList.add('error');
        errorMsg.classList.remove('hidden');
    } else {
        field.classList.remove('error');
        errorMsg.classList.add('hidden');
    }
    validateForm();
}

function validateForm() {
    var nom    = document.getElementById('nom').value.trim();
    var prenom = document.getElementById('prenom').value.trim();
    var dob    = document.getElementById('dob').value;
    var email  = document.getElementById('email').value.trim();
    var pass1  = document.getElementById('password').value;
    var pass2  = document.getElementById('confirm_password').value;
    var btn    = document.getElementById('submitBtn');

    var emailOk    = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    var passScore  = getPasswordStrength(pass1);
    var ok = nom && prenom && dob && emailOk && passScore >= 3 && pass1 === pass2 && pass1.length > 0;
    btn.disabled = !ok;
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('nom').addEventListener('input',    function(){ validateField('nom'); });
    document.getElementById('prenom').addEventListener('input', function(){ validateField('prenom'); });
    document.getElementById('dob').addEventListener('change',   function(){ validateField('dob'); });
    document.getElementById('email').addEventListener('input',  function(){ validateField('email'); });
    document.getElementById('password').addEventListener('input', function(){
        checkPasswordStrength();
        checkPasswordMatch();
    });
    document.getElementById('confirm_password').addEventListener('input', function(){
        checkPasswordMatch();
    });
});
</script>

<?php include "footer.php"; ?>