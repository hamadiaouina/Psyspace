<?php include "header.php"; ?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;1,400&family=Inter:wght@400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; }
    .font-serif { font-family: 'Merriweather', serif; }
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
        border-color: #2563eb;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
    }
    .input-field::placeholder { color: #94a3b8; }
    .input-field.error { border-color: #ef4444; }

    input[type="date"]::-webkit-calendar-picker-indicator {
        filter: invert(0.5);
        cursor: pointer;
    }

    .password-strength {
        height: 2px;
        background: #e2e8f0;
        border-radius: 1px;
        margin-top: 8px;
        overflow: hidden;
    }
    .strength-meter {
        height: 100%;
        width: 0;
        transition: width 0.3s, background 0.3s;
    }
</style>

<main class="min-h-screen bg-slate-50 flex items-center justify-center py-16 px-6">
    <div class="w-full max-w-5xl flex rounded-3xl shadow-xl overflow-hidden border border-slate-200 fade-in bg-white">

        <!-- Panneau gauche -->
        <div class="hidden md:flex w-5/12 bg-blue-950 flex-col justify-between p-12 text-white">
            <div>
                <a href="index.php" class="flex items-center gap-3 mb-12">
                    <img src="assets/images/logo.png" alt="PsySpace" class="h-8 w-auto">
                    <span class="text-lg font-bold">PsySpace</span>
                </a>
                <h2 class="font-serif text-3xl font-bold leading-snug mb-4">
                    Rejoignez une pratique<br>
                    <em class="text-blue-300">plus fluide.</em>
                </h2>
                <p class="text-blue-200/70 text-sm leading-relaxed">
                    Créez votre compte professionnel et accédez à un espace sécurisé pour gérer votre activité clinique.
                </p>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-5 flex items-center gap-4">
                <div class="w-10 h-10 bg-blue-500/20 rounded-xl flex items-center justify-center text-blue-300 shrink-0">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-0.5">Protection des données</p>
                    <p class="text-sm text-white/80">RGPD · HDS · chiffrement AES-256</p>
                </div>
            </div>
        </div>

        <!-- Panneau droit -->
        <div class="w-full md:w-7/12 p-10 md:p-14">
            <div class="mb-8">
                <h2 class="font-serif text-3xl font-bold text-slate-900 mb-2">Créer un compte</h2>
                <p class="text-sm text-slate-400">Renseignez vos informations pour ouvrir votre espace praticien.</p>
            </div>

            <?php if(isset($_GET['error'])): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl flex items-center gap-3">
                    <svg class="shrink-0" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php
                        if($_GET['error'] == "emailexist") echo "Cette adresse email est déjà utilisée.";
                        if($_GET['error'] == "empty") echo "Tous les champs sont obligatoires.";
                    ?>
                </div>
            <?php endif; ?>

            <form id="registerForm" action="register_action.php" method="POST" class="space-y-5" novalidate>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Nom</label>
                        <input type="text" id="nom" name="nom" required
                               class="input-field"
                               placeholder="Ex. Aouina"
                               oninput="validateField('nom')">
                        <p id="nomError" class="text-xs text-red-500 hidden">Veuillez renseigner un nom valide.</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Prénom</label>
                        <input type="text" id="prenom" name="prenom" required
                               class="input-field"
                               placeholder="Ex. Hamadi"
                               oninput="validateField('prenom')">
                        <p id="prenomError" class="text-xs text-red-500 hidden">Veuillez renseigner un prénom valide.</p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Date de naissance</label>
                    <input type="date" id="dob" name="dob" required
                           class="input-field"
                           onchange="validateField('dob')">
                    <p id="dobError" class="text-xs text-red-500 hidden">Vous devez avoir au moins 18 ans.</p>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Adresse email professionnelle</label>
                    <input type="email" id="email" name="email" required
                           class="input-field"
                           placeholder="votre@cabinet.fr"
                           oninput="validateField('email')">
                    <p id="emailError" class="text-xs text-red-500 hidden">Adresse email invalide.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Mot de passe</label>
                        <input type="password" id="password" name="password" required
                               class="input-field"
                               placeholder="••••••••"
                               oninput="checkPasswordStrength()">
                        <div class="password-strength">
                            <div class="strength-meter" id="strengthMeter"></div>
                        </div>
                        <p id="passwordHelp" class="text-xs text-slate-400 mt-1">Minimum 12 caractères avec majuscules, chiffres et symboles</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Confirmer le mot de passe</label>
                        <input type="password" id="confirm_password" required
                               class="input-field"
                               placeholder="••••••••"
                               oninput="checkPasswordMatch()">
                        <p id="confirmHelp" class="text-xs text-red-500 hidden">Les mots de passe ne correspondent pas.</p>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" id="submitBtn"
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold py-3.5 rounded-xl transition-all hover:-translate-y-0.5 shadow-sm shadow-blue-100">
                        Créer mon compte
                    </button>
                </div>
            </form>

            <div class="mt-10 pt-8 border-t border-slate-100 text-center">
                <p class="text-sm text-slate-500">
                    Vous avez déjà un compte ?
                    <a href="login.php" class="text-blue-600 font-semibold hover:text-blue-700 transition-colors ml-1">Se connecter</a>
                </p>
            </div>
        </div>
    </div>
</main>

<script>
function checkPasswordStrength() {
    const password = document.getElementById('password').value;
    const meter = document.getElementById('strengthMeter');
    const helpText = document.getElementById('passwordHelp');

    // Reset
    meter.style.width = '0%';
    meter.style.backgroundColor = '';

    if (password.length === 0) {
        helpText.textContent = 'Minimum 12 caractères avec majuscules, chiffres et symboles';
        helpText.className = 'text-xs text-slate-400 mt-1';
        return;
    }

    // Strength calculation
    let strength = 0;
    if (password.length >= 12) strength += 1;
    if (/[A-Z]/.test(password)) strength += 1;
    if (/[0-9]/.test(password)) strength += 1;
    if (/[^A-Za-z0-9]/.test(password)) strength += 1;

    // Update UI
    const width = (strength / 4) * 100;
    meter.style.width = width + '%';

    if (strength < 2) {
        meter.style.backgroundColor = '#ef4444';
        helpText.textContent = 'Mot de passe trop faible';
        helpText.className = 'text-xs text-red-500 mt-1';
    }
    else if (strength < 4) {
        meter.style.backgroundColor = '#f59e0b';
        helpText.textContent = 'Mot de passe moyen';
        helpText.className = 'text-xs text-yellow-500 mt-1';
    }
    else {
        meter.style.backgroundColor = '#10b981';
        helpText.textContent = 'Mot de passe fort';
        helpText.className = 'text-xs text-emerald-500 mt-1';
    }

    validateForm();
}

function checkPasswordMatch() {
    const pass1 = document.getElementById('password').value;
    const pass2 = document.getElementById('confirm_password').value;
    const confirmHelp = document.getElementById('confirmHelp');

    if (pass2.length > 0 && pass1 !== pass2) {
        confirmHelp.classList.remove('hidden');
    } else {
        confirmHelp.classList.add('hidden');
    }

    validateForm();
}

function validateField(name) {
    const field = document.getElementById(name);
    const errorMsg = document.getElementById(name + 'Error');
    let isValid = false;

    switch(name) {
        case 'nom':
        case 'prenom':
            isValid = /^[a-zA-ZÀ-ÿ\s\-]{2,}$/.test(field.value);
            break;
        case 'dob':
            if(!field.value) return;
            const birth = new Date(field.value);
            const age = new Date().getFullYear() - birth.getFullYear();
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
    const nom = document.getElementById('nom').value;
    const prenom = document.getElementById('prenom').value;
    const dob = document.getElementById('dob').value;
    const email = document.getElementById('email').value;
    const pass1 = document.getElementById('password').value;
    const pass2 = document.getElementById('confirm_password').value;
    const submitBtn = document.getElementById('submitBtn');

    // Check email validity
    const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

    // Check password strength
    let strength = 0;
    if (pass1.length >= 12) strength += 1;
    if (/[A-Z]/.test(pass1)) strength += 1;
    if (/[0-9]/.test(pass1)) strength += 1;
    if (/[^A-Za-z0-9]/.test(pass1)) strength += 1;

    submitBtn.disabled = !(nom && prenom && dob && emailValid && strength >= 4 && pass1 === pass2 && pass1.length > 0);
}

// Initial validation
document.addEventListener('DOMContentLoaded', () => {
    const inputs = ['nom', 'prenom', 'dob', 'email', 'password', 'confirm_password'];
    inputs.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', () => validateField(id));
    });
});
</script>

<?php include "footer.php"; ?>