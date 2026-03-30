<?php
session_start();

// Fonction pour lire le .env (si tu n'as pas déjà un chargeur de bibliothèque)
function getEnvValue($key) {
    $path = __DIR__ . '/../.env'; // Ajuste le chemin vers ton fichier .env
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            if (trim($name) == $key) return trim($value);
        }
    }
    return null;
}

// 1. Récupérer l'IP autorisée depuis le .env
$ip_autorisee = getEnvValue('ALLOWED_ADMIN_IP');
$ip_visiteur = $_SERVER['REMOTE_ADDR'];

// 2. Vérification stricte
if ($ip_visiteur !== $ip_autorisee) {
    // On redirige vers l'accueil pour que personne ne sache que la page existe
    header("Location: ../index.php"); 
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <title>PsySpace | Administration</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #1e293b; padding: 40px; border-radius: 12px; width: 100%; max-width: 350px; border: 1px solid #334155; }
        h2 { text-align: center; color: #4f46e5; }
        input { width: 100%; padding: 10px; margin: 10px 0; border-radius: 6px; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; }
        button { width: 100%; background: #4f46e5; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; font-weight: bold; }
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
    </div>
</body>
</html>