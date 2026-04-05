<?php
// --- 1. CONFIGURATION DE SÉCURITÉ ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.use_only_cookies', '1');

session_start();

// --- 2. VIDER LES DONNÉES DE SESSION ---
$_SESSION = array();

// --- 3. DÉTRUIRE LE COOKIE DE SESSION (CRITIQUE) ---
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// --- 4. DÉTRUIRE LA SESSION CÔTÉ SERVEUR ---
session_destroy();

// --- 5. REDIRECTION PROPRE VERS LE LOGIN ---
// Le paramètre ?logout=success permettra d'afficher un beau message sur la page de connexion
header("Location: login.php?logout=success");
exit();
?>