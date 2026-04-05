<?php
session_start();

// 1. On vide toutes les variables de session
$_SESSION = array();

// 2. SÉCURITÉ : On détruit le cookie de session dans le navigateur de l'admin
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. On détruit la session côté serveur
session_destroy();

// 4. Redirection propre avec le bon slash "/" (et un petit message de succès optionnel)
header("Location: ../index.php"); 
// (Ou "../login.php?logout=success" si tu préfères le renvoyer sur la page de connexion médecin)
exit();
?>