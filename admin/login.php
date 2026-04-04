<?php
// On inclut ton header qui démarre déjà la session et contient la logique du badge
include "../header.php"; 

// Vérification de sécurité (si le header.php n'a pas bloqué)
$admin_secret_key = getenv('ADMIN_BADGE_TOKEN') ?: "";
if (!isset($_COOKIE['psyspace_boss_key']) || $_COOKIE['psyspace_boss_key'] !== $admin_secret_key) {
    echo "<script>window.location.href='../index.php?error=unauthorized';</script>";
    exit();
}
?>

<!-- Interface de connexion -->
<div class="max-w-md mx-auto mt-20 p-6 bg-white dark:bg-slate-800 rounded-lg shadow-xl">
    <h2 class="text-2xl font-bold text-center text-slate-900 dark:text-white mb-6">Connexion Admin</h2>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-center font-semibold">
            <?php 
                if ($_GET['error'] == 'wrongpw') echo "Mot de passe incorrect.";
                elseif ($_GET['error'] == 'noaccount') echo "Aucun compte trouvé avec cet email.";
                elseif ($_GET['error'] == 'mailfail') echo "Erreur d'envoi du code.";
            ?>
        </div>
    <?php endif; ?>

    <!-- Le formulaire pointe vers login_action.php -->
    <form action="login_action.php" method="POST" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
            <input type="email" name="email" required class="w-full mt-1 p-2 border rounded-md dark:bg-slate-700 dark:border-slate-600 dark:text-white">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Mot de passe</label>
            <input type="password" name="password" required class="w-full mt-1 p-2 border rounded-md dark:bg-slate-700 dark:border-slate-600 dark:text-white">
        </div>
        <button type="submit" class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg transition-colors">
            Se connecter
        </button>
    </form>
</div>

</body>
</html>