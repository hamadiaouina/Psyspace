<?php
include "connection.php"; // Vérifie bien que ce fichier est à jour avec les infos du serveur
$message = "";
$show_form = false;

// 1. On récupère le token (soit de l'URL, soit du formulaire envoyé)
$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : '');

if(!empty($token)){
    $safe_token = mysqli_real_escape_string($con, $token);
    
    // On vérifie si le token existe et n'a pas expiré sur le serveur
    $query = "SELECT * FROM doctor WHERE reset_token='$safe_token' AND token_expiry > NOW()";
    $res = mysqli_query($con, $query);
    
    if(mysqli_num_rows($res) > 0) {
        $show_form = true;
    } else {
        $message = "<div class='p-5 text-rose-800 bg-rose-50 rounded-2xl border-2 border-rose-100 font-bold text-center'>Lien invalide ou expiré.</div>";
    }
}

// 2. Traitement du changement
if(isset($_POST['update-password'])){
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    if($pass1 !== $pass2){
        $message = "<div class='p-4 mb-4 text-rose-800 bg-rose-50 rounded-2xl font-bold text-xs text-center'>Les mots de passe ne correspondent pas.</div>";
        $show_form = true;
    } else {
        $new_hashed_password = password_hash($pass1, PASSWORD_DEFAULT);
        
        $update_query = "UPDATE doctor SET 
                         docpassword = '$new_hashed_password', 
                         reset_token = NULL, 
                         token_expiry = NULL 
                         WHERE reset_token = '$safe_token'";

        if(mysqli_query($con, $update_query)){
            $message = "<div class='p-6 text-emerald-800 bg-emerald-50 rounded-3xl border border-emerald-100 font-black text-center italic'>✓ MOT DE PASSE MIS À JOUR !</div>";
            header("Refresh: 3; url=login.php");
            $show_form = false;
        } else {
            $message = "<div class='p-4 mb-4 text-red-800 bg-red-50 rounded-2xl font-bold text-center'>Erreur serveur.</div>";
        }
    }
}
?>
<input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe | PsySpace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,700;1,400&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-serif { font-family: 'Merriweather', serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-5xl flex rounded-2xl shadow-xl overflow-hidden border border-slate-200 bg-white">

        <!-- Bloc gauche -->
        <div class="hidden md:flex w-5/12 bg-blue-950 p-12 flex-col justify-between text-white">
            <div>
                <a href="index.php" class="flex items-center gap-3 mb-16">
                    <img src="assets/images/logo.png" alt="PsySpace" class="h-8 w-auto">
                    <span class="text-lg font-bold">PsySpace</span>
                </a>

                <h1 class="font-serif text-3xl font-bold leading-snug mb-4">
                    Choisissez un<br>
                    <em class="text-blue-300">nouveau mot de passe.</em>
                </h1>

                <p class="text-sm text-blue-200/70 leading-relaxed">
                    Pour sécuriser votre compte, utilisez un mot de passe fort, unique et facile à conserver dans votre gestionnaire.
                </p>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-2xl p-5">
                <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-1">Recommandation</p>
                <p class="text-sm text-white/80">Au moins 8 caractères, avec une combinaison sûre.</p>
            </div>
        </div>

        <!-- Bloc droit -->
        <div class="w-full md:w-7/12 p-10 md:p-14">
            <div class="mb-8">
                <h2 class="font-serif text-3xl font-bold text-slate-900 mb-2">Nouveau mot de passe</h2>
                <p class="text-sm text-slate-400">Définissez un nouveau mot de passe pour votre compte.</p>
            </div>

            <?php echo $message; ?>

            <?php if($show_form): ?>
            <form method="POST" class="space-y-5 mt-6">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">
                        Nouveau mot de passe
                    </label>
                    <input 
                        type="password" 
                        name="pass1" 
                        required
                        placeholder="••••••••"
                        class="w-full px-4 py-3.5 border border-slate-200 rounded-xl bg-slate-50 text-slate-900 outline-none focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-100 transition-all"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">
                        Confirmer le mot de passe
                    </label>
                    <input 
                        type="password" 
                        name="pass2" 
                        required
                        placeholder="••••••••"
                        class="w-full px-4 py-3.5 border border-slate-200 rounded-xl bg-slate-50 text-slate-900 outline-none focus:border-blue-600 focus:bg-white focus:ring-4 focus:ring-blue-100 transition-all"
                    >
                </div>

                <button 
                    type="submit" 
                    name="update-password"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3.5 rounded-xl transition-all hover:-translate-y-0.5 shadow-sm shadow-blue-100"
                >
                    Valider le changement
                </button>
            </form>
            <?php endif; ?>

            <div class="mt-8 pt-6 border-t border-slate-100">
                <a href="login.php" class="text-sm text-slate-500 hover:text-blue-600 transition-colors inline-flex items-center gap-1">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                    Retour à la connexion
                </a>
            </div>
        </div>

    </div>

</body>
</html>
