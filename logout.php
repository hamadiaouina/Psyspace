<?php
session_start();

// 1. On vide le tableau de session côté serveur
$_SESSION = array();

// 2. SÉCURITÉ : On détruit le cookie de session côté client (Navigateur)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. On détruit définitivement la session serveur
session_destroy();

// 4. Redirection propre
header("Location: goodbye.php"); // Ou index.php selon ce que tu préfères
exit();
?>