<?php
// Configuration sécurisée des sessions AVANT le session_start
ini_set('session.cookie_httponly', '1'); 
ini_set('session.cookie_secure', '1');   
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

require_once 'config/db.php'; 

// Sécurité : Uniquement du POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

/**
 * --- 1) Validation Cloudflare Turnstile ---
 */
$turnstileSecret = getenv('TURNSTILE_SECRET') ?: 'TA_CLE_SECRETE_DE_TEST';
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
    exit();
}

try {
    /**
     * --- 3) Test DOCTOR UNIQUEMENT ---
     */
    // On récupère aussi le 'status' pour vérifier si le compte est actif
    $stmtDoc = $pdo->prepare("SELECT docid, docname, docpassword, status FROM doctor WHERE docemail = ? LIMIT 1");
    $stmtDoc->execute([$email]);
    $doctor = $stmtDoc->fetch();

    if ($doctor && password_verify($password, $doctor['docpassword'])) {
        
        // Vérification du statut du compte
        if ($doctor['status'] === 'suspended') {
            header("Location: login.php?error=suspended");
            exit();
        }
        if ($doctor['status'] === 'pending') {
            header("Location: login.php?error=pending");
            exit();
        }

        // PROTECTION : Change l'ID de session après connexion
        session_regenerate_id(true);
        
        $_SESSION['id'] = $doctor['docid'];
        $_SESSION['nom'] = $doctor['docname'];
        $_SESSION['role'] = 'doctor';
        $_SESSION['last_login'] = time(); 
        
        // Redirection vers l'espace docteur
        header("Location: welcome.php");
        exit();
    }

    // Si on arrive ici, c'est que soit l'email n'existe pas chez les docteurs, 
    // soit le mot de passe est faux. (L'admin n'est pas testé donc il échouera ici).
    header("Location: login.php?error=wrongpw&email=" . urlencode($email));
    exit();

} catch (PDOException $e) {
    error_log("Erreur PsySpace Auth : " . $e->getMessage());
    header("Location: login.php?error=server");
    exit();
}