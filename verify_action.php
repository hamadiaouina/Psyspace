<?php
// --- 1. CONFIGURATION SÉCURISÉE DES SESSIONS ---
ini_set('session.cookie_httponly', '1'); 
ini_set('session.cookie_secure', '1');   
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();
require_once "connection.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: register.php");
    exit();
}

// --- 2. RÉCUPÉRATION DE L'EMAIL (Anti-Falsification) ---
// On utilise la session au lieu du POST. Ainsi, un hacker ne peut pas 
// changer l'email dans le code HTML (Inspecter l'élément) pour valider le compte d'un autre.
if (!isset($_SESSION['pending_email'])) {
    header("Location: register.php");
    exit();
}

$email = $_SESSION['pending_email'];
$user_otp = trim($_POST['otp'] ?? '');

// --- 3. ANTI BRUTE-FORCE SUR LE CODE OTP ---
if (!isset($_SESSION['register_otp_attempts'])) {
    $_SESSION['register_otp_attempts'] = 0;
}
$_SESSION['register_otp_attempts']++;

if ($_SESSION['register_otp_attempts'] > 5) {
    // Plus de 5 tentatives fausses : on détruit le compte "pending" par sécurité extrême
    if (!isset($con)) { $con = $conn ?? null; }
    $stmt_del = $con->prepare("DELETE FROM doctor WHERE docemail = ? AND status = 'pending'");
    $stmt_del->bind_param("s", $email);
    $stmt_del->execute();
    
    unset($_SESSION['pending_email']);
    unset($_SESSION['register_otp_attempts']);
    
    // On le renvoie à l'inscription
    header("Location: register.php?error=spam_protection");
    exit();
}

if (empty($user_otp)) {
    header("Location: verify.php?error=empty");
    exit();
}

if (!isset($con)) { $con = $conn ?? null; }

// --- 4. VÉRIFICATION DU CODE (Limité à 5 minutes) ---
$stmt = $con->prepare("SELECT docid, docname, docemail FROM doctor WHERE docemail = ? AND otp_code = ? AND status = 'pending' AND created_at >= NOW() - INTERVAL 5 MINUTE");
$stmt->bind_param("ss", $email, $user_otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $doctor = $result->fetch_assoc();

    // --- 5. SUCCÈS : ACTIVATION DU COMPTE ---
    $update_stmt = $con->prepare("UPDATE doctor SET status = 'active', otp_code = NULL WHERE docemail = ?");
    $update_stmt->bind_param("s", $email);
    
    if ($update_stmt->execute()) {
        
        // 6. INITIALISATION DE LA SESSION SÉCURISÉE 
        // (Alignée exactement sur login_action.php pour que welcome.php fonctionne)
        session_regenerate_id(true); // Anti Session-Fixation
        
        $_SESSION['id'] = $doctor['docid'];
        $_SESSION['nom'] = $doctor['docname'];
        $_SESSION['role'] = 'doctor';
        $_SESSION['last_login'] = time(); 
        
        // Empreinte Digitale (Anti Session-Hijacking)
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // Nettoyage de la mémoire temporaire
        unset($_SESSION['pending_email']);
        unset($_SESSION['register_otp_attempts']);

        // Redirection vers le tableau de bord
        header("Location: welcome.php");
        exit();
        
    } else {
        header("Location: verify.php?error=dbfail");
        exit();
    }

} else {
    // Si on ne trouve rien, c'est soit le mauvais code, soit les 5 min sont passées
    // On ne renvoie plus l'email dans l'URL !
    header("Location: verify.php?error=expired_or_wrong");
    exit();
}