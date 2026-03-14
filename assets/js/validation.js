document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registerForm');
    const nom = document.getElementById('nom');
    const prenom = document.getElementById('prenom');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    const submitBtn = document.getElementById('submitBtn');

    // Éléments d'erreur
    const nameError = document.getElementById('nameError');
    const emailError = document.getElementById('emailError');
    const pwError = document.getElementById('pwError');
    const matchError = document.getElementById('matchError');

    function validate() {
        // Regex : Uniquement des lettres, espaces et tirets (pas de chiffres)
        const nameRegex = /^[a-zA-ZÀ-ÿ\s\-]+$/;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        let isNomValid = nameRegex.test(nom.value) && nom.value.length >= 2;
        let isPrenomValid = nameRegex.test(prenom.value) && prenom.value.length >= 2;
        let isEmailValid = emailRegex.test(email.value);
        let isPwStrong = password.value.length >= 8;
        let isMatch = password.value === confirmPassword.value && confirmPassword.value !== "";

        // Affichage des erreurs
        toggleError(nameError, !(isNomValid && isPrenomValid), (nom.value || prenom.value));
        toggleError(emailError, !isEmailValid, email.value);
        toggleError(pwError, !isPwStrong, password.value);
        toggleError(matchError, !isMatch, confirmPassword.value);

        // Activation du bouton
        submitBtn.disabled = !(isNomValid && isPrenomValid && isEmailValid && isPwStrong && isMatch);
    }

    function toggleError(element, shouldShow, hasValue) {
        if (shouldShow && hasValue) {
            element.classList.remove('hidden');
        } else {
            element.classList.add('hidden');
        }
    }

    // Bloquer la saisie de chiffres en direct
    [nom, prenom].forEach(input => {
        input.addEventListener('keypress', (e) => {
            if (/\d/.test(e.key)) {
                e.preventDefault();
            }
        });
    });

    // Écouter les changements
    [nom, prenom, email, password, confirmPassword].forEach(input => {
        input.addEventListener('input', validate);
    });
});