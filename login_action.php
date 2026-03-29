<?php
// Configuration sécurisée des sessions AVANT le session_start
ini_set('session.cookie_httponly', 1); // Empêche le vol via XSS
ini_set('session.cookie_secure', 1);   // Uniquement via HTTPS
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

require_once 'config/db.php'; 

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

/**
 * --- 1) Validation Cloudflare Turnstile ---
 */
$turnstileSecret = getenv('TURNSTILE_SECRET') ?: 'TA_CLE_SECRETE_DE_TEST'; // Prévoyance si l'env n'est pas chargé
$turnstileToken  = $_POST['cf-turnstile-response'] ?? '';

if (empty($turnstileToken)) {
    header("Location: login.php?error=captcha");
    exit();
}

$ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'secret'   => $turnstileSecret,
    'response' => $turnstileToken,
    'remoteip' => $_SERVER['REMOTE_ADDR']
]));
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response ?: '', true);
if (!($data['success'] ?? false)) {
    header("Location: login.php?error=captcha");
    exit();
}

/**
 * --- 2) Nettoyage des Inputs ---
 */
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || empty($password)) {
    header("Location: login.php?error=empty");
    // Après "header Location login.php?error=wrongpw"
    logAction($con, 0, 'login_failed', "Failed attempt for: $email");
    exit();
}

try {
    /**
     * --- 3) Test DOCTOR ---
     */
    $stmtDoc = $pdo->prepare("SELECT docid, docname, docpassword FROM doctor WHERE docemail = ? LIMIT 1");
    $stmtDoc->execute([$email]);
    $doctor = $stmtDoc->fetch();

    if ($doctor && password_verify($password, $doctor['docpassword'])) {
        // PROTECTION CRITIQUE : Change l'ID de session après connexion
        session_regenerate_id(true);
        
        $_SESSION['id'] = $doctor['docid'];
        $_SESSION['nom'] = $doctor['docname'];
        $_SESSION['role'] = 'doctor';
        $_SESSION['last_login'] = time(); // Pour gérer l'expiration plus tard
        
        header("Location: welcome.php");
        exit();
    }

    /**
     * --- 4) Test ADMIN ---
     */
    $stmtAdmin = $pdo->prepare("SELECT admid, admpassword FROM admin WHERE admemail = ? LIMIT 1");
    $stmtAdmin->execute([$email]);
    $admin = $stmtAdmin->fetch();

    if ($admin && password_verify($password, $admin['admpassword'])) {
        session_regenerate_id(true);
        
        $_SESSION['admin_id'] = $admin['admid'];
        $_SESSION['role'] = 'admin';
        
        header("Location: admin/dashboard_admin.php");
        exit();
    }

    // Si échec, on renvoie l'email pour le confort de l'utilisateur (déjà sécurisé par ton htmlspecialchars dans login.php)
    header("Location: login.php?error=wrongpw&email=" . urlencode($email));
    exit();

} catch (PDOException $e) {
    // On ne montre jamais l'erreur SQL brute à l'utilisateur
    error_log("Erreur PsySpace Auth : " . $e->getMessage());
    header("Location: login.php?error=server");
    exit();

}