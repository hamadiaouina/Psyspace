<?php
session_start();

// 1. Récupération intelligente de l'IP (Spécial Azure)
$user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (strpos($user_ip, ',') !== false) {
    $user_ip = trim(explode(',', $user_ip)[0]);
}
if (strpos($user_ip, ':') !== false && strpos($user_ip, '.') !== false) {
    $user_ip = explode(':', $user_ip)[0];
}
$user_ip = trim($user_ip);

// 2. Récupération de l'IP autorisée (Depuis Azure ou .env)
$allowed_ip = getenv('ALLOWED_ADMIN_IP'); 
if (!$allowed_ip) {
    $allowed_ip = $_SERVER['ALLOWED_ADMIN_IP'] ?? null;
}

// 3. Vérification
if (empty($allowed_ip) || $user_ip !== trim($allowed_ip)) {
    // Si l'IP ne match pas, on dégage vers l'accueil
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