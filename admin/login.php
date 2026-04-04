<?php
session_start();

// 1. Récupération du token depuis l'.env (ou variable d'environnement Cloud)
$admin_token = getenv('ADMIN_BADGE_TOKEN');

// 2. Sécurité : Si le cookie n'existe pas ou ne correspond pas au token de l'.env
if (!isset($_COOKIE['admin_secret_device']) || $_COOKIE['admin_secret_device'] !== $admin_token) {
    // Redirection flash vers l'accueil si l'appareil n'est pas "badgé"
    header("Location: ../index.php"); 
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="/assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PsySpace | Administration</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #1e293b; padding: 40px; border-radius: 12px; width: 100%; max-width: 350px; border: 1px solid #334155; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        h2 { text-align: center; color: #6366f1; margin-bottom: 24px; }
        input { width: 100%; padding: 12px; margin: 10px 0; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; outline: none; }
        input:focus { border-color: #6366f1; }
        button { width: 100%; background: #4f46e5; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: background 0.2s; margin-top: 10px; }
        button:hover { background: #4338ca; }
        .btn-back { display: block; text-align: center; margin-top: 20px; color: #94a3b8; text-decoration: none; font-size: 14px; transition: color 0.2s; }
        .btn-back:hover { color: white; }
    </style>
</head>
<body>
    <div class="box">
        <h2>PsySpace Admin</h2>
        <form action="login_action.php" method="POST">
            <input type="email" name="email" placeholder="Email Admin" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
        <a href="../index.php" class="btn-back">← Retour à l'accueil</a>
    </div>
</body>
</html>